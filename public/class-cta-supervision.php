<?php
/**
 * Supervision booking shortcode and AJAX handlers.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Supervision
 */
if ( ! class_exists( 'CTA_Supervision' ) ) {

class CTA_Supervision {

	/** @var int BBS max group size — hardcoded, not user-editable. */
	const GROUP_SEATS_MAX = 8;

	/** @var int Group session duration in minutes. */
	const GROUP_DURATION_MINS = 120;

	/** @var int Individual session duration in minutes. */
	const INDIVIDUAL_DURATION_MINS = 60;

	/**
	 * Register shortcode and AJAX handlers.
	 */
	public function __construct() {
		add_shortcode( 'cta_supervision_booking', array( $this, 'render_supervision' ) );

		add_action( 'wp_ajax_cta_book_session', array( $this, 'ajax_book_session' ) );
		add_action( 'wp_ajax_cta_cancel_booking', array( $this, 'ajax_cancel_booking' ) );
	}

	/**
	 * Render supervision booking shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_supervision( $atts ) {
		global $wpdb;

		$is_logged_in = is_user_logged_in();
		$user_status  = 'guest';
		$user_bookings = array();

		if ( $is_logged_in ) {
			$user_id     = get_current_user_id();
			$meta_status = CTA_Associate_Access::get_supervision_status( $user_id );
			$approval    = CTA_Associate_Access::get_approval_status( $user_id );

			if ( ! CTA_Associate_Access::can_access_supervision_features( $user_id ) ) {
				if ( CTA_Associate_Access::STATUS_REJECTED === $approval || 'rejected' === $meta_status ) {
					$user_status = 'rejected';
				} elseif ( CTA_Associate_Access::is_approved_awaiting_plan( $user_id ) ) {
					$user_status = 'awaiting_plan';
				} elseif ( CTA_Associate_Access::is_supervision_pending( $user_id ) || 'pending_approval' === $meta_status ) {
					$user_status = 'pending_approval';
				} elseif ( 'locked' === $meta_status || 'past_due' === $meta_status ) {
					$user_status = 'locked';
				} else {
					$user_status = 'inactive';
				}
			} else {
				$user_status = 'active';
			}

			// Only expose existing bookings once supervision access is fully approved.
			if ( 'active' === $user_status ) {
				$today    = cta_lms_current_date( 'Y-m-d' );
				$bookings = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT session_date, session_time, session_type, id
						FROM {$wpdb->prefix}cta_bookings
						WHERE user_id = %d
						AND status = 'confirmed'
						AND session_date >= %s",
						$user_id,
						$today
					)
				);

				foreach ( $bookings as $booking ) {
					$key = $this->get_session_key( $booking->session_date, $booking->session_time, $booking->session_type );
					$user_bookings[ $key ] = (int) $booking->id;
				}
			}
		}

		$today    = cta_lms_current_date( 'Y-m-d' );
		$sessions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *
				FROM {$wpdb->prefix}cta_bookings
				WHERE user_id = 0
				AND status = 'open'
				AND seats_booked < seats_total
				AND session_date >= %s
				ORDER BY session_date ASC, session_time ASC",
				$today
			)
		);

		foreach ( $sessions as $session ) {
			if ( 'group' === $session->session_type ) {
				$session->seats_total   = self::GROUP_SEATS_MAX;
				$session->duration_mins = self::GROUP_DURATION_MINS;
			} else {
				$session->duration_mins = self::INDIVIDUAL_DURATION_MINS;
			}
		}

		$stripe              = cta_get_stripe();
		$monthly_price       = CTA_Supervision_Plans::get_group_price();
		$individual_price    = (float) get_option( 'cta_individual_session_price', 120.0 );
		$login_url           = $this->get_login_url();
		$register_url        = CTA_Associate_Access::get_associate_registration_url();
		$can_purchase_supervision = ! $is_logged_in || CTA_Associate_Access::can_purchase_supervision();
		$calendar_month      = cta_lms_current_date( 'Y-m-01' );
		$session_dates       = array();

		foreach ( $sessions as $session ) {
			$session_dates[] = $session->session_date;
		}

		$session_dates = array_unique( $session_dates );
		$supervision   = $this;

		ob_start();
		include CTA_PLUGIN_DIR . 'templates/supervision.php';
		return ob_get_clean();
	}

	/**
	 * Whether a group/individual slot still has an open seat.
	 *
	 * Pure helper for tests and booking checks.
	 *
	 * @param int $seats_booked Seats already taken.
	 * @param int $seats_total  Capacity (8 group / 1 individual).
	 * @return bool
	 */
	public static function evaluate_has_open_seat( $seats_booked, $seats_total ) {
		$seats_booked = (int) $seats_booked;
		$seats_total  = max( 1, (int) $seats_total );

		return $seats_booked < $seats_total;
	}

	/**
	 * Capacity for a session type (BBS group max = 8; individual = 1).
	 *
	 * @param string $session_type group|individual.
	 * @return int
	 */
	public static function get_capacity_for_type( $session_type ) {
		return 'group' === $session_type ? self::GROUP_SEATS_MAX : 1;
	}

	/**
	 * Duration in minutes for a session type.
	 *
	 * @param string $session_type group|individual.
	 * @return int
	 */
	public static function get_duration_for_type( $session_type ) {
		return 'group' === $session_type ? self::GROUP_DURATION_MINS : self::INDIVIDUAL_DURATION_MINS;
	}

	/**
	 * Whether two session intervals overlap (same calendar day in session timezone).
	 *
	 * @param string $date_a Session A date Y-m-d.
	 * @param string $time_a Session A start H:i:s or H:i.
	 * @param int    $dur_a  Duration A minutes.
	 * @param string $date_b Session B date Y-m-d.
	 * @param string $time_b Session B start.
	 * @param int    $dur_b  Duration B minutes.
	 * @return bool
	 */
	public static function sessions_overlap( $date_a, $time_a, $dur_a, $date_b, $time_b, $dur_b ) {
		if ( (string) $date_a !== (string) $date_b ) {
			return false;
		}

		$start_a = self::time_to_minutes( $time_a );
		$start_b = self::time_to_minutes( $time_b );

		if ( null === $start_a || null === $start_b ) {
			return false;
		}

		$end_a = $start_a + max( 1, (int) $dur_a );
		$end_b = $start_b + max( 1, (int) $dur_b );

		return $start_a < $end_b && $start_b < $end_a;
	}

	/**
	 * Convert a time string to minutes from midnight.
	 *
	 * @param string $time H:i:s or H:i.
	 * @return int|null
	 */
	public static function time_to_minutes( $time ) {
		$time = trim( (string) $time );

		if ( ! preg_match( '/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $time, $m ) ) {
			return null;
		}

		$hour = (int) $m[1];
		$min  = (int) $m[2];

		if ( $hour > 23 || $min > 59 ) {
			return null;
		}

		return ( $hour * 60 ) + $min;
	}

	/**
	 * Whether a Join Session / meeting link may be shown.
	 *
	 * Requires: meeting URL present, viewer owns a confirmed booking for that session,
	 * and full supervision access (approved + plan + active).
	 *
	 * @param bool $has_meeting_url           Slot has a meeting URL.
	 * @param bool $is_own_confirmed_booking  Booking belongs to viewer and is confirmed.
	 * @param bool $has_supervision_access    can_access_meeting_links / features.
	 * @return bool
	 */
	public static function evaluate_can_join_meeting( $has_meeting_url, $is_own_confirmed_booking, $has_supervision_access ) {
		return (bool) $has_meeting_url && (bool) $is_own_confirmed_booking && (bool) $has_supervision_access;
	}

	/**
	 * AJAX: book a supervision session.
	 */
	public function ajax_book_session() {
		check_ajax_referer( 'cta_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please log in to book a session.', 'cta-lms' ),
				)
			);
		}

		$user_id = get_current_user_id();

		// Backend gate: associate + approved + qualifying plan + active (not UI-only).
		CTA_Associate_Access::require_supervision_access( $user_id );

		$status = (string) get_user_meta( $user_id, 'cta_supervision_status', true );

		if ( 'active' !== $status ) {
			wp_send_json_error(
				array(
					'message' => CTA_Associate_Access::get_access_denied_message( $user_id ),
					'code'    => 'supervision_not_active',
				)
			);
		}

		$session_id = absint( wp_unslash( $_POST['session_id'] ?? 0 ) );

		if ( ! $session_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid session selected.', 'cta-lms' ),
				)
			);
		}

		global $wpdb;

		$table = $wpdb->prefix . 'cta_bookings';
		$today = cta_lms_current_date( 'Y-m-d' );

		// Serialize seat claims for this slot (race-safe under concurrent bookers).
		$wpdb->query( 'START TRANSACTION' );

		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE id = %d
				AND user_id = 0
				AND status = 'open'
				AND session_date >= %s
				FOR UPDATE",
				$session_id,
				$today
			)
		);

		if ( ! $session ) {
			$wpdb->query( 'ROLLBACK' );
			wp_send_json_error(
				array(
					'message' => __( 'This session is no longer available.', 'cta-lms' ),
				)
			);
		}

		$session_type  = sanitize_text_field( $session->session_type );
		$duration_mins = self::get_duration_for_type( $session_type );
		$seats_total   = self::get_capacity_for_type( $session_type );

		if ( ! self::evaluate_has_open_seat( (int) $session->seats_booked, $seats_total ) ) {
			$wpdb->query( 'ROLLBACK' );
			wp_send_json_error(
				array(
					'message' => __( 'This session is full.', 'cta-lms' ),
					'code'    => 'session_full',
				)
			);
		}

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table}
				WHERE user_id = %d
				AND session_date = %s
				AND session_time = %s
				AND session_type = %s
				AND status = 'confirmed'
				LIMIT 1",
				$user_id,
				$session->session_date,
				$session->session_time,
				$session_type
			)
		);

		if ( $existing ) {
			$wpdb->query( 'ROLLBACK' );
			wp_send_json_error(
				array(
					'message' => __( 'You have already booked this session.', 'cta-lms' ),
					'code'    => 'duplicate_booking',
				)
			);
		}

		// Block overlapping sessions the same day (e.g. group 2h + individual 1h).
		$day_bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT session_type, session_time, duration_mins FROM {$table}
				WHERE user_id = %d
				AND session_date = %s
				AND status = 'confirmed'",
				$user_id,
				$session->session_date
			)
		);

		foreach ( (array) $day_bookings as $other ) {
			$other_duration = (int) $other->duration_mins;
			if ( $other_duration <= 0 ) {
				$other_duration = self::get_duration_for_type( $other->session_type );
			}

			if ( self::sessions_overlap(
				$session->session_date,
				$session->session_time,
				$duration_mins,
				$session->session_date,
				$other->session_time,
				$other_duration
			) ) {
				$wpdb->query( 'ROLLBACK' );
				wp_send_json_error(
					array(
						'message' => __( 'You already have a supervision session that overlaps this time.', 'cta-lms' ),
						'code'    => 'overlapping_booking',
					)
				);
			}
		}

		$sub_id = (string) get_user_meta( $user_id, 'cta_supervision_subscription_id', true );

		$inserted = $wpdb->insert(
			$table,
			array(
				'user_id'       => $user_id,
				'session_type'  => $session_type,
				'session_date'  => $session->session_date,
				'session_time'  => $session->session_time,
				'duration_mins' => $duration_mins,
				'seats_total'   => 0,
				'seats_booked'  => 0,
				'status'        => 'confirmed',
				'stripe_sub_id' => $sub_id ? $sub_id : null,
				'notes'         => wp_json_encode(
					array(
						'slot_id' => (int) $session->id,
					)
				),
			),
			array(
				'%d',
				'%s',
				'%s',
				'%s',
				'%d',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
			)
		);

		if ( ! $inserted ) {
			$wpdb->query( 'ROLLBACK' );
			wp_send_json_error(
				array(
					'message' => __( 'Unable to complete booking. Please try again.', 'cta-lms' ),
				)
			);
		}

		$booking_id = (int) $wpdb->insert_id;

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET seats_booked = seats_booked + 1,
				seats_total = %d,
				duration_mins = %d
				WHERE id = %d
				AND user_id = 0
				AND status = 'open'
				AND seats_booked < %d",
				$seats_total,
				$duration_mins,
				$session_id,
				$seats_total
			)
		);

		if ( ! $updated ) {
			$wpdb->query( 'ROLLBACK' );
			wp_send_json_error(
				array(
					'message' => __( 'This session just filled up. Please choose another time.', 'cta-lms' ),
					'code'    => 'session_full',
				)
			);
		}

		// Extra duplicate guard after insert (same user, same slot).
		$dup_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
				WHERE user_id = %d
				AND session_date = %s
				AND session_time = %s
				AND session_type = %s
				AND status = 'confirmed'",
				$user_id,
				$session->session_date,
				$session->session_time,
				$session_type
			)
		);

		if ( $dup_count > 1 ) {
			$wpdb->query( 'ROLLBACK' );
			wp_send_json_error(
				array(
					'message' => __( 'You have already booked this session.', 'cta-lms' ),
					'code'    => 'duplicate_booking',
				)
			);
		}

		$wpdb->query( 'COMMIT' );

		$seats_remaining = max( 0, $seats_total - ( (int) $session->seats_booked + 1 ) );

		CTA_Emails::send(
			'booking_confirmation',
			$user_id,
			array(
				'session'      => $session,
				'session_type' => $session_type,
			)
		);

		wp_send_json_success(
			array(
				'message'         => __( 'Session booked successfully!', 'cta-lms' ),
				'booking_id'      => $booking_id,
				'session_id'      => $session_id,
				'seats_remaining' => $seats_remaining,
				'datetime_label'  => $this->format_session_datetime( $session->session_date, $session->session_time ),
				'session_type'    => $session_type,
				'dashboard_url'   => $this->get_supervision_dashboard_url(),
			)
		);
	}

	/**
	 * AJAX: cancel a supervision booking.
	 */
	public function ajax_cancel_booking() {
		check_ajax_referer( 'cta_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please log in to cancel a booking.', 'cta-lms' ),
				)
			);
		}

		$booking_id = absint( wp_unslash( $_POST['booking_id'] ?? 0 ) );
		$user_id    = get_current_user_id();

		CTA_Associate_Access::require_supervision_access( $user_id );

		if ( ! $booking_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid booking.', 'cta-lms' ),
				)
			);
		}

		global $wpdb;

		$table   = $wpdb->prefix . 'cta_bookings';
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE id = %d
				AND user_id = %d
				AND status = 'confirmed'",
				$booking_id,
				$user_id
			)
		);

		if ( ! $booking ) {
			wp_send_json_error(
				array(
					'message' => __( 'Booking not found.', 'cta-lms' ),
				)
			);
		}

		$dt = cta_lms_session_datetime( $booking->session_date, $booking->session_time );

		if ( ! $dt || $dt->getTimestamp() <= ( time() + DAY_IN_SECONDS ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Bookings must be cancelled at least 24 hours before the session.', 'cta-lms' ),
				)
			);
		}

		$wpdb->update(
			$table,
			array( 'status' => 'cancelled' ),
			array( 'id' => $booking_id ),
			array( '%s' ),
			array( '%d' )
		);

		$slot_id = 0;
		$notes   = json_decode( (string) $booking->notes, true );

		if ( is_array( $notes ) && ! empty( $notes['slot_id'] ) ) {
			$slot_id = (int) $notes['slot_id'];
		}

		if ( $slot_id ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table}
					SET seats_booked = GREATEST(0, seats_booked - 1)
					WHERE id = %d
					AND user_id = 0
					AND status = 'open'",
					$slot_id
				)
			);
		} else {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table}
					SET seats_booked = GREATEST(0, seats_booked - 1)
					WHERE user_id = 0
					AND status = 'open'
					AND session_date = %s
					AND session_time = %s
					AND session_type = %s
					AND seats_booked > 0",
					$booking->session_date,
					$booking->session_time,
					$booking->session_type
				)
			);
		}

		wp_send_json_success(
			array(
				'message'    => __( 'Your booking has been cancelled.', 'cta-lms' ),
				'booking_id' => $booking_id,
			)
		);
	}

	/**
	 * Build a unique key for a session slot.
	 *
	 * @param string $date Session date.
	 * @param string $time Session time.
	 * @param string $type Session type.
	 * @return string
	 */
	private function get_session_key( $date, $time, $type ) {
		return $date . '|' . $time . '|' . $type;
	}

	/**
	 * Format session date and time for display.
	 *
	 * @param string $date Session date (Y-m-d).
	 * @param string $time Session time (H:i:s).
	 * @return string
	 */
	public function format_session_datetime( $date, $time ) {
		return cta_lms_format_session_datetime( $date, $time, 'l, F j, Y · g:i A T' );
	}

	/**
	 * Get supervision associate dashboard URL.
	 *
	 * @return string
	 */
	private function get_supervision_dashboard_url() {
		$page_id = absint( get_option( 'cta_supervision_dashboard_page_id', 0 ) );

		if ( ! $page_id && function_exists( 'cta_lms_find_page_id_by_shortcode' ) ) {
			$page_id = cta_lms_find_page_id_by_shortcode( 'cta_supervision_dashboard' );
		}

		if ( $page_id ) {
			$url = get_permalink( $page_id );

			if ( $url ) {
				return $url;
			}
		}

		return '';
	}

	/**
	 * Get login page URL.
	 *
	 * @return string
	 */
	private function get_login_url() {
		$page_id = absint( get_option( 'cta_login_page_id', 0 ) );

		if ( $page_id ) {
			$url = get_permalink( $page_id );
			if ( $url ) {
				return $url;
			}
		}

		return wp_login_url( get_permalink() );
	}
}
}
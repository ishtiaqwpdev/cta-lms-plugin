<?php
/**
 * Supervision associate dashboard.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Supervision_Dashboard
 */
if ( ! class_exists( 'CTA_Supervision_Dashboard' ) ) {

class CTA_Supervision_Dashboard {

	/** @var int Max upload size in bytes (10MB). */
	const MAX_UPLOAD_BYTES = 10485760;

	/** @var array Allowed document MIME types. */
	const ALLOWED_MIMES = array(
		'pdf'  => 'application/pdf',
		'doc'  => 'application/msword',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
	);

	/** @var array Allowed document categories. */
	const DOC_CATEGORIES = array(
		'bbs_agreement'   => 'BBS Supervision Agreement',
		'weekly_log'      => 'Weekly Hours Log',
		'experience_form' => 'Experience Verification Form',
		'other'           => 'Other',
	);

	/**
	 * Register shortcode and AJAX handlers.
	 */
	public function __construct() {
		add_shortcode( 'cta_supervision_dashboard', array( $this, 'render_dashboard' ) );

		add_action( 'wp_ajax_cta_upload_document', array( $this, 'ajax_upload_document' ) );
		add_action( 'wp_ajax_cta_delete_document', array( $this, 'ajax_delete_document' ) );
		add_action( 'wp_ajax_cta_get_portal_url', array( $this, 'ajax_get_portal_url' ) );
		add_action( 'wp_ajax_cta_get_supervision_access_status', array( $this, 'ajax_get_access_status' ) );

		add_filter( 'body_class', array( $this, 'add_body_class' ) );
	}

	/**
	 * AJAX: return the current user's live supervision access state.
	 *
	 * The pending dashboard polls this endpoint so approval takes effect without
	 * requiring logout/login. The response is also useful for access testing.
	 */
	public function ajax_get_access_status() {
		check_ajax_referer( 'cta_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please log in to continue.', 'cta-lms' ),
				)
			);
		}

		$user_id = get_current_user_id();

		wp_send_json_success(
			array(
				'approval_status'    => CTA_Associate_Access::get_approval_status( $user_id ),
				'supervision_status' => CTA_Associate_Access::get_supervision_status( $user_id ),
				'access_granted'     => CTA_Associate_Access::can_access_supervision_features( $user_id ),
				'dashboard_url'      => $this->get_dashboard_url(),
			)
		);
	}

	/**
	 * Add dashboard body class.
	 *
	 * @param array $classes Body classes.
	 * @return array
	 */
	public function add_body_class( $classes ) {
		global $post;

		if ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'cta_supervision_dashboard' ) ) {
			$classes[] = 'dashboard-page';
		}

		return $classes;
	}

	/**
	 * Render supervision dashboard shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_dashboard( $atts ) {
		$redirect = $this->check_associate_access();

		if ( is_string( $redirect ) ) {
			return $redirect;
		}

		global $wpdb;

		$user_id = get_current_user_id();
		$user    = wp_get_current_user();

		// After Stripe Checkout return (or stuck pending payment), activate access now.
		$this->maybe_finalize_returned_subscription( $user_id );

		// Heal completed payments that never received supervision status meta.
		$this->maybe_heal_completed_supervision_purchase( $user_id );

		// Refresh cancel-at-period-end / period dates from Stripe.
		$this->maybe_sync_subscription_from_stripe( $user_id );

		$supervision_status = (string) get_user_meta( $user_id, 'cta_supervision_status', true );
		$subscription_id    = (string) get_user_meta( $user_id, 'cta_supervision_subscription_id', true );
		$supervision_payment = CTA_Database::get_user_supervision_payment( $user_id, 'completed' );
		$has_supervision_purchase = (bool) $supervision_payment;

		if ( ! $has_supervision_purchase && get_user_meta( $user_id, 'cta_hybrid_plan_active', true ) ) {
			$has_supervision_purchase = true;
		}

		// Single resolver keeps name + price aligned (avoids Hybrid name with Group $260).
		$supervision_plan = CTA_Supervision_Plans::resolve_user_plan_slug( $user_id );
		$plan_name_meta   = CTA_Supervision_Plans::get_name( $supervision_plan );

		// Persist healed meta so admin/emails/popups stay consistent.
		$stored_slug = (string) get_user_meta( $user_id, 'cta_supervision_plan', true );
		$stored_name = (string) get_user_meta( $user_id, 'cta_supervision_plan_name', true );
		if ( $has_supervision_purchase || '' !== $stored_slug || '' !== $stored_name ) {
			if ( $stored_slug !== $supervision_plan ) {
				update_user_meta( $user_id, 'cta_supervision_plan', $supervision_plan );
			}
			if ( $stored_name !== $plan_name_meta ) {
				update_user_meta( $user_id, 'cta_supervision_plan_name', $plan_name_meta );
			}
		}

		$is_active         = ( 'active' === $supervision_status );
		$is_locked         = in_array( $supervision_status, array( 'locked', 'past_due' ), true );
		$is_pending_plan   = ( 'pending_approval' === $supervision_status );
		$approval_status   = CTA_Associate_Access::get_approval_status( $user_id );
		$can_access_supervision = CTA_Associate_Access::can_access_supervision_features( $user_id );
		$is_supervision_pending = CTA_Associate_Access::is_supervision_pending( $user_id );
		$is_approved_awaiting_plan = CTA_Associate_Access::is_approved_awaiting_plan( $user_id );
		$is_pending_approval    = ( ! $is_approved_awaiting_plan ) && (
			$is_supervision_pending || ( CTA_Associate_Access::is_associate( $user_id ) && ! CTA_Associate_Access::is_approved( $user_id ) )
		);

		// Paid / pending purchase should never fall through to "No active plan".
		$no_plan = ! $is_active && ! $is_locked && ! $is_pending_plan && ! $has_supervision_purchase && ! $is_pending_approval && ! $is_approved_awaiting_plan;

		if ( CTA_Associate_Access::STATUS_PENDING === $approval_status || $is_pending_plan || $is_supervision_pending ) {
			$onboarding_status_label = __( 'Pending Approval', 'cta-lms' );
			$onboarding_status_class = 'badge--warning';
			$onboarding_message      = CTA_Associate_Access::get_pending_message();
		} elseif ( $is_approved_awaiting_plan ) {
			$onboarding_status_label = __( 'Approved — Awaiting Plan', 'cta-lms' );
			$onboarding_status_class = 'badge--warning';
			$onboarding_message      = CTA_Associate_Access::get_approved_awaiting_plan_message();
		} elseif ( $is_active && $can_access_supervision ) {
			$onboarding_status_label = __( 'Approved', 'cta-lms' );
			$onboarding_status_class = 'badge--success';
			$onboarding_message      = __( 'Your supervision application has been approved. You can now access supervision services.', 'cta-lms' );
		} elseif ( $is_locked ) {
			$onboarding_status_label = __( 'Payment Past Due', 'cta-lms' );
			$onboarding_status_class = 'badge--danger';
			$onboarding_message      = __( 'Your last subscription payment failed. Use Manage Subscription to update your payment method and restore access.', 'cta-lms' );
		} else {
			$onboarding_status_label = '';
			$onboarding_status_class = '';
			$onboarding_message      = '';
		}

		$can_access_booking               = $can_access_supervision;
		$can_access_meeting_links         = $can_access_supervision;
		$can_access_supervision_resources = $can_access_supervision;
		$pending_approval_message         = CTA_Associate_Access::get_access_denied_message( $user_id );

		$upcoming_sessions = array();
		$session_history   = array();
		$documents         = array();
		$today             = cta_lms_current_date( 'Y-m-d' );

		// Never load sessions / materials until supervision access is fully approved.
		if ( $can_access_supervision ) {
			$upcoming_sessions = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}cta_bookings
					WHERE user_id = %d
					AND status = 'confirmed'
					AND session_date >= %s
					ORDER BY session_date ASC, session_time ASC",
					$user_id,
					$today
				)
			);

			foreach ( $upcoming_sessions as $index => $booking ) {
				$upcoming_sessions[ $index ] = $this->enrich_booking( $booking );
			}

			$session_history = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}cta_bookings
					WHERE user_id = %d
					AND (
						session_date < %s
						OR status IN ('cancelled', 'completed')
					)
					ORDER BY session_date DESC, session_time DESC
					LIMIT 10",
					$user_id,
					$today
				)
			);

			$documents = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}cta_documents
					WHERE user_id = %d
					ORDER BY uploaded_at DESC",
					$user_id
				)
			);
		}

		$monthly_price       = CTA_Supervision_Plans::get_price( $supervision_plan );
		$individual_price    = (float) get_option( 'cta_individual_session_price', 120.0 );
		$next_billing_date   = $this->get_subscription_billing_label( $user_id );
		$next_session_label  = $this->get_next_session_label( $upcoming_sessions );
		$plan_label          = $this->get_plan_label( $supervision_plan );
		$associate_number    = (string) get_user_meta( $user_id, 'cta_associate_number', true );
		$dashboard_url       = $this->get_dashboard_url();
		$supervision_url     = $this->get_supervision_page_url();
		$logout_url          = wp_logout_url( $dashboard_url ? $dashboard_url : home_url( '/' ) );
		$home_url            = home_url( '/' );
		$dashboard_user      = $this->get_dashboard_user_data( $user, $associate_number );
		$document_categories = self::DOC_CATEGORIES;
		$dashboard           = $this;
		$show_renew          = empty( $supervision_status ) || ( 'active' !== $supervision_status && 'pending_approval' !== $supervision_status );
		$support_email       = (string) get_option( 'cta_support_email', '' );

		if ( '' === $support_email ) {
			$support_email = (string) get_option( 'admin_email', 'support@clinicaltrainingacademy.com' );
		}

		ob_start();
		include CTA_PLUGIN_DIR . 'templates/dashboard-supervision.php';
		return ob_get_clean();
	}

	/**
	 * AJAX: upload a supervision document.
	 */
	public function ajax_upload_document() {
		check_ajax_referer( 'cta_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to upload documents.', 'cta-lms' ) ) );
		}

		CTA_Associate_Access::require_supervision_access();

		if ( ! $this->user_can_upload_documents() ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to upload documents.', 'cta-lms' ) ) );
		}

		if ( empty( $_FILES['document_file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file was uploaded.', 'cta-lms' ) ) );
		}

		$category_key = sanitize_text_field( wp_unslash( $_POST['doc_category'] ?? 'other' ) );

		if ( ! isset( self::DOC_CATEGORIES[ $category_key ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid document category.', 'cta-lms' ) ) );
		}

		$file = $_FILES['document_file'];

		if ( ! empty( $file['error'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Upload failed. Please try again.', 'cta-lms' ) ) );
		}

		if ( (int) $file['size'] > self::MAX_UPLOAD_BYTES ) {
			wp_send_json_error( array( 'message' => __( 'File exceeds the 10MB limit.', 'cta-lms' ) ) );
		}

		$checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], self::ALLOWED_MIMES );

		if ( empty( $checked['ext'] ) || ! isset( self::ALLOWED_MIMES[ $checked['ext'] ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Only PDF, DOC, and DOCX files are allowed.', 'cta-lms' ) ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$upload = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => self::ALLOWED_MIMES,
			)
		);

		if ( ! empty( $upload['error'] ) ) {
			wp_send_json_error( array( 'message' => esc_html( $upload['error'] ) ) );
		}

		global $wpdb;

		$user_id   = get_current_user_id();
		$file_name = sanitize_file_name( wp_basename( $file['name'] ) );

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'cta_documents',
			array(
				'user_id'       => $user_id,
				'file_name'     => $file_name,
				'file_url'      => esc_url_raw( $upload['url'] ),
				'file_type'     => $checked['ext'],
				'file_size'     => (int) $file['size'],
				'doc_category'  => $category_key,
				'review_status' => 'pending',
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			wp_send_json_error( array( 'message' => __( 'Unable to save document record.', 'cta-lms' ) ) );
		}

		$document = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}cta_documents WHERE id = %d",
				(int) $wpdb->insert_id
			)
		);

		wp_send_json_success(
			array(
				'message' => __( 'Document uploaded successfully.', 'cta-lms' ),
				'html'    => $this->render_document_row_html( $document ),
			)
		);
	}

	/**
	 * AJAX: delete a pending document.
	 */
	public function ajax_delete_document() {
		check_ajax_referer( 'cta_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to continue.', 'cta-lms' ) ) );
		}

		CTA_Associate_Access::require_supervision_access();

		$document_id = absint( wp_unslash( $_POST['document_id'] ?? 0 ) );
		$user_id     = get_current_user_id();

		global $wpdb;

		$document = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}cta_documents
				WHERE id = %d AND user_id = %d",
				$document_id,
				$user_id
			)
		);

		if ( ! $document ) {
			wp_send_json_error( array( 'message' => __( 'Document not found.', 'cta-lms' ) ) );
		}

		if ( 'pending' !== $document->review_status ) {
			wp_send_json_error( array( 'message' => __( 'Reviewed documents cannot be deleted.', 'cta-lms' ) ) );
		}

		$this->delete_document_file( $document->file_url );

		$wpdb->delete(
			$wpdb->prefix . 'cta_documents',
			array( 'id' => $document_id ),
			array( '%d' )
		);

		wp_send_json_success(
			array(
				'message'     => __( 'Document deleted.', 'cta-lms' ),
				'document_id' => $document_id,
			)
		);
	}

	/**
	 * AJAX: create Stripe Customer Portal session.
	 */
	public function ajax_get_portal_url() {
		check_ajax_referer( 'cta_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to continue.', 'cta-lms' ) ) );
		}

		$user_id = get_current_user_id();
		$stripe  = cta_get_stripe();
		$status  = (string) get_user_meta( $user_id, 'cta_supervision_status', true );

		$return_url = $this->get_dashboard_url();
		if ( ! $return_url ) {
			$return_url = home_url( '/' );
		}
		$return_url = add_query_arg( 'cta_billing', 'returned', $return_url );

		$supervision_plan = CTA_Supervision_Plans::resolve_user_plan_slug( $user_id );
		$show_renew       = empty( $status ) || ! in_array( $status, array( 'active', 'pending_approval', 'locked', 'past_due' ), true );
		$renew_url        = $this->get_supervision_page_url();
		$support_email    = (string) get_option( 'cta_support_email', '' );

		if ( '' === $support_email ) {
			$support_email = (string) get_option( 'admin_email', 'support@clinicaltrainingacademy.com' );
		}

		$bypass_on         = CTA_Stripe::is_payments_bypass_enabled();
		$stripe_configured = ( $stripe && $stripe->is_configured() );

		$fallback = array(
			'demo_mode'         => true,
			'stripe_configured' => ( $stripe_configured && ! $bypass_on ),
			'payments_bypass'   => $bypass_on,
			'plan_name'         => CTA_Supervision_Plans::get_name( $supervision_plan ),
			'status'            => $status ? $status : 'none',
			'show_renew'        => $show_renew,
			'renew_url'         => $renew_url ? esc_url_raw( $renew_url ) : '',
			'price'             => CTA_Supervision_Plans::get_price_label( $supervision_plan ),
			'next_billing'      => $this->get_subscription_billing_label( $user_id ),
			'support_email'     => sanitize_email( $support_email ),
		);

		// Testing Mode (payment bypass) intentionally blocks the real Stripe portal.
		if ( $bypass_on ) {
			$fallback['reason']  = 'payments_bypass';
			$fallback['message'] = __( 'Testing Mode is enabled in CTA LMS settings, so Stripe Checkout and the Customer Billing Portal are skipped. Turn off "Skip payments" (Testing Mode), keep your Stripe test API keys, then click Manage Subscription again.', 'cta-lms' );
			wp_send_json_success( $fallback );
		}

		if ( ! $stripe || ! $stripe_configured ) {
			$fallback['reason']  = 'stripe_not_configured';
			$fallback['message'] = __( 'Stripe is not configured. Add your Stripe API keys in CTA LMS → Settings, then try again.', 'cta-lms' );
			wp_send_json_success( $fallback );
		}

		$result = $stripe->create_billing_portal_session( $user_id, $return_url );

		if ( ! is_wp_error( $result ) ) {
			wp_send_json_success(
				array(
					'portal_url' => esc_url_raw( $result ),
				)
			);
		}

		$code = $result->get_error_code();

		// No Stripe customer yet — show renew / support fallback instead of a hard error.
		if ( 'no_customer' === $code ) {
			$fallback['reason']  = 'no_customer';
			$fallback['message'] = $result->get_error_message();
			wp_send_json_success( $fallback );
		}

		wp_send_json_error(
			array(
				'message' => $result->get_error_message(),
				'code'    => $code,
			)
		);
	}

	/**
	 * Human-readable next billing / access-through label for the current user.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private function get_subscription_billing_label( $user_id ) {
		$subscription_id = (string) get_user_meta( $user_id, 'cta_supervision_subscription_id', true );
		$cancel_pending  = '1' === (string) get_user_meta( $user_id, 'cta_supervision_cancel_at_period_end', true );
		$period_end      = absint( get_user_meta( $user_id, 'cta_supervision_period_end', true ) );

		$next = $this->get_next_billing_date( $subscription_id );

		if ( $cancel_pending && $period_end > 0 ) {
			return sprintf(
				/* translators: %s: formatted date */
				__( 'Access through %s (auto-renewal cancelled)', 'cta-lms' ),
				cta_lms_date( 'F j, Y', $period_end, cta_lms_get_timezone() )
			);
		}

		if ( $next ) {
			return $next;
		}

		return $this->get_demo_next_billing_date( $user_id );
	}

	/**
	 * Render document row partial HTML.
	 *
	 * @param object $document Document row.
	 * @return string
	 */
	public function render_document_row_html( $document ) {
		$dashboard = $this;

		ob_start();
		include CTA_PLUGIN_DIR . 'templates/partials/document-row.php';
		return ob_get_clean();
	}

	/**
	 * Enrich booking with slot seat data and meeting link access.
	 *
	 * @param object $booking Booking row.
	 * @return object
	 */
	public function enrich_booking( $booking ) {
		global $wpdb;

		$slot_id = 0;
		$notes   = json_decode( (string) $booking->notes, true );

		if ( is_array( $notes ) && ! empty( $notes['slot_id'] ) ) {
			$slot_id = (int) $notes['slot_id'];
		}

		$seats_booked = 0;
		$seats_total  = 'group' === $booking->session_type ? CTA_Supervision::GROUP_SEATS_MAX : 1;
		$meeting_url  = $this->extract_meeting_url_from_notes( $booking->notes );

		if ( $slot_id ) {
			$slot = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT seats_booked, seats_total, notes FROM {$wpdb->prefix}cta_bookings WHERE id = %d AND user_id = 0",
					$slot_id
				)
			);

			if ( $slot ) {
				$seats_booked = (int) $slot->seats_booked;
				$seats_total  = (int) $slot->seats_total;

				if ( ! $meeting_url ) {
					$meeting_url = $this->extract_meeting_url_from_notes( $slot->notes );
				}
			}
		}

		$booking->seats_booked = $seats_booked;
		$booking->seats_total  = max( 1, $seats_total );
		$booking->can_cancel   = $this->booking_can_cancel( $booking );
		$booking->meeting_url  = $meeting_url;

		$is_own_confirmed = (
			(int) $booking->user_id === (int) get_current_user_id()
			&& 'confirmed' === (string) $booking->status
		);

		$booking->can_join = CTA_Supervision::evaluate_can_join_meeting(
			(bool) $meeting_url,
			$is_own_confirmed,
			CTA_Associate_Access::can_access_meeting_links( get_current_user_id() )
		);

		// Never expose the raw meeting URL unless Join is allowed.
		if ( ! $booking->can_join ) {
			$booking->meeting_url = '';
		}

		return $booking;
	}

	/**
	 * Extract a meeting / join URL from booking notes.
	 *
	 * Supports JSON keys meeting_url, meeting_link, join_url, or a bare URL string.
	 *
	 * @param string $notes Notes field.
	 * @return string
	 */
	private function extract_meeting_url_from_notes( $notes ) {
		$notes = (string) $notes;

		if ( '' === $notes ) {
			return '';
		}

		$decoded = json_decode( $notes, true );

		if ( is_array( $decoded ) ) {
			foreach ( array( 'meeting_url', 'meeting_link', 'join_url', 'zoom_url' ) as $key ) {
				if ( ! empty( $decoded[ $key ] ) && filter_var( $decoded[ $key ], FILTER_VALIDATE_URL ) ) {
					return esc_url_raw( $decoded[ $key ] );
				}
			}

			return '';
		}

		$notes = trim( $notes );

		if ( filter_var( $notes, FILTER_VALIDATE_URL ) ) {
			return esc_url_raw( $notes );
		}

		return '';
	}

	/**
	 * Whether a booking can be cancelled (24hr policy).
	 *
	 * @param object $booking Booking row.
	 * @return bool
	 */
	public function booking_can_cancel( $booking ) {
		$dt = cta_lms_session_datetime( $booking->session_date, $booking->session_time );

		if ( ! $dt ) {
			return false;
		}

		return $dt->getTimestamp() > ( time() + DAY_IN_SECONDS );
	}

	/**
	 * Format session date for display.
	 *
	 * @param string $date Session date.
	 * @return string
	 */
	public function format_session_date( $date ) {
		return cta_lms_format_session_date( $date, 'l, F j, Y' );
	}

	/**
	 * Format session time for display.
	 *
	 * @param string $date Session date.
	 * @param string $time Session time.
	 * @return string
	 */
	public function format_session_time( $date, $time ) {
		return cta_lms_format_session_time( $date, $time, 'g:i A T' );
	}

	/**
	 * Format duration label.
	 *
	 * @param object $booking Booking row.
	 * @return string
	 */
	public function format_duration_label( $booking ) {
		$mins = (int) $booking->duration_mins;

		if ( 'group' === $booking->session_type || $mins >= 120 ) {
			return __( '2 hours', 'cta-lms' );
		}

		return __( '1 hour', 'cta-lms' );
	}

	/**
	 * Get history status label and badge class.
	 *
	 * @param object $booking Booking row.
	 * @return array
	 */
	public function get_history_status( $booking ) {
		if ( 'cancelled' === $booking->status ) {
			return array(
				'label' => __( 'Cancelled', 'cta-lms' ),
				'class' => 'badge--outline',
			);
		}

		return array(
			'label' => __( 'Completed', 'cta-lms' ),
			'class' => 'badge--success',
		);
	}

	/**
	 * Format file size for display.
	 *
	 * @param int $bytes File size in bytes.
	 * @return string
	 */
	public function format_file_size( $bytes ) {
		$bytes = (int) $bytes;

		if ( $bytes >= 1048576 ) {
			return round( $bytes / 1048576, 1 ) . ' MB';
		}

		if ( $bytes >= 1024 ) {
			return round( $bytes / 1024, 1 ) . ' KB';
		}

		return $bytes . ' B';
	}

	/**
	 * Get review status badge data.
	 *
	 * @param object $document Document row.
	 * @return array
	 */
	public function get_review_badge( $document ) {
		switch ( $document->review_status ) {
			case 'approved':
			case 'reviewed':
				return array(
					'label' => __( 'Reviewed', 'cta-lms' ),
					'class' => 'badge--success',
				);
			case 'rejected':
				return array(
					'label' => __( 'Rejected', 'cta-lms' ),
					'class' => 'badge--danger',
				);
			default:
				return array(
					'label' => __( 'Pending Review', 'cta-lms' ),
					'class' => 'badge--warning',
				);
		}
	}

	/**
	 * Get document category label.
	 *
	 * @param string $key Category key.
	 * @return string
	 */
	public function get_category_label( $key ) {
		return isset( self::DOC_CATEGORIES[ $key ] ) ? self::DOC_CATEGORIES[ $key ] : $key;
	}

	/**
	 * Truncate file name for display.
	 *
	 * @param string $name File name.
	 * @param int    $max  Max length.
	 * @return string
	 */
	public function truncate_filename( $name, $max = 42 ) {
		if ( strlen( $name ) <= $max ) {
			return $name;
		}

		return substr( $name, 0, $max - 3 ) . '...';
	}

	/**
	 * Check associate dashboard access.
	 *
	 * @return string|null
	 */
	/**
	 * Activate a supervision purchase when returning from Stripe Checkout.
	 *
	 * Also recovers stuck "pending" payment rows if the webhook never arrived.
	 *
	 * @param int $user_id User ID.
	 */
	private function maybe_finalize_returned_subscription( $user_id ) {
		$user_id = absint( $user_id );
		$stripe  = cta_get_stripe();

		if ( ! $user_id || ! $stripe ) {
			return;
		}

		$session_id = sanitize_text_field( wp_unslash( $_GET['session_id'] ?? '' ) );
		$flag       = sanitize_text_field( wp_unslash( $_GET['subscription'] ?? '' ) );

		if ( 'success' === $flag && $session_id ) {
			$stripe->finalize_checkout_session( $session_id, $user_id );
		}

		$stripe->maybe_finalize_user_pending_checkouts( $user_id );

		// Bypass / demo payments already wrote meta — still refresh caches.
		if ( 'success' === $flag || ! empty( $_GET['cta_paid'] ) ) {
			clean_user_cache( $user_id );
		}
	}

	/**
	 * If a completed supervision payment exists without plan status, activate it.
	 *
	 * Covers webhook/redirect failures where the payment row was marked completed
	 * but user meta was never written.
	 *
	 * @param int $user_id User ID.
	 */
	private function maybe_heal_completed_supervision_purchase( $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return;
		}

		$status = (string) get_user_meta( $user_id, 'cta_supervision_status', true );

		if ( in_array( $status, array( 'active', 'pending_approval', 'locked', 'past_due', 'rejected' ), true ) ) {
			return;
		}

		$payment = CTA_Database::get_user_supervision_payment( $user_id, 'completed' );

		if ( ! $payment ) {
			return;
		}

		$stripe = cta_get_stripe();

		if ( ! $stripe ) {
			update_user_meta( $user_id, 'cta_supervision_status', 'pending_approval' );
			$resolved_slug = CTA_Supervision_Plans::resolve_user_plan_slug( $user_id );
			update_user_meta( $user_id, 'cta_supervision_plan', $resolved_slug );
			update_user_meta( $user_id, 'cta_supervision_plan_name', CTA_Supervision_Plans::get_name( $resolved_slug ) );
			clean_user_cache( $user_id );
			return;
		}

		$stripe->activate_supervision_purchase(
			$user_id,
			array(
				'checkout_session_id' => (string) $payment->stripe_payment_id,
				'subscription_id'     => (string) get_user_meta( $user_id, 'cta_supervision_subscription_id', true ),
				'customer_id'         => (string) ( $payment->stripe_customer_id ?? '' ),
				'amount'              => (float) $payment->amount,
				'plan_name'           => (string) ( $payment->plan_name ?? '' ),
				'create_payment_row'  => false,
				'skip_payment_row'    => true,
				'send_receipt'        => false,
			)
		);

		clean_user_cache( $user_id );
	}

	private function check_associate_access() {
		$user  = wp_get_current_user();
		$roles = is_user_logged_in() ? (array) $user->roles : array();

		// Associates and admins stay on the portal.
		if ( in_array( 'cta_associate', $roles, true ) || in_array( 'administrator', $roles, true ) ) {
			return null;
		}

		// Guests, CE learners, and other roles hitting this portal (often via a
		// mis-pointed "Clinical Supervision" marketing link) should land on the
		// public supervision plans / booking page — not login or the CE dashboard.
		$booking = $this->get_supervision_page_url();

		if ( $booking ) {
			return $this->redirect_markup( $booking );
		}

		if ( ! is_user_logged_in() ) {
			return $this->redirect_markup( $this->get_login_url() );
		}

		return $this->redirect_markup( home_url( '/' ) );
	}

	/**
	 * Whether current user can upload documents.
	 *
	 * @return bool
	 */
	private function user_can_upload_documents() {
		$user_id = get_current_user_id();

		if ( ! CTA_Associate_Access::can_access_supervision_features( $user_id ) ) {
			return false;
		}

		$user  = get_userdata( $user_id );
		$roles = $user ? (array) $user->roles : array();

		return in_array( 'cta_associate', $roles, true ) || in_array( 'administrator', $roles, true );
	}

	/**
	 * Pull latest subscription cancel / period data from Stripe into user meta.
	 *
	 * @param int $user_id User ID.
	 */
	private function maybe_sync_subscription_from_stripe( $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id || CTA_Stripe::is_payments_bypass_enabled() ) {
			return;
		}

		$stripe = cta_get_stripe();

		if ( ! $stripe || ! $stripe->is_configured() ) {
			return;
		}

		// Always refresh after returning from the Customer Billing Portal.
		$force = isset( $_GET['cta_billing'] ) && 'returned' === sanitize_text_field( wp_unslash( $_GET['cta_billing'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$last = (int) get_user_meta( $user_id, 'cta_supervision_last_stripe_sync', true );
		if ( ! $force && $last && ( time() - $last ) < 60 ) {
			return;
		}

		$result = $stripe->sync_user_subscription_from_stripe( $user_id );

		if ( ! is_wp_error( $result ) ) {
			update_user_meta( $user_id, 'cta_supervision_last_stripe_sync', time() );
		}
	}

	/**
	 * Get Stripe customer ID for user.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private function get_stripe_customer_id( $user_id ) {
		$stripe = cta_get_stripe();

		if ( $stripe ) {
			return $stripe->resolve_stripe_customer_id( $user_id );
		}

		$customer_id = (string) get_user_meta( $user_id, 'cta_stripe_customer_id', true );

		return $customer_id;
	}

	/**
	 * Fetch next billing date from Stripe subscription.
	 *
	 * @param string $subscription_id Stripe subscription ID.
	 * @return string
	 */
	private function get_next_billing_date( $subscription_id ) {
		if ( empty( $subscription_id ) || ! class_exists( '\Stripe\Subscription' ) ) {
			return '';
		}

		$stripe = cta_get_stripe();

		if ( ! $stripe || ! $stripe->is_configured() ) {
			return '';
		}

		if ( 0 === strpos( $subscription_id, 'bypass-' ) ) {
			return '';
		}

		try {
			$subscription = \Stripe\Subscription::retrieve( $subscription_id );

			if ( ! empty( $subscription->current_period_end ) ) {
				return cta_lms_date( 'F j, Y', (int) $subscription->current_period_end, cta_lms_get_timezone() );
			}
		} catch ( Exception $e ) {
			return '';
		}

		return '';
	}

	/**
	 * Estimate next billing date for demo / bypass subscriptions.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private function get_demo_next_billing_date( $user_id ) {
		global $wpdb;

		$last_payment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT created_at FROM {$wpdb->prefix}cta_payments
				WHERE user_id = %d
				AND product_type = 'supervision'
				AND status = 'completed'
				ORDER BY created_at DESC
				LIMIT 1",
				$user_id
			)
		);

		if ( $last_payment && ! empty( $last_payment->created_at ) ) {
			$parsed = cta_lms_parse_datetime( $last_payment->created_at );

			if ( $parsed ) {
				$next = $parsed->setTimezone( cta_lms_get_timezone() )->modify( '+1 month' );
				return cta_lms_date( 'F j, Y', $next->getTimestamp(), cta_lms_get_timezone() );
			}
		}

		try {
			$next_month = ( new DateTimeImmutable( 'first day of next month', cta_lms_get_timezone() ) );
			return cta_lms_date( 'F j, Y', $next_month->getTimestamp(), cta_lms_get_timezone() );
		} catch ( Exception $e ) {
			return cta_lms_format_local_date( null, 'F j, Y' );
		}
	}

	/**
	 * Build next session stat label.
	 *
	 * @param array $sessions Upcoming sessions.
	 * @return string
	 */
	private function get_next_session_label( $sessions ) {
		if ( empty( $sessions ) ) {
			return __( 'No upcoming sessions', 'cta-lms' );
		}

		$next = $sessions[0];

		return sprintf(
			/* translators: %s: session date/time */
			__( 'Next Session: %s', 'cta-lms' ),
			$this->format_session_date( $next->session_date ) . ' · ' . $this->format_session_time( $next->session_date, $next->session_time )
		);
	}

	/**
	 * Get plan display label.
	 *
	 * @param string $plan Plan slug.
	 * @return string
	 */
	private function get_plan_label( $plan ) {
		return CTA_Supervision_Plans::get_name( $plan );
	}

	/**
	 * Delete uploaded file from disk.
	 *
	 * @param string $file_url File URL.
	 */
	private function delete_document_file( $file_url ) {
		$upload_dir = wp_upload_dir();
		$base_url   = $upload_dir['baseurl'];
		$base_dir   = $upload_dir['basedir'];

		if ( 0 === strpos( $file_url, $base_url ) ) {
			$file_path = $base_dir . str_replace( $base_url, '', $file_url );

			if ( file_exists( $file_path ) ) {
				if ( function_exists( 'wp_delete_file' ) ) {
					wp_delete_file( $file_path );
				} else {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
					unlink( $file_path );
				}
			}
		}
	}

	/**
	 * Dashboard user display data.
	 *
	 * @param WP_User $user              WordPress user.
	 * @param string  $associate_number  Associate number meta.
	 * @return array
	 */
	private function get_dashboard_user_data( $user, $associate_number ) {
		$name = function_exists( 'cta_lms_get_user_legal_name' )
			? cta_lms_get_user_legal_name( $user->ID )
			: ( $user->display_name ? $user->display_name : $user->user_login );
		$parts    = preg_split( '/\s+/', trim( $name ) );
		$initials = '';

		if ( ! empty( $parts[0] ) ) {
			$initials .= strtoupper( substr( $parts[0], 0, 1 ) );
		}
		if ( count( $parts ) > 1 && ! empty( $parts[ count( $parts ) - 1 ] ) ) {
			$initials .= strtoupper( substr( $parts[ count( $parts ) - 1 ], 0, 1 ) );
		}

		return array(
			'displayName'     => $name,
			'email'           => $user->user_email,
			'associateNumber' => $associate_number ? $associate_number : __( 'Associate', 'cta-lms' ),
			'initials'        => $initials ? $initials : '--',
		);
	}

	/**
	 * Redirect markup fallback.
	 *
	 * @param string $url Target URL.
	 * @return string
	 */
	private function redirect_markup( $url ) {
		if ( ! headers_sent() ) {
			wp_safe_redirect( $url );
			exit;
		}

		return '<script>window.location.replace(' . wp_json_encode( esc_url_raw( $url ) ) . ');</script>';
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

	/**
	 * Get supervision dashboard URL.
	 *
	 * @return string
	 */
	private function get_dashboard_url() {
		$page_id = absint( get_option( 'cta_supervision_dashboard_page_id', 0 ) );

		if ( ! $page_id ) {
			return '';
		}

		$url = get_permalink( $page_id );

		return $url ? $url : '';
	}

	/**
	 * Get CE student dashboard URL.
	 *
	 * @return string
	 */
	private function get_student_dashboard_url() {
		$page_id = absint( get_option( 'cta_student_dashboard_page_id', 0 ) );

		if ( ! $page_id ) {
			return '';
		}

		$url = get_permalink( $page_id );

		return $url ? $url : '';
	}

	/**
	 * Get supervision booking page URL.
	 *
	 * @return string
	 */
	private function get_supervision_page_url() {
		$page_id = absint( get_option( 'cta_supervision_page_id', 0 ) );
		$dash_id = absint( get_option( 'cta_supervision_dashboard_page_id', 0 ) );

		if ( $page_id && $dash_id && $page_id === $dash_id ) {
			$page_id = 0;
		}

		if ( ! $page_id && function_exists( 'cta_lms_find_page_id_by_shortcode' ) ) {
			$page_id = absint( cta_lms_find_page_id_by_shortcode( 'cta_supervision_booking' ) );
		}

		if ( ! $page_id ) {
			return '';
		}

		$url = get_permalink( $page_id );

		return $url ? $url : '';
	}
}
}
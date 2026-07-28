<?php
/**
 * Associate access checks — four independent status axes:
 *
 * 1. General account status (`cta_account_status`) — active/inactive registered user.
 * 2. CE / Exam Prep access — purchase/enrollment only; never gated by supervision approval.
 * 3. Supervision application (`cta_approval_status`) — pending / approved / rejected vetting.
 * 4. Supervision plan (`cta_supervision_status`) — purchased/assigned plan lifecycle.
 *
 * Until an Associate's supervision application is Approved (and they have an active plan),
 * they cannot access supervision-only features:
 * - supervision booking / scheduling
 * - meeting / join links
 * - supervision resources (documents & logs)
 *
 * Pending supervision approval must NEVER block CE courses, Exam Prep, or the general dashboard.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Associate_Access
 */
if ( ! class_exists( 'CTA_Associate_Access' ) ) {

class CTA_Associate_Access {

	const STATUS_PENDING  = 'pending_approval';
	const STATUS_APPROVED = 'approved';
	const STATUS_REJECTED = 'rejected';

	const ACCOUNT_ACTIVE   = 'active';
	const ACCOUNT_INACTIVE = 'inactive';

	/** Plan lifecycle values for `cta_supervision_status` (axis 4). */
	const PLAN_NONE        = 'none';
	const PLAN_ACTIVE      = 'active';
	const PLAN_PAUSED      = 'paused';
	const PLAN_PAST_DUE    = 'past_due';
	const PLAN_LOCKED      = 'locked';
	const PLAN_CANCELLED   = 'cancelled';
	const PLAN_AWAITING    = 'pending_approval'; // Purchased/assigned; waiting on application approval.

	const META_ACCOUNT_STATUS                 = 'cta_account_status';
	const META_SUPERVISION_APPLICATION_STATUS = 'cta_approval_status';
	const META_SUPERVISION_PLAN_STATUS        = 'cta_supervision_status';
	const META_ADMIN_ASSIGNED_PLAN            = 'cta_admin_assigned_plan';
	const META_ADMIN_ASSIGNED_NOTE            = 'cta_admin_assigned_plan_note';
	const META_ADMIN_ASSIGNED_AT              = 'cta_admin_assigned_plan_at';
	const META_ADMIN_ASSIGNED_BY              = 'cta_admin_assigned_plan_by';

	/**
	 * General account status (active/inactive) — independent of supervision vetting.
	 *
	 * @param int $user_id User ID.
	 * @return string active|inactive|''
	 */
	public static function get_account_status( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( ! $user_id ) {
			return '';
		}

		$status = (string) get_user_meta( $user_id, self::META_ACCOUNT_STATUS, true );

		if ( '' === $status ) {
			$user = get_userdata( $user_id );
			// Legacy accounts without the meta: treat any existing WP user as active.
			return ( $user && ! empty( $user->ID ) ) ? self::ACCOUNT_ACTIVE : '';
		}

		return $status;
	}

	/**
	 * Whether the general account is active (can use CE / Exam Prep / dashboards).
	 *
	 * Supervision application pending/rejected does not deactivate the account.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_account_active( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( ! $user_id ) {
			return false;
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return false;
		}

		return self::ACCOUNT_ACTIVE === self::get_account_status( $user_id );
	}

	/**
	 * Mark a newly registered (or existing) user as an active general account.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public static function ensure_account_active( $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return;
		}

		$current = (string) get_user_meta( $user_id, self::META_ACCOUNT_STATUS, true );

		if ( self::ACCOUNT_INACTIVE === $current ) {
			return;
		}

		update_user_meta( $user_id, self::META_ACCOUNT_STATUS, self::ACCOUNT_ACTIVE );
	}

	/**
	 * CE and Exam Prep purchase/access are never gated by supervision approval.
	 *
	 * Content still requires a valid purchase/enrollment (handled elsewhere).
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function can_access_ce_and_exam_prep( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( ! $user_id ) {
			return false;
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return false;
		}

		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			return true;
		}

		return self::is_account_active( $user_id );
	}

	/**
	 * General account dashboard URL (CE / My Courses).
	 *
	 * Always the student dashboard — never the supervision portal.
	 * Pending supervision applications must not change this destination.
	 *
	 * @param int $user_id Unused; kept for call-site compatibility.
	 * @return string
	 */
	public static function get_general_dashboard_url( $user_id = 0 ) {
		unset( $user_id );

		$ce_page_id  = absint( get_option( 'cta_student_dashboard_page_id', 0 ) );
		$sup_page_id = absint( get_option( 'cta_supervision_dashboard_page_id', 0 ) );

		// Guard against misconfigured identical page IDs (would dump users onto
		// the supervision pending screen instead of My Courses).
		if ( $ce_page_id && $sup_page_id && $ce_page_id === $sup_page_id ) {
			if ( function_exists( 'cta_lms_find_page_id_by_shortcode' ) ) {
				$found = absint( cta_lms_find_page_id_by_shortcode( 'cta_student_dashboard' ) );
				if ( $found && $found !== $sup_page_id ) {
					$ce_page_id = $found;
				}
			}
		}

		if ( $ce_page_id ) {
			$url = get_permalink( $ce_page_id );
			if ( $url ) {
				return $url;
			}
		}

		if ( function_exists( 'cta_lms_get_linked_page_url' ) ) {
			$url = cta_lms_get_linked_page_url( 'cta_student_dashboard_page_id' );
			if ( $url ) {
				$sup_url = self::get_supervision_dashboard_url();
				if ( ! $sup_url || untrailingslashit( $url ) !== untrailingslashit( $sup_url ) ) {
					return $url;
				}
			}
		}

		return home_url( '/' );
	}

	/**
	 * Supervision portal URL (booking / sessions / materials).
	 *
	 * @param int $user_id Unused; kept for call-site compatibility.
	 * @return string
	 */
	public static function get_supervision_dashboard_url( $user_id = 0 ) {
		unset( $user_id );

		if ( function_exists( 'cta_lms_get_linked_page_url' ) ) {
			$url = cta_lms_get_linked_page_url( 'cta_supervision_dashboard_page_id' );
			if ( $url ) {
				return $url;
			}
		}

		$sup_page_id = absint( get_option( 'cta_supervision_dashboard_page_id', 0 ) );
		$sup_url     = $sup_page_id ? get_permalink( $sup_page_id ) : '';

		return $sup_url ? $sup_url : '';
	}

	/**
	 * Primary frontend dashboard URL for a user ("My Dashboard").
	 *
	 * Always the general CE student dashboard so pending (or active)
	 * supervision status never hijacks account navigation.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public static function get_primary_dashboard_url( $user_id = 0 ) {
		return self::get_general_dashboard_url( $user_id );
	}

	/**
	 * Get supervision application status meta for a user.
	 *
	 * @param int $user_id User ID.
	 * @return string pending_approval|approved|rejected|''
	 */
	public static function get_approval_status( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( ! $user_id ) {
			return '';
		}

		return (string) get_user_meta( $user_id, self::META_SUPERVISION_APPLICATION_STATUS, true );
	}

	/**
	 * Alias: supervision application status (axis 3).
	 *
	 * @param int $user_id User ID.
	 * @return string pending_approval|approved|rejected|''
	 */
	public static function get_supervision_application_status( $user_id = 0 ) {
		return self::get_approval_status( $user_id );
	}

	/**
	 * Snapshot of the four independent status axes for a user.
	 *
	 * CE / Exam Prep access here means the account may purchase/use those products;
	 * individual course access still depends on enrollment/payment elsewhere.
	 *
	 * @param int $user_id User ID.
	 * @return array{
	 *   account_status:string,
	 *   ce_exam_prep_access:bool,
	 *   supervision_application_status:string,
	 *   supervision_plan_status:string
	 * }
	 */
	public static function get_independent_statuses( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		return array(
			'account_status'                   => self::get_account_status( $user_id ),
			'ce_exam_prep_access'              => self::can_access_ce_and_exam_prep( $user_id ),
			'supervision_application_status'   => self::get_supervision_application_status( $user_id ),
			'supervision_plan_status'          => self::get_supervision_plan_status( $user_id ),
		);
	}

	/**
	 * Whether the user has a completed supervision / All-Access purchase.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function has_purchased_plan( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( ! $user_id ) {
			return false;
		}

		if ( class_exists( 'CTA_Database' ) ) {
			$payment = CTA_Database::get_user_supervision_payment( $user_id, 'completed' );
			if ( $payment ) {
				return true;
			}
		}

		return (bool) get_user_meta( $user_id, 'cta_hybrid_plan_active', true );
	}

	/**
	 * Whether an administrator assigned a supervision plan (agency-paid).
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function has_admin_assigned_plan( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( ! $user_id ) {
			return false;
		}

		$slug = (string) get_user_meta( $user_id, self::META_ADMIN_ASSIGNED_PLAN, true );

		return '' !== $slug;
	}

	/**
	 * Whether the associate has a purchasable/qualifying plan for approval.
	 *
	 * Qualifying = completed purchase OR administratively assigned plan.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function has_qualifying_plan( $user_id = 0 ) {
		return self::has_purchased_plan( $user_id ) || self::has_admin_assigned_plan( $user_id );
	}

	/**
	 * Display label for the associate's plan (purchase or agency-assigned).
	 *
	 * @param int $user_id User ID.
	 * @return string Empty when none.
	 */
	public static function get_plan_display_name( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( ! $user_id ) {
			return '';
		}

		if ( self::has_admin_assigned_plan( $user_id ) ) {
			$slug = (string) get_user_meta( $user_id, self::META_ADMIN_ASSIGNED_PLAN, true );
			$name = class_exists( 'CTA_Supervision_Plans' )
				? CTA_Supervision_Plans::get_name( $slug )
				: $slug;

			return sprintf(
				/* translators: %s: plan name */
				__( 'Agency-assigned: %s', 'cta-lms' ),
				$name
			);
		}

		if ( class_exists( 'CTA_Supervision_Plans' ) ) {
			$slug = CTA_Supervision_Plans::resolve_user_plan_slug( $user_id );
			$has_plan = (
				'' !== (string) get_user_meta( $user_id, 'cta_supervision_plan', true )
				|| '' !== (string) get_user_meta( $user_id, 'cta_supervision_plan_name', true )
				|| (bool) get_user_meta( $user_id, 'cta_hybrid_plan_active', true )
				|| ( class_exists( 'CTA_Database' ) && CTA_Database::get_user_supervision_payment( $user_id, 'completed' ) )
			);

			if ( $has_plan ) {
				return CTA_Supervision_Plans::get_name( $slug );
			}

			return '';
		}

		$meta_name = (string) get_user_meta( $user_id, 'cta_supervision_plan_name', true );
		if ( '' !== $meta_name ) {
			return $meta_name;
		}

		return '';
	}

	/**
	 * Administratively assign a supervision plan (agency-paid arrangements).
	 *
	 * @param int    $user_id   User ID.
	 * @param string $plan_slug group|hybrid.
	 * @param string $note      Optional internal note.
	 * @return bool|WP_Error
	 */
	public static function assign_plan( $user_id, $plan_slug, $note = '' ) {
		$user_id   = absint( $user_id );
		$plan_slug = class_exists( 'CTA_Supervision_Plans' )
			? CTA_Supervision_Plans::normalize_slug( $plan_slug )
			: sanitize_key( $plan_slug );
		$note      = sanitize_textarea_field( $note );

		if ( ! $user_id || ! self::is_associate( $user_id ) ) {
			return new WP_Error( 'invalid_associate', __( 'Invalid Associate account.', 'cta-lms' ) );
		}

		if ( ! in_array( $plan_slug, array( 'group', 'hybrid' ), true ) ) {
			return new WP_Error( 'invalid_plan', __( 'Invalid supervision plan.', 'cta-lms' ) );
		}

		$plan_name = class_exists( 'CTA_Supervision_Plans' )
			? CTA_Supervision_Plans::get_name( $plan_slug )
			: $plan_slug;

		update_user_meta( $user_id, self::META_ADMIN_ASSIGNED_PLAN, $plan_slug );
		update_user_meta( $user_id, self::META_ADMIN_ASSIGNED_NOTE, $note );
		update_user_meta( $user_id, self::META_ADMIN_ASSIGNED_AT, current_time( 'mysql' ) );
		update_user_meta( $user_id, self::META_ADMIN_ASSIGNED_BY, get_current_user_id() );
		update_user_meta( $user_id, 'cta_supervision_plan', $plan_slug );
		update_user_meta( $user_id, 'cta_supervision_plan_name', $plan_name );

		if ( '' === self::get_approval_status( $user_id ) ) {
			update_user_meta( $user_id, self::META_SUPERVISION_APPLICATION_STATUS, self::STATUS_PENDING );
		}

		// Approved + assigned plan → activate access immediately.
		if ( self::is_approved( $user_id ) ) {
			update_user_meta( $user_id, self::META_SUPERVISION_PLAN_STATUS, self::PLAN_ACTIVE );
		} else {
			$status = self::get_supervision_status( $user_id );
			if ( ! in_array( $status, array( self::PLAN_ACTIVE, self::PLAN_AWAITING ), true ) ) {
				update_user_meta( $user_id, self::META_SUPERVISION_PLAN_STATUS, self::PLAN_AWAITING );
			}
		}

		self::ensure_account_active( $user_id );
		clean_user_cache( $user_id );

		return true;
	}

	/**
	 * Whether the user is an Associate (role check).
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_associate( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( ! $user_id ) {
			return false;
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return false;
		}

		return in_array( 'cta_associate', (array) $user->roles, true );
	}

	/**
	 * Whether the user may purchase supervision (or a supervision/hybrid plan).
	 *
	 * Registered Associates only. Administrators are allowed for testing.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function can_purchase_supervision( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( ! $user_id ) {
			return false;
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return false;
		}

		$roles = (array) $user->roles;

		if ( in_array( 'administrator', $roles, true ) ) {
			return true;
		}

		return in_array( 'cta_associate', $roles, true );
	}

	/**
	 * Login/register page URL opened on the registration form.
	 *
	 * @return string
	 */
	public static function get_associate_registration_url() {
		$page_id = absint( get_option( 'cta_login_page_id', 0 ) );
		$url     = $page_id ? get_permalink( $page_id ) : '';

		if ( ! $url ) {
			$url = wp_registration_url();
		}

		if ( ! $url ) {
			$url = home_url( '/' );
		}

		return add_query_arg( 'cta_auth', 'register', $url );
	}

	/**
	 * Message shown when a non-associate tries to buy supervision.
	 *
	 * @return string
	 */
	public static function get_associate_required_message() {
		return __(
			'Supervision is available only to Registered Associates (AMFT, ASW, APCC). Please register as a Registered Associate to continue.',
			'cta-lms'
		);
	}

	/**
	 * Whether the user has complete employer/agency application details on file.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function has_agency_application_info( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( ! $user_id ) {
			return false;
		}

		$employer = (string) get_user_meta( $user_id, 'cta_employer_agency_name', true );
		$rep_name = (string) get_user_meta( $user_id, 'cta_agency_representative_name', true );
		$rep_email = (string) get_user_meta( $user_id, 'cta_agency_representative_email', true );

		return '' !== $employer && '' !== $rep_name && is_email( $rep_email );
	}

	/**
	 * Parse agency fields from a request array (POST).
	 *
	 * @param array $source Request source (defaults to $_POST).
	 * @return array{employer_agency_name:string,agency_representative_name:string,agency_representative_email:string}
	 */
	public static function parse_agency_fields_from_request( $source = null ) {
		if ( null === $source ) {
			$source = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		$source = is_array( $source ) ? $source : array();

		return array(
			'employer_agency_name'        => sanitize_text_field( wp_unslash( $source['employer_agency_name'] ?? '' ) ),
			'agency_representative_name'  => sanitize_text_field( wp_unslash( $source['agency_representative_name'] ?? '' ) ),
			'agency_representative_email' => sanitize_email( wp_unslash( $source['agency_representative_email'] ?? '' ) ),
		);
	}

	/**
	 * Save supervision-application agency details and open the pending vetting status.
	 *
	 * @param int   $user_id User ID.
	 * @param array $fields  employer_agency_name, agency_representative_name, agency_representative_email.
	 * @param bool  $notify  Whether to send the agency representative email (once).
	 * @return true|WP_Error
	 */
	public static function save_supervision_application_agency( $user_id, $fields, $notify = true ) {
		$user_id = absint( $user_id );
		$fields  = is_array( $fields ) ? $fields : array();

		$employer  = sanitize_text_field( $fields['employer_agency_name'] ?? '' );
		$rep_name  = sanitize_text_field( $fields['agency_representative_name'] ?? '' );
		$rep_email = sanitize_email( $fields['agency_representative_email'] ?? '' );

		if ( ! $user_id || ! self::is_associate( $user_id ) ) {
			return new WP_Error( 'invalid_associate', __( 'Invalid Associate account.', 'cta-lms' ) );
		}

		if ( '' === $employer || '' === $rep_name || '' === $rep_email ) {
			return new WP_Error(
				'agency_info_required',
				__( 'Please provide Employer/Agency Name, Agency Representative Name, and Agency Representative Email to apply for supervision.', 'cta-lms' )
			);
		}

		if ( ! is_email( $rep_email ) ) {
			return new WP_Error(
				'invalid_agency_email',
				__( 'Please enter a valid agency representative email.', 'cta-lms' )
			);
		}

		update_user_meta( $user_id, 'cta_employer_agency_name', $employer );
		update_user_meta( $user_id, 'cta_agency_representative_name', $rep_name );
		update_user_meta( $user_id, 'cta_agency_representative_email', $rep_email );

		$approval = self::get_approval_status( $user_id );
		if ( '' === $approval || self::STATUS_PENDING === $approval ) {
			update_user_meta( $user_id, self::META_SUPERVISION_APPLICATION_STATUS, self::STATUS_PENDING );
		}

		// Application pending must never touch plan status or deactivate CE.
		self::ensure_account_active( $user_id );
		clean_user_cache( $user_id );

		if ( $notify && class_exists( 'CTA_Emails' ) ) {
			$already_sent = (string) get_user_meta( $user_id, 'cta_agency_notification_sent_at', true );

			if ( '' === $already_sent ) {
				$sent = CTA_Emails::send(
					'agency_representative_approval',
					$user_id,
					array(
						'employer_agency_name'        => $employer,
						'agency_representative_name'  => $rep_name,
						'agency_representative_email' => $rep_email,
					)
				);

				if ( $sent ) {
					update_user_meta( $user_id, 'cta_agency_notification_sent_at', current_time( 'mysql' ) );
				}
			}
		}

		return true;
	}

	/**
	 * Ensure agency application details exist before a supervision purchase continues.
	 *
	 * Prefers POST fields; falls back to saved user meta. Sends JSON error and exits on failure.
	 *
	 * @param int $user_id User ID.
	 * @return true
	 */
	public static function require_agency_for_supervision_application( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		$posted  = self::parse_agency_fields_from_request();

		$has_posted = (
			'' !== $posted['employer_agency_name']
			|| '' !== $posted['agency_representative_name']
			|| '' !== $posted['agency_representative_email']
		);

		if ( $has_posted ) {
			$result = self::save_supervision_application_agency( $user_id, $posted, true );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error(
					array(
						'message' => $result->get_error_message(),
						'code'    => $result->get_error_code(),
					)
				);
			}

			return true;
		}

		if ( self::has_agency_application_info( $user_id ) ) {
			// Ensure pending status is set if they already provided agency info earlier.
			$approval = self::get_approval_status( $user_id );
			if ( '' === $approval ) {
				update_user_meta( $user_id, self::META_SUPERVISION_APPLICATION_STATUS, self::STATUS_PENDING );
			}
			self::ensure_account_active( $user_id );
			return true;
		}

		wp_send_json_error(
			array(
				'message' => __(
					'Please provide Employer/Agency Name, Agency Representative Name, and Agency Representative Email to apply for supervision.',
					'cta-lms'
				),
				'code'    => 'agency_info_required',
			)
		);
	}

	/**
	 * Deny a purchase AJAX request when the user is not a Registered Associate.
	 *
	 * @param int $user_id User ID.
	 */
	public static function require_associate_for_purchase( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( self::can_purchase_supervision( $user_id ) ) {
			return;
		}

		wp_send_json_error(
			array(
				'message'      => self::get_associate_required_message(),
				'code'         => 'associate_required',
				'register_url' => self::get_associate_registration_url(),
			)
		);
	}

	/**
	 * Whether the Associate account is Approved.
	 *
	 * Non-associates and administrators are not subject to this gate.
	 * Associates with empty status are treated as pending (not approved).
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_approved( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( ! $user_id ) {
			return false;
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return false;
		}

		$roles = (array) $user->roles;

		if ( in_array( 'administrator', $roles, true ) ) {
			return true;
		}

		if ( ! in_array( 'cta_associate', $roles, true ) ) {
			return true;
		}

		return self::STATUS_APPROVED === self::get_approval_status( $user_id );
	}

	/**
	 * Raw supervision plan lifecycle meta (axis 4).
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public static function get_supervision_status( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( ! $user_id ) {
			return '';
		}

		return (string) get_user_meta( $user_id, self::META_SUPERVISION_PLAN_STATUS, true );
	}

	/**
	 * Supervision plan status with empty normalized to `none`.
	 *
	 * @param int $user_id User ID.
	 * @return string none|active|paused|past_due|locked|cancelled|pending_approval|rejected|…
	 */
	public static function get_supervision_plan_status( $user_id = 0 ) {
		$status = self::get_supervision_status( $user_id );

		return '' === $status ? self::PLAN_NONE : $status;
	}

	/**
	 * Whether the user has an Active supervision plan.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function has_active_supervision( $user_id = 0 ) {
		return self::PLAN_ACTIVE === self::get_supervision_status( $user_id );
	}

	/**
	 * Whether a purchased/assigned plan is waiting on application approval.
	 *
	 * Separate from axis 3 (application pending). Does not affect CE access.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_plan_awaiting_application_approval( $user_id = 0 ) {
		return self::PLAN_AWAITING === self::get_supervision_status( $user_id );
	}

	/**
	 * Whether the supervision application (vetting) is still pending (axis 3 only).
	 *
	 * Independent of general account status, CE/Exam Prep access, and plan status.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_supervision_pending( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( ! $user_id ) {
			return false;
		}

		$user = get_userdata( $user_id );

		if ( $user && in_array( 'administrator', (array) $user->roles, true ) ) {
			return false;
		}

		if ( ! self::is_associate( $user_id ) ) {
			return false;
		}

		return self::STATUS_PENDING === self::get_approval_status( $user_id );
	}

	/**
	 * Heal incorrect coupling between account / application / plan metas.
	 *
	 * - Keeps general accounts active while a supervision application is pending.
	 * - Clears orphaned plan `pending_approval` when there is no qualifying plan.
	 * Never writes inactive from supervision vetting.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public static function heal_decoupled_statuses( $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id || ! self::is_associate( $user_id ) ) {
			return;
		}

		// Supervision vetting must never deactivate the general account.
		$account = (string) get_user_meta( $user_id, self::META_ACCOUNT_STATUS, true );
		if ( self::ACCOUNT_INACTIVE !== $account ) {
			self::ensure_account_active( $user_id );
		} else {
			// Legacy bug: inactive was sometimes set because application was pending.
			$application = self::get_approval_status( $user_id );
			if ( in_array( $application, array( self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED ), true ) ) {
				update_user_meta( $user_id, self::META_ACCOUNT_STATUS, self::ACCOUNT_ACTIVE );
			}
		}

		// Plan axis should not say "pending approval" without a real plan.
		if (
			self::is_plan_awaiting_application_approval( $user_id )
			&& ! self::has_qualifying_plan( $user_id )
		) {
			delete_user_meta( $user_id, self::META_SUPERVISION_PLAN_STATUS );
		}
	}

	/**
	 * Whether the user may use any unlocked supervision features.
	 *
	 * Requires: Approved application + qualifying plan (purchase or agency-assigned)
	 * + Active supervision plan status. Administrators always pass.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function can_access_supervision_features( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( ! $user_id ) {
			return false;
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return false;
		}

		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			return true;
		}

		if ( ! self::is_associate( $user_id ) ) {
			return false;
		}

		return self::is_approved( $user_id )
			&& self::has_qualifying_plan( $user_id )
			&& self::has_active_supervision( $user_id );
	}

	/**
	 * Whether the user may use supervision booking / scheduling.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function can_access_booking( $user_id = 0 ) {
		return self::can_access_supervision_features( $user_id );
	}

	/**
	 * Whether the user may see or use session meeting / join links.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function can_access_meeting_links( $user_id = 0 ) {
		return self::can_access_supervision_features( $user_id );
	}

	/**
	 * Whether the user may access supervision resources (documents & logs).
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function can_access_supervision_resources( $user_id = 0 ) {
		return self::can_access_supervision_features( $user_id );
	}

	/**
	 * Shared denial message for gated supervision privileges.
	 *
	 * @return string
	 */
	public static function get_pending_message() {
		return __(
			'Supervision Application Pending: your application is under review. You can still purchase and access CE courses and Exam Preparation Programs. Supervision booking, meeting links, and materials stay locked until approved.',
			'cta-lms'
		);
	}

	/**
	 * Denial message for gated supervision privileges (context-aware).
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public static function get_access_denied_message( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( self::is_approved_awaiting_plan( $user_id ) ) {
			return self::get_approved_awaiting_plan_message();
		}

		return self::get_pending_message();
	}

	/**
	 * Deny a supervision AJAX request when access is not fully approved.
	 *
	 * @param int $user_id User ID.
	 * @return true|void Sends JSON error and exits when denied.
	 */
	public static function require_supervision_access( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( self::can_access_supervision_features( $user_id ) ) {
			return true;
		}

		wp_send_json_error(
			array(
				'message' => self::get_access_denied_message( $user_id ),
				'code'    => self::is_approved_awaiting_plan( $user_id )
					? 'supervision_awaiting_plan'
					: 'supervision_pending_approval',
			)
		);
	}

	/**
	 * Set an Associate's approval status.
	 *
	 * @param int    $user_id User ID.
	 * @param string $status  pending_approval|approved|rejected.
	 * @return bool
	 */
	public static function set_approval_status( $user_id, $status ) {
		$user_id = absint( $user_id );
		$status  = sanitize_text_field( $status );

		$allowed = array(
			self::STATUS_PENDING,
			self::STATUS_APPROVED,
			self::STATUS_REJECTED,
		);

		if ( ! $user_id || ! in_array( $status, $allowed, true ) ) {
			return false;
		}

		if ( ! self::is_associate( $user_id ) ) {
			return false;
		}

		update_user_meta( $user_id, self::META_SUPERVISION_APPLICATION_STATUS, $status );
		update_user_meta( $user_id, 'cta_approval_reviewed_at', current_time( 'mysql' ) );
		update_user_meta( $user_id, 'cta_approval_reviewed_by', get_current_user_id() );
		clean_user_cache( $user_id );

		// Application status changes must never deactivate the general account.
		self::ensure_account_active( $user_id );

		return true;
	}

	/**
	 * Approve an Associate account (application/vetting passed).
	 *
	 * Approval is independent of purchase. Full supervision access still requires
	 * a qualifying plan (purchase or agency-assigned) plus Active status.
	 *
	 * @param int $user_id User ID.
	 * @return bool|WP_Error
	 */
	public static function approve( $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id || ! self::is_associate( $user_id ) ) {
			return new WP_Error( 'invalid_associate', __( 'Invalid Associate account.', 'cta-lms' ) );
		}

		$ok = self::set_approval_status( $user_id, self::STATUS_APPROVED );

		if ( ! $ok ) {
			return false;
		}

		// Only unlock Active supervision when a plan already exists.
		if ( self::has_qualifying_plan( $user_id ) ) {
			self::activate_purchased_supervision( $user_id );
		}

		delete_user_meta( $user_id, 'cta_approval_rejection_reason' );
		clean_user_cache( $user_id );

		return true;
	}

	/**
	 * Mark a purchased or agency-assigned supervision plan Active after Associate approval.
	 *
	 * @param int $user_id User ID.
	 */
	public static function activate_purchased_supervision( $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return;
		}

		if ( 'active' === self::get_supervision_status( $user_id ) ) {
			return;
		}

		if ( ! self::has_qualifying_plan( $user_id ) ) {
			return;
		}

		update_user_meta( $user_id, self::META_SUPERVISION_PLAN_STATUS, self::PLAN_ACTIVE );
	}

	/**
	 * Pure decision helper for tests: may this associate use supervision features?
	 *
	 * @param bool $is_approved         Supervision application approved.
	 * @param bool $has_qualifying_plan Purchase or agency-assigned plan.
	 * @param bool $has_active_plan     Supervision plan status is active.
	 * @return bool
	 */
	public static function evaluate_feature_access( $is_approved, $has_qualifying_plan, $has_active_plan ) {
		return (bool) $is_approved && (bool) $has_qualifying_plan && (bool) $has_active_plan;
	}

	/**
	 * Pure decision helper for tests: may an admin approve this associate?
	 *
	 * Approval (vetting) does not require a plan. Access still does.
	 *
	 * @param bool $is_associate     Has associate role.
	 * @param bool $already_approved Already approved.
	 * @return bool
	 */
	public static function evaluate_can_approve( $is_associate, $already_approved = false ) {
		if ( ! $is_associate || $already_approved ) {
			return false;
		}

		return true;
	}

	/**
	 * Plan status key for admin display.
	 *
	 * @param int $user_id User ID.
	 * @return string none|purchased|admin_assigned
	 */
	public static function get_plan_status_key( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( ! $user_id ) {
			return 'none';
		}

		if ( self::has_admin_assigned_plan( $user_id ) ) {
			return 'admin_assigned';
		}

		if ( self::has_purchased_plan( $user_id ) ) {
			return 'purchased';
		}

		return 'none';
	}

	/**
	 * Human-readable plan status for admin (No Plan / Purchased / Admin-Assigned).
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public static function get_plan_status_label( $user_id = 0 ) {
		$key = self::get_plan_status_key( $user_id );

		switch ( $key ) {
			case 'admin_assigned':
				$name = self::get_plan_display_name( $user_id );
				return $name ? $name : __( 'Admin-Assigned', 'cta-lms' );
			case 'purchased':
				$name = self::get_plan_display_name( $user_id );
				if ( '' === $name ) {
					return __( 'Purchased', 'cta-lms' );
				}
				return sprintf(
					/* translators: %s: plan name */
					__( 'Purchased: %s', 'cta-lms' ),
					$name
				);
			case 'none':
			default:
				return __( 'No Plan', 'cta-lms' );
		}
	}

	/**
	 * Message when application is approved but no supervision plan is active yet.
	 *
	 * @return string
	 */
	public static function get_approved_awaiting_plan_message() {
		return __( 'Your application is approved. Please purchase a supervision plan to access sessions.', 'cta-lms' );
	}

	/**
	 * Whether the associate is approved but still lacks a qualifying plan.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_approved_awaiting_plan( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( ! $user_id || ! self::is_associate( $user_id ) ) {
			return false;
		}

		return self::is_approved( $user_id ) && ! self::has_qualifying_plan( $user_id );
	}

	/**
	 * Audit details for an administratively assigned plan.
	 *
	 * @param int $user_id User ID.
	 * @return array{slug:string,note:string,assigned_at:string,assigned_by:int,assigned_by_name:string}|null
	 */
	public static function get_admin_assigned_plan_audit( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( ! $user_id || ! self::has_admin_assigned_plan( $user_id ) ) {
			return null;
		}

		$by_id = absint( get_user_meta( $user_id, self::META_ADMIN_ASSIGNED_BY, true ) );
		$by    = $by_id ? get_userdata( $by_id ) : false;

		return array(
			'slug'             => (string) get_user_meta( $user_id, self::META_ADMIN_ASSIGNED_PLAN, true ),
			'note'             => (string) get_user_meta( $user_id, self::META_ADMIN_ASSIGNED_NOTE, true ),
			'assigned_at'      => (string) get_user_meta( $user_id, self::META_ADMIN_ASSIGNED_AT, true ),
			'assigned_by'      => $by_id,
			'assigned_by_name' => $by ? $by->display_name : '',
		);
	}

	/**
	 * Reject a supervision application (keeps supervision features locked).
	 *
	 * Does not deactivate the general account or CE / Exam Prep access.
	 * Plan lifecycle meta is left alone except clearing a plan-awaiting flag.
	 *
	 * @param int    $user_id User ID.
	 * @param string $reason  Optional rejection reason.
	 * @return bool
	 */
	public static function reject( $user_id, $reason = '' ) {
		$ok = self::set_approval_status( $user_id, self::STATUS_REJECTED );

		if ( ! $ok ) {
			return false;
		}

		$reason = sanitize_textarea_field( $reason );

		if ( '' === $reason ) {
			delete_user_meta( $user_id, 'cta_approval_rejection_reason' );
		} else {
			update_user_meta( $user_id, 'cta_approval_rejection_reason', $reason );
		}

		// Do not overwrite payment lifecycle with "rejected" — application axis owns that.
		if ( self::is_plan_awaiting_application_approval( $user_id ) ) {
			delete_user_meta( $user_id, self::META_SUPERVISION_PLAN_STATUS );
		}

		self::ensure_account_active( $user_id );
		clean_user_cache( $user_id );

		return true;
	}

	/**
	 * Get Associates currently awaiting approval.
	 *
	 * @param int $limit Max users to return.
	 * @return WP_User[]
	 */
	public static function get_pending_associates( $limit = 200 ) {
		return self::get_associates_for_approvals( self::STATUS_PENDING, $limit );
	}

	/**
	 * Get Associates for the admin approvals screen.
	 *
	 * Rejected Associates are never listed — only Pending and Approved.
	 *
	 * @param string $status Optional filter: pending_approval|approved|all|''.
	 * @param int    $limit  Max users to return.
	 * @return WP_User[]
	 */
	public static function get_associates_for_approvals( $status = 'all', $limit = 200 ) {
		$status  = sanitize_text_field( (string) $status );
		$visible = array(
			self::STATUS_PENDING,
			self::STATUS_APPROVED,
		);

		// Rejected (and any other status) are excluded from this screen.
		if ( self::STATUS_REJECTED === $status ) {
			return array();
		}

		$meta_query = array(
			array(
				'key'     => 'cta_approval_status',
				'value'   => $visible,
				'compare' => 'IN',
			),
		);

		if ( in_array( $status, $visible, true ) ) {
			$meta_query = array(
				array(
					'key'   => 'cta_approval_status',
					'value' => $status,
				),
			);
		}

		$query = new WP_User_Query(
			array(
				'role'       => 'cta_associate',
				'meta_query' => $meta_query,
				'number'     => absint( $limit ),
				'orderby'    => 'registered',
				'order'      => 'DESC',
			)
		);

		$users = $query->get_results();

		return $users ? $users : array();
	}

	/**
	 * Count Associates by approval status (for Approvals tabs).
	 *
	 * Rejected are counted internally but not exposed in the "all" total used by the UI.
	 *
	 * @return array{pending_approval:int,approved:int,all:int}
	 */
	public static function count_associates_by_approval_status() {
		$counts = array(
			self::STATUS_PENDING  => 0,
			self::STATUS_APPROVED => 0,
			'all'                 => 0,
		);

		foreach ( array( self::STATUS_PENDING, self::STATUS_APPROVED ) as $status ) {
			$query = new WP_User_Query(
				array(
					'role'        => 'cta_associate',
					'meta_key'    => 'cta_approval_status',
					'meta_value'  => $status,
					'fields'      => 'ID',
					'number'      => 1,
					'count_total' => true,
				)
			);
			$counts[ $status ] = (int) $query->get_total();
		}

		$counts['all'] = $counts[ self::STATUS_PENDING ] + $counts[ self::STATUS_APPROVED ];

		return $counts;
	}

	/**
	 * Human-readable approval status label.
	 *
	 * @param string $status Status slug.
	 * @return string
	 */
	public static function get_status_label( $status ) {
		switch ( $status ) {
			case self::STATUS_APPROVED:
				return __( 'Approved', 'cta-lms' );
			case self::STATUS_REJECTED:
				return __( 'Rejected', 'cta-lms' );
			case self::STATUS_PENDING:
			default:
				return __( 'Supervision Application Pending', 'cta-lms' );
		}
	}
}
}

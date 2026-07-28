<?php
/**
 * Centralized HTML email notifications for CTA LMS.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Emails
 */
if ( ! class_exists( 'CTA_Emails' ) ) {

class CTA_Emails {

	/**
	 * Register daily session reminder cron.
	 */
	public static function register_cron() {
		if ( ! wp_next_scheduled( 'cta_send_session_reminders' ) ) {
			wp_schedule_event( time(), 'daily', 'cta_send_session_reminders' );
		}
	}

	/**
	 * Send a typed email to a user.
	 *
	 * @param string $type    Email type slug.
	 * @param int    $user_id WordPress user ID.
	 * @param array  $data    Additional template data.
	 * @return bool
	 */
	public static function send( $type, $user_id, $data = array() ) {
		if ( self::is_configurable_type( $type ) && ! self::is_email_enabled( $type ) ) {
			return false;
		}

		$user = get_userdata( $user_id );

		if ( ! $user || ! is_email( $user->user_email ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( sprintf( 'CTA_Emails: user %d not found or invalid email for type %s', $user_id, $type ) );
			}
			return false;
		}

		switch ( $type ) {
			case 'welcome':
				return self::send_welcome( $user, $data );
			case 'enrollment_confirmation':
				return self::send_enrollment_confirmation( $user, $data );
			case 'booking_confirmation':
				return self::send_booking_confirmation( $user, $data );
			case 'session_reminder':
				return self::send_session_reminder( $user, $data );
			case 'certificate_ready':
				return self::send_certificate_ready( $user, $data );
			case 'payment_receipt':
				return self::send_payment_receipt( $user, $data );
			case 'payment_failed':
				return self::send_payment_failed( $user, $data );
			case 'supervision_locked':
				return self::send_supervision_locked( $user, $data );
			case 'agency_representative_approval':
				return self::send_agency_representative_approval( $user, $data );
			default:
				return false;
		}
	}

	/**
	 * Email types admins can manage.
	 *
	 * Subjects and bodies use {placeholder} tokens. Existing PHP templates remain
	 * the fallback until an admin saves a custom body.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_configurable_types() {
		return array(
			'welcome' => array(
				'label'           => __( 'Welcome Email', 'cta-lms' ),
				'description'     => __( 'Sent when a new student or Associate account is created.', 'cta-lms' ),
				'template'        => 'welcome',
				'default_subject' => __( 'Welcome to Clinical Training and Supervision Academy', 'cta-lms' ),
				'default_body'    => '<p>Hi {student_name},</p><h2>Welcome to Clinical Training and Supervision Academy!</h2><p>Your account has been created successfully.</p><div class="highlight-box"><p><strong>Account Type:</strong> {role_label}</p><p><strong>Email:</strong> {student_email}</p></div><p>You can now browse available learning and supervision services from your dashboard.</p><p><a class="btn-email" href="{dashboard_url}">Go to Dashboard</a></p><hr class="divider"><p><a href="{faq_url}">Visit our FAQ page</a></p>',
				'placeholders'    => array(
					'{student_name}'  => __( 'Student display name', 'cta-lms' ),
					'{student_email}' => __( 'Student email address', 'cta-lms' ),
					'{role_label}'    => __( 'Account type', 'cta-lms' ),
					'{dashboard_url}' => __( 'Student dashboard link', 'cta-lms' ),
					'{faq_url}'       => __( 'FAQ page link', 'cta-lms' ),
				),
			),
			'enrollment_confirmation' => array(
				'label'           => __( 'Enrollment Confirmation', 'cta-lms' ),
				'description'     => __( 'Sent after a student is enrolled in a course.', 'cta-lms' ),
				'template'        => 'enrollment-confirmation',
				'default_subject' => __( 'You\'re Enrolled — {course_name}', 'cta-lms' ),
				'default_body'    => '<p>Hi {student_name},</p><h2>You\'re enrolled!</h2><div class="highlight-box"><p><strong>Course:</strong> {course_name}</p><p><strong>CE Hours:</strong> {ce_hours}</p><p><strong>Payment:</strong> {payment_reference}</p><p><strong>Enrolled:</strong> {enrolled_date}</p></div><p>You can start with Module 1 and complete the course at your own pace.</p><p><a class="btn-email" href="{course_player_url}">Start Learning Now</a></p>',
				'placeholders'    => array(
					'{student_name}'     => __( 'Student display name', 'cta-lms' ),
					'{course_name}'      => __( 'Course title', 'cta-lms' ),
					'{ce_hours}'         => __( 'Course CE hours', 'cta-lms' ),
					'{payment_reference}'=> __( 'Short payment reference', 'cta-lms' ),
					'{enrolled_date}'    => __( 'Enrollment date', 'cta-lms' ),
					'{course_player_url}'=> __( 'Course player link', 'cta-lms' ),
				),
			),
			'booking_confirmation' => array(
				'label'           => __( 'Booking Confirmation', 'cta-lms' ),
				'description'     => __( 'Sent after an Associate books a supervision session.', 'cta-lms' ),
				'template'        => 'booking-confirmation',
				'default_subject' => __( 'Supervision Session Confirmed — {session_date}', 'cta-lms' ),
				'default_body'    => '<p>Hi {student_name},</p><h2>Your supervision session is confirmed!</h2><div class="highlight-box"><p><strong>Session Type:</strong> {session_type}</p><p><strong>Date:</strong> {session_date}</p><p><strong>Time:</strong> {session_time}</p><p><strong>Duration:</strong> {duration}</p><p><strong>Your spot:</strong> {seats_booked} of {seats_total}</p></div><p>Please prepare any cases you would like to discuss and join five minutes early.</p><p><a class="btn-email" href="{dashboard_url}">View My Sessions</a></p>',
				'placeholders'    => array(
					'{student_name}' => __( 'Associate display name', 'cta-lms' ),
					'{session_type}' => __( 'Group or individual session', 'cta-lms' ),
					'{session_date}' => __( 'Formatted session date', 'cta-lms' ),
					'{session_time}' => __( 'Formatted session time', 'cta-lms' ),
					'{duration}'     => __( 'Session duration', 'cta-lms' ),
					'{seats_booked}' => __( 'Number of booked seats', 'cta-lms' ),
					'{seats_total}'  => __( 'Total available seats', 'cta-lms' ),
					'{dashboard_url}'=> __( 'Supervision dashboard link', 'cta-lms' ),
				),
			),
			'session_reminder' => array(
				'label'           => __( 'Session Reminder', 'cta-lms' ),
				'description'     => __( 'Sent by the daily reminder task for sessions happening tomorrow.', 'cta-lms' ),
				'template'        => 'session-reminder',
				'default_subject' => __( 'Reminder: Your Supervision Session is Tomorrow', 'cta-lms' ),
				'default_body'    => '<p>Hi {student_name},</p><h2>Reminder: Your supervision session is tomorrow</h2><div class="highlight-box"><p><strong>Date:</strong> {session_date}</p><p><strong>Time:</strong> {session_time}</p><p><strong>Type:</strong> {session_type}</p><p><strong>Duration:</strong> {duration}</p></div><p>Please prepare your cases and test your video connection.</p><p><a class="btn-email" href="{dashboard_url}">View Session Details</a></p><hr class="divider"><p class="small-text">Need to cancel? You must cancel before {cancellation_deadline} to avoid being charged.</p>',
				'placeholders'    => array(
					'{student_name}'         => __( 'Associate display name', 'cta-lms' ),
					'{session_type}'         => __( 'Group or individual session', 'cta-lms' ),
					'{session_date}'         => __( 'Formatted session date', 'cta-lms' ),
					'{session_time}'         => __( 'Formatted session time', 'cta-lms' ),
					'{duration}'             => __( 'Session duration', 'cta-lms' ),
					'{cancellation_deadline}'=> __( 'Cancellation deadline', 'cta-lms' ),
					'{dashboard_url}'        => __( 'Supervision dashboard link', 'cta-lms' ),
				),
			),
			'certificate_ready' => array(
				'label'           => __( 'Certificate Ready', 'cta-lms' ),
				'description'     => __( 'Sent after a course certificate is generated.', 'cta-lms' ),
				'template'        => 'certificate-ready',
				'default_subject' => __( 'Your CE Certificate is Ready — {course_name}', 'cta-lms' ),
				'default_body'    => '<p>Hi {student_name},</p><h2>Your CE certificate is ready!</h2><p>Congratulations on completing your course. Your certificate is ready to download.</p><div class="highlight-box"><p><strong>Course:</strong> {course_name}</p><p><strong>CE Hours:</strong> {ce_hours}</p><p><strong>Certificate #:</strong> {certificate_number}</p><p><strong>Completion Date:</strong> {completion_date}</p></div><p><a class="btn-email" href="{certificate_url}">Download Certificate</a></p><p><a href="{dashboard_url}">View all my certificates</a></p>',
				'placeholders'    => array(
					'{student_name}'      => __( 'Student display name', 'cta-lms' ),
					'{course_name}'       => __( 'Course title', 'cta-lms' ),
					'{ce_hours}'          => __( 'Course CE hours', 'cta-lms' ),
					'{certificate_number}'=> __( 'Certificate number', 'cta-lms' ),
					'{completion_date}'   => __( 'Course completion date', 'cta-lms' ),
					'{certificate_url}'   => __( 'Certificate download link', 'cta-lms' ),
					'{dashboard_url}'     => __( 'Student dashboard link', 'cta-lms' ),
				),
			),
			'payment_receipt' => array(
				'label'           => __( 'Payment Receipt', 'cta-lms' ),
				'description'     => __( 'Sent after a successful course, bundle, or supervision payment.', 'cta-lms' ),
				'template'        => 'payment-receipt',
				'default_subject' => __( 'Payment Received — Thank You', 'cta-lms' ),
				'default_body'    => '<p>Hi {student_name},</p><h2>Payment received — thank you!</h2><div class="highlight-box"><p><strong>Item:</strong> {product_name}</p><p><strong>Amount:</strong> ${amount}</p><p><strong>Date:</strong> {payment_date}</p><p><strong>Transaction ID:</strong> {transaction_id}</p><p><strong>Status:</strong> Completed</p></div><p>Your access has been activated.</p><p><a class="btn-email" href="{dashboard_url}">Access Your Content</a></p><hr class="divider"><p class="small-text">For billing questions contact {support_email}.</p>',
				'placeholders'    => array(
					'{student_name}' => __( 'Customer display name', 'cta-lms' ),
					'{product_name}' => __( 'Purchased item or plan', 'cta-lms' ),
					'{amount}'       => __( 'Payment amount', 'cta-lms' ),
					'{payment_date}' => __( 'Payment date', 'cta-lms' ),
					'{transaction_id}'=> __( 'Short transaction reference', 'cta-lms' ),
					'{dashboard_url}'=> __( 'Customer dashboard link', 'cta-lms' ),
					'{support_email}'=> __( 'Support email address', 'cta-lms' ),
				),
			),
			'payment_failed' => array(
				'label'           => __( 'Payment Failed', 'cta-lms' ),
				'description'     => __( 'Sent when Stripe reports a failed subscription payment.', 'cta-lms' ),
				'template'        => 'payment-failed',
				'default_subject' => __( 'Action Required: Payment Failed', 'cta-lms' ),
				'default_body'    => '<p>Hi {student_name},</p><h2>Action required: Payment failed</h2><div class="warning-box"><p>We were unable to process your payment for {subscription_plan}. Your supervision access has been temporarily suspended.</p></div><p>Please update your payment method to restore access.</p><p><a class="btn-email" href="{billing_portal_url}">Update Payment Method</a></p><hr class="divider"><p class="small-text">If you need assistance contact us at {support_email}.</p>',
				'placeholders'    => array(
					'{student_name}'      => __( 'Customer display name', 'cta-lms' ),
					'{subscription_plan}' => __( 'Subscription plan name', 'cta-lms' ),
					'{billing_portal_url}'=> __( 'Stripe billing portal link', 'cta-lms' ),
					'{support_email}'     => __( 'Support email address', 'cta-lms' ),
				),
			),
			'supervision_locked' => array(
				'label'           => __( 'Supervision Access Locked', 'cta-lms' ),
				'description'     => __( 'Sent when a supervision subscription is cancelled and access is paused.', 'cta-lms' ),
				'template'        => 'supervision-access-locked',
				'default_subject' => __( 'Your Supervision Access Has Been Paused', 'cta-lms' ),
				'default_body'    => '<p>Hi {student_name},</p><h2>Your supervision access has been paused</h2><p>Your supervision subscription has been cancelled or a recent payment could not be processed.</p><p>You cannot book new sessions, but your session history and uploaded documents are preserved.</p><p><a class="btn-email" href="{supervision_url}">Reactivate Supervision</a></p><hr class="divider"><p class="small-text">Questions? Contact us at {support_email}.</p>',
				'placeholders'    => array(
					'{student_name}'  => __( 'Associate display name', 'cta-lms' ),
					'{supervision_url}'=> __( 'Supervision purchase page link', 'cta-lms' ),
					'{support_email}' => __( 'Support email address', 'cta-lms' ),
				),
			),
		);
	}

	/**
	 * Saved values for one configurable email.
	 *
	 * @param string $type Email type.
	 * @return array{enabled:bool,subject:string,body:string}
	 */
	public static function get_email_settings( $type ) {
		$types = self::get_configurable_types();

		if ( ! isset( $types[ $type ] ) ) {
			return array(
				'enabled' => true,
				'subject' => '',
				'body'    => '',
			);
		}

		return array(
			'enabled' => 'no' !== get_option( self::get_email_option_key( $type, 'enabled' ), 'yes' ),
			'subject' => (string) get_option( self::get_email_option_key( $type, 'subject' ), '' ),
			'body'    => (string) get_option( self::get_email_option_key( $type, 'body' ), '' ),
		);
	}

	/**
	 * Build a full HTML preview using safe sample data.
	 *
	 * @param string $type    Email type.
	 * @param string $subject Subject entered in admin.
	 * @param string $body    Body entered in admin.
	 * @return array|WP_Error
	 */
	public static function preview_email( $type, $subject = '', $body = '' ) {
		$types = self::get_configurable_types();

		if ( ! isset( $types[ $type ] ) ) {
			return new WP_Error( 'invalid_email_type', __( 'Invalid email type.', 'cta-lms' ) );
		}

		$user = (object) array(
			'ID'           => 1,
			'display_name' => 'Alex Morgan',
			'user_email'   => 'alex@example.com',
			'roles'        => array( 'cta_licensed_professional' ),
		);
		$vars = self::get_preview_vars( $type, $user );
		$vars['user'] = $user;

		$subject = $subject ? $subject : $types[ $type ]['default_subject'];
		$body    = $body ? $body : $types[ $type ]['default_body'];
		$subject = self::replace_placeholders( $subject, self::build_placeholder_values( $user, $vars, false ) );
		$body    = self::replace_placeholders( $body, self::build_placeholder_values( $user, $vars, true ) );

		$vars['email_subject'] = $subject;

		return array(
			'subject' => $subject,
			'html'    => self::render( $types[ $type ]['template'], $vars, $body ),
		);
	}

	/**
	 * Send daily session reminder emails.
	 */
	public static function send_daily_reminders() {
		global $wpdb;

		$tomorrow = cta_lms_current_date( 'Y-m-d' );
		try {
			$tomorrow_dt = new DateTimeImmutable( 'now', cta_lms_get_timezone() );
			$tomorrow    = $tomorrow_dt->modify( '+1 day' )->format( 'Y-m-d' );
		} catch ( Exception $e ) {
			$tomorrow = cta_lms_current_date( 'Y-m-d' );
			try {
				$fallback = new DateTimeImmutable( $tomorrow . ' 12:00:00', cta_lms_get_timezone() );
				$tomorrow = $fallback->modify( '+1 day' )->format( 'Y-m-d' );
			} catch ( Exception $ignored ) {
				$tomorrow = gmdate( 'Y-m-d', time() + DAY_IN_SECONDS );
			}
		}

		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}cta_bookings
				WHERE user_id > 0
				AND status = 'confirmed'
				AND session_date = %s",
				$tomorrow
			)
		);

		$sent = 0;

		foreach ( $bookings as $booking ) {
			if ( self::send( 'session_reminder', (int) $booking->user_id, array( 'session' => $booking ) ) ) {
				++$sent;
			}
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'CTA_Emails: sent %d session reminder(s) for %s', $sent, $tomorrow ) );
		}
	}

	/**
	 * Welcome email for new registrations.
	 *
	 * @param WP_User $user User object.
	 * @param array   $data Extra data.
	 * @return bool
	 */
	private static function send_welcome( $user, $data ) {
		$subject = __( 'Welcome to Clinical Training and Supervision Academy', 'cta-lms' );

		return self::deliver(
			$user,
			$subject,
			'welcome',
			array(
				'role_label'     => self::get_role_label( $user ),
				'dashboard_url'  => self::get_dashboard_url( $user ),
				'faq_url'        => self::get_page_url( 'cta_faq_page_id' ),
				'is_associate'   => in_array( 'cta_associate', (array) $user->roles, true ),
			)
		);
	}

	/**
	 * Enrollment confirmation email.
	 *
	 * @param WP_User $user User object.
	 * @param array   $data Must include course_id; optional payment_id.
	 * @return bool
	 */
	private static function send_enrollment_confirmation( $user, $data ) {
		$course_id = absint( $data['course_id'] ?? 0 );
		$course    = CTA_Database::get_course( $course_id );

		if ( ! $course ) {
			return false;
		}

		$subject = sprintf(
			/* translators: %s: course title */
			__( 'You\'re Enrolled — %s', 'cta-lms' ),
			$course->title
		);

		return self::deliver(
			$user,
			$subject,
			'enrollment-confirmation',
			array(
				'course'            => $course,
				'payment_id'        => sanitize_text_field( $data['payment_id'] ?? '' ),
				'payment_reference' => self::format_payment_reference( $data['payment_id'] ?? '' ),
				'ce_hours'          => self::format_ce_hours( $course ),
				'enrolled_date'     => cta_lms_format_local_date( null, 'F j, Y' ),
				'player_url'        => self::get_course_player_url( $course_id ),
			)
		);
	}

	/**
	 * Booking confirmation email.
	 *
	 * @param WP_User $user User object.
	 * @param array   $data Must include session; optional session_type.
	 * @return bool
	 */
	private static function send_booking_confirmation( $user, $data ) {
		$session = $data['session'] ?? null;

		if ( ! $session ) {
			return false;
		}

		$session_type = sanitize_text_field( $data['session_type'] ?? $session->session_type ?? 'group' );
		$subject      = sprintf(
			/* translators: %s: session date */
			__( 'Supervision Session Confirmed — %s', 'cta-lms' ),
			self::format_session_date( $session->session_date )
		);

		return self::deliver(
			$user,
			$subject,
			'booking-confirmation',
			array(
				'session'           => $session,
				'session_type'      => $session_type,
				'session_type_label'=> 'group' === $session_type ? __( 'Group Supervision', 'cta-lms' ) : __( 'Individual Supervision', 'cta-lms' ),
				'session_date'      => self::format_session_date( $session->session_date ),
				'session_time'      => self::format_session_time( $session->session_time, $session->session_date ),
				'duration_label'    => 'group' === $session_type ? __( '2 hours', 'cta-lms' ) : __( '1 hour', 'cta-lms' ),
				'dashboard_url'     => self::get_page_url( 'cta_supervision_dashboard_page_id' ),
			)
		);
	}

	/**
	 * Session reminder email (24 hours before).
	 *
	 * @param WP_User $user User object.
	 * @param array   $data Must include session booking object.
	 * @return bool
	 */
	private static function send_session_reminder( $user, $data ) {
		$session = $data['session'] ?? null;

		if ( ! $session ) {
			return false;
		}

		$session_type = sanitize_text_field( $session->session_type ?? 'group' );
		$subject      = __( 'Reminder: Your Supervision Session is Tomorrow', 'cta-lms' );

		return self::deliver(
			$user,
			$subject,
			'session-reminder',
			array(
				'session'                => $session,
				'session_type_label'     => 'group' === $session_type ? __( 'Group Supervision', 'cta-lms' ) : __( 'Individual Supervision', 'cta-lms' ),
				'session_date'           => self::format_session_date( $session->session_date ),
				'session_time'           => self::format_session_time( $session->session_time, $session->session_date ),
				'duration_label'         => (int) $session->duration_mins . ' ' . __( 'minutes', 'cta-lms' ),
				'cancellation_deadline'  => self::get_cancellation_deadline( $session ),
				'dashboard_url'          => self::get_page_url( 'cta_supervision_dashboard_page_id' ),
			)
		);
	}

	/**
	 * Certificate ready email.
	 *
	 * @param WP_User $user User object.
	 * @param array   $data Must include course and certificate objects.
	 * @return bool
	 */
	private static function send_certificate_ready( $user, $data ) {
		$course      = $data['course'] ?? null;
		$certificate = $data['certificate'] ?? null;

		if ( ! $course || ! $certificate ) {
			return false;
		}

		$evaluation = null;
		if ( class_exists( 'CTA_Database' ) ) {
			$evaluation = CTA_Database::get_course_evaluation( (int) $user->ID, (int) $course->id );
		}

		$completion_date = '';
		if ( class_exists( 'CTA_Certificates' ) ) {
			$completion_date = CTA_Certificates::format_issue_date(
				! empty( $certificate->issued_at ) ? (string) $certificate->issued_at : '',
				$evaluation
			);
		}
		if ( '' === $completion_date && ! empty( $certificate->issued_at ) ) {
			$completion_date = cta_lms_format_local_date(
				$certificate->issued_at,
				'F j, Y \a\t g:i A T',
				cta_lms_get_timezone()
			);
		}

		$subject = sprintf(
			/* translators: %s: course title */
			__( 'Your CE Certificate is Ready — %s', 'cta-lms' ),
			$course->title
		);

		$certificate_url = class_exists( 'CTA_Certificates' )
			? CTA_Certificates::get_print_url( (int) $certificate->id, true )
			: CTA_Database::get_certificate_url( $certificate );

		return self::deliver(
			$user,
			$subject,
			'certificate-ready',
			array(
				'course'             => $course,
				'certificate'        => $certificate,
				'ce_hours'           => self::format_ce_hours( $course ),
				'certificate_url'    => $certificate_url,
				'completion_date'    => $completion_date,
				'dashboard_url'      => self::get_page_url( 'cta_student_dashboard_page_id' ),
			)
		);
	}

	/**
	 * Payment receipt email.
	 *
	 * @param WP_User $user User object.
	 * @param array   $data Must include payment object or payment_id; optional product_name.
	 * @return bool
	 */
	private static function send_payment_receipt( $user, $data ) {
		$payment = $data['payment'] ?? null;

		if ( ! $payment && ! empty( $data['payment_id'] ) ) {
			global $wpdb;
			$payment = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}cta_payments WHERE stripe_payment_id = %s OR id = %d LIMIT 1",
					sanitize_text_field( $data['payment_id'] ),
					absint( $data['payment_id'] )
				)
			);
		}

		if ( ! $payment ) {
			return false;
		}

		$product_name = sanitize_text_field( $data['product_name'] ?? __( 'CTA Purchase', 'cta-lms' ) );
		$subject      = __( 'Payment Received — Thank You', 'cta-lms' );

		return self::deliver(
			$user,
			$subject,
			'payment-receipt',
			array(
				'payment'            => $payment,
				'product_name'       => $product_name,
				'amount'             => number_format( (float) $payment->amount, 2 ),
				'payment_date'       => ! empty( $payment->created_at ) ? cta_lms_format_local_date( $payment->created_at, 'F j, Y' ) : cta_lms_format_local_date( null, 'F j, Y' ),
				'transaction_ref'    => self::format_transaction_reference( $payment->stripe_payment_id ?? '' ),
				'dashboard_url'      => self::get_dashboard_url( $user ),
				'support_email'      => self::get_support_email(),
			)
		);
	}

	/**
	 * Payment failed email for supervision subscriptions.
	 *
	 * @param WP_User $user User object.
	 * @param array   $data Optional subscription_plan label.
	 * @return bool
	 */
	private static function send_payment_failed( $user, $data ) {
		$plan = sanitize_text_field( $data['subscription_plan'] ?? __( 'Supervision Subscription', 'cta-lms' ) );
		$subject = __( 'Action Required: Payment Failed', 'cta-lms' );

		return self::deliver(
			$user,
			$subject,
			'payment-failed',
			array(
				'subscription_plan' => $plan,
				'portal_url'        => self::get_billing_portal_url( $user->ID ),
				'support_email'     => self::get_support_email(),
			)
		);
	}

	/**
	 * Supervision access locked email.
	 *
	 * @param WP_User $user User object.
	 * @param array   $data Extra data.
	 * @return bool
	 */
	private static function send_supervision_locked( $user, $data ) {
		unset( $data );
		$subject = __( 'Your Supervision Access Has Been Paused', 'cta-lms' );

		return self::deliver(
			$user,
			$subject,
			'supervision-access-locked',
			array(
				'supervision_url' => self::get_page_url( 'cta_supervision_page_id' ),
				'support_email'   => self::get_support_email(),
			)
		);
	}

	/**
	 * Email agency representative with approval documents for signature.
	 *
	 * Sent when an Associate submits a supervision application (with agency details).
	 *
	 * @param WP_User $user Associate user.
	 * @param array   $data Optional overrides.
	 * @return bool
	 */
	private static function send_agency_representative_approval( $user, $data ) {
		$approval_status = (string) get_user_meta( $user->ID, 'cta_approval_status', true );

		if ( 'pending_approval' !== $approval_status ) {
			return false;
		}

		$rep_email = sanitize_email(
			$data['agency_representative_email'] ?? get_user_meta( $user->ID, 'cta_agency_representative_email', true )
		);
		$rep_name  = sanitize_text_field(
			$data['agency_representative_name'] ?? get_user_meta( $user->ID, 'cta_agency_representative_name', true )
		);
		$agency    = sanitize_text_field(
			$data['employer_agency_name'] ?? get_user_meta( $user->ID, 'cta_employer_agency_name', true )
		);

		if ( ! is_email( $rep_email ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( sprintf( 'CTA_Emails: invalid agency representative email for user %d', $user->ID ) );
			}
			return false;
		}

		$documents   = self::get_agency_approval_documents();
		$attachments = array();

		foreach ( $documents as $document ) {
			if ( ! empty( $document['path'] ) && file_exists( $document['path'] ) ) {
				$attachments[] = $document['path'];
			}
		}

		$subject = __( 'Action Required: CTA Associate Approval Documents for Signature', 'cta-lms' );

		return self::deliver_to(
			$rep_email,
			$subject,
			'agency-representative-approval',
			array(
				'user'                         => $user,
				'rep_name'                     => $rep_name,
				'agency_name'                  => $agency,
				'associate_name'               => $user->display_name,
				'associate_email'              => $user->user_email,
				'documents'                    => $documents,
				'support_email'                => self::get_support_email(),
			),
			$attachments
		);
	}

	/**
	 * Paths and labels for agency approval document attachments.
	 *
	 * @return array<int, array{label: string, path: string, url: string, filename: string}>
	 */
	public static function get_agency_approval_documents() {
		$docs = array(
			array(
				'label'    => __( 'Associate Intake & Regulatory Clearance Packet', 'cta-lms' ),
				'filename' => 'associate-intake-regulatory-clearance-packet.pdf',
			),
			array(
				'label'    => __( 'CTA Employer Oversight Agreement', 'cta-lms' ),
				'filename' => 'cta-employer-oversight-agreement.pdf',
			),
		);

		foreach ( $docs as &$doc ) {
			$doc['path'] = CTA_PLUGIN_DIR . 'assets/documents/' . $doc['filename'];
			$doc['url']  = CTA_PLUGIN_URL . 'assets/documents/' . $doc['filename'];
		}
		unset( $doc );

		return $docs;
	}

	/**
	 * Render email HTML using base wrapper.
	 *
	 * @param string $template    Template slug without .php.
	 * @param array  $vars        Template variables.
	 * @param string $custom_body Optional customized body HTML.
	 * @return string
	 */
	private static function render( $template, $vars = array(), $custom_body = '' ) {
		$vars['template']      = $template;
		$vars['email_subject'] = $vars['email_subject'] ?? 'CTA';
		$vars['logo_url']      = self::get_logo_url();
		$vars['custom_body']   = $custom_body;

		ob_start();
		extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		include CTA_PLUGIN_DIR . 'templates/emails/base.php';
		return ob_get_clean();
	}

	/**
	 * Send rendered HTML email to a WordPress user.
	 *
	 * @param WP_User $user        Recipient.
	 * @param string  $subject     Email subject.
	 * @param string  $template    Template slug.
	 * @param array   $vars        Template variables.
	 * @param array   $attachments Optional file paths.
	 * @return bool
	 */
	private static function deliver( $user, $subject, $template, $vars, $attachments = array() ) {
		$vars['user'] = $user;
		return self::deliver_to( $user->user_email, $subject, $template, $vars, $attachments );
	}

	/**
	 * Send rendered HTML email to an arbitrary address.
	 *
	 * @param string $to_email     Recipient email.
	 * @param string $subject      Email subject.
	 * @param string $template     Template slug.
	 * @param array  $vars         Template variables.
	 * @param array  $attachments  Optional absolute file paths.
	 * @return bool
	 */
	private static function deliver_to( $to_email, $subject, $template, $vars, $attachments = array() ) {
		$to_email = sanitize_email( $to_email );

		if ( ! is_email( $to_email ) ) {
			return false;
		}

		$custom_body = '';
		$type        = self::get_type_for_template( $template );

		if ( $type ) {
			$settings = self::get_email_settings( $type );

			if ( ! $settings['enabled'] ) {
				return false;
			}

			$user   = isset( $vars['user'] ) && is_object( $vars['user'] )
				? $vars['user']
				: (object) array(
					'ID'           => 0,
					'display_name' => '',
					'user_email'   => $to_email,
					'roles'        => array(),
				);
			if ( '' !== $settings['subject'] ) {
				$subject = self::replace_placeholders(
					$settings['subject'],
					self::build_placeholder_values( $user, $vars, false )
				);
			}

			if ( '' !== $settings['body'] ) {
				$custom_body = self::replace_placeholders(
					$settings['body'],
					self::build_placeholder_values( $user, $vars, true )
				);
			}
		}

		$vars['email_subject'] = $subject;
		$html                  = self::render( $template, $vars, $custom_body );
		$attachments           = array_values(
			array_filter(
				(array) $attachments,
				static function ( $path ) {
					return is_string( $path ) && $path && file_exists( $path ) && is_readable( $path );
				}
			)
		);

		$has_attachments = ! empty( $attachments );
		$headers         = self::get_headers( $has_attachments );

		if ( $has_attachments ) {
			add_action( 'phpmailer_init', array( __CLASS__, 'configure_phpmailer_html' ) );
		}

		$sent = wp_mail( $to_email, $subject, $html, $headers, $attachments );

		if ( $has_attachments ) {
			remove_action( 'phpmailer_init', array( __CLASS__, 'configure_phpmailer_html' ) );
		}

		// Attachments + HTML often fail on hosts; retry without files so the message still arrives.
		if ( ! $sent && $has_attachments ) {
			$sent = wp_mail( $to_email, $subject, $html, self::get_headers( false ) );
		}

		if ( ! $sent && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'CTA_Emails: wp_mail failed for %s (%s)', $to_email, $template ) );
		}

		return (bool) $sent;
	}

	/**
	 * Whether an email type is managed by Email Settings.
	 *
	 * @param string $type Email type.
	 * @return bool
	 */
	private static function is_configurable_type( $type ) {
		$types = self::get_configurable_types();
		return isset( $types[ $type ] );
	}

	/**
	 * Whether a configurable email is enabled.
	 *
	 * @param string $type Email type.
	 * @return bool
	 */
	private static function is_email_enabled( $type ) {
		$settings = self::get_email_settings( $type );
		return $settings['enabled'];
	}

	/**
	 * Option key for an email setting.
	 *
	 * @param string $type  Email type.
	 * @param string $field enabled|subject|body.
	 * @return string
	 */
	public static function get_email_option_key( $type, $field ) {
		return 'cta_email_' . sanitize_key( $type ) . '_' . sanitize_key( $field );
	}

	/**
	 * Find a configurable type by its legacy PHP template.
	 *
	 * @param string $template Template slug.
	 * @return string
	 */
	private static function get_type_for_template( $template ) {
		foreach ( self::get_configurable_types() as $type => $config ) {
			if ( $template === $config['template'] ) {
				return $type;
			}
		}

		return '';
	}

	/**
	 * Replace supported {placeholder} tokens.
	 *
	 * @param string $content Content containing placeholders.
	 * @param array  $values  Placeholder/value map.
	 * @return string
	 */
	private static function replace_placeholders( $content, $values ) {
		$replacements = $values;

		// Visual editors may URL-encode braces when a placeholder is used in href.
		foreach ( $values as $placeholder => $value ) {
			$replacements[ rawurlencode( $placeholder ) ] = $value;
		}

		return strtr( (string) $content, $replacements );
	}

	/**
	 * Build escaped placeholder values from the normal email template variables.
	 *
	 * @param object $user        Recipient user-like object.
	 * @param array  $vars        Template variables.
	 * @param bool   $escape_html Whether values will be inserted into HTML.
	 * @return array<string,string>
	 */
	private static function build_placeholder_values( $user, $vars, $escape_html = true ) {
		$course      = isset( $vars['course'] ) && is_object( $vars['course'] ) ? $vars['course'] : null;
		$certificate = isset( $vars['certificate'] ) && is_object( $vars['certificate'] ) ? $vars['certificate'] : null;
		$payment     = isset( $vars['payment'] ) && is_object( $vars['payment'] ) ? $vars['payment'] : null;
		$session     = isset( $vars['session'] ) && is_object( $vars['session'] ) ? $vars['session'] : null;
		$raw         = array(
			'{student_name}'          => $user->display_name ?? '',
			'{student_email}'         => $user->user_email ?? '',
			'{role_label}'            => $vars['role_label'] ?? '',
			'{dashboard_url}'         => $vars['dashboard_url'] ?? '',
			'{faq_url}'               => $vars['faq_url'] ?? '',
			'{course_name}'           => $course->title ?? '',
			'{ce_hours}'              => $vars['ce_hours'] ?? '',
			'{payment_reference}'     => $vars['payment_reference'] ?? '',
			'{enrolled_date}'         => $vars['enrolled_date'] ?? '',
			'{course_player_url}'     => $vars['player_url'] ?? '',
			'{session_type}'          => $vars['session_type_label'] ?? '',
			'{session_date}'          => $vars['session_date'] ?? '',
			'{session_time}'          => $vars['session_time'] ?? '',
			'{duration}'              => $vars['duration_label'] ?? '',
			'{seats_booked}'          => $session->seats_booked ?? '',
			'{seats_total}'           => $session->seats_total ?? '',
			'{cancellation_deadline}' => $vars['cancellation_deadline'] ?? '',
			'{certificate_number}'    => $certificate->certificate_number ?? '',
			'{certificate_url}'       => $vars['certificate_url'] ?? '',
			'{completion_date}'       => $vars['completion_date'] ?? '',
			'{product_name}'          => $vars['product_name'] ?? '',
			'{amount}'                => $vars['amount'] ?? ( $payment->amount ?? '' ),
			'{payment_date}'          => $vars['payment_date'] ?? '',
			'{transaction_id}'        => $vars['transaction_ref'] ?? '',
			'{subscription_plan}'     => $vars['subscription_plan'] ?? '',
			'{billing_portal_url}'    => $vars['portal_url'] ?? '',
			'{supervision_url}'       => $vars['supervision_url'] ?? '',
			'{support_email}'         => $vars['support_email'] ?? self::get_support_email(),
			'{program_admin_name}'    => get_option( 'cta_admin_name', 'Clinical Training and Supervision Academy' ),
		);
		$values      = array();

		foreach ( $raw as $placeholder => $value ) {
			$values[ $placeholder ] = $escape_html
				? esc_html( (string) $value )
				: sanitize_text_field( (string) $value );
		}

		return $values;
	}

	/**
	 * Sample variables used by the admin preview.
	 *
	 * @param string $type Email type.
	 * @param object $user Sample recipient.
	 * @return array
	 */
	private static function get_preview_vars( $type, $user ) {
		$course = (object) array(
			'title'    => 'Law & Ethics: 2026 Update',
			'ce_hours' => 3,
		);
		$session = (object) array(
			'session_type' => 'group',
			'session_date' => ( new DateTimeImmutable( 'now', cta_lms_get_timezone() ) )->modify( '+1 day' )->format( 'Y-m-d' ),
			'session_time' => '10:00:00',
			'duration_mins'=> 120,
			'seats_booked' => 4,
			'seats_total'  => 8,
		);
		$certificate = (object) array(
			'certificate_number' => 'CTA-' . cta_lms_current_date( 'Y' ) . '-123456',
			'issued_at'           => current_time( 'mysql' ),
		);
		$payment = (object) array(
			'amount'            => '139.00',
			'created_at'        => current_time( 'mysql' ),
			'stripe_payment_id' => 'pi_sample123456789',
		);
		$common = array(
			'role_label'           => __( 'Licensed Professional (CE)', 'cta-lms' ),
			'dashboard_url'        => home_url( '/student-dashboard/' ),
			'faq_url'              => home_url( '/faq/' ),
			'course'               => $course,
			'ce_hours'             => '3',
			'payment_reference'    => '#12345678',
			'enrolled_date'        => cta_lms_format_local_date( null, 'F j, Y' ),
			'player_url'           => home_url( '/course-player/?course_id=1' ),
			'session'              => $session,
			'session_type_label'   => __( 'Group Supervision', 'cta-lms' ),
			'session_date'         => self::format_session_date( $session->session_date ),
			'session_time'         => self::format_session_time( $session->session_time, $session->session_date ),
			'duration_label'       => __( '2 hours', 'cta-lms' ),
			'cancellation_deadline'=> self::get_cancellation_deadline( $session ),
			'certificate'          => $certificate,
			'certificate_url'      => home_url( '/sample-certificate/' ),
			'completion_date'      => cta_lms_format_local_date( null, 'F j, Y' ),
			'product_name'         => __( 'Annual CE Bundle', 'cta-lms' ),
			'payment'              => $payment,
			'amount'               => '139.00',
			'payment_date'         => cta_lms_format_local_date( null, 'F j, Y' ),
			'transaction_ref'      => 'sample123456',
			'subscription_plan'    => class_exists( 'CTA_Supervision_Plans' )
				? CTA_Supervision_Plans::get_name( CTA_Supervision_Plans::GROUP_SLUG )
				: 'Group Supervision',
			'portal_url'           => home_url( '/billing-portal/' ),
			'supervision_url'      => home_url( '/supervision/' ),
			'support_email'        => self::get_support_email(),
		);

		unset( $type, $user );
		return $common;
	}

	/**
	 * Ensure PHPMailer sends HTML when attachments are present.
	 *
	 * @param PHPMailer $phpmailer Mailer instance.
	 */
	public static function configure_phpmailer_html( $phpmailer ) {
		if ( is_object( $phpmailer ) && method_exists( $phpmailer, 'isHTML' ) ) {
			$phpmailer->isHTML( true );
			$phpmailer->CharSet = 'UTF-8';
		}
	}

	/**
	 * Email headers with branded From address.
	 *
	 * @param bool $for_attachments When true, omit Content-Type so wp_mail can build multipart.
	 * @return array
	 */
	public static function get_headers( $for_attachments = false ) {
		$from_name  = get_option( 'cta_admin_name', 'Clinical Training and Supervision Academy' );
		$from_email = self::get_support_email();

		$headers = array(
			'From: ' . $from_name . ' <' . $from_email . '>',
			'Reply-To: ' . $from_email,
		);

		if ( ! $for_attachments ) {
			array_unshift( $headers, 'Content-Type: text/html; charset=UTF-8' );
		}

		return $headers;
	}

	/**
	 * Support email address.
	 *
	 * @return string
	 */
	public static function get_support_email() {
		$email = get_option( 'cta_support_email', 'support@clinicaltrainingacademy.com' );
		return is_email( $email ) ? $email : 'support@clinicaltrainingacademy.com';
	}

	/**
	 * Logo URL for email header.
	 *
	 * @return string
	 */
	private static function get_logo_url() {
		return cta_lms_get_logo_url( 'white' );
	}

	/**
	 * Frontend client dashboard URL based on user role.
	 *
	 * @param WP_User $user User object.
	 * @return string
	 */
	public static function get_dashboard_url( $user ) {
		if ( class_exists( 'CTA_Associate_Access' ) ) {
			$url = CTA_Associate_Access::get_general_dashboard_url( (int) $user->ID );
			if ( $url ) {
				return $url;
			}
		}

		$roles = (array) $user->roles;

		if ( in_array( 'cta_licensed_professional', $roles, true ) || in_array( 'cta_associate', $roles, true ) ) {
			return self::get_page_url( 'cta_student_dashboard_page_id' );
		}

		// Prefer frontend client dashboards for admins and other roles.
		$student = self::get_page_url( 'cta_student_dashboard_page_id' );
		if ( $student && untrailingslashit( $student ) !== untrailingslashit( home_url( '/' ) ) ) {
			return $student;
		}

		return home_url( '/' );
	}

	/**
	 * Get permalink from plugin page option.
	 *
	 * @param string $option_name Option key.
	 * @return string
	 */
	public static function get_page_url( $option_name ) {
		$page_id = absint( get_option( $option_name, 0 ) );

		if ( ! $page_id ) {
			return home_url( '/' );
		}

		$url = get_permalink( $page_id );

		return $url ? $url : home_url( '/' );
	}

	/**
	 * Course player URL for a course.
	 *
	 * @param int $course_id Course ID.
	 * @return string
	 */
	public static function get_course_player_url( $course_id ) {
		$base = self::get_page_url( 'cta_course_player_page_id' );
		return add_query_arg( 'course_id', absint( $course_id ), $base );
	}

	/**
	 * Human-readable role label.
	 *
	 * @param WP_User $user User object.
	 * @return string
	 */
	private static function get_role_label( $user ) {
		$roles = (array) $user->roles;

		if ( in_array( 'cta_associate', $roles, true ) ) {
			return __( 'Associate (Supervision)', 'cta-lms' );
		}

		if ( in_array( 'cta_licensed_professional', $roles, true ) ) {
			return __( 'Licensed Professional (CE)', 'cta-lms' );
		}

		if ( in_array( 'administrator', $roles, true ) ) {
			return __( 'Administrator', 'cta-lms' );
		}

		return __( 'Student', 'cta-lms' );
	}

	/**
	 * Format CE hours for display.
	 *
	 * @param object $course Course row.
	 * @return string
	 */
	private static function format_ce_hours( $course ) {
		return rtrim( rtrim( number_format( (float) $course->ce_hours, 1, '.', '' ), '0' ), '.' );
	}

	/**
	 * Format payment ID reference (last 8 chars).
	 *
	 * @param string $payment_id Payment reference.
	 * @return string
	 */
	private static function format_payment_reference( $payment_id ) {
		$payment_id = sanitize_text_field( $payment_id );
		if ( strlen( $payment_id ) <= 8 ) {
			return $payment_id ? '#' . $payment_id : '—';
		}
		return '#' . substr( $payment_id, -8 );
	}

	/**
	 * Format Stripe transaction reference (last 12 chars).
	 *
	 * @param string $transaction_id Stripe ID.
	 * @return string
	 */
	private static function format_transaction_reference( $transaction_id ) {
		$transaction_id = sanitize_text_field( $transaction_id );
		if ( strlen( $transaction_id ) <= 12 ) {
			return $transaction_id ? $transaction_id : '—';
		}
		return substr( $transaction_id, -12 );
	}

	/**
	 * Format session date.
	 *
	 * @param string $date Date string.
	 * @return string
	 */
	private static function format_session_date( $date ) {
		return cta_lms_format_session_date( $date, 'l, F j, Y' );
	}

	/**
	 * Format session time with timezone abbreviation (PST/PDT).
	 *
	 * @param string $time Time string.
	 * @param string $date Optional session date for DST-accurate abbreviation.
	 * @return string
	 */
	private static function format_session_time( $time, $date = '' ) {
		if ( '' === $date ) {
			$date = cta_lms_current_date( 'Y-m-d' );
		}

		return cta_lms_format_session_time( $date, $time, 'g:i A T' );
	}

	/**
	 * Cancellation deadline (24 hours before session).
	 *
	 * @param object $session Booking row.
	 * @return string
	 */
	private static function get_cancellation_deadline( $session ) {
		$dt = cta_lms_session_datetime( $session->session_date, $session->session_time );

		if ( ! $dt ) {
			return '';
		}

		$deadline = $dt->modify( '-1 day' );

		return cta_lms_date( 'F j, Y g:i A T', $deadline->getTimestamp(), cta_lms_get_timezone() );
	}

	/**
	 * Create Stripe billing portal URL for a user.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private static function get_billing_portal_url( $user_id ) {
		$fallback = self::get_page_url( 'cta_supervision_dashboard_page_id' );
		$stripe   = function_exists( 'cta_get_stripe' ) ? cta_get_stripe() : null;

		if ( ! $stripe || ! $stripe->is_configured() ) {
			return $fallback;
		}

		$result = $stripe->create_billing_portal_session( $user_id, $fallback ? $fallback : home_url( '/' ) );

		if ( is_wp_error( $result ) ) {
			return $fallback;
		}

		return $result;
	}
}
}
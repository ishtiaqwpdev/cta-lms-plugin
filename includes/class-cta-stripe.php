<?php
/**
 * Stripe payment integration for courses and supervision subscriptions.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Stripe
 */
if ( ! class_exists( 'CTA_Stripe' ) ) {

class CTA_Stripe {

	/**
	 * Stripe secret key.
	 *
	 * @var string
	 */
	private $secret_key;

	/**
	 * Stripe publishable key.
	 *
	 * @var string
	 */
	private $publishable_key;

	/**
	 * Stripe webhook signing secret.
	 *
	 * @var string
	 */
	private $webhook_secret;

	/**
	 * Stripe mode (test|live).
	 *
	 * @var string
	 */
	private $mode;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->secret_key      = (string) get_option( 'cta_stripe_secret_key', '' );
		$this->publishable_key = (string) get_option( 'cta_stripe_publishable_key', '' );
		$this->webhook_secret  = (string) get_option( 'cta_stripe_webhook_secret', '' );
		$this->mode            = (string) get_option( 'cta_stripe_mode', 'test' );

		if ( ! empty( $this->secret_key ) && class_exists( '\Stripe\Stripe' ) ) {
			\Stripe\Stripe::setApiKey( $this->secret_key );
		}

		add_action( 'wp_ajax_cta_create_checkout', array( $this, 'create_checkout_session' ) );
		add_action( 'wp_ajax_nopriv_cta_create_checkout', array( $this, 'create_checkout_session' ) );

		add_action( 'wp_ajax_cta_create_subscription', array( $this, 'create_subscription_session' ) );
		add_action( 'wp_ajax_nopriv_cta_create_subscription', array( $this, 'create_subscription_session' ) );

		add_action( 'rest_api_init', array( $this, 'register_webhook_route' ) );
	}

	/**
	 * Whether Stripe secret key is configured.
	 *
	 * @return bool
	 */
	private function is_stripe_configured() {
		$key = get_option( 'cta_stripe_secret_key', '' );
		return ! empty( $key );
	}

	/**
	 * Whether Stripe keys are configured.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return ! empty( $this->secret_key ) && ! empty( $this->publishable_key ) && class_exists( '\Stripe\Stripe' );
	}

	/**
	 * Whether test/demo mode skips Stripe and enrolls users instantly.
	 *
	 * @return bool
	 */
	public static function is_payments_bypass_enabled() {
		return 'yes' === get_option( 'cta_payments_bypass', 'yes' );
	}

	/**
	 * Resolve Stripe customer ID for a WordPress user (meta, then payments table).
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public function resolve_stripe_customer_id( $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return '';
		}

		$customer_id = (string) get_user_meta( $user_id, 'cta_stripe_customer_id', true );

		if ( $customer_id ) {
			return $customer_id;
		}

		global $wpdb;

		$customer_id = (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT stripe_customer_id FROM {$wpdb->prefix}cta_payments
				WHERE user_id = %d
				AND stripe_customer_id IS NOT NULL
				AND stripe_customer_id != ''
				ORDER BY created_at DESC
				LIMIT 1",
				$user_id
			)
		);

		if ( $customer_id ) {
			update_user_meta( $user_id, 'cta_stripe_customer_id', $customer_id );
		}

		return $customer_id;
	}

	/**
	 * Feature flags for the Stripe Customer Billing Portal.
	 *
	 * Enables: invoice history, payment method update, cancel at period end
	 * (student keeps access until paid period ends; portal also offers reactivate
	 * while cancel_at_period_end is pending).
	 *
	 * @return array<string,mixed>
	 */
	private function get_billing_portal_features() {
		return array(
			'customer_update'       => array(
				'enabled'         => true,
				'allowed_updates' => array( 'email', 'address', 'phone', 'tax_id' ),
			),
			'invoice_history'       => array(
				'enabled' => true,
			),
			'payment_method_update' => array(
				'enabled' => true,
			),
			'subscription_cancel'   => array(
				'enabled'             => true,
				'mode'                => 'at_period_end',
				'proration_behavior'  => 'none',
				'cancellation_reason' => array(
					'enabled' => true,
					'options' => array(
						'too_expensive',
						'missing_features',
						'switched_service',
						'unused',
						'other',
					),
				),
			),
			'subscription_pause'    => array(
				'enabled' => false,
			),
			'subscription_update'   => array(
				'enabled' => false,
			),
		);
	}

	/**
	 * Ensure a Stripe Customer Portal configuration exists with self-service features.
	 *
	 * Creates or updates a dedicated CTA portal configuration (not a random
	 * Dashboard default that may lack cancel / invoice features).
	 *
	 * @return string|WP_Error Configuration ID or error.
	 */
	public function ensure_billing_portal_configuration() {
		if ( ! $this->is_configured() ) {
			return new WP_Error(
				'stripe_not_configured',
				__( 'Stripe is not configured.', 'cta-lms' )
			);
		}

		if ( ! class_exists( '\Stripe\BillingPortal\Configuration' ) ) {
			return '';
		}

		$features = $this->get_billing_portal_features();
		$existing = (string) get_option( 'cta_stripe_portal_configuration_id', '' );

		if ( $existing ) {
			try {
				$config = \Stripe\BillingPortal\Configuration::update(
					$existing,
					array(
						'business_profile' => array(
							'headline' => __( 'Manage your CTA subscription', 'cta-lms' ),
						),
						'features'         => $features,
					)
				);

				if ( ! empty( $config->id ) ) {
					update_option( 'cta_stripe_portal_configuration_id', (string) $config->id );
					return (string) $config->id;
				}
			} catch ( Exception $e ) {
				// Create a fresh configuration below.
			}
		}

		try {
			$config = \Stripe\BillingPortal\Configuration::create(
				array(
					'business_profile' => array(
						'headline' => __( 'Manage your CTA subscription', 'cta-lms' ),
					),
					'features'         => $features,
				)
			);

			if ( empty( $config->id ) ) {
				return new WP_Error(
					'portal_config_failed',
					__( 'Unable to create Stripe Customer Portal configuration.', 'cta-lms' )
				);
			}

			update_option( 'cta_stripe_portal_configuration_id', (string) $config->id );

			return (string) $config->id;
		} catch ( Exception $e ) {
			return new WP_Error(
				'portal_config_failed',
				sprintf(
					/* translators: %s: Stripe error message */
					__( 'Unable to configure Stripe Customer Portal: %s', 'cta-lms' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Create a Stripe Customer Billing Portal session URL for a user.
	 *
	 * @param int    $user_id    User ID.
	 * @param string $return_url URL to return to after portal.
	 * @return string|WP_Error Portal URL or error.
	 */
	public function create_billing_portal_session( $user_id, $return_url = '' ) {
		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return new WP_Error( 'invalid_user', __( 'Invalid user.', 'cta-lms' ) );
		}

		if ( self::is_payments_bypass_enabled() ) {
			return new WP_Error(
				'payments_bypass',
				__( 'Stripe billing portal is unavailable while payment bypass mode is enabled. Turn off Testing Mode in CTA LMS settings.', 'cta-lms' )
			);
		}

		if ( ! $this->is_configured() ) {
			return new WP_Error(
				'stripe_not_configured',
				__( 'Stripe is not configured. Add your Stripe API keys in CTA LMS settings.', 'cta-lms' )
			);
		}

		if ( ! class_exists( '\Stripe\BillingPortal\Session' ) ) {
			return new WP_Error(
				'stripe_sdk',
				__( 'Stripe SDK is missing Billing Portal support. Run composer install.', 'cta-lms' )
			);
		}

		$customer_id = $this->resolve_stripe_customer_id( $user_id );

		if ( ! $customer_id ) {
			return new WP_Error(
				'no_customer',
				__( 'No Stripe customer is linked to this account yet. Complete a subscription purchase first.', 'cta-lms' )
			);
		}

		if ( '' === $return_url ) {
			$return_url = home_url( '/' );
		}

		$params = array(
			'customer'   => $customer_id,
			'return_url' => esc_url_raw( $return_url ),
		);

		$config_id = $this->ensure_billing_portal_configuration();

		if ( is_wp_error( $config_id ) ) {
			return $config_id;
		}

		if ( is_string( $config_id ) && '' !== $config_id ) {
			$params['configuration'] = $config_id;
		}

		try {
			$session = \Stripe\BillingPortal\Session::create( $params );

			if ( empty( $session->url ) ) {
				return new WP_Error(
					'portal_failed',
					__( 'Stripe did not return a billing portal URL.', 'cta-lms' )
				);
			}

			return (string) $session->url;
		} catch ( Exception $e ) {
			return new WP_Error(
				'portal_failed',
				sprintf(
					/* translators: %s: Stripe error message */
					__( 'Unable to open billing portal: %s', 'cta-lms' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Resolve the Stripe subscription ID stored for a user.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public function resolve_supervision_subscription_id( $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return '';
		}

		$subscription_id = (string) get_user_meta( $user_id, 'cta_supervision_subscription_id', true );

		if ( $subscription_id && 0 !== strpos( $subscription_id, 'bypass-' ) ) {
			return $subscription_id;
		}

		$bundle_id = (string) get_user_meta( $user_id, 'cta_bundle_subscription_id', true );

		if ( $bundle_id && 0 !== strpos( $bundle_id, 'bypass-' ) ) {
			return $bundle_id;
		}

		return '';
	}

	/**
	 * Pull the latest Stripe subscription for a user and sync local meta.
	 *
	 * @param int $user_id User ID.
	 * @return true|WP_Error
	 */
	public function sync_user_subscription_from_stripe( $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return new WP_Error( 'invalid_user', __( 'Invalid user.', 'cta-lms' ) );
		}

		if ( ! $this->is_configured() || ! class_exists( '\Stripe\Subscription' ) ) {
			return new WP_Error( 'stripe_not_configured', __( 'Stripe is not configured.', 'cta-lms' ) );
		}

		$subscription_id = $this->resolve_supervision_subscription_id( $user_id );

		if ( ! $subscription_id ) {
			return new WP_Error(
				'no_subscription',
				__( 'No Stripe subscription is linked to this account.', 'cta-lms' )
			);
		}

		try {
			$subscription = \Stripe\Subscription::retrieve( $subscription_id );
			$this->sync_subscription_status_from_stripe( $subscription );
			return true;
		} catch ( Exception $e ) {
			return new WP_Error(
				'sync_failed',
				sprintf(
					/* translators: %s: Stripe error message */
					__( 'Unable to sync subscription from Stripe: %s', 'cta-lms' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Admin: cancel a student's Stripe subscription.
	 *
	 * @param int    $user_id User ID.
	 * @param string $mode    at_period_end|immediately.
	 * @return true|WP_Error
	 */
	public function admin_cancel_subscription( $user_id, $mode = 'at_period_end' ) {
		$user_id = absint( $user_id );
		$mode    = ( 'immediately' === $mode ) ? 'immediately' : 'at_period_end';

		if ( ! $user_id ) {
			return new WP_Error( 'invalid_user', __( 'Invalid user.', 'cta-lms' ) );
		}

		if ( ! $this->is_configured() || ! class_exists( '\Stripe\Subscription' ) ) {
			return new WP_Error( 'stripe_not_configured', __( 'Stripe is not configured.', 'cta-lms' ) );
		}

		$subscription_id = $this->resolve_supervision_subscription_id( $user_id );

		if ( ! $subscription_id ) {
			// Local-only cancellation (agency / bypass / already detached from Stripe).
			update_user_meta( $user_id, 'cta_supervision_status', 'cancelled' );
			update_user_meta( $user_id, 'cta_supervision_cancel_at_period_end', '0' );
			return true;
		}

		try {
			if ( 'immediately' === $mode ) {
				$subscription = \Stripe\Subscription::retrieve( $subscription_id );
				$subscription->cancel();
				$this->handle_subscription_cancelled( $subscription );
			} else {
				$subscription = \Stripe\Subscription::update(
					$subscription_id,
					array( 'cancel_at_period_end' => true )
				);
				$this->sync_subscription_status_from_stripe( $subscription );
			}

			return true;
		} catch ( Exception $e ) {
			return new WP_Error(
				'cancel_failed',
				sprintf(
					/* translators: %s: Stripe error message */
					__( 'Unable to cancel subscription in Stripe: %s', 'cta-lms' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Admin: clear cancel_at_period_end so renewal continues.
	 *
	 * @param int $user_id User ID.
	 * @return true|WP_Error
	 */
	public function admin_reactivate_subscription( $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return new WP_Error( 'invalid_user', __( 'Invalid user.', 'cta-lms' ) );
		}

		if ( ! $this->is_configured() || ! class_exists( '\Stripe\Subscription' ) ) {
			return new WP_Error( 'stripe_not_configured', __( 'Stripe is not configured.', 'cta-lms' ) );
		}

		$subscription_id = $this->resolve_supervision_subscription_id( $user_id );

		if ( ! $subscription_id ) {
			return new WP_Error(
				'no_subscription',
				__( 'No Stripe subscription is linked to this account.', 'cta-lms' )
			);
		}

		try {
			$subscription = \Stripe\Subscription::update(
				$subscription_id,
				array( 'cancel_at_period_end' => false )
			);
			$this->sync_subscription_status_from_stripe( $subscription );
			return true;
		} catch ( Exception $e ) {
			return new WP_Error(
				'reactivate_failed',
				sprintf(
					/* translators: %s: Stripe error message */
					__( 'Unable to reactivate subscription in Stripe: %s', 'cta-lms' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Sync local subscription meta from a Stripe subscription object.
	 *
	 * Keeps access active through the paid period when cancel_at_period_end is set.
	 *
	 * @param object $subscription Stripe subscription.
	 */
	public function sync_subscription_status_from_stripe( $subscription ) {
		$subscription_id = sanitize_text_field( $subscription->id ?? '' );

		if ( ! $subscription_id ) {
			return;
		}

		$user_id = $this->get_user_id_by_subscription( $subscription_id );

		if ( ! $user_id ) {
			return;
		}

		$status             = sanitize_text_field( $subscription->status ?? '' );
		$cancel_at_period_end = ! empty( $subscription->cancel_at_period_end );
		$period_end         = ! empty( $subscription->current_period_end ) ? (int) $subscription->current_period_end : 0;

		if ( $period_end > 0 ) {
			update_user_meta( $user_id, 'cta_supervision_period_end', $period_end );
		}

		update_user_meta( $user_id, 'cta_supervision_cancel_at_period_end', $cancel_at_period_end ? '1' : '0' );

		if ( in_array( $status, array( 'active', 'trialing' ), true ) ) {
			$current_local = (string) get_user_meta( $user_id, 'cta_supervision_status', true );

			// Do not override Associate Pending Approval with Active from Stripe alone.
			if ( 'pending_approval' !== $current_local ) {
				update_user_meta( $user_id, 'cta_supervision_status', 'active' );
			}
			return;
		}

		if ( in_array( $status, array( 'past_due', 'unpaid' ), true ) ) {
			update_user_meta( $user_id, 'cta_supervision_status', 'past_due' );
			return;
		}

		if ( in_array( $status, array( 'canceled', 'cancelled', 'incomplete_expired' ), true ) ) {
			update_user_meta( $user_id, 'cta_supervision_status', 'cancelled' );
			update_user_meta( $user_id, 'cta_supervision_cancel_at_period_end', '0' );
		}
	}

	/**
	 * Get publishable key for frontend.
	 *
	 * @return string
	 */
	public function get_publishable_key() {
		return $this->publishable_key;
	}

	/**
	 * Get supervision monthly price from options (DB).
	 *
	 * @return float
	 */
	public function get_supervision_monthly_price() {
		return CTA_Supervision_Plans::get_group_price();
	}

	/**
	 * Monthly price for a supervision plan slug.
	 *
	 * @param string $plan Plan slug (group|hybrid).
	 * @return float
	 */
	public function get_supervision_plan_price( $plan = 'group' ) {
		return CTA_Supervision_Plans::get_price( $plan );
	}

	/**
	 * Ensure All-Access subscription bundles use the canonical name and price.
	 *
	 * @param object $bundle Bundle row.
	 * @return object
	 */
	private function normalize_supervision_bundle( $bundle ) {
		if ( ! $bundle || 'subscription' !== (string) ( $bundle->plan_type ?? '' ) ) {
			return $bundle;
		}

		$slug = (string) ( $bundle->slug ?? '' );
		$name = (string) ( $bundle->name ?? '' );

		if (
			! in_array( $slug, array( CTA_Supervision_Plans::ALL_ACCESS_BUNDLE_SLUG, CTA_Supervision_Plans::LEGACY_HYBRID_BUNDLE_SLUG ), true )
			&& false === stripos( $name, 'Hybrid' )
			&& false === stripos( $name, 'All-Access Program' )
			&& false === stripos( $name, 'Supervision + CE' )
		) {
			return $bundle;
		}

		$bundle->name        = CTA_Supervision_Plans::get_name( CTA_Supervision_Plans::HYBRID_SLUG );
		$bundle->price       = CTA_Supervision_Plans::get_price( CTA_Supervision_Plans::HYBRID_SLUG );
		$bundle->description = CTA_Supervision_Plans::get_plan( CTA_Supervision_Plans::HYBRID_SLUG )['description'];
		$bundle->slug        = CTA_Supervision_Plans::ALL_ACCESS_BUNDLE_SLUG;

		return $bundle;
	}

	/**
	 * Create one-time Stripe Checkout session for a course.
	 *
	 * CE / Exam Prep checkout is available to any active account, including
	 * Registered Associates whose supervision application is still pending.
	 */
	public function create_checkout_session() {
		check_ajax_referer( 'cta_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please log in to enroll in this course.', 'cta-lms' ),
				)
			);
		}

		$user_id = get_current_user_id();

		if ( class_exists( 'CTA_Associate_Access' ) ) {
			CTA_Associate_Access::heal_decoupled_statuses( $user_id );
		}

		if (
			class_exists( 'CTA_Associate_Access' )
			&& ! CTA_Associate_Access::can_access_ce_and_exam_prep( $user_id )
		) {
			wp_send_json_error(
				array(
					'message' => __( 'Your account is inactive. Please contact support.', 'cta-lms' ),
					'code'    => 'account_inactive',
				)
			);
		}

		$course_id = absint( wp_unslash( $_POST['course_id'] ?? 0 ) );

		if ( ! $course_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid course selected.', 'cta-lms' ),
				)
			);
		}

		if ( ! $this->is_stripe_configured() ) {
			if ( ! empty( $_POST['demo_confirm'] ) ) {
				$this->bypass_course_enrollment( $course_id );
				return;
			}

			wp_send_json_success(
				array(
					'demo_mode'    => true,
					'checkout_url' => '',
				)
			);
		}

		if ( self::is_payments_bypass_enabled() ) {
			$this->bypass_course_enrollment( $course_id );
			return;
		}

		if ( ! $this->is_configured() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Payments are not configured yet. Please contact support.', 'cta-lms' ),
				)
			);
		}

		global $wpdb;

		$course = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}cta_courses
				WHERE id = %d AND status = 'published'",
				$course_id
			)
		);

		if ( ! $course ) {
			wp_send_json_error(
				array(
					'message' => __( 'Course not found.', 'cta-lms' ),
				)
			);
		}

		$user_id = get_current_user_id();

		$already_has_access = false;
		if ( class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course ) ) {
			$already_has_access = CTA_Exam_Access::has_active_access( $user_id, $course_id );
		} elseif ( class_exists( 'CTA_CE_Access' ) && CTA_CE_Access::is_ce_course( $course ) ) {
			$already_has_access = CTA_CE_Access::has_active_access( $user_id, $course_id );
		} else {
			$already_has_access = (bool) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}cta_enrollments
					WHERE user_id = %d AND course_id = %d AND status IN ('active','completed')",
					$user_id,
					$course_id
				)
			);
		}

		if ( $already_has_access ) {
			wp_send_json_error(
				array(
					'message' => __( 'You are already enrolled in this course.', 'cta-lms' ),
				)
			);
		}

		if ( (float) $course->price <= 0 ) {
			$this->bypass_course_enrollment( $course_id );
			return;
		}

		$course_page = $this->get_page_url( 'cta_single_course_page_id' );
		if ( ! $course_page ) {
			$course_page = home_url( '/' );
		}

		$dashboard_page = $this->get_page_url( 'cta_student_dashboard_page_id' );
		$success_base   = $dashboard_page ? $dashboard_page : $course_page;

		$success_url = $this->build_checkout_success_url(
			$success_base,
			array(
				'course_id'    => $course_id,
				'payment'      => 'success',
				'cta_enrolled' => '1',
			)
		);

		$cancel_url = add_query_arg( 'course_id', $course_id, $course_page );

		$ce_hours = rtrim( rtrim( number_format( (float) $course->ce_hours, 1, '.', '' ), '0' ), '.' );

		try {
			$session = \Stripe\Checkout\Session::create(
				array(
					'payment_method_types' => array( 'card' ),
					'mode'                 => 'payment',
					'customer_email'       => wp_get_current_user()->user_email,
					'line_items'           => array(
						array(
							'price_data' => array(
								'currency'     => 'usd',
								'unit_amount'  => (int) round( (float) $course->price * 100 ),
								'product_data' => array(
									'name'        => $course->title,
									'description' => sprintf(
										/* translators: %s: CE hours */
										__( '%s CE Hours — BBS Approved', 'cta-lms' ),
										$ce_hours
									),
								),
							),
							'quantity' => 1,
						),
					),
					'metadata'    => array(
						'user_id'      => (string) $user_id,
						'course_id'    => (string) $course_id,
						'product_type' => 'course',
					),
					'success_url' => $success_url,
					'cancel_url'  => $cancel_url,
				)
			);

			$wpdb->insert(
				$wpdb->prefix . 'cta_payments',
				array(
					'user_id'           => $user_id,
					'stripe_payment_id' => $session->id,
					'amount'            => $course->price,
					'currency'          => 'usd',
					'payment_type'      => 'one_time',
					'product_type'      => 'course',
					'product_id'        => $course_id,
					'status'            => 'pending',
				),
				array( '%d', '%s', '%f', '%s', '%s', '%s', '%d', '%s' )
			);

			wp_send_json_success(
				array(
					'checkout_url' => $session->url,
				)
			);
		} catch ( \Stripe\Exception\ApiErrorException $e ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: Stripe error message */
						__( 'Payment error: %s', 'cta-lms' ),
						$e->getMessage()
					),
				)
			);
		}
	}

	/**
	 * Create Stripe Checkout session for supervision subscription.
	 */
	public function create_subscription_session() {
		check_ajax_referer( 'cta_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please log in to subscribe to supervision.', 'cta-lms' ),
				)
			);
		}

		CTA_Associate_Access::require_associate_for_purchase( get_current_user_id() );
		CTA_Associate_Access::require_agency_for_supervision_application( get_current_user_id() );

		if ( ! $this->is_stripe_configured() ) {
			if ( ! empty( $_POST['demo_confirm'] ) ) {
				$this->bypass_supervision_subscription();
				return;
			}

			wp_send_json_success(
				array(
					'demo_mode'    => true,
					'checkout_url' => '',
				)
			);
		}

		if ( self::is_payments_bypass_enabled() ) {
			$this->bypass_supervision_subscription();
			return;
		}

		if ( ! $this->is_configured() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Payments are not configured yet. Please contact support.', 'cta-lms' ),
				)
			);
		}

		global $wpdb;

		$user_id = get_current_user_id();
		$price   = CTA_Supervision_Plans::get_group_price();

		if ( $price <= 0 ) {
			wp_send_json_error(
				array(
					'message' => __( 'Supervision pricing is not configured.', 'cta-lms' ),
				)
			);
		}

		$active_sub = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}cta_payments
				WHERE user_id = %d
				AND product_type = 'supervision'
				AND payment_type = 'subscription'
				AND status = 'completed'",
				$user_id
			)
		);

		if ( $active_sub ) {
			wp_send_json_error(
				array(
					'message' => __( 'You already have an active supervision subscription.', 'cta-lms' ),
				)
			);
		}

		$supervision_page = $this->get_page_url( 'cta_supervision_dashboard_page_id' );
		if ( ! $supervision_page ) {
			$supervision_page = $this->get_page_url( 'cta_supervision_page_id' );
		}
		if ( ! $supervision_page ) {
			$supervision_page = home_url( '/' );
		}

		$group_plan   = CTA_Supervision_Plans::get_plan( CTA_Supervision_Plans::GROUP_SLUG );
		$product_name = (string) get_option( 'cta_supervision_product_name', '' );
		if ( '' === $product_name ) {
			$product_name = $group_plan['name'];
		}

		$product_desc = (string) get_option( 'cta_supervision_product_description', '' );
		if ( '' === $product_desc ) {
			$product_desc = $group_plan['description'];
		}

		$success_url = $this->build_checkout_success_url(
			$supervision_page,
			array(
				'subscription' => 'success',
			)
		);

		$cancel_url = add_query_arg( 'subscription', 'cancelled', $supervision_page );

		try {
			$session_args = array(
				'payment_method_types' => array( 'card' ),
				'mode'                 => 'subscription',
				'line_items'           => array(
					array(
						'price_data' => array(
							'currency'     => 'usd',
							'unit_amount'  => (int) round( $price * 100 ),
							'recurring'    => array(
								'interval' => 'month',
							),
							'product_data' => array(
								'name'        => $product_name,
								'description' => $product_desc,
							),
						),
						'quantity' => 1,
					),
				),
				'metadata'    => array(
					'user_id'      => (string) $user_id,
					'product_type' => 'supervision',
				),
				'success_url' => $success_url,
				'cancel_url'  => $cancel_url,
			);

			$existing_customer = $this->resolve_stripe_customer_id( $user_id );

			if ( $existing_customer ) {
				$session_args['customer'] = $existing_customer;
			} else {
				$session_args['customer_email'] = wp_get_current_user()->user_email;
			}

			$session = \Stripe\Checkout\Session::create( $session_args );

			$wpdb->insert(
				$wpdb->prefix . 'cta_payments',
				array(
					'user_id'           => $user_id,
					'stripe_payment_id' => $session->id,
					'amount'            => $price,
					'currency'          => 'usd',
					'payment_type'      => 'subscription',
					'product_type'      => 'supervision',
					'product_id'        => 0,
					'plan_name'         => $product_name,
					'plan_details'      => wp_json_encode(
						array(
							'plan_slug'    => 'group',
							'plan_name'    => $product_name,
							'billing'      => 'monthly',
							'price'        => $price,
							'currency'     => 'usd',
							'product_type' => 'supervision',
							'description'  => $product_desc,
						)
					),
					'status'            => 'pending',
				),
				array( '%d', '%s', '%f', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
			);

			wp_send_json_success(
				array(
					'checkout_url' => $session->url,
				)
			);
		} catch ( \Stripe\Exception\ApiErrorException $e ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: Stripe error message */
						__( 'Payment error: %s', 'cta-lms' ),
						$e->getMessage()
					),
				)
			);
		}
	}

	/**
	 * Register Stripe webhook REST route.
	 */
	public function register_webhook_route() {
		register_rest_route(
			'cta-lms/v1',
			'/stripe-webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle incoming Stripe webhook events.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function handle_webhook( $request ) {
		if ( ! class_exists( '\Stripe\Webhook' ) ) {
			return new WP_REST_Response(
				array( 'error' => 'Stripe SDK not loaded.' ),
				500
			);
		}

		$payload    = $request->get_body();
		$sig_header = $request->get_header( 'stripe-signature' );

		try {
			if ( ! empty( $this->webhook_secret ) ) {
				$event = \Stripe\Webhook::constructEvent(
					$payload,
					$sig_header,
					$this->webhook_secret
				);
			} else {
				$event = json_decode( $payload );
			}
		} catch ( \UnexpectedValueException $e ) {
			return new WP_REST_Response( array( 'error' => 'Invalid payload.' ), 400 );
		} catch ( \Stripe\Exception\SignatureVerificationException $e ) {
			return new WP_REST_Response( array( 'error' => 'Invalid signature.' ), 400 );
		}

		if ( empty( $event->type ) ) {
			return new WP_REST_Response( array( 'error' => 'Missing event type.' ), 400 );
		}

		switch ( $event->type ) {
			case 'checkout.session.completed':
				$this->handle_checkout_completed( $event->data->object );
				break;

			case 'customer.subscription.updated':
				$this->sync_subscription_status_from_stripe( $event->data->object );
				break;

			case 'customer.subscription.deleted':
				$this->handle_subscription_cancelled( $event->data->object );
				break;

			case 'invoice.payment_failed':
				$this->handle_subscription_payment_failed( $event->data->object );
				break;

			case 'invoice.paid':
				$this->handle_subscription_invoice_paid( $event->data->object );
				break;
		}

		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	/**
	 * Process completed checkout session.
	 *
	 * @param object $session Stripe checkout session.
	 */
	private function handle_checkout_completed( $session ) {
		global $wpdb;

		$metadata = isset( $session->metadata ) ? (array) $session->metadata : array();
		$user_id  = absint( $metadata['user_id'] ?? 0 );
		$type     = sanitize_text_field( $metadata['product_type'] ?? '' );

		$wpdb->update(
			$wpdb->prefix . 'cta_payments',
			array(
				'status'             => 'completed',
				'stripe_customer_id' => sanitize_text_field( $session->customer ?? '' ),
			),
			array( 'stripe_payment_id' => sanitize_text_field( $session->id ) ),
			array( '%s', '%s' ),
			array( '%s' )
		);

		if ( 'course' === $type ) {
			$course_id = absint( $metadata['course_id'] ?? 0 );

			if ( $user_id && $course_id ) {
				$this->create_enrollment(
					$user_id,
					$course_id,
					sanitize_text_field( $session->id ),
					array(
						'access_source' => 'purchase',
						'expires_at'    => null,
					)
				);

				$course = CTA_Database::get_course( $course_id );
				if ( $course && class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course ) ) {
					$months = ! empty( $course->access_period_months ) ? (int) $course->access_period_months : 6;
					CTA_Exam_Access::grant_access( $user_id, $course_id, $months );
				}

				if ( $course ) {
					CTA_Emails::send(
						'payment_receipt',
						$user_id,
						array(
							'payment_id'   => sanitize_text_field( $session->id ),
							'product_name' => $course->title,
						)
					);
				}
			}
		}

		if ( 'supervision' === $type && $user_id ) {
			$subscription_id = sanitize_text_field( $session->subscription ?? '' );
			$customer_id     = sanitize_text_field( $session->customer ?? '' );
			$session_id      = sanitize_text_field( $session->id ?? '' );

			$this->activate_supervision_purchase(
				$user_id,
				array(
					'checkout_session_id' => $session_id,
					'subscription_id'     => $subscription_id,
					'customer_id'         => $customer_id,
					'amount'              => $this->get_supervision_monthly_price(),
					'send_receipt'        => true,
					'receipt_payment_id'  => $session_id,
				)
			);
		}

		if ( 'bundle' === $type && $user_id ) {
			$bundle_id = absint( $metadata['bundle_id'] ?? 0 );
			$billing   = sanitize_text_field( $metadata['billing'] ?? '' );

			if ( $bundle_id ) {
				$this->activate_bundle_access(
					$user_id,
					$bundle_id,
					$billing,
					sanitize_text_field( $session->id )
				);

				$subscription_id = sanitize_text_field( $session->subscription ?? '' );
				$customer_id     = sanitize_text_field( $session->customer ?? '' );

				if ( $customer_id ) {
					update_user_meta( $user_id, 'cta_stripe_customer_id', $customer_id );
				}

				if ( $subscription_id ) {
					$wpdb->update(
						$wpdb->prefix . 'cta_payments',
						array(
							'stripe_payment_id'  => $subscription_id,
							'stripe_customer_id' => $customer_id ? $customer_id : null,
							'status'             => 'completed',
						),
						array(
							'user_id'      => $user_id,
							'product_id'   => $bundle_id,
							'product_type' => 'bundle',
						),
						array( '%s', '%s', '%s' ),
						array( '%d', '%d', '%s' )
					);

					update_user_meta( $user_id, 'cta_bundle_subscription_id', $subscription_id );
				}
			}
		}
	}

	/**
	 * Create Stripe Checkout session for a membership bundle.
	 *
	 * @param object $bundle Bundle row from database.
	 */
	public function create_bundle_checkout_session( $bundle ) {
		$bundle = $this->normalize_supervision_bundle( $bundle );

		if ( ! $this->is_stripe_configured() ) {
			if ( ! empty( $_POST['demo_confirm'] ) ) {
				$this->bypass_bundle_purchase( $bundle );
				return;
			}

			wp_send_json_success(
				array(
					'demo_mode'    => true,
					'checkout_url' => '',
				)
			);
		}

		if ( ! $this->is_configured() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Payments are not configured yet. Please contact support.', 'cta-lms' ),
				)
			);
		}

		global $wpdb;

		$user_id = get_current_user_id();
		$user    = wp_get_current_user();
		$billing = sanitize_text_field( $bundle->billing_cycle );

		$memberships_page = $this->get_page_url( 'cta_memberships_page_id' );
		if ( ! $memberships_page ) {
			$memberships_page = home_url( '/' );
		}

		$success_url = $this->build_checkout_success_url(
			$memberships_page,
			array(
				'bundle_purchase' => 'success',
				'bundle_id'       => (int) $bundle->id,
			)
		);

		$cancel_url = $memberships_page;

		$mode         = ( 'monthly' === $billing ) ? 'subscription' : 'payment';
		$payment_type = ( 'monthly' === $billing ) ? 'subscription' : 'one_time';

		try {
			$session_args = array(
				'payment_method_types' => array( 'card' ),
				'mode'                 => $mode,
				'metadata'             => array(
					'user_id'      => (string) $user_id,
					'bundle_id'    => (string) $bundle->id,
					'product_type' => 'bundle',
					'billing'      => $billing,
				),
				'success_url'          => $success_url,
				'cancel_url'           => $cancel_url,
			);

			$existing_customer = $this->resolve_stripe_customer_id( $user_id );

			if ( $existing_customer ) {
				$session_args['customer'] = $existing_customer;
			} else {
				$session_args['customer_email'] = $user->user_email;
			}

			if ( 'monthly' === $billing && ! empty( $bundle->stripe_price_id ) ) {
				$session_args['line_items'] = array(
					array(
						'price'    => $bundle->stripe_price_id,
						'quantity' => 1,
					),
				);
			} elseif ( 'monthly' === $billing ) {
				$session_args['line_items'] = array(
					array(
						'price_data' => array(
							'currency'     => 'usd',
							'unit_amount'  => (int) round( (float) $bundle->price * 100 ),
							'recurring'    => array(
								'interval' => 'month',
							),
							'product_data' => array(
								'name'        => $bundle->name,
								'description' => wp_strip_all_tags( (string) $bundle->description ),
							),
						),
						'quantity'   => 1,
					),
				);
			} else {
				$session_args['line_items'] = array(
					array(
						'price_data' => array(
							'currency'     => 'usd',
							'unit_amount'  => (int) round( (float) $bundle->price * 100 ),
							'product_data' => array(
								'name'        => $bundle->name,
								'description' => wp_strip_all_tags( (string) $bundle->description ),
							),
						),
						'quantity'   => 1,
					),
				);
			}

			$session = \Stripe\Checkout\Session::create( $session_args );

			$wpdb->insert(
				$wpdb->prefix . 'cta_payments',
				array(
					'user_id'           => $user_id,
					'stripe_payment_id' => $session->id,
					'amount'            => $bundle->price,
					'currency'          => 'usd',
					'payment_type'      => $payment_type,
					'product_type'      => 'bundle',
					'product_id'        => (int) $bundle->id,
					'status'            => 'pending',
				),
				array( '%d', '%s', '%f', '%s', '%s', '%s', '%d', '%s' )
			);

			wp_send_json_success(
				array(
					'checkout_url' => $session->url,
				)
			);
		} catch ( \Stripe\Exception\ApiErrorException $e ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: Stripe error message */
						__( 'Payment error: %s', 'cta-lms' ),
						$e->getMessage()
					),
				)
			);
		}
	}

	/**
	 * Activate bundle access after successful payment.
	 *
	 * @param int    $user_id    User ID.
	 * @param int    $bundle_id  Bundle ID.
	 * @param string $billing    Billing cycle.
	 * @param string $payment_id Stripe session or payment ID.
	 */
	private function activate_bundle_access( $user_id, $bundle_id, $billing, $payment_id ) {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'cta_payments',
			array( 'status' => 'completed' ),
			array(
				'user_id'      => $user_id,
				'product_id'   => $bundle_id,
				'product_type' => 'bundle',
			),
			array( '%s' ),
			array( '%d', '%d', '%s' )
		);

		$bundle = CTA_Database::get_bundle( $bundle_id );
		if ( ! $bundle ) {
			return;
		}

		$included_ids = json_decode( (string) $bundle->included_courses, true );
		if ( ! is_array( $included_ids ) ) {
			$included_ids = array();
		}

		// CE memberships/bundles must never silently include exam prep products.
		if ( class_exists( 'CTA_Exam_Access' ) ) {
			$included_ids = CTA_Exam_Access::filter_ce_only_course_ids( $included_ids );
		}

		$enroll_args = class_exists( 'CTA_CE_Access' )
			? CTA_CE_Access::enrollment_args_for_bundle( $bundle, $billing )
			: array( 'access_source' => 'purchase', 'expires_at' => null );

		if ( 'annual' === $bundle->plan_type || 'yearly' === $billing || 'subscription' === $bundle->plan_type ) {
			$all_courses = CTA_Database::get_all_courses( 'published' );
			foreach ( $all_courses as $course ) {
				if ( class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course ) ) {
					continue;
				}
				$this->enroll_user_in_course( $user_id, (int) $course->id, $payment_id, $enroll_args );
			}
		} else {
			foreach ( $included_ids as $course_id ) {
				$this->enroll_user_in_course( $user_id, (int) $course_id, $payment_id, $enroll_args );
			}
		}

		if ( 'subscription' === $bundle->plan_type ) {
			$all_access_name  = CTA_Supervision_Plans::get_name( CTA_Supervision_Plans::HYBRID_SLUG );
			$all_access_price = (float) $bundle->price > 0
				? (float) $bundle->price
				: CTA_Supervision_Plans::get_price( CTA_Supervision_Plans::HYBRID_SLUG );

			$wpdb->update(
				$wpdb->prefix . 'cta_payments',
				array(
					'plan_name'    => $all_access_name,
					'plan_details' => wp_json_encode(
						array(
							'plan_slug'    => CTA_Supervision_Plans::HYBRID_SLUG,
							'bundle_id'    => (int) $bundle->id,
							'billing'      => sanitize_text_field( $bundle->billing_cycle ),
							'price'        => $all_access_price,
							'product_type' => 'bundle',
							'description'  => wp_strip_all_tags( (string) $bundle->description ),
						)
					),
				),
				array(
					'user_id'      => $user_id,
					'product_id'   => (int) $bundle->id,
					'product_type' => 'bundle',
				),
				array( '%s', '%s' ),
				array( '%d', '%d', '%s' )
			);

			$this->activate_supervision_purchase(
				$user_id,
				array(
					'subscription_id'    => (string) get_user_meta( $user_id, 'cta_bundle_subscription_id', true ),
					'customer_id'        => (string) get_user_meta( $user_id, 'cta_stripe_customer_id', true ),
					'amount'             => $all_access_price,
					'plan_slug'          => CTA_Supervision_Plans::HYBRID_SLUG,
					'plan_name'          => $all_access_name,
					'plan_details'       => array(
						'plan_slug'    => CTA_Supervision_Plans::HYBRID_SLUG,
						'bundle_id'    => (int) $bundle->id,
						'billing'      => sanitize_text_field( $bundle->billing_cycle ),
						'price'        => $all_access_price,
						'product_type' => 'bundle',
						'description'  => wp_strip_all_tags( (string) $bundle->description ),
					),
					'skip_payment_row'   => true,
					'send_receipt'       => false,
				)
			);

			update_user_meta( $user_id, 'cta_hybrid_plan_active', (int) $bundle->id );
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return;
		}

		CTA_Emails::send(
			'payment_receipt',
			$user_id,
			array(
				'payment_id'   => $payment_id,
				'product_name' => $bundle->name,
			)
		);
	}

	/**
	 * Skip Stripe and enroll the current user in a course (testing mode).
	 *
	 * @param int $course_id Course ID.
	 */
	private function bypass_course_enrollment( $course_id ) {
		global $wpdb;

		$course = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}cta_courses
				WHERE id = %d AND status = 'published'",
				$course_id
			)
		);

		if ( ! $course ) {
			wp_send_json_error(
				array(
					'message' => __( 'Course not found.', 'cta-lms' ),
				)
			);
		}

		$user_id = get_current_user_id();
		$payment_id = 'bypass-' . time();

		$enrolled = $this->create_enrollment(
			$user_id,
			$course_id,
			$payment_id,
			array(
				'access_source' => 'purchase',
				'expires_at'    => null,
			)
		);

		if ( ! $enrolled ) {
			wp_send_json_error(
				array(
					'message' => __( 'Unable to create enrollment. Please try again.', 'cta-lms' ),
				)
			);
		}

		$wpdb->insert(
			$wpdb->prefix . 'cta_payments',
			array(
				'user_id'           => $user_id,
				'stripe_payment_id' => $payment_id,
				'amount'            => (float) $course->price,
				'currency'          => 'usd',
				'payment_type'      => 'one_time',
				'product_type'      => 'course',
				'product_id'        => $course_id,
				'status'            => 'completed',
			),
			array( '%d', '%s', '%f', '%s', '%s', '%s', '%d', '%s' )
		);

		$dashboard = $this->get_page_url( 'cta_student_dashboard_page_id' );
		if ( ! $dashboard ) {
			$dashboard = $this->get_course_player_url( $course_id );
		} else {
			$dashboard = add_query_arg(
				array(
					'cta_enrolled' => '1',
					'course_id'    => $course_id,
					'_cta'         => (string) time(),
				),
				$dashboard
			);
		}

		wp_send_json_success(
			array(
				'enrolled'     => true,
				'redirect_url' => $dashboard,
				'message'      => __( 'Enrolled successfully (payment bypass mode).', 'cta-lms' ),
			)
		);
	}

	/**
	 * Skip Stripe and activate supervision subscription (testing mode).
	 */
	private function bypass_supervision_subscription() {
		global $wpdb;

		$user_id = get_current_user_id();

		CTA_Associate_Access::require_associate_for_purchase( $user_id );
		CTA_Associate_Access::require_agency_for_supervision_application( $user_id );

		$active_sub = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}cta_payments
				WHERE user_id = %d
				AND product_type = 'supervision'
				AND payment_type = 'subscription'
				AND status = 'completed'",
				$user_id
			)
		);

		if ( $active_sub ) {
			wp_send_json_error(
				array(
					'message' => __( 'You already have an active supervision subscription.', 'cta-lms' ),
				)
			);
		}

		$payment_id = 'bypass-sub-' . time();

		$this->activate_supervision_purchase(
			$user_id,
			array(
				'checkout_session_id' => $payment_id,
				'subscription_id'     => $payment_id,
				'amount'              => 0,
				'create_payment_row'  => true,
				'send_receipt'        => false,
			)
		);

		$redirect = $this->get_page_url( 'cta_supervision_dashboard_page_id' );
		if ( ! $redirect ) {
			$redirect = $this->get_page_url( 'cta_supervision_page_id' );
		}
		if ( ! $redirect ) {
			$redirect = home_url( '/' );
		}

		$redirect = add_query_arg(
			array(
				'subscription' => 'success',
				'cta_paid'     => '1',
				'_cta'         => (string) time(),
			),
			$redirect
		);

		clean_user_cache( $user_id );

		wp_send_json_success(
			array(
				'enrolled'     => true,
				'redirect_url' => $redirect,
				'message'      => __( 'Subscription recorded as Pending Approval (payment bypass mode).', 'cta-lms' ),
			)
		);
	}

	/**
	 * Create / complete a supervision purchase record and set Pending Approval.
	 *
	 * Uses existing `{prefix}cta_payments` + user meta — no duplicate user tables.
	 *
	 * @param int   $user_id User ID.
	 * @param array $args    Optional arguments.
	 * @return int Payment row ID, or 0 on failure.
	 */
	public function activate_supervision_purchase( $user_id, $args = array() ) {
		global $wpdb;

		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return 0;
		}

		$args = wp_parse_args(
			$args,
			array(
				'checkout_session_id' => '',
				'subscription_id'     => '',
				'customer_id'         => '',
				'amount'              => $this->get_supervision_monthly_price(),
				'plan_slug'           => 'group',
				'plan_name'           => '',
				'plan_details'        => array(),
				'product_id'          => 0,
				'create_payment_row'  => true,
				'skip_payment_row'    => false,
				'send_receipt'        => false,
				'receipt_payment_id'  => '',
			)
		);

		$plan_slug = sanitize_key( $args['plan_slug'] );
		if ( ! in_array( $plan_slug, array( CTA_Supervision_Plans::GROUP_SLUG, CTA_Supervision_Plans::HYBRID_SLUG ), true ) ) {
			$plan_slug = CTA_Supervision_Plans::GROUP_SLUG;
		}
		$plan_slug = CTA_Supervision_Plans::normalize_slug( $plan_slug );

		$plan_name = sanitize_text_field( $args['plan_name'] );
		if ( '' === $plan_name ) {
			$plan_name = CTA_Supervision_Plans::get_name( $plan_slug );
		} else {
			$plan_name = CTA_Supervision_Plans::canonicalize_name( $plan_name );
			// Keep slug aligned with the canonicalized name.
			if ( CTA_Supervision_Plans::name_indicates_all_access( $plan_name ) ) {
				$plan_slug = CTA_Supervision_Plans::HYBRID_SLUG;
			}
		}

		$amount = (float) $args['amount'];
		if ( $amount <= 0 ) {
			$amount = CTA_Supervision_Plans::get_price( $plan_slug );
		}

		$plan_details = is_array( $args['plan_details'] ) ? $args['plan_details'] : array();
		$plan_details = wp_parse_args(
			$plan_details,
			array(
				'plan_slug'             => $plan_slug,
				'plan_name'             => $plan_name,
				'billing'               => 'monthly',
				'price'                 => $amount,
				'currency'              => 'usd',
				'sessions_per_month'    => 4,
				'session_duration_mins' => class_exists( 'CTA_Supervision' ) ? CTA_Supervision::GROUP_DURATION_MINS : 120,
				'max_group_size'        => class_exists( 'CTA_Supervision' ) ? CTA_Supervision::GROUP_SEATS_MAX : 8,
				'product_type'          => 'supervision',
				'description'           => (string) get_option(
					'cta_supervision_product_description',
					__( 'Monthly group supervision subscription', 'cta-lms' )
				),
			)
		);

		$plan_details_json = wp_json_encode( $plan_details );
		$table             = $wpdb->prefix . 'cta_payments';
		$session_id        = sanitize_text_field( $args['checkout_session_id'] );
		$subscription_id   = sanitize_text_field( $args['subscription_id'] );
		$customer_id       = sanitize_text_field( $args['customer_id'] );
		$stripe_ref        = $subscription_id ? $subscription_id : $session_id;
		$payment_id        = 0;

		if ( empty( $args['skip_payment_row'] ) ) {
			if ( $session_id ) {
				$existing = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT id FROM {$table} WHERE stripe_payment_id = %s LIMIT 1",
						$session_id
					)
				);

				if ( $existing ) {
					$wpdb->update(
						$table,
						array(
							'user_id'            => $user_id,
							'stripe_payment_id'  => $stripe_ref ? $stripe_ref : $session_id,
							'stripe_customer_id' => $customer_id ? $customer_id : null,
							'amount'             => $amount,
							'currency'           => 'usd',
							'payment_type'       => 'subscription',
							'product_type'       => 'supervision',
							'product_id'         => absint( $args['product_id'] ),
							'plan_name'          => $plan_name,
							'plan_details'       => $plan_details_json,
							'status'             => 'completed',
						),
						array( 'id' => (int) $existing->id ),
						array( '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ),
						array( '%d' )
					);
					$payment_id = (int) $existing->id;
				}
			}

			if ( ! $payment_id && $stripe_ref && $stripe_ref !== $session_id ) {
				$existing = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT id FROM {$table} WHERE stripe_payment_id = %s LIMIT 1",
						$stripe_ref
					)
				);

				if ( $existing ) {
					$wpdb->update(
						$table,
						array(
							'user_id'            => $user_id,
							'stripe_customer_id' => $customer_id ? $customer_id : null,
							'amount'             => $amount,
							'plan_name'          => $plan_name,
							'plan_details'       => $plan_details_json,
							'status'             => 'completed',
							'product_type'       => 'supervision',
						),
						array( 'id' => (int) $existing->id ),
						array( '%d', '%s', '%f', '%s', '%s', '%s', '%s' ),
						array( '%d' )
					);
					$payment_id = (int) $existing->id;
				}
			}

			if ( ! $payment_id && ! empty( $args['create_payment_row'] ) ) {
				$inserted = $wpdb->insert(
					$table,
					array(
						'user_id'            => $user_id,
						'stripe_payment_id'  => $stripe_ref ? $stripe_ref : ( 'supervision-' . $user_id . '-' . time() ),
						'stripe_customer_id' => $customer_id ? $customer_id : null,
						'amount'             => $amount,
						'currency'           => 'usd',
						'payment_type'       => 'subscription',
						'product_type'       => 'supervision',
						'product_id'         => absint( $args['product_id'] ),
						'plan_name'          => $plan_name,
						'plan_details'       => $plan_details_json,
						'status'             => 'completed',
					),
					array( '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
				);

				if ( $inserted ) {
					$payment_id = (int) $wpdb->insert_id;
				}
			}
		}

		if ( $customer_id ) {
			update_user_meta( $user_id, 'cta_stripe_customer_id', $customer_id );
		}

		if ( $subscription_id ) {
			update_user_meta( $user_id, 'cta_supervision_subscription_id', $subscription_id );
		}

		update_user_meta( $user_id, 'cta_supervision_plan', $plan_slug );
		update_user_meta( $user_id, 'cta_supervision_plan_name', $plan_name );
		// Plan axis only — application pending lives on cta_approval_status.
		update_user_meta( $user_id, 'cta_supervision_status', 'pending_approval' );

		// Keep Associate supervision application in sync when still awaiting review.
		if ( class_exists( 'CTA_Associate_Access' ) && CTA_Associate_Access::is_associate( $user_id ) ) {
			$approval = CTA_Associate_Access::get_approval_status( $user_id );

			if ( '' === $approval || CTA_Associate_Access::STATUS_PENDING === $approval ) {
				update_user_meta( $user_id, 'cta_approval_status', CTA_Associate_Access::STATUS_PENDING );
				CTA_Associate_Access::notify_admins_pending_application( $user_id );
			} elseif ( CTA_Associate_Access::STATUS_APPROVED === $approval ) {
				// Already-approved Associates unlock immediately after purchase.
				update_user_meta( $user_id, 'cta_supervision_status', 'active' );
			}

			// Never let a supervision purchase deactivate CE / Exam Prep.
			CTA_Associate_Access::ensure_account_active( $user_id );
		}

		if ( ! empty( $args['send_receipt'] ) ) {
			CTA_Emails::send(
				'payment_receipt',
				$user_id,
				array(
					'payment_id'   => sanitize_text_field( $args['receipt_payment_id'] ? $args['receipt_payment_id'] : $stripe_ref ),
					'product_name' => $plan_name,
				)
			);
		}

		return $payment_id;
	}

	/**
	 * Skip Stripe and activate a membership bundle (testing mode).
	 *
	 * @param object $bundle Bundle row.
	 */
	public function bypass_bundle_purchase( $bundle ) {
		$bundle = $this->normalize_supervision_bundle( $bundle );
		$user_id    = get_current_user_id();
		$payment_id = 'bypass-bundle-' . time();
		$billing    = sanitize_text_field( $bundle->billing_cycle );

		$this->activate_bundle_access( $user_id, (int) $bundle->id, $billing, $payment_id );

		$redirect = CTA_Emails::get_page_url( 'cta_student_dashboard_page_id' );
		if ( ! $redirect ) {
			$redirect = home_url( '/' );
		}

		wp_send_json_success(
			array(
				'enrolled'     => true,
				'redirect_url' => $redirect,
				'message'      => __( 'Plan activated (payment bypass mode).', 'cta-lms' ),
			)
		);
	}

	/**
	 * Get course player URL for a course.
	 *
	 * @param int $course_id Course ID.
	 * @return string
	 */
	private function get_course_player_url( $course_id ) {
		$page = $this->get_page_url( 'cta_course_player_page_id' );
		if ( ! $page ) {
			$page = $this->get_page_url( 'cta_single_course_page_id' );
		}
		if ( ! $page ) {
			$page = home_url( '/' );
		}

		return add_query_arg( 'course_id', $course_id, $page );
	}

	/**
	 * Enroll a user in a course if not already enrolled.
	 *
	 * @param int    $user_id    User ID.
	 * @param int    $course_id  Course ID.
	 * @param string $payment_id Payment reference ID.
	 */
	private function enroll_user_in_course( $user_id, $course_id, $payment_id, $args = array() ) {
		if ( ! $course_id ) {
			return;
		}

		$this->create_enrollment( $user_id, $course_id, $payment_id, $args );
	}

	/**
	 * Create course enrollment after successful payment.
	 *
	 * @param int    $user_id    User ID.
	 * @param int    $course_id  Course ID.
	 * @param string $payment_id Stripe session/payment ID.
	 * @param array  $args {
	 *     Optional enrollment options.
	 *
	 *     @type string      $access_source purchase|membership. Default purchase.
	 *     @type string|null $expires_at    MySQL datetime or null (permanent).
	 * }
	 * @return bool True when enrollment exists or was created.
	 */
	private function create_enrollment( $user_id, $course_id, $payment_id, $args = array() ) {
		global $wpdb;

		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );

		if ( ! $user_id || ! $course_id ) {
			return false;
		}

		$args = wp_parse_args(
			$args,
			array(
				'access_source' => class_exists( 'CTA_CE_Access' ) ? CTA_CE_Access::SOURCE_PURCHASE : 'purchase',
				'expires_at'    => null,
			)
		);

		$access_source = sanitize_key( (string) $args['access_source'] );
		if ( ! in_array( $access_source, array( 'purchase', 'membership' ), true ) ) {
			$access_source = 'purchase';
		}

		$expires_at = null;
		if ( ! empty( $args['expires_at'] ) ) {
			$expires_at = sanitize_text_field( (string) $args['expires_at'] );
		}

		// Individual CE purchase is always permanent.
		$course = CTA_Database::get_course( $course_id );
		$is_ce  = $course && ( ! class_exists( 'CTA_Exam_Access' ) || ! CTA_Exam_Access::is_exam_prep( $course ) );
		if ( $is_ce && 'purchase' === $access_source ) {
			$expires_at = null;
		}

		// Never downgrade a prior individual purchase to membership access.
		if ( $is_ce && class_exists( 'CTA_CE_Access' ) && CTA_CE_Access::user_has_individual_purchase( $user_id, $course_id ) ) {
			$access_source = CTA_CE_Access::SOURCE_PURCHASE;
			$expires_at    = null;
		}

		$table = $wpdb->prefix . 'cta_enrollments';

		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE user_id = %d AND course_id = %d
				LIMIT 1",
				$user_id,
				$course_id
			)
		);

		$created_or_restored = false;

		if ( $existing ) {
			$existing_source = ! empty( $existing->access_source )
				? sanitize_key( (string) $existing->access_source )
				: '';

			// Preserve purchase permanence if already purchased.
			if ( 'purchase' === $existing_source || ( class_exists( 'CTA_CE_Access' ) && CTA_CE_Access::user_has_individual_purchase( $user_id, $course_id ) ) ) {
				$access_source = 'purchase';
				$expires_at    = null;
			}

			$update = array(
				'payment_id'    => sanitize_text_field( $payment_id ),
				'access_source' => $access_source,
				'expires_at'    => $expires_at,
			);
			$formats = array( '%s', '%s', '%s' );

			if ( 'active' === $existing->status || 'completed' === $existing->status ) {
				// Keep status; only refresh access metadata.
			} else {
				// Restore revoked/other statuses to active for a new grant.
				$update['status']   = 'active';
				$formats[]          = '%s';
			}

			$updated = $wpdb->update(
				$table,
				$update,
				array( 'id' => (int) $existing->id ),
				$formats,
				array( '%d' )
			);

			$created_or_restored = false !== $updated;
		} else {
			$inserted = $wpdb->insert(
				$table,
				array(
					'user_id'       => $user_id,
					'course_id'     => $course_id,
					'status'        => 'active',
					'progress'      => 0,
					'payment_id'    => sanitize_text_field( $payment_id ),
					'access_source' => $access_source,
					'expires_at'    => $expires_at,
				),
				array( '%d', '%d', '%s', '%d', '%s', '%s', '%s' )
			);

			if ( ! $inserted ) {
				// Unique-key race: another request may have inserted first.
				$retry = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM {$table}
						WHERE user_id = %d AND course_id = %d
						LIMIT 1",
						$user_id,
						$course_id
					)
				);

				$created_or_restored = (bool) $retry;
			} else {
				$created_or_restored = true;

				CTA_Emails::send(
					'enrollment_confirmation',
					$user_id,
					array(
						'course_id'  => $course_id,
						'payment_id' => $payment_id,
					)
				);
			}
		}

		// Exam prep: always ensure timed access row exists (progress stays in enrollments).
		if ( $created_or_restored && class_exists( 'CTA_Exam_Access' ) ) {
			if ( $course && CTA_Exam_Access::is_exam_prep( $course ) ) {
				$months = ! empty( $course->access_period_months ) ? (int) $course->access_period_months : 6;
				CTA_Exam_Access::grant_access( $user_id, $course_id, $months );
			}
		}

		return $created_or_restored;
	}

	/**
	 * Create enrollments for completed course payments that never got a row.
	 *
	 * @param int $user_id User ID.
	 * @return bool True when at least one enrollment was created/restored.
	 */
	public function maybe_sync_course_enrollments_from_payments( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return false;
		}

		$payments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, product_id, stripe_payment_id
				FROM {$wpdb->prefix}cta_payments
				WHERE user_id = %d
				AND product_type = 'course'
				AND status = 'completed'
				AND product_id > 0
				ORDER BY id DESC
				LIMIT 20",
				$user_id
			)
		);

		if ( empty( $payments ) ) {
			return false;
		}

		$did_sync = false;

		foreach ( $payments as $payment ) {
			$course_id = absint( $payment->product_id );
			if ( ! $course_id ) {
				continue;
			}

			$has_enrollment = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}cta_enrollments
					WHERE user_id = %d
					AND course_id = %d
					AND status IN ('active','completed')
					LIMIT 1",
					$user_id,
					$course_id
				)
			);

			if ( $has_enrollment ) {
				continue;
			}

			$payment_ref = ! empty( $payment->stripe_payment_id )
				? (string) $payment->stripe_payment_id
				: ( 'payment-' . (int) $payment->id );

			if ( $this->create_enrollment(
				$user_id,
				$course_id,
				$payment_ref,
				array(
					'access_source' => 'purchase',
					'expires_at'    => null,
				)
			) ) {
				$did_sync = true;
			}
		}

		return $did_sync;
	}

	/**
	 * Handle cancelled supervision subscription.
	 *
	 * @param object $subscription Stripe subscription object.
	 */
	private function handle_subscription_cancelled( $subscription ) {
		global $wpdb;

		$subscription_id = sanitize_text_field( $subscription->id ?? '' );

		if ( ! $subscription_id ) {
			return;
		}

		$wpdb->update(
			$wpdb->prefix . 'cta_payments',
			array( 'status' => 'refunded' ),
			array( 'stripe_payment_id' => $subscription_id ),
			array( '%s' ),
			array( '%s' )
		);

		$user_id = $this->get_user_id_by_subscription( $subscription_id );

		if ( $user_id ) {
			update_user_meta( $user_id, 'cta_supervision_status', 'cancelled' );
			update_user_meta( $user_id, 'cta_supervision_cancel_at_period_end', '0' );
			CTA_Emails::send( 'supervision_locked', $user_id );

			// Revoke membership-sourced CE access; keep individual purchases + certificates.
			if ( class_exists( 'CTA_CE_Access' ) ) {
				CTA_CE_Access::revoke_membership_access( $user_id );
			}
		}
	}

	/**
	 * Restore access after a successful subscription invoice payment.
	 *
	 * @param object $invoice Stripe invoice object.
	 */
	private function handle_subscription_invoice_paid( $invoice ) {
		$subscription_id = sanitize_text_field( $invoice->subscription ?? '' );

		if ( ! $subscription_id || ! class_exists( '\Stripe\Subscription' ) ) {
			return;
		}

		try {
			$subscription = \Stripe\Subscription::retrieve( $subscription_id );
			$this->sync_subscription_status_from_stripe( $subscription );
		} catch ( Exception $e ) {
			$user_id = $this->get_user_id_by_subscription( $subscription_id );
			if ( $user_id ) {
				update_user_meta( $user_id, 'cta_supervision_status', 'active' );
				update_user_meta( $user_id, 'cta_supervision_cancel_at_period_end', '0' );
			}
		}
	}

	/**
	 * Handle failed subscription invoice payment.
	 *
	 * @param object $invoice Stripe invoice object.
	 */
	private function handle_subscription_payment_failed( $invoice ) {
		$subscription_id = sanitize_text_field( $invoice->subscription ?? '' );

		if ( ! $subscription_id ) {
			return;
		}

		$user_id = $this->get_user_id_by_subscription( $subscription_id );

		if ( $user_id ) {
			update_user_meta( $user_id, 'cta_supervision_status', 'past_due' );
			$plan_slug = class_exists( 'CTA_Supervision_Plans' )
				? CTA_Supervision_Plans::resolve_user_plan_slug( $user_id )
				: 'group';
			CTA_Emails::send(
				'payment_failed',
				$user_id,
				array(
					'subscription_plan' => CTA_Supervision_Plans::get_name( $plan_slug ),
				)
			);
		}
	}

	/**
	 * Find WordPress user ID by Stripe subscription ID.
	 *
	 * @param string $subscription_id Stripe subscription ID.
	 * @return int
	 */
	private function get_user_id_by_subscription( $subscription_id ) {
		global $wpdb;

		$user_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->prefix}cta_payments
				WHERE stripe_payment_id = %s
				LIMIT 1",
				$subscription_id
			)
		);

		if ( $user_id ) {
			return (int) $user_id;
		}

		$users = get_users(
			array(
				'meta_key'   => 'cta_supervision_subscription_id',
				'meta_value' => $subscription_id,
				'number'     => 1,
				'fields'     => 'ID',
			)
		);

		if ( ! empty( $users[0] ) ) {
			return (int) $users[0];
		}

		$users = get_users(
			array(
				'meta_key'   => 'cta_bundle_subscription_id',
				'meta_value' => $subscription_id,
				'number'     => 1,
				'fields'     => 'ID',
			)
		);

		return ! empty( $users[0] ) ? (int) $users[0] : 0;
	}

	/**
	 * Build a Stripe Checkout success URL with an unencoded session placeholder.
	 *
	 * WordPress add_query_arg() encodes braces, which prevents Stripe from
	 * replacing {CHECKOUT_SESSION_ID}. Append the placeholder literally.
	 *
	 * @param string $base_url Base return URL.
	 * @param array  $args     Extra query args (without session_id).
	 * @return string
	 */
	private function build_checkout_success_url( $base_url, $args = array() ) {
		$url = $base_url ? $base_url : home_url( '/' );

		if ( ! empty( $args ) ) {
			$url = add_query_arg( $args, $url );
		}

		$separator = ( false === strpos( $url, '?' ) ) ? '?' : '&';

		return $url . $separator . 'session_id={CHECKOUT_SESSION_ID}';
	}

	/**
	 * Finalize a Stripe Checkout session after the buyer returns from Stripe.
	 *
	 * Webhooks can be delayed/missing; this activates access on success redirect.
	 *
	 * @param string $session_id Stripe Checkout Session ID (cs_...).
	 * @param int    $user_id    Expected WordPress user ID (0 = use session metadata).
	 * @return bool
	 */
	public function finalize_checkout_session( $session_id, $user_id = 0 ) {
		$session_id = sanitize_text_field( $session_id );
		$user_id    = absint( $user_id );

		if ( '' === $session_id || 0 !== strpos( $session_id, 'cs_' ) ) {
			return false;
		}

		if ( ! $this->is_configured() ) {
			return false;
		}

		try {
			$session = \Stripe\Checkout\Session::retrieve( $session_id );
		} catch ( \Exception $e ) {
			return false;
		}

		if ( ! $session || empty( $session->id ) ) {
			return false;
		}

		$session_status  = sanitize_text_field( (string) ( $session->status ?? '' ) );
		$payment_status  = sanitize_text_field( (string) ( $session->payment_status ?? '' ) );
		$is_paid         = in_array( $payment_status, array( 'paid', 'no_payment_required' ), true );
		$is_complete     = 'complete' === $session_status;

		if ( ! $is_complete && ! $is_paid ) {
			return false;
		}

		$metadata       = isset( $session->metadata ) ? (array) $session->metadata : array();
		$session_user_id = absint( $metadata['user_id'] ?? 0 );

		if ( $user_id && $session_user_id && (int) $user_id !== (int) $session_user_id ) {
			return false;
		}

		$this->handle_checkout_completed( $session );

		$final_user_id = $session_user_id ? $session_user_id : $user_id;
		if ( $final_user_id ) {
			clean_user_cache( $final_user_id );
		}

		return true;
	}

	/**
	 * Recover pending supervision checkout rows for the current user.
	 *
	 * @param int $user_id User ID.
	 * @return bool True when at least one session was finalized.
	 */
	public function maybe_finalize_user_pending_checkouts( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );

		if ( ! $user_id || ! $this->is_configured() ) {
			return false;
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT stripe_payment_id
				FROM {$wpdb->prefix}cta_payments
				WHERE user_id = %d
				AND product_type = 'supervision'
				AND status = 'pending'
				AND stripe_payment_id LIKE 'cs_%%'
				ORDER BY id DESC
				LIMIT 5",
				$user_id
			)
		);

		if ( empty( $rows ) ) {
			return false;
		}

		$did_finalize = false;

		foreach ( $rows as $row ) {
			$session_id = sanitize_text_field( (string) $row->stripe_payment_id );
			if ( $this->finalize_checkout_session( $session_id, $user_id ) ) {
				$did_finalize = true;
			}
		}

		return $did_finalize;
	}

	/**
	 * Recover pending course checkout rows for a user.
	 *
	 * This keeps the student dashboard in sync when the Stripe webhook is
	 * delayed or unavailable after a successful checkout.
	 *
	 * @param int $user_id User ID.
	 * @return bool True when at least one session was finalized.
	 */
	public function maybe_finalize_user_pending_course_checkouts( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );

		if ( ! $user_id || ! $this->is_configured() ) {
			return false;
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT stripe_payment_id
				FROM {$wpdb->prefix}cta_payments
				WHERE user_id = %d
				AND product_type = 'course'
				AND status = 'pending'
				AND stripe_payment_id LIKE 'cs_%%'
				ORDER BY id DESC
				LIMIT 5",
				$user_id
			)
		);

		if ( empty( $rows ) ) {
			return false;
		}

		$did_finalize = false;

		foreach ( $rows as $row ) {
			$session_id = sanitize_text_field( (string) $row->stripe_payment_id );
			if ( $this->finalize_checkout_session( $session_id, $user_id ) ) {
				$did_finalize = true;
			}
		}

		return $did_finalize;
	}

	/**
	 * Get permalink from plugin page option.
	 *
	 * @param string $option_name Option key.
	 * @return string
	 */
	private function get_page_url( $option_name ) {
		$page_id = absint( get_option( $option_name, 0 ) );

		if ( ! $page_id ) {
			return '';
		}

		$url = get_permalink( $page_id );

		return $url ? $url : '';
	}
}
}
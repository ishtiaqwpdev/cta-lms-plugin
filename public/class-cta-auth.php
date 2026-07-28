<?php
/**
 * AJAX authentication and login form shortcode.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Auth
 */
if ( ! class_exists( 'CTA_Auth' ) ) {

class CTA_Auth {

	/**
	 * Register AJAX handlers and shortcode.
	 */
	public function __construct() {
		add_action( 'wp_ajax_cta_login', array( $this, 'handle_login' ) );
		add_action( 'wp_ajax_nopriv_cta_login', array( $this, 'handle_login' ) );

		add_action( 'wp_ajax_cta_register', array( $this, 'handle_register' ) );
		add_action( 'wp_ajax_nopriv_cta_register', array( $this, 'handle_register' ) );

		add_action( 'wp_ajax_cta_auth_nonce', array( $this, 'handle_auth_nonce' ) );
		add_action( 'wp_ajax_nopriv_cta_auth_nonce', array( $this, 'handle_auth_nonce' ) );

		add_action( 'template_redirect', array( $this, 'nocache_login_page' ) );

		add_shortcode( 'cta_login_form', array( $this, 'render_login_form' ) );
	}

	/**
	 * Prevent page/object caches from serving stale auth nonces.
	 */
	public function nocache_login_page() {
		if ( ! class_exists( 'CTA_Loader' ) || ! CTA_Loader::is_login_page() ) {
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		nocache_headers();
	}

	/**
	 * Return fresh login/register nonces (bypasses cached page HTML).
	 */
	public function handle_auth_nonce() {
		wp_send_json_success(
			array(
				'login_nonce'    => wp_create_nonce( 'cta_login_action' ),
				'register_nonce' => wp_create_nonce( 'cta_register_action' ),
			)
		);
	}

	/**
	 * Verify auth nonce without dying (so the UI gets a JSON error).
	 *
	 * @param string $action Nonce action.
	 */
	private function verify_auth_nonce( $action ) {
		$nonce = sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ?? '' ) );

		if ( ! $nonce || ! wp_verify_nonce( $nonce, $action ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Your session expired. Please refresh the page and try again.', 'cta-lms' ),
					'code'    => 'invalid_nonce',
				)
			);
		}
	}

	/**
	 * Render the login/register form shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_login_form( $atts ) {
		$is_logged_in  = is_user_logged_in();
		$dashboard_url = home_url( '/' );
		$user          = null;
		$home_url      = home_url( '/' );
		$logo_url      = cta_lms_get_logo_url();
		$site_name     = get_bloginfo( 'name' );
		$lost_password_url = wp_lostpassword_url();
		$logout_url    = wp_logout_url( home_url() );

		if ( $is_logged_in ) {
			$user          = wp_get_current_user();
			$dashboard_url = $this->get_dashboard_url( $user );
		}

		ob_start();
		include CTA_PLUGIN_DIR . 'templates/login.php';
		return ob_get_clean();
	}

	/**
	 * Handle login AJAX request.
	 */
	public function handle_login() {
		$this->verify_auth_nonce( 'cta_login_action' );

		$email    = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$password = wp_unslash( $_POST['password'] ?? '' );

		if ( empty( $email ) || empty( $password ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please enter your email and password.', 'cta-lms' ),
				)
			);
		}

		$user = get_user_by( 'email', $email );

		if ( ! $user ) {
			wp_send_json_error(
				array(
					'message' => __( 'No account found with this email address.', 'cta-lms' ),
				)
			);
		}

		$credentials = array(
			'user_login'    => $user->user_login,
			'user_password' => $password,
			'remember'      => true,
		);

		$result = wp_signon( $credentials, is_ssl() );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Incorrect password. Please try again.', 'cta-lms' ),
				)
			);
		}

		$redirect_url = $this->get_dashboard_url( $result );

		wp_send_json_success(
			array(
				'message'      => __( 'Login successful! Redirecting...', 'cta-lms' ),
				'redirect_url' => $redirect_url,
			)
		);
	}

	/**
	 * Handle registration AJAX request.
	 */
	public function handle_register() {
		$this->verify_auth_nonce( 'cta_register_action' );

		$fullname  = sanitize_text_field( wp_unslash( $_POST['fullname'] ?? '' ) );
		$email     = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$password  = wp_unslash( $_POST['password'] ?? '' );
		$confirm   = wp_unslash( $_POST['confirm_password'] ?? '' );
		$user_type = sanitize_text_field( wp_unslash( $_POST['user_type'] ?? '' ) );

		if ( empty( $fullname ) || empty( $email ) || empty( $password ) || empty( $user_type ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please fill in all required fields.', 'cta-lms' ),
				)
			);
		}

		if ( ! is_email( $email ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please enter a valid email address.', 'cta-lms' ),
				)
			);
		}

		if ( $password !== $confirm ) {
			wp_send_json_error(
				array(
					'message' => __( 'Passwords do not match.', 'cta-lms' ),
				)
			);
		}

		if ( strlen( $password ) < 8 ) {
			wp_send_json_error(
				array(
					'message' => __( 'Password must be at least 8 characters.', 'cta-lms' ),
				)
			);
		}

		$allowed_roles = array(
			'cta_licensed_professional',
			'cta_associate',
		);

		if ( ! in_array( $user_type, $allowed_roles, true ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please select a valid account type.', 'cta-lms' ),
				)
			);
		}

		$agency_fields = array();
		if ( 'cta_associate' === $user_type && class_exists( 'CTA_Associate_Access' ) ) {
			$agency_fields = CTA_Associate_Access::parse_agency_fields_from_request( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$employer      = $agency_fields['employer_agency_name'] ?? '';
			$rep_name      = $agency_fields['agency_representative_name'] ?? '';
			$rep_email     = $agency_fields['agency_representative_email'] ?? '';

			if ( '' === $employer || '' === $rep_name || '' === $rep_email ) {
				wp_send_json_error(
					array(
						'message' => __( 'Please provide Employer/Agency Name, Agency Representative Name, and Agency Representative Email for your supervision application.', 'cta-lms' ),
					)
				);
			}

			if ( ! is_email( $rep_email ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'Please enter a valid agency representative email.', 'cta-lms' ),
					)
				);
			}
		}

		if ( email_exists( $email ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'An account with this email already exists.', 'cta-lms' ),
				)
			);
		}

		$username = sanitize_user( (string) strstr( $email, '@', true ), true );

		if ( '' === $username ) {
			$username = 'cta_user_' . time();
		}

		if ( username_exists( $username ) ) {
			$username = $username . '_' . time();
		}

		$user_id = wp_create_user( $username, $password, $email );

		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error(
				array(
					'message' => $user_id->get_error_message()
						? $user_id->get_error_message()
						: __( 'Registration failed. Please try again.', 'cta-lms' ),
				)
			);
		}

		wp_update_user(
			array(
				'ID'           => $user_id,
				'display_name' => $fullname,
				'nickname'     => $fullname,
			)
		);

		if ( function_exists( 'cta_lms_sync_user_name_parts' ) ) {
			cta_lms_sync_user_name_parts( $user_id, $fullname );
		}

		// Force-refresh so later get_userdata / cookies see the legal name.
		clean_user_cache( $user_id );

		if ( class_exists( 'CTA_Roles' ) ) {
			CTA_Roles::create_roles();
		}

		$user_obj = new WP_User( $user_id );
		$user_obj->set_role( $user_type );

		// General account is active immediately for CE / Exam Prep (all roles).
		if ( class_exists( 'CTA_Associate_Access' ) ) {
			CTA_Associate_Access::ensure_account_active( $user_id );
		} else {
			update_user_meta( $user_id, 'cta_account_status', 'active' );
		}

		// Associates: open supervision application at registration so Admin Approvals
		// receives the request immediately (CE stays usable while pending).
		if ( 'cta_associate' === $user_type && class_exists( 'CTA_Associate_Access' ) ) {
			$saved = CTA_Associate_Access::save_supervision_application_agency( $user_id, $agency_fields, true );
			if ( is_wp_error( $saved ) ) {
				if ( ! function_exists( 'wp_delete_user' ) ) {
					require_once ABSPATH . 'wp-admin/includes/user.php';
				}
				if ( function_exists( 'wp_delete_user' ) ) {
					wp_delete_user( $user_id );
				}
				wp_send_json_error(
					array(
						'message' => $saved->get_error_message(),
					)
				);
			}
			CTA_Associate_Access::heal_decoupled_statuses( $user_id );
		}

		// Refresh user so role/meta are available to email helpers.
		clean_user_cache( $user_id );
		$user = get_user_by( 'id', $user_id );

		// Never let email delivery break account creation / JSON response.
		try {
			CTA_Emails::send( 'welcome', $user_id );
		} catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'CTA_Auth: registration email failed — ' . $e->getMessage() );
			}
		}

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );

		$redirect_url = $this->get_dashboard_url( $user ? $user : $user_obj );

		$success_message = __( 'Account created successfully! Redirecting...', 'cta-lms' );
		if ( 'cta_associate' === $user_type ) {
			$success_message = __(
				'Account created. Your supervision application was sent to CTA admin for approval. You can use CE courses while you wait. Redirecting...',
				'cta-lms'
			);
		}

		wp_send_json_success(
			array(
				'message'      => $success_message,
				'redirect_url' => $redirect_url,
			)
		);
	}

	/**
	 * Get post-login destination based on user role.
	 *
	 * Always prefers the frontend customer dashboards (never wp-admin).
	 * Pending associates land on the CE dashboard (not the supervision pending screen).
	 * Associates with full supervision access may land on the supervision dashboard.
	 *
	 * @param WP_User $user WordPress user object.
	 * @return string
	 */
	private function get_dashboard_url( $user ) {
		if ( class_exists( 'CTA_Associate_Access' ) ) {
			$url = CTA_Associate_Access::get_general_dashboard_url( (int) $user->ID );
			if ( $url ) {
				return $url;
			}
		}

		$roles = (array) $user->roles;

		if ( in_array( 'cta_licensed_professional', $roles, true ) || in_array( 'cta_associate', $roles, true ) ) {
			return $this->resolve_dashboard_page_url(
				'cta_student_dashboard_page_id',
				'cta_student_dashboard'
			);
		}

		// Admins and other roles: CE student (customer) dashboard, same as header/emails.
		$student_url = $this->resolve_dashboard_page_url(
			'cta_student_dashboard_page_id',
			'cta_student_dashboard'
		);

		if ( $this->is_usable_frontend_url( $student_url ) ) {
			return $student_url;
		}

		$supervision_url = $this->resolve_dashboard_page_url(
			'cta_supervision_dashboard_page_id',
			'cta_supervision_dashboard'
		);

		if ( $this->is_usable_frontend_url( $supervision_url ) ) {
			return $supervision_url;
		}

		return home_url( '/' );
	}

	/**
	 * Resolve a CTA dashboard page URL from option or shortcode fallback.
	 *
	 * @param string $option_name Option key storing page ID.
	 * @param string $shortcode   Shortcode tag used to find the page.
	 * @return string
	 */
	private function resolve_dashboard_page_url( $option_name, $shortcode ) {
		$page_id = absint( get_option( $option_name, 0 ) );

		if ( ! $page_id && function_exists( 'cta_lms_find_page_id_by_shortcode' ) ) {
			$page_id = absint( cta_lms_find_page_id_by_shortcode( $shortcode ) );
		}

		if ( ! $page_id ) {
			return home_url( '/' );
		}

		$url = get_permalink( $page_id );

		return $url ? $url : home_url( '/' );
	}

	/**
	 * Whether a URL is a usable frontend destination (not empty/home-only fallback).
	 *
	 * @param string $url Candidate URL.
	 * @return bool
	 */
	private function is_usable_frontend_url( $url ) {
		$url = (string) $url;

		if ( '' === $url ) {
			return false;
		}

		$home = untrailingslashit( home_url( '/' ) );
		$cand = untrailingslashit( $url );

		return $cand && $cand !== $home;
	}
}
}
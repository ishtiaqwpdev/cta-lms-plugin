<?php
/**
 * Register all actions and filters for the plugin.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Loader
 */
if ( ! class_exists( 'CTA_Loader' ) ) {

class CTA_Loader {

	/**
	 * Actions to register with WordPress.
	 *
	 * @var array
	 */
	protected $actions = array();

	/**
	 * Filters to register with WordPress.
	 *
	 * @var array
	 */
	protected $filters = array();

	/**
	 * Queue an action hook.
	 *
	 * @param string   $hook          Hook name.
	 * @param object   $component     Object containing the callback.
	 * @param string   $callback      Callback method name.
	 * @param int      $priority      Hook priority.
	 * @param int      $accepted_args Number of accepted arguments.
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Queue a filter hook.
	 *
	 * @param string   $hook          Hook name.
	 * @param object   $component     Object containing the callback.
	 * @param string   $callback      Callback method name.
	 * @param int      $priority      Hook priority.
	 * @param int      $accepted_args Number of accepted arguments.
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Add a hook to the collection.
	 *
	 * @param array    $hooks         Existing hooks collection.
	 * @param string   $hook          Hook name.
	 * @param object   $component     Object containing the callback.
	 * @param string   $callback      Callback method name.
	 * @param int      $priority      Hook priority.
	 * @param int      $accepted_args Number of accepted arguments.
	 * @return array
	 */
	private function add( $hooks, $hook, $component, $callback, $priority, $accepted_args ) {
		$hooks[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);

		return $hooks;
	}

	/**
	 * Register queued hooks and set up plugin functionality.
	 */
	public function run() {
		$this->add_action( 'wp_enqueue_scripts', $this, 'enqueue_public_assets' );
		$this->add_action( 'template_redirect', $this, 'nocache_dynamic_pages' );
		$this->add_filter( 'body_class', $this, 'add_body_classes' );
		$this->add_filter( 'show_admin_bar', $this, 'hide_admin_bar_on_cta_pages' );
		$this->add_action( 'init', $this, 'register_shortcodes' );

		foreach ( $this->filters as $hook ) {
			add_filter(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}

		foreach ( $this->actions as $hook ) {
			add_action(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}
	}

	/**
	 * Prevent caches from serving stale logged-in dashboard state.
	 */
	public function nocache_dynamic_pages() {
		if ( ! self::is_dashboard_page() ) {
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		if ( ! defined( 'DONOTCACHEOBJECT' ) ) {
			define( 'DONOTCACHEOBJECT', true );
		}

		nocache_headers();
	}

	/**
	 * Enqueue public-facing CSS and JS on CTA pages only.
	 */
	public function enqueue_public_assets() {
		if ( ! self::should_enqueue_assets() ) {
			return;
		}

		wp_enqueue_style(
			'cta-google-fonts',
			'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap',
			array(),
			null
		);

		wp_enqueue_style(
			'cta-variables',
			CTA_PLUGIN_URL . 'assets/css/variables.css',
			array(),
			CTA_VERSION
		);

		wp_enqueue_style(
			'cta-global',
			CTA_PLUGIN_URL . 'assets/css/global.css',
			array( 'cta-variables' ),
			CTA_VERSION
		);

		wp_enqueue_style(
			'cta-components',
			CTA_PLUGIN_URL . 'assets/css/components.css',
			array( 'cta-global' ),
			CTA_VERSION
		);

		wp_enqueue_style(
			'cta-layout',
			CTA_PLUGIN_URL . 'assets/css/layout.css',
			array( 'cta-components' ),
			CTA_VERSION
		);

		wp_enqueue_style(
			'cta-theme-compat',
			CTA_PLUGIN_URL . 'assets/css/theme-compat.css',
			array( 'cta-layout' ),
			CTA_VERSION
		);

		wp_enqueue_script(
			'cta-main',
			CTA_PLUGIN_URL . 'assets/js/main.js',
			array( 'jquery' ),
			CTA_VERSION,
			true
		);

		wp_localize_script(
			'cta-main',
			'ctaAjax',
			array(
				'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
				'nonce'                => wp_create_nonce( 'cta_nonce' ),
				'pluginUrl'            => CTA_PLUGIN_URL,
				'isLoggedIn'           => is_user_logged_in() ? 'yes' : 'no',
				'currentUser'          => is_user_logged_in() ? wp_get_current_user()->display_name : '',
				'stripePublishableKey' => get_option( 'cta_stripe_publishable_key', '' ),
				'stripeConfigured'     => file_exists( CTA_PLUGIN_DIR . 'vendor/autoload.php' ) && ! empty( get_option( 'cta_stripe_secret_key', '' ) ) && ! empty( get_option( 'cta_stripe_publishable_key', '' ) ),
				'paymentsBypass'       => CTA_Stripe::is_payments_bypass_enabled() ? 'yes' : 'no',
				'loginRequiredMessage' => __( 'Please log in to continue.', 'cta-lms' ),
				'loginUrl'             => self::get_page_permalink( 'cta_login_page_id' ),
				'dashboardUrl'         => self::get_dashboard_url_for_user(),
				'logoutUrl'            => is_user_logged_in() ? wp_logout_url( home_url( '/' ) ) : '',
				'studentDashboardUrl'  => self::get_page_permalink( 'cta_student_dashboard_page_id' ),
				'supervisionDashboardUrl' => self::get_page_permalink( 'cta_supervision_dashboard_page_id' ),
				'coursePlayerUrl'      => self::get_page_permalink( 'cta_course_player_page_id' ),
				'quizPageUrl'          => self::get_page_permalink( 'cta_quiz_page_id' ),
				'coursesUrl'             => self::get_page_permalink( 'cta_courses_page_id' ),
			)
		);
	}

	/**
	 * Add body classes for CTA pages so theme styles can be overridden safely.
	 *
	 * @param array $classes Existing body classes.
	 * @return array
	 */
	public function add_body_classes( $classes ) {
		if ( ! self::should_enqueue_assets() ) {
			return $classes;
		}

		$classes[] = 'cta-lms-page';

		if ( self::is_login_page() ) {
			$classes[] = 'cta-lms-page--login';
			$classes[] = 'cta-lms-page--no-chrome';
		}

		if ( self::is_dashboard_page() ) {
			$classes[] = 'dashboard-page';
			$classes[] = 'cta-lms-page--no-chrome';
		}

		return $classes;
	}

	/**
	 * Hide the WordPress admin bar for non-admin users on CTA frontend pages.
	 *
	 * @param bool $show Whether to show the admin bar.
	 * @return bool
	 */
	public function hide_admin_bar_on_cta_pages( $show ) {
		if ( is_admin() || ! is_user_logged_in() ) {
			return $show;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return $show;
		}

		if ( self::should_enqueue_assets() ) {
			return false;
		}

		return $show;
	}

	/**
	 * Determine whether CTA frontend assets should load on the current request.
	 *
	 * @return bool
	 */
	public static function should_enqueue_assets() {
		if ( is_admin() ) {
			return false;
		}

		if ( self::is_registered_plugin_page() ) {
			return true;
		}

		global $post;

		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		return self::post_has_cta_shortcode( $post );
	}

	/**
	 * Whether the current page should hide theme/header/footer chrome.
	 *
	 * Login and dashboard surfaces are full-bleed app views.
	 *
	 * @return bool
	 */
	public static function is_no_chrome_page() {
		return self::is_login_page() || self::is_dashboard_page();
	}

	/**
	 * Whether the current request is the CTA login page.
	 *
	 * @return bool
	 */
	public static function is_login_page() {
		if ( self::is_current_plugin_page( 'cta_login_page_id' ) ) {
			return true;
		}

		return self::current_post_has_shortcode( 'cta_login_form' );
	}

	/**
	 * Whether the current request is a CTA dashboard / app page.
	 *
	 * @return bool
	 */
	public static function is_dashboard_page() {
		$dashboard_options = array(
			'cta_student_dashboard_page_id',
			'cta_course_player_page_id',
			'cta_supervision_dashboard_page_id',
			'cta_quiz_page_id',
		);

		foreach ( $dashboard_options as $option_name ) {
			if ( self::is_current_plugin_page( $option_name ) ) {
				return true;
			}
		}

		$dashboard_shortcodes = array(
			'cta_student_dashboard',
			'cta_course_player',
			'cta_supervision_dashboard',
			'cta_quiz',
		);

		foreach ( $dashboard_shortcodes as $shortcode ) {
			if ( self::current_post_has_shortcode( $shortcode ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the current singular post content includes a shortcode.
	 *
	 * @param string $shortcode Shortcode tag.
	 * @return bool
	 */
	private static function current_post_has_shortcode( $shortcode ) {
		global $post;

		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		if ( has_shortcode( $post->post_content, $shortcode ) ) {
			return true;
		}

		if ( metadata_exists( 'post', $post->ID, '_elementor_data' ) ) {
			$elementor_data = get_post_meta( $post->ID, '_elementor_data', true );

			if ( is_string( $elementor_data ) && false !== strpos( $elementor_data, '[' . $shortcode ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether the current singular page matches a plugin page option.
	 *
	 * @param string $option_name Option key storing the page ID.
	 * @return bool
	 */
	private static function is_current_plugin_page( $option_name ) {
		if ( ! is_page() ) {
			return false;
		}

		$page_id = absint( get_option( $option_name, 0 ) );

		return $page_id > 0 && is_page( $page_id );
	}

	/**
	 * Check whether the current page is one of the configured plugin pages.
	 *
	 * @return bool
	 */
	private static function is_registered_plugin_page() {
		if ( ! is_page() ) {
			return false;
		}

		foreach ( self::get_plugin_page_option_keys() as $option_name ) {
			if ( self::is_current_plugin_page( $option_name ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether post content contains a CTA shortcode.
	 *
	 * @param WP_Post $post Post object.
	 * @return bool
	 */
	private static function post_has_cta_shortcode( $post ) {
		if ( ! empty( $post->post_content ) ) {
			foreach ( self::get_shortcode_tags() as $shortcode ) {
				if ( has_shortcode( $post->post_content, $shortcode ) ) {
					return true;
				}
			}
		}

		// Elementor stores shortcodes in builder meta instead of post_content.
		if ( metadata_exists( 'post', $post->ID, '_elementor_data' ) ) {
			$elementor_data = get_post_meta( $post->ID, '_elementor_data', true );

			if ( is_string( $elementor_data ) && '' !== $elementor_data ) {
				foreach ( self::get_shortcode_tags() as $shortcode ) {
					if ( false !== strpos( $elementor_data, '[' . $shortcode ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Plugin page option keys.
	 *
	 * @return array
	 */
	private static function get_plugin_page_option_keys() {
		return array(
			'cta_login_page_id',
			'cta_courses_page_id',
			'cta_single_course_page_id',
			'cta_supervision_page_id',
			'cta_memberships_page_id',
			'cta_faq_page_id',
			'cta_policies_page_id',
			'cta_student_dashboard_page_id',
			'cta_course_player_page_id',
			'cta_supervision_dashboard_page_id',
			'cta_quiz_page_id',
		);
	}

	/**
	 * Registered CTA shortcode tags.
	 *
	 * @return array
	 */
	private static function get_shortcode_tags() {
		return array(
			'cta_header',
			'cta_footer',
			'cta_auth_button',
			'cta_login_form',
			'cta_course_catalog',
			'cta_single_course',
			'cta_supervision_booking',
			'cta_membership_pricing',
			'cta_student_dashboard',
			'cta_course_player',
			'cta_supervision_dashboard',
			'cta_quiz',
		);
	}

	/**
	 * Get permalink for a plugin page option.
	 *
	 * @param string $option_name Option key.
	 * @return string
	 */
	private static function get_page_permalink( $option_name ) {
		$page_id = absint( get_option( $option_name, 0 ) );

		if ( ! $page_id ) {
			return '';
		}

		$url = get_permalink( $page_id );

		return $url ? $url : '';
	}

	/**
	 * Dashboard URL for the current logged-in user.
	 *
	 * @return string
	 */
	private static function get_dashboard_url_for_user() {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$user = wp_get_current_user();

		if ( in_array( 'cta_associate', (array) $user->roles, true ) ) {
			return self::get_page_permalink( 'cta_supervision_dashboard_page_id' );
		}

		return self::get_page_permalink( 'cta_student_dashboard_page_id' );
	}

	/**
	 * Register shortcodes (handled by CTA_Shortcodes class).
	 */
	public function register_shortcodes() {
		// Shortcodes are registered in public/class-cta-shortcodes.php.
	}
}
}
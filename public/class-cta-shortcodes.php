<?php
/**
 * Register and render plugin shortcodes.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Shortcodes
 */
if ( ! class_exists( 'CTA_Shortcodes' ) ) {

class CTA_Shortcodes {

	/**
	 * Register shortcodes.
	 */
	public function __construct() {
		add_shortcode( 'cta_header', array( $this, 'render_header' ) );
		add_shortcode( 'cta_footer', array( $this, 'render_footer' ) );
		add_shortcode( 'cta_auth_button', array( $this, 'render_auth_button' ) );
	}

	/**
	 * Get a page permalink from a plugin option (with slug/shortcode fallbacks).
	 *
	 * @param string $option_name Option key storing the page ID.
	 * @return string
	 */
	private function get_page_url( $option_name ) {
		if ( function_exists( 'cta_lms_get_linked_page_url' ) ) {
			return cta_lms_get_linked_page_url( $option_name );
		}

		$page_id = absint( get_option( $option_name, 0 ) );

		if ( ! $page_id ) {
			return '';
		}

		$permalink = get_permalink( $page_id );

		return $permalink ? $permalink : '';
	}

	/**
	 * Resolve page ID for nav active-state checks.
	 *
	 * @param string $option_name Option key.
	 * @return int
	 */
	private function get_page_id( $option_name ) {
		if ( function_exists( 'cta_lms_resolve_linked_page_id' ) ) {
			return cta_lms_resolve_linked_page_id( $option_name );
		}

		return absint( get_option( $option_name, 0 ) );
	}

	/**
	 * Build the public header navigation items.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_header_nav_items() {
		$defs = array(
			array(
				'label'  => __( 'CE Courses', 'cta-lms' ),
				'option' => 'cta_courses_page_id',
			),
			array(
				'label'  => __( 'Supervision', 'cta-lms' ),
				'option' => 'cta_supervision_page_id',
			),
			array(
				'label'  => __( 'Memberships', 'cta-lms' ),
				'option' => 'cta_memberships_page_id',
			),
			array(
				'label'  => __( 'About', 'cta-lms' ),
				'option' => 'cta_about_page_id',
			),
			array(
				'label'  => __( 'FAQ', 'cta-lms' ),
				'option' => 'cta_faq_page_id',
			),
			array(
				'label'  => __( 'Contact', 'cta-lms' ),
				'option' => 'cta_contact_page_id',
			),
			array(
				'label'  => __( 'Policies', 'cta-lms' ),
				'option' => 'cta_policies_page_id',
			),
		);

		$items = array();

		foreach ( $defs as $def ) {
			$page_id = $this->get_page_id( $def['option'] );
			$url     = $page_id ? get_permalink( $page_id ) : '';

			if ( ! $url ) {
				continue;
			}

			$items[] = array(
				'label'     => $def['label'],
				'url'       => $url,
				'page_id'   => $page_id,
				'is_active' => $this->is_current_page( $page_id ),
			);
		}

		/**
		 * Filter CTA header navigation items.
		 *
		 * @param array $items Nav items.
		 */
		return (array) apply_filters( 'cta_lms_header_nav_items', $items );
	}

	/**
	 * Check whether a page ID matches the current page.
	 *
	 * @param int $page_id Page ID.
	 * @return bool
	 */
	private function is_current_page( $page_id ) {
		$page_id = absint( $page_id );

		if ( ! $page_id ) {
			return false;
		}

		return is_page( $page_id );
	}

	/**
	 * Render the site header shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_header( $atts ) {
		if ( class_exists( 'CTA_Loader' ) && CTA_Loader::is_no_chrome_page() ) {
			return '';
		}

		if ( class_exists( 'CTA_Loader' ) ) {
			CTA_Loader::enqueue_frontend_assets();
		}

		$atts = shortcode_atts(
			array(
				'show_nav' => 'yes',
			),
			$atts,
			'cta_header'
		);

		$current_user = wp_get_current_user();
		$is_logged_in = is_user_logged_in();
		$show_nav     = 'yes' === $atts['show_nav'];

		$login_url     = $this->get_page_url( 'cta_login_page_id' );
		$dashboard_url = '';
		$display_name  = '';

		if ( $is_logged_in ) {
			$dashboard_url = class_exists( 'CTA_Associate_Access' )
				? CTA_Associate_Access::get_general_dashboard_url( (int) $current_user->ID )
				: $this->get_page_url( 'cta_student_dashboard_page_id' );
			$display_name = function_exists( 'cta_lms_get_user_legal_name' )
				? cta_lms_get_user_legal_name( (int) $current_user->ID )
				: ( $current_user->display_name ? $current_user->display_name : $current_user->user_login );
		}

		$nav_items = $this->get_header_nav_items();

		$nav_links = array();
		foreach ( $nav_items as $item ) {
			$nav_links[ $item['label'] ] = $item['url'];
		}

		$home_url    = home_url( '/' );
		$enroll_url  = ! empty( $nav_links[ __( 'CE Courses', 'cta-lms' ) ] )
			? $nav_links[ __( 'CE Courses', 'cta-lms' ) ]
			: ( $this->get_page_url( 'cta_courses_page_id' ) ? $this->get_page_url( 'cta_courses_page_id' ) : $home_url );
		$courses_url = $this->get_page_url( 'cta_courses_page_id' );
		$exam_prep_url = $this->get_page_url( 'cta_exam_prep_page_id' );
		if ( ! $exam_prep_url && $courses_url ) {
			$exam_prep_url = add_query_arg( 'product_type', 'exam_prep', $courses_url );
		}
		$logout_url  = wp_logout_url( home_url() );
		$logo_url    = cta_lms_get_logo_url();
		$site_name   = get_bloginfo( 'name' );

		ob_start();
		include CTA_PLUGIN_DIR . 'templates/header.php';
		return ob_get_clean();
	}

	/**
	 * Get general (CE) dashboard URL for the current user.
	 *
	 * @return string
	 */
	private function get_dashboard_url_for_user() {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		if ( class_exists( 'CTA_Associate_Access' ) ) {
			return CTA_Associate_Access::get_general_dashboard_url( get_current_user_id() );
		}

		return $this->get_page_url( 'cta_student_dashboard_page_id' );
	}

	/**
	 * Render login/dashboard toggle button shortcode.
	 *
	 * Usage:
	 * [cta_auth_button]
	 * [cta_auth_button login_url="https://yoursite.com/login/" login_text="Log In" dashboard_text="My Dashboard"]
	 * [cta_auth_button style="primary" size="sm"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_auth_button( $atts ) {
		// Account menu belongs in the header only — never inside Theme Builder footers.
		if ( did_action( 'elementor/theme/before_do_footer' ) && ! did_action( 'elementor/theme/after_do_footer' ) ) {
			return '';
		}

		if ( class_exists( 'CTA_Loader' ) ) {
			CTA_Loader::enqueue_frontend_assets();
		}

		$atts = shortcode_atts(
			array(
				'login_url'      => '',
				'dashboard_url'  => '',
				'login_text'     => __( 'Login', 'cta-lms' ),
				'dashboard_text' => __( 'My Dashboard', 'cta-lms' ),
				'style'          => 'outline',
				'size'           => '',
				'class'          => '',
			),
			$atts,
			'cta_auth_button'
		);

		$is_logged_in = is_user_logged_in();
		$login_url    = ! empty( $atts['login_url'] )
			? esc_url_raw( $atts['login_url'] )
			: $this->get_page_url( 'cta_login_page_id' );
		$login_text   = $atts['login_text'];
		// Treat legacy Elementor labels as the guest CTA; logged-in state uses My Dashboard.
		if ( '' === trim( (string) $login_text ) ) {
			$login_text = __( 'Login', 'cta-lms' );
		}

		$dashboard_url = ! empty( $atts['dashboard_url'] )
			? esc_url_raw( $atts['dashboard_url'] )
			: $this->get_dashboard_url_for_user();

		if ( ! $dashboard_url && class_exists( 'CTA_Associate_Access' ) ) {
			$dashboard_url = CTA_Associate_Access::get_general_dashboard_url();
		}

		if ( ! $dashboard_url ) {
			$dashboard_url = $this->get_page_url( 'cta_student_dashboard_page_id' );
		}

		if ( ! $dashboard_url ) {
			$dashboard_url = home_url( '/' );
		}

		if ( ! $login_url ) {
			$login_url = wp_login_url( get_permalink() );
		}

		$dashboard_text = $atts['dashboard_text'] ? $atts['dashboard_text'] : __( 'My Dashboard', 'cta-lms' );
		$logout_url     = wp_logout_url( home_url( '/' ) );
		$courses_url    = $this->get_page_url( 'cta_courses_page_id' );
		$exam_prep_url  = $this->get_page_url( 'cta_exam_prep_page_id' );
		if ( ! $exam_prep_url && $courses_url ) {
			$exam_prep_url = add_query_arg( 'product_type', 'exam_prep', $courses_url );
		}
		$display_name   = '';

		if ( $is_logged_in ) {
			$user = wp_get_current_user();
			$display_name = function_exists( 'cta_lms_get_user_legal_name' )
				? cta_lms_get_user_legal_name( (int) $user->ID )
				: ( $user->display_name ? $user->display_name : $user->user_login );
		}

		$button_class = 'btn cta-auth-button';

		if ( 'primary' === $atts['style'] ) {
			$button_class .= ' btn-primary';
		} else {
			$button_class .= ' btn-outline';
		}

		if ( 'sm' === $atts['size'] ) {
			$button_class .= ' btn--sm';
		}

		if ( ! empty( $atts['class'] ) ) {
			$extra_classes = array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', $atts['class'] ) ) );
			if ( $extra_classes ) {
				$button_class .= ' ' . implode( ' ', $extra_classes );
			}
		}

		ob_start();
		include CTA_PLUGIN_DIR . 'templates/partials/auth-button.php';
		return ob_get_clean();
	}

	/**
	 * Render the site footer shortcode.
	 *
	 * @return string
	 */
	public function render_footer( $atts = array() ) {
		if ( class_exists( 'CTA_Loader' ) && CTA_Loader::is_no_chrome_page() ) {
			return '';
		}

		ob_start();
		include CTA_PLUGIN_DIR . 'templates/footer.php';
		return ob_get_clean();
	}
}
}
<?php
/**
 * Public page provisioning, URL sync, and navigation cleanup.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Pages
 */
if ( ! class_exists( 'CTA_Pages' ) ) {

class CTA_Pages {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_sync_pages' ), 20 );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_legacy_marketing_urls' ), 5 );
		add_filter( 'wp_nav_menu_objects', array( __CLASS__, 'exclude_internal_pages_from_menus' ), 20 );
		add_filter( 'wp_nav_menu_objects', array( __CLASS__, 'rewrite_logged_in_auth_nav_items' ), 25 );
		add_filter( 'wp_list_pages_excludes', array( __CLASS__, 'exclude_internal_pages_from_list' ) );
	}

	/**
	 * Public marketing pages that should exist and be linked from CTAs.
	 *
	 * @return array
	 */
	private static function get_public_page_defs() {
		return array(
			'cta_courses_page_id'     => array(
				'shortcode' => 'cta_course_catalog',
				'title'     => __( 'CE Courses', 'cta-lms' ),
				'slug'      => 'ce-courses',
			),
			'cta_supervision_page_id' => array(
				'shortcode' => 'cta_supervision_booking',
				'title'     => __( 'Clinical Supervision', 'cta-lms' ),
				'slug'      => 'supervision-booking',
			),
			'cta_memberships_page_id' => array(
				'shortcode' => 'cta_membership_pricing',
				'title'     => __( 'Memberships', 'cta-lms' ),
				'slug'      => 'memberships-page',
			),
		);
	}

	/**
	 * Internal app pages that must not appear in public menus.
	 *
	 * Login is intentionally public and must remain visible in the header.
	 *
	 * @return array Option keys.
	 */
	private static function get_internal_page_option_keys() {
		return array(
			'cta_quiz_page_id',
			'cta_course_player_page_id',
			'cta_student_dashboard_page_id',
			'cta_supervision_dashboard_page_id',
			'cta_single_course_page_id',
		);
	}

	/**
	 * Sync / create public pages when options are missing or mis-pointed.
	 */
	public static function maybe_sync_pages() {
		$flag = 'cta_pages_synced_' . CTA_VERSION;

		if ( get_option( $flag ) ) {
			return;
		}

		self::sync_public_pages();
		self::exclude_quiz_pages_from_primary_menu();
		self::sync_primary_nav_menu();
		update_option( $flag, 1 );
	}

	/**
	 * Ensure public page options resolve to the correct shortcode pages.
	 */
	public static function sync_public_pages() {
		$created = false;

		foreach ( self::get_public_page_defs() as $option_key => $def ) {
			$page_id = absint( get_option( $option_key, 0 ) );

			if ( $page_id && self::page_has_shortcode( $page_id, $def['shortcode'] ) && ! self::is_front_page_id( $page_id ) ) {
				continue;
			}

			$found = self::find_dedicated_shortcode_page( $def['shortcode'], $def['slug'] );

			if ( ! $found ) {
				$found   = self::create_page( $def['title'], $def['slug'], '[' . $def['shortcode'] . ']' );
				$created = $created || (bool) $found;
			}

			if ( $found ) {
				update_option( $option_key, $found );
			}
		}

		$dashboard_map = array(
			'cta_supervision_dashboard_page_id' => 'cta_supervision_dashboard',
			'cta_student_dashboard_page_id'     => 'cta_student_dashboard',
			'cta_quiz_page_id'                  => 'cta_quiz',
			'cta_login_page_id'                 => 'cta_login_form',
			'cta_course_player_page_id'         => 'cta_course_player',
			'cta_single_course_page_id'         => 'cta_single_course',
		);

		foreach ( $dashboard_map as $option_key => $shortcode ) {
			$page_id = absint( get_option( $option_key, 0 ) );

			if ( $page_id && self::page_has_shortcode( $page_id, $shortcode ) ) {
				continue;
			}

			if ( function_exists( 'cta_lms_find_page_id_by_shortcode' ) ) {
				$found = absint( cta_lms_find_page_id_by_shortcode( $shortcode ) );
				if ( $found ) {
					update_option( $option_key, $found );
				}
			}
		}

		$supervision_id = absint( get_option( 'cta_supervision_page_id', 0 ) );
		$dashboard_id   = absint( get_option( 'cta_supervision_dashboard_page_id', 0 ) );
		$booking        = function_exists( 'cta_lms_find_page_id_by_shortcode' )
			? absint( cta_lms_find_page_id_by_shortcode( 'cta_supervision_booking' ) )
			: 0;
		$booking_by_slug = get_page_by_path( 'supervision-booking' );
		if ( $booking_by_slug instanceof WP_Post && 'publish' === $booking_by_slug->post_status ) {
			$booking = (int) $booking_by_slug->ID;
		}

		if ( $booking && ( ! $supervision_id || $supervision_id === $dashboard_id || $supervision_id !== $booking || ! self::page_has_shortcode( $supervision_id, 'cta_supervision_booking' ) ) ) {
			update_option( 'cta_supervision_page_id', $booking );
		}

		// Marketing/content pages used in header + footer navigation.
		$nav_fallbacks = array(
			'cta_faq_page_id'      => array( 'faq', 'faqs' ),
			'cta_policies_page_id' => array( 'policies', 'privacy-policy-2', 'privacy-policy', 'terms-of-use' ),
			'cta_about_page_id'    => array( 'about', 'about-us' ),
			'cta_contact_page_id'  => array( 'contact', 'contact-us' ),
		);

		foreach ( $nav_fallbacks as $option_key => $slugs ) {
			$current = absint( get_option( $option_key, 0 ) );
			if ( $current && get_post_status( $current ) ) {
				continue;
			}

			foreach ( $slugs as $slug ) {
				$page = get_page_by_path( $slug );
				if ( $page instanceof WP_Post && 'publish' === $page->post_status ) {
					update_option( $option_key, (int) $page->ID );
					break;
				}
			}
		}

		if ( $created ) {
			flush_rewrite_rules( false );
		}
	}

	/**
	 * Find a non-front-page that hosts a shortcode, preferring the expected slug.
	 *
	 * @param string $shortcode Shortcode tag.
	 * @param string $preferred_slug Preferred page slug.
	 * @return int
	 */
	private static function find_dedicated_shortcode_page( $shortcode, $preferred_slug ) {
		$preferred_slug = sanitize_title( $preferred_slug );
		$by_slug        = get_page_by_path( $preferred_slug );

		if ( $by_slug instanceof WP_Post && self::page_has_shortcode( (int) $by_slug->ID, $shortcode ) && ! self::is_front_page_id( (int) $by_slug->ID ) ) {
			return (int) $by_slug->ID;
		}

		if ( function_exists( 'cta_lms_find_page_id_by_shortcode' ) ) {
			$found = absint( cta_lms_find_page_id_by_shortcode( $shortcode ) );
			if ( $found && ! self::is_front_page_id( $found ) ) {
				return $found;
			}
		}

		return 0;
	}

	/**
	 * Whether a page ID is the site front page.
	 *
	 * @param int $page_id Page ID.
	 * @return bool
	 */
	private static function is_front_page_id( $page_id ) {
		$page_id = absint( $page_id );

		if ( ! $page_id ) {
			return false;
		}

		$front_id = absint( get_option( 'page_on_front', 0 ) );

		return $front_id && $front_id === $page_id;
	}

	/**
	 * Redirect legacy / broken marketing URLs to the correct public pages.
	 */
	public static function redirect_legacy_marketing_urls() {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$request_path = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path         = trim( (string) wp_parse_url( $request_path, PHP_URL_PATH ), '/' );

		if ( '' === $path ) {
			return;
		}

		$courses_url = self::get_option_permalink( 'cta_courses_page_id' );
		$booking_url = self::get_option_permalink( 'cta_supervision_page_id' );

		if ( in_array( $path, array( 'ce-courses', 'courses', 'course-catalog', 'ce-course-catalog' ), true ) && $courses_url ) {
			$target_path = trim( (string) wp_parse_url( $courses_url, PHP_URL_PATH ), '/' );
			if ( $target_path !== $path ) {
				wp_safe_redirect( $courses_url, 301 );
				exit;
			}
		}

		if ( in_array( $path, array( 'supervision', 'clinical-supervision' ), true ) && $booking_url ) {
			wp_safe_redirect( $booking_url, 301 );
			exit;
		}

		$memberships_url = self::get_option_permalink( 'cta_memberships_page_id' );
		if ( 'memberships' === $path && $memberships_url ) {
			$target_path = trim( (string) wp_parse_url( $memberships_url, PHP_URL_PATH ), '/' );
			if ( $target_path && $target_path !== $path ) {
				wp_safe_redirect( $memberships_url, 301 );
				exit;
			}
		}
	}

	/**
	 * Ensure the theme / Elementor header menu includes public CTA pages.
	 *
	 * The site header uses the WordPress menu assigned to location "menu-1"
	 * (Hello Elementor). This keeps CE Courses, Supervision, Login, FAQ, etc.
	 * visible without requiring manual menu edits after plugin updates.
	 *
	 * @return void
	 */
	public static function sync_primary_nav_menu() {
		$menu_id = 0;
		$locations = get_nav_menu_locations();

		if ( ! empty( $locations['menu-1'] ) ) {
			$menu_id = absint( $locations['menu-1'] );
		} elseif ( ! empty( $locations['primary'] ) ) {
			$menu_id = absint( $locations['primary'] );
		}

		if ( ! $menu_id ) {
			$menus = wp_get_nav_menus();
			if ( ! empty( $menus[0] ) ) {
				$menu_id = (int) $menus[0]->term_id;
			}
		}

		if ( ! $menu_id ) {
			$menu_id = wp_create_nav_menu( __( 'CTA Main Menu', 'cta-lms' ) );
			if ( is_wp_error( $menu_id ) ) {
				return;
			}
			$menu_id = (int) $menu_id;
			$locations = is_array( $locations ) ? $locations : array();
			$locations['menu-1'] = $menu_id;
			set_theme_mod( 'nav_menu_locations', $locations );
		}

		$desired = array(
			array(
				'title'  => __( 'CE Courses', 'cta-lms' ),
				'option' => 'cta_courses_page_id',
			),
			array(
				'title'  => __( 'Supervision', 'cta-lms' ),
				'option' => 'cta_supervision_page_id',
			),
			array(
				'title'  => __( 'Memberships', 'cta-lms' ),
				'option' => 'cta_memberships_page_id',
			),
			array(
				'title'  => __( 'About', 'cta-lms' ),
				'option' => 'cta_about_page_id',
			),
			array(
				'title'  => __( 'FAQ', 'cta-lms' ),
				'option' => 'cta_faq_page_id',
			),
			array(
				'title'  => __( 'Contact', 'cta-lms' ),
				'option' => 'cta_contact_page_id',
			),
			array(
				'title'  => __( 'Policies', 'cta-lms' ),
				'option' => 'cta_policies_page_id',
			),
			array(
				'title'  => __( 'Login', 'cta-lms' ),
				'option' => 'cta_login_page_id',
			),
		);

		$existing_items = wp_get_nav_menu_items( $menu_id );
		$existing_ids   = array();
		$max_position   = 0;

		foreach ( (array) $existing_items as $item ) {
			$object_id = absint( $item->object_id );
			if ( $object_id ) {
				$existing_ids[ $object_id ] = (int) $item->ID;
			}
			$max_position = max( $max_position, (int) $item->menu_order );
		}

		// Remove broken custom links (e.g. Legal -> #) and internal app pages.
		$internal_ids = self::get_internal_page_ids();
		foreach ( (array) $existing_items as $item ) {
			$object_id = absint( $item->object_id );
			$url       = (string) $item->url;
			$title     = strtolower( trim( wp_strip_all_tags( (string) $item->title ) ) );

			$is_broken_custom = ( 'custom' === $item->type && ( '' === $url || '#' === $url ) );
			$is_internal      = $object_id && in_array( $object_id, $internal_ids, true );
			$is_quiz_title    = in_array( $title, array( 'course quiz', 'course-quiz', 'quiz' ), true );

			if ( $is_broken_custom || $is_internal || $is_quiz_title ) {
				wp_delete_post( (int) $item->ID, true );
				if ( $object_id && isset( $existing_ids[ $object_id ] ) ) {
					unset( $existing_ids[ $object_id ] );
				}
			}
		}

		foreach ( $desired as $entry ) {
			$page_id = function_exists( 'cta_lms_resolve_linked_page_id' )
				? cta_lms_resolve_linked_page_id( $entry['option'] )
				: absint( get_option( $entry['option'], 0 ) );

			if ( ! $page_id || ! get_post_status( $page_id ) ) {
				continue;
			}

			if ( isset( $existing_ids[ $page_id ] ) ) {
				continue;
			}

			++$max_position;

			wp_update_nav_menu_item(
				$menu_id,
				0,
				array(
					'menu-item-title'     => $entry['title'],
					'menu-item-object'    => 'page',
					'menu-item-object-id' => $page_id,
					'menu-item-type'      => 'post_type',
					'menu-item-status'    => 'publish',
					'menu-item-position'  => $max_position,
				)
			);
		}
	}

	/**
	 * When logged in, turn Login / Learner Login menu items into My Dashboard
	 * pointing at the CE student dashboard (never the supervision portal).
	 *
	 * @param array $items Menu items.
	 * @return array
	 */
	public static function rewrite_logged_in_auth_nav_items( $items ) {
		if ( is_admin() || ! is_user_logged_in() || empty( $items ) ) {
			return $items;
		}

		$dashboard_url = class_exists( 'CTA_Associate_Access' )
			? CTA_Associate_Access::get_general_dashboard_url( get_current_user_id() )
			: ( function_exists( 'cta_lms_get_linked_page_url' )
				? cta_lms_get_linked_page_url( 'cta_student_dashboard_page_id' )
				: '' );

		if ( ! $dashboard_url ) {
			return $items;
		}

		$login_page_id = absint( get_option( 'cta_login_page_id', 0 ) );
		$login_url     = $login_page_id ? (string) get_permalink( $login_page_id ) : '';
		$dash_page_id  = absint( get_option( 'cta_student_dashboard_page_id', 0 ) );
		$dashboard_label = __( 'My Dashboard', 'cta-lms' );

		foreach ( $items as $item ) {
			$title = strtolower( trim( wp_strip_all_tags( (string) $item->title ) ) );
			$title = preg_replace( '/[→➞»]+$/u', '', $title );
			$title = trim( (string) $title );
			$url   = isset( $item->url ) ? (string) $item->url : '';
			$object_id = isset( $item->object_id ) ? absint( $item->object_id ) : 0;

			$is_login_title = in_array(
				$title,
				array( 'login', 'log in', 'learner login', 'sign in', 'learner log in' ),
				true
			);
			$is_dashboard_title = in_array(
				$title,
				array( 'my dashboard', 'learner dashboard', 'my account', 'dashboard' ),
				true
			);
			$points_to_login = (
				( $login_page_id && $object_id === $login_page_id )
				|| ( $login_url && untrailingslashit( $url ) === untrailingslashit( $login_url ) )
			);

			// Rewrite guest login CTAs, and "Learner Dashboard" links that still point at /login/.
			if ( ! $is_login_title && ! ( $is_dashboard_title && $points_to_login ) ) {
				continue;
			}

			$item->title     = $dashboard_label;
			$item->url       = $dashboard_url;
			$item->object    = 'custom';
			$item->type      = 'custom';
			$item->object_id = $dash_page_id ? $dash_page_id : 0;
			$item->classes   = array_values(
				array_unique(
					array_merge(
						(array) $item->classes,
						array( 'cta-nav-my-dashboard' )
					)
				)
			);
		}

		return $items;
	}

	/**
	 * Remove quiz / dashboard / player pages from front-end nav menus.
	 *
	 * @param array $items Menu items.
	 * @return array
	 */
	public static function exclude_internal_pages_from_menus( $items ) {
		if ( is_admin() ) {
			return $items;
		}

		$exclude_ids = self::get_internal_page_ids();
		$filtered    = array();

		foreach ( (array) $items as $item ) {
			$object_id = isset( $item->object_id ) ? absint( $item->object_id ) : 0;
			$title     = isset( $item->title ) ? strtolower( trim( wp_strip_all_tags( (string) $item->title ) ) ) : '';
			$url       = isset( $item->url ) ? strtolower( (string) $item->url ) : '';

			if ( $object_id && in_array( $object_id, $exclude_ids, true ) ) {
				continue;
			}

			if ( in_array( $title, array( 'course quiz', 'course-quiz', 'quiz' ), true ) ) {
				continue;
			}

			if ( false !== strpos( $url, '/course-quiz' ) || false !== strpos( $url, '/quiz/' ) ) {
				continue;
			}

			$filtered[] = $item;
		}

		return $filtered;
	}

	/**
	 * Exclude internal pages from wp_list_pages output.
	 *
	 * @param array $exclude Existing excludes.
	 * @return array
	 */
	public static function exclude_internal_pages_from_list( $exclude ) {
		$exclude = array_map( 'absint', (array) $exclude );
		return array_values( array_unique( array_merge( $exclude, self::get_internal_page_ids() ) ) );
	}

	/**
	 * One-time removal of quiz pages from nav menus.
	 */
	private static function exclude_quiz_pages_from_primary_menu() {
		$quiz_ids = array();

		$id = absint( get_option( 'cta_quiz_page_id', 0 ) );
		if ( $id ) {
			$quiz_ids[] = $id;
		}

		if ( function_exists( 'cta_lms_find_page_id_by_shortcode' ) ) {
			$found = absint( cta_lms_find_page_id_by_shortcode( 'cta_quiz' ) );
			if ( $found ) {
				$quiz_ids[] = $found;
			}
		}

		$named = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'fields'         => 'ids',
				's'              => 'Course quiz',
			)
		);

		foreach ( (array) $named as $named_id ) {
			$quiz_ids[] = absint( $named_id );
		}

		$quiz_ids = array_values( array_unique( array_filter( $quiz_ids ) ) );

		if ( empty( $quiz_ids ) ) {
			return;
		}

		$menus = wp_get_nav_menus();

		foreach ( $menus as $menu ) {
			$items = wp_get_nav_menu_items( $menu->term_id );

			if ( empty( $items ) ) {
				continue;
			}

			foreach ( $items as $item ) {
				$object_id = absint( $item->object_id );
				$title     = strtolower( trim( wp_strip_all_tags( (string) $item->title ) ) );

				if ( in_array( $object_id, $quiz_ids, true ) || in_array( $title, array( 'course quiz', 'course-quiz', 'quiz' ), true ) ) {
					wp_delete_post( (int) $item->ID, true );
				}
			}
		}
	}

	/**
	 * Internal page IDs from stored options only (no full-site page scans).
	 *
	 * Scanning every page on each menu render previously timed out / crashed hosts.
	 *
	 * @return int[]
	 */
	private static function get_internal_page_ids() {
		static $ids = null;

		if ( null !== $ids ) {
			return $ids;
		}

		$ids = array();

		foreach ( self::get_internal_page_option_keys() as $option_key ) {
			$id = absint( get_option( $option_key, 0 ) );
			if ( $id ) {
				$ids[] = $id;
			}
		}

		$ids = array_values( array_unique( array_filter( $ids ) ) );
		return $ids;
	}

	/**
	 * Whether a page contains a shortcode.
	 *
	 * @param int    $page_id   Page ID.
	 * @param string $shortcode Shortcode tag.
	 * @return bool
	 */
	private static function page_has_shortcode( $page_id, $shortcode ) {
		$post = get_post( $page_id );

		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		if ( has_shortcode( $post->post_content, $shortcode ) ) {
			return true;
		}

		if ( metadata_exists( 'post', $page_id, '_elementor_data' ) ) {
			$data = get_post_meta( $page_id, '_elementor_data', true );
			if ( is_string( $data ) && false !== strpos( $data, '[' . $shortcode ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Create a published page if the slug is free; otherwise reuse the slug page.
	 *
	 * @param string $title   Page title.
	 * @param string $slug    Desired slug.
	 * @param string $content Page content.
	 * @return int
	 */
	private static function create_page( $title, $slug, $content ) {
		$existing = get_page_by_path( $slug );

		if ( $existing instanceof WP_Post ) {
			if ( 'publish' !== $existing->post_status ) {
				wp_update_post(
					array(
						'ID'          => $existing->ID,
						'post_status' => 'publish',
					)
				);
			}

			if ( false === strpos( (string) $existing->post_content, $content ) ) {
				wp_update_post(
					array(
						'ID'           => $existing->ID,
						'post_content' => $content,
					)
				);
			}

			return (int) $existing->ID;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => $content,
			),
			true
		);

		return is_wp_error( $page_id ) ? 0 : (int) $page_id;
	}

	/**
	 * Permalink for a page option.
	 *
	 * @param string $option_key Option key.
	 * @return string
	 */
	private static function get_option_permalink( $option_key ) {
		$page_id = absint( get_option( $option_key, 0 ) );

		if ( ! $page_id ) {
			return '';
		}

		$url = get_permalink( $page_id );

		return $url ? $url : '';
	}
}
}

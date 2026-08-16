<?php
/**
 * Canonical slug landing pages and legacy URL redirects for exam prep programs.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Course_Routes
 */
if ( ! class_exists( 'CTA_Course_Routes' ) ) {

class CTA_Course_Routes {

	/**
	 * Option prefix: cta_course_landing_page_{course_id} => WP page ID.
	 */
	const LANDING_OPTION_PREFIX = 'cta_course_landing_page_';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'redirect_legacy_course_urls' ), 4 );
	}

	/**
	 * Programs that receive a top-level slug landing page + legacy redirects.
	 *
	 * @return array<int,array{slug:string,legacy_slugs:string[],title:string}>
	 */
	public static function get_program_route_defs() {
		$defs = array();

		if ( class_exists( 'CTA_Lcsw_Aswb_Sync' ) ) {
			$defs[] = array(
				'slug'          => CTA_Lcsw_Aswb_Sync::SLUG,
				'legacy_slugs'  => array( CTA_Lcsw_Aswb_Sync::LEGACY_SLUG ),
				'title'         => CTA_Lcsw_Aswb_Sync::PUBLIC_TITLE,
				'find_callback' => array( 'CTA_Lcsw_Aswb_Sync', 'find_course' ),
			);
		}

		/**
		 * Filter additional exam prep program route definitions.
		 *
		 * @param array $defs Route definitions.
		 */
		return apply_filters( 'cta_course_route_defs', $defs );
	}

	/**
	 * Ensure slug landing pages exist and DB slugs are canonical.
	 *
	 * @param bool $force Re-sync even when version flag is set.
	 * @return array<string,mixed>
	 */
	public static function sync_landing_pages( $force = false ) {
		$flag = 'cta_course_routes_synced_' . CTA_VERSION;

		if ( ! $force && get_option( $flag ) ) {
			return array( 'synced' => array(), 'skipped' => true );
		}

		$synced = array();

		foreach ( self::get_program_route_defs() as $def ) {
			$slug = sanitize_title( (string) ( $def['slug'] ?? '' ) );
			if ( '' === $slug ) {
				continue;
			}

			$course = null;
			if ( ! empty( $def['find_callback'] ) && is_callable( $def['find_callback'] ) ) {
				$course = call_user_func( $def['find_callback'] );
			}

			if ( ! $course ) {
				continue;
			}

			$course_id = (int) $course->id;
			self::ensure_course_slug( $course_id, $slug );

			$page_title = (string) ( $def['title'] ?? $course->title );
			$page_id    = self::ensure_landing_page( $course_id, $slug, $page_title );

			if ( $page_id ) {
				$synced[ $slug ] = array(
					'course_id' => $course_id,
					'page_id'   => $page_id,
				);
			}
		}

		update_option( $flag, 1 );

		return array(
			'synced'  => $synced,
			'skipped' => false,
		);
	}

	/**
	 * Canonical public URL for a course when a landing page exists.
	 *
	 * @param int $course_id Course ID.
	 * @return string
	 */
	public static function get_canonical_url( $course_id ) {
		$course_id = absint( $course_id );
		if ( ! $course_id ) {
			return '';
		}

		$page_id = self::get_landing_page_id( $course_id );
		if ( ! $page_id ) {
			return '';
		}

		$url = get_permalink( $page_id );
		return $url ? (string) $url : '';
	}

	/**
	 * Resolve course ID from a landing page post.
	 *
	 * @param int $page_id WordPress page ID.
	 * @return int
	 */
	public static function get_course_id_for_landing_page( $page_id ) {
		$page_id = absint( $page_id );
		if ( ! $page_id ) {
			return 0;
		}

		foreach ( self::get_program_route_defs() as $def ) {
			$slug = sanitize_title( (string) ( $def['slug'] ?? '' ) );
			if ( '' === $slug ) {
				continue;
			}

			$course = null;
			if ( ! empty( $def['find_callback'] ) && is_callable( $def['find_callback'] ) ) {
				$course = call_user_func( $def['find_callback'] );
			}

			if ( ! $course ) {
				continue;
			}

			$course_id = (int) $course->id;
			if ( self::get_landing_page_id( $course_id ) === $page_id ) {
				return $course_id;
			}
		}

		return 0;
	}

	/**
	 * Parse course_id from [cta_single_course] shortcode attributes in post content.
	 *
	 * @param string $content Post content.
	 * @return int
	 */
	public static function parse_shortcode_course_id( $content ) {
		if ( ! preg_match( '/\[cta_single_course[^\]]*course_id=[\'"]?(\d+)/i', (string) $content, $matches ) ) {
			return 0;
		}

		return absint( $matches[1] );
	}

	/**
	 * 301 redirects for legacy slug paths and generic ?course_id= links.
	 */
	public static function redirect_legacy_course_urls() {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path        = trim( (string) wp_parse_url( $request_uri, PHP_URL_PATH ), '/' );

		if ( '' === $path ) {
			return;
		}

		foreach ( self::get_program_route_defs() as $def ) {
			$canonical_slug = sanitize_title( (string) ( $def['slug'] ?? '' ) );
			if ( '' === $canonical_slug ) {
				continue;
			}

			$legacy_slugs = array_map( 'sanitize_title', (array) ( $def['legacy_slugs'] ?? array() ) );
			if ( in_array( $path, $legacy_slugs, true ) ) {
				wp_safe_redirect( home_url( '/' . $canonical_slug . '/' ), 301 );
				exit;
			}
		}

		$course_id = 0;

		if ( self::is_generic_single_course_request( $path ) && isset( $_GET['course_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$course_id = absint( wp_unslash( $_GET['course_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( $course_id ) {
			$canonical = self::get_canonical_url( $course_id );
			if ( $canonical && ! self::paths_match( $path, $canonical ) ) {
				$target = self::merge_query_onto_url(
					$canonical,
					array( 'course_id' => null )
				);
				wp_safe_redirect( $target, 301 );
				exit;
			}

			if ( $canonical && self::paths_match( $path, $canonical ) && isset( $_GET['course_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$target = self::merge_query_onto_url( $canonical, array( 'course_id' => null ) );
				wp_safe_redirect( $target, 301 );
				exit;
			}
		}
	}

	/**
	 * Whether the current request targets the generic [cta_single_course] page.
	 *
	 * @param string $path Request path without leading/trailing slashes.
	 * @return bool
	 */
	private static function is_generic_single_course_request( $path ) {
		$page_id = absint( get_option( 'cta_single_course_page_id', 0 ) );
		if ( ! $page_id ) {
			return false;
		}

		$permalink = get_permalink( $page_id );
		if ( ! $permalink ) {
			return false;
		}

		$single_path = trim( (string) wp_parse_url( $permalink, PHP_URL_PATH ), '/' );

		return $single_path === trim( (string) $path, '/' );
	}

	/**
	 * Landing page ID for a course.
	 *
	 * @param int $course_id Course ID.
	 * @return int
	 */
	public static function get_landing_page_id( $course_id ) {
		return absint( get_option( self::LANDING_OPTION_PREFIX . absint( $course_id ), 0 ) );
	}

	/**
	 * Create or update the landing page for a program slug.
	 *
	 * @param int    $course_id Course ID.
	 * @param string $slug      Canonical slug.
	 * @param string $title     Page title.
	 * @return int
	 */
	private static function ensure_landing_page( $course_id, $slug, $title ) {
		$course_id = absint( $course_id );
		$slug      = sanitize_title( $slug );
		$title     = sanitize_text_field( $title );

		if ( ! $course_id || '' === $slug ) {
			return 0;
		}

		$content = '[cta_single_course course_id="' . $course_id . '"]';
		$page_id = 0;

		$stored_id = self::get_landing_page_id( $course_id );
		if ( $stored_id && get_post_status( $stored_id ) ) {
			$page_id = $stored_id;
		}

		$by_slug = get_page_by_path( $slug );
		if ( $by_slug instanceof WP_Post && 'trash' !== $by_slug->post_status ) {
			$page_id = (int) $by_slug->ID;
		}

		if ( $page_id ) {
			wp_update_post(
				array(
					'ID'           => $page_id,
					'post_title'   => $title,
					'post_name'    => $slug,
					'post_content' => $content,
					'post_status'  => 'publish',
					'post_type'    => 'page',
				)
			);
		} else {
			$page_id = wp_insert_post(
				array(
					'post_title'   => $title,
					'post_name'    => $slug,
					'post_content' => $content,
					'post_status'  => 'publish',
					'post_type'    => 'page',
				)
			);
		}

		$page_id = absint( $page_id );
		if ( $page_id ) {
			update_option( self::LANDING_OPTION_PREFIX . $course_id, $page_id, false );
		}

		return $page_id;
	}

	/**
	 * Ensure the course row uses the canonical slug.
	 *
	 * @param int    $course_id Course ID.
	 * @param string $slug      Canonical slug.
	 */
	private static function ensure_course_slug( $course_id, $slug ) {
		if ( ! class_exists( 'CTA_Database' ) ) {
			return;
		}

		$course = CTA_Database::get_course( absint( $course_id ) );
		if ( ! $course ) {
			return;
		}

		if ( sanitize_title( (string) ( $course->slug ?? '' ) ) === sanitize_title( $slug ) ) {
			return;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->prefix . 'cta_courses',
			array( 'slug' => sanitize_title( $slug ) ),
			array( 'id' => absint( $course_id ) ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Whether a request path matches a URL path.
	 *
	 * @param string $request_path Request path without leading/trailing slashes.
	 * @param string $url          Full URL.
	 * @return bool
	 */
	private static function paths_match( $request_path, $url ) {
		$target_path = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
		return $target_path === trim( (string) $request_path, '/' );
	}

	/**
	 * Merge current query args onto a base URL, optionally removing keys.
	 *
	 * @param string               $base_url Base URL.
	 * @param array<string,mixed>  $remove   Keys to strip (null values).
	 * @return string
	 */
	private static function merge_query_onto_url( $base_url, array $remove = array() ) {
		$args = array();
		foreach ( $_GET as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( array_key_exists( $key, $remove ) ) {
				continue;
			}
			$args[ sanitize_key( (string) $key ) ] = sanitize_text_field( wp_unslash( (string) $value ) );
		}

		if ( empty( $args ) ) {
			return (string) $base_url;
		}

		return add_query_arg( $args, $base_url );
	}
}

}

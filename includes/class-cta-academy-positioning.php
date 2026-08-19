<?php
/**
 * Sitewide academy positioning copy (CE, supervision, exam prep, professional resources).
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Academy_Positioning
 */
if ( ! class_exists( 'CTA_Academy_Positioning' ) ) {

class CTA_Academy_Positioning {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'output_site_meta_tags' ), 1 );
	}

	/**
	 * Approved full-academy positioning statement.
	 *
	 * @return string
	 */
	public static function full_statement() {
		return __(
			'CTA supports California mental health professionals with continuing education, clinical supervision, exam preparation, and professional resources designed to strengthen clinical judgment, competence, compliance, and client care.',
			'cta-lms'
		);
	}

	/**
	 * Short footer / about blurb (~115 chars).
	 *
	 * @return string
	 */
	public static function footer_tagline() {
		return __(
			'Continuing education, clinical supervision, exam preparation, and professional resources for California mental health professionals.',
			'cta-lms'
		);
	}

	/**
	 * Condensed SEO meta description (~155 chars) — includes exam preparation explicitly.
	 *
	 * @return string
	 */
	public static function meta_description() {
		return __(
			'California mental health CE, clinical supervision, exam preparation, and professional resources from CTA — strengthening clinical judgment, competence, compliance, and client care.',
			'cta-lms'
		);
	}

	/**
	 * Top announcement bar tagline (short, no period).
	 *
	 * @return string
	 */
	public static function top_bar_tagline() {
		return __(
			'Continuing education, clinical supervision, exam preparation, and professional resources',
			'cta-lms'
		);
	}

	/**
	 * Homepage pathway section intro (shorter than full statement).
	 *
	 * @return string
	 */
	public static function pathway_intro() {
		return __(
			'CTA supports California mental health professionals with continuing education, clinical supervision, exam preparation, and professional resources.',
			'cta-lms'
		);
	}

	/**
	 * Footer pre-CTA band copy.
	 *
	 * @return string
	 */
	public static function footer_cta_intro() {
		return __(
			'Explore CE courses, exam preparation programs, supervision services, and professional resources designed for California clinicians.',
			'cta-lms'
		);
	}

	/**
	 * Legacy narrow copy => approved replacement map.
	 *
	 * @return array<string,string>
	 */
	public static function get_legacy_replacements() {
		return array(
			'California-focused continuing education and clinical supervision' => self::top_bar_tagline(),
			"California's trusted platform for BBS-compliant continuing education and clinical supervision — built for working mental health professionals." => self::footer_tagline(),
			'Practical, accessible continuing education and clinical supervision for California mental health professionals.' => self::footer_tagline(),
			'CTA serves licensed clinicians completing continuing education and registered associates seeking structured clinical supervision.' => self::pathway_intro(),
			'Explore California-focused CE courses or learn more about CTA clinical supervision services.' => self::footer_cta_intro(),
			'California-focused continuing education and clinical supervision for mental health professionals.' => self::footer_tagline(),
		);
	}

	/**
	 * Output sitewide meta description when no course-specific SEO is present.
	 */
	public static function output_site_meta_tags() {
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}

		if ( self::should_skip_sitewide_meta() ) {
			return;
		}

		$description = self::meta_description();
		if ( '' === $description ) {
			return;
		}

		echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $description ) . '" />' . "\n";
	}

	/**
	 * Whether course/catalog pages already define their own meta description.
	 *
	 * @return bool
	 */
	private static function should_skip_sitewide_meta() {
		if ( isset( $_GET['course_id'] ) && absint( wp_unslash( $_GET['course_id'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}

		return false;
	}

	/**
	 * Replace legacy narrow positioning strings in WordPress + Elementor content.
	 *
	 * @param bool $force Run even if already synced for current version.
	 * @return array{posts_updated:int,fields_updated:int}
	 */
	public static function sync_sitewide_copy( $force = false ) {
		$flag = 'cta_academy_positioning_synced_' . CTA_VERSION;

		if ( ! $force && get_option( $flag ) ) {
			return array(
				'posts_updated'  => 0,
				'fields_updated' => 0,
			);
		}

		$replacements = self::get_legacy_replacements();
		$posts_updated  = 0;
		$fields_updated = 0;

		global $wpdb;

		$like_clauses = array();
		foreach ( array_keys( $replacements ) as $needle ) {
			$like_clauses[] = $wpdb->prepare(
				'(p.post_content LIKE %s OR pm.meta_value LIKE %s OR p.post_excerpt LIKE %s)',
				'%' . $wpdb->esc_like( $needle ) . '%',
				'%' . $wpdb->esc_like( $needle ) . '%',
				'%' . $wpdb->esc_like( $needle ) . '%'
			);
		}

		if ( empty( $like_clauses ) ) {
			update_option( $flag, 1 );
			return array(
				'posts_updated'  => 0,
				'fields_updated' => 0,
			);
		}

		$sql = "
			SELECT DISTINCT p.ID
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
				AND pm.meta_key IN ('_elementor_data', '_elementor_css')
			WHERE p.post_status IN ('publish', 'draft', 'private')
			AND p.post_type IN ('page', 'post', 'elementor_library')
			AND (" . implode( ' OR ', $like_clauses ) . ')
		';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$post_ids = $wpdb->get_col( $sql );
		$post_ids = array_values( array_unique( array_map( 'absint', (array) $post_ids ) ) );

		foreach ( $post_ids as $post_id ) {
			if ( ! $post_id ) {
				continue;
			}

			$post = get_post( $post_id );
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$updated_fields = 0;

			$new_content = self::apply_replacements( (string) $post->post_content, $replacements );
			if ( $new_content !== (string) $post->post_content ) {
				wp_update_post(
					array(
						'ID'           => $post_id,
						'post_content' => $new_content,
					)
				);
				++$updated_fields;
			}

			$new_excerpt = self::apply_replacements( (string) $post->post_excerpt, $replacements );
			if ( $new_excerpt !== (string) $post->post_excerpt ) {
				wp_update_post(
					array(
						'ID'           => $post_id,
						'post_excerpt' => $new_excerpt,
					)
				);
				++$updated_fields;
			}

			$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
			if ( is_string( $elementor_data ) && '' !== $elementor_data ) {
				$new_elementor = self::apply_replacements( $elementor_data, $replacements );
				if ( $new_elementor !== $elementor_data ) {
					update_post_meta( $post_id, '_elementor_data', wp_slash( $new_elementor ) );
					delete_post_meta( $post_id, '_elementor_css' );
					++$updated_fields;
				}
			}

			if ( $updated_fields > 0 ) {
				++$posts_updated;
				$fields_updated += $updated_fields;

				if ( class_exists( '\Elementor\Plugin' ) ) {
					\Elementor\Plugin::$instance->files_manager->clear_cache();
				}
			}
		}

		$tagline = get_option( 'blogdescription', '' );
		if ( is_string( $tagline ) && '' !== $tagline ) {
			$new_tagline = self::apply_replacements( $tagline, $replacements );
			if ( $new_tagline !== $tagline ) {
				update_option( 'blogdescription', $new_tagline );
				++$fields_updated;
			}
		}

		update_option( $flag, 1 );

		return array(
			'posts_updated'  => $posts_updated,
			'fields_updated' => $fields_updated,
		);
	}

	/**
	 * Apply ordered string replacements.
	 *
	 * @param string               $content      Source text.
	 * @param array<string,string> $replacements Replacement map.
	 * @return string
	 */
	private static function apply_replacements( $content, array $replacements ) {
		if ( '' === $content ) {
			return $content;
		}

		foreach ( $replacements as $old => $new ) {
			if ( false !== strpos( $content, $old ) ) {
				$content = str_replace( $old, $new, $content );
			}
		}

		return $content;
	}
}
}

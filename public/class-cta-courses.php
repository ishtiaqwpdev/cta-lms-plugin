<?php
/**
 * Course catalog and AJAX filtering.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Courses
 */
if ( ! class_exists( 'CTA_Courses' ) ) {

class CTA_Courses {

	/**
	 * Register shortcode and AJAX handlers.
	 */
	public function __construct() {
		add_shortcode( 'cta_course_catalog', array( $this, 'render_catalog' ) );
		add_shortcode( 'cta_single_course', array( $this, 'render_single_course' ) );

		add_action( 'wp_ajax_cta_filter_courses', array( $this, 'ajax_filter_courses' ) );
		add_action( 'wp_ajax_nopriv_cta_filter_courses', array( $this, 'ajax_filter_courses' ) );

		add_filter( 'body_class', array( $this, 'add_body_class' ) );
		add_filter( 'document_title_parts', array( $this, 'filter_document_title_parts' ) );
		add_action( 'wp_head', array( $this, 'output_course_meta_tags' ), 5 );
	}

	/**
	 * Render the course catalog shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_catalog( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit'        => -1,
				'category'     => '',
				'columns'      => 3,
				'product_type' => '',
			),
			$atts,
			'cta_course_catalog'
		);

		$limit           = intval( $atts['limit'] );
		$columns         = max( 1, min( 4, absint( $atts['columns'] ) ) );
		$active_category = sanitize_text_field( $atts['category'] );
		$search          = '';

		// Deep-link / shareable category archive: ?category=Alcoholism+%26+Other...
		if ( '' === $active_category && isset( $_GET['category'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$active_category = sanitize_text_field( wp_unslash( $_GET['category'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		// Deep-link exam prep catalog: ?product_type=exam_prep
		if ( '' === sanitize_text_field( $atts['product_type'] ) && isset( $_GET['product_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$atts['product_type'] = sanitize_text_field( wp_unslash( $_GET['product_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$all_published = $this->get_courses(
			array(
				'limit'    => $limit,
				'category' => $active_category,
				'status'   => 'published',
			)
		);

		$ce_courses   = array();
		$exam_courses = array();

		foreach ( $all_published as $course ) {
			if ( class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course ) ) {
				$exam_courses[] = $course;
			} else {
				$ce_courses[] = $course;
			}
		}

		// Default catalog shows CE; optional product_type="exam_prep" for exam-only shortcode.
		$product_type_filter = sanitize_text_field( $atts['product_type'] );
		if ( 'exam_prep' === $product_type_filter ) {
			$courses    = $exam_courses;
			$ce_courses = array();
		} elseif ( 'ce' === $product_type_filter ) {
			$courses      = $ce_courses;
			$exam_courses = array();
		} else {
			$courses = $ce_courses;
		}

		$categories = $this->get_categories( 'ce' === $product_type_filter || '' === $product_type_filter ? 'ce' : $product_type_filter );

		$alcoholism_course_url = $this->get_alcoholism_course_url();

		ob_start();
		include CTA_PLUGIN_DIR . 'templates/courses.php';
		return ob_get_clean();
	}

	/**
	 * Permalink for the published Alcoholism CE course, if one exists.
	 *
	 * Used so the Alcoholism category tab can deep-link to the course detail
	 * when available; otherwise the category archive query stays ready.
	 *
	 * @return string
	 */
	private function get_alcoholism_course_url() {
		global $wpdb;

		$category = class_exists( 'CTA_Admin' )
			? CTA_Admin::get_alcoholism_category_name()
			: 'Alcoholism & Other Chemical Substance Dependency';

		$table = $wpdb->prefix . 'cta_courses';

		// Prefer an exact category match after migration.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$course_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table}
				WHERE status = 'published'
				AND (product_type = %s OR product_type = '' OR product_type IS NULL)
				AND category = %s
				ORDER BY id ASC
				LIMIT 1",
				'ce',
				$category
			)
		);

		// Fallback: title match before the course category has been remounted.
		if ( ! $course_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$course_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table}
					WHERE status = 'published'
					AND (product_type = %s OR product_type = '' OR product_type IS NULL)
					AND title LIKE %s
					ORDER BY id ASC
					LIMIT 1",
					'ce',
					'%Alcoholism & Other Chemical Substance Dependency%'
				)
			);
		}

		if ( ! $course_id || ! function_exists( 'cta_lms_get_single_course_url' ) ) {
			return '';
		}

		return (string) cta_lms_get_single_course_url( $course_id );
	}

	/**
	 * Render single course detail shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_single_course( $atts ) {
		$course_id = absint( wp_unslash( $_GET['course_id'] ?? 0 ) );
		$payment_success = isset( $_GET['payment'] ) && 'success' === sanitize_text_field( wp_unslash( $_GET['payment'] ) );

		if ( ! $course_id ) {
			return '<div class="cta-empty-state"><p>' . esc_html__( 'No course specified.', 'cta-lms' ) . '</p></div>';
		}

		$course = CTA_Database::get_course( $course_id );

		if ( ! $course || 'published' !== $course->status ) {
			return '<div class="cta-empty-state"><p>' . esc_html__( 'Course not found.', 'cta-lms' ) . '</p></div>';
		}

		$modules     = CTA_Database::get_course_modules( $course_id );
		$objectives  = array();
		$is_enrolled = false;
		$player_url  = '';

		if ( ! empty( $course->learning_objectives ) ) {
			$decoded = json_decode( (string) $course->learning_objectives, true );
			if ( is_array( $decoded ) ) {
				$objectives = $decoded;
			}
		}

		if ( is_user_logged_in() ) {
			global $wpdb;
			$user_id     = get_current_user_id();

			if ( $payment_success && function_exists( 'cta_get_stripe' ) ) {
				$session_id = sanitize_text_field( wp_unslash( $_GET['session_id'] ?? '' ) );
				$stripe     = cta_get_stripe();

				if ( $session_id && $stripe ) {
					$stripe->finalize_checkout_session( $session_id, $user_id );
				}
			}

			$is_enrolled = (bool) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}cta_enrollments
					WHERE user_id = %d AND course_id = %d AND status IN ('active','completed')",
					$user_id,
					$course_id
				)
			);

			if ( $is_enrolled && class_exists( 'CTA_CE_Access' ) && CTA_CE_Access::is_ce_course( $course ) ) {
				$is_enrolled = CTA_CE_Access::has_active_access( $user_id, $course_id );
			} elseif ( $is_enrolled && class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course ) ) {
				$is_enrolled = CTA_Exam_Access::has_active_access( $user_id, $course_id );
			}
		}

		$player_page_id = absint( get_option( 'cta_course_player_page_id', 0 ) );
		if ( $player_page_id ) {
			$permalink = get_permalink( $player_page_id );
			if ( $permalink ) {
				$player_url = add_query_arg( 'course_id', $course_id, $permalink );
			}
		}

		$courses_url = CTA_Emails::get_page_url( 'cta_courses_page_id' );
		$total_mins  = 0;

		foreach ( $modules as $module ) {
			$total_mins += (int) $module->duration_mins;
		}

		$quiz            = CTA_Database::get_quiz_by_course( $course_id );
		$quiz_questions  = $quiz ? CTA_Database::get_quiz_questions( (int) $quiz->id ) : array();
		$preview_video   = $this->get_course_preview_video_markup( $course );
		$login_url       = CTA_Emails::get_page_url( 'cta_login_page_id' );
		$is_free_course  = (float) $course->price <= 0;
		$video_helper    = new CTA_Student_Dashboard();
		$resources       = CTA_Database::get_downloadable_resources( $course_id );
		$syllabus_meta   = class_exists( 'CTA_Syllabus_Sync' )
			? CTA_Syllabus_Sync::get_meta( $course )
			: array();

		ob_start();
		include CTA_PLUGIN_DIR . 'templates/single-course.php';
		return ob_get_clean();
	}

	/**
	 * Add body class on course pages.
	 *
	 * @param array $classes Body classes.
	 * @return array
	 */
	public function add_body_class( $classes ) {
		global $post;

		if ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'cta_single_course' ) ) {
			$classes[] = 'single-course-page';
		}

		return $classes;
	}

	/**
	 * Resolve the course currently shown by [cta_single_course], if any.
	 *
	 * @return object|null
	 */
	private function get_current_single_course() {
		global $post;

		if ( ! $post instanceof WP_Post || ! has_shortcode( $post->post_content, 'cta_single_course' ) ) {
			return null;
		}

		$course_id = absint( $_GET['course_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return $course_id ? CTA_Database::get_course( $course_id ) : null;
	}

	/**
	 * Apply syllabus SEO title on single-course pages.
	 *
	 * @param array $parts Title parts.
	 * @return array
	 */
	public function filter_document_title_parts( $parts ) {
		$course = $this->get_current_single_course();
		if ( ! $course ) {
			return $parts;
		}

		$meta = class_exists( 'CTA_Syllabus_Sync' ) ? CTA_Syllabus_Sync::get_meta( $course ) : array();
		if ( ! empty( $meta['seo_title'] ) ) {
			$parts['title'] = (string) $meta['seo_title'];
		}

		return $parts;
	}

	/**
	 * Output meta description (and related) from syllabus SEO fields.
	 */
	public function output_course_meta_tags() {
		$course = $this->get_current_single_course();
		if ( ! $course ) {
			return;
		}

		$meta = class_exists( 'CTA_Syllabus_Sync' ) ? CTA_Syllabus_Sync::get_meta( $course ) : array();
		if ( empty( $meta['meta_description'] ) ) {
			return;
		}

		echo '<meta name="description" content="' . esc_attr( (string) $meta['meta_description'] ) . '" />' . "\n";
	}

	/**
	 * Build preview video markup for the course hero.
	 *
	 * @param object $course Course row.
	 * @return string
	 */
	private function get_course_preview_video_markup( $course ) {
		$video_url = '';

		if ( ! empty( $course->video_url ) ) {
			$video_url = (string) $course->video_url;
		} elseif ( ! empty( $course->vimeo_id ) ) {
			$video_url = 'https://vimeo.com/' . preg_replace( '/\D/', '', (string) $course->vimeo_id );
		}

		if ( '' === $video_url ) {
			return '';
		}

		if ( preg_match( '/^\d+$/', trim( $video_url ) ) ) {
			$video_url = 'https://vimeo.com/' . trim( $video_url );
		}

		$youtube_id = $this->extract_youtube_id( $video_url );
		if ( $youtube_id ) {
			return sprintf(
				'<div class="course-hero__video-wrap"><iframe class="course-hero__iframe" src="https://www.youtube.com/embed/%1$s" title="%2$s" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div>',
				esc_attr( $youtube_id ),
				esc_attr( $course->title )
			);
		}

		if ( false !== strpos( $video_url, 'vimeo.com' ) ) {
			$vimeo_id = '';
			if ( preg_match( '/vimeo\.com\/(?:video\/)?(\d+)/', $video_url, $matches ) ) {
				$vimeo_id = $matches[1];
			}

			if ( $vimeo_id ) {
				return sprintf(
					'<div class="course-hero__video-wrap"><iframe class="course-hero__iframe" src="https://player.vimeo.com/video/%1$s" title="%2$s" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe></div>',
					esc_attr( $vimeo_id ),
					esc_attr( $course->title )
				);
			}
		}

		return sprintf(
			'<div class="course-hero__video-wrap"><video class="course-hero__html5-video" controls playsinline src="%1$s"></video></div>',
			esc_url( $video_url )
		);
	}

	/**
	 * Extract a YouTube video ID from a URL.
	 *
	 * @param string $url Video URL.
	 * @return string
	 */
	private function extract_youtube_id( $url ) {
		if ( preg_match( '/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]{6,})/', $url, $matches ) ) {
			return $matches[1];
		}

		return '';
	}

	/**
	 * AJAX handler for filtering and searching courses.
	 */
	public function ajax_filter_courses() {
		check_ajax_referer( 'cta_nonce', 'nonce' );

		$category     = sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) );
		$search       = sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) );
		$sort         = sanitize_text_field( wp_unslash( $_POST['sort'] ?? 'default' ) );
		$limit        = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : -1;
		$product_type = sanitize_text_field( wp_unslash( $_POST['product_type'] ?? 'ce' ) );

		$courses = $this->get_courses(
			array(
				'category'     => $category,
				'search'       => $search,
				'sort'         => $sort,
				'limit'        => $limit,
				'status'       => 'published',
				'product_type' => $product_type,
			)
		);

		ob_start();

		if ( empty( $courses ) ) {
			echo '<div class="cta-empty-state cta-empty-state--inline">';
			echo '<p>' . esc_html__( 'No courses found matching your search.', 'cta-lms' ) . '</p>';
			echo '</div>';
		} else {
			foreach ( $courses as $course ) {
				include CTA_PLUGIN_DIR . 'templates/partials/course-card.php';
			}
		}

		$html = ob_get_clean();

		wp_send_json_success(
			array(
				'html'  => $html,
				'count' => count( $courses ),
			)
		);
	}

	/**
	 * Fetch courses from the database with optional filters.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_courses( $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_courses';

		$defaults = array(
			'limit'        => -1,
			'category'     => '',
			'search'       => '',
			'sort'         => 'default',
			'status'       => 'published',
			'product_type' => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( 'status = %s' );
		$values = array( $args['status'] );

		if ( ! empty( $args['product_type'] ) && in_array( $args['product_type'], array( 'ce', 'exam_prep' ), true ) ) {
			$where[]  = 'product_type = %s';
			$values[] = $args['product_type'];
		}

		if ( ! empty( $args['category'] ) ) {
			$where[]  = 'category = %s';
			$values[] = $args['category'];
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]  = 'title LIKE %s';
			$values[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		$where_sql = 'WHERE ' . implode( ' AND ', $where );

		$order_sql = 'ORDER BY created_at DESC';

		if ( 'price_low' === $args['sort'] ) {
			$order_sql = 'ORDER BY price ASC';
		} elseif ( 'price_high' === $args['sort'] ) {
			$order_sql = 'ORDER BY price DESC';
		} elseif ( 'ce_hours' === $args['sort'] ) {
			$order_sql = 'ORDER BY ce_hours DESC';
		}

		$limit_sql = '';
		if ( $args['limit'] > 0 ) {
			$limit_sql = 'LIMIT ' . absint( $args['limit'] );
		}

		$sql = "SELECT * FROM {$table} {$where_sql} {$order_sql} {$limit_sql}";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders filled below.
		return $wpdb->get_results( $wpdb->prepare( $sql, ...$values ) );
	}

	/**
	 * Get unique published course categories.
	 *
	 * For CE catalogs, merge DB categories with the canonical admin list so
	 * new CE categories (e.g. Alcoholism) appear even before a course is linked,
	 * without duplicating labels. Exam Prep stays DB-driven.
	 *
	 * @param string $product_type Optional product type filter.
	 * @return array
	 */
	public function get_categories( $product_type = '' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_courses';

		if ( in_array( $product_type, array( 'ce', 'exam_prep' ), true ) ) {
			$from_db = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT category FROM {$table}
					WHERE status = 'published'
					AND product_type = %s
					AND category != ''
					AND category IS NOT NULL
					ORDER BY category ASC",
					$product_type
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is prefixed.
			$from_db = $wpdb->get_col(
				"SELECT DISTINCT category FROM {$table}
				WHERE status = 'published'
				AND category != ''
				AND category IS NOT NULL
				ORDER BY category ASC"
			);
		}

		$from_db = array_values(
			array_filter(
				array_map( 'strval', (array) $from_db ),
				static function ( $cat ) {
					return '' !== trim( $cat );
				}
			)
		);

		if ( 'exam_prep' === $product_type ) {
			return $from_db;
		}

		return $this->merge_ce_categories( $from_db );
	}

	/**
	 * Merge published CE categories with the canonical CE category list.
	 *
	 * Keeps existing categories, injects Alcoholism (and any other canonical CE
	 * categories that already have published courses), and sorts by admin order.
	 * Does not surface empty canonical categories except Alcoholism, which must
	 * stay visible so its archive link is ready before a course is connected.
	 *
	 * @param array $from_db Categories present on published courses.
	 * @return array
	 */
	private function merge_ce_categories( array $from_db ) {
		$alcoholism = class_exists( 'CTA_Admin' )
			? CTA_Admin::get_alcoholism_category_name()
			: 'Alcoholism & Other Chemical Substance Dependency';

		$canonical = class_exists( 'CTA_Admin' )
			? array_keys( CTA_Admin::get_course_categories() )
			: array(
				'Law & Ethics',
				'Clinical Skills',
				$alcoholism,
				'Specialized Topics',
				'Supervision',
				'Exam Preparation',
			);

		$canonical_ce = array_values(
			array_filter(
				$canonical,
				static function ( $cat ) {
					return 'Exam Preparation' !== $cat;
				}
			)
		);

		// Normalize near-duplicates of the Alcoholism label onto the exact title.
		$normalized_db = array();
		foreach ( $from_db as $cat ) {
			if ( $this->is_alcoholism_category_label( $cat ) ) {
				$cat = $alcoholism;
			}
			if ( 'Exam Preparation' === $cat ) {
				continue;
			}
			$normalized_db[ strtolower( $cat ) ] = $cat;
		}

		// Always include Alcoholism so the catalog tab/link exists even with 0 courses.
		$normalized_db[ strtolower( $alcoholism ) ] = $alcoholism;

		$ordered = array();
		$seen    = array();

		foreach ( $canonical_ce as $cat ) {
			$key = strtolower( $cat );
			if ( ! isset( $normalized_db[ $key ] ) ) {
				continue;
			}
			$ordered[]   = $normalized_db[ $key ];
			$seen[ $key ] = true;
		}

		$extras = array();
		foreach ( $normalized_db as $key => $cat ) {
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$extras[] = $cat;
		}
		natcasesort( $extras );

		return array_merge( $ordered, array_values( $extras ) );
	}

	/**
	 * Whether a stored category label is the Alcoholism CE category (exact or near-match).
	 *
	 * @param string $label Category label.
	 * @return bool
	 */
	private function is_alcoholism_category_label( $label ) {
		$label = trim( (string) $label );
		if ( '' === $label ) {
			return false;
		}

		$canonical = class_exists( 'CTA_Admin' )
			? CTA_Admin::get_alcoholism_category_name()
			: 'Alcoholism & Other Chemical Substance Dependency';

		if ( 0 === strcasecmp( $label, $canonical ) ) {
			return true;
		}

		// Avoid treating unrelated categories as Alcoholism.
		$normalized = strtolower( $label );
		return false !== strpos( $normalized, 'alcoholism' )
			&& false !== strpos( $normalized, 'chemical' )
			&& false !== strpos( $normalized, 'dependency' );
	}
}
}
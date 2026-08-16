<?php
/**
 * Exam Prep Downloads hub data provider.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CTA_Exam_Prep_Downloads' ) ) {

class CTA_Exam_Prep_Downloads {

	/**
	 * Ordered download categories.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function get_category_definitions() {
		return array(
			'workbooks'  => array(
				'label'       => __( 'Printable Workbooks', 'cta-lms' ),
				'description' => __( 'Student workbooks and printable lesson materials.', 'cta-lms' ),
			),
			'assessments'=> array(
				'label'       => __( 'Practice Assessments & Answer Keys', 'cta-lms' ),
				'description' => __( 'Printable practice banks, simulations, answer keys, and rationales when available.', 'cta-lms' ),
			),
			'toolkits'   => array(
				'label'       => __( 'Study Toolkits', 'cta-lms' ),
				'description' => __( 'Schedules, reference guides, readiness tools, trackers, and remediation materials.', 'cta-lms' ),
			),
			'audio'      => array(
				'label'       => __( 'Audio Downloads', 'cta-lms' ),
				'description' => __( 'Downloadable audio review tracks for offline study.', 'cta-lms' ),
			),
			'other'      => array(
				'label'       => __( 'Other Program Materials', 'cta-lms' ),
				'description' => __( 'Additional downloadable files included with this program.', 'cta-lms' ),
			),
		);
	}

	/**
	 * Build categorized download data for a course.
	 *
	 * @param object|null           $course    Course row.
	 * @param array                 $modules   Module rows.
	 * @param CTA_Student_Dashboard $dashboard Dashboard instance.
	 * @return array<string,mixed>
	 */
	public static function get_center_data_for_course( $course, array $modules, $dashboard ) {
		if ( ! $course || ! $dashboard ) {
			return self::empty_data();
		}

		$course_id = (int) $course->id;
		$resources = (array) CTA_Database::get_downloadable_resources( $course_id );
		if ( class_exists( 'CTA_Course_Materials' ) ) {
			$resources = CTA_Course_Materials::filter_student_visible_resources( $resources );
		}

		$module_order = array();
		foreach ( $modules as $index => $module ) {
			$module_order[ (int) $module->id ] = (int) $index;
		}

		$items      = self::build_items( $resources, $module_order );
		$categories = self::group_items( $items );

		$data = array(
			'categories'     => $categories,
			'item_count'     => count( $items ),
			'category_count' => count( $categories ),
			'has_downloads'  => ! empty( $items ),
			'downloads_url'  => $dashboard->get_player_view_url( $course_id, 'downloads' ),
		);

		return apply_filters( 'cta_exam_prep_downloads_data', $data, $course, $dashboard );
	}

	/**
	 * Empty payload.
	 *
	 * @return array<string,mixed>
	 */
	public static function empty_data() {
		return array(
			'categories'     => array(),
			'item_count'     => 0,
			'category_count' => 0,
			'has_downloads'  => false,
			'downloads_url'  => '',
		);
	}

	/**
	 * Compact category links for the course sidebar.
	 *
	 * @param array  $center  Downloads payload.
	 * @param array  $context Page context.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_sidebar_children( array $center, array $context ) {
		$children = array();
		$base_url = (string) ( $center['downloads_url'] ?? '' );

		foreach ( (array) ( $center['categories'] ?? array() ) as $category ) {
			$key = sanitize_key( (string) ( $category['key'] ?? '' ) );
			if ( '' === $key ) {
				continue;
			}
			$children[] = array(
				'key'       => 'downloads-' . $key,
				'label'     => (string) ( $category['label'] ?? '' ),
				'title'     => (string) ( $category['label'] ?? '' ),
				'url'       => $base_url . '#cta-dl-' . $key,
				'is_active' => false,
				'external'  => false,
			);
		}

		return $children;
	}

	/**
	 * Normalize real downloadable resource rows.
	 *
	 * @param array              $resources   Resource rows.
	 * @param array<int,int>     $module_order Module ID to display order.
	 * @return array<int,array<string,mixed>>
	 */
	private static function build_items( array $resources, array $module_order ) {
		$items = array();
		$seen  = array();

		foreach ( $resources as $resource ) {
			$resource_id = (int) ( $resource->id ?? 0 );
			if ( $resource_id <= 0 || ! class_exists( 'CTA_Course_Materials' ) ) {
				continue;
			}

			$user_id       = get_current_user_id();
			$can_access    = CTA_Course_Materials::user_can_access( $user_id, $resource );
			$requires_gate = CTA_Course_Materials::resource_requires_quiz_unlock( $resource );

			if ( ! $can_access && ! $requires_gate ) {
				continue;
			}

			$local        = CTA_Course_Materials::resolve_local_path( $resource );
			$external_url = self::get_external_file_url( $resource );
			if ( ! $local && '' === $external_url && $can_access ) {
				continue;
			}

			$filename = $local
				? basename( $local )
				: basename( (string) wp_parse_url( $external_url, PHP_URL_PATH ) );
			if ( '' === trim( $filename ) ) {
				continue;
			}

			$dedupe_keys = self::get_dedupe_keys( $resource, $local, $filename );
			$is_duplicate = false;
			foreach ( $dedupe_keys as $key ) {
				if ( isset( $seen[ $key ] ) ) {
					$is_duplicate = true;
					break;
				}
			}
			if ( $is_duplicate ) {
				continue;
			}
			foreach ( $dedupe_keys as $key ) {
				$seen[ $key ] = true;
			}

			$extension = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );
			$file_size = $local && is_readable( $local ) ? (int) filesize( $local ) : 0;
			$module_id = (int) ( $resource->module_id ?? 0 );
			$category  = self::classify_resource( $resource, $extension );

			$items[] = array(
				'resource_id'  => $resource_id,
				'title'        => (string) ( $resource->title ?? $filename ),
				'filename'     => 'toolkits' === $category ? '' : sanitize_file_name( $filename ),
				'extension'    => '' !== $extension ? strtoupper( $extension ) : __( 'FILE', 'cta-lms' ),
				'size_bytes'   => $file_size,
				'size_label'   => $file_size > 0 ? size_format( $file_size, 1 ) : '',
				'category'     => $category,
				'module_id'    => $module_id,
				'sort_order'   => isset( $module_order[ $module_id ] )
					? (int) $module_order[ $module_id ]
					: 1000 + (int) ( $resource->order_index ?? 0 ),
				'url'          => $can_access ? CTA_Course_Materials::get_download_url( $resource_id ) : '',
				'is_external'  => ! $local && '' !== $external_url,
				'locked'       => ! $can_access,
				'lock_message' => ( ! $can_access )
					? CTA_Course_Materials::get_unlock_lock_message( $user_id, $resource )
					: '',
			);
		}

		usort(
			$items,
			static function ( $a, $b ) {
				$category_order = array( 'workbooks', 'assessments', 'toolkits', 'audio', 'other' );
				$ca = array_search( (string) $a['category'], $category_order, true );
				$cb = array_search( (string) $b['category'], $category_order, true );
				$ca = false === $ca ? 99 : $ca;
				$cb = false === $cb ? 99 : $cb;
				if ( $ca !== $cb ) {
					return $ca <=> $cb;
				}
				$order = (int) $a['sort_order'] <=> (int) $b['sort_order'];
				return 0 !== $order ? $order : strnatcasecmp( (string) $a['title'], (string) $b['title'] );
			}
		);

		return $items;
	}

	/**
	 * Group items using the reusable category definitions.
	 *
	 * @param array<int,array<string,mixed>> $items Download items.
	 * @return array<int,array<string,mixed>>
	 */
	private static function group_items( array $items ) {
		$groups = array();
		foreach ( self::get_category_definitions() as $key => $definition ) {
			$groups[ $key ] = array(
				'key'         => $key,
				'label'       => (string) $definition['label'],
				'description' => (string) $definition['description'],
				'items'       => array(),
			);
		}

		foreach ( $items as $item ) {
			$key = (string) ( $item['category'] ?? 'other' );
			if ( ! isset( $groups[ $key ] ) ) {
				$key = 'other';
			}
			$groups[ $key ]['items'][] = $item;
		}

		return array_values(
			array_filter(
				$groups,
				static function ( $group ) {
					return ! empty( $group['items'] );
				}
			)
		);
	}

	/**
	 * Dynamic file category.
	 *
	 * @param object $resource  Resource row.
	 * @param string $extension Lowercase file extension.
	 * @return string
	 */
	private static function classify_resource( $resource, $extension ) {
		$haystack = strtolower(
			(string) ( $resource->title ?? '' ) . ' ' .
			(string) ( $resource->file_path ?? '' ) . ' ' .
			(string) ( $resource->file_url ?? '' )
		);
		$module_id = (int) ( $resource->module_id ?? 0 );

		if ( in_array( $extension, array( 'mp3', 'm4a', 'wav', 'ogg' ), true )
			|| preg_match( '/audio review|audio track|\/audio\//i', $haystack ) ) {
			return 'audio';
		}

		if ( ( $module_id > 0 && preg_match( '/workbook|student workbook|printable/i', $haystack ) )
			|| preg_match( '/workbook\s*\d+.*(?:student|printable)|student workbook|printable workbook/i', $haystack ) ) {
			return 'workbooks';
		}

		if ( ! empty( $resource->is_practice_test )
			|| preg_match( '/practice bank|question bank|practice assessment|practice exam|simulation|answer key|rationale|candidate form|candidate exam/i', $haystack ) ) {
			return 'assessments';
		}

		if ( preg_match( '/toolkit|study schedule|roadmap|readiness|progress tracker|quick reference|rapid review|decision guide|study map|exam trap|remediation|flashcard|study planning|error log/i', $haystack ) ) {
			return 'toolkits';
		}

		return 'other';
	}

	/**
	 * Return a valid legacy external file URL.
	 *
	 * @param object $resource Resource row.
	 * @return string
	 */
	private static function get_external_file_url( $resource ) {
		$url = (string) ( $resource->file_url ?? '' );
		if ( '' === $url || 0 === strpos( $url, 'cta-protected://' ) || ! wp_http_validate_url( $url ) ) {
			return '';
		}
		return $url;
	}

	/**
	 * Keys used to remove exact and version-labeled duplicates.
	 *
	 * @param object       $resource Resource row.
	 * @param string|false $local    Resolved local path.
	 * @param string       $filename Filename.
	 * @return string[]
	 */
	private static function get_dedupe_keys( $resource, $local, $filename ) {
		$keys = array();

		if ( ! empty( $resource->attachment_id ) ) {
			$keys[] = 'attachment:' . (int) $resource->attachment_id;
		}
		if ( $local ) {
			$normalized_path = strtolower( str_replace( '\\', '/', wp_normalize_path( $local ) ) );
			$keys[] = 'path:' . $normalized_path;
		}

		$title = self::normalize_label( (string) ( $resource->title ?? '' ) );
		$file  = self::normalize_label( (string) pathinfo( $filename, PATHINFO_FILENAME ) );
		if ( '' !== $title ) {
			$keys[] = 'title:' . $title;
		}
		if ( '' !== $file ) {
			$keys[] = 'file:' . $file;
		}

		return array_values( array_unique( $keys ) );
	}

	/**
	 * Normalize version/release variants for duplicate detection.
	 *
	 * @param string $value Label or filename.
	 * @return string
	 */
	private static function normalize_label( $value ) {
		$value = strtolower( wp_strip_all_tags( (string) $value ) );
		$value = preg_replace( '/^\s*[a-z]\d+[a-z]?\s*[-–—:]\s*/i', '', $value );
		$value = preg_replace( '/\bv\d+(?:\.\d+)*\b/i', '', $value );
		$value = preg_replace( '/\b(?:release|revised|final|formatted)\b/i', '', $value );
		$value = preg_replace( '/[^a-z0-9]+/', ' ', $value );
		return trim( (string) $value );
	}
}

}

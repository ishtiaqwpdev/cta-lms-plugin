<?php
/**
 * Exam Prep in-LMS workbook lessons (HTML converted from printable DOCX).
 *
 * Printable DOCX downloads remain available. HTML lessons power readable
 * Previous/Next module pages inside the course player.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Exam_Prep_Lessons
 */
if ( ! class_exists( 'CTA_Exam_Prep_Lessons' ) ) {

class CTA_Exam_Prep_Lessons {

	/**
	 * Course slug → materials program folder key.
	 *
	 * @return array<string,string>
	 */
	public static function get_program_map() {
		return array(
			'lpcc-ncmhce-exam-preparation'                   => 'lpcc-ncmhce',
			'lpcc-california-clinical-exam-preparation'      => 'lpcc-ncmhce',
			'lpcc-california-law-ethics-exam-preparation'    => 'lpcc-law-ethics',
			'lcsw-california-law-ethics-exam-preparation'    => 'lcsw-law-ethics',
			'lcsw-aswb-clinical-exam-preparation'            => 'lcsw-aswb',
			'lcsw-california-clinical-exam-preparation'      => 'lcsw-aswb',
			'lmft-california-clinical-exam-preparation'      => 'lmft-clinical',
			'lmft-amftrb-national-exam-preparation'          => 'lmft-amftrb',
		);
	}

	/**
	 * Extract workbook number from a module title like "Workbook 3: …".
	 *
	 * @param object|string $module Module row or title string.
	 * @return int
	 */
	public static function workbook_number_from_module( $module ) {
		$title = is_object( $module ) ? (string) ( $module->title ?? '' ) : (string) $module;
		if ( preg_match( '/^\s*Workbook\s+(\d+)\s*:/i', $title, $m ) ) {
			return absint( $m[1] );
		}
		return 0;
	}

	/**
	 * Resolve program folder key for a course.
	 *
	 * @param object|null $course Course row.
	 * @return string
	 */
	public static function program_key_for_course( $course ) {
		if ( ! $course || empty( $course->slug ) ) {
			return '';
		}
		$map  = self::get_program_map();
		$slug = sanitize_title( (string) $course->slug );
		return isset( $map[ $slug ] ) ? $map[ $slug ] : '';
	}

	/**
	 * Absolute path to a lesson HTML file.
	 *
	 * @param string $program_key Program folder.
	 * @param int    $workbook_num Workbook number.
	 * @return string
	 */
	public static function lesson_path( $program_key, $workbook_num ) {
		$program_key  = sanitize_title( (string) $program_key );
		$workbook_num = absint( $workbook_num );
		if ( '' === $program_key || $workbook_num < 1 ) {
			return '';
		}
		return CTA_PLUGIN_DIR . 'assets/course-materials/' . $program_key . '/lessons/wb' . sprintf( '%02d', $workbook_num ) . '.html';
	}

	/**
	 * Load sanitized lesson HTML for a course module.
	 *
	 * @param object|null $course Course row.
	 * @param object|null $module Module row.
	 * @return array{html:string,workbook_num:int,program:string}|null
	 */
	public static function get_lesson_for_module( $course, $module ) {
		if ( ! $course || ! $module ) {
			return null;
		}
		if ( class_exists( 'CTA_Exam_Access' ) && ! CTA_Exam_Access::is_exam_prep( $course ) ) {
			return null;
		}

		$program = self::program_key_for_course( $course );
		if ( '' === $program ) {
			return null;
		}

		$title = is_object( $module ) ? (string) ( $module->title ?? '' ) : '';
		$num   = self::workbook_number_from_module( $module );
		$path  = '';

		// Start Here / license-specific orientation lesson (non-workbook module).
		if ( preg_match( '/^\s*Start\s+Here\s*:/i', $title ) ) {
			$path = CTA_PLUGIN_DIR . 'assets/course-materials/' . $program . '/lessons/start-here.html';
			$num  = 0;
		} else {
			$path = self::lesson_path( $program, $num );
		}

		if ( '' === $path || ! is_readable( $path ) ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$raw = file_get_contents( $path );
		if ( false === $raw || '' === trim( $raw ) ) {
			return null;
		}

		$html = self::sanitize_lesson_html( $raw );
		if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
			return null;
		}

		return array(
			'html'         => $html,
			'workbook_num' => $num,
			'program'      => $program,
		);
	}

	/**
	 * Allowlisted HTML for lesson body.
	 *
	 * @param string $html Raw HTML.
	 * @return string
	 */
	public static function sanitize_lesson_html( $html ) {
		if ( function_exists( 'cta_lms_sanitize_utf8_text' ) ) {
			$html = cta_lms_sanitize_utf8_text( (string) $html );
		}

		// Safety net for legacy glued banner labels already stored in HTML.
		$html = preg_replace(
			'/\b(MATERIAL|NOTICE|WELCOME|LOCKS|PROGRAM|IMPORTANT|REPAIR|CONTROL|SEQUENCE|REASONING|LAB)(?=[A-Z][a-z])/u',
			'$1 ',
			(string) $html
		);

		$allowed = array(
			'article' => array(
				'class'         => true,
				'data-program'  => true,
				'data-workbook' => true,
			),
			'div'     => array( 'class' => true ),
			'h2'      => array( 'class' => true ),
			'h3'      => array( 'class' => true ),
			'h4'      => array( 'class' => true ),
			'p'       => array( 'class' => true ),
			'ul'      => array( 'class' => true ),
			'ol'      => array( 'class' => true ),
			'li'      => array( 'class' => true ),
			'table'   => array( 'class' => true ),
			'tbody'   => array(),
			'thead'   => array(),
			'tr'      => array(),
			'th'      => array( 'colspan' => true, 'rowspan' => true ),
			'td'      => array( 'colspan' => true, 'rowspan' => true ),
			'br'      => array(),
			'hr'      => array( 'class' => true ),
			'strong'  => array(),
			'em'      => array(),
			'b'       => array(),
			'i'       => array(),
		);

		return wp_kses( $html, $allowed );
	}

	/**
	 * Find the printable workbook download resource for the current module.
	 *
	 * @param array       $resources Course resources.
	 * @param object|null $module    Module row.
	 * @return object|null
	 */
	public static function find_workbook_resource( array $resources, $module ) {
		if ( empty( $resources ) || ! $module ) {
			return null;
		}

		$module_id = absint( $module->id );
		$num       = self::workbook_number_from_module( $module );

		foreach ( $resources as $resource ) {
			if ( empty( $resource->title ) ) {
				continue;
			}
			$title = (string) $resource->title;
			$file  = isset( $resource->file_name ) ? (string) $resource->file_name : '';
			$is_wb = ( false !== stripos( $title, 'workbook' ) || false !== stripos( $file, 'workbook' ) )
				&& false === stripos( $title, 'remediation' )
				&& empty( $resource->is_practice_test );

			if ( ! $is_wb ) {
				continue;
			}

			if ( $module_id && ! empty( $resource->module_id ) && absint( $resource->module_id ) === $module_id ) {
				return $resource;
			}

			if ( $num > 0 && preg_match( '/\bWB\s*' . $num . '\b|Workbook\s+' . $num . '\b/i', $title . ' ' . $file ) ) {
				return $resource;
			}
		}

		return null;
	}
}
}

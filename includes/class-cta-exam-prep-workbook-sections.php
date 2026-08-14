<?php
/**
 * Exam Prep workbook lesson — parse long HTML into in-page tab sections.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Exam_Prep_Workbook_Sections
 */
if ( ! class_exists( 'CTA_Exam_Prep_Workbook_Sections' ) ) {

class CTA_Exam_Prep_Workbook_Sections {

	/**
	 * Tab display order and default labels.
	 *
	 * @return array<string,string>
	 */
	public static function tab_labels() {
		return array(
			'overview'    => __( 'Overview', 'cta-lms' ),
			'core'        => __( 'Core Content', 'cta-lms' ),
			'reference'   => __( 'Reference Tables', 'cta-lms' ),
			'traps'       => __( 'Common Exam Traps', 'cta-lms' ),
			'applied'     => __( 'Applied / Scenarios', 'cta-lms' ),
			'summary'     => __( 'Chapter Summary', 'cta-lms' ),
			'knowledge'   => __( 'Knowledge Check', 'cta-lms' ),
			'answers'     => __( 'Answer Key & Rationales', 'cta-lms' ),
			'study_tools' => __( 'Notes & Study Tools', 'cta-lms' ),
			'practice'    => __( 'Practice Bank', 'cta-lms' ),
		);
	}

	/**
	 * Build tabbed sections from lesson HTML plus workbook extras.
	 *
	 * @param string $html               Sanitized lesson HTML.
	 * @param array  $extra              Optional practice bank data (quiz_cards, bank_url, quiz_page_id).
	 * @return array<int,array<string,mixed>>
	 */
	public static function build_tabs( $html, array $extra = array() ) {
		$html = trim( (string) $html );
		if ( '' === $html ) {
			return self::practice_tab_only( $extra );
		}

		$raw_sections = self::split_by_headings( $html );
		$grouped      = self::merge_into_groups( $raw_sections );
		$tabs         = array();
		$labels       = self::tab_labels();
		$order        = array_keys( $labels );

		foreach ( $order as $group_key ) {
			if ( 'practice' === $group_key ) {
				continue;
			}
			if ( empty( $grouped[ $group_key ] ) ) {
				continue;
			}

			$tabs[] = array(
				'key'   => $group_key,
				'label' => $labels[ $group_key ],
				'html'  => self::wrap_group_html( $grouped[ $group_key ] ),
				'type'  => 'lesson',
			);
		}

		$practice_tab = self::build_practice_tab( $extra );
		if ( $practice_tab ) {
			$tabs[] = $practice_tab;
		}

		if ( empty( $tabs ) ) {
			$tabs[] = array(
				'key'   => 'content',
				'label' => __( 'Workbook Content', 'cta-lms' ),
				'html'  => '<div class="cta-exam-lesson__body cta-ep-workbook-section__body">' . $html . '</div>',
				'type'  => 'lesson',
			);
		}

		/**
		 * Filter workbook in-page tabs after automatic grouping.
		 *
		 * @param array  $tabs  Tab rows with key, label, html, type.
		 * @param string $html  Original lesson HTML.
		 * @param array  $extra Practice bank extras.
		 */
		return apply_filters( 'cta_exam_prep_workbook_tabs', $tabs, $html, $extra );
	}

	/**
	 * Split lesson HTML at h2 headings.
	 *
	 * @param string $html Lesson HTML.
	 * @return array<int,array{heading:string,html:string,group:string}>
	 */
	public static function split_by_headings( $html ) {
		$sections = array();
		$inner    = $html;

		if ( preg_match( '/^\s*<article[^>]*>(.*)<\/article>\s*$/is', $html, $article_match ) ) {
			$inner = $article_match[1];
		}

		$parts = preg_split( '/(?=<h2\s+class="cta-lesson-h2">)/i', $inner, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $parts ) ) {
			return array(
				array(
					'heading' => '',
					'html'    => $html,
					'group'   => 'core',
				),
			);
		}

		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( '' === $part ) {
				continue;
			}

			if ( ! preg_match( '/<h2\s+class="cta-lesson-h2">([^<]*)<\/h2>/i', $part, $heading_match ) ) {
				$sections[] = array(
					'heading' => '',
					'html'    => $part,
					'group'   => 'overview',
				);
				continue;
			}

			$heading = html_entity_decode( wp_strip_all_tags( $heading_match[1] ), ENT_QUOTES, 'UTF-8' );
			$sections[] = array(
				'heading' => $heading,
				'html'    => $part,
				'group'   => self::classify_heading( $heading ),
			);
		}

		return $sections;
	}

	/**
	 * Classify a section heading into a tab group key.
	 *
	 * @param string $heading Heading text.
	 * @return string
	 */
	public static function classify_heading( $heading ) {
		$h = strtolower( trim( (string) $heading ) );

		if ( preg_match( '/how to use|learning objectives|why this topic matters|publication notice/', $h ) ) {
			return 'overview';
		}

		if ( preg_match( '/^chapter map/', $h ) ) {
			return 'reference';
		}

		if ( preg_match( '/^workbook close/', $h ) ) {
			return 'study_tools';
		}

		if ( preg_match( '/^common exam trap/', $h ) ) {
			return 'traps';
		}

		if ( preg_match( '/^chapter summary/', $h ) ) {
			return 'summary';
		}

		if ( preg_match( '/^knowledge check/', $h ) ) {
			return 'knowledge';
		}

		if ( preg_match( '/^answer key|detailed rationale/', $h ) ) {
			return 'answers';
		}

		if ( preg_match( '/score and study|sources and currency|notes and study|search and look|look-up tool|study tool/', $h ) ) {
			return 'study_tools';
		}

		if ( preg_match( '/worked example|applied question|scenario-based|scenario based|^applied /', $h ) ) {
			return 'applied';
		}

		if ( preg_match( '/worksheet|assessment map|integrated .* map|reference table|comparison table/', $h ) ) {
			return 'reference';
		}

		if ( preg_match( '/^(cta |student workbook|workbook \d+|start here)/i', $heading ) ) {
			return 'overview';
		}

		if ( preg_match( '/^\d+\.\s/', $heading ) ) {
			return 'core';
		}

		if ( preg_match( '/^worked examples$/', $h ) ) {
			return 'applied';
		}

		if ( preg_match( '/^integrated /', $h ) && preg_match( '/map|plan|sequence|assessment/', $h ) ) {
			return 'reference';
		}

		return 'core';
	}

	/**
	 * Merge consecutive sections sharing a group key.
	 *
	 * @param array $sections Split sections.
	 * @return array<string,array<int,array{heading:string,html:string,group:string}>>
	 */
	private static function merge_into_groups( array $sections ) {
		$grouped = array();

		foreach ( $sections as $section ) {
			$group = (string) ( $section['group'] ?? 'core' );
			if ( ! isset( $grouped[ $group ] ) ) {
				$grouped[ $group ] = array();
			}
			$grouped[ $group ][] = $section;
		}

		return $grouped;
	}

	/**
	 * Wrap grouped section HTML in a lesson body container.
	 *
	 * @param array $sections Section rows.
	 * @return string
	 */
	private static function wrap_group_html( array $sections ) {
		$chunks = array();
		foreach ( $sections as $section ) {
			if ( ! empty( $section['html'] ) ) {
				$chunks[] = (string) $section['html'];
			}
		}

		if ( empty( $chunks ) ) {
			return '';
		}

		return '<div class="cta-exam-lesson__body cta-ep-workbook-section__body">' . implode( "\n", $chunks ) . '</div>';
	}

	/**
	 * Build practice bank tab when quizzes or download exist.
	 *
	 * @param array $extra Extra data.
	 * @return array<string,mixed>|null
	 */
	private static function build_practice_tab( array $extra ) {
		$quiz_cards   = isset( $extra['quiz_cards'] ) ? (array) $extra['quiz_cards'] : array();
		$bank_url     = (string) ( $extra['bank_download_url'] ?? '' );
		$bank_title   = (string) ( $extra['bank_title'] ?? '' );
		$quiz_page_id = absint( $extra['quiz_page_id'] ?? 0 );

		if ( empty( $quiz_cards ) && '' === $bank_url ) {
			return null;
		}

		return array(
			'key'        => 'practice',
			'label'      => self::tab_labels()['practice'],
			'html'       => '',
			'type'       => 'practice',
			'quiz_cards' => $quiz_cards,
			'bank_url'   => $bank_url,
			'bank_title' => $bank_title,
			'quiz_page_id' => $quiz_page_id,
		);
	}

	/**
	 * Practice-only tab list when no lesson HTML exists.
	 *
	 * @param array $extra Extra data.
	 * @return array<int,array<string,mixed>>
	 */
	private static function practice_tab_only( array $extra ) {
		$tab = self::build_practice_tab( $extra );
		return $tab ? array( $tab ) : array();
	}
}

}

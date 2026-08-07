<?php
/**
 * Interactive Exam Prep flashcard decks (converted from printable DOCX).
 *
 * Printable DOCX downloads remain the source of truth for offline study.
 * JSON decks power the in-browser flip / prev / next / shuffle viewer.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Flashcards
 */
if ( ! class_exists( 'CTA_Flashcards' ) ) {

class CTA_Flashcards {

	/**
	 * Course slug → relative JSON path under the plugin directory.
	 *
	 * @return array<string,string>
	 */
	public static function get_deck_map() {
		return array(
			'lpcc-ncmhce-exam-preparation'              => 'assets/course-materials/lpcc-ncmhce/study-tools/flashcards.json',
			'lpcc-california-clinical-exam-preparation' => 'assets/course-materials/lpcc-ncmhce/study-tools/flashcards.json',
			'lcsw-aswb-clinical-exam-preparation'       => 'assets/course-materials/lcsw-aswb/study-tools/flashcards.json',
			'lcsw-california-clinical-exam-preparation' => 'assets/course-materials/lcsw-aswb/study-tools/flashcards.json',
			'lmft-california-clinical-exam-preparation' => 'assets/course-materials/lmft-clinical/study-tools/flashcards.json',
			'lmft-amftrb-national-exam-preparation'     => 'assets/course-materials/lmft-amftrb/study-tools/flashcards.json',
		);
	}

	/**
	 * Resolve flashcard deck for a course object/row.
	 *
	 * @param object|null $course Course row.
	 * @return array{title:string,count:int,cards:array<int,array<string,string>>}|null
	 */
	public static function get_deck_for_course( $course ) {
		if ( ! $course || empty( $course->slug ) ) {
			return null;
		}

		if ( class_exists( 'CTA_Exam_Access' ) && ! CTA_Exam_Access::is_exam_prep( $course ) ) {
			return null;
		}

		$map  = self::get_deck_map();
		$slug = sanitize_title( (string) $course->slug );
		if ( ! isset( $map[ $slug ] ) ) {
			return null;
		}

		$path = CTA_PLUGIN_DIR . $map[ $slug ];
		if ( ! is_readable( $path ) ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$raw = file_get_contents( $path );
		if ( false === $raw || '' === $raw ) {
			return null;
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) || empty( $data['cards'] ) || ! is_array( $data['cards'] ) ) {
			return null;
		}

		$cards = array();
		foreach ( $data['cards'] as $card ) {
			if ( ! is_array( $card ) ) {
				continue;
			}
			$front = isset( $card['front'] ) ? trim( wp_strip_all_tags( (string) $card['front'] ) ) : '';
			$back  = isset( $card['back'] ) ? trim( wp_strip_all_tags( (string) $card['back'] ) ) : '';
			if ( '' === $front || '' === $back ) {
				continue;
			}
			$cards[] = array(
				'id'    => isset( $card['id'] ) ? sanitize_text_field( (string) $card['id'] ) : (string) ( count( $cards ) + 1 ),
				'tag'   => isset( $card['tag'] ) ? sanitize_text_field( (string) $card['tag'] ) : '',
				'front' => $front,
				'back'  => $back,
			);
		}

		if ( empty( $cards ) ) {
			return null;
		}

		return array(
			'title' => ! empty( $data['title'] )
				? sanitize_text_field( (string) $data['title'] )
				: __( 'Flashcards', 'cta-lms' ),
			'count' => count( $cards ),
			'cards' => $cards,
		);
	}
}
}

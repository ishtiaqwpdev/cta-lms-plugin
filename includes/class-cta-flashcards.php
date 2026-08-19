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
			// LPCC NCMHCE legacy flashcards.json archived — use Flashcard Study Center only.
			'lpcc-california-law-ethics-exam-preparation'    => 'assets/course-materials/lpcc-law-ethics/study-tools/flashcards.json',
			'lcsw-california-law-ethics-exam-preparation'    => 'assets/course-materials/lcsw-law-ethics/study-tools/flashcards.json',
			'lcsw-aswb-clinical-exam-preparation'            => 'assets/course-materials/lcsw-aswb/study-tools/flashcards.json',
			'lcsw-california-clinical-exam-preparation'      => 'assets/course-materials/lcsw-aswb/study-tools/flashcards.json',
			// LMFT California Clinical legacy flashcards.json archived — use Flashcard Study Center only.
			'lmft-amftrb-national-exam-preparation'          => 'assets/course-materials/lmft-amftrb/study-tools/flashcards.json',
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

		if ( class_exists( 'CTA_Lmft_Clinical_Legacy_Flashcard_Archive' )
			&& CTA_Lmft_Clinical_Legacy_Flashcard_Archive::blocks_learner_legacy_deck( $course ) ) {
			return null;
		}

		if ( class_exists( 'CTA_Lpcc_Ncmhce_Legacy_Flashcard_Archive' )
			&& CTA_Lpcc_Ncmhce_Legacy_Flashcard_Archive::blocks_learner_legacy_deck( $course ) ) {
			return null;
		}

		if ( class_exists( 'CTA_Lcsw_Aswb_Legacy_Flashcard_Archive' )
			&& CTA_Lcsw_Aswb_Legacy_Flashcard_Archive::blocks_learner_legacy_deck( $course ) ) {
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
			$front = isset( $card['front'] ) ? self::sanitize_card_text( (string) $card['front'] ) : '';
			$back  = isset( $card['back'] ) ? self::sanitize_card_text( (string) $card['back'] ) : '';
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

	/**
	 * Sanitize flashcard face text while preserving intentional line breaks.
	 *
	 * Source decks often separate concepts with newlines. Do not collapse those
	 * into a single wrapped paragraph — CSS uses white-space: pre-wrap.
	 *
	 * @param string $text Raw front/back text.
	 * @return string
	 */
	private static function sanitize_card_text( $text ) {
		$text = (string) $text;
		if ( '' === $text ) {
			return '';
		}

		// Repair mojibake / charset issues (separate from layout wrapping).
		if ( function_exists( 'cta_lms_sanitize_utf8_text' ) ) {
			$text = cta_lms_sanitize_utf8_text( $text );
		}

		// Normalize line endings and turn break tags into newlines before stripping.
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		$text = preg_replace( '/<\s*br\s*\/?\s*>/i', "\n", $text );
		$text = preg_replace( '/<\/\s*p\s*>/i', "\n", $text );

		// Keep newlines (second arg false). Strip tags only.
		$text = wp_strip_all_tags( $text, false );

		// Trim edges; collapse runs of spaces/tabs within a line, not across lines.
		$lines = explode( "\n", $text );
		$lines = array_map(
			static function ( $line ) {
				$line = preg_replace( '/[ \t]+/', ' ', (string) $line );
				return trim( (string) $line );
			},
			$lines
		);

		// Preserve blank lines between paragraphs (max one empty line in a row).
		$out   = array();
		$blank = false;
		foreach ( $lines as $line ) {
			if ( '' === $line ) {
				if ( ! $blank && ! empty( $out ) ) {
					$out[] = '';
					$blank = true;
				}
				continue;
			}
			$out[] = $line;
			$blank = false;
		}

		return trim( implode( "\n", $out ) );
	}
}
}

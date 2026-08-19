<?php
/**
 * Exam Prep Flashcard Study Center — blueprint-aligned deck loader.
 *
 * Separate from the legacy CTA_Flashcards viewer (workbook/materials embed).
 * Decks live in per-program flashcard-study-center.json files.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Exam_Prep_Flashcard_Center
 */
if ( ! class_exists( 'CTA_Exam_Prep_Flashcard_Center' ) ) {

class CTA_Exam_Prep_Flashcard_Center {

	/**
	 * Course slug → relative JSON path under the plugin directory.
	 *
	 * @return array<string,string>
	 */
	public static function get_deck_path_map() {
		$map = array(
			'california-law-ethics-exam-preparation'      => 'assets/course-materials/lmft-law-ethics/study-tools/flashcard-study-center.json',
			'lmft-california-clinical-exam-preparation'   => 'assets/course-materials/lmft-clinical/study-tools/flashcard-study-center.json',
			'lmft-amftrb-national-exam-preparation'       => 'assets/course-materials/lmft-amftrb/study-tools/flashcard-study-center.json',
			'lcsw-aswb-clinical-exam-preparation'         => 'assets/course-materials/lcsw-aswb/study-tools/flashcard-study-center.json',
			'lcsw-california-clinical-exam-preparation'   => 'assets/course-materials/lcsw-aswb/study-tools/flashcard-study-center.json',
			'lcsw-california-law-ethics-exam-preparation' => 'assets/course-materials/lcsw-law-ethics/study-tools/flashcard-study-center.json',
			'lpcc-ncmhce-exam-preparation'                => 'assets/course-materials/lpcc-ncmhce/study-tools/flashcard-study-center.json',
			'lpcc-california-clinical-exam-preparation'   => 'assets/course-materials/lpcc-ncmhce/study-tools/flashcard-study-center.json',
			'lpcc-california-law-ethics-exam-preparation' => 'assets/course-materials/lpcc-law-ethics/study-tools/flashcard-study-center.json',
		);

		/**
		 * Filter deck JSON paths for exam-prep Flashcard Study Center.
		 *
		 * @param array<string,string> $map Course slug → relative path.
		 */
		return apply_filters( 'cta_exam_prep_flashcard_study_center_paths', $map );
	}

	/**
	 * Course slugs allowed to reuse legacy flashcards.json when Study Center JSON is empty.
	 *
	 * Programs with an approved blueprint-aligned Study Center deck must NOT appear here.
	 *
	 * @return array<int,string>
	 */
	public static function get_legacy_fallback_slugs() {
		$slugs = array(
			'lpcc-california-law-ethics-exam-preparation',
			'lcsw-california-law-ethics-exam-preparation',
			'lcsw-aswb-clinical-exam-preparation',
			'lcsw-california-clinical-exam-preparation',
			'lmft-amftrb-national-exam-preparation',
		);

		// When the approved Study Center deck is live, never fall back to legacy JSON.
		if ( self::study_center_deck_is_live( 'lcsw-aswb' ) ) {
			$slugs = array_values(
				array_diff(
					$slugs,
					array(
						'lcsw-aswb-clinical-exam-preparation',
						'lcsw-california-clinical-exam-preparation',
					)
				)
			);
		}

		if ( self::study_center_deck_is_live( 'lpcc-ncmhce' ) ) {
			$slugs = array_values(
				array_diff(
					$slugs,
					array(
						'lpcc-ncmhce-exam-preparation',
						'lpcc-california-clinical-exam-preparation',
					)
				)
			);
		}

		/**
		 * Filter slugs that may fall back to legacy flashcards.json decks.
		 *
		 * @param array<int,string> $slugs Course slugs.
		 */
		return apply_filters( 'cta_exam_prep_flashcard_study_center_legacy_fallback_slugs', $slugs );
	}

	/**
	 * Whether a program's flashcard-study-center.json has the approved live deck.
	 *
	 * @param string $program_key Program materials folder key (e.g. lcsw-aswb).
	 * @return bool
	 */
	public static function study_center_deck_is_live( $program_key ) {
		$program_key = sanitize_key( (string) $program_key );
		if ( '' === $program_key ) {
			return false;
		}

		$path = CTA_PLUGIN_DIR . 'assets/course-materials/' . $program_key . '/study-tools/flashcard-study-center.json';
		$data = self::read_deck_file( $path );
		if ( ! is_array( $data ) || empty( $data['cards'] ) || ! is_array( $data['cards'] ) ) {
			return false;
		}

		$expected = isset( $data['expected_total'] ) ? (int) $data['expected_total'] : 180;
		if ( $expected < 1 ) {
			$expected = 180;
		}

		return count( $data['cards'] ) >= $expected;
	}

	/**
	 * Default empty deck payload for a course.
	 *
	 * @param object|null $course Course row.
	 * @return array<string,mixed>
	 */
	public static function get_empty_deck( $course = null ) {
		$title = __( 'Flashcard Study Center', 'cta-lms' );
		if ( $course && function_exists( 'cta_lms_get_course_display_title' ) ) {
			$title = sprintf(
				/* translators: %s: program display title */
				__( '%s — Flashcard Study Center', 'cta-lms' ),
				cta_lms_get_course_display_title( $course )
			);
		}

		return array(
			'title'       => $title,
			'count'       => 0,
			'cards'       => array(),
			'domains'     => array(),
			'has_content' => false,
		);
	}

	/**
	 * Resolve Flashcard Study Center deck for a course.
	 *
	 * @param object|null $course Course row.
	 * @return array<string,mixed>
	 */
	public static function get_deck_for_course( $course ) {
		if ( ! $course || empty( $course->slug ) ) {
			return self::get_empty_deck( $course );
		}

		if ( class_exists( 'CTA_Exam_Access' ) && ! CTA_Exam_Access::is_exam_prep( $course ) ) {
			return self::get_empty_deck( $course );
		}

		$map  = self::get_deck_path_map();
		$slug = sanitize_title( (string) $course->slug );
		if ( ! isset( $map[ $slug ] ) ) {
			$deck = self::get_empty_deck( $course );
			return apply_filters( 'cta_exam_prep_flashcard_study_center_deck', $deck, $course );
		}

		$path = CTA_PLUGIN_DIR . ltrim( $map[ $slug ], '/' );
		$data = self::read_deck_file( $path );

		// Some programs ship a dedicated Study Center deck that must remain
		// separate from the legacy printable/interactive flashcards.json library.
		$legacy_fallback_slugs = self::get_legacy_fallback_slugs();
		if (
			( ! is_array( $data ) || empty( $data['cards'] ) )
			&& class_exists( 'CTA_Flashcards' )
			&& in_array( $slug, $legacy_fallback_slugs, true )
		) {
			$legacy_map = CTA_Flashcards::get_deck_map();
			if ( isset( $legacy_map[ $slug ] ) ) {
				$data = self::read_deck_file( CTA_PLUGIN_DIR . ltrim( $legacy_map[ $slug ], '/' ) );
			}
		}

		if ( ! is_array( $data ) ) {
			$deck = self::get_empty_deck( $course );
			return apply_filters( 'cta_exam_prep_flashcard_study_center_deck', $deck, $course );
		}

		$domain_map = self::normalize_domains( isset( $data['domains'] ) ? (array) $data['domains'] : array() );
		$cards      = self::normalize_cards(
			isset( $data['cards'] ) ? (array) $data['cards'] : array(),
			$domain_map
		);

		if ( empty( $cards ) ) {
			$deck = self::get_empty_deck( $course );
			if ( ! empty( $data['title'] ) ) {
				$deck['title'] = sanitize_text_field( (string) $data['title'] );
			}
			$deck['domains'] = array_values( $domain_map );
			return apply_filters( 'cta_exam_prep_flashcard_study_center_deck', $deck, $course );
		}

		$domains = self::build_domain_stats( $cards, $domain_map );

		$deck = array(
			'title'       => ! empty( $data['title'] )
				? sanitize_text_field( (string) $data['title'] )
				: self::get_empty_deck( $course )['title'],
			'count'       => count( $cards ),
			'cards'       => $cards,
			'domains'     => $domains,
			'has_content' => true,
		);

		/**
		 * Filter parsed Flashcard Study Center deck.
		 *
		 * @param array<string,mixed> $deck   Normalized deck.
		 * @param object              $course Course row.
		 */
		return apply_filters( 'cta_exam_prep_flashcard_study_center_deck', $deck, $course );
	}

	/**
	 * Read and decode a flashcard JSON file.
	 *
	 * @param string $path Absolute file path.
	 * @return array<string,mixed>|null
	 */
	private static function read_deck_file( $path ) {
		if ( ! is_readable( $path ) ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$raw = file_get_contents( $path );
		if ( false === $raw || '' === $raw ) {
			return null;
		}

		$data = json_decode( $raw, true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Normalize domain definitions from JSON.
	 *
	 * @param array<int,mixed> $domains Raw domain rows.
	 * @return array<string,array{key:string,label:string,order:int}>
	 */
	private static function normalize_domains( array $domains ) {
		$map = array();
		$order = 0;

		foreach ( $domains as $domain ) {
			if ( ! is_array( $domain ) ) {
				continue;
			}
			$key = sanitize_key( (string) ( $domain['key'] ?? $domain['id'] ?? '' ) );
			if ( '' === $key ) {
				continue;
			}
			$label = isset( $domain['label'] ) ? sanitize_text_field( (string) $domain['label'] ) : $key;
			$map[ $key ] = array(
				'key'   => $key,
				'label' => $label,
				'order' => isset( $domain['order'] ) ? (int) $domain['order'] : ++$order,
			);
		}

		uasort(
			$map,
			static function ( $a, $b ) {
				return (int) $a['order'] <=> (int) $b['order'];
			}
		);

		return $map;
	}

	/**
	 * Normalize card rows.
	 *
	 * @param array<int,mixed>                              $cards      Raw cards.
	 * @param array<string,array{key:string,label:string,order:int}> $domain_map Domain map.
	 * @return array<int,array<string,string>>
	 */
	private static function normalize_cards( array $cards, array &$domain_map ) {
		$normalized = array();
		$auto_order = count( $domain_map );

		foreach ( $cards as $index => $card ) {
			if ( ! is_array( $card ) ) {
				continue;
			}

			$front = isset( $card['front'] ) ? self::sanitize_card_text( (string) $card['front'] ) : '';
			$back  = isset( $card['back'] ) ? self::sanitize_card_text( (string) $card['back'] ) : '';
			if ( '' === $front || '' === $back ) {
				continue;
			}

			$domain_key = sanitize_key( (string) ( $card['domain'] ?? $card['category'] ?? '' ) );
			if ( '' === $domain_key && ! empty( $card['tag'] ) ) {
				$domain_key = sanitize_key( (string) $card['tag'] );
			}

			if ( '' === $domain_key ) {
				$domain_key = 'general';
			}

			if ( ! isset( $domain_map[ $domain_key ] ) ) {
				$label = ! empty( $card['domain_label'] )
					? sanitize_text_field( (string) $card['domain_label'] )
					: ucwords( str_replace( array( '-', '_' ), ' ', $domain_key ) );
				$domain_map[ $domain_key ] = array(
					'key'   => $domain_key,
					'label' => $label,
					'order' => ++$auto_order,
				);
			}

			$memory_cue = isset( $card['memory_cue'] )
				? self::sanitize_card_text( (string) $card['memory_cue'] )
				: ( isset( $card['memoryCue'] ) ? self::sanitize_card_text( (string) $card['memoryCue'] ) : '' );

			$row = array(
				'id'         => isset( $card['id'] ) ? sanitize_text_field( (string) $card['id'] ) : (string) ( count( $normalized ) + 1 ),
				'domain'     => $domain_key,
				'front'      => $front,
				'back'       => $back,
				'memory_cue' => $memory_cue,
			);

			if ( isset( $card['sort_order'] ) ) {
				$row['sort_order'] = (int) $card['sort_order'];
			}

			if ( ! empty( $card['meta'] ) && is_array( $card['meta'] ) ) {
				$row['meta'] = $card['meta'];
			}

			$normalized[] = $row;
		}

		usort(
			$normalized,
			static function ( $a, $b ) {
				$order = (int) ( $a['sort_order'] ?? 0 ) <=> (int) ( $b['sort_order'] ?? 0 );
				if ( 0 !== $order ) {
					return $order;
				}
				return strcmp( (string) ( $a['id'] ?? '' ), (string) ( $b['id'] ?? '' ) );
			}
		);

		return $normalized;
	}

	/**
	 * Attach card counts to domain rows for landing stats.
	 *
	 * @param array<int,array<string,string>>                           $cards      Cards.
	 * @param array<string,array{key:string,label:string,order:int}> $domain_map Domain map.
	 * @return array<int,array<string,mixed>>
	 */
	private static function build_domain_stats( array $cards, array $domain_map ) {
		$counts = array();
		foreach ( $cards as $card ) {
			$key = (string) ( $card['domain'] ?? 'general' );
			if ( ! isset( $counts[ $key ] ) ) {
				$counts[ $key ] = 0;
			}
			++$counts[ $key ];
		}

		$domains = array();
		foreach ( $domain_map as $key => $domain ) {
			$domains[] = array(
				'key'   => (string) $domain['key'],
				'label' => (string) $domain['label'],
				'order' => (int) $domain['order'],
				'count' => (int) ( $counts[ $key ] ?? 0 ),
			);
		}

		// Include domains inferred only from cards not listed in JSON domains array.
		foreach ( $counts as $key => $count ) {
			if ( isset( $domain_map[ $key ] ) ) {
				continue;
			}
			$domains[] = array(
				'key'   => $key,
				'label' => ucwords( str_replace( array( '-', '_' ), ' ', $key ) ),
				'order' => 999,
				'count' => (int) $count,
			);
		}

		usort(
			$domains,
			static function ( $a, $b ) {
				$order = (int) $a['order'] <=> (int) $b['order'];
				if ( 0 !== $order ) {
					return $order;
				}
				return strnatcasecmp( (string) $a['label'], (string) $b['label'] );
			}
		);

		return $domains;
	}

	/**
	 * Sanitize flashcard text while preserving intentional line breaks.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	private static function sanitize_card_text( $text ) {
		$text = (string) $text;
		if ( '' === $text ) {
			return '';
		}

		if ( function_exists( 'cta_lms_sanitize_utf8_text' ) ) {
			$text = cta_lms_sanitize_utf8_text( $text );
		}

		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		$text = preg_replace( '/<\s*br\s*\/?\s*>/i', "\n", $text );
		$text = preg_replace( '/<\/\s*p\s*>/i', "\n", $text );
		$text = wp_strip_all_tags( $text, false );

		$lines = explode( "\n", $text );
		$lines = array_map(
			static function ( $line ) {
				$line = preg_replace( '/[ \t]+/', ' ', (string) $line );
				return trim( (string) $line );
			},
			$lines
		);

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

<?php
/**
 * Inspect LMFT California Clinical Flashcard Study Center deck payload.
 *
 * Run: php scripts/test-lmft-flashcard-deck.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'CTA_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

function __( $text, $domain = null ) {
	unset( $domain );
	return $text;
}

function sanitize_key( $key ) {
	$key = strtolower( (string) $key );
	$key = preg_replace( '/[^a-z0-9_\-]+/', '-', $key );
	return trim( $key, '-' );
}

function sanitize_text_field( $str ) {
	return trim( strip_tags( (string) $str ) );
}

function sanitize_title( $title ) {
	return sanitize_key( (string) $title );
}

function wp_strip_all_tags( $text ) {
	return strip_tags( (string) $text );
}

class CTA_Flashcards {
	public static function get_deck_map() {
		return array(
			'lmft-california-clinical-exam-preparation' => 'assets/course-materials/lmft-clinical/study-tools/flashcards.json',
		);
	}
}

function apply_filters( $hook, $value ) {
	unset( $hook );
	return $value;
}

class CTA_Exam_Access {
	public static function is_exam_prep( $course ) {
		unset( $course );
		return true;
	}
}

require_once CTA_PLUGIN_DIR . 'includes/class-cta-exam-prep-flashcard-center.php';

$course = (object) array(
	'id'   => 1,
	'slug' => 'lmft-california-clinical-exam-preparation',
);

$deck = CTA_Exam_Prep_Flashcard_Center::get_deck_for_course( $course );

echo 'count=' . (int) ( $deck['count'] ?? 0 ) . PHP_EOL;
echo 'domains=' . count( (array) ( $deck['domains'] ?? array() ) ) . PHP_EOL;
echo 'has_content=' . ( ! empty( $deck['has_content'] ) ? 'yes' : 'no' ) . PHP_EOL;

$study_path = CTA_PLUGIN_DIR . 'assets/course-materials/lmft-clinical/study-tools/flashcard-study-center.json';
$legacy_path = CTA_PLUGIN_DIR . 'assets/course-materials/lmft-clinical/study-tools/flashcards.json';

$study = json_decode( (string) file_get_contents( $study_path ), true );
$legacy = json_decode( (string) file_get_contents( $legacy_path ), true );

echo 'study_center_cards=' . count( (array) ( $study['cards'] ?? array() ) ) . PHP_EOL;
echo 'legacy_cards=' . count( (array) ( $legacy['cards'] ?? array() ) ) . PHP_EOL;

if ( ! empty( $deck['cards'][0] ) ) {
	$first = $deck['cards'][0];
	echo 'sample_id=' . ( $first['id'] ?? '' ) . PHP_EOL;
	echo 'sample_domain=' . ( $first['domain'] ?? '' ) . PHP_EOL;
	echo 'sample_memory_cue=' . ( $first['memory_cue'] ?? '' ) . PHP_EOL;
}

if ( ! empty( $deck['domains'] ) ) {
	echo 'domain_labels=' . implode( ' | ', array_map(
		static function ( $d ) {
			return ( $d['label'] ?? '' ) . '(' . (int) ( $d['count'] ?? 0 ) . ')';
		},
		$deck['domains']
	) ) . PHP_EOL;
}

$legacy_slugs = CTA_Exam_Prep_Flashcard_Center::get_legacy_fallback_slugs();
echo 'legacy_fallback_enabled=' . ( in_array( 'lmft-california-clinical-exam-preparation', $legacy_slugs, true ) ? 'yes' : 'no' ) . PHP_EOL;

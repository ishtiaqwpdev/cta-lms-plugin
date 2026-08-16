<?php
/**
 * Validate LMFT Clinical Flashcard Study Center import (PROMPT 01, cards 1–30).
 *
 * Usage: php scripts/test-lmft-clinical-flashcard-import-01-30.php
 *
 * @package CTA_LMS
 */

$root = dirname( __DIR__ );

define( 'ABSPATH', $root . '/' );
define( 'CTA_PLUGIN_DIR', $root . '/' );

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]+/', '-', (string) $title ) );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text, $remove_breaks = false ) {
		unset( $remove_breaks );
		return strip_tags( (string) $text );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) {
		unset( $hook );
		return $value;
	}
}

class CTA_Exam_Access {
	public static function is_exam_prep( $course ) {
		unset( $course );
		return true;
	}
}

require_once CTA_PLUGIN_DIR . 'includes/class-cta-exam-prep-flashcard-center.php';

$pass = 0;
$fail = 0;

function assert_true( $cond, $msg ) {
	global $pass, $fail;
	if ( $cond ) {
		++$pass;
		echo "PASS: {$msg}\n";
		return;
	}
	++$fail;
	echo "FAIL: {$msg}\n";
}

$source_path = CTA_PLUGIN_DIR . 'scripts/_lmft-clinical-flashcards-prompt-01.json';
$deck_path   = CTA_PLUGIN_DIR . 'assets/course-materials/lmft-clinical/study-tools/flashcard-study-center.json';

assert_true( is_readable( $source_path ), 'Source export JSON exists' );
assert_true( is_readable( $deck_path ), 'Study Center deck JSON exists' );

$source = json_decode( (string) file_get_contents( $source_path ), true );
$stored = json_decode( (string) file_get_contents( $deck_path ), true );

assert_true( is_array( $source ) && ! empty( $source['cards'] ), 'Source JSON parses with cards' );
assert_true( is_array( $stored ) && ! empty( $stored['cards'] ), 'Deck JSON parses with cards' );
assert_true( 30 === count( $source['cards'] ), 'Source export has 30 cards' );
assert_true( count( $stored['cards'] ) >= 30, 'Deck JSON contains at least 30 cards' );
assert_true( 180 === (int) ( $stored['expected_total'] ?? 0 ), 'expected_total is 180' );
assert_true( (int) ( $stored['imported_through'] ?? 0 ) >= 30, 'imported_through is at least 30' );
assert_true( 'lmft-clinical' === (string) ( $stored['program'] ?? '' ), 'program is lmft-clinical' );

$expected_ids = array();
foreach ( $source['cards'] as $row ) {
	$expected_ids[] = (string) ( $row['Card ID'] ?? '' );
}

$stored_first_30 = array_slice( $stored['cards'], 0, 30 );
$stored_ids = array_map(
	static function ( $card ) {
		return (string) ( $card['id'] ?? '' );
	},
	$stored_first_30
);

assert_true( $expected_ids === $stored_ids, 'First 30 card IDs match export order (D1-001 through D1-030)' );

$sort_orders = array_map(
	static function ( $card ) {
		return (int) ( $card['sort_order'] ?? 0 );
	},
	$stored_first_30
);
assert_true( range( 1, 30 ) === $sort_orders, 'First 30 sort_order values are 1 through 30 in sequence' );

$course = (object) array(
	'id'   => 10,
	'slug' => 'lmft-california-clinical-exam-preparation',
);

$deck = CTA_Exam_Prep_Flashcard_Center::get_deck_for_course( $course );

assert_true( ! empty( $deck['has_content'] ), 'Loader reports has_content=true' );
assert_true( (int) ( $deck['count'] ?? 0 ) >= 30, 'Loader count is at least 30' );

$clinical_domain = null;
foreach ( (array) ( $deck['domains'] ?? array() ) as $domain_row ) {
	if ( 'clinical-evaluation' === (string) ( $domain_row['key'] ?? '' ) ) {
		$clinical_domain = $domain_row;
		break;
	}
}
assert_true( is_array( $clinical_domain ), 'Clinical Evaluation domain declared' );
assert_true( 'Clinical Evaluation' === ( $clinical_domain['label'] ?? '' ), 'Domain label is Clinical Evaluation' );
assert_true( (int) ( $clinical_domain['count'] ?? 0 ) >= 30, 'Clinical Evaluation domain count is at least 30' );

$legacy_slugs = CTA_Exam_Prep_Flashcard_Center::get_legacy_fallback_slugs();
assert_true(
	! in_array( 'lmft-california-clinical-exam-preparation', $legacy_slugs, true ),
	'LMFT Clinical is not on legacy flashcards.json fallback list'
);

$loader_ids = array_map(
	static function ( $card ) {
		return (string) ( $card['id'] ?? '' );
	},
	array_slice( (array) ( $deck['cards'] ?? array() ), 0, 30 )
);
assert_true( $expected_ids === $loader_ids, 'Loader preserves canonical sort order for first 30 cards' );

foreach ( $source['cards'] as $index => $row ) {
	$stored_card = $stored['cards'][ $index ] ?? array();
	$loader_card = $deck['cards'][ $index ] ?? array();

	assert_true(
		(string) ( $row['Front'] ?? '' ) === (string) ( $stored_card['front'] ?? '' ),
		'Front text preserved for ' . ( $row['Card ID'] ?? $index )
	);
	assert_true(
		(string) ( $row['Back'] ?? '' ) === (string) ( $stored_card['back'] ?? '' ),
		'Back text preserved for ' . ( $row['Card ID'] ?? $index )
	);
	assert_true(
		(string) ( $row['Memory Cue'] ?? '' ) === (string) ( $stored_card['memory_cue'] ?? '' ),
		'Memory Cue preserved for ' . ( $row['Card ID'] ?? $index )
	);
	assert_true(
		'clinical-evaluation' === (string) ( $stored_card['domain'] ?? '' ),
		'Domain key mapped for ' . ( $row['Card ID'] ?? $index )
	);

	$meta = isset( $stored_card['meta'] ) && is_array( $stored_card['meta'] ) ? $stored_card['meta'] : array();
	assert_true( (string) ( $row['Domain Code'] ?? '' ) === (string) ( $meta['domain_code'] ?? '' ), 'meta.domain_code for ' . ( $row['Card ID'] ?? $index ) );
	assert_true( (string) ( $row['Blueprint Section'] ?? '' ) === (string) ( $meta['blueprint_section'] ?? '' ), 'meta.blueprint_section for ' . ( $row['Card ID'] ?? $index ) );
	assert_true( (string) ( $row['Card Type'] ?? '' ) === (string) ( $meta['card_type'] ?? '' ), 'meta.card_type for ' . ( $row['Card ID'] ?? $index ) );
	assert_true( (string) ( $row['Concept'] ?? '' ) === (string) ( $meta['concept'] ?? '' ), 'meta.concept for ' . ( $row['Card ID'] ?? $index ) );
	assert_true( (string) ( $row['Tags'] ?? '' ) === (string) ( $meta['tags'] ?? '' ), 'meta.tags for ' . ( $row['Card ID'] ?? $index ) );
	assert_true( 'Learner' === (string) ( $meta['visibility'] ?? '' ), 'meta.visibility is Learner for ' . ( $row['Card ID'] ?? $index ) );
	assert_true( ! empty( $meta['active'] ), 'meta.active is true for ' . ( $row['Card ID'] ?? $index ) );
	assert_true( '1.0' === (string) ( $meta['content_version'] ?? '' ), 'meta.content_version is 1.0 for ' . ( $row['Card ID'] ?? $index ) );

	assert_true(
		(string) ( $stored_card['front'] ?? '' ) === (string) ( $loader_card['front'] ?? '' ),
		'Loader front matches stored for ' . ( $row['Card ID'] ?? $index )
	);
	assert_true(
		(int) ( $stored_card['sort_order'] ?? 0 ) === (int) ( $loader_card['sort_order'] ?? 0 ),
		'Loader sort_order matches stored for ' . ( $row['Card ID'] ?? $index )
	);
}

$first = $deck['cards'][0] ?? array();
assert_true( 'D1-001' === ( $first['id'] ?? '' ), 'First card id is D1-001' );
assert_true(
	strpos( (string) ( $first['front'] ?? '' ), 'At intake' ) === 0,
	'First card front begins with expected prompt'
);
assert_true(
	! empty( $first['memory_cue'] ),
	'First card includes memory cue in loader payload'
);

echo PHP_EOL;
echo "Results: {$pass} passed, {$fail} failed\n";

exit( $fail > 0 ? 1 : 0 );

<?php
/**
 * Validate LMFT Clinical Flashcard Study Center import (PROMPT 02, cards 31–60).
 *
 * Usage: php scripts/test-lmft-clinical-flashcard-import-31-60.php
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

$source_path = CTA_PLUGIN_DIR . 'scripts/_lmft-clinical-flashcards-prompt-02.json';
$deck_path   = CTA_PLUGIN_DIR . 'assets/course-materials/lmft-clinical/study-tools/flashcard-study-center.json';

assert_true( is_readable( $source_path ), 'Source export JSON exists' );
assert_true( is_readable( $deck_path ), 'Study Center deck JSON exists' );

$source = json_decode( (string) file_get_contents( $source_path ), true );
$stored = json_decode( (string) file_get_contents( $deck_path ), true );

assert_true( is_array( $source ) && ! empty( $source['cards'] ), 'Source JSON parses with cards' );
assert_true( is_array( $stored ) && ! empty( $stored['cards'] ), 'Deck JSON parses with cards' );
assert_true( 30 === count( $source['cards'] ), 'Source export has 30 cards' );
assert_true( count( $stored['cards'] ) >= 60, 'Deck JSON contains at least 60 cumulative cards' );
assert_true( 180 === (int) ( $stored['expected_total'] ?? 0 ), 'expected_total is 180' );
assert_true( (int) ( $stored['imported_through'] ?? 0 ) >= 60, 'imported_through is at least 60' );

$expected_ids = array();
foreach ( $source['cards'] as $row ) {
	$expected_ids[] = (string) ( $row['Card ID'] ?? '' );
}

$stored_by_id = array();
foreach ( $stored['cards'] as $card ) {
	$stored_by_id[ (string) ( $card['id'] ?? '' ) ] = $card;
}

$chunk_ids = array_map(
	static function ( $card ) {
		return (string) ( $card['id'] ?? '' );
	},
	array_slice( $stored['cards'], 30, 30 )
);
assert_true( $expected_ids === $chunk_ids, 'Cards 31–60 match export IDs (D1-031 through D2-011)' );

$sort_orders = array_map(
	static function ( $card ) {
		return (int) ( $card['sort_order'] ?? 0 );
	},
	array_slice( $stored['cards'], 0, 60 )
);
assert_true( range( 1, 60 ) === $sort_orders, 'First 60 sort_order values are 1 through 60 in sequence' );

$course = (object) array(
	'id'   => 10,
	'slug' => 'lmft-california-clinical-exam-preparation',
);

$deck = CTA_Exam_Prep_Flashcard_Center::get_deck_for_course( $course );

assert_true( ! empty( $deck['has_content'] ), 'Loader reports has_content=true' );
assert_true( (int) ( $deck['count'] ?? 0 ) >= 60, 'Loader count is at least 60' );
assert_true( count( (array) ( $deck['domains'] ?? array() ) ) >= 2, 'At least two domains declared' );

$domain_keys = array_map(
	static function ( $d ) {
		return (string) ( $d['key'] ?? '' );
	},
	(array) ( $deck['domains'] ?? array() )
);
assert_true( in_array( 'clinical-evaluation', $domain_keys, true ), 'Clinical Evaluation domain present' );
assert_true(
	in_array( 'developing-a-diagnostic-impression', $domain_keys, true ),
	'Developing a Diagnostic Impression domain present'
);

$domain_counts = array();
foreach ( (array) ( $deck['domains'] ?? array() ) as $domain ) {
	$domain_counts[ (string) ( $domain['key'] ?? '' ) ] = (int) ( $domain['count'] ?? 0 );
}
assert_true( ( $domain_counts['clinical-evaluation'] ?? 0 ) >= 49, 'Clinical Evaluation has at least 49 cards' );
assert_true(
	( $domain_counts['developing-a-diagnostic-impression'] ?? 0 ) >= 11,
	'Developing a Diagnostic Impression has at least 11 cards'
);

$loader_ids = array_map(
	static function ( $card ) {
		return (string) ( $card['id'] ?? '' );
	},
	(array) ( $deck['cards'] ?? array() )
);
assert_true( 'D1-001' === $loader_ids[0], 'First card remains D1-001 after merge' );
assert_true( 'D2-011' === $loader_ids[59], 'Sixtieth card is D2-011' );

foreach ( $source['cards'] as $row ) {
	$id          = (string) ( $row['Card ID'] ?? '' );
	$stored_card = $stored_by_id[ $id ] ?? array();

	assert_true(
		(string) ( $row['Front'] ?? '' ) === (string) ( $stored_card['front'] ?? '' ),
		'Front text preserved for ' . $id
	);
	assert_true(
		(string) ( $row['Back'] ?? '' ) === (string) ( $stored_card['back'] ?? '' ),
		'Back text preserved for ' . $id
	);
	assert_true(
		(string) ( $row['Memory Cue'] ?? '' ) === (string) ( $stored_card['memory_cue'] ?? '' ),
		'Memory Cue preserved for ' . $id
	);

	$expected_domain = 'D1' === (string) ( $row['Domain Code'] ?? '' )
		? 'clinical-evaluation'
		: 'developing-a-diagnostic-impression';
	assert_true(
		$expected_domain === (string) ( $stored_card['domain'] ?? '' ),
		'Domain key mapped for ' . $id
	);

	$meta = isset( $stored_card['meta'] ) && is_array( $stored_card['meta'] ) ? $stored_card['meta'] : array();
	assert_true( (string) ( $row['Domain Code'] ?? '' ) === (string) ( $meta['domain_code'] ?? '' ), 'meta.domain_code for ' . $id );
	assert_true( (string) ( $row['Blueprint Section'] ?? '' ) === (string) ( $meta['blueprint_section'] ?? '' ), 'meta.blueprint_section for ' . $id );
	assert_true( (string) ( $row['Card Type'] ?? '' ) === (string) ( $meta['card_type'] ?? '' ), 'meta.card_type for ' . $id );
	assert_true( (string) ( $row['Concept'] ?? '' ) === (string) ( $meta['concept'] ?? '' ), 'meta.concept for ' . $id );
	assert_true( (string) ( $row['Tags'] ?? '' ) === (string) ( $meta['tags'] ?? '' ), 'meta.tags for ' . $id );
	assert_true( 'Learner' === (string) ( $meta['visibility'] ?? '' ), 'meta.visibility is Learner for ' . $id );
	assert_true( ! empty( $meta['active'] ), 'meta.active is true for ' . $id );
	assert_true( '1.0' === (string) ( $meta['content_version'] ?? '' ), 'meta.content_version is 1.0 for ' . $id );
}

$first_new = $stored_by_id['D1-031'] ?? array();
assert_true(
	strpos( (string) ( $first_new['front'] ?? '' ), 'counting the number of people' ) !== false,
	'Card D1-031 front preserved'
);

$first_d2 = $stored_by_id['D2-001'] ?? array();
assert_true(
	strpos( (string) ( $first_d2['front'] ?? '' ), 'bipolar-spectrum differential' ) !== false,
	'Card D2-001 front preserved'
);
assert_true(
	'developing-a-diagnostic-impression' === (string) ( $first_d2['domain'] ?? '' ),
	'Card D2-001 mapped to Developing a Diagnostic Impression domain'
);

echo PHP_EOL;
echo "Results: {$pass} passed, {$fail} failed\n";

exit( $fail > 0 ? 1 : 0 );

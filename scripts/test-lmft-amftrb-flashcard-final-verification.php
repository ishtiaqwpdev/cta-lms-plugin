<?php
/**
 * Verification for LMFT AMFTRB Flashcard Study Center cutover (v1.0.274).
 *
 * Usage: php scripts/test-lmft-amftrb-flashcard-final-verification.php
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

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		unset( $option );
		return $default;
	}
}

class CTA_Exam_Access {
	public static function is_exam_prep( $course ) {
		unset( $course );
		return true;
	}
}

require_once CTA_PLUGIN_DIR . 'includes/class-cta-lmft-amftrb-legacy-flashcard-archive.php';
require_once CTA_PLUGIN_DIR . 'includes/class-cta-exam-prep-flashcard-center.php';
require_once CTA_PLUGIN_DIR . 'includes/class-cta-flashcards.php';

$pass = 0;
$fail = 0;
$warn = 0;

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

function assert_warn( $cond, $msg ) {
	global $pass, $warn;
	if ( $cond ) {
		++$pass;
		echo "PASS: {$msg}\n";
		return;
	}
	++$warn;
	echo "WARN: {$msg}\n";
}

$amftrb_course = (object) array(
	'id'   => 15,
	'slug' => 'lmft-amftrb-national-exam-preparation',
);

$clinical_course = (object) array(
	'id'   => 10,
	'slug' => 'lmft-california-clinical-exam-preparation',
);

$lpcc_course = (object) array(
	'id'   => 50,
	'slug' => 'lpcc-ncmhce-exam-preparation',
);

$archived_legacy = CTA_PLUGIN_DIR . 'assets/course-materials/lmft-amftrb/study-tools/_archived/lmft-amftrb-legacy-flashcards-v1.0-120.json';
$active_legacy   = CTA_PLUGIN_DIR . 'assets/course-materials/lmft-amftrb/study-tools/flashcards.json';
$study_center    = CTA_PLUGIN_DIR . 'assets/course-materials/lmft-amftrb/study-tools/flashcard-study-center.json';

echo "--- 1) Legacy deck archived ---\n";
assert_true( is_readable( $archived_legacy ), 'Legacy 120-card JSON preserved under _archived/' );
assert_true( ! is_readable( $active_legacy ), 'Active legacy flashcards.json removed from learner path' );

$legacy_raw  = is_readable( $archived_legacy ) ? file_get_contents( $archived_legacy ) : '';
$legacy_data = json_decode( (string) $legacy_raw, true );
assert_true(
	is_array( $legacy_data ) && 120 === count( $legacy_data['cards'] ?? array() ),
	'Archived legacy deck contains 120 cards'
);

$malformed_domain_keys = 0;
if ( is_array( $legacy_data['cards'] ?? null ) ) {
	foreach ( $legacy_data['cards'] as $card ) {
		$tag = (string) ( $card['tag'] ?? '' );
		if ( preg_match( '/^Workbook\s+\d+/i', $tag ) ) {
			++$malformed_domain_keys;
		}
	}
}
assert_true( 120 === $malformed_domain_keys, 'Legacy deck used workbook import tags (root cause of 120-domain bug)' );

echo "\n--- 2) Legacy fallback + CTA_Flashcards blocks ---\n";
assert_true(
	! in_array( 'lmft-amftrb-national-exam-preparation', CTA_Exam_Prep_Flashcard_Center::get_legacy_fallback_slugs(), true ),
	'AMFTRB not in Study Center legacy fallback slugs'
);
assert_true(
	CTA_Lmft_Amftrb_Legacy_Flashcard_Archive::blocks_learner_legacy_deck( $amftrb_course ),
	'Legacy CTA_Flashcards deck blocked for AMFTRB learners'
);
assert_true(
	null === CTA_Flashcards::get_deck_for_course( $amftrb_course ),
	'CTA_Flashcards returns null for AMFTRB (no legacy deck served)'
);
assert_true(
	! isset( CTA_Flashcards::get_deck_map()['lmft-amftrb-national-exam-preparation'] ),
	'AMFTRB removed from CTA_Flashcards deck map'
);

echo "\n--- 3) Study Center deck state ---\n";
$sc_raw  = is_readable( $study_center ) ? file_get_contents( $study_center ) : '';
$sc_data = json_decode( (string) $sc_raw, true );
$sc_domains = is_array( $sc_data['domains'] ?? null ) ? $sc_data['domains'] : array();
$sc_cards   = is_array( $sc_data['cards'] ?? null ) ? $sc_data['cards'] : array();

assert_true( 6 === count( $sc_domains ), 'Study Center declares 6 official AMFTRB domains' );
assert_warn(
	180 === count( $sc_cards ),
	'Study Center has 180 cards (CONTENT GAP until approved xlsx is imported)'
);
assert_true(
	! CTA_Exam_Prep_Flashcard_Center::study_center_deck_is_live( 'lmft-amftrb' ),
	'study_center_deck_is_live=false until 180-card deck imported'
);

$amftrb_deck = CTA_Exam_Prep_Flashcard_Center::get_deck_for_course( $amftrb_course );
assert_true(
	empty( $amftrb_deck['has_content'] ) || 0 === (int) ( $amftrb_deck['count'] ?? 0 ),
	'Learner AMFTRB deck shows empty state (not broken 120-card legacy)'
);
assert_true(
	count( $amftrb_deck['domains'] ?? array() ) <= 6,
	'Learner domain filters are not malformed workbook strings'
);

echo "\n--- 4) Regression — other programs untouched ---\n";
$clinical_deck = CTA_Exam_Prep_Flashcard_Center::get_deck_for_course( $clinical_course );
assert_true(
	180 === (int) ( $clinical_deck['count'] ?? 0 ),
	'LMFT California Clinical still serves 180-card Study Center deck'
);
assert_true(
	6 === count( $clinical_deck['domains'] ?? array() ),
	'LMFT California Clinical still has 6 blueprint domains'
);

$lpcc_deck = CTA_Exam_Prep_Flashcard_Center::get_deck_for_course( $lpcc_course );
assert_true(
	180 === (int) ( $lpcc_deck['count'] ?? 0 ),
	'LPCC NCMHCE still serves 180-card Study Center deck'
);

echo "\n--- 5) Flip reveal + navigation markup (shared fix) ---\n";
$template = (string) file_get_contents( CTA_PLUGIN_DIR . 'templates/partials/exam-prep-flashcard-center.php' );
$main_js  = (string) file_get_contents( CTA_PLUGIN_DIR . 'assets/js/main.js' );
assert_true(
	false !== strpos( $template, 'data-cta-fsc-answer' ),
	'Template uses scoped answer selector for flip reveal'
);
assert_true(
	false !== strpos( $main_js, 'data-cta-fsc-answer' ) && false !== strpos( $main_js, 'data-cta-fsc-nav-back' ),
	'main.js implements flip reveal and navigation hooks'
);
assert_true(
	false !== strpos( $main_js, 'shuffle' ) || false !== strpos( $main_js, 'Shuffle' ),
	'Shuffle control present in main.js'
);

echo "\n--- 6) Build/import tooling ready ---\n";
assert_true(
	is_readable( CTA_PLUGIN_DIR . 'scripts/build-lmft-amftrb-flashcard-study-center.php' ),
	'AMFTRB xlsx build script exists for approved 180-card import'
);
assert_true(
	false !== strpos( (string) file_get_contents( CTA_PLUGIN_DIR . 'includes/class-cta-flashcards.php' ), 'CTA_Lmft_Amftrb_Legacy_Flashcard_Archive::blocks_learner_legacy_deck' ),
	'Flashcards class wired to AMFTRB legacy archive'
);

echo "\n=== SUMMARY: {$pass} passed, {$fail} failed, {$warn} warnings ===\n";

if ( $fail > 0 ) {
	exit( 1 );
}

exit( 0 );

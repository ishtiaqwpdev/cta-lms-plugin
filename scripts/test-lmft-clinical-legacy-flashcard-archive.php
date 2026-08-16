<?php
/**
 * Validate LMFT Clinical legacy flashcard deck archive (PROMPT 00).
 *
 * Usage: php scripts/test-lmft-clinical-legacy-flashcard-archive.php
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

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
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

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value, $autoload = null ) {
		unset( $autoload );
		$GLOBALS['_test_options'][ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		return $GLOBALS['_test_options'][ $option ] ?? $default;
	}
}

$GLOBALS['_test_options'] = array();
$pass = 0;
$fail = 0;

function assert_true( $cond, $msg ) {
	global $pass, $fail;
	if ( $cond ) {
		++$pass;
		echo "PASS: {$msg}\n";
	} else {
		++$fail;
		echo "FAIL: {$msg}\n";
	}
}

require_once $root . '/includes/class-cta-exam-access.php';
require_once $root . '/includes/class-cta-lmft-clinical-legacy-forms-archive.php';
require_once $root . '/includes/class-cta-lmft-clinical-legacy-flashcard-archive.php';
require_once $root . '/includes/class-cta-flashcards.php';
require_once $root . '/includes/class-cta-exam-prep-flashcard-center.php';

$course = (object) array(
	'id'           => 10,
	'slug'         => 'lmft-california-clinical-exam-preparation',
	'product_type' => 'exam_prep',
);

$active_json   = CTA_Lmft_Clinical_Legacy_Flashcard_Archive::active_legacy_json_absolute_path();
$archived_json = CTA_Lmft_Clinical_Legacy_Flashcard_Archive::archived_json_absolute_path();
$lpcc_json     = CTA_PLUGIN_DIR . 'assets/course-materials/lpcc-law-ethics/study-tools/flashcards.json';
$flashcards    = file_get_contents( $root . '/includes/class-cta-flashcards.php' );
$materials     = file_get_contents( $root . '/includes/class-cta-course-materials.php' );
$sync          = file_get_contents( $root . '/includes/class-cta-lmft-clinical-sync.php' );
$lms           = file_get_contents( $root . '/cta-lms.php' );

echo "=== LMFT Clinical Legacy Flashcard Archive (PROMPT 00) ===\n\n";

assert_true(
	! is_readable( $active_json ),
	'Active lmft-clinical/study-tools/flashcards.json removed from learner path'
);
assert_true(
	is_readable( $archived_json ),
	'Legacy deck preserved at study-tools/_archived/lmft-clinical-legacy-flashcards-v1.0-132.json'
);

$archived_data = json_decode( (string) file_get_contents( $archived_json ), true );
$archived_count = is_array( $archived_data ) && ! empty( $archived_data['cards'] )
	? count( $archived_data['cards'] )
	: 0;
assert_true(
	132 === $archived_count,
	'Archived LMFT Clinical legacy deck contains 132 cards (not the 807-card LPCC library)'
);

$lpcc_data = json_decode( (string) file_get_contents( $lpcc_json ), true );
$lpcc_count = is_array( $lpcc_data ) && ! empty( $lpcc_data['cards'] )
	? count( $lpcc_data['cards'] )
	: (int) ( $lpcc_data['count'] ?? 0 );
assert_true(
	807 === $lpcc_count,
	'LPCC Law & Ethics 807-card flashcards.json library remains untouched'
);

assert_true(
	false === strpos( $flashcards, "'lmft-california-clinical-exam-preparation'" ),
	'CTA_Flashcards deck map no longer routes LMFT Clinical to legacy flashcards.json'
);
assert_true(
	false !== strpos( $flashcards, 'CTA_Lmft_Clinical_Legacy_Flashcard_Archive::blocks_learner_legacy_deck' ),
	'CTA_Flashcards blocks archived LMFT Clinical legacy deck'
);

// Simulate archived state for runtime deck resolution (upgrade hook sets option on deploy).
update_option(
	CTA_Lmft_Clinical_Legacy_Flashcard_Archive::ARCHIVE_OPTION,
	array(
		'archived'  => true,
		'course_id' => 10,
	),
	false
);

$legacy_deck = CTA_Flashcards::get_deck_for_course( $course );
assert_true(
	null === $legacy_deck,
	'CTA_Flashcards::get_deck_for_course returns null for LMFT Clinical after archive'
);

$fallback_slugs = CTA_Exam_Prep_Flashcard_Center::get_legacy_fallback_slugs();
assert_true(
	! in_array( 'lmft-california-clinical-exam-preparation', $fallback_slugs, true ),
	'Flashcard Study Center does not fall back to legacy flashcards.json for LMFT Clinical'
);

$study_center = CTA_Exam_Prep_Flashcard_Center::get_deck_for_course( $course );
assert_true(
	! empty( $study_center['has_content'] ),
	'Flashcard Study Center serves the new blueprint-aligned deck (not legacy flashcards.json)'
);
assert_true(
	180 === (int) ( $study_center['count'] ?? 0 ),
	'Flashcard Study Center has the full 180-card blueprint-aligned deck'
);

assert_true(
	CTA_Lmft_Clinical_Legacy_Flashcard_Archive::matches_legacy_flashcard_resource(
		(object) array(
			'file_path' => 'study-tools/CTA_LMFT_Clinical_Exam_Preparation_Flashcard_Collection_v1.0.docx',
			'title'     => 'Clinical Exam Preparation Flashcard Collection',
		)
	),
	'Legacy printable flashcard DOCX is identified for archive'
);

assert_true(
	false !== strpos( $materials, 'CTA_Lmft_Clinical_Legacy_Flashcard_Archive::is_archived_resource' ),
	'Course materials hide archived legacy flashcard downloads'
);
assert_true(
	false !== strpos( $materials, 'lmft-clinical/study-tools/flashcards.json' ),
	'Active legacy JSON path blocked in admin-restricted source paths'
);
assert_true(
	false !== strpos( $materials, 'study-tools/_archived/' ),
	'Archived legacy JSON folder blocked from learner downloads'
);

assert_true(
	false !== strpos( $sync, 'CTA_Lmft_Clinical_Legacy_Flashcard_Archive::resource_path_is_legacy_flashcard' ),
	'LMFT Clinical sync skips re-attaching archived legacy flashcard DOCX'
);

assert_true(
	false !== strpos( $lms, 'CTA_Lmft_Clinical_Legacy_Flashcard_Archive::archive_legacy_flashcards' ),
	'Upgrade hook archives legacy LMFT Clinical flashcards on v1.0.248'
);
assert_true(
	false !== strpos( $lms, "'1.0.248'" ),
	'Plugin version bumped to 1.0.248'
);

echo "\n=== Summary ===\n";
echo "Passed: {$pass}\n";
echo "Failed: {$fail}\n";

exit( $fail > 0 ? 1 : 0 );

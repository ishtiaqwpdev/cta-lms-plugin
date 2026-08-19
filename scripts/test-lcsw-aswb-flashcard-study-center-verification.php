<?php
/**
 * Verify LCSW ASWB Clinical Flashcard Study Center state (Step 1 / Step 3).
 *
 * Usage: php scripts/test-lcsw-aswb-flashcard-study-center-verification.php
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

require_once CTA_PLUGIN_DIR . 'includes/class-cta-flashcards.php';
require_once CTA_PLUGIN_DIR . 'includes/class-cta-exam-prep-flashcard-center.php';
require_once CTA_PLUGIN_DIR . 'includes/class-cta-lcsw-aswb-legacy-flashcard-archive.php';

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

$slug   = 'lcsw-aswb-clinical-exam-preparation';
$course = (object) array(
	'id'           => 11,
	'slug'         => $slug,
	'product_type' => 'exam_prep',
);

$study_center_path = $root . '/assets/course-materials/lcsw-aswb/study-tools/flashcard-study-center.json';
$legacy_path       = $root . '/assets/course-materials/lcsw-aswb/study-tools/flashcards.json';
$lmft_path         = $root . '/assets/course-materials/lmft-clinical/study-tools/flashcard-study-center.json';

$study_raw = is_readable( $study_center_path ) ? json_decode( (string) file_get_contents( $study_center_path ), true ) : null;
$legacy_raw = is_readable( $legacy_path ) ? json_decode( (string) file_get_contents( $legacy_path ), true ) : null;
$lmft_raw   = is_readable( $lmft_path ) ? json_decode( (string) file_get_contents( $lmft_path ), true ) : null;

$study_count = is_array( $study_raw ) && ! empty( $study_raw['cards'] ) ? count( $study_raw['cards'] ) : 0;
$legacy_count = is_array( $legacy_raw ) && ! empty( $legacy_raw['cards'] ) ? count( $legacy_raw['cards'] ) : 0;
$lmft_count   = is_array( $lmft_raw ) && ! empty( $lmft_raw['cards'] ) ? count( $lmft_raw['cards'] ) : 0;

echo "=== LCSW ASWB Flashcard Study Center verification ===\n\n";

assert_true( $study_count === 0, 'Study Center JSON is empty (approved 180-card deck not imported yet)' );
assert_true( $legacy_count === 132, 'Legacy flashcards.json has 132 cards' );

$legacy_tags = array();
if ( is_array( $legacy_raw['cards'] ?? null ) ) {
	foreach ( $legacy_raw['cards'] as $card ) {
		$tag = (string) ( $card['tag'] ?? '' );
		if ( ! isset( $legacy_tags[ $tag ] ) ) {
			$legacy_tags[ $tag ] = 0;
		}
		++$legacy_tags[ $tag ];
	}
}

assert_true(
	isset( $legacy_tags['LCSW  •  CORE'] ) || isset( $legacy_tags['LCSW • CORE'] ),
	'Legacy deck uses LCSWCORE workbook taxonomy'
);

$deck = CTA_Exam_Prep_Flashcard_Center::get_deck_for_course( $course );
assert_true( (int) ( $deck['count'] ?? 0 ) === 132, 'Learner deck loader currently serves 132 legacy cards via fallback' );
assert_true( ! CTA_Exam_Prep_Flashcard_Center::study_center_deck_is_live( 'lcsw-aswb' ), 'Study Center deck is not live yet' );

$fallback = CTA_Exam_Prep_Flashcard_Center::get_legacy_fallback_slugs();
assert_true(
	in_array( $slug, $fallback, true ),
	'LCSW slug remains in legacy fallback until 180-card deck is imported'
);

$empty_backs = 0;
foreach ( (array) ( $deck['cards'] ?? array() ) as $card ) {
	if ( '' === trim( (string) ( $card['back'] ?? '' ) ) ) {
		++$empty_backs;
	}
}
assert_true( 0 === $empty_backs, 'Normalized legacy cards all have populated back fields (blank reveal was a UI selector bug)' );

$lmft_course = (object) array(
	'id'           => 10,
	'slug'         => 'lmft-california-clinical-exam-preparation',
	'product_type' => 'exam_prep',
);
$lmft_deck = CTA_Exam_Prep_Flashcard_Center::get_deck_for_course( $lmft_course );
assert_true( (int) ( $lmft_deck['count'] ?? 0 ) === 180, 'LMFT Clinical deck unchanged at 180 cards' );
assert_true( $lmft_count === 180, 'LMFT Clinical JSON source still has 180 cards' );

$template = (string) file_get_contents( $root . '/templates/partials/exam-prep-flashcard-center.php' );
assert_true(
	false !== strpos( $template, 'data-cta-fsc-answer' ),
	'Template uses dedicated data-cta-fsc-answer for flip reveal text'
);
assert_true(
	false !== strpos( $template, 'data-cta-fsc-nav-back' ),
	'Template uses data-cta-fsc-nav-back for toolbar navigation (no selector collision)'
);

$main_js = (string) file_get_contents( $root . '/assets/js/main.js' );
assert_true(
	false !== strpos( $main_js, 'flipBtn.querySelector("[data-cta-fsc-answer]")' ),
	'Study Mode JS scopes answer element inside flip trigger'
);

echo "\nSummary: {$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );

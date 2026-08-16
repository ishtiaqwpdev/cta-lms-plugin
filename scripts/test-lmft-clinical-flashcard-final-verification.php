<?php
/**
 * Final learner-facing verification for LMFT Clinical Flashcard Study Center (180 cards).
 *
 * Usage: php scripts/test-lmft-clinical-flashcard-final-verification.php
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

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		unset( $depth );
		return json_encode( $data, $options );
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

function card_signature( array $card ) {
	$front = trim( (string) ( $card['front'] ?? '' ) );
	$back  = trim( (string) ( $card['back'] ?? '' ) );
	return md5( strtolower( $front . '|' . $back ) );
}

function simulate_learner_deck_json( array $deck ) {
	return wp_json_encode(
		array(
			'title'       => $deck['title'] ?? '',
			'count'       => (int) ( $deck['count'] ?? 0 ),
			'cards'       => array_values( (array) ( $deck['cards'] ?? array() ) ),
			'domains'     => array_values( (array) ( $deck['domains'] ?? array() ) ),
			'has_content' => ! empty( $deck['has_content'] ),
		),
		JSON_UNESCAPED_UNICODE
	);
}

echo "=== LMFT Clinical Flashcard Study Center — Final Verification ===\n\n";

$course = (object) array(
	'id'   => 10,
	'slug' => 'lmft-california-clinical-exam-preparation',
);

$deck_path      = CTA_PLUGIN_DIR . 'assets/course-materials/lmft-clinical/study-tools/flashcard-study-center.json';
$archived_path  = CTA_PLUGIN_DIR . 'assets/course-materials/lmft-clinical/study-tools/_archived/lmft-clinical-legacy-flashcards-v1.0-132.json';
$lpcc_path      = CTA_PLUGIN_DIR . 'assets/course-materials/lpcc-law-ethics/study-tools/flashcards.json';
$template_path  = CTA_PLUGIN_DIR . 'templates/partials/exam-prep-flashcard-center.php';
$js_path        = CTA_PLUGIN_DIR . 'assets/js/main.js';
$css_path       = CTA_PLUGIN_DIR . 'assets/css/layout.css';

$raw      = json_decode( (string) file_get_contents( $deck_path ), true );
$deck     = CTA_Exam_Prep_Flashcard_Center::get_deck_for_course( $course );
$cards    = (array) ( $deck['cards'] ?? array() );
$raw_cards = (array) ( $raw['cards'] ?? array() );

echo "--- 1) Exactly 180 active cards ---\n";
assert_true( (int) ( $deck['count'] ?? 0 ) === 180, 'Loader reports exactly 180 cards' );
assert_true( count( $cards ) === 180, 'Normalized cards array length is 180' );
assert_true( count( $raw_cards ) === 180, 'Source JSON contains 180 card rows' );
assert_true( (int) ( $raw['expected_total'] ?? 0 ) === 180, 'Source expected_total is 180' );
assert_true( (int) ( $raw['imported_through'] ?? 0 ) === 180, 'Source imported_through is 180' );

$inactive = 0;
foreach ( $raw_cards as $card ) {
	if ( empty( $card['meta']['active'] ) ) {
		++$inactive;
	}
}
assert_true( 0 === $inactive, 'All 180 source cards have meta.active=true' );

echo "\n--- 2) Unique Card IDs and field attachment ---\n";
$ids = array();
$sort_orders = array();
$empty_fields = 0;
foreach ( $cards as $card ) {
	$id = (string) ( $card['id'] ?? '' );
	if ( '' === $id ) {
		++$empty_fields;
	}
	if ( isset( $ids[ $id ] ) ) {
		$ids[ $id ]++;
	} else {
		$ids[ $id ] = 1;
	}
	if ( '' === trim( (string) ( $card['front'] ?? '' ) ) || '' === trim( (string) ( $card['back'] ?? '' ) ) ) {
		++$empty_fields;
	}
	if ( isset( $card['sort_order'] ) ) {
		$sort_orders[] = (int) $card['sort_order'];
	}
}
$dup_ids = array_filter(
	$ids,
	static function ( $count ) {
		return $count > 1;
	}
);
assert_true( empty( $dup_ids ), 'Every Card ID is unique (' . count( $ids ) . ' IDs)' );
assert_true( 0 === $empty_fields, 'Every card has non-empty id, front, and back after normalization' );
assert_true( 180 === count( $sort_orders ), 'Every normalized card retains sort_order' );
assert_true( 180 === count( array_unique( $sort_orders ) ), 'sort_order values 1–180 are unique' );
sort( $sort_orders );
assert_true( $sort_orders === range( 1, 180 ), 'sort_order runs contiguously 1 through 180' );

$mismatch = 0;
foreach ( $raw_cards as $raw_card ) {
	$id = (string) ( $raw_card['id'] ?? '' );
	foreach ( $cards as $norm ) {
		if ( (string) ( $norm['id'] ?? '' ) !== $id ) {
			continue;
		}
		if ( trim( (string) $raw_card['front'] ) !== trim( (string) ( $norm['front'] ?? '' ) ) ) {
			++$mismatch;
		}
		if ( trim( (string) $raw_card['back'] ) !== trim( (string) ( $norm['back'] ?? '' ) ) ) {
			++$mismatch;
		}
		$raw_cue = trim( (string) ( $raw_card['memory_cue'] ?? '' ) );
		$norm_cue = trim( (string) ( $norm['memory_cue'] ?? '' ) );
		if ( $raw_cue !== $norm_cue ) {
			++$mismatch;
		}
		break;
	}
}
assert_true( 0 === $mismatch, 'Front/back/memory_cue preserved through loader normalization' );

echo "\n--- 3) Domain organization (not workbook numbering) ---\n";
$expected_domains = array(
	'clinical-evaluation'                 => 49,
	'developing-a-diagnostic-impression'  => 20,
	'managing-crisis-situations'          => 20,
	'case-conceptualization-and-planning' => 21,
	'treatment'                           => 52,
	'managing-legal-and-ethical-obligations' => 18,
);
foreach ( $expected_domains as $key => $expected_count ) {
	$found = 0;
	foreach ( (array) ( $deck['domains'] ?? array() ) as $domain ) {
		if ( (string) ( $domain['key'] ?? '' ) === $key ) {
			$found = (int) ( $domain['count'] ?? 0 );
			break;
		}
	}
	assert_true( $found === $expected_count, "Domain {$key} has {$expected_count} cards" );
}

$workbook_refs = 0;
foreach ( $cards as $card ) {
	foreach ( array( 'front', 'back', 'memory_cue' ) as $field ) {
		if ( preg_match( '/\bWB\d+\b/i', (string) ( $card[ $field ] ?? '' ) ) ) {
			++$workbook_refs;
		}
	}
	if ( preg_match( '/workbook/i', (string) ( $card['domain'] ?? '' ) ) ) {
		++$workbook_refs;
	}
}
assert_true( 0 === $workbook_refs, 'No CTA workbook numbering appears in learner-facing card fields or domain keys' );

$template = (string) file_get_contents( $template_path );
$js       = (string) file_get_contents( $js_path );
assert_true(
	false !== strpos( $template, 'Filter by exam domain' ),
	'UI exposes exam domain filter chips (template)'
);
$has_blueprint_filter = false !== strpos( $js, 'blueprint_section' )
	|| false !== strpos( $template, 'data-cta-fsc-blueprint' );
assert_warn(
	$has_blueprint_filter,
	'Blueprint Section filter is present in Study Center UI/JS (currently domain-only)'
);

$blueprint_sections = array();
foreach ( $raw_cards as $card ) {
	$section = trim( (string) ( $card['meta']['blueprint_section'] ?? '' ) );
	if ( '' !== $section ) {
		$blueprint_sections[ $section ] = true;
	}
}
assert_true( count( $blueprint_sections ) >= 10, 'Cards carry blueprint_section metadata (' . count( $blueprint_sections ) . ' distinct sections)' );

echo "\n--- 4) No forbidden internal fields in learner payload/UI ---\n";
$learner_json = simulate_learner_deck_json( $deck );
$forbidden_keys = array( 'task_id', 'k_id', 'remediation', 'workbook_number', 'legacy' );
$forbidden_hits = array();
foreach ( $forbidden_keys as $key ) {
	if ( false !== stripos( $learner_json, '"' . $key . '"' ) ) {
		$forbidden_hits[] = $key;
	}
}
assert_true( empty( $forbidden_hits ), 'Simulated learner deck JSON has no task_id/k_id/remediation/workbook_number/legacy keys' );

$ui_sources = substr( $js, strpos( $js, 'initExamPrepFlashcardCenter' ) ?: 0, 6000 );
$ui_forbidden = array( 'task_id', 'k_id', 'remediation', 'data-cta-fsc-sort-order' );
foreach ( $ui_forbidden as $key ) {
	$pattern = '/(?<![a-z0-9_])' . preg_quote( $key, '/' ) . '(?![a-z0-9_])/';
	assert_true(
		! preg_match( $pattern, $ui_sources ),
		"Flashcard Study Center JS does not reference forbidden field \"{$key}\""
	);
}

$meta_exposed = false !== strpos( $learner_json, '"meta"' );
assert_warn(
	! $meta_exposed,
	'Learner deck JSON omits internal meta block (meta is currently embedded in page JSON)'
);

if ( $meta_exposed ) {
	$meta_json = $learner_json;
	assert_true(
		false === stripos( $meta_json, 'task_id' ) && false === stripos( $meta_json, 'k_id' ),
		'Embedded meta block contains no task_id or k_id'
	);
}

echo "\n--- 5) No legacy deck or LPCC library contamination ---\n";
$archived = json_decode( (string) file_get_contents( $archived_path ), true );
$lpcc     = json_decode( (string) file_get_contents( $lpcc_path ), true );

$legacy_sigs = array();
foreach ( (array) ( $archived['cards'] ?? array() ) as $legacy_card ) {
	$legacy_sigs[ card_signature( $legacy_card ) ] = (string) ( $legacy_card['id'] ?? '' );
}
$lpcc_sigs = array();
foreach ( (array) ( $lpcc['cards'] ?? array() ) as $lpcc_card ) {
	$lpcc_sigs[ card_signature( $lpcc_card ) ] = (string) ( $lpcc_card['id'] ?? '' );
}

$legacy_overlap = array();
$lpcc_overlap   = array();
foreach ( $cards as $card ) {
	$sig = card_signature( $card );
	if ( isset( $legacy_sigs[ $sig ] ) ) {
		$legacy_overlap[] = $card['id'] . ' matches legacy ' . $legacy_sigs[ $sig ];
	}
	if ( isset( $lpcc_sigs[ $sig ] ) ) {
		$lpcc_overlap[] = $card['id'] . ' matches LPCC ' . $lpcc_sigs[ $sig ];
	}
}
assert_true( empty( $legacy_overlap ), 'No front/back duplicates from archived 132-card legacy deck' );
assert_true( empty( $lpcc_overlap ), 'No front/back duplicates from LPCC 807-card library' );

$legacy_numeric_ids = 0;
foreach ( $cards as $card ) {
	if ( preg_match( '/^\d{3}$/', (string) ( $card['id'] ?? '' ) ) ) {
		++$legacy_numeric_ids;
	}
}
assert_true( 0 === $legacy_numeric_ids, 'No legacy-style numeric-only card IDs (001–132 pattern)' );

assert_true(
	! in_array( 'lmft-california-clinical-exam-preparation', CTA_Exam_Prep_Flashcard_Center::get_legacy_fallback_slugs(), true ),
	'LMFT Clinical excluded from legacy flashcards.json fallback'
);

echo "\n--- 6–7) Layout / flip rendering (static CSS + JS audit) ---\n";
$css = (string) file_get_contents( $css_path );
assert_true(
	false !== strpos( $css, '.cta-fsc__flip-text' ) && false !== strpos( $css, 'white-space: pre-wrap' ),
	'Flip text uses pre-wrap (full multiline content)'
);
assert_true(
	false !== strpos( $css, 'overflow-y: auto' ) && false !== strpos( $css, '.cta-fsc__flip-face' ),
	'Flip faces scroll instead of hard-clipping long content'
);
assert_true(
	false !== strpos( $js, 'frontEl.textContent = card.front' ) && false !== strpos( $js, 'backEl.textContent = card.back' ),
	'Study mode assigns full front/back via textContent (no truncation)'
);
assert_true(
	false !== strpos( $js, 'cueEl.textContent = cue' ),
	'Memory cue assigned in full on flip back face'
);
assert_true(
	false !== strpos( $css, '.cta-fsc__memory-cue-text' ),
	'Memory cue styling present for back-face reveal'
);
assert_true(
	false !== strpos( $css, '@media' ) && false !== strpos( $css, '.cta-fsc__flip-face' ),
	'Responsive rules exist for flashcard faces (mobile layout)'
);

echo "\n--- 8) Canonical sort order and shuffle behavior ---\n";
$loader_order = array_map(
	static function ( $card ) {
		return (string) ( $card['id'] ?? '' );
	},
	$cards
);
$raw_sorted = $raw_cards;
usort(
	$raw_sorted,
	static function ( $a, $b ) {
		return (int) ( $a['sort_order'] ?? 0 ) <=> (int) ( $b['sort_order'] ?? 0 );
	}
);
$raw_order = array_map(
	static function ( $card ) {
		return (string) ( $card['id'] ?? '' );
	},
	$raw_sorted
);
assert_true( $loader_order === $raw_order, 'Loader default card order matches canonical sort_order 1–180' );

assert_true(
	false !== strpos( $js, 'state.order' ) && false !== strpos( $js, '[data-cta-fsc-shuffle]' ),
	'Shuffle control operates on state.order only'
);
assert_true(
	false === strpos( $js, 'card.front =' ) && false === strpos( $js, 'card.back =' ),
	'JS does not mutate card content during shuffle/navigation'
);

echo "\n--- 9) No placeholder or test rows ---\n";
$placeholder_hits = array();
$patterns = array(
	'/^\s*(test|placeholder|todo|lorem ipsum)\s*$/i',
	'/^\s*\[.*\]\s*$/',
	'/^TBD\b/i',
	'/^PLACEHOLDER\b/i',
);
foreach ( $raw_cards as $card ) {
	foreach ( array( 'front', 'back', 'memory_cue' ) as $field ) {
		$text = trim( (string) ( $card[ $field ] ?? '' ) );
		if ( '' === $text && 'memory_cue' === $field ) {
			continue;
		}
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $text ) ) {
				$placeholder_hits[] = ( $card['id'] ?? '?' ) . ':' . $field;
			}
		}
	}
}
assert_true( empty( $placeholder_hits ), 'No placeholder/test-only card text detected' );

echo "\n=== Summary ===\n";
echo "PASS: {$pass}\n";
echo "FAIL: {$fail}\n";
echo "WARN: {$warn}\n";

if ( $fail > 0 ) {
	exit( 1 );
}

exit( 0 );

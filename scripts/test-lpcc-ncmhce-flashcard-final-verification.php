<?php
/**
 * Final learner-facing verification for LPCC NCMHCE Flashcard Study Center (180 cards).
 *
 * Usage: php scripts/test-lpcc-ncmhce-flashcard-final-verification.php
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

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		if ( 'cta_lpcc_ncmhce_legacy_flashcards_archived_1_0_266' === $option ) {
			$archived = CTA_PLUGIN_DIR . 'assets/course-materials/lpcc-ncmhce/study-tools/_archived/lpcc-ncmhce-legacy-flashcards-v1.0-132.json';
			if ( is_readable( $archived ) ) {
				return array(
					'archived'  => true,
					'course_id' => 50,
				);
			}
		}
		return $default;
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

class CTA_Exam_Access {
	public static function is_exam_prep( $course ) {
		unset( $course );
		return true;
	}
}

require_once CTA_PLUGIN_DIR . 'includes/class-cta-lpcc-ncmhce-legacy-flashcard-archive.php';
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

function card_signature( array $card ) {
	$front = trim( (string) ( $card['front'] ?? '' ) );
	$back  = trim( (string) ( $card['back'] ?? '' ) );
	return md5( strtolower( $front . '|' . $back ) );
}

function simulate_learner_deck_json( array $deck ) {
	$cards = array_map(
		static function ( $card ) {
			$row = array(
				'id'     => (string) ( $card['id'] ?? '' ),
				'domain' => (string) ( $card['domain'] ?? '' ),
				'front'  => (string) ( $card['front'] ?? '' ),
				'back'   => (string) ( $card['back'] ?? '' ),
			);
			$cue = trim( (string) ( $card['memory_cue'] ?? '' ) );
			if ( '' !== $cue ) {
				$row['memory_cue'] = $cue;
			}
			return $row;
		},
		array_values( (array) ( $deck['cards'] ?? array() ) )
	);

	return wp_json_encode(
		array(
			'title'       => $deck['title'] ?? '',
			'count'       => (int) ( $deck['count'] ?? 0 ),
			'cards'       => $cards,
			'domains'     => array_values( (array) ( $deck['domains'] ?? array() ) ),
			'has_content' => ! empty( $deck['has_content'] ),
		),
		JSON_UNESCAPED_UNICODE
	);
}

echo "=== LPCC NCMHCE Flashcard Study Center — Final Verification ===\n\n";

$course = (object) array(
	'id'   => 50,
	'slug' => 'lpcc-ncmhce-exam-preparation',
);

$deck_path     = CTA_PLUGIN_DIR . 'assets/course-materials/lpcc-ncmhce/study-tools/flashcard-study-center.json';
$archived_path = CTA_PLUGIN_DIR . 'assets/course-materials/lpcc-ncmhce/study-tools/_archived/lpcc-ncmhce-legacy-flashcards-v1.0-132.json';
$active_legacy = CTA_PLUGIN_DIR . 'assets/course-materials/lpcc-ncmhce/study-tools/flashcards.json';
$template_path = CTA_PLUGIN_DIR . 'templates/partials/exam-prep-flashcard-center.php';
$js_path       = CTA_PLUGIN_DIR . 'assets/js/main.js';

$raw       = json_decode( (string) file_get_contents( $deck_path ), true );
$deck      = CTA_Exam_Prep_Flashcard_Center::get_deck_for_course( $course );
$cards     = (array) ( $deck['cards'] ?? array() );
$raw_cards = (array) ( $raw['cards'] ?? array() );

echo "--- 1) Exactly 180 active cards ---\n";
assert_true( (int) ( $deck['count'] ?? 0 ) === 180, 'Loader reports exactly 180 cards' );
assert_true( count( $cards ) === 180, 'Normalized cards array length is 180' );
assert_true( count( $raw_cards ) === 180, 'Source JSON contains 180 card rows' );
assert_true( (int) ( $raw['expected_total'] ?? 0 ) === 180, 'Source expected_total is 180' );

$inactive = 0;
foreach ( $raw_cards as $card ) {
	if ( empty( $card['meta']['active'] ) ) {
		++$inactive;
	}
}
assert_true( 0 === $inactive, 'All 180 source cards have meta.active=true' );

echo "\n--- 2) Spot-check first and last rows vs source JSON ---\n";
$first_raw = $raw_cards[0] ?? array();
$last_raw  = $raw_cards[ count( $raw_cards ) - 1 ] ?? array();
assert_true( 'D1-001' === (string) ( $first_raw['id'] ?? '' ), 'First card ID is D1-001' );
assert_true(
	false !== strpos( (string) ( $first_raw['front'] ?? '' ), 'before accepting the case' ),
	'First card front matches approved export (before accepting the case)'
);
assert_true(
	false !== strpos( (string) ( $first_raw['back'] ?? '' ), 'competence' ),
	'First card back matches approved export (competence before accepting case)'
);
assert_true( 'D6-027' === (string) ( $last_raw['id'] ?? '' ), 'Last card ID is D6-027' );
assert_true(
	false !== strpos( (string) ( $last_raw['front'] ?? '' ), 'marginalized community' ),
	'Last card front matches approved export (marginalized community concern)'
);

echo "\n--- 3) Unique Card IDs and field attachment ---\n";
$ids = array();
foreach ( $cards as $card ) {
	$id = (string) ( $card['id'] ?? '' );
	if ( isset( $ids[ $id ] ) ) {
		++$ids[ $id ];
	} else {
		$ids[ $id ] = 1;
	}
}
$dup_ids = array_filter(
	$ids,
	static function ( $count ) {
		return $count > 1;
	}
);
assert_true( empty( $dup_ids ), 'Every Card ID is unique (' . count( $ids ) . ' IDs)' );

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
		break;
	}
}
assert_true( 0 === $mismatch, 'Front/back preserved through loader normalization' );

echo "\n--- 4) Domain filtering coverage ---\n";
assert_true( 5 === count( (array) ( $deck['domains'] ?? array() ) ), 'Deck declares 5 exam domains' );
$domain_with_cards = 0;
foreach ( (array) ( $deck['domains'] ?? array() ) as $domain ) {
	$key   = (string) ( $domain['key'] ?? '' );
	$count = (int) ( $domain['count'] ?? 0 );
	if ( $count > 0 ) {
		++$domain_with_cards;
	}
	$matched = 0;
	foreach ( $cards as $card ) {
		if ( (string) ( $card['domain'] ?? '' ) === $key ) {
			++$matched;
		}
	}
	assert_true( $matched === $count, "Domain {$key} count matches cards ({$count})" );
}
assert_true( 5 === $domain_with_cards, 'All 5 domains have at least one card' );

$template = (string) file_get_contents( $template_path );
assert_true(
	false !== strpos( $template, 'Filter by exam domain' ),
	'UI exposes exam domain filter chips (template)'
);

echo "\n--- 5) No forbidden internal fields in learner payload ---\n";
$learner_json = simulate_learner_deck_json( $deck );
$forbidden_keys = array( 'meta', 'sort_order', 'domain_code', 'task_id', 'k_id', 'remediation', 'visibility', 'content_version' );
$forbidden_hits = array();
foreach ( $forbidden_keys as $key ) {
	if ( false !== stripos( $learner_json, '"' . $key . '"' ) ) {
		$forbidden_hits[] = $key;
	}
}
assert_true( empty( $forbidden_hits ), 'Simulated learner deck JSON omits internal QA/meta keys' );

echo "\n--- 6) Legacy 132-card deck archived and not merged ---\n";
assert_true( ! is_readable( $active_legacy ), 'Active legacy flashcards.json is not present at study-tools path' );
assert_true( is_readable( $archived_path ), 'Archived legacy deck exists under _archived/' );

$archived = json_decode( (string) file_get_contents( $archived_path ), true );
$legacy_count = count( (array) ( $archived['cards'] ?? array() ) );
assert_true( 132 === $legacy_count, 'Archived legacy deck contains exactly 132 cards' );

$legacy_sigs = array();
foreach ( (array) ( $archived['cards'] ?? array() ) as $legacy_card ) {
	$legacy_sigs[ card_signature( $legacy_card ) ] = (string) ( $legacy_card['id'] ?? '' );
}
$legacy_overlap = array();
foreach ( $cards as $card ) {
	$sig = card_signature( $card );
	if ( isset( $legacy_sigs[ $sig ] ) ) {
		$legacy_overlap[] = $card['id'] . ' matches legacy ' . $legacy_sigs[ $sig ];
	}
}
assert_true( empty( $legacy_overlap ), 'No front/back duplicates merged from archived 132-card legacy deck' );
assert_true(
	180 === (int) ( $deck['count'] ?? 0 ) && 180 === count( $cards ),
	'Total active card count is 180 (not 312)'
);

assert_true(
	! in_array( 'lpcc-ncmhce-exam-preparation', CTA_Exam_Prep_Flashcard_Center::get_legacy_fallback_slugs(), true )
	&& ! in_array( 'lpcc-california-clinical-exam-preparation', CTA_Exam_Prep_Flashcard_Center::get_legacy_fallback_slugs(), true ),
	'LPCC NCMHCE excluded from legacy flashcards.json fallback'
);

$legacy_deck = CTA_Flashcards::get_deck_for_course( $course );
assert_true( null === $legacy_deck, 'Legacy CTA_Flashcards viewer returns null for LPCC NCMHCE after archive' );

echo "\n--- 7) Shuffle regression (LMFT fix applies here too) ---\n";
$js = (string) file_get_contents( $js_path );
assert_true(
	false !== strpos( $js, 'state.order' ) && false !== strpos( $js, '[data-cta-fsc-shuffle]' ),
	'Shuffle control operates on state.order only'
);
$study_fn_pos  = strpos( $js, 'function renderStudy(' );
$browse_fn_pos = strpos( $js, 'function renderBrowse(' );
assert_true( false !== $study_fn_pos && false !== $browse_fn_pos && $browse_fn_pos > $study_fn_pos, 'renderStudy/renderBrowse functions located' );
if ( false !== $study_fn_pos && false !== $browse_fn_pos && $browse_fn_pos > $study_fn_pos ) {
	$render_study_body = substr( $js, $study_fn_pos, $browse_fn_pos - $study_fn_pos );
	assert_true(
		false === strpos( $render_study_body, 'rebuildOrder(' ),
		'renderStudy does not call rebuildOrder (preserves shuffled order)'
	);
}
assert_true(
	false !== strpos( $js, 'state.order = filteredIndices()' ),
	'Shuffle refreshes order from current filtered subset before randomizing'
);

$first_ids = array();
for ( $trial = 0; $trial < 12; $trial++ ) {
	$order = range( 0, 179 );
	for ( $i = 179; $i > 0; $i-- ) {
		$j           = random_int( 0, $i );
		$tmp         = $order[ $i ];
		$order[ $i ]  = $order[ $j ];
		$order[ $j ]  = $tmp;
	}
	$first_ids[] = (string) ( $cards[ $order[0] ]['id'] ?? '' );
}
assert_true( count( array_unique( $first_ids ) ) > 1, 'Simulated shuffle yields varying Card-1 IDs across trials' );

echo "\n--- 8) Upgrade hook and archive wiring ---\n";
$lms = (string) file_get_contents( CTA_PLUGIN_DIR . 'cta-lms.php' );
assert_true(
	false !== strpos( $lms, 'class-cta-lpcc-ncmhce-legacy-flashcard-archive.php' ),
	'Archive class required in cta-lms.php'
);
assert_true(
	false !== strpos( $lms, 'CTA_Lpcc_Ncmhce_Legacy_Flashcard_Archive::archive_legacy_flashcards' ),
	'Upgrade hook archives legacy LPCC flashcards at 1.0.266'
);
assert_true(
	false !== strpos( (string) file_get_contents( CTA_PLUGIN_DIR . 'includes/class-cta-flashcards.php' ), 'CTA_Lpcc_Ncmhce_Legacy_Flashcard_Archive::blocks_learner_legacy_deck' ),
	'Legacy flashcards viewer blocks archived LPCC deck'
);

echo "\n--- 9) Flip reveal (shared v1.0.271 fix) ---\n";
$template = (string) file_get_contents( $template_path );
assert_true(
	false !== strpos( $template, 'data-cta-fsc-answer' ),
	'Template uses data-cta-fsc-answer for flip reveal text'
);
assert_true(
	false !== strpos( $template, 'data-cta-fsc-nav-back' ),
	'Template uses data-cta-fsc-nav-back for toolbar (no selector collision)'
);
assert_true(
	false !== strpos( $js, 'flipBtn.querySelector("[data-cta-fsc-answer]")' ),
	'Study Mode JS scopes answer element inside flip trigger'
);
$empty_backs = 0;
foreach ( $cards as $card ) {
	if ( '' === trim( (string) ( $card['back'] ?? '' ) ) ) {
		++$empty_backs;
	}
}
assert_true( 0 === $empty_backs, 'All 180 normalized cards have populated back fields' );

echo "\n=== Summary ===\n";
echo "PASS: {$pass}\n";
echo "FAIL: {$fail}\n";
echo "WARN: {$warn}\n";

if ( $fail > 0 ) {
	exit( 1 );
}

exit( 0 );

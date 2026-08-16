<?php
/**
 * Validate LMFT Clinical Form A/B 240-minute timer configuration.
 *
 * Usage: php scripts/test-lmft-clinical-form-timers.php
 *
 * @package CTA_LMS
 */

$root = dirname( __DIR__ );

define( 'ABSPATH', $root . '/' );
define( 'CTA_PLUGIN_DIR', $root . '/' );

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( '_n' ) ) {
	function _n( $single, $plural, $number, $domain = 'default' ) {
		return 1 === (int) $number ? $single : $plural;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

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

require_once $root . '/includes/class-cta-lmft-clinical-form-a-sync.php';
require_once $root . '/includes/class-cta-lmft-clinical-form-b-sync.php';
require_once $root . '/includes/class-cta-lmft-clinical-sync.php';
require_once $root . '/public/class-cta-quiz.php';

assert_true(
	240 === CTA_Lmft_Clinical_Form_A_Sync::TIME_LIMIT_MINS,
	'Form A sync constant is 240 minutes'
);
assert_true(
	240 === CTA_Lmft_Clinical_Form_B_Sync::TIME_LIMIT_MINS,
	'Form B sync constant is 240 minutes'
);
assert_true(
	240 === CTA_Lmft_Clinical_Sync::FORM_TIME_LIMIT_MINS,
	'LMFT clinical program timer constant is 240 minutes'
);

$form_a_src = file_get_contents( $root . '/includes/class-cta-lmft-clinical-form-a-sync.php' );
$form_b_src = file_get_contents( $root . '/includes/class-cta-lmft-clinical-form-b-sync.php' );
$quiz_src   = file_get_contents( $root . '/public/class-cta-quiz.php' );
$quiz_tpl   = file_get_contents( $root . '/templates/quiz.php' );
$main_js    = file_get_contents( $root . '/assets/js/main.js' );
$lms        = file_get_contents( $root . '/cta-lms.php' );
$sync_src   = file_get_contents( $root . '/includes/class-cta-lmft-clinical-sync.php' );

assert_true(
	false !== strpos( $form_a_src, "'time_limit_mins' => self::TIME_LIMIT_MINS" ),
	'Form A sync writes time_limit_mins from TIME_LIMIT_MINS'
);
assert_true(
	false !== strpos( $form_b_src, "'time_limit_mins' => self::TIME_LIMIT_MINS" ),
	'Form B sync writes time_limit_mins from TIME_LIMIT_MINS'
);
assert_true(
	false !== strpos( $sync_src, 'sync_comprehensive_simulation_time_limits' ),
	'Program sync exposes timer heal method'
);
assert_true(
	false !== strpos( $sync_src, "quiz_type IN ('form_a', 'form_b')" ),
	'Timer heal targets Form A and Form B only'
);

$quiz_row = (object) array( 'time_limit_mins' => 240 );
assert_true(
	240 === CTA_Quiz::get_time_limit_mins( $quiz_row ),
	'Quiz helper resolves 240-minute limit'
);
assert_true(
	false !== strpos( CTA_Quiz::format_time_limit_label( 240 ), '240' ),
	'Quiz helper formats 240-minute label'
);
assert_true(
	'No limit' === CTA_Quiz::format_time_limit_label( 0 ),
	'Untimed quizzes still show No limit'
);

assert_true(
	false !== strpos( $quiz_src, 'get_time_limit_mins' ),
	'Quiz renderer uses effective time limit'
);
assert_true(
	false !== strpos( $quiz_tpl, '$time_limit_mins' ),
	'Quiz template exposes data-time-limit from resolved minutes'
);
assert_true(
	false !== strpos( $main_js, 'getSecondsRemaining' ),
	'Quiz JS computes remaining time from attempt start'
);
assert_true(
	false !== strpos( $main_js, 'submitQuiz(true)' ),
	'Quiz JS auto-submits when timer expires'
);
assert_true(
	false !== strpos( $lms, '1.0.245' ),
	'Version bump 1.0.245'
);
assert_true(
	false !== strpos( $lms, 'sync_comprehensive_simulation_time_limits' ),
	'Upgrade hook applies Form A/B timer settings'
);

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );

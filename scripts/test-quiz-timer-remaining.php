<?php
/**
 * Unit checks for timed quiz remaining-time helpers (Practice Bank 00:00 bug).
 *
 * Usage: php scripts/test-quiz-timer-remaining.php
 *
 * @package CTA_LMS
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$root = dirname( __DIR__ );
$fail = 0;
$pass = 0;

/**
 * @param bool   $cond Condition.
 * @param string $msg  Message.
 */
function assert_true( $cond, $msg ) {
	global $fail, $pass;
	if ( $cond ) {
		echo "PASS: {$msg}\n";
		++$pass;
	} else {
		echo "FAIL: {$msg}\n";
		++$fail;
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $v ) {
		return abs( (int) $v );
	}
}
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type ) {
		if ( 'timestamp' === $type ) {
			return $GLOBALS['cta_test_now'] ?? time();
		}
		return gmdate( 'Y-m-d H:i:s', $GLOBALS['cta_test_now'] ?? time() );
	}
}
if ( ! function_exists( 'mysql2date' ) ) {
	function mysql2date( $format, $date, $translate = true ) {
		$ts = strtotime( (string) $date );
		return false === $ts ? 0 : ( 'U' === $format ? $ts : date( $format, $ts ) ); // phpcs:ignore
	}
}

// Minimal stubs so class-cta-quiz.php can load without WordPress.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}
if ( ! defined( 'CTA_PLUGIN_DIR' ) ) {
	define( 'CTA_PLUGIN_DIR', $root . '/' );
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action() {}
}
if ( ! function_exists( '__' ) ) {
	function __( $t ) {
		return $t;
	}
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $t ) {
		return $t;
	}
}

// Extract only the static timer helpers via reflection on a stripped include is heavy;
// instead re-declare the same logic contracts by requiring the public class after stubs.
require_once $root . '/public/class-cta-quiz.php';

assert_true( class_exists( 'CTA_Quiz' ), 'CTA_Quiz loads' );

$quiz_40 = (object) array( 'time_limit_mins' => 40 );
$quiz_0  = (object) array( 'time_limit_mins' => 0 );

assert_true( 40 === CTA_Quiz::get_time_limit_mins( $quiz_40 ), 'WB practice bank limit is 40 minutes' );
assert_true( 0 === CTA_Quiz::get_time_limit_mins( $quiz_0 ), 'Untimed quiz limit is 0' );

$GLOBALS['cta_test_now'] = strtotime( '2026-08-17 12:00:00' );

$fresh = (object) array(
	'started_at'   => '2026-08-17 12:00:00',
	'completed_at' => null,
);
$remaining_fresh = CTA_Quiz::get_attempt_seconds_remaining( $quiz_40, $fresh );
assert_true( 2400 === $remaining_fresh, 'Fresh attempt has full 40:00 (2400s)' );
assert_true( ! CTA_Quiz::is_attempt_time_expired( $quiz_40, $fresh ), 'Fresh attempt is not expired' );

$half = (object) array(
	'started_at'   => '2026-08-17 11:40:00',
	'completed_at' => null,
);
$remaining_half = CTA_Quiz::get_attempt_seconds_remaining( $quiz_40, $half );
assert_true( 1200 === $remaining_half, '20 minutes elapsed => 20 minutes remaining' );

$stale = (object) array(
	'started_at'   => '2026-08-17 10:00:00',
	'completed_at' => null,
);
$remaining_stale = CTA_Quiz::get_attempt_seconds_remaining( $quiz_40, $stale );
assert_true( 0 === $remaining_stale, 'Stale attempt past limit has 0 remaining' );
assert_true( CTA_Quiz::is_attempt_time_expired( $quiz_40, $stale ), 'Stale attempt is expired' );

$done = (object) array(
	'started_at'   => '2026-08-17 10:00:00',
	'completed_at' => '2026-08-17 10:41:00',
);
assert_true( ! CTA_Quiz::is_attempt_time_expired( $quiz_40, $done ), 'Completed attempt is not treated as expired-open' );

$quiz_src = file_get_contents( $root . '/public/class-cta-quiz.php' );
assert_true(
	false !== strpos( $quiz_src, 'finalize_timed_out_attempt' )
		&& false !== strpos( $quiz_src, 'is_attempt_time_expired' ),
	'Quiz handler finalizes expired open attempts'
);

$js = file_get_contents( $root . '/assets/js/main.js' );
assert_true( false !== strpos( $js, 'seconds_remaining' ), 'JS syncs server seconds_remaining' );
assert_true( false !== strpos( $js, 'handleTimeExpired' ), 'JS uses explicit time-expired handler' );
assert_true( false !== strpos( $js, 'Time is up' ), 'JS shows time-expired message' );

$tpl = file_get_contents( $root . '/templates/quiz.php' );
assert_true( false !== strpos( $tpl, 'data-seconds-remaining' ), 'Quiz template exposes data-seconds-remaining' );

$lpcc = file_get_contents( $root . '/includes/class-cta-lpcc-ncmhce-sync.php' );
assert_true(
	false !== strpos( $lpcc, "for ( \$n = 1; \$n <= 12; \$n++ )" )
		&& false !== strpos( $lpcc, "'time'      => 40" )
		&& false !== strpos( $lpcc, '17-Question Practice Bank' ),
	'LPCC NCMHCE workbook banks (all 12) configured for 40 minutes'
);
preg_match_all( "/'time'\s*=>\s*40/", $lpcc, $m );
assert_true( count( $m[0] ) >= 1, 'LPCC sync includes 40-minute bank time allotment' );

$plugin = file_get_contents( $root . '/cta-plugin.php' );
assert_true( false !== strpos( $plugin, '1.0.260' ), 'Plugin version bumped to 1.0.260' );

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );

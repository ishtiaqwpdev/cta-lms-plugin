<?php
/**
 * Verify LMFT Clinical Form A/B sequential gating (Implementation Guide v1.1 §5).
 *
 * Usage: php scripts/test-lmft-clinical-form-gates.php
 *
 * @package CTA_LMS
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$root = dirname( __DIR__ );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}
if ( ! defined( 'CTA_PLUGIN_DIR' ) ) {
	define( 'CTA_PLUGIN_DIR', $root . '/' );
}

$pass = 0;
$fail = 0;

/**
 * @param bool   $cond Condition.
 * @param string $msg  Message.
 */
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

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text Text.
	 * @param string $domain Domain.
	 * @return string
	 */
	function __( $text, $domain = null ) {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * @param string $key Key.
	 * @return string
	 */
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * @param string $str String.
	 * @return string
	 */
	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}

if ( ! function_exists( 'absint' ) ) {
	/**
	 * @param mixed $val Value.
	 * @return int
	 */
	function absint( $val ) {
		return abs( (int) $val );
	}
}

if ( ! function_exists( 'current_time' ) ) {
	/**
	 * @param string $type Type.
	 * @return string
	 */
	function current_time( $type ) {
		unset( $type );
		return gmdate( 'Y-m-d H:i:s' );
	}
}

$GLOBALS['cta_test_user_meta'] = array();

if ( ! function_exists( 'get_user_meta' ) ) {
	/**
	 * @param int    $user_id User ID.
	 * @param string $key     Meta key.
	 * @param bool   $single  Single.
	 * @return mixed
	 */
	function get_user_meta( $user_id, $key, $single = false ) {
		$store = $GLOBALS['cta_test_user_meta'];
		$val   = $store[ $user_id ][ $key ] ?? '';
		return $single ? $val : array( $val );
	}
}

if ( ! function_exists( 'update_user_meta' ) ) {
	/**
	 * @param int    $user_id User ID.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Value.
	 * @return bool
	 */
	function update_user_meta( $user_id, $key, $value ) {
		$GLOBALS['cta_test_user_meta'][ $user_id ][ $key ] = $value;
		return true;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error stub.
	 */
	class WP_Error {
		/**
		 * @var string
		 */
		public $code;
		/**
		 * @var string
		 */
		public $message;
		/**
		 * @param string $code Code.
		 * @param string $message Message.
		 */
		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}
		/**
		 * @return string
		 */
		public function get_error_message() {
			return $this->message;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * @param mixed $thing Thing.
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

require_once $root . '/includes/class-cta-lmft-clinical-legacy-forms-archive.php';
require_once $root . '/includes/class-cta-lmft-clinical-form-gates.php';

/**
 * Stub course materials helpers used by Form Gates.
 */
class CTA_Course_Materials {
	/**
	 * @var array<string,bool>
	 */
	public static $completed = array();
	/**
	 * @var bool
	 */
	public static $has_remediation_workbook = false;

	/**
	 * @param int    $user_id User.
	 * @param int    $course_id Course.
	 * @param string $type Type.
	 * @return bool
	 */
	public static function user_has_completed_quiz_type( $user_id, $course_id, $type ) {
		return ! empty( self::$completed[ $user_id . ':' . $course_id . ':' . $type ] );
	}

	/**
	 * @param int $course_id Course.
	 * @return bool
	 */
	public static function course_has_form_a_remediation( $course_id ) {
		unset( $course_id );
		return self::$has_remediation_workbook;
	}

	/**
	 * @param int $course_id Course.
	 * @return string
	 */
	public static function form_a_remediation_meta_key( $course_id ) {
		return 'cta_form_a_remediation_complete_' . absint( $course_id );
	}

	/**
	 * @param int $user_id User.
	 * @param int $course_id Course.
	 * @return bool
	 */
	public static function user_has_completed_form_a_remediation( $user_id, $course_id ) {
		$val = get_user_meta( $user_id, self::form_a_remediation_meta_key( $course_id ), true );
		return is_string( $val ) && '' !== $val;
	}
}

/**
 * Stub CE completion.
 */
class CTA_CE_Completion {
	/**
	 * @var bool
	 */
	public static $modules_complete = false;

	/**
	 * @param int         $user_id User.
	 * @param int         $course_id Course.
	 * @param object|null $enrollment Enrollment.
	 * @return bool
	 */
	public static function modules_complete( $user_id, $course_id, $enrollment = null ) {
		unset( $user_id, $course_id, $enrollment );
		return self::$modules_complete;
	}
}

$course = (object) array(
	'id'   => 10,
	'slug' => 'lmft-california-clinical-exam-preparation',
	'title'=> 'LMFT California Clinical Exam Preparation',
);

$form_a = (object) array(
	'id'        => 101,
	'quiz_type' => 'form_a',
	'title'     => 'Comprehensive Simulation - Form A',
	'status'    => 'active',
);

$form_b = (object) array(
	'id'        => 102,
	'quiz_type' => 'form_b',
	'title'     => 'Comprehensive Simulation - Form B',
	'status'    => 'active',
);

$archived_a = (object) array(
	'id'        => 201,
	'quiz_type' => 'legacy_form_a',
	'title'     => '[Archived] Form A — 150-Question Comprehensive Simulation',
	'status'    => 'archived',
);

echo "=== LMFT Clinical Form A/B Sequential Gates ===\n\n";

assert_true( CTA_Lmft_Clinical_Form_Gates::applies_to_course( $course ), 'Gates apply to LMFT Clinical course' );
assert_true( CTA_Lmft_Clinical_Form_Gates::is_active_form_quiz( $form_a ), 'Active Form A recognized' );
assert_true( CTA_Lmft_Clinical_Form_Gates::is_active_form_quiz( $form_b ), 'Active Form B recognized' );
assert_true( ! CTA_Lmft_Clinical_Form_Gates::is_active_form_quiz( $archived_a ), 'Archived Form A excluded from active gates' );

$user = 8;
$cid  = 10;
CTA_CE_Completion::$modules_complete = false;
CTA_Course_Materials::$completed     = array();
$GLOBALS['cta_test_user_meta']       = array();

$a0 = CTA_Lmft_Clinical_Form_Gates::assert_quiz_accessible( $form_a, $course, $user );
$b0 = CTA_Lmft_Clinical_Form_Gates::assert_quiz_accessible( $form_b, $course, $user );
assert_true( is_wp_error( $a0 ), 'Stage 0: Form A locked before workbooks' );
assert_true( is_wp_error( $b0 ), 'Stage 0: Form B locked before workbooks' );
assert_true( false !== stripos( $a0->get_error_message(), 'workbook' ), 'Stage 0: Form A message mentions workbooks' );

CTA_CE_Completion::$modules_complete = true;
$a1 = CTA_Lmft_Clinical_Form_Gates::assert_quiz_accessible( $form_a, $course, $user );
$b1 = CTA_Lmft_Clinical_Form_Gates::assert_quiz_accessible( $form_b, $course, $user );
assert_true( true === $a1, 'Stage 1: Form A unlocks after workbooks complete' );
assert_true( is_wp_error( $b1 ), 'Stage 1: Form B still locked after workbooks only' );
assert_true( false !== stripos( $b1->get_error_message(), 'Form A' ), 'Stage 1: Form B message mentions Form A (not workbooks-only)' );
assert_true( false === stripos( $b1->get_error_message(), 'Complete all program workbooks' ), 'Stage 1: Form B message is not the workbook message' );

CTA_Course_Materials::$completed[ $user . ':' . $cid . ':form_a' ] = true;
$b2 = CTA_Lmft_Clinical_Form_Gates::assert_quiz_accessible( $form_b, $course, $user );
assert_true( is_wp_error( $b2 ), 'Stage 2: Form B locked after Form A submit without remediation' );
assert_true( false !== stripos( $b2->get_error_message(), 'rationale' ) || false !== stripos( $b2->get_error_message(), 'remediation' ), 'Stage 2: Form B message mentions remediation/review' );

update_user_meta( $user, CTA_Course_Materials::form_a_remediation_meta_key( $cid ), current_time( 'mysql' ) );
$b3 = CTA_Lmft_Clinical_Form_Gates::assert_quiz_accessible( $form_b, $course, $user );
assert_true( true === $b3, 'Stage 3: Form B unlocks after Form A + remediation' );

$lock_a = CTA_Lmft_Clinical_Form_Gates::get_card_lock_state( $form_a, $course, $user, null, false );
$lock_b = CTA_Lmft_Clinical_Form_Gates::get_card_lock_state( $form_b, $course, $user, null, false );
assert_true( empty( $lock_a['entry_locked'] ) && empty( $lock_b['entry_locked'] ), 'Stage 3: Exam Center cards unlocked for both forms' );

// Direct-URL style: archived quiz must not satisfy active Form A gate wiring.
assert_true(
	! CTA_Lmft_Clinical_Form_Gates::is_active_form_quiz( $archived_a ),
	'Direct access path ignores archived legacy Form A quiz rows'
);

$center = file_get_contents( $root . '/includes/class-cta-exam-prep-exam-center.php' );
$quiz   = file_get_contents( $root . '/public/class-cta-quiz.php' );
$card   = file_get_contents( $root . '/templates/partials/exam-prep-exam-center-card.php' );
assert_true( false !== strpos( $center, 'CTA_Lmft_Clinical_Form_Gates' ), 'Exam Center uses LMFT Form Gates' );
assert_true( false !== strpos( $quiz, 'CTA_Lmft_Clinical_Form_Gates' ), 'Quiz page enforces LMFT Form Gates' );
assert_true( false !== strpos( $card, 'lock_button' ), 'Exam Center card uses stage-specific lock button label' );

echo "\n=== Summary ===\nPASS: {$pass}\nFAIL: {$fail}\n";
exit( $fail > 0 ? 1 : 0 );

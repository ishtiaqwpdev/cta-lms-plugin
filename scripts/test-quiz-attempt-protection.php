<?php
/**
 * Regression tests for assessment entry gating and progress persistence.
 *
 * Run: C:\xampp\php\php.exe scripts/test-quiz-attempt-protection.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'CTA_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

function __( $text, $domain = null ) {
	unset( $domain );
	return $text;
}

function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

class WP_Error {
	private $code;
	private $message;

	public function __construct( $code, $message ) {
		$this->code    = $code;
		$this->message = $message;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}
}

class CTA_Exam_Access {
	public static function is_exam_prep( $course ) {
		return $course && 'exam_prep' === $course->product_type;
	}

	public static function has_active_access( $user_id, $course_id ) {
		unset( $user_id, $course_id );
		return true;
	}
}

class CTA_Database {
	public static $course;
	public static $enrollment;
	public static $quiz;

	public static function get_user_enrollment( $user_id, $course_id ) {
		unset( $user_id, $course_id );
		return self::$enrollment;
	}

	public static function get_course( $course_id ) {
		unset( $course_id );
		return self::$course;
	}

	public static function get_quiz_for_course( $course_id, $quiz_id = 0 ) {
		unset( $course_id, $quiz_id );
		return self::$quiz;
	}
}

class CTA_CE_Completion {
	public static $modules_complete = false;

	public static function sync_progress( $user_id, $course_id, $enrollment ) {
		unset( $user_id, $course_id );
		return (int) $enrollment->progress;
	}

	public static function assert_can_access_exam( $user_id, $course_id ) {
		unset( $user_id, $course_id );
		return self::$modules_complete
			? true
			: new WP_Error( 'cta_seq_modules', 'Complete all program modules before starting assessments.' );
	}
}

require_once CTA_PLUGIN_DIR . 'public/class-cta-quiz.php';

$passed = 0;
$failed = 0;

function assert_test( $condition, $label ) {
	global $passed, $failed;
	if ( $condition ) {
		echo "PASS: {$label}\n";
		++$passed;
		return;
	}
	echo "FAIL: {$label}\n";
	++$failed;
}

CTA_Database::$course     = (object) array( 'id' => 11, 'product_type' => 'exam_prep' );
CTA_Database::$enrollment = (object) array( 'id' => 7, 'progress' => 0 );
CTA_Database::$quiz       = (object) array( 'id' => 3, 'course_id' => 11 );

$quiz       = ( new ReflectionClass( 'CTA_Quiz' ) )->newInstanceWithoutConstructor();
$validate   = new ReflectionMethod( 'CTA_Quiz', 'validate_quiz_access' );
$sanitize   = new ReflectionMethod( 'CTA_Quiz', 'sanitize_quiz_answers' );
$in_progress = new ReflectionMethod( 'CTA_Quiz', 'is_attempt_in_progress' );
$validate->setAccessible( true );
$sanitize->setAccessible( true );
$in_progress->setAccessible( true );

CTA_CE_Completion::$modules_complete = false;
$blocked = $validate->invoke( $quiz, 5, 11, true, 3 );
assert_test( is_wp_error( $blocked ) && 'cta_seq_modules' === $blocked->get_error_code(), 'Incomplete learner blocked before attempt creation' );

$base_access = $validate->invoke( $quiz, 5, 11, false, 3 );
assert_test( is_array( $base_access ), 'Existing attempt can pass enrollment/access validation without rechecking prerequisites' );

CTA_CE_Completion::$modules_complete = true;
$allowed = $validate->invoke( $quiz, 5, 11, true, 3 );
assert_test( is_array( $allowed ) && 3 === (int) $allowed['quiz']->id, 'Complete learner allowed to start assessment' );

$questions = array(
	(object) array( 'id' => 101 ),
	(object) array( 'id' => 102 ),
);
$answers = $sanitize->invoke( $quiz, array( 101 => 'a', 102 => 'x', 999 => 'd' ), $questions, false );
assert_test( array( 101 => 'a' ) === $answers, 'Autosave accepts only valid answers belonging to the assessment' );

assert_test( $in_progress->invoke( $quiz, (object) array( 'completed_at' => null ) ), 'NULL completed_at is resumable' );
assert_test( $in_progress->invoke( $quiz, (object) array( 'completed_at' => '0000-00-00 00:00:00' ) ), 'Legacy zero-date attempt is resumable' );
assert_test( ! $in_progress->invoke( $quiz, (object) array( 'completed_at' => '2026-08-15 10:00:00' ) ), 'Completed attempt cannot be overwritten' );

$quiz_source = file_get_contents( CTA_PLUGIN_DIR . 'public/class-cta-quiz.php' );
$js_source   = file_get_contents( CTA_PLUGIN_DIR . 'assets/js/main.js' );
assert_test( false !== strpos( $quiz_source, 'wp_ajax_cta_save_quiz_progress' ), 'Server autosave endpoint registered' );
assert_test( false !== strpos( $js_source, 'scheduleQuizAutosave' ) && false !== strpos( $js_source, 'navigator.sendBeacon' ), 'Browser autosave and page-exit safeguard present' );
assert_test( false === strpos( $quiz_source, 'Re-assert module completion so an in-flight attempt' ), 'Submission no longer rechecks entry prerequisites' );

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed ? 1 : 0 );

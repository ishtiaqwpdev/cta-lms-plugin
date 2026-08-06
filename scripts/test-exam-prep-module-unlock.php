<?php
/**
 * Unit test: Exam Prep modules unlock in any order; CE stays sequential.
 *
 * Run: C:\xampp\php\php.exe scripts/test-exam-prep-module-unlock.php
 */

if ( ! class_exists( 'CTA_Exam_Access' ) ) {
	class CTA_Exam_Access {
		const PRODUCT_TYPE_EXAM_PREP = 'exam_prep';

		public static function is_exam_prep( $course_or_type ) {
			if ( is_object( $course_or_type ) ) {
				$type = isset( $course_or_type->product_type ) ? (string) $course_or_type->product_type : 'ce';
			} else {
				$type = (string) $course_or_type;
			}
			return self::PRODUCT_TYPE_EXAM_PREP === $type;
		}
	}
}

if ( ! class_exists( 'CTA_Database' ) ) {
	class CTA_Database {
		public static function get_course( $id ) {
			return null;
		}
	}
}

/**
 * Minimal stand-in for CTA_Student_Dashboard access helpers.
 */
class CTA_Module_Unlock_Test_Harness {
	public function get_module_index( $modules, $module_id ) {
		foreach ( $modules as $index => $module ) {
			if ( (int) $module->id === (int) $module_id ) {
				return $index;
			}
		}
		return -1;
	}

	public function is_module_accessible( $modules, $completed_ids, $module_id, $course = null ) {
		$index = $this->get_module_index( $modules, $module_id );

		if ( $index < 0 ) {
			return false;
		}

		if ( null === $course && ! empty( $modules[0]->course_id ) ) {
			$course = CTA_Database::get_course( (int) $modules[0]->course_id );
		}

		if ( $course && class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course ) ) {
			return true;
		}

		if ( 0 === $index ) {
			return true;
		}

		$previous_id = (int) $modules[ $index - 1 ]->id;

		return in_array( $previous_id, $completed_ids, true );
	}
}

function cta_assert( $cond, $label ) {
	if ( $cond ) {
		echo "PASS: {$label}\n";
		return true;
	}
	echo "FAIL: {$label}\n";
	return false;
}

$modules = array(
	(object) array( 'id' => 101, 'course_id' => 1, 'title' => 'WB1' ),
	(object) array( 'id' => 102, 'course_id' => 1, 'title' => 'WB2' ),
	(object) array( 'id' => 103, 'course_id' => 1, 'title' => 'WB3' ),
);

$exam_prep = (object) array( 'id' => 1, 'product_type' => 'exam_prep', 'title' => 'LCSW Exam Prep' );
$ce        = (object) array( 'id' => 2, 'product_type' => 'ce', 'title' => 'Telehealth CE' );
$harness   = new CTA_Module_Unlock_Test_Harness();
$pass      = true;

// Exam Prep: zero completions — every module open.
$completed = array();
$pass = cta_assert( $harness->is_module_accessible( $modules, $completed, 101, $exam_prep ), 'Exam Prep module 1 open with no completions' ) && $pass;
$pass = cta_assert( $harness->is_module_accessible( $modules, $completed, 102, $exam_prep ), 'Exam Prep module 2 open with no completions' ) && $pass;
$pass = cta_assert( $harness->is_module_accessible( $modules, $completed, 103, $exam_prep ), 'Exam Prep module 3 open with no completions' ) && $pass;

// CE: zero completions — only first open.
$pass = cta_assert( $harness->is_module_accessible( $modules, $completed, 101, $ce ), 'CE module 1 open with no completions' ) && $pass;
$pass = cta_assert( ! $harness->is_module_accessible( $modules, $completed, 102, $ce ), 'CE module 2 locked with no completions' ) && $pass;
$pass = cta_assert( ! $harness->is_module_accessible( $modules, $completed, 103, $ce ), 'CE module 3 locked with no completions' ) && $pass;

// CE: after module 1 complete — module 2 opens, 3 still locked.
$completed = array( 101 );
$pass = cta_assert( $harness->is_module_accessible( $modules, $completed, 102, $ce ), 'CE module 2 unlocks after module 1' ) && $pass;
$pass = cta_assert( ! $harness->is_module_accessible( $modules, $completed, 103, $ce ), 'CE module 3 still locked after only module 1' ) && $pass;

// Unknown module always false for both.
$pass = cta_assert( ! $harness->is_module_accessible( $modules, $completed, 999, $exam_prep ), 'Unknown module denied on Exam Prep' ) && $pass;
$pass = cta_assert( ! $harness->is_module_accessible( $modules, $completed, 999, $ce ), 'Unknown module denied on CE' ) && $pass;

echo $pass ? "\nALL PASSED\n" : "\nSOME FAILED\n";
exit( $pass ? 0 : 1 );

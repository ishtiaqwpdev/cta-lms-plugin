<?php
/**
 * LCSW ASWB Clinical Form A/B content quality checks.
 *
 * Detects legacy answer-cue patterns (long correct option + weak/empty distractors)
 * in quiz seed arrays. Used before marking forms learner-ready.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CTA_Lcsw_Aswb_Form_Quality' ) ) {

class CTA_Lcsw_Aswb_Form_Quality {

	const TARGET_QUESTION_COUNT = 122;
	const FORM_A_SEED           = 'includes/quiz-seeds/lcsw-aswb-form-a.php';
	const FORM_B_SEED           = 'includes/quiz-seeds/lcsw-aswb-form-b.php';

	/**
	 * When final rebuilt seeds land, set these Q1 needles for live DB fingerprint checks.
	 */
	const FINAL_FORM_A_Q1_NEEDLE = 'At a rural behavioral-health clinic, siblings are fighting after assuming new caregiving roles';
	const FINAL_FORM_B_Q1_NEEDLE = 'A manager at a hospital social-work service asks clinicians to code every intake';

	/**
	 * @param string $seed_rel Relative seed path under plugin dir.
	 * @return array<int,array<string,string>>
	 */
	public static function load_seed_questions( $seed_rel ) {
		$path = CTA_PLUGIN_DIR . ltrim( (string) $seed_rel, '/' );
		if ( ! is_readable( $path ) ) {
			return array();
		}

		$rows = include $path;
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array<string,array<int,array<string,string>>>
	 */
	public static function get_form_seed_questions() {
		return array(
			'form_a' => self::load_seed_questions( self::FORM_A_SEED ),
			'form_b' => self::load_seed_questions( self::FORM_B_SEED ),
		);
	}

	/**
	 * @param array $question Question row.
	 * @return array<string,int>
	 */
	public static function option_lengths( array $question ) {
		return array(
			'a' => strlen( trim( (string) ( $question['option_a'] ?? '' ) ) ),
			'b' => strlen( trim( (string) ( $question['option_b'] ?? '' ) ) ),
			'c' => strlen( trim( (string) ( $question['option_c'] ?? '' ) ) ),
			'd' => strlen( trim( (string) ( $question['option_d'] ?? '' ) ) ),
		);
	}

	/**
	 * Whether a question exhibits the legacy answer-cue pattern.
	 *
	 * @param array $question Question row.
	 * @return bool
	 */
	public static function question_has_answer_cue( array $question ) {
		$lens    = self::option_lengths( $question );
		$correct = strtolower( (string) ( $question['correct_option'] ?? '' ) );
		if ( ! isset( $lens[ $correct ] ) || $lens[ $correct ] < 80 ) {
			return false;
		}

		$others = array();
		foreach ( $lens as $key => $len ) {
			if ( $key === $correct || 0 === $len ) {
				continue;
			}
			$others[] = $len;
		}

		if ( empty( $others ) ) {
			return true;
		}

		$avg_other = array_sum( $others ) / count( $others );
		return $lens[ $correct ] >= ( 1.8 * $avg_other );
	}

	/**
	 * @param array<int,array<string,string>> $questions Question rows.
	 * @return array{count:int,empty_option_d:int,answer_cue_count:int,max_length_ratio:float}
	 */
	public static function audit_questions( array $questions ) {
		$empty_d   = 0;
		$cue_count = 0;
		$max_ratio = 0.0;

		foreach ( $questions as $question ) {
			$lens = self::option_lengths( $question );
			if ( 0 === $lens['d'] ) {
				++$empty_d;
			}

			if ( self::question_has_answer_cue( $question ) ) {
				++$cue_count;
			}

			$nonzero = array_values( array_filter( $lens ) );
			if ( count( $nonzero ) >= 2 ) {
				$min = min( $nonzero );
				$max = max( $nonzero );
				$ratio = $min > 0 ? ( $max / $min ) : ( $max > 0 ? 999.0 : 1.0 );
				if ( $ratio > $max_ratio ) {
					$max_ratio = $ratio;
				}
			}
		}

		return array(
			'count'            => count( $questions ),
			'empty_option_d'   => $empty_d,
			'answer_cue_count' => $cue_count,
			'max_length_ratio' => round( $max_ratio, 2 ),
		);
	}

	/**
	 * Approved final production seeds should have zero answer-cue flags.
	 *
	 * @param array<int,array<string,string>> $questions Question rows.
	 * @return bool
	 */
	public static function seeds_meet_final_standard( array $questions ) {
		if ( self::TARGET_QUESTION_COUNT !== count( $questions ) ) {
			return false;
		}

		$audit = self::audit_questions( $questions );
		return 0 === (int) $audit['answer_cue_count'] && 0 === (int) $audit['empty_option_d'];
	}

	/**
	 * @param string $form form_a|form_b.
	 * @return bool
	 */
	public static function seed_file_meets_final_standard( $form ) {
		$form = strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $form ) );
		$map  = array(
			'form_a' => self::FORM_A_SEED,
			'form_b' => self::FORM_B_SEED,
		);
		if ( ! isset( $map[ $form ] ) ) {
			return false;
		}

		return self::seeds_meet_final_standard( self::load_seed_questions( $map[ $form ] ) );
	}

	/**
	 * @return bool
	 */
	public static function all_seed_files_meet_final_standard() {
		return self::seed_file_meets_final_standard( 'form_a' )
			&& self::seed_file_meets_final_standard( 'form_b' );
	}

	/**
	 * @param string $quiz_type form_a|form_b.
	 * @param string $q1_text   First question text from live DB.
	 * @return bool
	 */
	public static function q1_matches_final_fingerprint( $quiz_type, $q1_text ) {
		$quiz_type = strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $quiz_type ) );
		$needle    = '';
		if ( 'form_a' === $quiz_type ) {
			$needle = self::FINAL_FORM_A_Q1_NEEDLE;
		} elseif ( 'form_b' === $quiz_type ) {
			$needle = self::FINAL_FORM_B_Q1_NEEDLE;
		}

		if ( '' === trim( $needle ) ) {
			return false;
		}

		return false !== stripos( (string) $q1_text, $needle );
	}
}

}

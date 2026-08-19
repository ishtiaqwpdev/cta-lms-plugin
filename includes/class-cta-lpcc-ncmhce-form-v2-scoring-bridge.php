<?php
/**
 * Shared LPCC NCMHCE Form A/B v2.0 scoring facade.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CTA_Lpcc_Ncmhce_Form_V2_Scoring_Bridge' ) ) {

class CTA_Lpcc_Ncmhce_Form_V2_Scoring_Bridge {

	/**
	 * @param object|null $quiz Quiz row.
	 * @return string|null Scoring class name.
	 */
	private static function resolve_scoring_class( $quiz ) {
		if ( ! $quiz ) {
			return null;
		}
		if ( class_exists( 'CTA_Lpcc_Ncmhce_Form_A_V2_Scoring' )
			&& CTA_Lpcc_Ncmhce_Form_A_V2_Scoring::uses_scored_field_test_scoring( $quiz ) ) {
			return 'CTA_Lpcc_Ncmhce_Form_A_V2_Scoring';
		}
		if ( class_exists( 'CTA_Lpcc_Ncmhce_Form_B_V2_Scoring' )
			&& CTA_Lpcc_Ncmhce_Form_B_V2_Scoring::uses_scored_field_test_scoring( $quiz ) ) {
			return 'CTA_Lpcc_Ncmhce_Form_B_V2_Scoring';
		}
		return null;
	}

	public static function uses_scored_field_test_scoring( $quiz, $course = null ) {
		return null !== self::resolve_scoring_class( $quiz );
	}

	public static function withholds_pass_fail( $quiz ) {
		$class = self::resolve_scoring_class( $quiz );
		return $class && $class::withholds_pass_fail( $quiz );
	}

	public static function calculate_display_score( array $questions, array $sanitized, $quiz ) {
		$class = self::resolve_scoring_class( $quiz );
		if ( ! $class ) {
			return array(
				'score'                      => 0,
				'passed'                     => false,
				'scored_correct'             => 0,
				'scored_total'               => 0,
				'pass_threshold_unspecified' => true,
			);
		}
		return $class::calculate_display_score( $questions, $sanitized, $quiz );
	}

	public static function is_staging_quiz( $quiz ) {
		if ( ! $quiz ) {
			return false;
		}
		if ( class_exists( 'CTA_Lpcc_Ncmhce_Form_A_V2_Sync' )
			&& CTA_Lpcc_Ncmhce_Form_A_V2_Sync::is_staging_quiz( $quiz ) ) {
			return true;
		}
		if ( class_exists( 'CTA_Lpcc_Ncmhce_Form_B_V2_Sync' )
			&& CTA_Lpcc_Ncmhce_Form_B_V2_Sync::is_staging_quiz( $quiz ) ) {
			return true;
		}
		return false;
	}
}

}

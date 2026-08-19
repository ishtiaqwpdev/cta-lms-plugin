<?php
/**
 * Audit LCSW ASWB Form A/B for answer-cue patterns and compare to LMFT final forms.
 *
 * Usage: php scripts/audit-lcsw-aswb-form-answer-cues.php
 *
 * @package CTA_LMS
 */

$root = dirname( __DIR__ );
define( 'ABSPATH', $root . '/' );

function load_seed( $file ) {
	$path = dirname( __DIR__ ) . '/includes/quiz-seeds/' . $file;
	if ( ! is_readable( $path ) ) {
		return array();
	}
	$rows = include $path;
	return is_array( $rows ) ? $rows : array();
}

function option_lengths( array $q ) {
	$opts = array(
		'a' => strlen( trim( (string) ( $q['option_a'] ?? '' ) ) ),
		'b' => strlen( trim( (string) ( $q['option_b'] ?? '' ) ) ),
		'c' => strlen( trim( (string) ( $q['option_c'] ?? '' ) ) ),
		'd' => strlen( trim( (string) ( $q['option_d'] ?? '' ) ) ),
	);
	return $opts;
}

function analyze_deck( $label, array $questions ) {
	$empty_d     = 0;
	$cue_flags   = 0;
	$max_ratio   = 0.0;
	$samples     = array();

	foreach ( $questions as $idx => $q ) {
		$lens = option_lengths( $q );
		if ( 0 === $lens['d'] ) {
			++$empty_d;
		}

		$nonzero = array_values( array_filter( $lens ) );
		if ( count( $nonzero ) < 2 ) {
			continue;
		}

		$max = max( $nonzero );
		$min = min( $nonzero );
		$ratio = $min > 0 ? ( $max / $min ) : ( $max > 0 ? 999.0 : 1.0 );
		if ( $ratio > $max_ratio ) {
			$max_ratio = $ratio;
		}

		$correct = strtolower( (string) ( $q['correct_option'] ?? '' ) );
		$correct_len = isset( $lens[ $correct ] ) ? $lens[ $correct ] : 0;
		$others = array();
		foreach ( $lens as $k => $len ) {
			if ( $k === $correct || 0 === $len ) {
				continue;
			}
			$others[] = $len;
		}
		if ( empty( $others ) ) {
			continue;
		}
		$avg_other = array_sum( $others ) / count( $others );
		if ( $correct_len >= 1.8 * $avg_other && $correct_len >= 80 ) {
			++$cue_flags;
			if ( count( $samples ) < 5 ) {
				$samples[] = array(
					'num'     => $idx + 1,
					'correct' => strtoupper( $correct ),
					'correct_len' => $correct_len,
					'avg_other'   => (int) round( $avg_other ),
					'front'   => substr( (string) ( $q['question_text'] ?? '' ), 0, 72 ) . '...',
				);
			}
		}
	}

	return array(
		'label'      => $label,
		'count'      => count( $questions ),
		'empty_d'    => $empty_d,
		'cue_flags'  => $cue_flags,
		'max_ratio'  => round( $max_ratio, 2 ),
		'samples'    => $samples,
		'q1'         => isset( $questions[0]['question_text'] ) ? substr( (string) $questions[0]['question_text'], 0, 100 ) : '',
	);
}

$sets = array(
	'LCSW Form A (seed)' => load_seed( 'lcsw-aswb-form-a.php' ),
	'LCSW Form B (seed)' => load_seed( 'lcsw-aswb-form-b.php' ),
	'LMFT Form A (final)' => load_seed( 'lmft-clinical-form-a.php' ),
	'LMFT Form B (final)' => load_seed( 'lmft-clinical-form-b.php' ),
);

echo "=== LCSW ASWB Form A/B Answer-Cue Audit ===\n\n";

foreach ( $sets as $name => $questions ) {
	$r = analyze_deck( $name, $questions );
	echo "{$r['label']}\n";
	echo "  Questions: {$r['count']}\n";
	echo "  Empty option_d: {$r['empty_d']}\n";
	echo "  Answer-cue flagged items (correct >=1.8x avg other, correct >=80 chars): {$r['cue_flags']}\n";
	echo "  Max option length ratio: {$r['max_ratio']}\n";
	echo "  Q1: {$r['q1']}\n";
	if ( ! empty( $r['samples'] ) ) {
		echo "  Sample cue-flagged items:\n";
		foreach ( $r['samples'] as $s ) {
			echo "    Q{$s['num']} correct {$s['correct']} len={$s['correct_len']} avg_other={$s['avg_other']}: {$s['front']}\n";
		}
	}
	echo "\n";
}

echo "Data source for learner forms:\n";
echo "  - PHP seeds: includes/quiz-seeds/lcsw-aswb-form-a.php, lcsw-aswb-form-b.php\n";
echo "  - Sync class: includes/class-cta-lcsw-aswb-sync.php::sync_assessments()\n";
echo "  - DB tables: wp_cta_quizzes + wp_cta_quiz_questions (via replace_form_quiz)\n";
echo "  - Printable DOCX: assets/course-materials/lcsw-aswb/simulations/CTA_LCSW_2026_*_122_Question_Exam_v1.0.docx\n\n";

echo "Alternate rebuilt seeds searched: none found (no lcsw-aswb-form-a-items-*, no v2 docx, no form sync classes).\n";

<?php
/**
 * Quiz question partial.
 *
 * @package CTA_LMS
 *
 * @var object $question
 * @var int    $question_number
 * @var string $user_answer
 * @var bool   $review
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$options = array(
	'a' => isset( $question->option_a ) ? (string) $question->option_a : '',
	'b' => isset( $question->option_b ) ? (string) $question->option_b : '',
	'c' => isset( $question->option_c ) ? (string) $question->option_c : '',
	'd' => isset( $question->option_d ) ? (string) $question->option_d : '',
);
?>
<fieldset class="cta-quiz-question card" data-question-id="<?php echo esc_attr( $question->id ); ?>">
	<legend class="cta-quiz-question__legend">
		<span class="cta-quiz-question__number"><?php echo esc_html( (string) $question_number ); ?>.</span>
		<?php echo esc_html( $question->question_text ); ?>
	</legend>
	<div class="cta-quiz-question__options">
		<?php foreach ( $options as $key => $label ) : ?>
			<?php if ( '' === trim( (string) $label ) ) : ?>
				<?php continue; ?>
			<?php endif; ?>
			<label class="cta-quiz-option">
				<input
					type="radio"
					name="answer_<?php echo esc_attr( $question->id ); ?>"
					value="<?php echo esc_attr( $key ); ?>"
					<?php checked( $user_answer, $key ); ?>
				>
				<span class="cta-quiz-option__label"><?php echo esc_html( strtoupper( $key ) . '. ' . $label ); ?></span>
			</label>
		<?php endforeach; ?>
	</div>
	<div class="cta-quiz-question__feedback" hidden></div>
</fieldset>

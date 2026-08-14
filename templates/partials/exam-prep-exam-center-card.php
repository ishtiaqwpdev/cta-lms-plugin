<?php
/**
 * Single assessment card for the Exam Center.
 *
 * @package CTA_LMS
 *
 * @var array  $exam              Enriched exam/bank card row.
 * @var string $start_label       Primary action label when not attempted.
 * @var string $retake_label      Primary action label when attempted.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $exam ) || ! is_array( $exam ) ) {
	return;
}

$quiz_url         = (string) ( $exam['url'] ?? '' );
$has_attempts     = ! empty( $exam['has_attempts'] );
$attempt_count    = (int) ( $exam['attempt_count'] ?? 0 );
$best_score       = isset( $exam['best_score'] ) ? (int) $exam['best_score'] : null;
$latest_score     = isset( $exam['latest_score'] ) ? (int) $exam['latest_score'] : null;
$passed           = ! empty( $exam['passed'] );
$question_count   = (int) ( $exam['question_count'] ?? 0 );
$type_label       = (string) ( $exam['type_label'] ?? '' );
$category_label   = (string) ( $exam['category_label'] ?? '' );
$category         = sanitize_key( (string) ( $exam['category'] ?? 'full_simulation' ) );
$title            = (string) ( $exam['title'] ?? '' );
$review_materials = isset( $exam['review_materials'] ) ? (array) $exam['review_materials'] : array();
$attempts         = isset( $exam['attempts'] ) ? (array) $exam['attempts'] : array();
$card_id          = 'cta-ec-card-' . (int) ( $exam['quiz_id'] ?? 0 );
$start_label      = ! empty( $start_label ) ? (string) $start_label : __( 'Start Exam', 'cta-lms' );
$retake_label     = ! empty( $retake_label ) ? (string) $retake_label : __( 'Retake Exam', 'cta-lms' );
$badge_class      = 'cta-ec__badge--category cta-ec__badge--' . str_replace( '_', '-', $category );
?>
<article class="cta-ec__card cta-ec__card--<?php echo esc_attr( str_replace( '_', '-', $category ) ); ?>" aria-labelledby="<?php echo esc_attr( $card_id ); ?>-title">
	<div class="cta-ec__card-head">
		<div class="cta-ec__card-badges">
			<?php if ( '' !== $category_label ) : ?>
				<span class="<?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $category_label ); ?></span>
			<?php endif; ?>
			<?php if ( '' !== $type_label && $type_label !== $category_label ) : ?>
				<span class="cta-ec__badge cta-ec__badge--type"><?php echo esc_html( $type_label ); ?></span>
			<?php endif; ?>
			<?php if ( $question_count > 0 ) : ?>
				<span class="cta-ec__badge cta-ec__badge--count">
					<?php
					printf(
						/* translators: %d: question count */
						esc_html( _n( '%d question', '%d questions', $question_count, 'cta-lms' ) ),
						$question_count
					);
					?>
				</span>
			<?php endif; ?>
		</div>
		<?php if ( $has_attempts ) : ?>
			<div class="cta-ec__score-block" aria-label="<?php esc_attr_e( 'Your scores', 'cta-lms' ); ?>">
				<?php if ( null !== $best_score ) : ?>
					<div class="cta-ec__score cta-ec__score--best">
						<span class="cta-ec__score-label"><?php esc_html_e( 'Best', 'cta-lms' ); ?></span>
						<span class="cta-ec__score-value <?php echo $passed ? 'is-passed' : ''; ?>">
							<?php echo esc_html( (string) $best_score ); ?>%
						</span>
					</div>
				<?php endif; ?>
				<?php if ( null !== $latest_score && $latest_score !== $best_score ) : ?>
					<div class="cta-ec__score cta-ec__score--latest">
						<span class="cta-ec__score-label"><?php esc_html_e( 'Latest', 'cta-lms' ); ?></span>
						<span class="cta-ec__score-value"><?php echo esc_html( (string) $latest_score ); ?>%</span>
					</div>
				<?php endif; ?>
				<?php if ( $passed ) : ?>
					<span class="cta-ec__passed-pill"><?php esc_html_e( 'Passed', 'cta-lms' ); ?></span>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

	<h3 class="cta-ec__card-title" id="<?php echo esc_attr( $card_id ); ?>-title"><?php echo esc_html( $title ); ?></h3>

	<?php if ( $has_attempts && $attempt_count > 0 ) : ?>
		<p class="cta-ec__attempt-meta">
			<?php
			printf(
				/* translators: %d: number of attempts */
				esc_html( _n( '%d attempt recorded', '%d attempts recorded', $attempt_count, 'cta-lms' ) ),
				$attempt_count
			);
			?>
		</p>
	<?php endif; ?>

	<div class="cta-ec__card-actions">
		<?php if ( $quiz_url && '#' !== $quiz_url ) : ?>
			<a class="btn btn-primary" href="<?php echo esc_url( $quiz_url ); ?>">
				<?php echo esc_html( $has_attempts ? $retake_label : $start_label ); ?>
			</a>
		<?php endif; ?>

		<?php if ( $has_attempts && $quiz_url && '#' !== $quiz_url ) : ?>
			<a class="btn btn-outline" href="<?php echo esc_url( $quiz_url ); ?>">
				<?php esc_html_e( 'Review Results', 'cta-lms' ); ?>
			</a>
		<?php endif; ?>
	</div>

	<?php if ( $has_attempts && ! empty( $review_materials ) ) : ?>
		<div class="cta-ec__review-links">
			<p class="cta-ec__review-heading"><?php esc_html_e( 'Post-exam review', 'cta-lms' ); ?></p>
			<ul class="cta-ec__review-list">
				<?php foreach ( $review_materials as $material ) : ?>
					<?php
					$mat_label  = (string) ( $material['label'] ?? '' );
					$mat_url    = (string) ( $material['url'] ?? '' );
					$accessible = ! empty( $material['accessible'] );
					?>
					<li class="cta-ec__review-item">
						<?php if ( $accessible && '' !== $mat_url ) : ?>
							<a
								class="cta-ec__review-link"
								href="<?php echo esc_url( $mat_url ); ?>"
								target="_blank"
								rel="noopener noreferrer"
							>
								<?php echo esc_html( $mat_label ); ?>
							</a>
						<?php else : ?>
							<span
								class="cta-ec__review-link cta-ec__review-link--locked"
								<?php if ( ! empty( $material['lock_message'] ) ) : ?>
									title="<?php echo esc_attr( (string) $material['lock_message'] ); ?>"
								<?php endif; ?>
							>
								<?php echo esc_html( $mat_label ); ?>
								<span class="cta-ec__lock-icon" aria-hidden="true">&#128274;</span>
								<span class="screen-reader-text"><?php esc_html_e( '(locked)', 'cta-lms' ); ?></span>
							</span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php if ( $has_attempts && count( $attempts ) > 1 ) : ?>
		<details class="cta-ec__history">
			<summary class="cta-ec__history-toggle">
				<?php esc_html_e( 'Attempt history', 'cta-lms' ); ?>
			</summary>
			<ul class="cta-ec__history-list">
				<?php foreach ( $attempts as $attempt ) : ?>
					<li class="cta-ec__history-item">
						<span class="cta-ec__history-score <?php echo ! empty( $attempt['passed'] ) ? 'is-passed' : ''; ?>">
							<?php echo esc_html( (string) (int) ( $attempt['score'] ?? 0 ) ); ?>%
						</span>
						<span class="cta-ec__history-meta">
							<?php
							$attempt_num   = (int) ( $attempt['attempt_number'] ?? 0 );
							$attempt_label = ! empty( $attempt['completed_label'] )
								? (string) $attempt['completed_label']
								: __( 'Completed', 'cta-lms' );
							printf(
								/* translators: 1: attempt number, 2: date */
								esc_html__( 'Attempt %1$d — %2$s', 'cta-lms' ),
								$attempt_num,
								esc_html( $attempt_label )
							);
							?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		</details>
	<?php endif; ?>
</article>

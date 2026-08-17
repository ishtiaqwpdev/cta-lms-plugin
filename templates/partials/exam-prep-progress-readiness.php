<?php
/**
 * Exam Prep Progress / Readiness dashboard.
 *
 * @package CTA_LMS
 *
 * @var array $progress_readiness_data Dashboard payload.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data           = isset( $progress_readiness_data ) && is_array( $progress_readiness_data )
	? $progress_readiness_data
	: CTA_Exam_Prep_Progress_Readiness::empty_data();
$overview       = (array) ( $data['overview'] ?? array() );
$flashcards     = (array) ( $data['flashcards'] ?? array() );
$exam_summary   = (array) ( $data['exam_summary'] ?? array() );
$exams          = (array) ( $data['exams'] ?? array() );
$workbook_banks = (array) ( $data['workbook_banks'] ?? array() );
$readiness      = (array) ( $data['readiness'] ?? array() );
$remediation    = (array) ( $data['remediation'] ?? array() );
$guidance       = (array) ( $data['guidance'] ?? array() );
$percent        = max( 0, min( 100, (int) ( $overview['percent'] ?? 0 ) ) );
?>
<div
	class="cta-pr"
	data-cta-progress-readiness
	data-flashcard-storage-key="<?php echo esc_attr( (string) ( $flashcards['storage_key'] ?? '' ) ); ?>"
	data-flashcard-total="<?php echo esc_attr( (string) (int) ( $flashcards['total_count'] ?? 0 ) ); ?>"
>
	<p class="cta-ep-home-section__lede">
		<?php esc_html_e( 'A consolidated view of program completion, assessment performance, flashcard study, readiness tools, and recommended next steps.', 'cta-lms' ); ?>
	</p>

	<section class="cta-pr__overview" aria-labelledby="cta-pr-overview-title">
		<div class="cta-pr__completion-card">
			<div class="cta-pr__ring" style="--cta-pr-progress: <?php echo esc_attr( (string) $percent ); ?>%;" aria-label="<?php echo esc_attr( sprintf( __( '%d percent complete', 'cta-lms' ), $percent ) ); ?>">
				<div class="cta-pr__ring-inner">
					<strong><?php echo esc_html( (string) $percent ); ?>%</strong>
					<span><?php esc_html_e( 'complete', 'cta-lms' ); ?></span>
				</div>
			</div>
			<div class="cta-pr__completion-copy">
				<h3 id="cta-pr-overview-title"><?php esc_html_e( 'Program Completion', 'cta-lms' ); ?></h3>
				<p>
					<?php
					printf(
						/* translators: 1: completed modules, 2: total modules */
						esc_html__( '%1$d of %2$d workbooks completed', 'cta-lms' ),
						(int) ( $overview['completed_count'] ?? 0 ),
						(int) ( $overview['total_count'] ?? 0 )
					);
					?>
				</p>
				<div class="progress cta-pr__progress">
					<div class="progress__track">
						<div class="progress__bar" style="width: <?php echo esc_attr( (string) $percent ); ?>%;"></div>
					</div>
				</div>
			</div>
		</div>

		<div class="cta-pr__stats">
			<div class="cta-pr__stat">
				<span class="cta-pr__stat-value"><?php echo esc_html( (string) (int) ( $exam_summary['attempted_count'] ?? 0 ) ); ?>/<?php echo esc_html( (string) (int) ( $exam_summary['total_count'] ?? 0 ) ); ?></span>
				<span class="cta-pr__stat-label"><?php esc_html_e( 'Practice exams attempted', 'cta-lms' ); ?></span>
			</div>
			<div class="cta-pr__stat">
				<span class="cta-pr__stat-value"><?php echo esc_html( (string) (int) ( $exam_summary['passed_count'] ?? 0 ) ); ?></span>
				<span class="cta-pr__stat-label"><?php esc_html_e( 'Assessments passed', 'cta-lms' ); ?></span>
			</div>
			<div class="cta-pr__stat cta-pr__stat--flashcards">
				<span class="cta-pr__stat-value" data-cta-pr-flashcard-reviewed>—</span>
				<span class="cta-pr__stat-label"><?php esc_html_e( 'Flashcards reviewed', 'cta-lms' ); ?></span>
				<span class="cta-pr__stat-note" data-cta-pr-flashcard-note>
					<?php echo ! empty( $flashcards['has_content'] ) ? esc_html__( 'Saved in this browser', 'cta-lms' ) : esc_html__( 'Deck not yet published', 'cta-lms' ); ?>
				</span>
			</div>
		</div>
	</section>

	<?php if ( ! empty( $exams ) ) : ?>
		<section class="cta-pr__section" aria-labelledby="cta-pr-exams-title">
			<header class="cta-pr__section-head">
				<div>
					<h3 id="cta-pr-exams-title"><?php esc_html_e( 'Practice Exam Performance', 'cta-lms' ); ?></h3>
					<p><?php esc_html_e( 'Completed attempts and best scores from Practice Exams.', 'cta-lms' ); ?></p>
				</div>
				<?php if ( ! empty( $exam_summary['url'] ) ) : ?>
					<a class="btn btn-outline btn--sm" href="<?php echo esc_url( (string) $exam_summary['url'] ); ?>"><?php esc_html_e( 'Open Practice Exams', 'cta-lms' ); ?></a>
				<?php endif; ?>
			</header>
			<div class="cta-pr__assessment-grid">
				<?php foreach ( $exams as $exam ) : ?>
					<article class="cta-pr__assessment-card">
						<div class="cta-pr__assessment-top">
							<span class="cta-pr__pill"><?php echo esc_html( (string) ( $exam['type_label'] ?? __( 'Practice Assessment', 'cta-lms' ) ) ); ?></span>
							<?php if ( ! empty( $exam['passed'] ) ) : ?>
								<span class="cta-pr__status cta-pr__status--passed"><?php esc_html_e( 'Passed', 'cta-lms' ); ?></span>
							<?php elseif ( ! empty( $exam['has_attempts'] ) ) : ?>
								<span class="cta-pr__status cta-pr__status--review"><?php esc_html_e( 'Review', 'cta-lms' ); ?></span>
							<?php else : ?>
								<span class="cta-pr__status"><?php esc_html_e( 'Not attempted', 'cta-lms' ); ?></span>
							<?php endif; ?>
						</div>
						<h4><?php echo esc_html( (string) ( $exam['title'] ?? '' ) ); ?></h4>
						<div class="cta-pr__assessment-meta">
							<span>
								<?php
								printf(
									/* translators: %d: attempt count */
									esc_html( _n( '%d attempt', '%d attempts', (int) ( $exam['attempt_count'] ?? 0 ), 'cta-lms' ) ),
									(int) ( $exam['attempt_count'] ?? 0 )
								);
								?>
							</span>
							<strong>
								<?php
								echo null !== ( $exam['best_score'] ?? null )
									? esc_html( sprintf( __( 'Best: %d%%', 'cta-lms' ), (int) $exam['best_score'] ) )
									: esc_html__( 'Best: —', 'cta-lms' );
								?>
							</strong>
						</div>
						<?php if ( ! empty( $exam['url'] ) ) : ?>
							<a class="cta-pr__text-link" href="<?php echo esc_url( (string) $exam['url'] ); ?>">
								<?php echo ! empty( $exam['has_attempts'] ) ? esc_html__( 'Review or retake', 'cta-lms' ) : esc_html__( 'Start assessment', 'cta-lms' ); ?> →
							</a>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( ! empty( $workbook_banks ) ) : ?>
		<section class="cta-pr__section" aria-labelledby="cta-pr-banks-title">
			<header class="cta-pr__section-head">
				<div>
					<h3 id="cta-pr-banks-title"><?php esc_html_e( 'Workbook Practice Banks', 'cta-lms' ); ?></h3>
					<p><?php esc_html_e( 'Attempt status and best score for each tracked workbook-level knowledge check.', 'cta-lms' ); ?></p>
				</div>
			</header>
			<div class="cta-pr__bank-list">
				<?php foreach ( $workbook_banks as $bank ) : ?>
					<a class="cta-pr__bank-row" href="<?php echo esc_url( (string) ( $bank['url'] ?? '' ) ); ?>">
						<span class="cta-pr__bank-title"><?php echo esc_html( (string) ( $bank['label'] ?? $bank['title'] ?? '' ) ); ?></span>
						<span class="cta-pr__bank-attempts">
							<?php
							printf(
								/* translators: %d: attempt count */
								esc_html( _n( '%d attempt', '%d attempts', (int) ( $bank['attempt_count'] ?? 0 ), 'cta-lms' ) ),
								(int) ( $bank['attempt_count'] ?? 0 )
							);
							?>
						</span>
						<span class="cta-pr__bank-score <?php echo ! empty( $bank['passed'] ) ? 'is-passed' : ''; ?>">
							<?php
							$status_label = (string) ( $bank['status_label'] ?? '' );
							if ( '' === $status_label && class_exists( 'CTA_Exam_Prep_Workbooks' ) ) {
								$status_label = CTA_Exam_Prep_Workbooks::get_practice_bank_status_label( (string) ( $bank['status'] ?? 'not_started' ) );
							}
							if ( '' !== $status_label ) {
								echo esc_html( $status_label );
								if ( null !== ( $bank['best_score'] ?? null ) ) {
									echo ' — ' . esc_html( sprintf( __( 'Best %d%%', 'cta-lms' ), (int) $bank['best_score'] ) );
								}
							} else {
								echo null !== ( $bank['best_score'] ?? null )
									? esc_html( sprintf( __( 'Best %d%%', 'cta-lms' ), (int) $bank['best_score'] ) )
									: esc_html__( 'Not Started', 'cta-lms' );
							}
							?>
						</span>
					</a>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<section class="cta-pr__section" id="cta-pr-readiness" aria-labelledby="cta-pr-readiness-title">
		<header class="cta-pr__section-head">
			<div>
				<h3 id="cta-pr-readiness-title"><?php esc_html_e( 'Readiness Tools', 'cta-lms' ); ?></h3>
				<p><?php esc_html_e( 'Use these program-specific tools to check readiness and organize final preparation.', 'cta-lms' ); ?></p>
			</div>
		</header>
		<?php if ( ! empty( $readiness ) ) : ?>
			<div class="cta-pr__resource-grid">
				<?php foreach ( $readiness as $resource ) : ?>
					<article class="cta-pr__resource-card">
						<div class="cta-pr__resource-badges">
							<span class="cta-pr__pill"><?php echo esc_html( (string) ( $resource['format_label'] ?? '' ) ); ?></span>
							<span class="cta-pr__pill cta-pr__pill--<?php echo esc_attr( (string) ( $resource['mode'] ?? 'download' ) ); ?>">
								<?php echo 'view' === ( $resource['mode'] ?? '' ) ? esc_html__( 'View in browser', 'cta-lms' ) : esc_html__( 'Download', 'cta-lms' ); ?>
							</span>
						</div>
						<h4><?php echo esc_html( (string) ( $resource['title'] ?? '' ) ); ?></h4>
						<p><?php echo esc_html( (string) ( $resource['description'] ?? '' ) ); ?></p>
						<a class="btn <?php echo 'view' === ( $resource['mode'] ?? '' ) ? 'btn-primary' : 'btn-outline'; ?> btn--sm" href="<?php echo esc_url( (string) ( $resource['url'] ?? '' ) ); ?>" target="_blank" rel="noopener noreferrer">
							<?php echo esc_html( (string) ( $resource['action_label'] ?? __( 'Open', 'cta-lms' ) ) ); ?>
						</a>
					</article>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p class="cta-pr__empty"><?php esc_html_e( 'No separate readiness files are published for this program yet. Your completion and assessment data above remain your current readiness snapshot.', 'cta-lms' ); ?></p>
		<?php endif; ?>
	</section>

	<section class="cta-pr__section" id="cta-pr-remediation" aria-labelledby="cta-pr-remediation-title">
		<header class="cta-pr__section-head">
			<div>
				<h3 id="cta-pr-remediation-title"><?php esc_html_e( 'Remediation Guidance', 'cta-lms' ); ?></h3>
				<p><?php esc_html_e( 'Recommended next steps based on the aggregate assessment data currently tracked by the platform.', 'cta-lms' ); ?></p>
			</div>
		</header>
		<?php foreach ( $guidance as $item ) : ?>
			<div class="cta-pr__guidance cta-pr__guidance--<?php echo esc_attr( sanitize_key( (string) ( $item['tone'] ?? 'info' ) ) ); ?>">
				<div>
					<h4><?php echo esc_html( (string) ( $item['title'] ?? '' ) ); ?></h4>
					<p><?php echo esc_html( (string) ( $item['text'] ?? '' ) ); ?></p>
				</div>
				<?php if ( ! empty( $item['url'] ) ) : ?>
					<a class="btn btn-outline btn--sm" href="<?php echo esc_url( (string) $item['url'] ); ?>"<?php echo ! empty( $remediation ) && (string) $item['url'] === (string) ( $remediation[0]['url'] ?? '' ) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
						<?php echo esc_html( (string) ( $item['label'] ?? __( 'Open', 'cta-lms' ) ) ); ?>
					</a>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>

		<?php if ( ! empty( $remediation ) ) : ?>
			<div class="cta-pr__resource-grid cta-pr__resource-grid--remediation">
				<?php foreach ( $remediation as $resource ) : ?>
					<article class="cta-pr__resource-card">
						<div class="cta-pr__resource-badges">
							<span class="cta-pr__pill"><?php echo esc_html( (string) ( $resource['format_label'] ?? '' ) ); ?></span>
							<span class="cta-pr__pill cta-pr__pill--<?php echo esc_attr( (string) ( $resource['mode'] ?? 'download' ) ); ?>">
								<?php echo 'view' === ( $resource['mode'] ?? '' ) ? esc_html__( 'View in browser', 'cta-lms' ) : esc_html__( 'Download', 'cta-lms' ); ?>
							</span>
						</div>
						<h4><?php echo esc_html( (string) ( $resource['title'] ?? '' ) ); ?></h4>
						<p><?php echo esc_html( (string) ( $resource['description'] ?? '' ) ); ?></p>
						<a class="btn <?php echo 'view' === ( $resource['mode'] ?? '' ) ? 'btn-primary' : 'btn-outline'; ?> btn--sm" href="<?php echo esc_url( (string) ( $resource['url'] ?? '' ) ); ?>" target="_blank" rel="noopener noreferrer">
							<?php echo esc_html( (string) ( $resource['action_label'] ?? __( 'Open', 'cta-lms' ) ) ); ?>
						</a>
					</article>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<p class="cta-pr__data-note"><?php esc_html_e( 'Domain-level remediation will appear when assessment attempts store domain mappings. Current recommendations use completion, attempt, score, and pass/fail data only.', 'cta-lms' ); ?></p>
	</section>
</div>

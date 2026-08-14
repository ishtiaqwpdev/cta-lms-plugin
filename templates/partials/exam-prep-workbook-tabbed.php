<?php
/**
 * Tabbed in-page layout for exam prep workbook lesson content.
 *
 * @package CTA_LMS
 *
 * @var array  $workbook_tabs     Tabs from CTA_Exam_Prep_Workbook_Sections::build_tabs().
 * @var string $wb_download_url   Printable workbook URL.
 * @var string $bank_download_url Practice bank download URL.
 * @var array|null $practice_bank_action Resolved practice bank toolbar action.
 * @var object|null $practice_bank_resource Practice bank resource row.
 * @var string $prev_url          Previous workbook URL.
 * @var string $next_url          Next workbook URL.
 * @var bool   $module_complete   Whether module is complete.
 * @var object $module            Current module.
 * @var object $course            Course row.
 * @var array  $workbook_quiz_cards Quiz cards (for practice tab fallback).
 * @var int    $quiz_page_id      Quiz page ID.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $workbook_tabs ) ) {
	return;
}

$tab_count   = count( $workbook_tabs );
$initial_key = (string) ( $workbook_tabs[0]['key'] ?? 'overview' );

foreach ( $workbook_tabs as $tab ) {
	if ( ! empty( $tab['is_default'] ) ) {
		$initial_key = (string) $tab['key'];
		break;
	}
}
?>
<div
	class="cta-ep-workbook-tabbed"
	data-cta-ep-workbook-tabs
	data-tab-count="<?php echo esc_attr( (string) $tab_count ); ?>"
>
	<div class="cta-ep-workbook-toolbar" data-cta-ep-workbook-toolbar>
		<?php if ( $wb_download_url || ! empty( $practice_bank_action ) ) : ?>
			<div class="cta-ep-workbook-actions cta-ep-workbook-actions--toolbar">
				<?php if ( $wb_download_url ) : ?>
					<a class="btn btn-outline btn--sm" href="<?php echo esc_url( $wb_download_url ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Download Printable Workbook (DOCX)', 'cta-lms' ); ?>
					</a>
				<?php endif; ?>
				<?php if ( ! empty( $practice_bank_action ) ) : ?>
					<?php
					$pb_mode   = (string) ( $practice_bank_action['mode'] ?? '' );
					$pb_url    = (string) ( $practice_bank_action['url'] ?? '' );
					$pb_label  = (string) ( $practice_bank_action['label'] ?? __( 'Practice Bank', 'cta-lms' ) );
					$pb_tag    = (string) ( $practice_bank_action['category_label'] ?? __( 'Workbook Practice Bank', 'cta-lms' ) );
					$pb_docx   = (string) ( $practice_bank_action['docx_url'] ?? '' );
					$pb_class  = ( 'download' === $pb_mode ) ? 'btn btn-outline btn--sm' : 'btn btn-primary btn--sm';
					$pb_target = ( 'download' === $pb_mode ) ? '_blank' : '';
					?>
					<div class="cta-ep-workbook-practice-bank">
						<span class="cta-assessment-tag cta-assessment-tag--workbook"><?php echo esc_html( $pb_tag ); ?></span>
						<a
							class="<?php echo esc_attr( $pb_class ); ?>"
							href="<?php echo esc_url( $pb_url ); ?>"
							<?php if ( $pb_target ) : ?>
								target="<?php echo esc_attr( $pb_target ); ?>" rel="noopener noreferrer"
							<?php endif; ?>
						>
							<?php echo esc_html( $pb_label ); ?>
						</a>
					</div>
					<?php if ( '' !== $pb_docx && 'download' !== $pb_mode ) : ?>
						<a class="btn btn-outline btn--sm" href="<?php echo esc_url( $pb_docx ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Download Practice Bank (DOCX)', 'cta-lms' ); ?>
						</a>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="cta-ep-workbook-toolbar__nav" data-cta-workbook-nav>
			<?php if ( $prev_url ) : ?>
				<a href="<?php echo esc_url( $prev_url ); ?>" class="btn btn-outline btn--sm">&larr; <?php esc_html_e( 'Previous Workbook', 'cta-lms' ); ?></a>
			<?php endif; ?>
			<?php if ( $next_url ) : ?>
				<a href="<?php echo esc_url( $next_url ); ?>" class="btn btn-outline btn--sm cta-next-module-link"><?php esc_html_e( 'Next Workbook', 'cta-lms' ); ?> &rarr;</a>
			<?php endif; ?>
		</div>

		<div class="cta-ep-workbook-progress" data-cta-ep-workbook-progress aria-live="polite">
			<div class="cta-ep-workbook-progress__meta">
				<span class="cta-ep-workbook-progress__label" data-cta-ep-workbook-progress-label>
					<?php
					printf(
						/* translators: 1: current section number, 2: total sections */
						esc_html__( 'Section %1$d of %2$d', 'cta-lms' ),
						1,
						(int) $tab_count
					);
					?>
				</span>
			</div>
			<div class="cta-ep-workbook-progress__track" aria-hidden="true">
				<div class="cta-ep-workbook-progress__bar" data-cta-ep-workbook-progress-bar style="width: <?php echo esc_attr( (string) round( 100 / max( 1, $tab_count ) ) ); ?>%;"></div>
			</div>
		</div>

		<div class="cta-ep-workbook-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Workbook sections', 'cta-lms' ); ?>">
			<?php foreach ( $workbook_tabs as $index => $tab ) : ?>
				<?php
				$tab_key   = (string) ( $tab['key'] ?? 'tab-' . $index );
				$is_active = $tab_key === $initial_key;
				?>
				<button
					type="button"
					class="cta-ep-workbook-tabs__tab<?php echo $is_active ? ' is-active' : ''; ?>"
					role="tab"
					id="cta-ep-wb-tab-<?php echo esc_attr( $tab_key ); ?>"
					aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
					aria-controls="cta-ep-wb-panel-<?php echo esc_attr( $tab_key ); ?>"
					data-cta-ep-workbook-tab="<?php echo esc_attr( $tab_key ); ?>"
					data-tab-index="<?php echo esc_attr( (string) ( (int) $index + 1 ) ); ?>"
				>
					<?php echo esc_html( (string) ( $tab['label'] ?? '' ) ); ?>
				</button>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="cta-ep-workbook-panels">
		<?php foreach ( $workbook_tabs as $index => $tab ) : ?>
			<?php
			$tab_key   = (string) ( $tab['key'] ?? 'tab-' . $index );
			$is_active = $tab_key === $initial_key;
			$tab_type  = (string) ( $tab['type'] ?? 'lesson' );
			?>
			<section
				class="cta-ep-workbook-panel<?php echo $is_active ? ' is-active' : ''; ?>"
				id="cta-ep-wb-panel-<?php echo esc_attr( $tab_key ); ?>"
				role="tabpanel"
				aria-labelledby="cta-ep-wb-tab-<?php echo esc_attr( $tab_key ); ?>"
				data-cta-ep-workbook-panel="<?php echo esc_attr( $tab_key ); ?>"
				<?php echo $is_active ? '' : 'hidden'; ?>
			>
				<div class="cta-exam-lesson cta-ep-workbook-section">
					<?php if ( 'practice' === $tab_type ) : ?>
						<?php
						$cards = isset( $tab['quiz_cards'] ) ? (array) $tab['quiz_cards'] : array();
						$qpid  = absint( $tab['quiz_page_id'] ?? $quiz_page_id ?? 0 );
						?>
						<div class="cta-ep-workbook-section__body">
							<?php
							$wb_bank_label = class_exists( 'CTA_Exam_Prep_Workbooks' )
								? CTA_Exam_Prep_Workbooks::get_workbook_practice_bank_button_label( $module ?? null )
								: __( 'Practice Bank', 'cta-lms' );
							$wb_bank_tag = class_exists( 'CTA_Exam_Prep_Workbooks' )
								? CTA_Exam_Prep_Workbooks::get_assessment_category_label( 'workbook_bank', ! empty( $cards[0]['quiz'] ) ? $cards[0]['quiz'] : null )
								: __( 'Workbook Practice Bank', 'cta-lms' );
							?>
							<span class="cta-assessment-tag cta-assessment-tag--workbook"><?php echo esc_html( $wb_bank_tag ); ?></span>
							<h2 class="dashboard-section__title"><?php echo esc_html( $wb_bank_label ); ?></h2>
							<p class="cta-ep-workbook-section__hint">
								<?php esc_html_e( 'This practice bank covers only this workbook — not the full program exam.', 'cta-lms' ); ?>
							</p>
							<?php if ( ! empty( $cards ) ) : ?>
								<ul class="cta-exam-assessment-list">
									<?php foreach ( $cards as $card ) : ?>
										<?php
										$card_quiz = $card['quiz'] ?? null;
										$card_label = class_exists( 'CTA_Exam_Prep_Workbooks' )
											? CTA_Exam_Prep_Workbooks::get_workbook_practice_bank_button_label( $module ?? null, $card_quiz )
											: ( $card_quiz ? (string) $card_quiz->title : $wb_bank_label );
										?>
										<li class="cta-exam-assessment-list__item">
											<div class="cta-exam-assessment-list__meta">
												<strong><?php echo esc_html( $card_label ); ?></strong>
												<?php if ( ! empty( $card['passed'] ) ) : ?>
													<span class="badge badge--success"><?php echo esc_html__( 'Passed', 'cta-lms' ); ?> — <?php echo esc_html( (string) (int) $card['best']->score ); ?>%</span>
												<?php elseif ( ! empty( $card['best'] ) ) : ?>
													<span class="badge"><?php echo esc_html__( 'Best score', 'cta-lms' ); ?>: <?php echo esc_html( (string) (int) $card['best']->score ); ?>%</span>
												<?php else : ?>
													<span class="badge"><?php echo esc_html__( 'Not started', 'cta-lms' ); ?></span>
												<?php endif; ?>
											</div>
											<?php if ( $qpid && ! empty( $card['url'] ) && '#' !== $card['url'] ) : ?>
												<a href="<?php echo esc_url( $card['url'] ); ?>" class="btn btn-primary btn--sm cta-quiz-btn">
													<?php echo ! empty( $card['passed'] ) ? esc_html__( 'Retake', 'cta-lms' ) : esc_html__( 'Start Practice Bank', 'cta-lms' ); ?>
												</a>
											<?php endif; ?>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php elseif ( ! empty( $tab['bank_url'] ) ) : ?>
								<p><?php esc_html_e( 'Use the downloadable practice bank link above, or check back when the online quiz is published for this program.', 'cta-lms' ); ?></p>
							<?php endif; ?>
						</div>
					<?php else : ?>
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo $tab['html'] ?? '';
						?>
					<?php endif; ?>
				</div>
			</section>
		<?php endforeach; ?>
	</div>

	<div class="course-player__lesson-actions cta-ep-workbook-tabbed__actions" data-course-player-actions>
		<?php if ( $module_complete ) : ?>
			<button type="button" class="btn btn-primary course-player__action-btn" id="cta-mark-complete" disabled>
				<?php echo esc_html__( 'Completed', 'cta-lms' ); ?>
			</button>
		<?php else : ?>
			<button
				type="button"
				class="btn btn-primary course-player__action-btn"
				id="cta-mark-complete"
				data-module-id="<?php echo esc_attr( (int) $module->id ); ?>"
				data-course-id="<?php echo esc_attr( (int) $course->id ); ?>"
			>
				<?php esc_html_e( 'Mark Workbook Complete', 'cta-lms' ); ?>
			</button>
		<?php endif; ?>
	</div>
</div>

<?php
/**
 * Reusable "Getting Started / Exam Strategy" section for exam-prep Course Home.
 *
 * @package CTA_LMS
 *
 * @var object $course              Course row.
 * @var array  $getting_started     Config from CTA_Exam_Prep_Getting_Started::get_config_for_course().
 * @var string $first_workbook_url Optional URL to first workbook module.
 * @var string $workbooks_list_url  URL to workbooks overview list.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $getting_started ) || ! is_array( $getting_started ) ) {
	return;
}

$display_title = function_exists( 'cta_lms_get_course_display_title' )
	? cta_lms_get_course_display_title( $course )
	: (string) $course->title;

$orientation     = isset( $getting_started['orientation'] ) ? (array) $getting_started['orientation'] : array();
$roadmap_steps   = isset( $getting_started['roadmap_steps'] ) ? (array) $getting_started['roadmap_steps'] : array();
$study_sequence  = isset( $getting_started['study_sequence'] ) ? (array) $getting_started['study_sequence'] : array();
$exam_overview   = isset( $getting_started['exam_overview'] ) ? (array) $getting_started['exam_overview'] : array();
$study_schedules = isset( $getting_started['study_schedules'] ) ? (array) $getting_started['study_schedules'] : array();
$readiness       = isset( $getting_started['readiness'] ) ? (array) $getting_started['readiness'] : array();

$schedule_url   = (string) ( $study_schedules['combined_url'] ?? '' );
$readiness_url  = (string) ( $readiness['url'] ?? '' );
$has_schedules  = '' !== $schedule_url;
$has_readiness  = '' !== $readiness_url;
?>
<section class="cta-ep-getting-started" aria-labelledby="cta-ep-getting-started-title">
	<header class="cta-ep-getting-started__header">
		<p class="cta-ep-getting-started__eyebrow"><?php esc_html_e( 'Getting Started / Exam Strategy', 'cta-lms' ); ?></p>
		<h2 class="cta-ep-getting-started__title" id="cta-ep-getting-started-title"><?php echo esc_html( $display_title ); ?></h2>
		<?php if ( ! empty( $orientation['intro'] ) ) : ?>
			<p class="cta-ep-getting-started__intro"><?php echo esc_html( (string) $orientation['intro'] ); ?></p>
		<?php endif; ?>
	</header>

	<?php if ( ! empty( $roadmap_steps ) ) : ?>
		<div class="cta-ep-getting-started__block">
			<h3 class="cta-ep-getting-started__heading"><?php esc_html_e( 'Program Roadmap', 'cta-lms' ); ?></h3>
			<ol class="cta-ep-roadmap" aria-label="<?php esc_attr_e( 'Program journey', 'cta-lms' ); ?>">
				<?php foreach ( $roadmap_steps as $index => $step ) : ?>
					<li class="cta-ep-roadmap__step">
						<span class="cta-ep-roadmap__marker" aria-hidden="true"><?php echo esc_html( (string) ( (int) $index + 1 ) ); ?></span>
						<div class="cta-ep-roadmap__content">
							<strong class="cta-ep-roadmap__label"><?php echo esc_html( (string) ( $step['label'] ?? '' ) ); ?></strong>
							<?php if ( ! empty( $step['description'] ) ) : ?>
								<span class="cta-ep-roadmap__desc"><?php echo esc_html( (string) $step['description'] ); ?></span>
							<?php endif; ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
	<?php endif; ?>

	<div class="cta-ep-getting-started__grid">
		<?php if ( ! empty( $study_sequence ) ) : ?>
			<div class="cta-ep-getting-started__block cta-ep-getting-started__block--card">
				<h3 class="cta-ep-getting-started__heading"><?php esc_html_e( 'Recommended Study Sequence', 'cta-lms' ); ?></h3>
				<ol class="cta-ep-sequence">
					<?php foreach ( $study_sequence as $seq_index => $item ) : ?>
						<li class="cta-ep-sequence__item">
							<span class="cta-ep-sequence__num"><?php echo esc_html( (string) ( (int) $seq_index + 1 ) ); ?></span>
							<div class="cta-ep-sequence__body">
								<strong class="cta-ep-sequence__title"><?php echo esc_html( (string) ( $item['title'] ?? '' ) ); ?></strong>
								<?php if ( ! empty( $item['description'] ) ) : ?>
									<p class="cta-ep-sequence__desc"><?php echo esc_html( (string) $item['description'] ); ?></p>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ol>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $exam_overview['exam_name'] ) || ! empty( $exam_overview['format'] ) || ! empty( $exam_overview['domains'] ) ) : ?>
			<div class="cta-ep-getting-started__block cta-ep-getting-started__block--card">
				<h3 class="cta-ep-getting-started__heading"><?php esc_html_e( 'Exam Overview', 'cta-lms' ); ?></h3>
				<?php if ( ! empty( $exam_overview['exam_name'] ) ) : ?>
					<p class="cta-ep-exam-overview__name"><strong><?php esc_html_e( 'Exam:', 'cta-lms' ); ?></strong> <?php echo esc_html( (string) $exam_overview['exam_name'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $exam_overview['format'] ) ) : ?>
					<p class="cta-ep-exam-overview__format"><?php echo esc_html( (string) $exam_overview['format'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $exam_overview['domains'] ) && is_array( $exam_overview['domains'] ) ) : ?>
					<p class="cta-ep-exam-overview__domains-label"><strong><?php esc_html_e( 'Key areas covered:', 'cta-lms' ); ?></strong></p>
					<ul class="cta-ep-exam-overview__domains">
						<?php foreach ( $exam_overview['domains'] as $domain ) : ?>
							<li><?php echo esc_html( (string) $domain ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

	<div class="cta-ep-getting-started__grid cta-ep-getting-started__grid--tools">
		<?php if ( ! empty( $study_schedules['intro'] ) || $has_schedules ) : ?>
			<div class="cta-ep-getting-started__block cta-ep-getting-started__block--card">
				<h3 class="cta-ep-getting-started__heading"><?php esc_html_e( 'Study Schedules', 'cta-lms' ); ?></h3>
				<?php if ( ! empty( $study_schedules['intro'] ) ) : ?>
					<p class="cta-ep-getting-started__lede"><?php echo esc_html( (string) $study_schedules['intro'] ); ?></p>
				<?php endif; ?>
				<?php if ( $has_schedules ) : ?>
					<div class="cta-ep-schedule-links">
						<?php
						$schedule_options = array(
							'10' => __( '10-Week Schedule', 'cta-lms' ),
							'14' => __( '14-Week Schedule', 'cta-lms' ),
							'18' => __( '18-Week Schedule', 'cta-lms' ),
						);
						foreach ( $schedule_options as $weeks => $label ) :
							?>
							<a class="btn btn-secondary btn--sm cta-ep-schedule-links__btn" href="<?php echo esc_url( $schedule_url ); ?>" target="_blank" rel="noopener noreferrer">
								<?php echo esc_html( $label ); ?>
							</a>
						<?php endforeach; ?>
					</div>
					<?php if ( ! empty( $study_schedules['combined_title'] ) && ! preg_match( '/\bworkbook\s*\d+/i', (string) $study_schedules['combined_title'] ) ) : ?>
						<p class="cta-ep-getting-started__note">
							<a href="<?php echo esc_url( $schedule_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( (string) $study_schedules['combined_title'] ); ?></a>
						</p>
					<?php endif; ?>
				<?php else : ?>
					<p class="cta-ep-getting-started__note cta-ep-getting-started__note--muted"><?php esc_html_e( 'Study schedule downloads will appear here when available for this program.', 'cta-lms' ); ?></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $readiness['summary'] ) || $has_readiness ) : ?>
			<div class="cta-ep-getting-started__block cta-ep-getting-started__block--card">
				<h3 class="cta-ep-getting-started__heading"><?php esc_html_e( 'Readiness Guidance', 'cta-lms' ); ?></h3>
				<?php if ( ! empty( $readiness['summary'] ) ) : ?>
					<p class="cta-ep-getting-started__lede"><?php echo esc_html( (string) $readiness['summary'] ); ?></p>
				<?php endif; ?>
				<?php if ( $has_readiness ) : ?>
					<p>
						<a class="btn btn-primary btn--sm" href="<?php echo esc_url( $readiness_url ); ?>" target="_blank" rel="noopener noreferrer">
							<?php
							echo esc_html(
								! empty( $readiness['title'] )
									? (string) $readiness['title']
									: __( 'Open Readiness Self-Assessment', 'cta-lms' )
							);
							?>
						</a>
					</p>
				<?php else : ?>
					<p class="cta-ep-getting-started__note cta-ep-getting-started__note--muted"><?php esc_html_e( 'Readiness self-assessment tool will appear here when available for this program.', 'cta-lms' ); ?></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( ! empty( $workbooks_list_url ) ) : ?>
		<div class="cta-ep-getting-started__cta">
			<a class="btn btn-primary" href="<?php echo esc_url( $workbooks_list_url ); ?>">
				<?php esc_html_e( 'Go to Workbooks', 'cta-lms' ); ?>
			</a>
			<?php if ( ! empty( $first_workbook_url ) && $first_workbook_url !== $workbooks_list_url ) : ?>
				<a class="btn btn-outline" href="<?php echo esc_url( $first_workbook_url ); ?>">
					<?php esc_html_e( 'Begin with Start Here / Workbook 1', 'cta-lms' ); ?>
				</a>
			<?php endif; ?>
		</div>
	<?php elseif ( ! empty( $first_workbook_url ) ) : ?>
		<div class="cta-ep-getting-started__cta">
			<a class="btn btn-primary" href="<?php echo esc_url( $first_workbook_url ); ?>">
				<?php esc_html_e( 'Begin with Start Here / Workbook 1', 'cta-lms' ); ?>
			</a>
		</div>
	<?php endif; ?>
</section>

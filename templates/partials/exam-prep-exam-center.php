<?php
/**
 * Exam Prep Practice Exams (Exam Center) landing.
 *
 * @package CTA_LMS
 *
 * @var array $exam_center_data Center payload from CTA_Exam_Prep_Exam_Center.
 * @var object $course          Course row.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$center = isset( $exam_center_data ) && is_array( $exam_center_data )
	? $exam_center_data
	: ( class_exists( 'CTA_Exam_Prep_Exam_Center' ) && ! empty( $course ) && ! empty( $dashboard )
		? CTA_Exam_Prep_Exam_Center::get_center_data_for_course( $course, $dashboard )
		: CTA_Exam_Prep_Exam_Center::empty_center_data() );

$simulations       = isset( $center['simulations'] ) ? (array) $center['simulations'] : array();
$cumulative_banks  = isset( $center['cumulative_banks'] ) ? (array) $center['cumulative_banks'] : array();
$exam_count        = (int) ( $center['exam_count'] ?? 0 );
$simulation_count  = (int) ( $center['simulation_count'] ?? count( $simulations ) );
$cumulative_count  = (int) ( $center['cumulative_count'] ?? count( $cumulative_banks ) );
$attempted_count   = (int) ( $center['attempted_count'] ?? 0 );
$passed_count      = (int) ( $center['passed_count'] ?? 0 );
$has_simulations   = ! empty( $center['has_simulations'] ) && ! empty( $simulations );
$has_cumulative    = ! empty( $center['has_cumulative'] ) && ! empty( $cumulative_banks );
$has_exams         = $has_simulations || $has_cumulative;
?>
<div class="cta-ec" data-cta-exam-center>
	<p class="cta-ep-home-section__lede">
		<?php esc_html_e( 'Full-length simulations and cumulative practice banks live here. Workbook-specific practice banks stay on each workbook page — look for the “Workbook Practice Bank” tag on workbook toolbars.', 'cta-lms' ); ?>
	</p>

	<div class="cta-ec__stats">
		<div class="cta-ec__stat-card">
			<span class="cta-ec__stat-value"><?php echo esc_html( (string) $exam_count ); ?></span>
			<span class="cta-ec__stat-label"><?php esc_html_e( 'Program assessments', 'cta-lms' ); ?></span>
		</div>
		<div class="cta-ec__stat-card">
			<span class="cta-ec__stat-value"><?php echo esc_html( (string) $simulation_count ); ?></span>
			<span class="cta-ec__stat-label"><?php esc_html_e( 'Full simulations', 'cta-lms' ); ?></span>
		</div>
		<div class="cta-ec__stat-card">
			<span class="cta-ec__stat-value"><?php echo esc_html( (string) $attempted_count ); ?></span>
			<span class="cta-ec__stat-label"><?php esc_html_e( 'Attempts recorded', 'cta-lms' ); ?></span>
		</div>
	</div>

	<?php if ( ! $has_exams ) : ?>
		<div class="cta-ec__empty" role="status">
			<p class="cta-ec__empty-title"><?php esc_html_e( 'Practice exams coming soon', 'cta-lms' ); ?></p>
			<p class="cta-ec__empty-text">
				<?php esc_html_e( 'Full-length simulations for this program are being finalized. Workbook practice banks are available on each workbook page in the meantime.', 'cta-lms' ); ?>
			</p>
		</div>
	<?php else : ?>
		<?php if ( $has_simulations ) : ?>
			<section class="cta-ec__section" aria-labelledby="cta-ec-simulations-title">
				<header class="cta-ec__section-head">
					<h3 class="cta-ec__section-title" id="cta-ec-simulations-title"><?php esc_html_e( 'Full-Length Simulations', 'cta-lms' ); ?></h3>
					<p class="cta-ec__section-lede">
						<?php esc_html_e( 'Comprehensive program-wide exams — Form A/B, practice examinations, and final readiness simulations.', 'cta-lms' ); ?>
					</p>
				</header>
				<div class="cta-ec__grid">
					<?php foreach ( $simulations as $exam ) : ?>
						<?php
						$start_label  = __( 'Start Exam', 'cta-lms' );
						$retake_label = __( 'Retake Exam', 'cta-lms' );
						include CTA_PLUGIN_DIR . 'templates/partials/exam-prep-exam-center-card.php';
						?>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( $has_cumulative ) : ?>
			<section class="cta-ec__section" aria-labelledby="cta-ec-cumulative-title">
				<header class="cta-ec__section-head">
					<h3 class="cta-ec__section-title" id="cta-ec-cumulative-title"><?php esc_html_e( 'Cumulative Practice Banks', 'cta-lms' ); ?></h3>
					<p class="cta-ec__section-lede">
						<?php esc_html_e( 'Multi-workbook checkpoint assessments — shorter than full simulations, broader than a single workbook practice bank.', 'cta-lms' ); ?>
					</p>
				</header>
				<div class="cta-ec__grid">
					<?php foreach ( $cumulative_banks as $exam ) : ?>
						<?php
						$start_label  = __( 'Start Practice Bank', 'cta-lms' );
						$retake_label = __( 'Retake Practice Bank', 'cta-lms' );
						include CTA_PLUGIN_DIR . 'templates/partials/exam-prep-exam-center-card.php';
						?>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>
	<?php endif; ?>

	<div class="cta-ec__legend" aria-label="<?php esc_attr_e( 'Assessment type guide', 'cta-lms' ); ?>">
		<p class="cta-ec__legend-title"><?php esc_html_e( 'How to tell them apart', 'cta-lms' ); ?></p>
		<ul class="cta-ec__legend-list">
			<li class="cta-ec__legend-item">
				<span class="cta-assessment-tag cta-assessment-tag--workbook"><?php esc_html_e( 'Workbook Practice Bank', 'cta-lms' ); ?></span>
				<?php esc_html_e( '— on each workbook page; scoped to that workbook only.', 'cta-lms' ); ?>
			</li>
			<li class="cta-ec__legend-item">
				<span class="cta-assessment-tag cta-assessment-tag--cumulative"><?php esc_html_e( 'Cumulative Practice Bank', 'cta-lms' ); ?></span>
				<?php esc_html_e( '— listed above when your program includes checkpoint assessments.', 'cta-lms' ); ?>
			</li>
			<li class="cta-ec__legend-item">
				<span class="cta-assessment-tag cta-assessment-tag--simulation"><?php esc_html_e( 'Full Simulation', 'cta-lms' ); ?></span>
				<?php esc_html_e( '— comprehensive exams simulating the real test (Form A/B, final readiness).', 'cta-lms' ); ?>
			</li>
		</ul>
	</div>
</div>

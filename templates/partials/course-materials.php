<?php
/**
 * Course materials / downloads list for enrolled learners.
 *
 * @package CTA_LMS
 *
 * @var array  $resources   Resource rows.
 * @var array  $modules     Optional module rows for grouping labels.
 * @var string $heading     Optional section heading.
 * @var bool   $is_enrolled Whether the current user is enrolled.
 * @var bool   $show_locked When true, show a locked message for unenrolled users if resources exist.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$resources    = isset( $resources ) ? (array) $resources : array();
$modules      = isset( $modules ) ? (array) $modules : array();
$heading      = isset( $heading ) ? $heading : __( 'Course Materials', 'cta-lms' );
$is_enrolled  = ! empty( $is_enrolled );
$show_locked  = ! empty( $show_locked );
$has_resources = ! empty( $resources );

if ( ! $has_resources && ! $show_locked ) {
	return;
}

$grouped = class_exists( 'CTA_Course_Materials' )
	? CTA_Course_Materials::group_for_display( $resources, $modules )
	: array(
		'course'  => $resources,
		'modules' => array(),
	);

/**
 * Render one resource download row.
 *
 * @param object $resource Resource.
 */
$cta_render_material_item = static function ( $resource ) {
	$serve_url = class_exists( 'CTA_Course_Materials' )
		? CTA_Course_Materials::get_serve_url( (int) $resource->id )
		: '';
	$type_label = ! empty( $resource->file_type ) ? strtoupper( (string) $resource->file_type ) : '';
	?>
	<li class="cta-materials-list__item course-module-list__item">
		<div class="course-module-list__row">
			<span class="course-module-list__number" aria-hidden="true">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
			</span>
			<div class="course-module-list__info">
				<strong class="course-module-list__title"><?php echo esc_html( $resource->title ); ?></strong>
				<?php if ( $type_label ) : ?>
					<p class="course-module-list__desc"><?php echo esc_html( $type_label ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( $serve_url ) : ?>
				<a
					href="<?php echo esc_url( $serve_url ); ?>"
					class="btn btn-outline btn--sm cta-materials-list__download"
					target="_blank"
					rel="noopener noreferrer"
				>
					<?php echo esc_html__( 'Open / Download', 'cta-lms' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</li>
	<?php
};
?>
<section class="cta-materials-section course-player__resources-section" aria-labelledby="cta-materials-title">
	<h2 class="dashboard-section__title" id="cta-materials-title"><?php echo esc_html( $heading ); ?></h2>

	<?php if ( ! $is_enrolled ) : ?>
		<div class="cta-quiz-locked-message">
			<p><?php echo esc_html__( 'Enroll in this course to unlock downloadable materials.', 'cta-lms' ); ?></p>
		</div>
	<?php elseif ( ! $has_resources ) : ?>
		<p class="cta-empty-state cta-empty-state--inline"><?php echo esc_html__( 'No materials have been added to this course yet.', 'cta-lms' ); ?></p>
	<?php else : ?>
		<?php if ( ! empty( $grouped['course'] ) ) : ?>
			<ul class="cta-materials-list course-module-list">
				<?php foreach ( $grouped['course'] as $resource ) : ?>
					<?php $cta_render_material_item( $resource ); ?>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php foreach ( $grouped['modules'] as $module_group ) : ?>
			<h3 class="cta-materials-list__module-title" style="margin:1rem 0 0.5rem;font-size:1rem;">
				<?php echo esc_html( $module_group['title'] ); ?>
			</h3>
			<ul class="cta-materials-list course-module-list">
				<?php foreach ( $module_group['resources'] as $resource ) : ?>
					<?php $cta_render_material_item( $resource ); ?>
				<?php endforeach; ?>
			</ul>
		<?php endforeach; ?>
	<?php endif; ?>
</section>

<?php
/**
 * Exam Prep catalog template for [cta_exam_prep_catalog] shortcode.
 *
 * Same card/grid conventions as the CE catalog; Exam Prep only (no CE hours).
 *
 * @package CTA_LMS
 *
 * @var array  $courses Exam preparation program objects.
 * @var string $search  Current search term.
 * @var int    $columns Grid column count.
 * @var int    $limit   Program limit (-1 for all).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$course_count = is_array( $courses ) ? count( $courses ) : 0;
$grid_class   = 'cta-courses-grid cta-courses-grid--cols-' . absint( $columns );

$catalog_base_url = '';
if ( function_exists( 'cta_lms_get_linked_page_url' ) ) {
	$catalog_base_url = cta_lms_get_linked_page_url( 'cta_exam_prep_page_id' );
}
if ( ! $catalog_base_url && function_exists( 'get_permalink' ) ) {
	$catalog_base_url = (string) get_permalink();
}
?>
<div class="cta-plugin-wrapper">
<div class="cta-lms cta-course-catalog cta-exam-prep-catalog" data-limit="<?php echo esc_attr( (int) $limit ); ?>" data-product-type="exam_prep">
	<div class="cta-catalog-inner">
	<?php if ( empty( $courses ) ) : ?>
		<div class="cta-empty-state">
			<div class="cta-empty-state__icon" aria-hidden="true">&#128218;</div>
			<h3><?php echo esc_html__( 'No Exam Preparation programs available yet', 'cta-lms' ); ?></h3>
			<p><?php echo esc_html__( 'Check back soon — Exam Preparation programs are being prepared for launch.', 'cta-lms' ); ?></p>
		</div>
	<?php else : ?>
		<div class="cta-filter-bar">
			<div class="cta-filter-bar__row">
				<input
					type="text"
					id="cta-course-search"
					class="cta-filter-bar__search form-input"
					placeholder="<?php echo esc_attr__( 'Search programs...', 'cta-lms' ); ?>"
					value="<?php echo esc_attr( $search ); ?>"
					aria-label="<?php echo esc_attr__( 'Search Exam Preparation programs', 'cta-lms' ); ?>"
				>

				<div class="cta-filter-bar__pills" role="group" aria-label="<?php echo esc_attr__( 'Filter programs', 'cta-lms' ); ?>">
					<a
						href="<?php echo esc_url( $catalog_base_url ? $catalog_base_url : '#' ); ?>"
						class="cta-pill cta-pill--active"
						data-category=""
						role="button"
					>
						<span class="cta-pill__label"><?php echo esc_html__( 'All Programs', 'cta-lms' ); ?></span>
					</a>
				</div>

				<select id="cta-course-sort" class="cta-filter-bar__sort form-select" aria-label="<?php echo esc_attr__( 'Sort programs', 'cta-lms' ); ?>">
					<option value="default"><?php echo esc_html__( 'Sort by: Default', 'cta-lms' ); ?></option>
					<option value="price_low"><?php echo esc_html__( 'Price: Low to High', 'cta-lms' ); ?></option>
					<option value="price_high"><?php echo esc_html__( 'Price: High to Low', 'cta-lms' ); ?></option>
				</select>

				<span class="cta-filter-bar__count">
					<?php
					printf(
						/* translators: %d: number of programs */
						esc_html__( 'Showing %d programs', 'cta-lms' ),
						(int) $course_count
					);
					?>
				</span>
			</div>
		</div>

		<p class="cta-exam-prep-catalog__notice" style="margin:0 0 1.25rem;">
			<?php echo esc_html__( 'Exam Preparation Only — No CE Credit. These programs do not award continuing education hours or CE certificates.', 'cta-lms' ); ?>
		</p>

		<div id="cta-courses-loader" class="cta-loader" style="display:none" aria-hidden="true">
			<div class="cta-loader__spinner"></div>
			<p><?php echo esc_html__( 'Loading programs...', 'cta-lms' ); ?></p>
		</div>

		<div id="cta-courses-grid" class="<?php echo esc_attr( $grid_class ); ?>">
			<?php foreach ( $courses as $course ) : ?>
				<?php include CTA_PLUGIN_DIR . 'templates/partials/course-card.php'; ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	</div>
</div>
</div>

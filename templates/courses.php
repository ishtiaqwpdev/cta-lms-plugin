<?php
/**
 * Course catalog template for [cta_course_catalog] shortcode.
 *
 * @package CTA_LMS
 *
 * @var array  $courses         CE course objects (default grid).
 * @var array  $exam_courses    Exam preparation programs (separate section).
 * @var array  $categories      Unique category names.
 * @var string $active_category Active category filter.
 * @var string $search          Current search term.
 * @var int    $columns         Grid column count.
 * @var int    $limit           Course limit (-1 for all).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$course_count = is_array( $courses ) ? count( $courses ) : 0;
$exam_courses = isset( $exam_courses ) && is_array( $exam_courses ) ? $exam_courses : array();
$grid_class   = 'cta-courses-grid cta-courses-grid--cols-' . absint( $columns );
$alcoholism_course_url = isset( $alcoholism_course_url ) ? (string) $alcoholism_course_url : '';

$catalog_base_url = '';
if ( function_exists( 'cta_lms_get_linked_page_url' ) ) {
	$catalog_base_url = cta_lms_get_linked_page_url( 'cta_courses_page_id' );
}
if ( ! $catalog_base_url && function_exists( 'get_permalink' ) ) {
	$catalog_base_url = (string) get_permalink();
}
?>
<div class="cta-plugin-wrapper">
<div class="cta-lms cta-course-catalog" data-limit="<?php echo esc_attr( (int) $limit ); ?>" data-product-type="ce">
	<div class="cta-catalog-inner">
	<?php if ( empty( $courses ) && empty( $exam_courses ) && empty( $categories ) ) : ?>
		<div class="cta-empty-state">
			<div class="cta-empty-state__icon" aria-hidden="true">&#128218;</div>
			<h3><?php echo esc_html__( 'No courses available yet', 'cta-lms' ); ?></h3>
			<p><?php echo esc_html__( 'Check back soon — courses are being added.', 'cta-lms' ); ?></p>
		</div>
	<?php else : ?>
		<?php if ( ! empty( $courses ) || ! empty( $categories ) ) : ?>
		<div class="cta-filter-bar">
			<div class="cta-filter-bar__row">
				<input
					type="text"
					id="cta-course-search"
					class="cta-filter-bar__search form-input"
					placeholder="<?php echo esc_attr__( 'Search courses...', 'cta-lms' ); ?>"
					value="<?php echo esc_attr( $search ); ?>"
					aria-label="<?php echo esc_attr__( 'Search courses', 'cta-lms' ); ?>"
				>

				<div class="cta-filter-bar__pills" role="group" aria-label="<?php echo esc_attr__( 'Filter by category', 'cta-lms' ); ?>">
					<a
						href="<?php echo esc_url( $catalog_base_url ? remove_query_arg( 'category', $catalog_base_url ) : '#' ); ?>"
						class="cta-pill <?php echo empty( $active_category ) ? 'cta-pill--active' : ''; ?>"
						data-category=""
						role="button"
					>
						<?php echo esc_html__( 'All Courses', 'cta-lms' ); ?>
					</a>

					<?php foreach ( $categories as $cat ) : ?>
						<?php if ( 'Exam Preparation' === $cat ) { continue; } ?>
						<?php
						$cat_archive_url = $catalog_base_url
							? add_query_arg( 'category', $cat, $catalog_base_url )
							: '#';

						// Prefer the related Alcoholism course detail URL when exactly one match exists.
						$alcoholism_label = class_exists( 'CTA_Admin' )
							? CTA_Admin::get_alcoholism_category_name()
							: 'Alcoholism & Other Chemical Substance Dependency';
						if (
							$cat === $alcoholism_label
							&& ! empty( $alcoholism_course_url )
						) {
							$cat_archive_url = $alcoholism_course_url;
						}
						?>
						<a
							href="<?php echo esc_url( $cat_archive_url ); ?>"
							class="cta-pill <?php echo ( $active_category === $cat ) ? 'cta-pill--active' : ''; ?>"
							data-category="<?php echo esc_attr( $cat ); ?>"
							role="button"
						>
							<span class="cta-pill__label"><?php echo esc_html( $cat ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>

				<select id="cta-course-sort" class="cta-filter-bar__sort form-select" aria-label="<?php echo esc_attr__( 'Sort courses', 'cta-lms' ); ?>">
					<option value="default"><?php echo esc_html__( 'Sort by: Default', 'cta-lms' ); ?></option>
					<option value="price_low"><?php echo esc_html__( 'Price: Low to High', 'cta-lms' ); ?></option>
					<option value="price_high"><?php echo esc_html__( 'Price: High to Low', 'cta-lms' ); ?></option>
					<option value="ce_hours"><?php echo esc_html__( 'CE Hours', 'cta-lms' ); ?></option>
				</select>

				<span class="cta-filter-bar__count">
					<?php
					printf(
						/* translators: %d: number of courses */
						esc_html__( 'Showing %d courses', 'cta-lms' ),
						(int) $course_count
					);
					?>
				</span>
			</div>
		</div>

		<div id="cta-courses-loader" class="cta-loader" style="display:none" aria-hidden="true">
			<div class="cta-loader__spinner"></div>
			<p><?php echo esc_html__( 'Loading courses...', 'cta-lms' ); ?></p>
		</div>

		<div id="cta-courses-grid" class="<?php echo esc_attr( $grid_class ); ?>">
			<?php foreach ( $courses as $course ) : ?>
				<?php include CTA_PLUGIN_DIR . 'templates/partials/course-card.php'; ?>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $exam_courses ) ) : ?>
			<section class="cta-exam-prep-catalog" style="margin-top:3rem;">
				<header class="cta-exam-prep-catalog__header" style="margin-bottom:1.5rem;">
					<h2><?php echo esc_html__( 'Exam Preparation Programs', 'cta-lms' ); ?></h2>
					<p><?php echo esc_html__( 'Non-CE study programs with timed access to instructional content, workbooks, practice tests, and mock exams.', 'cta-lms' ); ?></p>
				</header>
				<div class="<?php echo esc_attr( $grid_class ); ?>">
					<?php foreach ( $exam_courses as $course ) : ?>
						<?php include CTA_PLUGIN_DIR . 'templates/partials/course-card.php'; ?>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>
	<?php endif; ?>
	</div>
</div>
</div>

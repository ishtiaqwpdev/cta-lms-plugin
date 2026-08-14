<?php
/**
 * Exam Prep — Workbooks / Learning Modules list view.
 *
 * @package CTA_LMS
 *
 * @var object                $course          Course row.
 * @var array                 $workbook_items  Rows from CTA_Exam_Prep_Workbooks::get_workbook_list_items().
 * @var string                $home_url        Course home URL.
 * @var string                $dashboard_url   Student dashboard URL.
 * @var string                $player_base     Player base URL.
 * @var array                 $dashboard_user  Sidebar user data.
 * @var int                   $progress        Program progress.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$display_title = function_exists( 'cta_lms_get_course_display_title' )
	? cta_lms_get_course_display_title( $course )
	: (string) $course->title;

$completed_count = 0;
foreach ( (array) $workbook_items as $item ) {
	if ( ! empty( $item['is_complete'] ) ) {
		++$completed_count;
	}
}
$total_count = count( (array) $workbook_items );
?>
<div class="cta-plugin-wrapper">
<div class="cta-lms cta-exam-prep-workbooks dashboard-layout" data-exam-prep-workbooks data-course-id="<?php echo esc_attr( (int) $course->id ); ?>">

	<aside class="dashboard-sidebar" aria-label="<?php echo esc_attr__( 'Dashboard navigation', 'cta-lms' ); ?>">
		<div class="dashboard-sidebar__user">
			<div class="dashboard-sidebar__avatar" aria-hidden="true"><?php echo esc_html( $dashboard_user['initials'] ); ?></div>
			<div class="dashboard-sidebar__user-info">
				<p class="dashboard-sidebar__name"><?php echo esc_html( $dashboard_user['displayName'] ); ?></p>
				<p class="dashboard-sidebar__license"><?php echo esc_html( $dashboard_user['licenseNumber'] ); ?></p>
			</div>
		</div>
		<nav class="dashboard-sidebar__nav">
			<?php if ( $dashboard_url ) : ?>
				<a href="<?php echo esc_url( $dashboard_url ); ?>" class="dashboard-sidebar__link">
					<?php echo esc_html__( 'My Courses', 'cta-lms' ); ?>
				</a>
			<?php endif; ?>
		</nav>
		<?php include CTA_PLUGIN_DIR . 'templates/partials/dashboard-sidebar-footer.php'; ?>
	</aside>

	<?php include CTA_PLUGIN_DIR . 'templates/partials/dashboard-mobile-bar.php'; ?>

	<div class="dashboard-main">
		<p class="course-player__back">
			<a href="<?php echo esc_url( $home_url ); ?>">&larr; <?php echo esc_html__( 'Back to Course Home', 'cta-lms' ); ?></a>
		</p>

		<?php include CTA_PLUGIN_DIR . 'templates/partials/exam-prep-dashboard-nav.php'; ?>

		<header class="cta-ep-workbooks__header">
			<p class="cta-ep-workbooks__eyebrow"><?php esc_html_e( 'Workbooks / Learning Modules', 'cta-lms' ); ?></p>
			<h1 class="cta-ep-workbooks__title"><?php echo esc_html( $display_title ); ?></h1>
			<p class="cta-ep-workbooks__summary">
				<?php
				printf(
					/* translators: 1: completed count, 2: total count */
					esc_html__( '%1$d of %2$d modules complete — open any workbook to read online, download, and take the paired practice bank.', 'cta-lms' ),
					(int) $completed_count,
					(int) $total_count
				);
				?>
			</p>
		</header>

		<div class="cta-ep-workbooks-grid" role="list">
			<?php foreach ( (array) $workbook_items as $item ) : ?>
				<article class="cta-ep-workbooks-grid__card<?php echo ! empty( $item['is_complete'] ) ? ' is-complete' : ''; ?>" role="listitem">
					<div class="cta-ep-workbooks-grid__card-head">
						<span class="cta-ep-workbooks-grid__label"><?php echo esc_html( (string) $item['label'] ); ?></span>
						<?php if ( ! empty( $item['is_complete'] ) ) : ?>
							<span class="cta-ep-workbooks-grid__status"><?php esc_html_e( 'Complete', 'cta-lms' ); ?></span>
						<?php endif; ?>
					</div>
					<h2 class="cta-ep-workbooks-grid__card-title">
						<a href="<?php echo esc_url( (string) $item['url'] ); ?>"><?php echo esc_html( (string) $item['title'] ); ?></a>
					</h2>
					<?php if ( ! empty( $item['description'] ) ) : ?>
						<p class="cta-ep-workbooks-grid__desc"><?php echo esc_html( (string) $item['description'] ); ?></p>
					<?php endif; ?>
					<a class="btn btn-primary btn--sm cta-ep-workbooks-grid__open" href="<?php echo esc_url( (string) $item['url'] ); ?>">
						<?php echo ! empty( $item['is_complete'] ) ? esc_html__( 'Review Workbook', 'cta-lms' ) : esc_html__( 'Open Workbook', 'cta-lms' ); ?>
					</a>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</div>
</div>

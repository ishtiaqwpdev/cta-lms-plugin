<?php
/**
 * Exam Prep Course Home Dashboard (Phase 2 landing view).
 *
 * Getting Started / Exam Strategy is the first section; additional dashboard
 * sections (Workbooks, Flashcards, Practice Exams, etc.) will be added below.
 *
 * @package CTA_LMS
 *
 * @var object                $course           Course row.
 * @var array                 $modules          Course modules.
 * @var object                $enrollment       Enrollment row.
 * @var array                 $completed_ids    Completed module IDs.
 * @var int                   $progress         Progress percentage.
 * @var array                 $getting_started  Getting started config.
 * @var string                $workbooks_list_url URL to workbooks list.
 * @var string                $course_home_url    Course home URL.
 * @var string                $player_base      Player page base URL.
 * @var string                $dashboard_url    Student dashboard URL.
 * @var string                $logout_url       Logout URL.
 * @var string                $home_url         Site home URL.
 * @var array                 $dashboard_user   Sidebar user data.
 * @var CTA_Student_Dashboard $dashboard        Dashboard instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$display_title = function_exists( 'cta_lms_get_course_display_title' )
	? cta_lms_get_course_display_title( $course )
	: (string) $course->title;

$home_url_player = $course_home_url ?? add_query_arg(
	array(
		'course_id' => (int) $course->id,
		'view'      => 'home',
	),
	$player_base
);
$workbooks_list_url = $workbooks_list_url ?? ( class_exists( 'CTA_Exam_Prep_Workbooks' )
	? CTA_Exam_Prep_Workbooks::get_workbooks_list_url( (int) $course->id, $player_base )
	: $home_url_player );
?>
<div class="cta-plugin-wrapper">
<div class="cta-lms cta-exam-prep-home dashboard-layout" data-exam-prep-home data-course-id="<?php echo esc_attr( (int) $course->id ); ?>">

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
					<span class="dashboard-sidebar__icon" aria-hidden="true">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
					</span>
					<?php echo esc_html__( 'My Courses', 'cta-lms' ); ?>
				</a>
			<?php endif; ?>
		</nav>

		<?php include CTA_PLUGIN_DIR . 'templates/partials/dashboard-sidebar-footer.php'; ?>
	</aside>

	<?php include CTA_PLUGIN_DIR . 'templates/partials/dashboard-mobile-bar.php'; ?>

	<div class="dashboard-main">
		<?php if ( $dashboard_url ) : ?>
			<p class="course-player__back">
				<a href="<?php echo esc_url( $dashboard_url ); ?>">&larr; <?php echo esc_html__( 'Back to My Courses', 'cta-lms' ); ?></a>
			</p>
		<?php endif; ?>

		<header class="cta-exam-prep-home__hero">
			<p class="cta-exam-prep-home__badge"><?php esc_html_e( 'Exam Preparation Program', 'cta-lms' ); ?></p>
			<h1 class="cta-exam-prep-home__title"><?php echo esc_html( $display_title ); ?></h1>
			<div class="cta-exam-prep-home__meta">
				<div class="progress cta-exam-prep-home__progress">
					<div class="progress__label">
						<span><?php echo esc_html__( 'Program progress', 'cta-lms' ); ?></span>
						<span class="progress__percent"><?php echo esc_html( (string) (int) $progress ); ?>%</span>
					</div>
					<div class="progress__track">
						<div class="progress__bar" style="width: <?php echo esc_attr( (string) (int) $progress ); ?>%;"></div>
					</div>
				</div>
			</div>
		</header>

		<?php
		$home_url = $home_url_player;
		$active   = 'home';
		include CTA_PLUGIN_DIR . 'templates/partials/exam-prep-dashboard-nav.php';
		?>

		<?php
		include CTA_PLUGIN_DIR . 'templates/partials/exam-prep-getting-started.php';
		?>

		<section class="cta-ep-home-section" aria-labelledby="cta-ep-home-workbooks-link-title">
			<h2 class="dashboard-section__title" id="cta-ep-home-workbooks-link-title"><?php esc_html_e( 'Workbooks / Learning Modules', 'cta-lms' ); ?></h2>
			<p class="cta-ep-home-section__lede"><?php esc_html_e( 'Browse all program workbooks, track completion, and open each module individually.', 'cta-lms' ); ?></p>
			<a class="btn btn-primary" href="<?php echo esc_url( $workbooks_list_url ); ?>"><?php esc_html_e( 'View All Workbooks', 'cta-lms' ); ?></a>
		</section>

		<?php
		/**
		 * Future Course Home sections (Workbooks, Flashcards, Practice Exams,
		 * Study Resources, Downloads) will hook in below this line.
		 */
		do_action( 'cta_exam_prep_course_home_after_getting_started', $course, $modules, $enrollment );
		?>
	</div>
</div>
</div>

<?php
/**
 * Exam Prep section view (Flashcards, Practice Exams, Resources, etc.).
 *
 * @package CTA_LMS
 *
 * @var object                $course           Course row.
 * @var array                 $modules          Course modules.
 * @var object                $enrollment       Enrollment row.
 * @var array                 $completed_ids    Completed module IDs.
 * @var int                   $progress         Progress percentage.
 * @var string                $section_view     Section key: flashcards|exams|resources|downloads|audio|progress.
 * @var array                 $sidebar_nav      Sidebar navigation tree.
 * @var array                 $section_data     Section-specific render data.
 * @var string                $course_home_url  Course home URL.
 * @var string                $workbooks_list_url Workbooks list URL.
 * @var string                $player_base      Player base URL.
 * @var string                $dashboard_url    Student dashboard URL.
 * @var array                 $dashboard_user   Sidebar user data.
 * @var CTA_Student_Dashboard $dashboard        Dashboard instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$display_title = function_exists( 'cta_lms_get_course_display_title' )
	? cta_lms_get_course_display_title( $course )
	: (string) $course->title;

$section_titles = array(
	'flashcards' => __( 'Flashcard Study Center', 'cta-lms' ),
	'exams'      => __( 'Practice Exams', 'cta-lms' ),
	'resources'  => __( 'Study Resources', 'cta-lms' ),
	'downloads'  => __( 'Downloads', 'cta-lms' ),
	'audio'      => __( 'Audio Review', 'cta-lms' ),
	'progress'   => __( 'Progress / Readiness', 'cta-lms' ),
);

$page_title = isset( $section_titles[ $section_view ] )
	? $section_titles[ $section_view ]
	: __( 'Course Section', 'cta-lms' );

$active = ! empty( $sidebar_nav['active_section'] )
	? (string) $sidebar_nav['active_section']
	: (string) $section_view;
$home_url_player = $course_home_url ?? add_query_arg(
	array(
		'course_id' => (int) $course->id,
		'view'      => 'home',
	),
	$player_base
);
?>
<div class="cta-plugin-wrapper">
<div class="cta-lms cta-exam-prep-section dashboard-layout" data-exam-prep-section data-course-id="<?php echo esc_attr( (int) $course->id ); ?>" data-section-view="<?php echo esc_attr( (string) $section_view ); ?>">

	<?php include CTA_PLUGIN_DIR . 'templates/partials/exam-prep-dashboard-sidebar.php'; ?>

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

		<section class="cta-ep-home-section cta-ep-section-view" aria-labelledby="cta-ep-section-view-title">
			<h2 class="dashboard-section__title" id="cta-ep-section-view-title"><?php echo esc_html( $page_title ); ?></h2>

			<?php if ( 'flashcards' === $section_view ) : ?>
				<?php
				$flashcard_center_deck = $section_data['flashcard_center_deck'] ?? null;
				include CTA_PLUGIN_DIR . 'templates/partials/exam-prep-flashcard-center.php';
				?>
			<?php elseif ( 'exams' === $section_view ) : ?>
				<?php
				$exam_center_data = $section_data['exam_center_data'] ?? null;
				include CTA_PLUGIN_DIR . 'templates/partials/exam-prep-exam-center.php';
				?>
			<?php elseif ( 'downloads' === $section_view ) : ?>
				<?php
				$downloads_data = $section_data['downloads_data'] ?? null;
				include CTA_PLUGIN_DIR . 'templates/partials/exam-prep-downloads.php';
				?>
			<?php elseif ( 'audio' === $section_view ) : ?>
				<?php
				$audio_review_data = $section_data['audio_review_data'] ?? null;
				include CTA_PLUGIN_DIR . 'templates/partials/exam-prep-audio-review.php';
				?>
			<?php elseif ( 'resources' === $section_view && ! empty( $section_data['resource_items'] ) ) : ?>
				<p class="cta-ep-home-section__lede">
					<?php esc_html_e( 'Program guides, schedules, toolkits, and reference downloads.', 'cta-lms' ); ?>
				</p>
				<ul class="cta-ep-section-resources">
					<?php foreach ( (array) $section_data['resource_items'] as $item ) : ?>
						<li class="cta-ep-section-resources__item<?php echo ! empty( $item['locked'] ) ? ' cta-ep-section-resources__item--locked' : ''; ?>">
							<?php if ( ! empty( $item['locked'] ) ) : ?>
								<span class="cta-ep-section-resources__link cta-ep-section-resources__link--locked" aria-disabled="true">
									<?php echo esc_html( (string) ( $item['label'] ?? '' ) ); ?>
								</span>
								<?php if ( ! empty( $item['lock_message'] ) ) : ?>
									<p class="cta-ep-section-resources__lock-message"><?php echo esc_html( (string) $item['lock_message'] ); ?></p>
								<?php endif; ?>
							<?php else : ?>
								<a
									class="cta-ep-section-resources__link"
									href="<?php echo esc_url( (string) ( $item['url'] ?? '' ) ); ?>"
									<?php echo ! empty( $item['external'] ) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
								>
									<?php echo esc_html( (string) ( $item['label'] ?? '' ) ); ?>
								</a>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php elseif ( 'progress' === $section_view ) : ?>
				<?php
				$progress_readiness_data = $section_data['progress_readiness_data'] ?? null;
				include CTA_PLUGIN_DIR . 'templates/partials/exam-prep-progress-readiness.php';
				?>
			<?php else : ?>
				<p class="cta-ep-home-section__lede"><?php esc_html_e( 'Content for this section will appear here when available for your program.', 'cta-lms' ); ?></p>
			<?php endif; ?>
		</section>
	</div>
</div>
</div>

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

		<?php
		$home_url = $home_url_player;
		include CTA_PLUGIN_DIR . 'templates/partials/exam-prep-dashboard-nav.php';
		?>

		<section class="cta-ep-home-section cta-ep-section-view" aria-labelledby="cta-ep-section-view-title">
			<h2 class="dashboard-section__title" id="cta-ep-section-view-title"><?php echo esc_html( $page_title ); ?></h2>

			<?php if ( 'flashcards' === $section_view && ! empty( $section_data['flashcard_deck'] ) ) : ?>
				<?php
				$flashcard_deck = $section_data['flashcard_deck'];
				include CTA_PLUGIN_DIR . 'templates/partials/flashcard-viewer.php';
				?>
			<?php elseif ( 'exams' === $section_view && ! empty( $section_data['quiz_cards'] ) ) : ?>
				<p class="cta-ep-home-section__lede"><?php esc_html_e( 'Launch a full-length simulation or checkpoint assessment. Your best attempt score is tracked for each exam.', 'cta-lms' ); ?></p>
				<ul class="cta-ep-section-cards">
					<?php foreach ( (array) $section_data['quiz_cards'] as $card ) : ?>
						<?php $quiz = $card['quiz'] ?? null; ?>
						<?php if ( ! $quiz ) : continue; endif; ?>
						<li class="cta-ep-section-cards__item">
							<div class="cta-ep-section-cards__body">
								<h3 class="cta-ep-section-cards__title"><?php echo esc_html( (string) $quiz->title ); ?></h3>
								<?php if ( ! empty( $card['passed'] ) ) : ?>
									<p class="cta-ep-section-cards__meta cta-ep-section-cards__meta--passed"><?php esc_html_e( 'Passed', 'cta-lms' ); ?></p>
								<?php elseif ( ! empty( $card['best'] ) ) : ?>
									<p class="cta-ep-section-cards__meta">
										<?php
										printf(
											/* translators: %d: best score percentage */
											esc_html__( 'Best score: %d%%', 'cta-lms' ),
											(int) $card['best']->score
										);
										?>
									</p>
								<?php endif; ?>
							</div>
							<a class="btn btn-primary btn--sm" href="<?php echo esc_url( (string) $card['url'] ); ?>">
								<?php esc_html_e( 'Start Exam', 'cta-lms' ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php elseif ( in_array( $section_view, array( 'resources', 'downloads', 'audio' ), true ) && ! empty( $section_data['resource_items'] ) ) : ?>
				<p class="cta-ep-home-section__lede">
					<?php
					if ( 'audio' === $section_view ) {
						esc_html_e( 'Stream or download audio review tracks for this program.', 'cta-lms' );
					} elseif ( 'downloads' === $section_view ) {
						esc_html_e( 'Printable workbooks and practice banks for offline study.', 'cta-lms' );
					} else {
						esc_html_e( 'Program guides, schedules, toolkits, and reference downloads.', 'cta-lms' );
					}
					?>
				</p>
				<ul class="cta-ep-section-resources">
					<?php foreach ( (array) $section_data['resource_items'] as $item ) : ?>
						<li class="cta-ep-section-resources__item">
							<a
								class="cta-ep-section-resources__link"
								href="<?php echo esc_url( (string) ( $item['url'] ?? '' ) ); ?>"
								<?php echo ! empty( $item['external'] ) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
							>
								<?php echo esc_html( (string) ( $item['label'] ?? '' ) ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php elseif ( 'progress' === $section_view ) : ?>
				<p class="cta-ep-home-section__lede"><?php esc_html_e( 'Track your program completion and use readiness tools to gauge exam preparedness.', 'cta-lms' ); ?></p>
				<div class="cta-ep-progress-summary">
					<p class="cta-ep-progress-summary__stat">
						<strong><?php echo esc_html( (string) (int) $progress ); ?>%</strong>
						<?php esc_html_e( 'modules complete', 'cta-lms' ); ?>
					</p>
					<p class="cta-ep-progress-summary__count">
						<?php
						printf(
							/* translators: 1: completed count, 2: total count */
							esc_html__( '%1$d of %2$d workbooks completed', 'cta-lms' ),
							count( (array) $completed_ids ),
							count( (array) $modules )
						);
						?>
					</p>
				</div>
				<?php if ( ! empty( $section_data['readiness_items'] ) ) : ?>
					<ul class="cta-ep-section-resources">
						<?php foreach ( (array) $section_data['readiness_items'] as $item ) : ?>
							<li class="cta-ep-section-resources__item">
								<a
									class="cta-ep-section-resources__link"
									href="<?php echo esc_url( (string) ( $item['url'] ?? '' ) ); ?>"
									target="_blank"
									rel="noopener noreferrer"
								>
									<?php echo esc_html( (string) ( $item['label'] ?? '' ) ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			<?php else : ?>
				<p class="cta-ep-home-section__lede"><?php esc_html_e( 'Content for this section will appear here when available for your program.', 'cta-lms' ); ?></p>
			<?php endif; ?>
		</section>
	</div>
</div>
</div>

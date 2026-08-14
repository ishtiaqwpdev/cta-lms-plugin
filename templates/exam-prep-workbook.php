<?php
/**
 * Exam Prep individual workbook page — workbook-scoped content only.
 *
 * @package CTA_LMS
 *
 * @var object                $course              Course row.
 * @var object                $module              Current module.
 * @var array                 $modules             All modules.
 * @var object                $enrollment          Enrollment row.
 * @var array                 $completed_ids       Completed module IDs.
 * @var object|null           $prev_module         Previous module.
 * @var object|null           $next_module         Next module.
 * @var bool                  $module_complete     Whether current module is complete.
 * @var array                 $workbook_quiz_cards Workbook-scoped quiz cards.
 * @var object|null           $workbook_resource   Printable workbook download.
 * @var object|null           $practice_bank_resource Downloadable practice bank.
 * @var int                   $quiz_page_id        Quiz page ID.
 * @var string                $home_url            Course home URL.
 * @var string                $workbooks_url       Workbooks list URL.
 * @var string                $player_base         Player base URL.
 * @var string                $dashboard_url       Student dashboard URL.
 * @var array                 $dashboard_user      Sidebar user data.
 * @var CTA_Student_Dashboard $dashboard           Dashboard instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$prev_url = $prev_module
	? add_query_arg(
		array(
			'course_id' => (int) $course->id,
			'module_id' => (int) $prev_module->id,
		),
		$player_base
	)
	: '';

$next_url = $next_module
	? add_query_arg(
		array(
			'course_id' => (int) $course->id,
			'module_id' => (int) $next_module->id,
		),
		$player_base
	)
	: '';

$exam_lesson = null;
if ( class_exists( 'CTA_Exam_Prep_Lessons' ) ) {
	$exam_lesson = CTA_Exam_Prep_Lessons::get_lesson_for_module( $course, $module );
}

if ( ! $workbook_resource && class_exists( 'CTA_Exam_Prep_Lessons' ) && ! empty( $resources ) ) {
	$workbook_resource = CTA_Exam_Prep_Lessons::find_workbook_resource( (array) $resources, $module );
}

$wb_download_url = '';
if ( $workbook_resource && class_exists( 'CTA_Course_Materials' ) ) {
	$wb_can_dl = CTA_Course_Materials::user_can_access( get_current_user_id(), $workbook_resource );
	$wb_download_url = $wb_can_dl ? CTA_Course_Materials::get_serve_url( (int) $workbook_resource->id ) : '';
}

$bank_download_url = '';
if ( $practice_bank_resource && class_exists( 'CTA_Course_Materials' ) ) {
	$bank_can_dl = CTA_Course_Materials::user_can_access( get_current_user_id(), $practice_bank_resource );
	$bank_download_url = $bank_can_dl ? CTA_Course_Materials::get_serve_url( (int) $practice_bank_resource->id ) : '';
}
?>
<div class="cta-plugin-wrapper">
<div class="cta-lms cta-exam-prep-workbook dashboard-layout cta-course-player" data-course-player data-course-id="<?php echo esc_attr( (int) $course->id ); ?>" data-module-id="<?php echo esc_attr( (int) $module->id ); ?>" data-exam-prep="1">

	<?php include CTA_PLUGIN_DIR . 'templates/partials/exam-prep-dashboard-sidebar.php'; ?>

	<?php include CTA_PLUGIN_DIR . 'templates/partials/dashboard-mobile-bar.php'; ?>

	<div class="dashboard-main">
		<p class="course-player__back">
			<a href="<?php echo esc_url( $workbooks_url ); ?>">&larr; <?php echo esc_html__( 'All Workbooks', 'cta-lms' ); ?></a>
			<span class="course-player__back-sep" aria-hidden="true">·</span>
			<a href="<?php echo esc_url( $home_url ); ?>"><?php echo esc_html__( 'Course Home', 'cta-lms' ); ?></a>
		</p>

		<?php
		$active = 'workbooks';
		include CTA_PLUGIN_DIR . 'templates/partials/exam-prep-dashboard-nav.php';
		?>

		<div class="course-player-layout" data-cta-player-layout>
			<div class="course-player__content">
				<h1 class="course-player__lesson-title"><?php echo esc_html( (string) $module->title ); ?></h1>

				<?php
				$workbook_tabs = array();
				if ( ! empty( $exam_lesson['html'] ) && class_exists( 'CTA_Exam_Prep_Workbook_Sections' ) ) {
					$workbook_tabs = CTA_Exam_Prep_Workbook_Sections::build_tabs(
						$exam_lesson['html'],
						array(
							'quiz_cards'        => $workbook_quiz_cards ?? array(),
							'bank_download_url' => $bank_download_url,
							'bank_title'        => $practice_bank_resource ? (string) $practice_bank_resource->title : '',
							'quiz_page_id'      => $quiz_page_id ?? 0,
						)
					);
				} elseif ( ! empty( $workbook_quiz_cards ) || $bank_download_url ) {
					$workbook_tabs = class_exists( 'CTA_Exam_Prep_Workbook_Sections' )
						? CTA_Exam_Prep_Workbook_Sections::build_tabs(
							'',
							array(
								'quiz_cards'        => $workbook_quiz_cards ?? array(),
								'bank_download_url' => $bank_download_url,
								'bank_title'        => $practice_bank_resource ? (string) $practice_bank_resource->title : '',
								'quiz_page_id'      => $quiz_page_id ?? 0,
							)
						)
						: array();
				}

				if ( ! empty( $workbook_tabs ) ) {
					include CTA_PLUGIN_DIR . 'templates/partials/exam-prep-workbook-tabbed.php';
				} elseif ( ! empty( $exam_lesson['html'] ) ) {
					?>
					<div class="cta-exam-lesson">
						<div class="cta-exam-lesson__body">
							<?php echo $exam_lesson['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
					</div>
					<div class="course-player__lesson-actions" data-course-player-actions>
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
					<?php
				} else {
					?>
					<p class="cta-exam-lesson__missing">
						<?php esc_html_e( 'Online lesson text is not available for this workbook yet. Use the printable download above if available.', 'cta-lms' ); ?>
					</p>
					<?php
				}
				?>
			</div>

			<aside class="course-player__sidebar" aria-label="<?php esc_attr_e( 'Program workbooks', 'cta-lms' ); ?>">
				<button type="button" class="course-player__nav-toggle" data-cta-player-nav-toggle aria-expanded="true" aria-controls="cta-player-module-nav">
					<span data-cta-player-nav-label><?php esc_html_e( 'Hide workbook list', 'cta-lms' ); ?></span>
				</button>
				<div class="course-player__modules" id="cta-player-module-nav">
					<div class="course-player__modules-header">
						<?php esc_html_e( 'Workbooks', 'cta-lms' ); ?>
					</div>
					<p class="course-player__modules-hint"><?php esc_html_e( 'Recommended order is a suggestion only — open any workbook anytime.', 'cta-lms' ); ?></p>
					<div class="course-player__module-list">
						<ul class="cta-module-list">
							<?php foreach ( $modules as $index => $mod ) : ?>
								<?php
								$mod_id      = (int) $mod->id;
								$is_complete = in_array( $mod_id, $completed_ids, true );
								$is_current  = $mod_id === (int) $module->id;
								$mod_url     = add_query_arg(
									array(
										'course_id' => (int) $course->id,
										'module_id' => $mod_id,
									),
									$player_base
								);
								$item_classes = array( 'cta-module-list__item' );
								if ( $is_complete ) {
									$item_classes[] = 'cta-module-list__item--complete';
								}
								if ( $is_current ) {
									$item_classes[] = 'cta-module-list__item--current';
								}
								?>
								<li class="<?php echo esc_attr( implode( ' ', $item_classes ) ); ?>">
									<a href="<?php echo esc_url( $mod_url ); ?>" class="cta-module-list__link">
										<span class="cta-module-list__title"><?php echo esc_html( (string) $mod->title ); ?></span>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
			</aside>
		</div>
	</div>
</div>
</div>

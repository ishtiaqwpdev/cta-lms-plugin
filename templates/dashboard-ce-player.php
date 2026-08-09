<?php
/**
 * CE course player template.
 *
 * @package CTA_LMS
 *
 * @var object      $course          Course row.
 * @var object      $module          Current module row.
 * @var array       $modules         All course modules.
 * @var object      $enrollment      Enrollment row.
 * @var array       $completed_ids   Completed module IDs.
 * @var object|null $prev_module     Previous module.
 * @var object|null $next_module     Next module.
 * @var int         $progress        Progress percentage.
 * @var bool        $quiz_unlocked   Whether all modules are complete.
 * @var bool        $quiz_available  Whether the course has a published quiz with questions.
 * @var bool        $module_complete Whether current module is complete.
 * @var string      $video_markup    Video embed HTML.
 * @var string      $quiz_url        Quiz page URL.
 * @var string      $dashboard_url   Dashboard URL.
 * @var string      $player_base     Course player page URL.
 * @var string      $logout_url      Logout URL.
 * @var array       $dashboard_user  User display data.
 * @var CTA_Student_Dashboard $dashboard Dashboard instance.
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
?>
<div class="cta-plugin-wrapper">
<div class="cta-lms cta-course-player dashboard-layout" data-course-player data-course-id="<?php echo esc_attr( $course->id ); ?>" data-module-id="<?php echo esc_attr( $module->id ); ?>" data-exam-prep="<?php echo ! empty( $is_exam_prep ) ? '1' : '0'; ?>">

	<aside class="dashboard-sidebar" aria-label="<?php echo esc_attr__( 'Dashboard navigation', 'cta-lms' ); ?>">
		<div class="dashboard-sidebar__user">
			<div class="dashboard-sidebar__avatar" data-user-avatar aria-hidden="true"><?php echo esc_html( $dashboard_user['initials'] ); ?></div>
			<div class="dashboard-sidebar__user-info">
				<p class="dashboard-sidebar__name" data-user-name><?php echo esc_html( $dashboard_user['displayName'] ); ?></p>
				<p class="dashboard-sidebar__license" data-user-license><?php echo esc_html( $dashboard_user['licenseNumber'] ); ?></p>
			</div>
		</div>

		<nav class="dashboard-sidebar__nav" id="dashboard-sidebar-nav">
			<?php if ( $dashboard_url ) : ?>
				<a href="<?php echo esc_url( $dashboard_url ); ?>" class="dashboard-sidebar__link dashboard-sidebar__link--active">
					<span class="dashboard-sidebar__icon" aria-hidden="true">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
					</span>
					<?php echo esc_html__( 'My Courses', 'cta-lms' ); ?>
				</a>
				<a href="<?php echo esc_url( $dashboard_url . '#certificates' ); ?>" class="dashboard-sidebar__link">
					<span class="dashboard-sidebar__icon" aria-hidden="true">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
					</span>
					<?php echo esc_html__( 'My Certificates', 'cta-lms' ); ?>
				</a>
				<a href="<?php echo esc_url( $dashboard_url . '#settings' ); ?>" class="dashboard-sidebar__link">
					<span class="dashboard-sidebar__icon" aria-hidden="true">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
					</span>
					<?php echo esc_html__( 'Account Settings', 'cta-lms' ); ?>
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

		<div class="course-player-layout">
			<div class="course-player__content">
				<?php echo $video_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in get_module_video_markup(). ?>

				<h1 class="course-player__lesson-title"><?php echo esc_html( $module->title ); ?></h1>

				<?php
				$exam_lesson = null;
				$workbook_resource = null;
				if ( ! empty( $is_exam_prep ) && class_exists( 'CTA_Exam_Prep_Lessons' ) ) {
					$exam_lesson = CTA_Exam_Prep_Lessons::get_lesson_for_module( $course, $module );
					$player_resources_for_wb = isset( $resources ) ? (array) $resources : array();
					$workbook_resource = CTA_Exam_Prep_Lessons::find_workbook_resource( $player_resources_for_wb, $module );
				}
				?>

				<?php if ( ! empty( $exam_lesson['html'] ) ) : ?>
					<div class="cta-exam-lesson">
						<?php if ( $workbook_resource && class_exists( 'CTA_Course_Materials' ) ) : ?>
							<?php
							$wb_can_dl = CTA_Course_Materials::user_can_access( get_current_user_id(), $workbook_resource );
							$wb_url    = $wb_can_dl ? CTA_Course_Materials::get_serve_url( (int) $workbook_resource->id ) : '';
							?>
							<div class="cta-exam-lesson__download">
								<?php if ( $wb_url ) : ?>
									<a class="btn btn-outline btn--sm" href="<?php echo esc_url( $wb_url ); ?>" target="_blank" rel="noopener noreferrer">
										<?php echo esc_html__( 'Download printable workbook (DOCX)', 'cta-lms' ); ?>
									</a>
								<?php else : ?>
									<span class="btn btn-outline btn--sm" aria-disabled="true">
										<?php echo esc_html__( 'Printable workbook available in Course Materials', 'cta-lms' ); ?>
									</span>
								<?php endif; ?>
								<p class="cta-exam-lesson__download-note">
									<?php echo esc_html__( 'Read this workbook online below, or download the printable DOCX. Both options stay available — neither replaces the other.', 'cta-lms' ); ?>
								</p>
							</div>
						<?php endif; ?>

						<div class="cta-exam-lesson__body">
							<?php echo $exam_lesson['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized via CTA_Exam_Prep_Lessons::sanitize_lesson_html(). ?>
						</div>

						<div class="cta-exam-lesson__nav">
							<?php if ( $prev_url ) : ?>
								<a href="<?php echo esc_url( $prev_url ); ?>" class="btn btn-outline">&larr; <?php echo esc_html__( 'Previous Workbook', 'cta-lms' ); ?></a>
							<?php else : ?>
								<span></span>
							<?php endif; ?>
							<?php if ( $next_url ) : ?>
								<a href="<?php echo esc_url( $next_url ); ?>" class="btn btn-primary cta-next-module-link"><?php echo esc_html__( 'Next Workbook', 'cta-lms' ); ?> &rarr;</a>
							<?php endif; ?>
						</div>
					</div>
				<?php elseif ( ! empty( $is_exam_prep ) ) : ?>
					<p class="cta-exam-lesson__missing">
						<?php echo esc_html__( 'Online lesson text is not available for this workbook yet. Use the printable DOCX in Course Materials below.', 'cta-lms' ); ?>
					</p>
				<?php endif; ?>

				<div class="course-player__lesson-actions" data-course-player-actions>
					<?php if ( $module_complete ) : ?>
						<button type="button" class="btn btn-primary course-player__action-btn" id="cta-mark-complete" disabled>
							<?php echo cta_lms_get_icon( 'check-circle', 18, 'cta-icon cta-icon--inline' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo esc_html__( 'Completed', 'cta-lms' ); ?>
						</button>
					<?php else : ?>
						<button
							type="button"
							class="btn btn-primary course-player__action-btn"
							id="cta-mark-complete"
							data-module-id="<?php echo esc_attr( $module->id ); ?>"
							data-course-id="<?php echo esc_attr( $course->id ); ?>"
						>
							<?php echo esc_html__( 'Mark as Complete', 'cta-lms' ); ?>
						</button>
					<?php endif; ?>

					<div class="course-player__nav-links">
						<?php if ( $prev_url ) : ?>
							<a href="<?php echo esc_url( $prev_url ); ?>" class="btn btn-outline course-player__action-btn">&larr; <?php echo esc_html__( 'Previous Module', 'cta-lms' ); ?></a>
						<?php endif; ?>
						<?php if ( $next_url ) : ?>
							<a href="<?php echo esc_url( $next_url ); ?>" class="btn btn-outline course-player__action-btn cta-next-module-link"><?php echo esc_html__( 'Next Module', 'cta-lms' ); ?> &rarr;</a>
						<?php endif; ?>
					</div>
				</div>

				<section class="course-player__quiz-section" aria-labelledby="course-quiz-title">
					<h2 class="dashboard-section__title" id="course-quiz-title">
						<?php echo ! empty( $is_exam_prep ) ? esc_html__( 'Assessments', 'cta-lms' ) : esc_html__( 'Final Examination', 'cta-lms' ); ?>
					</h2>
					<?php if ( ! $quiz_available ) : ?>
						<div class="cta-quiz-coming-soon">
							<p>
								<?php
								echo ! empty( $is_exam_prep )
									? esc_html__( 'Assessments coming soon. Practice / Form A / Form B have not been published yet — you can keep working through the modules in the meantime.', 'cta-lms' )
									: esc_html__( 'Final examination coming soon. The final examination for this course has not been published yet — you can keep working through the modules in the meantime.', 'cta-lms' );
								?>
							</p>
						</div>
					<?php else : ?>
						<?php if ( ! empty( $is_exam_prep ) ) : ?>
							<div class="cta-quiz-unlocked-message">
								<p><?php echo esc_html__( 'Program assessments are available at any time. The recommended study sequence is provided as guidance.', 'cta-lms' ); ?></p>
								<ul class="cta-exam-assessment-list">
									<?php foreach ( $quiz_cards as $card ) : ?>
										<li class="cta-exam-assessment-list__item">
											<div class="cta-exam-assessment-list__meta">
												<strong><?php echo esc_html( $card['quiz']->title ); ?></strong>
												<?php if ( ! empty( $card['locked'] ) ) : ?>
													<span class="badge badge--warning"><?php echo esc_html__( 'Locked', 'cta-lms' ); ?></span>
												<?php elseif ( $card['passed'] ) : ?>
													<span class="badge badge--success"><?php echo esc_html__( 'Passed', 'cta-lms' ); ?> — <?php echo esc_html( (string) (int) $card['best']->score ); ?>%</span>
												<?php elseif ( $card['best'] ) : ?>
													<span class="badge"><?php echo esc_html__( 'Best score', 'cta-lms' ); ?>: <?php echo esc_html( (string) (int) $card['best']->score ); ?>%</span>
												<?php else : ?>
													<span class="badge"><?php echo esc_html__( 'Not started', 'cta-lms' ); ?></span>
												<?php endif; ?>
												<?php if ( ! empty( $card['locked'] ) && ! empty( $card['lock_msg'] ) ) : ?>
													<p class="text-small" style="margin:0.35rem 0 0;"><?php echo esc_html( $card['lock_msg'] ); ?></p>
												<?php endif; ?>
											</div>
											<?php if ( ! empty( $card['locked'] ) ) : ?>
												<span class="btn btn-outline btn--sm" aria-disabled="true" title="<?php echo esc_attr( (string) ( $card['lock_msg'] ?? '' ) ); ?>">
													<?php echo esc_html__( 'Locked', 'cta-lms' ); ?>
												</span>
											<?php elseif ( $quiz_page_id && ! empty( $card['url'] ) && '#' !== $card['url'] ) : ?>
												<a href="<?php echo esc_url( $card['url'] ); ?>" class="btn btn-primary btn--sm cta-quiz-btn">
													<?php echo $card['passed'] ? esc_html__( 'Retake', 'cta-lms' ) : esc_html__( 'Start', 'cta-lms' ); ?>
												</a>
											<?php endif; ?>
										</li>
									<?php endforeach; ?>
								</ul>
								<?php if ( ! $quiz_page_id ) : ?>
									<p class="cta-empty-state"><?php echo esc_html__( 'Quiz page is not configured. Ask the site admin to assign the Quiz Page in CTA LMS Settings.', 'cta-lms' ); ?></p>
								<?php endif; ?>
							</div>
						<?php else : ?>
							<div class="cta-quiz-locked-message" <?php echo $quiz_unlocked ? 'hidden' : ''; ?>>
								<p><?php echo esc_html__( 'Complete all instructional modules, including the Course Integration Capstone, to unlock the final examination.', 'cta-lms' ); ?></p>
							</div>
							<div class="cta-quiz-unlocked-message" <?php echo $quiz_unlocked ? '' : 'hidden'; ?>>
								<p><?php echo esc_html__( 'All modules complete! Take the final examination (70% to pass, unlimited attempts, no time limit) to continue to the course evaluation and certificate.', 'cta-lms' ); ?></p>
								<?php if ( $quiz_page_id && $quiz_url && '#' !== $quiz_url ) : ?>
									<a href="<?php echo esc_url( ! empty( $quiz_cards[0]['url'] ) ? $quiz_cards[0]['url'] : $quiz_url ); ?>" class="btn btn-primary cta-quiz-btn">
										<?php echo esc_html__( 'Take Final Examination', 'cta-lms' ); ?>
									</a>
								<?php else : ?>
									<p class="cta-empty-state"><?php echo esc_html__( 'Quiz page is not configured. Ask the site admin to assign the Quiz Page in CTA LMS Settings.', 'cta-lms' ); ?></p>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					<?php endif; ?>
				</section>

				<?php
				$player_resources = isset( $resources ) ? (array) $resources : array();
				$syllabus_resource = class_exists( 'CTA_Course_Materials' )
					? CTA_Course_Materials::find_syllabus_resource( $player_resources )
					: null;
				$syllabus_meta_player = array();
				if ( ! empty( $course->syllabus_meta ) ) {
					$decoded_player = json_decode( (string) $course->syllabus_meta, true );
					$syllabus_meta_player = is_array( $decoded_player ) ? $decoded_player : array();
				}
				$show_player_course_info = $syllabus_resource
					|| ! empty( $syllabus_meta_player['course_code'] )
					|| ! empty( $syllabus_meta_player['instructional_method'] );
				?>
				<?php if ( $show_player_course_info ) : ?>
					<section class="course-section" aria-labelledby="cta-player-course-info-title">
						<h2 class="dashboard-section__title" id="cta-player-course-info-title"><?php esc_html_e( 'Course Information', 'cta-lms' ); ?></h2>
						<ul class="course-info-list">
							<?php if ( ! empty( $syllabus_meta_player['course_code'] ) ) : ?>
								<li><strong><?php esc_html_e( 'Course Code:', 'cta-lms' ); ?></strong> <?php echo esc_html( $syllabus_meta_player['course_code'] ); ?></li>
							<?php endif; ?>
							<?php if ( ! empty( $syllabus_meta_player['instructional_method'] ) ) : ?>
								<li><strong><?php esc_html_e( 'Instructional Method:', 'cta-lms' ); ?></strong> <?php echo esc_html( $syllabus_meta_player['instructional_method'] ); ?></li>
							<?php endif; ?>
							<?php if ( $syllabus_resource ) : ?>
								<li>
									<strong><?php esc_html_e( 'Downloadable Syllabus:', 'cta-lms' ); ?></strong>
									<?php
									$syllabus_url = CTA_Course_Materials::get_serve_url( (int) $syllabus_resource->id );
									?>
									<?php if ( $syllabus_url ) : ?>
										<a href="<?php echo esc_url( $syllabus_url ); ?>" target="_blank" rel="noopener noreferrer">
											<?php echo esc_html( $syllabus_resource->title ); ?>
										</a>
									<?php else : ?>
										<?php echo esc_html( $syllabus_resource->title ); ?>
									<?php endif; ?>
								</li>
							<?php endif; ?>
						</ul>
					</section>
				<?php endif; ?>

				<?php if ( ! empty( $player_resources ) ) : ?>
					<?php
					$resources   = $player_resources;
					$heading     = ! empty( $is_exam_prep ) ? __( 'Downloadable Materials', 'cta-lms' ) : __( 'Course Materials', 'cta-lms' );
					$is_enrolled = true;
					include CTA_PLUGIN_DIR . 'templates/partials/course-materials.php';
					?>
				<?php endif; ?>
			</div>

			<aside class="course-player__sidebar" aria-label="<?php echo ! empty( $is_exam_prep ) ? esc_attr__( 'Program workbooks', 'cta-lms' ) : esc_attr__( 'Course modules', 'cta-lms' ); ?>">
				<div class="course-player__modules">
					<div class="course-player__modules-header">
						<?php
						echo esc_html(
							function_exists( 'cta_lms_get_course_display_title' )
								? cta_lms_get_course_display_title( $course )
								: $course->title
						);
						?>
						— <?php echo ! empty( $is_exam_prep ) ? esc_html__( 'Workbooks', 'cta-lms' ) : esc_html__( 'Modules', 'cta-lms' ); ?>
					</div>
					<?php if ( ! empty( $is_exam_prep ) ) : ?>
						<p class="course-player__modules-hint">
							<?php esc_html_e( 'Recommended order is a suggestion only — open any workbook anytime.', 'cta-lms' ); ?>
						</p>
					<?php endif; ?>
					<div class="course-player__module-list">
						<ul class="cta-module-list">
							<?php foreach ( $modules as $index => $mod ) : ?>
								<?php
								$mod_id       = (int) $mod->id;
								$is_complete  = in_array( $mod_id, $completed_ids, true );
								$is_current   = $mod_id === (int) $module->id;
								$is_locked    = ! $dashboard->is_module_accessible( $modules, $completed_ids, $mod_id, $course );
								$mod_url      = add_query_arg(
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
								if ( $is_locked ) {
									$item_classes[] = 'cta-module-list__item--locked';
								}

								$recommended_label = '';
								if ( ! empty( $is_exam_prep ) ) {
									$recommended_label = sprintf(
										/* translators: %d: suggested workbook order number (1-based). */
										__( 'Recommended #%d', 'cta-lms' ),
										(int) $index + 1
									);
								}
								?>
								<li class="<?php echo esc_attr( implode( ' ', $item_classes ) ); ?>" data-module-id="<?php echo esc_attr( $mod_id ); ?>">
									<?php if ( $is_locked ) : ?>
										<span class="cta-module-list__link" title="<?php echo esc_attr__( 'Complete previous modules first', 'cta-lms' ); ?>">
											<span class="cta-module-list__icon" aria-hidden="true"><?php echo cta_lms_get_icon( 'lock', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											<span class="cta-module-list__title">
												<?php echo esc_html( $mod->title ); ?>
												<?php if ( $recommended_label ) : ?>
													<span class="cta-module-list__recommended"><?php echo esc_html( $recommended_label ); ?></span>
												<?php endif; ?>
											</span>
										</span>
									<?php elseif ( $is_complete ) : ?>
										<a href="<?php echo esc_url( $mod_url ); ?>" class="cta-module-list__link">
											<span class="cta-module-list__icon" aria-hidden="true"><?php echo cta_lms_get_icon( 'check-circle', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											<span class="cta-module-list__title">
												<?php echo esc_html( $mod->title ); ?>
												<?php if ( $recommended_label ) : ?>
													<span class="cta-module-list__recommended"><?php echo esc_html( $recommended_label ); ?></span>
												<?php endif; ?>
											</span>
										</a>
									<?php else : ?>
										<a href="<?php echo esc_url( $mod_url ); ?>" class="cta-module-list__link">
											<span class="cta-module-list__icon" aria-hidden="true"><?php echo $is_current ? cta_lms_get_icon( 'arrow-right', 16 ) : cta_lms_get_icon( 'circle', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											<span class="cta-module-list__title">
												<?php echo esc_html( $mod->title ); ?>
												<?php if ( $recommended_label ) : ?>
													<span class="cta-module-list__recommended"><?php echo esc_html( $recommended_label ); ?></span>
												<?php endif; ?>
											</span>
										</a>
									<?php endif; ?>
								</li>
								<?php
								// CTA-CE-001: mid-course knowledge check marker after Module 3 (not instructional time).
								if ( empty( $is_exam_prep ) && 2 === (int) $index && ! empty( $syllabus_meta_player['mid_course_knowledge_check_note'] ) ) :
									?>
									<li class="cta-module-list__item cta-module-list__item--admin-note">
										<span class="cta-module-list__link" title="<?php echo esc_attr( (string) $syllabus_meta_player['mid_course_knowledge_check_note'] ); ?>">
											<span class="cta-module-list__icon" aria-hidden="true"><?php echo cta_lms_get_icon( 'check-circle', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											<span class="cta-module-list__title"><?php esc_html_e( 'Mid-Course Knowledge Check (admin — not CE minutes)', 'cta-lms' ); ?></span>
										</span>
									</li>
								<?php endif; ?>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
			</aside>
		</div>
	</div>
</div>
</div>

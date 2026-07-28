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
<div class="cta-lms cta-course-player dashboard-layout" data-course-player data-course-id="<?php echo esc_attr( $course->id ); ?>" data-module-id="<?php echo esc_attr( $module->id ); ?>">

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

				<div class="course-player__lesson-actions" data-course-player-actions>
					<?php if ( $module_complete ) : ?>
						<button type="button" class="btn btn-primary" id="cta-mark-complete" disabled>
							<?php echo cta_lms_get_icon( 'check-circle', 18, 'cta-icon cta-icon--inline' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo esc_html__( 'Completed', 'cta-lms' ); ?>
						</button>
					<?php else : ?>
						<button
							type="button"
							class="btn btn-primary"
							id="cta-mark-complete"
							data-module-id="<?php echo esc_attr( $module->id ); ?>"
							data-course-id="<?php echo esc_attr( $course->id ); ?>"
						>
							<?php echo esc_html__( 'Mark as Complete', 'cta-lms' ); ?>
						</button>
					<?php endif; ?>

					<div class="course-player__nav-links">
						<?php if ( $prev_url ) : ?>
							<a href="<?php echo esc_url( $prev_url ); ?>" class="btn btn-outline btn--sm">&larr; <?php echo esc_html__( 'Previous Module', 'cta-lms' ); ?></a>
						<?php endif; ?>
						<?php if ( $next_url ) : ?>
							<a href="<?php echo esc_url( $next_url ); ?>" class="btn btn-outline btn--sm cta-next-module-link"><?php echo esc_html__( 'Next Module', 'cta-lms' ); ?> &rarr;</a>
						<?php endif; ?>
					</div>
				</div>

				<section class="course-player__quiz-section" aria-labelledby="course-quiz-title">
					<h2 class="dashboard-section__title" id="course-quiz-title">
						<?php echo ! empty( $is_exam_prep ) ? esc_html__( 'Practice / Mock Exam', 'cta-lms' ) : esc_html__( 'Course Quiz', 'cta-lms' ); ?>
					</h2>
					<?php if ( ! $quiz_available ) : ?>
						<div class="cta-quiz-coming-soon">
							<p><?php echo esc_html__( 'Quiz coming soon. The final quiz for this course has not been published yet — you can keep working through the modules in the meantime.', 'cta-lms' ); ?></p>
						</div>
					<?php else : ?>
						<div class="cta-quiz-locked-message" <?php echo $quiz_unlocked ? 'hidden' : ''; ?>>
							<p><?php echo esc_html__( 'Complete all modules to unlock the quiz', 'cta-lms' ); ?></p>
						</div>
						<div class="cta-quiz-unlocked-message" <?php echo $quiz_unlocked ? '' : 'hidden'; ?>>
							<p>
								<?php
								echo ! empty( $is_exam_prep )
									? esc_html__( 'All modules complete! Take the practice / mock exam. Answer rationales are shown after you submit.', 'cta-lms' )
									: esc_html__( 'All modules complete! Take the final quiz (70% to pass, unlimited attempts, no time limit) to earn your certificate.', 'cta-lms' );
								?>
							</p>
							<?php if ( $quiz_page_id && $quiz_url && '#' !== $quiz_url ) : ?>
								<a href="<?php echo esc_url( $quiz_url ); ?>" class="btn btn-primary cta-quiz-btn">
									<?php echo ! empty( $is_exam_prep ) ? esc_html__( 'Start Practice Exam', 'cta-lms' ) : esc_html__( 'Take Quiz', 'cta-lms' ); ?>
								</a>
							<?php else : ?>
								<p class="cta-empty-state"><?php echo esc_html__( 'Quiz page is not configured. Ask the site admin to assign the Quiz Page in CTA LMS Settings.', 'cta-lms' ); ?></p>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</section>

				<?php if ( ! empty( $resources ) ) : ?>
					<?php
					$heading     = ! empty( $is_exam_prep ) ? __( 'Downloadable Materials', 'cta-lms' ) : __( 'Course Materials', 'cta-lms' );
					$is_enrolled = true;
					include CTA_PLUGIN_DIR . 'templates/partials/course-materials.php';
					?>
				<?php endif; ?>
			</div>

			<aside class="course-player__sidebar" aria-label="<?php echo esc_attr__( 'Course modules', 'cta-lms' ); ?>">
				<div class="course-player__modules">
					<div class="course-player__modules-header">
						<?php echo esc_html( $course->title ); ?> — <?php echo esc_html__( 'Modules', 'cta-lms' ); ?>
					</div>
					<div class="course-player__module-list">
						<ul class="cta-module-list">
							<?php foreach ( $modules as $index => $mod ) : ?>
								<?php
								$mod_id       = (int) $mod->id;
								$is_complete  = in_array( $mod_id, $completed_ids, true );
								$is_current   = $mod_id === (int) $module->id;
								$is_locked    = ! $dashboard->is_module_accessible( $modules, $completed_ids, $mod_id );
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
								?>
								<li class="<?php echo esc_attr( implode( ' ', $item_classes ) ); ?>" data-module-id="<?php echo esc_attr( $mod_id ); ?>">
									<?php if ( $is_locked ) : ?>
										<span class="cta-module-list__link" title="<?php echo esc_attr__( 'Complete previous modules first', 'cta-lms' ); ?>">
											<span class="cta-module-list__icon" aria-hidden="true"><?php echo cta_lms_get_icon( 'lock', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											<span class="cta-module-list__title"><?php echo esc_html( $mod->title ); ?></span>
										</span>
									<?php elseif ( $is_complete ) : ?>
										<a href="<?php echo esc_url( $mod_url ); ?>" class="cta-module-list__link">
											<span class="cta-module-list__icon" aria-hidden="true"><?php echo cta_lms_get_icon( 'check-circle', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											<span class="cta-module-list__title"><?php echo esc_html( $mod->title ); ?></span>
										</a>
									<?php else : ?>
										<a href="<?php echo esc_url( $mod_url ); ?>" class="cta-module-list__link">
											<span class="cta-module-list__icon" aria-hidden="true"><?php echo $is_current ? cta_lms_get_icon( 'arrow-right', 16 ) : cta_lms_get_icon( 'circle', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											<span class="cta-module-list__title"><?php echo esc_html( $mod->title ); ?></span>
										</a>
									<?php endif; ?>
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

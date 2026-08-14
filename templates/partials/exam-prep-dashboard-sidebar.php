<?php
/**
 * Exam Prep dashboard sidebar with nested hover / accordion navigation.
 *
 * @package CTA_LMS
 *
 * @var array  $sidebar_nav    Nav tree from CTA_Exam_Prep_Sidebar_Nav::build().
 * @var array  $dashboard_user Sidebar user block data.
 * @var string $dashboard_url  My Courses dashboard URL.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $sidebar_nav ) || empty( $sidebar_nav['course'] ) ) {
	?>
	<aside class="dashboard-sidebar" aria-label="<?php echo esc_attr__( 'Dashboard navigation', 'cta-lms' ); ?>">
		<div class="dashboard-sidebar__user">
			<div class="dashboard-sidebar__avatar" aria-hidden="true"><?php echo esc_html( $dashboard_user['initials'] ?? '' ); ?></div>
			<div class="dashboard-sidebar__user-info">
				<p class="dashboard-sidebar__name"><?php echo esc_html( $dashboard_user['displayName'] ?? '' ); ?></p>
				<p class="dashboard-sidebar__license"><?php echo esc_html( $dashboard_user['licenseNumber'] ?? '' ); ?></p>
			</div>
		</div>
		<nav class="dashboard-sidebar__nav">
			<?php if ( ! empty( $dashboard_url ) ) : ?>
				<a href="<?php echo esc_url( $dashboard_url ); ?>" class="dashboard-sidebar__link">
					<?php echo esc_html__( 'My Courses', 'cta-lms' ); ?>
				</a>
			<?php endif; ?>
		</nav>
		<?php include CTA_PLUGIN_DIR . 'templates/partials/dashboard-sidebar-footer.php'; ?>
	</aside>
	<?php
	return;
}

$course_nav       = (array) $sidebar_nav['course'];
$sections         = isset( $sidebar_nav['sections'] ) ? (array) $sidebar_nav['sections'] : array();
$enrolled_courses = isset( $sidebar_nav['enrolled_courses'] ) ? (array) $sidebar_nav['enrolled_courses'] : array();
$my_courses_url   = (string) ( $sidebar_nav['my_courses_url'] ?? $dashboard_url ?? '' );
$active_section   = (string) ( $sidebar_nav['active_section'] ?? '' );
$course_has_branch = ! empty( $sections );
?>
<aside class="dashboard-sidebar" aria-label="<?php echo esc_attr__( 'Dashboard navigation', 'cta-lms' ); ?>">
	<div class="dashboard-sidebar__user">
		<div class="dashboard-sidebar__avatar" aria-hidden="true"><?php echo esc_html( $dashboard_user['initials'] ?? '' ); ?></div>
		<div class="dashboard-sidebar__user-info">
			<p class="dashboard-sidebar__name"><?php echo esc_html( $dashboard_user['displayName'] ?? '' ); ?></p>
			<p class="dashboard-sidebar__license"><?php echo esc_html( $dashboard_user['licenseNumber'] ?? '' ); ?></p>
		</div>
	</div>

	<nav class="dashboard-sidebar__nav cta-ep-sidebar-nav" data-cta-ep-sidebar-nav>
		<?php if ( $my_courses_url ) : ?>
			<div class="cta-ep-sidebar-nav__root<?php echo ! empty( $enrolled_courses ) ? ' cta-ep-sidebar-nav__root--has-flyout' : ''; ?>">
				<a
					href="<?php echo esc_url( $my_courses_url ); ?>"
					class="dashboard-sidebar__link cta-ep-sidebar-nav__root-link"
				>
					<span class="dashboard-sidebar__icon" aria-hidden="true">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
					</span>
					<?php echo esc_html__( 'My Courses', 'cta-lms' ); ?>
				</a>

				<?php if ( ! empty( $enrolled_courses ) ) : ?>
					<div class="cta-ep-sidebar-nav__flyout cta-ep-sidebar-nav__flyout--courses" data-cta-ep-sidebar-flyout>
						<p class="cta-ep-sidebar-nav__flyout-label"><?php esc_html_e( 'Your Programs', 'cta-lms' ); ?></p>
						<ul class="cta-ep-sidebar-nav__list cta-ep-sidebar-nav__list--flat">
							<?php foreach ( $enrolled_courses as $enrolled ) : ?>
								<li class="cta-ep-sidebar-nav__item">
									<a
										class="cta-ep-sidebar-nav__link<?php echo ! empty( $enrolled['is_current'] ) ? ' is-active' : ''; ?>"
										href="<?php echo esc_url( (string) $enrolled['url'] ); ?>"
										<?php echo ! empty( $enrolled['is_current'] ) ? 'aria-current="page"' : ''; ?>
									>
										<?php echo esc_html( (string) $enrolled['title'] ); ?>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( $course_has_branch ) : ?>
			<div
				class="cta-ep-sidebar-nav__course-branch<?php echo $active_section ? ' is-active-course' : ''; ?>"
				data-cta-ep-sidebar-branch
				data-cta-ep-sidebar-branch-key="course-<?php echo esc_attr( (string) (int) $course_nav['id'] ); ?>"
			>
				<button
					type="button"
					class="dashboard-sidebar__link cta-ep-sidebar-nav__course-trigger"
					data-cta-ep-sidebar-trigger
					aria-expanded="false"
					aria-controls="cta-ep-sidebar-sections-<?php echo esc_attr( (string) (int) $course_nav['id'] ); ?>"
				>
					<span class="dashboard-sidebar__icon" aria-hidden="true">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c0 1.1 2.7 2 6 2s6-.9 6-2v-5"></path></svg>
					</span>
					<span class="cta-ep-sidebar-nav__course-title"><?php echo esc_html( (string) $course_nav['title'] ); ?></span>
					<span class="cta-ep-sidebar-nav__chevron" aria-hidden="true">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"></path></svg>
					</span>
				</button>

				<div
					class="cta-ep-sidebar-nav__flyout cta-ep-sidebar-nav__flyout--l1"
					id="cta-ep-sidebar-sections-<?php echo esc_attr( (string) (int) $course_nav['id'] ); ?>"
					data-cta-ep-sidebar-flyout
					data-cta-ep-sidebar-panel
				>
					<p class="cta-ep-sidebar-nav__flyout-label"><?php esc_html_e( 'Course Sections', 'cta-lms' ); ?></p>
					<ul class="cta-ep-sidebar-nav__list">
						<?php foreach ( $sections as $section ) : ?>
							<?php
							$section_key    = (string) ( $section['key'] ?? '' );
							$has_children   = ! empty( $section['has_children'] );
							$is_active      = ! empty( $section['is_active'] );
							$section_classes = 'cta-ep-sidebar-nav__item';
							if ( $has_children ) {
								$section_classes .= ' cta-ep-sidebar-nav__item--has-children';
							}
							if ( $is_active ) {
								$section_classes .= ' is-active-branch';
							}
							?>
							<li
								class="<?php echo esc_attr( $section_classes ); ?>"
								data-cta-ep-sidebar-item="<?php echo esc_attr( $section_key ); ?>"
							>
								<div class="cta-ep-sidebar-nav__row">
									<a
										class="cta-ep-sidebar-nav__link<?php echo $is_active && ! $has_children ? ' is-active' : ''; ?><?php echo $is_active ? ' is-active-section' : ''; ?>"
										href="<?php echo esc_url( (string) ( $section['url'] ?? '' ) ); ?>"
										<?php echo $is_active && ! $has_children ? 'aria-current="page"' : ''; ?>
									>
										<?php echo esc_html( (string) ( $section['label'] ?? '' ) ); ?>
									</a>
									<?php if ( $has_children ) : ?>
										<button
											type="button"
											class="cta-ep-sidebar-nav__subtoggle"
											data-cta-ep-sidebar-subtoggle
											aria-expanded="<?php echo $is_active ? 'true' : 'false'; ?>"
											aria-label="<?php echo esc_attr( sprintf( __( 'Show %s items', 'cta-lms' ), (string) ( $section['label'] ?? '' ) ) ); ?>"
										>
											<span class="cta-ep-sidebar-nav__chevron" aria-hidden="true">
												<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"></path></svg>
											</span>
										</button>
									<?php endif; ?>
								</div>

								<?php if ( $has_children && ! empty( $section['children'] ) ) : ?>
									<div
										class="cta-ep-sidebar-nav__flyout cta-ep-sidebar-nav__flyout--l2<?php echo $is_active ? ' is-open' : ''; ?>"
										data-cta-ep-sidebar-flyout
									>
										<ul class="cta-ep-sidebar-nav__list cta-ep-sidebar-nav__list--nested">
											<?php foreach ( (array) $section['children'] as $child ) : ?>
												<li class="cta-ep-sidebar-nav__item cta-ep-sidebar-nav__item--child">
													<a
														class="cta-ep-sidebar-nav__link cta-ep-sidebar-nav__link--child<?php echo ! empty( $child['is_active'] ) ? ' is-active' : ''; ?><?php echo ! empty( $child['is_complete'] ) ? ' is-complete' : ''; ?><?php echo ! empty( $child['passed'] ) ? ' is-passed' : ''; ?>"
														href="<?php echo esc_url( (string) ( $child['url'] ?? '' ) ); ?>"
														<?php echo ! empty( $child['external'] ) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
														<?php echo ! empty( $child['is_active'] ) ? 'aria-current="page"' : ''; ?>
														<?php echo ! empty( $child['title'] ) && (string) $child['title'] !== (string) ( $child['label'] ?? '' ) ? 'title="' . esc_attr( (string) $child['title'] ) . '"' : ''; ?>
													>
														<?php echo esc_html( (string) ( $child['label'] ?? '' ) ); ?>
													</a>
												</li>
											<?php endforeach; ?>
										</ul>
									</div>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		<?php endif; ?>
	</nav>

	<?php include CTA_PLUGIN_DIR . 'templates/partials/dashboard-sidebar-footer.php'; ?>
</aside>

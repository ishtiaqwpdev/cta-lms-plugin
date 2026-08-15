<?php
/**
 * Exam Prep dashboard sidebar — collapsed main tabs with nested sub-menus.
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
			<div class="cta-ep-sidebar-nav__root<?php echo ! empty( $enrolled_courses ) ? ' has-submenu' : ''; ?>">
				<a href="<?php echo esc_url( $my_courses_url ); ?>" class="dashboard-sidebar__link cta-ep-sidebar-nav__root-link">
					<?php echo esc_html__( 'My Courses', 'cta-lms' ); ?>
				</a>

				<?php if ( ! empty( $enrolled_courses ) ) : ?>
					<div class="cta-ep-sidebar-nav__submenu cta-ep-sidebar-nav__submenu--flyout" data-cta-ep-sidebar-submenu hidden>
						<p class="cta-ep-sidebar-nav__submenu-label"><?php esc_html_e( 'Your Programs', 'cta-lms' ); ?></p>
						<ul class="cta-ep-sidebar-nav__submenu-list">
							<?php foreach ( $enrolled_courses as $enrolled ) : ?>
								<li>
									<a
										class="cta-ep-sidebar-nav__submenu-link<?php echo ! empty( $enrolled['is_current'] ) ? ' is-active' : ''; ?>"
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

		<?php if ( ! empty( $course_nav['title'] ) ) : ?>
			<p class="dashboard-sidebar__license cta-ep-sidebar-nav__course-label"><?php echo esc_html( (string) $course_nav['title'] ); ?></p>
		<?php endif; ?>

		<?php if ( ! empty( $sections ) ) : ?>
			<ul class="cta-ep-sidebar-nav__tabs" role="list">
				<?php foreach ( $sections as $section ) : ?>
					<?php
					$section_key  = (string) ( $section['key'] ?? '' );
					$has_children = ! empty( $section['has_children'] ) && ! empty( $section['children'] );
					$is_active    = ! empty( $section['is_active'] );
					$tab_classes  = 'cta-ep-sidebar-nav__tab';
					if ( $has_children ) {
						$tab_classes .= ' has-children';
					}
					if ( $is_active ) {
						$tab_classes .= ' is-active';
					}
					?>
					<li
						class="<?php echo esc_attr( $tab_classes ); ?>"
						data-cta-ep-sidebar-tab="<?php echo esc_attr( $section_key ); ?>"
					>
						<div class="cta-ep-sidebar-nav__tab-row">
							<a
								class="dashboard-sidebar__link cta-ep-sidebar-nav__tab-link<?php echo $is_active ? ' is-active-section' : ''; ?>"
								href="<?php echo esc_url( (string) ( $section['url'] ?? '' ) ); ?>"
								<?php echo $is_active ? 'aria-current="page"' : ''; ?>
							>
								<?php echo esc_html( (string) ( $section['label'] ?? '' ) ); ?>
							</a>
							<?php if ( $has_children ) : ?>
								<button
									type="button"
									class="cta-ep-sidebar-nav__expand cta-ep-sidebar-nav__expand--touch-only"
									data-cta-ep-sidebar-expand
									aria-expanded="false"
									aria-label="<?php echo esc_attr( sprintf( __( 'Expand %s', 'cta-lms' ), (string) ( $section['label'] ?? '' ) ) ); ?>"
								>
									<svg class="cta-ep-sidebar-nav__chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
								</button>
							<?php endif; ?>
						</div>

						<?php if ( $has_children ) : ?>
							<div class="cta-ep-sidebar-nav__submenu" data-cta-ep-sidebar-submenu hidden>
								<ul class="cta-ep-sidebar-nav__submenu-list">
									<?php foreach ( (array) $section['children'] as $child ) : ?>
										<li>
											<?php if ( ! empty( $child['locked'] ) ) : ?>
												<span
													class="cta-ep-sidebar-nav__submenu-link cta-ep-sidebar-nav__submenu-link--locked"
													role="note"
													<?php echo ! empty( $child['lock_message'] ) ? 'title="' . esc_attr( (string) $child['lock_message'] ) . '"' : ''; ?>
												>
													<?php echo esc_html( (string) ( $child['label'] ?? '' ) ); ?>
													<span class="screen-reader-text"><?php esc_html_e( '(locked)', 'cta-lms' ); ?></span>
												</span>
												<?php if ( ! empty( $child['lock_message'] ) ) : ?>
													<p class="cta-ep-sidebar-nav__lock-message"><?php echo esc_html( (string) $child['lock_message'] ); ?></p>
												<?php endif; ?>
											<?php else : ?>
												<a
													class="cta-ep-sidebar-nav__submenu-link<?php echo ! empty( $child['is_active'] ) ? ' is-active' : ''; ?><?php echo ! empty( $child['is_complete'] ) ? ' is-complete' : ''; ?><?php echo ! empty( $child['passed'] ) ? ' is-passed' : ''; ?>"
													href="<?php echo esc_url( (string) ( $child['url'] ?? '' ) ); ?>"
													<?php echo ! empty( $child['external'] ) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
													<?php echo ! empty( $child['is_active'] ) ? 'aria-current="page"' : ''; ?>
													<?php echo ! empty( $child['title'] ) && (string) $child['title'] !== (string) ( $child['label'] ?? '' ) ? 'title="' . esc_attr( (string) $child['title'] ) . '"' : ''; ?>
												>
													<?php echo esc_html( (string) ( $child['label'] ?? '' ) ); ?>
												</a>
											<?php endif; ?>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</nav>

	<?php include CTA_PLUGIN_DIR . 'templates/partials/dashboard-sidebar-footer.php'; ?>
</aside>

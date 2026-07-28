<?php
/**
 * CE student dashboard template.
 *
 * @package CTA_LMS
 *
 * @var array  $in_progress    In-progress enrollment items.
 * @var array  $completed      Completed enrollment items.
 * @var array  $certificates   Certificate records for certificates panel.
 * @var float  $total_ce       Total CE hours earned.
 * @var string $dashboard_url  Dashboard page URL.
 * @var string $courses_url    Courses catalog URL.
 * @var string $login_url      Login page URL.
 * @var string $home_url            Site home URL.
 * @var string $logout_url          Logout URL.
 * @var array  $dashboard_user User display data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$has_enrollments = ! empty( $in_progress ) || ! empty( $completed ) || ! empty( $exam_prep );
$in_progress_count = count( $in_progress );
$completed_count   = count( $completed );
$exam_prep         = isset( $exam_prep ) ? $exam_prep : array();
$exam_prep_count   = count( $exam_prep );
?>
<div class="cta-plugin-wrapper">
<div class="cta-lms cta-student-dashboard dashboard-layout" data-dashboard data-dashboard-user="<?php echo esc_attr( wp_json_encode( $dashboard_user ) ); ?>">

	<aside class="dashboard-sidebar" aria-label="<?php echo esc_attr__( 'Dashboard navigation', 'cta-lms' ); ?>">
		<div class="dashboard-sidebar__user">
			<div class="dashboard-sidebar__avatar" data-user-avatar aria-hidden="true"><?php echo esc_html( $dashboard_user['initials'] ); ?></div>
			<div class="dashboard-sidebar__user-info">
				<p class="dashboard-sidebar__name" data-user-name><?php echo esc_html( $dashboard_user['displayName'] ); ?></p>
				<p class="dashboard-sidebar__license" data-user-license><?php echo esc_html( $dashboard_user['licenseNumber'] ); ?></p>
			</div>
		</div>

		<nav class="dashboard-sidebar__nav" id="dashboard-sidebar-nav">
			<a href="#" class="dashboard-sidebar__link dashboard-sidebar__link--active" data-dashboard-nav="courses" aria-current="page">
				<span class="dashboard-sidebar__icon" aria-hidden="true">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
				</span>
				<?php echo esc_html__( 'My Courses', 'cta-lms' ); ?>
			</a>
			<a href="#certificates" class="dashboard-sidebar__link" data-dashboard-nav="certificates">
				<span class="dashboard-sidebar__icon" aria-hidden="true">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
				</span>
				<?php echo esc_html__( 'My Certificates', 'cta-lms' ); ?>
			</a>
			<a href="#settings" class="dashboard-sidebar__link" data-dashboard-nav="settings">
				<span class="dashboard-sidebar__icon" aria-hidden="true">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
				</span>
				<?php echo esc_html__( 'Account Settings', 'cta-lms' ); ?>
			</a>
			<?php if ( ! empty( $is_associate ) && ! empty( $supervision_dashboard_url ) ) : ?>
				<a href="<?php echo esc_url( $supervision_dashboard_url ); ?>" class="dashboard-sidebar__link">
					<span class="dashboard-sidebar__icon" aria-hidden="true">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
					</span>
					<?php echo esc_html__( 'Supervision', 'cta-lms' ); ?>
				</a>
			<?php endif; ?>
		</nav>

		<?php include CTA_PLUGIN_DIR . 'templates/partials/dashboard-sidebar-footer.php'; ?>
	</aside>

	<?php include CTA_PLUGIN_DIR . 'templates/partials/dashboard-mobile-bar.php'; ?>

	<div class="dashboard-main">

		<div class="dashboard-panel dashboard-panel--active" data-dashboard-panel="courses">

			<header class="dashboard-welcome">
				<div>
					<h1 class="dashboard-welcome__greeting" data-user-greeting>
						<?php
						printf(
							/* translators: %s: user display name */
							esc_html__( 'Welcome back, %s', 'cta-lms' ),
							esc_html( $dashboard_user['displayName'] )
						);
						?>
					</h1>
					<p class="dashboard-welcome__date" id="dashboard-date"><?php echo esc_html( cta_lms_format_local_date( null, 'l, F j, Y' ) ); ?></p>
				</div>
				<?php if ( $has_enrollments ) : ?>
					<div class="dashboard-stats" aria-label="<?php echo esc_attr__( 'Your progress summary', 'cta-lms' ); ?>">
						<span class="dashboard-stat-pill">
							<?php
							printf(
								/* translators: %d: number of courses in progress */
								esc_html( _n( '%d In Progress', '%d In Progress', $in_progress_count, 'cta-lms' ) ),
								(int) $in_progress_count
							);
							?>
						</span>
						<span class="dashboard-stat-pill dashboard-stat-pill--success">
							<?php
							printf(
								/* translators: %d: number of completed courses */
								esc_html( _n( '%d Completed', '%d Completed', $completed_count, 'cta-lms' ) ),
								(int) $completed_count
							);
							?>
						</span>
						<span class="dashboard-stat-pill">
							<?php
							printf(
								/* translators: %s: CE hours total */
								esc_html__( '%s CE Hours Earned', 'cta-lms' ),
								esc_html( rtrim( rtrim( number_format( $total_ce, 1, '.', '' ), '0' ), '.' ) )
							);
							?>
						</span>
					</div>
				<?php endif; ?>
			</header>

			<?php if ( ! $has_enrollments ) : ?>
				<div class="cta-empty-state">
					<h3><?php echo esc_html__( "You haven't enrolled in any courses yet", 'cta-lms' ); ?></h3>
					<p><?php echo esc_html__( 'Browse our CE catalog to get started.', 'cta-lms' ); ?></p>
					<a href="<?php echo esc_url( ! empty( $home_url ) ? $home_url : home_url( '/' ) ); ?>" class="btn btn-primary btn--lg cta-empty-state__cta">
						<?php echo esc_html__( 'View All Courses', 'cta-lms' ); ?>
					</a>
				</div>
			<?php else : ?>

				<section class="dashboard-section" aria-labelledby="continue-learning-title">
					<h2 class="dashboard-section__title" id="continue-learning-title"><?php echo esc_html__( 'Continue Learning', 'cta-lms' ); ?></h2>
					<div class="dashboard-course-list">
						<?php if ( empty( $in_progress ) ) : ?>
							<p class="cta-empty-state cta-empty-state--inline"><?php echo esc_html__( 'No courses in progress', 'cta-lms' ); ?></p>
						<?php else : ?>
							<?php foreach ( $in_progress as $item ) : ?>
								<?php include CTA_PLUGIN_DIR . 'templates/partials/progress-card.php'; ?>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</section>

				<section class="dashboard-section" aria-labelledby="completed-courses-title">
					<h2 class="dashboard-section__title" id="completed-courses-title"><?php echo esc_html__( 'Completed Courses', 'cta-lms' ); ?></h2>
					<div class="dashboard-course-list">
						<?php if ( empty( $completed ) ) : ?>
							<p class="cta-empty-state cta-empty-state--inline"><?php echo esc_html__( 'No completed courses yet', 'cta-lms' ); ?></p>
						<?php else : ?>
							<?php foreach ( $completed as $item ) : ?>
								<?php include CTA_PLUGIN_DIR . 'templates/partials/certificate-card.php'; ?>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</section>

				<section class="dashboard-section" aria-labelledby="exam-prep-title">
					<h2 class="dashboard-section__title" id="exam-prep-title"><?php echo esc_html__( 'Exam Preparation Programs', 'cta-lms' ); ?></h2>
					<div class="dashboard-course-list">
						<?php if ( empty( $exam_prep ) ) : ?>
							<p class="cta-empty-state cta-empty-state--inline"><?php echo esc_html__( 'No exam preparation programs purchased yet', 'cta-lms' ); ?></p>
						<?php else : ?>
							<?php foreach ( $exam_prep as $item ) : ?>
								<?php include CTA_PLUGIN_DIR . 'templates/partials/exam-prep-card.php'; ?>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</section>

			<?php endif; ?>
		</div>

		<div class="dashboard-panel" data-dashboard-panel="certificates" hidden>
			<header class="dashboard-welcome">
				<div>
					<h1 class="dashboard-welcome__greeting"><?php echo esc_html__( 'My Certificates', 'cta-lms' ); ?></h1>
					<p class="dashboard-welcome__subtitle"><?php echo esc_html__( 'Download BBS-compliant CE certificates for completed courses', 'cta-lms' ); ?></p>
				</div>
			</header>

			<section class="dashboard-section" aria-labelledby="certificates-list-title">
				<h2 class="dashboard-section__title" id="certificates-list-title"><?php echo esc_html__( 'Earned Certificates', 'cta-lms' ); ?></h2>
				<div class="dashboard-course-list">
					<?php if ( empty( $certificates ) ) : ?>
						<p class="cta-empty-state cta-empty-state--inline"><?php echo esc_html__( 'No certificates yet', 'cta-lms' ); ?></p>
					<?php else : ?>
						<?php foreach ( $certificates as $cert_item ) : ?>
							<?php
							$item = (object) array(
								'enrollment'  => $cert_item->enrollment,
								'course'      => $cert_item->course,
								'certificate' => $cert_item->certificate,
							);
							include CTA_PLUGIN_DIR . 'templates/partials/certificate-card.php';
							?>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</section>
		</div>

		<div class="dashboard-panel" data-dashboard-panel="settings" hidden>
			<header class="dashboard-welcome">
				<div>
					<h1 class="dashboard-welcome__greeting"><?php echo esc_html__( 'Account Settings', 'cta-lms' ); ?></h1>
					<p class="dashboard-welcome__subtitle">
						<?php
						echo esc_html(
							! empty( $is_associate )
								? __( 'Manage your associate profile', 'cta-lms' )
								: __( 'Manage your profile and license information', 'cta-lms' )
						);
						?>
					</p>
				</div>
			</header>

			<section class="dashboard-settings card" aria-labelledby="settings-form-title">
				<h2 class="dashboard-section__title" id="settings-form-title"><?php echo esc_html__( 'Profile', 'cta-lms' ); ?></h2>
				<form class="dashboard-settings-form cta-dashboard-settings-form" action="#" method="post" novalidate>
					<div class="form-group">
						<label class="form-label" for="settings-name"><?php echo esc_html__( 'Full Name', 'cta-lms' ); ?></label>
						<input type="text" id="settings-name" name="full_name" class="form-input" value="<?php echo esc_attr( $dashboard_user['displayName'] ); ?>" required>
					</div>
					<div class="form-group">
						<label class="form-label" for="settings-email"><?php echo esc_html__( 'Email', 'cta-lms' ); ?></label>
						<input type="email" id="settings-email" name="email" class="form-input" value="<?php echo esc_attr( $dashboard_user['email'] ); ?>" readonly>
					</div>
					<?php if ( ! empty( $is_associate ) ) : ?>
						<div class="form-group">
							<label class="form-label" for="settings-associate"><?php echo esc_html__( 'Associate Number', 'cta-lms' ); ?></label>
							<input type="text" id="settings-associate" name="associate_number" class="form-input" value="<?php echo esc_attr( isset( $dashboard_user['associateNumber'] ) ? $dashboard_user['associateNumber'] : '' ); ?>" required>
						</div>
					<?php else : ?>
						<div class="form-group">
							<label class="form-label" for="settings-license"><?php echo esc_html__( 'License Number', 'cta-lms' ); ?></label>
							<input type="text" id="settings-license" name="license_number" class="form-input" value="<?php echo esc_attr( get_user_meta( get_current_user_id(), 'cta_license_number', true ) ); ?>" maxlength="64" required>
							<p class="form-hint" style="margin-top:0.35rem;font-size:0.85em;opacity:0.8;"><?php echo esc_html__( 'Enter your license or registration number (formats vary by type, e.g. LMFT, LCSW, AMFT).', 'cta-lms' ); ?></p>
						</div>
						<div class="form-group">
							<label class="form-label" for="settings-license-type"><?php echo esc_html__( 'License Type', 'cta-lms' ); ?></label>
							<select id="settings-license-type" name="license_type" class="form-input">
								<?php
								$current_type = (string) get_user_meta( get_current_user_id(), 'cta_license_type', true );
								$types        = cta_lms_get_license_types();
								foreach ( $types as $type ) :
									?>
									<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $current_type, $type ); ?>><?php echo esc_html( $type ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					<?php endif; ?>
					<button type="submit" class="btn btn-primary" data-save-settings><?php echo esc_html__( 'Save Changes', 'cta-lms' ); ?></button>
				</form>
			</section>
		</div>

	</div>
</div>
</div>

<?php
/**
 * Supervision associate dashboard template.
 *
 * @package CTA_LMS
 *
 * @var bool   $is_active
 * @var bool   $is_locked
 * @var bool   $is_pending_plan
 * @var bool   $has_supervision_purchase
 * @var bool   $is_approved_awaiting_plan
 * @var bool   $can_access_supervision
 * @var bool   $no_plan
 * @var bool   $is_pending_approval
 * @var bool   $can_access_booking
 * @var bool   $can_access_meeting_links
 * @var bool   $can_access_supervision_resources
 * @var string $pending_approval_message
 * @var string $supervision_status
 * @var string $supervision_plan
 * @var string $plan_label
 * @var string $onboarding_status_label
 * @var string $onboarding_status_class
 * @var string $onboarding_message
 * @var string $associate_number
 * @var array  $upcoming_sessions
 * @var array  $session_history
 * @var array  $documents
 * @var float  $monthly_price
 * @var float  $individual_price
 * @var string $next_billing_date
 * @var string $next_session_label
 * @var string $dashboard_url
 * @var string $supervision_url
 * @var string $home_url            Site home URL.
 * @var string $logout_url          Logout URL.
 * @var array  $dashboard_user
 * @var array  $document_categories
 * @var CTA_Supervision_Dashboard $dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$monthly_display    = '$' . number_format( $monthly_price, 0 );
$individual_display = '$' . number_format( $individual_price, 0 );
$document_count     = count( $documents );
?>
<div class="cta-plugin-wrapper">
<div class="cta-lms cta-supervision-dashboard dashboard-layout" data-dashboard data-dashboard-user="<?php echo esc_attr( wp_json_encode( $dashboard_user ) ); ?>">

	<?php if ( $no_plan && empty( $is_pending_approval ) ) : ?>
		<div class="dashboard-main dashboard-main--full">
			<?php if ( ! empty( $home_url ) ) : ?>
				<p class="dashboard-home-link"><a href="<?php echo esc_url( $home_url ); ?>">&larr; <?php echo esc_html__( 'Back to Home', 'cta-lms' ); ?></a></p>
			<?php endif; ?>
			<div class="cta-empty-state cta-supervision-no-plan">
				<h1><?php echo esc_html__( 'No active plan', 'cta-lms' ); ?></h1>
				<p><?php echo esc_html__( 'Subscribe to Group Supervision or purchase an Individual 1-on-1 session to access your associate dashboard and book sessions.', 'cta-lms' ); ?></p>

				<div class="grid-2 grid-2--gap-lg supervision-services__grid cta-supervision-plan-grid">
					<article class="card service-card">
						<div class="service-card__badge-row">
							<span class="badge badge--primary"><?php echo esc_html__( 'Subscription', 'cta-lms' ); ?></span>
						</div>
						<h3 class="service-card__title"><?php echo esc_html__( 'Group Supervision', 'cta-lms' ); ?></h3>
						<p class="service-card__price"><?php echo esc_html( $monthly_display ); ?></p>
						<p class="service-card__price-unit"><?php echo esc_html__( '/ month', 'cta-lms' ); ?></p>
						<button type="button" class="btn btn-primary btn--lg service-card__cta cta-subscribe-btn" data-cta-supervision-subscribe data-course-title="<?php echo esc_attr( CTA_Supervision_Plans::get_name( CTA_Supervision_Plans::GROUP_SLUG ) ); ?>" data-price="<?php echo esc_attr( CTA_Supervision_Plans::get_price_label( CTA_Supervision_Plans::GROUP_SLUG ) ); ?>">
							<?php echo esc_html__( 'Subscribe Now', 'cta-lms' ); ?>
						</button>
					</article>

					<article class="card service-card service-card--featured">
						<div class="service-card__badge-row">
							<span class="badge badge--outline"><?php echo esc_html__( 'One-time', 'cta-lms' ); ?></span>
						</div>
						<h3 class="service-card__title"><?php echo esc_html__( 'Individual 1-on-1', 'cta-lms' ); ?></h3>
						<p class="service-card__price"><?php echo esc_html( $individual_display ); ?></p>
						<p class="service-card__price-unit"><?php echo esc_html__( '/ session', 'cta-lms' ); ?></p>
						<button type="button" class="btn btn-primary btn--lg service-card__cta cta-individual-session-btn" data-cta-individual-session-purchase data-course-title="<?php echo esc_attr( CTA_Supervision_Plans::get_individual_session_name() ); ?>" data-price="<?php echo esc_attr( CTA_Supervision_Plans::get_individual_session_price_label() ); ?>">
							<?php echo esc_html__( 'Purchase Session', 'cta-lms' ); ?>
						</button>
					</article>
				</div>
			</div>
		</div>

	<?php elseif ( $is_locked && empty( $is_pending_approval ) ) : ?>
		<div class="dashboard-main dashboard-main--full">
			<?php if ( ! empty( $home_url ) ) : ?>
				<p class="dashboard-home-link"><a href="<?php echo esc_url( $home_url ); ?>">&larr; <?php echo esc_html__( 'Back to Home', 'cta-lms' ); ?></a></p>
			<?php endif; ?>
			<div class="cta-supervision-locked">
				<div class="cta-alert cta-alert--danger" role="alert">
					<h2><?php echo esc_html__( 'Your supervision access is currently suspended due to a payment issue.', 'cta-lms' ); ?></h2>
					<p><?php echo esc_html__( 'Update your payment method to restore booking access and document uploads.', 'cta-lms' ); ?></p>
					<div class="cta-alert__actions">
						<button type="button" class="btn btn-primary cta-manage-subscription">
							<?php echo esc_html__( 'Update Payment Method', 'cta-lms' ); ?>
						</button>
						<?php
						include CTA_PLUGIN_DIR . 'templates/partials/subscription-modal-actions.php';
						?>
						<a href="mailto:<?php echo esc_attr( sanitize_email( $support_email ) ); ?>" class="btn btn-outline">
							<?php echo esc_html__( 'Contact Support', 'cta-lms' ); ?>
						</a>
					</div>
				</div>
			</div>
			<?php if ( ! empty( $has_supervision_purchase ) ) : ?>
				<?php include CTA_PLUGIN_DIR . 'templates/partials/supervision-onboarding-status.php'; ?>
			<?php endif; ?>
		</div>

	<?php elseif ( ! empty( $is_approved_awaiting_plan ) ) : ?>
		<div class="dashboard-main dashboard-main--full">
			<?php if ( ! empty( $home_url ) ) : ?>
				<p class="dashboard-home-link"><a href="<?php echo esc_url( $home_url ); ?>">&larr; <?php echo esc_html__( 'Back to Home', 'cta-lms' ); ?></a></p>
			<?php endif; ?>
			<div class="cta-empty-state cta-supervision-awaiting-plan">
				<h1><?php echo esc_html__( 'Application Approved', 'cta-lms' ); ?></h1>
				<p><?php echo esc_html( CTA_Associate_Access::get_approved_awaiting_plan_message() ); ?></p>
				<p><?php echo esc_html__( 'Session booking and supervision materials stay locked until a plan is active.', 'cta-lms' ); ?></p>

				<div class="grid-2 grid-2--gap-lg supervision-services__grid cta-supervision-plan-grid">
					<article class="card service-card">
						<div class="service-card__badge-row">
							<span class="badge badge--primary"><?php echo esc_html__( 'Subscription', 'cta-lms' ); ?></span>
						</div>
						<h3 class="service-card__title"><?php echo esc_html( CTA_Supervision_Plans::get_name( CTA_Supervision_Plans::GROUP_SLUG ) ); ?></h3>
						<p class="service-card__price"><?php echo esc_html( '$' . number_format( CTA_Supervision_Plans::get_group_price(), 0 ) ); ?></p>
						<p class="service-card__price-unit"><?php echo esc_html__( '/ month', 'cta-lms' ); ?></p>
						<?php if ( $supervision_url ) : ?>
							<a href="<?php echo esc_url( $supervision_url ); ?>" class="btn btn-primary btn--lg service-card__cta">
								<?php echo esc_html__( 'View Plans & Subscribe', 'cta-lms' ); ?>
							</a>
						<?php else : ?>
							<button type="button" class="btn btn-primary btn--lg service-card__cta cta-subscribe-btn" data-cta-supervision-subscribe data-course-title="<?php echo esc_attr( CTA_Supervision_Plans::get_name( CTA_Supervision_Plans::GROUP_SLUG ) ); ?>" data-price="<?php echo esc_attr( CTA_Supervision_Plans::get_price_label( CTA_Supervision_Plans::GROUP_SLUG ) ); ?>">
								<?php echo esc_html__( 'Subscribe Now', 'cta-lms' ); ?>
							</button>
						<?php endif; ?>
					</article>
					<article class="card service-card service-card--featured">
						<div class="service-card__badge-row">
							<span class="badge badge--outline"><?php echo esc_html__( 'One-time', 'cta-lms' ); ?></span>
						</div>
						<h3 class="service-card__title"><?php echo esc_html__( 'Individual 1-on-1', 'cta-lms' ); ?></h3>
						<p class="service-card__price"><?php echo esc_html( '$' . number_format( CTA_Supervision_Plans::get_individual_session_price(), 0 ) ); ?></p>
						<p class="service-card__price-unit"><?php echo esc_html__( '/ session', 'cta-lms' ); ?></p>
						<button type="button" class="btn btn-primary btn--lg service-card__cta cta-individual-session-btn" data-cta-individual-session-purchase data-course-title="<?php echo esc_attr( CTA_Supervision_Plans::get_individual_session_name() ); ?>" data-price="<?php echo esc_attr( CTA_Supervision_Plans::get_individual_session_price_label() ); ?>">
							<?php echo esc_html__( 'Purchase Session', 'cta-lms' ); ?>
						</button>
					</article>
				</div>
			</div>
		</div>

	<?php elseif ( empty( $can_access_supervision ) ) : ?>
		<div class="dashboard-main dashboard-main--full">
			<?php if ( ! empty( $home_url ) ) : ?>
				<p class="dashboard-home-link"><a href="<?php echo esc_url( $home_url ); ?>">&larr; <?php echo esc_html__( 'Back to Home', 'cta-lms' ); ?></a></p>
			<?php endif; ?>
			<div class="cta-empty-state cta-supervision-pending-approval">
				<h1><?php echo esc_html__( 'Supervision Application Pending', 'cta-lms' ); ?></h1>
				<p><?php echo esc_html( $pending_approval_message ); ?></p>
				<p><?php echo esc_html__( 'Supervision dashboard features, session booking, and materials stay locked until your supervision application is approved. Your general account, CE courses, and Exam Preparation Programs are not affected.', 'cta-lms' ); ?></p>
				<p class="cta-supervision-pending-actions" style="display:flex;flex-wrap:wrap;gap:0.75rem;justify-content:center;margin-top:1.25rem;">
					<?php if ( ! empty( $student_dashboard_url ) ) : ?>
						<a href="<?php echo esc_url( $student_dashboard_url ); ?>" class="btn btn-primary"><?php echo esc_html__( 'Go to My Courses', 'cta-lms' ); ?></a>
					<?php endif; ?>
					<?php if ( ! empty( $courses_url ) ) : ?>
						<a href="<?php echo esc_url( $courses_url ); ?>" class="btn btn-outline"><?php echo esc_html__( 'Browse CE Courses', 'cta-lms' ); ?></a>
					<?php endif; ?>
				</p>
			</div>
			<?php if ( ! empty( $has_supervision_purchase ) ) : ?>
				<?php include CTA_PLUGIN_DIR . 'templates/partials/supervision-onboarding-status.php'; ?>
			<?php endif; ?>
		</div>

	<?php else : ?>

		<aside class="dashboard-sidebar" aria-label="<?php echo esc_attr__( 'Dashboard navigation', 'cta-lms' ); ?>">
			<div class="dashboard-sidebar__user">
				<div class="dashboard-sidebar__avatar" data-user-avatar aria-hidden="true"><?php echo esc_html( $dashboard_user['initials'] ); ?></div>
				<div class="dashboard-sidebar__user-info">
					<p class="dashboard-sidebar__name" data-user-name><?php echo esc_html( $dashboard_user['displayName'] ); ?></p>
					<p class="dashboard-sidebar__license" data-user-license><?php echo esc_html( $dashboard_user['associateNumber'] ); ?></p>
				</div>
			</div>

			<nav class="dashboard-sidebar__nav" id="dashboard-sidebar-nav">
				<a href="#" class="dashboard-sidebar__link dashboard-sidebar__link--active" data-dashboard-nav="sessions" aria-current="page">
					<span class="dashboard-sidebar__icon" aria-hidden="true">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
					</span>
					<?php echo esc_html__( 'My Sessions', 'cta-lms' ); ?>
				</a>
				<?php if ( ! empty( $can_access_supervision_resources ) ) : ?>
					<a href="#documents" class="dashboard-sidebar__link" data-dashboard-nav="documents">
						<span class="dashboard-sidebar__icon" aria-hidden="true">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><line x1="9" y1="15" x2="15" y2="15"></line></svg>
						</span>
						<?php echo esc_html__( 'Documents & Logs', 'cta-lms' ); ?>
					</a>
				<?php endif; ?>
				<a href="#subscription" class="dashboard-sidebar__link" data-dashboard-nav="subscription">
					<span class="dashboard-sidebar__icon" aria-hidden="true">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
					</span>
					<?php echo esc_html__( 'Subscription', 'cta-lms' ); ?>
				</a>
				<a href="#settings" class="dashboard-sidebar__link" data-dashboard-nav="settings">
					<span class="dashboard-sidebar__icon" aria-hidden="true">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
					</span>
					<?php echo esc_html__( 'Account Settings', 'cta-lms' ); ?>
				</a>
			</nav>

			<?php include CTA_PLUGIN_DIR . 'templates/partials/dashboard-sidebar-footer.php'; ?>
		</aside>

		<?php include CTA_PLUGIN_DIR . 'templates/partials/dashboard-mobile-bar.php'; ?>

		<div class="dashboard-main">

			<div class="dashboard-panel dashboard-panel--active" data-dashboard-panel="sessions">
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
						<p class="dashboard-welcome__subtitle"><?php echo esc_html( $associate_number ? $associate_number : $dashboard_user['associateNumber'] ); ?></p>
					</div>
					<div class="dashboard-stats" aria-label="<?php echo esc_attr__( 'Supervision summary', 'cta-lms' ); ?>">
						<span class="dashboard-stat-pill dashboard-stat-pill--success"><?php echo esc_html( $plan_label ); ?></span>
						<span class="dashboard-stat-pill"><?php echo esc_html( $next_session_label ); ?></span>
						<span class="dashboard-stat-pill">
							<?php
							printf(
								/* translators: %d: document count */
								esc_html( _n( '%d Document Uploaded', '%d Documents Uploaded', $document_count, 'cta-lms' ) ),
								(int) $document_count
							);
							?>
						</span>
					</div>
				</header>

				<?php if ( ! empty( $has_supervision_purchase ) ) : ?>
					<?php include CTA_PLUGIN_DIR . 'templates/partials/supervision-onboarding-status.php'; ?>
				<?php endif; ?>

				<section class="dashboard-section" aria-labelledby="subscription-status-title">
					<h2 class="dashboard-section__title" id="subscription-status-title"><?php echo esc_html__( 'Subscription Status', 'cta-lms' ); ?></h2>
					<article class="card subscription-card">
						<div class="subscription-card__details">
							<p class="subscription-card__plan">
								<span class="badge badge--primary"><?php echo esc_html( $plan_label ); ?></span>
							</p>
							<?php if ( $next_billing_date ) : ?>
								<p class="subscription-card__billing">
									<?php
									$cancel_pending = '1' === (string) get_user_meta( get_current_user_id(), 'cta_supervision_cancel_at_period_end', true );
									if ( $cancel_pending ) {
										echo esc_html( $next_billing_date );
									} else {
										printf(
											/* translators: %s: billing date */
											esc_html__( 'Next billing date: %s', 'cta-lms' ),
											esc_html( $next_billing_date )
										);
									}
									?>
								</p>
							<?php endif; ?>
						</div>
						<div class="subscription-card__actions">
							<?php
							$cancel_pending_badge = '1' === (string) get_user_meta( get_current_user_id(), 'cta_supervision_cancel_at_period_end', true );
							if ( $cancel_pending_badge ) :
								?>
								<span class="badge badge--warning"><?php echo esc_html__( 'Cancels at period end', 'cta-lms' ); ?></span>
							<?php else : ?>
								<span class="badge badge--success"><?php echo esc_html__( 'Active', 'cta-lms' ); ?></span>
							<?php endif; ?>
							<button type="button" class="btn btn-outline cta-manage-subscription">
								<?php echo esc_html__( 'Manage Subscription', 'cta-lms' ); ?>
							</button>
						</div>
					</article>
				</section>

				<section class="dashboard-section" aria-labelledby="upcoming-sessions-title">
					<h2 class="dashboard-section__title" id="upcoming-sessions-title"><?php echo esc_html__( 'Upcoming Sessions', 'cta-lms' ); ?></h2>
					<?php if ( empty( $upcoming_sessions ) ) : ?>
						<div class="cta-empty-state cta-empty-state--inline">
							<p><?php echo esc_html__( 'No upcoming sessions booked', 'cta-lms' ); ?></p>
							<?php if ( $supervision_url && ! empty( $can_access_booking ) ) : ?>
								<a href="<?php echo esc_url( $supervision_url . '#booking' ); ?>" class="btn btn-primary">
									<?php echo esc_html__( 'Book a Session', 'cta-lms' ); ?>
								</a>
							<?php endif; ?>
						</div>
					<?php else : ?>
						<div class="session-list" id="cta-upcoming-sessions">
							<?php foreach ( $upcoming_sessions as $session ) : ?>
								<?php include CTA_PLUGIN_DIR . 'templates/partials/session-upcoming-card.php'; ?>
							<?php endforeach; ?>
						</div>
						<?php if ( $supervision_url && ! empty( $can_access_booking ) ) : ?>
							<a href="<?php echo esc_url( $supervision_url . '#booking' ); ?>" class="btn btn-outline">
								<?php echo esc_html__( 'Book New Session', 'cta-lms' ); ?>
							</a>
						<?php endif; ?>
					<?php endif; ?>
				</section>

				<section class="dashboard-section" aria-labelledby="session-history-title">
					<h2 class="dashboard-section__title" id="session-history-title"><?php echo esc_html__( 'Session History', 'cta-lms' ); ?></h2>
					<?php if ( empty( $session_history ) ) : ?>
						<p class="cta-empty-state cta-empty-state--inline"><?php echo esc_html__( 'No past sessions yet', 'cta-lms' ); ?></p>
					<?php else : ?>
						<div class="session-table-wrap">
							<table class="session-table">
								<thead>
									<tr>
										<th scope="col"><?php echo esc_html__( 'Date', 'cta-lms' ); ?></th>
										<th scope="col"><?php echo esc_html__( 'Type', 'cta-lms' ); ?></th>
										<th scope="col"><?php echo esc_html__( 'Duration', 'cta-lms' ); ?></th>
										<th scope="col"><?php echo esc_html__( 'Status', 'cta-lms' ); ?></th>
										<th scope="col"><?php echo esc_html__( 'Notes', 'cta-lms' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $session_history as $history ) : ?>
										<?php $status = $dashboard->get_history_status( $history ); ?>
										<tr>
											<td><?php echo esc_html( cta_lms_format_session_date( $history->session_date, 'M j, Y' ) ); ?></td>
											<td><?php echo esc_html( ucfirst( $history->session_type ) ); ?></td>
											<td><?php echo esc_html( $dashboard->format_duration_label( $history ) ); ?></td>
											<td><span class="badge <?php echo esc_attr( $status['class'] ); ?>"><?php echo esc_html( $status['label'] ); ?></span></td>
											<td>
												<?php
												$notes_display = '—';
												if ( ! empty( $history->notes ) ) {
													$decoded = json_decode( (string) $history->notes, true );
													if ( ! is_array( $decoded ) ) {
														$notes_display = wp_strip_all_tags( $history->notes );
													}
												}
												echo esc_html( $notes_display );
												?>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</section>
			</div>

			<?php if ( ! empty( $can_access_supervision_resources ) ) : ?>
			<div class="dashboard-panel" data-dashboard-panel="documents" hidden>
				<header class="dashboard-welcome">
					<div>
						<h1 class="dashboard-welcome__greeting"><?php echo esc_html__( 'Documents & BBS Logs', 'cta-lms' ); ?></h1>
						<p class="dashboard-welcome__subtitle"><?php echo esc_html__( 'Upload agreements, hour logs, and verification forms', 'cta-lms' ); ?></p>
					</div>
				</header>

				<section class="dashboard-section" aria-labelledby="documents-title">
					<h2 class="dashboard-section__title" id="documents-title"><?php echo esc_html__( 'Documents & BBS Logs', 'cta-lms' ); ?></h2>

					<div class="cta-upload-controls">
						<label class="form-label" for="cta-doc-category"><?php echo esc_html__( 'Document Category', 'cta-lms' ); ?></label>
						<select id="cta-doc-category" class="form-select cta-doc-category-select">
							<?php foreach ( $document_categories as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="upload-zone" id="cta-upload-zone" role="button" tabindex="0" aria-label="<?php echo esc_attr__( 'Upload BBS documents', 'cta-lms' ); ?>">
						<span class="upload-zone__icon" aria-hidden="true">
							<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
						</span>
						<p class="upload-zone__text"><?php echo esc_html__( 'Drag files here or click to upload', 'cta-lms' ); ?></p>
						<p class="upload-zone__hint"><?php echo esc_html__( 'PDF, DOC, or DOCX — max 10 MB', 'cta-lms' ); ?></p>
						<p class="cta-upload-progress" id="cta-upload-progress" hidden><?php echo esc_html__( 'Uploading…', 'cta-lms' ); ?></p>
						<p class="cta-upload-error" id="cta-upload-error" hidden role="alert"></p>
					</div>
					<input type="file" id="cta-upload-input" class="cta-upload-input" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" hidden>

					<div class="document-list" id="cta-document-list">
						<?php if ( empty( $documents ) ) : ?>
							<p class="cta-empty-state cta-empty-state--inline" id="cta-document-empty"><?php echo esc_html__( 'No documents uploaded yet', 'cta-lms' ); ?></p>
						<?php else : ?>
							<?php foreach ( $documents as $document ) : ?>
								<?php echo $dashboard->render_document_row_html( $document ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</section>
			</div>
			<?php endif; ?>

			<div class="dashboard-panel" data-dashboard-panel="subscription" hidden>
				<header class="dashboard-welcome">
					<div>
						<h1 class="dashboard-welcome__greeting"><?php echo esc_html__( 'Subscription', 'cta-lms' ); ?></h1>
						<p class="dashboard-welcome__subtitle"><?php echo esc_html__( 'Manage your group supervision plan', 'cta-lms' ); ?></p>
					</div>
				</header>

				<article class="card subscription-card">
					<div class="subscription-card__details">
						<p class="subscription-card__plan">
							<?php
							printf(
								/* translators: 1: plan name, 2: monthly price */
								esc_html__( '%1$s — %2$s/month', 'cta-lms' ),
								esc_html( $plan_label ),
								esc_html( $monthly_display )
							);
							?>
						</p>
						<?php if ( $next_billing_date ) : ?>
							<p class="subscription-card__billing">
								<?php
								$cancel_pending = '1' === (string) get_user_meta( get_current_user_id(), 'cta_supervision_cancel_at_period_end', true );
								if ( $cancel_pending ) {
									echo esc_html( $next_billing_date );
								} else {
									printf(
										/* translators: %s: billing date */
										esc_html__( 'Next billing date: %s', 'cta-lms' ),
										esc_html( $next_billing_date )
									);
								}
								?>
							</p>
						<?php endif; ?>
					</div>
					<div class="subscription-card__actions">
						<?php
						$cancel_pending_badge = '1' === (string) get_user_meta( get_current_user_id(), 'cta_supervision_cancel_at_period_end', true );
						if ( $cancel_pending_badge ) :
							?>
							<span class="badge badge--warning"><?php echo esc_html__( 'Cancels at period end', 'cta-lms' ); ?></span>
						<?php else : ?>
							<span class="badge badge--success"><?php echo esc_html__( 'Active', 'cta-lms' ); ?></span>
						<?php endif; ?>
						<button type="button" class="btn btn-outline cta-manage-subscription">
							<?php echo esc_html__( 'Manage Subscription', 'cta-lms' ); ?>
						</button>
					</div>
				</article>
			</div>

			<div class="dashboard-panel" data-dashboard-panel="settings" hidden>
				<header class="dashboard-welcome">
					<div>
						<h1 class="dashboard-welcome__greeting"><?php echo esc_html__( 'Account Settings', 'cta-lms' ); ?></h1>
						<p class="dashboard-welcome__subtitle"><?php echo esc_html__( 'Update your associate profile', 'cta-lms' ); ?></p>
					</div>
				</header>

				<section class="dashboard-settings card" aria-labelledby="supervision-settings-title">
					<h2 class="dashboard-section__title" id="supervision-settings-title"><?php echo esc_html__( 'Profile', 'cta-lms' ); ?></h2>
					<form class="dashboard-settings-form cta-dashboard-settings-form" action="#" method="post" novalidate>
						<div class="form-group">
							<label class="form-label" for="sup-name"><?php echo esc_html__( 'Full Name', 'cta-lms' ); ?></label>
							<input type="text" id="sup-name" name="full_name" class="form-input" value="<?php echo esc_attr( $dashboard_user['displayName'] ); ?>" required>
						</div>
						<div class="form-group">
							<label class="form-label" for="sup-email"><?php echo esc_html__( 'Email', 'cta-lms' ); ?></label>
							<input type="email" id="sup-email" name="email" class="form-input" value="<?php echo esc_attr( $dashboard_user['email'] ); ?>" readonly>
						</div>
						<div class="form-group">
							<label class="form-label" for="sup-associate"><?php echo esc_html__( 'Associate Number', 'cta-lms' ); ?></label>
							<input type="text" id="sup-associate" name="associate_number" class="form-input" value="<?php echo esc_attr( $associate_number ); ?>" required>
						</div>
						<button type="submit" class="btn btn-primary" data-save-settings><?php echo esc_html__( 'Save Changes', 'cta-lms' ); ?></button>
					</form>
				</section>
			</div>

		</div>
	<?php endif; ?>
</div>
</div>

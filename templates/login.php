<?php
/**
 * Login and registration template for [cta_login_form] shortcode.
 *
 * @package CTA_LMS
 *
 * @var bool    $is_logged_in      Whether the visitor is logged in.
 * @var WP_User $user              Current user when logged in.
 * @var string  $dashboard_url     Dashboard URL for the current user.
 * @var string  $home_url          Site home URL.
 * @var string  $logo_url          Plugin logo URL.
 * @var string  $site_name         Site name.
 * @var string  $lost_password_url WordPress lost password URL.
 * @var string  $logout_url        WordPress logout URL.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php if ( $is_logged_in ) : ?>
	<div class="cta-plugin-wrapper">
	<div class="cta-lms cta-login-shortcode">
		<div class="cta-already-logged-in">
			<p>
				<?php
				printf(
					/* translators: %s: user display name */
					esc_html__( 'You are already logged in as %s', 'cta-lms' ),
					esc_html( $user->display_name )
				);
				?>
			</p>
			<?php if ( $dashboard_url ) : ?>
				<a href="<?php echo esc_url( $dashboard_url ); ?>" class="btn btn-primary">
					<?php echo esc_html__( 'Go to Dashboard', 'cta-lms' ); ?>
				</a>
			<?php endif; ?>
			<a href="<?php echo esc_url( $logout_url ); ?>" class="btn btn-outline">
				<?php echo esc_html__( 'Log Out', 'cta-lms' ); ?>
			</a>
		</div>
	</div>
	</div>
<?php else : ?>
	<div class="cta-plugin-wrapper">
	<div class="cta-lms cta-login-shortcode auth-page">
		<div class="auth-page__layout">
			<aside class="auth-page__brand" aria-label="<?php echo esc_attr__( 'About CTA', 'cta-lms' ); ?>">
				<div class="auth-page__brand-pattern" aria-hidden="true">
					<span class="auth-page__brand-shape auth-page__brand-shape--1"></span>
					<span class="auth-page__brand-shape auth-page__brand-shape--2"></span>
					<span class="auth-page__brand-shape auth-page__brand-shape--3"></span>
				</div>

				<div class="auth-page__logo">
					<a href="<?php echo esc_url( $home_url ); ?>">
						<img
							src="<?php echo esc_url( $logo_url ); ?>"
							alt="<?php echo esc_attr( $site_name ); ?>"
							width="200"
							height="52"
							decoding="async"
							onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"
						>
						<span style="display:none;"><?php echo esc_html( $site_name ); ?></span>
					</a>
				</div>

				<div class="auth-page__brand-content">
					<h1 class="auth-page__brand-title"><?php echo esc_html__( 'Welcome to CTA', 'cta-lms' ); ?></h1>
					<p class="auth-page__brand-text">
						<?php echo esc_html__( 'Access your courses, track your progress, and manage your supervision sessions all in one place.', 'cta-lms' ); ?>
					</p>
				</div>
			</aside>

			<main class="auth-page__form-panel">
				<div class="auth-page__form-container">
					<form
						id="cta-login-form"
						class="auth-form"
						method="post"
						aria-labelledby="cta-login-form-title"
						novalidate
					>
						<h2 class="auth-form__title" id="cta-login-form-title"><?php echo esc_html__( 'Log In', 'cta-lms' ); ?></h2>

						<?php wp_nonce_field( 'cta_login_action', 'cta_login_nonce' ); ?>

						<div id="cta-login-error" class="cta-form-error" style="display:none" role="alert"></div>

						<div class="form-group">
							<label class="form-label" for="cta-login-email"><?php echo esc_html__( 'Email', 'cta-lms' ); ?></label>
							<input
								type="email"
								id="cta-login-email"
								name="cta_email"
								class="form-input"
								autocomplete="email"
								required
							>
						</div>

						<?php
						echo cta_lms_render_password_field(
							array(
								'id'           => 'cta-login-password',
								'name'         => 'cta_password',
								'label'        => __( 'Password', 'cta-lms' ),
								'autocomplete' => 'current-password',
							)
						); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>

						<div class="auth-form__row">
							<span></span>
							<a href="<?php echo esc_url( $lost_password_url ); ?>" class="auth-form__forgot">
								<?php echo esc_html__( 'Forgot Password?', 'cta-lms' ); ?>
							</a>
						</div>

						<button type="button" id="cta-login-btn" class="btn btn-primary btn--lg auth-form__submit">
							<?php echo esc_html__( 'Log In', 'cta-lms' ); ?>
						</button>

						<div class="auth-form__divider" aria-hidden="true"><?php echo esc_html__( 'or', 'cta-lms' ); ?></div>

						<div class="auth-form__switch">
							<p class="auth-form__switch-text">
								<?php echo esc_html__( "Don't have an account?", 'cta-lms' ); ?>
							</p>
							<button type="button" class="btn btn-outline btn--lg btn--full auth-form__switch-action" data-cta-auth-toggle="register">
								<?php echo esc_html__( 'Register here', 'cta-lms' ); ?>
							</button>
						</div>
					</form>

					<form
						id="cta-register-form"
						class="auth-form form-hidden"
						method="post"
						aria-labelledby="cta-register-form-title"
						hidden
						novalidate
					>
						<h2 class="auth-form__title" id="cta-register-form-title"><?php echo esc_html__( 'Create Account', 'cta-lms' ); ?></h2>

						<?php wp_nonce_field( 'cta_register_action', 'cta_register_nonce' ); ?>

						<div id="cta-register-error" class="cta-form-error" style="display:none" role="alert"></div>
						<div id="cta-register-success" class="cta-form-success" style="display:none" role="status"></div>

						<div class="form-group">
							<label class="form-label" for="cta-register-fullname"><?php echo esc_html__( 'Full Name', 'cta-lms' ); ?></label>
							<input
								type="text"
								id="cta-register-fullname"
								name="cta_fullname"
								class="form-input"
								autocomplete="name"
								required
							>
						</div>

						<div class="form-group">
							<label class="form-label" for="cta-register-email"><?php echo esc_html__( 'Email', 'cta-lms' ); ?></label>
							<input
								type="email"
								id="cta-register-email"
								name="cta_reg_email"
								class="form-input"
								autocomplete="email"
								required
							>
						</div>

						<?php
						echo cta_lms_render_password_field(
							array(
								'id'           => 'cta-register-password',
								'name'         => 'cta_reg_password',
								'label'        => __( 'Password', 'cta-lms' ),
								'autocomplete' => 'new-password',
							)
						); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

						echo cta_lms_render_password_field(
							array(
								'id'           => 'cta-register-confirm-password',
								'name'         => 'cta_reg_confirm_password',
								'label'        => __( 'Confirm Password', 'cta-lms' ),
								'autocomplete' => 'new-password',
							)
						); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>

						<div class="form-group">
							<label class="form-label" for="cta-register-user-type"><?php echo esc_html__( 'I am a...', 'cta-lms' ); ?></label>
							<select id="cta-register-user-type" name="cta_user_type" class="form-select" required>
								<option value=""><?php echo esc_html__( 'I am a...', 'cta-lms' ); ?></option>
								<option value="cta_licensed_professional">
									<?php echo esc_html__( 'Licensed Professional (LMFT, LCSW, LPCC, LEP)', 'cta-lms' ); ?>
								</option>
								<option value="cta_associate">
									<?php echo esc_html__( 'Registered Associate (AMFT, ASW, APCC)', 'cta-lms' ); ?>
								</option>
							</select>
							<p class="form-hint" id="cta-register-role-hint" style="margin-top:0.35rem;font-size:0.85em;opacity:0.85;">
								<?php echo esc_html__( 'Registered Associates: your account is created immediately for CE courses. Your supervision application is sent to CTA admin for approval.', 'cta-lms' ); ?>
							</p>
						</div>

						<div id="cta-register-agency-fields" class="cta-register-agency-fields" hidden>
							<div class="form-group">
								<label class="form-label" for="cta-register-employer"><?php echo esc_html__( 'Employer/Agency Name', 'cta-lms' ); ?></label>
								<input
									type="text"
									id="cta-register-employer"
									name="employer_agency_name"
									class="form-input"
									autocomplete="organization"
								>
							</div>
							<div class="form-group">
								<label class="form-label" for="cta-register-agency-rep-name"><?php echo esc_html__( 'Agency Representative Name', 'cta-lms' ); ?></label>
								<input
									type="text"
									id="cta-register-agency-rep-name"
									name="agency_representative_name"
									class="form-input"
									autocomplete="name"
								>
							</div>
							<div class="form-group">
								<label class="form-label" for="cta-register-agency-rep-email"><?php echo esc_html__( 'Agency Representative Email', 'cta-lms' ); ?></label>
								<input
									type="email"
									id="cta-register-agency-rep-email"
									name="agency_representative_email"
									class="form-input"
									autocomplete="email"
								>
							</div>
							<p class="form-hint" style="margin-top:0;font-size:0.85em;opacity:0.85;">
								<?php echo esc_html__( 'These details are required so CTA admin can review your supervision application. Your agency representative will also receive notification by email.', 'cta-lms' ); ?>
							</p>
						</div>

						<button type="button" id="cta-register-btn" class="btn btn-primary btn--lg auth-form__submit">
							<?php echo esc_html__( 'Create Account', 'cta-lms' ); ?>
						</button>

						<div class="auth-form__switch">
							<p class="auth-form__switch-text">
								<?php echo esc_html__( 'Already have an account?', 'cta-lms' ); ?>
							</p>
							<button type="button" class="btn btn-outline btn--lg btn--full auth-form__switch-action" data-cta-auth-toggle="login">
								<?php echo esc_html__( 'Log In', 'cta-lms' ); ?>
							</button>
						</div>
					</form>
				</div>
			</main>
		</div>
	</div>
	</div>
<?php endif; ?>

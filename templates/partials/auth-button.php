<?php
/**
 * Login / Dashboard auth button partial.
 *
 * Renders both guest and logged-in controls so cached Elementor headers can
 * still flip to the correct state via the inline cookie/AJAX sync script.
 *
 * @package CTA_LMS
 *
 * @var string $button_class          CSS classes for the button.
 * @var string $login_url             Login page URL.
 * @var string $login_text            Guest button label.
 * @var string $dashboard_url         General (CE) dashboard URL.
 * @var string $dashboard_text        Logged-in primary label.
 * @var string $logout_url            Logout URL.
 * @var string $courses_url           Browse CE courses URL.
 * @var string $exam_prep_url         Browse exam prep URL.
 * @var string $display_name          Current user display name.
 * @var bool   $is_logged_in          Server-side login state.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_logged_in   = ! empty( $is_logged_in );
$display_name   = isset( $display_name ) ? (string) $display_name : '';
$dashboard_url  = isset( $dashboard_url ) ? (string) $dashboard_url : '';
$dashboard_text = isset( $dashboard_text ) ? (string) $dashboard_text : __( 'My Dashboard', 'cta-lms' );
$login_url      = isset( $login_url ) ? (string) $login_url : '';
$login_text     = isset( $login_text ) ? (string) $login_text : __( 'Login', 'cta-lms' );
$logout_url     = isset( $logout_url ) ? (string) $logout_url : '';
$courses_url    = isset( $courses_url ) ? (string) $courses_url : '';
$exam_prep_url  = isset( $exam_prep_url ) ? (string) $exam_prep_url : '';
$button_class   = isset( $button_class ) ? (string) $button_class : 'btn cta-auth-button btn-outline';

$certs_url    = $dashboard_url ? $dashboard_url . '#certificates' : '';
$settings_url = $dashboard_url ? $dashboard_url . '#settings' : '';
$courses_panel_url = $dashboard_url ? $dashboard_url . '#courses' : $dashboard_url;
?>
<div
	class="cta-plugin-wrapper cta-auth-button-wrap"
	data-cta-auth-root
	data-logged-in="<?php echo $is_logged_in ? 'yes' : 'no'; ?>"
	data-login-url="<?php echo esc_url( $login_url ); ?>"
	data-dashboard-url="<?php echo esc_url( $dashboard_url ); ?>"
	data-logout-url="<?php echo esc_url( $logout_url ); ?>"
	data-login-text="<?php echo esc_attr( $login_text ); ?>"
	data-dashboard-text="<?php echo esc_attr( $dashboard_text ); ?>"
	data-display-name="<?php echo esc_attr( $display_name ); ?>"
>
	<a
		href="<?php echo esc_url( $login_url ? $login_url : '#' ); ?>"
		class="<?php echo esc_attr( $button_class ); ?> cta-auth-link cta-auth-link--guest"
		data-cta-auth-guest
		<?php echo $is_logged_in ? 'hidden' : ''; ?>
	>
		<?php echo esc_html( $login_text ); ?>
	</a>

	<div class="cta-auth-account<?php echo $is_logged_in ? ' is-openable' : ''; ?>" data-cta-auth-user <?php echo $is_logged_in ? '' : 'hidden'; ?>>
		<button
			type="button"
			class="<?php echo esc_attr( $button_class ); ?> cta-auth-account__toggle"
			data-cta-auth-toggle
			aria-expanded="false"
			aria-haspopup="true"
		>
			<span class="cta-auth-account__label" data-cta-auth-label>
				<?php
				echo esc_html(
					$display_name
						? sprintf(
							/* translators: %s: user display name */
							__( 'Hi, %s', 'cta-lms' ),
							$display_name
						)
						: $dashboard_text
				);
				?>
			</span>
			<span class="cta-auth-account__caret" aria-hidden="true">▾</span>
		</button>
		<div class="cta-auth-account__menu" data-cta-auth-menu hidden>
			<?php if ( $dashboard_url ) : ?>
				<a href="<?php echo esc_url( $dashboard_url ); ?>"><?php echo esc_html__( 'My Dashboard', 'cta-lms' ); ?></a>
			<?php endif; ?>
			<?php if ( $courses_panel_url ) : ?>
				<a href="<?php echo esc_url( $courses_panel_url ); ?>"><?php echo esc_html__( 'My Courses', 'cta-lms' ); ?></a>
			<?php endif; ?>
			<?php if ( $certs_url ) : ?>
				<a href="<?php echo esc_url( $certs_url ); ?>"><?php echo esc_html__( 'My Certificates', 'cta-lms' ); ?></a>
			<?php endif; ?>
			<?php if ( $settings_url ) : ?>
				<a href="<?php echo esc_url( $settings_url ); ?>"><?php echo esc_html__( 'Account Settings', 'cta-lms' ); ?></a>
			<?php endif; ?>
			<?php if ( $courses_url ) : ?>
				<a href="<?php echo esc_url( $courses_url ); ?>"><?php echo esc_html__( 'Browse CE Courses', 'cta-lms' ); ?></a>
			<?php endif; ?>
			<?php if ( $exam_prep_url ) : ?>
				<a href="<?php echo esc_url( $exam_prep_url ); ?>"><?php echo esc_html__( 'Browse Exam Preparation', 'cta-lms' ); ?></a>
			<?php endif; ?>
			<?php if ( $logout_url ) : ?>
				<a href="<?php echo esc_url( $logout_url ); ?>" class="cta-auth-account__logout"><?php echo esc_html__( 'Log Out', 'cta-lms' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</div>
<script>
(function () {
  try {
    var roots = document.querySelectorAll("[data-cta-auth-root]");
    if (!roots.length) return;
    var cookieLoggedIn = /(?:^|;\s*)wordpress_logged_in_/.test(document.cookie);
    roots.forEach(function (root) {
      var serverLoggedIn = root.getAttribute("data-logged-in") === "yes";
      var loggedIn = serverLoggedIn || cookieLoggedIn;
      var guest = root.querySelector("[data-cta-auth-guest]");
      var user = root.querySelector("[data-cta-auth-user]");
      if (!guest || !user) return;
      if (loggedIn) {
        guest.hidden = true;
        user.hidden = false;
        root.setAttribute("data-logged-in", "yes");
      } else {
        guest.hidden = false;
        user.hidden = true;
        root.setAttribute("data-logged-in", "no");
      }
    });
  } catch (e) {}
})();
</script>

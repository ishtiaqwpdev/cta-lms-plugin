<?php
/**
 * Welcome email body.
 *
 * @var WP_User $user
 * @var string  $role_label
 * @var string  $dashboard_url
 * @var string  $faq_url
 * @var bool    $is_associate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p><?php printf( esc_html__( 'Hi %s,', 'cta-lms' ), esc_html( $user->display_name ) ); ?></p>

<p><?php esc_html_e( 'Welcome to Clinical Training and Supervision Academy!', 'cta-lms' ); ?></p>
<p><?php esc_html_e( 'Your account has been created successfully.', 'cta-lms' ); ?></p>

<div class="highlight-box">
	<p><strong><?php esc_html_e( 'Account Type:', 'cta-lms' ); ?></strong> <?php echo esc_html( $role_label ); ?></p>
	<p><strong><?php esc_html_e( 'Email:', 'cta-lms' ); ?></strong> <?php echo esc_html( $user->user_email ); ?></p>
</div>

<p><strong><?php esc_html_e( 'You can now:', 'cta-lms' ); ?></strong></p>
<ul>
	<li><?php esc_html_e( 'Browse and enroll in CE courses', 'cta-lms' ); ?></li>
	<li><?php esc_html_e( 'Download certificates upon completion', 'cta-lms' ); ?></li>
	<?php if ( ! empty( $is_associate ) ) : ?>
		<li><?php esc_html_e( 'Book supervision sessions', 'cta-lms' ); ?></li>
	<?php endif; ?>
</ul>

<p><a class="btn-email" href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'Go to Dashboard', 'cta-lms' ); ?></a></p>

<hr class="divider">

<p><?php esc_html_e( 'Need help getting started?', 'cta-lms' ); ?></p>
<?php if ( $faq_url && home_url( '/' ) !== $faq_url ) : ?>
	<p><a href="<?php echo esc_url( $faq_url ); ?>" style="color:#3266A9;"><?php esc_html_e( 'Visit our FAQ page', 'cta-lms' ); ?></a></p>
<?php endif; ?>

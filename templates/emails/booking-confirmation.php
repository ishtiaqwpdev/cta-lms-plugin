<?php
/**
 * Booking confirmation email body.
 *
 * @var WP_User $user
 * @var object  $session
 * @var string  $session_type
 * @var string  $session_type_label
 * @var string  $session_date
 * @var string  $session_time
 * @var string  $duration_label
 * @var string  $dashboard_url
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p><?php printf( esc_html__( 'Hi %s,', 'cta-lms' ), esc_html( $user->display_name ) ); ?></p>

<h2><?php esc_html_e( 'Your supervision session is confirmed!', 'cta-lms' ); ?> ✅</h2>

<div class="highlight-box">
	<p><strong><?php esc_html_e( 'Session Type:', 'cta-lms' ); ?></strong> <?php echo esc_html( $session_type_label ); ?></p>
	<p><strong><?php esc_html_e( 'Date:', 'cta-lms' ); ?></strong> <?php echo esc_html( $session_date ); ?></p>
	<p><strong><?php esc_html_e( 'Time:', 'cta-lms' ); ?></strong> <?php echo esc_html( $session_time ); ?></p>
	<p><strong><?php esc_html_e( 'Duration:', 'cta-lms' ); ?></strong> <?php echo esc_html( $duration_label ); ?></p>
	<?php if ( 'group' === $session_type ) : ?>
		<p><strong><?php esc_html_e( 'Your spot:', 'cta-lms' ); ?></strong> <?php echo esc_html( (int) $session->seats_booked . ' of ' . (int) $session->seats_total ); ?></p>
	<?php endif; ?>
</div>

<p><strong><?php esc_html_e( 'What to prepare:', 'cta-lms' ); ?></strong></p>
<ul>
	<li><?php esc_html_e( 'Review any cases you\'d like to discuss', 'cta-lms' ); ?></li>
	<li><?php esc_html_e( 'Have your BBS hours log ready', 'cta-lms' ); ?></li>
	<li><?php esc_html_e( 'Join 5 minutes early', 'cta-lms' ); ?></li>
</ul>

<p><a class="btn-email" href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'View My Sessions', 'cta-lms' ); ?></a></p>

<hr class="divider">

<p class="small-text"><strong><?php esc_html_e( 'Cancellation policy:', 'cta-lms' ); ?></strong> <?php esc_html_e( 'Cancellations must be made at least 24 hours before your session. Late cancellations cannot be refunded.', 'cta-lms' ); ?></p>

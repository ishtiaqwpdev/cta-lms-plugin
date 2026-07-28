<?php
/**
 * Session reminder email body.
 *
 * @var WP_User $user
 * @var object  $session
 * @var string  $session_type_label
 * @var string  $session_date
 * @var string  $session_time
 * @var string  $duration_label
 * @var string  $cancellation_deadline
 * @var string  $dashboard_url
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p><?php printf( esc_html__( 'Hi %s,', 'cta-lms' ), esc_html( $user->display_name ) ); ?></p>

<h2><?php esc_html_e( 'Reminder: Your supervision session is tomorrow', 'cta-lms' ); ?> 🗓️</h2>

<div class="highlight-box">
	<p><strong><?php esc_html_e( 'Date:', 'cta-lms' ); ?></strong> <?php echo esc_html( $session_date ); ?></p>
	<p><strong><?php esc_html_e( 'Time:', 'cta-lms' ); ?></strong> <?php echo esc_html( $session_time ); ?></p>
	<p><strong><?php esc_html_e( 'Type:', 'cta-lms' ); ?></strong> <?php echo esc_html( $session_type_label ); ?></p>
	<p><strong><?php esc_html_e( 'Duration:', 'cta-lms' ); ?></strong> <?php echo esc_html( $duration_label ); ?></p>
</div>

<p><strong><?php esc_html_e( 'Before your session:', 'cta-lms' ); ?></strong></p>
<ul>
	<li><?php esc_html_e( 'Upload any new BBS log hours', 'cta-lms' ); ?></li>
	<li><?php esc_html_e( 'Prepare cases for discussion', 'cta-lms' ); ?></li>
	<li><?php esc_html_e( 'Test your video connection', 'cta-lms' ); ?></li>
</ul>

<p><a class="btn-email" href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'View Session Details', 'cta-lms' ); ?></a></p>

<hr class="divider">

<p class="small-text">
	<?php
	printf(
		/* translators: %s: cancellation deadline datetime */
		esc_html__( 'Need to cancel? You must cancel before %s to avoid being charged.', 'cta-lms' ),
		esc_html( $cancellation_deadline )
	);
	?>
</p>

<?php
/**
 * Supervision access locked email body.
 *
 * @var WP_User $user
 * @var string  $supervision_url
 * @var string  $support_email
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p><?php printf( esc_html__( 'Hi %s,', 'cta-lms' ), esc_html( $user->display_name ) ); ?></p>

<h2><?php esc_html_e( 'Your supervision access has been paused', 'cta-lms' ); ?> ⏸️</h2>

<p><?php esc_html_e( 'Your supervision subscription has been cancelled or a recent payment could not be processed.', 'cta-lms' ); ?></p>

<p><strong><?php esc_html_e( 'What this means:', 'cta-lms' ); ?></strong></p>
<ul>
	<li><?php esc_html_e( 'You cannot book new sessions', 'cta-lms' ); ?></li>
	<li><?php esc_html_e( 'Existing session history is preserved', 'cta-lms' ); ?></li>
	<li><?php esc_html_e( 'Your uploaded documents are safe', 'cta-lms' ); ?></li>
</ul>

<p><strong><?php esc_html_e( 'To reactivate:', 'cta-lms' ); ?></strong> <?php esc_html_e( 'Subscribe again from the supervision page.', 'cta-lms' ); ?></p>

<p><a class="btn-email" href="<?php echo esc_url( $supervision_url ); ?>"><?php esc_html_e( 'Reactivate Supervision', 'cta-lms' ); ?></a></p>

<hr class="divider">

<p class="small-text">
	<?php
	printf(
		/* translators: %s: support email */
		esc_html__( 'Questions? Contact us at %s', 'cta-lms' ),
		esc_html( $support_email )
	);
	?>
</p>

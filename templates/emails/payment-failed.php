<?php
/**
 * Payment failed email body.
 *
 * @var WP_User $user
 * @var string  $subscription_plan
 * @var string  $portal_url
 * @var string  $support_email
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p><?php printf( esc_html__( 'Hi %s,', 'cta-lms' ), esc_html( $user->display_name ) ); ?></p>

<h2><?php esc_html_e( 'Action required: Payment failed', 'cta-lms' ); ?> ⚠️</h2>

<div class="warning-box">
	<p>
		<?php
		printf(
			/* translators: %s: subscription plan name */
			esc_html__( 'We were unable to process your payment for %s. Your supervision access has been temporarily suspended.', 'cta-lms' ),
			esc_html( $subscription_plan )
		);
		?>
	</p>
</div>

<p><strong><?php esc_html_e( 'To restore your access:', 'cta-lms' ); ?></strong></p>
<ol>
	<li><?php esc_html_e( 'Update your payment method', 'cta-lms' ); ?></li>
	<li><?php esc_html_e( 'Your subscription will automatically resume', 'cta-lms' ); ?></li>
</ol>

<p><a class="btn-email" href="<?php echo esc_url( $portal_url ); ?>"><?php esc_html_e( 'Update Payment Method', 'cta-lms' ); ?></a></p>

<hr class="divider">

<p class="small-text">
	<?php
	printf(
		/* translators: %s: support email */
		esc_html__( 'If you need assistance contact us at %s', 'cta-lms' ),
		esc_html( $support_email )
	);
	?>
</p>
<p class="small-text"><?php esc_html_e( 'Your BBS hours and documents are safe and will be accessible once payment is restored.', 'cta-lms' ); ?></p>

<?php
/**
 * Payment receipt email body.
 *
 * @var WP_User $user
 * @var object  $payment
 * @var string  $product_name
 * @var string  $amount
 * @var string  $payment_date
 * @var string  $transaction_ref
 * @var string  $dashboard_url
 * @var string  $support_email
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p><?php printf( esc_html__( 'Hi %s,', 'cta-lms' ), esc_html( $user->display_name ) ); ?></p>

<h2><?php esc_html_e( 'Payment received — thank you!', 'cta-lms' ); ?> 💳</h2>

<div class="highlight-box">
	<p><strong><?php esc_html_e( 'Item:', 'cta-lms' ); ?></strong> <?php echo esc_html( $product_name ); ?></p>
	<p><strong><?php esc_html_e( 'Amount:', 'cta-lms' ); ?></strong> $<?php echo esc_html( $amount ); ?></p>
	<p><strong><?php esc_html_e( 'Date:', 'cta-lms' ); ?></strong> <?php echo esc_html( $payment_date ); ?></p>
	<p><strong><?php esc_html_e( 'Transaction ID:', 'cta-lms' ); ?></strong> <?php echo esc_html( $transaction_ref ); ?></p>
	<p><strong><?php esc_html_e( 'Status:', 'cta-lms' ); ?></strong> <?php esc_html_e( 'Completed', 'cta-lms' ); ?> ✅</p>
</div>

<p><?php esc_html_e( 'Your access has been activated.', 'cta-lms' ); ?></p>

<p><a class="btn-email" href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'Access Your Content', 'cta-lms' ); ?></a></p>

<hr class="divider">

<p class="small-text">
	<?php esc_html_e( 'This is your official payment receipt. Please keep it for your records.', 'cta-lms' ); ?>
	<?php
	printf(
		/* translators: %s: support email */
		esc_html__( 'For billing questions contact: %s', 'cta-lms' ),
		esc_html( $support_email )
	);
	?>
</p>

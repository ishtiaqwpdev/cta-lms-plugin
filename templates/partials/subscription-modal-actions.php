<?php
/**
 * Renew / cancel actions for subscription management UI.
 *
 * @package CTA_LMS
 *
 * @var bool   $show_renew       Whether to show the renew button.
 * @var string $supervision_url  Supervision pricing page URL.
 * @var string $support_email    Support email for cancellation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$support_email = ! empty( $support_email ) ? $support_email : 'support@clinicaltrainingacademy.com';
?>
<?php if ( ! empty( $show_renew ) && ! empty( $supervision_url ) ) : ?>
	<a
		href="<?php echo esc_url( $supervision_url ); ?>"
		class="cta-renew-btn"
		style="display:block;width:100%;padding:14px;background:#16A34A;color:#fff;text-align:center;font-weight:600;font-size:15px;font-family:'Montserrat',sans-serif;text-decoration:none;margin-bottom:10px;border:none;cursor:pointer;border-radius:10px;"
	>
		<?php echo esc_html__( '🔄 Renew Subscription', 'cta-lms' ); ?>
	</a>
<?php else : ?>
	<a
		href="<?php echo esc_url( 'mailto:' . sanitize_email( $support_email ) ); ?>"
		style="display:block;text-align:center;font-size:13px;color:#6B7280;margin-top:8px;margin-bottom:10px;text-decoration:underline;"
	>
		<?php echo esc_html__( 'Contact support about billing', 'cta-lms' ); ?>
	</a>
<?php endif; ?>

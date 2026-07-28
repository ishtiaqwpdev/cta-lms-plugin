<?php
/**
 * Email base wrapper template.
 *
 * @package CTA_LMS
 *
 * @var string $template
 * @var string $email_subject
 * @var string $logo_url
 * @var string $custom_body
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $email_subject ?? 'CTA' ); ?></title>
	<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
	<style>
		body { margin: 0; padding: 0; background: #F4F8FF; font-family: 'Montserrat', Arial, Helvetica, sans-serif; font-weight: 400; }
		.email-wrapper { max-width: 600px; margin: 0 auto; background: #ffffff; }
		.email-header { background: #122B51; padding: 24px 32px; text-align: center; }
		.email-header img { height: 48px; width: auto; }
		.email-body { padding: 32px; color: #1A1A2E; font-size: 15px; line-height: 1.6; font-family: 'Montserrat', Arial, Helvetica, sans-serif; font-weight: 400; }
		.email-footer { background: #F4F8FF; padding: 20px 32px; text-align: center; font-size: 12px; color: #6B7280; font-family: 'Montserrat', Arial, Helvetica, sans-serif; }
		.btn-email { display: inline-block; background: #3266A9; color: #ffffff !important; padding: 12px 28px; border-radius: 6px; text-decoration: none; font-weight: 400; margin: 16px 0; font-family: 'Montserrat', Arial, Helvetica, sans-serif; }
		.highlight-box { background: #F4F8FF; border-left: 4px solid #3266A9; padding: 16px 20px; margin: 16px 0; border-radius: 0 6px 6px 0; }
		.warning-box { background: #FEF2F2; border-left: 4px solid #DC2626; padding: 16px 20px; margin: 16px 0; border-radius: 0 6px 6px 0; color: #991B1B; }
		h1, h2, h3 { color: #122B51; margin-top: 0; font-family: 'Playfair Display', Georgia, 'Times New Roman', serif; font-weight: 700; }
		.divider { border: none; border-top: 1px solid #E5E7EB; margin: 24px 0; }
		ul { padding-left: 20px; }
		.small-text { font-size: 13px; color: #6B7280; }
	</style>
</head>
<body>
<div class="email-wrapper">
	<div class="email-header">
		<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'Clinical Training and Supervision Academy', 'cta-lms' ); ?>">
	</div>
	<div class="email-body">
		<?php if ( ! empty( $custom_body ) ) : ?>
			<?php echo wp_kses_post( $custom_body ); ?>
		<?php else : ?>
			<?php include CTA_PLUGIN_DIR . 'templates/emails/' . $template . '.php'; ?>
		<?php endif; ?>
	</div>
	<div class="email-footer">
		<p><?php esc_html_e( 'Clinical Training and Supervision Academy', 'cta-lms' ); ?></p>
		<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="color:#3266A9;">clinicaltrainingacademy.com</a></p>
		<p>
			<?php
			printf(
				/* translators: %s: support email */
				esc_html__( 'Questions? Email us at %s', 'cta-lms' ),
				esc_html( CTA_Emails::get_support_email() )
			);
			?>
		</p>
	</div>
</div>
</body>
</html>

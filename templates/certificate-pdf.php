<?php
/**
 * Dompdf-oriented certificate markup.
 *
 * Visual intent matches templates/certificate.php print stylesheet
 * (logo, navy/gold frame, typography, spacing, signature). Uses Dompdf-safe
 * nested borders instead of CSS outline/flex — not a redesign.
 *
 * @package CTA_LMS
 *
 * @var string $student_name
 * @var string $course_title
 * @var string $ce_hours
 * @var string $completion_date
 * @var string $license_number
 * @var string $provider_name
 * @var string $provider_number
 * @var string $provider_line
 * @var string $provider_address
 * @var string $cepa_stamp_url
 * @var string $certificate_number
 * @var string $logo_url
 * @var string $header_text
 * @var string $footer_text
 * @var string $signature_name
 * @var string $signature_url
 * @var string $organization_name
 * @var string $administrator_title
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$license_display     = $license_number ? esc_html( $license_number ) : esc_html__( 'N/A', 'cta-lms' );
$header_text         = ! empty( $header_text ) ? $header_text : __( 'Certificate of Completion', 'cta-lms' );
$footer_text         = ! empty( $footer_text ) ? $footer_text : 'clinicaltrainingacademy.com';
$signature_name      = ! empty( $signature_name ) ? $signature_name : __( 'Program Administrator', 'cta-lms' );
$organization_name   = ! empty( $organization_name ) ? $organization_name : __( 'Clinical Training and Supervision Academy', 'cta-lms' );
$administrator_title = ! empty( $administrator_title ) ? $administrator_title : __( 'Program Administrator', 'cta-lms' );
$provider_name       = ! empty( $provider_name ) ? $provider_name : __( 'Clinical Training & Supervision Academy', 'cta-lms' );
$provider_line       = ! empty( $provider_line ) ? $provider_line : __( 'CAMFT-Approved Continuing Education Provider #122418', 'cta-lms' );
$provider_address    = ! empty( $provider_address ) ? $provider_address : '';
$cepa_stamp_url      = ! empty( $cepa_stamp_url ) ? $cepa_stamp_url : '';
if ( empty( $signature_url ) && class_exists( 'CTA_Certificates' ) ) {
	$signature_url = CTA_Certificates::get_signature_data_uri();
}
$signature_url = ! empty( $signature_url ) ? $signature_url : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title><?php echo esc_html( $certificate_number ); ?></title>
	<style>
		@page {
			margin: 0.4in;
		}
		* { box-sizing: border-box; }
		html, body {
			margin: 0;
			padding: 0;
			background: #ffffff;
		}
		body {
			/* DejaVu Serif is bundled with Dompdf (Georgia look-alike for PDF embed). */
			font-family: "DejaVu Serif", Georgia, "Times New Roman", serif;
			color: #122B51;
			background: #ffffff;
		}
		/* Outer navy double frame + inner gold rule (mirrors HTML outline). */
		.certificate-outer {
			width: 700pt;
			margin: 0 auto;
			border: 5px double #122B51;
			padding: 10px;
			background: #ffffff;
		}
		.certificate-inner {
			border: 1px solid #c5a572;
			padding: 22px 36px 16px;
			text-align: center;
			background: #ffffff;
		}
		.logo {
			display: block;
			width: 220px;
			height: 52px;
			margin: 0 auto 10px;
		}
		h1 {
			font-size: 26px;
			margin: 0 0 4px;
			letter-spacing: 0.06em;
			text-transform: uppercase;
			line-height: 1.15;
			font-weight: bold;
			color: #122B51;
		}
		.subtitle {
			font-size: 14px;
			margin: 0 0 14px;
			letter-spacing: 0.12em;
			text-transform: uppercase;
			color: #475467;
		}
		.lead {
			font-size: 16px;
			margin: 6px 0;
			color: #122B51;
		}
		.recipient {
			font-size: 28px;
			font-weight: bold;
			margin: 8px 0;
			line-height: 1.2;
			color: #122B51;
		}
		.course-title {
			font-size: 18px;
			font-weight: bold;
			margin: 8px 0 4px;
			line-height: 1.3;
			color: #122B51;
		}
		.ce-hours {
			font-size: 18px;
			margin: 4px 0 10px;
			color: #122B51;
		}
		.meta {
			font-size: 14px;
			line-height: 1.55;
			margin: 8px auto;
			max-width: 720px;
			color: #122B51;
		}
		.meta p { margin: 1px 0; }
		.divider {
			width: 160px;
			height: 1px;
			background: #c5a572;
			margin: 12px auto 10px;
			border: 0;
			font-size: 1px;
			line-height: 1px;
		}
		.provider-line {
			font-size: 12px;
			line-height: 1.45;
			margin: 0;
			max-width: 620px;
			color: #475467;
		}
		.provider-approval {
			width: 520px;
			margin: 0 auto 10px;
			border-collapse: collapse;
		}
		.provider-stamp-cell {
			width: 76px;
			padding: 0 12px 0 0;
			vertical-align: middle;
		}
		.provider-copy-cell {
			padding: 0;
			text-align: left;
			vertical-align: middle;
		}
		.provider-stamp {
			display: block;
			width: 68px;
			height: 68px;
			margin: 0;
		}
		.provider-name {
			margin: 0 0 3px;
			font-size: 13px;
			font-weight: bold;
			color: #122B51;
		}
		.provider-address {
			margin: 3px 0 0;
			font-size: 10px;
			line-height: 1.35;
			color: #667085;
		}
		.signature-block {
			margin: 2px auto 0;
			width: 320px;
			text-align: center;
		}
		.signature-mark {
			height: 56px;
			margin: 0 auto;
			text-align: center;
		}
		.signature-image {
			display: block;
			max-width: 230px;
			max-height: 54px;
			width: 230px;
			height: auto;
			margin: 0 auto;
		}
		.signature-rule {
			width: 200px;
			height: 0;
			margin: 2px auto 8px;
			border: 0;
			border-top: 1px solid #122B51;
			border-bottom: 1px solid #c5a572;
			padding: 0;
			font-size: 1px;
			line-height: 1px;
		}
		.signature-name {
			margin: 0 0 2px;
			font-size: 13px;
			font-weight: bold;
			letter-spacing: 0.02em;
			color: #122B51;
			line-height: 1.3;
		}
		.signature-title {
			margin: 0 0 1px;
			font-size: 11px;
			font-style: italic;
			color: #475467;
			line-height: 1.3;
		}
		.signature-org {
			margin: 0;
			font-size: 10px;
			letter-spacing: 0.04em;
			text-transform: uppercase;
			color: #667085;
			line-height: 1.35;
		}
		.verify {
			margin-top: 14px;
			font-size: 12px;
			font-weight: bold;
			letter-spacing: 0.03em;
			color: #122B51;
		}
		.footer {
			margin-top: 5px;
			font-size: 10px;
			color: #667085;
		}
	</style>
</head>
<body>
	<div class="certificate-outer">
		<div class="certificate-inner">
			<?php if ( ! empty( $logo_url ) ) : ?>
				<?php
				$logo_src = ( 0 === strpos( $logo_url, 'data:' ) )
					? esc_attr( $logo_url )
					: esc_url( $logo_url );
				?>
				<img class="logo" src="<?php echo $logo_src; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?>" width="220" height="52" alt="<?php echo esc_attr( $organization_name ); ?>">
			<?php endif; ?>

			<h1><?php echo esc_html( $header_text ); ?></h1>
			<p class="subtitle"><?php esc_html_e( 'Continuing Education', 'cta-lms' ); ?></p>

			<p class="lead"><?php esc_html_e( 'This certifies that', 'cta-lms' ); ?></p>
			<p class="recipient"><?php echo esc_html( $student_name ); ?></p>
			<p class="lead"><?php esc_html_e( 'has successfully completed', 'cta-lms' ); ?></p>
			<p class="course-title"><?php echo esc_html( $course_title ); ?></p>
			<p class="ce-hours"><?php echo esc_html( $ce_hours ); ?> <?php esc_html_e( 'CE Hours', 'cta-lms' ); ?></p>

			<div class="meta">
				<p><?php esc_html_e( 'Issued:', 'cta-lms' ); ?> <?php echo esc_html( $completion_date ); ?></p>
				<p><?php esc_html_e( 'License/Registration Number:', 'cta-lms' ); ?> <?php echo $license_display; ?></p>
			</div>

			<div class="divider">&nbsp;</div>

			<table class="provider-approval" role="presentation">
				<tr>
					<?php if ( ! empty( $cepa_stamp_url ) ) : ?>
						<td class="provider-stamp-cell">
							<?php
							$stamp_src = ( 0 === strpos( (string) $cepa_stamp_url, 'data:' ) )
								? esc_attr( $cepa_stamp_url )
								: esc_url( $cepa_stamp_url );
							?>
							<img
								class="provider-stamp"
								src="<?php echo $stamp_src; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?>"
								width="68"
								height="68"
								alt="<?php echo esc_attr( __( 'CAMFT Approved Continuing Education Provider', 'cta-lms' ) ); ?>"
							>
						</td>
					<?php endif; ?>
					<td class="provider-copy-cell">
						<p class="provider-name"><?php echo esc_html( $provider_name ); ?></p>
						<p class="provider-line"><?php echo esc_html( $provider_line ); ?></p>
						<?php if ( ! empty( $provider_address ) ) : ?>
							<p class="provider-address"><?php echo esc_html( $provider_address ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<div class="signature-block">
				<?php if ( ! empty( $signature_url ) ) : ?>
					<?php
					$sig_src = ( 0 === strpos( (string) $signature_url, 'data:' ) )
						? esc_attr( $signature_url )
						: esc_url( $signature_url );
					?>
					<div class="signature-mark">
						<img
							class="signature-image"
							src="<?php echo $sig_src; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?>"
							alt="<?php echo esc_attr( sprintf( /* translators: %s: signer name */ __( 'Signature of %s', 'cta-lms' ), $signature_name ) ); ?>"
							width="230"
							height="54"
						>
					</div>
				<?php endif; ?>
				<hr class="signature-rule" />
				<p class="signature-name"><?php echo esc_html( $signature_name ); ?></p>
				<p class="signature-title"><?php echo esc_html( $administrator_title ); ?></p>
				<p class="signature-org"><?php echo esc_html( $organization_name ); ?></p>
			</div>

			<p class="verify">
				<?php esc_html_e( 'Certificate Verification Number:', 'cta-lms' ); ?>
				<?php echo esc_html( $certificate_number ); ?>
			</p>
			<p class="footer"><?php echo esc_html( $footer_text ); ?></p>
		</div>
	</div>
</body>
</html>

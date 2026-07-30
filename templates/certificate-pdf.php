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
 * @var string $provider_number
 * @var string $certificate_number
 * @var string $logo_url
 * @var string $header_text
 * @var string $footer_text
 * @var string $signature_name
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title><?php echo esc_html( $certificate_number ); ?></title>
	<style>
		@page {
			size: letter landscape;
			margin: 0.35in;
		}
		* { box-sizing: border-box; margin: 0; padding: 0; }
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
			width: 100%;
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
			width: 200px;
			height: 2px;
			background: #122B51;
			margin: 12px auto;
			border: 0;
			font-size: 1px;
			line-height: 1px;
		}
		.signature-block {
			margin-top: 10px;
			text-align: center;
		}
		.signature-line {
			width: 300px;
			border-top: 1px solid #122B51;
			margin: 0 auto 4px;
			padding-top: 6px;
			font-size: 13px;
			line-height: 1.4;
			color: #122B51;
		}
		.verify {
			margin-top: 10px;
			font-size: 13px;
			font-weight: bold;
			color: #122B51;
		}
		.footer {
			margin-top: 6px;
			font-size: 11px;
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

			<p class="meta">
				<?php esc_html_e( 'CAMFT CEPA Provider Number:', 'cta-lms' ); ?>
				<?php echo esc_html( $provider_number ? $provider_number : __( 'N/A', 'cta-lms' ) ); ?>
			</p>

			<div class="signature-block">
				<div class="signature-line">
					<?php echo esc_html( $signature_name ); ?><br>
					<?php echo esc_html( $administrator_title ); ?><br>
					<?php echo esc_html( $organization_name ); ?>
				</div>
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

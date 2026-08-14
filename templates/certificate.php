<?php
/**
 * Printable CE certificate HTML (self-contained inline CSS).
 *
 * Designed for one landscape page (A4 / Letter). Use browser Print → Save as PDF.
 * Learner "Download Certificate" streams a real PDF via Dompdf (see certificate-pdf.php).
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
 * @var bool   $auto_print
 * @var string $download_url
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
$auto_print          = ! empty( $auto_print );
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $certificate_number ); ?></title>
	<style>
		@page {
			size: landscape;
			margin: 0.4in;
		}
		* { box-sizing: border-box; }
		html, body {
			margin: 0;
			padding: 0;
			height: 100%;
		}
		body {
			padding: 16px;
			font-family: Georgia, "Times New Roman", serif;
			color: #122B51;
			background: #e8eef5;
			-webkit-print-color-adjust: exact;
			print-color-adjust: exact;
		}
		.certificate-shell {
			max-width: 1050px;
			margin: 0 auto;
		}
		.certificate {
			width: 100%;
			min-height: calc(100vh - 32px);
			padding: 36px 48px 28px;
			background: #ffffff;
			border: 6px double #122B51;
			outline: 1px solid #c5a572;
			outline-offset: -12px;
			text-align: center;
			position: relative;
			display: flex;
			flex-direction: column;
			justify-content: center;
		}
		.logo {
			display: block;
			max-width: 260px;
			max-height: 64px;
			width: auto;
			height: auto;
			margin: 0 auto 12px;
			object-fit: contain;
		}
		h1 {
			font-size: 30px;
			margin: 0 0 4px;
			letter-spacing: 0.06em;
			text-transform: uppercase;
			line-height: 1.15;
		}
		.subtitle {
			font-size: 14px;
			margin: 0 0 18px;
			letter-spacing: 0.12em;
			text-transform: uppercase;
			color: #475467;
		}
		.lead { font-size: 16px; margin: 8px 0; }
		.recipient {
			font-size: 32px;
			font-weight: bold;
			margin: 10px 0;
			line-height: 1.2;
			word-wrap: break-word;
		}
		.course-title {
			font-size: 20px;
			font-weight: bold;
			margin: 10px 0 6px;
			line-height: 1.3;
			word-wrap: break-word;
		}
		.ce-hours {
			font-size: 18px;
			margin: 6px 0 14px;
		}
		.meta {
			font-size: 14px;
			line-height: 1.65;
			margin: 10px auto;
			max-width: 720px;
		}
		.meta p { margin: 2px 0; }
		.divider {
			width: 160px;
			height: 1px;
			background: #c5a572;
			margin: 14px auto 12px;
			position: relative;
		}
		.divider::before {
			content: "";
			display: block;
			width: 8px;
			height: 8px;
			border: 1px solid #c5a572;
			border-radius: 50%;
			background: #fff;
			position: absolute;
			left: 50%;
			top: 50%;
			margin: -4px 0 0 -4px;
		}
		.provider-line {
			font-size: 13px;
			line-height: 1.5;
			margin: 0;
			max-width: 640px;
			color: #475467;
			letter-spacing: 0.01em;
		}
		.provider-approval {
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 16px;
			max-width: 620px;
			margin: 0 auto 14px;
			text-align: left;
		}
		.provider-stamp {
			display: block;
			flex: 0 0 auto;
			width: 82px;
			height: 82px;
			object-fit: contain;
		}
		.provider-copy {
			min-width: 0;
		}
		.provider-name {
			margin: 0 0 3px;
			font-size: 14px;
			font-weight: bold;
			color: #122B51;
		}
		.provider-address {
			margin: 4px 0 0;
			font-size: 11px;
			line-height: 1.4;
			color: #667085;
		}
		.signature-block {
			margin: 4px auto 0;
			max-width: 340px;
			text-align: center;
		}
		.signature-mark {
			min-height: 58px;
			margin: 0 auto 0;
			display: flex;
			align-items: flex-end;
			justify-content: center;
		}
		.signature-image {
			display: block;
			max-width: 240px;
			max-height: 58px;
			width: auto;
			height: auto;
			margin: 0 auto;
			object-fit: contain;
			object-position: center bottom;
		}
		.signature-rule {
			width: 220px;
			height: 0;
			margin: 2px auto 10px;
			border: 0;
			border-top: 1px solid #122B51;
			border-bottom: 1px solid #c5a572;
			padding: 0;
		}
		.signature-name {
			margin: 0 0 2px;
			font-size: 14px;
			font-weight: bold;
			letter-spacing: 0.02em;
			color: #122B51;
			line-height: 1.35;
		}
		.signature-title {
			margin: 0 0 1px;
			font-size: 12px;
			font-style: italic;
			color: #475467;
			line-height: 1.35;
		}
		.signature-org {
			margin: 0;
			font-size: 11px;
			letter-spacing: 0.04em;
			text-transform: uppercase;
			color: #667085;
			line-height: 1.4;
		}
		.verify {
			margin-top: 18px;
			font-size: 12px;
			font-weight: bold;
			letter-spacing: 0.03em;
			color: #122B51;
		}
		.footer {
			margin-top: 6px;
			font-size: 11px;
			color: #667085;
		}
		.print-actions {
			max-width: 1050px;
			margin: 0 auto 12px;
			text-align: center;
		}
		.print-actions__buttons {
			display: flex;
			flex-wrap: wrap;
			gap: 10px;
			justify-content: center;
		}
		.print-actions button,
		.print-actions a {
			font: inherit;
			padding: 10px 18px;
			cursor: pointer;
			background: #122B51;
			color: #fff;
			border: 0;
			border-radius: 4px;
			text-decoration: none;
			display: inline-block;
		}
		.print-actions a.print-actions__download {
			background: #fff;
			color: #122B51;
			border: 1px solid #122B51;
		}
		.print-actions p {
			margin: 8px 0 0;
			font-size: 13px;
			color: #475467;
			font-family: system-ui, sans-serif;
		}
		@media print {
			body {
				padding: 0;
				background: #ffffff;
			}
			.print-actions { display: none !important; }
			.certificate-shell { max-width: none; }
			.certificate {
				min-height: auto;
				height: auto;
				padding: 28px 36px 20px;
				border-width: 5px;
				outline-offset: -10px;
				page-break-inside: avoid;
				break-inside: avoid;
			}
			.logo { max-height: 56px; max-width: 240px; }
			h1 { font-size: 26px; }
			.recipient { font-size: 28px; }
			.course-title { font-size: 18px; }
		}
	</style>
</head>
<body>
	<div class="print-actions">
		<div class="print-actions__buttons">
			<button type="button" onclick="window.print();"><?php esc_html_e( 'Print / Save as PDF', 'cta-lms' ); ?></button>
			<?php if ( ! empty( $download_url ) ) : ?>
				<a class="print-actions__download" href="<?php echo esc_url( $download_url ); ?>"><?php esc_html_e( 'Download Certificate', 'cta-lms' ); ?></a>
			<?php endif; ?>
		</div>
		<p><?php esc_html_e( 'Use Print / Save as PDF to open the print dialog, or Download Certificate to save a PDF to your device.', 'cta-lms' ); ?></p>
	</div>

	<div class="certificate-shell">
		<div class="certificate">
			<?php if ( ! empty( $logo_url ) ) : ?>
				<?php
				// esc_url() strips data: URIs used for print/PDF embedding — keep those via esc_attr.
				$logo_src = ( 0 === strpos( $logo_url, 'data:' ) )
					? esc_attr( $logo_url )
					: esc_url( $logo_url );
				?>
				<img class="logo" src="<?php echo $logo_src; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?>" alt="<?php echo esc_attr( $organization_name ); ?>">
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

			<div class="divider"></div>

			<div class="provider-approval">
				<?php if ( ! empty( $cepa_stamp_url ) ) : ?>
					<?php
					$stamp_src = ( 0 === strpos( (string) $cepa_stamp_url, 'data:' ) )
						? esc_attr( $cepa_stamp_url )
						: esc_url( $cepa_stamp_url );
					?>
					<img
						class="provider-stamp"
						src="<?php echo $stamp_src; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?>"
						width="82"
						height="82"
						alt="<?php echo esc_attr( __( 'CAMFT Approved Continuing Education Provider', 'cta-lms' ) ); ?>"
					>
				<?php endif; ?>
				<div class="provider-copy">
					<p class="provider-name"><?php echo esc_html( $provider_name ); ?></p>
					<p class="provider-line"><?php echo esc_html( $provider_line ); ?></p>
					<?php if ( ! empty( $provider_address ) ) : ?>
						<p class="provider-address"><?php echo esc_html( $provider_address ); ?></p>
					<?php endif; ?>
				</div>
			</div>

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
							width="240"
							height="58"
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

	<?php if ( $auto_print ) : ?>
		<script>
			window.addEventListener('load', function () {
				setTimeout(function () { window.print(); }, 350);
			});
		</script>
	<?php endif; ?>
</body>
</html>

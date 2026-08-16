<?php
/**
 * Printable CE certificate HTML (self-contained inline CSS).
 *
 * Designed for one landscape Letter page. Download streams Dompdf PDF via
 * certificate-pdf.php. Content is limited to the approved single-page field set.
 *
 * @package CTA_LMS
 *
 * @var string $student_name
 * @var string $course_title
 * @var string $ce_hours
 * @var string $completion_date
 * @var string $license_number
 * @var string $license_type
 * @var string $delivery_format
 * @var string $provider_name
 * @var string $provider_number
 * @var string $provider_line
 * @var string $provider_address
 * @var array  $provider_address_lines
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

$license_display = $license_number ? esc_html( $license_number ) : esc_html__( 'N/A', 'cta-lms' );
$license_type    = isset( $license_type ) ? trim( (string) $license_type ) : '';
$delivery_format = ! empty( $delivery_format )
	? (string) $delivery_format
	: __( 'Asynchronous Distance Learning', 'cta-lms' );
$header_text         = ! empty( $header_text ) ? $header_text : __( 'Certificate of Completion', 'cta-lms' );
$footer_text         = ! empty( $footer_text ) ? $footer_text : 'clinicaltrainingacademy.com';
$signature_name      = ! empty( $signature_name ) ? $signature_name : __( 'Program Administrator', 'cta-lms' );
$organization_name   = ! empty( $organization_name ) ? $organization_name : __( 'Clinical Training and Supervision Academy', 'cta-lms' );
$administrator_title = ! empty( $administrator_title ) ? $administrator_title : __( 'Program Administrator', 'cta-lms' );
$provider_name       = ! empty( $provider_name ) ? $provider_name : __( 'Clinical Training and Supervision Academy', 'cta-lms' );
$provider_line       = ! empty( $provider_line ) ? $provider_line : __( 'CAMFT-Approved Continuing Education Provider #122418', 'cta-lms' );
$provider_address       = ! empty( $provider_address ) ? $provider_address : '';
$provider_address_lines = isset( $provider_address_lines ) && is_array( $provider_address_lines )
	? array_values( array_filter( array_map( 'strval', $provider_address_lines ) ) )
	: array();
if ( empty( $provider_address_lines ) && class_exists( 'CTA_Certificates' ) ) {
	$provider_address_lines = CTA_Certificates::get_provider_address_lines();
} elseif ( empty( $provider_address_lines ) && '' !== $provider_address ) {
	$provider_address_lines = preg_split( '/\r\n|\r|\n/', $provider_address );
	$provider_address_lines = array_values( array_filter( array_map( 'trim', (array) $provider_address_lines ) ) );
}
$provider_address_display = ! empty( $provider_address_lines )
	? implode( ', ', $provider_address_lines )
	: (string) $provider_address;
$cepa_stamp_url = ! empty( $cepa_stamp_url ) ? $cepa_stamp_url : '';
if ( empty( $signature_url ) && class_exists( 'CTA_Certificates' ) ) {
	$signature_url = CTA_Certificates::get_signature_data_uri();
}
$signature_url = ! empty( $signature_url ) ? $signature_url : '';
$auto_print    = ! empty( $auto_print );

$license_line = '' !== $license_type
	? $license_type . ' — ' . ( $license_number ? $license_number : __( 'N/A', 'cta-lms' ) )
	: ( $license_number ? $license_number : __( 'N/A', 'cta-lms' ) );
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
			margin: 0.35in;
		}
		* { box-sizing: border-box; }
		html, body {
			margin: 0;
			padding: 0;
			height: 100%;
		}
		body {
			padding: 12px;
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
			padding: 22px 36px 16px;
			background: #ffffff;
			border: 5px double #122B51;
			outline: 1px solid #c5a572;
			outline-offset: -10px;
			text-align: center;
			position: relative;
		}
		.logo {
			display: block;
			max-width: 220px;
			max-height: 52px;
			width: auto;
			height: auto;
			margin: 0 auto 8px;
			object-fit: contain;
		}
		h1 {
			font-size: 26px;
			margin: 0 0 2px;
			letter-spacing: 0.06em;
			text-transform: uppercase;
			line-height: 1.1;
		}
		.subtitle {
			font-size: 12px;
			margin: 0 0 10px;
			letter-spacing: 0.12em;
			text-transform: uppercase;
			color: #475467;
		}
		.lead { font-size: 14px; margin: 4px 0; }
		.recipient {
			font-size: 28px;
			font-weight: bold;
			margin: 4px 0;
			line-height: 1.15;
			word-wrap: break-word;
		}
		.license-line {
			font-size: 13px;
			margin: 2px 0 8px;
			line-height: 1.3;
			word-wrap: break-word;
		}
		.course-title {
			font-size: 17px;
			font-weight: bold;
			margin: 4px 0 8px;
			line-height: 1.25;
			word-wrap: break-word;
		}
		.meta {
			font-size: 13px;
			line-height: 1.45;
			margin: 4px auto 8px;
			max-width: 720px;
		}
		.meta p { margin: 2px 0; }
		.ce-hours {
			font-size: 15px;
			font-weight: bold;
			margin: 2px 0;
		}
		.divider {
			width: 140px;
			height: 1px;
			background: #c5a572;
			margin: 10px auto 10px;
			position: relative;
		}
		.divider::before {
			content: "";
			display: block;
			width: 7px;
			height: 7px;
			border: 1px solid #c5a572;
			border-radius: 50%;
			background: #fff;
			position: absolute;
			left: 50%;
			top: 50%;
			margin: -4px 0 0 -4px;
		}
		.provider-line {
			font-size: 12px;
			line-height: 1.4;
			margin: 0;
			color: #475467;
		}
		.provider-approval {
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 14px;
			max-width: 600px;
			margin: 0 auto 8px;
			text-align: left;
		}
		.provider-stamp {
			display: block;
			flex: 0 0 auto;
			width: 82px;
			height: 82px;
			object-fit: contain;
		}
		.provider-copy { min-width: 0; }
		.provider-name {
			margin: 0 0 2px;
			font-size: 13px;
			font-weight: bold;
			color: #122B51;
		}
		.provider-address {
			margin: 3px 0 0;
			font-size: 11px;
			line-height: 1.35;
			color: #667085;
		}
		.signature-block {
			margin: 2px auto 0;
			max-width: 320px;
			text-align: center;
		}
		.signature-mark {
			min-height: 44px;
			margin: 0 auto;
			display: flex;
			align-items: flex-end;
			justify-content: center;
		}
		.signature-image {
			display: block;
			max-width: 210px;
			max-height: 44px;
			width: auto;
			height: auto;
			margin: 0 auto;
			object-fit: contain;
			object-position: center bottom;
		}
		.signature-rule {
			width: 200px;
			height: 0;
			margin: 2px auto 6px;
			border: 0;
			border-top: 1px solid #122B51;
			border-bottom: 1px solid #c5a572;
			padding: 0;
		}
		.signature-name {
			margin: 0 0 1px;
			font-size: 13px;
			font-weight: bold;
			letter-spacing: 0.02em;
			color: #122B51;
			line-height: 1.3;
		}
		.signature-title {
			margin: 0;
			font-size: 11px;
			font-style: italic;
			color: #475467;
			line-height: 1.3;
		}
		.verify {
			margin-top: 10px;
			font-size: 12px;
			font-weight: bold;
			letter-spacing: 0.03em;
			color: #122B51;
		}
		.footer {
			margin-top: 4px;
			font-size: 11px;
			color: #667085;
		}
		.print-actions {
			max-width: 1050px;
			margin: 0 auto 10px;
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
				padding: 18px 28px 12px;
				border-width: 4px;
				outline-offset: -8px;
				page-break-inside: avoid;
				break-inside: avoid;
			}
			.logo { max-height: 48px; max-width: 200px; }
			h1 { font-size: 22px; }
			.recipient { font-size: 24px; }
			.course-title { font-size: 15px; }
			.provider-stamp { width: 68px; height: 68px; }
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
			<p class="license-line"><?php echo esc_html( $license_line ); ?></p>

			<p class="lead"><?php esc_html_e( 'has successfully completed', 'cta-lms' ); ?></p>
			<p class="course-title"><?php echo esc_html( $course_title ); ?></p>

			<div class="meta">
				<p><?php esc_html_e( 'Course Delivery Format:', 'cta-lms' ); ?> <?php echo esc_html( $delivery_format ); ?></p>
				<p><?php esc_html_e( 'Course Completion Date:', 'cta-lms' ); ?> <?php echo esc_html( $completion_date ); ?></p>
				<p class="ce-hours"><?php echo esc_html( $ce_hours ); ?> <?php esc_html_e( 'CE Hours', 'cta-lms' ); ?></p>
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
					<?php if ( '' !== $provider_address_display ) : ?>
						<p class="provider-address"><?php echo esc_html( $provider_address_display ); ?></p>
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
							width="210"
							height="44"
						>
					</div>
				<?php endif; ?>
				<hr class="signature-rule" />
				<p class="signature-name"><?php echo esc_html( $signature_name ); ?></p>
				<p class="signature-title"><?php echo esc_html( $administrator_title ); ?></p>
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

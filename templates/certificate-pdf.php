<?php
/**
 * Dompdf-oriented CE certificate markup (single landscape page).
 *
 * Visual intent matches templates/certificate.php: logo, navy/gold frame,
 * typography, CAMFT stamp, signature. Content is limited to the approved
 * single-page field set (no course code, provisional language, expanded
 * instructional-method copy, or completion-statement paragraph).
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

$license_line = '' !== $license_type
	? $license_type . ' — ' . ( $license_number ? $license_number : __( 'N/A', 'cta-lms' ) )
	: ( $license_number ? $license_number : __( 'N/A', 'cta-lms' ) );
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title><?php echo esc_html( $certificate_number ); ?></title>
	<style>
		@page { margin: 0.35in; }
		* { box-sizing: border-box; }
		html, body {
			margin: 0;
			padding: 0;
			background: #ffffff;
		}
		body {
			font-family: "DejaVu Serif", Georgia, "Times New Roman", serif;
			color: #122B51;
			background: #ffffff;
		}
		.certificate-outer {
			width: 100%;
			border: 4px double #122B51;
			padding: 8px;
			background: #ffffff;
		}
		.certificate-inner {
			border: 1px solid #c5a572;
			padding: 14px 28px 10px;
			text-align: center;
			background: #ffffff;
		}
		.logo {
			display: block;
			width: 190px;
			height: 45px;
			margin: 0 auto 6px;
		}
		h1 {
			font-size: 22px;
			margin: 0 0 2px;
			letter-spacing: 0.06em;
			text-transform: uppercase;
			line-height: 1.1;
			font-weight: bold;
			color: #122B51;
		}
		.subtitle {
			font-size: 11px;
			margin: 0 0 8px;
			letter-spacing: 0.12em;
			text-transform: uppercase;
			color: #475467;
		}
		.lead {
			font-size: 13px;
			margin: 3px 0;
			color: #122B51;
		}
		.recipient {
			font-size: 24px;
			font-weight: bold;
			margin: 4px 0;
			line-height: 1.15;
			color: #122B51;
		}
		.license-line {
			font-size: 12px;
			margin: 2px 0 6px;
			color: #122B51;
			line-height: 1.3;
		}
		.course-title {
			font-size: 15px;
			font-weight: bold;
			margin: 4px 0 6px;
			line-height: 1.25;
			color: #122B51;
		}
		.meta {
			font-size: 12px;
			line-height: 1.4;
			margin: 4px auto 6px;
			max-width: 680px;
			color: #122B51;
		}
		.meta p { margin: 1px 0; }
		.ce-hours {
			font-size: 14px;
			font-weight: bold;
			margin: 2px 0;
			color: #122B51;
		}
		.divider {
			width: 140px;
			height: 1px;
			background: #c5a572;
			margin: 8px auto 8px;
			border: 0;
			font-size: 1px;
			line-height: 1px;
		}
		.provider-line {
			font-size: 11px;
			line-height: 1.35;
			margin: 0;
			color: #475467;
		}
		.provider-approval {
			width: 500px;
			margin: 0 auto 6px;
			border-collapse: collapse;
		}
		.provider-stamp-cell {
			width: 76px;
			padding: 0 10px 0 0;
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
			margin: 0 0 2px;
			font-size: 12px;
			font-weight: bold;
			color: #122B51;
		}
		.provider-address {
			margin: 2px 0 0;
			font-size: 10px;
			line-height: 1.3;
			color: #667085;
		}
		.signature-block {
			margin: 2px auto 0;
			width: 300px;
			text-align: center;
		}
		.signature-mark {
			height: 42px;
			margin: 0 auto;
			text-align: center;
		}
		.signature-image {
			display: block;
			max-width: 200px;
			max-height: 40px;
			width: 200px;
			height: auto;
			margin: 0 auto;
		}
		.signature-rule {
			width: 180px;
			height: 0;
			margin: 1px auto 4px;
			border: 0;
			border-top: 1px solid #122B51;
			border-bottom: 1px solid #c5a572;
			padding: 0;
			font-size: 1px;
			line-height: 1px;
		}
		.signature-name {
			margin: 0 0 1px;
			font-size: 12px;
			font-weight: bold;
			letter-spacing: 0.02em;
			color: #122B51;
			line-height: 1.25;
		}
		.signature-title {
			margin: 0;
			font-size: 10px;
			font-style: italic;
			color: #475467;
			line-height: 1.25;
		}
		.verify {
			margin-top: 8px;
			font-size: 11px;
			font-weight: bold;
			letter-spacing: 0.03em;
			color: #122B51;
		}
		.footer {
			margin-top: 3px;
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
				<img class="logo" src="<?php echo $logo_src; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?>" width="190" height="45" alt="<?php echo esc_attr( $organization_name ); ?>">
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
						<?php if ( '' !== $provider_address_display ) : ?>
							<p class="provider-address"><?php echo esc_html( $provider_address_display ); ?></p>
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
							width="200"
							height="40"
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
</body>
</html>

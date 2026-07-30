<?php
/**
 * Offline smoke test: render a sample certificate PDF via Dompdf.
 *
 * Usage: php scripts/test-certificate-pdf.php
 */

$root = dirname( __DIR__ );
require_once $root . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}

// Minimal WP escape stubs for the PDF template.
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $text ) {
		return filter_var( (string) $text, FILTER_SANITIZE_URL );
	}
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = null ) {
		unset( $domain );
		return esc_html( $text );
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) {
		unset( $domain );
		return (string) $text;
	}
}
if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = null ) {
		echo esc_html__( $text, $domain );
	}
}

$certificate_number  = 'CTA-2026-151748';
$student_name        = 'Test Learner';
$course_title        = 'California Law & Ethics for Mental Health Professionals';
$ce_hours            = '6';
$completion_date     = 'July 30, 2026 at 9:00 AM PDT';
$license_number      = 'LMFT123456';
$provider_number     = '57023';
$header_text         = 'Certificate of Completion';
$footer_text         = 'clinicaltrainingacademy.com';
$signature_name      = 'Candice Fuimaono, MS, LMFT';
$organization_name   = 'Clinical Training and Supervision Academy';
$administrator_title = 'Program Administrator';
$logo_url            = '';

$logo_candidates = array(
	$root . '/assets/img/logo.svg',
	$root . '/assets/images/cta-logo.png',
	$root . '/assets/images/logo.png',
	$root . '/assets/img/logo.png',
	$root . '/assets/cta-logo.png',
);

foreach ( $logo_candidates as $candidate ) {
	if ( is_readable( $candidate ) ) {
		$mime = 'image/png';
		if ( preg_match( '/\.jpe?g$/i', $candidate ) ) {
			$mime = 'image/jpeg';
		} elseif ( preg_match( '/\.svg$/i', $candidate ) ) {
			$mime = 'image/svg+xml';
		} elseif ( preg_match( '/\.webp$/i', $candidate ) ) {
			$mime = 'image/webp';
		}
		$logo_url = 'data:' . $mime . ';base64,' . base64_encode( (string) file_get_contents( $candidate ) );
		break;
	}
}

ob_start();
include $root . '/templates/certificate-pdf.php';
$html = (string) ob_get_clean();

$options = new Dompdf\Options();
$options->set( 'isRemoteEnabled', false );
$options->set( 'isHtml5ParserEnabled', true );
$options->set( 'isFontSubsettingEnabled', true );
$options->set( 'defaultFont', 'DejaVu Serif' );

$dompdf = new Dompdf\Dompdf( $options );
$dompdf->loadHtml( $html, 'UTF-8' );
$dompdf->setPaper( 'letter', 'landscape' );
$dompdf->render();
$pdf = $dompdf->output();

$out_dir = $root . '/tmp';
if ( ! is_dir( $out_dir ) ) {
	mkdir( $out_dir, 0777, true );
}

$out_file = $out_dir . '/' . $certificate_number . '.pdf';
file_put_contents( $out_file, $pdf );

$ok_magic = 0 === strpos( $pdf, '%PDF' );
$page_count = $dompdf->getCanvas()->get_page_number();

echo "Wrote: {$out_file}\n";
echo 'Bytes: ' . strlen( $pdf ) . "\n";
echo 'Magic %PDF: ' . ( $ok_magic ? 'yes' : 'no' ) . "\n";
echo 'Pages: ' . $page_count . "\n";
echo 'Filename: ' . basename( $out_file ) . "\n";

exit( $ok_magic && $page_count >= 1 ? 0 : 1 );

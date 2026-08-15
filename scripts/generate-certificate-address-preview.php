<?php
/**
 * Write a CE certificate HTML preview with the approved provider mailing address.
 *
 * Usage: php scripts/generate-certificate-address-preview.php
 */

$root = dirname( __DIR__ );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}
if ( ! defined( 'CTA_PLUGIN_DIR' ) ) {
	define( 'CTA_PLUGIN_DIR', $root . '/' );
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) {
		unset( $domain );
		return (string) $text;
	}
}
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
		return (string) $text;
	}
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = null ) {
		unset( $domain );
		return esc_html( $text );
	}
}
if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = null ) {
		echo esc_html__( $text, $domain );
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		unset( $option );
		return $default;
	}
}

require_once $root . '/public/class-cta-certificates.php';

$student_name           = 'Sample Learner, LMFT';
$course_title           = 'California Law & Ethics for Mental Health Professionals';
$ce_hours               = '6';
$completion_date        = 'July 30, 2026 at 9:00 AM PDT';
$license_number         = 'LMFT123456';
$provider_name          = CTA_Certificates::get_provider_name();
$provider_number        = CTA_Certificates::get_provider_number();
$provider_line          = CTA_Certificates::get_provider_line();
$provider_address       = CTA_Certificates::get_provider_address();
$provider_address_lines = CTA_Certificates::get_provider_address_lines();
$certificate_number     = 'CTA-2026-TEST-ADDR';
$header_text            = 'Certificate of Completion';
$footer_text            = 'clinicaltrainingacademy.com';
$signature_name         = 'Candice Fuimaono, MS, LMFT';
$organization_name      = $provider_name;
$administrator_title    = 'Program Administrator';
$auto_print             = false;
$download_url           = '';
$logo_url               = '';
$signature_url    = CTA_Certificates::get_signature_data_uri();
$cepa_stamp_url   = CTA_Certificates::get_cepa_stamp_data_uri();

ob_start();
include $root . '/templates/certificate.php';
$html = (string) ob_get_clean();

$out_dir = $root . '/tmp';
if ( ! is_dir( $out_dir ) ) {
	mkdir( $out_dir, 0777, true );
}

$out_file = $out_dir . '/CE-Certificate-Preview-With-Address.html';
file_put_contents( $out_file, $html );

echo "Wrote: {$out_file}\n";
echo "Provider block:\n";
echo "  {$provider_name}\n";
echo "  {$provider_line}\n";
foreach ( $provider_address_lines as $line ) {
	echo "  {$line}\n";
}

$checks = array(
	$student_name,
	$course_title,
	'6 CE Hours',
	$completion_date,
	$license_number,
	$provider_name,
	$provider_line,
	'6296 Magnolia Ave #1077',
	'Riverside, CA 92506',
	$signature_name,
	$certificate_number,
);
$missing = array();
foreach ( $checks as $check ) {
	if ( false === strpos( $html, esc_html( $check ) ) ) {
		$missing[] = $check;
	}
}

echo 'All required fields present: ' . ( empty( $missing ) ? 'yes' : 'no' ) . "\n";
if ( ! empty( $missing ) ) {
	echo 'Missing: ' . implode( ' | ', $missing ) . "\n";
	exit( 1 );
}

exit( 0 );

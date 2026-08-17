<?php
/**
 * Debug Dompdf certificate render: dump frame text + write PDF/HTML.
 */
$root = dirname( __DIR__ );
require_once $root . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}

foreach ( array( 'esc_html', 'esc_attr', 'esc_url', 'esc_html__', '__' ) as $fn ) {
	if ( ! function_exists( $fn ) ) {
		eval( 'function ' . $fn . '( $text, $domain = null ) { unset( $domain ); return is_string( $text ) ? htmlspecialchars( (string) $text, ENT_QUOTES, "UTF-8" ) : ""; }' );
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

$svg = $root . '/assets/img/logo.svg';
if ( is_readable( $svg ) ) {
	$logo_url = 'data:image/svg+xml;base64,' . base64_encode( (string) file_get_contents( $svg ) );
}

ob_start();
include $root . '/templates/certificate-pdf.php';
$html = (string) ob_get_clean();

file_put_contents( $root . '/tmp/debug-certificate.html', $html );
echo 'HTML bytes: ' . strlen( $html ) . PHP_EOL;
echo 'HTML has recipient: ' . ( false !== strpos( $html, 'Test Learner' ) ? 'yes' : 'no' ) . PHP_EOL;

$options = new Dompdf\Options();
$options->set( 'isRemoteEnabled', false );
$options->set( 'isHtml5ParserEnabled', true );
$options->set( 'isFontSubsettingEnabled', true );
$options->set( 'defaultFont', 'DejaVu Serif' );
$options->set( 'debugPng', false );
$options->set( 'debugKeepTemp', false );

$dompdf = new Dompdf\Dompdf( $options );
$dompdf->loadHtml( $html, 'UTF-8' );
$dompdf->setPaper( 'letter', 'landscape' );
$dompdf->render();

$pdf = $dompdf->output();
file_put_contents( $root . '/tmp/CTA-2026-151748.pdf', $pdf );

$canvas = $dompdf->getCanvas();
echo 'Pages: ' . $canvas->get_page_count() . PHP_EOL;
echo 'PDF bytes: ' . strlen( $pdf ) . PHP_EOL;
echo 'Magic: ' . substr( $pdf, 0, 8 ) . PHP_EOL;

// Walk text frames.
$tree = $dompdf->getTree();
$text_bits = array();
$iterator = $tree->get_frames();
foreach ( $iterator as $frame ) {
	$node = $frame->get_node();
	if ( $node && XML_TEXT_NODE === $node->nodeType ) {
		$t = trim( $node->nodeValue );
		if ( '' !== $t ) {
			$text_bits[] = $t;
		}
	}
}
echo 'Text frames: ' . count( $text_bits ) . PHP_EOL;
echo 'Sample text: ' . implode( ' | ', array_slice( $text_bits, 0, 12 ) ) . PHP_EOL;

// Check for fonts in PDF
echo 'HasFont: ' . ( false !== strpos( $pdf, '/Font' ) ? 'yes' : 'no' ) . PHP_EOL;
echo 'HasContent: ' . ( false !== strpos( $pdf, '/Contents' ) ? 'yes' : 'no' ) . PHP_EOL;
echo 'HasXObject: ' . ( false !== strpos( $pdf, '/XObject' ) ? 'yes' : 'no' ) . PHP_EOL;

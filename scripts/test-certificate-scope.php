<?php
/**
 * Offline smoke test for CE vs Exam Prep certificate scope.
 *
 * Usage: php scripts/test-certificate-scope.php
 */

$root = dirname( __DIR__ );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) {
		unset( $domain );
		return (string) $text;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		unset( $option );
		return $default;
	}
}

class CTA_Exam_Access {
	public static function is_exam_prep( $course ) {
		return ! empty( $course->is_exam_prep );
	}

	public static function has_ce_certificate( $course ) {
		return ! empty( $course->has_ce_certificate );
	}
}

require_once $root . '/public/class-cta-certificates.php';

$ce_course = (object) array(
	'is_exam_prep'      => false,
	'has_ce_certificate' => true,
);

$exam_prep_course = (object) array(
	'is_exam_prep'      => true,
	'has_ce_certificate' => false,
);

$ce_allowed        = CTA_Certificates::is_ce_certificate_course( $ce_course );
$exam_prep_blocked = ! CTA_Certificates::is_ce_certificate_course( $exam_prep_course );
$provider_number   = '#122418' === CTA_Certificates::get_provider_number();
$provider_name     = 'Clinical Training & Supervision Academy' === CTA_Certificates::get_provider_name();
$provider_address  = array(
	'6296 Magnolia Ave #1077',
	'Riverside, CA 92506',
) === CTA_Certificates::get_provider_address_lines();

echo 'CE certificate allowed: ' . ( $ce_allowed ? 'yes' : 'no' ) . PHP_EOL;
echo 'Exam Prep certificate blocked: ' . ( $exam_prep_blocked ? 'yes' : 'no' ) . PHP_EOL;
echo 'Provider number exact: ' . ( $provider_number ? 'yes' : 'no' ) . PHP_EOL;
echo 'Provider address lines exact: ' . ( $provider_address ? 'yes' : 'no' ) . PHP_EOL;

exit( $ce_allowed && $exam_prep_blocked && $provider_number && $provider_name && $provider_address ? 0 : 1 );

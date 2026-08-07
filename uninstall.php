<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop all plugin tables
$tables = array(
	$wpdb->prefix . 'cta_courses',
	$wpdb->prefix . 'cta_course_modules',
	$wpdb->prefix . 'cta_enrollments',
	$wpdb->prefix . 'cta_bookings',
	$wpdb->prefix . 'cta_documents',
	$wpdb->prefix . 'cta_payments',
	$wpdb->prefix . 'cta_bundles',
	$wpdb->prefix . 'cta_certificates',
	$wpdb->prefix . 'cta_quizzes',
	$wpdb->prefix . 'cta_quiz_questions',
	$wpdb->prefix . 'cta_quiz_attempts',
	$wpdb->prefix . 'cta_evaluations',
	$wpdb->prefix . 'cta_evaluation_questions',
	$wpdb->prefix . 'cta_course_attestations',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS $table" );
}

// Remove custom roles
$roles_file = plugin_dir_path( __FILE__ ) . 'includes/class-cta-roles.php';
if ( file_exists( $roles_file ) ) {
	require_once $roles_file;
	if ( class_exists( 'CTA_Roles' ) && method_exists( 'CTA_Roles', 'remove_roles' ) ) {
		CTA_Roles::remove_roles();
	}
}

// Delete plugin options
delete_option( 'cta_lms_version' );
delete_option( 'cta_login_page_id' );
delete_option( 'cta_courses_page_id' );
delete_option( 'cta_supervision_page_id' );
delete_option( 'cta_memberships_page_id' );
delete_option( 'cta_faq_page_id' );
delete_option( 'cta_policies_page_id' );
delete_option( 'cta_student_dashboard_page_id' );
delete_option( 'cta_course_player_page_id' );
delete_option( 'cta_supervision_dashboard_page_id' );
delete_option( 'cta_single_course_page_id' );
delete_option( 'cta_quiz_page_id' );
delete_option( 'cta_camft_provider_number' );
delete_option( 'cta_certificate_upload_dir' );
delete_option( 'cta_stripe_secret_key' );
delete_option( 'cta_stripe_publishable_key' );
delete_option( 'cta_stripe_webhook_secret' );
delete_option( 'cta_stripe_mode' );
delete_option( 'cta_stripe_portal_configuration_id' );
delete_option( 'cta_payments_bypass' );
delete_option( 'cta_supervision_monthly_price' );
delete_option( 'cta_supervision_all_access_price' );
delete_option( 'cta_supervision_product_name' );
delete_option( 'cta_supervision_product_description' );
delete_option( 'cta_timezone' );
delete_option( 'cta_cepa_provider_number' );
delete_option( 'cta_admin_name' );
delete_option( 'cta_support_email' );
delete_option( 'cta_certificate_header_text' );
delete_option( 'cta_certificate_footer_text' );
delete_option( 'cta_certificate_signature_name' );
delete_option( 'cta_certificate_signature_image_url' );

$cta_email_types = array(
	'welcome',
	'enrollment_confirmation',
	'booking_confirmation',
	'session_reminder',
	'certificate_ready',
	'payment_receipt',
	'payment_failed',
	'supervision_locked',
);

foreach ( $cta_email_types as $cta_email_type ) {
	delete_option( 'cta_email_' . $cta_email_type . '_enabled' );
	delete_option( 'cta_email_' . $cta_email_type . '_subject' );
	delete_option( 'cta_email_' . $cta_email_type . '_body' );
}

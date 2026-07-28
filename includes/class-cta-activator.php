<?php
/**
 * Fired during plugin activation.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Activator
 */
if ( ! class_exists( 'CTA_Activator' ) ) {

class CTA_Activator {

	/**
	 * Run activation tasks.
	 */
	public static function activate() {
		try {
			self::run_activation();
		} catch ( Throwable $e ) {
			update_option(
				'cta_lms_activation_error',
				wp_json_encode(
					array(
						'message' => $e->getMessage(),
						'file'    => $e->getFile(),
						'line'    => $e->getLine(),
						'time'    => time(),
					)
				)
			);

			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'CTA LMS activation error: ' . $e->getMessage() );
			}

			// Still stamp version so upgrades can finish schema on next load.
			if ( defined( 'CTA_VERSION' ) ) {
				update_option( 'cta_lms_version', CTA_VERSION );
			}
		}
	}

	/**
	 * Activation work (may throw on strict mysqli hosts).
	 */
	private static function run_activation() {
		if ( ! class_exists( 'CTA_Roles' ) || ! class_exists( 'CTA_Database' ) ) {
			wp_die(
				esc_html__( 'CTA LMS could not load required files. Delete the plugin folder and reinstall the plugin zip.', 'cta-lms' ),
				esc_html__( 'Plugin Activation Error', 'cta-lms' ),
				array( 'back_link' => true )
			);
		}

		CTA_Roles::create_roles();

		try {
			CTA_Database::create_tables();
			self::maybe_seed_bundles();
		} catch ( Throwable $e ) {
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'CTA LMS create_tables error: ' . $e->getMessage() );
			}
		}

		if ( class_exists( 'CTA_Emails' ) ) {
			CTA_Emails::register_cron();

			foreach ( array_keys( CTA_Emails::get_configurable_types() ) as $email_type ) {
				add_option( CTA_Emails::get_email_option_key( $email_type, 'enabled' ), 'yes' );
				add_option( CTA_Emails::get_email_option_key( $email_type, 'subject' ), '' );
				add_option( CTA_Emails::get_email_option_key( $email_type, 'body' ), '' );
			}
		}

		if ( function_exists( 'cta_lms_ensure_utf8_environment' ) ) {
			cta_lms_ensure_utf8_environment();
		}

		add_option( 'cta_lms_version', CTA_VERSION );
		add_option( 'cta_login_page_id', 0 );
		add_option( 'cta_courses_page_id', 0 );
		add_option( 'cta_supervision_page_id', 0 );
		add_option( 'cta_memberships_page_id', 0 );
		add_option( 'cta_faq_page_id', 0 );
		add_option( 'cta_policies_page_id', 0 );
		add_option( 'cta_student_dashboard_page_id', 0 );
		add_option( 'cta_course_player_page_id', 0 );
		add_option( 'cta_supervision_dashboard_page_id', 0 );
		add_option( 'cta_single_course_page_id', 0 );
		add_option( 'cta_quiz_page_id', 0 );
		add_option( 'cta_camft_provider_number', '' );
		add_option( 'cta_certificate_upload_dir', 'cta-certificates' );
		add_option( 'cta_stripe_secret_key', '' );
		add_option( 'cta_stripe_publishable_key', '' );
		add_option( 'cta_stripe_webhook_secret', '' );
		add_option( 'cta_stripe_mode', 'test' );
		add_option( 'cta_payments_bypass', 'yes' );
		add_option( 'cta_supervision_monthly_price', 260.0 );
		add_option( 'cta_supervision_all_access_price', 350.0 );
		add_option( 'cta_supervision_product_name', 'Group Supervision' );
		add_option( 'cta_supervision_product_description', 'Monthly group supervision subscription' );
		add_option( 'cta_timezone', 'America/Los_Angeles' );
		add_option( 'cta_cepa_provider_number', 'CAMFT CEPA #003369' );
		add_option( 'cta_admin_name', 'Candice Fuimaono, MS, LMFT' );
		add_option( 'cta_support_email', 'support@clinicaltrainingacademy.com' );
		add_option( 'cta_certificate_header_text', 'Certificate of Completion' );
		add_option( 'cta_certificate_footer_text', 'clinicaltrainingacademy.com' );
		add_option( 'cta_certificate_signature_name', 'Candice Fuimaono, MS, LMFT' );

		update_option( 'cta_lms_version', CTA_VERSION );
		delete_option( 'cta_lms_activation_error' );

		flush_rewrite_rules();
	}

	/**
	 * Seed default bundles only after the bundles table exists.
	 */
	private static function maybe_seed_bundles() {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_bundles';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return;
		}

		if ( class_exists( 'CTA_Database' ) ) {
			CTA_Database::seed_bundles();
		}
	}
}
}

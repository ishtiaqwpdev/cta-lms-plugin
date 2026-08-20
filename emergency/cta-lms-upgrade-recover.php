<?php
/**
 * Emergency recovery: upload to wp-content/mu-plugins/ if CTA LMS plugin update 504s the site.
 *
 * Filename on server: wp-content/mu-plugins/cta-lms-upgrade-recover.php
 *
 * Clears the upgrade lock, stamps the plugin version, and queues batched sync
 * so the next normal page load can complete without running heavy migrations inline.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'plugins_loaded',
	static function () {
		delete_transient( 'cta_lms_upgrading' );
		delete_transient( 'cta_lms_deferred_processing' );
		delete_transient( 'cta_lcsw_aswb_wb_bank_heal_lock' );

		if ( defined( 'CTA_VERSION' ) ) {
			update_option( 'cta_lms_version', CTA_VERSION );
		}

		$queue = (array) get_option( 'cta_lms_deferred_upgrade_queue', array() );
		if ( ! in_array( 'lcsw_workbook_banks', $queue, true ) ) {
			$queue[] = 'lcsw_workbook_banks';
			update_option( 'cta_lms_deferred_upgrade_queue', $queue, false );
		}

		if ( function_exists( 'wp_schedule_single_event' ) && ! wp_next_scheduled( 'cta_lms_process_deferred_upgrades' ) ) {
			wp_schedule_single_event( time() + 30, 'cta_lms_process_deferred_upgrades' );
		}
	},
	1
);

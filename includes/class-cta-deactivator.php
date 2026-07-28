<?php
/**
 * Fired during plugin deactivation.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Deactivator
 */
if ( ! class_exists( 'CTA_Deactivator' ) ) {

class CTA_Deactivator {

	/**
	 * Run deactivation tasks.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'cta_send_session_reminders' );
		flush_rewrite_rules();
	}
}
}
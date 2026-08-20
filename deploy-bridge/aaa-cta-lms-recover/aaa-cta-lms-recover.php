<?php
/**
 * Plugin Name: AAA CTA LMS Live Recover
 * Description: Stops CTA LMS upgrade 504 loops and runs batched workbook sync. Upload via Plugins → Add New → Upload (no cPanel needed). Safe to delete after live is healthy.
 * Version: 1.0.1
 * Author: CTA LMS
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package CTA_LMS_Recover
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stamp DB version before CTA LMS upgrade hook (plugins_loaded priority 5).
 */
add_action(
	'plugins_loaded',
	static function () {
		delete_transient( 'cta_lms_upgrading' );
		delete_transient( 'cta_lms_deferred_processing' );
		delete_transient( 'cta_lcsw_aswb_wb_bank_heal_lock' );

		$target = defined( 'CTA_VERSION' ) ? (string) CTA_VERSION : '1.0.283';
		update_option( 'cta_lms_version', $target );

		$queue = (array) get_option( 'cta_lms_deferred_upgrade_queue', array() );
		if ( ! in_array( 'lcsw_workbook_banks', $queue, true ) ) {
			$queue[] = 'lcsw_workbook_banks';
			update_option( 'cta_lms_deferred_upgrade_queue', $queue, false );
		}

		if ( ! wp_next_scheduled( 'cta_lms_process_deferred_upgrades' ) ) {
			wp_schedule_single_event( time() + 30, 'cta_lms_process_deferred_upgrades' );
		}
	},
	1
);

add_action(
	'admin_menu',
	static function () {
		add_management_page(
			'CTA LMS Recover',
			'CTA LMS Recover',
			'manage_options',
			'aaa-cta-lms-recover',
			'aaa_cta_lms_recover_render_page'
		);
	}
);

/**
 * @return void
 */
function aaa_cta_lms_recover_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['aaa_cta_recover_action'] ) && check_admin_referer( 'aaa_cta_recover' ) ) {
		$action = sanitize_key( wp_unslash( (string) $_POST['aaa_cta_recover_action'] ) );
		if ( 'unlock' === $action ) {
			aaa_cta_lms_recover_unlock();
			echo '<div class="notice notice-success"><p>Upgrade locks cleared and version stamped.</p></div>';
		} elseif ( 'sync_batch' === $action ) {
			$result = aaa_cta_lms_recover_run_batch();
			$class  = ! empty( $result['ok'] ) ? 'success' : 'warning';
			echo '<div class="notice notice-' . esc_attr( $class ) . '"><p>' . esc_html( (string) ( $result['message'] ?? '' ) ) . '</p></div>';
		} elseif ( 'queue' === $action ) {
			aaa_cta_lms_recover_queue_workbooks();
			echo '<div class="notice notice-success"><p>Workbook bank sync queued for background processing.</p></div>';
		}
	}

	$installed = (string) get_option( 'cta_lms_version', '0' );
	$file_ver  = defined( 'CTA_VERSION' ) ? (string) CTA_VERSION : 'unknown';
	$queue     = (array) get_option( 'cta_lms_deferred_upgrade_queue', array() );
	$remaining = class_exists( 'CTA_Lcsw_Aswb_Sync' ) ? aaa_cta_lms_recover_missing_banks() : -1;

	?>
	<div class="wrap">
		<h1>CTA LMS Live Recover</h1>
		<p>Use this tool when WP Pusher or plugin update shows <strong>504 Gateway Timeout</strong>. No cPanel/FTP required.</p>
		<table class="widefat striped" style="max-width:640px">
			<tbody>
				<tr><th>DB version (cta_lms_version)</th><td><code><?php echo esc_html( $installed ); ?></code></td></tr>
				<tr><th>Plugin file version (CTA_VERSION)</th><td><code><?php echo esc_html( $file_ver ); ?></code></td></tr>
				<tr><th>Deferred queue</th><td><code><?php echo esc_html( implode( ', ', $queue ) ?: '(empty)' ); ?></code></td></tr>
				<tr><th>Missing LCSW workbook banks</th><td><code><?php echo esc_html( -1 === $remaining ? 'CTA LMS not loaded' : (string) $remaining ); ?></code></td></tr>
			</tbody>
		</table>
		<form method="post" style="margin-top:16px">
			<?php wp_nonce_field( 'aaa_cta_recover' ); ?>
			<p>
				<button type="submit" class="button button-primary" name="aaa_cta_recover_action" value="unlock">1. Clear 504 upgrade lock</button>
				<button type="submit" class="button" name="aaa_cta_recover_action" value="queue">2. Queue workbook sync</button>
				<button type="submit" class="button" name="aaa_cta_recover_action" value="sync_batch">3. Run one sync batch now</button>
			</p>
		</form>
		<p><strong>After recover:</strong> Tools → WP Pusher → pull <code>main</code> again, or ask host to deploy v1.0.283 from GitHub. Deactivate/delete this helper plugin when done.</p>
	</div>
	<?php
}

/**
 * @return void
 */
function aaa_cta_lms_recover_unlock() {
	delete_transient( 'cta_lms_upgrading' );
	delete_transient( 'cta_lms_deferred_processing' );
	delete_transient( 'cta_lcsw_aswb_wb_bank_heal_lock' );
	$target = defined( 'CTA_VERSION' ) ? (string) CTA_VERSION : '1.0.283';
	update_option( 'cta_lms_version', $target );
}

/**
 * @return void
 */
function aaa_cta_lms_recover_queue_workbooks() {
	$queue = (array) get_option( 'cta_lms_deferred_upgrade_queue', array() );
	if ( ! in_array( 'lcsw_workbook_banks', $queue, true ) ) {
		$queue[] = 'lcsw_workbook_banks';
		update_option( 'cta_lms_deferred_upgrade_queue', $queue, false );
	}
	if ( ! wp_next_scheduled( 'cta_lms_process_deferred_upgrades' ) ) {
		wp_schedule_single_event( time() + 15, 'cta_lms_process_deferred_upgrades' );
	}
}

/**
 * @return array{ok:bool,message:string}
 */
function aaa_cta_lms_recover_run_batch() {
	if ( class_exists( 'CTA_Lms_Deferred_Upgrades' ) ) {
		CTA_Lms_Deferred_Upgrades::process_one();
		$remaining = aaa_cta_lms_recover_missing_banks();
		return array(
			'ok'      => 0 === $remaining,
			'message' => 0 === $remaining
				? 'All 12 workbook practice banks are live.'
				: sprintf( 'Batch processed. About %d workbook bank(s) still missing — click again or wait for cron.', $remaining ),
		);
	}

	if ( class_exists( 'CTA_Lcsw_Aswb_Sync' ) && method_exists( 'CTA_Lcsw_Aswb_Sync', 'sync_workbook_banks_missing' ) ) {
		$result = CTA_Lcsw_Aswb_Sync::sync_workbook_banks_missing( 0, 2 );
		return array(
			'ok'      => ! empty( $result['ok'] ),
			'message' => ! empty( $result['ok'] )
				? 'All 12 workbook practice banks are live.'
				: sprintf(
					'Synced %d bank(s); %d remaining.',
					(int) ( $result['synced'] ?? 0 ),
					(int) ( $result['remaining'] ?? 0 )
				),
		);
	}

	return array(
		'ok'      => false,
		'message' => 'CTA LMS classes not loaded — activate the main CTA Academy LMS plugin first.',
	);
}

/**
 * @return int
 */
function aaa_cta_lms_recover_missing_banks() {
	if ( ! class_exists( 'CTA_Lcsw_Aswb_Sync' ) || ! method_exists( 'CTA_Lcsw_Aswb_Sync', 'workbook_banks_are_live' ) ) {
		return -1;
	}
	if ( CTA_Lcsw_Aswb_Sync::workbook_banks_are_live() ) {
		return 0;
	}
	$missing = 0;
	for ( $n = 1; $n <= 12; $n++ ) {
		$health = CTA_Lcsw_Aswb_Sync::get_live_workbook_bank_health( $n );
		if ( empty( $health['ok'] ) ) {
			++$missing;
		}
	}
	return $missing;
}

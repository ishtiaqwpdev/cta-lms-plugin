<?php
/**
 * Queue heavy LMS upgrade/sync work so plugin updates do not 504 the live site.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CTA_Lms_Deferred_Upgrades' ) ) {

class CTA_Lms_Deferred_Upgrades {

	const QUEUE_OPTION = 'cta_lms_deferred_upgrade_queue';
	const CRON_HOOK    = 'cta_lms_process_deferred_upgrades';
	const LOCK_KEY     = 'cta_lms_deferred_processing';

	/**
	 * Register cron + lazy processor hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'process_one' ) );
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_process_on_load' ), 25 );
	}

	/**
	 * @param string $task Task key.
	 * @return void
	 */
	public static function queue( $task ) {
		$task = sanitize_key( (string) $task );
		if ( '' === $task ) {
			return;
		}

		$queue = (array) get_option( self::QUEUE_OPTION, array() );
		if ( ! in_array( $task, $queue, true ) ) {
			$queue[] = $task;
			update_option( self::QUEUE_OPTION, $queue, false );
		}

		self::schedule();
	}

	/**
	 * @return void
	 */
	public static function schedule() {
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}

		wp_schedule_single_event( time() + 15, self::CRON_HOOK );
	}

	/**
	 * Process one queued task (cron).
	 *
	 * @return void
	 */
	public static function process_one() {
		if ( get_transient( self::LOCK_KEY ) || get_transient( 'cta_lms_upgrading' ) ) {
			self::schedule();
			return;
		}

		$queue = (array) get_option( self::QUEUE_OPTION, array() );
		if ( empty( $queue ) ) {
			return;
		}

		set_transient( self::LOCK_KEY, 1, MINUTE_IN_SECONDS );

		$task  = (string) array_shift( $queue );
		update_option( self::QUEUE_OPTION, $queue, false );

		self::run_task( $task );

		delete_transient( self::LOCK_KEY );

		if ( ! empty( $queue ) ) {
			self::schedule();
		}
	}

	/**
	 * Fallback processor when WP-Cron is disabled (admin loads only).
	 *
	 * @return void
	 */
	public static function maybe_process_on_load() {
		if ( get_transient( 'cta_lms_upgrading' ) ) {
			return;
		}

		$queue = (array) get_option( self::QUEUE_OPTION, array() );
		if ( empty( $queue ) ) {
			return;
		}

		if ( ! is_admin() && ! wp_doing_cron() ) {
			return;
		}

		self::process_one();
	}

	/**
	 * @param string $task Task key.
	 * @return void
	 */
	private static function run_task( $task ) {
		switch ( $task ) {
			case 'lcsw_workbook_banks':
				if ( class_exists( 'CTA_Lcsw_Aswb_Sync' ) ) {
					$result = CTA_Lcsw_Aswb_Sync::sync_workbook_banks_missing( 0, 2 );
					if ( empty( $result['ok'] ) && ! empty( $result['remaining'] ) ) {
						self::queue( 'lcsw_workbook_banks' );
					}
				}
				break;

			case 'lcsw_forms_ab':
				if ( class_exists( 'CTA_Lcsw_Aswb_Sync' ) ) {
					CTA_Lcsw_Aswb_Sync::sync_forms_only( 0 );
				}
				break;

			case 'lmft_clinical_form_a':
				if ( class_exists( 'CTA_Lmft_Clinical_Legacy_Forms_Archive' ) ) {
					CTA_Lmft_Clinical_Legacy_Forms_Archive::archive_non_final_active_forms(
						CTA_Lmft_Clinical_Legacy_Forms_Archive::TARGET_COURSE_ID,
						true
					);
					CTA_Lmft_Clinical_Legacy_Forms_Archive::purge_archived_duplicate_form_quizzes(
						CTA_Lmft_Clinical_Legacy_Forms_Archive::TARGET_COURSE_ID,
						true
					);
				}
				if ( class_exists( 'CTA_Lmft_Clinical_Form_A_Sync' ) ) {
					CTA_Lmft_Clinical_Form_A_Sync::sync( true );
				}
				if ( class_exists( 'CTA_Lmft_Clinical_Form_A_Answer_Sync' ) ) {
					CTA_Lmft_Clinical_Form_A_Answer_Sync::sync_answer_keys( true );
				}
				break;

			case 'lmft_amftrb_workbook_banks':
				if ( class_exists( 'CTA_Lmft_Amftrb_Sync' ) ) {
					CTA_Lmft_Amftrb_Sync::ensure_learner_forms( 0, true );
				}
				break;

			case 'lpcc_ncmhce_forms_ab':
				if ( class_exists( 'CTA_Lpcc_Ncmhce_Form_A_Sync' ) ) {
					CTA_Lpcc_Ncmhce_Form_A_Sync::sync( true );
				}
				if ( class_exists( 'CTA_Lpcc_Ncmhce_Form_B_Sync' ) ) {
					CTA_Lpcc_Ncmhce_Form_B_Sync::sync( true );
				}
				break;

			default:
				break;
		}
	}
}

}

<?php
/**
 * Plugin Name: CTA Academy LMS
 * Plugin URI: https://clinicaltrainingacademy.com
 * Description: Complete LMS platform for Clinical Training and Supervision Academy.
 * Version: 1.0.113
 * Author: David James
 * Author URI: https://clinicaltrainingacademy.com
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cta-lms
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'CTA Academy LMS requires PHP 7.4 or higher.', 'cta-lms' );
			echo '</p></div>';
		}
	);
	return;
}

// Second copy already booted — never redefine functions/constants.
if ( defined( 'CTA_LMS_LOADED' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__( 'Another CTA LMS copy is already loaded. Delete the duplicate plugin folder.', 'cta-lms' );
			echo '</p></div>';
		}
	);
	return;
}

define( 'CTA_LMS_LOADED', true );
define( 'CTA_PLUGIN_FILE', __FILE__ );
define( 'CTA_VERSION', '1.0.109' );
define( 'CTA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CTA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CTA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Persist last fatal so it can be read via WP File Manager without hPanel.
 *
 * @param string $message Error message.
 * @param string $file    File path.
 * @param int    $line    Line number.
 */
if ( ! function_exists( 'cta_lms_store_fatal' ) ) {
	function cta_lms_store_fatal( $message, $file = '', $line = 0 ) {
		$payload = array(
			'message' => (string) $message,
			'file'    => (string) $file,
			'line'    => (int) $line,
			'time'    => gmdate( 'c' ),
		);

		$log = CTA_PLUGIN_DIR . 'cta-fatal-log.txt';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		@file_put_contents( $log, wp_json_encode( $payload ) . "\n" );

		if ( function_exists( 'update_option' ) ) {
			update_option( 'cta_lms_activation_error', wp_json_encode( $payload ), false );
		}
	}
}

/**
 * Clear sticky activation/bootstrap fatals after a successful load.
 */
if ( ! function_exists( 'cta_lms_clear_fatal' ) ) {
	function cta_lms_clear_fatal() {
		if ( function_exists( 'delete_option' ) ) {
			delete_option( 'cta_lms_activation_error' );
		}

		$log = CTA_PLUGIN_DIR . 'cta-fatal-log.txt';
		if ( is_string( $log ) && $log && file_exists( $log ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $log );
		}
	}
}

register_shutdown_function(
	static function () {
		$error = error_get_last();
		if ( ! is_array( $error ) ) {
			return;
		}

		$fatal_types = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR );
		if ( ! in_array( (int) $error['type'], $fatal_types, true ) ) {
			return;
		}

		$plugin_dir = defined( 'CTA_PLUGIN_DIR' ) ? CTA_PLUGIN_DIR : '';
		$file       = isset( $error['file'] ) ? (string) $error['file'] : '';

		// Only record fatals that originated inside this plugin.
		if ( $plugin_dir && $file && false === strpos( $file, $plugin_dir ) ) {
			return;
		}

		if ( function_exists( 'cta_lms_store_fatal' ) ) {
			cta_lms_store_fatal(
				isset( $error['message'] ) ? $error['message'] : 'Unknown fatal',
				$file,
				isset( $error['line'] ) ? (int) $error['line'] : 0
			);
		}
	}
);

/**
 * Load a plugin PHP file relative to plugin root.
 *
 * @param string $relative Relative path.
 * @return bool
 */
if ( ! function_exists( 'cta_lms_require_file' ) ) {
	function cta_lms_require_file( $relative ) {
		$path = CTA_PLUGIN_DIR . ltrim( (string) $relative, '/\\' );
		if ( ! file_exists( $path ) ) {
			cta_lms_store_fatal( 'Missing required file: ' . $relative, $path, 0 );
			return false;
		}

		try {
			require_once $path;
		} catch ( Throwable $e ) {
			cta_lms_store_fatal( $e->getMessage(), $e->getFile(), $e->getLine() );
			return false;
		}

		return true;
	}
}

/**
 * Files required to activate (create roles/tables) without loading the whole LMS.
 *
 * @return bool
 */
if ( ! function_exists( 'cta_lms_load_activation_deps' ) ) {
	function cta_lms_load_activation_deps() {
		$files = array(
			'includes/cta-timezone.php',
			'includes/cta-encoding.php',
			'includes/class-cta-roles.php',
			'includes/class-cta-supervision-plans.php',
			'includes/class-cta-evaluation-questions.php',
			'includes/class-cta-course-attestation.php',
			'includes/class-cta-database.php',
			'includes/class-cta-emails.php',
			'includes/class-cta-activator.php',
			'includes/class-cta-deactivator.php',
		);

		foreach ( $files as $file ) {
			if ( ! cta_lms_require_file( $file ) ) {
				return false;
			}
		}

		return true;
	}
}

/**
 * Deactivate known legacy CTA LMS installs without blocking boot.
 */
if ( ! function_exists( 'cta_academy_deactivate_legacy_plugins' ) ) {
	function cta_academy_deactivate_legacy_plugins() {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$self = plugin_basename( CTA_PLUGIN_FILE );

		$legacy = array(
			'cta-lms/Cta-plugin.php',
			'cta-lms/cta-plugin.php',
			'cta-lms/cta-lms.php',
			'cta-design/Cta-plugin.php',
			'cta-design/cta-plugin.php',
			'cta-design/cta-lms.php',
			'cta-lms-plugin/Cta-plugin.php',
			'cta-lms-plugin/cta-plugin.php',
			'cta-lms-plugin/cta-lms.php',
			'cta-academy-lms/Cta-plugin.php',
		);

		$to_deactivate = array();

		foreach ( $legacy as $path ) {
			if ( $path === $self ) {
				continue;
			}
			if ( function_exists( 'is_plugin_active' ) && is_plugin_active( $path ) ) {
				$to_deactivate[] = $path;
			}
		}

		// Also deactivate any other installed CTA LMS copy (by header text domain / name).
		if ( function_exists( 'get_plugins' ) ) {
			foreach ( get_plugins() as $plugin_file => $plugin_data ) {
				if ( $plugin_file === $self ) {
					continue;
				}
				$domain = $plugin_data['TextDomain'] ?? '';
				$name   = $plugin_data['Name'] ?? '';
				if ( 'cta-lms' === $domain || false !== stripos( $name, 'CTA LMS' ) || false !== stripos( $name, 'CTA Academy' ) ) {
					if ( function_exists( 'is_plugin_active' ) && is_plugin_active( $plugin_file ) ) {
						$to_deactivate[] = $plugin_file;
					}
				}
			}
		}

		$to_deactivate = array_unique( $to_deactivate );
		if ( empty( $to_deactivate ) ) {
			return;
		}

		try {
			deactivate_plugins( $to_deactivate, true );
		} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Never block site boot if deactivation fails.
		}
	}
}

if ( function_exists( 'add_action' ) ) {
	add_action( 'plugins_loaded', 'cta_academy_deactivate_legacy_plugins', 0 );
	add_action( 'admin_init', 'cta_academy_deactivate_legacy_plugins', 1 );
}

// Activation only needs a small subset of classes (avoids fatals from optional/heavy files).
$cta_activation_ready = cta_lms_load_activation_deps();

if ( $cta_activation_ready && class_exists( 'CTA_Activator' ) ) {
	register_activation_hook( __FILE__, array( 'CTA_Activator', 'activate' ) );
}

if ( $cta_activation_ready && class_exists( 'CTA_Deactivator' ) ) {
	register_deactivation_hook( __FILE__, array( 'CTA_Deactivator', 'deactivate' ) );
}

/**
 * Full plugin bootstrap after WordPress is ready.
 */
if ( ! function_exists( 'cta_lms_load_full_bootstrap' ) ) {
	function cta_lms_load_full_bootstrap() {
		$bootstrap = CTA_PLUGIN_DIR . 'cta-lms.php';
		if ( ! file_exists( $bootstrap ) ) {
			cta_lms_store_fatal( 'Bootstrap file cta-lms.php is missing.', $bootstrap, 0 );
			return;
		}

		try {
			require_once $bootstrap;
			// Deploy windows can briefly store a missing-file fatal; clear once boot succeeds.
			if ( function_exists( 'cta_lms_clear_fatal' ) ) {
				cta_lms_clear_fatal();
			}
		} catch ( Throwable $e ) {
			cta_lms_store_fatal( $e->getMessage(), $e->getFile(), $e->getLine() );
		}
	}
}

add_action( 'plugins_loaded', 'cta_lms_load_full_bootstrap', 1 );

// Show stored fatal on admin screens only while the problem is still real.
add_action(
	'admin_notices',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$bootstrap = CTA_PLUGIN_DIR . 'cta-lms.php';
		$raw       = get_option( 'cta_lms_activation_error', '' );
		if ( ! $raw ) {
			$log = CTA_PLUGIN_DIR . 'cta-fatal-log.txt';
			if ( file_exists( $log ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$raw = trim( (string) file_get_contents( $log ) );
			}
		}

		if ( ! $raw ) {
			return;
		}

		$data = json_decode( (string) $raw, true );
		$msg  = is_array( $data ) && ! empty( $data['message'] ) ? (string) $data['message'] : (string) $raw;
		$file = is_array( $data ) && ! empty( $data['file'] ) ? (string) $data['file'] : '';
		$line = is_array( $data ) && ! empty( $data['line'] ) ? (int) $data['line'] : 0;

		// Stale deploy notice: bootstrap exists and LMS already loaded — drop it.
		$is_missing_bootstrap = false !== stripos( $msg, 'Bootstrap file cta-lms.php is missing' );
		if ( $is_missing_bootstrap && file_exists( $bootstrap ) && defined( 'CTA_LMS_BOOTSTRAPPED' ) ) {
			if ( function_exists( 'cta_lms_clear_fatal' ) ) {
				cta_lms_clear_fatal();
			}
			return;
		}

		$plugin_slug = basename( untrailingslashit( CTA_PLUGIN_DIR ) );
		$log_hint    = 'wp-content/plugins/' . $plugin_slug . '/cta-fatal-log.txt';

		echo '<div class="notice notice-error"><p><strong>';
		esc_html_e( 'CTA LMS error:', 'cta-lms' );
		echo '</strong> ';
		echo esc_html( $msg );
		if ( $file ) {
			echo '<br><code>' . esc_html( $file . ( $line ? ':' . $line : '' ) ) . '</code>';
		}
		echo '</p><p>';
		echo esc_html(
			sprintf(
				/* translators: %s: relative path to fatal log */
				__( 'Also check %s via WP File Manager.', 'cta-lms' ),
				$log_hint
			)
		);
		echo '</p></div>';
	}
);

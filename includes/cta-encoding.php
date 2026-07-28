<?php
/**
 * UTF-8 encoding helpers and mojibake repair.
 *
 * Typographic punctuation in plugin PHP/JS is stored as UTF-8. When a theme or
 * host serves pages as Windows-1252/ISO-8859-1 (or blog_charset is wrong), those
 * bytes render as classic mojibake. This module forces UTF-8 for WordPress/CTA
 * surfaces and repairs already-corrupted text.
 *
 * Classic failure mode: UTF-8 em dash bytes displayed as Windows-1252 (shows as
 * a-circumflex, euro, quote).
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'cta_lms_ensure_utf8_environment' ) ) {
	/**
	 * Force WordPress / DB to use UTF-8 (utf8mb4 when available).
	 */
	function cta_lms_ensure_utf8_environment() {
		try {
			$charset = (string) get_option( 'blog_charset', '' );

			if ( '' === $charset || ! preg_match( '/^utf-?8$/i', $charset ) ) {
				update_option( 'blog_charset', 'UTF-8' );
			}

			global $wpdb;

			if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'set_charset' ) && isset( $wpdb->dbh ) && $wpdb->dbh ) {
				$db_charset = defined( 'DB_CHARSET' ) && DB_CHARSET ? DB_CHARSET : 'utf8mb4';
				$db_collate = defined( 'DB_COLLATE' ) && DB_COLLATE ? DB_COLLATE : '';
				// phpcs:ignore WordPress.DB.DatabaseCollationMismatch -- intentional utf8 alignment.
				$wpdb->set_charset( $wpdb->dbh, $db_charset, $db_collate );
			}
		} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- never block boot.
			// Hosts with strict mysqli can throw; encoding is best-effort only.
		}
	}
}

if ( ! function_exists( 'cta_lms_fix_mojibake' ) ) {
	/**
	 * Repair common UTF-8 interpreted-as-Windows-1252 mojibake sequences.
	 *
	 * Map keys are the UTF-8 encoding of the mojibake characters (hex), so this
	 * file stays ASCII-safe regardless of editor encoding.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	function cta_lms_fix_mojibake( $text ) {
		if ( ! is_string( $text ) || '' === $text ) {
			return is_string( $text ) ? $text : '';
		}

		$map = array(
			// Left double quote mojibake -> U+201C
			"\xC3\xA2\xE2\x82\xAC\xC5\x93"         => "\xE2\x80\x9C",
			// Right double quote mojibake -> U+201D
			"\xC3\xA2\xE2\x82\xAC\xC2\x9D"         => "\xE2\x80\x9D",
			// Left single quote mojibake -> U+2018
			"\xC3\xA2\xE2\x82\xAC\xCB\x9C"         => "\xE2\x80\x98",
			// Right single quote mojibake -> U+2019
			"\xC3\xA2\xE2\x82\xAC\xE2\x84\xA2"     => "\xE2\x80\x99",
			// Em dash mojibake -> U+2014
			"\xC3\xA2\xE2\x82\xAC\xE2\x80\x9D"     => "\xE2\x80\x94",
			// En dash mojibake -> U+2013
			"\xC3\xA2\xE2\x82\xAC\xE2\x80\x9C"     => "\xE2\x80\x93",
			// Ellipsis mojibake -> U+2026
			"\xC3\xA2\xE2\x82\xAC\xC2\xA6"         => "\xE2\x80\xA6",
			// Bullet mojibake -> U+2022
			"\xC3\xA2\xE2\x82\xAC\xC2\xA2"         => "\xE2\x80\xA2",
			// Truncated ASCII-quote forms pasted from broken UIs
			"\xC3\xA2\xE2\x82\xAC\""               => "\xE2\x80\x94",
			"\xC3\xA2\xE2\x82\xAC'"                => "\xE2\x80\x99",
			// Non-breaking space misread as A-circumflex + nbsp/space
			"\xC3\x82\xC2\xA0"                     => "\xC2\xA0",
			"\xC3\x82 "                            => ' ',
		);

		return strtr( $text, $map );
	}
}

if ( ! function_exists( 'cta_lms_sanitize_utf8_text' ) ) {
	/**
	 * Normalize user-entered text: valid UTF-8 + mojibake repair.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	function cta_lms_sanitize_utf8_text( $text ) {
		$text = cta_lms_fix_mojibake( (string) $text );

		if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $text, 'UTF-8' ) ) {
			if ( function_exists( 'mb_convert_encoding' ) ) {
				$converted = @mb_convert_encoding( $text, 'UTF-8', 'UTF-8, Windows-1252, ISO-8859-1' );
				if ( is_string( $converted ) && '' !== $converted ) {
					$text = $converted;
				}
			}
			$text = cta_lms_fix_mojibake( $text );
		}

		if ( function_exists( 'iconv' ) ) {
			$clean = @iconv( 'UTF-8', 'UTF-8//IGNORE', $text );
			if ( is_string( $clean ) ) {
				$text = $clean;
			}
		}

		return $text;
	}
}

if ( ! function_exists( 'cta_lms_sanitize_utf8_html' ) ) {
	/**
	 * Sanitize HTML-ish content while repairing mojibake.
	 *
	 * @param string $html Raw HTML.
	 * @return string
	 */
	function cta_lms_sanitize_utf8_html( $html ) {
		return cta_lms_sanitize_utf8_text( (string) $html );
	}
}

if ( ! function_exists( 'cta_lms_repair_stored_utf8_content' ) ) {
	/**
	 * One-shot repair of plugin content fields that may already contain mojibake.
	 */
	function cta_lms_repair_stored_utf8_content() {
		try {
			cta_lms_repair_stored_utf8_content_inner();
		} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- never block boot.
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'CTA LMS UTF-8 repair skipped: ' . $e->getMessage() );
			}
		}
	}
}

if ( ! function_exists( 'cta_lms_repair_stored_utf8_content_inner' ) ) {
	/**
	 * Inner repair implementation (may throw on strict mysqli hosts).
	 */
	function cta_lms_repair_stored_utf8_content_inner() {
		global $wpdb;

		$option_keys = array(
			'cta_supervision_product_name',
			'cta_supervision_product_description',
			'cta_certificate_header_text',
			'cta_certificate_footer_text',
			'cta_certificate_signature_name',
			'cta_admin_name',
			'cta_cepa_provider_number',
		);

		if ( class_exists( 'CTA_Emails' ) ) {
			foreach ( array_keys( CTA_Emails::get_configurable_types() ) as $email_type ) {
				$option_keys[] = CTA_Emails::get_email_option_key( $email_type, 'subject' );
				$option_keys[] = CTA_Emails::get_email_option_key( $email_type, 'body' );
			}
		}

		foreach ( $option_keys as $key ) {
			$value = get_option( $key, null );
			if ( ! is_string( $value ) || '' === $value ) {
				continue;
			}
			$fixed = cta_lms_sanitize_utf8_text( $value );
			if ( $fixed !== $value ) {
				update_option( $key, $fixed );
			}
		}

		if ( ! isset( $wpdb ) ) {
			return;
		}

		$tables = array(
			array(
				'table'  => $wpdb->prefix . 'cta_courses',
				'fields' => array( 'title', 'description', 'learning_objectives', 'category', 'syllabus_meta' ),
			),
			array(
				'table'  => $wpdb->prefix . 'cta_bundles',
				'fields' => array( 'name', 'description' ),
			),
			array(
				'table'  => $wpdb->prefix . 'cta_modules',
				'fields' => array( 'title', 'description' ),
			),
			array(
				'table'  => $wpdb->prefix . 'cta_downloadable_resources',
				'fields' => array( 'title' ),
			),
		);

		if ( class_exists( 'CTA_Evaluation_Questions' ) ) {
			$tables[] = array(
				'table'  => $wpdb->prefix . CTA_Evaluation_Questions::TABLE,
				// Real columns: section_label, label, options_json (not prompt/help_text).
				'fields' => array( 'section_label', 'label', 'options_json' ),
			);
		}

		foreach ( $tables as $def ) {
			$table = $def['table'];
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $exists !== $table ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$existing_cols = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );
			if ( ! is_array( $existing_cols ) ) {
				continue;
			}

			$fields = array_values( array_intersect( $def['fields'], $existing_cols ) );
			if ( empty( $fields ) || ! in_array( 'id', $existing_cols, true ) ) {
				continue;
			}

			$cols = implode( ', ', array_map( static function ( $col ) {
				return '`' . str_replace( '`', '', $col ) . '`';
			}, array_merge( array( 'id' ), $fields ) ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( "SELECT {$cols} FROM `{$table}`", ARRAY_A );
			if ( ! is_array( $rows ) ) {
				continue;
			}

			foreach ( $rows as $row ) {
				$update = array();
				foreach ( $fields as $field ) {
					if ( ! isset( $row[ $field ] ) || ! is_string( $row[ $field ] ) || '' === $row[ $field ] ) {
						continue;
					}
					$fixed = cta_lms_sanitize_utf8_text( $row[ $field ] );
					if ( $fixed !== $row[ $field ] ) {
						$update[ $field ] = $fixed;
					}
				}
				if ( empty( $update ) ) {
					continue;
				}
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update( $table, $update, array( 'id' => (int) $row['id'] ) );
			}
		}

		$meta_keys = array(
			'cta_supervision_plan_name',
			'cta_approval_notes',
			'cta_extension_notes',
		);

		foreach ( $meta_keys as $meta_key ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$metas = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT umeta_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value <> ''",
					$meta_key
				),
				ARRAY_A
			);
			if ( ! is_array( $metas ) ) {
				continue;
			}
			foreach ( $metas as $meta ) {
				$value = (string) $meta['meta_value'];
				$fixed = cta_lms_sanitize_utf8_text( $value );
				if ( $fixed !== $value ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->update(
						$wpdb->usermeta,
						array( 'meta_value' => $fixed ),
						array( 'umeta_id' => (int) $meta['umeta_id'] )
					);
				}
			}
		}
	}
}

if ( ! function_exists( 'cta_lms_register_encoding_hooks' ) ) {
	/**
	 * Register runtime hooks that keep responses/scripts on UTF-8.
	 */
	function cta_lms_register_encoding_hooks() {
		add_action( 'plugins_loaded', 'cta_lms_ensure_utf8_environment', 1 );

		add_filter(
			'script_loader_tag',
			static function ( $tag, $handle ) {
				if ( 0 !== strpos( (string) $handle, 'cta-' ) ) {
					return $tag;
				}
				if ( false !== stripos( $tag, 'charset=' ) ) {
					return $tag;
				}
				return preg_replace( '/<script\b/i', '<script charset="UTF-8"', $tag, 1 );
			},
			10,
			2
		);

		add_filter(
			'wp_headers',
			static function ( $headers ) {
				$charset = get_option( 'blog_charset', 'UTF-8' );
				if ( ! preg_match( '/^utf-?8$/i', (string) $charset ) ) {
					$charset = 'UTF-8';
				}
				if ( empty( $headers['Content-Type'] ) ) {
					$html_type = get_option( 'html_type', 'text/html' );
					$headers['Content-Type'] = $html_type . '; charset=' . $charset;
				} elseif ( is_string( $headers['Content-Type'] ) && false === stripos( $headers['Content-Type'], 'charset=' ) ) {
					$headers['Content-Type'] .= '; charset=' . $charset;
				}
				return $headers;
			}
		);

		// Early charset declaration; safe duplicate of theme output when blog_charset is UTF-8.
		add_action(
			'wp_head',
			static function () {
				echo '<meta charset="UTF-8" />' . "\n";
			},
			0
		);

		add_action(
			'admin_head',
			static function () {
				echo '<meta charset="UTF-8" />' . "\n";
			},
			0
		);
	}
}

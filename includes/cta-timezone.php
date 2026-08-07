<?php
/**
 * CTA LMS timezone helpers.
 *
 * Storage model (do not change without a migration):
 * - Supervision session_date / session_time: wall-clock values in the CTA display
 *   timezone (default America/Los_Angeles). They are NOT UTC instants.
 * - MySQL datetimes written via current_time( 'mysql' ): WordPress site timezone
 *   (often UTC on hosts). Parsed with wp_timezone() then converted for display.
 * - Stripe period ends / unix timestamps: true UTC instants.
 *
 * Display always uses the CTA timezone setting (Pacific by default) and emits
 * reliable PST/PDT labels instead of PHP's fragile "T" abbreviation (which can
 * surface as GMT+0000 on some hosts when formatting falls back to UTC).
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'cta_lms_is_non_cta_server_timezone' ) ) {
	/**
	 * Whether a timezone is a known non-CTA host/local zone (e.g. Pakistan).
	 *
	 * CTA is California-based; certificates and dashboards must not use these.
	 *
	 * @param string $timezone IANA timezone id.
	 * @return bool
	 */
	function cta_lms_is_non_cta_server_timezone( $timezone ) {
		$timezone = (string) $timezone;
		$blocked  = array(
			'Asia/Karachi',
			'Asia/Calcutta',
			'Asia/Kolkata',
			'Asia/Dhaka',
			'Asia/Colombo',
		);

		return in_array( $timezone, $blocked, true );
	}
}

if ( ! function_exists( 'cta_lms_ensure_pacific_timezone' ) ) {
	/**
	 * Root-level timezone heal for CTA (California / Pacific Time).
	 *
	 * - Keeps plugin display timezone on America/Los_Angeles when empty or
	 *   set to a known server-local zone (Asia/Karachi → PKT on certificates).
	 * - Aligns WordPress Settings → General → Timezone when empty or similarly
	 *   mis-set, so current_time() / wp_timezone() stay consistent with CTA.
	 *
	 * @return string Resolved CTA timezone string.
	 */
	function cta_lms_ensure_pacific_timezone() {
		$desired = 'America/Los_Angeles';
		$cta     = (string) get_option( 'cta_timezone', '' );

		if ( '' === $cta || cta_lms_is_non_cta_server_timezone( $cta ) ) {
			update_option( 'cta_timezone', $desired, false );
			$cta = $desired;
		}

		try {
			new DateTimeZone( $cta );
		} catch ( Exception $e ) {
			update_option( 'cta_timezone', $desired, false );
			$cta = $desired;
		}

		$wp_tz = (string) get_option( 'timezone_string', '' );
		if ( '' === $wp_tz || cta_lms_is_non_cta_server_timezone( $wp_tz ) ) {
			update_option( 'timezone_string', $cta, false );
			// gmt_offset is ignored when timezone_string is set; clear stale offset.
			update_option( 'gmt_offset', 0, false );
		}

		return $cta;
	}
}

if ( ! function_exists( 'cta_lms_get_timezone_string' ) ) {
	/**
	 * Plugin display timezone string (defaults to Pacific Time).
	 *
	 * @return string
	 */
	function cta_lms_get_timezone_string() {
		$timezone = (string) get_option( 'cta_timezone', 'America/Los_Angeles' );

		if ( '' === $timezone || cta_lms_is_non_cta_server_timezone( $timezone ) ) {
			$timezone = 'America/Los_Angeles';
		}

		try {
			new DateTimeZone( $timezone );
			return $timezone;
		} catch ( Exception $e ) {
			return 'America/Los_Angeles';
		}
	}
}

if ( ! function_exists( 'cta_lms_get_timezone' ) ) {
	/**
	 * Plugin display timezone object.
	 *
	 * @return DateTimeZone
	 */
	function cta_lms_get_timezone() {
		return new DateTimeZone( cta_lms_get_timezone_string() );
	}
}

if ( ! function_exists( 'cta_lms_parse_datetime' ) ) {
	/**
	 * Parse a MySQL datetime stored via current_time( 'mysql' ) (WordPress site timezone).
	 *
	 * @param string $mysql_datetime MySQL datetime string.
	 * @return DateTimeImmutable|null
	 */
	function cta_lms_parse_datetime( $mysql_datetime ) {
		$mysql_datetime = trim( (string) $mysql_datetime );

		if ( '' === $mysql_datetime || '0000-00-00 00:00:00' === $mysql_datetime ) {
			return null;
		}

		try {
			$wp_tz = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
			return new DateTimeImmutable( $mysql_datetime, $wp_tz );
		} catch ( Exception $e ) {
			return null;
		}
	}
}

if ( ! function_exists( 'cta_lms_resolve_timezone' ) ) {
	/**
	 * Resolve a timezone string or object, falling back to the CTA display timezone.
	 *
	 * @param DateTimeZone|string|null $timezone Timezone candidate.
	 * @return DateTimeZone
	 */
	function cta_lms_resolve_timezone( $timezone = null ) {
		if ( $timezone instanceof DateTimeZone ) {
			return $timezone;
		}

		if ( is_string( $timezone ) && '' !== $timezone ) {
			try {
				return new DateTimeZone( $timezone );
			} catch ( Exception $e ) {
				// Fall through to CTA timezone.
			}
		}

		return cta_lms_get_timezone();
	}
}

if ( ! function_exists( 'cta_lms_escape_date_format_literal' ) ) {
	/**
	 * Escape a literal string for use inside a PHP date() / wp_date() format.
	 *
	 * @param string $literal Literal text (e.g. PST).
	 * @return string
	 */
	function cta_lms_escape_date_format_literal( $literal ) {
		$literal = (string) $literal;
		$out     = '';
		$len     = strlen( $literal );

		for ( $i = 0; $i < $len; $i++ ) {
			$out .= '\\' . $literal[ $i ];
		}

		return $out;
	}
}

if ( ! function_exists( 'cta_lms_get_timezone_abbr' ) ) {
	/**
	 * Reliable timezone abbreviation for display (never GMT+0000 for Pacific).
	 *
	 * @param DateTimeInterface $dt Datetime already in the display timezone.
	 * @return string
	 */
	function cta_lms_get_timezone_abbr( DateTimeInterface $dt ) {
		$name = $dt->getTimezone()->getName();

		// Pacific: derive PST/PDT from DST flag (avoids host-specific T quirks).
		if ( in_array( $name, array( 'America/Los_Angeles', 'US/Pacific', 'PST8PDT' ), true ) ) {
			return ( '1' === $dt->format( 'I' ) ) ? 'PDT' : 'PST';
		}

		$abbr = $dt->format( 'T' );

		// PHP/Windows sometimes returns GMT / GMT+0000 / +0000 even when offset ≠ 0.
		if (
			'' === $abbr
			|| 0 === strcasecmp( $abbr, 'GMT' )
			|| 0 === strcasecmp( $abbr, 'UTC' )
			|| (bool) preg_match( '/^GMT[+-]/d{2}:?\d{2}$/i', $abbr )
			|| (bool) preg_match( '/^[+-]\d{4}$/', $abbr )
		) {
			$offset_hours = (int) floor( ( (int) $dt->format( 'Z' ) ) / 3600 );

			if ( 0 === $offset_hours ) {
				// Confirm real UTC vs broken label for a non-UTC zone.
				if ( 'UTC' === $name || 'Etc/UTC' === $name || 'GMT' === $name || 'Etc/GMT' === $name ) {
					return 'UTC';
				}
			}

			return sprintf( 'UTC%+d', $offset_hours );
		}

		return $abbr;
	}
}

if ( ! function_exists( 'cta_lms_date' ) ) {
	/**
	 * Format a unix timestamp in the CTA (or override) timezone.
	 *
	 * Replaces unescaped "T" in the format with a reliable abbreviation so
	 * learners never see GMT+0000 for Pacific sessions.
	 *
	 * @param string                   $format    PHP date format.
	 * @param int|null                 $timestamp Unix timestamp (null = now).
	 * @param DateTimeZone|string|null $timezone  Display timezone.
	 * @return string
	 */
	function cta_lms_date( $format, $timestamp = null, $timezone = null ) {
		$tz = cta_lms_resolve_timezone( $timezone );

		if ( null === $timestamp ) {
			$timestamp = time();
		}

		$timestamp = (int) $timestamp;
		$dt        = ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $tz );
		$format    = (string) $format;

		if ( preg_match( '/(?<!\\\\)T/', $format ) ) {
			$abbr   = cta_lms_get_timezone_abbr( $dt );
			$format = preg_replace( '/(?<!\\\\)T/', cta_lms_escape_date_format_literal( $abbr ), $format );
		}

		if ( function_exists( 'wp_date' ) ) {
			return wp_date( $format, $timestamp, $tz );
		}

		return $dt->format( $format );
	}
}

if ( ! function_exists( 'cta_lms_format_local_date' ) ) {
	/**
	 * Format a timestamp or MySQL datetime for display in the CTA timezone (or override).
	 *
	 * @param string|int|null          $value    MySQL datetime, unix timestamp, or null for now.
	 * @param string                   $format   PHP date format.
	 * @param DateTimeZone|string|null $timezone Display timezone.
	 * @return string
	 */
	function cta_lms_format_local_date( $value = null, $format = 'F j, Y', $timezone = null ) {
		$tz = cta_lms_resolve_timezone( $timezone );

		if ( null === $value || '' === $value ) {
			return cta_lms_date( $format, null, $tz );
		}

		if ( is_numeric( $value ) ) {
			return cta_lms_date( $format, (int) $value, $tz );
		}

		$parsed = cta_lms_parse_datetime( (string) $value );

		if ( ! $parsed ) {
			return cta_lms_date( $format, null, $tz );
		}

		return cta_lms_date( $format, $parsed->getTimestamp(), $tz );
	}
}

if ( ! function_exists( 'cta_lms_session_datetime' ) ) {
	/**
	 * Build a DateTimeImmutable for a supervision session wall-clock time.
	 *
	 * Session date/time fields are stored as Pacific (CTA timezone) wall clock values.
	 *
	 * @param string $date Y-m-d date.
	 * @param string $time Optional H:i or H:i:s time.
	 * @return DateTimeImmutable|null
	 */
	function cta_lms_session_datetime( $date, $time = '00:00:00' ) {
		$date = trim( (string) $date );
		$time = trim( (string) $time );

		if ( '' === $date ) {
			return null;
		}

		if ( '' === $time ) {
			$time = '00:00:00';
		}

		if ( preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
			$time .= ':00';
		}

		try {
			return new DateTimeImmutable( $date . ' ' . $time, cta_lms_get_timezone() );
		} catch ( Exception $e ) {
			return null;
		}
	}
}

if ( ! function_exists( 'cta_lms_format_session_date' ) ) {
	/**
	 * Format a session date in the CTA timezone.
	 *
	 * @param string $date   Y-m-d.
	 * @param string $format PHP date format.
	 * @return string
	 */
	function cta_lms_format_session_date( $date, $format = 'l, F j, Y' ) {
		$dt = cta_lms_session_datetime( $date, '00:00:00' );

		if ( ! $dt ) {
			return (string) $date;
		}

		return cta_lms_date( $format, $dt->getTimestamp(), cta_lms_get_timezone() );
	}
}

if ( ! function_exists( 'cta_lms_format_session_time' ) ) {
	/**
	 * Format a session time with timezone abbreviation (PST/PDT).
	 *
	 * @param string $date   Y-m-d.
	 * @param string $time   H:i[:s].
	 * @param string $format PHP date format.
	 * @return string
	 */
	function cta_lms_format_session_time( $date, $time, $format = 'g:i A T' ) {
		$dt = cta_lms_session_datetime( $date, $time );

		if ( ! $dt ) {
			return substr( (string) $time, 0, 5 );
		}

		return cta_lms_date( $format, $dt->getTimestamp(), cta_lms_get_timezone() );
	}
}

if ( ! function_exists( 'cta_lms_format_session_datetime' ) ) {
	/**
	 * Format a full session date + time label.
	 *
	 * @param string $date   Y-m-d.
	 * @param string $time   H:i[:s].
	 * @param string $format PHP date format.
	 * @return string
	 */
	function cta_lms_format_session_datetime( $date, $time, $format = 'l, F j, Y · g:i A T' ) {
		$dt = cta_lms_session_datetime( $date, $time );

		if ( ! $dt ) {
			return trim( $date . ' ' . substr( (string) $time, 0, 5 ) );
		}

		return cta_lms_date( $format, $dt->getTimestamp(), cta_lms_get_timezone() );
	}
}

if ( ! function_exists( 'cta_lms_current_date' ) ) {
	/**
	 * Current calendar date in the CTA timezone.
	 *
	 * @param string $format PHP date format.
	 * @return string
	 */
	function cta_lms_current_date( $format = 'Y-m-d' ) {
		return cta_lms_date( $format, null, cta_lms_get_timezone() );
	}
}

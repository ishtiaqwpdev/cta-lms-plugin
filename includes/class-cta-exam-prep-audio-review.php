<?php
/**
 * Exam Prep Audio Review center data provider.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CTA_Exam_Prep_Audio_Review' ) ) {

class CTA_Exam_Prep_Audio_Review {

	/**
	 * Build grouped audio review data for a course.
	 *
	 * @param object|null           $course    Course row.
	 * @param array                 $modules   Module rows.
	 * @param CTA_Student_Dashboard $dashboard Dashboard instance.
	 * @return array<string,mixed>
	 */
	public static function get_center_data_for_course( $course, array $modules, $dashboard ) {
		if ( ! $course || ! $dashboard ) {
			return self::empty_data();
		}

		$course_id = (int) $course->id;
		$resources = (array) CTA_Database::get_downloadable_resources( $course_id );
		if ( class_exists( 'CTA_Course_Materials' ) ) {
			$resources = CTA_Course_Materials::filter_student_visible_resources( $resources );
		}

		$module_map = array();
		foreach ( $modules as $index => $module ) {
			$module_map[ (int) $module->id ] = array(
				'title' => (string) ( $module->title ?? '' ),
				'order' => (int) $index,
			);
		}

		$tracks = self::build_tracks( $resources, $module_map );
		$groups = self::group_tracks( $tracks );
		$data   = array(
			'groups'          => $groups,
			'tracks'          => $tracks,
			'track_count'     => count( $tracks ),
			'group_count'     => count( $groups ),
			'total_runtime'   => self::sum_runtime_labels( $tracks ),
			'has_audio'       => ! empty( $tracks ),
			'audio_url'       => $dashboard->get_player_view_url( $course_id, 'audio' ),
		);

		return apply_filters( 'cta_exam_prep_audio_review_data', $data, $course, $dashboard );
	}

	/**
	 * Empty payload.
	 *
	 * @return array<string,mixed>
	 */
	public static function empty_data() {
		return array(
			'groups'        => array(),
			'tracks'        => array(),
			'track_count'   => 0,
			'group_count'   => 0,
			'total_runtime' => '',
			'has_audio'     => false,
			'audio_url'     => '',
		);
	}

	/**
	 * Sidebar group links; an empty array suppresses the Audio Review section.
	 *
	 * @param array $center Audio center payload.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_sidebar_children( array $center ) {
		$children = array();
		$base_url = (string) ( $center['audio_url'] ?? '' );

		foreach ( (array) ( $center['groups'] ?? array() ) as $group ) {
			$key = sanitize_key( (string) ( $group['key'] ?? '' ) );
			if ( '' === $key ) {
				continue;
			}
			$children[] = array(
				'key'       => 'audio-' . $key,
				'label'     => (string) ( $group['label'] ?? '' ),
				'title'     => (string) ( $group['label'] ?? '' ),
				'url'       => $base_url . '#cta-ar-' . $key,
				'is_active' => false,
				'external'  => false,
			);
		}

		return $children;
	}

	/**
	 * Build normalized playable track rows.
	 *
	 * @param array                         $resources  Resource rows.
	 * @param array<int,array<string,mixed>> $module_map Module metadata.
	 * @return array<int,array<string,mixed>>
	 */
	private static function build_tracks( array $resources, array $module_map ) {
		$tracks = array();
		$seen   = array();

		foreach ( $resources as $resource ) {
			if ( ! self::is_audio_resource( $resource ) || ! class_exists( 'CTA_Course_Materials' ) ) {
				continue;
			}
			if ( ! CTA_Course_Materials::user_can_access( get_current_user_id(), $resource ) ) {
				continue;
			}

			$resource_id = (int) ( $resource->id ?? 0 );
			$stream_url  = CTA_Course_Materials::get_serve_url( $resource_id );
			$download_url= CTA_Course_Materials::get_download_url( $resource_id );
			$local       = CTA_Course_Materials::resolve_local_path( $resource );
			$external    = self::get_external_audio_url( $resource );
			if ( $resource_id <= 0 || '' === $stream_url || ( ! $local && '' === $external ) ) {
				continue;
			}

			$filename = $local
				? basename( $local )
				: basename( (string) wp_parse_url( $external, PHP_URL_PATH ) );
			$dedupe   = $local
				? strtolower( wp_normalize_path( $local ) )
				: strtolower( $external );
			if ( isset( $seen[ $dedupe ] ) ) {
				continue;
			}
			$seen[ $dedupe ] = true;

			$meta      = self::resolve_authoritative_meta( $resource );
			$module_id = (int) ( $resource->module_id ?? 0 );
			$track     = (int) ( $meta['track'] ?? self::infer_track_number( $resource, $filename ) );
			$title     = ! empty( $meta['title'] )
				? (string) $meta['title']
				: self::clean_title( (string) ( $resource->title ?? $filename ) );
			$runtime   = ! empty( $meta['runtime'] ) ? self::normalize_runtime( (string) $meta['runtime'] ) : '';

			$tracks[] = array(
				'resource_id'  => $resource_id,
				'track_number' => $track,
				'title'        => $title,
				'runtime'      => $runtime,
				'filename'     => sanitize_file_name( $filename ),
				'stream_url'   => $stream_url,
				'download_url' => $download_url,
				'module_id'    => $module_id,
				'module_title' => isset( $module_map[ $module_id ] ) ? (string) $module_map[ $module_id ]['title'] : '',
				'sort_order'   => $track > 0
					? $track
					: ( isset( $module_map[ $module_id ] ) ? 100 + (int) $module_map[ $module_id ]['order'] : 999 ),
				'group'        => $module_id > 0 ? 'workbook' : 'program',
			);
		}

		usort(
			$tracks,
			static function ( $a, $b ) {
				$order = (int) $a['sort_order'] <=> (int) $b['sort_order'];
				return 0 !== $order ? $order : strnatcasecmp( (string) $a['title'], (string) $b['title'] );
			}
		);

		return $tracks;
	}

	/**
	 * Group workbook-aligned and program-level recordings.
	 *
	 * @param array<int,array<string,mixed>> $tracks Tracks.
	 * @return array<int,array<string,mixed>>
	 */
	private static function group_tracks( array $tracks ) {
		$groups = array(
			'workbook' => array(
				'key'         => 'workbook-reviews',
				'label'       => __( 'Workbook-Aligned Audio Reviews', 'cta-lms' ),
				'description' => __( 'Recorded reviews arranged in workbook and study sequence order.', 'cta-lms' ),
				'tracks'      => array(),
			),
			'program'  => array(
				'key'         => 'program-reviews',
				'label'       => __( 'Program Review Recordings', 'cta-lms' ),
				'description' => __( 'Program-wide audio summaries and integrated review sessions.', 'cta-lms' ),
				'tracks'      => array(),
			),
		);

		foreach ( $tracks as $track ) {
			$key = 'workbook' === ( $track['group'] ?? '' ) ? 'workbook' : 'program';
			$groups[ $key ]['tracks'][] = $track;
		}

		return array_values(
			array_filter(
				$groups,
				static function ( $group ) {
					return ! empty( $group['tracks'] );
				}
			)
		);
	}

	/**
	 * Resolve exact track title/runtime from program sync metadata when present.
	 *
	 * @param object $resource Resource row.
	 * @return array<string,mixed>
	 */
	private static function resolve_authoritative_meta( $resource ) {
		$bits = strtolower(
			(string) ( $resource->file_path ?? '' ) . ' ' .
			(string) ( $resource->file_url ?? '' ) . ' ' .
			(string) ( $resource->title ?? '' )
		);
		$resolvers = array();

		if ( false !== strpos( $bits, 'lmft_amftrb' ) || false !== strpos( $bits, 'lmft-amftrb' ) ) {
			$resolvers[] = array( 'CTA_Lmft_Amftrb_Sync', 'resolve_audio_meta' );
		} elseif ( false !== strpos( $bits, 'cta_lpcc_audio' )
			|| false !== strpos( $bits, 'lpcc-ncmhce' )
			|| false !== strpos( $bits, 'lpcc_ncmhce' ) ) {
			$resolvers[] = array( 'CTA_Lpcc_Ncmhce_Sync', 'resolve_audio_meta' );
		}

		foreach ( $resolvers as $resolver ) {
			if ( is_callable( $resolver ) ) {
				$meta = call_user_func( $resolver, $resource );
				if ( is_array( $meta ) && ! empty( $meta ) ) {
					return $meta;
				}
			}
		}

		return array();
	}

	/**
	 * Audio resource detection.
	 *
	 * @param object|null $resource Resource row.
	 * @return bool
	 */
	public static function is_audio_resource( $resource ) {
		if ( ! $resource ) {
			return false;
		}

		$bits = strtolower(
			(string) ( $resource->file_path ?? '' ) . ' ' .
			(string) ( $resource->file_url ?? '' ) . ' ' .
			(string) ( $resource->title ?? '' ) . ' ' .
			(string) ( $resource->file_type ?? '' )
		);

		return (bool) preg_match( '/\.(mp3|m4a|wav|ogg)\b|audio review|audio track/i', $bits );
	}

	/**
	 * Valid legacy external audio URL.
	 *
	 * @param object $resource Resource row.
	 * @return string
	 */
	private static function get_external_audio_url( $resource ) {
		$url = (string) ( $resource->file_url ?? '' );
		if ( '' === $url || 0 === strpos( $url, 'cta-protected://' ) || ! wp_http_validate_url( $url ) ) {
			return '';
		}
		return $url;
	}

	/**
	 * Infer a track number from title/path.
	 *
	 * @param object $resource Resource row.
	 * @param string $filename Filename.
	 * @return int
	 */
	private static function infer_track_number( $resource, $filename ) {
		$bits = (string) ( $resource->title ?? '' ) . ' ' .
			(string) ( $resource->file_path ?? '' ) . ' ' .
			(string) $filename;
		return preg_match( '/(?:track|audio)[_\s-]*0*(\d+)/i', $bits, $match ) ? (int) $match[1] : 0;
	}

	/**
	 * Clean a fallback recording title.
	 *
	 * @param string $title Raw title.
	 * @return string
	 */
	private static function clean_title( $title ) {
		$title = preg_replace( '/\bv\d+(?:\.\d+)*\b/i', '', (string) $title );
		$title = preg_replace( '/[_-]+/', ' ', (string) $title );
		return trim( preg_replace( '/\s+/', ' ', (string) $title ) );
	}

	/**
	 * Normalize runtimes with milliseconds to learner-friendly mm:ss.
	 *
	 * @param string $runtime Runtime label.
	 * @return string
	 */
	private static function normalize_runtime( $runtime ) {
		$runtime = trim( (string) $runtime );
		if ( preg_match( '/^(\d+):(\d{2})(?:\.(\d+))?$/', $runtime, $match ) ) {
			$minutes = (int) $match[1];
			$seconds = (int) $match[2];
			if ( ! empty( $match[3] ) && (int) substr( str_pad( $match[3], 3, '0' ), 0, 3 ) >= 500 ) {
				++$seconds;
				if ( $seconds >= 60 ) {
					$seconds = 0;
					++$minutes;
				}
			}
			return sprintf( '%d:%02d', $minutes, $seconds );
		}
		return $runtime;
	}

	/**
	 * Sum known mm:ss runtimes.
	 *
	 * @param array<int,array<string,mixed>> $tracks Tracks.
	 * @return string
	 */
	private static function sum_runtime_labels( array $tracks ) {
		$total = 0;
		foreach ( $tracks as $track ) {
			$runtime = (string) ( $track['runtime'] ?? '' );
			if ( preg_match( '/^(\d+):(\d{2})$/', $runtime, $match ) ) {
				$total += ( (int) $match[1] * 60 ) + (int) $match[2];
			}
		}
		if ( $total <= 0 ) {
			return '';
		}
		$hours   = (int) floor( $total / 3600 );
		$minutes = (int) floor( ( $total % 3600 ) / 60 );
		$seconds = $total % 60;
		return $hours > 0
			? sprintf( '%d:%02d:%02d', $hours, $minutes, $seconds )
			: sprintf( '%d:%02d', $minutes, $seconds );
	}
}

}

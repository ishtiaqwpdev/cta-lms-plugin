<?php
/**
 * Course downloadable materials: storage, access checks, and gated serving.
 *
 * Files are copied into a protected uploads subdirectory so unenrolled users
 * cannot download them via a direct Media Library URL. Learners always fetch
 * materials through the gated serve endpoint after enrollment is verified.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Course_Materials
 */
if ( ! class_exists( 'CTA_Course_Materials' ) ) {

class CTA_Course_Materials {

	const UPLOAD_SUBDIR = 'cta-course-materials';

	/** @var int Max upload size in bytes (20MB). */
	const MAX_UPLOAD_BYTES = 20971520;

	/** @var array Allowed MIME types keyed by extension. */
	const ALLOWED_MIMES = array(
		'pdf'  => 'application/pdf',
		'doc'  => 'application/msword',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
	);

	/**
	 * Allowed extensions for course materials (PDF / DOC / DOCX).
	 *
	 * @return string[]
	 */
	public static function allowed_extensions() {
		return array( 'pdf', 'doc', 'docx' );
	}

	/**
	 * Validate an attachment for use as a course material.
	 *
	 * @param int $attachment_id Media Library attachment ID.
	 * @return true|WP_Error
	 */
	public static function validate_attachment( $attachment_id ) {
		$attachment_id = absint( $attachment_id );

		if ( ! $attachment_id ) {
			return new WP_Error(
				'cta_resource_missing',
				__( 'Please select or upload a file for this material.', 'cta-lms' )
			);
		}

		$path = get_attached_file( $attachment_id );

		if ( ! $path || ! file_exists( $path ) ) {
			return new WP_Error(
				'cta_resource_missing',
				__( 'Attachment file not found.', 'cta-lms' )
			);
		}

		$size = filesize( $path );
		if ( false !== $size && $size > self::MAX_UPLOAD_BYTES ) {
			return new WP_Error(
				'cta_resource_too_large',
				__( 'File exceeds the 20MB size limit. Please upload a smaller PDF, DOC, or DOCX file.', 'cta-lms' )
			);
		}

		$checked = wp_check_filetype_and_ext( $path, basename( $path ), self::ALLOWED_MIMES );
		$ext     = ! empty( $checked['ext'] ) ? strtolower( (string) $checked['ext'] ) : '';

		if ( '' === $ext || ! isset( self::ALLOWED_MIMES[ $ext ] ) ) {
			// Fallback: basename extension when filetype helpers are inconclusive (common for DOC).
			$fallback = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
			if ( ! in_array( $fallback, self::allowed_extensions(), true ) ) {
				return new WP_Error(
					'cta_resource_invalid_type',
					__( 'Only PDF, DOC, and DOCX files are allowed for course materials.', 'cta-lms' )
				);
			}
		}

		return true;
	}

	/**
	 * Whether a user may access a resource.
	 *
	 * @param int         $user_id  User ID.
	 * @param object|null $resource Resource row.
	 * @return bool
	 */
	public static function user_can_access( $user_id, $resource ) {
		$user_id = absint( $user_id );

		if ( ! $user_id || ! $resource || empty( $resource->course_id ) ) {
			return false;
		}

		$course_id  = (int) $resource->course_id;
		$enrollment = class_exists( 'CTA_Database' )
			? CTA_Database::get_user_enrollment( $user_id, $course_id )
			: null;

		if ( ! $enrollment || ! in_array( $enrollment->status, array( 'active', 'completed' ), true ) ) {
			return false;
		}

		$course = CTA_Database::get_course( $course_id );

		if ( class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course ) ) {
			return CTA_Exam_Access::has_active_access( $user_id, $course_id );
		}

		if ( class_exists( 'CTA_CE_Access' ) && CTA_CE_Access::is_ce_course( $course ) ) {
			return CTA_CE_Access::has_active_access( $user_id, $course_id );
		}

		return true;
	}

	/**
	 * Public (gated) download URL for a resource — never exposes the raw file path.
	 *
	 * @param int $resource_id Resource ID.
	 * @return string
	 */
	public static function get_serve_url( $resource_id ) {
		$resource_id = absint( $resource_id );

		if ( ! $resource_id ) {
			return '';
		}

		return wp_nonce_url(
			add_query_arg(
				array(
					'action'      => 'cta_serve_resource',
					'resource_id' => $resource_id,
				),
				admin_url( 'admin-post.php' )
			),
			'cta_serve_resource_' . $resource_id
		);
	}

	/**
	 * Absolute path to the protected materials root (creates dir + deny rules).
	 *
	 * @return string|WP_Error
	 */
	public static function get_protected_root() {
		$upload = wp_upload_dir();

		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'cta_upload', $upload['error'] );
		}

		$dir = trailingslashit( $upload['basedir'] ) . self::UPLOAD_SUBDIR;

		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'cta_mkdir', __( 'Could not create materials directory.', 'cta-lms' ) );
		}

		self::ensure_deny_rules( $dir );

		return $dir;
	}

	/**
	 * Write .htaccess / index.php deny rules into the materials directory.
	 *
	 * @param string $dir Absolute directory path.
	 */
	public static function ensure_deny_rules( $dir ) {
		$dir = trailingslashit( $dir );

		$htaccess = $dir . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents(
				$htaccess,
				"Order deny,allow\nDeny from all\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n"
			);
		}

		$index = $dir . 'index.php';
		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
	}

	/**
	 * Copy a Media Library attachment into the protected materials folder.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $course_id     Course ID.
	 * @return array|WP_Error { relative_path, file_url_placeholder, file_type, absolute_path }
	 */
	public static function import_attachment_to_protected( $attachment_id, $course_id ) {
		$attachment_id = absint( $attachment_id );
		$course_id     = absint( $course_id );

		$valid = self::validate_attachment( $attachment_id );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$source = get_attached_file( $attachment_id );

		if ( ! $source || ! file_exists( $source ) ) {
			return new WP_Error( 'cta_missing_file', __( 'Attachment file not found.', 'cta-lms' ) );
		}

		$root = self::get_protected_root();
		if ( is_wp_error( $root ) ) {
			return $root;
		}

		$course_dir = trailingslashit( $root ) . $course_id;
		if ( ! wp_mkdir_p( $course_dir ) ) {
			return new WP_Error( 'cta_mkdir', __( 'Could not create course materials folder.', 'cta-lms' ) );
		}
		self::ensure_deny_rules( $course_dir );

		$filename = wp_unique_filename( $course_dir, basename( $source ) );
		$dest     = trailingslashit( $course_dir ) . $filename;

		if ( ! copy( $source, $dest ) ) {
			return new WP_Error( 'cta_copy', __( 'Could not copy file into protected storage.', 'cta-lms' ) );
		}

		$relative = self::UPLOAD_SUBDIR . '/' . $course_id . '/' . $filename;
		$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		return array(
			'relative_path' => $relative,
			'absolute_path' => $dest,
			'file_type'     => $ext ? $ext : 'file',
			// Placeholder — learners never receive a direct URL.
			'file_url'      => 'cta-protected://' . $relative,
		);
	}

	/**
	 * Bundled course materials shipped inside the plugin (source → course match).
	 *
	 * Files live under assets/course-materials/ and are served only through the
	 * enrollment-gated download endpoint (never as a public plugin URL).
	 *
	 * @return array[]
	 */
	public static function get_bundled_materials() {
		return array(
			array(
				'course_match_titles' => array(
					'California Law & Ethics for Mental Health Professionals: Navigating the Evolving Clinical Landscape',
					'California Law & Ethics for Mental Health Professionals',
				),
				'course_code'         => 'CTA-CE-001',
				'source_file'         => 'assets/course-materials/CTA_CE_001_California_Law_Ethics_Final_Syllabus_v2_1.pdf',
				'alt_source_files'    => array(
					'assets/course-materials/Final_Syllabus_v2_1.pdf',
					'assets/course-materials/CTA_CE_001_Final_Syllabus_v2_1.pdf',
				),
				'title'               => 'Final Syllabus v2.1',
				'resource_key'        => 'cta_ce_001_final_syllabus_v2_1',
				'is_syllabus'         => true,
			),
			array(
				'course_match_titles' => array(
					'California Law & Ethics for Mental Health Professionals: Navigating the Evolving Clinical Landscape',
					'California Law & Ethics for Mental Health Professionals',
				),
				'course_code'         => 'CTA-CE-001',
				'source_file'         => 'assets/course-materials/CTA_California_Law_Ethics_Practice_Protection_Toolkit_v1_0.pdf',
				'alt_source_files'    => array(
					'assets/course-materials/CTA_California_Law_Ethics_Practice_Protection_Toolkit_v1.0.pdf',
				),
				'title'               => 'California Law & Ethics Practice Protection Toolkit (Resource Workbook)',
				'resource_key'        => 'cta_ce_001_practice_protection_toolkit_v1',
				// Course-level only: supplementary workbook covering all topic areas (not module-gated).
				'module_id'           => 0,
			),
			array(
				'course_match_titles' => array(
					'Clinical and Ethical Excellence in Telehealth: The Essential California Framework',
					'Clinical and Ethical Excellence in Telehealth',
				),
				'source_file'         => 'assets/course-materials/CTA_Telehealth_Clinical_Resource_Toolkit_v2_0.docx',
				// Prefer PDF if present (matches other course handouts); fall back to DOCX.
				'alt_source_files'    => array(
					'assets/course-materials/CTA_Telehealth_Clinical_Resource_Toolkit_v2_0.pdf',
				),
				'title'               => 'CTA Telehealth Clinical Resource Toolkit (v2.0)',
				'resource_key'        => 'telehealth_clinical_resource_toolkit_v2',
			),
		);
	}

	/**
	 * Find the downloadable syllabus resource among course materials (if any).
	 *
	 * Prefers titles that look like a syllabus PDF (e.g. "Final Syllabus v2.1").
	 *
	 * @param array $resources Downloadable resource rows.
	 * @return object|null
	 */
	public static function find_syllabus_resource( array $resources ) {
		$preferred = null;
		foreach ( $resources as $resource ) {
			$title = isset( $resource->title ) ? (string) $resource->title : '';
			if ( '' === $title ) {
				continue;
			}
			if ( preg_match( '/\bfinal\s+syllabus\b/i', $title ) || preg_match( '/\bsyllabus\b.*\bv\s*2\.?1\b/i', $title ) ) {
				return $resource;
			}
			if ( ! $preferred && preg_match( '/\bsyllabus\b/i', $title ) ) {
				$preferred = $resource;
			}
		}
		return $preferred;
	}

	/**
	 * Ensure bundled materials are registered as downloadable resources.
	 *
	 * Idempotent: skips when the resource title (or known alias) already exists
	 * for the course, or when the source file is not present in the plugin package.
	 *
	 * @return array{attached:int,skipped:int,missing:array}
	 */
	public static function ensure_bundled_resources() {
		global $wpdb;

		$attached = 0;
		$skipped  = 0;
		$missing  = array();

		foreach ( self::get_bundled_materials() as $bundle ) {
			$source = self::resolve_bundled_source_path( $bundle );
			if ( '' === $source ) {
				$missing[] = (string) ( $bundle['source_file'] ?? '' );
				++$skipped;
				continue;
			}

			$course = self::find_course_for_bundle( $bundle );
			if ( ! $course ) {
				++$skipped;
				continue;
			}

			$course_id = (int) $course->id;
			$title     = sanitize_text_field( (string) ( $bundle['title'] ?? basename( $source ) ) );

			if ( self::course_has_bundled_resource( $course_id, $bundle, $title ) ) {
				++$skipped;
				continue;
			}

			$imported = self::import_local_file_to_protected( $source, $course_id );
			if ( is_wp_error( $imported ) ) {
				$missing[] = basename( $source ) . ' (' . $imported->get_error_message() . ')';
				++$skipped;
				continue;
			}

			$max_order = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(MAX(order_index), -1) FROM {$wpdb->prefix}cta_downloadable_resources WHERE course_id = %d",
					$course_id
				)
			);

			$module_id = isset( $bundle['module_id'] ) ? absint( $bundle['module_id'] ) : 0;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$ok = $wpdb->insert(
				$wpdb->prefix . 'cta_downloadable_resources',
				array(
					'course_id'        => $course_id,
					'module_id'        => $module_id,
					'attachment_id'    => 0,
					'title'            => $title,
					'file_url'         => $imported['file_url'],
					'file_path'        => $imported['relative_path'],
					'file_type'        => $imported['file_type'],
					'order_index'      => $max_order + 1,
					'is_practice_test' => 0,
				),
				array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d' )
			);

			if ( $ok ) {
				++$attached;
			} else {
				++$skipped;
			}
		}

		return array(
			'attached' => $attached,
			'skipped'  => $skipped,
			'missing'  => $missing,
		);
	}

	/**
	 * Whether a course already has this bundled resource (by title or alias).
	 *
	 * @param int    $course_id Course ID.
	 * @param array  $bundle    Bundle definition.
	 * @param string $title     Canonical title.
	 * @return bool
	 */
	private static function course_has_bundled_resource( $course_id, array $bundle, $title ) {
		global $wpdb;

		$aliases = array( $title );
		if ( ! empty( $bundle['title_aliases'] ) && is_array( $bundle['title_aliases'] ) ) {
			foreach ( $bundle['title_aliases'] as $alias ) {
				$alias = sanitize_text_field( (string) $alias );
				if ( '' !== $alias ) {
					$aliases[] = $alias;
				}
			}
		}

		// Recognize common informal names for the Practice Protection Toolkit.
		if ( ! empty( $bundle['resource_key'] ) && 'cta_ce_001_practice_protection_toolkit_v1' === $bundle['resource_key'] ) {
			$aliases[] = 'California Law & Ethics Practice Protection Toolkit v1.0';
			$aliases[] = 'Practice Protection Toolkit';
			$aliases[] = 'California Law & Ethics Practice Protection Toolkit';
		}

		$aliases = array_values( array_unique( $aliases ) );
		if ( empty( $aliases ) ) {
			return false;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $aliases ), '%s' ) );
		$params       = array_merge( array( (int) $course_id ), $aliases );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared
		$existing_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}cta_downloadable_resources
				WHERE course_id = %d AND title IN ($placeholders)
				LIMIT 1",
				$params
			)
		);

		return $existing_id > 0;
	}

	/**
	 * Resolve the first existing bundled source path for a material definition.
	 *
	 * @param array $bundle Bundle definition.
	 * @return string Absolute path or empty.
	 */
	private static function resolve_bundled_source_path( array $bundle ) {
		$candidates = array();
		if ( ! empty( $bundle['alt_source_files'] ) && is_array( $bundle['alt_source_files'] ) ) {
			foreach ( $bundle['alt_source_files'] as $rel ) {
				$candidates[] = (string) $rel;
			}
		}
		if ( ! empty( $bundle['source_file'] ) ) {
			$candidates[] = (string) $bundle['source_file'];
		}

		foreach ( $candidates as $relative ) {
			$relative = ltrim( str_replace( '\\', '/', $relative ), '/' );
			$path     = CTA_PLUGIN_DIR . $relative;
			if ( is_readable( $path ) ) {
				return $path;
			}
		}

		return '';
	}

	/**
	 * Find a CE course matching bundled course_code or title aliases.
	 *
	 * @param array $bundle Bundle definition.
	 * @return object|null
	 */
	private static function find_course_for_bundle( array $bundle ) {
		$code = isset( $bundle['course_code'] ) ? trim( (string) $bundle['course_code'] ) : '';
		if ( '' !== $code && 'CTA-CE-001' === $code && class_exists( 'CTA_Law_Ethics_Module_Sync' ) ) {
			$course = CTA_Law_Ethics_Module_Sync::find_course();
			if ( $course ) {
				return $course;
			}
		}

		if ( '' !== $code && class_exists( 'CTA_Database' ) ) {
			global $wpdb;
			$table = $wpdb->prefix . 'cta_courses';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC" );
			foreach ( (array) $rows as $row ) {
				if ( empty( $row->syllabus_meta ) ) {
					continue;
				}
				$decoded = json_decode( (string) $row->syllabus_meta, true );
				if ( is_array( $decoded ) && isset( $decoded['course_code'] ) && $code === (string) $decoded['course_code'] ) {
					return $row;
				}
			}
		}

		$titles = isset( $bundle['course_match_titles'] ) ? (array) $bundle['course_match_titles'] : array();
		foreach ( $titles as $title ) {
			$title = trim( (string) $title );
			if ( '' === $title || ! class_exists( 'CTA_Database' ) ) {
				continue;
			}
			$course = CTA_Database::get_course_by_title( $title );
			if ( $course ) {
				return $course;
			}
		}
		return null;
	}

	/**
	 * Copy a local filesystem file into protected course materials storage.
	 *
	 * @param string $source_path Absolute source path.
	 * @param int    $course_id   Course ID.
	 * @return array|WP_Error
	 */
	public static function import_local_file_to_protected( $source_path, $course_id ) {
		$course_id   = absint( $course_id );
		$source_path = (string) $source_path;

		if ( ! $course_id || ! is_readable( $source_path ) ) {
			return new WP_Error( 'cta_missing_file', __( 'Source material file not found.', 'cta-lms' ) );
		}

		$size = filesize( $source_path );
		if ( false !== $size && $size > self::MAX_UPLOAD_BYTES ) {
			return new WP_Error(
				'cta_resource_too_large',
				__( 'File exceeds the 20MB size limit. Please upload a smaller PDF, DOC, or DOCX file.', 'cta-lms' )
			);
		}

		$ext = strtolower( pathinfo( $source_path, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, self::allowed_extensions(), true ) ) {
			return new WP_Error(
				'cta_resource_invalid_type',
				__( 'Only PDF, DOC, and DOCX files are allowed for course materials.', 'cta-lms' )
			);
		}

		$root = self::get_protected_root();
		if ( is_wp_error( $root ) ) {
			return $root;
		}

		$course_dir = trailingslashit( $root ) . $course_id;
		if ( ! wp_mkdir_p( $course_dir ) ) {
			return new WP_Error( 'cta_mkdir', __( 'Could not create course materials folder.', 'cta-lms' ) );
		}
		self::ensure_deny_rules( $course_dir );

		$filename = wp_unique_filename( $course_dir, basename( $source_path ) );
		$dest     = trailingslashit( $course_dir ) . $filename;

		if ( ! copy( $source_path, $dest ) ) {
			return new WP_Error( 'cta_copy', __( 'Could not copy file into protected storage.', 'cta-lms' ) );
		}

		$relative = self::UPLOAD_SUBDIR . '/' . $course_id . '/' . $filename;

		return array(
			'relative_path' => $relative,
			'absolute_path' => $dest,
			'file_type'     => $ext,
			'file_url'      => 'cta-protected://' . $relative,
		);
	}

	/**
	 * Resolve an absolute filesystem path for a resource, if local.
	 *
	 * @param object $resource Resource row.
	 * @return string Empty when not a local protected/attachment file.
	 */
	public static function resolve_local_path( $resource ) {
		if ( ! $resource ) {
			return '';
		}

		if ( ! empty( $resource->file_path ) ) {
			$upload = wp_upload_dir();
			if ( empty( $upload['error'] ) ) {
				$path = trailingslashit( $upload['basedir'] ) . ltrim( (string) $resource->file_path, '/\\' );
				if ( file_exists( $path ) ) {
					return $path;
				}
			}
		}

		if ( ! empty( $resource->attachment_id ) ) {
			$path = get_attached_file( (int) $resource->attachment_id );
			if ( $path && file_exists( $path ) ) {
				return $path;
			}
		}

		if ( ! empty( $resource->file_url ) && 0 !== strpos( (string) $resource->file_url, 'cta-protected://' ) ) {
			$upload = wp_upload_dir();
			$url    = (string) $resource->file_url;
			if ( empty( $upload['error'] ) && 0 === strpos( $url, $upload['baseurl'] ) ) {
				$relative = substr( $url, strlen( $upload['baseurl'] ) );
				$path     = trailingslashit( $upload['basedir'] ) . ltrim( $relative, '/\\' );
				if ( file_exists( $path ) ) {
					return $path;
				}
			}
		}

		return '';
	}

	/**
	 * Stream a resource to the browser after access checks (admin-post handler).
	 */
	public static function handle_serve_request() {
		$resource_id = absint( wp_unslash( $_GET['resource_id'] ?? 0 ) );

		if ( ! $resource_id || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'cta_serve_resource_' . $resource_id ) ) {
			wp_die( esc_html__( 'Invalid download request.', 'cta-lms' ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			auth_redirect();
			exit;
		}

		$resource = CTA_Database::get_downloadable_resource( $resource_id );

		if ( ! $resource ) {
			wp_die( esc_html__( 'File not found.', 'cta-lms' ), 404 );
		}

		if ( ! self::user_can_access( get_current_user_id(), $resource ) ) {
			wp_die( esc_html__( 'You must be enrolled in this course to download materials.', 'cta-lms' ), 403 );
		}

		$local = self::resolve_local_path( $resource );

		if ( $local ) {
			$filename = sanitize_file_name( basename( $local ) );
			$mime     = wp_check_filetype( $filename );
			$type     = ! empty( $mime['type'] ) ? $mime['type'] : 'application/octet-stream';

			nocache_headers();
			header( 'Content-Type: ' . $type );
			header( 'Content-Length: ' . (string) filesize( $local ) );
			header( 'Content-Disposition: inline; filename="' . $filename . '"' );
			header( 'X-Content-Type-Options: nosniff' );

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
			readfile( $local );
			exit;
		}

		// Legacy external URL fallback (still gated by enrollment above).
		$url = (string) ( $resource->file_url ?? '' );
		if ( $url && 0 !== strpos( $url, 'cta-protected://' ) ) {
			wp_safe_redirect( esc_url_raw( $url ) );
			exit;
		}

		wp_die( esc_html__( 'File is unavailable.', 'cta-lms' ), 404 );
	}

	/**
	 * Group resources for display (course-level vs per-module).
	 *
	 * @param array $resources Resource rows.
	 * @param array $modules   Module rows (for titles).
	 * @return array{course:array,modules:array<int,array>}
	 */
	public static function group_for_display( $resources, $modules = array() ) {
		$module_titles = array();
		foreach ( (array) $modules as $module ) {
			$module_titles[ (int) $module->id ] = $module->title;
		}

		$grouped = array(
			'course'  => array(),
			'modules' => array(),
		);

		foreach ( (array) $resources as $resource ) {
			$module_id = isset( $resource->module_id ) ? absint( $resource->module_id ) : 0;
			if ( $module_id > 0 ) {
				if ( ! isset( $grouped['modules'][ $module_id ] ) ) {
					$grouped['modules'][ $module_id ] = array(
						'title'     => isset( $module_titles[ $module_id ] ) ? $module_titles[ $module_id ] : __( 'Module', 'cta-lms' ),
						'resources' => array(),
					);
				}
				$grouped['modules'][ $module_id ]['resources'][] = $resource;
			} else {
				$grouped['course'][] = $resource;
			}
		}

		return $grouped;
	}
}

}

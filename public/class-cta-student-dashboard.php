<?php
/**
 * CE student dashboard, course player, and certificates.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Student_Dashboard
 */
if ( ! class_exists( 'CTA_Student_Dashboard' ) ) {

class CTA_Student_Dashboard {

	/**
	 * Register shortcodes and AJAX handlers.
	 */
	public function __construct() {
		add_shortcode( 'cta_student_dashboard', array( $this, 'render_dashboard' ) );
		add_shortcode( 'cta_course_player', array( $this, 'render_player' ) );

		add_action( 'wp_ajax_cta_complete_module', array( $this, 'ajax_mark_module_complete' ) );
		add_action( 'wp_ajax_cta_download_cert', array( $this, 'ajax_download_certificate' ) );
		add_action( 'wp_ajax_cta_save_profile', array( $this, 'ajax_save_profile' ) );
		add_action( 'wp_ajax_cta_download_resource', array( $this, 'ajax_download_resource' ) );
		add_action( 'admin_post_cta_serve_resource', array( 'CTA_Course_Materials', 'handle_serve_request' ) );
		add_action( 'admin_post_nopriv_cta_serve_resource', array( 'CTA_Course_Materials', 'handle_serve_request' ) );
		add_action( 'admin_post_cta_print_certificate', array( 'CTA_Certificates', 'handle_print_request' ) );
		add_action( 'admin_post_nopriv_cta_print_certificate', array( 'CTA_Certificates', 'handle_print_request' ) );

		add_filter( 'body_class', array( $this, 'add_body_class' ) );
	}

	/**
	 * Add dashboard body class on student pages.
	 *
	 * @param array $classes Body classes.
	 * @return array
	 */
	public function add_body_class( $classes ) {
		if ( ! CTA_Loader::should_enqueue_assets() ) {
			return $classes;
		}

		$page_id = absint( get_option( 'cta_course_player_page_id', 0 ) );
		$dash_id = absint( get_option( 'cta_student_dashboard_page_id', 0 ) );

		if (
			( $page_id && is_page( $page_id ) )
			|| ( $dash_id && is_page( $dash_id ) )
		) {
			$classes[] = 'dashboard-page';
		}

		return $classes;
	}

	/**
	 * Render student dashboard shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_dashboard( $atts ) {
		$redirect = $this->check_student_access();

		if ( is_string( $redirect ) ) {
			return $redirect;
		}

		$user_id = get_current_user_id();

		if ( function_exists( 'cta_get_stripe' ) ) {
			$stripe = cta_get_stripe();

			if ( $stripe ) {
				$session_id = sanitize_text_field( wp_unslash( $_GET['session_id'] ?? '' ) );

				if ( $session_id ) {
					$stripe->finalize_checkout_session( $session_id, $user_id );
				}

				$stripe->maybe_finalize_user_pending_course_checkouts( $user_id );
				$stripe->maybe_sync_course_enrollments_from_payments( $user_id );
			}
		}

		$enrollments = CTA_Database::get_user_enrollments( $user_id );
		$in_progress = array();
		$completed   = array();
		$exam_prep   = array();
		$certificates = array();
		$total_ce    = 0.0;

		foreach ( $enrollments as $enrollment ) {
			$course = CTA_Database::get_course( (int) $enrollment->course_id );

			if ( ! $course ) {
				continue;
			}

			$modules       = CTA_Database::get_course_modules( (int) $course->id );
			$completed_ids = $this->parse_completed_modules( $enrollment->modules_completed );
			$total_modules = count( $modules );
			$next_module   = $this->get_next_module( $modules, $completed_ids );
			$certificate   = CTA_Database::get_enrollment_certificate( $user_id, (int) $enrollment->id );
			$is_exam       = class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course );
			$access        = $is_exam ? CTA_Exam_Access::get_access_record( $user_id, (int) $course->id ) : null;
			$has_access    = $is_exam ? CTA_Exam_Access::has_active_access( $user_id, (int) $course->id ) : true;
			$resources     = CTA_Database::get_downloadable_resources( (int) $course->id );
			$quiz          = CTA_Database::get_quiz_by_course( (int) $course->id );
			$quiz_url      = '';

			if ( $quiz ) {
				$quiz_page = CTA_Emails::get_page_url( 'cta_quiz_page_id' );
				if ( $quiz_page ) {
					$quiz_url = add_query_arg(
						array(
							'course_id' => (int) $course->id,
							'quiz_id'   => (int) $quiz->id,
						),
						$quiz_page
					);
				}
			}

			$item = (object) array(
				'enrollment'      => $enrollment,
				'course'          => $course,
				'modules'         => $modules,
				'completed_ids'   => $completed_ids,
				'total_modules'   => $total_modules,
				'completed_count' => count( $completed_ids ),
				'next_module_id'  => $next_module ? (int) $next_module->id : 0,
				'certificate'     => $is_exam ? null : $certificate,
				'player_url'      => ( $is_exam && ! $has_access ) ? '' : $this->get_player_url( (int) $course->id, $next_module ? (int) $next_module->id : 0 ),
				'is_exam_prep'    => $is_exam,
				'has_active_access' => $has_access,
				'access'          => $access,
				'expires_at'      => $access && ! empty( $access->expires_at ) ? $access->expires_at : null,
				'resources'       => $resources,
				'quiz_url'        => ( $is_exam && ! $has_access ) ? '' : $quiz_url,
			);

			if ( $is_exam ) {
				$exam_prep[] = $item;
				continue;
			}

			if ( 'completed' === $enrollment->status ) {
				$completed[] = $item;
				$total_ce   += (float) $course->ce_hours;
				if ( $certificate ) {
					$certificates[] = (object) array(
						'course'      => $course,
						'enrollment'  => $enrollment,
						'certificate' => $certificate,
					);
				}
			} elseif ( 'active' === $enrollment->status && (int) $enrollment->progress < 100 ) {
				$in_progress[] = $item;
			} elseif ( 'active' === $enrollment->status ) {
				$in_progress[] = $item;
			}
		}

		$user           = wp_get_current_user();
		$dashboard      = $this;
		$dashboard_url  = $this->get_dashboard_url();
		$courses_url    = $this->get_courses_url();
		$login_url      = $this->get_login_url();
		$logout_url     = wp_logout_url( $dashboard_url ? $dashboard_url : home_url( '/' ) );
		$home_url       = home_url( '/' );
		$dashboard_user = $this->get_dashboard_user_data( $user );
		$is_associate   = ! empty( $dashboard_user['isAssociate'] );
		$supervision_dashboard_url = $is_associate ? $this->get_supervision_dashboard_url() : '';

		ob_start();
		include CTA_PLUGIN_DIR . 'templates/dashboard-ce.php';
		return ob_get_clean();
	}

	/**
	 * Render course player shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_player( $atts ) {
		$redirect = $this->check_student_access();

		if ( is_string( $redirect ) ) {
			return $redirect;
		}

		$course_id  = absint( wp_unslash( $_GET['course_id'] ?? 0 ) );
		$module_id  = absint( wp_unslash( $_GET['module_id'] ?? 0 ) );
		$user_id    = get_current_user_id();
		$enrollment = CTA_Database::get_user_enrollment( $user_id, $course_id );
		$course     = CTA_Database::get_course( $course_id );

		if ( ! $course ) {
			return '<div class="cta-empty-state"><p>' . esc_html__( 'Course not found.', 'cta-lms' ) . '</p></div>';
		}

		if ( ! $enrollment ) {
			$single_course_url = CTA_Emails::get_page_url( 'cta_single_course_page_id' );
			if ( $single_course_url && $course_id ) {
				$single_course_url = add_query_arg( 'course_id', $course_id, $single_course_url );
			}

			ob_start();
			?>
			<div class="cta-plugin-wrapper">
				<div class="cta-empty-state cta-empty-state--locked">
					<h2><?php esc_html_e( 'Course locked', 'cta-lms' ); ?></h2>
					<p><?php esc_html_e( 'You need to enroll in this course before you can access lessons and modules.', 'cta-lms' ); ?></p>
					<?php if ( $single_course_url ) : ?>
						<a href="<?php echo esc_url( $single_course_url ); ?>" class="btn btn-primary"><?php esc_html_e( 'View Course & Enroll', 'cta-lms' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		// Exam prep: expiration gates content access; enrollment/progress remain.
		if ( class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course ) ) {
			if ( ! CTA_Exam_Access::has_active_access( $user_id, $course_id ) ) {
				$access = CTA_Exam_Access::get_access_record( $user_id, $course_id );
				ob_start();
				?>
				<div class="cta-plugin-wrapper">
					<div class="cta-empty-state cta-empty-state--locked">
						<h2><?php esc_html_e( 'Access expired', 'cta-lms' ); ?></h2>
						<p>
							<?php
							if ( $access && ! empty( $access->expires_at ) ) {
								printf(
									/* translators: %s: formatted expiration date */
									esc_html__( 'Your access to this Exam Preparation Program expired on %s. Your progress has been preserved. Contact an administrator if you need an extension.', 'cta-lms' ),
									esc_html( cta_lms_format_local_date( $access->expires_at, 'F j, Y' ) )
								);
							} else {
								esc_html_e( 'Your access to this Exam Preparation Program has expired. Your progress has been preserved.', 'cta-lms' );
							}
							?>
						</p>
						<?php if ( $this->get_dashboard_url() ) : ?>
							<a href="<?php echo esc_url( $this->get_dashboard_url() ); ?>" class="btn btn-primary"><?php esc_html_e( 'Back to Dashboard', 'cta-lms' ); ?></a>
						<?php endif; ?>
					</div>
				</div>
				<?php
				return ob_get_clean();
			}
		}

		if ( 'completed' === $enrollment->status && ! ( class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course ) ) ) {
			$dashboard_url = $this->get_dashboard_url();
			ob_start();
			?>
			<div class="cta-plugin-wrapper">
				<div class="cta-empty-state">
					<h2><?php esc_html_e( 'Course completed', 'cta-lms' ); ?></h2>
					<p><?php esc_html_e( 'You have already finished this course. View your certificate from the dashboard.', 'cta-lms' ); ?></p>
					<?php if ( $dashboard_url ) : ?>
						<a href="<?php echo esc_url( $dashboard_url ); ?>" class="btn btn-primary"><?php esc_html_e( 'Go to Dashboard', 'cta-lms' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		$modules       = CTA_Database::get_course_modules( $course_id );
		$completed_ids = $this->parse_completed_modules( $enrollment->modules_completed );

		if ( empty( $modules ) ) {
			$dashboard_url = $this->get_dashboard_url();
			ob_start();
			?>
			<div class="cta-plugin-wrapper">
				<div class="cta-empty-state cta-empty-state--coming-soon">
					<h2><?php echo esc_html( $course->title ); ?></h2>
					<p><?php esc_html_e( 'Course content is coming soon. The lessons for this course are still being prepared — please check back shortly.', 'cta-lms' ); ?></p>
					<?php if ( $dashboard_url ) : ?>
						<a href="<?php echo esc_url( $dashboard_url ); ?>" class="btn btn-primary"><?php esc_html_e( 'Back to My Courses', 'cta-lms' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		if ( ! $module_id ) {
			$next_module = $this->get_next_module( $modules, $completed_ids );
			$module_id   = $next_module ? (int) $next_module->id : (int) $modules[0]->id;
		}

		$module = null;

		foreach ( $modules as $mod ) {
			if ( (int) $mod->id === $module_id ) {
				$module = $mod;
				break;
			}
		}

		if ( ! $module ) {
			$module    = $modules[0];
			$module_id = (int) $module->id;
		}

		if ( ! $this->is_module_accessible( $modules, $completed_ids, $module_id ) ) {
			$accessible = $this->get_next_module( $modules, $completed_ids );
			$module     = $accessible ? $accessible : $modules[0];
			$module_id  = (int) $module->id;
		}

		$module_index   = $this->get_module_index( $modules, $module_id );
		$prev_module    = $module_index > 0 ? $modules[ $module_index - 1 ] : null;
		$next_module    = ( $module_index >= 0 && $module_index < count( $modules ) - 1 ) ? $modules[ $module_index + 1 ] : null;
		$progress       = (int) $enrollment->progress;
		$quiz_unlocked  = count( $completed_ids ) >= count( $modules ) && count( $modules ) > 0;
		$quiz_url       = $this->get_quiz_url( $course_id );
		$quiz_page_id   = absint( get_option( 'cta_quiz_page_id', 0 ) );

		// A quiz only counts as "available" when it exists AND has questions,
		// otherwise the course is still being finalized (show "coming soon").
		$course_quiz     = CTA_Database::get_quiz_by_course( $course_id );
		$quiz_questions  = $course_quiz ? CTA_Database::get_quiz_questions( (int) $course_quiz->id ) : array();
		$quiz_available  = $course_quiz && ! empty( $quiz_questions );
		$dashboard_url  = $this->get_dashboard_url();
		$player_base    = $this->get_player_page_url();
		$user           = wp_get_current_user();
		$logout_url     = wp_logout_url( $dashboard_url ? $dashboard_url : home_url( '/' ) );
		$home_url       = home_url( '/' );
		$dashboard_user = $this->get_dashboard_user_data( $user );
		$video_markup   = $this->get_module_video_markup( $module, $course );
		$module_complete = in_array( (int) $module->id, $completed_ids, true );
		$dashboard      = $this;
		$resources      = CTA_Database::get_downloadable_resources( $course_id );
		$is_exam_prep   = class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course );

		ob_start();
		include CTA_PLUGIN_DIR . 'templates/dashboard-ce-player.php';
		return ob_get_clean();
	}

	/**
	 * AJAX: mark a module complete.
	 */
	public function ajax_mark_module_complete() {
		check_ajax_referer( 'cta_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please log in to continue.', 'cta-lms' ),
				)
			);
		}

		$user_id    = get_current_user_id();
		$course_id  = absint( wp_unslash( $_POST['course_id'] ?? 0 ) );
		$module_id  = absint( wp_unslash( $_POST['module_id'] ?? 0 ) );
		$enrollment = CTA_Database::get_user_enrollment( $user_id, $course_id );

		if ( ! $enrollment || 'completed' === $enrollment->status ) {
			wp_send_json_error(
				array(
					'message' => __( 'Enrollment not found.', 'cta-lms' ),
				)
			);
		}

		$course = CTA_Database::get_course( $course_id );
		if ( class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course ) ) {
			if ( ! CTA_Exam_Access::has_active_access( $user_id, $course_id ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'Your access to this Exam Preparation Program has expired.', 'cta-lms' ),
					)
				);
			}
		}

		$modules = CTA_Database::get_course_modules( $course_id );
		$module  = null;

		foreach ( $modules as $mod ) {
			if ( (int) $mod->id === $module_id ) {
				$module = $mod;
				break;
			}
		}

		if ( ! $module ) {
			wp_send_json_error(
				array(
					'message' => __( 'Module not found.', 'cta-lms' ),
				)
			);
		}

		$completed_ids = $this->parse_completed_modules( $enrollment->modules_completed );

		if ( ! $this->is_module_accessible( $modules, $completed_ids, $module_id ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Complete previous modules first.', 'cta-lms' ),
				)
			);
		}

		if ( ! in_array( $module_id, $completed_ids, true ) ) {
			$completed_ids[] = $module_id;
		}

		$total_modules = count( $modules );
		$progress      = $total_modules > 0
			? (int) round( ( count( $completed_ids ) / $total_modules ) * 100 )
			: 0;

		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'cta_enrollments',
			array(
				'progress'           => $progress,
				'modules_completed'  => wp_json_encode( array_values( array_unique( $completed_ids ) ) ),
			),
			array( 'id' => (int) $enrollment->id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		$next_module    = $this->get_next_module( $modules, $completed_ids );
		$next_module_id = $next_module ? (int) $next_module->id : 0;
		$next_url       = $next_module_id
			? $this->get_player_url( $course_id, $next_module_id )
			: '';

		wp_send_json_success(
			array(
				'message'          => __( 'Module marked complete.', 'cta-lms' ),
				'progress'         => $progress,
				'completed_count'  => count( $completed_ids ),
				'total_modules'    => $total_modules,
				'module_id'        => $module_id,
				'quiz_unlocked'    => $progress >= 100,
				'next_module_id'   => $next_module_id,
				'next_module_url'  => $next_url,
			)
		);
	}

	/**
	 * AJAX: gated download for course resources (enrollment or active exam access required).
	 */
	public function ajax_download_resource() {
		check_ajax_referer( 'cta_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to download this file.', 'cta-lms' ) ) );
		}

		$resource_id = absint( wp_unslash( $_POST['resource_id'] ?? 0 ) );
		$user_id     = get_current_user_id();
		$resource    = CTA_Database::get_downloadable_resource( $resource_id );

		if ( ! $resource ) {
			wp_send_json_error( array( 'message' => __( 'Resource not found.', 'cta-lms' ) ) );
		}

		if ( ! class_exists( 'CTA_Course_Materials' ) || ! CTA_Course_Materials::user_can_access( $user_id, $resource ) ) {
			wp_send_json_error( array( 'message' => __( 'You must be enrolled to download this file.', 'cta-lms' ) ) );
		}

		wp_send_json_success(
			array(
				'download_url' => CTA_Course_Materials::get_serve_url( $resource_id ),
				'title'        => $resource->title,
			)
		);
	}

	/**
	 * AJAX: download certificate.
	 */
	public function ajax_download_certificate() {
		check_ajax_referer( 'cta_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please log in to download your certificate.', 'cta-lms' ),
				)
			);
		}

		$certificate_id = absint( wp_unslash( $_POST['certificate_id'] ?? 0 ) );
		$user_id        = get_current_user_id();
		$certificate    = CTA_Database::get_certificate( $certificate_id );

		if ( ! $certificate || (int) $certificate->user_id !== $user_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'Certificate not found.', 'cta-lms' ),
				)
			);
		}

		// Rebuild HTML with the student's current license number + logo before download.
		CTA_Certificates::refresh_file( $certificate );
		$certificate = CTA_Database::get_certificate( $certificate_id );

		$print_url    = CTA_Certificates::get_print_url( (int) $certificate->id, true );
		$download_url = CTA_Certificates::get_download_url( (int) $certificate->id );

		if ( empty( $print_url ) && empty( $download_url ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Certificate file is unavailable.', 'cta-lms' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'print_url'          => $print_url,
				'download_url'       => $download_url ? $download_url : $print_url,
				'certificate_number' => $certificate->certificate_number,
			)
		);
	}

	/**
	 * AJAX: save dashboard profile settings.
	 */
	public function ajax_save_profile() {
		check_ajax_referer( 'cta_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to continue.', 'cta-lms' ) ) );
		}

		$user_id       = get_current_user_id();
		$user          = wp_get_current_user();
		$roles         = (array) $user->roles;
		$is_associate  = in_array( 'cta_associate', $roles, true );
		$full_name     = sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) );
		$license_number = cta_lms_sanitize_license_number( wp_unslash( $_POST['license_number'] ?? '' ) );
		$license_type   = sanitize_text_field( wp_unslash( $_POST['license_type'] ?? '' ) );
		$associate_number = sanitize_text_field( wp_unslash( $_POST['associate_number'] ?? '' ) );
		$allowed_types  = cta_lms_get_license_types();

		if ( '' === $full_name ) {
			wp_send_json_error( array( 'message' => __( 'Full name is required.', 'cta-lms' ) ) );
		}

		// License is required for licensed professionals; associates use associate number.
		if ( ! $is_associate ) {
			if ( '' === $license_number ) {
				wp_send_json_error( array( 'message' => __( 'License number is required.', 'cta-lms' ) ) );
			}

			if ( ! cta_lms_is_valid_license_number( $license_number ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'License number looks invalid. Include at least one letter or number (formats vary by license type).', 'cta-lms' ),
					)
				);
			}
		}

		wp_update_user(
			array(
				'ID'           => $user_id,
				'display_name' => $full_name,
			)
		);

		if ( function_exists( 'cta_lms_sync_user_name_parts' ) ) {
			cta_lms_sync_user_name_parts( $user_id, $full_name );
		}

		if ( $is_associate ) {
			update_user_meta( $user_id, 'cta_associate_number', $associate_number );
		} else {
			update_user_meta( $user_id, 'cta_license_number', $license_number );

			if ( $license_type && in_array( $license_type, $allowed_types, true ) ) {
				update_user_meta( $user_id, 'cta_license_type', $license_type );
			}

			// Keep issued certificate HTML in sync with the updated license number.
			if ( class_exists( 'CTA_Certificates' ) ) {
				CTA_Certificates::refresh_user_certificates( $user_id );
			}
		}

		$display_name = function_exists( 'cta_lms_get_user_legal_name' )
			? cta_lms_get_user_legal_name( $user_id )
			: $full_name;

		wp_send_json_success(
			array(
				'message'          => __( 'Your changes have been saved successfully.', 'cta-lms' ),
				'displayName'      => $display_name ? $display_name : $full_name,
				'licenseNumber'    => $license_number,
				'associateNumber'  => $associate_number,
				'initials'         => $this->get_name_initials( $display_name ? $display_name : $full_name ),
			)
		);
	}

	/**
	 * Build initials from a display name.
	 *
	 * @param string $name Full name.
	 * @return string
	 */
	private function get_name_initials( $name ) {
		$parts    = preg_split( '/\s+/', trim( (string) $name ) );
		$initials = '';

		if ( ! empty( $parts[0] ) ) {
			$initials .= strtoupper( substr( $parts[0], 0, 1 ) );
		}
		if ( count( $parts ) > 1 && ! empty( $parts[ count( $parts ) - 1 ] ) ) {
			$initials .= strtoupper( substr( $parts[ count( $parts ) - 1 ], 0, 1 ) );
		}

		return $initials ? $initials : '--';
	}

	/**
	 * Finalize course completion after quiz pass AND evaluation submission.
	 *
	 * @param int $enrollment_id Enrollment ID.
	 * @param int $user_id       WordPress user ID.
	 * @return bool
	 */
	public function finalize_course_completion( $enrollment_id, $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		global $wpdb;

		$enrollment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}cta_enrollments WHERE id = %d AND user_id = %d",
				$enrollment_id,
				$user_id
			)
		);

		if ( ! $enrollment || 'completed' === $enrollment->status ) {
			return false;
		}

		$evaluation = CTA_Database::get_course_evaluation( $user_id, (int) $enrollment->course_id );

		if ( ! $evaluation ) {
			return false;
		}

		return $this->complete_course( $enrollment, $user_id );
	}

	/**
	 * Mark enrollment complete and issue certificate.
	 *
	 * @param object $enrollment Enrollment row.
	 * @param int    $user_id    WordPress user ID.
	 * @return bool
	 */
	private function complete_course( $enrollment, $user_id ) {
		$certificate = CTA_Certificates::generate( $user_id, (int) $enrollment->course_id );

		return (bool) $certificate;
	}

	/**
	 * Parse modules_completed JSON into integer IDs.
	 *
	 * @param string|null $json Stored JSON.
	 * @return array
	 */
	public function parse_completed_modules( $json ) {
		$decoded = json_decode( (string) $json, true );

		if ( ! is_array( $decoded ) ) {
			return array();
		}

		return array_values(
			array_unique(
				array_map(
					'intval',
					array_filter( $decoded )
				)
			)
		);
	}

	/**
	 * Get first incomplete module.
	 *
	 * @param array $modules       Course modules.
	 * @param array $completed_ids Completed module IDs.
	 * @return object|null
	 */
	public function get_next_module( $modules, $completed_ids ) {
		foreach ( $modules as $module ) {
			if ( ! in_array( (int) $module->id, $completed_ids, true ) ) {
				return $module;
			}
		}

		return null;
	}

	/**
	 * Determine whether a module can be accessed.
	 *
	 * @param array $modules       Course modules.
	 * @param array $completed_ids Completed module IDs.
	 * @param int   $module_id     Module ID.
	 * @return bool
	 */
	public function is_module_accessible( $modules, $completed_ids, $module_id ) {
		$index = $this->get_module_index( $modules, $module_id );

		if ( $index < 0 ) {
			return false;
		}

		if ( 0 === $index ) {
			return true;
		}

		$previous_id = (int) $modules[ $index - 1 ]->id;

		return in_array( $previous_id, $completed_ids, true );
	}

	/**
	 * Get module position in ordered list.
	 *
	 * @param array $modules   Course modules.
	 * @param int   $module_id Module ID.
	 * @return int
	 */
	public function get_module_index( $modules, $module_id ) {
		foreach ( $modules as $index => $module ) {
			if ( (int) $module->id === (int) $module_id ) {
				return $index;
			}
		}

		return -1;
	}

	/**
	 * Build course player URL with query args.
	 *
	 * @param int $course_id Course ID.
	 * @param int $module_id Module ID.
	 * @return string
	 */
	public function get_player_url( $course_id, $module_id = 0 ) {
		$base = $this->get_player_page_url();

		if ( ! $base ) {
			return '';
		}

		$args = array( 'course_id' => $course_id );

		if ( $module_id ) {
			$args['module_id'] = $module_id;
		}

		return add_query_arg( $args, $base );
	}

	/**
	 * Check student dashboard / course player access and redirect if needed.
	 *
	 * Registered Associates may use CE and Exam Prep freely. Supervision
	 * application status must not redirect them away from this dashboard.
	 *
	 * @return string|null Redirect markup or null if access granted.
	 */
	private function check_student_access() {
		if ( ! is_user_logged_in() ) {
			return $this->redirect_markup( $this->get_login_url() );
		}

		$user  = wp_get_current_user();
		$roles = (array) $user->roles;

		if (
			in_array( 'cta_licensed_professional', $roles, true )
			|| in_array( 'cta_associate', $roles, true )
			|| in_array( 'administrator', $roles, true )
		) {
			if (
				class_exists( 'CTA_Associate_Access' )
				&& in_array( 'cta_associate', $roles, true )
			) {
				CTA_Associate_Access::heal_decoupled_statuses( (int) $user->ID );
			}

			if (
				class_exists( 'CTA_Associate_Access' )
				&& ! CTA_Associate_Access::can_access_ce_and_exam_prep( (int) $user->ID )
			) {
				return $this->redirect_markup( home_url( '/' ) );
			}

			return null;
		}

		return $this->redirect_markup( home_url( '/' ) );
	}

	/**
	 * Output redirect markup when headers already sent.
	 *
	 * @param string $url Target URL.
	 * @return string
	 */
	private function redirect_markup( $url ) {
		if ( ! headers_sent() ) {
			wp_safe_redirect( $url );
			exit;
		}

		return '<script>window.location.replace(' . wp_json_encode( esc_url_raw( $url ) ) . ');</script>';
	}

	/**
	 * Dashboard user display data.
	 *
	 * @param WP_User $user WordPress user.
	 * @return array
	 */
	private function get_dashboard_user_data( $user ) {
		$is_associate = in_array( 'cta_associate', (array) $user->roles, true );
		$license      = cta_lms_get_user_license_number( $user->ID );
		$associate    = (string) get_user_meta( $user->ID, 'cta_associate_number', true );
		$name         = function_exists( 'cta_lms_get_user_legal_name' )
			? cta_lms_get_user_legal_name( $user->ID )
			: ( $user->display_name ? $user->display_name : $user->user_login );
		$parts    = preg_split( '/\s+/', trim( $name ) );
		$initials = '';

		if ( ! empty( $parts[0] ) ) {
			$initials .= strtoupper( substr( $parts[0], 0, 1 ) );
		}
		if ( count( $parts ) > 1 && ! empty( $parts[ count( $parts ) - 1 ] ) ) {
			$initials .= strtoupper( substr( $parts[ count( $parts ) - 1 ], 0, 1 ) );
		}

		if ( $is_associate ) {
			$subtitle = $associate ? $associate : __( 'Registered Associate', 'cta-lms' );
		} else {
			$subtitle = $license ? $license : __( 'Licensed Professional', 'cta-lms' );
		}

		return array(
			'displayName'     => $name,
			'email'           => $user->user_email,
			'licenseNumber'   => $subtitle,
			'associateNumber' => $associate,
			'isAssociate'     => $is_associate,
			'initials'        => $initials ? $initials : '--',
		);
	}

	/**
	 * Build video embed markup for a module.
	 *
	 * @param object $module Module row.
	 * @param object $course Course row.
	 * @return string
	 */
	public function get_module_video_markup( $module, $course ) {
		$video_url = (string) $module->video_url;

		if ( preg_match( '/^\d+$/', trim( $video_url ) ) ) {
			$video_url = 'https://vimeo.com/' . trim( $video_url );
		}

		if ( empty( $video_url ) && ! empty( $course->video_url ) ) {
			$video_url = (string) $course->video_url;
		}

		if ( empty( $video_url ) && ! empty( $course->vimeo_id ) ) {
			$video_url = 'https://vimeo.com/' . preg_replace( '/\D/', '', (string) $course->vimeo_id );
		}

		if ( empty( $video_url ) ) {
			return '<div class="course-player__video course-player__video--placeholder"><p>' . esc_html__( 'Video coming soon', 'cta-lms' ) . '</p></div>';
		}

		$youtube_id = $this->extract_youtube_id( $video_url );
		if ( $youtube_id ) {
			return sprintf(
				'<div class="course-player__video-wrap"><iframe class="course-player__iframe" src="https://www.youtube.com/embed/%1$s" title="%2$s" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div>',
				esc_attr( $youtube_id ),
				esc_attr( $module->title )
			);
		}

		if ( false !== strpos( $video_url, 'vimeo.com' ) ) {
			$vimeo_id = '';

			if ( preg_match( '/vimeo\.com\/(?:video\/)?(\d+)/', $video_url, $matches ) ) {
				$vimeo_id = $matches[1];
			}

			if ( $vimeo_id ) {
				return sprintf(
					'<div class="course-player__video-wrap"><iframe class="course-player__iframe" src="https://player.vimeo.com/video/%1$s" title="%2$s" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe></div>',
					esc_attr( $vimeo_id ),
					esc_attr( $module->title )
				);
			}
		}

		return sprintf(
			'<div class="course-player__video-wrap"><video class="course-player__html5-video" controls playsinline src="%1$s"></video></div>',
			esc_url( $video_url )
		);
	}

	/**
	 * Extract a YouTube video ID from common URL formats.
	 *
	 * @param string $url Video URL.
	 * @return string
	 */
	private function extract_youtube_id( $url ) {
		if ( preg_match( '/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]{6,})/', $url, $matches ) ) {
			return $matches[1];
		}

		return '';
	}

	/**
	 * Get login page URL.
	 *
	 * @return string
	 */
	private function get_login_url() {
		$page_id = absint( get_option( 'cta_login_page_id', 0 ) );

		if ( $page_id ) {
			$url = get_permalink( $page_id );
			if ( $url ) {
				return $url;
			}
		}

		return wp_login_url( get_permalink() );
	}

	/**
	 * Get student dashboard page URL.
	 *
	 * @return string
	 */
	private function get_dashboard_url() {
		$page_id = absint( get_option( 'cta_student_dashboard_page_id', 0 ) );

		if ( ! $page_id ) {
			return '';
		}

		$url = get_permalink( $page_id );

		return $url ? $url : '';
	}

	/**
	 * Get course player page URL.
	 *
	 * @return string
	 */
	private function get_player_page_url() {
		$page_id = absint( get_option( 'cta_course_player_page_id', 0 ) );

		if ( ! $page_id ) {
			return '';
		}

		$url = get_permalink( $page_id );

		return $url ? $url : '';
	}

	/**
	 * Get supervision dashboard URL.
	 *
	 * @return string
	 */
	private function get_supervision_dashboard_url() {
		$page_id = absint( get_option( 'cta_supervision_dashboard_page_id', 0 ) );

		if ( ! $page_id ) {
			return '';
		}

		$url = get_permalink( $page_id );

		return $url ? $url : '';
	}

	/**
	 * Get courses catalog URL.
	 *
	 * @return string
	 */
	private function get_courses_url() {
		$page_id       = absint( get_option( 'cta_courses_page_id', 0 ) );
		$login_page_id = absint( get_option( 'cta_login_page_id', 0 ) );

		// Never send users to the login page from a "browse courses" CTA.
		if ( $page_id && $login_page_id && (int) $page_id === (int) $login_page_id ) {
			$page_id = 0;
		}

		if ( ! $page_id && function_exists( 'cta_lms_find_page_id_by_shortcode' ) ) {
			$page_id = cta_lms_find_page_id_by_shortcode( 'cta_course_catalog' );
		}

		if ( $page_id && ( ! $login_page_id || (int) $page_id !== (int) $login_page_id ) ) {
			$url = get_permalink( $page_id );

			if ( $url ) {
				return $url;
			}
		}

		return home_url( '/' );
	}

	/**
	 * Get quiz page URL for a course.
	 *
	 * @param int $course_id Course ID.
	 * @return string
	 */
	private function get_quiz_url( $course_id ) {
		$page_id = absint( get_option( 'cta_quiz_page_id', 0 ) );

		if ( ! $page_id ) {
			return '#';
		}

		return add_query_arg( 'course_id', $course_id, get_permalink( $page_id ) );
	}
}
}
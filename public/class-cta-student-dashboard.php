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
		add_action( 'wp_ajax_cta_complete_form_a_remediation', array( $this, 'ajax_mark_form_a_remediation_complete' ) );
		add_action( 'wp_ajax_cta_mark_preserved_attempt', array( $this, 'ajax_mark_preserved_attempt' ) );
		add_action( 'wp_ajax_cta_download_cert', array( $this, 'ajax_download_certificate' ) );
		add_action( 'wp_ajax_cta_save_profile', array( $this, 'ajax_save_profile' ) );
		add_action( 'wp_ajax_cta_download_resource', array( $this, 'ajax_download_resource' ) );
		add_action( 'admin_post_cta_serve_resource', array( 'CTA_Course_Materials', 'handle_serve_request' ) );
		add_action( 'admin_post_nopriv_cta_serve_resource', array( 'CTA_Course_Materials', 'handle_serve_request' ) );
		// Legacy admin-post hooks kept for old emailed links; new URLs use the frontend route.
		add_action( 'admin_post_cta_print_certificate', array( 'CTA_Certificates', 'handle_print_request' ) );
		add_action( 'admin_post_nopriv_cta_print_certificate', array( 'CTA_Certificates', 'handle_print_request' ) );
		add_action( 'init', array( 'CTA_Certificates', 'maybe_handle_frontend_request' ), 5 );

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
			if ( $is_exam ) {
				$has_access = CTA_Exam_Access::has_active_access( $user_id, (int) $course->id );
			} elseif ( class_exists( 'CTA_CE_Access' ) ) {
				$has_access = CTA_CE_Access::has_active_access( $user_id, (int) $course->id );
			} else {
				$has_access = in_array( (string) $enrollment->status, array( 'active', 'completed' ), true );
			}
			$resources     = CTA_Database::get_downloadable_resources( (int) $course->id );
			if ( class_exists( 'CTA_Course_Materials' ) ) {
				$resources = CTA_Course_Materials::filter_student_visible_resources( $resources );
			}
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
				'player_url'      => ( ! $has_access ) ? '' : ( $is_exam ? $this->get_player_home_url( (int) $course->id ) : $this->get_player_url( (int) $course->id, $next_module ? (int) $next_module->id : 0 ) ),
				'is_exam_prep'    => $is_exam,
				'has_active_access' => $has_access,
				'access'          => $access,
				'expires_at'      => $access && ! empty( $access->expires_at ) ? $access->expires_at : ( ! empty( $enrollment->expires_at ) ? $enrollment->expires_at : null ),
				'resources'       => $resources,
				'quiz_url'        => ( ! $has_access ) ? '' : $quiz_url,
			);

			if ( $is_exam ) {
				$exam_prep[] = $item;
				continue;
			}

			if ( 'completed' === $enrollment->status ) {
				$completed[] = $item;
				$total_ce   += (float) $course->ce_hours;
			} elseif ( $has_access && 'active' === $enrollment->status ) {
				$in_progress[] = $item;
			}
		}

		// Certificates are permanent — list from certificate rows, not membership/enrollment status.
		$cert_rows = CTA_Database::get_user_certificates( $user_id );
		foreach ( (array) $cert_rows as $certificate ) {
			$course = CTA_Database::get_course( (int) $certificate->course_id );
			if ( ! $course ) {
				continue;
			}
			if ( class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course ) ) {
				continue;
			}
			$enrollment = CTA_Database::get_user_enrollment( $user_id, (int) $certificate->course_id );
			$certificates[] = (object) array(
				'course'      => $course,
				'enrollment'  => $enrollment,
				'certificate' => $certificate,
			);
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
		} elseif ( class_exists( 'CTA_CE_Access' ) && CTA_CE_Access::is_ce_course( $course ) && ! CTA_CE_Access::has_active_access( $user_id, $course_id ) ) {
			ob_start();
			?>
			<div class="cta-plugin-wrapper">
				<div class="cta-empty-state cta-empty-state--locked">
					<h2><?php esc_html_e( 'Membership access ended', 'cta-lms' ); ?></h2>
					<p><?php esc_html_e( 'Your membership access to this course is no longer active. Certificates you already earned remain available in My Certificates. Purchase this course individually for permanent access.', 'cta-lms' ); ?></p>
					<?php if ( $this->get_dashboard_url() ) : ?>
						<a href="<?php echo esc_url( $this->get_dashboard_url() ); ?>" class="btn btn-primary"><?php esc_html_e( 'Back to Dashboard', 'cta-lms' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
			<?php
			return ob_get_clean();
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
		if ( class_exists( 'CTA_Suicide_Risk_Module_Sync' ) ) {
			$sr_course = CTA_Suicide_Risk_Module_Sync::find_course();
			if ( $sr_course && (int) $sr_course->id === $course_id ) {
				CTA_Suicide_Risk_Module_Sync::ensure();
				if ( class_exists( 'CTA_Suicide_Risk_Toolkit_Sync' ) ) {
					CTA_Suicide_Risk_Toolkit_Sync::ensure();
				}
				if ( class_exists( 'CTA_Suicide_Risk_Certificate_Sync' ) ) {
					CTA_Suicide_Risk_Certificate_Sync::ensure();
				}
				$modules = CTA_Database::get_course_modules( $course_id );
			}
		}
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

		$view         = sanitize_key( wp_unslash( $_GET['view'] ?? '' ) );
		$is_exam_prep = class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course );

		$section_views = array( 'flashcards', 'exams', 'resources', 'downloads', 'audio', 'progress' );

		if ( $is_exam_prep && in_array( $view, $section_views, true ) ) {
			return $this->render_exam_prep_section( $course, $modules, $enrollment, $completed_ids, $view );
		}

		if ( $is_exam_prep && 'workbooks' === $view ) {
			return $this->render_exam_prep_workbooks_list( $course, $modules, $enrollment, $completed_ids );
		}

		if ( $is_exam_prep && ( 'home' === $view || ! $module_id ) ) {
			return $this->render_exam_prep_course_home( $course, $modules, $enrollment, $completed_ids );
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

		if ( ! $this->is_module_accessible( $modules, $completed_ids, $module_id, $course ) ) {
			$accessible = $this->get_next_module( $modules, $completed_ids );
			$module     = $accessible ? $accessible : $modules[0];
			$module_id  = (int) $module->id;
		}

		$module_index   = $this->get_module_index( $modules, $module_id );
		$prev_module    = $module_index > 0 ? $modules[ $module_index - 1 ] : null;
		$next_module    = ( $module_index >= 0 && $module_index < count( $modules ) - 1 ) ? $modules[ $module_index + 1 ] : null;
		if ( class_exists( 'CTA_CE_Completion' ) ) {
			$progress = CTA_CE_Completion::sync_progress( get_current_user_id(), $course_id, $enrollment );
		} else {
			$progress = (int) $enrollment->progress;
		}
		// Require every active module ID (incl. Capstone) — do not trust completed-ID count
		// alone, which can be inflated by archived/remapped legacy modules.
		$quiz_unlocked = class_exists( 'CTA_CE_Completion' )
			? CTA_CE_Completion::modules_complete( get_current_user_id(), $course_id, $enrollment )
			: ( class_exists( 'CTA_Certificates' ) && CTA_Certificates::user_completed_all_modules( get_current_user_id(), $course_id, $enrollment ) );
		$quiz_url       = $this->get_quiz_url( $course_id );
		$quiz_page_id   = absint( get_option( 'cta_quiz_page_id', 0 ) );

		// Self-heal CTA-CE-001 final exam when staging/live missed the 1.0.185 upgrade seed.
		if ( class_exists( 'CTA_Law_Ethics_Exam_Sync' ) ) {
			$law_ethics_course = CTA_Law_Ethics_Exam_Sync::find_course();
			if ( $law_ethics_course && (int) $law_ethics_course->id === (int) $course_id ) {
				try {
					CTA_Law_Ethics_Exam_Sync::ensure();
				} catch ( Throwable $e ) {
					if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						error_log( 'CTA Law Ethics exam ensure failed: ' . $e->getMessage() );
					}
				}
			}
		}

		// Self-heal CTA-CE-003 final exam when deploy missed the 1.0.212/1.0.213 seeds.
		if ( class_exists( 'CTA_Suicide_Risk_Exam_Sync' ) ) {
			$sr_course = CTA_Suicide_Risk_Exam_Sync::find_course();
			if ( $sr_course && (int) $sr_course->id === (int) $course_id ) {
				try {
					CTA_Suicide_Risk_Exam_Sync::ensure();
				} catch ( Throwable $e ) {
					if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						error_log( 'CTA Suicide Risk exam ensure failed: ' . $e->getMessage() );
					}
				}
			}
		}

		// Exam Prep can have multiple assessments; CE still uses the primary quiz.
		$course_quizzes = CTA_Database::get_quizzes_by_course( $course_id, true );
		$quiz_cards     = array();
		$user_id_player = get_current_user_id();
		foreach ( $course_quizzes as $qrow ) {
			$q_questions = CTA_Database::get_quiz_questions( (int) $qrow->id );
			if ( empty( $q_questions ) ) {
				continue;
			}
			$attempts = CTA_Database::get_user_quiz_attempts( $user_id_player, (int) $qrow->id );
			$active   = CTA_Database::get_active_quiz_attempt( $user_id_player, (int) $qrow->id );
			$best     = null;
			foreach ( $attempts as $att ) {
				if ( null === $best || (int) $att->score > (int) $best->score ) {
					$best = $att;
				}
			}
			// Form B is independent of Form A — no sequential Form A → Form B lock.
			$quiz_cards[] = array(
				'quiz'     => $qrow,
				'url'      => $this->get_quiz_url( $course_id, (int) $qrow->id ),
				'attempts' => $attempts,
				'active'   => $active,
				'best'     => $best,
				'passed'   => $best && (int) $best->passed,
				'locked'   => ! $quiz_unlocked && ! $active,
				'lock_msg' => ! $quiz_unlocked && ! $active
					? __( 'Complete all program workbooks before starting this assessment.', 'cta-lms' )
					: '',
			);
		}
		$quiz_available = ! empty( $quiz_cards );
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
		if ( class_exists( 'CTA_Course_Materials' ) ) {
			$resources = CTA_Course_Materials::filter_student_visible_resources( $resources );
		}
		$is_exam_prep   = class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course );

		if ( $is_exam_prep ) {
			$home_url      = $this->get_player_home_url( $course_id );
			$workbooks_url = class_exists( 'CTA_Exam_Prep_Workbooks' )
				? CTA_Exam_Prep_Workbooks::get_workbooks_list_url( $course_id, $player_base )
				: $home_url;
			$workbook_resource = class_exists( 'CTA_Exam_Prep_Lessons' )
				? CTA_Exam_Prep_Lessons::find_workbook_resource( $resources, $module )
				: null;
			$practice_bank_resource = class_exists( 'CTA_Exam_Prep_Workbooks' )
				? CTA_Exam_Prep_Workbooks::find_practice_bank_resource( $resources, $module )
				: null;
			$workbook_quiz_cards = class_exists( 'CTA_Exam_Prep_Workbooks' )
				? CTA_Exam_Prep_Workbooks::get_workbook_quiz_cards( $course, $module, $quiz_cards, $user_id_player )
				: array();
			$sidebar_nav = $this->get_exam_prep_sidebar_nav(
				$course,
				$modules,
				$completed_ids,
				array(
					'view'      => '',
					'module_id' => $module_id,
				)
			);

			ob_start();
			include CTA_PLUGIN_DIR . 'templates/exam-prep-workbook.php';
			return ob_get_clean();
		}

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
		} elseif ( class_exists( 'CTA_CE_Access' ) && CTA_CE_Access::is_ce_course( $course ) && ! CTA_CE_Access::has_active_access( $user_id, $course_id ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Your membership access to this course is no longer active.', 'cta-lms' ),
				)
			);
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

		if ( ! $this->is_module_accessible( $modules, $completed_ids, $module_id, $course ) ) {
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

		$is_exam_prep = class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course );
		$quiz_unlocked = class_exists( 'CTA_CE_Completion' )
			? CTA_CE_Completion::modules_complete( $user_id, $course_id, null )
			: ( $progress >= 100 );

		wp_send_json_success(
			array(
				'message'          => __( 'Module marked complete.', 'cta-lms' ),
				'progress'         => $progress,
				'completed_count'  => count( $completed_ids ),
				'total_modules'    => $total_modules,
				'module_id'        => $module_id,
				'quiz_unlocked'    => $quiz_unlocked,
				'is_exam_prep'     => $is_exam_prep,
				'next_module_id'   => $next_module_id,
				'next_module_url'  => $next_url,
			)
		);
	}

	/**
	 * AJAX: mark Form A Remediation Workbook complete (progress tracking; does not gate Form B).
	 */
	public function ajax_mark_form_a_remediation_complete() {
		check_ajax_referer( 'cta_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to continue.', 'cta-lms' ) ) );
		}

		$user_id   = get_current_user_id();
		$course_id = absint( wp_unslash( $_POST['course_id'] ?? 0 ) );
		$course    = $course_id ? CTA_Database::get_course( $course_id ) : null;
		$enrollment = CTA_Database::get_user_enrollment( $user_id, $course_id );

		if ( ! $enrollment || ! $course ) {
			wp_send_json_error( array( 'message' => __( 'Enrollment not found.', 'cta-lms' ) ) );
		}

		if ( class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course ) ) {
			if ( ! CTA_Exam_Access::has_active_access( $user_id, $course_id ) ) {
				wp_send_json_error(
					array( 'message' => __( 'Your access to this Exam Preparation Program has expired.', 'cta-lms' ) )
				);
			}
		}

		if ( ! class_exists( 'CTA_Course_Materials' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unable to update remediation status.', 'cta-lms' ) ) );
		}

		$result = CTA_Course_Materials::mark_form_a_remediation_complete( $user_id, $course_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'         => __( 'Form A Remediation marked complete.', 'cta-lms' ),
				'form_b_unlocked' => true,
			)
		);
	}

	/**
	 * AJAX: record a preserved first attempt for a printable candidate assessment (AMFTRB gates).
	 */
	public function ajax_mark_preserved_attempt() {
		check_ajax_referer( 'cta_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to continue.', 'cta-lms' ) ) );
		}

		$user_id     = get_current_user_id();
		$course_id   = absint( wp_unslash( $_POST['course_id'] ?? 0 ) );
		$unlock_type = sanitize_text_field( wp_unslash( $_POST['unlock_type'] ?? '' ) );
		$resource_id = absint( wp_unslash( $_POST['resource_id'] ?? 0 ) );
		$course      = $course_id ? CTA_Database::get_course( $course_id ) : null;
		$enrollment  = CTA_Database::get_user_enrollment( $user_id, $course_id );

		if ( ! $enrollment || ! $course ) {
			wp_send_json_error( array( 'message' => __( 'Enrollment not found.', 'cta-lms' ) ) );
		}

		if ( ! class_exists( 'CTA_Exam_Access' ) || ! CTA_Exam_Access::uses_assessment_gates( $course ) ) {
			wp_send_json_error( array( 'message' => __( 'Preserved attempts are not used for this program.', 'cta-lms' ) ) );
		}

		if ( ! CTA_Exam_Access::has_active_access( $user_id, $course_id ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Your access to this Exam Preparation Program has expired.', 'cta-lms' ) )
			);
		}

		if ( ! class_exists( 'CTA_Course_Materials' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unable to record attempt.', 'cta-lms' ) ) );
		}

		if ( $resource_id ) {
			$resource = CTA_Database::get_downloadable_resource( $resource_id );
			if ( ! $resource || (int) $resource->course_id !== $course_id ) {
				wp_send_json_error( array( 'message' => __( 'Assessment not found.', 'cta-lms' ) ) );
			}
			if ( ! CTA_Course_Materials::user_can_access( $user_id, $resource ) ) {
				wp_send_json_error( array( 'message' => __( 'You cannot record an attempt for this file yet.', 'cta-lms' ) ) );
			}
			$inferred = CTA_Course_Materials::infer_preserved_attempt_type( $resource );
			if ( '' === $inferred ) {
				wp_send_json_error( array( 'message' => __( 'This file is not a candidate assessment.', 'cta-lms' ) ) );
			}
			$unlock_type = $inferred;
		}

		$result = CTA_Course_Materials::mark_preserved_attempt( $user_id, $course_id, $unlock_type );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'     => __( 'Assessment attempt recorded. Matching answer keys and rationales are now available.', 'cta-lms' ),
				'unlock_type' => $unlock_type,
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

		$course = CTA_Database::get_course( (int) $enrollment->course_id );
		if ( class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course ) ) {
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
		$course = CTA_Database::get_course( (int) $enrollment->course_id );
		if ( class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course ) ) {
			return false;
		}

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
	 * Exam Preparation programs allow any module/workbook in any order once enrolled.
	 * CE courses keep sequential unlock: each module opens only after the previous is complete.
	 *
	 * @param array       $modules       Course modules.
	 * @param array       $completed_ids Completed module IDs.
	 * @param int         $module_id     Module ID.
	 * @param object|null $course        Optional course row (used to detect Exam Prep).
	 * @return bool
	 */
	public function is_module_accessible( $modules, $completed_ids, $module_id, $course = null ) {
		$index = $this->get_module_index( $modules, $module_id );

		if ( $index < 0 ) {
			return false;
		}

		if ( null === $course && ! empty( $modules[0]->course_id ) ) {
			$course = CTA_Database::get_course( (int) $modules[0]->course_id );
		}

		// Exam Prep: all workbooks/modules are open immediately (any order).
		if ( $course && class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course ) ) {
			return true;
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
	 * Build exam prep Course Home dashboard URL.
	 *
	 * @param int $course_id Course ID.
	 * @return string
	 */
	public function get_player_home_url( $course_id ) {
		$base = $this->get_player_page_url();

		if ( ! $base ) {
			return '';
		}

		return add_query_arg(
			array(
				'course_id' => $course_id,
				'view'      => 'home',
			),
			$base
		);
	}

	/**
	 * Build exam prep workbooks list URL.
	 *
	 * @param int $course_id Course ID.
	 * @return string
	 */
	public function get_player_workbooks_url( $course_id ) {
		$base = $this->get_player_page_url();

		if ( ! $base ) {
			return '';
		}

		return class_exists( 'CTA_Exam_Prep_Workbooks' )
			? CTA_Exam_Prep_Workbooks::get_workbooks_list_url( $course_id, $base )
			: add_query_arg(
				array(
					'course_id' => $course_id,
					'view'      => 'workbooks',
				),
				$base
			);
	}

	/**
	 * Build exam prep player URL for a named section view.
	 *
	 * @param int    $course_id Course ID.
	 * @param string $view      Section view key.
	 * @return string
	 */
	public function get_player_view_url( $course_id, $view ) {
		$base = $this->get_player_page_url();

		if ( ! $base ) {
			return '';
		}

		return add_query_arg(
			array(
				'course_id' => absint( $course_id ),
				'view'      => sanitize_key( (string) $view ),
			),
			$base
		);
	}

	/**
	 * Build sidebar navigation tree for an exam-prep course view.
	 *
	 * @param object $course        Course row.
	 * @param array  $modules       Module rows.
	 * @param array  $completed_ids Completed module IDs.
	 * @param array  $context       Optional page context overrides.
	 * @return array<string,mixed>
	 */
	public function get_exam_prep_sidebar_nav( $course, $modules, $completed_ids, array $context = array() ) {
		if ( ! class_exists( 'CTA_Exam_Prep_Sidebar_Nav' ) ) {
			return array();
		}

		if ( ! isset( $context['view'] ) ) {
			$context['view'] = sanitize_key( wp_unslash( $_GET['view'] ?? '' ) );
		}
		if ( ! isset( $context['module_id'] ) ) {
			$context['module_id'] = absint( $_GET['module_id'] ?? 0 );
		}
		if ( ! isset( $context['resource_id'] ) ) {
			$context['resource_id'] = absint( $_GET['resource_id'] ?? 0 );
		}
		if ( ! isset( $context['quiz_id'] ) ) {
			$context['quiz_id'] = absint( $_GET['quiz_id'] ?? 0 );
		}

		try {
			return CTA_Exam_Prep_Sidebar_Nav::build( $course, $modules, $completed_ids, $this, $context );
		} catch ( Throwable $e ) {
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'CTA exam prep sidebar nav build failed: ' . $e->getMessage() );
			}
			return array();
		}
	}

	/**
	 * Render exam prep workbooks overview list.
	 *
	 * @param object $course        Course row.
	 * @param array  $modules       Module rows.
	 * @param object $enrollment    Enrollment row.
	 * @param array  $completed_ids Completed module IDs.
	 * @return string
	 */
	private function render_exam_prep_workbooks_list( $course, $modules, $enrollment, $completed_ids ) {
		$course_id = (int) $course->id;

		if ( class_exists( 'CTA_CE_Completion' ) ) {
			$progress = CTA_CE_Completion::sync_progress( get_current_user_id(), $course_id, $enrollment );
		} else {
			$progress = (int) $enrollment->progress;
		}

		$dashboard_url  = $this->get_dashboard_url();
		$player_base    = $this->get_player_page_url();
		$home_url       = $this->get_player_home_url( $course_id );
		$active         = 'workbooks';
		$user           = wp_get_current_user();
		$dashboard_user = $this->get_dashboard_user_data( $user );
		$workbook_items = class_exists( 'CTA_Exam_Prep_Workbooks' )
			? CTA_Exam_Prep_Workbooks::get_workbook_list_items( $course, $modules, $completed_ids, $player_base )
			: array();
		$sidebar_nav    = $this->get_exam_prep_sidebar_nav(
			$course,
			$modules,
			$completed_ids,
			array(
				'view'      => 'workbooks',
				'module_id' => 0,
			)
		);

		ob_start();
		include CTA_PLUGIN_DIR . 'templates/exam-prep-workbooks-list.php';
		return ob_get_clean();
	}

	/**
	 * Render exam prep Course Home dashboard (Getting Started landing).
	 *
	 * @param object $course        Course row.
	 * @param array  $modules       Module rows.
	 * @param object $enrollment    Enrollment row.
	 * @param array  $completed_ids Completed module IDs.
	 * @return string
	 */
	private function render_exam_prep_course_home( $course, $modules, $enrollment, $completed_ids ) {
		$course_id = (int) $course->id;

		if ( class_exists( 'CTA_CE_Completion' ) ) {
			$progress = CTA_CE_Completion::sync_progress( get_current_user_id(), $course_id, $enrollment );
		} else {
			$progress = (int) $enrollment->progress;
		}

		$resources = CTA_Database::get_downloadable_resources( $course_id );
		if ( class_exists( 'CTA_Course_Materials' ) ) {
			$resources = CTA_Course_Materials::filter_student_visible_resources( $resources );
		}

		$getting_started = class_exists( 'CTA_Exam_Prep_Getting_Started' )
			? CTA_Exam_Prep_Getting_Started::get_config_for_course( $course, $resources )
			: array();

		$first_module       = ! empty( $modules[0] ) ? $modules[0] : null;
		$workbooks_list_url = $this->get_player_workbooks_url( $course_id );
		$first_workbook_url = $first_module
			? $this->get_player_url( $course_id, (int) $first_module->id )
			: $workbooks_list_url;
		$course_home_url    = $this->get_player_home_url( $course_id );

		$dashboard_url  = $this->get_dashboard_url();
		$player_base    = $this->get_player_page_url();
		$user           = wp_get_current_user();
		$logout_url     = wp_logout_url( $dashboard_url ? $dashboard_url : home_url( '/' ) );
		$home_url       = home_url( '/' );
		$dashboard_user = $this->get_dashboard_user_data( $user );
		$dashboard      = $this;
		$sidebar_nav    = $this->get_exam_prep_sidebar_nav(
			$course,
			$modules,
			$completed_ids,
			array(
				'view'      => 'home',
				'module_id' => 0,
			)
		);

		ob_start();
		include CTA_PLUGIN_DIR . 'templates/exam-prep-course-home.php';
		return ob_get_clean();
	}

	/**
	 * Render an exam prep section view (flashcards, exams, resources, etc.).
	 *
	 * @param object $course        Course row.
	 * @param array  $modules       Module rows.
	 * @param object $enrollment    Enrollment row.
	 * @param array  $completed_ids Completed module IDs.
	 * @param string $section_view  Section view key.
	 * @return string
	 */
	private function render_exam_prep_section( $course, $modules, $enrollment, $completed_ids, $section_view ) {
		$course_id    = (int) $course->id;
		$section_view = sanitize_key( (string) $section_view );

		if ( class_exists( 'CTA_CE_Completion' ) ) {
			$progress = CTA_CE_Completion::sync_progress( get_current_user_id(), $course_id, $enrollment );
		} else {
			$progress = (int) $enrollment->progress;
		}

		$resources = CTA_Database::get_downloadable_resources( $course_id );
		if ( class_exists( 'CTA_Course_Materials' ) ) {
			$resources = CTA_Course_Materials::filter_student_visible_resources( $resources );
		}

		$getting_started = class_exists( 'CTA_Exam_Prep_Getting_Started' )
			? CTA_Exam_Prep_Getting_Started::get_config_for_course( $course, $resources )
			: array();

		$context = array(
			'view'      => $section_view,
			'module_id' => 0,
		);
		$sidebar_nav = $this->get_exam_prep_sidebar_nav( $course, $modules, $completed_ids, $context );

		$section_data = array();

		if ( 'flashcards' === $section_view && class_exists( 'CTA_Exam_Prep_Flashcard_Center' ) ) {
			$section_data['flashcard_center_deck'] = CTA_Exam_Prep_Flashcard_Center::get_deck_for_course( $course );
		}

		if ( 'exams' === $section_view && class_exists( 'CTA_Exam_Prep_Exam_Center' ) ) {
			$section_data['exam_center_data'] = CTA_Exam_Prep_Exam_Center::get_center_data_for_course( $course, $this );
		}

		if ( 'progress' === $section_view && class_exists( 'CTA_Exam_Prep_Progress_Readiness' ) ) {
			$section_data['progress_readiness_data'] = CTA_Exam_Prep_Progress_Readiness::get_dashboard_data(
				$course,
				(array) $modules,
				(array) $completed_ids,
				$this
			);
		}

		if ( 'downloads' === $section_view && class_exists( 'CTA_Exam_Prep_Downloads' ) ) {
			$section_data['downloads_data'] = CTA_Exam_Prep_Downloads::get_center_data_for_course(
				$course,
				(array) $modules,
				$this
			);
		}

		if ( 'audio' === $section_view && class_exists( 'CTA_Exam_Prep_Audio_Review' ) ) {
			$section_data['audio_review_data'] = CTA_Exam_Prep_Audio_Review::get_center_data_for_course(
				$course,
				(array) $modules,
				$this
			);
		}

		if ( 'resources' === $section_view && ! empty( $sidebar_nav['sections'] ) ) {
			foreach ( (array) $sidebar_nav['sections'] as $nav_section ) {
				if ( (string) ( $nav_section['key'] ?? '' ) !== $section_view ) {
					continue;
				}
				$section_data['resource_items'] = isset( $nav_section['children'] ) ? (array) $nav_section['children'] : array();
				break;
			}
		}

		$dashboard_url      = $this->get_dashboard_url();
		$player_base        = $this->get_player_page_url();
		$course_home_url    = $this->get_player_home_url( $course_id );
		$workbooks_list_url = $this->get_player_workbooks_url( $course_id );
		$user               = wp_get_current_user();
		$dashboard_user     = $this->get_dashboard_user_data( $user );
		$dashboard          = $this;

		ob_start();
		include CTA_PLUGIN_DIR . 'templates/exam-prep-section.php';
		return ob_get_clean();
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
	 * Build responsive Vimeo iframe embed (padding wrapper + player API).
	 *
	 * Closed captions use the Vimeo player's built-in CC control when tracks
	 * are uploaded on the Vimeo asset.
	 *
	 * @param string $vimeo_id  Numeric Vimeo ID.
	 * @param string $title     Accessible iframe title.
	 * @param string $wrap_class CSS class for the outer wrap.
	 * @return string
	 */
	public static function get_vimeo_responsive_embed( $vimeo_id, $title = '', $wrap_class = 'course-player__video-wrap' ) {
		$vimeo_id = preg_replace( '/\D/', '', (string) $vimeo_id );
		if ( '' === $vimeo_id ) {
			return '';
		}

		$title = $title ? (string) $title : __( 'Course video', 'cta-lms' );
		$src   = sprintf(
			'https://player.vimeo.com/video/%1$s?badge=0&autopause=0&player_id=0&app_id=58479',
			rawurlencode( $vimeo_id )
		);

		return sprintf(
			'<div class="%1$s cta-vimeo-embed" style="padding:56.25%% 0 0 0;position:relative;">
				<iframe
					src="%2$s"
					frameborder="0"
					allow="autoplay; fullscreen; picture-in-picture; clipboard-write; encrypted-media; web-share"
					referrerpolicy="strict-origin-when-cross-origin"
					style="position:absolute;top:0;left:0;width:100%%;height:100%%;"
					title="%3$s"
					allowfullscreen
				></iframe>
			</div>',
			esc_attr( $wrap_class ),
			esc_url( $src ),
			esc_attr( $title )
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
		// Exam Prep programs ship written materials only at launch — never advertise video.
		if ( class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course ) ) {
			return '';
		}

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

		if ( empty( $video_url ) && ! empty( $course ) && class_exists( 'CTA_Suicide_Risk_Module_Sync' ) ) {
			$sr_course = CTA_Suicide_Risk_Module_Sync::find_course();
			if ( $sr_course && (int) $sr_course->id === (int) $course->id ) {
				$video_url = CTA_Suicide_Risk_Module_Sync::get_video_url_for_title( (string) $module->title );
			}
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

		if ( false !== strpos( $video_url, 'vimeo.com' ) || preg_match( '/^\d+$/', trim( $video_url ) ) ) {
			$vimeo_id = '';

			if ( preg_match( '/vimeo\.com\/(?:video\/)?(\d+)/', $video_url, $matches ) ) {
				$vimeo_id = $matches[1];
			} elseif ( preg_match( '/^\d+$/', trim( $video_url ) ) ) {
				$vimeo_id = trim( $video_url );
			}

			if ( $vimeo_id ) {
				return self::get_vimeo_responsive_embed( $vimeo_id, (string) $module->title, 'course-player__video-wrap' );
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
	public function get_dashboard_url() {
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
	public function get_player_page_url() {
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
	 * Get quiz page URL for a course (and optional specific assessment).
	 *
	 * @param int $course_id Course ID.
	 * @param int $quiz_id   Optional quiz/assessment ID.
	 * @return string
	 */
	public function get_quiz_url( $course_id, $quiz_id = 0 ) {
		$page_id = absint( get_option( 'cta_quiz_page_id', 0 ) );

		if ( ! $page_id ) {
			return '#';
		}

		$args = array( 'course_id' => absint( $course_id ) );
		if ( absint( $quiz_id ) ) {
			$args['quiz_id'] = absint( $quiz_id );
		}

		return add_query_arg( $args, get_permalink( $page_id ) );
	}
}
}
<?php
/**
 * Course quiz shortcode and AJAX handlers.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Quiz
 */
if ( ! class_exists( 'CTA_Quiz' ) ) {

class CTA_Quiz {

	/**
	 * Register shortcode and AJAX handlers.
	 */
	public function __construct() {
		add_shortcode( 'cta_quiz', array( $this, 'render_quiz' ) );

		add_action( 'wp_ajax_cta_start_quiz', array( $this, 'ajax_start_quiz' ) );
		add_action( 'wp_ajax_cta_save_quiz_progress', array( $this, 'ajax_save_quiz_progress' ) );
		add_action( 'wp_ajax_cta_submit_quiz', array( $this, 'ajax_submit_quiz' ) );
		add_action( 'wp_ajax_cta_submit_evaluation', array( $this, 'ajax_submit_evaluation' ) );
		add_action( 'wp_ajax_cta_submit_attestation', array( $this, 'ajax_submit_attestation' ) );

		add_filter( 'body_class', array( $this, 'add_body_class' ) );
	}

	/**
	 * Add quiz page body class.
	 *
	 * @param array $classes Body classes.
	 * @return array
	 */
	public function add_body_class( $classes ) {
		global $post;

		if ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'cta_quiz' ) ) {
			$classes[] = 'dashboard-page';
		}

		return $classes;
	}

	/**
	 * Render quiz shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_quiz( $atts ) {
		if ( ! is_user_logged_in() ) {
			return $this->redirect_markup( $this->get_login_url() );
		}

		$course_id = isset( $_GET['course_id'] ) ? absint( wp_unslash( $_GET['course_id'] ) ) : 0;
		$quiz_id   = isset( $_GET['quiz_id'] ) ? absint( wp_unslash( $_GET['quiz_id'] ) ) : 0;

		if ( ! $course_id && isset( $_GET['course'] ) ) {
			$course_id = absint( wp_unslash( $_GET['course'] ) );
		}

		if ( ! $course_id ) {
			$dashboard_url = get_permalink( get_option( 'cta_student_dashboard_page_id' ) );
			ob_start();
			?>
			<div class="cta-plugin-wrapper">
				<div class="cta-empty-state" style="text-align:center; padding:60px 20px;">
					<h2><?php esc_html_e( 'No Course Selected', 'cta-lms' ); ?></h2>
					<p><?php esc_html_e( 'Please access the quiz from your course page.', 'cta-lms' ); ?></p>
					<?php if ( $dashboard_url ) : ?>
						<a href="<?php echo esc_url( $dashboard_url ); ?>" class="btn btn-primary"><?php esc_html_e( 'Go to Dashboard', 'cta-lms' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		$user_id    = get_current_user_id();
		$course     = CTA_Database::get_course( $course_id );
		$enrollment = CTA_Database::get_user_enrollment( $user_id, $course_id );
		$quiz       = CTA_Database::get_quiz_for_course( $course_id, $quiz_id );

		if ( ! $course ) {
			return '<div class="cta-plugin-wrapper"><div class="cta-empty-state"><p>' . esc_html__( 'Course not found.', 'cta-lms' ) . '</p></div></div>';
		}

		if ( ! $enrollment ) {
			return $this->render_message_state(
				__( 'Enrollment Required', 'cta-lms' ),
				__( 'You must be enrolled in this course to take the quiz.', 'cta-lms' ),
				$this->get_course_page_url( $course_id ),
				__( 'View Course', 'cta-lms' )
			);
		}

		if ( class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course ) && ! CTA_Exam_Access::has_active_access( $user_id, $course_id ) ) {
			return $this->render_message_state(
				__( 'Access expired', 'cta-lms' ),
				__( 'Your access to this Exam Preparation Program has expired.', 'cta-lms' ),
				$this->get_dashboard_url(),
				__( 'Back to Dashboard', 'cta-lms' )
			);
		}

		if ( class_exists( 'CTA_CE_Access' ) && CTA_CE_Access::is_ce_course( $course ) && ! CTA_CE_Access::has_active_access( $user_id, $course_id ) ) {
			return $this->render_message_state(
				__( 'Membership access ended', 'cta-lms' ),
				__( 'Your membership access to this course is no longer active. Certificates you already earned remain available in My Certificates.', 'cta-lms' ),
				$this->get_dashboard_url(),
				__( 'Back to Dashboard', 'cta-lms' )
			);
		}

		if ( class_exists( 'CTA_CE_Completion' ) ) {
			CTA_CE_Completion::sync_progress( $user_id, $course_id, $enrollment );
			$enrollment = CTA_Database::get_user_enrollment( $user_id, $course_id );
		}

		// Self-heal CTA-CE-003 final exam when deploy missed the 1.0.212/1.0.213 seeds.
		if ( class_exists( 'CTA_Suicide_Risk_Exam_Sync' ) ) {
			$sr_course = CTA_Suicide_Risk_Exam_Sync::find_course();
			if ( $sr_course && (int) $sr_course->id === (int) $course_id ) {
				try {
					CTA_Suicide_Risk_Exam_Sync::ensure();
					if ( class_exists( 'CTA_Suicide_Risk_Evaluation_Sync' ) ) {
						CTA_Suicide_Risk_Evaluation_Sync::ensure();
					}
				} catch ( Throwable $e ) {
					if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						error_log( 'CTA Suicide Risk quiz ensure failed: ' . $e->getMessage() );
					}
				}
			}
		}

		// A previously created attempt is an authorization snapshot: always let
		// the learner resume and submit it, even if course progress changes later.
		$active_attempt = $quiz
			? CTA_Database::get_active_quiz_attempt( $user_id, (int) $quiz->id )
			: null;

		$modules_done = class_exists( 'CTA_CE_Completion' )
			? CTA_CE_Completion::modules_complete( $user_id, $course_id, $enrollment )
			: ( class_exists( 'CTA_Certificates' ) && CTA_Certificates::user_completed_all_modules( $user_id, $course_id, $enrollment ) );

		$is_exam_prep = class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course );

		// Prerequisites belong at entry, before a new attempt can be created.
		// Existing attempts are resumable so prior learner work is never stranded.
		if ( ! $modules_done && ! $active_attempt ) {
			return $this->render_message_state(
				$is_exam_prep
					? __( 'Complete All Workbooks First', 'cta-lms' )
					: __( 'Complete All Modules First', 'cta-lms' ),
				$is_exam_prep
					? __( 'Complete all program workbooks before starting this assessment. Your completed workbooks are saved, and the assessment will unlock automatically.', 'cta-lms' )
					: __( 'Finish every instructional module, including the Course Integration Capstone, before starting the final examination.', 'cta-lms' ),
				$this->get_player_url( $course_id ),
				__( 'Back to Course', 'cta-lms' )
			);
		}

		$questions = $quiz ? CTA_Database::get_quiz_questions( (int) $quiz->id ) : array();

		if ( ! $quiz || empty( $questions ) ) {
			return $this->render_message_state(
				__( 'Final Examination Coming Soon', 'cta-lms' ),
				__( 'The final examination for this course has not been published yet. Please check back soon.', 'cta-lms' ),
				$this->get_player_url( $course_id ),
				__( 'Back to Course', 'cta-lms' )
			);
		}

		$attempts        = CTA_Database::get_user_quiz_attempts( $user_id, (int) $quiz->id );
		$evaluation      = CTA_Database::get_course_evaluation( $user_id, $course_id );
		$attestation     = class_exists( 'CTA_Course_Attestation' )
			? CTA_Course_Attestation::get( $user_id, $course_id )
			: null;
		$certificate     = CTA_Certificates::get_certificate( $user_id, $course_id );
		$passed_attempt  = $this->get_passed_attempt( $attempts );
		$attempt_count   = count( $attempts );
		$last_attempt    = ! empty( $attempts ) ? $attempts[0] : null;
		$view_state      = 'start';
		$evaluation_questions = self::get_evaluation_questions( $course_id );
		$attestation_text     = class_exists( 'CTA_Course_Attestation' )
			? CTA_Course_Attestation::default_attestation_text( $course ? (string) $course->title : '' )
			: '';
		// $is_exam_prep already resolved above.

		// Non-AJAX Start/Retry: create attempt on POST so Start never depends on admin-ajax alone.
		if (
			! $active_attempt
			&& isset( $_POST['cta_start_quiz'] )
			&& '1' === (string) wp_unslash( $_POST['cta_start_quiz'] )
			&& isset( $_POST['_wpnonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'cta_start_quiz_' . $course_id . '_' . (int) $quiz->id )
		) {
			if ( class_exists( 'CTA_Database' ) ) {
				CTA_Database::maybe_ensure_quiz_attempt_schema();
			}
			if ( $is_exam_prep || ! $this->get_passed_attempt( $attempts ) ) {
				$created = $this->create_quiz_attempt( $user_id, (int) $quiz->id, $course_id );
				if ( $created ) {
					$active_attempt = $created;
				}
			}
		}

		// Recover certificate only when evaluation + attestation are both complete.
		if ( ! $is_exam_prep && $passed_attempt && $evaluation && $attestation && ! $certificate ) {
			$certificate = CTA_Certificates::generate( $user_id, $course_id, $evaluation );
		}

		$inline_attestation = class_exists( 'CTA_CE_Completion' )
			&& CTA_CE_Completion::evaluation_includes_inline_attestation( $course_id );

		// Exam Prep practice banks stay retakeable — do not lock the start panel after a pass.
		if ( ! $is_exam_prep && $certificate && $evaluation && $attestation && $passed_attempt ) {
			$view_state = 'certificate_ready';
		} elseif ( ! $is_exam_prep && $passed_attempt && $evaluation && ! $attestation && ! $inline_attestation ) {
			$view_state = 'attestation';
		} elseif ( ! $is_exam_prep && $passed_attempt && ( ! $evaluation || ( ! $attestation && $inline_attestation ) ) ) {
			$view_state = 'evaluation';
		} elseif ( $active_attempt ) {
			$view_state = 'in_progress';
		}

		$dashboard_url = $this->get_dashboard_url();
		$player_url    = $this->get_player_url( $course_id );
		if ( $is_exam_prep && class_exists( 'CTA_Student_Dashboard' ) ) {
			$dash = new CTA_Student_Dashboard();
			$exams_url = $dash->get_player_view_url( $course_id, 'exams' );
			if ( $exams_url ) {
				$player_url = $exams_url;
			}
		}
		$quiz_handler  = $this;
		$question_count = count( $questions );
		$time_limit_mins = self::get_time_limit_mins( $quiz );
		$time_limit_label = self::format_time_limit_label( $time_limit_mins );
		$attempt_started_at = ( $active_attempt && ! empty( $active_attempt->started_at ) )
			? (string) $active_attempt->started_at
			: '';
		$attempts_label   = __( 'Unlimited', 'cta-lms' );
		$exam_instructions = class_exists( 'CTA_Suicide_Risk_Exam_Sync' )
			? CTA_Suicide_Risk_Exam_Sync::get_exam_instructions_for_course( $course )
			: '';
		$ce_teaching_points = class_exists( 'CTA_Suicide_Risk_Exam_Sync' )
			? CTA_Suicide_Risk_Exam_Sync::course_should_reveal_teaching_points( $course, $quiz )
			: false;

		ob_start();
		include CTA_PLUGIN_DIR . 'templates/quiz.php';
		return ob_get_clean();
	}

	/**
	 * AJAX: start a new quiz attempt.
	 */
	/**
	 * AJAX: start a new quiz attempt.
	 */
	public function ajax_start_quiz() {
		try {
			check_ajax_referer( 'cta_nonce', 'nonce' );

			if ( ! is_user_logged_in() ) {
				wp_send_json_error( array( 'message' => __( 'Please log in to continue.', 'cta-lms' ) ) );
			}

			$course_id    = absint( wp_unslash( $_POST['course_id'] ?? 0 ) );
			$quiz_id      = absint( wp_unslash( $_POST['quiz_id'] ?? 0 ) );
			$user_id      = get_current_user_id();
			$course       = CTA_Database::get_course( $course_id );
			$is_exam_prep = class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course );

			// Heal attempt schema/indexes before access checks so Start/Retry never dead-ends.
			if ( class_exists( 'CTA_Database' ) ) {
				CTA_Database::maybe_ensure_quiz_attempt_schema();
			}

			// Validate enrollment/product access first so an existing attempt can
			// always be resumed. Module prerequisites are checked only before a
			// brand-new attempt is created.
			$check = $this->validate_quiz_access( $user_id, $course_id, false, $quiz_id );

			if ( is_wp_error( $check ) ) {
				wp_send_json_error( array( 'message' => $check->get_error_message() ) );
			}

			/** @var object $quiz */
			$quiz   = $check['quiz'];
			$course = isset( $check['course'] ) ? $check['course'] : $course;
			$active = CTA_Database::get_active_quiz_attempt( $user_id, (int) $quiz->id );

			if ( $active ) {
				$payload = $this->build_attempt_payload( $quiz, $active );
				if ( empty( $payload['html'] ) || empty( $payload['question_count'] ) ) {
					wp_send_json_error( array( 'message' => __( 'This assessment has no questions yet. Please contact support.', 'cta-lms' ) ) );
				}
				wp_send_json_success( $payload );
			}

			$entry_check = $this->validate_quiz_access( $user_id, $course_id, true, $quiz_id );
			if ( is_wp_error( $entry_check ) ) {
				wp_send_json_error( array( 'message' => $entry_check->get_error_message() ) );
			}

			$quiz     = $entry_check['quiz'];
			$course   = isset( $entry_check['course'] ) ? $entry_check['course'] : $course;
			$attempts = CTA_Database::get_user_quiz_attempts( $user_id, (int) $quiz->id );

			// CE: block after a pass. Exam Prep assessments may be retaken independently.
			if ( ! $is_exam_prep && $this->get_passed_attempt( $attempts ) ) {
				wp_send_json_error( array( 'message' => __( 'You have already passed this quiz.', 'cta-lms' ) ) );
			}

			$attempt = $this->create_quiz_attempt( $user_id, (int) $quiz->id, $course_id );

			if ( ! $attempt ) {
				$active = CTA_Database::get_active_quiz_attempt( $user_id, (int) $quiz->id );
				if ( $active ) {
					wp_send_json_success( $this->build_attempt_payload( $quiz, $active ) );
				}
				global $wpdb;
				$message = __( 'Unable to start quiz. Please refresh the page and try again.', 'cta-lms' );
				if ( ! empty( $wpdb->last_error ) ) {
					$message .= ' [' . $wpdb->last_error . ']';
				}
				wp_send_json_error(
					array(
						'message'      => $message,
						'use_fallback' => true,
					)
				);
			}

			$payload = $this->build_attempt_payload( $quiz, $attempt );
			if ( empty( $payload['html'] ) || empty( $payload['question_count'] ) ) {
				wp_send_json_error( array( 'message' => __( 'This assessment has no questions yet. Please contact support.', 'cta-lms' ) ) );
			}

			wp_send_json_success( $payload );
		} catch ( Throwable $e ) {
			wp_send_json_error(
				array(
					'message'      => __( 'Unable to start quiz. Please refresh the page and try again.', 'cta-lms' ) . ' [' . $e->getMessage() . ']',
					'use_fallback' => true,
				)
			);
		}
	}

	/**
	 * AJAX: persist in-progress answers so interrupted assessments can resume.
	 */
	public function ajax_save_quiz_progress() {
		check_ajax_referer( 'cta_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to save your progress.', 'cta-lms' ) ) );
		}

		$attempt_id = absint( wp_unslash( $_POST['attempt_id'] ?? 0 ) );
		$user_id    = get_current_user_id();
		$answers_in = isset( $_POST['answers'] ) ? wp_unslash( $_POST['answers'] ) : array();

		if ( ! is_array( $answers_in ) ) {
			$answers_in = array();
		}

		global $wpdb;

		$attempt = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}cta_quiz_attempts WHERE id = %d AND user_id = %d",
				$attempt_id,
				$user_id
			)
		);

		if ( ! $attempt || ! $this->is_attempt_in_progress( $attempt ) ) {
			wp_send_json_error( array( 'message' => __( 'This assessment attempt is no longer active.', 'cta-lms' ) ) );
		}

		$questions = CTA_Database::get_quiz_questions( (int) $attempt->quiz_id );
		if ( empty( $questions ) ) {
			wp_send_json_error( array( 'message' => __( 'Assessment questions were not found.', 'cta-lms' ) ) );
		}

		$sanitized = $this->sanitize_quiz_answers( $answers_in, $questions, false );
		$updated   = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}cta_quiz_attempts
				SET answers = %s
				WHERE id = %d AND user_id = %d
					AND (completed_at IS NULL OR completed_at = '0000-00-00 00:00:00' OR completed_at = '0000-00-00')",
				wp_json_encode( $sanitized ),
				$attempt_id,
				$user_id
			)
		);

		if ( false === $updated ) {
			wp_send_json_error( array( 'message' => __( 'Unable to save assessment progress.', 'cta-lms' ) ) );
		}

		wp_send_json_success(
			array(
				'saved_count' => count( $sanitized ),
				'saved_at'    => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * AJAX: submit quiz answers.
	 */
	public function ajax_submit_quiz() {
		check_ajax_referer( 'cta_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to continue.', 'cta-lms' ) ) );
		}

		$attempt_id = absint( wp_unslash( $_POST['attempt_id'] ?? 0 ) );
		$user_id    = get_current_user_id();
		$answers_in = isset( $_POST['answers'] ) ? wp_unslash( $_POST['answers'] ) : array();

		if ( ! is_array( $answers_in ) ) {
			$answers_in = array();
		}

		global $wpdb;

		$attempt = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}cta_quiz_attempts WHERE id = %d AND user_id = %d",
				$attempt_id,
				$user_id
			)
		);

		if ( ! $attempt || ! $this->is_attempt_in_progress( $attempt ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid quiz attempt.', 'cta-lms' ) ) );
		}

		$quiz      = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}cta_quizzes WHERE id = %d",
				(int) $attempt->quiz_id
			)
		);
		$questions = CTA_Database::get_quiz_questions( (int) $attempt->quiz_id );

		if ( ! $quiz || empty( $questions ) ) {
			wp_send_json_error( array( 'message' => __( 'Quiz not found.', 'cta-lms' ) ) );
		}

		$sanitized = $this->sanitize_quiz_answers( $answers_in, $questions, true );
		$correct   = 0;
		$total     = count( $questions );
		$revealed  = array();

		$course_for_reveal = CTA_Database::get_course( (int) $attempt->course_id );
		$is_exam_prep      = class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course_for_reveal );
		$uses_core_scoring = class_exists( 'CTA_Lmft_Clinical_Comprehensive_Scoring' )
			&& CTA_Lmft_Clinical_Comprehensive_Scoring::uses_core_calibration_scoring( $quiz, $course_for_reveal );
		// CE finals: store rationales for admin QA; do not reveal to learners until owner approves.
		$reveal_explanations = (bool) apply_filters(
			'cta_lms_reveal_quiz_explanations',
			$is_exam_prep,
			$quiz,
			$course_for_reveal
		);
		$reveal_correct = (bool) apply_filters(
			'cta_lms_reveal_quiz_correct_answers',
			$is_exam_prep,
			$quiz,
			$course_for_reveal
		);

		if ( class_exists( 'CTA_Lmft_Clinical_Comprehensive_Review' )
			&& CTA_Lmft_Clinical_Comprehensive_Review::applies_to_quiz( $quiz, $course_for_reveal ) ) {
			// Submitting this form is the unlock event for its own review content.
			$reveal_explanations = true;
			$reveal_correct      = true;
		}

		foreach ( $questions as $question ) {
			$qid    = (int) $question->id;
			$answer = isset( $sanitized[ $qid ] ) ? $sanitized[ $qid ] : '';

			if ( $answer && $answer === $question->correct_option ) {
				++$correct;
			}

			$explanation = '';
			if ( $reveal_explanations ) {
				if ( class_exists( 'CTA_Lmft_Clinical_Comprehensive_Review' )
					&& CTA_Lmft_Clinical_Comprehensive_Review::applies_to_quiz( $quiz, $course_for_reveal ) ) {
					$explanation = CTA_Lmft_Clinical_Comprehensive_Review::get_learner_explanation_for_question( $quiz, $question );
				} else {
					$explanation = (string) $question->explanation;
				}
			}

			$revealed[] = array(
				'question_id'    => $qid,
				'user_answer'    => $answer,
				'correct_option' => $reveal_correct ? (string) $question->correct_option : '',
				'explanation'    => $explanation,
				'is_correct'     => ( $answer === $question->correct_option ),
			);
		}

		$score  = $total > 0 ? (int) round( ( $correct / $total ) * 100 ) : 0;
		$passed = $score >= (int) $quiz->passing_score ? 1 : 0;

		if ( $uses_core_scoring ) {
			$core_score = CTA_Lmft_Clinical_Comprehensive_Scoring::calculate_display_score(
				$questions,
				$sanitized,
				$quiz,
				(int) $quiz->passing_score
			);
			$score  = (int) $core_score['score'];
			$passed = ! empty( $core_score['passed'] ) ? 1 : 0;
		}

		$completed = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}cta_quiz_attempts
				SET answers = %s, score = %d, passed = %d, completed_at = %s
				WHERE id = %d AND user_id = %d
					AND (completed_at IS NULL OR completed_at = '0000-00-00 00:00:00' OR completed_at = '0000-00-00')",
				wp_json_encode( $sanitized ),
				$score,
				$passed,
				current_time( 'mysql' ),
				$attempt_id,
				$user_id
			)
		);

		if ( false === $completed || 0 === (int) $completed ) {
			wp_send_json_error( array( 'message' => __( 'This assessment was already submitted or could not be saved. Please refresh to review your attempt.', 'cta-lms' ) ) );
		}

		if ( $passed ) {
			$course    = $course_for_reveal;
			$next_step = 'evaluation';

			// Exam prep: no CE evaluation / certificate path.
			if ( $is_exam_prep ) {
				$next_step = 'complete';
			}

			wp_send_json_success(
				array(
					'passed'     => true,
					'score'      => $score,
					'message'    => sprintf(
						/* translators: %d: score percentage */
						__( 'Congratulations! You passed with %d%%', 'cta-lms' ),
						$score
					),
					'next_step'  => $next_step,
					'passing_score' => (int) $quiz->passing_score,
					'results'    => $revealed,
				)
			);
		}

		wp_send_json_success(
			array(
				'passed'        => false,
				'score'         => $score,
				'message'       => sprintf(
					/* translators: 1: score, 2: passing score */
					__( 'Score: %1$d%%. Passing score is %2$d%%.', 'cta-lms' ),
					$score,
					(int) $quiz->passing_score
				),
				'can_retry'     => true,
				'passing_score' => (int) $quiz->passing_score,
				'results'       => $revealed,
			)
		);
	}

	/**
	 * AJAX: submit course evaluation (certificate issued only after attestation).
	 */
	public function ajax_submit_evaluation() {
		check_ajax_referer( 'cta_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to continue.', 'cta-lms' ) ) );
		}

		$course_id = absint( wp_unslash( $_POST['course_id'] ?? 0 ) );
		$user_id   = get_current_user_id();
		$check     = $this->validate_quiz_access( $user_id, $course_id, false );

		if ( is_wp_error( $check ) ) {
			wp_send_json_error( array( 'message' => $check->get_error_message() ) );
		}

		$course = isset( $check['course'] ) ? $check['course'] : CTA_Database::get_course( $course_id );
		if ( class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course ) ) {
			wp_send_json_error( array( 'message' => __( 'Exam Preparation Programs do not require a CE evaluation or certificate.', 'cta-lms' ) ) );
		}

		if ( class_exists( 'CTA_CE_Completion' ) ) {
			$seq = CTA_CE_Completion::assert_can_access_evaluation( $user_id, $course_id );
			if ( is_wp_error( $seq ) ) {
				wp_send_json_error( array( 'message' => $seq->get_error_message() ) );
			}
		}

		/** @var object $quiz */
		$quiz     = $check['quiz'];
		$attempts = CTA_Database::get_user_quiz_attempts( $user_id, (int) $quiz->id );

		if ( ! $this->get_passed_attempt( $attempts ) ) {
			wp_send_json_error( array( 'message' => __( 'You must pass the quiz before submitting an evaluation.', 'cta-lms' ) ) );
		}

		// Allow additional historical submissions without overwriting prior rows.
		// Certificate still requires attestation after evaluation.

		$raw_responses = isset( $_POST['responses'] ) ? wp_unslash( $_POST['responses'] ) : array();

		if ( is_string( $raw_responses ) ) {
			$decoded       = json_decode( $raw_responses, true );
			$raw_responses = is_array( $decoded ) ? $decoded : array();
		}

		if ( ! is_array( $raw_responses ) ) {
			$raw_responses = array();
		}

		$parsed = $this->sanitize_evaluation_responses( $raw_responses, $course_id );

		if ( is_wp_error( $parsed ) ) {
			wp_send_json_error( array( 'message' => $parsed->get_error_message() ) );
		}

		$responses_map = isset( $parsed['responses'] ) && is_array( $parsed['responses'] ) ? $parsed['responses'] : array();

		// Course-specific evaluation forms with inline Section 9 attestation.
		$inline_attestation_config = class_exists( 'CTA_CE_Completion' )
			? CTA_CE_Completion::inline_attestation_config( $course_id )
			: null;
		$inline_attestation        = ! empty( $inline_attestation_config );
		$inline_signature_name     = '';
		$inline_signature_date     = '';
		if ( $inline_attestation && is_array( $inline_attestation_config ) ) {
			$agree_raw = self::evaluation_response_value(
				$responses_map,
				(array) ( $inline_attestation_config['agree_keys'] ?? array() )
			);
			$agree_ok  = false;
			foreach ( (array) ( $inline_attestation_config['agree_keys'] ?? array() ) as $agree_key ) {
				if ( is_array( $responses_map[ $agree_key ] ?? null ) && ! empty( $responses_map[ $agree_key ] ) ) {
					$agree_ok = true;
					break;
				}
			}
			if ( ! $agree_ok && '' !== $agree_raw && '0' !== $agree_raw ) {
				$agree_ok = true;
			}

			$inline_signature_name = self::evaluation_response_value(
				$responses_map,
				(array) ( $inline_attestation_config['signature_keys'] ?? array() )
			);
			$inline_signature_date = self::evaluation_response_value(
				$responses_map,
				(array) ( $inline_attestation_config['date_keys'] ?? array() )
			);

			if ( ! $agree_ok ) {
				wp_send_json_error(
					array(
						'message' => __( 'Please check the course-completion attestation checkbox to continue.', 'cta-lms' ),
						'code'    => 'cta_attestation_agree',
					)
				);
			}
			if ( '' === trim( $inline_signature_name ) || strlen( trim( $inline_signature_name ) ) < 2 ) {
				wp_send_json_error(
					array(
						'message' => __( 'Please complete the Typed Name field to electronically sign this attestation.', 'cta-lms' ),
						'code'    => 'cta_attestation_signature',
					)
				);
			}
			if ( '' === trim( $inline_signature_date ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'Please enter the attestation date.', 'cta-lms' ),
						'code'    => 'cta_attestation_date',
					)
				);
			}
		}

		$timezone = sanitize_text_field( wp_unslash( $_POST['timezone'] ?? '' ) );
		if ( $timezone && ! $this->is_valid_timezone( $timezone ) ) {
			$timezone = '';
		}
		// Never persist PKT / Asia/Karachi (or similar) — certificates must stay Pacific.
		if (
			$timezone
			&& function_exists( 'cta_lms_is_non_cta_server_timezone' )
			&& cta_lms_is_non_cta_server_timezone( $timezone )
		) {
			$timezone = '';
		}

		$user         = get_userdata( $user_id );
		$student_name = function_exists( 'cta_lms_get_user_legal_name' )
			? cta_lms_get_user_legal_name( $user_id )
			: ( $user ? $user->display_name : '' );
		$student_email = $user ? (string) $user->user_email : '';

		// Prefer participant-info answers from the evaluation form when present.
		$form_name     = self::evaluation_response_value( $responses_map, array( 'participant_cert_name', 'camft_participant_cert_name' ) );
		$form_email    = self::evaluation_response_value( $responses_map, array( 'participant_email', 'camft_participant_email' ) );
		$form_lic_type = self::evaluation_response_value( $responses_map, array( 'participant_license_type', 'camft_participant_license_type' ) );
		$form_lic_num  = self::evaluation_response_value( $responses_map, array( 'participant_license_number', 'camft_participant_license_number' ) );

		if ( '' !== $form_name ) {
			$student_name = $form_name;
			if ( function_exists( 'cta_lms_sync_user_name_parts' ) ) {
				cta_lms_sync_user_name_parts( $user_id, $form_name );
			}
			wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => $form_name,
				)
			);
		}
		if ( '' !== $form_email && is_email( $form_email ) ) {
			$student_email = $form_email;
		}
		if ( '' !== $form_lic_type ) {
			update_user_meta( $user_id, 'cta_license_type', sanitize_text_field( $form_lic_type ) );
		}
		if ( '' !== $form_lic_num ) {
			$lic = function_exists( 'cta_lms_sanitize_license_number' )
				? cta_lms_sanitize_license_number( $form_lic_num )
				: sanitize_text_field( $form_lic_num );
			update_user_meta( $user_id, 'cta_license_number', $lic );
		}

		global $wpdb;

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'cta_evaluations',
			array(
				'user_id'           => $user_id,
				'course_id'         => $course_id,
				'rating'            => (int) $parsed['rating'],
				'content_quality'   => (int) $parsed['content_quality'],
				'instructor_rating' => (int) $parsed['instructor_rating'],
				'would_recommend'   => (int) $parsed['would_recommend'],
				'comments'          => $parsed['comments'],
				'responses'         => wp_json_encode( $parsed['responses'] ),
				'timezone'          => $timezone,
				'submitted_at'      => current_time( 'mysql' ),
				'status'            => 'completed',
				'course_title'      => $course ? (string) $course->title : '',
				'student_name'      => (string) $student_name,
				'student_email'     => (string) $student_email,
			),
			array( '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			wp_send_json_error( array( 'message' => __( 'Unable to save evaluation.', 'cta-lms' ) ) );
		}

		$evaluation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}cta_evaluations WHERE id = %d",
				(int) $wpdb->insert_id
			)
		);

		$has_attestation = class_exists( 'CTA_Course_Attestation' )
			&& CTA_Course_Attestation::has( $user_id, $course_id );

		// Inline Section 9 attestation lives in the same evaluation form (validated above).
		if ( ! $has_attestation
			&& $inline_attestation
			&& class_exists( 'CTA_Course_Attestation' ) ) {
			$attestation_text = '';
			if ( class_exists( 'CTA_CE_Completion' ) ) {
				$config = CTA_CE_Completion::inline_attestation_config( $course_id );
				if ( is_array( $config ) && ! empty( $config['statement'] ) ) {
					$attestation_text = (string) $config['statement'];
				}
			}
			if ( '' === $attestation_text && class_exists( 'CTA_Law_Ethics_Evaluation_Sync' ) ) {
				$attestation_text = CTA_Law_Ethics_Evaluation_Sync::attestation_statement();
			}
			$result           = CTA_Course_Attestation::submit(
				$user_id,
				$course_id,
				$attestation_text,
				$inline_signature_name,
				$inline_signature_date
			);
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}
			$has_attestation = true;
		}

		if ( $has_attestation ) {
			$certificate = CTA_Certificates::generate( $user_id, $course_id, $evaluation );
			if ( ! $certificate ) {
				wp_send_json_error( array( 'message' => __( 'Evaluation saved but certificate could not be generated. Confirm modules, attestation, and exam requirements.', 'cta-lms' ) ) );
			}

			wp_send_json_success(
				array(
					'message'            => __( 'Thank you! Your certificate is ready.', 'cta-lms' ),
					'next_step'          => 'certificate',
					'evaluation_id'      => (int) $evaluation->id,
					'certificate_id'     => (int) $certificate->id,
					'certificate_number' => $certificate->certificate_number,
					'print_url'          => CTA_Certificates::get_print_url( (int) $certificate->id, true ),
					'download_url'       => CTA_Certificates::get_download_url( (int) $certificate->id ),
					'dashboard_url'      => $this->get_dashboard_url(),
				)
			);
		}

		wp_send_json_success(
			array(
				'message'       => __( 'Evaluation submitted. Please complete the course-completion attestation.', 'cta-lms' ),
				'next_step'     => 'attestation',
				'evaluation_id' => (int) $evaluation->id,
				'dashboard_url' => $this->get_dashboard_url(),
			)
		);
	}

	/**
	 * AJAX: submit course-completion attestation and issue certificate.
	 */
	public function ajax_submit_attestation() {
		check_ajax_referer( 'cta_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to continue.', 'cta-lms' ) ) );
		}

		if ( ! class_exists( 'CTA_Course_Attestation' ) ) {
			wp_send_json_error( array( 'message' => __( 'Attestation module unavailable.', 'cta-lms' ) ) );
		}

		$course_id = absint( wp_unslash( $_POST['course_id'] ?? 0 ) );
		$user_id   = get_current_user_id();
		$check     = $this->validate_quiz_access( $user_id, $course_id, false );

		if ( is_wp_error( $check ) ) {
			wp_send_json_error( array( 'message' => $check->get_error_message() ) );
		}

		$course = isset( $check['course'] ) ? $check['course'] : CTA_Database::get_course( $course_id );
		if ( class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course ) ) {
			wp_send_json_error( array( 'message' => __( 'Exam Preparation Programs do not require attestation.', 'cta-lms' ) ) );
		}

		if ( class_exists( 'CTA_CE_Completion' ) ) {
			$seq = CTA_CE_Completion::assert_can_access_attestation( $user_id, $course_id );
			if ( is_wp_error( $seq ) ) {
				wp_send_json_error( array( 'message' => $seq->get_error_message() ) );
			}
		}

		$agreed = ! empty( $_POST['agree'] );
		if ( ! $agreed ) {
			wp_send_json_error( array( 'message' => __( 'You must check the attestation checkbox to continue.', 'cta-lms' ) ) );
		}

		/** @var object $quiz */
		$quiz     = $check['quiz'];
		$attempts = CTA_Database::get_user_quiz_attempts( $user_id, (int) $quiz->id );

		if ( ! $this->get_passed_attempt( $attempts ) ) {
			wp_send_json_error( array( 'message' => __( 'You must pass the final examination first.', 'cta-lms' ) ) );
		}

		$evaluation = CTA_Database::get_course_evaluation( $user_id, $course_id );
		if ( ! $evaluation ) {
			wp_send_json_error( array( 'message' => __( 'Submit the course evaluation before attestation.', 'cta-lms' ) ) );
		}

		$course_title     = $course ? (string) $course->title : '';
		$attestation_text = CTA_Course_Attestation::default_attestation_text( $course_title );
		$signature_name   = sanitize_text_field(
			wp_unslash(
				$_POST['signature_name']
				?? $_POST['attestation_signature']
				?? $_POST['typed_name']
				?? ''
			)
		);
		$signature_date = sanitize_text_field(
			wp_unslash(
				$_POST['signature_date']
				?? $_POST['attestation_date']
				?? ''
			)
		);

		if ( '' === trim( $signature_name ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please complete the Typed Name field to electronically sign this attestation.', 'cta-lms' ),
					'code'    => 'cta_attestation_signature',
				)
			);
		}

		if ( '' === trim( $signature_date ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please enter the attestation date.', 'cta-lms' ),
					'code'    => 'cta_attestation_date',
				)
			);
		}

		$result = CTA_Course_Attestation::submit( $user_id, $course_id, $attestation_text, $signature_name, $signature_date );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				)
			);
		}

		$certificate = CTA_Certificates::generate( $user_id, $course_id, $evaluation );

		if ( ! $certificate ) {
			wp_send_json_error(
				array(
					'message' => __( 'Attestation saved but certificate could not be generated. Confirm all modules are complete.', 'cta-lms' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'message'            => __( 'Thank you! Your certificate is ready.', 'cta-lms' ),
				'next_step'          => 'certificate',
				'certificate_id'     => (int) $certificate->id,
				'certificate_number' => $certificate->certificate_number,
				'print_url'          => CTA_Certificates::get_print_url( (int) $certificate->id, true ),
				'download_url'       => CTA_Certificates::get_download_url( (int) $certificate->id ),
				'dashboard_url'      => $this->get_dashboard_url(),
			)
		);
	}

	/**
	 * Read the first non-empty evaluation response for a list of question keys.
	 *
	 * @param array $responses Response map.
	 * @param array $keys      Candidate question keys.
	 * @return string
	 */
	private static function evaluation_response_value( $responses, $keys ) {
		foreach ( (array) $keys as $key ) {
			if ( ! isset( $responses[ $key ] ) ) {
				continue;
			}
			$value = $responses[ $key ];
			if ( is_array( $value ) ) {
				continue;
			}
			$value = trim( (string) $value );
			if ( '' !== $value ) {
				return $value;
			}
		}
		return '';
	}

	/**
	 * Structured course evaluation questions (per-course).
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	public static function get_evaluation_questions( $course_id = 0 ) {
		$course_id = absint( $course_id );
		if ( class_exists( 'CTA_Evaluation_Questions' ) ) {
			return CTA_Evaluation_Questions::get_form_questions( $course_id );
		}

		return array();
	}

	/**
	 * Sanitize and validate structured evaluation responses for a course.
	 *
	 * @param array $raw_responses Submitted responses keyed by question ID.
	 * @param int   $course_id     Course ID.
	 * @return array|WP_Error
	 */
	private function sanitize_evaluation_responses( $raw_responses, $course_id = 0 ) {
		$questions = self::get_evaluation_questions( $course_id );
		$clean     = array();
		$summary   = array(
			'rating'            => 0,
			'content_quality'   => 0,
			'instructor_rating' => 0,
			'would_recommend'   => 0,
			'comments'          => '',
		);

		foreach ( $questions as $question ) {
			$id    = $question['id'];
			$type  = isset( $question['type'] ) ? $question['type'] : 'rating';
			$value = isset( $raw_responses[ $id ] ) ? $raw_responses[ $id ] : '';

			// Display-only rows are not answered.
			if ( 'info' === $type ) {
				continue;
			}

			if ( in_array( $type, array( 'rating', 'likert' ), true ) ) {
				$opt_val      = sanitize_text_field( (string) ( is_array( $value ) ? '' : $value ) );
				$allowed_keys = array_map( 'strval', array_keys( (array) ( $question['options'] ?? array() ) ) );
				if ( empty( $allowed_keys ) ) {
					$allowed_keys = array( '1', '2', '3', '4', '5', 'na' );
				}
				if ( ! empty( $question['required'] ) && ( '' === $opt_val || ! in_array( $opt_val, $allowed_keys, true ) ) ) {
					return new WP_Error(
						'missing_field',
						sprintf(
							/* translators: %s: question label */
							__( 'Please answer: %s', 'cta-lms' ),
							$question['label']
						)
					);
				}
				$clean[ $id ] = in_array( $opt_val, $allowed_keys, true ) ? $opt_val : '';
			} elseif ( in_array( $type, array( 'radio', 'multiple_choice', 'yes_no', 'dropdown' ), true ) ) {
				$answer  = sanitize_text_field( (string) ( is_array( $value ) ? '' : $value ) );
				$allowed = array_map( 'strval', array_keys( (array) ( $question['options'] ?? array() ) ) );
				if ( ! empty( $question['required'] ) && ( '' === $answer || ! in_array( $answer, $allowed, true ) ) ) {
					return new WP_Error(
						'missing_field',
						sprintf(
							/* translators: %s: question label */
							__( 'Please answer: %s', 'cta-lms' ),
							$question['label']
						)
					);
				}
				$clean[ $id ] = in_array( $answer, $allowed, true ) ? $answer : '';
			} elseif ( 'checkbox' === $type ) {
				$answers = is_array( $value ) ? $value : ( '' === $value ? array() : array( $value ) );
				$allowed = array_map( 'strval', array_keys( (array) ( $question['options'] ?? array() ) ) );
				$picked  = array();
				foreach ( $answers as $answer ) {
					$answer = sanitize_text_field( (string) $answer );
					if ( in_array( $answer, $allowed, true ) ) {
						$picked[] = $answer;
					}
				}
				if ( ! empty( $question['required'] ) && empty( $picked ) ) {
					return new WP_Error(
						'missing_field',
						sprintf(
							/* translators: %s: question label */
							__( 'Please answer: %s', 'cta-lms' ),
							$question['label']
						)
					);
				}
				$clean[ $id ] = $picked;
			} elseif ( in_array( $type, array( 'textarea', 'paragraph', 'short_text' ), true ) ) {
				$text = 'short_text' === $type
					? sanitize_text_field( (string) ( is_array( $value ) ? '' : $value ) )
					: sanitize_textarea_field( (string) ( is_array( $value ) ? '' : $value ) );

				// Email field is prefilled from the account; if a stale client omitted it,
				// fall back to the logged-in user's email so submit still succeeds.
				$bare_id = (string) $id;
				if ( 0 === strpos( $bare_id, 'camft_' ) ) {
					$bare_id = substr( $bare_id, 6 );
				}
				if ( 'participant_email' === $bare_id && '' === trim( $text ) && is_user_logged_in() ) {
					$current = wp_get_current_user();
					if ( $current && is_email( (string) $current->user_email ) ) {
						$text = (string) $current->user_email;
					}
				}

				if ( ! empty( $question['required'] ) && '' === trim( $text ) ) {
					return new WP_Error(
						'missing_field',
						sprintf(
							/* translators: %s: question label */
							__( 'Please answer: %s', 'cta-lms' ),
							$question['label']
						)
					);
				}
				// Email participant field must be a valid address.
				if ( 'participant_email' === $bare_id && '' !== $text && ! is_email( $text ) ) {
					return new WP_Error(
						'invalid_email',
						__( 'Please enter a valid email address.', 'cta-lms' )
					);
				}
				$clean[ $id ] = $text;
			} else {
				$clean[ $id ] = sanitize_text_field( (string) ( is_array( $value ) ? wp_json_encode( $value ) : $value ) );
			}

			if ( empty( $question['summary'] ) ) {
				continue;
			}

			switch ( $question['summary'] ) {
				case 'rating':
				case 'content_quality':
				case 'instructor_rating':
					$summary[ $question['summary'] ] = absint( is_array( $clean[ $id ] ) ? 0 : $clean[ $id ] );
					break;
				case 'would_recommend':
					$raw_rec = is_array( $clean[ $id ] ) ? '' : (string) $clean[ $id ];
					if ( 'yes' === $raw_rec || '1' === $raw_rec ) {
						$summary['would_recommend'] = 1;
					} elseif ( is_numeric( $raw_rec ) && (int) $raw_rec >= 4 ) {
						// Rating scale: 4 Agree / 5 Strongly Agree counts as recommend.
						$summary['would_recommend'] = 1;
					} else {
						$summary['would_recommend'] = 0;
					}
					break;
				case 'comments':
					$summary['comments'] = is_array( $clean[ $id ] ) ? '' : (string) $clean[ $id ];
					break;
			}
		}

		return array_merge(
			$summary,
			array(
				'responses' => $clean,
			)
		);
	}

	/**
	 * Validate an IANA timezone identifier.
	 *
	 * @param string $timezone Timezone string.
	 * @return bool
	 */
	private function is_valid_timezone( $timezone ) {
		$timezone = (string) $timezone;

		if ( '' === $timezone ) {
			return false;
		}

		try {
			new DateTimeZone( $timezone );
			return true;
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Render quiz questions for template or AJAX.
	 *
	 * @param object $quiz     Quiz row.
	 * @param object $attempt  Attempt row.
	 * @param array  $questions Question rows.
	 * @param bool   $review   Whether to show review state.
	 * @return string
	 */
	public function render_quiz_questions( $quiz, $attempt, $questions, $review = false ) {
		$quiz_obj = $this;
		$answers  = array();

		if ( ! empty( $attempt->answers ) ) {
			$decoded = json_decode( (string) $attempt->answers, true );
			if ( is_array( $decoded ) ) {
				$answers = $decoded;
			}
		}

		ob_start();

		foreach ( $questions as $index => $question ) {
			$question_number = $index + 1;
			$user_answer     = isset( $answers[ $question->id ] ) ? $answers[ $question->id ] : '';
			include CTA_PLUGIN_DIR . 'templates/partials/quiz-question.php';
		}

		return ob_get_clean();
	}

	/**
	 * Whether an attempt row is still open for autosave/submission.
	 *
	 * @param object|null $attempt Attempt row.
	 * @return bool
	 */
	private function is_attempt_in_progress( $attempt ) {
		if ( ! $attempt ) {
			return false;
		}

		$completed_at = isset( $attempt->completed_at ) ? trim( (string) $attempt->completed_at ) : '';
		return '' === $completed_at
			|| '0000-00-00' === $completed_at
			|| '0000-00-00 00:00:00' === $completed_at;
	}

	/**
	 * Keep only answers for questions belonging to this assessment.
	 *
	 * @param array $answers_in    Raw submitted answer map.
	 * @param array $questions     Quiz question rows.
	 * @param bool  $include_empty Include unanswered question IDs.
	 * @return array<int,string>
	 */
	private function sanitize_quiz_answers( array $answers_in, array $questions, $include_empty = false ) {
		$sanitized = array();

		foreach ( $questions as $question ) {
			$qid    = (int) $question->id;
			$answer = isset( $answers_in[ $qid ] ) ? sanitize_text_field( $answers_in[ $qid ] ) : '';
			$answer = in_array( $answer, array( 'a', 'b', 'c', 'd' ), true ) ? $answer : '';

			if ( '' !== $answer || $include_empty ) {
				$sanitized[ $qid ] = $answer;
			}
		}

		return $sanitized;
	}

	/**
	 * Validate quiz access for a user and course.
	 *
	 * @param int  $user_id           User ID.
	 * @param int  $course_id         Course ID.
	 * @param bool $require_complete  Require 100% module progress.
	 * @param int  $quiz_id           Optional specific quiz ID.
	 * @return array|WP_Error
	 */
	private function validate_quiz_access( $user_id, $course_id, $require_complete = true, $quiz_id = 0 ) {
		$enrollment = CTA_Database::get_user_enrollment( $user_id, $course_id );
		$course     = CTA_Database::get_course( $course_id );
		$quiz       = CTA_Database::get_quiz_for_course( $course_id, $quiz_id );

		if ( ! $enrollment ) {
			return new WP_Error( 'not_enrolled', __( 'You are not enrolled in this course.', 'cta-lms' ) );
		}

		if ( class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course ) ) {
			if ( ! CTA_Exam_Access::has_active_access( $user_id, $course_id ) ) {
				return new WP_Error( 'exam_expired', __( 'Your access to this Exam Preparation Program has expired.', 'cta-lms' ) );
			}
		} elseif ( class_exists( 'CTA_CE_Access' ) && CTA_CE_Access::is_ce_course( $course ) && ! CTA_CE_Access::has_active_access( $user_id, $course_id ) ) {
			return new WP_Error( 'ce_access_ended', __( 'Your membership access to this course is no longer active.', 'cta-lms' ) );
		}

		if ( $require_complete ) {
			if ( class_exists( 'CTA_CE_Completion' ) ) {
				CTA_CE_Completion::sync_progress( $user_id, $course_id, $enrollment );
				$enrollment = CTA_Database::get_user_enrollment( $user_id, $course_id );
				$seq        = CTA_CE_Completion::assert_can_access_exam( $user_id, $course_id );
				if ( is_wp_error( $seq ) ) {
					return $seq;
				}
			} elseif ( class_exists( 'CTA_Certificates' ) && ! CTA_Certificates::user_completed_all_modules( $user_id, $course_id, $enrollment ) ) {
				return new WP_Error(
					'incomplete',
					__( 'Complete all instructional modules before starting this assessment.', 'cta-lms' )
				);
			} elseif ( (int) $enrollment->progress < 100 ) {
				return new WP_Error( 'incomplete', __( 'Complete all modules before starting this assessment.', 'cta-lms' ) );
			}
		}

		if ( ! $quiz ) {
			return new WP_Error( 'no_quiz', __( 'Quiz not available.', 'cta-lms' ) );
		}

		return array(
			'enrollment' => $enrollment,
			'quiz'       => $quiz,
			'course'     => $course,
		);
	}

	/**
	 * Create a new in-progress quiz attempt row.
	 *
	 * Uses MAX(attempt_number)+1 across all rows (including incomplete) and never
	 * depends on a UNIQUE DB index (those are dropped by schema heal).
	 *
	 * @param int $user_id   User ID.
	 * @param int $quiz_id   Quiz ID.
	 * @param int $course_id Course ID.
	 * @return object|null Attempt row or null on failure.
	 */
	private function create_quiz_attempt( $user_id, $quiz_id, $course_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_quiz_attempts';
		$now   = current_time( 'mysql' );

		if ( class_exists( 'CTA_Database' ) ) {
			CTA_Database::maybe_ensure_quiz_attempt_schema();
		}

		$active = CTA_Database::get_active_quiz_attempt( $user_id, $quiz_id );
		if ( $active ) {
			return $active;
		}

		for ( $try = 0; $try < 8; $try++ ) {
			$max = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT MAX(attempt_number) FROM {$table} WHERE user_id = %d AND quiz_id = %d",
					$user_id,
					$quiz_id
				)
			);

			$attempt_number = max( 1, absint( $max ) + 1 + $try );

			$wpdb->last_error = '';

			// Prefer $wpdb->insert; omit completed_at so DEFAULT NULL applies.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$inserted = $wpdb->insert(
				$table,
				array(
					'user_id'        => $user_id,
					'quiz_id'        => $quiz_id,
					'course_id'      => $course_id,
					'answers'        => '',
					'score'          => 0,
					'passed'         => 0,
					'attempt_number' => $attempt_number,
					'started_at'     => $now,
				),
				array( '%d', '%d', '%d', '%s', '%d', '%d', '%d', '%s' )
			);

			if ( false === $inserted ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$inserted = $wpdb->query(
					$wpdb->prepare(
						"INSERT INTO {$table}
							(user_id, quiz_id, course_id, answers, score, passed, attempt_number, started_at, completed_at)
						 VALUES
							(%d, %d, %d, %s, %d, %d, %d, %s, NULL)",
						$user_id,
						$quiz_id,
						$course_id,
						'',
						0,
						0,
						$attempt_number,
						$now
					)
				);
			}

			$new_id = absint( $wpdb->insert_id );
			if ( ! $new_id && false !== $inserted ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$new_id = absint(
					$wpdb->get_var(
						$wpdb->prepare(
							"SELECT id FROM {$table}
							WHERE user_id = %d AND quiz_id = %d AND attempt_number = %d
								AND (completed_at IS NULL OR completed_at = '0000-00-00 00:00:00' OR completed_at = '')
							ORDER BY id DESC LIMIT 1",
							$user_id,
							$quiz_id,
							$attempt_number
						)
					)
				);
			}

			if ( $new_id ) {
				$row = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$table} WHERE id = %d",
						$new_id
					)
				);
				if ( $row ) {
					return $row;
				}
			}

			if ( class_exists( 'CTA_Database' ) ) {
				delete_option( 'cta_quiz_attempt_schema_v138' );
				CTA_Database::maybe_ensure_quiz_attempt_schema();
			}
		}

		return CTA_Database::get_active_quiz_attempt( $user_id, $quiz_id );
	}

	/**
	 * Build AJAX payload for quiz attempt start.
	 *
	 * @param object $quiz    Quiz row.
	 * @param object $attempt Attempt row.
	 * @return array
	 */
	private function build_attempt_payload( $quiz, $attempt ) {
		$questions = CTA_Database::get_quiz_questions( (int) $quiz->id );

		$time_limit_mins = self::get_time_limit_mins( $quiz );

		return array(
			'quiz_id'            => (int) $quiz->id,
			'attempt_id'         => (int) $attempt->id,
			'course_id'          => (int) $attempt->course_id,
			'time_limit_mins'    => $time_limit_mins,
			'attempt_started_at' => ! empty( $attempt->started_at ) ? (string) $attempt->started_at : '',
			'passing_score'      => (int) $quiz->passing_score ?: 70,
			'max_attempts'       => 0,
			'question_count'     => count( $questions ),
			'html'               => $this->render_quiz_questions( $quiz, $attempt, $questions ),
		);
	}

	/**
	 * Resolve the effective quiz time limit in minutes (0 = untimed).
	 *
	 * @param object|null $quiz Quiz row.
	 * @return int
	 */
	public static function get_time_limit_mins( $quiz ) {
		if ( ! $quiz || ! isset( $quiz->time_limit_mins ) ) {
			return 0;
		}

		return max( 0, absint( $quiz->time_limit_mins ) );
	}

	/**
	 * Format a quiz time limit for the start panel.
	 *
	 * @param int $minutes Time limit in minutes.
	 * @return string
	 */
	public static function format_time_limit_label( $minutes ) {
		$minutes = max( 0, absint( $minutes ) );
		if ( $minutes <= 0 ) {
			return __( 'No limit', 'cta-lms' );
		}

		return sprintf(
			/* translators: %d: number of minutes */
			_n( '%d minute', '%d minutes', $minutes, 'cta-lms' ),
			$minutes
		);
	}

	/**
	 * Get first passing attempt from list.
	 *
	 * @param array $attempts Attempt rows.
	 * @return object|null
	 */
	private function get_passed_attempt( $attempts ) {
		foreach ( $attempts as $attempt ) {
			if ( (int) $attempt->passed ) {
				return $attempt;
			}
		}

		return null;
	}

	/**
	 * Sanitize star rating 1-5.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	private function sanitize_rating( $value ) {
		$rating = absint( $value );

		if ( $rating < 1 || $rating > 5 ) {
			return 0;
		}

		return $rating;
	}

	/**
	 * Render simple message state block.
	 *
	 * @param string $title   Title.
	 * @param string $message Message.
	 * @param string $url     Button URL.
	 * @param string $label   Button label.
	 * @return string
	 */
	private function render_message_state( $title, $message, $url, $label ) {
		ob_start();
		?>
		<div class="cta-plugin-wrapper">
		<div class="cta-quiz-page">
			<div class="cta-empty-state">
				<h2><?php echo esc_html( $title ); ?></h2>
				<p><?php echo esc_html( $message ); ?></p>
				<?php if ( $url ) : ?>
					<a href="<?php echo esc_url( $url ); ?>" class="btn btn-primary"><?php echo esc_html( $label ); ?></a>
				<?php endif; ?>
			</div>
		</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Redirect markup.
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
	 * Get login URL.
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
	 * Get student dashboard URL.
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
	 * Get course player URL.
	 *
	 * @param int $course_id Course ID.
	 * @return string
	 */
	private function get_player_url( $course_id ) {
		$page_id = absint( get_option( 'cta_course_player_page_id', 0 ) );

		if ( ! $page_id ) {
			return '';
		}

		return add_query_arg( 'course_id', $course_id, get_permalink( $page_id ) );
	}

	/**
	 * Get single course page URL.
	 *
	 * @param int $course_id Course ID.
	 * @return string
	 */
	private function get_course_page_url( $course_id ) {
		$page_id = absint( get_option( 'cta_single_course_page_id', 0 ) );

		if ( ! $page_id ) {
			$courses_page = absint( get_option( 'cta_courses_page_id', 0 ) );
			return $courses_page ? get_permalink( $courses_page ) : '';
		}

		return add_query_arg( 'course_id', $course_id, get_permalink( $page_id ) );
	}
}
}
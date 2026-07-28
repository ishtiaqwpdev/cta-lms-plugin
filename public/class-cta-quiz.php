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
		$quiz       = CTA_Database::get_quiz_by_course( $course_id );

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

		if ( (int) $enrollment->progress < 100 ) {
			return $this->render_message_state(
				__( 'Complete All Modules First', 'cta-lms' ),
				__( 'Finish every module before starting the course quiz.', 'cta-lms' ),
				$this->get_player_url( $course_id ),
				__( 'Back to Course', 'cta-lms' )
			);
		}

		$questions = $quiz ? CTA_Database::get_quiz_questions( (int) $quiz->id ) : array();

		if ( ! $quiz || empty( $questions ) ) {
			return $this->render_message_state(
				__( 'Quiz Coming Soon', 'cta-lms' ),
				__( 'The final quiz for this course has not been published yet. Please check back soon.', 'cta-lms' ),
				$this->get_player_url( $course_id ),
				__( 'Back to Course', 'cta-lms' )
			);
		}

		$attempts        = CTA_Database::get_user_quiz_attempts( $user_id, (int) $quiz->id );
		$active_attempt  = CTA_Database::get_active_quiz_attempt( $user_id, (int) $quiz->id );
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
			? CTA_Course_Attestation::default_attestation_text()
			: '';
		$is_exam_prep    = class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course );

		// Recover certificate only when evaluation + attestation are both complete.
		if ( ! $is_exam_prep && $passed_attempt && $evaluation && $attestation && ! $certificate ) {
			$certificate = CTA_Certificates::generate( $user_id, $course_id, $evaluation );
		}

		if ( $is_exam_prep && $passed_attempt ) {
			$view_state = 'exam_complete';
		} elseif ( $certificate && $evaluation && $attestation && $passed_attempt ) {
			$view_state = 'certificate_ready';
		} elseif ( $passed_attempt && $evaluation && ! $attestation ) {
			$view_state = 'attestation';
		} elseif ( $passed_attempt && ! $evaluation ) {
			$view_state = 'evaluation';
		} elseif ( $active_attempt ) {
			$view_state = 'in_progress';
		}

		$dashboard_url = $this->get_dashboard_url();
		$player_url    = $this->get_player_url( $course_id );
		$quiz_handler  = $this;
		$question_count = count( $questions );
		// Quizzes are untimed by policy; ignore any legacy time_limit_mins values.
		$time_limit_label = __( 'No limit', 'cta-lms' );
		$attempts_label   = __( 'Unlimited', 'cta-lms' );

		ob_start();
		include CTA_PLUGIN_DIR . 'templates/quiz.php';
		return ob_get_clean();
	}

	/**
	 * AJAX: start a new quiz attempt.
	 */
	public function ajax_start_quiz() {
		check_ajax_referer( 'cta_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to continue.', 'cta-lms' ) ) );
		}

		$course_id = absint( wp_unslash( $_POST['course_id'] ?? 0 ) );
		$user_id   = get_current_user_id();
		$check     = $this->validate_quiz_access( $user_id, $course_id );

		if ( is_wp_error( $check ) ) {
			wp_send_json_error( array( 'message' => $check->get_error_message() ) );
		}

		/** @var object $quiz */
		$quiz       = $check['quiz'];
		$attempts   = CTA_Database::get_user_quiz_attempts( $user_id, (int) $quiz->id );
		$active     = CTA_Database::get_active_quiz_attempt( $user_id, (int) $quiz->id );

		if ( $active ) {
			wp_send_json_success( $this->build_attempt_payload( $quiz, $active ) );
		}

		if ( $this->get_passed_attempt( $attempts ) ) {
			wp_send_json_error( array( 'message' => __( 'You have already passed this quiz.', 'cta-lms' ) ) );
		}

		// max_attempts of 0 (or any unset/legacy value) means unlimited failed retakes.
		// Only a passing attempt blocks further starts.

		global $wpdb;

		$attempt_number = count( $attempts ) + 1;

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'cta_quiz_attempts',
			array(
				'user_id'        => $user_id,
				'quiz_id'        => (int) $quiz->id,
				'course_id'      => $course_id,
				'attempt_number' => $attempt_number,
				'started_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%d', '%s' )
		);

		if ( ! $inserted ) {
			wp_send_json_error( array( 'message' => __( 'Unable to start quiz.', 'cta-lms' ) ) );
		}

		$attempt = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}cta_quiz_attempts WHERE id = %d",
				(int) $wpdb->insert_id
			)
		);

		wp_send_json_success( $this->build_attempt_payload( $quiz, $attempt ) );
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

		if ( ! $attempt || ! empty( $attempt->completed_at ) ) {
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

		$sanitized = array();
		$correct   = 0;
		$total     = count( $questions );
		$revealed  = array();

		foreach ( $questions as $question ) {
			$qid    = (int) $question->id;
			$answer = isset( $answers_in[ $qid ] ) ? sanitize_text_field( $answers_in[ $qid ] ) : '';
			$answer = in_array( $answer, array( 'a', 'b', 'c', 'd' ), true ) ? $answer : '';

			$sanitized[ $qid ] = $answer;

			if ( $answer && $answer === $question->correct_option ) {
				++$correct;
			}

			$revealed[] = array(
				'question_id'    => $qid,
				'user_answer'    => $answer,
				'correct_option' => $question->correct_option,
				'explanation'    => $question->explanation,
				'is_correct'     => ( $answer === $question->correct_option ),
			);
		}

		$score  = $total > 0 ? (int) round( ( $correct / $total ) * 100 ) : 0;
		$passed = $score >= (int) $quiz->passing_score ? 1 : 0;

		$wpdb->update(
			$wpdb->prefix . 'cta_quiz_attempts',
			array(
				'answers'      => wp_json_encode( $sanitized ),
				'score'        => $score,
				'passed'       => $passed,
				'completed_at' => current_time( 'mysql' ),
			),
			array( 'id' => $attempt_id ),
			array( '%s', '%d', '%d', '%s' ),
			array( '%d' )
		);

		if ( $passed ) {
			$course    = CTA_Database::get_course( (int) $attempt->course_id );
			$next_step = 'evaluation';

			// Exam prep: no CE evaluation / certificate path.
			if ( class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course ) ) {
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

		$timezone = sanitize_text_field( wp_unslash( $_POST['timezone'] ?? '' ) );
		if ( $timezone && ! $this->is_valid_timezone( $timezone ) ) {
			$timezone = '';
		}

		$user         = get_userdata( $user_id );
		$student_name = function_exists( 'cta_lms_get_user_legal_name' )
			? cta_lms_get_user_legal_name( $user_id )
			: ( $user ? $user->display_name : '' );

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
				'student_email'     => $user ? (string) $user->user_email : '',
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

		$agreed = ! empty( $_POST['agree'] );
		if ( ! $agreed ) {
			wp_send_json_error( array( 'message' => __( 'You must agree to the attestation to continue.', 'cta-lms' ) ) );
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

		// Statement text is always the server-side CE standard wording (never rely on a client hidden field).
		$attestation_text = CTA_Course_Attestation::default_attestation_text();
		$signature_name   = sanitize_text_field(
			wp_unslash(
				$_POST['signature_name']
				?? $_POST['attestation_signature']
				?? $_POST['typed_name']
				?? ''
			)
		);

		if ( '' === trim( $signature_name ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please type your full legal name as your electronic signature to complete this attestation.', 'cta-lms' ),
					'code'    => 'cta_attestation_signature',
				)
			);
		}

		$result = CTA_Course_Attestation::submit( $user_id, $course_id, $attestation_text, $signature_name );

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

			if ( in_array( $type, array( 'rating', 'likert' ), true ) ) {
				$rating = absint( is_array( $value ) ? 0 : $value );
				$allowed_keys = array_map( 'strval', array_keys( (array) ( $question['options'] ?? array() ) ) );
				if ( empty( $allowed_keys ) ) {
					$allowed_keys = array( '1', '2', '3', '4', '5' );
				}
				if ( ! empty( $question['required'] ) && ( $rating < 1 || ! in_array( (string) $rating, $allowed_keys, true ) && ! in_array( (string) $value, $allowed_keys, true ) ) ) {
					// Accept either numeric 1-5 or option keys like "5" for Excellent.
					$opt_val = sanitize_text_field( (string) ( is_array( $value ) ? '' : $value ) );
					if ( '' === $opt_val || ! in_array( $opt_val, $allowed_keys, true ) ) {
						return new WP_Error(
							'missing_field',
							sprintf(
								/* translators: %s: question label */
								__( 'Please answer: %s', 'cta-lms' ),
								$question['label']
							)
						);
					}
					$clean[ $id ] = $opt_val;
				} else {
					$opt_val = sanitize_text_field( (string) ( is_array( $value ) ? '' : $value ) );
					$clean[ $id ] = in_array( $opt_val, $allowed_keys, true ) ? $opt_val : ( $rating > 0 ? (string) $rating : '' );
				}
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
					$summary['would_recommend'] = ( 'yes' === $clean[ $id ] || '1' === (string) $clean[ $id ] ) ? 1 : 0;
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
	 * Validate quiz access for a user and course.
	 *
	 * @param int  $user_id           User ID.
	 * @param int  $course_id         Course ID.
	 * @param bool $require_complete  Require 100% module progress.
	 * @return array|WP_Error
	 */
	private function validate_quiz_access( $user_id, $course_id, $require_complete = true ) {
		$enrollment = CTA_Database::get_user_enrollment( $user_id, $course_id );
		$quiz       = CTA_Database::get_quiz_by_course( $course_id );
		$course     = CTA_Database::get_course( $course_id );

		if ( ! $enrollment ) {
			return new WP_Error( 'not_enrolled', __( 'You are not enrolled in this course.', 'cta-lms' ) );
		}

		if ( class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course ) ) {
			if ( ! CTA_Exam_Access::has_active_access( $user_id, $course_id ) ) {
				return new WP_Error( 'exam_expired', __( 'Your access to this Exam Preparation Program has expired.', 'cta-lms' ) );
			}
		}

		if ( $require_complete && (int) $enrollment->progress < 100 ) {
			return new WP_Error( 'incomplete', __( 'Complete all modules first.', 'cta-lms' ) );
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
	 * Build AJAX payload for quiz attempt start.
	 *
	 * @param object $quiz    Quiz row.
	 * @param object $attempt Attempt row.
	 * @return array
	 */
	private function build_attempt_payload( $quiz, $attempt ) {
		$questions = CTA_Database::get_quiz_questions( (int) $quiz->id );
		$safe      = array();

		foreach ( $questions as $question ) {
			$safe[] = array(
				'id'            => (int) $question->id,
				'question_text' => $question->question_text,
				'option_a'      => $question->option_a,
				'option_b'      => $question->option_b,
				'option_c'      => $question->option_c,
				'option_d'      => $question->option_d,
				'order_index'   => (int) $question->order_index,
			);
		}

		return array(
			'quiz_id'         => (int) $quiz->id,
			'attempt_id'      => (int) $attempt->id,
			'course_id'       => (int) $attempt->course_id,
			'time_limit_mins' => 0,
			'passing_score'   => (int) $quiz->passing_score ?: 70,
			'max_attempts'    => 0,
			'question_count'  => count( $safe ),
			'questions'       => $safe,
			'html'            => $this->render_quiz_questions( $quiz, $attempt, $questions ),
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
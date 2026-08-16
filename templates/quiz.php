<?php
/**
 * Course quiz page template.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cert_print_url    = ( $certificate && class_exists( 'CTA_Certificates' ) )
	? CTA_Certificates::get_print_url( (int) $certificate->id, true )
	: '';
$cert_download_url = ( $certificate && class_exists( 'CTA_Certificates' ) )
	? CTA_Certificates::get_download_url( (int) $certificate->id )
	: '';
$cert_url          = $cert_print_url;
$is_exam_prep      = ! empty( $is_exam_prep );
$display_title     = ( ! empty( $course ) && function_exists( 'cta_lms_get_course_display_title' ) )
	? cta_lms_get_course_display_title( $course )
	: ( ! empty( $course->title ) ? (string) $course->title : '' );
if ( empty( $evaluation_questions ) || ! is_array( $evaluation_questions ) ) {
	$evaluation_questions = CTA_Quiz::get_evaluation_questions();
}
?>
<div class="cta-plugin-wrapper">
<div
	class="cta-lms cta-quiz-page"
	id="cta-quiz-app"
	data-course-id="<?php echo esc_attr( $course->id ); ?>"
	data-quiz-id="<?php echo esc_attr( $quiz->id ); ?>"
	data-attempt-id="<?php echo esc_attr( $active_attempt ? $active_attempt->id : 0 ); ?>"
	data-time-limit="<?php echo esc_attr( (string) (int) ( $time_limit_mins ?? 0 ) ); ?>"
	<?php if ( ! empty( $attempt_started_at ) ) : ?>
	data-attempt-started-at="<?php echo esc_attr( $attempt_started_at ); ?>"
	<?php endif; ?>
	data-passing-score="<?php echo esc_attr( (int) $quiz->passing_score ?: 70 ); ?>"
	data-question-count="<?php echo esc_attr( $question_count ); ?>"
	data-view-state="<?php echo esc_attr( $view_state ); ?>"
	data-exam-prep="<?php echo ! empty( $is_exam_prep ) ? '1' : '0'; ?>"
	data-ce-teaching-points="<?php echo ! empty( $ce_teaching_points ) ? '1' : '0'; ?>"
	<?php if ( ! empty( $dashboard_url ) ) : ?>
		data-dashboard-url="<?php echo esc_url( $dashboard_url ); ?>"
	<?php endif; ?>
>
	<div class="cta-quiz-header">
		<p class="course-player__back">
			<?php if ( $player_url ) : ?>
				<a href="<?php echo esc_url( $player_url ); ?>">&larr; <?php echo ! empty( $is_exam_prep ) ? esc_html__( 'Back to Practice Exams', 'cta-lms' ) : esc_html__( 'Back to Course', 'cta-lms' ); ?></a>
			<?php endif; ?>
		</p>
		<h1 class="cta-quiz-course-title"><?php echo esc_html( $display_title ); ?></h1>
		<div class="cta-quiz-timer" id="cta-quiz-timer" hidden aria-hidden="true"></div>
	</div>

	<div class="cta-quiz-panel <?php echo 'start' === $view_state ? 'cta-quiz-panel--active' : ''; ?>" data-quiz-panel="start" <?php echo 'start' !== $view_state ? 'hidden' : ''; ?>>
		<div class="card cta-quiz-start-card">
			<h2><?php echo esc_html( $quiz->title ); ?></h2>
			<?php if ( ! empty( $exam_instructions ) ) : ?>
				<div class="cta-quiz-exam-instructions" role="note">
					<?php foreach ( preg_split( "/\r\n|\r|\n/", (string) $exam_instructions ) as $exam_instruction_paragraph ) : ?>
						<?php
						$exam_instruction_paragraph = trim( (string) $exam_instruction_paragraph );
						if ( '' === $exam_instruction_paragraph ) {
							continue;
						}
						?>
						<p><?php echo esc_html( $exam_instruction_paragraph ); ?></p>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<div class="cta-quiz-info-grid">
				<div><strong><?php echo esc_html__( 'Questions', 'cta-lms' ); ?></strong><span><?php echo esc_html( (string) $question_count ); ?></span></div>
				<div><strong><?php echo esc_html__( 'Passing Score', 'cta-lms' ); ?></strong><span><?php echo esc_html( (int) $quiz->passing_score ?: 70 ); ?>%</span></div>
				<div><strong><?php echo esc_html__( 'Time Limit', 'cta-lms' ); ?></strong><span><?php echo esc_html( $time_limit_label ); ?></span></div>
				<div><strong><?php echo esc_html__( 'Attempts', 'cta-lms' ); ?></strong><span><?php echo esc_html( $attempts_label ); ?></span></div>
			</div>
			<?php if ( $attempt_count > 0 ) : ?>
				<p class="cta-quiz-last-attempt">
					<?php
					printf(
						/* translators: %d: number of previous attempts */
						esc_html__( 'Previous attempts: %d', 'cta-lms' ),
						(int) $attempt_count
					);
					?>
				</p>
			<?php endif; ?>
			<?php if ( $last_attempt ) : ?>
				<p class="cta-quiz-last-attempt">
					<?php
					$result_label = (int) $last_attempt->passed
						? esc_html__( 'Passed', 'cta-lms' )
						: esc_html__( 'Failed', 'cta-lms' );
					printf(
						/* translators: 1: score, 2: result */
						esc_html__( 'Last attempt: %1$d%% — %2$s', 'cta-lms' ),
						(int) $last_attempt->score,
						$result_label
					);
					?>
				</p>
			<?php endif; ?>
			<form method="post" id="cta-start-quiz-form" class="cta-start-quiz-form" action="">
				<?php wp_nonce_field( 'cta_start_quiz_' . (int) $course->id . '_' . (int) $quiz->id ); ?>
				<input type="hidden" name="cta_start_quiz" value="1" />
				<button type="button" class="btn btn-primary btn--lg" id="cta-start-quiz"><?php echo esc_html__( 'Start Quiz', 'cta-lms' ); ?></button>
				<noscript>
					<button type="submit" class="btn btn-primary btn--lg"><?php echo esc_html__( 'Start Quiz', 'cta-lms' ); ?></button>
				</noscript>
			</form>
		</div>
	</div>

	<div class="cta-quiz-panel <?php echo 'in_progress' === $view_state ? 'cta-quiz-panel--active' : ''; ?>" data-quiz-panel="questions" <?php echo 'in_progress' !== $view_state ? 'hidden' : ''; ?>>
		<p class="cta-quiz-progress" id="cta-quiz-progress"><?php echo esc_html__( 'Questions answered: 0 of 0', 'cta-lms' ); ?></p>
		<p class="cta-quiz-save-status" id="cta-quiz-save-status" role="status" aria-live="polite">
			<?php esc_html_e( 'Answers are saved automatically.', 'cta-lms' ); ?>
		</p>
		<form id="cta-quiz-form" class="cta-quiz-form">
			<div id="cta-quiz-questions">
				<?php
				if ( 'in_progress' === $view_state && $active_attempt ) {
					echo $quiz_handler->render_quiz_questions( $quiz, $active_attempt, $questions ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>
			</div>
			<div class="cta-quiz-submit-section">
				<p class="cta-quiz-submit-warning"><?php echo esc_html__( 'Are you sure? You cannot change answers after submitting.', 'cta-lms' ); ?></p>
				<button type="button" class="btn btn-primary" id="cta-submit-quiz" disabled><?php echo esc_html__( 'Submit Quiz', 'cta-lms' ); ?></button>
			</div>
		</form>
	</div>

	<div class="cta-quiz-panel" data-quiz-panel="result" hidden>
		<div class="cta-quiz-result" id="cta-quiz-result"></div>
	</div>

	<div class="cta-quiz-panel <?php echo 'evaluation' === $view_state ? 'cta-quiz-panel--active' : ''; ?>" data-quiz-panel="evaluation" <?php echo 'evaluation' !== $view_state ? 'hidden' : ''; ?>>
		<?php if ( empty( $is_exam_prep ) ) : ?>
		<div class="card cta-quiz-evaluation">
			<h2><?php echo esc_html__( 'Course Evaluation', 'cta-lms' ); ?></h2>
			<?php
			$cta_inline_attest = class_exists( 'CTA_CE_Completion' )
				&& ! empty( $course->id )
				&& CTA_CE_Completion::evaluation_includes_inline_attestation( (int) $course->id );
			?>
			<p>
				<?php
				echo $cta_inline_attest
					? esc_html__( 'Please complete all required sections of this course-specific evaluation, including the completion attestation. Your CE certificate is issued only after this full submission.', 'cta-lms' )
					: esc_html__( 'Please complete this course-specific evaluation. After submission you will complete a short attestation before your certificate is issued.', 'cta-lms' );
				?>
			</p>
			<form id="cta-evaluation-form" class="cta-evaluation-form cta-evaluation-form--matrix" novalidate>
				<?php
				$cta_eval_user_id = get_current_user_id();
				$cta_eval_user    = $cta_eval_user_id ? get_userdata( $cta_eval_user_id ) : false;
				$cta_eval_prefill = array(
					'participant_cert_name'             => function_exists( 'cta_lms_get_user_legal_name' )
						? (string) cta_lms_get_user_legal_name( $cta_eval_user_id )
						: ( $cta_eval_user ? (string) $cta_eval_user->display_name : '' ),
					'participant_email'                 => $cta_eval_user ? (string) $cta_eval_user->user_email : '',
					'participant_license_type'          => (string) get_user_meta( $cta_eval_user_id, 'cta_license_type', true ),
					'participant_license_number'        => function_exists( 'cta_lms_get_user_license_number' )
						? (string) cta_lms_get_user_license_number( $cta_eval_user_id )
						: (string) get_user_meta( $cta_eval_user_id, 'cta_license_number', true ),
					'participant_completion_date'       => current_time( 'Y-m-d' ),
					'completion_attestation_signature'  => function_exists( 'cta_lms_get_user_legal_name' )
						? (string) cta_lms_get_user_legal_name( $cta_eval_user_id )
						: ( $cta_eval_user ? (string) $cta_eval_user->display_name : '' ),
					'completion_attestation_date'       => current_time( 'Y-m-d' ),
					'sra_attest_signature'              => function_exists( 'cta_lms_get_user_legal_name' )
						? (string) cta_lms_get_user_legal_name( $cta_eval_user_id )
						: ( $cta_eval_user ? (string) $cta_eval_user->display_name : '' ),
					'sra_attest_date'                   => current_time( 'Y-m-d' ),
					'participant_state_jurisdiction'    => (string) get_user_meta( $cta_eval_user_id, 'cta_license_state', true ),
				);
				// Prefill also works when course copies use the camft_ prefix.
				foreach ( array_keys( $cta_eval_prefill ) as $pref_key ) {
					$cta_eval_prefill[ 'camft_' . $pref_key ] = $cta_eval_prefill[ $pref_key ];
				}

				/**
				 * Resolve a prefill value for a question key.
				 *
				 * @param string $qid Question key.
				 * @return string
				 */
				$cta_eval_prefill_value = static function ( $qid ) use ( $cta_eval_prefill ) {
					$qid = (string) $qid;
					return isset( $cta_eval_prefill[ $qid ] ) ? (string) $cta_eval_prefill[ $qid ] : '';
				};

				/**
				 * Normalize a question's display type (same mapping as before).
				 *
				 * @param array $question Question row.
				 * @return string
				 */
				$cta_eval_normalize_type = static function ( $question ) {
					$q_type = isset( $question['type'] ) ? (string) $question['type'] : 'rating';
					if ( 'textarea' === $q_type ) {
						return 'paragraph';
					}
					if ( 'multiple_choice' === $q_type || 'yes_no' === $q_type ) {
						return 'radio';
					}
					return $q_type;
				};

				/**
				 * Resolve options for a question.
				 *
				 * @param array  $question Question row.
				 * @param string $q_type   Normalized type.
				 * @return array
				 */
				$cta_eval_options = static function ( $question, $q_type ) {
					if ( ! empty( $question['options'] ) && is_array( $question['options'] ) ) {
						return $question['options'];
					}
					if ( in_array( $q_type, array( 'rating', 'likert' ), true ) && class_exists( 'CTA_Evaluation_Questions' ) ) {
						return CTA_Evaluation_Questions::default_rating_options();
					}
					return array();
				};

				/**
				 * Whether this question can sit in a compact rating matrix row.
				 *
				 * @param string $q_type  Normalized type.
				 * @param array  $options Option map.
				 * @return bool
				 */
				$cta_eval_is_matrixable = static function ( $q_type, $options ) {
					if ( ! in_array( $q_type, array( 'rating', 'likert', 'radio' ), true ) ) {
						return false;
					}
					if ( count( $options ) < 2 || count( $options ) > 7 ) {
						return false;
					}
					return true;
				};

				// Group consecutive matrixable questions that share the same section + option keys.
				$eval_blocks   = array();
				$matrix_buffer = null;

				$flush_matrix = static function () use ( &$eval_blocks, &$matrix_buffer ) {
					if ( null !== $matrix_buffer && ! empty( $matrix_buffer['questions'] ) ) {
						$eval_blocks[] = $matrix_buffer;
					}
					$matrix_buffer = null;
				};

				foreach ( $evaluation_questions as $question ) {
					$q_type  = $cta_eval_normalize_type( $question );
					$options = $cta_eval_options( $question, $q_type );
					$section = isset( $question['section'] ) ? (string) $question['section'] : '';

					if ( $cta_eval_is_matrixable( $q_type, $options ) ) {
						$option_sig = wp_json_encode( array_map( 'strval', array_keys( $options ) ) );
						if (
							null !== $matrix_buffer
							&& $matrix_buffer['section'] === $section
							&& $matrix_buffer['option_sig'] === $option_sig
						) {
							$matrix_buffer['questions'][] = array(
								'question' => $question,
								'type'     => $q_type,
								'options'  => $options,
							);
							continue;
						}
						$flush_matrix();
						$matrix_buffer = array(
							'kind'       => 'matrix',
							'section'    => $section,
							'option_sig' => $option_sig,
							'options'    => $options,
							'questions'  => array(
								array(
									'question' => $question,
									'type'     => $q_type,
									'options'  => $options,
								),
							),
						);
						continue;
					}

					$flush_matrix();
					$eval_blocks[] = array(
						'kind'     => 'single',
						'section'  => $section,
						'question' => $question,
						'type'     => $q_type,
						'options'  => $options,
					);
				}
				$flush_matrix();

				$current_section = '';
				foreach ( $eval_blocks as $block ) :
					if ( $block['section'] !== $current_section ) :
						$current_section = $block['section'];
						if ( '' !== $current_section ) :
							?>
							<h3 class="cta-evaluation-section__title"><?php echo esc_html( $current_section ); ?></h3>
							<?php if ( false !== stripos( $current_section, 'Learning Objectives' ) ) : ?>
								<p class="cta-evaluation-section__intro"><?php echo esc_html__( 'Rate your ability to meet each objective as a result of completing this course.', 'cta-lms' ); ?></p>
							<?php elseif ( false !== stripos( $current_section, 'Rating Scale' ) ) : ?>
								<p class="cta-evaluation-section__intro"><?php echo esc_html__( '1 = Strongly Disagree, 2 = Disagree, 3 = Neutral, 4 = Agree, 5 = Strongly Agree, N/A = Not Applicable.', 'cta-lms' ); ?></p>
							<?php endif; ?>
						<?php
						endif;
					endif;

					if ( 'matrix' === $block['kind'] ) :
						$scale_options = $block['options'];
						?>
						<div class="cta-evaluation-matrix-wrap">
							<table class="cta-evaluation-matrix">
								<thead>
									<tr>
										<th scope="col" class="cta-evaluation-matrix__prompt-col">
											<span class="screen-reader-text"><?php echo esc_html__( 'Question', 'cta-lms' ); ?></span>
										</th>
										<?php foreach ( $scale_options as $value => $option_label ) : ?>
											<th scope="col" class="cta-evaluation-matrix__scale-col" title="<?php echo esc_attr( $option_label ); ?>">
												<span class="cta-evaluation-matrix__scale-num"><?php echo esc_html( 'na' === (string) $value ? 'N/A' : (string) $value ); ?></span>
												<span class="cta-evaluation-matrix__scale-label"><?php echo esc_html( $option_label ); ?></span>
											</th>
										<?php endforeach; ?>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $block['questions'] as $row ) :
										$question = $row['question'];
										$q_type   = $row['type'];
										$options  = $row['options'];
										?>
										<tr class="form-group cta-evaluation-question cta-evaluation-matrix__row" data-question-id="<?php echo esc_attr( $question['id'] ); ?>" data-question-type="<?php echo esc_attr( $q_type ); ?>">
											<th scope="row" class="cta-evaluation-matrix__prompt" id="eval-label-<?php echo esc_attr( $question['id'] ); ?>">
												<?php echo esc_html( $question['label'] ); ?>
												<?php if ( ! empty( $question['required'] ) ) : ?>
													<span class="cta-required" aria-hidden="true">*</span>
												<?php endif; ?>
											</th>
											<?php foreach ( $options as $value => $option_label ) : ?>
												<td class="cta-evaluation-matrix__cell">
													<label class="cta-evaluation-matrix__choice" title="<?php echo esc_attr( $option_label ); ?>">
														<input
															type="radio"
															name="responses[<?php echo esc_attr( $question['id'] ); ?>]"
															value="<?php echo esc_attr( (string) $value ); ?>"
															aria-label="<?php echo esc_attr( $option_label ); ?>"
															<?php echo ! empty( $question['required'] ) ? 'required' : ''; ?>
														>
														<span class="cta-evaluation-matrix__choice-face" aria-hidden="true"><?php echo esc_html( 'na' === (string) $value ? 'N/A' : (string) $value ); ?></span>
													</label>
												</td>
											<?php endforeach; ?>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
							<p class="cta-evaluation-matrix__legend" aria-hidden="true">
								<?php
								$legend_parts = array();
								foreach ( $scale_options as $value => $option_label ) {
									$legend_parts[] = esc_html( (string) $value ) . ' = ' . esc_html( $option_label );
								}
								echo implode( ' · ', $legend_parts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
								?>
							</p>
						</div>
						<?php
					else :
						$question = $block['question'];
						$q_type   = $block['type'];
						$options  = $block['options'];
						?>
						<div class="form-group cta-evaluation-question" data-question-id="<?php echo esc_attr( $question['id'] ); ?>" data-question-type="<?php echo esc_attr( $q_type ); ?>">
							<?php if ( 'info' === $q_type ) : ?>
								<?php
								$info_value = '';
								if ( ! empty( $options['display'] ) ) {
									$info_value = (string) $options['display'];
								}
								?>
								<p class="cta-evaluation-info">
									<strong class="cta-evaluation-info__label"><?php echo esc_html( $question['label'] ); ?></strong>
									<?php if ( '' !== $info_value ) : ?>
										<span class="cta-evaluation-info__value"><?php echo esc_html( $info_value ); ?></span>
									<?php endif; ?>
								</p>
							<?php elseif ( in_array( $q_type, array( 'paragraph', 'short_text' ), true ) ) : ?>
								<label class="form-label" for="eval-<?php echo esc_attr( $question['id'] ); ?>">
									<?php echo esc_html( $question['label'] ); ?>
									<?php if ( ! empty( $question['required'] ) ) : ?>
										<span class="cta-required" aria-hidden="true">*</span>
									<?php endif; ?>
								</label>
								<?php if ( 'short_text' === $q_type ) : ?>
									<?php
									$pref_val   = $cta_eval_prefill_value( $question['id'] );
									$qid_l      = strtolower( (string) $question['id'] );
									$input_type = 'text';
									if ( false !== strpos( $qid_l, 'email' ) ) {
										$input_type = 'email';
									} elseif ( false !== strpos( $qid_l, 'date' ) ) {
										$input_type = 'date';
									}
									?>
									<input
										type="<?php echo esc_attr( $input_type ); ?>"
										id="eval-<?php echo esc_attr( $question['id'] ); ?>"
										name="responses[<?php echo esc_attr( $question['id'] ); ?>]"
										class="form-input"
										value="<?php echo esc_attr( $pref_val ); ?>"
										<?php echo ! empty( $question['required'] ) ? 'required' : ''; ?>
									>
								<?php else : ?>
									<textarea
										id="eval-<?php echo esc_attr( $question['id'] ); ?>"
										name="responses[<?php echo esc_attr( $question['id'] ); ?>]"
										class="form-input"
										rows="4"
										<?php echo ! empty( $question['required'] ) ? 'required' : ''; ?>
									></textarea>
								<?php endif; ?>
							<?php elseif ( 'dropdown' === $q_type ) : ?>
								<label class="form-label" for="eval-<?php echo esc_attr( $question['id'] ); ?>">
									<?php echo esc_html( $question['label'] ); ?>
									<?php if ( ! empty( $question['required'] ) ) : ?>
										<span class="cta-required" aria-hidden="true">*</span>
									<?php endif; ?>
								</label>
								<?php $pref_val = $cta_eval_prefill_value( $question['id'] ); ?>
								<select
									id="eval-<?php echo esc_attr( $question['id'] ); ?>"
									name="responses[<?php echo esc_attr( $question['id'] ); ?>]"
									class="form-select"
									<?php echo ! empty( $question['required'] ) ? 'required' : ''; ?>
								>
									<option value=""><?php echo esc_html__( 'Select an option', 'cta-lms' ); ?></option>
									<?php foreach ( $options as $value => $option_label ) : ?>
										<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $pref_val, (string) $value ); ?>><?php echo esc_html( $option_label ); ?></option>
									<?php endforeach; ?>
								</select>
							<?php elseif ( 'checkbox' === $q_type ) : ?>
								<span class="form-label" id="eval-label-<?php echo esc_attr( $question['id'] ); ?>">
									<?php echo esc_html( $question['label'] ); ?>
									<?php if ( ! empty( $question['required'] ) ) : ?>
										<span class="cta-required" aria-hidden="true">*</span>
									<?php endif; ?>
								</span>
								<div class="cta-evaluation-options" role="group" aria-labelledby="eval-label-<?php echo esc_attr( $question['id'] ); ?>">
									<?php foreach ( $options as $value => $option_label ) : ?>
										<label class="cta-evaluation-option">
											<input
												type="checkbox"
												name="responses[<?php echo esc_attr( $question['id'] ); ?>][]"
												value="<?php echo esc_attr( (string) $value ); ?>"
											>
											<span><?php echo esc_html( $option_label ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							<?php else : ?>
								<span class="form-label" id="eval-label-<?php echo esc_attr( $question['id'] ); ?>">
									<?php echo esc_html( $question['label'] ); ?>
									<?php if ( ! empty( $question['required'] ) ) : ?>
										<span class="cta-required" aria-hidden="true">*</span>
									<?php endif; ?>
								</span>
								<div class="cta-evaluation-options cta-evaluation-options--inline" role="radiogroup" aria-labelledby="eval-label-<?php echo esc_attr( $question['id'] ); ?>">
									<?php foreach ( $options as $value => $option_label ) : ?>
										<label class="cta-evaluation-option">
											<input
												type="radio"
												name="responses[<?php echo esc_attr( $question['id'] ); ?>]"
												value="<?php echo esc_attr( (string) $value ); ?>"
												<?php echo ! empty( $question['required'] ) ? 'required' : ''; ?>
											>
											<span><?php echo esc_html( $option_label ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
						<?php
					endif;
				endforeach;
				?>
				<button type="button" class="btn btn-primary" id="cta-submit-evaluation"><?php echo esc_html__( 'Submit Evaluation', 'cta-lms' ); ?></button>
			</form>
		</div>
		<?php endif; ?>
	</div>

	<div class="cta-quiz-panel <?php echo ( isset( $view_state ) && 'attestation' === $view_state ) ? 'cta-quiz-panel--active' : ''; ?>" data-quiz-panel="attestation" <?php echo ( ! isset( $view_state ) || 'attestation' !== $view_state ) ? 'hidden' : ''; ?>>
		<?php if ( empty( $is_exam_prep ) ) : ?>
		<div class="card cta-quiz-attestation">
			<h2><?php echo esc_html__( 'Mandatory Completion Attestation', 'cta-lms' ); ?></h2>
			<p><?php echo esc_html__( 'A CE certificate cannot be issued until you check the attestation and complete the Typed Name field.', 'cta-lms' ); ?></p>
			<form id="cta-attestation-form" class="cta-attestation-form" novalidate>
				<?php
				$attestation_statement = ! empty( $attestation_text )
					? (string) $attestation_text
					: ( class_exists( 'CTA_Course_Attestation' ) ? CTA_Course_Attestation::default_attestation_text( ! empty( $course->title ) ? (string) $course->title : '' ) : '' );
				$attestation_name_prefill = '';
				if ( function_exists( 'cta_lms_get_user_legal_name' ) ) {
					$attestation_name_prefill = (string) cta_lms_get_user_legal_name( get_current_user_id() );
				}
				if ( '' === $attestation_name_prefill ) {
					$current = wp_get_current_user();
					$attestation_name_prefill = $current && $current->display_name ? (string) $current->display_name : '';
				}
				$attestation_date_prefill = current_time( 'Y-m-d' );
				?>
				<label class="cta-attestation-agree cta-attestation-agree--statement">
					<input type="checkbox" id="cta-attestation-agree" name="agree" value="1" required>
					<span id="cta-attestation-statement"><?php echo esc_html( $attestation_statement ); ?></span>
				</label>

				<div class="form-group cta-attestation-signature">
					<label class="form-label" for="cta-attestation-signature">
						<?php echo esc_html__( 'Typed Name', 'cta-lms' ); ?>
						<span class="cta-required" aria-hidden="true">*</span>
					</label>
					<input
						type="text"
						id="cta-attestation-signature"
						name="signature_name"
						class="form-input"
						autocomplete="name"
						required
						placeholder="<?php echo esc_attr__( 'Type your full legal name', 'cta-lms' ); ?>"
						value="<?php echo esc_attr( $attestation_name_prefill ); ?>"
					>
					<p class="form-hint" style="margin-top:0.35rem;font-size:0.85em;opacity:0.85;">
						<?php echo esc_html__( 'This typed name serves as your electronic signature.', 'cta-lms' ); ?>
					</p>
				</div>

				<div class="form-group cta-attestation-date">
					<label class="form-label" for="cta-attestation-date">
						<?php echo esc_html__( 'Date', 'cta-lms' ); ?>
						<span class="cta-required" aria-hidden="true">*</span>
					</label>
					<input
						type="date"
						id="cta-attestation-date"
						name="signature_date"
						class="form-input"
						required
						value="<?php echo esc_attr( $attestation_date_prefill ); ?>"
					>
				</div>

				<button type="button" class="btn btn-primary" id="cta-submit-attestation"><?php echo esc_html__( 'Submit Attestation & Get Certificate', 'cta-lms' ); ?></button>
			</form>
		</div>
		<?php endif; ?>
	</div>

	<div class="cta-quiz-panel <?php echo 'exam_complete' === $view_state ? 'cta-quiz-panel--active' : ''; ?>" data-quiz-panel="exam_complete" <?php echo 'exam_complete' !== $view_state ? 'hidden' : ''; ?>>
		<div class="cta-quiz-certificate-ready card">
			<div class="cta-quiz-certificate-ready__icon" aria-hidden="true">✓</div>
			<h2><?php echo esc_html__( 'Assessment complete!', 'cta-lms' ); ?></h2>
			<p><?php echo esc_html__( 'Great work — you completed this Exam Preparation assessment. Answer rationales are shown after each attempt.', 'cta-lms' ); ?></p>
			<?php if ( $last_attempt && (int) $last_attempt->passed ) : ?>
				<p><?php echo esc_html__( 'Your score:', 'cta-lms' ); ?> <strong><?php echo esc_html( (string) (int) $last_attempt->score ); ?>%</strong></p>
			<?php endif; ?>
			<button type="button" class="btn btn-primary" id="cta-retake-exam-quiz"><?php echo esc_html__( 'Retake This Assessment', 'cta-lms' ); ?></button>
			<?php if ( $player_url ) : ?>
				<a href="<?php echo esc_url( $player_url ); ?>" class="btn btn-outline"><?php echo esc_html__( 'Back to Practice Exams', 'cta-lms' ); ?></a>
			<?php endif; ?>
			<?php if ( $dashboard_url ) : ?>
				<a href="<?php echo esc_url( $dashboard_url ); ?>" class="btn btn-outline"><?php echo esc_html__( 'Return to Dashboard', 'cta-lms' ); ?></a>
			<?php endif; ?>
		</div>
	</div>

	<div class="cta-quiz-panel <?php echo 'certificate_ready' === $view_state ? 'cta-quiz-panel--active' : ''; ?>" data-quiz-panel="certificate" <?php echo 'certificate_ready' !== $view_state ? 'hidden' : ''; ?>>
		<div class="cta-quiz-certificate-ready card">
			<div class="cta-quiz-certificate-ready__icon" aria-hidden="true">🏆</div>
			<h2><?php echo esc_html__( 'Your certificate is ready!', 'cta-lms' ); ?></h2>
			<?php if ( $certificate ) : ?>
				<p><?php echo esc_html__( 'Certificate number:', 'cta-lms' ); ?> <strong id="cta-certificate-number"><?php echo esc_html( $certificate->certificate_number ); ?></strong></p>
			<?php else : ?>
				<p><?php echo esc_html__( 'Certificate number:', 'cta-lms' ); ?> <strong id="cta-certificate-number"></strong></p>
			<?php endif; ?>
			<div id="cta-certificate-actions" class="cta-certificate-actions">
				<?php if ( $certificate && ( $cert_print_url || $cert_download_url ) ) : ?>
					<?php if ( $cert_print_url ) : ?>
						<a href="<?php echo esc_url( $cert_print_url ); ?>" class="btn btn-primary cta-print-cert-btn" data-certificate-id="<?php echo esc_attr( $certificate->id ); ?>" data-cert-action="print" target="_blank" rel="noopener">
							<?php echo esc_html__( 'Print / Save as PDF', 'cta-lms' ); ?>
						</a>
					<?php endif; ?>
					<?php if ( $cert_download_url ) : ?>
						<a href="<?php echo esc_url( $cert_download_url ); ?>" class="btn btn-outline cta-download-cert-btn" data-certificate-id="<?php echo esc_attr( $certificate->id ); ?>" data-cert-action="download" rel="noopener">
							<?php echo esc_html__( 'Download Certificate', 'cta-lms' ); ?>
						</a>
					<?php endif; ?>
				<?php endif; ?>
			</div>
			<?php if ( $dashboard_url ) : ?>
				<a href="<?php echo esc_url( $dashboard_url ); ?>" class="btn btn-outline"><?php echo esc_html__( 'Return to Dashboard', 'cta-lms' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</div>
</div>

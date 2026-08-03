<?php
/**
 * Single course detail template.
 *
 * @package CTA_LMS
 *
 * @var object $course
 * @var array  $modules
 * @var array  $objectives
 * @var bool   $is_enrolled
 * @var string $player_url
 * @var string $courses_url
 * @var int    $total_mins
 * @var bool   $payment_success
 * @var string $preview_video
 * @var object|null $quiz
 * @var array  $quiz_questions
 * @var string $login_url
 * @var bool   $is_free_course
 * @var CTA_Student_Dashboard $video_helper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ce_hours = rtrim( rtrim( number_format( (float) $course->ce_hours, 1, '.', '' ), '0' ), '.' );
$duration_hours = $total_mins > 0 ? round( $total_mins / 60, 1 ) : $course->ce_hours;
$admin_name = get_option( 'cta_admin_name', 'Candice Fuimaono, MS, LMFT' );
$is_exam_prep = class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course );
$access_months = (int) ( $course->access_period_months ?? 6 );
?>
<div class="cta-plugin-wrapper">
<div class="cta-lms cta-single-course">
	<?php if ( ! empty( $payment_success ) ) : ?>
		<div class="cta-notice cta-notice--success" role="status">
			<p>
				<?php
				echo $is_exam_prep
					? esc_html__( 'Payment successful! Your Exam Preparation Program access is active. Start studying below.', 'cta-lms' )
					: esc_html__( 'Payment successful! You are now enrolled. Start learning below.', 'cta-lms' );
				?>
			</p>
		</div>
	<?php endif; ?>

	<section class="course-hero" aria-labelledby="course-hero-title">
		<div class="course-hero__bg" aria-hidden="true"></div>
		<div class="cta-container course-hero__layout">
			<div class="course-hero__content">
				<nav class="course-hero__breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'cta-lms' ); ?>">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'cta-lms' ); ?></a>
					<span class="course-hero__breadcrumb-separator" aria-hidden="true">/</span>
					<a href="<?php echo esc_url( $courses_url ); ?>"><?php echo $is_exam_prep ? esc_html__( 'Exam Prep', 'cta-lms' ) : esc_html__( 'CE Courses', 'cta-lms' ); ?></a>
					<span class="course-hero__breadcrumb-separator" aria-hidden="true">/</span>
					<span class="course-hero__breadcrumb-current"><?php echo esc_html( $course->title ); ?></span>
				</nav>
				<div class="course-hero__badges">
					<?php if ( $is_exam_prep ) : ?>
						<span class="badge badge--primary">
							<?php
							printf(
								/* translators: %d: months of access */
								esc_html__( '%d months access', 'cta-lms' ),
								$access_months
							);
							?>
						</span>
						<span class="badge"><?php esc_html_e( 'Non-CE', 'cta-lms' ); ?></span>
					<?php else : ?>
						<span class="badge badge--success"><?php echo esc_html( $ce_hours ); ?> <?php esc_html_e( 'CE Hours', 'cta-lms' ); ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $course->category ) ) : ?>
						<span class="badge badge--primary"><?php echo esc_html( $course->category ); ?></span>
					<?php endif; ?>
				</div>
				<?php
				$syllabus_meta = isset( $syllabus_meta ) && is_array( $syllabus_meta ) ? $syllabus_meta : array();
				$short_description = ! empty( $syllabus_meta['short_description'] )
					? (string) $syllabus_meta['short_description']
					: '';
				$image_alt = ! empty( $syllabus_meta['image_alt'] )
					? (string) $syllabus_meta['image_alt']
					: (string) $course->title;
				?>
				<h1 class="course-hero__title" id="course-hero-title"><?php echo esc_html( $course->title ); ?></h1>
				<?php if ( '' !== $short_description ) : ?>
					<p class="course-hero__summary"><?php echo esc_html( $short_description ); ?></p>
				<?php elseif ( ! empty( $course->description ) ) : ?>
					<p class="course-hero__summary"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $course->description ), 40 ) ); ?></p>
				<?php endif; ?>
				<div class="course-hero__meta">
					<div class="course-hero__instructor">
						<div class="course-hero__instructor-avatar" aria-hidden="true"><?php echo esc_html( strtoupper( substr( $admin_name, 0, 1 ) ) ); ?></div>
						<span class="course-hero__instructor-name"><?php echo esc_html( $admin_name ); ?></span>
					</div>
				</div>
			</div>
			<div class="course-hero__media">
				<?php if ( ! empty( $course->thumbnail_url ) ) : ?>
					<img src="<?php echo esc_url( $course->thumbnail_url ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>" class="course-hero__video-thumb">
				<?php elseif ( ! empty( $preview_video ) ) : ?>
					<?php echo $preview_video; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php else : ?>
					<div class="course-hero__video course-hero__video--placeholder" aria-hidden="true"></div>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<section class="course-detail">
		<div class="cta-container course-detail__layout">
			<div class="course-detail__main">
				<?php
				$educational_goals = ! empty( $syllabus_meta['educational_goals'] ) && is_array( $syllabus_meta['educational_goals'] )
					? $syllabus_meta['educational_goals']
					: array();
				$completion_requirements = ! empty( $syllabus_meta['completion_requirements'] ) && is_array( $syllabus_meta['completion_requirements'] )
					? $syllabus_meta['completion_requirements']
					: array();
				$syllabus_references = ! empty( $syllabus_meta['references'] ) && is_array( $syllabus_meta['references'] )
					? $syllabus_meta['references']
					: array();
				$key_topics = ! empty( $syllabus_meta['key_topics'] ) && is_array( $syllabus_meta['key_topics'] )
					? $syllabus_meta['key_topics']
					: array();
				$educational_notice = ! empty( $syllabus_meta['educational_notice'] )
					? (string) $syllabus_meta['educational_notice']
					: '';
				$has_course_info = ! empty( $syllabus_meta['course_level'] )
					|| ! empty( $syllabus_meta['target_audience'] )
					|| ! empty( $syllabus_meta['instructional_method'] )
					|| ! empty( $syllabus_meta['presenter'] )
					|| ! empty( $syllabus_meta['course_code'] )
					|| ! empty( $syllabus_meta['course_classification'] )
					|| ( ! $is_exam_prep && (float) $course->ce_hours > 0 );

				$resources_for_info = isset( $resources ) ? (array) $resources : array();
				$syllabus_resource  = class_exists( 'CTA_Course_Materials' )
					? CTA_Course_Materials::find_syllabus_resource( $resources_for_info )
					: null;
				if ( $syllabus_resource ) {
					$has_course_info = true;
				}
				?>

				<?php if ( ! empty( $course->description ) ) : ?>
					<section class="course-section" aria-labelledby="course-overview-title">
						<h2 class="course-section__title" id="course-overview-title"><?php esc_html_e( 'Course Description', 'cta-lms' ); ?></h2>
						<div class="course-content-block__text"><?php echo wp_kses_post( $course->description ); ?></div>
					</section>
				<?php endif; ?>

				<?php if ( $has_course_info ) : ?>
					<section class="course-section" aria-labelledby="course-info-title">
						<h2 class="course-section__title" id="course-info-title"><?php esc_html_e( 'Course Information', 'cta-lms' ); ?></h2>
						<ul class="course-info-list">
							<?php if ( ! empty( $syllabus_meta['course_code'] ) ) : ?>
								<li><strong><?php esc_html_e( 'Course Code:', 'cta-lms' ); ?></strong> <?php echo esc_html( $syllabus_meta['course_code'] ); ?></li>
							<?php endif; ?>
							<?php if ( ! empty( $syllabus_meta['course_classification'] ) ) : ?>
								<li><strong><?php esc_html_e( 'Course Classification:', 'cta-lms' ); ?></strong> <?php echo esc_html( $syllabus_meta['course_classification'] ); ?></li>
							<?php endif; ?>
							<?php if ( ! empty( $syllabus_meta['course_level'] ) ) : ?>
								<li><strong><?php esc_html_e( 'Course Level:', 'cta-lms' ); ?></strong> <?php echo esc_html( $syllabus_meta['course_level'] ); ?></li>
							<?php endif; ?>
							<?php if ( ! empty( $syllabus_meta['target_audience'] ) ) : ?>
								<li><strong><?php esc_html_e( 'Target Audience:', 'cta-lms' ); ?></strong> <?php echo esc_html( $syllabus_meta['target_audience'] ); ?></li>
							<?php endif; ?>
							<?php if ( ! empty( $syllabus_meta['instructional_method'] ) ) : ?>
								<li><strong><?php esc_html_e( 'Instructional Method:', 'cta-lms' ); ?></strong> <?php echo esc_html( $syllabus_meta['instructional_method'] ); ?></li>
							<?php endif; ?>
							<?php if ( ! $is_exam_prep && (float) $course->ce_hours > 0 ) : ?>
								<li><strong><?php esc_html_e( 'CE Credits:', 'cta-lms' ); ?></strong> <?php echo esc_html( $ce_hours ); ?> <?php esc_html_e( 'CE Hours', 'cta-lms' ); ?> (<?php echo esc_html( (string) ( (float) $ce_hours * 60 ) ); ?> <?php esc_html_e( 'Minutes of Active Instruction', 'cta-lms' ); ?>)</li>
							<?php endif; ?>
							<?php if ( ! empty( $syllabus_meta['presenter'] ) ) : ?>
								<li><strong><?php esc_html_e( 'Presenter/Author:', 'cta-lms' ); ?></strong> <?php echo esc_html( $syllabus_meta['presenter'] ); ?></li>
							<?php endif; ?>
							<?php if ( $syllabus_resource ) : ?>
								<li>
									<strong><?php esc_html_e( 'Downloadable Syllabus:', 'cta-lms' ); ?></strong>
									<?php if ( ! empty( $is_enrolled ) ) : ?>
										<?php
										$syllabus_url = CTA_Course_Materials::get_serve_url( (int) $syllabus_resource->id );
										?>
										<?php if ( $syllabus_url ) : ?>
											<a href="<?php echo esc_url( $syllabus_url ); ?>" class="course-info-list__download" target="_blank" rel="noopener noreferrer">
												<?php echo esc_html( $syllabus_resource->title ); ?>
											</a>
										<?php else : ?>
											<?php echo esc_html( $syllabus_resource->title ); ?>
										<?php endif; ?>
									<?php else : ?>
										<span><?php esc_html_e( 'Available after enrollment', 'cta-lms' ); ?></span>
									<?php endif; ?>
								</li>
							<?php endif; ?>
						</ul>
					</section>
				<?php endif; ?>

				<?php if ( ! empty( $educational_goals ) ) : ?>
					<section class="course-section" aria-labelledby="course-goals-title">
						<h2 class="course-section__title" id="course-goals-title"><?php esc_html_e( 'Educational Goals', 'cta-lms' ); ?></h2>
						<ul class="checklist">
							<?php foreach ( $educational_goals as $goal ) : ?>
								<li class="checklist__item">
									<span class="checklist__icon" aria-hidden="true">✓</span>
									<?php echo esc_html( $goal ); ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>

				<?php if ( ! empty( $objectives ) ) : ?>
					<section class="course-section" aria-labelledby="course-learn-title">
						<h2 class="course-section__title" id="course-learn-title"><?php esc_html_e( 'What Participants Will Learn', 'cta-lms' ); ?></h2>
						<ol class="course-objectives-list">
							<?php foreach ( $objectives as $objective ) : ?>
								<li><?php echo esc_html( $objective ); ?></li>
							<?php endforeach; ?>
						</ol>
					</section>
				<?php endif; ?>

				<?php if ( ! empty( $key_topics ) ) : ?>
					<section class="course-section" aria-labelledby="course-topics-title">
						<h2 class="course-section__title" id="course-topics-title"><?php esc_html_e( 'Key Topics', 'cta-lms' ); ?></h2>
						<ul class="checklist">
							<?php foreach ( $key_topics as $topic ) : ?>
								<li class="checklist__item">
									<span class="checklist__icon" aria-hidden="true">✓</span>
									<?php echo esc_html( $topic ); ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>

				<section class="course-section" aria-labelledby="course-content-title">
					<h2 class="course-section__title" id="course-content-title"><?php esc_html_e( 'Instructional Modules', 'cta-lms' ); ?></h2>
					<?php if ( ! $is_enrolled && ( ! empty( $modules ) || $quiz ) ) : ?>
						<p class="course-content-lock-notice">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
							<?php esc_html_e( 'Enroll in this course to unlock all video lessons, modules, and the final quiz.', 'cta-lms' ); ?>
						</p>
					<?php endif; ?>
					<?php if ( empty( $modules ) && empty( $quiz ) ) : ?>
						<p><?php esc_html_e( 'Course modules coming soon.', 'cta-lms' ); ?></p>
					<?php else : ?>
						<?php if ( ! empty( $modules ) ) : ?>
							<ul class="course-module-list">
								<?php foreach ( $modules as $index => $module ) : ?>
									<?php
									$has_module_video = ! empty( trim( (string) $module->video_url ) );
									$module_locked    = ! $is_enrolled;
									$module_video_id  = 'cta-module-video-' . (int) $module->id;
									$item_classes     = array( 'course-module-list__item' );

									if ( $has_module_video ) {
										$item_classes[] = 'course-module-list__item--has-video';
									}
									if ( $module_locked ) {
										$item_classes[] = 'course-module-list__item--locked';
									}
									?>
									<li class="<?php echo esc_attr( implode( ' ', $item_classes ) ); ?>">
										<div class="course-module-list__header">
											<span class="course-module-list__number"><?php echo esc_html( (string) ( $index + 1 ) ); ?></span>
											<div class="course-module-list__info">
												<strong class="course-module-list__title"><?php echo esc_html( $module->title ); ?></strong>
												<?php if ( ! empty( $module->description ) ) : ?>
													<?php
													if ( class_exists( 'CTA_Syllabus_Sync' ) ) {
														echo CTA_Syllabus_Sync::render_module_description_html( $module->description ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside helper.
													} else {
														echo '<p class="course-module-list__desc">' . esc_html( $module->description ) . '</p>';
													}
													?>
												<?php endif; ?>
												<?php if ( $has_module_video ) : ?>
													<span class="course-module-list__video-tag">
														<?php if ( $module_locked ) : ?>
															<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
															<?php esc_html_e( 'Locked video lesson', 'cta-lms' ); ?>
														<?php else : ?>
															<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
															<?php esc_html_e( 'Video lesson', 'cta-lms' ); ?>
														<?php endif; ?>
													</span>
												<?php endif; ?>
											</div>
											<span class="course-module-list__duration"><?php echo esc_html( (int) $module->duration_mins . ' min' ); ?></span>
											<?php if ( $module_locked ) : ?>
												<span class="course-module-list__lock" title="<?php esc_attr_e( 'Enroll to unlock this lesson', 'cta-lms' ); ?>" aria-hidden="true">
													<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
												</span>
											<?php elseif ( $has_module_video ) : ?>
												<?php $module_video_markup = $video_helper->get_module_video_markup( $module, $course ); ?>
												<button
													type="button"
													class="course-module-list__play"
													data-cta-module-preview
													data-module-title="<?php echo esc_attr( $module->title ); ?>"
													data-target="<?php echo esc_attr( $module_video_id ); ?>"
													aria-label="<?php echo esc_attr( sprintf( __( 'Preview video: %s', 'cta-lms' ), $module->title ) ); ?>"
												>
													<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
												</button>
												<div id="<?php echo esc_attr( $module_video_id ); ?>" class="cta-module-preview-source" hidden>
													<?php echo $module_video_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
												</div>
											<?php endif; ?>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					<?php endif; ?>
				</section>

				<?php if ( $quiz && ! $is_exam_prep ) : ?>
					<section class="course-section" aria-labelledby="course-exam-title">
						<h2 class="course-section__title" id="course-exam-title"><?php esc_html_e( 'Final Examination', 'cta-lms' ); ?></h2>
						<div class="course-module-list__quiz<?php echo ! $is_enrolled ? ' course-module-list__quiz--locked' : ''; ?>">
							<div class="course-module-list__header course-module-list__header--quiz">
								<span class="course-module-list__number" aria-hidden="true">
									<?php if ( ! $is_enrolled ) : ?>
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
									<?php else : ?>
										✓
									<?php endif; ?>
								</span>
								<div class="course-module-list__info">
									<strong class="course-module-list__title"><?php echo esc_html( $quiz->title ? $quiz->title : __( 'Final Examination', 'cta-lms' ) ); ?></strong>
									<p class="course-module-list__desc">
										<?php
										printf(
											/* translators: 1: question count, 2: passing score */
											esc_html__( 'Final examination — %1$d questions, %2$d%% to pass, unlimited attempts. Not counted toward CE instructional minutes.', 'cta-lms' ),
											count( $quiz_questions ),
											(int) $quiz->passing_score
										);
										?>
									</p>
								</div>
								<span class="course-module-list__badge"><?php echo $is_enrolled ? esc_html__( 'Exam', 'cta-lms' ) : esc_html__( 'Locked', 'cta-lms' ); ?></span>
							</div>
						</div>
					</section>

					<section class="course-section" aria-labelledby="course-eval-title">
						<h2 class="course-section__title" id="course-eval-title"><?php esc_html_e( 'Course-Specific Evaluation', 'cta-lms' ); ?></h2>
						<p><?php esc_html_e( 'After passing the final examination, complete the course-specific evaluation, including a rating for each measurable learning objective. Evaluation time is not counted toward CE instructional minutes.', 'cta-lms' ); ?></p>
					</section>

					<?php if ( ! empty( $syllabus_meta['attestation_required'] ) ) : ?>
						<section class="course-section" aria-labelledby="course-attestation-title">
							<h2 class="course-section__title" id="course-attestation-title"><?php esc_html_e( 'Course-Completion Attestation', 'cta-lms' ); ?></h2>
							<p><?php esc_html_e( 'Submit the required course-completion attestation for asynchronous distance learning before a CE certificate is issued.', 'cta-lms' ); ?></p>
						</section>
					<?php endif; ?>

					<?php if ( ! empty( $course->has_ce_certificate ) || ! isset( $course->has_ce_certificate ) ) : ?>
					<section class="course-section" aria-labelledby="course-certificate-title">
						<h2 class="course-section__title" id="course-certificate-title"><?php esc_html_e( 'Certificate', 'cta-lms' ); ?></h2>
						<p><?php esc_html_e( 'A CE certificate is issued only after all completion requirements are satisfied (modules, final examination, evaluation, and attestation).', 'cta-lms' ); ?></p>
					</section>
					<?php endif; ?>
				<?php elseif ( $quiz && $is_exam_prep ) : ?>
					<section class="course-section" aria-labelledby="course-exam-title">
						<h2 class="course-section__title" id="course-exam-title"><?php esc_html_e( 'Practice / Mock Exam', 'cta-lms' ); ?></h2>
						<div class="course-module-list__quiz<?php echo ! $is_enrolled ? ' course-module-list__quiz--locked' : ''; ?>">
							<div class="course-module-list__header course-module-list__header--quiz">
								<span class="course-module-list__number" aria-hidden="true">✓</span>
								<div class="course-module-list__info">
									<strong class="course-module-list__title"><?php echo esc_html( $quiz->title ); ?></strong>
									<p class="course-module-list__desc">
										<?php
										printf(
											esc_html__( 'Practice / mock exam — %1$d questions, %2$d%% required to pass.', 'cta-lms' ),
											count( $quiz_questions ),
											(int) $quiz->passing_score
										);
										?>
									</p>
								</div>
							</div>
						</div>
					</section>
				<?php endif; ?>

				<?php if ( ! empty( $completion_requirements ) ) : ?>
					<section class="course-section" aria-labelledby="course-completion-title">
						<h2 class="course-section__title" id="course-completion-title"><?php esc_html_e( 'Course Completion Requirements', 'cta-lms' ); ?></h2>
						<?php if ( 1 === count( $completion_requirements ) ) : ?>
							<div class="course-content-block__text">
								<p><?php echo esc_html( $completion_requirements[0] ); ?></p>
							</div>
						<?php else : ?>
							<ul class="checklist">
								<?php foreach ( $completion_requirements as $req ) : ?>
									<li class="checklist__item">
										<span class="checklist__icon" aria-hidden="true">✓</span>
										<?php echo esc_html( $req ); ?>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</section>
				<?php endif; ?>

				<?php if ( '' !== $educational_notice ) : ?>
					<section class="course-section course-section--notice" aria-labelledby="course-notice-title">
						<h2 class="course-section__title" id="course-notice-title"><?php esc_html_e( 'Educational Notice', 'cta-lms' ); ?></h2>
						<div class="course-content-block__text">
							<p><?php echo esc_html( $educational_notice ); ?></p>
						</div>
					</section>
				<?php endif; ?>

				<?php if ( ! empty( $syllabus_references ) ) : ?>
					<section class="course-section" aria-labelledby="course-references-title">
						<h2 class="course-section__title" id="course-references-title"><?php esc_html_e( 'References', 'cta-lms' ); ?></h2>
						<ul class="course-references-list">
							<?php foreach ( $syllabus_references as $ref ) : ?>
								<li><?php echo esc_html( $ref ); ?></li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>

				<?php
				$resources     = isset( $resources ) ? $resources : array();
				$show_locked   = ! empty( $resources );
				$heading       = ! empty( $is_exam_prep ) ? __( 'Program Materials', 'cta-lms' ) : __( 'Course Materials', 'cta-lms' );
				include CTA_PLUGIN_DIR . 'templates/partials/course-materials.php';
				?>
			</div>

			<aside class="course-detail__sidebar course-sidebar" aria-label="<?php esc_attr_e( 'Course enrollment', 'cta-lms' ); ?>">
				<div class="course-sidebar__card">
					<p class="course-sidebar__price">
						<?php if ( $is_free_course ) : ?>
							<?php esc_html_e( 'Free', 'cta-lms' ); ?>
						<?php else : ?>
							<?php
							echo esc_html(
								function_exists( 'cta_lms_format_money' )
									? cta_lms_format_money( (float) $course->price )
									: ( '$' . number_format( (float) $course->price, 2 ) )
							);
							?>
						<?php endif; ?>
					</p>
					<ul class="course-sidebar__meta">
						<li class="course-sidebar__meta-item"><span><strong><?php esc_html_e( 'Format:', 'cta-lms' ); ?></strong> <?php esc_html_e( 'Self-paced, online', 'cta-lms' ); ?></span></li>
						<li class="course-sidebar__meta-item"><span><strong><?php esc_html_e( 'Modules:', 'cta-lms' ); ?></strong> <?php echo esc_html( (string) count( $modules ) ); ?></span></li>
						<?php if ( $quiz ) : ?>
							<li class="course-sidebar__meta-item"><span><strong><?php echo $is_exam_prep ? esc_html__( 'Practice exam:', 'cta-lms' ) : esc_html__( 'Quiz:', 'cta-lms' ); ?></strong> <?php echo esc_html( (string) count( $quiz_questions ) ); ?> <?php esc_html_e( 'questions', 'cta-lms' ); ?></span></li>
						<?php endif; ?>
						<?php if ( $is_exam_prep ) : ?>
							<li class="course-sidebar__meta-item"><span><strong><?php esc_html_e( 'Access:', 'cta-lms' ); ?></strong> <?php echo esc_html( (string) $access_months ); ?> <?php esc_html_e( 'months', 'cta-lms' ); ?></span></li>
							<li class="course-sidebar__meta-item"><span><strong><?php esc_html_e( 'CE Hours:', 'cta-lms' ); ?></strong> <?php esc_html_e( 'None (non-CE)', 'cta-lms' ); ?></span></li>
							<li class="course-sidebar__meta-item"><span><strong><?php esc_html_e( 'Certificate:', 'cta-lms' ); ?></strong> <?php esc_html_e( 'Not included', 'cta-lms' ); ?></span></li>
						<?php else : ?>
							<li class="course-sidebar__meta-item"><span><strong><?php esc_html_e( 'CE Hours:', 'cta-lms' ); ?></strong> <?php echo esc_html( $ce_hours ); ?></span></li>
							<li class="course-sidebar__meta-item"><span><strong><?php esc_html_e( 'Duration:', 'cta-lms' ); ?></strong> <?php echo esc_html( (string) $duration_hours ); ?> <?php esc_html_e( 'hours', 'cta-lms' ); ?></span></li>
							<li class="course-sidebar__meta-item"><span><strong><?php esc_html_e( 'Certificate:', 'cta-lms' ); ?></strong> <?php esc_html_e( 'Provided on completion', 'cta-lms' ); ?></span></li>
						<?php endif; ?>
					</ul>

					<?php if ( ! $is_free_course ) : ?>
						<p class="course-sidebar__label"><?php esc_html_e( 'Secure Payment:', 'cta-lms' ); ?></p>
						<div class="course-sidebar__payments" aria-label="<?php esc_attr_e( 'Accepted payment methods', 'cta-lms' ); ?>">
							<span class="course-sidebar__payment-icon">Visa</span>
							<span class="course-sidebar__payment-icon">MC</span>
							<span class="course-sidebar__payment-icon">Amex</span>
							<span class="course-sidebar__payment-icon">Stripe</span>
						</div>
					<?php endif; ?>

					<?php if ( $is_enrolled && $player_url ) : ?>
						<a href="<?php echo esc_url( $player_url ); ?>" class="btn btn-primary btn--lg course-sidebar__enroll"><?php esc_html_e( 'Continue Learning', 'cta-lms' ); ?></a>
					<?php elseif ( $is_enrolled ) : ?>
						<p class="course-sidebar__notice"><?php esc_html_e( 'You are enrolled. Configure the Course Player page in CTA LMS Settings to start learning.', 'cta-lms' ); ?></p>
					<?php elseif ( ! is_user_logged_in() && $login_url ) : ?>
						<a href="<?php echo esc_url( $login_url ); ?>" class="btn btn-primary btn--lg course-sidebar__enroll">
							<?php esc_html_e( 'Login to Enroll', 'cta-lms' ); ?>
						</a>
					<?php else : ?>
						<button type="button" id="enroll-btn" class="btn btn-primary btn--lg course-sidebar__enroll" data-cta-course-checkout data-course-id="<?php echo esc_attr( $course->id ); ?>" data-course-title="<?php echo esc_attr( $course->title ); ?>" data-price="<?php echo esc_attr( $is_free_course ? __( 'Free', 'cta-lms' ) : ( function_exists( 'cta_lms_format_money' ) ? cta_lms_format_money( (float) $course->price ) : ( '$' . number_format( (float) $course->price, 2 ) ) ) ); ?>">
							<?php echo $is_free_course ? esc_html__( 'Enroll Free', 'cta-lms' ) : esc_html__( 'Enroll Now', 'cta-lms' ); ?>
						</button>
					<?php endif; ?>
				</div>
			</aside>
		</div>
	</section>

	<div class="cta-video-modal" id="cta-course-video-modal" hidden>
		<div class="cta-video-modal__backdrop" data-cta-close-video-modal></div>
		<div class="cta-video-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="cta-course-video-modal-title">
			<button type="button" class="cta-video-modal__close" data-cta-close-video-modal aria-label="<?php esc_attr_e( 'Close video', 'cta-lms' ); ?>">&times;</button>
			<h3 class="cta-video-modal__title" id="cta-course-video-modal-title"></h3>
			<div class="cta-video-modal__player"></div>
		</div>
	</div>
</div>
</div>

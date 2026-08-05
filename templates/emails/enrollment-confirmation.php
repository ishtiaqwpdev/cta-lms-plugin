<?php
/**
 * Enrollment confirmation email body.
 *
 * @var WP_User $user
 * @var object  $course
 * @var string  $payment_reference
 * @var string  $ce_hours
 * @var string  $enrolled_date
 * @var string  $player_url
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_exam_prep = class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course );
?>
<p><?php printf( esc_html__( 'Hi %s,', 'cta-lms' ), esc_html( $user->display_name ) ); ?></p>

<h2><?php esc_html_e( 'You\'re enrolled!', 'cta-lms' ); ?> 🎓</h2>

<div class="highlight-box">
	<?php if ( $is_exam_prep ) : ?>
		<p><strong><?php esc_html_e( 'Program:', 'cta-lms' ); ?></strong> <?php echo esc_html( function_exists( 'cta_lms_get_course_display_title' ) ? cta_lms_get_course_display_title( $course ) : $course->title ); ?></p>
		<p><strong><?php esc_html_e( 'Classification:', 'cta-lms' ); ?></strong> <?php esc_html_e( 'Exam Preparation Only — No CE Credit', 'cta-lms' ); ?></p>
	<?php else : ?>
		<p><strong><?php esc_html_e( 'Course:', 'cta-lms' ); ?></strong> <?php echo esc_html( function_exists( 'cta_lms_get_course_display_title' ) ? cta_lms_get_course_display_title( $course ) : $course->title ); ?></p>
		<p><strong><?php esc_html_e( 'CE Hours:', 'cta-lms' ); ?></strong> <?php echo esc_html( $ce_hours ); ?></p>
	<?php endif; ?>
	<p><strong><?php esc_html_e( 'Payment:', 'cta-lms' ); ?></strong> <?php echo esc_html( $payment_reference ); ?></p>
	<p><strong><?php esc_html_e( 'Enrolled:', 'cta-lms' ); ?></strong> <?php echo esc_html( $enrolled_date ); ?></p>
</div>

<p><strong><?php esc_html_e( 'What\'s next:', 'cta-lms' ); ?></strong></p>
<ol>
	<li><?php esc_html_e( 'Log in to your dashboard', 'cta-lms' ); ?></li>
	<li><?php esc_html_e( 'Start with Module 1', 'cta-lms' ); ?></li>
	<li><?php esc_html_e( 'Complete all modules at your own pace', 'cta-lms' ); ?></li>
	<?php if ( $is_exam_prep ) : ?>
		<li><?php esc_html_e( 'Begin the program assessments when modules are complete', 'cta-lms' ); ?></li>
	<?php else : ?>
		<li><?php esc_html_e( 'Pass the final quiz (70% required)', 'cta-lms' ); ?></li>
		<li><?php esc_html_e( 'Submit course evaluation', 'cta-lms' ); ?></li>
		<li><?php esc_html_e( 'Download your CE certificate', 'cta-lms' ); ?></li>
	<?php endif; ?>
</ol>

<p><a class="btn-email" href="<?php echo esc_url( $player_url ); ?>"><?php echo $is_exam_prep ? esc_html__( 'Start Preparing Now', 'cta-lms' ) : esc_html__( 'Start Learning Now', 'cta-lms' ); ?></a></p>

<hr class="divider">

<p class="small-text"><?php echo $is_exam_prep ? esc_html__( 'This Exam Preparation Program is self-paced — take it on your schedule.', 'cta-lms' ) : esc_html__( 'This course is self-paced — take it on your schedule.', 'cta-lms' ); ?></p>

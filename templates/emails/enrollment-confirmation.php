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
?>
<p><?php printf( esc_html__( 'Hi %s,', 'cta-lms' ), esc_html( $user->display_name ) ); ?></p>

<h2><?php esc_html_e( 'You\'re enrolled!', 'cta-lms' ); ?> 🎓</h2>

<div class="highlight-box">
	<p><strong><?php esc_html_e( 'Course:', 'cta-lms' ); ?></strong> <?php echo esc_html( $course->title ); ?></p>
	<p><strong><?php esc_html_e( 'CE Hours:', 'cta-lms' ); ?></strong> <?php echo esc_html( $ce_hours ); ?></p>
	<p><strong><?php esc_html_e( 'Payment:', 'cta-lms' ); ?></strong> <?php echo esc_html( $payment_reference ); ?></p>
	<p><strong><?php esc_html_e( 'Enrolled:', 'cta-lms' ); ?></strong> <?php echo esc_html( $enrolled_date ); ?></p>
</div>

<p><strong><?php esc_html_e( 'What\'s next:', 'cta-lms' ); ?></strong></p>
<ol>
	<li><?php esc_html_e( 'Log in to your dashboard', 'cta-lms' ); ?></li>
	<li><?php esc_html_e( 'Start with Module 1', 'cta-lms' ); ?></li>
	<li><?php esc_html_e( 'Complete all modules at your own pace', 'cta-lms' ); ?></li>
	<li><?php esc_html_e( 'Pass the final quiz (70% required)', 'cta-lms' ); ?></li>
	<li><?php esc_html_e( 'Submit course evaluation', 'cta-lms' ); ?></li>
	<li><?php esc_html_e( 'Download your CE certificate', 'cta-lms' ); ?></li>
</ol>

<p><a class="btn-email" href="<?php echo esc_url( $player_url ); ?>"><?php esc_html_e( 'Start Learning Now', 'cta-lms' ); ?></a></p>

<hr class="divider">

<p class="small-text"><?php esc_html_e( 'This course is self-paced — take it on your schedule.', 'cta-lms' ); ?></p>

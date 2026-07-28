<?php
/**
 * Certificate ready email body.
 *
 * @var WP_User $user
 * @var object  $course
 * @var object  $certificate
 * @var string  $ce_hours
 * @var string  $certificate_url
 * @var string  $completion_date
 * @var string  $dashboard_url
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p><?php printf( esc_html__( 'Hi %s,', 'cta-lms' ), esc_html( $user->display_name ) ); ?></p>

<h2><?php esc_html_e( 'Your CE certificate is ready!', 'cta-lms' ); ?> 🎉</h2>

<p><?php esc_html_e( 'Congratulations on completing your course. Your certificate has been issued and is ready to download.', 'cta-lms' ); ?></p>

<div class="highlight-box">
	<p><strong><?php esc_html_e( 'Course:', 'cta-lms' ); ?></strong> <?php echo esc_html( $course->title ); ?></p>
	<p><strong><?php esc_html_e( 'CE Hours:', 'cta-lms' ); ?></strong> <?php echo esc_html( $ce_hours ); ?> <?php esc_html_e( 'CE Hours', 'cta-lms' ); ?></p>
	<p><strong><?php esc_html_e( 'Certificate #:', 'cta-lms' ); ?></strong> <?php echo esc_html( $certificate->certificate_number ); ?></p>
	<p><strong><?php esc_html_e( 'Completion Date:', 'cta-lms' ); ?></strong> <?php echo esc_html( $completion_date ); ?></p>
	<p><strong><?php esc_html_e( 'Your Name:', 'cta-lms' ); ?></strong> <?php echo esc_html( $user->display_name ); ?></p>
</div>

<p><?php esc_html_e( 'This certificate is BBS-compliant and meets requirements for license renewal.', 'cta-lms' ); ?></p>

<p><a class="btn-email" href="<?php echo esc_url( $certificate_url ); ?>"><?php esc_html_e( 'Download Certificate', 'cta-lms' ); ?></a></p>

<hr class="divider">

<p class="small-text"><?php esc_html_e( 'Save this email for your records. You can also download your certificate anytime from your dashboard.', 'cta-lms' ); ?></p>

<p><a class="btn-email" href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'View All My Certificates', 'cta-lms' ); ?></a></p>

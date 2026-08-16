<?php
/**
 * Reusable product disclaimers block (exam prep / product pages).
 *
 * @package CTA_LMS
 *
 * @var array  $disclaimers List of disclaimer strings.
 * @var string $heading Optional heading.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$disclaimers = isset( $disclaimers ) && is_array( $disclaimers ) ? $disclaimers : array();
$heading     = isset( $heading ) ? (string) $heading : __( 'Important Notices', 'cta-lms' );

if ( empty( $disclaimers ) ) {
	return;
}
?>
<section class="course-section course-section--disclaimers" aria-labelledby="course-disclaimers-title">
	<h2 class="course-section__title" id="course-disclaimers-title"><?php echo esc_html( $heading ); ?></h2>
	<ul class="course-disclaimers-list">
		<?php foreach ( $disclaimers as $notice ) : ?>
			<li><?php echo esc_html( (string) $notice ); ?></li>
		<?php endforeach; ?>
	</ul>
</section>

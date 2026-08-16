<?php
/**
 * Product FAQ accordion (exam prep product pages).
 *
 * @package CTA_LMS
 *
 * @var array $faqs List of {question, answer} rows.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$faqs = isset( $faqs ) && is_array( $faqs ) ? $faqs : array();
if ( empty( $faqs ) ) {
	return;
}
?>
<section class="course-section course-section--faqs" aria-labelledby="course-faqs-title">
	<h2 class="course-section__title" id="course-faqs-title"><?php esc_html_e( 'Frequently Asked Questions', 'cta-lms' ); ?></h2>
	<div class="course-faq-list">
		<?php foreach ( $faqs as $index => $faq ) : ?>
			<?php
			$q = isset( $faq['question'] ) ? (string) $faq['question'] : '';
			$a = isset( $faq['answer'] ) ? (string) $faq['answer'] : '';
			if ( '' === $q || '' === $a ) {
				continue;
			}
			$faq_id = 'course-faq-' . (int) $index;
			?>
			<details class="course-faq-item" id="<?php echo esc_attr( $faq_id ); ?>">
				<summary class="course-faq-item__question"><?php echo esc_html( $q ); ?></summary>
				<div class="course-faq-item__answer">
					<p><?php echo esc_html( $a ); ?></p>
				</div>
			</details>
		<?php endforeach; ?>
	</div>
</section>

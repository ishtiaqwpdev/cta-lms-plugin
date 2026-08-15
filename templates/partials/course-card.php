<?php
/**
 * Reusable course card partial.
 *
 * @package CTA_LMS
 *
 * @var object $course Course row from the database.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$course_url = function_exists( 'cta_lms_get_single_course_url' )
	? cta_lms_get_single_course_url( (int) $course->id )
	: '';

$is_enrolled = false;

if ( is_user_logged_in() ) {
	global $wpdb;

	$user_id     = get_current_user_id();
	$is_enrolled = (bool) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}cta_enrollments
			WHERE user_id = %d AND course_id = %d AND status = 'active'",
			$user_id,
			absint( $course->id )
		)
	);
}

$ce_hours_display = rtrim( rtrim( number_format( (float) $course->ce_hours, 1, '.', '' ), '0' ), '.' );
$category         = ! empty( $course->category ) ? $course->category : '';
$price_value      = (float) $course->price;
$price_display    = function_exists( 'cta_lms_format_money' )
	? cta_lms_format_money( $price_value )
	: ( '$' . number_format( $price_value, 2 ) );
$is_exam_prep     = class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course );
$link_label       = $is_enrolled
	? __( 'Continue', 'cta-lms' ) . ' →'
	: ( $is_exam_prep ? __( 'View Program', 'cta-lms' ) . ' →' : __( 'View Course', 'cta-lms' ) . ' →' );

$card_meta = class_exists( 'CTA_Syllabus_Sync' ) ? CTA_Syllabus_Sync::get_meta( $course ) : array();
$card_desc = ! empty( $card_meta['short_description'] )
	? (string) $card_meta['short_description']
	: wp_trim_words( wp_strip_all_tags( (string) $course->description ), 15 );
$card_alt  = ! empty( $card_meta['image_alt'] )
	? (string) $card_meta['image_alt']
	: (string) $course->title;
$commercial_pending = class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::commercial_terms_pending( $course );
$display_title      = function_exists( 'cta_lms_get_course_display_title' )
	? cta_lms_get_course_display_title( $course )
	: (string) $course->title;
?>
<article
	class="cta-course-card card course-card course-card--catalog<?php echo $is_exam_prep ? ' course-card--exam-prep' : ''; ?>"
	data-category="<?php echo esc_attr( $category ); ?>"
	data-price="<?php echo esc_attr( $course->price ); ?>"
	data-ce-hours="<?php echo esc_attr( $is_exam_prep ? '' : $course->ce_hours ); ?>"
	data-product-type="<?php echo esc_attr( $is_exam_prep ? 'exam_prep' : 'ce' ); ?>"
>
	<div class="cta-course-card__thumb course-card__media">
		<?php if ( ! empty( $course->thumbnail_url ) ) : ?>
			<img
				src="<?php echo esc_url( $course->thumbnail_url ); ?>"
				alt="<?php echo esc_attr( $card_alt ); ?>"
				class="<?php echo $is_exam_prep ? 'cta-exam-prep-artwork' : ''; ?>"
				loading="lazy"
			>
		<?php else : ?>
			<div class="cta-course-card__thumb-placeholder course-card__thumb">
				<span aria-hidden="true">&#128214;</span>
			</div>
		<?php endif; ?>

		<span class="cta-course-card__price course-card__price"><?php echo esc_html( $price_display ); ?></span>
	</div>

	<div class="cta-course-card__body card__body">
		<div class="course-card__meta-row">
			<?php if ( $category ) : ?>
				<span class="cta-course-card__category course-card__tag">
					<span class="course-card__tag-dot" aria-hidden="true"></span>
					<?php echo esc_html( $category ); ?>
				</span>
			<?php endif; ?>

			<?php if ( $is_exam_prep ) : ?>
				<span class="cta-badge badge badge--primary course-card__badge">
					<?php
					if ( $commercial_pending ) {
						esc_html_e( 'Pricing pending', 'cta-lms' );
					} else {
						printf(
							/* translators: %d: access months */
							esc_html__( '%d mo access', 'cta-lms' ),
							(int) ( $course->access_period_months ?? 6 )
						);
					}
					?>
				</span>
			<?php else : ?>
				<span class="cta-badge cta-badge--ce badge badge--success course-card__badge">
					<?php echo esc_html( $ce_hours_display ); ?> <?php echo esc_html__( 'CE', 'cta-lms' ); ?>
				</span>
			<?php endif; ?>
		</div>

		<h3 class="cta-course-card__title card__title course-card__title">
			<?php if ( $course_url ) : ?>
				<a href="<?php echo esc_url( $course_url ); ?>"><?php echo esc_html( $display_title ); ?></a>
			<?php else : ?>
				<?php echo esc_html( $display_title ); ?>
			<?php endif; ?>
		</h3>

		<p class="cta-course-card__desc card__text course-card__text">
			<?php echo esc_html( $card_desc ); ?>
		</p>

		<div class="cta-course-card__footer course-card__footer">
			<?php if ( $course_url ) : ?>
				<a href="<?php echo esc_url( $course_url ); ?>" class="course-card__link cta-course-card__link">
					<?php echo esc_html( $link_label ); ?>
				</a>
			<?php else : ?>
				<span class="course-card__footer-label"><?php echo esc_html__( 'Details page not configured', 'cta-lms' ); ?></span>
			<?php endif; ?>
		</div>
	</div>
</article>

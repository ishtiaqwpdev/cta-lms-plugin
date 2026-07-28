<?php
/**
 * Supervision onboarding status card.
 *
 * @package CTA_LMS
 *
 * @var string $onboarding_status_label
 * @var string $onboarding_status_class
 * @var string $onboarding_message
 * @var string $plan_label
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="dashboard-section" aria-labelledby="supervision-onboarding-title">
	<h2 class="dashboard-section__title" id="supervision-onboarding-title">
		<?php echo esc_html__( 'Supervision Onboarding', 'cta-lms' ); ?>
	</h2>
	<article class="card subscription-card">
		<div class="subscription-card__details">
			<p class="subscription-card__plan">
				<?php echo esc_html( $plan_label ); ?>
			</p>
			<p class="subscription-card__billing">
				<?php echo esc_html( $onboarding_message ); ?>
			</p>
		</div>
		<div class="subscription-card__actions">
			<span class="badge <?php echo esc_attr( $onboarding_status_class ); ?>">
				<?php echo esc_html( $onboarding_status_label ); ?>
			</span>
		</div>
	</article>
</section>

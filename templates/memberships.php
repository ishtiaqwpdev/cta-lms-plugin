<?php
/**
 * Memberships and bundles pricing template.
 *
 * @package CTA_LMS
 *
 * @var array  $bundles                Active bundle objects.
 * @var array  $user_bundles           Bundle IDs owned by current user.
 * @var array  $courses_map            Course ID => course object map.
 * @var int    $published_course_count Number of published courses.
 * @var string $login_url              Login page URL.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$memberships_page_url = '';

$page_id = absint( get_option( 'cta_memberships_page_id', 0 ) );
if ( $page_id ) {
	$memberships_page_url = get_permalink( $page_id );
}
?>
<div class="cta-plugin-wrapper">
<div class="cta-lms cta-membership-pricing">
	<?php if ( empty( $bundles ) ) : ?>
		<div class="cta-empty-state">
			<h3><?php echo esc_html__( 'No plans available yet', 'cta-lms' ); ?></h3>
			<p><?php echo esc_html__( 'Check back soon.', 'cta-lms' ); ?></p>
		</div>
	<?php else : ?>
		<section class="cta-page-hero page-hero">
			<div class="page-hero__inner">
				<nav class="cta-breadcrumb page-hero__breadcrumb" aria-label="<?php echo esc_attr__( 'Breadcrumb', 'cta-lms' ); ?>">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html__( 'Home', 'cta-lms' ); ?></a>
					<span class="page-hero__breadcrumb-separator" aria-hidden="true">/</span>
					<span class="page-hero__breadcrumb-current"><?php echo esc_html__( 'Memberships & Bundles', 'cta-lms' ); ?></span>
				</nav>
				<h1 class="page-hero__title"><?php echo esc_html__( 'Memberships & Bundles', 'cta-lms' ); ?></h1>
				<p class="page-hero__subtitle">
					<?php echo esc_html__( 'Save more with course bundles and unlimited access plans', 'cta-lms' ); ?>
				</p>
			</div>
		</section>

		<section class="cta-pricing-section section">
			<div class="cta-container">
				<div class="cta-pricing-grid pricing-grid">
					<?php foreach ( $bundles as $bundle ) : ?>
						<?php
						$is_owned    = in_array( (int) $bundle->id, $user_bundles, true );
						$is_featured = (bool) $bundle->is_featured;

						$included_ids = json_decode( (string) $bundle->included_courses, true );
						if ( ! is_array( $included_ids ) ) {
							$included_ids = array();
						}

						$included_names = array();
						foreach ( $included_ids as $course_id ) {
							$course_id = (int) $course_id;
							if ( isset( $courses_map[ $course_id ] ) ) {
								$course_obj       = $courses_map[ $course_id ];
								$ce_hours_display = rtrim( rtrim( number_format( (float) $course_obj->ce_hours, 1, '.', '' ), '0' ), '.' );
								$included_names[] = $course_obj->title . ' (' . $ce_hours_display . ' CE)';
							}
						}

						$price_display = '$' . number_format( (float) $bundle->price, 2 );
						$cycle_display = '';

						if ( 'monthly' === $bundle->billing_cycle ) {
							$cycle_display = __( '/month', 'cta-lms' );
						} elseif ( 'yearly' === $bundle->billing_cycle ) {
							$cycle_display = __( '/year', 'cta-lms' );
						}
						?>
						<div
							class="cta-pricing-card pricing-card card <?php echo $is_featured ? 'cta-pricing-card--featured pricing-card--featured' : ''; ?>"
							data-bundle-id="<?php echo esc_attr( $bundle->id ); ?>"
						>
							<?php if ( $is_featured ) : ?>
								<div class="cta-pricing-card__ribbon pricing-card__ribbon">
									<?php echo esc_html__( 'BEST VALUE', 'cta-lms' ); ?>
								</div>
							<?php endif; ?>

							<div class="cta-pricing-card__header">
								<span class="cta-pricing-card__type pricing-card__name">
									<?php echo esc_html( ucfirst( $bundle->plan_type ) ); ?>
								</span>
								<h3 class="cta-pricing-card__name pricing-card__name">
									<?php echo esc_html( $bundle->name ); ?>
								</h3>
								<div class="cta-pricing-card__price pricing-card__price">
									<span class="price-amount"><?php echo esc_html( $price_display ); ?></span>
									<?php if ( $cycle_display ) : ?>
										<span class="price-cycle pricing-card__price-suffix"><?php echo esc_html( $cycle_display ); ?></span>
									<?php endif; ?>
								</div>
								<p class="cta-pricing-card__desc pricing-card__period">
									<?php echo esc_html( $bundle->description ); ?>
								</p>
							</div>

							<div class="cta-pricing-card__includes pricing-card__includes">
								<?php if ( ! empty( $included_names ) ) : ?>
									<p class="cta-pricing-card__includes-label pricing-card__includes-title">
										<?php echo esc_html__( 'Includes:', 'cta-lms' ); ?>
									</p>
									<ul class="cta-checklist checklist">
										<?php foreach ( $included_names as $name ) : ?>
											<li class="cta-checklist__item checklist__item">
												<span class="cta-checklist__check checklist__icon" aria-hidden="true">&#10003;</span>
												<?php echo esc_html( $name ); ?>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php elseif ( 'annual' === $bundle->plan_type ) : ?>
									<ul class="cta-checklist checklist">
										<li class="cta-checklist__item checklist__item">
											<span class="cta-checklist__check checklist__icon" aria-hidden="true">&#10003;</span>
											<?php
											printf(
												/* translators: %d: number of published courses */
												esc_html__( 'All %d published CE courses included', 'cta-lms' ),
												(int) $published_course_count
											);
											?>
										</li>
										<li class="cta-checklist__item checklist__item">
											<span class="cta-checklist__check checklist__icon" aria-hidden="true">&#10003;</span>
											<?php echo esc_html__( '12 months unlimited access', 'cta-lms' ); ?>
										</li>
										<li class="cta-checklist__item checklist__item">
											<span class="cta-checklist__check checklist__icon" aria-hidden="true">&#10003;</span>
											<?php echo esc_html__( 'All future published courses included', 'cta-lms' ); ?>
										</li>
									</ul>
								<?php elseif ( 'subscription' === $bundle->plan_type ) : ?>
									<ul class="cta-checklist checklist">
										<li class="cta-checklist__item checklist__item">
											<span class="cta-checklist__check checklist__icon" aria-hidden="true">&#10003;</span>
											<?php echo esc_html__( 'Group supervision (4 sessions/month)', 'cta-lms' ); ?>
										</li>
										<li class="cta-checklist__item checklist__item">
											<span class="cta-checklist__check checklist__icon" aria-hidden="true">&#10003;</span>
											<?php echo esc_html__( '1 individual session/month', 'cta-lms' ); ?>
										</li>
										<li class="cta-checklist__item checklist__item">
											<span class="cta-checklist__check checklist__icon" aria-hidden="true">&#10003;</span>
											<?php
											printf(
												/* translators: %d: number of published courses */
												esc_html__( 'Full CE library access (%d courses)', 'cta-lms' ),
												(int) $published_course_count
											);
											?>
										</li>
									</ul>
								<?php endif; ?>
							</div>

							<div class="cta-pricing-card__footer pricing-card__cta">
								<?php
								$requires_associate = ( 'subscription' === $bundle->plan_type )
									|| false !== stripos( (string) $bundle->slug, 'supervision' )
									|| false !== stripos( (string) $bundle->slug, 'hybrid' )
									|| false !== stripos( (string) $bundle->name, 'supervision' );
								?>
								<?php if ( $is_owned ) : ?>
									<button type="button" class="btn btn-primary btn--full" disabled>
										<?php echo esc_html__( 'Already Purchased', 'cta-lms' ); ?>
									</button>
								<?php elseif ( ! is_user_logged_in() ) : ?>
									<a href="<?php echo esc_url( $login_url ); ?>" class="btn btn-primary btn--full">
										<?php echo esc_html__( 'Log In to Purchase', 'cta-lms' ); ?>
									</a>
								<?php elseif ( $requires_associate && empty( $can_purchase_supervision ) ) : ?>
									<p class="pricing-card__note"><?php echo esc_html( $associate_required_message ); ?></p>
									<a href="<?php echo esc_url( $register_url ); ?>" class="btn btn-primary btn--full">
										<?php echo esc_html__( 'Register as Associate', 'cta-lms' ); ?>
									</a>
								<?php else : ?>
									<button
										type="button"
										class="btn btn-primary btn--full cta-bundle-btn"
										data-bundle-id="<?php echo esc_attr( $bundle->id ); ?>"
										data-plan-type="<?php echo esc_attr( $bundle->plan_type ); ?>"
										data-billing="<?php echo esc_attr( $bundle->billing_cycle ); ?>"
										data-price="<?php echo esc_attr( $price_display . $cycle_display ); ?>"
									>
										<?php echo esc_html__( 'Choose Plan', 'cta-lms' ); ?>
									</button>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>

		<section class="cta-comparison-section comparison-section section section--bg">
			<div class="cta-container">
				<header class="comparison-section__header section__header">
					<h2 class="text-h2"><?php echo esc_html__( 'Plan Comparison', 'cta-lms' ); ?></h2>
				</header>
				<div class="cta-table-wrap comparison-table-wrap">
					<table class="cta-comparison-table comparison-table">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Feature', 'cta-lms' ); ?></th>
								<?php foreach ( $bundles as $bundle ) : ?>
									<th><?php echo esc_html( $bundle->name ); ?></th>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><?php echo esc_html__( 'CE Courses Access', 'cta-lms' ); ?></td>
								<?php foreach ( $bundles as $bundle ) : ?>
									<?php
									$ids = json_decode( (string) $bundle->included_courses, true );
									if ( ! is_array( $ids ) ) {
										$ids = array();
									}

									if ( 'annual' === $bundle->plan_type || 'subscription' === $bundle->plan_type ) {
										$display = sprintf(
											/* translators: %d: number of courses */
											__( 'All %d', 'cta-lms' ),
											(int) $published_course_count
										);
									} elseif ( count( $ids ) > 0 ) {
										$display = sprintf(
											/* translators: %d: number of courses */
											_n( '%d course', '%d courses', count( $ids ), 'cta-lms' ),
											count( $ids )
										);
									} else {
										$display = '—';
									}
									?>
									<td><?php echo esc_html( $display ); ?></td>
								<?php endforeach; ?>
							</tr>
							<tr>
								<td><?php echo esc_html__( 'Supervision Access', 'cta-lms' ); ?></td>
								<?php foreach ( $bundles as $bundle ) : ?>
									<td>
										<?php echo 'subscription' === $bundle->plan_type ? esc_html__( 'Yes', 'cta-lms' ) : '—'; ?>
									</td>
								<?php endforeach; ?>
							</tr>
							<tr>
								<td><?php echo esc_html__( 'Certificate Included', 'cta-lms' ); ?></td>
								<?php foreach ( $bundles as $bundle ) : ?>
									<td><?php echo esc_html__( 'Yes', 'cta-lms' ); ?></td>
								<?php endforeach; ?>
							</tr>
							<tr>
								<td><?php echo esc_html__( 'Billing', 'cta-lms' ); ?></td>
								<?php foreach ( $bundles as $bundle ) : ?>
									<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $bundle->billing_cycle ) ) ); ?></td>
								<?php endforeach; ?>
							</tr>
							<tr>
								<td><?php echo esc_html__( 'Price', 'cta-lms' ); ?></td>
								<?php foreach ( $bundles as $bundle ) : ?>
									<?php
									$price = '$' . number_format( (float) $bundle->price, 2 );
									if ( 'monthly' === $bundle->billing_cycle ) {
										$price .= '/mo';
									}
									if ( 'yearly' === $bundle->billing_cycle ) {
										$price .= '/yr';
									}
									?>
									<td><strong><?php echo esc_html( $price ); ?></strong></td>
								<?php endforeach; ?>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</section>
	<?php endif; ?>
</div>
</div>

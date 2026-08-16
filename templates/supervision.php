<?php
/**
 * Supervision services and booking template.
 *
 * @package CTA_LMS
 *
 * @var array       $sessions        Available session slot rows.
 * @var string      $user_status     guest|active|locked|inactive|pending_approval|rejected|awaiting_plan.
 * @var bool        $is_logged_in    Whether user is logged in.
 * @var array       $user_bookings   User booking map (session key => booking ID).
 * @var float       $monthly_price   Group supervision monthly price.
 * @var float       $individual_price Individual session price.
 * @var string      $login_url       Login page URL.
 * @var string      $calendar_month  First day of calendar month (Y-m-01).
 * @var array       $session_dates   Dates that have available sessions.
 * @var CTA_Supervision $supervision Supervision class instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$monthly_display    = '$' . number_format( $monthly_price, 0 );
$individual_display = '$' . number_format( $individual_price, 0 );
$current_user_id    = $is_logged_in ? get_current_user_id() : 0;
$can_book_group     = $is_logged_in && class_exists( 'CTA_Supervision' )
	? CTA_Supervision::can_book_group_sessions( $current_user_id )
	: false;
$can_book_individual = $is_logged_in && class_exists( 'CTA_Supervision' )
	? CTA_Supervision::can_book_individual_sessions( $current_user_id )
	: false;
$can_book_sessions  = $can_book_group || $can_book_individual;
$has_subscription   = $can_book_group && ( 'active' === $user_status );
$individual_credits = ( $is_logged_in && class_exists( 'CTA_Supervision' ) )
	? CTA_Supervision::get_individual_session_credits( $current_user_id )
	: 0;
$can_purchase_supervision = isset( $can_purchase_supervision ) ? (bool) $can_purchase_supervision : true;
$individual_price_label = class_exists( 'CTA_Supervision_Plans' )
	? CTA_Supervision_Plans::get_individual_session_price_label()
	: ( $individual_display . '/session' );
$individual_session_name = class_exists( 'CTA_Supervision_Plans' )
	? CTA_Supervision_Plans::get_individual_session_name()
	: __( 'Individual 1-on-1 Supervision', 'cta-lms' );
$register_url       = isset( $register_url ) ? $register_url : CTA_Associate_Access::get_associate_registration_url();
$associate_required_message = CTA_Associate_Access::get_associate_required_message();
$pending_message    = class_exists( 'CTA_Associate_Access' ) ? CTA_Associate_Access::get_pending_message() : '';
$ce_dashboard_url   = class_exists( 'CTA_Associate_Access' )
	? CTA_Associate_Access::get_general_dashboard_url()
	: home_url( '/' );

$calendar_ts   = cta_lms_session_datetime( $calendar_month, '00:00:00' );
$calendar_ts   = $calendar_ts ? $calendar_ts->getTimestamp() : strtotime( $calendar_month );
$month_label   = cta_lms_date( 'F Y', $calendar_ts, cta_lms_get_timezone() );
$days_in_month = (int) cta_lms_date( 't', $calendar_ts, cta_lms_get_timezone() );
$first_weekday = (int) cta_lms_date( 'w', $calendar_ts, cta_lms_get_timezone() );
$today         = cta_lms_current_date( 'Y-m-d' );
$selected_date = ! empty( $session_dates ) ? min( $session_dates ) : $today;
?>
<div class="cta-plugin-wrapper">
<div
	class="cta-lms cta-supervision-booking"
	data-can-book="<?php echo $can_book_sessions ? 'yes' : 'no'; ?>"
	data-user-status="<?php echo esc_attr( (string) $user_status ); ?>"
	data-pending-message="<?php echo esc_attr( $pending_message ); ?>"
	data-ce-dashboard-url="<?php echo esc_url( $ce_dashboard_url ); ?>"
>

	<section class="page-hero" aria-labelledby="supervision-hero-title">
		<div class="page-hero__inner">
			<nav class="page-hero__breadcrumb" aria-label="<?php echo esc_attr__( 'Breadcrumb', 'cta-lms' ); ?>">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html__( 'Home', 'cta-lms' ); ?></a>
				<span class="page-hero__breadcrumb-separator" aria-hidden="true">/</span>
				<span class="page-hero__breadcrumb-current"><?php echo esc_html__( 'Supervision', 'cta-lms' ); ?></span>
			</nav>
			<h1 class="page-hero__title" id="supervision-hero-title"><?php echo esc_html__( 'Clinical Supervision Services', 'cta-lms' ); ?></h1>
			<p class="page-hero__subtitle">
				<?php echo esc_html__( 'BBS-compliant supervision for AMFT, ASW, and APCC associates', 'cta-lms' ); ?>
			</p>
		</div>
	</section>

	<section class="section supervision-services" aria-labelledby="supervision-services-title">
		<div class="cta-container">
			<h2 class="visually-hidden" id="supervision-services-title"><?php echo esc_html__( 'Supervision Plans', 'cta-lms' ); ?></h2>
			<div class="grid-2 grid-2--gap-lg supervision-services__grid">

				<article class="card service-card">
					<div class="service-card__badge-row">
						<span class="badge badge--primary"><?php echo esc_html__( 'Subscription', 'cta-lms' ); ?></span>
					</div>
					<h3 class="service-card__title"><?php echo esc_html__( 'Group Supervision', 'cta-lms' ); ?></h3>
					<p class="service-card__price"><?php echo esc_html( $monthly_display ); ?></p>
					<p class="service-card__price-unit"><?php echo esc_html__( '/ month', 'cta-lms' ); ?></p>
					<ul class="checklist service-card__features">
						<li class="checklist__item">
							<span class="checklist__icon" aria-hidden="true">
								<svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2"><polyline points="2 6 5 9 10 3"></polyline></svg>
							</span>
							<?php echo esc_html__( '4 sessions per month', 'cta-lms' ); ?>
						</li>
						<li class="checklist__item">
							<span class="checklist__icon" aria-hidden="true">
								<svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2"><polyline points="2 6 5 9 10 3"></polyline></svg>
							</span>
							<?php echo esc_html__( '2 hours per session', 'cta-lms' ); ?>
						</li>
						<li class="checklist__item">
							<span class="checklist__icon" aria-hidden="true">
								<svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2"><polyline points="2 6 5 9 10 3"></polyline></svg>
							</span>
							<?php
							printf(
								/* translators: %d: max group size */
								esc_html__( 'Max %d associates per group', 'cta-lms' ),
								CTA_Supervision::GROUP_SEATS_MAX
							);
							?>
						</li>
						<li class="checklist__item">
							<span class="checklist__icon" aria-hidden="true">
								<svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2"><polyline points="2 6 5 9 10 3"></polyline></svg>
							</span>
							<?php echo esc_html__( 'BBS-compliant documentation', 'cta-lms' ); ?>
						</li>
						<li class="checklist__item">
							<span class="checklist__icon" aria-hidden="true">
								<svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2"><polyline points="2 6 5 9 10 3"></polyline></svg>
							</span>
							<?php echo esc_html__( 'Cancel anytime', 'cta-lms' ); ?>
						</li>
					</ul>
					<?php if ( ! $is_logged_in ) : ?>
						<a href="<?php echo esc_url( $login_url ); ?>" class="btn btn-primary btn--lg service-card__cta">
							<?php echo esc_html__( 'Login to Book', 'cta-lms' ); ?>
						</a>
					<?php elseif ( $has_subscription ) : ?>
						<a href="#booking" class="btn btn-primary btn--lg service-card__cta">
							<?php echo esc_html__( 'Book Group Session', 'cta-lms' ); ?>
						</a>
					<?php elseif ( ! $can_purchase_supervision ) : ?>
						<p class="service-card__note"><?php echo esc_html( $associate_required_message ); ?></p>
						<a href="<?php echo esc_url( $register_url ); ?>" class="btn btn-primary btn--lg service-card__cta">
							<?php echo esc_html__( 'Register as Associate', 'cta-lms' ); ?>
						</a>
					<?php else : ?>
						<button type="button" class="btn btn-primary btn--lg service-card__cta cta-subscribe-btn" data-cta-supervision-subscribe data-course-title="<?php echo esc_attr( CTA_Supervision_Plans::get_name( CTA_Supervision_Plans::GROUP_SLUG ) ); ?>" data-price="<?php echo esc_attr( CTA_Supervision_Plans::get_price_label( CTA_Supervision_Plans::GROUP_SLUG ) ); ?>">
							<?php echo esc_html__( 'Subscribe Now', 'cta-lms' ); ?>
						</button>
					<?php endif; ?>
				</article>

				<article class="card service-card service-card--featured">
					<span class="badge badge--success service-card__popular"><?php echo esc_html__( 'Most Popular', 'cta-lms' ); ?></span>
					<div class="service-card__badge-row">
						<span class="badge badge--outline"><?php echo esc_html__( 'One-time', 'cta-lms' ); ?></span>
					</div>
					<h3 class="service-card__title"><?php echo esc_html__( 'Individual 1-on-1', 'cta-lms' ); ?></h3>
					<p class="service-card__price"><?php echo esc_html( $individual_display ); ?></p>
					<p class="service-card__price-unit"><?php echo esc_html__( '/ session', 'cta-lms' ); ?></p>
					<ul class="checklist service-card__features">
						<li class="checklist__item">
							<span class="checklist__icon" aria-hidden="true">
								<svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2"><polyline points="2 6 5 9 10 3"></polyline></svg>
							</span>
							<?php echo esc_html__( '60-minute session', 'cta-lms' ); ?>
						</li>
						<li class="checklist__item">
							<span class="checklist__icon" aria-hidden="true">
								<svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2"><polyline points="2 6 5 9 10 3"></polyline></svg>
							</span>
							<?php echo esc_html__( 'Personalized feedback', 'cta-lms' ); ?>
						</li>
						<li class="checklist__item">
							<span class="checklist__icon" aria-hidden="true">
								<svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2"><polyline points="2 6 5 9 10 3"></polyline></svg>
							</span>
							<?php echo esc_html__( 'Flexible scheduling', 'cta-lms' ); ?>
						</li>
						<li class="checklist__item">
							<span class="checklist__icon" aria-hidden="true">
								<svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2"><polyline points="2 6 5 9 10 3"></polyline></svg>
							</span>
							<?php echo esc_html__( 'Pay per session', 'cta-lms' ); ?>
						</li>
					</ul>
					<?php if ( ! $is_logged_in ) : ?>
						<a href="<?php echo esc_url( $login_url ); ?>" class="btn btn-primary btn--lg service-card__cta">
							<?php echo esc_html__( 'Login to Book', 'cta-lms' ); ?>
						</a>
					<?php elseif ( $can_book_individual ) : ?>
						<a href="#booking" class="btn btn-primary btn--lg service-card__cta">
							<?php echo esc_html__( 'Book Individual Session', 'cta-lms' ); ?>
						</a>
						<?php if ( $can_purchase_supervision ) : ?>
							<button type="button" class="btn btn-outline btn--lg service-card__cta cta-individual-session-btn" data-cta-individual-session-purchase data-course-title="<?php echo esc_attr( $individual_session_name ); ?>" data-price="<?php echo esc_attr( $individual_price_label ); ?>" style="margin-top:0.75rem;">
								<?php
								echo esc_html(
									$individual_credits > 0
										? __( 'Buy Another Session', 'cta-lms' )
										: __( 'Purchase Session', 'cta-lms' )
								);
								?>
							</button>
						<?php endif; ?>
					<?php elseif ( ! $can_purchase_supervision ) : ?>
						<p class="service-card__note"><?php echo esc_html( $associate_required_message ); ?></p>
						<a href="<?php echo esc_url( $register_url ); ?>" class="btn btn-primary btn--lg service-card__cta">
							<?php echo esc_html__( 'Register as Associate', 'cta-lms' ); ?>
						</a>
					<?php else : ?>
						<button type="button" class="btn btn-primary btn--lg service-card__cta cta-individual-session-btn" data-cta-individual-session-purchase data-course-title="<?php echo esc_attr( $individual_session_name ); ?>" data-price="<?php echo esc_attr( $individual_price_label ); ?>">
							<?php echo esc_html__( 'Purchase Session', 'cta-lms' ); ?>
						</button>
					<?php endif; ?>
					<?php if ( $is_logged_in && $individual_credits > 0 ) : ?>
						<p class="service-card__note" style="margin-top:0.75rem;">
							<?php
							printf(
								/* translators: %d: prepaid session credit count */
								esc_html( _n( '%d session credit available', '%d session credits available', $individual_credits, 'cta-lms' ) ),
								(int) $individual_credits
							);
							?>
						</p>
					<?php endif; ?>
				</article>

			</div>
		</div>
	</section>

	<section class="how-it-works section--bg" aria-labelledby="how-it-works-title">
		<div class="cta-container">
			<header class="section__header">
				<h2 class="text-h2" id="how-it-works-title"><?php echo esc_html__( 'How It Works', 'cta-lms' ); ?></h2>
			</header>
			<div class="how-it-works__steps">
				<div class="how-it-works__step">
					<span class="how-it-works__number" aria-hidden="true">1</span>
					<p class="how-it-works__label"><?php echo esc_html__( 'Choose Your Plan', 'cta-lms' ); ?></p>
				</div>
				<div class="how-it-works__step">
					<span class="how-it-works__number" aria-hidden="true">2</span>
					<p class="how-it-works__label"><?php echo esc_html__( 'Create Account & Subscribe', 'cta-lms' ); ?></p>
				</div>
				<div class="how-it-works__step">
					<span class="how-it-works__number" aria-hidden="true">3</span>
					<p class="how-it-works__label"><?php echo esc_html__( 'Book Your Sessions', 'cta-lms' ); ?></p>
				</div>
				<div class="how-it-works__step">
					<span class="how-it-works__number" aria-hidden="true">4</span>
					<p class="how-it-works__label"><?php echo esc_html__( 'Attend & Track Hours', 'cta-lms' ); ?></p>
				</div>
			</div>
		</div>
	</section>

	<section class="bbs-compliance section--bg" aria-labelledby="bbs-compliance-title">
		<div class="cta-container">
			<header class="bbs-compliance__header">
				<h2 class="text-h2" id="bbs-compliance-title"><?php echo esc_html__( 'BBS-Compliant from Day One', 'cta-lms' ); ?></h2>
			</header>
			<div class="bbs-compliance__grid">
				<article class="bbs-compliance__item">
					<div class="bbs-compliance__icon" aria-hidden="true">
						<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
					</div>
					<h3 class="bbs-compliance__title"><?php echo esc_html__( 'Document Upload', 'cta-lms' ); ?></h3>
					<p class="bbs-compliance__text"><?php echo esc_html__( 'Upload BBS supervision agreements and weekly supervision logs directly to your dashboard.', 'cta-lms' ); ?></p>
				</article>
				<article class="bbs-compliance__item">
					<div class="bbs-compliance__icon" aria-hidden="true">
						<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
					</div>
					<h3 class="bbs-compliance__title"><?php echo esc_html__( 'Weekly Hour Tracking', 'cta-lms' ); ?></h3>
					<p class="bbs-compliance__text"><?php echo esc_html__( 'Log AMFT, ASW, and APCC supervision hours with structured weekly tracking tools.', 'cta-lms' ); ?></p>
				</article>
				<article class="bbs-compliance__item">
					<div class="bbs-compliance__icon" aria-hidden="true">
						<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
					</div>
					<h3 class="bbs-compliance__title"><?php echo esc_html__( '8-Person Group Cap', 'cta-lms' ); ?></h3>
					<p class="bbs-compliance__text"><?php echo esc_html__( 'Group sizes are capped at 8 associates per session, enforced automatically per California law.', 'cta-lms' ); ?></p>
				</article>
			</div>
		</div>
	</section>

	<section class="booking-section" id="booking" aria-labelledby="booking-title">
		<div class="cta-container">
			<header class="booking-section__header">
				<h2 class="text-h2" id="booking-title"><?php echo esc_html__( 'Book Your Session', 'cta-lms' ); ?></h2>
			</header>

			<?php if ( ! $can_book_sessions ) : ?>
				<div class="cta-empty-state">
					<?php if ( ! $is_logged_in ) : ?>
						<h3><?php echo esc_html__( 'Log in to view available sessions', 'cta-lms' ); ?></h3>
						<p><?php echo esc_html__( 'Subscribe to Group Supervision or purchase an Individual 1-on-1 session to access the booking calendar.', 'cta-lms' ); ?></p>
						<a href="<?php echo esc_url( $login_url ); ?>" class="btn btn-primary"><?php echo esc_html__( 'Login to Book', 'cta-lms' ); ?></a>
					<?php elseif ( 'pending_approval' === $user_status ) : ?>
						<h3><?php echo esc_html__( 'Supervision Application Pending', 'cta-lms' ); ?></h3>
						<p><?php echo esc_html( CTA_Associate_Access::get_pending_message() ); ?></p>
						<p><?php echo esc_html__( 'Session booking stays locked until your supervision application is approved. CTA admin has been notified to review your application.', 'cta-lms' ); ?></p>
						<?php if ( $ce_dashboard_url ) : ?>
							<p style="margin-top:1rem;">
								<a href="<?php echo esc_url( $ce_dashboard_url ); ?>" class="btn btn-primary"><?php echo esc_html__( 'Go to My Courses', 'cta-lms' ); ?></a>
							</p>
						<?php endif; ?>
					<?php elseif ( 'awaiting_plan' === $user_status || ( $is_logged_in && class_exists( 'CTA_Associate_Access' ) && CTA_Associate_Access::is_approved_awaiting_plan( get_current_user_id() ) ) ) : ?>
						<h3><?php echo esc_html__( 'Application Approved', 'cta-lms' ); ?></h3>
						<p><?php echo esc_html( CTA_Associate_Access::get_approved_awaiting_plan_message() ); ?></p>
						<p><?php echo esc_html__( 'Choose Group Supervision (monthly) or purchase an Individual 1-on-1 session ($120/session) above.', 'cta-lms' ); ?></p>
						<button type="button" class="btn btn-primary cta-individual-session-btn" data-cta-individual-session-purchase data-course-title="<?php echo esc_attr( $individual_session_name ); ?>" data-price="<?php echo esc_attr( $individual_price_label ); ?>">
							<?php echo esc_html__( 'Purchase Individual Session', 'cta-lms' ); ?>
						</button>
					<?php elseif ( 'rejected' === $user_status ) : ?>
						<h3><?php echo esc_html__( 'Supervision access unavailable', 'cta-lms' ); ?></h3>
						<p><?php echo esc_html__( 'Your supervision application was not approved. Please contact support if you believe this is an error.', 'cta-lms' ); ?></p>
					<?php elseif ( 'locked' === $user_status ) : ?>
						<h3><?php echo esc_html__( 'Your supervision access is locked', 'cta-lms' ); ?></h3>
						<p><?php echo esc_html__( 'Please contact support to restore booking access.', 'cta-lms' ); ?></p>
					<?php elseif ( ! $can_purchase_supervision ) : ?>
						<h3><?php echo esc_html__( 'Registered Associates only', 'cta-lms' ); ?></h3>
						<p><?php echo esc_html( $associate_required_message ); ?></p>
						<a href="<?php echo esc_url( $register_url ); ?>" class="btn btn-primary">
							<?php echo esc_html__( 'Register as Associate', 'cta-lms' ); ?>
						</a>
					<?php else : ?>
						<h3><?php echo esc_html__( 'Choose a supervision option to book', 'cta-lms' ); ?></h3>
						<p><?php echo esc_html__( 'Subscribe to Group Supervision for monthly sessions, or purchase an Individual 1-on-1 session credit ($120/session).', 'cta-lms' ); ?></p>
						<button type="button" class="btn btn-primary cta-subscribe-btn" data-cta-supervision-subscribe data-course-title="<?php echo esc_attr( CTA_Supervision_Plans::get_name( CTA_Supervision_Plans::GROUP_SLUG ) ); ?>" data-price="<?php echo esc_attr( CTA_Supervision_Plans::get_price_label( CTA_Supervision_Plans::GROUP_SLUG ) ); ?>">
							<?php echo esc_html__( 'Subscribe to Group', 'cta-lms' ); ?>
						</button>
						<button type="button" class="btn btn-outline cta-individual-session-btn" data-cta-individual-session-purchase data-course-title="<?php echo esc_attr( $individual_session_name ); ?>" data-price="<?php echo esc_attr( $individual_price_label ); ?>" style="margin-left:0.5rem;">
							<?php echo esc_html__( 'Purchase Individual Session', 'cta-lms' ); ?>
						</button>
					<?php endif; ?>
				</div>
			<?php elseif ( empty( $sessions ) ) : ?>
				<div class="cta-empty-state">
					<h3><?php echo esc_html__( 'No sessions available yet', 'cta-lms' ); ?></h3>
					<p><?php echo esc_html__( 'Check back soon for upcoming supervision times.', 'cta-lms' ); ?></p>
				</div>
			<?php else : ?>
				<div class="cta-supervision-booking__layout">
					<div
						class="booking-calendar cta-booking-calendar"
						data-selected-date="<?php echo esc_attr( $selected_date ); ?>"
						aria-label="<?php echo esc_attr__( 'Booking calendar', 'cta-lms' ); ?>"
					>
						<p class="booking-calendar__month"><?php echo esc_html( $month_label ); ?></p>
						<div class="booking-calendar__weekdays" aria-hidden="true">
							<?php
							$weekdays = array(
								__( 'Sun', 'cta-lms' ),
								__( 'Mon', 'cta-lms' ),
								__( 'Tue', 'cta-lms' ),
								__( 'Wed', 'cta-lms' ),
								__( 'Thu', 'cta-lms' ),
								__( 'Fri', 'cta-lms' ),
								__( 'Sat', 'cta-lms' ),
							);
							foreach ( $weekdays as $weekday ) :
								?>
								<span class="booking-calendar__weekday"><?php echo esc_html( $weekday ); ?></span>
							<?php endforeach; ?>
						</div>
						<div class="booking-calendar__days">
							<?php
							for ( $i = 0; $i < $first_weekday; $i++ ) :
								?>
								<span class="booking-calendar__day booking-calendar__day--empty"></span>
							<?php endfor; ?>

							<?php
							for ( $day = 1; $day <= $days_in_month; $day++ ) :
								$date_str    = sprintf( '%s-%02d', substr( (string) $calendar_month, 0, 7 ), $day );
								$has_slots   = in_array( $date_str, $session_dates, true );
								$is_selected = ( $date_str === $selected_date );
								$classes     = array( 'booking-calendar__day', 'cta-calendar-day' );

								if ( $has_slots ) {
									$classes[] = 'booking-calendar__day--available';
								}
								if ( $is_selected ) {
									$classes[] = 'booking-calendar__day--selected';
								}
								?>
								<button
									type="button"
									class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
									data-date="<?php echo esc_attr( $date_str ); ?>"
									<?php echo $has_slots ? '' : 'disabled'; ?>
								>
									<?php echo esc_html( (string) $day ); ?>
								</button>
							<?php endfor; ?>
						</div>
						<p class="booking-calendar__hint"><?php echo esc_html__( 'Select a date to view available sessions', 'cta-lms' ); ?></p>
					</div>

					<div class="session-list cta-session-list" id="cta-supervision-sessions">
						<?php foreach ( $sessions as $session ) : ?>
							<?php include CTA_PLUGIN_DIR . 'templates/partials/session-card.php'; ?>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</section>

</div>
</div>

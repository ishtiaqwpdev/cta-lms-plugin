<?php
/**
 * Upcoming supervision session card.
 *
 * @package CTA_LMS
 *
 * @var object                   $session   Enriched booking row.
 * @var CTA_Supervision_Dashboard $dashboard Dashboard instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$type_class = 'group' === $session->session_type ? 'badge--primary' : 'badge--teal';
$type_label = 'group' === $session->session_type ? __( 'Group', 'cta-lms' ) : __( 'Individual', 'cta-lms' );
$session_dt    = cta_lms_session_datetime( $session->session_date, $session->session_time );
$datetime_attr = $session_dt
	? esc_attr( $session_dt->format( 'c' ) )
	: esc_attr( $session->session_date . 'T' . $session->session_time );
?>
<article
	class="card session-card cta-session-upcoming-card"
	data-booking-id="<?php echo esc_attr( $session->id ); ?>"
>
	<div class="session-card__info">
		<p class="session-card__datetime"><?php echo esc_html( $dashboard->format_session_date( $session->session_date ) ); ?></p>
		<p class="session-card__time"><?php echo esc_html( $dashboard->format_session_time( $session->session_date, $session->session_time ) ); ?></p>
		<p class="session-card__meta">
			<span class="badge <?php echo esc_attr( $type_class ); ?>"><?php echo esc_html( $type_label ); ?></span>
			<span><?php echo esc_html( $dashboard->format_duration_label( $session ) ); ?></span>
		</p>
		<?php if ( 'group' === $session->session_type ) : ?>
			<p class="session-card__seats">
				<?php
				printf(
					/* translators: 1: seats booked, 2: seats total */
					esc_html__( '%1$d of %2$d seats filled', 'cta-lms' ),
					(int) $session->seats_booked,
					(int) $session->seats_total
				);
				?>
			</p>
		<?php endif; ?>
		<span class="badge badge--success"><?php echo esc_html__( 'Confirmed', 'cta-lms' ); ?></span>
	</div>

	<div class="session-card__actions">
		<?php if ( ! empty( $session->can_join ) && ! empty( $session->meeting_url ) ) : ?>
			<a
				href="<?php echo esc_url( $session->meeting_url ); ?>"
				class="btn btn-primary btn--sm cta-join-session"
				target="_blank"
				rel="noopener noreferrer"
			>
				<?php echo esc_html__( 'Join Session', 'cta-lms' ); ?>
			</a>
		<?php endif; ?>
		<?php if ( $session->can_cancel ) : ?>
			<button
				type="button"
				class="btn btn-outline btn--sm cta-cancel-booking"
				data-booking-id="<?php echo esc_attr( $session->id ); ?>"
				data-session-date="<?php echo esc_attr( $session->session_date ); ?>"
				data-session-datetime="<?php echo $datetime_attr; ?>"
			>
				<?php echo esc_html__( 'Cancel Booking', 'cta-lms' ); ?>
			</button>
		<?php else : ?>
			<span class="text-small cta-cancel-blocked"><?php echo esc_html__( 'Cannot cancel (within 24 hours)', 'cta-lms' ); ?></span>
		<?php endif; ?>
	</div>
</article>

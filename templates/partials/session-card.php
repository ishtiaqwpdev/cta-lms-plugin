<?php
/**
 * Supervision session card partial.
 *
 * @package CTA_LMS
 *
 * @var object $session       Session slot row.
 * @var array  $user_bookings Map of session keys to booking IDs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$session_key = $session->session_date . '|' . $session->session_time . '|' . $session->session_type;
$user_booked = isset( $user_bookings[ $session_key ] );
$booking_id  = $user_booked ? (int) $user_bookings[ $session_key ] : 0;

$seats_total     = 'group' === $session->session_type ? CTA_Supervision::GROUP_SEATS_MAX : 1;
$seats_remaining = max( 0, $seats_total - (int) $session->seats_booked );
$is_full         = $seats_remaining <= 0;

$type_label = 'group' === $session->session_type
	? __( 'Group', 'cta-lms' )
	: __( 'Individual', 'cta-lms' );

$duration_label = 'group' === $session->session_type
	? __( '2 hours', 'cta-lms' )
	: __( '60 minutes', 'cta-lms' );

$supervision = isset( $supervision ) ? $supervision : null;
$datetime    = $supervision instanceof CTA_Supervision
	? $supervision->format_session_datetime( $session->session_date, $session->session_time )
	: esc_html( $session->session_date . ' ' . $session->session_time );
?>
<article
	class="card session-card cta-session-card"
	data-session-id="<?php echo esc_attr( $session->id ); ?>"
	data-session-date="<?php echo esc_attr( $session->session_date ); ?>"
	data-session-type="<?php echo esc_attr( $session->session_type ); ?>"
	<?php echo $user_booked ? 'data-booking-id="' . esc_attr( $booking_id ) . '"' : ''; ?>
>
	<div class="session-card__info">
		<p class="session-card__datetime"><?php echo esc_html( $datetime ); ?></p>
		<p class="session-card__type">
			<span class="badge <?php echo 'group' === $session->session_type ? 'badge--primary' : 'badge--outline'; ?>">
				<?php echo esc_html( $type_label ); ?>
			</span>
			<?php echo esc_html( $duration_label ); ?>
		</p>
		<?php if ( 'group' === $session->session_type ) : ?>
			<p class="session-card__seats cta-session-seats">
				<?php if ( $is_full ) : ?>
					<span class="badge badge--outline"><?php echo esc_html__( 'Full', 'cta-lms' ); ?></span>
				<?php else : ?>
					<?php
					printf(
						/* translators: %d: seats remaining */
						esc_html__( '%d seats remaining', 'cta-lms' ),
						(int) $seats_remaining
					);
					?>
				<?php endif; ?>
			</p>
		<?php endif; ?>
	</div>

	<div class="session-card__actions">
		<?php if ( $user_booked ) : ?>
			<span class="badge badge--success cta-session-booked-label">
				<?php echo cta_lms_get_icon( 'check-circle', 14, 'cta-icon cta-icon--inline' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo esc_html__( 'Booked', 'cta-lms' ); ?>
			</span>
			<button
				type="button"
				class="btn btn-outline btn--sm cta-cancel-btn"
				data-booking-id="<?php echo esc_attr( $booking_id ); ?>"
				data-session-id="<?php echo esc_attr( $session->id ); ?>"
			>
				<?php echo esc_html__( 'Cancel', 'cta-lms' ); ?>
			</button>
		<?php elseif ( $is_full ) : ?>
			<button type="button" class="btn btn-primary cta-book-btn" disabled>
				<?php echo esc_html__( 'Full', 'cta-lms' ); ?>
			</button>
		<?php else : ?>
			<button
				type="button"
				class="btn btn-primary cta-book-btn"
				data-session-id="<?php echo esc_attr( $session->id ); ?>"
				data-session-type="<?php echo esc_attr( $session->session_type ); ?>"
			>
				<?php echo esc_html__( 'Book Session', 'cta-lms' ); ?>
			</button>
		<?php endif; ?>
	</div>
</article>

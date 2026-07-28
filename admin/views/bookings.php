<?php
/**
 * Admin bookings view.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap cta-admin-wrap">
	<div class="cta-admin-header-row">
		<h1><?php esc_html_e( 'Supervision Bookings', 'cta-lms' ); ?></h1>
		<?php if ( 'upcoming' === $tab ) : ?>
			<button type="button" class="page-title-action" id="cta-open-session-modal"><?php esc_html_e( 'Add New Session', 'cta-lms' ); ?></button>
		<?php endif; ?>
	</div>

	<div class="cta-admin-tabs">
		<a class="cta-admin-tab <?php echo 'upcoming' === $tab ? 'cta-admin-tab--active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=cta-lms-bookings&tab=upcoming' ) ); ?>"><?php esc_html_e( 'Upcoming Sessions', 'cta-lms' ); ?></a>
		<a class="cta-admin-tab <?php echo 'history' === $tab ? 'cta-admin-tab--active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=cta-lms-bookings&tab=history' ) ); ?>"><?php esc_html_e( 'Session History', 'cta-lms' ); ?></a>
	</div>

	<table class="widefat striped cta-admin-table">
		<thead>
			<?php if ( 'history' === $tab ) : ?>
				<tr>
					<th><?php esc_html_e( 'User', 'cta-lms' ); ?></th>
					<th><?php esc_html_e( 'Date', 'cta-lms' ); ?></th>
					<th><?php esc_html_e( 'Time', 'cta-lms' ); ?></th>
					<th><?php esc_html_e( 'Type', 'cta-lms' ); ?></th>
					<th><?php esc_html_e( 'Status', 'cta-lms' ); ?></th>
				</tr>
			<?php else : ?>
				<tr>
					<th><?php esc_html_e( 'Date', 'cta-lms' ); ?></th>
					<th><?php esc_html_e( 'Time', 'cta-lms' ); ?></th>
					<th><?php esc_html_e( 'Type', 'cta-lms' ); ?></th>
					<th><?php esc_html_e( 'Booked / Total', 'cta-lms' ); ?></th>
					<th><?php esc_html_e( 'Status', 'cta-lms' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'cta-lms' ); ?></th>
				</tr>
			<?php endif; ?>
		</thead>
		<tbody id="cta-sessions-list">
			<?php if ( empty( $sessions ) ) : ?>
				<tr><td colspan="<?php echo 'history' === $tab ? '5' : '6'; ?>"><?php esc_html_e( 'No sessions found.', 'cta-lms' ); ?></td></tr>
			<?php elseif ( 'history' === $tab ) : ?>
				<?php foreach ( $sessions as $session ) : ?>
					<tr>
						<td><?php echo esc_html( $session->display_name ? $session->display_name : __( 'Unknown', 'cta-lms' ) ); ?></td>
						<td><?php echo esc_html( cta_lms_format_session_date( $session->session_date, 'M j, Y' ) ); ?></td>
						<td><?php echo esc_html( cta_lms_format_session_time( $session->session_date, $session->session_time, 'g:i A T' ) ); ?></td>
						<td><?php echo esc_html( ucfirst( $session->session_type ) ); ?></td>
						<td><?php echo esc_html( ucfirst( $session->status ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<?php foreach ( $sessions as $session ) : ?>
					<?php echo $admin->render_session_row_html( $session ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>

<div id="cta-session-modal" class="cta-admin-modal" hidden>
	<div class="cta-admin-modal__content">
		<button type="button" class="cta-admin-modal__close" aria-label="<?php esc_attr_e( 'Close', 'cta-lms' ); ?>">&times;</button>
		<h2><?php esc_html_e( 'Add Supervision Session', 'cta-lms' ); ?></h2>
		<form id="cta-add-session-form">
			<p>
				<label for="cta-session-date"><?php esc_html_e( 'Date', 'cta-lms' ); ?></label><br>
				<input type="date" id="cta-session-date" required>
			</p>
			<p>
				<label for="cta-session-time"><?php esc_html_e( 'Time', 'cta-lms' ); ?></label><br>
				<input type="time" id="cta-session-time" required>
				<span class="description"><?php echo esc_html( sprintf( /* translators: %s: timezone name */ __( 'Enter time in %s (Pacific by default).', 'cta-lms' ), cta_lms_get_timezone_string() ) ); ?></span>
			</p>
			<p>
				<label for="cta-session-type"><?php esc_html_e( 'Type', 'cta-lms' ); ?></label><br>
				<select id="cta-session-type">
					<option value="group"><?php esc_html_e( 'Group (max 8 seats, 120 min)', 'cta-lms' ); ?></option>
					<option value="individual"><?php esc_html_e( 'Individual (1 seat, 60 min)', 'cta-lms' ); ?></option>
				</select>
			</p>
			<p id="cta-session-seats-wrap">
				<label for="cta-session-seats"><?php esc_html_e( 'Total Seats', 'cta-lms' ); ?></label><br>
				<input type="number" id="cta-session-seats" min="1" max="8" value="8">
			</p>
			<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Create Session', 'cta-lms' ); ?></button></p>
		</form>
	</div>
</div>

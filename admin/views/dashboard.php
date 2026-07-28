<?php
/**
 * Admin dashboard view.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$notice = sanitize_text_field( wp_unslash( $_GET['cta_notice'] ?? '' ) );
?>
<div class="wrap cta-admin-wrap">
	<h1><?php esc_html_e( 'CTA LMS Dashboard', 'cta-lms' ); ?></h1>

	<?php if ( 'settings_saved' === $notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'cta-lms' ); ?></p></div>
	<?php endif; ?>

	<div class="cta-admin-stats">
		<div class="cta-stat-card">
			<span class="cta-stat-card__label"><?php esc_html_e( 'Published Courses', 'cta-lms' ); ?></span>
			<strong class="cta-stat-card__value"><?php echo esc_html( (string) $stats['total_courses'] ); ?></strong>
		</div>
		<div class="cta-stat-card">
			<span class="cta-stat-card__label"><?php esc_html_e( 'Enrolled Users', 'cta-lms' ); ?></span>
			<strong class="cta-stat-card__value"><?php echo esc_html( (string) $stats['total_enrolled'] ); ?></strong>
		</div>
		<div class="cta-stat-card">
			<span class="cta-stat-card__label"><?php esc_html_e( 'Completions', 'cta-lms' ); ?></span>
			<strong class="cta-stat-card__value"><?php echo esc_html( (string) $stats['total_completions'] ); ?></strong>
		</div>
		<div class="cta-stat-card">
			<span class="cta-stat-card__label"><?php esc_html_e( 'Total Revenue', 'cta-lms' ); ?></span>
			<strong class="cta-stat-card__value">$<?php echo esc_html( number_format( $stats['total_revenue'], 2 ) ); ?></strong>
		</div>
		<div class="cta-stat-card">
			<span class="cta-stat-card__label"><?php esc_html_e( 'Active Subscribers', 'cta-lms' ); ?></span>
			<strong class="cta-stat-card__value"><?php echo esc_html( (string) $stats['active_subscribers'] ); ?></strong>
		</div>
		<div class="cta-stat-card">
			<span class="cta-stat-card__label"><?php esc_html_e( 'Certificates Issued', 'cta-lms' ); ?></span>
			<strong class="cta-stat-card__value"><?php echo esc_html( (string) $stats['certificates_issued'] ); ?></strong>
		</div>
	</div>

	<div class="cta-admin-grid">
		<div class="cta-admin-panel">
			<h2><?php esc_html_e( 'Recent Enrollments', 'cta-lms' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'User', 'cta-lms' ); ?></th>
						<th><?php esc_html_e( 'Course', 'cta-lms' ); ?></th>
						<th><?php esc_html_e( 'Date', 'cta-lms' ); ?></th>
						<th><?php esc_html_e( 'Payment', 'cta-lms' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $recent_enrollments ) ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No enrollments yet.', 'cta-lms' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $recent_enrollments as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row->display_name ? $row->display_name : __( 'Unknown', 'cta-lms' ) ); ?></td>
								<td><?php echo esc_html( $row->course_title ? $row->course_title : '#' . (int) $row->course_id ); ?></td>
								<td><?php echo esc_html( cta_lms_format_local_date( $row->enrolled_at, 'M j, Y' ) ); ?></td>
								<td><?php echo esc_html( $row->payment_status ? $row->payment_status : __( 'N/A', 'cta-lms' ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<div class="cta-admin-panel">
			<h2><?php esc_html_e( 'Recent Bookings', 'cta-lms' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'User', 'cta-lms' ); ?></th>
						<th><?php esc_html_e( 'Session', 'cta-lms' ); ?></th>
						<th><?php esc_html_e( 'Date', 'cta-lms' ); ?></th>
						<th><?php esc_html_e( 'Status', 'cta-lms' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $recent_bookings ) ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No bookings yet.', 'cta-lms' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $recent_bookings as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row->display_name ? $row->display_name : __( 'Unknown', 'cta-lms' ) ); ?></td>
								<td><?php echo esc_html( ucfirst( $row->session_type ) ); ?></td>
								<td><?php echo esc_html( cta_lms_format_session_datetime( $row->session_date, $row->session_time, 'M j, Y g:i A T' ) ); ?></td>
								<td><?php echo esc_html( ucfirst( $row->status ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>

	<div class="cta-admin-quick-links">
		<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=cta-lms-course-edit' ) ); ?>"><?php esc_html_e( 'Add New Course', 'cta-lms' ); ?></a>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cta-lms-approvals' ) ); ?>"><?php esc_html_e( 'Approvals', 'cta-lms' ); ?></a>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cta-lms-users' ) ); ?>"><?php esc_html_e( 'View All Users', 'cta-lms' ); ?></a>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cta-lms-settings' ) ); ?>"><?php esc_html_e( 'Configure Settings', 'cta-lms' ); ?></a>
	</div>
</div>

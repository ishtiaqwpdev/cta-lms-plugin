<?php
/**
 * Admin supervision purchase approvals.
 *
 * @package CTA_LMS
 *
 * @var array  $purchase_records Supervision purchase/user records.
 * @var string $current_status   Filter: all|pending_approval|approved|rejected.
 * @var array  $status_counts    Counts keyed by status.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$flash            = sanitize_text_field( wp_unslash( $_GET['cta_approval'] ?? '' ) );
$current_status   = isset( $current_status ) ? $current_status : 'all';
$status_counts    = isset( $status_counts ) && is_array( $status_counts ) ? $status_counts : array();
$purchase_records = isset( $purchase_records ) && is_array( $purchase_records ) ? $purchase_records : array();

$base_url = admin_url( 'admin.php?page=cta-lms-approvals' );
$tabs     = array(
	'all'              => __( 'All', 'cta-lms' ),
	'pending_approval' => __( 'Pending Approval', 'cta-lms' ),
	'approved'         => __( 'Approved', 'cta-lms' ),
	'rejected'         => __( 'Rejected', 'cta-lms' ),
);

$empty_messages = array(
	'all'              => __( 'No Associates awaiting review yet.', 'cta-lms' ),
	'pending_approval' => __( 'No Associates are currently pending approval.', 'cta-lms' ),
	'approved'         => __( 'No approved Associates found.', 'cta-lms' ),
	'rejected'         => __( 'No rejected Associates found.', 'cta-lms' ),
);
?>
<div class="wrap cta-admin-wrap">
	<h1><?php esc_html_e( 'Supervision Approvals', 'cta-lms' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Approval Status is application vetting. Plan Status is whether they purchased or were assigned a plan. Full dashboard/booking access requires both Approved and an active plan.', 'cta-lms' ); ?>
	</p>

	<?php if ( 'approved' === $flash ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Associate approved. Supervision access unlocks once they have a purchased or admin-assigned plan.', 'cta-lms' ); ?></p></div>
	<?php elseif ( 'assigned' === $flash ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Agency-paid plan assigned. If the Associate is already Approved, supervision access is now active.', 'cta-lms' ); ?></p></div>
	<?php elseif ( 'rejected' === $flash ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Associate rejected. Supervision access remains locked.', 'cta-lms' ); ?></p></div>
	<?php elseif ( 'error' === $flash ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Unable to update approval status. Please try again.', 'cta-lms' ); ?></p></div>
	<?php endif; ?>

	<div id="cta-approvals-notice" class="notice" hidden></div>

	<ul class="subsubsub cta-approvals-tabs">
		<?php
		$tab_keys = array_keys( $tabs );
		$last_key = end( $tab_keys );
		foreach ( $tabs as $slug => $label ) :
			$count = isset( $status_counts[ $slug ] ) ? (int) $status_counts[ $slug ] : 0;
			$url   = 'all' === $slug ? $base_url : add_query_arg( 'status', $slug, $base_url );
			$class = $current_status === $slug ? 'current' : '';
			?>
			<li class="<?php echo esc_attr( $slug ); ?>">
				<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $class ); ?>">
					<?php echo esc_html( $label ); ?>
					<span class="count">(<?php echo esc_html( (string) $count ); ?>)</span>
				</a>
				<?php if ( $slug !== $last_key ) : ?> | <?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>

	<table class="widefat striped cta-admin-table" id="cta-pending-approvals-table" data-current-status="<?php echo esc_attr( $current_status ); ?>">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Associate', 'cta-lms' ); ?></th>
				<th><?php esc_html_e( 'Email', 'cta-lms' ); ?></th>
				<th><?php esc_html_e( 'Approval Status', 'cta-lms' ); ?></th>
				<th><?php esc_html_e( 'Plan Status', 'cta-lms' ); ?></th>
				<th><?php esc_html_e( 'Access', 'cta-lms' ); ?></th>
				<th><?php esc_html_e( 'Purchase / Assigned', 'cta-lms' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'cta-lms' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $purchase_records ) ) : ?>
				<tr class="cta-approvals-empty">
					<td colspan="7"><?php echo esc_html( $empty_messages[ $current_status ] ?? $empty_messages['all'] ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $purchase_records as $record ) : ?>
					<?php
					$user             = $record['user'];
					$payment          = ! empty( $record['payment'] ) ? $record['payment'] : null;
					$approval_status  = $record['status'];
					$status_label     = CTA_Associate_Access::get_status_label( $approval_status );
					$is_approved      = CTA_Associate_Access::STATUS_APPROVED === $approval_status;
					$is_rejected      = CTA_Associate_Access::STATUS_REJECTED === $approval_status;
					$has_plan         = ! empty( $record['has_plan'] );
					$plan_status_key  = isset( $record['plan_status_key'] ) ? $record['plan_status_key'] : ( $has_plan ? 'purchased' : 'none' );
					$plan_status_label = isset( $record['plan_status_label'] ) ? $record['plan_status_label'] : ( $has_plan ? $record['plan_name'] : __( 'No Plan', 'cta-lms' ) );
					$access_granted   = ! empty( $record['access_granted'] );
					$audit            = ! empty( $record['admin_plan_audit'] ) && is_array( $record['admin_plan_audit'] ) ? $record['admin_plan_audit'] : null;
					$purchase_date    = ( $payment && ! empty( $payment->created_at ) && 'completed' === (string) ( $payment->status ?? '' ) )
						? cta_lms_format_local_date( $payment->created_at, 'M j, Y g:i a' )
						: ( $audit && ! empty( $audit['assigned_at'] )
							? cta_lms_format_local_date( $audit['assigned_at'], 'M j, Y g:i a' )
							: '—' );
					$plan_details     = is_array( $record['plan_details'] ) ? $record['plan_details'] : array();
					$details_payload  = array(
						'user_name'         => $user->display_name,
						'user_email'        => $user->user_email,
						'approval_status'   => $status_label,
						'plan_status'       => $plan_status_label,
						'access'            => $access_granted ? __( 'Full access', 'cta-lms' ) : __( 'Locked', 'cta-lms' ),
						'plan_name'         => $record['plan_name'],
						'purchase_date'     => $purchase_date,
						'registered_date'   => ! empty( $user->user_registered )
							? cta_lms_format_local_date( $user->user_registered, 'M j, Y g:i a' )
							: '',
						'amount'            => ( $payment && 'completed' === (string) ( $payment->status ?? '' ) )
							? ( '$' . number_format( (float) $payment->amount, 2 ) . ' ' . strtoupper( (string) $payment->currency ) )
							: '',
						'billing'           => $payment
							? sanitize_text_field( (string) ( $plan_details['billing'] ?? $payment->payment_type ) )
							: '',
						'description'       => sanitize_text_field( (string) ( $plan_details['description'] ?? '' ) ),
						'stripe_reference'  => $payment ? sanitize_text_field( (string) $payment->stripe_payment_id ) : '',
						'assigned_by'       => $audit ? (string) ( $audit['assigned_by_name'] ?? '' ) : '',
						'assigned_note'     => $audit ? (string) ( $audit['note'] ?? '' ) : '',
						'status'            => $status_label,
						'rejection_reason'  => $record['rejection_reason'],
					);
					?>
					<tr
						class="cta-approval-row"
						data-user-id="<?php echo esc_attr( $user->ID ); ?>"
						data-status="<?php echo esc_attr( $approval_status ); ?>"
						data-has-plan="<?php echo $has_plan ? '1' : '0'; ?>"
						data-plan-status="<?php echo esc_attr( $plan_status_key ); ?>"
					>
						<td><strong><?php echo esc_html( $user->display_name ); ?></strong></td>
						<td><a href="mailto:<?php echo esc_attr( $user->user_email ); ?>"><?php echo esc_html( $user->user_email ); ?></a></td>
						<td>
							<span class="cta-approval-status-badge cta-approval-status-badge--<?php echo esc_attr( $approval_status ); ?>">
								<?php echo esc_html( $status_label ); ?>
							</span>
						</td>
						<td>
							<span class="cta-plan-status-badge cta-plan-status-badge--<?php echo esc_attr( $plan_status_key ); ?>">
								<?php echo esc_html( $plan_status_label ); ?>
							</span>
							<?php if ( $audit && ! empty( $audit['assigned_by_name'] ) ) : ?>
								<br><small class="description">
									<?php
									printf(
										/* translators: 1: admin name, 2: datetime */
										esc_html__( 'Assigned by %1$s on %2$s', 'cta-lms' ),
										esc_html( $audit['assigned_by_name'] ),
										esc_html( $purchase_date )
									);
									?>
								</small>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $access_granted ) : ?>
								<span class="cta-access-badge cta-access-badge--open"><?php esc_html_e( 'Full access', 'cta-lms' ); ?></span>
							<?php elseif ( $is_approved && ! $has_plan ) : ?>
								<span class="cta-access-badge cta-access-badge--awaiting"><?php esc_html_e( 'Awaiting plan', 'cta-lms' ); ?></span>
							<?php else : ?>
								<span class="cta-access-badge cta-access-badge--locked"><?php esc_html_e( 'Locked', 'cta-lms' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $purchase_date ); ?></td>
						<td class="cta-table-actions">
							<button
								type="button"
								class="button cta-view-supervision-purchase"
								data-purchase-details="<?php echo esc_attr( wp_json_encode( $details_payload ) ); ?>"
							>
								<?php esc_html_e( 'View Details', 'cta-lms' ); ?>
							</button>

							<?php if ( ! empty( $record['is_associate'] ) && ! $has_plan && ! $is_rejected ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cta-approval-form cta-approval-form--assign">
									<input type="hidden" name="action" value="cta_assign_associate_plan">
									<input type="hidden" name="user_id" value="<?php echo esc_attr( $user->ID ); ?>">
									<?php wp_nonce_field( 'cta_assign_plan_' . $user->ID, 'cta_assign_plan_nonce' ); ?>
									<select name="plan_slug" class="cta-assign-plan-select" aria-label="<?php esc_attr_e( 'Agency-paid plan', 'cta-lms' ); ?>">
										<option value="group"><?php echo esc_html( CTA_Supervision_Plans::get_name( CTA_Supervision_Plans::GROUP_SLUG ) ); ?></option>
										<option value="hybrid"><?php echo esc_html( CTA_Supervision_Plans::get_name( CTA_Supervision_Plans::HYBRID_SLUG ) ); ?></option>
									</select>
									<input type="text" name="note" class="regular-text" placeholder="<?php esc_attr_e( 'Optional note (agency/employer)', 'cta-lms' ); ?>" />
									<button type="submit" class="button cta-assign-associate-plan">
										<?php esc_html_e( 'Assign Plan', 'cta-lms' ); ?>
									</button>
								</form>
							<?php endif; ?>

							<?php if ( ! empty( $record['is_associate'] ) && ! $is_approved ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cta-approval-form cta-approval-form--approve">
									<input type="hidden" name="action" value="cta_approve_associate">
									<input type="hidden" name="user_id" value="<?php echo esc_attr( $user->ID ); ?>">
									<?php wp_nonce_field( 'cta_review_associate_' . $user->ID, 'cta_approval_nonce' ); ?>
									<button type="submit" class="button button-primary cta-approve-associate">
										<?php esc_html_e( 'Approve', 'cta-lms' ); ?>
									</button>
								</form>
							<?php endif; ?>

							<?php if ( ! empty( $record['is_associate'] ) && ! $is_rejected ) : ?>
								<button
									type="button"
									class="button cta-open-reject-associate"
									data-user-id="<?php echo esc_attr( $user->ID ); ?>"
									data-user-name="<?php echo esc_attr( $user->display_name ); ?>"
									data-review-nonce="<?php echo esc_attr( wp_create_nonce( 'cta_review_associate_' . $user->ID ) ); ?>"
								>
									<?php echo esc_html( $is_approved ? __( 'Revoke / Reject', 'cta-lms' ) : __( 'Reject', 'cta-lms' ) ); ?>
								</button>
							<?php elseif ( empty( $record['is_associate'] ) ) : ?>
								<span class="cta-approval-action-note"><?php esc_html_e( 'Not an Associate account', 'cta-lms' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<div id="cta-purchase-details-modal" class="cta-admin-modal" hidden>
		<div class="cta-admin-modal__content">
			<button type="button" class="cta-admin-modal__close" aria-label="<?php esc_attr_e( 'Close', 'cta-lms' ); ?>">&times;</button>
			<h2><?php esc_html_e( 'Associate Access Details', 'cta-lms' ); ?></h2>
			<dl id="cta-purchase-details-list" class="cta-purchase-details-list"></dl>
		</div>
	</div>

	<div id="cta-reject-associate-modal" class="cta-admin-modal" hidden>
		<div class="cta-admin-modal__content">
			<button type="button" class="cta-admin-modal__close" aria-label="<?php esc_attr_e( 'Close', 'cta-lms' ); ?>">&times;</button>
			<h2><?php esc_html_e( 'Reject Associate', 'cta-lms' ); ?></h2>
			<p id="cta-reject-associate-name"></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cta-approval-form cta-approval-form--reject">
				<input type="hidden" name="action" value="cta_reject_associate">
				<input type="hidden" name="user_id" id="cta-reject-user-id" value="">
				<input type="hidden" name="cta_approval_nonce" id="cta-reject-nonce" value="">
				<p>
					<label for="cta-rejection-reason"><strong><?php esc_html_e( 'Reason (optional)', 'cta-lms' ); ?></strong></label>
				</p>
				<textarea id="cta-rejection-reason" name="reason" rows="5" class="large-text" placeholder="<?php echo esc_attr__( 'Add an internal reason for rejecting this supervision application.', 'cta-lms' ); ?>"></textarea>
				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Confirm Rejection', 'cta-lms' ); ?></button>
					<button type="button" class="button cta-admin-modal__close"><?php esc_html_e( 'Cancel', 'cta-lms' ); ?></button>
				</p>
			</form>
		</div>
	</div>
</div>

<?php
/**
 * Admin settings view.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$notice = sanitize_text_field( wp_unslash( $_GET['cta_notice'] ?? '' ) );
?>
<div class="wrap cta-admin-wrap">
	<h1><?php esc_html_e( 'CTA LMS Settings', 'cta-lms' ); ?></h1>

	<?php if ( 'settings_saved' === $notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved successfully.', 'cta-lms' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cta-admin-form">
		<?php wp_nonce_field( 'cta_save_settings' ); ?>
		<input type="hidden" name="action" value="cta_save_settings">

		<div class="cta-admin-panel">
			<h2><?php esc_html_e( 'Stripe Configuration', 'cta-lms' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Mode', 'cta-lms' ); ?></th>
					<td>
						<label><input type="radio" name="cta_stripe_mode" value="test" <?php checked( get_option( 'cta_stripe_mode', 'test' ), 'test' ); ?>> <?php esc_html_e( 'Test', 'cta-lms' ); ?></label>
						<label><input type="radio" name="cta_stripe_mode" value="live" <?php checked( get_option( 'cta_stripe_mode', 'test' ), 'live' ); ?>> <?php esc_html_e( 'Live', 'cta-lms' ); ?></label>
					</td>
				</tr>
				<tr>
					<th><label for="cta_stripe_secret_key"><?php esc_html_e( 'Secret Key', 'cta-lms' ); ?></label></th>
					<td><input type="password" class="regular-text" id="cta_stripe_secret_key" name="cta_stripe_secret_key" value="<?php echo esc_attr( get_option( 'cta_stripe_secret_key', '' ) ); ?>" autocomplete="off"></td>
				</tr>
				<tr>
					<th><label for="cta_stripe_publishable_key"><?php esc_html_e( 'Publishable Key', 'cta-lms' ); ?></label></th>
					<td><input type="text" class="regular-text" id="cta_stripe_publishable_key" name="cta_stripe_publishable_key" value="<?php echo esc_attr( get_option( 'cta_stripe_publishable_key', '' ) ); ?>"></td>
				</tr>
				<tr>
					<th><label for="cta_stripe_webhook_secret"><?php esc_html_e( 'Webhook Secret', 'cta-lms' ); ?></label></th>
					<td><input type="password" class="regular-text" id="cta_stripe_webhook_secret" name="cta_stripe_webhook_secret" value="<?php echo esc_attr( get_option( 'cta_stripe_webhook_secret', '' ) ); ?>" autocomplete="off"></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Webhook URL', 'cta-lms' ); ?></th>
					<td><input type="text" class="large-text" readonly value="<?php echo esc_attr( $webhook_url ); ?>"></td>
				</tr>
			</table>
			<p>
				<button type="button" class="button" id="cta-test-stripe"><?php esc_html_e( 'Test Connection', 'cta-lms' ); ?></button>
				<span id="cta-stripe-test-result" class="cta-inline-result"></span>
			</p>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Testing Mode', 'cta-lms' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="cta_payments_bypass" value="yes" <?php checked( get_option( 'cta_payments_bypass', 'yes' ), 'yes' ); ?>>
							<?php esc_html_e( 'Skip payments (instant enroll / subscribe without Stripe)', 'cta-lms' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Enable only for UI demos without Stripe. This is NOT Stripe test mode. Turn this OFF to use real Stripe Checkout and the Customer Billing Portal (with Stripe test keys if you are still testing payments).', 'cta-lms' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Customer Billing Portal', 'cta-lms' ); ?></th>
					<td>
						<p class="description" style="margin-top:0;">
							<?php esc_html_e( "Students use Manage Subscription to open Stripe's Customer Portal (update payment method, view invoices, cancel auto-renewal at period end, and reactivate). The portal configuration is created automatically in your Stripe account on first use.", 'cta-lms' ); ?>
						</p>
						<?php
						$portal_config = (string) get_option( 'cta_stripe_portal_configuration_id', '' );
						if ( $portal_config ) :
							?>
							<p>
								<code><?php echo esc_html( $portal_config ); ?></code>
							</p>
						<?php endif; ?>
						<p>
							<button type="button" class="button" id="cta-ensure-portal"><?php esc_html_e( 'Ensure Portal Configuration', 'cta-lms' ); ?></button>
							<span id="cta-portal-test-result" class="cta-inline-result"></span>
						</p>
						<p class="description">
							<?php
							printf(
								/* translators: %s: Stripe dashboard URL */
								esc_html__( 'Webhook events required: checkout.session.completed, customer.subscription.updated, customer.subscription.deleted, invoice.paid, invoice.payment_failed. Dashboard: %s', 'cta-lms' ),
								'https://dashboard.stripe.com/' . ( 'live' === get_option( 'cta_stripe_mode', 'test' ) ? '' : 'test/' ) . 'settings/billing/portal'
							);
							?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<div class="cta-admin-panel">
			<h2><?php esc_html_e( 'Page Assignments', 'cta-lms' ); ?></h2>
			<table class="form-table">
				<?php foreach ( $page_options as $option_key => $label ) : ?>
					<tr>
						<th><label for="<?php echo esc_attr( $option_key ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td>
							<select id="<?php echo esc_attr( $option_key ); ?>" name="<?php echo esc_attr( $option_key ); ?>">
								<option value="0"><?php esc_html_e( '— Select Page —', 'cta-lms' ); ?></option>
								<?php foreach ( $pages as $page ) : ?>
									<option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( get_option( $option_key, 0 ), $page->ID ); ?>><?php echo esc_html( $page->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
		</div>

		<div class="cta-admin-panel">
			<h2><?php esc_html_e( 'CTA Configuration', 'cta-lms' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><label for="cta_timezone"><?php esc_html_e( 'Display Timezone', 'cta-lms' ); ?></label></th>
					<td>
						<select id="cta_timezone" name="cta_timezone">
							<?php
							$current_tz = (string) get_option( 'cta_timezone', 'America/Los_Angeles' );
							$zones      = timezone_identifiers_list();
							foreach ( $zones as $zone ) :
								?>
								<option value="<?php echo esc_attr( $zone ); ?>" <?php selected( $current_tz, $zone ); ?>><?php echo esc_html( $zone ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'All booking times, dashboards, emails, and admin timestamps display in this timezone. Default: America/Los_Angeles (Pacific Time).', 'cta-lms' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th><label for="cta_camft_provider_number"><?php esc_html_e( 'CAMFT CEPA Provider Number', 'cta-lms' ); ?></label></th>
					<td><input type="text" class="regular-text" id="cta_camft_provider_number" name="cta_camft_provider_number" value="<?php echo esc_attr( get_option( 'cta_camft_provider_number', '' ) ); ?>"></td>
				</tr>
				<tr>
					<th><label for="cta_admin_name"><?php esc_html_e( 'Program Administrator Name', 'cta-lms' ); ?></label></th>
					<td><input type="text" class="regular-text" id="cta_admin_name" name="cta_admin_name" value="<?php echo esc_attr( get_option( 'cta_admin_name', 'Candice Fuimaono, MS, LMFT' ) ); ?>"></td>
				</tr>
				<tr>
					<th><label for="cta_support_email"><?php esc_html_e( 'Support Email', 'cta-lms' ); ?></label></th>
					<td><input type="email" class="regular-text" id="cta_support_email" name="cta_support_email" value="<?php echo esc_attr( get_option( 'cta_support_email', 'support@clinicaltrainingacademy.com' ) ); ?>"></td>
				</tr>
			</table>
		</div>

		<div class="cta-admin-panel">
			<h2><?php esc_html_e( 'Certificate Settings', 'cta-lms' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><label for="cta_certificate_header_text"><?php esc_html_e( 'Certificate Header Text', 'cta-lms' ); ?></label></th>
					<td><input type="text" class="regular-text" id="cta_certificate_header_text" name="cta_certificate_header_text" value="<?php echo esc_attr( get_option( 'cta_certificate_header_text', 'Certificate of Completion' ) ); ?>"></td>
				</tr>
				<tr>
					<th><label for="cta_certificate_footer_text"><?php esc_html_e( 'Certificate Footer Text', 'cta-lms' ); ?></label></th>
					<td><input type="text" class="regular-text" id="cta_certificate_footer_text" name="cta_certificate_footer_text" value="<?php echo esc_attr( get_option( 'cta_certificate_footer_text', 'clinicaltrainingacademy.com' ) ); ?>"></td>
				</tr>
				<tr>
					<th><label for="cta_certificate_signature_name"><?php esc_html_e( 'Administrator Signature Name', 'cta-lms' ); ?></label></th>
					<td><input type="text" class="regular-text" id="cta_certificate_signature_name" name="cta_certificate_signature_name" value="<?php echo esc_attr( get_option( 'cta_certificate_signature_name', 'Candice Fuimaono, MS, LMFT' ) ); ?>"></td>
				</tr>
				<tr>
					<th><label for="cta_certificate_signature_image_url"><?php esc_html_e( 'Administrator Signature Image', 'cta-lms' ); ?></label></th>
					<td>
						<?php
						$sig_img_url = (string) get_option( 'cta_certificate_signature_image_url', '' );
						$bundled_sig = '';
						if ( class_exists( 'CTA_Certificates' ) ) {
							foreach ( CTA_Certificates::get_bundled_signature_paths() as $sig_path ) {
								if ( is_readable( $sig_path ) ) {
									$bundled_sig = CTA_PLUGIN_URL . 'assets/img/' . basename( $sig_path );
									break;
								}
							}
						}
						$preview_src = $sig_img_url ? $sig_img_url : $bundled_sig;
						?>
						<input type="url" class="regular-text" id="cta_certificate_signature_image_url" name="cta_certificate_signature_image_url" value="<?php echo esc_attr( $sig_img_url ); ?>" placeholder="https://…/signature.png">
						<p>
							<button type="button" class="button" id="cta-select-signature-image"><?php esc_html_e( 'Select from Media Library', 'cta-lms' ); ?></button>
							<button type="button" class="button" id="cta-clear-signature-image"><?php esc_html_e( 'Clear', 'cta-lms' ); ?></button>
						</p>
						<?php if ( $preview_src ) : ?>
							<p><img src="<?php echo esc_url( $preview_src ); ?>" alt="" style="max-width:220px;max-height:72px;height:auto;border:1px solid #d0d5dd;padding:6px;background:#fff;"></p>
						<?php endif; ?>
						<p class="description">
							<?php esc_html_e( 'Appears above the typed name on every CE certificate. Prefer a transparent PNG. Bundled fallback path: assets/img/certificate-signature.png', 'cta-lms' ); ?>
						</p>
					</td>
				</tr>
			</table>
			<p>
				<button type="button" class="button" id="cta-preview-certificate"><?php esc_html_e( 'Preview Certificate', 'cta-lms' ); ?></button>
			</p>
		</div>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'cta-lms' ); ?></button>
		</p>
	</form>
</div>
<script>
(function ($) {
	$(function () {
		var frame;
		$('#cta-select-signature-image').on('click', function (e) {
			e.preventDefault();
			if (frame) {
				frame.open();
				return;
			}
			frame = wp.media({
				title: '<?php echo esc_js( __( 'Select signature image', 'cta-lms' ) ); ?>',
				button: { text: '<?php echo esc_js( __( 'Use this signature', 'cta-lms' ) ); ?>' },
				library: { type: 'image' },
				multiple: false
			});
			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				if (attachment && attachment.url) {
					$('#cta_certificate_signature_image_url').val(attachment.url);
				}
			});
			frame.open();
		});
		$('#cta-clear-signature-image').on('click', function (e) {
			e.preventDefault();
			$('#cta_certificate_signature_image_url').val('');
		});
	});
})(jQuery);
</script>

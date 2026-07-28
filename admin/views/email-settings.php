<?php
/**
 * Admin automated email settings.
 *
 * @package CTA_LMS
 *
 * @var array $email_types Configurable email definitions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$notice = sanitize_text_field( wp_unslash( $_GET['cta_notice'] ?? '' ) );
$common_placeholders = array(
	'{program_admin_name}' => __( 'Program Administrator display name', 'cta-lms' ),
	'{support_email}'      => __( 'Support email address', 'cta-lms' ),
);
?>
<div class="wrap cta-admin-wrap cta-email-settings">
	<h1><?php esc_html_e( 'Email Settings', 'cta-lms' ); ?></h1>
	<p><?php esc_html_e( 'Control the automated messages CTA LMS sends. Saved content overrides the built-in email templates; untouched emails continue using their original defaults.', 'cta-lms' ); ?></p>

	<?php if ( 'email_settings_saved' === $notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Email settings saved successfully.', 'cta-lms' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cta-admin-form">
		<?php wp_nonce_field( 'cta_save_email_settings' ); ?>
		<input type="hidden" name="action" value="cta_save_email_settings">

		<div class="cta-admin-panel">
			<h2><?php esc_html_e( 'General Email Settings', 'cta-lms' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><label for="cta_admin_name"><?php esc_html_e( 'Program Administrator Display Name', 'cta-lms' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="cta_admin_name" name="cta_admin_name" value="<?php echo esc_attr( get_option( 'cta_admin_name', 'Candice Fuimaono, MS, LMFT' ) ); ?>">
						<p class="description"><?php esc_html_e( 'Used as the sender name in automated emails.', 'cta-lms' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="cta_support_email"><?php esc_html_e( 'Support Email (From / Reply-To)', 'cta-lms' ); ?></label></th>
					<td>
						<input type="email" class="regular-text" id="cta_support_email" name="cta_support_email" value="<?php echo esc_attr( get_option( 'cta_support_email', 'support@clinicaltrainingacademy.com' ) ); ?>" required>
						<p class="description"><?php esc_html_e( 'Messages are sent from this address and replies are directed here.', 'cta-lms' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<div class="cta-admin-panel">
			<h2><?php esc_html_e( 'Automated Email Templates', 'cta-lms' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Choose an email, edit its subject and content, then preview it with sample data. Keep placeholder text inside braces exactly as shown.', 'cta-lms' ); ?></p>

			<div class="cta-email-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Email templates', 'cta-lms' ); ?>">
				<?php $tab_index = 0; ?>
				<?php foreach ( $email_types as $type => $config ) : ?>
					<button
						type="button"
						class="cta-email-tab <?php echo 0 === $tab_index ? 'cta-email-tab--active' : ''; ?>"
						data-email-tab="<?php echo esc_attr( $type ); ?>"
						role="tab"
						aria-selected="<?php echo 0 === $tab_index ? 'true' : 'false'; ?>"
					>
						<?php echo esc_html( $config['label'] ); ?>
					</button>
					<?php ++$tab_index; ?>
				<?php endforeach; ?>
			</div>

			<?php $panel_index = 0; ?>
			<?php foreach ( $email_types as $type => $config ) : ?>
				<?php
				$settings  = CTA_Emails::get_email_settings( $type );
				$subject   = '' !== $settings['subject'] ? $settings['subject'] : $config['default_subject'];
				$body      = '' !== $settings['body'] ? $settings['body'] : $config['default_body'];
				$editor_id = 'cta_email_' . sanitize_key( $type ) . '_body';
				$placeholders = array_merge( $common_placeholders, $config['placeholders'] );
				?>
				<section
					class="cta-email-panel"
					data-email-panel="<?php echo esc_attr( $type ); ?>"
					role="tabpanel"
					<?php echo 0 !== $panel_index ? 'hidden' : ''; ?>
				>
					<div class="cta-email-panel__header">
						<div>
							<h3><?php echo esc_html( $config['label'] ); ?></h3>
							<p><?php echo esc_html( $config['description'] ); ?></p>
						</div>
						<label class="cta-toggle">
							<input
								type="checkbox"
								name="emails[<?php echo esc_attr( $type ); ?>][enabled]"
								value="yes"
								<?php checked( $settings['enabled'] ); ?>
							>
							<span class="cta-toggle__slider" aria-hidden="true"></span>
							<span class="cta-toggle__label"><?php esc_html_e( 'Enabled', 'cta-lms' ); ?></span>
						</label>
					</div>

					<p>
						<label for="cta-email-subject-<?php echo esc_attr( $type ); ?>"><strong><?php esc_html_e( 'Subject Line', 'cta-lms' ); ?></strong></label><br>
						<input
							type="text"
							class="large-text cta-email-subject"
							id="cta-email-subject-<?php echo esc_attr( $type ); ?>"
							name="emails[<?php echo esc_attr( $type ); ?>][subject]"
							value="<?php echo esc_attr( $subject ); ?>"
						>
					</p>

					<div class="cta-email-placeholders">
						<strong><?php esc_html_e( 'Available placeholders:', 'cta-lms' ); ?></strong>
						<?php foreach ( $placeholders as $placeholder => $description ) : ?>
							<code title="<?php echo esc_attr( $description ); ?>"><?php echo esc_html( $placeholder ); ?></code>
						<?php endforeach; ?>
					</div>

					<p><strong><?php esc_html_e( 'Email Body', 'cta-lms' ); ?></strong></p>
					<?php
					wp_editor(
						$body,
						$editor_id,
						array(
							'textarea_name' => 'emails[' . $type . '][body]',
							'textarea_rows' => 14,
							'media_buttons' => false,
							'teeny'         => true,
							'quicktags'     => true,
						)
					);
					?>

					<p class="cta-email-actions">
						<button
							type="button"
							class="button cta-preview-email"
							data-email-type="<?php echo esc_attr( $type ); ?>"
							data-editor-id="<?php echo esc_attr( $editor_id ); ?>"
						>
							<?php esc_html_e( 'Preview Email', 'cta-lms' ); ?>
						</button>
						<span class="cta-inline-result" aria-live="polite"></span>
					</p>
				</section>
				<?php ++$panel_index; ?>
			<?php endforeach; ?>
		</div>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Email Settings', 'cta-lms' ); ?></button>
		</p>
	</form>

	<div id="cta-email-preview-modal" class="cta-admin-modal" hidden>
		<div class="cta-admin-modal__content cta-email-preview-modal__content">
			<button type="button" class="cta-admin-modal__close" aria-label="<?php esc_attr_e( 'Close', 'cta-lms' ); ?>">&times;</button>
			<h2 id="cta-email-preview-subject"><?php esc_html_e( 'Email Preview', 'cta-lms' ); ?></h2>
			<iframe id="cta-email-preview-frame" title="<?php esc_attr_e( 'Email preview', 'cta-lms' ); ?>"></iframe>
		</div>
	</div>
</div>

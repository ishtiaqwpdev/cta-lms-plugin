<?php
/**
 * Admin shortcodes reference view.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap cta-admin-wrap">
	<h1><?php esc_html_e( 'CTA Shortcodes', 'cta-lms' ); ?></h1>
	<p><?php esc_html_e( 'Reference guide for Candice — copy shortcodes into WordPress pages.', 'cta-lms' ); ?></p>

	<div class="cta-shortcode-grid">
		<?php foreach ( $shortcodes as $item ) : ?>
			<div class="cta-shortcode-card">
				<div class="cta-shortcode-card__code">
					<code><?php echo esc_html( $item['code'] ); ?></code>
					<button type="button" class="button button-small cta-copy-shortcode" data-shortcode="<?php echo esc_attr( $item['code'] ); ?>"><?php esc_html_e( 'Copy', 'cta-lms' ); ?></button>
				</div>
				<p><strong><?php esc_html_e( 'Description:', 'cta-lms' ); ?></strong> <?php echo esc_html( $item['description'] ); ?></p>
				<p><strong><?php esc_html_e( 'Usage:', 'cta-lms' ); ?></strong> <?php echo esc_html( $item['usage'] ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>
</div>

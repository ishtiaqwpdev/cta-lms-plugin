<?php
/**
 * Login / Dashboard auth button partial.
 *
 * @package CTA_LMS
 *
 * @var string $button_url  Destination URL.
 * @var string $button_text Button label.
 * @var string $button_class CSS classes for the button.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="cta-plugin-wrapper cta-auth-button-wrap">
	<a href="<?php echo esc_url( $button_url ); ?>" class="<?php echo esc_attr( $button_class ); ?>">
		<?php echo esc_html( $button_text ); ?>
	</a>
</div>

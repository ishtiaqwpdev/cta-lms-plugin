<?php
/**
 * Mobile dashboard top bar with menu toggle.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dashboard-mobile-bar">
	<button type="button" class="dashboard-mobile-bar__toggle" data-dashboard-menu-toggle aria-expanded="false" aria-controls="dashboard-sidebar-nav">
		<span class="dashboard-mobile-bar__toggle-icon" aria-hidden="true"></span>
		<span class="screen-reader-text"><?php echo esc_html__( 'Toggle dashboard menu', 'cta-lms' ); ?></span>
	</button>
	<p class="dashboard-mobile-bar__title"><?php echo esc_html__( 'Dashboard', 'cta-lms' ); ?></p>
</div>

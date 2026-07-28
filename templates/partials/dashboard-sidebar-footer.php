<?php
/**
 * Dashboard sidebar footer links.
 *
 * @package CTA_LMS
 *
 * @var string $home_url   Site home URL.
 * @var string $logout_url Logout URL.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dashboard-sidebar__footer">
	<?php if ( ! empty( $home_url ) ) : ?>
		<a href="<?php echo esc_url( $home_url ); ?>" class="dashboard-sidebar__link dashboard-sidebar__link--home">
			<span class="dashboard-sidebar__icon" aria-hidden="true">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
			</span>
			<?php echo esc_html__( 'Back to Home', 'cta-lms' ); ?>
		</a>
	<?php endif; ?>
	<a href="<?php echo esc_url( $logout_url ); ?>" class="dashboard-sidebar__link" data-auth-logout>
		<span class="dashboard-sidebar__icon" aria-hidden="true">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
		</span>
		<?php echo esc_html__( 'Log Out', 'cta-lms' ); ?>
	</a>
</div>

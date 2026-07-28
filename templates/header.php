<?php
/**
 * Site header template for [cta_header] shortcode.
 *
 * @package CTA_LMS
 *
 * @var array  $nav_items   Navigation items with label, url, and page_id.
 * @var array  $nav_links   Label => URL map for action buttons.
 * @var string $login_url   Login page URL.
 * @var string $dashboard_url Dashboard page URL for logged-in users.
 * @var string $enroll_url  Enroll Now button URL.
 * @var string $logout_url  WordPress logout URL.
 * @var string $logo_url    Plugin logo URL.
 * @var string $home_url    Site home URL.
 * @var string $site_name   Site name for accessible text.
 * @var bool   $is_logged_in Whether the visitor is logged in.
 * @var bool   $show_nav    Whether to render the navigation menu.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<header class="cta-lms site-header">
	<div class="site-header__inner">
		<div class="site-header__logo">
			<a href="<?php echo esc_url( $home_url ); ?>">
				<img
					src="<?php echo esc_url( $logo_url ); ?>"
					alt="<?php echo esc_attr( $site_name ); ?>"
					onerror="this.style.display='none'; this.nextElementSibling.classList.add('site-header__logo-text--visible');"
				>
				<span class="site-header__logo-text"><?php echo esc_html( $site_name ); ?></span>
			</a>
		</div>

		<?php if ( $show_nav ) : ?>
			<nav class="site-header__nav" aria-label="<?php echo esc_attr__( 'Main navigation', 'cta-lms' ); ?>">
				<ul class="site-header__nav-list">
					<?php foreach ( $nav_items as $item ) : ?>
						<?php
						$is_disabled = empty( $item['url'] );
						$is_active   = ! $is_disabled && ! empty( $item['is_active'] );
						$item_class  = $is_disabled ? 'nav-item--disabled' : '';
						$link_class  = 'site-header__nav-link';

						if ( $is_active ) {
							$link_class .= ' site-header__nav-link--active';
						}

						$item_url   = $is_disabled ? '#' : $item['url'];
						$item_title = $is_disabled ? esc_attr__( 'Page not configured yet', 'cta-lms' ) : '';
						?>
						<li class="<?php echo esc_attr( $item_class ); ?>">
							<a
								href="<?php echo esc_url( $item_url ); ?>"
								class="<?php echo esc_attr( $link_class ); ?>"
								<?php echo $item_title ? 'title="' . esc_attr( $item_title ) . '"' : ''; ?>
							>
								<?php echo esc_html( $item['label'] ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>
		<?php endif; ?>

		<div class="site-header__actions">
			<?php if ( $is_logged_in ) : ?>
				<?php if ( $dashboard_url ) : ?>
					<a href="<?php echo esc_url( $dashboard_url ); ?>" class="btn btn-outline btn--sm">
						<?php echo esc_html__( 'My Dashboard', 'cta-lms' ); ?>
					</a>
				<?php endif; ?>
				<a href="<?php echo esc_url( $logout_url ); ?>" class="btn btn-outline btn--sm">
					<?php echo esc_html__( 'Log Out', 'cta-lms' ); ?>
				</a>
			<?php elseif ( $login_url ) : ?>
				<a href="<?php echo esc_url( $login_url ); ?>" class="btn btn-outline btn--sm">
					<?php echo esc_html__( 'Login', 'cta-lms' ); ?>
				</a>
			<?php endif; ?>

			<a href="<?php echo esc_url( $enroll_url ); ?>" class="btn btn-primary">
				<?php echo esc_html__( 'Enroll Now', 'cta-lms' ); ?>
			</a>
		</div>

		<button
			class="mobile-menu-toggle"
			type="button"
			aria-label="<?php echo esc_attr__( 'Toggle navigation menu', 'cta-lms' ); ?>"
			aria-expanded="false"
		>
			<span class="mobile-menu-toggle__bar"></span>
			<span class="mobile-menu-toggle__bar"></span>
			<span class="mobile-menu-toggle__bar"></span>
		</button>
	</div>
</header>

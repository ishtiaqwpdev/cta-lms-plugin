<?php
/**
 * Site footer template for [cta_footer] shortcode.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get a page permalink from a plugin option.
 *
 * @param string $option_name Option key storing the page ID.
 * @return string
 */
if ( ! function_exists( 'cta_footer_get_page_url' ) ) {
	function cta_footer_get_page_url( $option_name ) {
		if ( function_exists( 'cta_lms_get_linked_page_url' ) ) {
			return cta_lms_get_linked_page_url( $option_name );
		}

		$page_id = absint( get_option( $option_name, 0 ) );

		if ( ! $page_id ) {
			return '';
		}

		$permalink = get_permalink( $page_id );

		return $permalink ? $permalink : '';
	}
}

$site_name = get_bloginfo( 'name' );

$quick_links = array_values(
	array_filter(
		array(
			array(
				'label' => __( 'CE Courses', 'cta-lms' ),
				'url'   => cta_footer_get_page_url( 'cta_courses_page_id' ),
			),
			array(
				'label' => __( 'Exam Preparation', 'cta-lms' ),
				'url'   => cta_footer_get_page_url( 'cta_exam_prep_page_id' ),
			),
			array(
				'label' => __( 'Supervision', 'cta-lms' ),
				'url'   => cta_footer_get_page_url( 'cta_supervision_page_id' ),
			),
			array(
				'label' => __( 'Memberships', 'cta-lms' ),
				'url'   => cta_footer_get_page_url( 'cta_memberships_page_id' ),
			),
			array(
				'label' => __( 'About', 'cta-lms' ),
				'url'   => cta_footer_get_page_url( 'cta_about_page_id' ),
			),
			array(
				'label' => __( 'FAQ', 'cta-lms' ),
				'url'   => cta_footer_get_page_url( 'cta_faq_page_id' ),
			),
			array(
				'label' => __( 'Contact', 'cta-lms' ),
				'url'   => cta_footer_get_page_url( 'cta_contact_page_id' ),
			),
			array(
				'label' => __( 'Policies', 'cta-lms' ),
				'url'   => cta_footer_get_page_url( 'cta_policies_page_id' ),
			),
		),
		static function ( $link ) {
			return ! empty( $link['url'] );
		}
	)
);

$legal_link_defs = array(
	array(
		'label' => __( 'FAQ', 'cta-lms' ),
		'url'   => cta_footer_get_page_url( 'cta_faq_page_id' ),
	),
	array(
		'label' => __( 'Policies', 'cta-lms' ),
		'url'   => cta_footer_get_page_url( 'cta_policies_page_id' ),
	),
);

foreach ( array(
	'refund-policy'    => __( 'Refund Policy', 'cta-lms' ),
	'grievance-policy' => __( 'Grievance Policy', 'cta-lms' ),
	'terms-of-use'     => __( 'Terms of Use', 'cta-lms' ),
) as $slug => $label ) {
	$page = get_page_by_path( $slug );
	if ( $page instanceof WP_Post ) {
		$legal_link_defs[] = array(
			'label' => $label,
			'url'   => get_permalink( $page ),
		);
	}
}

$legal_links = array_values(
	array_filter(
		$legal_link_defs,
		static function ( $link ) {
			return ! empty( $link['url'] );
		}
	)
);

$contact_email = 'support@clinicaltrainingacademy.com';
?>
<footer class="cta-lms site-footer">
	<div class="site-footer__grid">
		<div class="site-footer__column">
			<span class="site-footer__logo-text"><?php echo esc_html( $site_name ); ?></span>
			<p class="site-footer__about-text">
				<?php echo esc_html__( "California's trusted platform for BBS-compliant continuing education and clinical supervision — built for working mental health professionals.", 'cta-lms' ); ?>
			</p>
		</div>

		<div class="site-footer__column">
			<h3 class="site-footer__column-title"><?php echo esc_html__( 'Quick Links', 'cta-lms' ); ?></h3>
			<ul class="site-footer__links">
				<?php foreach ( $quick_links as $link ) : ?>
					<?php if ( ! empty( $link['url'] ) ) : ?>
						<li>
							<a href="<?php echo esc_url( $link['url'] ); ?>">
								<?php echo esc_html( $link['label'] ); ?>
							</a>
						</li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
		</div>

		<div class="site-footer__column">
			<h3 class="site-footer__column-title"><?php echo esc_html__( 'Legal', 'cta-lms' ); ?></h3>
			<ul class="site-footer__links">
				<?php foreach ( $legal_links as $link ) : ?>
					<?php if ( ! empty( $link['url'] ) ) : ?>
						<li>
							<a href="<?php echo esc_url( $link['url'] ); ?>">
								<?php echo esc_html( $link['label'] ); ?>
							</a>
						</li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
		</div>

		<div class="site-footer__column">
			<h3 class="site-footer__column-title"><?php echo esc_html__( 'Contact', 'cta-lms' ); ?></h3>
			<div class="site-footer__contact-item">
				<span aria-hidden="true">&#9993;</span>
				<a href="<?php echo esc_url( 'mailto:' . $contact_email ); ?>">
					<?php echo esc_html( $contact_email ); ?>
				</a>
			</div>
		</div>
	</div>

	<div class="site-footer__bottom">
		<div class="site-footer__bottom-inner">
			<p>
				<?php
				printf(
					/* translators: 1: current year, 2: site name */
					esc_html__( '&copy; %1$s %2$s. All rights reserved.', 'cta-lms' ),
					esc_html( gmdate( 'Y' ) ),
					esc_html( $site_name )
				);
				?>
			</p>
		</div>
	</div>
</footer>

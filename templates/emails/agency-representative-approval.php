<?php
/**
 * Agency representative approval documents email body.
 *
 * @var WP_User $user
 * @var string  $rep_name
 * @var string  $agency_name
 * @var string  $associate_name
 * @var string  $associate_email
 * @var array   $documents
 * @var string  $support_email
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$greeting_name = $rep_name ? $rep_name : __( 'Agency Representative', 'cta-lms' );
?>
<p><?php printf( esc_html__( 'Hi %s,', 'cta-lms' ), esc_html( $greeting_name ) ); ?></p>

<p>
	<?php
	printf(
		/* translators: %s: associate display name */
		esc_html__( '%s has registered as a Registered Associate with Clinical Training and Supervision Academy and is pending approval.', 'cta-lms' ),
		esc_html( $associate_name )
	);
	?>
</p>

<div class="highlight-box">
	<?php if ( ! empty( $agency_name ) ) : ?>
		<p><strong><?php esc_html_e( 'Employer/Agency:', 'cta-lms' ); ?></strong> <?php echo esc_html( $agency_name ); ?></p>
	<?php endif; ?>
	<p><strong><?php esc_html_e( 'Associate Name:', 'cta-lms' ); ?></strong> <?php echo esc_html( $associate_name ); ?></p>
	<p><strong><?php esc_html_e( 'Associate Email:', 'cta-lms' ); ?></strong> <?php echo esc_html( $associate_email ); ?></p>
	<p><strong><?php esc_html_e( 'Status:', 'cta-lms' ); ?></strong> <?php esc_html_e( 'Pending Approval', 'cta-lms' ); ?></p>
</div>

<p><?php esc_html_e( 'Please review and sign the following required documents, which are attached to this email:', 'cta-lms' ); ?></p>

<ul>
	<?php foreach ( (array) $documents as $document ) : ?>
		<li>
			<strong><?php echo esc_html( $document['label'] ); ?></strong>
			<?php if ( ! empty( $document['url'] ) ) : ?>
				— <a href="<?php echo esc_url( $document['url'] ); ?>" style="color:#3266A9;"><?php esc_html_e( 'Download', 'cta-lms' ); ?></a>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
</ul>

<p><?php esc_html_e( 'Please sign both documents and return them so we can complete the associate approval process.', 'cta-lms' ); ?></p>

<hr class="divider">

<p class="small-text">
	<?php
	printf(
		/* translators: %s: support email */
		esc_html__( 'Questions? Contact us at %s.', 'cta-lms' ),
		esc_html( $support_email )
	);
	?>
</p>

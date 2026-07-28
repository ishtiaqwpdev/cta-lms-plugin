<?php
/**
 * Custom user roles for CTA LMS.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Roles
 */
if ( ! class_exists( 'CTA_Roles' ) ) {

class CTA_Roles {

	/**
	 * Create custom plugin roles.
	 */
	public static function create_roles() {
		add_role(
			'cta_licensed_professional',
			'CTA Licensed Professional',
			array(
				'read'                        => true,
				'cta_access_courses'          => true,
				'cta_download_certificates'   => true,
			)
		);

		add_role(
			'cta_associate',
			'CTA Associate',
			array(
				'read'                      => true,
				'cta_access_supervision'    => true,
				'cta_upload_bbs_documents'  => true,
				'cta_book_sessions'         => true,
			)
		);
	}

	/**
	 * Remove custom plugin roles (called on uninstall only).
	 */
	public static function remove_roles() {
		remove_role( 'cta_licensed_professional' );
		remove_role( 'cta_associate' );
	}
}
}
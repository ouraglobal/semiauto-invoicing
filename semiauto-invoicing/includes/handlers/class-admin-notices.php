<?php
/**
 * Admin Notification Manager
 * 
 * Centralizes all dashboard notices for dependencies, errors, and success states.
 * 
 * @package SemiAutoInvoicing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OG_PSR_Admin_Notices {

	public function __construct() {
		add_action( 'admin_notices', array( $this, 'render_notices' ) );
	}

	/**
	 * Dispatcher for all admin-side notifications
	 */
	public function render_notices() {
		// 1. Dependency Notice (WooCommerce Missing)
		if ( ! OG_PSR_Activator::get_instance()->is_woocommerce_active() ) {
			$this->render_woo_missing_notice();
			return;
		}
	}

	private function render_woo_missing_notice() {
		printf(
			'<div class="notice notice-error"><p><strong>%1$s %2$s</strong> <a href="%3$s">%4$s</a></p></div>',
			esc_html__( 'SemiAuto Invoicing – Local PDFs & Global E-Invoicing', 'semiauto-invoicing' ),
			esc_html__( 'requires WooCommerce to be installed and active!', 'semiauto-invoicing' ),
			esc_url( admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) ),
			esc_html__( 'Install WooCommerce', 'semiauto-invoicing' )
		);
	}
}

<?php
/**
 * Customer document email (HTML).
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/ogpsr-customer-document.php.
 *
 * @package SemiAutoInvoicing
 *
 * @var WC_Order $order
 * @var string   $document_label
 * @var string   $email_heading
 * @var string   $additional_content
 * @var bool     $sent_to_admin
 * @var bool     $plain_text
 * @var WC_Email $email
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core email hook.
do_action( 'woocommerce_email_header', $email_heading, $email );

echo '<p class="ogpsr-email-message">' . esc_html(
	sprintf(
		/* translators: 1: document type (e.g. Receipt), 2: order number, 3: blog name */
		__( 'Hello, here is your %1$s for order #%2$s from %3$s.', 'semiauto-invoicing' ),
		strtolower( $document_label ),
		$order->get_order_number(),
		get_option( 'blogname' )
	)
) . '</p>';

if ( $additional_content ) {
	echo '<div class="ogpsr-email-additional-content">' . wp_kses_post( wpautop( wptexturize( $additional_content ) ) ) . '</div>';
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core email hook.
do_action( 'woocommerce_email_footer', $email );

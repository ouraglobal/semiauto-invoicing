<?php
/**
 * Customer document email (plain text).
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/ogpsr-customer-document.php.
 *
 * @package SemiAutoInvoicing
 *
 * @var WC_Order $order
 * @var string   $document_label
 * @var string   $email_heading
 * @var string   $additional_content
 * @var WC_Email $email
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo esc_html( wp_strip_all_tags( $email_heading ) ) . "\n\n";

echo esc_html(
	sprintf(
		/* translators: 1: document type (e.g. Receipt), 2: order number, 3: blog name */
		__( 'Hello, here is your %1$s for order #%2$s from %3$s.', 'semiauto-invoicing' ),
		strtolower( $document_label ),
		$order->get_order_number(),
		get_option( 'blogname' )
	)
) . "\n\n";

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core email hook.
echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );

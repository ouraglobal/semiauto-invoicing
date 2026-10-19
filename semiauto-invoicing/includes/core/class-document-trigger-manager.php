<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Automates Document generation on Order Status triggers
 *
 * @package SemiAutoInvoicing
 */
final class OG_PSR_Document_Trigger_Manager {
    private const DOCUMENT_SETTINGS = array(
        'thermal'   => 'ogpsr_thermal_generation_trigger',
        'a4'        => 'ogpsr_a4_generation_trigger',
        'packaging' => 'ogpsr_packaging_generation_trigger',
    );

    /**
     * Register hooks dynamically based on store settings
     */
    public static function init() {
        foreach ( self::DOCUMENT_SETTINGS as $document_type => $setting_key ) {
            $status = sanitize_key( OG_PSR_Settings::get( $setting_key, '' ) );
            if ( '' === $status ) {
                continue;
            }
            add_action( 'woocommerce_order_status_' . $status, function ( $order_id ) use($document_type) {
                self::generate_document( (int) $order_id, $document_type );
            }, 20 );
        }
        // REST API Order Creation Trigger
        add_action(
            'woocommerce_rest_insert_shop_order_object',
            array(__CLASS__, 'handle_rest_order_creation'),
            20,
            3
        );
    }

    /**
     * Handle orders created via WooCommerce REST API (POST /wp-json/wc/v3/orders)
     *
     * @param WC_Order         $order   The WC_Order object
     * @param WP_REST_Request  $request The REST request object
     * @param bool             $creating True if order is being created
     */
    public static function handle_rest_order_creation( $order, $request, $creating ) {
        if ( !$creating || !$order instanceof WC_Order ) {
            return;
        }
        $order_status = $order->get_status();
        foreach ( self::DOCUMENT_SETTINGS as $document_type => $setting_key ) {
            $status = sanitize_key( OG_PSR_Settings::get( $setting_key, '' ) );
            if ( '' !== $status && $status === $order_status ) {
                self::generate_document( $order->get_id(), $document_type );
            }
        }
    }

    /**
     * Execute PDF generation using the core PDF Generator service
     */
    private static function generate_document( $order_id, $document_type ) {
        $order = wc_get_order( $order_id );
        if ( !$order ) {
            return;
        }
        if ( !class_exists( 'OG_PSR_PDF_Generator' ) ) {
            return;
        }
        $pdf_gen = new OG_PSR_PDF_Generator();
        switch ( $document_type ) {
            case 'thermal':
                $pdf_gen->generate_thermal( $order_id );
                break;
            case 'a4':
                $pdf_gen->generate_a4( $order_id );
                break;
            case 'packaging':
                $pdf_gen->generate_packaging( $order_id );
                break;
            default:
                break;
        }
    }

}

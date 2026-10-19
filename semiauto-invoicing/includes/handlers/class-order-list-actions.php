<?php

/**
 * Order List Actions
 * 
 * Registers and renders PDF action buttons in the WooCommerce orders list for
 * A4 Invoices, Thermal Receipts, and Packaging Slips.
 * 
 * @package SemiAutoInvoicing
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
class OG_PSR_Order_List_Actions {
    /**
     * Hook registers and custom handlers initialization.
     */
    public static function init() {
        $instance = new self();
        // Classic CPT edit-shop_order columns registration
        add_filter( 'manage_edit-shop_order_columns', array($instance, 'add_order_list_column'), 15 );
        add_action(
            'manage_shop_order_posts_custom_column',
            array($instance, 'render_order_list_column_content'),
            15,
            2
        );
        // HPOS edit wc-orders columns registration
        add_filter( 'manage_woocommerce_page_wc-orders_columns', array($instance, 'add_order_list_column'), 15 );
        add_action(
            'manage_woocommerce_page_wc-orders_custom_column',
            array($instance, 'render_hpos_order_list_column_content'),
            15,
            2
        );
        // Native Bulk Actions Registration (CPT + HPOS)
        add_filter( 'bulk_actions-edit-shop_order', array($instance, 'register_bulk_actions') );
        add_filter( 'bulk_actions-woocommerce_page_wc-orders', array($instance, 'register_bulk_actions') );
        add_filter(
            'handle_bulk_actions-edit-shop_order',
            array($instance, 'handle_bulk_actions'),
            10,
            3
        );
        add_filter(
            'handle_bulk_actions-woocommerce_page_wc-orders',
            array($instance, 'handle_bulk_actions'),
            10,
            3
        );
    }

    /**
     * Register bulk actions into WooCommerce Order List dropdown
     *
     * @param array $actions Bulk actions map
     * @return array
     */
    public function register_bulk_actions( $actions ) {
        if ( !is_array( $actions ) ) {
            $actions = array();
        }
        return $actions;
    }

    /**
     * Handle execution of custom bulk actions from WooCommerce Order List
     *
     * @param string $redirect_to Redirect URL after processing
     * @param string $action      Triggered action key
     * @param array  $ids         Selected order IDs
     * @return string
     */
    public function handle_bulk_actions( $redirect_to, $action, $ids ) {
        if ( empty( $ids ) || !current_user_can( 'manage_woocommerce' ) ) {
            return $redirect_to;
        }
        return $redirect_to;
    }

    /**
     * Registers the Documents download action column and dynamic Peppol Gateway columns.
     * 
     * @param array $columns Existing table columns.
     * @return array Modified table columns.
     */
    public function add_order_list_column( $columns ) {
        if ( !is_array( $columns ) ) {
            $columns = array();
        }
        $columns['ogpsr_documents'] = __( 'Documents', 'semiauto-invoicing' );
        return $columns;
    }

    /**
     * Resolves an order object safely from an ID or object instance.
     *
     * @param mixed $order_or_id
     * @return WC_Order|false
     */
    private function get_order_object( $order_or_id ) {
        try {
            if ( is_object( $order_or_id ) && (is_a( $order_or_id, 'WC_Abstract_Order' ) || is_a( $order_or_id, 'WC_Order' )) ) {
                return $order_or_id;
            }
            if ( is_numeric( $order_or_id ) && $order_or_id > 0 && function_exists( 'wc_get_order' ) ) {
                $order = wc_get_order( $order_or_id );
                return ( is_object( $order ) && (is_a( $order, 'WC_Abstract_Order' ) || is_a( $order, 'WC_Order' )) ? $order : false );
            }
        } catch ( \Throwable $e ) {
            OG_PSR_Logger::error( 'OG PSR get_order_object error: ' . $e->getMessage() );
        }
        return false;
    }

    /**
     * Render callback for WooCommerce HPOS layouts.
     * 
     * @param string $column Column key identifier.
     * @param mixed  $order  WC Order instance or Order ID.
     */
    public function render_hpos_order_list_column_content( $column, $order ) {
        try {
            $order_obj = $this->get_order_object( $order );
            if ( !$order_obj ) {
                return;
            }
            if ( 'ogpsr_documents' === $column ) {
                $this->render_actions( $order_obj );
            }
        } catch ( \Throwable $e ) {
            OG_PSR_Logger::error( 'OG PSR render_hpos_order_list_column_content error: ' . $e->getMessage() );
        }
    }

    /**
     * Render callback for Classic Custom Post Type layouts.
     * 
     * @param string $column Column key identifier.
     * @param mixed  $post_id Post database row ID or Order instance.
     */
    public function render_order_list_column_content( $column, $post_id ) {
        try {
            $order_obj = $this->get_order_object( $post_id );
            if ( !$order_obj ) {
                return;
            }
            if ( 'ogpsr_documents' === $column ) {
                $this->render_actions( $order_obj );
            }
        } catch ( \Throwable $e ) {
            OG_PSR_Logger::error( 'OG PSR render_order_list_column_content error: ' . $e->getMessage() );
        }
    }

    /**
     * Renders direct download action buttons for all active formats.
     * 
     * @param WC_Order $order WC Order instance.
     */
    private function render_actions( $order ) {
        try {
            $order = $this->get_order_object( $order );
            if ( !$order ) {
                return;
            }
            $order_id = $order->get_id();
            $enabled_formats = OG_PSR_Settings::get( 'ogpsr_receipt_formats', array('thermal') );
            if ( !is_array( $enabled_formats ) ) {
                $enabled_formats = array('thermal');
            }
            echo '<div class="ogpsr-order-list-actions-wrapper">';
            // 1. A4 Invoice
            if ( in_array( 'a4', $enabled_formats, true ) ) {
                $a4_url = OG_PSR_Download_Handler::get_download_url( $order_id, 'a4' );
                printf(
                    '<a href="%s" class="button ogpsr-list-btn ogpsr-btn-a4" target="_blank" title="%s"><span class="dashicons dashicons-pdf"></span> %s</a>',
                    esc_url( $a4_url ),
                    esc_attr__( 'Download A4 Invoice', 'semiauto-invoicing' ),
                    esc_html__( 'A4', 'semiauto-invoicing' )
                );
            }
            // 2. Thermal Receipt
            if ( in_array( 'thermal', $enabled_formats, true ) ) {
                $thermal_url = OG_PSR_Download_Handler::get_download_url( $order_id, 'thermal' );
                printf(
                    '<a href="%s" class="button ogpsr-list-btn ogpsr-btn-thermal" target="_blank" title="%s"><span class="dashicons dashicons-printer"></span> %s</a>',
                    esc_url( $thermal_url ),
                    esc_attr__( 'Download Thermal Receipt', 'semiauto-invoicing' ),
                    esc_html__( 'Receipt', 'semiauto-invoicing' )
                );
            }
            // 3. Packaging Slip
            if ( in_array( 'packaging', $enabled_formats, true ) ) {
                $pkg_url = OG_PSR_Download_Handler::get_download_url( $order_id, 'packaging' );
                printf(
                    '<a href="%s" class="button ogpsr-list-btn ogpsr-btn-pkg" target="_blank" title="%s"><span class="dashicons dashicons-archive"></span> %s</a>',
                    esc_url( $pkg_url ),
                    esc_attr__( 'Download Packaging Slip', 'semiauto-invoicing' ),
                    esc_html__( 'Pack', 'semiauto-invoicing' )
                );
            }
            echo '</div>';
        } catch ( \Throwable $e ) {
            OG_PSR_Logger::error( 'OG PSR render_actions error: ' . $e->getMessage() );
        }
    }

}

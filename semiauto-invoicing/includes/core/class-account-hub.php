<?php

/**
 * My Account Document Hub Class
 * 
 * Handles the "My Documents" tab in the WooCommerce My Account area.
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( !class_exists( 'OG_PSR_Account_Hub' ) ) {
    class OG_PSR_Account_Hub {
        private static $endpoint = 'my-documents';

        /**
         * Initialize hooks.
         */
        public static function init() {
            add_action( 'init', array(__CLASS__, 'register_endpoint') );
            add_filter( 'query_vars', array(__CLASS__, 'add_query_vars'), 0 );
            add_filter( 'woocommerce_get_query_vars', array(__CLASS__, 'add_woocommerce_query_vars') );
            add_filter( 'woocommerce_account_menu_items', array(__CLASS__, 'add_menu_item') );
            add_filter( 'woocommerce_endpoint_' . self::$endpoint . '_title', array(__CLASS__, 'endpoint_title') );
            add_action( 'woocommerce_account_' . self::$endpoint . '_endpoint', array(__CLASS__, 'render_endpoint_content') );
            // Enqueue frontend styles
            add_action( 'wp_enqueue_scripts', array(__CLASS__, 'enqueue_frontend_assets') );
        }

        /**
         * Register the custom endpoint with versioned upgrade detection and rule-presence verification.
         *
         * Avoids expensive rewrite flushes on every request while ensuring the endpoint
         * is self-healed upon installation, plugin version upgrade, or cache loss without
         * requiring the user to manually visit Settings -> Permalinks.
         */
        public static function register_endpoint() {
            add_rewrite_endpoint( self::$endpoint, EP_ROOT | EP_PAGES );
            // 1. Upgrade detection: Check if rewrite version matches current plugin version
            $stored_version = get_option( 'ogpsr_rewrite_version', '' );
            $needs_flush = OG_PSR_VERSION !== $stored_version;
            // 2. Rule presence check: Verify whether the endpoint actually exists in cached rewrite_rules
            if ( !$needs_flush ) {
                $rules = get_option( 'rewrite_rules' );
                if ( is_array( $rules ) && !empty( $rules ) ) {
                    $has_rule = false;
                    foreach ( $rules as $pattern => $query ) {
                        if ( str_contains( $pattern, self::$endpoint ) || str_contains( $query, self::$endpoint ) ) {
                            $has_rule = true;
                            break;
                        }
                    }
                    if ( !$has_rule ) {
                        $needs_flush = true;
                    }
                }
            }
            // 3. One-time soft flush (false = do NOT rewrite .htaccess, only refresh DB rewrite_rules cache)
            if ( $needs_flush ) {
                // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules -- One-time version-gated soft flush for endpoint registration.
                flush_rewrite_rules( false );
                update_option( 'ogpsr_rewrite_version', OG_PSR_VERSION );
            }
        }

        /**
         * Add query vars for WordPress.
         *
         * @param array $vars
         * @return array
         */
        public static function add_query_vars( $vars ) {
            $vars[] = self::$endpoint;
            return $vars;
        }

        /**
         * Add query vars for WooCommerce query engine.
         *
         * @param array $vars
         * @return array
         */
        public static function add_woocommerce_query_vars( $vars ) {
            $vars[self::$endpoint] = self::$endpoint;
            return $vars;
        }

        /**
         * Filter the endpoint title.
         *
         * @param string $title
         * @return string
         */
        public static function endpoint_title( $title ) {
            return __( 'My Documents', 'semiauto-invoicing' );
        }

        /**
         * Add the menu item to frontend My Account page.
         */
        public static function add_menu_item( $items ) {
            $new_items = array();
            $inserted = false;
            foreach ( $items as $key => $value ) {
                $new_items[$key] = $value;
                if ( 'orders' === $key ) {
                    $new_items[self::$endpoint] = __( 'My Documents', 'semiauto-invoicing' );
                    $inserted = true;
                }
            }
            if ( !$inserted && !isset( $new_items[self::$endpoint] ) ) {
                $new_items[self::$endpoint] = __( 'My Documents', 'semiauto-invoicing' );
            }
            return $new_items;
        }

        /**
         * Enqueue frontend styles.
         */
        public static function enqueue_frontend_assets() {
            if ( is_account_page() ) {
                wp_enqueue_style( 'dashicons' );
                wp_enqueue_style(
                    'ogpsr-frontend',
                    OG_PSR_URL . 'assets/css/ogpsr-frontend.css',
                    array('dashicons'),
                    OG_PSR_VERSION
                );
            }
        }

        /**
         * Render the tab content.
         */
        public static function render_endpoint_content() {
            $current_user = get_current_user_id();
            if ( !$current_user ) {
                return;
            }
            // Get customer orders across all registered statuses (paginated to avoid loading unbounded orders)
            $order_statuses = ( function_exists( 'wc_get_order_statuses' ) ? array_keys( wc_get_order_statuses() ) : array(
                'completed',
                'processing',
                'on-hold',
                'pending',
                'cancelled',
                'refunded',
                'failed'
            ) );
            $orders = wc_get_orders( array(
                'customer' => $current_user,
                'limit'    => apply_filters( 'ogpsr_my_documents_orders_limit', 25 ),
                'status'   => $order_statuses,
            ) );
            $enabled_formats = get_option( 'ogpsr_receipt_formats', array('thermal', 'a4') );
            if ( !is_array( $enabled_formats ) ) {
                $enabled_formats = array();
            }
            echo '<div class="ogpsr-hub-container">';
            echo '<header class="ogpsr-hub-header">';
            echo '<h2>' . esc_html__( 'My Documents', 'semiauto-invoicing' ) . '</h2>';
            echo '<p>' . esc_html__( 'Access and download your invoices, receipts, and packaging documents.', 'semiauto-invoicing' ) . '</p>';
            echo '</header>';
            if ( empty( $orders ) ) {
                echo '<div class="ogpsr-hub-empty">';
                echo '<p>' . esc_html__( 'No documents available yet. Place an order to see your invoices here.', 'semiauto-invoicing' ) . '</p>';
                echo '</div>';
            } else {
                echo '<div class="ogpsr-hub-table-wrapper">';
                echo '<table class="ogpsr-hub-table">';
                echo '<thead><tr>';
                echo '<th>' . esc_html__( 'Order', 'semiauto-invoicing' ) . '</th>';
                echo '<th>' . esc_html__( 'Date', 'semiauto-invoicing' ) . '</th>';
                echo '<th>' . esc_html__( 'Status', 'semiauto-invoicing' ) . '</th>';
                echo '<th>' . esc_html__( 'Available Documents', 'semiauto-invoicing' ) . '</th>';
                echo '</tr></thead>';
                echo '<tbody>';
                foreach ( $orders as $order ) {
                    echo '<tr>';
                    echo '<td><span class="ogpsr-order-number">#' . esc_html( $order->get_order_number() ) . '</span></td>';
                    echo '<td><span class="ogpsr-order-date">' . esc_html( wc_format_datetime( $order->get_date_created() ) ) . '</span></td>';
                    echo '<td><span class="ogpsr-order-status ogpsr-order-status--' . esc_attr( sanitize_html_class( $order->get_status() ) ) . '">' . esc_html( ( function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $order->get_status() ) : ucfirst( $order->get_status() ) ) ) . '</span></td>';
                    echo '<td class="ogpsr-hub-actions"><div class="ogpsr-actions-flex">';
                    if ( in_array( 'a4', $enabled_formats, true ) ) {
                        echo '<a href="' . esc_url( OG_PSR_Download_Handler::get_download_url( $order->get_id(), 'a4' ) ) . '" class="ogpsr-hub-btn" title="' . esc_attr__( 'Download A4 Invoice', 'semiauto-invoicing' ) . '">';
                        echo '<span class="dashicons dashicons-pdf"></span>';
                        echo '<span class="btn-text">' . esc_html__( 'Invoice', 'semiauto-invoicing' ) . '</span>';
                        echo '</a>';
                    }
                    if ( in_array( 'thermal', $enabled_formats, true ) ) {
                        echo '<a href="' . esc_url( OG_PSR_Download_Handler::get_download_url( $order->get_id(), 'thermal' ) ) . '" class="ogpsr-hub-btn" title="' . esc_attr__( 'Download Thermal Receipt', 'semiauto-invoicing' ) . '">';
                        echo '<span class="dashicons dashicons-printer"></span>';
                        echo '<span class="btn-text">' . esc_html__( 'Receipt', 'semiauto-invoicing' ) . '</span>';
                        echo '</a>';
                    }
                    echo '</div></td>';
                    echo '</tr>';
                }
                echo '</tbody></table></div>';
            }
            echo '</div>';
        }

    }

}
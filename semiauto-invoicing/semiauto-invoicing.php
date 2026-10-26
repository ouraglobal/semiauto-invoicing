<?php

/**
 * Plugin Name:       SemiAuto Invoicing
 * Plugin URI:        https://ouraglobal.ae/semiauto-invoicing/
 * Description:       Local PDFs & Global E-Invoicing compliance assistant. Thermal receipts (44–80mm), PDF invoices, and packaging slips for WooCommerce.
 * Version:           8.5.1
 * Author:            ouraglobal
 * Contributors:      salmansh169
 * Author URI:        https://ouraglobal.ae/
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0-standalone.html
 * Text Domain:       semiauto-invoicing
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Requires Plugins:  woocommerce
 * WC requires at least: 10.0.0
 * WC tested up to:   11.2.1
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( function_exists( 'ogpsr_fs' ) ) {
    ogpsr_fs()->set_basename( false, __FILE__ );
} else {
    // --- 1. FREEMIUS INITIALIZATION ---
    // fs_init_begin
    if ( !function_exists( 'ogpsr_fs' ) ) {
        /**
         * Singleton accessor for the Freemius SDK instance.
         */
        function ogpsr_fs() {
            global $ogpsr_fs;
            if ( !isset( $ogpsr_fs ) ) {
                // Activate WordPress Multisite Network integration if running on WPMS.
                if ( is_multisite() ) {
                    if ( !defined( 'WP_FS__PRODUCT_23702_MULTISITE' ) ) {
                        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Freemius multisite integration constant requirement.
                        define( 'WP_FS__PRODUCT_23702_MULTISITE', true );
                    }
                }
                require_once __DIR__ . '/vendor/freemius/start.php';
                $ogpsr_fs = fs_dynamic_init( array(
                    'id'               => '23702',
                    'slug'             => 'semiauto-invoicing',
                    'premium_slug'     => 'semiauto-invoicing-premium',
                    'type'             => 'plugin',
                    'public_key'       => 'pk_c4285c0bdae32d8f945769e0efb3a',
                    'is_premium'       => false,
                    'premium_suffix'   => 'Premium',
                    'has_paid_plans'   => true,
                    'is_org_compliant' => true,
                    'menu'             => array(
                        'slug'           => 'wc-settings',
                        'override_exact' => true,
                        'support'        => false,
                        'parent'         => array(
                            'slug' => 'woocommerce',
                        ),
                    ),
                    'is_live'          => true,
                ) );
            }
            return $ogpsr_fs;
        }

        ogpsr_fs();
        do_action( 'ogpsr_fs_loaded' );
        function ogpsr_fs_settings_url() {
            return admin_url( 'admin.php?page=wc-settings&tab=pdf_smart_receipts' );
        }

        function ogpsr_fs_pricing_url() {
            return 'https://ouraglobal.ae/semiauto-invoicing/#pricing';
        }

        ogpsr_fs()->add_filter( 'connect_url', 'ogpsr_fs_settings_url' );
        ogpsr_fs()->add_filter( 'after_skip_url', 'ogpsr_fs_settings_url' );
        ogpsr_fs()->add_filter( 'after_connect_url', 'ogpsr_fs_settings_url' );
        ogpsr_fs()->add_filter( 'after_pending_connect_url', 'ogpsr_fs_settings_url' );
        ogpsr_fs()->add_filter( 'pricing_url', 'ogpsr_fs_pricing_url' );
        ogpsr_fs()->add_action( 'after_uninstall', array('OG_PSR_Activator', 'uninstall_cleanup') );
    }
    // fs_init_end
    /**
     * HPOS (High-Performance Order Storage) Compatibility
     */
    add_action( 'before_woocommerce_init', function () {
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        }
    } );
    // Define core constants
    if ( !defined( 'OG_PSR_VERSION' ) ) {
        define( 'OG_PSR_VERSION', '8.5.1' );
    }
    if ( !defined( 'OG_PSR_PATH' ) ) {
        define( 'OG_PSR_PATH', plugin_dir_path( __FILE__ ) );
    }
    if ( !defined( 'OG_PSR_URL' ) ) {
        define( 'OG_PSR_URL', plugin_dir_url( __FILE__ ) );
    }
    define( 'OG_PSR_FILE', __FILE__ );
    // --- 2. ACTIVATOR & VERSION GUARD ---
    require_once OG_PSR_PATH . 'includes/core/class-activator.php';
    if ( !OG_PSR_Activator::runtime_version_guard() ) {
        return;
    }
    // Register WordPress Lifecycle Hooks in the root loading scope per WP.org requirements
    register_activation_hook( OG_PSR_FILE, array('OG_PSR_Activator', 'activate') );
    register_deactivation_hook( OG_PSR_FILE, array('OG_PSR_Activator', 'deactivate') );
    // Action links on plugins page
    add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
        $custom = array('<a href="https://ouraglobal.ae/semiauto-invoicing/documentation/" target="_blank">' . __( 'Documentation', 'semiauto-invoicing' ) . '</a>');
        return array_merge( $custom, $links );
    } );
    // --- 4. CORE INITIALIZATION ---
    add_action( 'plugins_loaded', function () {
        if ( !defined( 'OG_PSR_UPLOADS_DIR' ) ) {
            $upload_dir = wp_upload_dir();
            define( 'OG_PSR_UPLOADS_DIR', $upload_dir['basedir'] . '/semiauto-invoicing' );
        }
        // Initialize Activator hooks
        OG_PSR_Activator::get_instance()->init();
        // Check WooCommerce Dependency
        if ( !OG_PSR_Activator::get_instance()->is_woocommerce_active() ) {
            require_once OG_PSR_PATH . 'includes/handlers/class-admin-notices.php';
            new OG_PSR_Admin_Notices();
            return;
        }
        require_once OG_PSR_PATH . 'includes/core/class-document-registry.php';
        require_once OG_PSR_PATH . 'includes/core/class-security.php';
        require_once OG_PSR_PATH . 'includes/core/class-logger.php';
        require_once OG_PSR_PATH . 'includes/core/class-addon-parser.php';
        require_once OG_PSR_PATH . 'includes/document-engine/class-template-registry.php';
        require_once OG_PSR_PATH . 'includes/document-engine/class-document-data-engine.php';
        require_once OG_PSR_PATH . 'includes/core/class-pdf-generator.php';
        require_once OG_PSR_PATH . 'includes/core/class-admin-settings.php';
        require_once OG_PSR_PATH . 'includes/core/class-status-diagnostics.php';
        require_once OG_PSR_PATH . 'includes/core/class-account-hub.php';
        require_once OG_PSR_PATH . 'includes/core/class-document-trigger-manager.php';
        require_once OG_PSR_PATH . 'includes/handlers/class-order-metabox.php';
        require_once OG_PSR_PATH . 'includes/handlers/class-download-handler.php';
        require_once OG_PSR_PATH . 'includes/handlers/class-admin-notices.php';
        require_once OG_PSR_PATH . 'includes/handlers/class-email-handler.php';
        require_once OG_PSR_PATH . 'includes/handlers/class-order-list-actions.php';
        require_once OG_PSR_PATH . 'includes/admin/class-mock-order.php';
        require_once OG_PSR_PATH . 'includes/admin/class-preview-renderer.php';
        require_once OG_PSR_PATH . 'includes/core/class-privacy.php';
        // Initialize Privacy & GDPR Data Exporter/Eraser
        OG_PSR_Privacy::init();
        // Initialize Global Services
        OG_PSR_Settings::init();
        OG_PSR_Account_Hub::init();
        OG_PSR_Order_List_Actions::init();
        OG_PSR_Document_Trigger_Manager::init();
        new OG_PSR_Preview_Renderer();
        new OG_PSR_Order_Metabox();
        new OG_PSR_Download_Handler();
        new OG_PSR_Email_Handler();
        new OG_PSR_Admin_Notices();
        // ISO Currency Filter (Admin)
        if ( is_admin() && !wp_doing_ajax() ) {
            add_filter(
                'woocommerce_currency_symbol',
                function ( $symbol, $currency ) {
                    $mode = get_option( 'ogpsr_currency_display_mode', 'symbol' );
                    return ( 'code' === $mode ? $currency : $symbol );
                },
                999,
                2
            );
        }
    } );
}
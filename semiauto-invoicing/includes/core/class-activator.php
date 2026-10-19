<?php

/**
 * Plugin Activator & Lifecycle Manager
 * 
 * Handles activation dependencies, version isolation, and uninstallation cleanup.
 * 
 * @package SemiAutoInvoicing
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
class OG_PSR_Activator {
    /**
     * Singleton instance
     */
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize lifecycle hooks and background listeners
     */
    public function init() {
        // Bind recurring daily cleanup event
        add_action( 'ogpsr_daily_cleanup_event', array(__CLASS__, 'execute_daily_cleanup') );
    }

    /**
     * Activation logic
     */
    public static function activate() {
        // 1. Register endpoints and flush rewrite rules (soft flush)
        if ( class_exists( 'OG_PSR_Account_Hub' ) ) {
            OG_PSR_Account_Hub::register_endpoint();
            update_option( 'ogpsr_rewrite_version', OG_PSR_VERSION );
        }
        // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules -- Executed strictly on plugin activation hook.
        flush_rewrite_rules( false );
        // 2. Schedule cleanup events
        if ( !wp_next_scheduled( 'ogpsr_daily_cleanup_event' ) ) {
            wp_schedule_event( time(), 'daily', 'ogpsr_daily_cleanup_event' );
        }
    }

    /**
     * Plugin Deactivation Callback
     *
     * Automatically called by WordPress via register_deactivation_hook().
     * Clears all recurring cron events (temporary PDF/cache purges and async API pollers)
     * from the WordPress cron table to comply with WordPress.org guidelines.
     */
    public static function deactivate() {
        wp_clear_scheduled_hook( 'ogpsr_daily_cleanup_event' );
    }

    /**
     * Daily Maintenance & Storage Cleanup Callback
     *
     * Automatically removes orphaned temporary export files and flushes expired caches.
     */
    public static function execute_daily_cleanup() {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/semiauto-invoicing/temp';
        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        if ( $wp_filesystem && $wp_filesystem->is_dir( $temp_dir ) ) {
            $files = $wp_filesystem->dirlist( $temp_dir );
            if ( is_array( $files ) ) {
                $now = time();
                foreach ( $files as $name => $file_info ) {
                    // Purge temporary export ZIPs and scratch files older than 24 hours
                    if ( isset( $file_info['lastmodunix'] ) && $now - $file_info['lastmodunix'] > 86400 ) {
                        $wp_filesystem->delete( $temp_dir . '/' . $name );
                    }
                }
            }
        }
    }

    /**
     * Check if WooCommerce is active on the current installation.
     *
     * Validates active plugin records across both single-site and WordPress Multisite (WPMS)
     * network-activated plugin environments prior to running dependent receipt/invoice logic.
     *
     * @return bool True if WooCommerce is active, false otherwise.
     */
    public function is_woocommerce_active() {
        $active_plugins = (array) get_option( 'active_plugins', array() );
        if ( in_array( 'woocommerce/woocommerce.php', $active_plugins, true ) ) {
            return true;
        }
        if ( is_multisite() ) {
            $active_sitewide_plugins = (array) get_site_option( 'active_sitewide_plugins', array() );
            if ( isset( $active_sitewide_plugins['woocommerce/woocommerce.php'] ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Version Isolation Guard
     * Prevents fatal errors if multiple files attempt to load simultaneously.
     * Dual-version deactivation and license management are handled natively by Freemius SDK.
     */
    public static function runtime_version_guard() {
        if ( defined( 'OG_PSR_LOADED' ) ) {
            return false;
        }
        define( 'OG_PSR_LOADED', true );
        return true;
    }

    /**
     * Deep Cleanup on Plugin Uninstall
     *
     * Executed via Freemius SDK's 'after_uninstall' lifecycle hook (ogpsr_fs()->add_action('after_uninstall')).
     * Standard uninstall.php is intentionally avoided per official Freemius guidelines to ensure that
     * deleting an inactive Free plugin edition during/after a Premium upgrade does NOT inadvertently
     * purge active database options, post meta, or generated receipts.
     *
     * Cleanup executes ONLY when the merchant has explicitly enabled the 'ogpsr_delete_on_uninstall'
     * opt-in toggle in the plugin Storage settings.
     */
    public static function uninstall_cleanup() {
        if ( 'yes' !== get_option( 'ogpsr_delete_on_uninstall', 'no' ) ) {
            return;
        }
        global $wpdb;
        // 1. Delete Options
        $like_options = $wpdb->esc_like( 'ogpsr_' ) . '%';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup of plugin-prefixed rows (LIKE match); no core API deletes by key prefix.
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_options ) );
        // 2. Delete Post Meta
        $like_pm_psr = $wpdb->esc_like( 'ogpsr_' ) . '%';
        $like_pm_upsr = $wpdb->esc_like( '_ogpsr_' ) . '%';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup of plugin-prefixed rows (LIKE match); no core API deletes by key prefix.
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s OR meta_key LIKE %s", $like_pm_psr, $like_pm_upsr ) );
        // 3. HPOS Meta Cleanup
        $order_meta_table = "{$wpdb->prefix}wc_orders_meta";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup of plugin-prefixed rows (LIKE match); no core API deletes by key prefix.
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $order_meta_table ) ) === $order_meta_table ) {
            $like_psr = $wpdb->esc_like( 'ogpsr_' ) . '%';
            $like_upsr = $wpdb->esc_like( '_ogpsr_' ) . '%';
            // Table names cannot be parameterised via prepare(); esc_sql() is the correct hardening method here.
            $safe_table = esc_sql( $order_meta_table );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall cleanup of plugin-prefixed rows (LIKE match); no core API deletes by key prefix.
            $wpdb->query( $wpdb->prepare( "DELETE FROM `{$safe_table}` WHERE meta_key LIKE %s OR meta_key LIKE %s", $like_psr, $like_upsr ) );
        }
        // 4. Delete Files (Strictly scoped to plugin's dedicated upload namespace)
        $upload_dir = wp_upload_dir();
        $receipts_dir = $upload_dir['basedir'] . '/semiauto-invoicing';
        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        if ( class_exists( 'OG_PSR_Security' ) && OG_PSR_Security::is_path_within( $receipts_dir, $upload_dir['basedir'] ) ) {
            if ( $wp_filesystem && $wp_filesystem->exists( $receipts_dir ) ) {
                $wp_filesystem->delete( $receipts_dir, true );
            }
        }
    }

}

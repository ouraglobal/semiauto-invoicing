<?php

/**
 * System Status & Diagnostics Class
 * 
 * Gathers server environment data, library support, and folder permissions
 * for the Status & Support admin tab.
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( !class_exists( 'OG_PSR_Status_Diagnostics' ) ) {
    class OG_PSR_Status_Diagnostics {
        /**
         * Get all system info in a structured array.
         *
         * @return array
         */
        public static function get_system_report() {
            $storage_security_status = self::get_server_security_status();
            global $wpdb, $wp_filesystem;
            if ( empty( $wp_filesystem ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }
            $is_storage_writable = defined( 'OG_PSR_UPLOADS_DIR' ) && $wp_filesystem && $wp_filesystem->is_dir( OG_PSR_UPLOADS_DIR ) && $wp_filesystem->is_writable( OG_PSR_UPLOADS_DIR );
            $report = array(
                'environment'         => array(
                    'label' => __( 'Core Environment', 'semiauto-invoicing' ),
                    'data'  => array(
                        'server_type'        => array(
                            'label'  => __( 'Server Software', 'semiauto-invoicing' ),
                            'value'  => ( isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'Unknown' ),
                            'status' => 'info',
                            'desc'   => __( 'The underlying software running your website (e.g., Nginx or Apache).', 'semiauto-invoicing' ),
                        ),
                        'php_version'        => array(
                            'label'  => __( 'PHP Version (Recommended: 8.0+)', 'semiauto-invoicing' ),
                            'value'  => phpversion(),
                            'status' => ( version_compare( phpversion(), '7.4', '>=' ) ? 'ok' : 'warning' ),
                            'desc'   => __( 'PHP is the engine that runs this plugin. Modern versions are faster and more secure.', 'semiauto-invoicing' ),
                        ),
                        'https_status'       => array(
                            'label'  => __( 'SSL / HTTPS', 'semiauto-invoicing' ),
                            'value'  => ( is_ssl() ? __( 'Active & Secure', 'semiauto-invoicing' ) : __( 'Insecure Connection', 'semiauto-invoicing' ) ),
                            'status' => ( is_ssl() ? 'ok' : 'error' ),
                            'desc'   => __( 'Encryption is required for secure document handling and e-invoicing compliance.', 'semiauto-invoicing' ),
                        ),
                        'memory_limit'       => array(
                            'label'  => __( 'PHP Memory Limit', 'semiauto-invoicing' ),
                            'value'  => ini_get( 'memory_limit' ),
                            'status' => ( self::convert_hr_to_bytes( ini_get( 'memory_limit' ) ) >= self::convert_hr_to_bytes( '256M' ) ? 'ok' : 'warning' ),
                            'desc'   => __( 'High-quality PDF generation is memory intensive. 256MB is the recommended baseline.', 'semiauto-invoicing' ),
                        ),
                        'max_execution_time' => array(
                            'label'  => __( 'PHP Max Execution Time', 'semiauto-invoicing' ),
                            'value'  => ini_get( 'max_execution_time' ) . 's',
                            'status' => ( (int) ini_get( 'max_execution_time' ) >= 60 || 0 === (int) ini_get( 'max_execution_time' ) ? 'ok' : 'warning' ),
                            'desc'   => __( 'The amount of time a script is allowed to run. 60+ is recommended for batch PDF generation.', 'semiauto-invoicing' ),
                        ),
                        'curl'               => array(
                            'label'  => __( 'cURL Extension', 'semiauto-invoicing' ),
                            'value'  => ( extension_loaded( 'curl' ) ? __( 'Installed', 'semiauto-invoicing' ) : __( 'Missing', 'semiauto-invoicing' ) ),
                            'status' => ( extension_loaded( 'curl' ) ? 'ok' : 'warning' ),
                            'desc'   => __( 'Required for communicating with external APIs or fetching remote assets like fonts and logos.', 'semiauto-invoicing' ),
                        ),
                    ),
                ),
                'wp_environment'      => array(
                    'label' => __( 'WordPress Environment', 'semiauto-invoicing' ),
                    'data'  => array(
                        'wp_version'     => array(
                            'label'  => __( 'WordPress Version', 'semiauto-invoicing' ),
                            'value'  => get_bloginfo( 'version' ),
                            'status' => 'info',
                            'desc'   => __( 'The version of WordPress currently installed on this server.', 'semiauto-invoicing' ),
                        ),
                        'plugin_version' => array(
                            'label'  => __( 'Plugin Version', 'semiauto-invoicing' ),
                            'value'  => ( defined( 'OG_PSR_VERSION' ) ? OG_PSR_VERSION : 'Unknown' ),
                            'status' => 'ok',
                            'desc'   => __( 'The active version and tier of this plugin.', 'semiauto-invoicing' ),
                        ),
                        'wc_version'     => array(
                            'label'  => __( 'WooCommerce Version', 'semiauto-invoicing' ),
                            'value'  => ( defined( 'WC_VERSION' ) ? WC_VERSION : __( 'Not Installed', 'semiauto-invoicing' ) ),
                            'status' => ( defined( 'WC_VERSION' ) ? 'ok' : 'error' ),
                            'desc'   => __( 'The version of WooCommerce currently active. The plugin relies heavily on its data structures.', 'semiauto-invoicing' ),
                        ),
                        'timezone'       => array(
                            'label'  => __( 'WordPress Timezone', 'semiauto-invoicing' ),
                            'value'  => ( function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : (( get_option( 'timezone_string' ) ? get_option( 'timezone_string' ) : 'UTC' )) ),
                            'status' => 'info',
                            'desc'   => __( 'The timezone used for document generation timestamps and e-invoicing compliance.', 'semiauto-invoicing' ),
                        ),
                        'theme'          => array(
                            'label'  => __( 'Active Theme', 'semiauto-invoicing' ),
                            'value'  => ( function_exists( 'wp_get_theme' ) && is_object( wp_get_theme() ) && method_exists( wp_get_theme(), 'exists' ) && wp_get_theme()->exists() ? wp_get_theme()->get( 'Name' ) : 'Unknown' ),
                            'status' => 'info',
                            'desc'   => __( 'Helps identify potential template overrides or stylesheet conflicts.', 'semiauto-invoicing' ),
                        ),
                        'wp_debug'       => array(
                            'label'  => __( 'WP Debug Mode', 'semiauto-invoicing' ),
                            'value'  => ( defined( 'WP_DEBUG' ) && WP_DEBUG ? __( 'Enabled', 'semiauto-invoicing' ) : __( 'Disabled', 'semiauto-invoicing' ) ),
                            'status' => ( defined( 'WP_DEBUG' ) && WP_DEBUG ? 'warning' : 'ok' ),
                            'desc'   => __( 'If enabled, PHP errors are actively logged/displayed. Should be disabled in production.', 'semiauto-invoicing' ),
                        ),
                    ),
                ),
                'store_configuration' => array(
                    'label' => __( 'WooCommerce Configuration', 'semiauto-invoicing' ),
                    'data'  => array(
                        'base_currency'      => array(
                            'label'  => __( 'Base Currency', 'semiauto-invoicing' ),
                            'value'  => ( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'N/A' ),
                            'status' => 'info',
                            'desc'   => __( 'The primary currency used for store calculations and E-Invoicing.', 'semiauto-invoicing' ),
                        ),
                        'taxes_enabled'      => array(
                            'label'  => __( 'Taxes Enabled', 'semiauto-invoicing' ),
                            'value'  => ( 'yes' === get_option( 'woocommerce_calc_taxes' ) ? __( 'Yes', 'semiauto-invoicing' ) : __( 'No', 'semiauto-invoicing' ) ),
                            'status' => ( 'yes' === get_option( 'woocommerce_calc_taxes' ) ? 'ok' : 'info' ),
                            'desc'   => __( 'Determines if the plugin needs to calculate tax breakdowns and subtotals.', 'semiauto-invoicing' ),
                        ),
                        'prices_include_tax' => array(
                            'label'  => __( 'Prices Entered With Tax', 'semiauto-invoicing' ),
                            'value'  => ( 'yes' === get_option( 'woocommerce_prices_include_tax' ) ? __( 'Yes (Inclusive)', 'semiauto-invoicing' ) : __( 'No (Exclusive)', 'semiauto-invoicing' ) ),
                            'status' => 'info',
                            'desc'   => __( 'Crucial for diagnosing mathematical discrepancies in XML reconciliation.', 'semiauto-invoicing' ),
                        ),
                    ),
                ),
                'document_engine'     => array(
                    'label' => __( 'PDF & Generation Engine', 'semiauto-invoicing' ),
                    'data'  => array(
                        'write_permissions' => array(
                            'label'  => __( 'PDF Storage Writeable', 'semiauto-invoicing' ),
                            'value'  => ( $is_storage_writable ? __( 'Yes (Optimized)', 'semiauto-invoicing' ) : __( 'No (Action Required)', 'semiauto-invoicing' ) ),
                            'status' => ( $is_storage_writable ? 'ok' : 'error' ),
                            'desc'   => __( 'The plugin needs permission to save generated PDFs to your server disk.', 'semiauto-invoicing' ),
                        ),
                        'storage_security'  => array(
                            'label'  => __( 'Web Server Storage Defense', 'semiauto-invoicing' ),
                            'value'  => $storage_security_status['value'],
                            'status' => $storage_security_status['status'],
                            'desc'   => $storage_security_status['desc'],
                        ),
                        'dom_xml'           => array(
                            'label'  => __( 'DOM/XML Extension', 'semiauto-invoicing' ),
                            'value'  => ( extension_loaded( 'dom' ) ? __( 'Installed', 'semiauto-invoicing' ) : __( 'Missing', 'semiauto-invoicing' ) ),
                            'status' => ( extension_loaded( 'dom' ) ? 'ok' : 'error' ),
                            'desc'   => __( 'Required for converting HTML templates into high-fidelity PDF documents.', 'semiauto-invoicing' ),
                        ),
                        'gd_library'        => array(
                            'label'  => __( 'GD (Graphics Library)', 'semiauto-invoicing' ),
                            'value'  => ( extension_loaded( 'gd' ) ? __( 'Installed', 'semiauto-invoicing' ) : __( 'Missing', 'semiauto-invoicing' ) ),
                            'status' => ( extension_loaded( 'gd' ) ? 'ok' : 'error' ),
                            'desc'   => __( 'Enables the processing of logos and high-resolution barcodes on your documents.', 'semiauto-invoicing' ),
                        ),
                        'bcmath'            => array(
                            'label'  => __( 'BCMath extension', 'semiauto-invoicing' ),
                            'value'  => ( extension_loaded( 'bcmath' ) ? __( 'Installed', 'semiauto-invoicing' ) : __( 'Missing', 'semiauto-invoicing' ) ),
                            'status' => ( extension_loaded( 'bcmath' ) ? 'ok' : 'info' ),
                            'desc'   => __( 'Recommended for high-precision mathematical operations in international e-invoicing.', 'semiauto-invoicing' ),
                        ),
                        'mbstring'          => array(
                            'label'  => __( 'MBString extension', 'semiauto-invoicing' ),
                            'value'  => ( extension_loaded( 'mbstring' ) ? __( 'Installed', 'semiauto-invoicing' ) : __( 'Missing', 'semiauto-invoicing' ) ),
                            'status' => ( extension_loaded( 'mbstring' ) ? 'ok' : 'error' ),
                            'desc'   => __( 'Required for handling multi-byte characters in Japanese and Arabic documents.', 'semiauto-invoicing' ),
                        ),
                        'css_cache'         => array(
                            'label'  => __( 'Transient CSS Cache', 'semiauto-invoicing' ),
                            'value'  => self::get_cache_status( 'css' ),
                            'status' => 'info',
                            'desc'   => __( 'Minifies and caches template styles to speed up the PDF generation process.', 'semiauto-invoicing' ),
                        ),
                        'logo_cache'        => array(
                            'label'  => __( 'Transient Logo Cache', 'semiauto-invoicing' ),
                            'value'  => self::get_cache_status( 'logo' ),
                            'status' => 'info',
                            'desc'   => __( 'Caches the base64 encoded logo to prevent redundant disk/network I/O.', 'semiauto-invoicing' ),
                        ),
                    ),
                ),
            );
            return $report;
        }

        /**
         * Get status label for transient cache.
         *
         * @param string $type Cache type ('css' or 'logo').
         * @return string
         */
        private static function get_cache_status( $type ) {
            $transient_key = ( 'css' === $type ? 'ogpsr_cached_css_v' . OG_PSR_VERSION : 'ogpsr_cached_logo_v' . OG_PSR_VERSION );
            $cached = get_transient( $transient_key );
            if ( false !== $cached ) {
                return __( 'Cached (Warmed)', 'semiauto-invoicing' );
            }
            return __( 'Uncached (On-Demand)', 'semiauto-invoicing' );
        }

        /**
         * Safely convert human-readable memory limit strings (e.g. 256M) to bytes.
         */
        private static function convert_hr_to_bytes( $value ) {
            if ( function_exists( 'wp_convert_hr_to_bytes' ) ) {
                return wp_convert_hr_to_bytes( $value );
            }
            $value = strtolower( trim( $value ) );
            $bytes = (int) $value;
            if ( false !== strpos( $value, 'g' ) ) {
                $bytes *= 1024 * 1024 * 1024;
            } elseif ( false !== strpos( $value, 'm' ) ) {
                $bytes *= 1024 * 1024;
            } elseif ( false !== strpos( $value, 'k' ) ) {
                $bytes *= 1024;
            }
            return $bytes;
        }

        /**
         * Determine the active web server software and evaluate direct storage access defenses.
         *
         * @return array
         */
        private static function get_storage_directories_for_security_check() {
            if ( !class_exists( 'OG_PSR_Security' ) ) {
                return array();
            }
            $root = OG_PSR_Security::resolve_upload_root();
            $directories = array();
            global $wp_filesystem;
            if ( empty( $wp_filesystem ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                if ( !WP_Filesystem() || empty( $wp_filesystem ) ) {
                    return array($root);
                }
            }
            $document_types = array('invoice', 'thermal_receipt', 'packaging_slip');
            foreach ( $document_types as $document_type ) {
                $directory = OG_PSR_Security::resolve_document_root( $document_type );
                if ( $wp_filesystem->is_dir( $directory ) ) {
                    $directories[] = $directory;
                }
            }
            // Before any documents have been generated, inspect the root to avoid claiming protection.
            return ( !empty( $directories ) ? array_values( array_unique( $directories ) ) : array($root) );
        }

        public static function get_server_security_status() {
            $server_software = ( isset( $_SERVER['SERVER_SOFTWARE'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) ) : '' );
            $is_nginx = false !== strpos( $server_software, 'nginx' );
            $is_iis = false !== strpos( $server_software, 'microsoft-iis' ) || false !== strpos( $server_software, 'iis' );
            $is_apache = false !== strpos( $server_software, 'apache' ) || false !== strpos( $server_software, 'litespeed' );
            $storage_dir = ( class_exists( 'OG_PSR_Security' ) ? OG_PSR_Security::resolve_upload_root() : '' );
            $storage_directories = self::get_storage_directories_for_security_check();
            if ( $is_nginx ) {
                $nginx_confirmed = (bool) apply_filters( 'ogpsr_storage_protection_confirmed', false, $storage_dir );
                return array(
                    'value'  => ( $nginx_confirmed ? __( 'Nginx Protection Manually Confirmed', 'semiauto-invoicing' ) : __( 'Nginx Protection Not Verified', 'semiauto-invoicing' ) ),
                    'status' => 'warning',
                    'desc'   => sprintf( 
                        /* translators: 1: documentation link */
                        __( 'Nginx ignores .htaccess and web.config. Configure and verify a server-level deny rule, then use the plugin confirmation filter if appropriate: <a href="%s" target="_blank" rel="noopener">Nginx instructions</a>. The plugin cannot independently inspect Nginx configuration.', 'semiauto-invoicing' ),
                        'https://ouraglobal.ae/semiauto-invoicing/documentation/#nginx-security'
                     ),
                );
            }
            if ( !class_exists( 'OG_PSR_Security' ) || '' === $storage_dir ) {
                return array(
                    'value'  => __( 'Protection Not Verified', 'semiauto-invoicing' ),
                    'status' => 'error',
                    'desc'   => __( 'Storage protection files could not be inspected. Direct file access is not verified.', 'semiauto-invoicing' ),
                );
            }
            if ( $is_iis ) {
                $protected = !empty( $storage_directories );
                foreach ( $storage_directories as $directory ) {
                    if ( !OG_PSR_Security::is_protection_file_valid( $directory, 'webconfig' ) ) {
                        $protected = false;
                        break;
                    }
                }
                return array(
                    'value'  => ( $protected ? __( 'Protected (IIS web.config verified)', 'semiauto-invoicing' ) : __( 'IIS Protection Not Verified', 'semiauto-invoicing' ) ),
                    'status' => ( $protected ? 'ok' : 'error' ),
                    'desc'   => ( $protected ? __( 'The storage web.config is readable and contains an IIS deny-all rule.', 'semiauto-invoicing' ) : __( 'The storage web.config is missing, unreadable, or lacks a valid IIS deny-all rule.', 'semiauto-invoicing' ) ),
                );
            }
            if ( $is_apache ) {
                $protected = !empty( $storage_directories );
                foreach ( $storage_directories as $directory ) {
                    if ( !OG_PSR_Security::is_protection_file_valid( $directory, 'htaccess' ) ) {
                        $protected = false;
                        break;
                    }
                }
                return array(
                    'value'  => ( $protected ? __( 'Protected (.htaccess rules verified)', 'semiauto-invoicing' ) : __( 'Apache/LiteSpeed Protection Not Verified', 'semiauto-invoicing' ) ),
                    'status' => ( $protected ? 'ok' : 'error' ),
                    'desc'   => ( $protected ? __( 'The storage .htaccess exists, is readable, and contains an Apache deny-all rule.', 'semiauto-invoicing' ) : __( 'The storage .htaccess is missing, unreadable, or lacks a valid Apache deny-all rule.', 'semiauto-invoicing' ) ),
                );
            }
            $server_confirmed = (bool) apply_filters( 'ogpsr_storage_protection_confirmed', false, $storage_dir );
            return array(
                'value'  => ( $server_confirmed ? __( 'Protection Manually Confirmed (Server Unknown)', 'semiauto-invoicing' ) : __( 'Protection Not Verified', 'semiauto-invoicing' ) ),
                'status' => 'warning',
                'desc'   => ( $server_confirmed ? __( 'A site-level confirmation was provided, but the plugin cannot independently identify the active web server or verify its access rules.', 'semiauto-invoicing' ) : __( 'The active web server is unknown. Confirm server-level document access protection before enabling PDF generation.', 'semiauto-invoicing' ) ),
            );
        }

    }

}
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
            global $wpdb, $wp_filesystem;
            if ( empty( $wp_filesystem ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }
            $is_storage_writable = defined( 'OG_PSR_UPLOADS_DIR' ) && $wp_filesystem && $wp_filesystem->is_dir( OG_PSR_UPLOADS_DIR ) && $wp_filesystem->is_writable( OG_PSR_UPLOADS_DIR );
            $report = [
                'environment'         => [
                    'label' => __( 'Core Environment', 'semiauto-invoicing' ),
                    'data'  => [
                        'server_type'        => [
                            'label'  => __( 'Server Software', 'semiauto-invoicing' ),
                            'value'  => ( isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'Unknown' ),
                            'status' => 'info',
                            'desc'   => __( 'The underlying software running your website (e.g., Nginx or Apache).', 'semiauto-invoicing' ),
                        ],
                        'php_version'        => [
                            'label'  => __( 'PHP Version (Recommended: 8.0+)', 'semiauto-invoicing' ),
                            'value'  => phpversion(),
                            'status' => ( version_compare( phpversion(), '7.4', '>=' ) ? 'ok' : 'warning' ),
                            'desc'   => __( 'PHP is the engine that runs this plugin. Modern versions are faster and more secure.', 'semiauto-invoicing' ),
                        ],
                        'https_status'       => [
                            'label'  => __( 'SSL / HTTPS', 'semiauto-invoicing' ),
                            'value'  => ( is_ssl() ? __( 'Active & Secure', 'semiauto-invoicing' ) : __( 'Insecure Connection', 'semiauto-invoicing' ) ),
                            'status' => ( is_ssl() ? 'ok' : 'error' ),
                            'desc'   => __( 'Encryption is required for secure document handling and e-invoicing compliance.', 'semiauto-invoicing' ),
                        ],
                        'memory_limit'       => [
                            'label'  => __( 'PHP Memory Limit', 'semiauto-invoicing' ),
                            'value'  => ini_get( 'memory_limit' ),
                            'status' => ( self::convert_hr_to_bytes( ini_get( 'memory_limit' ) ) >= self::convert_hr_to_bytes( '256M' ) ? 'ok' : 'warning' ),
                            'desc'   => __( 'High-quality PDF generation is memory intensive. 256MB is the recommended baseline.', 'semiauto-invoicing' ),
                        ],
                        'max_execution_time' => [
                            'label'  => __( 'PHP Max Execution Time', 'semiauto-invoicing' ),
                            'value'  => ini_get( 'max_execution_time' ) . 's',
                            'status' => ( (int) ini_get( 'max_execution_time' ) >= 60 || (int) ini_get( 'max_execution_time' ) === 0 ? 'ok' : 'warning' ),
                            'desc'   => __( 'The amount of time a script is allowed to run. 60+ is recommended for batch PDF generation.', 'semiauto-invoicing' ),
                        ],
                        'curl'               => [
                            'label'  => __( 'cURL Extension', 'semiauto-invoicing' ),
                            'value'  => ( extension_loaded( 'curl' ) ? __( 'Installed', 'semiauto-invoicing' ) : __( 'Missing', 'semiauto-invoicing' ) ),
                            'status' => ( extension_loaded( 'curl' ) ? 'ok' : 'warning' ),
                            'desc'   => __( 'Required for communicating with external APIs or fetching remote assets like fonts and logos.', 'semiauto-invoicing' ),
                        ],
                    ],
                ],
                'wp_environment'      => [
                    'label' => __( 'WordPress Environment', 'semiauto-invoicing' ),
                    'data'  => [
                        'wp_version'     => [
                            'label'  => __( 'WordPress Version', 'semiauto-invoicing' ),
                            'value'  => get_bloginfo( 'version' ),
                            'status' => 'info',
                            'desc'   => __( 'The version of WordPress currently installed on this server.', 'semiauto-invoicing' ),
                        ],
                        'plugin_version' => [
                            'label'  => __( 'Plugin Version', 'semiauto-invoicing' ),
                            'value'  => ( defined( 'OG_PSR_VERSION' ) ? OG_PSR_VERSION : 'Unknown' ),
                            'status' => 'ok',
                            'desc'   => __( 'The active version and tier of this plugin.', 'semiauto-invoicing' ),
                        ],
                        'wc_version'     => [
                            'label'  => __( 'WooCommerce Version', 'semiauto-invoicing' ),
                            'value'  => ( defined( 'WC_VERSION' ) ? WC_VERSION : __( 'Not Installed', 'semiauto-invoicing' ) ),
                            'status' => ( defined( 'WC_VERSION' ) ? 'ok' : 'error' ),
                            'desc'   => __( 'The version of WooCommerce currently active. The plugin relies heavily on its data structures.', 'semiauto-invoicing' ),
                        ],
                        'timezone'       => [
                            'label'  => __( 'WordPress Timezone', 'semiauto-invoicing' ),
                            'value'  => ( function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : (( get_option( 'timezone_string' ) ? get_option( 'timezone_string' ) : 'UTC' )) ),
                            'status' => 'info',
                            'desc'   => __( 'The timezone used for document generation timestamps and e-invoicing compliance.', 'semiauto-invoicing' ),
                        ],
                        'theme'          => [
                            'label'  => __( 'Active Theme', 'semiauto-invoicing' ),
                            'value'  => ( function_exists( 'wp_get_theme' ) && ($theme = wp_get_theme()) && is_object( $theme ) && method_exists( $theme, 'exists' ) && $theme->exists() ? $theme->get( 'Name' ) : 'Unknown' ),
                            'status' => 'info',
                            'desc'   => __( 'Helps identify potential template overrides or stylesheet conflicts.', 'semiauto-invoicing' ),
                        ],
                        'wp_debug'       => [
                            'label'  => __( 'WP Debug Mode', 'semiauto-invoicing' ),
                            'value'  => ( defined( 'WP_DEBUG' ) && WP_DEBUG ? __( 'Enabled', 'semiauto-invoicing' ) : __( 'Disabled', 'semiauto-invoicing' ) ),
                            'status' => ( defined( 'WP_DEBUG' ) && WP_DEBUG ? 'warning' : 'ok' ),
                            'desc'   => __( 'If enabled, PHP errors are actively logged/displayed. Should be disabled in production.', 'semiauto-invoicing' ),
                        ],
                    ],
                ],
                'store_configuration' => [
                    'label' => __( 'WooCommerce Configuration', 'semiauto-invoicing' ),
                    'data'  => [
                        'base_currency'      => [
                            'label'  => __( 'Base Currency', 'semiauto-invoicing' ),
                            'value'  => ( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'N/A' ),
                            'status' => 'info',
                            'desc'   => __( 'The primary currency used for store calculations and E-Invoicing.', 'semiauto-invoicing' ),
                        ],
                        'taxes_enabled'      => [
                            'label'  => __( 'Taxes Enabled', 'semiauto-invoicing' ),
                            'value'  => ( get_option( 'woocommerce_calc_taxes' ) === 'yes' ? __( 'Yes', 'semiauto-invoicing' ) : __( 'No', 'semiauto-invoicing' ) ),
                            'status' => ( get_option( 'woocommerce_calc_taxes' ) === 'yes' ? 'ok' : 'info' ),
                            'desc'   => __( 'Determines if the plugin needs to calculate tax breakdowns and subtotals.', 'semiauto-invoicing' ),
                        ],
                        'prices_include_tax' => [
                            'label'  => __( 'Prices Entered With Tax', 'semiauto-invoicing' ),
                            'value'  => ( get_option( 'woocommerce_prices_include_tax' ) === 'yes' ? __( 'Yes (Inclusive)', 'semiauto-invoicing' ) : __( 'No (Exclusive)', 'semiauto-invoicing' ) ),
                            'status' => 'info',
                            'desc'   => __( 'Crucial for diagnosing mathematical discrepancies in XML reconciliation.', 'semiauto-invoicing' ),
                        ],
                    ],
                ],
                'document_engine'     => [
                    'label' => __( 'PDF & Generation Engine', 'semiauto-invoicing' ),
                    'data'  => [
                        'write_permissions' => [
                            'label'  => __( 'PDF Storage Writeable', 'semiauto-invoicing' ),
                            'value'  => ( $is_storage_writable ? __( 'Yes (Optimized)', 'semiauto-invoicing' ) : __( 'No (Action Required)', 'semiauto-invoicing' ) ),
                            'status' => ( $is_storage_writable ? 'ok' : 'error' ),
                            'desc'   => __( 'The plugin needs permission to save generated PDFs to your server disk.', 'semiauto-invoicing' ),
                        ],
                        'storage_security'  => [
                            'label'  => __( 'Web Server Storage Defense', 'semiauto-invoicing' ),
                            'value'  => self::get_server_security_status()['value'],
                            'status' => self::get_server_security_status()['status'],
                            'desc'   => self::get_server_security_status()['desc'],
                        ],
                        'dom_xml'           => [
                            'label'  => __( 'DOM/XML Extension', 'semiauto-invoicing' ),
                            'value'  => ( extension_loaded( 'dom' ) ? __( 'Installed', 'semiauto-invoicing' ) : __( 'Missing', 'semiauto-invoicing' ) ),
                            'status' => ( extension_loaded( 'dom' ) ? 'ok' : 'error' ),
                            'desc'   => __( 'Required for converting HTML templates into high-fidelity PDF documents.', 'semiauto-invoicing' ),
                        ],
                        'gd_library'        => [
                            'label'  => __( 'GD (Graphics Library)', 'semiauto-invoicing' ),
                            'value'  => ( extension_loaded( 'gd' ) ? __( 'Installed', 'semiauto-invoicing' ) : __( 'Missing', 'semiauto-invoicing' ) ),
                            'status' => ( extension_loaded( 'gd' ) ? 'ok' : 'error' ),
                            'desc'   => __( 'Enables the processing of logos and high-resolution barcodes on your documents.', 'semiauto-invoicing' ),
                        ],
                        'bcmath'            => [
                            'label'  => __( 'BCMath extension', 'semiauto-invoicing' ),
                            'value'  => ( extension_loaded( 'bcmath' ) ? __( 'Installed', 'semiauto-invoicing' ) : __( 'Missing', 'semiauto-invoicing' ) ),
                            'status' => ( extension_loaded( 'bcmath' ) ? 'ok' : 'info' ),
                            'desc'   => __( 'Recommended for high-precision mathematical operations in international e-invoicing.', 'semiauto-invoicing' ),
                        ],
                        'mbstring'          => [
                            'label'  => __( 'MBString extension', 'semiauto-invoicing' ),
                            'value'  => ( extension_loaded( 'mbstring' ) ? __( 'Installed', 'semiauto-invoicing' ) : __( 'Missing', 'semiauto-invoicing' ) ),
                            'status' => ( extension_loaded( 'mbstring' ) ? 'ok' : 'error' ),
                            'desc'   => __( 'Required for handling multi-byte characters in Japanese and Arabic documents.', 'semiauto-invoicing' ),
                        ],
                        'css_cache'         => [
                            'label'  => __( 'Transient CSS Cache', 'semiauto-invoicing' ),
                            'value'  => self::get_cache_status( 'css' ),
                            'status' => 'info',
                            'desc'   => __( 'Minifies and caches template styles to speed up the PDF generation process.', 'semiauto-invoicing' ),
                        ],
                        'logo_cache'        => [
                            'label'  => __( 'Transient Logo Cache', 'semiauto-invoicing' ),
                            'value'  => self::get_cache_status( 'logo' ),
                            'status' => 'info',
                            'desc'   => __( 'Caches the base64 encoded logo to prevent redundant disk/network I/O.', 'semiauto-invoicing' ),
                        ],
                    ],
                ],
            ];
            return $report;
        }

        /**
         * Get status label for transient cache.
         *
         * @param string $type Cache type ('css' or 'logo').
         * @return string
         */
        private static function get_cache_status( $type ) {
            $transient_key = ( $type === 'css' ? 'ogpsr_cached_css_v' . OG_PSR_VERSION : 'ogpsr_cached_logo_v' . OG_PSR_VERSION );
            $cached = get_transient( $transient_key );
            if ( $cached !== false ) {
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
            if ( strpos( $value, 'g' ) !== false ) {
                $bytes *= 1024 * 1024 * 1024;
            } elseif ( strpos( $value, 'm' ) !== false ) {
                $bytes *= 1024 * 1024;
            } elseif ( strpos( $value, 'k' ) !== false ) {
                $bytes *= 1024;
            }
            return $bytes;
        }

        /**
         * Determine the active web server software and evaluate direct storage access defenses.
         *
         * @return array
         */
        public static function get_server_security_status() {
            $server_software = ( isset( $_SERVER['SERVER_SOFTWARE'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) ) : '' );
            $is_nginx = strpos( $server_software, 'nginx' ) !== false;
            $is_iis = strpos( $server_software, 'microsoft-iis' ) !== false || strpos( $server_software, 'iis' ) !== false;
            $is_apache = strpos( $server_software, 'apache' ) !== false || strpos( $server_software, 'litespeed' ) !== false;
            if ( $is_nginx ) {
                return [
                    'value'  => __( 'Nginx Server Notice', 'semiauto-invoicing' ),
                    'status' => 'warning',
                    'desc'   => sprintf( 
                        /* translators: 1: documentation link */
                        __( 'Nginx ignores .htaccess files. To block direct HTTP access to your stored invoices, add the location block directive from the <a href="%s" target="_blank" rel="noopener">Documentation</a> to your server configuration.', 'semiauto-invoicing' ),
                        'https://ouraglobal.ae/semiauto-invoicing/documentation/#nginx-security'
                     ),
                ];
            }
            if ( $is_iis ) {
                return [
                    'value'  => __( 'Protected (IIS web.config)', 'semiauto-invoicing' ),
                    'status' => 'ok',
                    'desc'   => __( 'Direct HTTP file browsing is actively blocked by Microsoft IIS web.config authorization rules.', 'semiauto-invoicing' ),
                ];
            }
            // Apache / LiteSpeed / Default
            return [
                'value'  => __( 'Protected (.htaccess Active)', 'semiauto-invoicing' ),
                'status' => 'ok',
                'desc'   => __( 'Direct HTTP file browsing is actively blocked by Apache/LiteSpeed .htaccess deny rules.', 'semiauto-invoicing' ),
            ];
        }

    }

}
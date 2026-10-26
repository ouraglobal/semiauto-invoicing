<?php

/**
 * Admin Settings Coordinator Class
 * 
 * Serves as the main entry point, registering hooks with WooCommerce, enqueuing assets,
 * and delegating definitions, HTML renderers, and AJAX handlers to modular subclasses.
 * 
 * @package SemiAutoInvoicing
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
// Load settings subclasses immediately
require_once OG_PSR_PATH . 'includes/admin/class-settings-definitions.php';
require_once OG_PSR_PATH . 'includes/admin/class-settings-renderers.php';
require_once OG_PSR_PATH . 'includes/admin/class-settings-helpers.php';
require_once OG_PSR_PATH . 'includes/admin/class-settings-ajax.php';
if ( !class_exists( 'OG_PSR_Settings' ) ) {
    class OG_PSR_Settings {
        /**
         * Whitelisted sub-tab definitions: slug => [label, dashicon, method]
         *
         * @return array
         */
        public static function get_sub_tabs() {
            return array(
                'general'   => array(
                    'label'  => __( 'General', 'semiauto-invoicing' ),
                    'icon'   => 'dashicons-admin-settings',
                    'method' => 'get_general_settings',
                ),
                'thermal'   => array(
                    'label'  => __( 'Thermal Receipt', 'semiauto-invoicing' ),
                    'icon'   => 'dashicons-printer',
                    'method' => 'get_thermal_settings',
                ),
                'a4'        => array(
                    'label'  => __( 'A4 Documents', 'semiauto-invoicing' ),
                    'icon'   => 'dashicons-media-document',
                    'method' => 'get_a4_settings',
                ),
                'packaging' => array(
                    'label'  => __( 'Packaging Slip', 'semiauto-invoicing' ),
                    'icon'   => 'dashicons-archive',
                    'method' => 'get_packaging_settings',
                ),
                'xml'       => array(
                    'label'  => __( 'XML (Peppol)', 'semiauto-invoicing' ),
                    'icon'   => 'dashicons-code-standards',
                    'method' => 'get_xml_settings',
                ),
                'barcode'   => array(
                    'label'  => __( 'Barcode & QR', 'semiauto-invoicing' ),
                    'icon'   => 'dashicons-format-image',
                    'method' => 'get_barcode_settings',
                ),
                'design'    => array(
                    'label'  => __( 'Design & Style', 'semiauto-invoicing' ),
                    'icon'   => 'dashicons-art',
                    'method' => 'get_design_settings',
                ),
                'storage'   => array(
                    'label'  => __( 'Storage', 'semiauto-invoicing' ),
                    'icon'   => 'dashicons-database',
                    'method' => 'get_storage_settings',
                ),
                'extras'    => array(
                    'label'  => __( 'Extras', 'semiauto-invoicing' ),
                    'icon'   => 'dashicons-plus-alt',
                    'method' => 'get_extras_settings',
                ),
                'gateways'  => array(
                    'label'  => __( 'Peppol Gateways', 'semiauto-invoicing' ),
                    'icon'   => 'dashicons-admin-links',
                    'method' => 'get_gateways_settings',
                ),
                'billing'   => array(
                    'label'  => __( 'Billing Documents', 'semiauto-invoicing' ),
                    'icon'   => 'dashicons-portfolio',
                    'method' => 'get_billing_settings',
                ),
                'status'    => array(
                    'label'  => __( 'Status & Diagnostics', 'semiauto-invoicing' ),
                    'icon'   => 'dashicons-info-outline',
                    'method' => 'get_status_settings',
                ),
            );
        }

        /**
         * Returns the currently active sub-tab slug, defaulting to 'general'.
         *
         * @return string
         */
        public static function get_active_section() {
            // WooCommerce resolves and sanitizes the settings section on its settings screen.
            global $current_section;
            $tabs = self::get_sub_tabs();
            $section = ( !empty( $current_section ) && is_string( $current_section ) ? sanitize_key( $current_section ) : 'general' );
            return ( array_key_exists( $section, $tabs ) ? $section : 'general' );
        }

        /**
         * Initialize settings and AJAX hooks. Load admin subclasses.
         */
        public static function init() {
            add_filter( 'woocommerce_settings_tabs_array', array(__CLASS__, 'add_settings_tab'), 60 );
            add_action( 'woocommerce_settings_tabs_pdf_smart_receipts', array(__CLASS__, 'settings_tab') );
            add_action( 'woocommerce_update_options_pdf_smart_receipts', array(__CLASS__, 'update_settings') );
            add_action( 'woocommerce_admin_field_ogpsr_image_upload', array('OG_PSR_Settings_Renderers', 'render_image_upload_field') );
            add_action( 'woocommerce_admin_field_ogpsr_system_status', array('OG_PSR_Settings_Renderers', 'render_system_status_field') );
            add_action( 'woocommerce_admin_field_ogpsr_compliance_disclaimer', array('OG_PSR_Settings_Renderers', 'render_compliance_disclaimer_field') );
            add_action( 'woocommerce_admin_field_ogpsr_showcase_banner', array('OG_PSR_Settings_Renderers', 'render_showcase_banner_field') );
            add_action( 'woocommerce_admin_field_ogpsr_compact_showcase_banner', array('OG_PSR_Settings_Renderers', 'render_compact_showcase_banner_field') );
            add_action( 'admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_scripts') );
            add_action( 'admin_post_ogpsr_view_receipt', array(__CLASS__, 'view_receipt_handler') );
            add_action( 'wp_ajax_ogpsr_sync_woo_details', array('OG_PSR_Settings_AJAX', 'sync_woo_details_ajax') );
            add_action( 'wp_ajax_ogpsr_purge_cache_ajax', array('OG_PSR_Settings_AJAX', 'purge_cache_ajax') );
            add_action( 'wp_ajax_ogpsr_ajax_save_settings', array('OG_PSR_Settings_AJAX', 'ajax_save_settings') );
            add_action( 'wp_ajax_ogpsr_regenerate_file', array('OG_PSR_Settings_AJAX', 'regenerate_file_ajax') );
        }

        /**
         * Add the plugin tab to WooCommerce Settings.
         *
         * @param array $settings_tabs
         * @return array
         */
        public static function add_settings_tab( $settings_tabs ) {
            $settings_tabs['pdf_smart_receipts'] = __( 'SemiAuto Invoicing', 'semiauto-invoicing' );
            return $settings_tabs;
        }

        /**
         * Renders the sub-tab navigation bar above the settings form.
         */
        private static function render_sub_tabs() {
            $active = self::get_active_section();
            $tabs = self::get_sub_tabs();
            $base_url = admin_url( 'admin.php?page=wc-settings&tab=pdf_smart_receipts' );
            echo '<div class="ogpsr-section-nav" id="ogpsr-section-nav">';
            foreach ( $tabs as $slug => $tab ) {
                $url = esc_url( add_query_arg( 'section', $slug, $base_url ) );
                $is_active = $slug === $active;
                $css_class = ( $is_active ? 'ogpsr-section-tab ogpsr-section-tab--active' : 'ogpsr-section-tab' );
                echo '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $css_class ) . '"' . (( $is_active ? ' aria-current="page"' : '' )) . '>';
                echo '  <span class="dashicons ' . esc_attr( $tab['icon'] ) . '"></span>';
                echo '  ' . esc_html( $tab['label'] );
                // Optional count badge of a sub-tab (e.g. open e-invoicing issues on Status & Diagnostics).
                $badge = (string) apply_filters( 'ogpsr_section_tab_badge', '', $slug );
                if ( '' !== $badge ) {
                    echo ' <span class="ogpsr-section-tab__badge">' . esc_html( $badge ) . '</span>';
                }
                echo '</a>';
            }
            echo '</div>';
        }

        /**
         * Render the settings page layout.
         */
        public static function settings_tab() {
            self::render_sub_tabs();
            $section = self::get_active_section();
            $tabs = self::get_sub_tabs();
            $method = $tabs[$section]['method'];
            $is_showcase = in_array( $section, array(
                'xml',
                'barcode',
                'extras',
                'gateways',
                'billing'
            ), true );
            $hide_preview = $is_showcase || in_array( $section, array(
                'status',
                'gateways',
                'storage',
                'billing'
            ), true );
            $layout_classes = array();
            if ( $hide_preview ) {
                $layout_classes[] = 'ogpsr-hide-preview';
            }
            if ( $is_showcase ) {
                $layout_classes[] = 'ogpsr-showcase-active-tab';
            }
            echo '<div class="ogpsr-admin-split-layout ' . esc_attr( implode( ' ', $layout_classes ) ) . '">';
            echo '  <div class="ogpsr-admin-settings-column">';
            if ( !function_exists( 'woocommerce_admin_fields' ) && defined( 'WC_ABSPATH' ) ) {
                include_once WC_ABSPATH . 'includes/admin/wc-admin-functions.php';
            }
            if ( function_exists( 'woocommerce_admin_fields' ) && class_exists( 'OG_PSR_Settings_Definitions' ) && method_exists( 'OG_PSR_Settings_Definitions', $method ) ) {
                woocommerce_admin_fields( OG_PSR_Settings_Definitions::$method() );
            }
            echo '  </div>';
            if ( !$hide_preview && class_exists( 'OG_PSR_Settings_Renderers' ) ) {
                OG_PSR_Settings_Renderers::render_live_preview_column( $section );
            }
            echo '</div>';
        }

        /**
         * Standard WooCommerce save handler (triggered by native WC submit).
         */
        public static function update_settings() {
            check_admin_referer( 'woocommerce-settings' );
            $section = self::get_active_section();
            $tabs = self::get_sub_tabs();
            if ( !function_exists( 'woocommerce_update_options' ) && defined( 'WC_ABSPATH' ) ) {
                include_once WC_ABSPATH . 'includes/admin/wc-admin-functions.php';
            }
            $method = ( isset( $tabs[$section]['method'] ) ? $tabs[$section]['method'] : 'get_general_settings' );
            if ( class_exists( 'OG_PSR_Settings_Definitions' ) && method_exists( 'OG_PSR_Settings_Definitions', $method ) ) {
                $settings = OG_PSR_Settings_Definitions::$method();
                if ( is_array( $settings ) && function_exists( 'woocommerce_update_options' ) ) {
                    woocommerce_update_options( $settings );
                }
                OG_PSR_PDF_Generator::purge_cache();
            }
        }

        /**
         * Retrieves a plugin option with default fallback.
         *
         * @param string $key           Option key name.
         * @param mixed  $default_value Default value if option is not set.
         * @return mixed Option value or fallback default.
         */
        public static function get( $key, $default_value = '' ) {
            return get_option( $key, $default_value );
        }

        /**
         * Safe helper to set an option value.
         *
         * @param string $key   Option key name.
         * @param mixed  $value Value to set.
         * @return bool True if value was updated, false otherwise.
         */
        public static function set( $key, $value ) {
            return update_option( $key, $value );
        }

        /**
         * Secure PDF/XML viewer file dispatcher.
         */
        public static function view_receipt_handler() {
            if ( !current_user_can( 'manage_woocommerce' ) && !current_user_can( 'manage_options' ) && !current_user_can( 'edit_shop_orders' ) ) {
                OG_PSR_Logger::warning( __( 'Unauthorized administrative document view attempt.', 'semiauto-invoicing' ) );
                wp_die( esc_html__( 'Unauthorized access.', 'semiauto-invoicing' ), esc_html__( 'Forbidden', 'semiauto-invoicing' ), array(
                    'response' => 403,
                ) );
            }
            check_admin_referer( 'ogpsr_view_receipt' );
            $filename = ( isset( $_GET['file'] ) ? sanitize_file_name( wp_unslash( $_GET['file'] ) ) : '' );
            $file_ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
            $is_pdf = 'pdf' === $file_ext;
            $is_valid_format = $is_pdf;
            if ( empty( $filename ) || !$is_valid_format ) {
                wp_die( esc_html__( 'Invalid or missing file.', 'semiauto-invoicing' ), esc_html__( 'Bad Request', 'semiauto-invoicing' ), array(
                    'response' => 400,
                ) );
            }
            $upload_dir = wp_upload_dir();
            $base_dir = ( class_exists( 'OG_PSR_Security' ) ? OG_PSR_Security::resolve_upload_root() : wp_normalize_path( $upload_dir['basedir'] . '/semiauto-invoicing' ) );
            $possible_paths = array();
            if ( class_exists( 'OG_PSR_Document_Registry' ) ) {
                foreach ( OG_PSR_Document_Registry::get_all_subdirs() as $subdir ) {
                    $possible_paths[] = $base_dir . '/' . $subdir . '/' . $filename;
                }
            } else {
                $possible_paths = array($base_dir . '/thermal-receipts/' . $filename, $base_dir . '/invoices/pdf/' . $filename, $base_dir . '/packaging-slips/' . $filename);
            }
            global $wp_filesystem;
            if ( empty( $wp_filesystem ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }
            $file_path = null;
            foreach ( $possible_paths as $path ) {
                $path = wp_normalize_path( $path );
                if ( !class_exists( 'OG_PSR_Security' ) || !OG_PSR_Security::is_path_within( $path, $base_dir ) ) {
                    continue;
                }
                if ( $wp_filesystem && $wp_filesystem->exists( $path ) && !$wp_filesystem->is_dir( $path ) ) {
                    $file_path = $path;
                    break;
                }
            }
            // Self-healing fallback: If exact filename was not found (e.g. hash changed after regeneration), attempt directory scan or dynamic generation
            if ( empty( $file_path ) ) {
                $valid_ext_pattern = 'pdf';
                if ( preg_match( '/^([a-z0-9_-]+?)-(\\d+)(?:-([a-f0-9]+))?\\.(' . $valid_ext_pattern . ')$/i', $filename, $matches ) ) {
                    $prefix_type = strtolower( $matches[1] );
                    $extracted_order_id = absint( $matches[2] );
                    $ext = strtolower( $matches[4] ?? 'pdf' );
                    // 1. Scan candidate directories for existing file matching {type}-{order_id}*.{ext}
                    foreach ( $possible_paths as $candidate_path ) {
                        $parent_dir = dirname( $candidate_path );
                        if ( $wp_filesystem && $wp_filesystem->is_dir( $parent_dir ) ) {
                            $dir_files = $wp_filesystem->dirlist( $parent_dir );
                            if ( !empty( $dir_files ) && is_array( $dir_files ) ) {
                                foreach ( $dir_files as $f_entry ) {
                                    $f_name = ( is_array( $f_entry ) ? $f_entry['name'] ?? '' : $f_entry );
                                    if ( $f_name && preg_match( '/^' . preg_quote( $prefix_type, '/' ) . '-' . $extracted_order_id . '(?:-[a-f0-9]+)?\\.' . preg_quote( $ext, '/' ) . '$/i', $f_name ) ) {
                                        $resolved = wp_normalize_path( $parent_dir . '/' . $f_name );
                                        if ( class_exists( 'OG_PSR_Security' ) && OG_PSR_Security::is_path_within( $resolved, $base_dir ) ) {
                                            $file_path = $resolved;
                                            $filename = $f_name;
                                            break 2;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    // 2. On-demand dynamic generation fallback if still not found
                    if ( empty( $file_path ) && $extracted_order_id > 0 && class_exists( 'OG_PSR_PDF_Generator' ) ) {
                        $order = wc_get_order( $extracted_order_id );
                        if ( $order ) {
                            $pdf_gen = new OG_PSR_PDF_Generator();
                            $generated_path = null;
                            switch ( $prefix_type ) {
                                case 'thermal':
                                    $generated_path = $pdf_gen->generate_thermal( $extracted_order_id );
                                    break;
                                case 'invoice':
                                    $generated_path = $pdf_gen->generate_a4( $extracted_order_id );
                                    break;
                                case 'packaging':
                                    $generated_path = $pdf_gen->generate_packaging( $extracted_order_id );
                                    break;
                                default:
                                    break;
                            }
                            if ( $generated_path && !is_wp_error( $generated_path ) && $wp_filesystem->exists( $generated_path ) ) {
                                $candidate_gen = wp_normalize_path( (string) $generated_path );
                                if ( class_exists( 'OG_PSR_Security' ) && OG_PSR_Security::is_path_within( $candidate_gen, $base_dir ) ) {
                                    $file_path = $candidate_gen;
                                    $filename = basename( $file_path );
                                }
                            }
                        }
                    }
                }
            }
            if ( $file_path && $wp_filesystem && $wp_filesystem->exists( $file_path ) && class_exists( 'OG_PSR_Security' ) && OG_PSR_Security::is_path_within( $file_path, $base_dir ) ) {
                $content_type = 'application/pdf';
                header( 'Content-Type: ' . $content_type );
                header( 'X-Content-Type-Options: nosniff' );
                // Rendered inline: XML / JSON is shown in a sandbox (no scripts); PDFs keep the browser viewer.
                if ( false === strpos( (string) $content_type, 'pdf' ) ) {
                    header( 'Content-Security-Policy: sandbox' );
                }
                $disposition = ( isset( $_GET['download'] ) && '1' === $_GET['download'] ? 'attachment' : 'inline' );
                header( 'Content-Disposition: ' . $disposition . '; filename="' . $filename . '"' );
                header( 'Content-Transfer-Encoding: binary' );
                header( 'Accept-Ranges: bytes' );
                header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
                header( 'Pragma: no-cache' );
                /* translators: 1: disposition action (viewed/downloaded), 2: filename */
                OG_PSR_Logger::info( sprintf( __( 'Admin %1$s document: %2$s', 'semiauto-invoicing' ), $disposition, $filename ) );
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary file stream (PDF/XML/ZIP download); escaping would corrupt the file.
                echo $wp_filesystem->get_contents( $file_path );
                exit;
            } else {
                wp_die( esc_html__( 'Receipt file not found.', 'semiauto-invoicing' ), esc_html__( 'Not Found', 'semiauto-invoicing' ), array(
                    'response' => 404,
                ) );
            }
        }

        /**
         * Register the document and live preview stylesheets.
         *
         * Single source of truth for the preview style handles, shared by the admin
         * enqueue and the live preview AJAX renderer so both always load identical assets.
         */
        public static function register_preview_styles() {
            if ( !wp_style_is( 'ogpsr-document-template', 'registered' ) ) {
                wp_register_style(
                    'ogpsr-document-template',
                    OG_PSR_URL . 'assets/css/ogpsr-document-template.css',
                    array(),
                    OG_PSR_VERSION
                );
            }
            if ( !wp_style_is( 'ogpsr-preview', 'registered' ) ) {
                wp_register_style(
                    'ogpsr-preview',
                    OG_PSR_URL . 'assets/css/ogpsr-preview.css',
                    array('ogpsr-document-template'),
                    OG_PSR_VERSION
                );
            }
        }

        /**
         * Enqueue all admin scripts and styles.
         */
        public static function enqueue_admin_scripts() {
            $screen = get_current_screen();
            if ( !$screen ) {
                return;
            }
            // WooCommerce resolves and sanitizes the active settings tab on its settings screen.
            global $current_tab;
            $tab = ( is_string( $current_tab ) ? $current_tab : '' );
            $is_settings = isset( $screen->id ) && false !== strpos( $screen->id, 'wc-settings' ) && 'pdf_smart_receipts' === $tab;
            $post_type = ( !empty( $screen->post_type ) ? $screen->post_type : '' );
            $is_order = in_array( $post_type, array('shop_order', 'shop_order_refund'), true ) || isset( $screen->id ) && (false !== strpos( $screen->id, 'wc-orders' ) || false !== strpos( $screen->id, 'shop_order' ) || 'edit-shop_order' === $screen->id);
            if ( !$is_settings && !$is_order && 'widgets' !== $screen->id ) {
                return;
            }
            wp_enqueue_media();
            self::register_preview_styles();
            wp_enqueue_style( 'ogpsr-document-template' );
            wp_enqueue_style( 'ogpsr-preview' );
            wp_enqueue_style(
                'ogpsr-admin-style',
                OG_PSR_URL . 'assets/css/ogpsr-admin.css',
                array('ogpsr-document-template', 'ogpsr-preview'),
                OG_PSR_VERSION
            );
            wp_enqueue_script(
                'ogpsr-admin-script',
                OG_PSR_URL . 'assets/js/ogpsr-admin.js',
                array('jquery', 'wp-util'),
                OG_PSR_VERSION,
                true
            );
            /* translators: %s: HTTP status code */
            $preview_network_error = __( 'Server or Network Error (Code: %s)', 'semiauto-invoicing' );
            $script_data = array(
                'ajax_url'        => admin_url( 'admin-ajax.php' ),
                'manager_nonce'   => wp_create_nonce( 'ogpsr_manager_nonce' ),
                'preview_nonce'   => wp_create_nonce( 'ogpsr_preview_nonce' ),
                'ajax_save_nonce' => wp_create_nonce( 'ogpsr_ajax_save_nonce' ),
                'regen_nonce'     => wp_create_nonce( 'ogpsr_regenerate_file' ),
                'purge_nonce'     => wp_create_nonce( 'ogpsr_purge_cache_nonce' ),
                'sync_nonce'      => wp_create_nonce( 'ogpsr_sync_nonce' ),
                'debug'           => defined( 'WP_DEBUG' ) && WP_DEBUG,
                'strings'         => array(
                    'regenerating'             => __( 'Regenerating...', 'semiauto-invoicing' ),
                    'regenerate'               => __( 'Regenerate', 'semiauto-invoicing' ),
                    'saving'                   => __( 'Saving...', 'semiauto-invoicing' ),
                    'save'                     => __( 'Save', 'semiauto-invoicing' ),
                    'saved'                    => __( 'Settings saved successfully!', 'semiauto-invoicing' ),
                    'error'                    => __( 'Error occurred', 'semiauto-invoicing' ),
                    'active'                   => __( 'Active', 'semiauto-invoicing' ),
                    'syncing'                  => __( 'Syncing...', 'semiauto-invoicing' ),
                    'sync'                     => __( 'Sync from WooCommerce', 'semiauto-invoicing' ),
                    'synced'                   => __( 'Settings synchronized with WooCommerce!', 'semiauto-invoicing' ),
                    'server_error'             => __( 'A server error occurred. Please refresh and try again.', 'semiauto-invoicing' ),
                    'confirm_purge'            => __( 'Are you sure you want to purge the "Turbo-Burst" cache? This will force a fresh generation of all document styles and logos.', 'semiauto-invoicing' ),
                    'confirm_purge_short'      => __( 'Are you sure you want to purge the cache?', 'semiauto-invoicing' ),
                    'mandatory_fields_missing' => __( 'Missing mandatory compliance fields. Please check the highlighted inputs and ensure all jurisdictional requirements are met before saving.', 'semiauto-invoicing' ),
                    'choose_image'             => __( 'Choose Image', 'semiauto-invoicing' ),
                    'autofilled'               => __( 'Settings synchronized with WooCommerce!', 'semiauto-invoicing' ),
                    'new_fee'                  => __( 'Processing Fee', 'semiauto-invoicing' ),
                    'deleting'                 => __( 'Deleting...', 'semiauto-invoicing' ),
                    'delete'                   => __( 'Delete', 'semiauto-invoicing' ),
                    'confirm_delete'           => __( 'Are you sure you want to delete this receipt file?', 'semiauto-invoicing' ),
                    'generated'                => __( 'Status: Generated', 'semiauto-invoicing' ),
                    'download'                 => __( 'Download', 'semiauto-invoicing' ),
                    'preview_failed'           => __( 'Preview Failed', 'semiauto-invoicing' ),
                    'preview_unknown_error'    => __( 'Unknown Preview Error', 'semiauto-invoicing' ),
                    'preview_network_error'    => $preview_network_error,
                ),
                'base_country'    => (function () {
                    $country = ( WC()->countries ? WC()->countries->get_base_country() : get_option( 'woocommerce_default_country', 'AE' ) );
                    return ( $country ? strtoupper( explode( ':', $country )[0] ) : 'AE' );
                })(),
            );
            wp_localize_script( 'ogpsr-admin-script', 'ogpsr_admin_data', $script_data );
        }

        /* -------------------------------------------------------------
         * backwards-compatibility Shims & Delegation
         * ------------------------------------------------------------- */
        public static function get_wc_emails() {
            return OG_PSR_Settings_Helpers::get_wc_emails();
        }

        public static function get_order_statuses() {
            return OG_PSR_Settings_Helpers::get_order_statuses();
        }

        public static function send_to_customer_email( $order_id, $pdf_path, $document_type ) {
            return OG_PSR_Settings_Helpers::send_to_customer_email( $order_id, $pdf_path, $document_type );
        }

        public static function get_active_payment_methods_titles() {
            return OG_PSR_Settings_Helpers::get_active_payment_methods_titles();
        }

        public static function get_bacs_account_options() {
            return OG_PSR_Settings_Helpers::get_bacs_account_options();
        }

        public static function sanitize_array_setting( $value ) {
            return OG_PSR_Settings_Helpers::sanitize_array_setting( $value );
        }

    }

}
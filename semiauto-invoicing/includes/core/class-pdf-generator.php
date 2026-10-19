<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
use Dompdf\Dompdf;
use Dompdf\Options;
if ( !class_exists( 'OG_PSR_PDF_Generator' ) ) {
    /**
     * PDF Generator Class
     * Responsible for rendering HTML to PDF using Dompdf.
     * Supports both thermal receipts and A4 invoices.
     */
    class OG_PSR_PDF_Generator {
        /**
         * If true, current generation is for the Live Preview (bypasses cache)
         */
        public static $is_preview = false;

        /**
         * Formats Country and State codes into human-readable "Country - State" string.
         *
         * @param string $country_code 2-letter country code
         * @param string $state_code State code
         * @return string Formatted country/state string
         */
        public static function format_country_state( $country_code, $state_code = '' ) {
            if ( empty( $country_code ) ) {
                return '';
            }
            $country_name = $country_code;
            if ( function_exists( 'WC' ) && WC()->countries ) {
                $countries = WC()->countries->get_countries();
                if ( isset( $countries[$country_code] ) ) {
                    $country_name = $countries[$country_code];
                }
            }
            $state_name = '';
            if ( !empty( $state_code ) && function_exists( 'WC' ) && WC()->countries ) {
                $states = WC()->countries->get_states( $country_code );
                if ( is_array( $states ) && isset( $states[$state_code] ) ) {
                    $state_name = $states[$state_code];
                } else {
                    $state_name = $state_code;
                }
            }
            if ( !empty( $state_name ) ) {
                return $country_name . ' - ' . $state_name;
            }
            return $country_name;
        }

        /**
         * Resolves the active store country code.
         *
         * @return string ISO 3166-1 alpha-2 country code
         */
        public static function get_current_store_country() {
            $country = OG_PSR_Settings::get( 'ogpsr_business_country' );
            if ( empty( $country ) ) {
                $country = OG_PSR_Settings::get( 'ogpsr_xml_supplier_country' );
            }
            if ( empty( $country ) && function_exists( 'wc_get_base_location' ) ) {
                $base_loc = wc_get_base_location();
                $country = $base_loc['country'] ?? '';
            }
            if ( empty( $country ) ) {
                $country = get_option( 'woocommerce_default_country', '' );
            }
            if ( false !== strpos( (string) $country, ':' ) ) {
                $country = explode( ':', (string) $country )[0];
            }
            return strtoupper( trim( (string) $country ) );
        }

        /**
         * Generates an immutable hash signature of the current store identity (country, name, and tax ID).
         *
         * @return string
         */
        public static function get_current_store_signature() {
            $country = self::get_current_store_country();
            $name = OG_PSR_Settings::get( 'ogpsr_business_name', get_bloginfo( 'name' ) );
            $tax_id = ( OG_PSR_Settings::get( 'ogpsr_xml_tax_id' ) ? OG_PSR_Settings::get( 'ogpsr_xml_tax_id' ) : (( OG_PSR_Settings::get( 'ogpsr_vat_number' ) ? OG_PSR_Settings::get( 'ogpsr_vat_number' ) : OG_PSR_Settings::get( 'ogpsr_trn_number' ) )) );
            return md5( $country . '|' . $name . '|' . $tax_id );
        }

        /**
         * Stamps the order with the current merchant store identity signature.
         *
         * @param WC_Order|WC_Abstract_Order $order
         * @return void
         */
        public static function stamp_order_store_identity( $order ) {
            if ( $order && method_exists( $order, 'update_meta_data' ) ) {
                $order->update_meta_data( '_ogpsr_generated_store_country', self::get_current_store_country() );
                $order->update_meta_data( '_ogpsr_generated_store_sig', self::get_current_store_signature() );
            }
        }

        /**
         * Resolves the human-readable payment method title with multi-tier fallbacks across
         * WooCommerce gateways, third-party POS integrations (Chwazi, FooSales, Oliver, YITH POS),
         * and standard gateway slug mappings.
         *
         * @param WC_Abstract_Order|int $order
         * @return string Human-readable payment method title
         */
        public static function get_payment_method_title( $order ) {
            if ( is_numeric( $order ) ) {
                $order = wc_get_order( $order );
            }
            if ( !$order instanceof WC_Abstract_Order ) {
                return '';
            }
            $source = ( $order instanceof WC_Order_Refund ? wc_get_order( $order->get_parent_id() ) : $order );
            if ( !$source ) {
                $source = $order;
            }
            // 1. Direct payment method title on the order
            if ( method_exists( $source, 'get_payment_method_title' ) ) {
                $title = $source->get_payment_method_title();
                if ( !empty( $title ) ) {
                    return $title;
                }
            }
            // 2. Check third-party POS / tender metadata (Chwazi, FooSales, Oliver, YITH POS, Actuality)
            $pos_payment_keys = array(
                '_chwazi_payment_method_title',
                '_chwazi_payment_method',
                '_chwazi_tender_type',
                'foosales_payment_type',
                '_foosales_payment_type',
                '_oliver_payment_title',
                '_oliver_payment_method',
                '_pos_payment_type',
                '_pos_payment_method',
                '_yith_pos_payment_title',
                '_yith_pos_payment_method'
            );
            foreach ( $pos_payment_keys as $key ) {
                $pos_val = $source->get_meta( $key );
                if ( !empty( $pos_val ) ) {
                    return sanitize_text_field( $pos_val );
                }
            }
            // 3. Fallback to registered WooCommerce payment gateway title
            $method_slug = ( method_exists( $source, 'get_payment_method' ) ? $source->get_payment_method() : '' );
            if ( !empty( $method_slug ) ) {
                if ( function_exists( 'WC' ) && WC()->payment_gateways ) {
                    $gateways = WC()->payment_gateways->payment_gateways();
                    if ( isset( $gateways[$method_slug] ) && is_object( $gateways[$method_slug] ) && method_exists( $gateways[$method_slug], 'get_title' ) ) {
                        $gw_title = $gateways[$method_slug]->get_title();
                        if ( !empty( $gw_title ) ) {
                            return $gw_title;
                        }
                    }
                }
                // 4. Standard WooCommerce & POS Gateway Slugs Map
                $standard_map = array(
                    'bacs'       => __( 'Direct Bank Transfer', 'semiauto-invoicing' ),
                    'cheque'     => __( 'Check Payments', 'semiauto-invoicing' ),
                    'cod'        => __( 'Cash on Delivery', 'semiauto-invoicing' ),
                    'paypal'     => __( 'PayPal', 'semiauto-invoicing' ),
                    'stripe'     => __( 'Credit Card (Stripe)', 'semiauto-invoicing' ),
                    'pos_cash'   => __( 'Cash (POS)', 'semiauto-invoicing' ),
                    'pos_card'   => __( 'Card (POS)', 'semiauto-invoicing' ),
                    'chwazi_pos' => __( 'Chwazi POS', 'semiauto-invoicing' ),
                    'foosales'   => __( 'FooSales POS', 'semiauto-invoicing' ),
                    'oliver_pos' => __( 'Oliver POS', 'semiauto-invoicing' ),
                );
                if ( isset( $standard_map[$method_slug] ) ) {
                    return $standard_map[$method_slug];
                }
                // 5. Pretty formatted slug
                return ucwords( str_replace( array('_', '-'), ' ', $method_slug ) );
            }
            return '';
        }

        /**
         * Filters the currency symbol based on the plugin setting.
         * Replaces symbols (e.g. $) with ISO codes (e.g. USD) if configured.
         */
        public function filter_currency_symbol( $symbol, $currency ) {
            $mode = OG_PSR_Settings::get( 'ogpsr_currency_display_mode', 'symbol' );
            return ( 'code' === $mode ? $currency : $symbol );
        }

        public static function get_logo_base64() {
            $attachment_id = (int) OG_PSR_Settings::get( 'ogpsr_logo_attachment_id', 0 );
            if ( $attachment_id <= 0 ) {
                return null;
            }
            // Unified cache key for reliable purging
            $transient_key = 'ogpsr_logo_b64_cache_' . $attachment_id . '_' . OG_PSR_VERSION;
            if ( !self::$is_preview ) {
                $cached_logo = get_transient( $transient_key );
                if ( false !== $cached_logo ) {
                    return $cached_logo;
                }
            }
            $logo_path = OG_PSR_Security::resolve_logo_attachment( $attachment_id );
            if ( !$logo_path ) {
                return null;
            }
            global $wp_filesystem;
            if ( empty( $wp_filesystem ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }
            if ( !$wp_filesystem || !$wp_filesystem->exists( $logo_path ) ) {
                return null;
            }
            $image_data = (string) $wp_filesystem->get_contents( $logo_path );
            if ( empty( $image_data ) ) {
                return null;
            }
            $filetype = wp_check_filetype( $logo_path );
            $mime_type = ( !empty( $filetype['type'] ) ? $filetype['type'] : 'image/png' );
            $final_logo = 'data:' . $mime_type . ';base64,' . base64_encode( $image_data );
            set_transient( $transient_key, $final_logo, DAY_IN_SECONDS );
            return $final_logo;
        }

        /**
         * Purges the global PDF and base64 logo cache.
         * Should be called when settings or logo are updated.
         */
        public static function purge_cache() {
            $attachment_id = (int) OG_PSR_Settings::get( 'ogpsr_logo_attachment_id', 0 );
            if ( $attachment_id > 0 ) {
                delete_transient( 'ogpsr_logo_b64_cache_' . $attachment_id . '_' . OG_PSR_VERSION );
            }
            delete_transient( 'ogpsr_logo_b64_cache_' . OG_PSR_VERSION );
        }

        /**
         * Generate PDFs for an order based on enabled formats
         *
         * @param int $order_id WooCommerce Order ID
         * @return array Array of generated file paths
         */
        public function generate( $order_id ) {
            // Increase limits for PDF generation to prevent incomplete files.
            if ( function_exists( 'wp_raise_memory_limit' ) ) {
                wp_raise_memory_limit( 'admin' );
            }
            // Extend the PHP execution time limit safely using WooCommerce wrapper.
            if ( function_exists( 'wc_set_time_limit' ) ) {
                wc_set_time_limit( 300 );
            }
            $formats = OG_PSR_Settings::get( 'ogpsr_receipt_formats', array('thermal') );
            if ( !is_array( $formats ) ) {
                $formats = array('thermal');
            }
            $files = array();
            $order = wc_get_order( $order_id );
            if ( !$order ) {
                return array();
            }
            // Apply ISO Currency filter if enabled
            add_filter(
                'woocommerce_currency_symbol',
                array($this, 'filter_currency_symbol'),
                999,
                2
            );
            // Standardize the Invoice Number across common third-party plugins
            $invoice_number = $order->get_meta( '_invoice_number' );
            if ( empty( $invoice_number ) ) {
                $invoice_number = $order->get_meta( '_wcpdf_invoice_number' );
            }
            if ( empty( $invoice_number ) ) {
                $invoice_number = (string) (( method_exists( $order, 'get_order_number' ) ? $order->get_order_number() : $order->get_id() ));
            }
            if ( in_array( 'thermal', $formats, true ) ) {
                $thermal_file = $this->generate_thermal( $order_id, $invoice_number );
                if ( $thermal_file ) {
                    $files[] = $thermal_file;
                }
            }
            if ( in_array( 'a4', $formats, true ) ) {
                $a4_file = $this->generate_a4( $order_id, $invoice_number );
                if ( $a4_file ) {
                    $files[] = $a4_file;
                }
            }
            if ( in_array( 'packaging', $formats, true ) ) {
                $pkg_file = $this->generate_packaging( $order_id, $invoice_number );
                if ( $pkg_file ) {
                    $files[] = $pkg_file;
                }
            }
            // Clean up filter
            remove_filter( 'woocommerce_currency_symbol', array($this, 'filter_currency_symbol'), 999 );
            return $files;
        }

        /**
         * Force regenerate thermal receipt (ignores cache)
         *
         * @param int $order_id WooCommerce Order ID
         * @return string|null Absolute file path or null on failure
         */
        public function force_regenerate_thermal( $order_id ) {
            $fs = $this->init_filesystem();
            // Delete all existing thermal PDFs for this order
            $dir = ( class_exists( 'OG_PSR_Security' ) ? OG_PSR_Security::resolve_document_root( 'thermal_receipt' ) : wp_normalize_path( wp_upload_dir()['basedir'] . '/semiauto-invoicing/thermal-receipts' ) );
            $old_files = $this->match_files_fs( $dir, 'thermal-' . $order_id . '-' );
            if ( $fs ) {
                foreach ( $old_files as $file ) {
                    if ( $fs->exists( $file ) ) {
                        $fs->delete( $file );
                    }
                }
            }
            // Clear metadata to ensure fresh generation storage
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $order->delete_meta_data( '_ogpsr_thermal_path' );
                $order->save();
            }
            // Generate new one
            return $this->generate_thermal( $order_id );
        }

        /**
         * Force regenerate A4 invoice (ignores cache)
         *
         * @param int $order_id WooCommerce Order ID
         * @return string|null Absolute file path or null on failure
         */
        public function force_regenerate_a4( $order_id ) {
            $fs = $this->init_filesystem();
            $order = wc_get_order( $order_id );
            $upload_root = ( class_exists( 'OG_PSR_Security' ) ? OG_PSR_Security::resolve_upload_root() : wp_normalize_path( wp_upload_dir()['basedir'] . '/semiauto-invoicing' ) );
            $dir = ( class_exists( 'OG_PSR_Security' ) ? OG_PSR_Security::resolve_document_root( 'invoice' ) : wp_normalize_path( $upload_root . '/invoices/pdf' ) );
            $prefix = 'invoice-' . $order_id . '-';
            $old_files = $this->match_files_fs( $dir, $prefix );
            if ( $fs ) {
                foreach ( $old_files as $file ) {
                    $fname = basename( $file );
                    $parts = explode( '-', preg_replace( '/\\.[^.]+$/', '', $fname ) );
                    // Preserve sub-variant files with extended multi-segment naming patterns (e.g. invoice-ID-variant-hash.pdf)
                    if ( count( $parts ) >= 4 ) {
                        continue;
                    }
                    if ( $fs->exists( $file ) ) {
                        $fs->delete( $file );
                    }
                }
            }
            // Clear metadata to ensure fresh generation storage
            if ( $order ) {
                $order->delete_meta_data( '_ogpsr_a4_path' );
                $order->save();
            }
            // Generate new one
            return $this->generate_a4( $order_id );
        }

        /**
         * Force regenerate packaging slip (ignores cache)
         *
         * @param int $order_id WooCommerce Order ID
         * @return string|null Absolute file path or null on failure
         */
        public function force_regenerate_packaging( $order_id ) {
            $fs = $this->init_filesystem();
            // Delete all existing packaging slips for this order
            $dir = ( class_exists( 'OG_PSR_Security' ) ? OG_PSR_Security::resolve_document_root( 'packaging_slip' ) : wp_normalize_path( wp_upload_dir()['basedir'] . '/semiauto-invoicing/packaging-slips' ) );
            $old_files = $this->match_files_fs( $dir, 'packaging-' . $order_id . '-' );
            if ( $fs ) {
                foreach ( $old_files as $file ) {
                    if ( $fs->exists( $file ) ) {
                        $fs->delete( $file );
                    }
                }
            }
            // Clear metadata to ensure fresh generation storage
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $order->delete_meta_data( '_ogpsr_packaging_path' );
                $order->save();
            }
            // Generate new one
            return $this->generate_packaging( $order_id );
        }

        /**
         * Compute a comprehensive settings hash for a specific document format.
         *
         * @param string $doc_type Document type (see OG_PSR_Document_Registry)
         * @param WC_Order|int $order_or_id
         * @return string 8-character hash
         */
        public static function get_document_settings_signature( $doc_type, $order_or_id ) {
            $hash_data = array(
                $doc_type,
                ( class_exists( 'OG_PSR_Document_Registry' ) ? OG_PSR_Document_Registry::sanitize_font_family( OG_PSR_Settings::get( 'ogpsr_font_family', 'DejaVu Sans' ) ) : 'DejaVu Sans' ),
                OG_PSR_Settings::get( 'ogpsr_font_size', 9 ),
                OG_PSR_Settings::get( 'ogpsr_line_height', 1.2 ),
                OG_PSR_Settings::get( 'ogpsr_business_name', '' ),
                OG_PSR_Settings::get( 'ogpsr_business_name_format', 'none' ),
                OG_PSR_Settings::get( 'ogpsr_business_address', '' ),
                OG_PSR_Settings::get( 'ogpsr_business_city', '' ),
                OG_PSR_Settings::get( 'ogpsr_business_postcode', '' ),
                OG_PSR_Settings::get( 'ogpsr_business_contact', '' ),
                OG_PSR_Settings::get( 'ogpsr_logo_attachment_id', 0 ),
                OG_PSR_Settings::get( 'ogpsr_customer_info_fields', array() ),
                OG_PSR_Settings::get( 'ogpsr_currency_display_mode', 'symbol' ),
                OG_PSR_Settings::get( 'ogpsr_header_text', '' ),
                OG_PSR_Settings::get( 'ogpsr_footer_text', '' )
            );
            if ( 'thermal' === $doc_type ) {
                $hash_data[] = OG_PSR_Settings::get( 'ogpsr_paper_width', '57' );
                $hash_data[] = OG_PSR_Settings::get( 'ogpsr_thermal_terms_and_conditions', '' );
                $hash_data[] = OG_PSR_Settings::get( 'ogpsr_show_tax_inclusion_line', 'yes' );
                $hash_data[] = OG_PSR_Settings::get( 'ogpsr_thermal_show_coupon_codes', 'yes' );
                $hash_data[] = OG_PSR_Settings::get( 'ogpsr_thermal_show_product_meta', 'yes' );
                $hash_data[] = OG_PSR_Settings::get( 'ogpsr_thermal_show_cashier', 'no' );
                $hash_data[] = OG_PSR_Settings::get( 'ogpsr_thermal_cashier_name', '' );
            } elseif ( 'invoice' === $doc_type ) {
                $hash_data[] = OG_PSR_Settings::get( 'ogpsr_a4_show_due_date', 'yes' );
                $hash_data[] = OG_PSR_Settings::get( 'ogpsr_a4_payment_terms', '30' );
                $hash_data[] = OG_PSR_Settings::get( 'ogpsr_show_bank_details', 'yes' );
                $hash_data[] = OG_PSR_Settings::get( 'ogpsr_force_bank_details', 'no' );
                $hash_data[] = OG_PSR_Settings::get( 'ogpsr_selected_bank_accounts', array() );
                $hash_data[] = OG_PSR_Settings::get( 'ogpsr_a4_show_product_ean', 'no' );
                $hash_data[] = OG_PSR_Settings::get( 'ogpsr_a4_show_product_sku', 'no' );
                $hash_data[] = OG_PSR_Settings::get( 'ogpsr_a4_show_coupon_codes', 'yes' );
                $hash_data[] = OG_PSR_Settings::get( 'ogpsr_a4_terms_and_conditions', '' );
            }
            return substr( md5( (string) wp_json_encode( $hash_data ) ), 0, 8 );
        }

        /**
         * Atomically publishes a generated document buffer to its final destination,
         * ensuring zero partial reads and safe deferred stale-version cleanup.
         *
         * @param string                  $destination_path Final destination file path.
         * @param string                  $content          Binary/string content to publish.
         * @param string                  $cleanup_prefix   Prefix pattern for obsolete variant cleanup in directory (e.g. 'thermal-123-').
         * @param WP_Filesystem_Base|null $fs               Optional initialized filesystem object.
         * @return bool True on success, false on failure.
         */
        public static function atomic_publish(
            $destination_path,
            $content,
            $cleanup_prefix = '',
            $fs = null
        ) {
            if ( empty( $destination_path ) || empty( $content ) ) {
                return false;
            }
            // Validate destination file extension
            $ext = strtolower( pathinfo( $destination_path, PATHINFO_EXTENSION ) );
            $valid_ext = 'pdf' === $ext;
            if ( !$valid_ext ) {
                return false;
            }
            // Establish, create, and verify the trusted destination directory first (TOCTOU defense)
            $raw_dir = dirname( $destination_path );
            $safe_filename = basename( $destination_path );
            $trusted_dir = ( class_exists( 'OG_PSR_Security' ) ? OG_PSR_Security::establish_trusted_directory( $raw_dir ) : (( function_exists( 'wp_mkdir_p' ) ? ( wp_mkdir_p( $raw_dir ) ? realpath( $raw_dir ) : false ) : false )) );
            if ( false === $trusted_dir || empty( $trusted_dir ) ) {
                OG_PSR_Logger::error( 'Failed to establish trusted destination directory for document publishing.' );
                return false;
            }
            // Reconstruct destination strictly from already-resolved trusted directory and safe filename
            $destination_path = wp_normalize_path( $trusted_dir . '/' . $safe_filename );
            // Enforce final defense-in-depth containment check within plugin uploads root
            if ( !class_exists( 'OG_PSR_Security' ) || !OG_PSR_Security::is_path_within( $destination_path, OG_PSR_Security::resolve_upload_root() ) ) {
                return false;
            }
            if ( empty( $fs ) ) {
                global $wp_filesystem;
                if ( empty( $wp_filesystem ) ) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    WP_Filesystem();
                }
                $fs = $wp_filesystem;
            }
            if ( !$fs ) {
                return false;
            }
            $dir = $trusted_dir;
            $tmp_file = $trusted_dir . '/' . $safe_filename . '.tmp.' . wp_generate_password( 16, false, false );
            if ( !class_exists( 'OG_PSR_Security' ) || !OG_PSR_Security::is_path_within( $tmp_file, OG_PSR_Security::resolve_upload_root() ) ) {
                return false;
            }
            $written = $fs->put_contents( $tmp_file, $content );
            if ( !$written || !$fs->exists( $tmp_file ) || 0 === (int) $fs->size( $tmp_file ) ) {
                if ( $fs->exists( $tmp_file ) ) {
                    $fs->delete( $tmp_file );
                }
                return false;
            }
            // Atomic publish via WP_Filesystem move
            $published = $fs->move( $tmp_file, $destination_path, true );
            // Clean up temporary file if move failed
            if ( $fs->exists( $tmp_file ) ) {
                $fs->delete( $tmp_file );
            }
            if ( !$published ) {
                return false;
            }
            // Post-publish deferred cleanup of obsolete variants for this order
            if ( !empty( $cleanup_prefix ) && $fs->is_dir( $dir ) ) {
                $dir_list = $fs->dirlist( $dir );
                if ( is_array( $dir_list ) ) {
                    $ext_suffix = '.' . $ext;
                    foreach ( $dir_list as $file_info ) {
                        $name = $file_info['name'];
                        if ( 0 === strpos( $name, $cleanup_prefix ) && substr( $name, -strlen( $ext_suffix ) ) === $ext_suffix ) {
                            $old_file = $dir . '/' . $name;
                            if ( $old_file !== $destination_path && false === strpos( $old_file, '.tmp.' ) && $fs->exists( $old_file ) ) {
                                if ( class_exists( 'OG_PSR_Security' ) && OG_PSR_Security::is_path_within( $old_file, OG_PSR_Security::resolve_upload_root() ) ) {
                                    $fs->delete( $old_file );
                                }
                            }
                        }
                    }
                }
            }
            return true;
        }

        /**
         * Generate thermal receipt PDF
         *
         * @param int $order_id WooCommerce Order ID
         * @return string|null Absolute file path or null on failure
         */
        public function generate_thermal( $order_id, $invoice_number = '' ) {
            $dir = ( class_exists( 'OG_PSR_Security' ) ? OG_PSR_Security::resolve_document_root( 'thermal_receipt' ) : wp_normalize_path( wp_upload_dir()['basedir'] . '/semiauto-invoicing/thermal-receipts' ) );
            // Fail closed before cached PDFs can be returned or new files generated.
            if ( !class_exists( 'OG_PSR_Security' ) || !OG_PSR_Security::ensure_directory_security( $dir ) ) {
                if ( class_exists( 'OG_PSR_Logger' ) ) {
                    OG_PSR_Logger::error( 'Storage protection could not be verified; PDF generation was stopped.', $order_id );
                }
                return null;
            }
            // Compute centralized settings hash for cache-busting (PHASE2-CACHE-001)
            $settings_hash = self::get_document_settings_signature( 'thermal', $order_id );
            $file = $dir . '/thermal-' . $order_id . '-' . $settings_hash . '.pdf';
            $fs = $this->init_filesystem();
            // Check if file exists using WP_Filesystem
            if ( $fs && $fs->exists( $file ) ) {
                return $file;
            }
            // Ensure thermal subdirectory exists
            if ( function_exists( 'wp_mkdir_p' ) ) {
                wp_mkdir_p( $dir );
            } elseif ( $fs && !$fs->is_dir( $dir ) ) {
                $fs->mkdir( $dir );
            }
            $this->ensure_security_files( $dir );
            self::ensure_directory_security( $dir );
            $order = wc_get_order( $order_id );
            if ( !$order ) {
                return null;
            }
            require_once OG_PSR_PATH . 'vendor/dompdf/autoload.inc.php';
            // Apply ISO Currency filter if enabled
            add_filter(
                'woocommerce_currency_symbol',
                array($this, 'filter_currency_symbol'),
                999,
                2
            );
            $paper_width_mm = (int) OG_PSR_Settings::get( 'ogpsr_paper_width', '57' );
            $doc = null;
            if ( class_exists( 'OG_PSR_Document_Data_Engine' ) ) {
                $doc = OG_PSR_Document_Data_Engine::build_document_context( $order, 'thermal_receipt', (string) $paper_width_mm . 'mm' );
            }
            $fs = $this->init_filesystem();
            ob_start();
            $template_path = OG_PSR_Template_Registry::get_template_path();
            if ( $fs && $fs->exists( $template_path ) ) {
                load_template( $template_path, false, array(
                    'doc' => $doc,
                ) );
            }
            $html = ob_get_clean();
            // Strip TranslatePress-specific tags (#!trpst# and #!trpen#) to ensure clean HTML for PDF rendering.
            $html = preg_replace( '/#\\!trpst#.*?#\\!trpen#/s', '', $html );
            $options = $this->get_dompdf_options( 72 );
            $dompdf = new Dompdf($options);
            $height_mm = 500;
            $dompdf->setPaper( array(
                0,
                0,
                $this->mm_to_pt( $paper_width_mm ),
                $this->mm_to_pt( $height_mm )
            ), 'portrait' );
            try {
                // Minify HTML whitespace to optimize Dompdf FrameTree memory usage
                $html = preg_replace( '/>\\s+</', '><', trim( $html ) );
                $dompdf->loadHtml( $html );
                if ( is_array( $doc ) && class_exists( 'OG_PSR_Template_Registry' ) ) {
                    $dynamic_rules = OG_PSR_Template_Registry::compile_document_rules( $doc );
                    if ( !empty( $dynamic_rules ) ) {
                        $dompdf->getCss()->load_css( $dynamic_rules );
                    }
                }
                $dompdf->render();
                $pdf_output = $dompdf->output();
                // Atomically publish with deferred stale variant cleanup (RC-004)
                $written = self::atomic_publish(
                    $file,
                    $pdf_output,
                    'thermal-' . $order_id . '-',
                    $fs
                );
                if ( !$written ) {
                    OG_PSR_Logger::error( 'Failed to write Thermal Receipt to disk.', $order_id );
                    return null;
                }
                // Save the generated file path to order meta for caching
                $order->update_meta_data( '_ogpsr_thermal_path', $file );
                self::stamp_order_store_identity( $order );
                $order->save();
            } catch ( Exception $e ) {
                OG_PSR_Logger::error( 'Failed to generate Thermal Receipt: ' . $e->getMessage(), $order_id );
                return null;
            } finally {
                // Always remove the currency filter and free memory resources
                remove_filter( 'woocommerce_currency_symbol', array($this, 'filter_currency_symbol'), 999 );
                if ( isset( $dompdf ) ) {
                    unset($dompdf);
                }
            }
            if ( 'yes' === OG_PSR_Settings::get( 'ogpsr_enable_activity_logging', 'no' ) ) {
                /* translators: %s: Order ID */
                OG_PSR_Logger::info( sprintf( __( 'Thermal Receipt successfully generated for Order #%s', 'semiauto-invoicing' ), $order_id ) );
            }
            return $file;
        }

        /**
         * Generate A4 invoice PDF
         *
         * @param int $order_id WooCommerce Order ID
         * @return string|null Absolute file path or null on failure
         */
        public function generate_a4( $order_id, $invoice_number = '' ) {
            $order = wc_get_order( $order_id );
            if ( !$order ) {
                return null;
            }
            $upload_root = ( class_exists( 'OG_PSR_Security' ) ? OG_PSR_Security::resolve_upload_root() : wp_normalize_path( wp_upload_dir()['basedir'] . '/semiauto-invoicing' ) );
            $doc_type = 'invoice';
            $dir = ( class_exists( 'OG_PSR_Security' ) ? OG_PSR_Security::resolve_document_root( 'invoice' ) : wp_normalize_path( $upload_root . '/invoices/pdf' ) );
            $file_prefix = 'invoice-' . $order_id . '-';
            // Verify the final target, including Premium credit-note routing, before cache lookup.
            if ( !class_exists( 'OG_PSR_Security' ) || !OG_PSR_Security::ensure_directory_security( $dir ) ) {
                if ( class_exists( 'OG_PSR_Logger' ) ) {
                    OG_PSR_Logger::error( 'Storage protection could not be verified; PDF generation was stopped.', $order_id );
                }
                return null;
            }
            // Compute centralized settings hash for active document type
            $settings_hash = self::get_document_settings_signature( $doc_type, $order_id );
            $file = $dir . '/' . $file_prefix . $settings_hash . '.pdf';
            $fs = $this->init_filesystem();
            // Check if file exists using WP_Filesystem
            if ( $fs && $fs->exists( $file ) ) {
                return $file;
            }
            // Ensure target directory and security files exist
            if ( function_exists( 'wp_mkdir_p' ) ) {
                wp_mkdir_p( $dir );
            } elseif ( $fs && !$fs->is_dir( $dir ) ) {
                $fs->mkdir( $dir );
            }
            $this->ensure_security_files( $dir );
            self::ensure_directory_security( $dir );
            // Generate invoice number
            $invoice_number = $this->get_invoice_number( $order_id );
            require_once OG_PSR_PATH . 'vendor/dompdf/autoload.inc.php';
            // Apply ISO Currency filter if enabled
            add_filter(
                'woocommerce_currency_symbol',
                array($this, 'filter_currency_symbol'),
                999,
                2
            );
            // Prepare normalized $doc view-model context array
            $doc = null;
            if ( class_exists( 'OG_PSR_Document_Data_Engine' ) ) {
                $doc = OG_PSR_Document_Data_Engine::build_document_context( $order );
            }
            $fs = $this->init_filesystem();
            ob_start();
            // Resolve presentation template path via Template Registry
            $template_path = OG_PSR_Template_Registry::get_template_path();
            if ( $fs && $fs->exists( $template_path ) ) {
                load_template( $template_path, false, array(
                    'doc' => $doc,
                ) );
            }
            $html = ob_get_clean();
            // Strip TranslatePress-specific tags to prevent rendering artifacts in the final PDF.
            $html = preg_replace( '/#\\!trpst#.*?#\\!trpen#/s', '', $html );
            $options = $this->get_dompdf_options( 96 );
            $dompdf = new Dompdf($options);
            try {
                $dompdf->setPaper( 'A4', 'portrait' );
                // Minify HTML whitespace to optimize Dompdf FrameTree memory usage
                $html = preg_replace( '/>\\s+</', '><', trim( $html ) );
                $dompdf->loadHtml( $html );
                if ( is_array( $doc ) && class_exists( 'OG_PSR_Template_Registry' ) ) {
                    $dynamic_rules = OG_PSR_Template_Registry::compile_document_rules( $doc );
                    if ( !empty( $dynamic_rules ) ) {
                        $dompdf->getCss()->load_css( $dynamic_rules );
                    }
                }
                $dompdf->render();
                // Inject dynamic page numbering on canvas ({PAGE_NUM} / {PAGE_COUNT})
                $canvas = $dompdf->getCanvas();
                $font_metrics = $dompdf->getFontMetrics();
                $page_font = ( $font_metrics ? $font_metrics->getFont( 'DejaVu Sans', 'normal' ) : null );
                if ( $canvas && $font_metrics && $page_font ) {
                    $page_num_str = '{PAGE_NUM} / {PAGE_COUNT}';
                    $calc_width = $font_metrics->getTextWidth( '88 / 88', $page_font, 8 );
                    $canvas->page_text(
                        $canvas->get_width() - 40 - $calc_width,
                        $canvas->get_height() - 25,
                        $page_num_str,
                        $page_font,
                        8,
                        array(0.5, 0.5, 0.5)
                    );
                }
                $pdf_output = $dompdf->output();
                // Atomically publish with deferred stale variant cleanup (RC-004)
                $written = self::atomic_publish(
                    $file,
                    $pdf_output,
                    $file_prefix,
                    $fs
                );
                if ( !$written ) {
                    OG_PSR_Logger::error( 'Failed to write A4 Invoice to disk.', $order_id );
                    return null;
                }
                // Save the generated file path to order meta for caching
                $order->update_meta_data( '_ogpsr_a4_path', $file );
                self::stamp_order_store_identity( $order );
                $order->save();
            } catch ( \Throwable $e ) {
                OG_PSR_Logger::error( 'Failed to generate A4 Invoice: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), $order_id );
                return null;
            } finally {
                // Always remove the currency filter and free memory resources
                remove_filter( 'woocommerce_currency_symbol', array($this, 'filter_currency_symbol'), 999 );
                if ( isset( $dompdf ) ) {
                    unset($dompdf);
                }
            }
            if ( 'yes' === OG_PSR_Settings::get( 'ogpsr_enable_activity_logging', 'no' ) ) {
                /* translators: %s: Order ID */
                OG_PSR_Logger::info( sprintf( __( 'A4 Invoice successfully generated for Order #%s', 'semiauto-invoicing' ), $order_id ) );
            }
            return $file;
        }

        /**
         * Generate Packaging Slip PDF
         * 
         * @param int $order_id WooCommerce Order ID
         * @return string|null Absolute file path or null on failure
         */
        public function generate_packaging( $order_id, $invoice_number = '' ) {
            $dir = ( class_exists( 'OG_PSR_Security' ) ? OG_PSR_Security::resolve_document_root( 'packaging_slip' ) : wp_normalize_path( wp_upload_dir()['basedir'] . '/semiauto-invoicing/packaging-slips' ) );
            // Fail closed before cached PDFs can be returned or new files generated.
            if ( !class_exists( 'OG_PSR_Security' ) || !OG_PSR_Security::ensure_directory_security( $dir ) ) {
                if ( class_exists( 'OG_PSR_Logger' ) ) {
                    OG_PSR_Logger::error( 'Storage protection could not be verified; PDF generation was stopped.', $order_id );
                }
                return null;
            }
            // Compute centralized settings hash for Packaging-specific settings (PHASE2-CACHE-001)
            $settings_hash = self::get_document_settings_signature( 'packaging', $order_id );
            $file = $dir . '/packaging-' . $order_id . '-' . $settings_hash . '.pdf';
            $fs = $this->init_filesystem();
            // Check if file exists using WP_Filesystem
            if ( $fs && $fs->exists( $file ) ) {
                return $file;
            }
            // Ensure packaging subdirectory exists
            if ( function_exists( 'wp_mkdir_p' ) ) {
                wp_mkdir_p( $dir );
            } elseif ( $fs && !$fs->is_dir( $dir ) ) {
                $fs->mkdir( $dir );
            }
            $this->ensure_security_files( $dir );
            self::ensure_directory_security( $dir );
            $order = wc_get_order( $order_id );
            if ( !$order ) {
                return null;
            }
            require_once OG_PSR_PATH . 'vendor/dompdf/autoload.inc.php';
            // Apply ISO Currency filter if enabled
            add_filter(
                'woocommerce_currency_symbol',
                array($this, 'filter_currency_symbol'),
                999,
                2
            );
            $doc = null;
            if ( class_exists( 'OG_PSR_Document_Data_Engine' ) ) {
                $doc = OG_PSR_Document_Data_Engine::build_document_context( $order, 'packaging_slip', 'a4' );
            }
            $fs = $this->init_filesystem();
            ob_start();
            $template_path = OG_PSR_Template_Registry::get_template_path();
            if ( $fs && $fs->exists( $template_path ) ) {
                load_template( $template_path, false, array(
                    'doc' => $doc,
                ) );
            }
            $html = ob_get_clean();
            // Strip TranslatePress-specific tags to prevent rendering artifacts in the final PDF.
            $html = preg_replace( '/#\\!trpst#.*?#\\!trpen#/s', '', $html );
            $options = $this->get_dompdf_options( 96 );
            $dompdf = new Dompdf($options);
            try {
                $dompdf->setPaper( 'A4', 'portrait' );
                // Minify HTML whitespace to optimize Dompdf FrameTree memory usage
                $html = preg_replace( '/>\\s+</', '><', trim( $html ) );
                $dompdf->loadHtml( $html );
                if ( is_array( $doc ) && class_exists( 'OG_PSR_Template_Registry' ) ) {
                    $dynamic_rules = OG_PSR_Template_Registry::compile_document_rules( $doc );
                    if ( !empty( $dynamic_rules ) ) {
                        $dompdf->getCss()->load_css( $dynamic_rules );
                    }
                }
                $dompdf->render();
                // Inject dynamic page numbering on canvas ({PAGE_NUM} / {PAGE_COUNT})
                $canvas = $dompdf->getCanvas();
                $font_metrics = $dompdf->getFontMetrics();
                $page_font = ( $font_metrics ? $font_metrics->getFont( 'DejaVu Sans', 'normal' ) : null );
                if ( $canvas && $font_metrics && $page_font ) {
                    $page_num_str = '{PAGE_NUM} / {PAGE_COUNT}';
                    $calc_width = $font_metrics->getTextWidth( '88 / 88', $page_font, 8 );
                    $canvas->page_text(
                        $canvas->get_width() - 40 - $calc_width,
                        $canvas->get_height() - 25,
                        $page_num_str,
                        $page_font,
                        8,
                        array(0.5, 0.5, 0.5)
                    );
                }
                $pdf_output = $dompdf->output();
                // Atomically publish with deferred stale variant cleanup (RC-004)
                $written = self::atomic_publish(
                    $file,
                    $pdf_output,
                    'packaging-' . $order_id . '-',
                    $fs
                );
                if ( !$written ) {
                    OG_PSR_Logger::error( 'Failed to write Packaging Slip to disk.', $order_id );
                    return null;
                }
                // Save the generated file path to order meta for caching
                $order->update_meta_data( '_ogpsr_packaging_path', $file );
                self::stamp_order_store_identity( $order );
                $order->save();
            } catch ( Exception $e ) {
                OG_PSR_Logger::error( 'Failed to generate Packaging Slip: ' . $e->getMessage(), $order_id );
                return null;
            } finally {
                // Always remove the currency filter and free memory resources
                remove_filter( 'woocommerce_currency_symbol', array($this, 'filter_currency_symbol'), 999 );
                if ( isset( $dompdf ) ) {
                    unset($dompdf);
                }
            }
            if ( 'yes' === OG_PSR_Settings::get( 'ogpsr_enable_activity_logging', 'no' ) ) {
                /* translators: %s: Order ID */
                OG_PSR_Logger::info( sprintf( __( 'Packaging Slip successfully generated for Order #%s', 'semiauto-invoicing' ), $order_id ) );
            }
            return $file;
        }

        /**
         * Get invoice number for an order.
         *
         * @param int $order_id WooCommerce Order ID.
         * @return string Invoice number.
         */
        private function get_invoice_number( $order_id ) {
            return '#' . $order_id;
        }

        /**
         * Initialize WP_Filesystem
         * 
         * @return \WP_Filesystem_Base|false
         */
        public function init_filesystem() {
            global $wp_filesystem;
            if ( empty( $wp_filesystem ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }
            return $wp_filesystem;
        }

        /**
         * Find matching files in a directory using WP_Filesystem instead of glob().
         *
         * @param string $dir       Absolute directory path.
         * @param string $prefix    Prefix to match (e.g., 'thermal-123-', 'invoice-123-', 'packaging-123-').
         * @param string $extension Extension to match (e.g., '.pdf').
         * @return array List of absolute matching file paths.
         */
        public function match_files_fs( $dir, $prefix, $extension = '.pdf' ) {
            $fs = $this->init_filesystem();
            $matches = array();
            if ( $fs && $fs->is_dir( $dir ) ) {
                $dir_list = $fs->dirlist( $dir );
                if ( is_array( $dir_list ) ) {
                    foreach ( $dir_list as $file_info ) {
                        $name = $file_info['name'];
                        if ( 0 === strpos( $name, $prefix ) && substr( $name, -strlen( $extension ) ) === $extension ) {
                            $matches[] = $dir . '/' . $name;
                        }
                    }
                }
            }
            return $matches;
        }

        /**
         * Get or generate the A4 PDF file path for an order.
         *
         * @param WC_Order|WC_Order_Refund|int $order
         * @return string Path to generated PDF file or empty string on failure.
         */
        public static function get_order_pdf_path( $order ) {
            try {
                $order_id = ( is_numeric( $order ) ? (int) $order : (( $order instanceof WC_Abstract_Order ? $order->get_id() : 0 )) );
                if ( !$order_id ) {
                    return '';
                }
                $generator = new self();
                $pdf_path = $generator->generate_a4( $order_id );
                $fs = $generator->init_filesystem();
                return ( is_string( $pdf_path ) && $fs && $fs->exists( $pdf_path ) ? $pdf_path : '' );
            } catch ( \Throwable $e ) {
                OG_PSR_Logger::error( 'OG PSR get_order_pdf_path error: ' . $e->getMessage() );
                return '';
            }
        }

        /**
         * Ensures the uploads directory exists and is secured
         *
         * @param string $dir Optional directory path. Defaults to plugin upload root.
         * @return bool
         */
        public static function ensure_directory_security( $dir = '' ) {
            if ( class_exists( 'OG_PSR_Security' ) ) {
                return OG_PSR_Security::ensure_directory_security( $dir );
            }
            return false;
        }

        public function mm_to_pt( $mm ) {
            // Correct formula: points = (millimeters ÷ 25.4) × 72
            // 1 inch = 25.4mm = 72 points
            return $mm / 25.4 * 72;
        }

        /**
         * Ensure security files exist in directory
         *
         * @param string $dir Directory path
         * @return bool
         */
        public function ensure_security_files( $dir ) {
            return self::ensure_directory_security( $dir );
        }

        /**
         * Get persistent font cache directory with security protection
         *
         * @return string Absolute path to font cache directory
         */
        public static function get_font_cache_dir() {
            $upload_dir = wp_upload_dir();
            $font_dir = $upload_dir['basedir'] . '/semiauto-invoicing/fonts';
            $generator = new self();
            $fs = $generator->init_filesystem();
            if ( $fs && !$fs->is_dir( $font_dir ) ) {
                $fs->mkdir( $font_dir );
                $generator->ensure_security_files( $font_dir );
            }
            return $font_dir;
        }

        /**
         * Centralized Dompdf Options Factory
         * Configured for maximum generation speed, persistent font caching, chroot isolation, and stream compression.
         *
         * @param int $dpi Resolution DPI (72 for thermal, 96 for A4)
         * @return Options Configured Dompdf Options instance
         */
        public function get_dompdf_options( $dpi = 96 ) {
            $options = new Options();
            $options->set( 'isRemoteEnabled', false );
            $options->set( 'isHtml5ParserEnabled', true );
            $options->set( 'isFontSubsettingEnabled', true );
            $options->set( 'isCompressEnabled', true );
            $options->set( 'defaultFont', ( class_exists( 'OG_PSR_Document_Registry' ) ? OG_PSR_Document_Registry::sanitize_font_family( OG_PSR_Settings::get( 'ogpsr_font_family', 'DejaVu Sans' ) ) : 'DejaVu Sans' ) );
            $options->set( 'dpi', (int) $dpi );
            // Phase 1: Dedicated Persistent Font Cache
            $font_dir = self::get_font_cache_dir();
            $options->set( 'fontDir', $font_dir );
            $options->set( 'fontCache', $font_dir );
            // Phase 2: Local chroot Asset Resolution
            $chroot = array(WP_CONTENT_DIR, OG_PSR_PATH);
            $options->set( 'chroot', $chroot );
            return $options;
        }

    }

    // End class OG_PSR_PDF_Generator
}
// End if !class_exists
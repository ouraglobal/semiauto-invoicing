<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( !class_exists( 'OG_PSR_Order_Metabox' ) ) {
    /**
     * Order Metabox Class
     * 
     * Adds custom metaboxes to the WooCommerce order edit screens,
     * allowing users to view, generate, and regenerate PDFs and XMLs.
     * 
     * @package SemiAutoInvoicing
     */
    class OG_PSR_Order_Metabox {
        /**
         * Register hooks and actions.
         */
        public function __construct() {
            add_action( 'add_meta_boxes', [$this, 'add_metabox'] );
        }

        /**
         * Register the metabox on the shop_order post type.
         */
        public function add_metabox() {
            add_meta_box(
                'ogpsr_pdf_controls',
                __( 'Receipt & Invoice Documents', 'semiauto-invoicing' ),
                [$this, 'render_metabox'],
                'shop_order',
                'side',
                'default'
            );
            // Support for HPOS (High-Performance Order Storage)
            add_meta_box(
                'ogpsr_pdf_controls',
                __( 'Receipt & Invoice Documents', 'semiauto-invoicing' ),
                [$this, 'render_metabox'],
                'woocommerce_page_wc-orders',
                'side',
                'default'
            );
        }

        /**
         * Render the metabox content.
         * 
         * @param WC_Order|WP_Post $post The current order object or post object.
         */
        public function render_metabox( $post ) {
            // Handle both traditional posts and HPOS orders
            $order = ( $post instanceof WC_Order ? $post : wc_get_order( $post->ID ) );
            if ( !$order ) {
                return;
            }
            $order_id = $order->get_id();
            $enabled_formats = OG_PSR_Settings::get( 'ogpsr_receipt_formats', ['thermal'] );
            if ( !is_array( $enabled_formats ) ) {
                $enabled_formats = ['thermal'];
            }
            $store_details_changed = self::has_store_details_changed( $order );
            wp_nonce_field( 'ogpsr_regenerate_file', 'ogpsr_regenerate_nonce' );
            echo '<div class="ogpsr-pdf-controls">';
            if ( in_array( 'thermal', $enabled_formats ) ) {
                $thermal_exists = $this->check_pdf_exists( $order, 'thermal' );
                echo '<div class="ogpsr-pdf-item" data-format="thermal">';
                echo '  <div class="ogpsr-pdf-title">';
                echo '      <span class="ogpsr-dot ' . (( $thermal_exists ? 'success' : 'missing' )) . '">●</span> ';
                echo esc_html__( 'Thermal Receipt', 'semiauto-invoicing' );
                echo '      <span class="spinner ogpsr-spinner"></span>';
                echo '  </div>';
                echo '  <div class="ogpsr-pdf-status ' . esc_attr( ( $thermal_exists ? 'exists' : 'missing' ) ) . '">';
                echo ( $thermal_exists ? esc_html__( 'Status: Generated', 'semiauto-invoicing' ) : esc_html__( 'Status: Not Generated', 'semiauto-invoicing' ) );
                echo '  </div>';
                echo '  <div class="ogpsr-pdf-actions">';
                echo '      <button type="button" class="button button-primary ogpsr-regenerate-btn" data-order-id="' . esc_attr( $order_id ) . '" data-format="thermal" data-nonce="' . esc_attr( wp_create_nonce( 'ogpsr_regenerate_file' ) ) . '">';
                echo ( $thermal_exists ? esc_html__( 'Regenerate', 'semiauto-invoicing' ) : esc_html__( 'Generate', 'semiauto-invoicing' ) );
                echo '      </button>';
                echo '      <a href="' . esc_url( OG_PSR_Download_Handler::get_download_url( $order_id, 'thermal' ) ) . '" class="button ogpsr-download-link ' . (( $thermal_exists ? '' : 'ogpsr-hide' )) . '" target="_blank"';
                echo '>';
                echo esc_html__( 'Download', 'semiauto-invoicing' );
                echo '      </a>';
                echo '  </div>';
                if ( $thermal_exists && $store_details_changed ) {
                    echo '<div class="ogpsr-store-diff-notice">';
                    echo '    <span class="dashicons dashicons-info"></span>';
                    echo '    <span>' . esc_html__( 'This document was generated with different store details. Regenerating will update store details to current.', 'semiauto-invoicing' ) . '</span>';
                    echo '</div>';
                }
                echo '  <div class="ogpsr-mt-5">';
                echo '      <label>';
                echo '          <input type="checkbox" class="ogpsr-send-email" />';
                echo esc_html__( 'Send email to customer', 'semiauto-invoicing' );
                echo '      </label>';
                echo '  </div>';
                echo '</div>';
            }
            if ( in_array( 'a4', $enabled_formats ) ) {
                $a4_exists = $this->check_pdf_exists( $order, 'a4' );
                echo '<div class="ogpsr-pdf-item" data-format="a4">';
                echo '  <div class="ogpsr-pdf-title">';
                echo '      <span class="ogpsr-dot ' . (( $a4_exists ? 'success' : 'missing' )) . '">●</span> ';
                echo esc_html__( 'Primary Order Invoice', 'semiauto-invoicing' );
                echo '      <span class="spinner ogpsr-spinner"></span>';
                echo '  </div>';
                echo '  <div class="ogpsr-pdf-status ' . esc_attr( ( $a4_exists ? 'exists' : 'missing' ) ) . '">';
                echo ( $a4_exists ? esc_html__( 'Status: Generated', 'semiauto-invoicing' ) : esc_html__( 'Status: Not Generated', 'semiauto-invoicing' ) );
                echo '  </div>';
                echo '  <div class="ogpsr-pdf-actions">';
                echo '      <button type="button" class="button button-primary ogpsr-regenerate-btn" data-order-id="' . esc_attr( $order_id ) . '" data-format="a4" data-nonce="' . esc_attr( wp_create_nonce( 'ogpsr_regenerate_file' ) ) . '">';
                echo ( $a4_exists ? esc_html__( 'Regenerate', 'semiauto-invoicing' ) : esc_html__( 'Generate', 'semiauto-invoicing' ) );
                echo '      </button>';
                echo '      <a href="' . esc_url( OG_PSR_Download_Handler::get_download_url( $order_id, 'a4' ) ) . '" class="button ogpsr-download-link ' . (( $a4_exists ? '' : 'ogpsr-hide' )) . '" target="_blank">';
                echo esc_html__( 'Download', 'semiauto-invoicing' );
                echo '      </a>';
                echo '  </div>';
                if ( $a4_exists && $store_details_changed ) {
                    echo '<div class="ogpsr-store-diff-notice">';
                    echo '    <span class="dashicons dashicons-info"></span>';
                    echo '    <span>' . esc_html__( 'This document was generated with different store details. Regenerating will update store details to current.', 'semiauto-invoicing' ) . '</span>';
                    echo '</div>';
                }
                echo '  <div class="ogpsr-mt-5">';
                echo '      <label>';
                echo '          <input type="checkbox" class="ogpsr-send-email" />';
                echo esc_html__( 'Send email to customer', 'semiauto-invoicing' );
                echo '      </label>';
                echo '  </div>';
                echo '</div>';
            }
            if ( in_array( 'packaging', $enabled_formats ) ) {
                $pkg_exists = $this->check_pdf_exists( $order, 'packaging' );
                echo '<div class="ogpsr-pdf-item" data-format="packaging">';
                echo '  <div class="ogpsr-pdf-title">';
                echo '      <span class="ogpsr-dot ' . (( $pkg_exists ? 'success' : 'missing' )) . '">●</span> ';
                echo esc_html__( 'Packaging Slip', 'semiauto-invoicing' );
                echo '      <span class="spinner ogpsr-spinner"></span>';
                echo '  </div>';
                echo '  <div class="ogpsr-pdf-status ' . esc_attr( ( $pkg_exists ? 'exists' : 'missing' ) ) . '">';
                echo ( $pkg_exists ? esc_html__( 'Status: Generated', 'semiauto-invoicing' ) : esc_html__( 'Status: Not Generated', 'semiauto-invoicing' ) );
                echo '  </div>';
                echo '  <div class="ogpsr-pdf-actions">';
                echo '      <button type="button" class="button button-primary ogpsr-regenerate-btn" data-order-id="' . esc_attr( $order_id ) . '" data-format="packaging" data-nonce="' . esc_attr( wp_create_nonce( 'ogpsr_regenerate_file' ) ) . '">';
                echo ( $pkg_exists ? esc_html__( 'Regenerate', 'semiauto-invoicing' ) : esc_html__( 'Generate', 'semiauto-invoicing' ) );
                echo '      </button>';
                echo '      <a href="' . esc_url( OG_PSR_Download_Handler::get_download_url( $order_id, 'packaging' ) ) . '" class="button ogpsr-download-link ' . (( $pkg_exists ? '' : 'ogpsr-hide' )) . '" target="_blank">';
                echo esc_html__( 'Download', 'semiauto-invoicing' );
                echo '      </a>';
                echo '  </div>';
                if ( $pkg_exists && $store_details_changed ) {
                    echo '<div class="ogpsr-store-diff-notice">';
                    echo '    <span class="dashicons dashicons-info"></span>';
                    echo '    <span>' . esc_html__( 'This document was generated with different store details. Regenerating will update store details to current.', 'semiauto-invoicing' ) . '</span>';
                    echo '</div>';
                }
                echo '  <div class="ogpsr-mt-5">';
                echo '      <label>';
                echo '          <input type="checkbox" class="ogpsr-send-email" />';
                echo esc_html__( 'Send email to customer', 'semiauto-invoicing' );
                echo '      </label>';
                echo '  </div>';
                echo '</div>';
            }
            echo '</div>';
        }

        /**
         * Check if PDF exists for order
         *
         * @param WC_Order $order
         * @param string   $format
         * @return bool
         */
        private function check_pdf_exists( $order, $format ) {
            // OPTIMIZATION: Check meta path first via Document Registry
            $meta_key = OG_PSR_Document_Registry::get_meta_key( $format );
            if ( $meta_key ) {
                $path = ( $order ? $order->get_meta( $meta_key ) : '' );
                // Security: Enforce central security barrier and authoritative document validation
                if ( $path && class_exists( 'OG_PSR_Document_Registry' ) && OG_PSR_Document_Registry::validate_document_file( $path, $format ) ) {
                    return true;
                }
            }
            // Fallback: Scan filesystem directory for matching files
            $upload_dir = wp_upload_dir();
            $subdir = OG_PSR_Document_Registry::get_storage_subdir_by_format( $format );
            $prefix = OG_PSR_Document_Registry::get_filename_prefix( $format );
            if ( !$subdir || !$prefix ) {
                return false;
            }
            $base_dir = ( class_exists( 'OG_PSR_Security' ) ? OG_PSR_Security::resolve_upload_root() : wp_normalize_path( wp_upload_dir()['basedir'] . '/semiauto-invoicing' ) );
            $dir = $base_dir . '/' . $subdir;
            $existing = [];
            global $wp_filesystem;
            if ( empty( $wp_filesystem ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }
            if ( $wp_filesystem && $wp_filesystem->is_dir( $dir ) ) {
                $dir_list = $wp_filesystem->dirlist( $dir );
                if ( is_array( $dir_list ) ) {
                    $file_prefix = $prefix . $order->get_id() . '-';
                    foreach ( $dir_list as $file_info ) {
                        $name = $file_info['name'];
                        if ( strpos( $name, $file_prefix ) === 0 && substr( $name, -4 ) === '.pdf' ) {
                            $candidate_file = $dir . '/' . $name;
                            if ( class_exists( 'OG_PSR_Document_Registry' ) && OG_PSR_Document_Registry::validate_document_file( $candidate_file, $format ) ) {
                                $existing[] = $candidate_file;
                            }
                        }
                    }
                }
            }
            // Update meta if found via fallback
            if ( !empty( $existing ) ) {
                if ( $order && $meta_key ) {
                    $order->update_meta_data( $meta_key, $existing[0] );
                    $order->save();
                }
                return true;
            }
            return false;
        }

        /**
         * Checks if store identity has changed since the order document was generated.
         *
         * @param WC_Order|WC_Abstract_Order $order
         * @return bool
         */
        public static function has_store_details_changed( $order ) {
            if ( !is_a( $order, 'WC_Abstract_Order' ) ) {
                return false;
            }
            $generated_sig = $order->get_meta( '_ogpsr_generated_store_sig', true );
            if ( !empty( $generated_sig ) ) {
                return $generated_sig !== OG_PSR_PDF_Generator::get_current_store_signature();
            }
            $generated_country = $order->get_meta( '_ogpsr_generated_store_country', true );
            if ( !empty( $generated_country ) ) {
                return $generated_country !== OG_PSR_PDF_Generator::get_current_store_country();
            }
            return false;
        }

    }

}
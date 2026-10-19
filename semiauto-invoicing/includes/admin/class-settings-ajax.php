<?php

/**
 * Settings Tab AJAX Handlers and Controllers
 * 
 * Secure processing for settings saving, transient purging, diagnostic cleanup, and receipt regeneration.
 * 
 * @package SemiAutoInvoicing
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( !class_exists( 'OG_PSR_Settings_AJAX' ) ) {
    class OG_PSR_Settings_AJAX {
        /**
         * AJAX Save Handler — processes settings without a full page refresh.
         */
        public static function ajax_save_settings() {
            // 1. Nonce verification.
            if ( !isset( $_POST['nonce'] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ogpsr_ajax_save_nonce' ) ) {
                wp_send_json_error( array(
                    'message' => esc_html__( 'Security check failed.', 'semiauto-invoicing' ),
                ) );
            }
            // 2. Capability check.
            if ( !current_user_can( 'manage_woocommerce' ) ) {
                wp_send_json_error( array(
                    'message' => esc_html__( 'You do not have permission to change these settings.', 'semiauto-invoicing' ),
                ) );
            }
            // 3. Validate the section slug against a strict whitelist.
            $section = ( isset( $_POST['section'] ) ? sanitize_key( wp_unslash( $_POST['section'] ) ) : 'general' );
            $sub_tabs = OG_PSR_Settings::get_sub_tabs();
            if ( !array_key_exists( $section, $sub_tabs ) ) {
                wp_send_json_error( array(
                    'message' => esc_html__( 'Invalid settings section.', 'semiauto-invoicing' ),
                ) );
            }
            $method = $sub_tabs[$section]['method'];
            if ( class_exists( 'OG_PSR_Settings_Definitions' ) && method_exists( 'OG_PSR_Settings_Definitions', $method ) ) {
                $settings = OG_PSR_Settings_Definitions::$method();
                if ( is_array( $settings ) ) {
                    woocommerce_update_options( $settings );
                }
            }
            $saved_msg = sprintf( 
                /* translators: %s: Time string */
                esc_html__( 'Settings saved at %s.', 'semiauto-invoicing' ),
                esc_html( wp_date( get_option( 'time_format' ) ) )
             );
            // Purge Transients to ensure changes reflect immediately.
            OG_PSR_PDF_Generator::purge_cache();
            // Log Pulse
            OG_PSR_Logger::info( __( 'Settings updated by administrator.', 'semiauto-invoicing' ) );
            wp_send_json_success( array(
                'message' => $saved_msg,
            ) );
        }

        /**
         * AJAX: Purge the "Turbo-Burst" Transient cache.
         */
        public static function purge_cache_ajax() {
            check_ajax_referer( 'ogpsr_purge_cache_nonce', 'nonce' );
            if ( !current_user_can( 'manage_options' ) ) {
                wp_send_json_error( array(
                    'message' => __( 'Insufficient permissions.', 'semiauto-invoicing' ),
                ) );
            }
            OG_PSR_PDF_Generator::purge_cache();
            if ( 'yes' === get_option( 'ogpsr_enable_activity_logging', 'no' ) ) {
                OG_PSR_Logger::info( __( 'Turbo-Burst cache manually purged by administrator.', 'semiauto-invoicing' ) );
            }
            wp_send_json_success( array(
                'message' => __( 'Cache successfully purged!', 'semiauto-invoicing' ),
            ) );
        }

        /**
         * AJAX handler to regenerate a specific document file.
         */
        public static function regenerate_file_ajax() {
            // 1. Security Check
            if ( !isset( $_POST['nonce'] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ogpsr_regenerate_file' ) ) {
                wp_send_json_error( array(
                    'message' => __( 'Security check failed.', 'semiauto-invoicing' ),
                ) );
            }
            if ( !current_user_can( 'manage_woocommerce' ) ) {
                wp_send_json_error( array(
                    'message' => __( 'Insufficient permissions.', 'semiauto-invoicing' ),
                ) );
            }
            $order_id = ( isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0 );
            $format = ( isset( $_POST['format'] ) ? sanitize_key( wp_unslash( $_POST['format'] ) ) : 'thermal' );
            if ( !$order_id ) {
                wp_send_json_error( array(
                    'message' => __( 'Invalid Order ID.', 'semiauto-invoicing' ),
                ) );
            }
            $pdf_gen = new OG_PSR_PDF_Generator();
            $file_path = null;
            try {
                switch ( $format ) {
                    case 'thermal':
                        $file_path = $pdf_gen->force_regenerate_thermal( $order_id );
                        break;
                    case 'a4':
                        $file_path = $pdf_gen->force_regenerate_a4( $order_id );
                        break;
                    case 'packaging':
                        $file_path = $pdf_gen->force_regenerate_packaging( $order_id );
                        break;
                    default:
                        throw new Exception(__( 'Invalid format specified.', 'semiauto-invoicing' ));
                }
                if ( is_wp_error( $file_path ) ) {
                    throw new Exception($file_path->get_error_message());
                }
                global $wp_filesystem;
                if ( empty( $wp_filesystem ) ) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    WP_Filesystem();
                }
                if ( $file_path && $wp_filesystem && $wp_filesystem->exists( $file_path ) && !$wp_filesystem->is_dir( $file_path ) ) {
                    // Security: Enforce final filesystem containment barrier on regenerated file (fail-closed)
                    if ( !class_exists( 'OG_PSR_Security' ) || !OG_PSR_Security::is_path_within( $file_path, OG_PSR_Security::resolve_upload_root() ) ) {
                        throw new Exception(__( 'Security check failed: Generated file path outside upload boundary.', 'semiauto-invoicing' ));
                    }
                    $send_email = isset( $_POST['send_email'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['send_email'] ) );
                    $email_sent = false;
                    if ( $send_email ) {
                        $email_sent = OG_PSR_Settings_Helpers::send_to_customer_email( $order_id, $file_path, $format );
                    }
                    $msg = __( 'File regenerated successfully.', 'semiauto-invoicing' );
                    if ( $send_email ) {
                        $msg .= ( $email_sent ? ' ' . __( 'Email sent to customer.', 'semiauto-invoicing' ) : ' ' . __( 'Email sending failed.', 'semiauto-invoicing' ) );
                    }
                    $response_data = array(
                        'message'      => $msg,
                        'filename'     => basename( $file_path ),
                        'download_url' => OG_PSR_Download_Handler::get_download_url( $order_id, $format ),
                        'view_url'     => wp_nonce_url( admin_url( 'admin-post.php?action=ogpsr_view_receipt&file=' . rawurlencode( basename( $file_path ) ) ), 'ogpsr_view_receipt' ),
                    );
                    wp_send_json_success( $response_data );
                } else {
                    throw new Exception(__( 'File generation failed or file not found.', 'semiauto-invoicing' ));
                }
            } catch ( Exception $e ) {
                OG_PSR_Logger::error( 'OGPSR Regenerate Error: ' . $e->getMessage(), $order_id );
                wp_send_json_error( array(
                    'message' => __( 'Document regeneration failed. Please check the system log for details.', 'semiauto-invoicing' ),
                ) );
            } catch ( Throwable $t ) {
                OG_PSR_Logger::error( 'OGPSR Regenerate Fatal: ' . $t->getMessage(), $order_id );
                wp_send_json_error( array(
                    'message' => __( 'A technical error occurred during document generation. Please check the system log.', 'semiauto-invoicing' ),
                ) );
            }
        }

        /**
         * AJAX Handler: Synchronize WooCommerce Company Details
         */
        public static function sync_woo_details_ajax() {
            if ( !current_user_can( 'manage_woocommerce' ) && !current_user_can( 'manage_options' ) ) {
                wp_send_json_error( array(
                    'message' => __( 'Unauthorized access', 'semiauto-invoicing' ),
                ) );
            }
            if ( !check_ajax_referer( 'ogpsr_sync_nonce', 'nonce', false ) && !check_ajax_referer( 'ogpsr_ajax_save_nonce', 'nonce', false ) ) {
                wp_send_json_error( array(
                    'message' => __( 'Security verification failed. Please refresh the page and try again.', 'semiauto-invoicing' ),
                ) );
            }
            $store_address = get_option( 'woocommerce_store_address', '' );
            $store_address_2 = get_option( 'woocommerce_store_address_2', '' );
            $store_city = get_option( 'woocommerce_store_city', '' );
            $store_postcode = get_option( 'woocommerce_store_postcode', '' );
            $store_country = get_option( 'woocommerce_default_country', '' );
            // Format business street address ONLY (excluding city & postcode to prevent duplication)
            $address_parts = array();
            if ( !empty( $store_address ) ) {
                $address_parts[] = $store_address;
            }
            if ( !empty( $store_address_2 ) ) {
                $address_parts[] = $store_address_2;
            }
            $formatted_address = implode( "\n", $address_parts );
            $supplier_country = '';
            $state_code = '';
            if ( !empty( $store_country ) ) {
                $country_parts = explode( ':', $store_country );
                $supplier_country = $country_parts[0];
                $state_code = ( isset( $country_parts[1] ) ? $country_parts[1] : '' );
            }
            // Format contact info with Email and Website
            $contact_parts = array();
            $store_email = ( get_option( 'woocommerce_email_from_address' ) ? get_option( 'woocommerce_email_from_address' ) : get_option( 'admin_email', '' ) );
            if ( !empty( $store_email ) ) {
                $contact_parts[] = 'Email: ' . $store_email;
            }
            $site_url = home_url();
            if ( !empty( $site_url ) ) {
                $contact_parts[] = 'Website: ' . $site_url;
            }
            $formatted_contact = implode( "\n", $contact_parts );
            $business_name = get_bloginfo( 'name' );
            wp_send_json_success( array(
                'business_name'     => $business_name,
                'business_address'  => $formatted_address,
                'business_city'     => $store_city,
                'business_postcode' => $store_postcode,
                'business_contact'  => $formatted_contact,
                'supplier_country'  => $supplier_country,
                'state_code'        => $state_code,
            ) );
        }

    }

}
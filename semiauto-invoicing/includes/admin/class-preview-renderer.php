<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Live Preview Renderer
 * 
 * Handles AJAX requests to render templates using the Mock Order Engine.
 * Supports Thermal (44/58/80mm), A4, and Packaging formats.
 */
class OG_PSR_Preview_Renderer {
    public function __construct() {
        add_action( 'wp_ajax_ogpsr_get_live_preview', array($this, 'handle_preview_request') );
    }

    public function handle_preview_request() {
        // Output buffering guard: catch and discard any deprecation notices or unexpected warnings in PHP 8.x
        if ( 0 === ob_get_level() ) {
            ob_start();
        }
        check_ajax_referer( 'ogpsr_preview_nonce', 'nonce' );
        if ( !current_user_can( 'manage_woocommerce' ) ) {
            if ( ob_get_level() > 0 ) {
                ob_end_clean();
            }
            wp_send_json_error( array(
                'message' => __( 'Unauthorized', 'semiauto-invoicing' ),
            ) );
            return;
        }
        $format = ( isset( $_REQUEST['format'] ) ? sanitize_key( wp_unslash( $_REQUEST['format'] ) ) : 'thermal' );
        $width = ( isset( $_REQUEST['width'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['width'] ) ) : '80mm' );
        if ( !in_array( $width, array(
            '80mm',
            '76mm',
            '58mm',
            '57mm',
            '44mm',
            'a4',
            'packaging',
            '210mm',
            '100%'
        ), true ) ) {
            $width = '80mm';
        }
        // Base text sanitization (line breaks preserved); each key is then whitelisted and validated per type below.
        $raw_settings = ( isset( $_REQUEST['settings'] ) && is_array( $_REQUEST['settings'] ) ? map_deep( wp_unslash( $_REQUEST['settings'] ), 'sanitize_textarea_field' ) : array() );
        $live_settings = array();
        $allowed_ids = ( class_exists( 'OG_PSR_Settings_Definitions' ) && method_exists( 'OG_PSR_Settings_Definitions', 'get_all_setting_ids' ) ? OG_PSR_Settings_Definitions::get_all_setting_ids() : array() );
        // Apply Live Settings Filters with strict prefix and key whitelisting
        if ( !empty( $raw_settings ) ) {
            foreach ( $raw_settings as $option_name => $live_val ) {
                $clean_option = sanitize_key( $option_name );
                // Only allow recognized plugin settings
                if ( 0 !== strpos( $clean_option, 'ogpsr_' ) ) {
                    continue;
                }
                if ( empty( $allowed_ids ) || !in_array( $clean_option, $allowed_ids, true ) ) {
                    continue;
                }
                // Strict context-aware validation
                if ( in_array( $clean_option, array('ogpsr_font_family', 'ogpsr_thermal_font_family'), true ) ) {
                    $safe_val = ( class_exists( 'OG_PSR_Document_Registry' ) ? OG_PSR_Document_Registry::sanitize_font_family( $live_val ) : 'DejaVu Sans' );
                } elseif ( in_array( $clean_option, array('ogpsr_accent_color', 'ogpsr_font_color', 'ogpsr_thermal_accent_color'), true ) ) {
                    $safe_val = ( is_string( $live_val ) ? ( sanitize_hex_color( $live_val ) ? sanitize_hex_color( $live_val ) : '#1e293b' ) : '#1e293b' );
                } elseif ( 'ogpsr_font_size' === $clean_option ) {
                    $safe_val = (string) min( 24, max( 6, absint( ( is_scalar( $live_val ) ? $live_val : 9 ) ) ) );
                } elseif ( 'ogpsr_line_height' === $clean_option ) {
                    $safe_val = (string) min( 3.0, max( 1.0, (float) (( is_scalar( $live_val ) ? $live_val : 1.2 )) ) );
                } elseif ( 'ogpsr_logo_attachment_id' === $clean_option ) {
                    $safe_val = absint( $live_val );
                } elseif ( 'ogpsr_receipt_formats' === $clean_option ) {
                    // Strictly whitelist Free formats: a4, thermal, packaging
                    if ( is_array( $live_val ) ) {
                        $allowed_formats = array('a4', 'thermal', 'packaging');
                        $safe_val = array();
                        foreach ( $live_val as $item ) {
                            if ( is_string( $item ) && in_array( $item, $allowed_formats, true ) ) {
                                $safe_val[] = $item;
                            }
                        }
                    } else {
                        $safe_val = array('thermal');
                    }
                } elseif ( 'ogpsr_customer_info_fields' === $clean_option ) {
                    // Strictly whitelist recognized customer detail fields
                    if ( is_array( $live_val ) ) {
                        $allowed_customer_fields = array(
                            'first_name',
                            'last_name',
                            'company',
                            'address_1',
                            'city',
                            'postcode',
                            'country',
                            'phone',
                            'email',
                            'custom_fields'
                        );
                        $safe_val = array();
                        foreach ( $live_val as $item ) {
                            if ( is_string( $item ) && in_array( $item, $allowed_customer_fields, true ) ) {
                                $safe_val[] = $item;
                            }
                        }
                    } else {
                        $safe_val = array();
                    }
                } elseif ( in_array( $clean_option, array('ogpsr_selected_bank_accounts', 'ogpsr_thermal_meta_keys'), true ) ) {
                    // Strictly whitelist 1D flat arrays of strings/integers, rejecting arbitrary nested arrays
                    if ( is_array( $live_val ) ) {
                        $safe_val = array();
                        foreach ( $live_val as $item ) {
                            if ( is_scalar( $item ) ) {
                                $safe_val[] = sanitize_text_field( (string) $item );
                            }
                        }
                    } else {
                        $safe_val = array();
                    }
                } elseif ( is_array( $live_val ) ) {
                    // Reject any unrecognized nested arrays
                    $safe_val = '';
                } elseif ( in_array( $clean_option, array(
                    'ogpsr_business_contact',
                    'ogpsr_business_address',
                    'ogpsr_business_address_2',
                    'ogpsr_footer_text',
                    'ogpsr_terms_text',
                    'ogpsr_thermal_footer_text',
                    'ogpsr_packaging_notes',
                    'ogpsr_custom_fees_data',
                    'ogpsr_staff_roster_data',
                    'ogpsr_staff_roster'
                ), true ) ) {
                    $safe_val = ( is_string( $live_val ) ? sanitize_textarea_field( $live_val ) : '' );
                } else {
                    $safe_val = ( is_string( $live_val ) ? sanitize_text_field( $live_val ) : '' );
                }
                if ( is_array( $safe_val ) && in_array( '__ogpsr_empty__', $safe_val, true ) ) {
                    $safe_val = array();
                }
                $live_settings[$clean_option] = $safe_val;
                add_filter( 'pre_option_' . $clean_option, function ( $pre_val ) use($safe_val) {
                    if ( '' === $safe_val || null === $safe_val ) {
                        return false;
                    }
                    return $safe_val;
                } );
            }
        }
        // High-Fidelity Currency Sync: Ensure preview reflects ISO Code vs Symbol setting
        add_filter(
            'woocommerce_currency_symbol',
            function ( $symbol, $currency ) use($live_settings) {
                $mode = $live_settings['ogpsr_currency_display_mode'] ?? (( class_exists( 'OG_PSR_Settings' ) ? OG_PSR_Settings::get( 'ogpsr_currency_display_mode', 'symbol' ) : 'symbol' ));
                return ( 'code' === $mode ? $currency : $symbol );
            },
            999,
            2
        );
        // Validate format against canonical Free Document Registry
        if ( class_exists( 'OG_PSR_Document_Registry' ) && !OG_PSR_Document_Registry::is_supported_format( $format ) ) {
            ob_get_clean();
            wp_send_json_error( array(
                'message' => __( 'Invalid preview format.', 'semiauto-invoicing' ),
            ) );
            return;
        }
        // Optimization: Use a static variable to cache the order object across the same request
        // and a transient to cache the Order ID across multiple AJAX calls for this session.
        static $cached_order = null;
        if ( null !== $cached_order ) {
            $order = $cached_order;
        } else {
            $user_id = get_current_user_id();
            $transient_key = 'ogpsr_preview_order_id_' . $user_id;
            $preview_order_id = get_transient( $transient_key );
            $order = ( $preview_order_id ? wc_get_order( $preview_order_id ) : null );
            if ( $order instanceof WC_Order_Refund ) {
                delete_transient( $transient_key );
                $order = null;
            }
            if ( !$order && function_exists( 'wc_get_orders' ) ) {
                $recent_orders = wc_get_orders( array(
                    'limit'   => 1,
                    'orderby' => 'date',
                    'order'   => 'DESC',
                    'return'  => 'ids',
                    'type'    => 'shop_order',
                ) );
                if ( !empty( $recent_orders ) ) {
                    $order = wc_get_order( $recent_orders[0] );
                    set_transient( $transient_key, $recent_orders[0], HOUR_IN_SECONDS );
                }
            }
            if ( !$order ) {
                if ( class_exists( 'OG_PSR_Mock_Order' ) ) {
                    $order = new OG_PSR_Mock_Order();
                }
            }
            $cached_order = $order;
        }
        $is_demo = $order instanceof OG_PSR_Mock_Order;
        // Signal to the PDF Generator that we are in Preview Mode (bypasses cache)
        if ( class_exists( 'OG_PSR_PDF_Generator' ) ) {
            OG_PSR_PDF_Generator::$is_preview = true;
        }
        // Render Template with error trapping
        try {
            $html = $this->render_template( $format, $order, $width );
            $html = $this->prepare_preview_document( $html, $width );
            if ( $is_demo ) {
                $demo_notice = '<div class="ogpsr-demo-notice">' . esc_html__( 'Showing Demo Data (No orders found)', 'semiauto-invoicing' ) . '</div>';
                $html = str_replace( '</body>', $demo_notice . '</body>', $html );
            }
            if ( ob_get_level() > 0 ) {
                ob_end_clean();
            }
            wp_send_json_success( array(
                'html' => $html,
            ) );
        } catch ( Throwable $e ) {
            if ( class_exists( 'OG_PSR_Logger' ) ) {
                OG_PSR_Logger::error( 'Preview Rendering Error: ' . $e->getMessage() );
            }
            if ( ob_get_level() > 0 ) {
                ob_end_clean();
            }
            wp_send_json_error( array(
                'message' => __( 'Preview could not be generated. Please verify your settings and try again.', 'semiauto-invoicing' ),
            ) );
            return;
        }
    }

    /**
     * Prepare the rendered template for the isolated live preview iframe.
     *
     * Prints the registered preview stylesheets into the document head through the
     * core WP_Styles printer, so styles are applied before the first paint in every
     * browser, and adds the preview scoping classes and paper-width attribute
     * server-side. The PDF (Dompdf) pipeline does not use this method.
     *
     * @param string $html  Rendered document template markup.
     * @param string $width Whitelisted preview paper width.
     * @return string
     */
    private function prepare_preview_document( $html, $width ) {
        $html = (string) $html;
        if ( class_exists( 'OG_PSR_Settings' ) && method_exists( 'OG_PSR_Settings', 'register_preview_styles' ) ) {
            OG_PSR_Settings::register_preview_styles();
            $styles = wp_styles();
            $do_concat = $styles->do_concat;
            $styles->do_concat = false;
            ob_start();
            $styles->do_items( array('ogpsr-preview') );
            $links = (string) ob_get_clean();
            $styles->do_concat = $do_concat;
            $head_close = stripos( $html, '</head>' );
            if ( '' !== $links && false !== $head_close ) {
                $html = substr_replace(
                    $html,
                    $links,
                    $head_close,
                    0
                );
            }
        }
        $html = preg_replace(
            '/<html\\b/i',
            '<html data-ogpsr-width="' . esc_attr( $width ) . '"',
            $html,
            1
        );
        $html = preg_replace(
            '/(<html\\b[^>]*\\bclass=")/i',
            '$1ogpsr-preview-html ',
            (string) $html,
            1
        );
        $html = preg_replace(
            '/(<body\\b[^>]*\\bclass=")/i',
            '$1ogpsr-preview-body ',
            (string) $html,
            1
        );
        return (string) $html;
    }

    private function render_template( $format, $order, $width ) {
        $doc_type = 'invoice';
        if ( 'packaging' === $format ) {
            $doc_type = 'packaging_slip';
        } elseif ( 'thermal' === $format ) {
            $doc_type = 'thermal_receipt';
        }
        $page_format = ( in_array( $format, array('a4', 'packaging'), true ) ? 'a4' : $width );
        $doc = array();
        if ( class_exists( 'OG_PSR_Document_Data_Engine' ) ) {
            $doc = OG_PSR_Document_Data_Engine::build_document_context( $order, $doc_type, $page_format );
        }
        $template_path = ( class_exists( 'OG_PSR_Template_Registry' ) ? OG_PSR_Template_Registry::get_template_path() : wp_normalize_path( OG_PSR_PATH . 'templates/themes/modern-slate/document-template.php' ) );
        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        if ( !$wp_filesystem || !$wp_filesystem->exists( $template_path ) ) {
            return esc_html__( 'Template not found.', 'semiauto-invoicing' );
        }
        ob_start();
        load_template( $template_path, false, array(
            'doc' => $doc,
        ) );
        return (string) ob_get_clean();
    }

}

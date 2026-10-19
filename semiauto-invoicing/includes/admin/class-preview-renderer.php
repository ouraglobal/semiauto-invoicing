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
        add_action( 'wp_ajax_ogpsr_get_live_preview', [$this, 'handle_preview_request'] );
    }

    public function handle_preview_request() {
        // Output buffering guard: catch and discard any deprecation notices or unexpected warnings in PHP 8.x
        ob_start();
        check_ajax_referer( 'ogpsr_preview_nonce', 'nonce' );
        if ( !current_user_can( 'manage_woocommerce' ) ) {
            ob_get_clean();
            wp_send_json_error( [
                'message' => __( 'Unauthorized', 'semiauto-invoicing' ),
            ] );
        }
        $format = ( isset( $_REQUEST['format'] ) ? sanitize_key( wp_unslash( $_REQUEST['format'] ) ) : 'thermal' );
        $width = ( isset( $_REQUEST['width'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['width'] ) ) : '80mm' );
        if ( !in_array( $width, [
            '80mm',
            '76mm',
            '58mm',
            '57mm',
            '44mm',
            'a4',
            'packaging',
            '210mm',
            '100%'
        ], true ) ) {
            $width = '80mm';
        }
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Whitelisted and sanitized item-by-item against get_all_setting_ids() below.
        $raw_settings = ( isset( $_REQUEST['settings'] ) && is_array( $_REQUEST['settings'] ) ? (array) wp_unslash( $_REQUEST['settings'] ) : [] );
        $live_settings = [];
        $allowed_fonts = [
            'DejaVu Sans',
            'Courier',
            'Helvetica',
            'Times',
            'Verdana',
            'Arial'
        ];
        $allowed_ids = ( class_exists( 'OG_PSR_Settings_Definitions' ) && method_exists( 'OG_PSR_Settings_Definitions', 'get_all_setting_ids' ) ? OG_PSR_Settings_Definitions::get_all_setting_ids() : [] );
        // Apply Live Settings Filters with strict prefix and key whitelisting
        if ( !empty( $raw_settings ) ) {
            foreach ( $raw_settings as $option_name => $live_val ) {
                $clean_option = sanitize_key( $option_name );
                // Only allow recognized plugin settings
                if ( strpos( $clean_option, 'ogpsr_' ) !== 0 ) {
                    continue;
                }
                if ( empty( $allowed_ids ) || !in_array( $clean_option, $allowed_ids, true ) ) {
                    continue;
                }
                // Strict context-aware validation
                if ( in_array( $clean_option, ['ogpsr_font_family', 'ogpsr_thermal_font_family'], true ) ) {
                    $safe_val = ( class_exists( 'OG_PSR_Document_Registry' ) ? OG_PSR_Document_Registry::sanitize_font_family( $live_val ) : 'DejaVu Sans' );
                } elseif ( in_array( $clean_option, ['ogpsr_accent_color', 'ogpsr_font_color', 'ogpsr_thermal_accent_color'], true ) ) {
                    $safe_val = ( is_string( $live_val ) ? ( sanitize_hex_color( $live_val ) ?: '#1e293b' ) : '#1e293b' );
                } elseif ( $clean_option === 'ogpsr_font_size' ) {
                    $safe_val = (string) min( 24, max( 6, absint( ( is_scalar( $live_val ) ? $live_val : 9 ) ) ) );
                } elseif ( $clean_option === 'ogpsr_line_height' ) {
                    $safe_val = (string) min( 3.0, max( 1.0, (float) (( is_scalar( $live_val ) ? $live_val : 1.2 )) ) );
                } elseif ( $clean_option === 'ogpsr_logo_attachment_id' ) {
                    $safe_val = absint( $live_val );
                } elseif ( $clean_option === 'ogpsr_receipt_formats' ) {
                    // Strictly whitelist Free formats: a4, thermal, packaging
                    if ( is_array( $live_val ) ) {
                        $allowed_formats = ['a4', 'thermal', 'packaging'];
                        $safe_val = [];
                        foreach ( $live_val as $item ) {
                            if ( is_string( $item ) && in_array( $item, $allowed_formats, true ) ) {
                                $safe_val[] = $item;
                            }
                        }
                    } else {
                        $safe_val = ['thermal'];
                    }
                } elseif ( $clean_option === 'ogpsr_customer_info_fields' ) {
                    // Strictly whitelist recognized customer detail fields
                    if ( is_array( $live_val ) ) {
                        $allowed_customer_fields = [
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
                        ];
                        $safe_val = [];
                        foreach ( $live_val as $item ) {
                            if ( is_string( $item ) && in_array( $item, $allowed_customer_fields, true ) ) {
                                $safe_val[] = $item;
                            }
                        }
                    } else {
                        $safe_val = [];
                    }
                } elseif ( in_array( $clean_option, ['ogpsr_selected_bank_accounts', 'ogpsr_thermal_meta_keys'], true ) ) {
                    // Strictly whitelist 1D flat arrays of strings/integers, rejecting arbitrary nested arrays
                    if ( is_array( $live_val ) ) {
                        $safe_val = [];
                        foreach ( $live_val as $item ) {
                            if ( is_scalar( $item ) ) {
                                $safe_val[] = sanitize_text_field( (string) $item );
                            }
                        }
                    } else {
                        $safe_val = [];
                    }
                } elseif ( is_array( $live_val ) ) {
                    // Reject any unrecognized nested arrays
                    $safe_val = '';
                } elseif ( in_array( $clean_option, [
                    'ogpsr_business_contact',
                    'ogpsr_business_address',
                    'ogpsr_business_address_2',
                    'ogpsr_footer_text',
                    'ogpsr_terms_text',
                    'ogpsr_thermal_footer_text',
                    'ogpsr_packaging_notes',
                    'ogpsr_custom_fees_data',
                    'ogpsr_staff_roster_data'
                ], true ) ) {
                    $safe_val = ( is_string( $live_val ) ? sanitize_textarea_field( $live_val ) : '' );
                } else {
                    $safe_val = ( is_string( $live_val ) ? sanitize_text_field( $live_val ) : '' );
                }
                if ( is_array( $safe_val ) && in_array( '__ogpsr_empty__', $safe_val, true ) ) {
                    $safe_val = [];
                }
                $live_settings[$clean_option] = $safe_val;
                add_filter( 'pre_option_' . $clean_option, function ( $pre_val ) use($safe_val) {
                    if ( $safe_val === '' || $safe_val === null ) {
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
                return ( $mode === 'code' ? $currency : $symbol );
            },
            999,
            2
        );
        // Validate format against canonical Free Document Registry
        if ( class_exists( 'OG_PSR_Document_Registry' ) && !OG_PSR_Document_Registry::is_supported_format( $format ) ) {
            ob_get_clean();
            wp_send_json_error( [
                'message' => __( 'Invalid preview format.', 'semiauto-invoicing' ),
            ] );
            return;
        }
        // Optimization: Use a static variable to cache the order object across the same request
        // and a transient to cache the Order ID across multiple AJAX calls for this session.
        static $cached_order = null;
        if ( $cached_order !== null ) {
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
                $recent_orders = wc_get_orders( [
                    'limit'   => 1,
                    'orderby' => 'date',
                    'order'   => 'DESC',
                    'return'  => 'ids',
                    'type'    => 'shop_order',
                ] );
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
            if ( $is_demo ) {
                $demo_notice = '<div class="ogpsr-demo-notice">' . esc_html__( 'Showing Demo Data (No orders found)', 'semiauto-invoicing' ) . '</div>';
                $html = str_replace( '</body>', $demo_notice . '</body>', $html );
            }
            ob_get_clean();
            wp_send_json_success( [
                'html' => $html,
            ] );
        } catch ( Throwable $e ) {
            if ( class_exists( 'OG_PSR_Logger' ) ) {
                OG_PSR_Logger::error( 'Preview Rendering Error: ' . $e->getMessage() );
            }
            ob_get_clean();
            wp_send_json_error( [
                'message' => __( 'Preview could not be generated. Please verify your settings and try again.', 'semiauto-invoicing' ),
            ] );
        }
    }

    private function render_template( $format, $order, $width ) {
        $doc_type = 'invoice';
        if ( $format === 'packaging' ) {
            $doc_type = 'packaging_slip';
        } elseif ( $format === 'thermal' ) {
            $doc_type = 'thermal_receipt';
        }
        $page_format = ( in_array( $format, ['a4', 'packaging'], true ) ? 'a4' : $width );
        $doc = [];
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
        include $template_path;
        $content = ob_get_clean();
        // If content already contains <html> (Unified templates),
        // Inject preview data attributes instead of wrapping it again.
        if ( strpos( $content, '<html' ) !== false ) {
            return $this->inject_preview_styles( $content, $format, $width );
        }
        return $this->wrap_in_shell( $content, $format, $width );
    }

    private function inject_preview_styles( $html, $format, $width ) {
        wp_enqueue_style(
            'ogpsr-document-template',
            OG_PSR_URL . 'assets/css/ogpsr-document-template.css',
            [],
            OG_PSR_VERSION
        );
        wp_enqueue_style(
            'ogpsr-preview',
            OG_PSR_URL . 'assets/css/ogpsr-preview.css',
            ['ogpsr-document-template'],
            OG_PSR_VERSION
        );
        global $wp_styles;
        if ( is_object( $wp_styles ) && isset( $wp_styles->done ) && is_array( $wp_styles->done ) ) {
            $wp_styles->done = array_diff( $wp_styles->done, ['ogpsr-document-template', 'ogpsr-preview'] );
            if ( isset( $wp_styles->queue ) && is_array( $wp_styles->queue ) ) {
                $wp_styles->queue = array_unique( array_merge( $wp_styles->queue, ['ogpsr-document-template', 'ogpsr-preview'] ) );
            }
        }
        /**
         * Architectural Note for WordPress.org Reviewers:
         * The preview canvas runs inside an isolated iframe document context (srcdoc), which has its
         * own isolated DOM tree and cannot inherit stylesheets enqueued on the outer admin page.
         * We register the stylesheets via wp_enqueue_style() and serialize the authorized <link> tags
         * into the isolated iframe document head using wp_print_styles().
         */
        ob_start();
        wp_print_styles( ['ogpsr-document-template', 'ogpsr-preview'] );
        $enqueued_tags = ob_get_clean();
        // Append class and data-ogpsr-width attributes without manual style string building
        $html = str_replace( '<html', '<html class="ogpsr-preview-html" data-ogpsr-width="' . esc_attr( $width ) . '"', $html );
        // Use regex to find <body and inject/merge preview classes
        if ( preg_match( '/<body[^>]*class=["\']([^"\']*)["\']/', $html, $matches ) ) {
            // Merge with existing classes
            $new_classes = $matches[1] . ' ogpsr-preview-body ogpsr-format-' . esc_attr( $format );
            $html = str_replace( $matches[0], str_replace( $matches[1], $new_classes, $matches[0] ), $html );
        } else {
            // No class attribute found, add it
            $html = str_replace( '<body', '<body class="ogpsr-preview-body ogpsr-format-' . esc_attr( $format ) . '"', $html );
        }
        if ( strpos( $html, '</head>' ) !== false ) {
            return str_replace( '</head>', $enqueued_tags . '</head>', $html );
        }
        return $enqueued_tags . $html;
    }

    private function wrap_in_shell( $content, $format, $width ) {
        wp_enqueue_style(
            'ogpsr-document-template',
            OG_PSR_URL . 'assets/css/ogpsr-document-template.css',
            [],
            OG_PSR_VERSION
        );
        wp_enqueue_style(
            'ogpsr-preview',
            OG_PSR_URL . 'assets/css/ogpsr-preview.css',
            ['ogpsr-document-template'],
            OG_PSR_VERSION
        );
        global $wp_styles;
        if ( is_object( $wp_styles ) && isset( $wp_styles->done ) && is_array( $wp_styles->done ) ) {
            $wp_styles->done = array_diff( $wp_styles->done, ['ogpsr-document-template', 'ogpsr-preview'] );
            if ( isset( $wp_styles->queue ) && is_array( $wp_styles->queue ) ) {
                $wp_styles->queue = array_unique( array_merge( $wp_styles->queue, ['ogpsr-document-template', 'ogpsr-preview'] ) );
            }
        }
        /**
         * Architectural Note for WordPress.org Reviewers:
         * The preview canvas runs inside an isolated iframe document context (srcdoc), which has its
         * own isolated DOM tree and cannot inherit stylesheets enqueued on the outer admin page.
         * We register the stylesheets via wp_enqueue_style() and serialize the authorized <link> tags
         * into the isolated iframe document head using wp_print_styles().
         */
        ob_start();
        wp_print_styles( ['ogpsr-document-template', 'ogpsr-preview'] );
        $enqueued_tags = ob_get_clean();
        $shell = '<!DOCTYPE html>' . "\n";
        $shell .= '<html class="ogpsr-preview-html" data-ogpsr-width="' . esc_attr( $width ) . '">' . "\n";
        $shell .= '<head>' . "\n";
        $shell .= '    <meta charset="utf-8">' . "\n";
        $shell .= '    ' . $enqueued_tags . "\n";
        $shell .= '</head>' . "\n";
        $shell .= '<body class="ogpsr-preview-body ogpsr-format-' . esc_attr( $format ) . '">' . "\n";
        $shell .= $content . "\n";
        $shell .= '</body>' . "\n";
        $shell .= '</html>';
        return $shell;
    }

}

<?php

/**
 * Universal Document Data Engine Class
 * 
 * Normalizes WooCommerce WC_Order instances into a clean presentation-ready $doc context array
 * for Free Core document formats (Invoices, Packaging Slips, Thermal Receipts).
 * PRO document types, sequential numbering, dual currency, and vector barcodes
 * are strictly delegated via Freemius Double-Guard to dedicated premium classes.
 * 
 * @package SemiAutoInvoicing
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( !class_exists( 'OG_PSR_Document_Data_Engine' ) ) {
    class OG_PSR_Document_Data_Engine {
        /**
         * Build normalized document context array from order.
         * 
         * @param WC_Order|WC_Order_Refund|int $order_or_id
         * @param string                       $requested_doc_type  Optional: 'invoice', 'packaging_slip', 'thermal_receipt'
         * @param string                       $requested_format    Optional: 'a4', '44mm', '57mm', '58mm', '76mm', '80mm'
         * @return array Presentation-ready $doc data array.
         */
        public static function build_document_context( $order_or_id, $requested_doc_type = '', $requested_format = '' ) {
            $order = self::resolve_order_object( $order_or_id );
            if ( !$order ) {
                return self::get_empty_context();
            }
            $order_id = $order->get_id();
            $parent_order = ( method_exists( $order, 'get_parent_id' ) && $order->get_parent_id() > 0 && function_exists( 'wc_get_order' ) ? wc_get_order( $order->get_parent_id() ) : null );
            $billing_source_order = ( $parent_order && $order instanceof WC_Order_Refund ? $parent_order : $order );
            // 1. Determine Free Core Document Type
            $doc_type = sanitize_key( $requested_doc_type );
            if ( empty( $doc_type ) || class_exists( 'OG_PSR_Document_Registry' ) && !OG_PSR_Document_Registry::is_supported_type( $doc_type ) ) {
                $doc_type = 'invoice';
            }
            // 2. Determine Page Format & Paper Width
            $page_format = sanitize_key( $requested_format );
            if ( empty( $page_format ) ) {
                if ( $doc_type === 'thermal_receipt' ) {
                    $paper_width = OG_PSR_Settings::get( 'ogpsr_paper_width', '57' );
                    $page_format = 'thermal_' . $paper_width . 'mm';
                } else {
                    $page_format = 'a4';
                }
            } else {
                if ( $page_format === '210mm' || in_array( $doc_type, ['invoice', 'packaging_slip'], true ) ) {
                    if ( strpos( $page_format, 'mm' ) !== false && $page_format !== '210mm' ) {
                        $page_format = ( strpos( $page_format, 'thermal_' ) === false ? 'thermal_' . $page_format : $page_format );
                    } else {
                        $page_format = 'a4';
                    }
                } elseif ( strpos( $page_format, 'mm' ) !== false && strpos( $page_format, 'thermal_' ) === false ) {
                    $page_format = 'thermal_' . $page_format;
                }
            }
            $is_thermal = $doc_type === 'thermal_receipt' || strpos( $page_format, 'thermal_' ) === 0 && !in_array( $doc_type, ['invoice', 'packaging_slip'], true );
            $paper_width_mm = 80;
            if ( $is_thermal ) {
                if ( preg_match( '/(\\d+)mm/', $page_format, $matches ) ) {
                    $paper_width_mm = (int) $matches[1];
                } else {
                    $paper_width_mm = (int) OG_PSR_Settings::get( 'ogpsr_paper_width', '57' );
                }
            }
            // 3. Resolve Font Family & Styling Customizations
            $raw_font = OG_PSR_Settings::get( 'ogpsr_font_family', 'DejaVu Sans' );
            $font_family = ( class_exists( 'OG_PSR_Document_Registry' ) ? OG_PSR_Document_Registry::sanitize_font_family( $raw_font ) : 'DejaVu Sans' );
            $accent_color = ( $is_thermal ? OG_PSR_Settings::get( 'ogpsr_thermal_accent_color', OG_PSR_Settings::get( 'ogpsr_accent_color', '#333333' ) ) : OG_PSR_Settings::get( 'ogpsr_accent_color', '#1e293b' ) );
            if ( empty( $accent_color ) || !preg_match( '/^#[a-fA-F0-9]{6}$/', $accent_color ) ) {
                $accent_color = ( $is_thermal ? '#333333' : '#1e293b' );
            }
            $font_color = OG_PSR_Settings::get( 'ogpsr_font_color', '#1e293b' );
            if ( empty( $font_color ) || !preg_match( '/^#[a-fA-F0-9]{6}$/', $font_color ) ) {
                $font_color = '#1e293b';
            }
            if ( $doc_type === 'packaging_slip' ) {
                $font_size = (int) OG_PSR_Settings::get( 'ogpsr_font_size', 9 );
                $line_height = (float) OG_PSR_Settings::get( 'ogpsr_line_height', 1.2 );
            } elseif ( $is_thermal ) {
                $font_size = 8;
                $line_height = 1.25;
            } else {
                $font_size = (int) OG_PSR_Settings::get( 'ogpsr_font_size', 9 );
                $line_height = (float) OG_PSR_Settings::get( 'ogpsr_line_height', 1.2 );
            }
            // 4. Resolve Free Document Titles
            $title = '';
            switch ( $doc_type ) {
                case 'packaging_slip':
                    $title = __( 'PACKAGING SLIP', 'semiauto-invoicing' );
                    break;
                case 'thermal_receipt':
                    $title = __( 'RECEIPT', 'semiauto-invoicing' );
                    break;
                case 'invoice':
                default:
                    $title = __( 'TAX INVOICE', 'semiauto-invoicing' );
                    break;
            }
            $document_number = '#' . $order_id;
            $date_created = ( $order->get_date_created() ? $order->get_date_created()->date_i18n( get_option( 'date_format' ) ) : '' );
            // Compute Due Date and Payment Terms (honoring order overrides and A4 settings)
            $due_date = '';
            $show_due_date = OG_PSR_Settings::get( 'ogpsr_a4_show_due_date', 'yes' ) === 'yes';
            $show_payment_terms = OG_PSR_Settings::get( 'ogpsr_a4_show_payment_terms', 'yes' ) === 'yes';
            $payment_terms_days = (int) OG_PSR_Settings::get( 'ogpsr_a4_payment_terms', OG_PSR_Settings::get( 'ogpsr_payment_due_days', 30 ) );
            $payment_terms_fmt = OG_PSR_Settings::get( 'ogpsr_a4_payment_terms_format', 'net' );
            // Formatted Payment Terms string
            $payment_terms_str = '';
            if ( $doc_type === 'invoice' && $show_payment_terms ) {
                $manual_terms = ( method_exists( $order, 'get_meta' ) ? $order->get_meta( '_ogpsr_payment_terms' ) : '' );
                if ( empty( $manual_terms ) && $parent_order && method_exists( $parent_order, 'get_meta' ) ) {
                    $manual_terms = $parent_order->get_meta( '_ogpsr_payment_terms' );
                }
                if ( !empty( $manual_terms ) ) {
                    $payment_terms_str = $manual_terms;
                } else {
                    if ( $payment_terms_fmt === 'days' ) {
                        /* translators: %d: Number of days for payment terms. */
                        $payment_terms_str = sprintf( _n(
                            '%d Day',
                            '%d Days',
                            $payment_terms_days,
                            'semiauto-invoicing'
                        ), $payment_terms_days );
                    } else {
                        /* translators: %d: Number of days for Net payment terms. */
                        $payment_terms_str = sprintf( __( 'Net %d', 'semiauto-invoicing' ), $payment_terms_days );
                    }
                }
            }
            if ( $doc_type === 'invoice' && $show_due_date ) {
                $manual_due_date = ( method_exists( $order, 'get_meta' ) ? $order->get_meta( '_ogpsr_due_date' ) : '' );
                if ( empty( $manual_due_date ) && $parent_order && method_exists( $parent_order, 'get_meta' ) ) {
                    $manual_due_date = $parent_order->get_meta( '_ogpsr_due_date' );
                }
                if ( !empty( $manual_due_date ) ) {
                    $due_date = $manual_due_date;
                } else {
                    $date_src = ( $order->get_date_created() ?: (( $parent_order ? $parent_order->get_date_created() : null )) );
                    if ( $date_src ) {
                        $date_copy = clone $date_src;
                        $due_date = $date_copy->modify( '+' . $payment_terms_days . ' days' )->date_i18n( get_option( 'date_format' ) );
                    }
                }
            }
            $customer_note = ( method_exists( $order, 'get_customer_note' ) ? $order->get_customer_note() : '' );
            if ( empty( $customer_note ) && $parent_order && method_exists( $parent_order, 'get_customer_note' ) ) {
                $customer_note = $parent_order->get_customer_note();
            }
            // 5. Seller (Merchant) Context & Country Resolution
            $raw_store_country = OG_PSR_Settings::get( 'ogpsr_business_country' );
            if ( empty( $raw_store_country ) && function_exists( 'wc_get_base_location' ) ) {
                $base_loc = wc_get_base_location();
                $raw_store_country = $base_loc['country'] ?? '';
            }
            if ( strpos( (string) $raw_store_country, ':' ) !== false ) {
                $raw_store_country = explode( ':', (string) $raw_store_country )[0];
            }
            $raw_store_country = strtoupper( trim( (string) $raw_store_country ) );
            $seller_country = $raw_store_country;
            $seller_state = OG_PSR_Settings::get( 'ogpsr_business_state' );
            if ( function_exists( 'WC' ) && WC()->countries ) {
                $countries = WC()->countries->get_countries();
                if ( !empty( $raw_store_country ) && isset( $countries[$raw_store_country] ) ) {
                    $seller_country = $countries[$raw_store_country];
                }
                if ( !empty( $raw_store_country ) && !empty( $seller_state ) ) {
                    $states = WC()->countries->get_states( $raw_store_country );
                    if ( is_array( $states ) && isset( $states[$seller_state] ) ) {
                        $seller_state = $states[$seller_state];
                    }
                }
            }
            // 5. Seller (Merchant) Context & Country Resolution
            $business_name = trim( (string) OG_PSR_Settings::get( 'ogpsr_business_name' ) );
            if ( empty( $business_name ) ) {
                $business_name = get_bloginfo( 'name' );
            }
            if ( empty( $business_name ) ) {
                $business_name = get_option( 'blogname', '' );
            }
            $business_name = wp_specialchars_decode( (string) $business_name, ENT_QUOTES );
            if ( OG_PSR_Settings::get( 'ogpsr_business_name_format', 'none' ) === 'uppercase' ) {
                $business_name = strtoupper( $business_name );
            }
            $raw_store_country = OG_PSR_Settings::get( 'ogpsr_business_country' );
            if ( empty( $raw_store_country ) && function_exists( 'wc_get_base_location' ) ) {
                $base_loc = wc_get_base_location();
                $raw_store_country = $base_loc['country'] ?? '';
            }
            if ( strpos( (string) $raw_store_country, ':' ) !== false ) {
                $raw_store_country = explode( ':', (string) $raw_store_country )[0];
            }
            $raw_store_country = strtoupper( trim( (string) $raw_store_country ) );
            $seller_country = $raw_store_country;
            $seller_state = OG_PSR_Settings::get( 'ogpsr_business_state' );
            if ( function_exists( 'WC' ) && WC()->countries ) {
                $countries = WC()->countries->get_countries();
                if ( !empty( $raw_store_country ) && isset( $countries[$raw_store_country] ) ) {
                    $seller_country = $countries[$raw_store_country];
                }
                if ( !empty( $raw_store_country ) && !empty( $seller_state ) ) {
                    $states = WC()->countries->get_states( $raw_store_country );
                    if ( is_array( $states ) && isset( $states[$seller_state] ) ) {
                        $seller_state = $states[$seller_state];
                    }
                }
            }
            $seller_contact = OG_PSR_Settings::get( 'ogpsr_business_contact' );
            $seller = [
                'name'         => $business_name,
                'address_1'    => OG_PSR_Settings::get( 'ogpsr_business_address' ),
                'address_2'    => OG_PSR_Settings::get( 'ogpsr_business_address_2' ),
                'city'         => OG_PSR_Settings::get( 'ogpsr_business_city' ),
                'state'        => $seller_state,
                'postcode'     => OG_PSR_Settings::get( 'ogpsr_business_postcode' ),
                'country'      => $seller_country,
                'country_code' => $raw_store_country,
                'contact'      => $seller_contact,
                'email'        => '',
                'phone'        => '',
                'website'      => '',
                'logo_url'     => ( class_exists( 'OG_PSR_PDF_Generator' ) ? OG_PSR_PDF_Generator::get_logo_base64() : '' ),
            ];
            // 6. Enabled Customer Info Fields Filter
            $enabled_fields = OG_PSR_Settings::get( 'ogpsr_customer_info_fields', [
                'first_name',
                'last_name',
                'company',
                'address_1',
                'city',
                'postcode',
                'country',
                'phone',
                'email'
            ] );
            if ( !is_array( $enabled_fields ) ) {
                $enabled_fields = ( !empty( $enabled_fields ) ? (array) $enabled_fields : [
                    'first_name',
                    'last_name',
                    'company',
                    'address_1',
                    'city',
                    'postcode',
                    'country',
                    'phone',
                    'email'
                ] );
            }
            // Buyer Context & Location Parsing (Primary: Billing Order, Fallback: Customer User Profile)
            $customer_user_id = 0;
            if ( $billing_source_order && method_exists( $billing_source_order, 'get_customer_id' ) ) {
                $customer_user_id = (int) $billing_source_order->get_customer_id();
            } elseif ( method_exists( $order, 'get_customer_id' ) ) {
                $customer_user_id = (int) $order->get_customer_id();
            }
            // Billing First Name
            $billing_first_name = '';
            if ( in_array( 'first_name', $enabled_fields, true ) ) {
                if ( $billing_source_order && method_exists( $billing_source_order, 'get_billing_first_name' ) ) {
                    $billing_first_name = $billing_source_order->get_billing_first_name();
                }
                if ( empty( $billing_first_name ) && method_exists( $order, 'get_billing_first_name' ) ) {
                    $billing_first_name = $order->get_billing_first_name();
                }
                if ( empty( $billing_first_name ) && $customer_user_id > 0 ) {
                    $billing_first_name = ( get_user_meta( $customer_user_id, 'billing_first_name', true ) ?: get_user_meta( $customer_user_id, 'first_name', true ) );
                }
            }
            // Billing Last Name
            $billing_last_name = '';
            if ( in_array( 'last_name', $enabled_fields, true ) ) {
                if ( $billing_source_order && method_exists( $billing_source_order, 'get_billing_last_name' ) ) {
                    $billing_last_name = $billing_source_order->get_billing_last_name();
                }
                if ( empty( $billing_last_name ) && method_exists( $order, 'get_billing_last_name' ) ) {
                    $billing_last_name = $order->get_billing_last_name();
                }
                if ( empty( $billing_last_name ) && $customer_user_id > 0 ) {
                    $billing_last_name = ( get_user_meta( $customer_user_id, 'billing_last_name', true ) ?: get_user_meta( $customer_user_id, 'last_name', true ) );
                }
            }
            // Country & State Codes
            $buyer_country_code = '';
            if ( $billing_source_order && method_exists( $billing_source_order, 'get_billing_country' ) ) {
                $buyer_country_code = $billing_source_order->get_billing_country();
            }
            if ( empty( $buyer_country_code ) && method_exists( $order, 'get_billing_country' ) ) {
                $buyer_country_code = $order->get_billing_country();
            }
            if ( empty( $buyer_country_code ) && $customer_user_id > 0 ) {
                $buyer_country_code = get_user_meta( $customer_user_id, 'billing_country', true );
            }
            $buyer_state_code = '';
            if ( $billing_source_order && method_exists( $billing_source_order, 'get_billing_state' ) ) {
                $buyer_state_code = $billing_source_order->get_billing_state();
            }
            if ( empty( $buyer_state_code ) && method_exists( $order, 'get_billing_state' ) ) {
                $buyer_state_code = $order->get_billing_state();
            }
            if ( empty( $buyer_state_code ) && $customer_user_id > 0 ) {
                $buyer_state_code = get_user_meta( $customer_user_id, 'billing_state', true );
            }
            $buyer_country = $buyer_country_code;
            $buyer_state = $buyer_state_code;
            if ( function_exists( 'WC' ) && WC()->countries ) {
                $countries = WC()->countries->get_countries();
                if ( !empty( $buyer_country_code ) && isset( $countries[$buyer_country_code] ) ) {
                    $buyer_country = $countries[$buyer_country_code];
                }
                if ( !empty( $buyer_country_code ) && !empty( $buyer_state_code ) ) {
                    $states = WC()->countries->get_states( $buyer_country_code );
                    if ( is_array( $states ) && isset( $states[$buyer_state_code] ) ) {
                        $buyer_state = $states[$buyer_state_code];
                    }
                }
            }
            // Company Name (Safe Native Getters Only - Zero Direct is_internal_meta_key get_meta Calls)
            $buyer_company = '';
            if ( in_array( 'company', $enabled_fields, true ) ) {
                if ( $billing_source_order && method_exists( $billing_source_order, 'get_billing_company' ) ) {
                    $buyer_company = $billing_source_order->get_billing_company();
                }
                if ( empty( $buyer_company ) && method_exists( $order, 'get_billing_company' ) ) {
                    $buyer_company = $order->get_billing_company();
                }
                if ( empty( $buyer_company ) && $billing_source_order && method_exists( $billing_source_order, 'get_shipping_company' ) ) {
                    $buyer_company = $billing_source_order->get_shipping_company();
                }
                if ( empty( $buyer_company ) && method_exists( $order, 'get_shipping_company' ) ) {
                    $buyer_company = $order->get_shipping_company();
                }
                if ( empty( $buyer_company ) && $customer_user_id > 0 ) {
                    $buyer_company = get_user_meta( $customer_user_id, 'billing_company', true );
                }
            }
            // Address Lines
            $buyer_address_1 = '';
            if ( in_array( 'address_1', $enabled_fields, true ) ) {
                if ( $billing_source_order && method_exists( $billing_source_order, 'get_billing_address_1' ) ) {
                    $buyer_address_1 = $billing_source_order->get_billing_address_1();
                }
                if ( empty( $buyer_address_1 ) && method_exists( $order, 'get_billing_address_1' ) ) {
                    $buyer_address_1 = $order->get_billing_address_1();
                }
                if ( empty( $buyer_address_1 ) && $customer_user_id > 0 ) {
                    $buyer_address_1 = get_user_meta( $customer_user_id, 'billing_address_1', true );
                }
            }
            $buyer_address_2 = '';
            if ( in_array( 'address_1', $enabled_fields, true ) ) {
                if ( $billing_source_order && method_exists( $billing_source_order, 'get_billing_address_2' ) ) {
                    $buyer_address_2 = $billing_source_order->get_billing_address_2();
                }
                if ( empty( $buyer_address_2 ) && method_exists( $order, 'get_billing_address_2' ) ) {
                    $buyer_address_2 = $order->get_billing_address_2();
                }
                if ( empty( $buyer_address_2 ) && $customer_user_id > 0 ) {
                    $buyer_address_2 = get_user_meta( $customer_user_id, 'billing_address_2', true );
                }
            }
            $buyer_city = '';
            if ( in_array( 'city', $enabled_fields, true ) ) {
                if ( $billing_source_order && method_exists( $billing_source_order, 'get_billing_city' ) ) {
                    $buyer_city = $billing_source_order->get_billing_city();
                }
                if ( empty( $buyer_city ) && method_exists( $order, 'get_billing_city' ) ) {
                    $buyer_city = $order->get_billing_city();
                }
                if ( empty( $buyer_city ) && $customer_user_id > 0 ) {
                    $buyer_city = get_user_meta( $customer_user_id, 'billing_city', true );
                }
            }
            $buyer_postcode = '';
            if ( in_array( 'postcode', $enabled_fields, true ) ) {
                if ( $billing_source_order && method_exists( $billing_source_order, 'get_billing_postcode' ) ) {
                    $buyer_postcode = $billing_source_order->get_billing_postcode();
                }
                if ( empty( $buyer_postcode ) && method_exists( $order, 'get_billing_postcode' ) ) {
                    $buyer_postcode = $order->get_billing_postcode();
                }
                if ( empty( $buyer_postcode ) && $customer_user_id > 0 ) {
                    $buyer_postcode = get_user_meta( $customer_user_id, 'billing_postcode', true );
                }
            }
            $buyer_email = '';
            if ( in_array( 'email', $enabled_fields, true ) ) {
                if ( $billing_source_order && method_exists( $billing_source_order, 'get_billing_email' ) ) {
                    $buyer_email = $billing_source_order->get_billing_email();
                }
                if ( empty( $buyer_email ) && method_exists( $order, 'get_billing_email' ) ) {
                    $buyer_email = $order->get_billing_email();
                }
                if ( empty( $buyer_email ) && $customer_user_id > 0 ) {
                    $user_data = get_userdata( $customer_user_id );
                    if ( $user_data ) {
                        $buyer_email = $user_data->user_email;
                    }
                }
            }
            $buyer_phone = '';
            if ( in_array( 'phone', $enabled_fields, true ) ) {
                if ( $billing_source_order && method_exists( $billing_source_order, 'get_billing_phone' ) ) {
                    $buyer_phone = $billing_source_order->get_billing_phone();
                }
                if ( empty( $buyer_phone ) && method_exists( $order, 'get_billing_phone' ) ) {
                    $buyer_phone = $order->get_billing_phone();
                }
                if ( empty( $buyer_phone ) && $customer_user_id > 0 ) {
                    $buyer_phone = get_user_meta( $customer_user_id, 'billing_phone', true );
                }
            }
            $has_location_fields = in_array( 'city', $enabled_fields, true ) || in_array( 'address_1', $enabled_fields, true );
            $buyer = [
                'name'         => trim( $billing_first_name . ' ' . $billing_last_name ),
                'company'      => $buyer_company,
                'address_1'    => $buyer_address_1,
                'address_2'    => $buyer_address_2,
                'city'         => $buyer_city,
                'state'        => ( $has_location_fields ? $buyer_state : '' ),
                'state_code'   => ( $has_location_fields ? $buyer_state_code : '' ),
                'postcode'     => $buyer_postcode,
                'country'      => ( in_array( 'country', $enabled_fields, true ) ? $buyer_country : '' ),
                'country_code' => ( in_array( 'country', $enabled_fields, true ) ? $buyer_country_code : '' ),
                'email'        => $buyer_email,
                'phone'        => $buyer_phone,
            ];
            // Custom Customer Fields
            $custom_fields = [];
            $meta_source_order = ( $billing_source_order && method_exists( $billing_source_order, 'get_meta_data' ) ? $billing_source_order : $order );
            if ( in_array( 'custom_fields', $enabled_fields, true ) && method_exists( $meta_source_order, 'get_meta_data' ) ) {
                foreach ( $meta_source_order->get_meta_data() as $meta ) {
                    if ( !is_protected_meta( $meta->key ) && strpos( $meta->key, '_' ) !== 0 ) {
                        $custom_fields[] = [
                            'label' => str_replace( ['_', '-'], ' ', ucfirst( $meta->key ) ),
                            'value' => ( is_array( $meta->value ) ? implode( ', ', $meta->value ) : (string) $meta->value ),
                        ];
                    }
                }
            }
            // 7. Shipping Context
            $shipping_source_order = ( $order && method_exists( $order, 'has_shipping_address' ) && $order->has_shipping_address() ? $order : $billing_source_order );
            $shipping_first_name = ( in_array( 'first_name', $enabled_fields, true ) && method_exists( $shipping_source_order, 'get_shipping_first_name' ) ? ( $shipping_source_order->get_shipping_first_name() ?: $billing_first_name ) : '' );
            $shipping_last_name = ( in_array( 'last_name', $enabled_fields, true ) && method_exists( $shipping_source_order, 'get_shipping_last_name' ) ? ( $shipping_source_order->get_shipping_last_name() ?: $billing_last_name ) : '' );
            $shipping_company = ( in_array( 'company', $enabled_fields, true ) && method_exists( $shipping_source_order, 'get_shipping_company' ) ? ( $shipping_source_order->get_shipping_company() ?: $buyer['company'] ) : (( in_array( 'company', $enabled_fields, true ) ? $buyer['company'] : '' )) );
            $shipping_address_1 = ( in_array( 'address_1', $enabled_fields, true ) && method_exists( $shipping_source_order, 'get_shipping_address_1' ) ? ( $shipping_source_order->get_shipping_address_1() ?: $buyer['address_1'] ) : '' );
            $shipping_address_2 = ( in_array( 'address_1', $enabled_fields, true ) && method_exists( $shipping_source_order, 'get_shipping_address_2' ) ? ( $shipping_source_order->get_shipping_address_2() ?: $buyer['address_2'] ) : '' );
            $shipping_city = ( in_array( 'city', $enabled_fields, true ) && method_exists( $shipping_source_order, 'get_shipping_city' ) ? ( $shipping_source_order->get_shipping_city() ?: $buyer['city'] ) : '' );
            $shipping_state = ( $has_location_fields && method_exists( $shipping_source_order, 'get_shipping_state' ) ? ( $shipping_source_order->get_shipping_state() ?: $buyer['state'] ) : '' );
            $shipping_postcode = ( in_array( 'postcode', $enabled_fields, true ) && method_exists( $shipping_source_order, 'get_shipping_postcode' ) ? ( $shipping_source_order->get_shipping_postcode() ?: $buyer['postcode'] ) : '' );
            $shipping_country = ( in_array( 'country', $enabled_fields, true ) && method_exists( $shipping_source_order, 'get_shipping_country' ) ? ( $shipping_source_order->get_shipping_country() ?: $buyer['country'] ) : '' );
            $has_shipping = (bool) ($shipping_address_1 || $shipping_city);
            $shipping = [
                'enabled'   => $has_shipping,
                'name'      => trim( $shipping_first_name . ' ' . $shipping_last_name ),
                'company'   => $shipping_company,
                'address_1' => $shipping_address_1,
                'address_2' => $shipping_address_2,
                'city'      => $shipping_city,
                'state'     => $shipping_state,
                'postcode'  => $shipping_postcode,
                'country'   => $shipping_country,
            ];
            // 8. Chwazi Pickup & Delivery Plugin Support & Generic Delivery Metadata
            $chwazi_type = ( method_exists( $order, 'get_meta' ) ? $order->get_meta( 'lpac_dps_order_type' ) : '' );
            if ( empty( $chwazi_type ) && $parent_order && method_exists( $parent_order, 'get_meta' ) ) {
                $chwazi_type = $parent_order->get_meta( 'lpac_dps_order_type' );
            }
            $chwazi_label = '';
            $chwazi_time = '';
            $chwazi_date = '';
            $chwazi_slot = '';
            if ( $chwazi_type === 'delivery' ) {
                $chwazi_label = __( 'Delivery', 'semiauto-invoicing' );
                $d_date = ( method_exists( $order, 'get_meta' ) ? $order->get_meta( 'lpac_dps_delivery_date' ) : '' );
                if ( empty( $d_date ) && $parent_order && method_exists( $parent_order, 'get_meta' ) ) {
                    $d_date = $parent_order->get_meta( 'lpac_dps_delivery_date' );
                }
                $d_time = ( method_exists( $order, 'get_meta' ) ? $order->get_meta( 'lpac_dps_delivery_time' ) : '' );
                if ( empty( $d_time ) && $parent_order && method_exists( $parent_order, 'get_meta' ) ) {
                    $d_time = $parent_order->get_meta( 'lpac_dps_delivery_time' );
                }
                $chwazi_date = $d_date;
                $chwazi_slot = $d_time;
                $chwazi_time = trim( $d_date . ' ' . $d_time );
            } elseif ( $chwazi_type === 'pickup' ) {
                $chwazi_label = __( 'Pickup', 'semiauto-invoicing' );
                $p_date = ( method_exists( $order, 'get_meta' ) ? $order->get_meta( 'lpac_dps_pickup_date' ) : '' );
                if ( empty( $p_date ) && $parent_order && method_exists( $parent_order, 'get_meta' ) ) {
                    $p_date = $parent_order->get_meta( 'lpac_dps_pickup_date' );
                }
                $p_time = ( method_exists( $order, 'get_meta' ) ? $order->get_meta( 'lpac_dps_pickup_time' ) : '' );
                if ( empty( $p_time ) && $parent_order && method_exists( $parent_order, 'get_meta' ) ) {
                    $p_time = $parent_order->get_meta( 'lpac_dps_pickup_time' );
                }
                $chwazi_date = $p_date;
                $chwazi_slot = $p_time;
                $chwazi_time = trim( $p_date . ' ' . $p_time );
            } else {
                // Fallbacks for other popular WooCommerce delivery / pickup plugins (Orderable, Iconic, Tyche, etc.)
                $meta_source = ( method_exists( $order, 'get_meta' ) ? $order : (( $parent_order && method_exists( $parent_order, 'get_meta' ) ? $parent_order : null )) );
                if ( $meta_source ) {
                    $generic_type = $meta_source->get_meta( '_orderable_order_type' );
                    $generic_date = ( (( (( $meta_source->get_meta( '_delivery_date' ) ?: $meta_source->get_meta( '_jckwds_date' ) )) ?: $meta_source->get_meta( 'delivery_date' ) )) ?: $meta_source->get_meta( '_orderable_delivery_date' ) );
                    $generic_time = ( (( (( (( $meta_source->get_meta( '_delivery_time' ) ?: $meta_source->get_meta( '_jckwds_timeslot' ) )) ?: $meta_source->get_meta( 'delivery_time' ) )) ?: $meta_source->get_meta( '_delivery_time_frame' ) )) ?: $meta_source->get_meta( '_orderable_delivery_time' ) );
                    if ( !empty( $generic_date ) || !empty( $generic_time ) ) {
                        $chwazi_type = ( $generic_type === 'pickup' ? 'pickup' : 'delivery' );
                        $chwazi_label = ( $chwazi_type === 'pickup' ? __( 'Pickup', 'semiauto-invoicing' ) : __( 'Delivery', 'semiauto-invoicing' ) );
                        $chwazi_date = $generic_date;
                        $chwazi_slot = $generic_time;
                        $chwazi_time = trim( $generic_date . ' ' . $generic_time );
                    }
                }
                if ( empty( $chwazi_label ) && method_exists( $order, 'get_shipping_methods' ) ) {
                    $shipping_methods = $order->get_shipping_methods();
                    if ( !empty( $shipping_methods ) ) {
                        foreach ( $shipping_methods as $sm ) {
                            $chwazi_label = $sm->get_name();
                            break;
                        }
                    }
                }
            }
            $delivery_pickup = [
                'has_info'  => !empty( $chwazi_label ),
                'type'      => $chwazi_type,
                'label'     => $chwazi_label,
                'date'      => $chwazi_date,
                'time_slot' => $chwazi_slot,
                'time'      => $chwazi_time,
            ];
            // 8. Line Items Context
            $line_items = [];
            if ( method_exists( $order, 'get_items' ) ) {
                foreach ( $order->get_items() as $item_id => $item ) {
                    if ( !$item instanceof WC_Order_Item_Product && empty( $order->is_mock ) ) {
                        continue;
                    }
                    $product = ( method_exists( $item, 'get_product' ) ? $item->get_product() : null );
                    $qty = (int) (( method_exists( $item, 'get_quantity' ) ? $item->get_quantity() : 1 ));
                    $total = (float) (( method_exists( $item, 'get_total' ) ? $item->get_total() : 0 ));
                    $subtotal = (float) (( method_exists( $item, 'get_subtotal' ) ? $item->get_subtotal() : 0 ));
                    $tax = (float) (( method_exists( $item, 'get_total_tax' ) ? $item->get_total_tax() : 0 ));
                    $unit_price = ( $qty > 0 ? $subtotal / $qty : 0 );
                    $meta_strings = [];
                    if ( method_exists( $item, 'get_formatted_meta_data' ) ) {
                        foreach ( $item->get_formatted_meta_data( '_' ) as $meta_id => $meta ) {
                            $meta_strings[] = esc_html( $meta->display_key ) . ': ' . wp_strip_all_tags( $meta->display_value );
                        }
                    }
                    $description = '';
                    $ean_gtin = '';
                    if ( $product ) {
                        $description = ( method_exists( $product, 'get_short_description' ) ? $product->get_short_description() : '' );
                        if ( empty( $description ) && method_exists( $product, 'get_description' ) ) {
                            $description = $product->get_description();
                        }
                        if ( method_exists( $product, 'get_meta' ) ) {
                            $ean_gtin = ( $product->get_meta( '_ean' ) ?: (( $product->get_meta( '_gtin' ) ?: (( $product->get_meta( '_barcode' ) ?: $product->get_meta( '_wpm_gtin' ) )) )) );
                        }
                        $sku = ( method_exists( $product, 'get_sku' ) ? $product->get_sku() : '' );
                        if ( empty( $ean_gtin ) && !empty( $sku ) && ctype_digit( (string) $sku ) && (strlen( (string) $sku ) === 13 || strlen( (string) $sku ) === 14) ) {
                            $ean_gtin = $sku;
                        }
                    }
                    $addons = ( class_exists( 'OG_PSR_Addon_Parser' ) ? OG_PSR_Addon_Parser::get_addons( $item ) : [] );
                    $line_items[] = [
                        'id'          => $item_id,
                        'name'        => $item->get_name(),
                        'sku'         => ( $product ? $product->get_sku() : '' ),
                        'ean'         => $ean_gtin,
                        'description' => $description,
                        'addons'      => $addons,
                        'qty'         => $qty,
                        'unit_price'  => wc_price( $unit_price, [
                            'currency' => $order->get_currency(),
                        ] ),
                        'subtotal'    => wc_price( $subtotal, [
                            'currency' => $order->get_currency(),
                        ] ),
                        'tax'         => wc_price( $tax, [
                            'currency' => $order->get_currency(),
                        ] ),
                        'total'       => wc_price( $total + $tax, [
                            'currency' => $order->get_currency(),
                        ] ),
                        'meta'        => implode( ' | ', $meta_strings ),
                    ];
                }
            }
            // 9. Financial Summary Context & WooCommerce Order Fee Line Items
            $currency = ( method_exists( $order, 'get_currency' ) ? $order->get_currency() : get_woocommerce_currency() );
            $coupons = ( method_exists( $order, 'get_coupon_codes' ) ? $order->get_coupon_codes() : [] );
            $show_coupon_setting = ( $is_thermal ? 'ogpsr_thermal_show_coupon_codes' : 'ogpsr_a4_show_coupon_codes' );
            $show_coupons = OG_PSR_Settings::get( $show_coupon_setting, 'yes' ) === 'yes';
            $discount_label = __( 'Discount', 'semiauto-invoicing' );
            if ( !empty( $coupons ) && $show_coupons ) {
                $discount_label .= ' (' . implode( ', ', $coupons ) . ')';
            }
            $fees = [];
            $fees_total = 0.0;
            if ( method_exists( $order, 'get_fees' ) ) {
                foreach ( $order->get_fees() as $fee_id => $fee_item ) {
                    $fee_amount = (float) $fee_item->get_total();
                    $fee_tax = (float) $fee_item->get_total_tax();
                    $fees_total += $fee_amount;
                    $fees[] = [
                        'id'        => $fee_id,
                        'name'      => $fee_item->get_name(),
                        'total'     => wc_price( $fee_amount, [
                            'currency' => $currency,
                        ] ),
                        'raw_total' => $fee_amount,
                        'tax'       => wc_price( $fee_tax, [
                            'currency' => $currency,
                        ] ),
                        'raw_tax'   => $fee_tax,
                    ];
                }
            }
            // Fetch WooCommerce Native Order Item Totals (Order Details & Email Exact Parity)
            $raw_item_totals = ( method_exists( $order, 'get_order_item_totals' ) ? $order->get_order_item_totals() : [] );
            $order_item_totals = [];
            if ( !empty( $raw_item_totals ) && is_array( $raw_item_totals ) ) {
                foreach ( $raw_item_totals as $key => $total_row ) {
                    if ( $key === 'payment_method' ) {
                        continue;
                        // Payment method is presented in metadata / footer blocks
                    }
                    $label = $total_row['label'] ?? '';
                    if ( $key === 'discount' && !empty( $coupons ) ) {
                        $show_coupons = ( $is_thermal ? OG_PSR_Settings::get( 'ogpsr_thermal_show_coupon_codes', 'yes' ) === 'yes' : OG_PSR_Settings::get( 'ogpsr_a4_show_coupon_codes', 'yes' ) === 'yes' );
                        if ( $show_coupons ) {
                            $label = __( 'Discount', 'semiauto-invoicing' ) . ' (' . implode( ', ', $coupons ) . '):';
                        } else {
                            $label = __( 'Discount:', 'semiauto-invoicing' );
                        }
                    }
                    $order_item_totals[$key] = [
                        'key'   => $key,
                        'label' => $label,
                        'value' => $total_row['value'] ?? '',
                    ];
                }
            }
            $raw_subtotal = (float) (( method_exists( $order, 'get_subtotal' ) ? $order->get_subtotal() : 0 ));
            $raw_tax_total = (float) (( method_exists( $order, 'get_total_tax' ) ? $order->get_total_tax() : 0 ));
            $raw_grand_total = (float) (( method_exists( $order, 'get_total' ) ? $order->get_total() : 0 ));
            $raw_discount = (float) (( method_exists( $order, 'get_discount_total' ) ? $order->get_discount_total() : 0 ));
            $raw_shipping = (float) (( method_exists( $order, 'get_shipping_total' ) ? $order->get_shipping_total() : 0 ));
            $totals_summary = [
                'subtotal'          => wc_price( $raw_subtotal, [
                    'currency' => $currency,
                ] ),
                'raw_subtotal'      => $raw_subtotal,
                'discount'          => wc_price( $raw_discount, [
                    'currency' => $currency,
                ] ),
                'raw_discount'      => $raw_discount,
                'discount_label'    => $discount_label,
                'has_discount'      => $raw_discount > 0,
                'shipping'          => wc_price( $raw_shipping, [
                    'currency' => $currency,
                ] ),
                'raw_shipping'      => $raw_shipping,
                'has_shipping'      => $raw_shipping > 0,
                'fees'              => $fees,
                'fees_total'        => wc_price( $fees_total, [
                    'currency' => $currency,
                ] ),
                'raw_fees_total'    => $fees_total,
                'has_fees'          => !empty( $fees ),
                'tax_total'         => wc_price( $raw_tax_total, [
                    'currency' => $currency,
                ] ),
                'raw_tax_total'     => $raw_tax_total,
                'has_tax'           => $raw_tax_total > 0,
                'grand_total'       => wc_price( $raw_grand_total, [
                    'currency' => $currency,
                ] ),
                'raw_grand_total'   => $raw_grand_total,
                'currency'          => $currency,
                'order_item_totals' => $order_item_totals,
            ];
            // 9b. BACS Bank Details Context
            $payment_method = ( method_exists( $order, 'get_payment_method' ) ? $order->get_payment_method() : '' );
            $show_bank_details = OG_PSR_Settings::get( 'ogpsr_show_bank_details', 'no' );
            $force_bank_details = OG_PSR_Settings::get( 'ogpsr_force_bank_details', 'no' );
            $selected_bank_accounts = OG_PSR_Settings::get( 'ogpsr_selected_bank_accounts', [] );
            if ( !is_array( $selected_bank_accounts ) ) {
                $selected_bank_accounts = ( !empty( $selected_bank_accounts ) ? (array) $selected_bank_accounts : [] );
            }
            $bank_accounts = [];
            if ( $show_bank_details === 'yes' && ($payment_method === 'bacs' || $force_bank_details === 'yes' || !empty( $order->is_mock )) ) {
                $all_bacs = get_option( 'woocommerce_bacs_accounts', [] );
                if ( empty( $selected_bank_accounts ) && !empty( $all_bacs ) && is_array( $all_bacs ) ) {
                    $selected_bank_accounts = array_map( function ( $k ) {
                        return 'bacs_' . $k;
                    }, array_keys( $all_bacs ) );
                }
                if ( !empty( $all_bacs ) && is_array( $all_bacs ) ) {
                    foreach ( $all_bacs as $idx => $acc ) {
                        if ( is_array( $selected_bank_accounts ) && in_array( 'bacs_' . $idx, $selected_bank_accounts ) ) {
                            $bank_accounts[] = [
                                'bank_name'      => ( !empty( $acc['bank_name'] ) ? $acc['bank_name'] : '' ),
                                'account_name'   => ( !empty( $acc['account_name'] ) ? $acc['account_name'] : '' ),
                                'account_number' => ( !empty( $acc['account_number'] ) ? $acc['account_number'] : '' ),
                                'sort_code'      => ( !empty( $acc['sort_code'] ) ? $acc['sort_code'] : '' ),
                                'iban'           => ( !empty( $acc['iban'] ) ? $acc['iban'] : '' ),
                                'bic'            => ( !empty( $acc['bic'] ) ? $acc['bic'] : '' ),
                            ];
                        }
                    }
                }
                if ( empty( $bank_accounts ) && method_exists( $order, 'get_bacs_accounts' ) ) {
                    $bank_accounts = $order->get_bacs_accounts();
                }
            }
            // 10. Multi-Rate Tax Breakdown
            $tax_breakdown = [];
            if ( method_exists( $order, 'get_tax_totals' ) ) {
                foreach ( $order->get_tax_totals() as $code => $tax_obj ) {
                    $tax_breakdown[] = [
                        'label'  => $tax_obj->label,
                        'amount' => wc_price( (float) $tax_obj->amount, [
                            'currency' => $currency,
                        ] ),
                    ];
                }
            }
            // 11. Load Master CSS Stylesheet
            $css_file_path = OG_PSR_PATH . 'assets/css/ogpsr-document-template.css';
            $compiled_css = '';
            global $wp_filesystem;
            if ( empty( $wp_filesystem ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }
            if ( $wp_filesystem && $wp_filesystem->exists( $css_file_path ) ) {
                $compiled_css = (string) $wp_filesystem->get_contents( $css_file_path );
            }
            // Build Base View-Model Context
            $doc = [
                'order'                => $order,
                'order_id'             => $order_id,
                'is_refund'            => false,
                'doc_type'             => $doc_type,
                'page_format'          => $page_format,
                'paper_width_mm'       => $paper_width_mm,
                'is_thermal'           => $is_thermal,
                'title'                => $title,
                'document_number'      => $document_number,
                'date_created'         => $date_created,
                'due_date'             => $due_date,
                'show_due_date'        => $show_due_date,
                'show_payment_terms'   => $doc_type === 'invoice' && $show_payment_terms,
                'payment_terms'        => $payment_terms_str,
                'payment_terms_days'   => $payment_terms_days,
                'customer_note'        => $customer_note,
                'font_family'          => $font_family,
                'brand_color'          => $accent_color,
                'accent_color'         => $accent_color,
                'font_color'           => $font_color,
                'font_size'            => $font_size,
                'line_height'          => $line_height,
                'seller'               => $seller,
                'buyer'                => $buyer,
                'custom_fields'        => $custom_fields,
                'shipping'             => $shipping,
                'delivery_pickup'      => $delivery_pickup,
                'line_items'           => $line_items,
                'totals'               => $totals_summary,
                'order_item_totals'    => $order_item_totals,
                'tax_breakdown'        => $tax_breakdown,
                'payment_methods'      => OG_PSR_Settings::get_active_payment_methods_titles(),
                'show_payment_methods' => OG_PSR_Settings::get( 'ogpsr_show_payment_methods', 'no' ),
                'payment_method_title' => ( method_exists( $order, 'get_payment_method_title' ) ? $order->get_payment_method_title() : '' ),
                'footer_text'          => OG_PSR_Settings::get( 'ogpsr_footer_text', __( 'Thank you for your business!', 'semiauto-invoicing' ) ),
                'header_text'          => OG_PSR_Settings::get( 'ogpsr_header_text', '' ),
                'base_rules'           => $compiled_css,
                'show_tax_inclusion'   => $is_thermal && OG_PSR_Settings::get( 'ogpsr_show_tax_inclusion_line', 'yes' ) === 'yes',
                'show_sku'             => OG_PSR_Settings::get( 'ogpsr_a4_show_product_sku', 'no' ) === 'yes',
                'show_ean'             => OG_PSR_Settings::get( 'ogpsr_a4_show_product_ean', 'no' ) === 'yes',
                'show_product_meta'    => true,
                'terms_html'           => ( $is_thermal ? OG_PSR_Settings::get( 'ogpsr_thermal_terms_and_conditions', '' ) : OG_PSR_Settings::get( 'ogpsr_a4_terms_and_conditions', '' ) ),
                'bank_details'         => $bank_accounts,
            ];
            return apply_filters( 'ogpsr_universal_document_context', $doc, $order );
        }

        /**
         * Resolves WC_Order object safely.
         * 
         * @param mixed $order_or_id
         * @return WC_Order|WC_Order_Refund|false
         */
        private static function resolve_order_object( $order_or_id ) {
            if ( $order_or_id instanceof WC_Abstract_Order ) {
                return $order_or_id;
            }
            if ( is_object( $order_or_id ) && !empty( $order_or_id->is_mock ) ) {
                return $order_or_id;
            }
            if ( is_numeric( $order_or_id ) && $order_or_id > 0 && function_exists( 'wc_get_order' ) ) {
                return wc_get_order( $order_or_id );
            }
            return false;
        }

        /**
         * Empty context fallback.
         * 
         * @return array
         */
        private static function get_empty_context() {
            return [
                'doc_type'             => 'invoice',
                'page_format'          => 'a4',
                'is_thermal'           => false,
                'title'                => __( 'TAX INVOICE', 'semiauto-invoicing' ),
                'document_number'      => '#0',
                'date_created'         => '',
                'due_date'             => '',
                'customer_note'        => '',
                'font_family'          => 'DejaVu Sans',
                'brand_color'          => '#1e293b',
                'seller'               => [],
                'buyer'                => [],
                'shipping'             => [
                    'enabled' => false,
                ],
                'line_items'           => [],
                'totals'               => [
                    'subtotal'     => 0,
                    'grand_total'  => 0,
                    'currency'     => 'EUR',
                    'has_discount' => false,
                    'has_shipping' => false,
                    'has_tax'      => false,
                ],
                'tax_breakdown'        => [],
                'payment_methods'      => [],
                'show_payment_methods' => 'no',
                'footer_text'          => '',
                'header_text'          => '',
                'base_rules'           => '',
                'terms_html'           => '',
            ];
        }

    }

}
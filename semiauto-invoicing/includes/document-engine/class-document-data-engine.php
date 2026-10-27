<?php

/**
 * Universal Document Data Engine Class
 * 
 * Normalizes WooCommerce WC_Order instances into a clean presentation-ready $doc context array
 * for document formats (Invoices, Packaging Slips, Thermal Receipts).
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
                if ( 'thermal_receipt' === $doc_type ) {
                    $paper_width = OG_PSR_Settings::get( 'ogpsr_paper_width', '57' );
                    $page_format = 'thermal_' . $paper_width . 'mm';
                } else {
                    $page_format = 'a4';
                }
            } elseif ( '210mm' === $page_format || in_array( $doc_type, array('invoice', 'packaging_slip'), true ) ) {
                if ( false !== strpos( $page_format, 'mm' ) && '210mm' !== $page_format ) {
                    $page_format = ( false === strpos( $page_format, 'thermal_' ) ? 'thermal_' . $page_format : $page_format );
                } else {
                    $page_format = 'a4';
                }
            } elseif ( false !== strpos( $page_format, 'mm' ) && false === strpos( $page_format, 'thermal_' ) ) {
                $page_format = 'thermal_' . $page_format;
            }
            $is_thermal = 'thermal_receipt' === $doc_type || 0 === strpos( $page_format, 'thermal_' ) && !in_array( $doc_type, array('invoice', 'packaging_slip'), true );
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
            if ( 'packaging_slip' === $doc_type ) {
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
            $show_due_date = 'yes' === OG_PSR_Settings::get( 'ogpsr_a4_show_due_date', 'yes' );
            $show_payment_terms = 'yes' === OG_PSR_Settings::get( 'ogpsr_a4_show_payment_terms', 'yes' );
            $payment_terms_days = (int) OG_PSR_Settings::get( 'ogpsr_a4_payment_terms', OG_PSR_Settings::get( 'ogpsr_payment_due_days', 30 ) );
            $payment_terms_fmt = OG_PSR_Settings::get( 'ogpsr_a4_payment_terms_format', 'net' );
            // Formatted Payment Terms string
            $payment_terms_str = '';
            if ( 'invoice' === $doc_type && $show_payment_terms ) {
                $manual_terms = ( method_exists( $order, 'get_meta' ) ? $order->get_meta( '_ogpsr_payment_terms' ) : '' );
                if ( empty( $manual_terms ) && $parent_order && method_exists( $parent_order, 'get_meta' ) ) {
                    $manual_terms = $parent_order->get_meta( '_ogpsr_payment_terms' );
                }
                if ( !empty( $manual_terms ) ) {
                    $payment_terms_str = $manual_terms;
                } elseif ( 'days' === $payment_terms_fmt ) {
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
            if ( 'invoice' === $doc_type && $show_due_date ) {
                $manual_due_date = ( method_exists( $order, 'get_meta' ) ? $order->get_meta( '_ogpsr_due_date' ) : '' );
                if ( empty( $manual_due_date ) && $parent_order && method_exists( $parent_order, 'get_meta' ) ) {
                    $manual_due_date = $parent_order->get_meta( '_ogpsr_due_date' );
                }
                if ( !empty( $manual_due_date ) ) {
                    $due_date = $manual_due_date;
                } else {
                    $date_src = ( $order->get_date_created() ? $order->get_date_created() : (( $parent_order ? $parent_order->get_date_created() : null )) );
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
            if ( false !== strpos( (string) $raw_store_country, ':' ) ) {
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
            if ( 'uppercase' === OG_PSR_Settings::get( 'ogpsr_business_name_format', 'none' ) ) {
                $business_name = strtoupper( $business_name );
            }
            $raw_store_country = OG_PSR_Settings::get( 'ogpsr_business_country' );
            if ( empty( $raw_store_country ) && function_exists( 'wc_get_base_location' ) ) {
                $base_loc = wc_get_base_location();
                $raw_store_country = $base_loc['country'] ?? '';
            }
            if ( false !== strpos( (string) $raw_store_country, ':' ) ) {
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
            $seller = array(
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
            );
            // 6. Enabled Customer Info Fields Filter
            $enabled_fields = OG_PSR_Settings::get( 'ogpsr_customer_info_fields', array(
                'first_name',
                'last_name',
                'company',
                'address_1',
                'city',
                'postcode',
                'country',
                'phone',
                'email'
            ) );
            if ( !is_array( $enabled_fields ) ) {
                $enabled_fields = ( !empty( $enabled_fields ) ? (array) $enabled_fields : array(
                    'first_name',
                    'last_name',
                    'company',
                    'address_1',
                    'city',
                    'postcode',
                    'country',
                    'phone',
                    'email'
                ) );
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
                    $billing_first_name = ( get_user_meta( $customer_user_id, 'billing_first_name', true ) ? get_user_meta( $customer_user_id, 'billing_first_name', true ) : get_user_meta( $customer_user_id, 'first_name', true ) );
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
                    $billing_last_name = ( get_user_meta( $customer_user_id, 'billing_last_name', true ) ? get_user_meta( $customer_user_id, 'billing_last_name', true ) : get_user_meta( $customer_user_id, 'last_name', true ) );
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
            $buyer = array(
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
            );
            // Custom Customer Fields
            $custom_fields = array();
            $meta_source_order = ( $billing_source_order && method_exists( $billing_source_order, 'get_meta_data' ) ? $billing_source_order : $order );
            if ( in_array( 'custom_fields', $enabled_fields, true ) && method_exists( $meta_source_order, 'get_meta_data' ) ) {
                foreach ( $meta_source_order->get_meta_data() as $meta ) {
                    if ( !is_protected_meta( $meta->key ) && 0 !== strpos( $meta->key, '_' ) ) {
                        $custom_fields[] = array(
                            'label' => str_replace( array('_', '-'), ' ', ucfirst( $meta->key ) ),
                            'value' => ( is_array( $meta->value ) ? implode( ', ', $meta->value ) : (string) $meta->value ),
                        );
                    }
                }
            }
            // 7. Shipping Context
            $shipping_source_order = ( $order && method_exists( $order, 'has_shipping_address' ) && $order->has_shipping_address() ? $order : $billing_source_order );
            $shipping_first_name = ( in_array( 'first_name', $enabled_fields, true ) && method_exists( $shipping_source_order, 'get_shipping_first_name' ) ? ( $shipping_source_order->get_shipping_first_name() ? $shipping_source_order->get_shipping_first_name() : $billing_first_name ) : '' );
            $shipping_last_name = ( in_array( 'last_name', $enabled_fields, true ) && method_exists( $shipping_source_order, 'get_shipping_last_name' ) ? ( $shipping_source_order->get_shipping_last_name() ? $shipping_source_order->get_shipping_last_name() : $billing_last_name ) : '' );
            $shipping_company = ( in_array( 'company', $enabled_fields, true ) && method_exists( $shipping_source_order, 'get_shipping_company' ) ? ( $shipping_source_order->get_shipping_company() ? $shipping_source_order->get_shipping_company() : $buyer['company'] ) : (( in_array( 'company', $enabled_fields, true ) ? $buyer['company'] : '' )) );
            $shipping_address_1 = ( in_array( 'address_1', $enabled_fields, true ) && method_exists( $shipping_source_order, 'get_shipping_address_1' ) ? ( $shipping_source_order->get_shipping_address_1() ? $shipping_source_order->get_shipping_address_1() : $buyer['address_1'] ) : '' );
            $shipping_address_2 = ( in_array( 'address_1', $enabled_fields, true ) && method_exists( $shipping_source_order, 'get_shipping_address_2' ) ? ( $shipping_source_order->get_shipping_address_2() ? $shipping_source_order->get_shipping_address_2() : $buyer['address_2'] ) : '' );
            $shipping_city = ( in_array( 'city', $enabled_fields, true ) && method_exists( $shipping_source_order, 'get_shipping_city' ) ? ( $shipping_source_order->get_shipping_city() ? $shipping_source_order->get_shipping_city() : $buyer['city'] ) : '' );
            $shipping_state = ( $has_location_fields && method_exists( $shipping_source_order, 'get_shipping_state' ) ? ( $shipping_source_order->get_shipping_state() ? $shipping_source_order->get_shipping_state() : $buyer['state'] ) : '' );
            $shipping_postcode = ( in_array( 'postcode', $enabled_fields, true ) && method_exists( $shipping_source_order, 'get_shipping_postcode' ) ? ( $shipping_source_order->get_shipping_postcode() ? $shipping_source_order->get_shipping_postcode() : $buyer['postcode'] ) : '' );
            $shipping_country = ( in_array( 'country', $enabled_fields, true ) && method_exists( $shipping_source_order, 'get_shipping_country' ) ? ( $shipping_source_order->get_shipping_country() ? $shipping_source_order->get_shipping_country() : $buyer['country'] ) : '' );
            // Shipping country and state print by name, like the billing address (the billing fallbacks are already names).
            if ( function_exists( 'WC' ) && WC()->countries ) {
                $shipping_country_code = (string) $shipping_country;
                $shipping_countries = WC()->countries->get_countries();
                if ( '' !== $shipping_country_code && isset( $shipping_countries[$shipping_country_code] ) ) {
                    $shipping_country = $shipping_countries[$shipping_country_code];
                    $shipping_states = WC()->countries->get_states( $shipping_country_code );
                    if ( '' !== (string) $shipping_state && is_array( $shipping_states ) && isset( $shipping_states[$shipping_state] ) ) {
                        $shipping_state = $shipping_states[$shipping_state];
                    }
                }
            }
            $has_shipping = (bool) ($shipping_address_1 || $shipping_city);
            $shipping = array(
                'enabled'   => $has_shipping,
                'name'      => trim( $shipping_first_name . ' ' . $shipping_last_name ),
                'company'   => $shipping_company,
                'address_1' => $shipping_address_1,
                'address_2' => $shipping_address_2,
                'city'      => $shipping_city,
                'state'     => $shipping_state,
                'postcode'  => $shipping_postcode,
                'country'   => $shipping_country,
            );
            // 8. Chwazi Pickup & Delivery Plugin Support & Generic Delivery Metadata
            $chwazi_type = ( method_exists( $order, 'get_meta' ) ? $order->get_meta( 'lpac_dps_order_type' ) : '' );
            if ( empty( $chwazi_type ) && $parent_order && method_exists( $parent_order, 'get_meta' ) ) {
                $chwazi_type = $parent_order->get_meta( 'lpac_dps_order_type' );
            }
            $chwazi_label = '';
            $chwazi_time = '';
            $chwazi_date = '';
            $chwazi_slot = '';
            if ( 'delivery' === $chwazi_type ) {
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
            } elseif ( 'pickup' === $chwazi_type ) {
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
                    $generic_date = ( $meta_source->get_meta( '_delivery_date' ) ? $meta_source->get_meta( '_delivery_date' ) : (( $meta_source->get_meta( '_jckwds_date' ) ? $meta_source->get_meta( '_jckwds_date' ) : (( $meta_source->get_meta( 'delivery_date' ) ? $meta_source->get_meta( 'delivery_date' ) : $meta_source->get_meta( '_orderable_delivery_date' ) )) )) );
                    $generic_time = ( $meta_source->get_meta( '_delivery_time' ) ? $meta_source->get_meta( '_delivery_time' ) : (( $meta_source->get_meta( '_jckwds_timeslot' ) ? $meta_source->get_meta( '_jckwds_timeslot' ) : (( $meta_source->get_meta( 'delivery_time' ) ? $meta_source->get_meta( 'delivery_time' ) : (( $meta_source->get_meta( '_delivery_time_frame' ) ? $meta_source->get_meta( '_delivery_time_frame' ) : $meta_source->get_meta( '_orderable_delivery_time' ) )) )) )) );
                    if ( empty( $generic_date ) ) {
                        // Shared resolver (Tyche, Orderable, CodeRockz pickup, Iconic Ymd, generic delivery/pickup date meta).
                        $resolved_delivery = self::resolve_delivery_date( $order );
                        if ( '' !== $resolved_delivery['date'] ) {
                            $generic_date = ( function_exists( 'mysql2date' ) ? mysql2date( get_option( 'date_format' ), $resolved_delivery['date'] ) : $resolved_delivery['date'] );
                            if ( 'pickup' === $resolved_delivery['type'] ) {
                                $generic_type = 'pickup';
                            }
                        }
                    }
                    if ( !empty( $generic_date ) || !empty( $generic_time ) ) {
                        $chwazi_type = ( 'pickup' === $generic_type ? 'pickup' : 'delivery' );
                        $chwazi_label = ( 'pickup' === $chwazi_type ? __( 'Pickup', 'semiauto-invoicing' ) : __( 'Delivery', 'semiauto-invoicing' ) );
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
            $delivery_pickup = array(
                'has_info'  => !empty( $chwazi_label ),
                'type'      => $chwazi_type,
                'label'     => $chwazi_label,
                'date'      => $chwazi_date,
                'time_slot' => $chwazi_slot,
                'time'      => $chwazi_time,
            );
            // 8. Line Items Context
            $line_items = array();
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
                    $meta_strings = array();
                    if ( method_exists( $item, 'get_formatted_meta_data' ) ) {
                        foreach ( $item->get_formatted_meta_data( '_' ) as $meta_id => $meta ) {
                            $meta_key = ( isset( $meta->key ) ? (string) $meta->key : '' );
                            // YITH selections are rendered once through OG_PSR_Addon_Parser,
                            // which uses YITH's saved display labels and values where available.
                            if ( class_exists( 'OG_PSR_Addon_Parser' ) && OG_PSR_Addon_Parser::is_yith_addon_meta_key( $meta_key ) ) {
                                continue;
                            }
                            $meta_value = wp_strip_all_tags( $meta->display_value );
                            $meta_strings[] = esc_html( $meta->display_key ) . ': ' . $meta_value;
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
                            $ean_gtin = ( $product->get_meta( '_ean' ) ? $product->get_meta( '_ean' ) : (( $product->get_meta( '_gtin' ) ? $product->get_meta( '_gtin' ) : (( $product->get_meta( '_barcode' ) ? $product->get_meta( '_barcode' ) : $product->get_meta( '_wpm_gtin' ) )) )) );
                        }
                        $sku = ( method_exists( $product, 'get_sku' ) ? $product->get_sku() : '' );
                        if ( empty( $ean_gtin ) && !empty( $sku ) && ctype_digit( (string) $sku ) && (13 === strlen( (string) $sku ) || 14 === strlen( (string) $sku )) ) {
                            $ean_gtin = $sku;
                        }
                    }
                    $addons = ( class_exists( 'OG_PSR_Addon_Parser' ) ? OG_PSR_Addon_Parser::get_addons( $item ) : array() );
                    $line_items[] = array(
                        'id'          => $item_id,
                        'name'        => $item->get_name(),
                        'sku'         => ( $product ? $product->get_sku() : '' ),
                        'ean'         => $ean_gtin,
                        'description' => $description,
                        'addons'      => $addons,
                        'qty'         => $qty,
                        'unit_price'  => wc_price( $unit_price, array(
                            'currency' => $order->get_currency(),
                        ) ),
                        'subtotal'    => wc_price( $subtotal, array(
                            'currency' => $order->get_currency(),
                        ) ),
                        'tax'         => wc_price( $tax, array(
                            'currency' => $order->get_currency(),
                        ) ),
                        'total'       => wc_price( $total + $tax, array(
                            'currency' => $order->get_currency(),
                        ) ),
                        'meta'        => implode( ' | ', $meta_strings ),
                    );
                }
            }
            // 9. Financial Summary Context & WooCommerce Order Fee Line Items
            $currency = ( method_exists( $order, 'get_currency' ) ? $order->get_currency() : get_woocommerce_currency() );
            $coupons = ( method_exists( $order, 'get_coupon_codes' ) ? $order->get_coupon_codes() : array() );
            $show_coupon_setting = ( $is_thermal ? 'ogpsr_thermal_show_coupon_codes' : 'ogpsr_a4_show_coupon_codes' );
            $show_coupons = 'yes' === OG_PSR_Settings::get( $show_coupon_setting, 'yes' );
            $discount_label = __( 'Discount', 'semiauto-invoicing' );
            if ( !empty( $coupons ) && $show_coupons ) {
                $discount_label .= ' (' . implode( ', ', $coupons ) . ')';
            }
            $fees = array();
            $fees_total = 0.0;
            if ( method_exists( $order, 'get_fees' ) ) {
                foreach ( $order->get_fees() as $fee_id => $fee_item ) {
                    $fee_amount = (float) $fee_item->get_total();
                    $fee_tax = (float) $fee_item->get_total_tax();
                    $fees_total += $fee_amount;
                    $fees[] = array(
                        'id'        => $fee_id,
                        'name'      => $fee_item->get_name(),
                        'total'     => wc_price( $fee_amount, array(
                            'currency' => $currency,
                        ) ),
                        'raw_total' => $fee_amount,
                        'tax'       => wc_price( $fee_tax, array(
                            'currency' => $currency,
                        ) ),
                        'raw_tax'   => $fee_tax,
                    );
                }
            }
            // Fetch WooCommerce Native Order Item Totals (Order Details & Email Exact Parity)
            $raw_item_totals = ( method_exists( $order, 'get_order_item_totals' ) ? $order->get_order_item_totals() : array() );
            $order_item_totals = array();
            // Tax rows keep WooCommerce's order and wording; only the recorded rate is added to their label.
            $order_tax_rates = self::get_order_tax_rates( $order );
            $tax_row_percents = array();
            foreach ( $order_tax_rates as $order_tax_rate ) {
                $tax_row_percents[sanitize_title( $order_tax_rate['code'] )][] = $order_tax_rate['percent'];
            }
            if ( !empty( $raw_item_totals ) && is_array( $raw_item_totals ) ) {
                foreach ( $raw_item_totals as $key => $total_row ) {
                    if ( 'payment_method' === $key ) {
                        continue;
                        // Payment method is presented in metadata / footer blocks
                    }
                    $label = $total_row['label'] ?? '';
                    if ( 'discount' === $key && !empty( $coupons ) ) {
                        $show_coupons = ( $is_thermal ? 'yes' === OG_PSR_Settings::get( 'ogpsr_thermal_show_coupon_codes', 'yes' ) : 'yes' === OG_PSR_Settings::get( 'ogpsr_a4_show_coupon_codes', 'yes' ) );
                        if ( $show_coupons ) {
                            $label = __( 'Discount', 'semiauto-invoicing' ) . ' (' . implode( ', ', $coupons ) . '):';
                        } else {
                            $label = __( 'Discount:', 'semiauto-invoicing' );
                        }
                    }
                    if ( isset( $tax_row_percents[$key] ) ) {
                        $label = self::add_tax_rate_to_label( $label, $tax_row_percents[$key] );
                    } elseif ( 'tax' === $key ) {
                        $label = self::add_tax_rate_to_label( $label, array_column( $order_tax_rates, 'percent' ) );
                    }
                    $order_item_totals[$key] = array(
                        'key'   => $key,
                        'label' => $label,
                        'value' => $total_row['value'] ?? '',
                    );
                }
            }
            $raw_subtotal = (float) (( method_exists( $order, 'get_subtotal' ) ? $order->get_subtotal() : 0 ));
            $raw_tax_total = (float) (( method_exists( $order, 'get_total_tax' ) ? $order->get_total_tax() : 0 ));
            $raw_grand_total = (float) (( method_exists( $order, 'get_total' ) ? $order->get_total() : 0 ));
            $raw_discount = (float) (( method_exists( $order, 'get_discount_total' ) ? $order->get_discount_total() : 0 ));
            $raw_shipping = (float) (( method_exists( $order, 'get_shipping_total' ) ? $order->get_shipping_total() : 0 ));
            $totals_summary = array(
                'subtotal'          => wc_price( $raw_subtotal, array(
                    'currency' => $currency,
                ) ),
                'raw_subtotal'      => $raw_subtotal,
                'discount'          => wc_price( $raw_discount, array(
                    'currency' => $currency,
                ) ),
                'raw_discount'      => $raw_discount,
                'discount_label'    => $discount_label,
                'has_discount'      => $raw_discount > 0,
                'shipping'          => wc_price( $raw_shipping, array(
                    'currency' => $currency,
                ) ),
                'raw_shipping'      => $raw_shipping,
                'has_shipping'      => $raw_shipping > 0,
                'fees'              => $fees,
                'fees_total'        => wc_price( $fees_total, array(
                    'currency' => $currency,
                ) ),
                'raw_fees_total'    => $fees_total,
                'has_fees'          => !empty( $fees ),
                'tax_total'         => wc_price( $raw_tax_total, array(
                    'currency' => $currency,
                ) ),
                'raw_tax_total'     => $raw_tax_total,
                'has_tax'           => $raw_tax_total > 0,
                'grand_total'       => wc_price( $raw_grand_total, array(
                    'currency' => $currency,
                ) ),
                'raw_grand_total'   => $raw_grand_total,
                'currency'          => $currency,
                'order_item_totals' => $order_item_totals,
            );
            // 9b. BACS Bank Details Context
            $payment_method = ( method_exists( $order, 'get_payment_method' ) ? $order->get_payment_method() : '' );
            $show_bank_details = OG_PSR_Settings::get( 'ogpsr_show_bank_details', 'no' );
            $force_bank_details = OG_PSR_Settings::get( 'ogpsr_force_bank_details', 'no' );
            $selected_bank_accounts = OG_PSR_Settings::get( 'ogpsr_selected_bank_accounts', array() );
            if ( !is_array( $selected_bank_accounts ) ) {
                $selected_bank_accounts = ( !empty( $selected_bank_accounts ) ? (array) $selected_bank_accounts : array() );
            }
            $bank_accounts = array();
            if ( 'yes' === $show_bank_details && ('bacs' === $payment_method || 'yes' === $force_bank_details || !empty( $order->is_mock )) ) {
                $all_bacs = get_option( 'woocommerce_bacs_accounts', array() );
                if ( empty( $selected_bank_accounts ) && !empty( $all_bacs ) && is_array( $all_bacs ) ) {
                    $selected_bank_accounts = array_map( function ( $k ) {
                        return 'bacs_' . $k;
                    }, array_keys( $all_bacs ) );
                }
                if ( !empty( $all_bacs ) && is_array( $all_bacs ) ) {
                    foreach ( $all_bacs as $idx => $acc ) {
                        if ( is_array( $selected_bank_accounts ) && in_array( 'bacs_' . $idx, $selected_bank_accounts, true ) ) {
                            $bank_accounts[] = array(
                                'bank_name'      => ( !empty( $acc['bank_name'] ) ? $acc['bank_name'] : '' ),
                                'account_name'   => ( !empty( $acc['account_name'] ) ? $acc['account_name'] : '' ),
                                'account_number' => ( !empty( $acc['account_number'] ) ? $acc['account_number'] : '' ),
                                'sort_code'      => ( !empty( $acc['sort_code'] ) ? $acc['sort_code'] : '' ),
                                'iban'           => ( !empty( $acc['iban'] ) ? $acc['iban'] : '' ),
                                'bic'            => ( !empty( $acc['bic'] ) ? $acc['bic'] : '' ),
                            );
                        }
                    }
                }
                if ( empty( $bank_accounts ) && method_exists( $order, 'get_bacs_accounts' ) ) {
                    $bank_accounts = $order->get_bacs_accounts();
                }
            }
            // 10. Multi-Rate Tax Breakdown
            $tax_breakdown = array();
            if ( method_exists( $order, 'get_tax_totals' ) ) {
                foreach ( $order->get_tax_totals() as $code => $tax_obj ) {
                    $tax_breakdown[] = array(
                        'label'  => self::add_tax_rate_to_label( $tax_obj->label, array($order_tax_rates[(int) ($tax_obj->rate_id ?? 0)]['percent'] ?? '') ),
                        'amount' => wc_price( (float) $tax_obj->amount, array(
                            'currency' => $currency,
                        ) ),
                    );
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
            $doc = array(
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
                'show_payment_terms'   => 'invoice' === $doc_type && $show_payment_terms,
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
                'show_tax_inclusion'   => $is_thermal && 'yes' === OG_PSR_Settings::get( 'ogpsr_show_tax_inclusion_line', 'yes' ),
                'show_sku'             => 'yes' === OG_PSR_Settings::get( 'ogpsr_a4_show_product_sku', 'no' ),
                'show_ean'             => 'yes' === OG_PSR_Settings::get( 'ogpsr_a4_show_product_ean', 'no' ),
                'show_product_meta'    => true,
                'terms_html'           => sanitize_textarea_field( (string) preg_replace( '/<br\\s*\\/?>/i', "\n", (string) (( $is_thermal ? OG_PSR_Settings::get( 'ogpsr_thermal_terms_and_conditions', '' ) : OG_PSR_Settings::get( 'ogpsr_a4_terms_and_conditions', '' ) )) ) ),
                'bank_details'         => $bank_accounts,
            );
            return apply_filters( 'ogpsr_universal_document_context', $doc, $order );
        }

        /**
         * Resolves the delivery / pickup date stored on an order by the store or by a delivery plugin.
         *
         * Order meta is read on the order first, then on its parent (refunds). Keys and formats are taken from the
         * plugins' own source or documentation:
         *   _ogpsr_delivery_date                   SemiAuto Invoicing (Y-m-d)
         *   lpac_dps_delivery_date / _pickup_date  Chwazi Delivery & Pickup Scheduling (site date format)
         *   delivery_date / pickup_date            CodeRockz Woo Delivery (Y-m-d)
         *   jckwds_date_ymd                        Iconic Delivery Slots (Ymd)
         *   _orddd_timestamp                       Tyche Order Delivery Date Pro (timestamp of the local date)
         *   _orddd_lite_timestamp                  Tyche Order Delivery Date Lite (timestamp of the local date)
         *   _orderable_order_timestamp             Orderable (timestamp shifted to local time)
         *   _delivery_date                         Generic delivery meta
         * Any other meta key naming a delivery or pickup date is accepted only when its value is an unambiguous date.
         *
         * @param WC_Abstract_Order|object $order Order or refund.
         * @return array { date: Y-m-d or '', type: 'delivery'|'pickup'|'', source: meta key or '' }
         */
        public static function resolve_delivery_date( $order ) {
            $result = array(
                'date'   => '',
                'type'   => '',
                'source' => '',
            );
            $sources = array();
            if ( is_object( $order ) && method_exists( $order, 'get_meta' ) ) {
                $sources[] = $order;
            }
            if ( is_object( $order ) && method_exists( $order, 'get_parent_id' ) && $order->get_parent_id() > 0 && function_exists( 'wc_get_order' ) ) {
                $parent = wc_get_order( $order->get_parent_id() );
                if ( $parent && method_exists( $parent, 'get_meta' ) ) {
                    $sources[] = $parent;
                }
            }
            // Key => array( value format, type ).
            $known = array(
                '_ogpsr_delivery_date'       => array('date', 'delivery'),
                'lpac_dps_delivery_date'     => array('site', 'delivery'),
                'lpac_dps_pickup_date'       => array('site', 'pickup'),
                'delivery_date'              => array('date', 'delivery'),
                'pickup_date'                => array('date', 'pickup'),
                'jckwds_date_ymd'            => array('date', 'delivery'),
                '_jckwds_date_ymd'           => array('date', 'delivery'),
                '_orddd_timestamp'           => array('local_ts', 'delivery'),
                '_orddd_lite_timestamp'      => array('local_ts', 'delivery'),
                '_orderable_order_timestamp' => array('local_ts', 'delivery'),
                '_delivery_date'             => array('date', 'delivery'),
            );
            foreach ( $sources as $source ) {
                $chwazi_type = (string) $source->get_meta( 'lpac_dps_order_type' );
                foreach ( $known as $key => $spec ) {
                    if ( 0 === strpos( $key, 'lpac_dps_' ) && $chwazi_type && $spec[1] !== $chwazi_type ) {
                        continue;
                    }
                    $date = self::normalize_delivery_date( $source->get_meta( $key ), $spec[0] );
                    if ( '' !== $date ) {
                        $type = $spec[1];
                        if ( '_orderable_order_timestamp' === $key && 'pickup' === $source->get_meta( '_orderable_service_type' ) ) {
                            $type = 'pickup';
                        }
                        if ( in_array( $key, array('delivery_date', 'pickup_date'), true ) && $source->get_meta( 'delivery_type' ) ) {
                            $type = ( 'pickup' === $source->get_meta( 'delivery_type' ) ? 'pickup' : 'delivery' );
                        }
                        $result = array(
                            'date'   => $date,
                            'type'   => $type,
                            'source' => $key,
                        );
                        break 2;
                    }
                }
            }
            if ( '' === $result['date'] ) {
                foreach ( $sources as $source ) {
                    if ( !method_exists( $source, 'get_meta_data' ) ) {
                        continue;
                    }
                    foreach ( $source->get_meta_data() as $meta ) {
                        $data = ( method_exists( $meta, 'get_data' ) ? $meta->get_data() : array() );
                        $key = ( isset( $data['key'] ) ? (string) $data['key'] : '' );
                        if ( '' === $key || isset( $known[$key] ) || !preg_match( '/(deliver|pickup).*(date|day)/i', $key ) ) {
                            continue;
                        }
                        $date = self::normalize_delivery_date( ( isset( $data['value'] ) ? $data['value'] : '' ), 'date' );
                        if ( '' !== $date ) {
                            $result = array(
                                'date'   => $date,
                                'type'   => ( false !== stripos( $key, 'pickup' ) ? 'pickup' : 'delivery' ),
                                'source' => $key,
                            );
                            break 2;
                        }
                    }
                }
            }
            /**
             * Filters the resolved delivery / pickup date so any delivery plugin can supply it.
             *
             * @param array             $result { date: Y-m-d or '', type, source }.
             * @param WC_Abstract_Order $order  Order or refund.
             */
            $filtered = apply_filters( 'ogpsr_delivery_date', $result, $order );
            if ( is_array( $filtered ) && isset( $filtered['date'] ) && $filtered['date'] !== $result['date'] ) {
                $filtered['date'] = self::normalize_delivery_date( $filtered['date'], 'date' );
                $result = array_merge( $result, $filtered );
            }
            return $result;
        }

        /**
         * Converts a stored delivery value to Y-m-d without guessing day/month order.
         *
         * @param mixed  $value  Stored meta value.
         * @param string $format 'date' (Y-m-d or Ymd), 'site' (site date format, then Y-m-d / Ymd) or 'local_ts'
         *                       (Unix timestamp that already carries the local date).
         * @return string Y-m-d or ''.
         */
        private static function normalize_delivery_date( $value, $format ) {
            if ( !is_scalar( $value ) ) {
                return '';
            }
            $value = trim( (string) $value );
            if ( '' === $value ) {
                return '';
            }
            if ( 'local_ts' === $format ) {
                return ( ctype_digit( $value ) && (int) $value > 0 ? gmdate( 'Y-m-d', (int) $value ) : '' );
            }
            if ( 'site' === $format && function_exists( 'get_option' ) ) {
                $site = DateTime::createFromFormat( '!' . get_option( 'date_format', 'Y-m-d' ), $value );
                if ( $site && $site->format( get_option( 'date_format', 'Y-m-d' ) ) === $value ) {
                    return $site->format( 'Y-m-d' );
                }
            }
            if ( preg_match( '/^(\\d{4})-?(\\d{2})-?(\\d{2})(?:[ T]\\d{2}:\\d{2}(?::\\d{2})?)?$/', $value, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
                return $m[1] . '-' . $m[2] . '-' . $m[3];
            }
            return '';
        }

        /**
         * Tax rates charged on an order, as WooCommerce recorded them on the order's tax lines.
         *
         * The rate name and percentage come from the order itself, so a rate edited or deleted later in the
         * WooCommerce tax settings does not change the documents. A refund uses its parent order's tax lines.
         *
         * @param mixed $order Order or refund.
         * @return array Rate ID => array( 'code' => string, 'label' => string, 'percent' => string ('' when not recorded) ).
         */
        public static function get_order_tax_rates( $order ) {
            $rates = array();
            if ( !is_object( $order ) || !method_exists( $order, 'get_items' ) ) {
                return $rates;
            }
            $tax_items = $order->get_items( 'tax' );
            if ( empty( $tax_items ) && $order instanceof WC_Order_Refund ) {
                $parent = wc_get_order( $order->get_parent_id() );
                $tax_items = ( $parent ? $parent->get_items( 'tax' ) : array() );
            }
            foreach ( (array) $tax_items as $tax_item ) {
                if ( !$tax_item instanceof WC_Order_Item_Tax ) {
                    continue;
                }
                $percent = $tax_item->get_rate_percent();
                $rates[(int) $tax_item->get_rate_id()] = array(
                    'code'    => (string) $tax_item->get_rate_code(),
                    'label'   => (string) $tax_item->get_label(),
                    'percent' => ( null === $percent || '' === $percent ? '' : self::format_tax_percent( (float) $percent ) ),
                );
            }
            return $rates;
        }

        /**
         * Formats a tax rate the way the store shows decimals, e.g. "19%" or "7,7%".
         *
         * @param float $percent Tax rate in percent.
         * @return string
         */
        public static function format_tax_percent( $percent ) {
            return wc_format_localized_decimal( wc_format_decimal( round( (float) $percent, 4 ), false, true ) ) . '%';
        }

        /**
         * Adds the tax rate(s) to a tax label, e.g. "VAT:" becomes "VAT (5%):" and "VAT" becomes "VAT (7% + 19%)".
         *
         * The label is returned unchanged when it already shows a percentage or no rate was recorded.
         *
         * @param string $label    Tax label (with or without the trailing colon).
         * @param array  $percents Formatted rates (see format_tax_percent()).
         * @return string
         */
        public static function add_tax_rate_to_label( $label, $percents ) {
            $label = (string) $label;
            $percents = array_values( array_unique( array_filter( array_map( 'strval', (array) $percents ), 'strlen' ) ) );
            if ( empty( $percents ) || false !== strpos( $label, '%' ) ) {
                return $label;
            }
            $trimmed = rtrim( $label );
            $has_colon = ':' === substr( $trimmed, -1 );
            $name = ( $has_colon ? rtrim( substr( $trimmed, 0, -1 ) ) : $trimmed );
            /* translators: 1: tax name, e.g. VAT; 2: tax rate(s), e.g. 19% or 7% + 19% */
            return sprintf( __( '%1$s (%2$s)', 'semiauto-invoicing' ), $name, implode( ' + ', $percents ) ) . (( $has_colon ? ':' : '' ));
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
            return array(
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
                'seller'               => array(),
                'buyer'                => array(),
                'shipping'             => array(
                    'enabled' => false,
                ),
                'line_items'           => array(),
                'totals'               => array(
                    'subtotal'     => 0,
                    'grand_total'  => 0,
                    'currency'     => 'EUR',
                    'has_discount' => false,
                    'has_shipping' => false,
                    'has_tax'      => false,
                ),
                'tax_breakdown'        => array(),
                'payment_methods'      => array(),
                'show_payment_methods' => 'no',
                'footer_text'          => '',
                'header_text'          => '',
                'base_rules'           => '',
                'terms_html'           => '',
            );
        }

    }

}
<?php

/**
 * Settings Tab Helpers and B2B Context Utilities
 * 
 * Provides metadata retrieval, email dispatchers, and input sanitization utilities.
 * 
 * @package SemiAutoInvoicing
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( !class_exists( 'OG_PSR_Settings_Helpers' ) ) {
    class OG_PSR_Settings_Helpers {
        /**
         * Get all WooCommerce email IDs
         *
         * @return array
         */
        public static function get_wc_emails() {
            $emails = [];
            if ( function_exists( 'WC' ) ) {
                $wc_emails = WC()->mailer()->get_emails();
                foreach ( $wc_emails as $email ) {
                    if ( !empty( $email->id ) ) {
                        $emails[$email->id] = $email->get_title();
                    }
                }
            }
            return ( $emails ?: [
                'new_order'                 => __( 'New Order', 'semiauto-invoicing' ),
                'customer_completed_order'  => __( 'Completed Order', 'semiauto-invoicing' ),
                'customer_processing_order' => __( 'Processing Order', 'semiauto-invoicing' ),
                'customer_invoice'          => __( 'Customer Invoice', 'semiauto-invoicing' ),
            ] );
        }

        /**
         * Get all WooCommerce order statuses
         *
         * @return array
         */
        public static function get_order_statuses() {
            $statuses = [];
            if ( function_exists( 'wc_get_order_statuses' ) ) {
                $statuses = wc_get_order_statuses();
                $clean_statuses = [];
                foreach ( $statuses as $id => $label ) {
                    $clean_statuses[str_replace( 'wc-', '', $id )] = $label;
                }
                return $clean_statuses;
            }
            return [
                'pending'    => __( 'Pending', 'semiauto-invoicing' ),
                'processing' => __( 'Processing', 'semiauto-invoicing' ),
                'on-hold'    => __( 'On Hold', 'semiauto-invoicing' ),
                'completed'  => __( 'Completed', 'semiauto-invoicing' ),
                'cancelled'  => __( 'Cancelled', 'semiauto-invoicing' ),
                'refunded'   => __( 'Refunded', 'semiauto-invoicing' ),
                'failed'     => __( 'Failed', 'semiauto-invoicing' ),
            ];
        }

        /**
         * Helper: Sends the generated PDF to the customer via email.
         *
         * @param int    $order_id
         * @param string $file_path
         * @param string $document_type
         * @return bool
         */
        public static function send_to_customer_email( $order_id, $file_path, $document_type ) {
            $order = wc_get_order( $order_id );
            if ( !$order ) {
                return false;
            }
            $customer_email = $order->get_billing_email();
            if ( !$customer_email ) {
                return false;
            }
            $format_labels = [
                'thermal'   => __( 'Receipt', 'semiauto-invoicing' ),
                'a4'        => __( 'Invoice', 'semiauto-invoicing' ),
                'packaging' => __( 'Packaging Slip', 'semiauto-invoicing' ),
            ];
            $doc_type = ( isset( $format_labels[$document_type] ) ? $format_labels[$document_type] : __( 'Document', 'semiauto-invoicing' ) );
            $order_num = ( method_exists( $order, 'get_order_number' ) ? sanitize_text_field( (string) $order->get_order_number() ) : (string) $order->get_id() );
            $subject = sanitize_text_field( sprintf( 
                /* translators: 1: document type (e.g. Receipt), 2: order number */
                __( 'Your %1$s for Order #%2$s', 'semiauto-invoicing' ),
                $doc_type,
                $order_num
             ) );
            $blogname = get_option( 'blogname' );
            $message = sprintf(
                /* translators: 1: document type (e.g. Receipt), 2: order number, 3: blog name */
                __( 'Hello, here is your %1$s for order #%2$s from %3$s.', 'semiauto-invoicing' ),
                esc_html( strtolower( $doc_type ) ),
                esc_html( (string) $order_num ),
                esc_html( (string) $blogname )
            );
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            $attachments = [$file_path];
            return wp_mail(
                $customer_email,
                $subject,
                $message,
                $headers,
                $attachments
            );
        }

        /**
         * Helper to get active payment gateway titles
         * 
         * @return array
         */
        public static function get_active_payment_methods_titles() {
            if ( !function_exists( 'WC' ) ) {
                return [];
            }
            $gateways = WC()->payment_gateways->get_available_payment_gateways();
            $titles = [];
            if ( $gateways ) {
                foreach ( $gateways as $gateway ) {
                    if ( $gateway->enabled == 'yes' ) {
                        $titles[] = $gateway->get_title();
                    }
                }
            }
            return $titles;
        }

        /**
         * Helper to get BACS account options for settings
         * 
         * @return array
         */
        public static function get_bacs_account_options() {
            $accounts = get_option( 'woocommerce_bacs_accounts', [] );
            $options = [];
            if ( !empty( $accounts ) && is_array( $accounts ) ) {
                foreach ( $accounts as $index => $account ) {
                    $label = ( isset( $account['bank_name'] ) ? $account['bank_name'] : __( 'Bank', 'semiauto-invoicing' ) );
                    if ( isset( $account['iban'] ) && !empty( $account['iban'] ) ) {
                        $label .= ' - ' . substr( $account['iban'], -4 );
                    } elseif ( isset( $account['account_number'] ) && !empty( $account['account_number'] ) ) {
                        $label .= ' - ' . substr( $account['account_number'], -4 );
                    }
                    $options['bacs_' . $index] = $label;
                }
            }
            return $options;
        }

        /**
         * Sanitize callback for multiselect/array settings
         * Ensures array values are strictly sanitized with sanitize_text_field,
         * with optional allowlist filtering against registered option domains.
         * 
         * @param mixed $value
         * @param array $allowed_keys Optional allowlist of permitted scalar keys.
         * @return array
         */
        public static function sanitize_array_setting( $value, $allowed_keys = [] ) {
            if ( !is_array( $value ) ) {
                return [];
            }
            $clean = array_values( array_filter( array_map( function ( $item ) {
                return ( is_scalar( $item ) ? sanitize_text_field( wp_unslash( (string) $item ) ) : '' );
            }, $value ), function ( $item ) {
                return $item !== '';
            } ) );
            if ( !empty( $allowed_keys ) && is_array( $allowed_keys ) ) {
                $clean = array_values( array_filter( $clean, function ( $item ) use($allowed_keys) {
                    return in_array( $item, $allowed_keys, true );
                } ) );
            }
            return $clean;
        }

    }

}
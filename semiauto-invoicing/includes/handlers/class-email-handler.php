<?php

/**
 * Email Attachment Handler
 * 
 * Manages the intelligent attachment of PDFs and XMLs to WooCommerce emails.
 * 
 * @package SemiAutoInvoicing
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
class OG_PSR_Email_Handler {
    public static $is_sending_reminder = false;

    public function __construct() {
        add_filter(
            'woocommerce_email_attachments',
            [$this, 'handle_attachments'],
            10,
            3
        );
    }

    /**
     * Determine which documents to attach to outgoing emails
     */
    public function handle_attachments( $attachments, $email_id, $order ) {
        if ( !$order instanceof WC_Abstract_Order ) {
            return $attachments;
        }
        $order_id = $order->get_id();
        $pdf_gen = new OG_PSR_PDF_Generator();
        $formats = (array) OG_PSR_Settings::get( 'ogpsr_receipt_formats', ['thermal'] );
        // 1. Thermal Receipt Attachment
        $thermal_emails = (array) OG_PSR_Settings::get( 'ogpsr_email_ids', ['new_order'] );
        if ( class_exists( 'OG_PSR_Document_Registry' ) && OG_PSR_Document_Registry::is_email_attachable( 'thermal' ) && in_array( 'thermal', $formats, true ) && (in_array( $email_id, $thermal_emails, true ) || self::$is_sending_reminder && $email_id === 'customer_invoice') ) {
            $file = $pdf_gen->generate_thermal( $order_id );
            if ( $file && OG_PSR_Document_Registry::validate_document_file( $file, 'thermal' ) ) {
                $attachments[] = $file;
                /* translators: %s: WooCommerce email identifier */
                OG_PSR_Logger::info( sprintf( __( 'Attached Thermal Receipt to %s email', 'semiauto-invoicing' ), $email_id ), $order_id );
            }
        }
        // 2. A4 Invoice Attachment
        $a4_emails = (array) OG_PSR_Settings::get( 'ogpsr_a4_email_ids', [] );
        if ( class_exists( 'OG_PSR_Document_Registry' ) && OG_PSR_Document_Registry::is_email_attachable( 'a4' ) && in_array( 'a4', $formats, true ) && in_array( $email_id, $a4_emails, true ) ) {
            $file = $pdf_gen->generate_a4( $order_id );
            if ( $file && OG_PSR_Document_Registry::validate_document_file( $file, 'a4' ) ) {
                $attachments[] = $file;
                /* translators: %s: WooCommerce email identifier */
                OG_PSR_Logger::info( sprintf( __( 'Attached A4 Invoice to %s email', 'semiauto-invoicing' ), $email_id ), $order_id );
            }
        }
        // 3. Packaging Slip Attachment
        $packaging_emails = (array) OG_PSR_Settings::get( 'ogpsr_packaging_email_ids', [] );
        if ( class_exists( 'OG_PSR_Document_Registry' ) && OG_PSR_Document_Registry::is_email_attachable( 'packaging' ) && in_array( 'packaging', $formats, true ) && in_array( $email_id, $packaging_emails, true ) ) {
            $file = $pdf_gen->generate_packaging( $order_id );
            if ( $file && OG_PSR_Document_Registry::validate_document_file( $file, 'packaging' ) ) {
                $attachments[] = $file;
                /* translators: %s: WooCommerce email identifier */
                OG_PSR_Logger::info( sprintf( __( 'Attached Packaging Slip to %s email', 'semiauto-invoicing' ), $email_id ), $order_id );
            }
        }
        return $attachments;
    }

}

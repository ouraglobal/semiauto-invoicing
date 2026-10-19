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
            array($this, 'handle_attachments'),
            10,
            3
        );
        add_filter( 'woocommerce_email_classes', array(__CLASS__, 'register_email_classes') );
        add_filter(
            'woocommerce_email_styles',
            array(__CLASS__, 'add_email_styles'),
            10,
            2
        );
    }

    /**
     * Register the customer document email with the WooCommerce mailer.
     *
     * @param array $emails Registered WooCommerce email classes.
     * @return array
     */
    public static function register_email_classes( $emails ) {
        require_once OG_PSR_PATH . 'includes/handlers/class-email-customer-document.php';
        if ( class_exists( 'OG_PSR_Email_Customer_Document' ) ) {
            $emails['OG_PSR_Email_Customer_Document'] = new OG_PSR_Email_Customer_Document();
        }
        return $emails;
    }

    /**
     * Append the plugin email stylesheet to WooCommerce email CSS for the customer document email.
     *
     * WooCommerce inlines this CSS into the message for mail client compatibility.
     *
     * @param string        $css   WooCommerce email CSS.
     * @param WC_Email|null $email Email being rendered.
     * @return string
     */
    public static function add_email_styles( $css, $email = null ) {
        if ( !is_object( $email ) || !isset( $email->id ) || 'ogpsr_customer_document' !== $email->id ) {
            return $css;
        }
        return $css . self::read_email_stylesheet( OG_PSR_PATH . 'assets/css/ogpsr-email.css' );
    }

    /**
     * Read a plugin email stylesheet through the WordPress Filesystem API.
     *
     * @param string $path Absolute stylesheet path inside the plugin assets directory.
     * @return string
     */
    public static function read_email_stylesheet( $path ) {
        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        $path = wp_normalize_path( $path );
        $assets_dir = wp_normalize_path( OG_PSR_PATH . 'assets/css/' );
        if ( !$wp_filesystem || 0 !== strpos( $path, $assets_dir ) || '.css' !== substr( $path, -4 ) || !$wp_filesystem->exists( $path ) ) {
            return '';
        }
        return "\n" . (string) $wp_filesystem->get_contents( $path );
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
        $formats = (array) OG_PSR_Settings::get( 'ogpsr_receipt_formats', array('thermal') );
        // 1. Thermal Receipt Attachment
        $thermal_emails = (array) OG_PSR_Settings::get( 'ogpsr_email_ids', array('new_order') );
        if ( class_exists( 'OG_PSR_Document_Registry' ) && OG_PSR_Document_Registry::is_email_attachable( 'thermal' ) && in_array( 'thermal', $formats, true ) && (in_array( $email_id, $thermal_emails, true ) || self::$is_sending_reminder && 'customer_invoice' === $email_id) ) {
            $file = $pdf_gen->generate_thermal( $order_id );
            if ( $file && OG_PSR_Document_Registry::validate_document_file( $file, 'thermal' ) ) {
                $attachments[] = $file;
                /* translators: %s: WooCommerce email identifier */
                OG_PSR_Logger::info( sprintf( __( 'Attached Thermal Receipt to %s email', 'semiauto-invoicing' ), $email_id ), $order_id );
            }
        }
        // 2. A4 Invoice Attachment
        $a4_emails = (array) OG_PSR_Settings::get( 'ogpsr_a4_email_ids', array() );
        if ( class_exists( 'OG_PSR_Document_Registry' ) && OG_PSR_Document_Registry::is_email_attachable( 'a4' ) && in_array( 'a4', $formats, true ) && in_array( $email_id, $a4_emails, true ) ) {
            $file = $pdf_gen->generate_a4( $order_id );
            if ( $file && OG_PSR_Document_Registry::validate_document_file( $file, 'a4' ) ) {
                $attachments[] = $file;
                /* translators: %s: WooCommerce email identifier */
                OG_PSR_Logger::info( sprintf( __( 'Attached A4 Invoice to %s email', 'semiauto-invoicing' ), $email_id ), $order_id );
            }
        }
        // 3. Packaging Slip Attachment
        $packaging_emails = (array) OG_PSR_Settings::get( 'ogpsr_packaging_email_ids', array() );
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

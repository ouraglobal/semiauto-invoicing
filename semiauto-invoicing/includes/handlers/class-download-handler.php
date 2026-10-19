<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( !class_exists( 'OG_PSR_Download_Handler' ) ) {
    /**
     * Secure PDF Download Handler
     * Handles secure downloads of PDF files with permission checks
     */
    class OG_PSR_Download_Handler {
        /**
         * Initialize hooks
         */
        public function __construct() {
            add_action( 'init', [$this, 'handle_download'] );
        }

        /**
         * Handle secure PDF download
         */
        public function handle_download() {
            if ( !isset( $_GET['ogpsr_download_order'] ) || !isset( $_GET['format'] ) || !isset( $_GET['nonce'] ) ) {
                return;
            }
            $order_id = intval( wp_unslash( $_GET['ogpsr_download_order'] ) );
            $format = sanitize_key( wp_unslash( $_GET['format'] ) );
            // thermal|a4|packaging
            $nonce = sanitize_text_field( wp_unslash( $_GET['nonce'] ) );
            // Verify nonce
            if ( !wp_verify_nonce( $nonce, 'ogpsr_download_' . $order_id . '_' . $format ) ) {
                wp_die( esc_html__( 'Security check failed', 'semiauto-invoicing' ), 403 );
            }
            $order = wc_get_order( $order_id );
            if ( !$order ) {
                wp_die( esc_html__( 'Order not found.', 'semiauto-invoicing' ), 404 );
            }
            // Enforce central security barrier: admin capabilities or matching logged-in customer (strict denial for guests)
            if ( !class_exists( 'OG_PSR_Security' ) || !OG_PSR_Security::can_access_order_document( $order, $format ) ) {
                /* translators: 1: Order ID, 2: Document format */
                OG_PSR_Logger::warning( sprintf( __( 'Unauthorized document download attempt for Order #%1$d (format: %2$s)', 'semiauto-invoicing' ), $order_id, $format ), $order_id );
                wp_die( esc_html__( 'You do not have permission to download this file', 'semiauto-invoicing' ), 403 );
            }
            // Find the file using the generator's existence logic
            $generator = new OG_PSR_PDF_Generator();
            $file_path = null;
            switch ( $format ) {
                case 'thermal':
                    $file_path = $generator->generate_thermal( $order_id );
                    break;
                case 'a4':
                    $file_path = $generator->generate_a4( $order_id );
                    break;
                case 'packaging':
                    $file_path = $generator->generate_packaging( $order_id );
                    break;
                default:
                    break;
            }
            if ( is_wp_error( $file_path ) ) {
                wp_die( esc_html( $file_path->get_error_message() ), esc_html__( 'Generation Failed', 'semiauto-invoicing' ), array(
                    'response' => 500,
                ) );
            }
            if ( empty( $file_path ) ) {
                wp_die( esc_html__( 'Invalid or unsupported document format.', 'semiauto-invoicing' ), esc_html__( 'Forbidden', 'semiauto-invoicing' ), array(
                    'response' => 403,
                ) );
            }
            // Security: Enforce strict filesystem containment within plugin uploads root
            if ( !class_exists( 'OG_PSR_Security' ) || !OG_PSR_Security::is_path_within( $file_path, OG_PSR_Security::resolve_upload_root() ) ) {
                wp_die( esc_html__( 'Access to the requested file is not allowed.', 'semiauto-invoicing' ), esc_html__( 'Forbidden', 'semiauto-invoicing' ), array(
                    'response' => 403,
                ) );
            }
            $file_path = wp_normalize_path( (string) $file_path );
            $base_dir = OG_PSR_Security::resolve_upload_root();
            // Initialize WP_Filesystem early to avoid direct PHP filesystem calls.
            global $wp_filesystem;
            if ( empty( $wp_filesystem ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }
            if ( !$file_path || empty( $wp_filesystem ) || !$wp_filesystem->exists( $file_path ) || !class_exists( 'OG_PSR_Document_Registry' ) || !OG_PSR_Document_Registry::validate_document_file( $file_path, $format ) ) {
                wp_die( esc_html__( 'File could not be generated or found.', 'semiauto-invoicing' ), esc_html__( 'Not Found', 'semiauto-invoicing' ), array(
                    'response' => 404,
                ) );
                return;
            }
            // Serve file
            $extension = pathinfo( $file_path, PATHINFO_EXTENSION );
            $mime_type = 'application/pdf';
            // Clean all active output buffers to prevent PHP notices or whitespace corruption in downloaded PDFs
            while ( ob_get_level() > 0 ) {
                ob_end_clean();
            }
            header( 'Content-Type: ' . $mime_type );
            header( 'Content-Disposition: attachment; filename="' . basename( $file_path ) . '"' );
            $file_size = ( !empty( $wp_filesystem ) ? $wp_filesystem->size( $file_path ) : 0 );
            header( 'Content-Length: ' . $file_size );
            header( 'Cache-Control: private, no-transform, max-age=0, must-revalidate' );
            header( 'Pragma: no-cache' );
            header( 'X-Robots-Tag: noindex, nofollow, noarchive' );
            if ( !empty( $wp_filesystem ) && $wp_filesystem->exists( $file_path ) ) {
                /* translators: 1: Filename, 2: Document format */
                OG_PSR_Logger::info( sprintf( __( 'Order document downloaded: %1$s (format: %2$s)', 'semiauto-invoicing' ), basename( $file_path ), $format ), $order_id );
                // Use WP_Filesystem to read the file contents — this is the WordPress-compliant method.
                // Reviewer Comment: Outputting the binary stream for the PDF file download. Binary content must be output raw.
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo $wp_filesystem->get_contents( $file_path );
            } else {
                // Should not happen given the early check, but added for safety.
                wp_die( esc_html__( 'File not found.', 'semiauto-invoicing' ), esc_html__( 'File Not Found', 'semiauto-invoicing' ), array(
                    'response' => 404,
                ) );
                return;
            }
            exit;
        }

        /**
         * Generate secure download URL
         *
         * @param int $order_id
         * @param string $format Free format: thermal|a4|packaging (premium formats resolved via delegation)
         * @return string Secure download URL
         */
        public static function get_download_url( $order_id, $format ) {
            $nonce = wp_create_nonce( 'ogpsr_download_' . $order_id . '_' . $format );
            $args = [
                'ogpsr_download_order' => $order_id,
                'format'               => $format,
                'nonce'                => $nonce,
            ];
            return add_query_arg( $args, home_url() );
        }

    }

}
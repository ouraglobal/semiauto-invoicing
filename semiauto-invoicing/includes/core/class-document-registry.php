<?php

/**
 * Document Registry Class
 * 
 * Canonical Single Source of Truth for all supported document types,
 * format aliases, and storage subdirectories in the Free Core tier.
 * 
 * @package SemiAutoInvoicing
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( !class_exists( 'OG_PSR_Document_Registry' ) ) {
    class OG_PSR_Document_Registry {
        /**
         * Canonical Free Document Types
         */
        const FREE_DOCUMENT_TYPES = ['invoice', 'thermal_receipt', 'packaging_slip'];

        /**
         * Format to Canonical Document Type Mapping
         */
        const FREE_FORMATS = [
            'a4'        => 'invoice',
            'thermal'   => 'thermal_receipt',
            'packaging' => 'packaging_slip',
        ];

        /**
         * Storage Subdirectory Mapping for Free Document Types
         */
        const FREE_STORAGE_SUBDIRS = [
            'invoice'         => 'invoices/pdf',
            'thermal_receipt' => 'thermal-receipts',
            'packaging_slip'  => 'packaging-slips',
        ];

        /**
         * Order Meta Key Mapping (Format Alias => Meta Key)
         */
        const FREE_META_KEYS = [
            'thermal'   => '_ogpsr_thermal_path',
            'a4'        => '_ogpsr_a4_path',
            'packaging' => '_ogpsr_packaging_path',
        ];

        /**
         * Filename Prefix Mapping (Format Alias => Prefix)
         */
        const FREE_FILENAME_PREFIXES = [
            'thermal'   => 'thermal-',
            'a4'        => 'invoice-',
            'packaging' => 'packaging-',
        ];

        /**
         * Storage Subdirectory Mapping by Format Alias
         */
        const FREE_STORAGE_BY_FORMAT = [
            'thermal'   => 'thermal-receipts',
            'a4'        => 'invoices/pdf',
            'packaging' => 'packaging-slips',
        ];

        /**
         * Check if document type is supported in Free Core
         *
         * @param string $type
         * @return bool
         */
        public static function is_supported_type( $type ) {
            return in_array( $type, self::FREE_DOCUMENT_TYPES, true );
        }

        /**
         * Check if format alias is supported in Free Core
         *
         * @param string $format
         * @return bool
         */
        public static function is_supported_format( $format ) {
            return array_key_exists( $format, self::FREE_FORMATS );
        }

        /**
         * Convert format alias to canonical document type
         *
         * @param string $format
         * @return string|false
         */
        public static function format_to_type( $format ) {
            return self::FREE_FORMATS[$format] ?? false;
        }

        /**
         * Convert document type to format alias
         *
         * @param string $type
         * @return string|false
         */
        public static function type_to_format( $type ) {
            $flipped = array_flip( self::FREE_FORMATS );
            return $flipped[$type] ?? false;
        }

        /**
         * Get storage subdirectory for a document type
         *
         * @param string $doc_type
         * @return string|false
         */
        public static function get_storage_subdir( $doc_type ) {
            return self::FREE_STORAGE_SUBDIRS[$doc_type] ?? false;
        }

        /**
         * Get order meta key for a format alias
         *
         * @param string $format Format alias (thermal, a4, packaging)
         * @return string|false
         */
        public static function get_meta_key( $format ) {
            return self::FREE_META_KEYS[$format] ?? false;
        }

        /**
         * Get filename prefix for a format alias
         *
         * @param string $format Format alias (thermal, a4, packaging)
         * @return string|false
         */
        public static function get_filename_prefix( $format ) {
            return self::FREE_FILENAME_PREFIXES[$format] ?? false;
        }

        /**
         * Get storage subdirectory by format alias
         *
         * @param string $format Format alias (thermal, a4, packaging)
         * @return string|false
         */
        public static function get_storage_subdir_by_format( $format ) {
            return self::FREE_STORAGE_BY_FORMAT[$format] ?? false;
        }

        /**
         * Get all supported Free document types
         *
         * @return array
         */
        public static function get_free_document_types() {
            return self::FREE_DOCUMENT_TYPES;
        }

        /**
         * Get all supported Free formats
         *
         * @return array
         */
        public static function get_free_formats() {
            return self::FREE_FORMATS;
        }

        /**
         * Get all Free storage subdirectories
         *
         * @return array
         */
        public static function get_all_subdirs() {
            return array_values( self::FREE_STORAGE_SUBDIRS );
        }

        /**
         * Get all Free order meta keys
         *
         * @return array
         */
        public static function get_free_meta_keys() {
            return array_values( self::FREE_META_KEYS );
        }

        /**
         * Canonical list of allowed PDF typography fonts across all document engines
         */
        const ALLOWED_FONTS = [
            'DejaVu Sans',
            'Courier',
            'Helvetica',
            'Times',
            'Verdana',
            'Arial'
        ];

        /**
         * Get canonical list of allowed fonts
         *
         * @return array
         */
        public static function get_allowed_fonts() {
            return self::ALLOWED_FONTS;
        }

        /**
         * Authoritative semantic validation and sanitization of a font family.
         * Ensures no malicious CSS characters, quotes, or breakout payloads pass through.
         *
         * @param mixed  $font_family
         * @param string $fallback
         * @return string
         */
        public static function sanitize_font_family( $font_family, $fallback = 'DejaVu Sans' ) {
            if ( empty( $font_family ) || !is_string( $font_family ) ) {
                return $fallback;
            }
            return ( in_array( $font_family, self::ALLOWED_FONTS, true ) ? $font_family : $fallback );
        }

        /**
         * Check if a format is attachable to WooCommerce emails in Free Core
         *
         * @param string $format
         * @return bool
         */
        public static function is_email_attachable( $format ) {
            return in_array( $format, ['thermal', 'a4', 'packaging'], true );
        }

        /**
         * Get allowed file extensions for document format
         *
         * @param string $format
         * @return array List of lowercased allowed extensions (e.g. ['pdf'])
         */
        public static function get_allowed_extensions( $format = '' ) {
            $allowed = ['pdf'];
            return $allowed;
        }

        /**
         * Authoritative validation of a generated document file on disk.
         * Enforces non-empty string, upload root containment, allowed extension,
         * existing non-directory status, and non-zero byte size via WP_Filesystem.
         *
         * @param string $file_path Absolute path to validate
         * @param string $format    Optional format alias (thermal, a4, packaging)
         * @return bool True if valid, false otherwise
         */
        public static function validate_document_file( $file_path, $format = '' ) {
            if ( empty( $file_path ) || !is_string( $file_path ) ) {
                return false;
            }
            // Normalize path
            $normalized = wp_normalize_path( $file_path );
            // Verify extension
            $ext = strtolower( pathinfo( $normalized, PATHINFO_EXTENSION ) );
            $allowed_exts = self::get_allowed_extensions( $format );
            if ( !in_array( $ext, $allowed_exts, true ) ) {
                return false;
            }
            // Verify upload root containment (Fail-Closed)
            if ( !class_exists( 'OG_PSR_Security' ) ) {
                return false;
            }
            $upload_root = OG_PSR_Security::resolve_upload_root();
            if ( empty( $upload_root ) || !OG_PSR_Security::is_path_within( $normalized, $upload_root ) ) {
                return false;
            }
            // Verify disk status via WP_Filesystem
            global $wp_filesystem;
            if ( empty( $wp_filesystem ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }
            if ( !$wp_filesystem || !$wp_filesystem->exists( $normalized ) || $wp_filesystem->is_dir( $normalized ) ) {
                return false;
            }
            $size = (int) $wp_filesystem->size( $normalized );
            return $size > 0;
        }

    }

}
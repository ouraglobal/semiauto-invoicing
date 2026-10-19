<?php

/**
 * Security & Authorization Barrier Class
 * 
 * Centralized security services for SemiAuto Invoicing:
 * - Order document authorization & capability checks
 * - Filesystem containment & path traversal defense
 * - Local media attachment validation & boundary checks
 * 
 * @package SemiAutoInvoicing
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( !class_exists( 'OG_PSR_Security' ) ) {
    class OG_PSR_Security {
        /**
         * Resolve the plugin's base uploads root directory
         *
         * @return string
         */
        public static function resolve_upload_root() {
            $default_dir = ( defined( 'OG_PSR_UPLOADS_DIR' ) ? OG_PSR_UPLOADS_DIR : (function () {
                $upload_dir = wp_upload_dir();
                return $upload_dir['basedir'] . '/semiauto-invoicing';
            })() );
            $filtered_dir = apply_filters( 'ogpsr_document_storage_root', $default_dir );
            // Fail-safe validation: Ensure the filtered path is a valid non-empty string and not a remote URI wrapper
            if ( empty( $filtered_dir ) || !is_string( $filtered_dir ) || preg_match( '/^[a-zA-Z0-9\\+\\.\\-]+:\\/\\//', $filtered_dir ) ) {
                $filtered_dir = $default_dir;
            }
            return wp_normalize_path( $filtered_dir );
        }

        /**
         * Resolve root directory for a specific canonical document type
         *
         * @param string $doc_type
         * @return string
         */
        public static function resolve_document_root( $doc_type ) {
            $upload_root = self::resolve_upload_root();
            $subdir = OG_PSR_Document_Registry::get_storage_subdir( $doc_type );
            if ( $subdir ) {
                return wp_normalize_path( $upload_root . '/' . $subdir );
            }
            return $upload_root;
        }

        /**
         * Establish, create, and verify a trusted destination directory within the upload root.
         *
         * Mitigates TOCTOU conditions by ensuring the directory exists and its canonical
         * realpath is validated strictly within the upload root before filenames are constructed.
         * If the directory is a symlink resolving outside the upload root, fails closed immediately.
         *
         * @param string $dir Target directory path.
         * @return string|false Canonical normalized directory path on success, false on failure.
         */
        public static function establish_trusted_directory( $dir ) {
            if ( empty( $dir ) || !is_string( $dir ) ) {
                return false;
            }
            // Reject null-bytes, stream wrappers, and URI schemes
            if ( strpos( $dir, "\x00" ) !== false || preg_match( '/^[a-zA-Z0-9\\+\\.\\-]+:\\/\\//', $dir ) ) {
                return false;
            }
            $upload_root = self::resolve_upload_root();
            $real_upload_root = realpath( $upload_root );
            if ( $real_upload_root === false ) {
                // Ensure upload root exists and re-resolve
                if ( function_exists( 'wp_mkdir_p' ) ) {
                    wp_mkdir_p( $upload_root );
                }
                $real_upload_root = realpath( $upload_root );
                if ( $real_upload_root === false ) {
                    return false;
                }
            }
            $real_upload_root = wp_normalize_path( $real_upload_root );
            $real_root_slash = rtrim( $real_upload_root, '/' ) . '/';
            // Ensure destination directory exists using standard WordPress filesystem mechanism
            $normalized_dir = wp_normalize_path( $dir );
            if ( !file_exists( $normalized_dir ) ) {
                if ( function_exists( 'wp_mkdir_p' ) ) {
                    wp_mkdir_p( $normalized_dir );
                }
            }
            // Canonicalize the directory path
            $real_dir = realpath( $normalized_dir );
            if ( $real_dir === false || !is_dir( $real_dir ) ) {
                return false;
            }
            $real_dir_normalized = wp_normalize_path( $real_dir );
            $real_dir_slash = rtrim( $real_dir_normalized, '/' ) . '/';
            // Strict directory-boundary containment comparison against trusted upload root
            if ( strpos( $real_dir_slash, $real_root_slash ) !== 0 ) {
                // Fail closed: directory resolves outside upload root (e.g. symlink traversal)
                return false;
            }
            return $real_dir_normalized;
        }

        /**
         * Validate that a candidate file path resides strictly inside an allowed base directory
         *
         * @param string $candidate  Candidate absolute file path.
         * @param string|null $base_dir Optional base directory. Defaults to plugin upload root.
         * @return bool
         */
        public static function is_path_within( $candidate, $base_dir = null ) {
            if ( empty( $candidate ) || !is_string( $candidate ) ) {
                return false;
            }
            // Reject null-bytes
            if ( strpos( $candidate, "\x00" ) !== false ) {
                return false;
            }
            // Reject stream wrappers and URI schemes (e.g. phar://, php://, file://)
            if ( preg_match( '/^[a-zA-Z0-9\\+\\.\\-]+:\\/\\//', $candidate ) ) {
                return false;
            }
            $normalized_candidate = wp_normalize_path( $candidate );
            if ( empty( $base_dir ) ) {
                $base_dir = self::resolve_upload_root();
            }
            $normalized_base = wp_normalize_path( $base_dir );
            // Resolve real base path
            $real_base = realpath( $normalized_base );
            if ( $real_base === false ) {
                // If base dir doesn't exist yet, check directory structure directly
                $real_base = $normalized_base;
            } else {
                $real_base = wp_normalize_path( $real_base );
            }
            $real_base = rtrim( $real_base, '/' ) . '/';
            // Resolve real candidate path
            $real_candidate = realpath( $normalized_candidate );
            if ( $real_candidate === false ) {
                // If the path contains traversal sequences ('..'), it MUST resolve cleanly with realpath()
                if ( strpos( $normalized_candidate, '..' ) !== false ) {
                    return false;
                }
                // If the file/directory doesn't exist yet, walk up ancestor tree to find closest existing directory
                $check_dir = dirname( $normalized_candidate );
                $real_ancestor = false;
                $unresolved_tail = [];
                while ( !empty( $check_dir ) && $check_dir !== '.' && $check_dir !== '/' && $check_dir !== '\\' ) {
                    $resolved = realpath( $check_dir );
                    if ( $resolved !== false ) {
                        $real_ancestor = wp_normalize_path( $resolved );
                        break;
                    }
                    $unresolved_tail[] = basename( $check_dir );
                    $check_dir = dirname( $check_dir );
                }
                if ( $real_ancestor === false ) {
                    // Fail closed if no existing directory ancestor can be resolved
                    return false;
                }
                // Verify the resolved existing ancestor is strictly within the real base directory
                if ( strpos( $real_ancestor . '/', $real_base ) !== 0 ) {
                    return false;
                }
                // Reconstruct resolved path from existing ancestor + unresolved tail + filename
                $real_candidate = rtrim( $real_ancestor, '/' ) . (( !empty( $unresolved_tail ) ? '/' . implode( '/', array_reverse( $unresolved_tail ) ) : '' )) . '/' . basename( $normalized_candidate );
            } else {
                $real_candidate = wp_normalize_path( $real_candidate );
            }
            return strpos( $real_candidate . '/', $real_base ) === 0;
        }

        /**
         * Authorize user access to an order document
         *
         * Enforces strict authorization:
         * - Admin / Shop Manager: ALLOW
         * - Logged-in Customer matching order customer_id (> 0): ALLOW
         * - Unauthenticated Guest (0 === 0): DENY
         *
         * @param WC_Order|int $order_or_id
         * @param string $format
         * @return bool
         */
        public static function can_access_order_document( $order_or_id, $format = 'a4' ) {
            // 0. Format Validation Barrier
            if ( empty( $format ) || !is_string( $format ) || $format !== sanitize_key( $format ) ) {
                return false;
            }
            $format = sanitize_key( $format );
            $is_supported = class_exists( 'OG_PSR_Document_Registry' ) && OG_PSR_Document_Registry::is_supported_format( $format );
            if ( !$is_supported ) {
            }
            if ( !$is_supported ) {
                return false;
            }
            $order = ( $order_or_id instanceof WC_Order ? $order_or_id : wc_get_order( $order_or_id ) );
            if ( !$order ) {
                return false;
            }
            // 1. Administrative Capability Check
            if ( current_user_can( 'edit_shop_orders' ) || current_user_can( 'manage_woocommerce' ) ) {
                return true;
            }
            // 2. Logged-in Customer Ownership Check
            $current_user_id = get_current_user_id();
            if ( $current_user_id > 0 ) {
                $order_customer_id = (int) $order->get_customer_id();
                if ( $order_customer_id > 0 && $order_customer_id === $current_user_id ) {
                    return true;
                }
            }
            // 3. Guests (current_user_id === 0) or non-matching customers are denied
            return false;
        }

        /**
         * Validate and resolve a local WordPress Media Library attachment ID
         *
         * @param int $attachment_id
         * @return string|false Absolute local file path on success, false on failure.
         */
        public static function resolve_logo_attachment( $attachment_id ) {
            $attachment_id = (int) $attachment_id;
            if ( $attachment_id <= 0 ) {
                return false;
            }
            // Verify attachment is a valid image in WordPress Media Library
            if ( !wp_attachment_is_image( $attachment_id ) ) {
                return false;
            }
            // Explicit MIME type allowlist for safe raster image formats (preventing SVG/XXE vectors in PDF rendering)
            $mime_type = get_post_mime_type( $attachment_id );
            $allowed_mimes = [
                'image/png',
                'image/jpeg',
                'image/jpg',
                'image/gif',
                'image/webp'
            ];
            if ( !$mime_type || !in_array( $mime_type, $allowed_mimes, true ) ) {
                return false;
            }
            $file_path = get_attached_file( $attachment_id );
            if ( empty( $file_path ) || !is_string( $file_path ) ) {
                return false;
            }
            // Verify safe file extension
            $filetype = wp_check_filetype( $file_path );
            $allowed_exts = [
                'png',
                'jpg',
                'jpeg',
                'gif',
                'webp'
            ];
            if ( empty( $filetype['ext'] ) || !in_array( strtolower( $filetype['ext'] ), $allowed_exts, true ) ) {
                return false;
            }
            global $wp_filesystem;
            if ( empty( $wp_filesystem ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }
            if ( !$wp_filesystem || !$wp_filesystem->exists( $file_path ) || $wp_filesystem->is_dir( $file_path ) ) {
                return false;
            }
            // Restrict maximum logo file size to 5MB to prevent memory exhaustion
            $file_size = (int) $wp_filesystem->size( $file_path );
            if ( $file_size <= 0 || $file_size > 5 * 1024 * 1024 ) {
                return false;
            }
            $wp_upload_dir = wp_upload_dir();
            $allowed_root = $wp_upload_dir['basedir'];
            // Validate that the image file resides strictly within WordPress uploads directory
            if ( !self::is_path_within( $file_path, $allowed_root ) ) {
                return false;
            }
            return wp_normalize_path( $file_path );
        }

        /**
         * Ensure storage directory exists and is secured against direct HTTP browsing.
         * Creates Apache 2.2/2.4+ & LiteSpeed .htaccess, Microsoft IIS web.config, and universal index.php.
         *
         * @param string $dir Target directory path. Defaults to plugin upload root.
         * @return bool True if directory exists and is secured, false otherwise.
         */
        public static function ensure_directory_security( $dir = '' ) {
            global $wp_filesystem;
            if ( empty( $wp_filesystem ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }
            if ( empty( $dir ) ) {
                $dir = self::resolve_upload_root();
            }
            $dir = wp_normalize_path( $dir );
            if ( $wp_filesystem ) {
                if ( !$wp_filesystem->exists( $dir ) ) {
                    if ( function_exists( 'wp_mkdir_p' ) ) {
                        wp_mkdir_p( $dir );
                    } elseif ( !$wp_filesystem->mkdir( $dir ) ) {
                        return false;
                    }
                }
                // 1. Apache 2.2 & 2.4+ / LiteSpeed .htaccess
                $htaccess_file = $dir . '/.htaccess';
                if ( !$wp_filesystem->exists( $htaccess_file ) ) {
                    $rules = "# Apache 2.4+ & LiteSpeed\n";
                    $rules .= "<IfModule mod_authz_core.c>\n";
                    $rules .= "    Require all denied\n";
                    $rules .= "</IfModule>\n";
                    $rules .= "# Apache 2.2\n";
                    $rules .= "<IfModule !mod_authz_core.c>\n";
                    $rules .= "    Deny from all\n";
                    $rules .= "</IfModule>\n";
                    $wp_filesystem->put_contents( $htaccess_file, $rules );
                }
                // 2. Microsoft IIS web.config
                $web_config = $dir . '/web.config';
                if ( !$wp_filesystem->exists( $web_config ) ) {
                    $iis_rules = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
                    $iis_rules .= "<configuration>\n";
                    $iis_rules .= "    <system.webServer>\n";
                    $iis_rules .= "        <authorization>\n";
                    $iis_rules .= "            <deny users=\"*\" />\n";
                    $iis_rules .= "        </authorization>\n";
                    $iis_rules .= "    </system.webServer>\n";
                    $iis_rules .= "</configuration>\n";
                    $wp_filesystem->put_contents( $web_config, $iis_rules );
                }
                // 3. Nginx / Universal index.php
                $index_file = $dir . '/index.php';
                if ( !$wp_filesystem->exists( $index_file ) ) {
                    $wp_filesystem->put_contents( $index_file, "<?php\n// Silence is golden.\n" );
                }
                return true;
            }
            return false;
        }

    }

}
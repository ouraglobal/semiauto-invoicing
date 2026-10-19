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
            $subdir = ( class_exists( 'OG_PSR_Document_Registry' ) ? OG_PSR_Document_Registry::get_storage_subdir( $doc_type ) : false );
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
            if ( false !== strpos( $dir, "\x00" ) || preg_match( '/^[a-zA-Z0-9\\+\\.\\-]+:\\/\\//', $dir ) ) {
                return false;
            }
            $upload_root = self::resolve_upload_root();
            $real_upload_root = realpath( $upload_root );
            if ( false === $real_upload_root ) {
                // Ensure upload root exists and re-resolve
                if ( function_exists( 'wp_mkdir_p' ) ) {
                    wp_mkdir_p( $upload_root );
                }
                $real_upload_root = realpath( $upload_root );
                if ( false === $real_upload_root ) {
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
            if ( false === $real_dir || !is_dir( $real_dir ) ) {
                return false;
            }
            $real_dir_normalized = wp_normalize_path( $real_dir );
            $real_dir_slash = rtrim( $real_dir_normalized, '/' ) . '/';
            // Strict directory-boundary containment comparison against trusted upload root
            if ( 0 !== strpos( $real_dir_slash, $real_root_slash ) ) {
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
            if ( false !== strpos( $candidate, "\x00" ) ) {
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
            if ( false === $real_base ) {
                // If base dir doesn't exist yet, check directory structure directly
                $real_base = $normalized_base;
            } else {
                $real_base = wp_normalize_path( $real_base );
            }
            $real_base = rtrim( $real_base, '/' ) . '/';
            // Resolve real candidate path
            $real_candidate = realpath( $normalized_candidate );
            if ( false === $real_candidate ) {
                // If the path contains traversal sequences ('..'), it MUST resolve cleanly with realpath()
                if ( false !== strpos( $normalized_candidate, '..' ) ) {
                    return false;
                }
                // If the file/directory doesn't exist yet, walk up ancestor tree to find closest existing directory
                $check_dir = dirname( $normalized_candidate );
                $real_ancestor = false;
                $unresolved_tail = array();
                while ( !empty( $check_dir ) && '.' !== $check_dir && '/' !== $check_dir && '\\' !== $check_dir ) {
                    $resolved = realpath( $check_dir );
                    if ( false !== $resolved ) {
                        $real_ancestor = wp_normalize_path( $resolved );
                        break;
                    }
                    $unresolved_tail[] = basename( $check_dir );
                    $check_dir = dirname( $check_dir );
                }
                if ( false === $real_ancestor ) {
                    // Fail closed if no existing directory ancestor can be resolved
                    return false;
                }
                // Verify the resolved existing ancestor is strictly within the real base directory
                if ( 0 !== strpos( $real_ancestor . '/', $real_base ) ) {
                    return false;
                }
                // Reconstruct resolved path from existing ancestor + unresolved tail + filename
                $real_candidate = rtrim( $real_ancestor, '/' ) . (( !empty( $unresolved_tail ) ? '/' . implode( '/', array_reverse( $unresolved_tail ) ) : '' )) . '/' . basename( $normalized_candidate );
            } else {
                $real_candidate = wp_normalize_path( $real_candidate );
            }
            return 0 === strpos( $real_candidate . '/', $real_base );
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
            if ( empty( $format ) || !is_string( $format ) || sanitize_key( $format ) !== $format ) {
                return false;
            }
            $format = sanitize_key( $format );
            $is_supported = class_exists( 'OG_PSR_Document_Registry' ) && OG_PSR_Document_Registry::is_supported_format( $format );
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
            $allowed_mimes = array(
                'image/png',
                'image/jpeg',
                'image/jpg',
                'image/gif',
                'image/webp'
            );
            if ( !$mime_type || !in_array( $mime_type, $allowed_mimes, true ) ) {
                return false;
            }
            $file_path = get_attached_file( $attachment_id );
            if ( empty( $file_path ) || !is_string( $file_path ) ) {
                return false;
            }
            // Verify safe file extension
            $filetype = wp_check_filetype( $file_path );
            $allowed_exts = array(
                'png',
                'jpg',
                'jpeg',
                'gif',
                'webp'
            );
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
        public static function is_protection_file_valid( $dir, $type ) {
            if ( empty( $dir ) || !is_string( $dir ) || !in_array( $type, array('htaccess', 'webconfig'), true ) ) {
                return false;
            }
            global $wp_filesystem;
            if ( empty( $wp_filesystem ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                if ( !WP_Filesystem() ) {
                    return false;
                }
            }
            if ( empty( $wp_filesystem ) ) {
                return false;
            }
            $dir = wp_normalize_path( $dir );
            $file = ( 'htaccess' === $type ? $dir . '/.htaccess' : $dir . '/web.config' );
            if ( !$wp_filesystem->exists( $file ) || $wp_filesystem->is_dir( $file ) ) {
                return false;
            }
            $contents = $wp_filesystem->get_contents( $file );
            if ( !is_string( $contents ) || '' === trim( $contents ) ) {
                return false;
            }
            if ( 'htaccess' === $type ) {
                // Ignore commented examples; only active Apache deny directives count.
                $active_contents = preg_replace( '/^[ \\t]*#.*$/m', '', $contents );
                return (bool) preg_match( '/(^|\\n)\\s*Require\\s+all\\s+denied\\b/im', $active_contents ) || (bool) preg_match( '/(^|\\n)\\s*Deny\\s+from\\s+all\\b/im', $active_contents );
            }
            // IIS URL Authorization uses an <add accessType="Deny" users="*" /> rule.
            // Ignore XML comments so commented-out examples cannot be mistaken for active rules.
            $contents = preg_replace( '/<!--.*?-->/s', '', $contents );
            $iis_rule_pattern = '/<\\s*security\\s*>.*?<\\s*authorization\\s*>.*?' . '<\\s*add\\b[^>]*\\baccessType\\s*=\\s*["\']Deny["\']' . '[^>]*\\busers\\s*=\\s*["\']\\s*\\*["\'][^>]*\\/?\\s*>' . '.*?<\\/\\s*authorization\\s*>.*?<\\/\\s*security\\s*>/is';
            return (bool) preg_match( $iis_rule_pattern, $contents );
        }

        public static function ensure_directory_security( $dir = '' ) {
            global $wp_filesystem;
            if ( empty( $wp_filesystem ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                if ( !WP_Filesystem() ) {
                    return false;
                }
            }
            if ( empty( $wp_filesystem ) ) {
                return false;
            }
            if ( empty( $dir ) ) {
                $dir = self::resolve_upload_root();
            }
            if ( !is_string( $dir ) || '' === trim( $dir ) || false !== strpos( $dir, "\x00" ) ) {
                return false;
            }
            $dir = wp_normalize_path( $dir );
            if ( !$wp_filesystem->is_dir( $dir ) ) {
                if ( function_exists( 'wp_mkdir_p' ) ) {
                    wp_mkdir_p( $dir );
                } else {
                    $wp_filesystem->mkdir( $dir );
                }
            }
            if ( !$wp_filesystem->is_dir( $dir ) ) {
                return false;
            }
            // Write each missing protection file and explicitly check each write result.
            $htaccess_write_failed = false;
            $web_config_write_failed = false;
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
                $htaccess_written = $wp_filesystem->put_contents( $htaccess_file, $rules, FS_CHMOD_FILE );
                if ( !$htaccess_written ) {
                    // The relevant server-specific verification below will fail if this write was needed.
                    $htaccess_write_failed = true;
                }
            }
            $web_config = $dir . '/web.config';
            $iis_rules = '<' . '?xml version="1.0" encoding="UTF-8"?' . ">\n";
            $iis_rules .= "<configuration>\n";
            $iis_rules .= "    <system.webServer>\n";
            $iis_rules .= "        <security>\n";
            $iis_rules .= "            <authorization>\n";
            $iis_rules .= "                <add accessType=\"Deny\" users=\"*\" />\n";
            $iis_rules .= "            </authorization>\n";
            $iis_rules .= "        </security>\n";
            $iis_rules .= "    </system.webServer>\n";
            $iis_rules .= "</configuration>\n";
            if ( !$wp_filesystem->exists( $web_config ) ) {
                $web_config_written = $wp_filesystem->put_contents( $web_config, $iis_rules, FS_CHMOD_FILE );
                if ( !$web_config_written ) {
                    // The active server's protection is checked below; diagnostics will not claim IIS is protected.
                    $web_config_write_failed = true;
                }
            } elseif ( !self::is_protection_file_valid( $dir, 'webconfig' ) ) {
                // Migrate the previous plugin-generated IIS rule only when its exact legacy block is present.
                $existing_web_config = $wp_filesystem->get_contents( $web_config );
                $legacy_block = '/<system\\.webServer>\\s*<authorization>\\s*' . '<deny\\s+users=["\']\\*["\']\\s*\\/>\\s*' . '<\\/authorization>\\s*<\\/system\\.webServer>/i';
                if ( is_string( $existing_web_config ) && preg_match( $legacy_block, $existing_web_config ) ) {
                    $replacement = '<system.webServer><security><authorization>' . '<add accessType="Deny" users="*" />' . '</authorization></security></system.webServer>';
                    $migrated_web_config = preg_replace(
                        $legacy_block,
                        $replacement,
                        $existing_web_config,
                        1
                    );
                    $web_config_written = is_string( $migrated_web_config ) && $wp_filesystem->put_contents( $web_config, $migrated_web_config, FS_CHMOD_FILE );
                    if ( !$web_config_written ) {
                        $web_config_write_failed = true;
                    }
                }
            }
            $index_file = $dir . '/index.php';
            if ( !$wp_filesystem->exists( $index_file ) ) {
                $index_written = $wp_filesystem->put_contents( $index_file, "<?php\n// Silence is golden.\n", FS_CHMOD_FILE );
                if ( !$index_written ) {
                    // index.php is defense-in-depth; do not treat it as a substitute for deny rules.
                    if ( class_exists( 'OG_PSR_Logger' ) ) {
                        OG_PSR_Logger::warning( 'Could not create the storage directory index fallback.' );
                    }
                }
            }
            $server_software = ( isset( $_SERVER['SERVER_SOFTWARE'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) ) : '' );
            $is_nginx = false !== strpos( $server_software, 'nginx' );
            $is_iis = false !== strpos( $server_software, 'microsoft-iis' ) || false !== strpos( $server_software, 'iis' );
            $is_apache = false !== strpos( $server_software, 'apache' ) || false !== strpos( $server_software, 'litespeed' );
            if ( $is_nginx ) {
                // Nginx ignores both .htaccess and web.config. Require explicit site-level
                // confirmation after the documented server-level deny rule has been configured.
                // Developers can confirm this with the ogpsr_storage_protection_confirmed filter.
                return (bool) apply_filters( 'ogpsr_storage_protection_confirmed', false, self::resolve_upload_root() );
            }
            if ( $is_iis ) {
                return !$web_config_write_failed && self::is_protection_file_valid( $dir, 'webconfig' );
            }
            if ( $is_apache ) {
                return !$htaccess_write_failed && self::is_protection_file_valid( $dir, 'htaccess' );
            }
            // Unknown server: the plugin cannot establish which configuration file is enforced.
            // Require explicit site-level confirmation instead of treating file presence as protection.
            return (bool) apply_filters( 'ogpsr_storage_protection_confirmed', false, self::resolve_upload_root() );
        }

    }

}
<?php
/**
 * Universal Template Registry Class
 * 
 * Registers available Universal Document presentation themes and resolves absolute file paths.
 * Part of the Free Core Document Engine architecture.
 * 
 * @package SemiAutoInvoicing
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('OG_PSR_Template_Registry')) {
    class OG_PSR_Template_Registry
    {
        /**
         * Get registered document themes array.
         * 
         * @return array Map of theme slug => theme title
         */
        public static function get_registered_themes()
        {
            $themes = [
                'modern-slate' => __('Modern Corporate Slate (Default)', 'semiauto-invoicing'),
            ];

            return apply_filters('ogpsr_registered_document_themes', $themes);
        }

        /**
         * Get active template theme slug from settings.
         * 
         * @return string Theme slug
         */
        public static function get_active_theme_slug()
        {
            $registered = self::get_registered_themes();
            $theme      = OG_PSR_Settings::get('ogpsr_document_theme', 'modern-slate');
            
            return array_key_exists($theme, $registered) ? $theme : 'modern-slate';
        }

        /**
         * Resolve absolute path to presentation template file for theme.
         * 
         * @param string $theme_slug Optional theme slug (defaults to active setting).
         * @return string Absolute file path to template file.
         */
        public static function get_template_path($theme_slug = '')
        {
            if (empty($theme_slug)) {
                $theme_slug = self::get_active_theme_slug();
            }

            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }

            $themes_dir      = wp_normalize_path(OG_PSR_PATH . 'templates/themes/');
            $custom_template = wp_normalize_path($themes_dir . sanitize_key($theme_slug) . '/document-template.php');
            if ($wp_filesystem && $wp_filesystem->exists($custom_template) && class_exists('OG_PSR_Security') && OG_PSR_Security::is_path_within($custom_template, $themes_dir)) {
                return $custom_template;
            }

            // Fallback to default modern-slate theme
            $fallback_template = wp_normalize_path($themes_dir . 'modern-slate/document-template.php');
            if ($wp_filesystem && $wp_filesystem->exists($fallback_template)) {
                return $fallback_template;
            }

            // Safe fallback
            return $fallback_template;
        }

        /**
         * Compile layout declarations and dynamic typography rules for headless PDF document rendering.
         * Returns pure CSS content without HTML tags for programmatic Dompdf injection.
         * 
         * @param array $doc Document context view-model array.
         * @return string Pure sanitized CSS declarations.
         */
        public static function compile_document_rules($doc = [])
        {
            // Internal layout definitions loaded strictly from plugin assets via WP_Filesystem (Zero arbitrary user CSS)
            $base_rules = '';
            $css_file_path = defined('OG_PSR_PATH') ? OG_PSR_PATH . 'assets/css/ogpsr-document-template.css' : dirname(dirname(__DIR__)) . '/assets/css/ogpsr-document-template.css';

            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }

            if ($wp_filesystem && $wp_filesystem->exists($css_file_path)) {
                $base_rules = (string) $wp_filesystem->get_contents($css_file_path);
            }

            $is_thermal     = !empty($doc['is_thermal']);
            
            $font_family    = class_exists('OG_PSR_Document_Registry') 
                ? OG_PSR_Document_Registry::sanitize_font_family($doc['font_family'] ?? '') 
                : 'DejaVu Sans';
            
            $accent_color   = sanitize_hex_color($doc['accent_color'] ?? ($doc['brand_color'] ?? '#1e293b')) ?: '#1e293b';
            $font_color     = sanitize_hex_color($doc['font_color'] ?? '#1e293b') ?: '#1e293b';
            $font_size      = min(24, max(6, absint($doc['font_size'] ?? 9)));
            $line_height    = min(3.0, max(1.0, (float) ($doc['line_height'] ?? 1.2)));

            $thermal_page_rules = $is_thermal 
                ? "@page { margin: 2mm !important; }\nbody { padding: 0 !important; margin: 0 !important; }"
                : "@page { size: a4 portrait; margin: 10mm !important; }\nbody { padding: 0 !important; margin: 0 !important; }";

            $body_typography = "font-size: " . $font_size . "pt; line-height: " . $line_height . ";";

            return $base_rules . "\n" .
                   $thermal_page_rules . "\n" .
                   "body { font-family: \"" . esc_attr($font_family) . "\", \"DejaVu Sans\", sans-serif; color: " . esc_attr($font_color) . "; " . $body_typography . " }\n" .
                   ".ogpsr-brand-bg { background-color: " . esc_attr($accent_color) . " !important; color: #ffffff !important; }\n" .
                   ".ogpsr-brand-text { color: " . esc_attr($accent_color) . " !important; }";
        }
    }
}

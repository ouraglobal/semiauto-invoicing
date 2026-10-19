<?php

/**
 * Settings Tab Custom Field Renderers and Callbacks
 * 
 * Handles rendering for System Status, Image Upload, and Feature Showcase Banners.
 * 
 * @package SemiAutoInvoicing
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( !class_exists( 'OG_PSR_Settings_Renderers' ) ) {
    class OG_PSR_Settings_Renderers {
        /**
         * Renders the Status & Diagnostics UI.
         */
        public static function render_system_status_field() {
            $report = OG_PSR_Status_Diagnostics::get_system_report();
            $logs = OG_PSR_Logger::get_recent_logs( 50 );
            ?>
            <tr valign="top">
                <td colspan="2" class="ogpsr-padding-0">
                    <div class="ogpsr-status-container">
                        <div class="ogpsr-status-grid">
                            <?php 
            $icons = [
                'wp_environment'      => 'wordpress',
                'store_configuration' => 'store',
                'environment'         => 'performance',
                'document_engine'     => 'admin-plugins',
                'compliance'          => 'shield',
                'peppol_gateways'     => 'cloud',
            ];
            foreach ( $report as $group_key => $group ) {
                $icon = $icons[$group_key] ?? 'info';
                ?>
                                <div class="ogpsr-status-card">
                                    <h3><span class="dashicons dashicons-<?php 
                echo esc_attr( $icon );
                ?>"></span>
                                        <?php 
                echo esc_html( $group['label'] );
                ?></h3>
                                    <table class="ogpsr-status-table">
                                        <?php 
                foreach ( $group['data'] as $item ) {
                    ?>
                                            <tr>
                                                <td>
                                                    <?php 
                    echo esc_html( $item['label'] );
                    ?>
                                                    <?php 
                    if ( !empty( $item['desc'] ) ) {
                        ?>
                                                        <?php 
                        echo ( function_exists( 'wc_help_tip' ) ? wp_kses_post( wc_help_tip( $item['desc'] ) ) : '<span class="woocommerce-help-tip" data-tip="' . esc_attr( $item['desc'] ) . '"></span>' );
                        ?>
                                                    <?php 
                    }
                    ?>
                                                </td>
                                                <td>
                                                    <?php 
                    if ( isset( $item['status'] ) ) {
                        ?>
                                                        <span
                                                            class="ogpsr-status-badge ogpsr-status-badge--<?php 
                        echo esc_attr( $item['status'] );
                        ?>">
                                                            <?php 
                        echo esc_html( $item['value'] );
                        ?>
                                                        </span>
                                                    <?php 
                    } else {
                        ?>
                                                        <?php 
                        echo esc_html( $item['value'] );
                        ?>
                                                    <?php 
                    }
                    ?>
                                                </td>
                                            </tr>
                                        <?php 
                }
                ?>
                                    </table>
                                </div>
                            <?php 
            }
            ?>
                        </div>

                        <!-- Live Log Console -->
                        <div class="ogpsr-status-card ogpsr-mt-20">
                            <h3><span class="dashicons dashicons-media-text"></span>
                                <?php 
            esc_html_e( 'Live Activity Logs', 'semiauto-invoicing' );
            ?></h3>
                            <p class="description">
                                <?php 
            esc_html_e( 'Last 50 events captured by the document engine.', 'semiauto-invoicing' );
            ?>
                            </p>

                            <div class="ogpsr-log-console">
                                <pre><?php 
            echo esc_html( $logs );
            ?></pre>
                            </div>

                            <div class="ogpsr-tools-flex">
                                <a href="<?php 
            echo esc_url( admin_url( 'admin.php?page=wc-status&tab=logs' ) );
            ?>"
                                    class="button button-secondary" target="_blank">
                                    <span class="dashicons dashicons-external"></span>
                                    <?php 
            esc_html_e( 'View Full WooCommerce Logs', 'semiauto-invoicing' );
            ?>
                                </a>
                                <button type="button" class="button button-secondary ogpsr-purge-cache-btn" data-type="all">
                                    <span class="dashicons dashicons-trash"></span>
                                    <?php 
            esc_html_e( 'Purge Cache', 'semiauto-invoicing' );
            ?>
                                </button>
                            </div>
                        </div>

                        <!-- Compliance & Legal Disclaimer -->
                        <?php 
            self::render_compliance_disclaimer_card();
            ?>
                    </div>
                </td>
            </tr>
            <?php 
        }

        /**
         * Render Compliance Assistant & Legal Disclaimer Field (for Storage tab and others)
         *
         * @param array $args
         */
        public static function render_compliance_disclaimer_field( $args = [] ) {
            ?>
            <tr valign="top">
                <td colspan="2" class="ogpsr-padding-0">
                    <?php 
            self::render_compliance_disclaimer_card();
            ?>
                </td>
            </tr>
            <?php 
        }

        /**
         * Render Compliance Assistant & Legal Disclaimer Card with Privacy Policy links.
         */
        public static function render_compliance_disclaimer_card() {
            $privacy_guide_url = admin_url( 'options-privacy.php?tab=policyguide' );
            $privacy_policy_url = ( function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '' );
            ?>
            <div class="ogpsr-status-card ogpsr-compliance-disclaimer-card ogpsr-mt-20">
                <div class="ogpsr-flex-between ogpsr-mb-10">
                    <h3 class="ogpsr-m-0">
                        <span class="dashicons dashicons-shield ogpsr-color-primary ogpsr-mr-5"></span>
                        <?php 
            esc_html_e( 'Compliance Assistant & Legal Disclaimer', 'semiauto-invoicing' );
            ?>
                    </h3>
                    <span class="ogpsr-status-badge ogpsr-status-badge--ok">
                        <span class="dashicons dashicons-yes ogpsr-v-middle"></span>
                        <?php 
            esc_html_e( 'GDPR & Privacy Aligned', 'semiauto-invoicing' );
            ?>
                    </span>
                </div>
                <p class="description ogpsr-mb-15">
                    <?php 
            esc_html_e( 'SemiAuto Invoicing provides software tools designed to act as a compliance assistant for merchants generating standards-aligned receipts, invoices, and e-invoicing documents. This plugin does not constitute legal, tax, or regulatory advice and does not guarantee regulatory compliance. Store owners remain solely responsible for ensuring that their invoicing and tax workflows satisfy all applicable statutory requirements in their respective jurisdictions.', 'semiauto-invoicing' );
            ?>
                </p>
                <div class="ogpsr-tools-flex ogpsr-compliance-links">
                    <button type="button" class="button button-primary ogpsr-read-privacy-policy-btn">
                        <span class="dashicons dashicons-book-alt ogpsr-v-middle"></span>
                        <?php 
            esc_html_e( 'Read Plugin Privacy Policy & Retention Disclosures', 'semiauto-invoicing' );
            ?>
                    </button>
                    <a href="<?php 
            echo esc_url( $privacy_guide_url );
            ?>" target="_blank" class="button button-secondary">
                        <span class="dashicons dashicons-privacy ogpsr-v-middle"></span>
                        <?php 
            esc_html_e( 'WordPress Privacy Guide', 'semiauto-invoicing' );
            ?>
                    </a>
                    <?php 
            if ( !empty( $privacy_policy_url ) ) {
                ?>
                        <a href="<?php 
                echo esc_url( $privacy_policy_url );
                ?>" target="_blank" class="button button-secondary">
                            <span class="dashicons dashicons-external ogpsr-v-middle"></span>
                            <?php 
                esc_html_e( 'View Store Privacy Policy', 'semiauto-invoicing' );
                ?>
                        </a>
                    <?php 
            }
            ?>
                </div>
            </div>
            <?php 
            self::render_privacy_policy_modal();
        }

        /**
         * Render Privacy Policy & Data Retention Disclosures Modal.
         */
        public static function render_privacy_policy_modal() {
            static $rendered = false;
            if ( $rendered ) {
                return;
            }
            $rendered = true;
            $policy_html = ( class_exists( 'OG_PSR_Privacy' ) ? OG_PSR_Privacy::get_privacy_policy_html() : '' );
            ?>
            <div id="ogpsr-privacy-policy-modal" class="ogpsr-modal-backdrop ogpsr-hidden">
                <div class="ogpsr-sync-modal ogpsr-privacy-modal" role="dialog" aria-modal="true">
                    <div class="ogpsr-modal-header">
                        <div class="ogpsr-modal-header-icon">
                            <span class="dashicons dashicons-shield"></span>
                        </div>
                        <div class="ogpsr-modal-header-text">
                            <h3><?php 
            esc_html_e( 'SemiAuto Invoicing — Privacy & Data Retention Policy', 'semiauto-invoicing' );
            ?></h3>
                            <p class="ogpsr-modal-subtitle"><?php 
            esc_html_e( 'Statutory compliance, GDPR retention, and document security disclosures.', 'semiauto-invoicing' );
            ?></p>
                        </div>
                        <button type="button" class="ogpsr-modal-close ogpsr-close-privacy-modal-btn" aria-label="<?php 
            esc_attr_e( 'Close', 'semiauto-invoicing' );
            ?>">&times;</button>
                    </div>
                    <div class="ogpsr-modal-body ogpsr-privacy-modal-body">
                        <div class="ogpsr-privacy-content">
                            <?php 
            echo wp_kses_post( $policy_html );
            ?>
                        </div>
                    </div>
                    <div class="ogpsr-modal-footer ogpsr-flex-between">
                        <a href="<?php 
            echo esc_url( admin_url( 'options-privacy.php?tab=policyguide' ) );
            ?>" target="_blank" class="button button-secondary">
                            <span class="dashicons dashicons-external ogpsr-v-middle"></span>
                            <?php 
            esc_html_e( 'Open in WP Privacy Guide', 'semiauto-invoicing' );
            ?>
                        </a>
                        <button type="button" class="button button-primary ogpsr-close-privacy-modal-btn">
                            <?php 
            esc_html_e( 'Done', 'semiauto-invoicing' );
            ?>
                        </button>
                    </div>
                </div>
            </div>
            <?php 
        }

        /**
         * Render Image Upload Field
         * 
         * @param array $args
         */
        public static function render_image_upload_field( $args ) {
            $id = esc_attr( $args['id'] );
            $label = esc_html( $args['name'] );
            $desc = ( isset( $args['desc'] ) ? wp_kses_post( $args['desc'] ) : '' );
            $attachment_id = (int) OG_PSR_Settings::get( 'ogpsr_logo_attachment_id', 0 );
            $preview_src = '';
            if ( $attachment_id > 0 ) {
                $img = wp_get_attachment_image_src( $attachment_id, 'medium' );
                if ( $img ) {
                    $preview_src = $img[0];
                }
            }
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php 
            echo esc_attr( $id );
            ?>"><?php 
            echo esc_html( $label );
            ?></label>
                </th>
                <td class="forminp forminp-image">
                    <input type="hidden" name="ogpsr_logo_attachment_id" id="ogpsr_logo_attachment_id" value="<?php 
            echo esc_attr( ( $attachment_id > 0 ? (string) $attachment_id : '' ) );
            ?>">
                    
                    <div class="ogpsr-logo-preview-wrapper ogpsr-mb-10<?php 
            echo ( empty( $preview_src ) ? ' ogpsr-hide' : '' );
            ?>">
                        <img id="ogpsr_logo_preview_img" src="<?php 
            echo esc_url( $preview_src );
            ?>" class="ogpsr-logo-preview-img" alt="">
                    </div>

                    <div class="ogpsr-flex-align-center ogpsr-gap-10">
                        <button type="button" class="button ogpsr-image-upload-button" data-target="ogpsr_logo_attachment_id" data-attachment-target="ogpsr_logo_attachment_id">
                            <?php 
            esc_html_e( 'Select / Upload Image', 'semiauto-invoicing' );
            ?>
                        </button>
                        <button type="button" id="ogpsr_remove_logo_btn" class="button ogpsr-btn-remove-logo<?php 
            echo ( empty( $preview_src ) ? ' ogpsr-hide' : '' );
            ?>">
                            <?php 
            esc_html_e( 'Remove Logo', 'semiauto-invoicing' );
            ?>
                        </button>
                    </div>
                    <?php 
            if ( $desc ) {
                ?>
                        <p class="description"><?php 
                echo wp_kses_post( $desc );
                ?></p>
                    <?php 
            }
            ?>
                </td>
            </tr>
            <?php 
        }

        /**
         * Renders the Feature Showcase & Capabilities Banner on 100% PRO tabs in Free tier.
         *
         * @param array $value Field config array with 'showcase' key.
         */
        public static function render_showcase_banner_field( $value ) {
            $showcase = sanitize_key( $value['showcase'] ?? 'general' );
            $upgrade_url = ( function_exists( 'ogpsr_fs' ) ? ogpsr_fs()->get_upgrade_url() : 'https://ouraglobal.ae/semiauto-invoicing/#pricing' );
            $showcases = [
                'xml'      => [
                    'icon'     => 'dashicons-shield',
                    'title'    => __( 'Global E-Invoicing & Peppol Compliance Assistant', 'semiauto-invoicing' ),
                    'subtitle' => __( 'Compliance assistant designed to help store owners generate standards-aligned XML billing for EU, Germany, France, Japan, Singapore, Malaysia, Australia, New Zealand & UAE.', 'semiauto-invoicing' ),
                    'features' => [
                        __( 'Assists with Peppol BIS Billing 3.0 & EU PINT electronic invoice standards', 'semiauto-invoicing' ),
                        __( 'Dynamic E-Invoicing Profile Routing: Automatically determines recipient jurisdiction and switches XML format between BIS 3.0, PINT, Factur-X & XRechnung', 'semiauto-invoicing' ),
                        __( 'Assists with Hybrid PDF/A-3 (Factur-X 1.09.2 & ZUGFeRD 2.5.2) embedded XML generation across all 5 profiles (MINIMUM to EXTENDED)', 'semiauto-invoicing' ),
                        __( 'Assists with French Chorus Pro B2G (EDI Rules S2.19 & G3.01) & German XRechnung 3.0.2 format specifications', 'semiauto-invoicing' ),
                        __( 'Assists with Malaysia PINT MY (LHDNM MyInvois v1.3.1), UAE PINT AE FTA guidelines & AUNZ / Japan / Singapore PINT UBL specifications', 'semiauto-invoicing' ),
                        __( 'Pre-Flight Validation Assistant: Validates Peppol Endpoint IDs across 64 canonical EAS schemes with checksum algorithms (ABN, NZBN, JCT, TRN) and plain-language guidance', 'semiauto-invoicing' ),
                        __( 'Compliance assistant mappings: Tax Class, UNCL 4461 Payment & UN/ECE Unit of Measure', 'semiauto-invoicing' )
                    ],
                ],
                'barcode'  => [
                    'icon'     => 'dashicons-tag',
                    'title'    => __( 'Automated Barcodes & QR Tracking', 'semiauto-invoicing' ),
                    'subtitle' => __( 'Embed high-density scan codes and instant order tracking on receipts and invoices.', 'semiauto-invoicing' ),
                    'features' => [
                        __( 'Alphanumeric Code 128 & GS1 GTIN/UPC EAN-13 Barcode engines', 'semiauto-invoicing' ),
                        __( 'Dynamic QR codes linking directly to customer order status & invoice verification', 'semiauto-invoicing' ),
                        __( 'Custom text, tracking URL, and tokenized order number encodings', 'semiauto-invoicing' ),
                        __( 'Vector-crisp SVG/PNG rendering optimized for POS thermal & laser print output', 'semiauto-invoicing' )
                    ],
                ],
                'design'   => [
                    'icon'     => 'dashicons-art',
                    'title'    => __( 'Design Studio & Document Customization', 'semiauto-invoicing' ),
                    'subtitle' => __( 'Complete typography, branding colors, and document styling controls.', 'semiauto-invoicing' ),
                    'features' => [
                        __( 'International font sets including DejaVu Sans with Arabic & Asian glyph support', 'semiauto-invoicing' ),
                        __( 'Thermal POS monospaced typography & custom line height adjustments', 'semiauto-invoicing' ),
                        __( 'Custom brand accent colors, font colors, and high-fidelity preview canvases', 'semiauto-invoicing' ),
                        __( 'Interactive Live Preview iframe that renders template tweaks in real-time', 'semiauto-invoicing' )
                    ],
                ],
                'extras'   => [
                    'icon'     => 'dashicons-cart',
                    'title'    => __( 'Custom Fees & Enterprise Add-ons', 'semiauto-invoicing' ),
                    'subtitle' => __( 'Add recurring or conditional checkout surcharges, packaging fees, and gratuities.', 'semiauto-invoicing' ),
                    'features' => [__( 'Dynamic custom checkout fees repeater with fixed or percentage calculations', 'semiauto-invoicing' ), __( 'Taxable fee integration mapped automatically to Peppol XML line items', 'semiauto-invoicing' ), __( 'Purchase Order (PO) checkout tracking and sequential invoice numbering', 'semiauto-invoicing' )],
                ],
                'gateways' => [
                    'icon'     => 'dashicons-cloud',
                    'title'    => __( 'Peppol Access Point & Multi-Gateway Integration (StoreCove & A-Cube)', 'semiauto-invoicing' ),
                    'subtitle' => __( 'Direct automated cloud transmission to government tax portals and enterprise buyers via StoreCove and A-Cube Peppol API.', 'semiauto-invoicing' ),
                    'features' => [
                        __( 'Multi-Gateway architecture supporting StoreCove and A-Cube Peppol API', 'semiauto-invoicing' ),
                        __( 'Automated UBL 2.1 XML and JSON mode transmission on order completion', 'semiauto-invoicing' ),
                        __( 'Live transmission status tracking (Delivered, Accepted, Rejected, Cleared) inside order metabox', 'semiauto-invoicing' ),
                        __( 'Interactive Legal Entity selection & Hydra API discovery for multi-entity businesses', 'semiauto-invoicing' )
                    ],
                ],
                'storage'  => [
                    'icon'     => 'dashicons-database',
                    'title'    => __( 'Document Storage & Automated Retention', 'semiauto-invoicing' ),
                    'subtitle' => __( 'Automated retention schedules, multi-format file management, and bulk ZIP archiving.', 'semiauto-invoicing' ),
                    'features' => [
                        __( 'Configurable document retention periods with automatic background purging', 'semiauto-invoicing' ),
                        __( 'Multi-tab document explorer (Thermal receipts, A4 invoices, credit notes & XML)', 'semiauto-invoicing' ),
                        __( 'One-click bulk ZIP archive generation and secure admin export', 'semiauto-invoicing' ),
                        __( 'Granular single and bulk document deletion with storage size meters', 'semiauto-invoicing' )
                    ],
                ],
            ];
            $info = $showcases[$showcase] ?? $showcases['xml'];
            ?>
            <div class="ogpsr-showcase-banner">
                <div class="ogpsr-showcase-banner__glow"></div>
                <div class="ogpsr-showcase-banner__content">
                    <div class="ogpsr-showcase-banner__header">
                        <span class="dashicons <?php 
            echo esc_attr( $info['icon'] );
            ?> ogpsr-showcase-banner__icon"></span>
                        <div>
                            <h2 class="ogpsr-showcase-banner__title"><?php 
            echo esc_html( $info['title'] );
            ?></h2>
                            <p class="ogpsr-showcase-banner__subtitle"><?php 
            echo esc_html( $info['subtitle'] );
            ?></p>
                        </div>
                    </div>
                    <ul class="ogpsr-showcase-banner__features ogpsr-showcase-banner__feature-list">
                        <?php 
            foreach ( $info['features'] as $feat ) {
                ?>
                            <li><span class="dashicons dashicons-yes-alt ogpsr-showcase-banner__check ogpsr-text-success"></span> <?php 
                echo esc_html( $feat );
                ?></li>
                        <?php 
            }
            ?>
                    </ul>
                    <div class="ogpsr-showcase-banner__action ogpsr-showcase-banner__footer">
                        <a href="<?php 
            echo esc_url( $upgrade_url );
            ?>" target="_blank" rel="noopener noreferrer" 
                            class="button button-primary button-hero ogpsr-showcase-banner__btn">
                            <span class="dashicons dashicons-star-filled ogpsr-v-middle"></span>
                            <?php 
            esc_html_e( 'Explore Pro Features', 'semiauto-invoicing' );
            ?>
                        </a>
                        <span class="ogpsr-showcase-banner__guarantee">
                            <span class="dashicons dashicons-lock ogpsr-v-middle"></span>
                            <?php 
            esc_html_e( 'The Pro version provides advanced compliance assistance, automated XML generation, and cross-border standards support.', 'semiauto-invoicing' );
            ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php 
        }

        /**
         * Renders a Compact Feature Showcase Banner at the bottom of core settings tabs.
         *
         * @param array $value Field config array.
         */
        public static function render_compact_showcase_banner_field( $value ) {
            $showcase = sanitize_key( $value['showcase'] ?? 'general' );
            $upgrade_url = ( function_exists( 'ogpsr_fs' ) ? ogpsr_fs()->get_upgrade_url() : 'https://ouraglobal.ae/semiauto-invoicing/#pricing' );
            $premium_link = '<a href="' . esc_url( $upgrade_url ) . '" target="_blank" rel="noopener noreferrer" class="ogpsr-showcase-link">' . esc_html__( 'Premium version', 'semiauto-invoicing' ) . '</a>';
            $compact_data = [
                'thermal' => [
                    'icon'  => 'dashicons-printer',
                    'title' => __( 'Thermal POS Pro Add-ons', 'semiauto-invoicing' ),
                    'desc'  => sprintf( 
                        /* translators: %s: Hyperlink to Premium version */
                        __( 'Kitchen companion slips, cashier staff rosters & overrides, automated receipt reminders, sequential receipt numbering, and cash drawer pulse triggers are available in the %s.', 'semiauto-invoicing' ),
                        $premium_link
                     ),
                ],
                'a4'      => [
                    'icon'  => 'dashicons-pdf',
                    'title' => __( 'A4 Documents Pro Add-ons', 'semiauto-invoicing' ),
                    'desc'  => sprintf( 
                        /* translators: %s: Hyperlink to Premium version */
                        __( 'Automated Credit Notes on refunds, continuous sequential invoice numbering, Purchase Order (PO) checkout fields, and online payment links are available in the %s.', 'semiauto-invoicing' ),
                        $premium_link
                     ),
                ],
            ];
            $item = $compact_data[$showcase] ?? [
                'icon'  => 'dashicons-star-filled',
                'title' => __( 'Premium Document Capabilities', 'semiauto-invoicing' ),
                'desc'  => sprintf( 
                    /* translators: %s: Hyperlink to Premium version */
                    __( 'Explore the %s for advanced compliance assistance, document automation & global e-invoicing standards.', 'semiauto-invoicing' ),
                    $premium_link
                 ),
            ];
            ?>
            <tr valign="top" class="ogpsr-compact-showcase-row">
                <td colspan="2" class="ogpsr-padding-0">
                    <div class="ogpsr-compact-showcase-banner">
                        <div class="ogpsr-compact-showcase-banner__left">
                            <div class="ogpsr-compact-showcase-banner__icon-wrap">
                                <span class="dashicons <?php 
            echo esc_attr( $item['icon'] );
            ?>"></span>
                            </div>
                            <div class="ogpsr-compact-showcase-banner__content">
                                <div class="ogpsr-compact-showcase-banner__title-row">
                                    <span class="ogpsr-pro-badge"><?php 
            esc_html_e( 'PRO', 'semiauto-invoicing' );
            ?></span>
                                    <strong><?php 
            echo esc_html( $item['title'] );
            ?></strong>
                                </div>
                                <p class="ogpsr-compact-showcase-banner__desc"><?php 
            echo wp_kses_post( $item['desc'] );
            ?></p>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>
            <?php 
        }

        /**
         * Render settings tab live preview column (Header, device switcher, canvas).
         *
         * @param string $section Active section slug.
         */
        public static function render_live_preview_column( $section ) {
            $is_a4_tab = in_array( $section, ['a4', 'packaging'], true );
            $is_xml_tab = $section === 'xml';
            echo '<div class="ogpsr-admin-preview-column" id="ogpsr-live-preview">';
            echo '<div class="ogpsr-preview-header">';
            echo esc_html__( 'Live Preview', 'semiauto-invoicing' );
            echo '<div class="ogpsr-device-switcher">';
            echo '<button type="button" class="ogpsr-device-btn ' . (( !$is_a4_tab && !$is_xml_tab ? 'active' : '' )) . '" data-format="thermal" data-width="80mm" title="' . esc_attr__( 'Thermal 80mm', 'semiauto-invoicing' ) . '">80</button>';
            echo '<button type="button" class="ogpsr-device-btn" data-format="thermal" data-width="76mm" title="' . esc_attr__( 'Thermal 76mm', 'semiauto-invoicing' ) . '">76</button>';
            echo '<button type="button" class="ogpsr-device-btn" data-format="thermal" data-width="58mm" title="' . esc_attr__( 'Thermal 58mm', 'semiauto-invoicing' ) . '">58</button>';
            echo '<button type="button" class="ogpsr-device-btn" data-format="thermal" data-width="57mm" title="' . esc_attr__( 'Thermal 57mm', 'semiauto-invoicing' ) . '">57</button>';
            echo '<button type="button" class="ogpsr-device-btn" data-format="thermal" data-width="44mm" title="' . esc_attr__( 'Thermal 44mm', 'semiauto-invoicing' ) . '">44</button>';
            echo '<button type="button" class="ogpsr-device-btn ' . (( $section === 'a4' ? 'active' : '' )) . '" data-format="a4" data-width="210mm" title="' . esc_attr__( 'A4 Document', 'semiauto-invoicing' ) . '">A4</button>';
            echo '<button type="button" class="ogpsr-device-btn ' . (( $section === 'packaging' ? 'active' : '' )) . '" data-format="packaging" data-width="210mm" title="' . esc_attr__( 'Packaging Slip', 'semiauto-invoicing' ) . '">PKG</button>';
            echo '</div>';
            echo '</div>';
            self::render_live_preview_canvas();
            echo '</div>';
        }

        /**
         * Render settings tab live preview canvas iframe.
         * Note: sandbox="allow-same-origin allow-scripts" is specifically required to enable
         * parent-to-iframe CSS custom property scaling (--ogpsr-scale) and scrollHeight auto-height measurement.
         */
        public static function render_live_preview_canvas() {
            echo '<div class="ogpsr-preview-canvas">';
            echo '<div class="ogpsr-preview-error-notice ogpsr-hide" id="ogpsr-preview-error-notice"></div>';
            echo '<div class="ogpsr-preview-iframe-wrapper" id="ogpsr-preview-wrapper">';
            echo '<iframe id="ogpsr-preview-iframe" src="about:blank" sandbox="allow-same-origin allow-scripts"></iframe>';
            echo '<div class="ogpsr-preview-loader">';
            echo '<span class="dashicons dashicons-update spin"></span>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '<div class="ogpsr-preview-footer">';
            echo '<small>' . esc_html__( 'Demo data is used for preview. Unsaved changes are shown dynamically; click Save to apply.', 'semiauto-invoicing' ) . '</small>';
            echo '</div>';
        }

    }

}
<?php

/**
 * WordPress Privacy Data Exporter & Eraser Integration
 * 
 * Handles GDPR / WordPress Personal Data Exporter & Personal Data Eraser hooks.
 * Registers plugin disclosures under Settings > Privacy (wp_add_privacy_policy_content).
 * 
 * @package SemiAutoInvoicing
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( !class_exists( 'OG_PSR_Privacy' ) ) {
    class OG_PSR_Privacy {
        /**
         * Initialize privacy hooks
         */
        public static function init() {
            // 1. Register suggested privacy policy content
            add_action( 'admin_init', [__CLASS__, 'add_privacy_policy_content'] );
            // 2. Register Personal Data Exporters
            add_filter( 'wp_privacy_personal_data_exporters', [__CLASS__, 'register_exporter'] );
            // 3. Register Personal Data Erasers
            add_filter( 'wp_privacy_personal_data_erasers', [__CLASS__, 'register_eraser'] );
        }

        /**
         * Returns the structured suggested privacy policy content HTML.
         *
         * @return string
         */
        public static function get_privacy_policy_html() {
            $content = '<h2>' . esc_html__( 'SemiAuto Invoicing – Local PDFs & Receipts', 'semiauto-invoicing' ) . '</h2>';
            $content .= '<p>' . esc_html__( 'This plugin generates digital and printable POS thermal receipts, A4 commercial invoices, and logistics packaging slips locally for WooCommerce orders.', 'semiauto-invoicing' ) . '</p>';
            $content .= '<h3>' . esc_html__( 'What data is processed and stored', 'semiauto-invoicing' ) . '</h3>';
            $content .= '<p>' . esc_html__( 'When customer orders are processed, customer transactional data (customer names, company names, billing and shipping addresses, phone numbers, and email addresses), purchased line items, payment methods, and applicable tax totals are formatted into PDF documents stored locally on your web server.', 'semiauto-invoicing' ) . '</p>';
            $content .= '<h3>' . esc_html__( 'Data Lifecycle & Processing Architecture', 'semiauto-invoicing' ) . '</h3>';
            $content .= '<p>' . esc_html__( 'Order data flows strictly from WooCommerce into the local SemiAuto Document Engine. Generated documents are saved to a protected local uploads directory. Invoices and receipts may be attached to WooCommerce customer transactional emails when enabled by the store administrator. When statutory retention periods conclude or when non-financial caches expire, temporary artifacts and un-retained documents are purged from the filesystem.', 'semiauto-invoicing' ) . '</p>';
            $content .= '<h3>' . esc_html__( 'Where documents are stored & security', 'semiauto-invoicing' ) . '</h3>';
            $content .= '<p>' . esc_html__( 'Generated PDF documents are stored locally within the plugin\'s document storage directory inside your WordPress uploads folder (wp-content/uploads/semiauto-invoicing/). The plugin creates server-specific protection files where supported; Nginx installations require the documented server rule to block direct web access. The plugin serves customer downloads through authenticated document endpoints. Documents can only be securely accessed or downloaded by authenticated customers for their own orders or by authorized store administrators with order management capabilities.', 'semiauto-invoicing' ) . '</p>';
            $content .= '<h3>' . esc_html__( 'Data Retention & Personal Data Erasure', 'semiauto-invoicing' ) . '</h3>';
            $content .= '<p>' . esc_html__( 'Document Retention: SemiAuto may retain generated invoices, receipts, and other financial records for the period configured or required for applicable accounting, tax, or statutory record-keeping purposes. Where statutory retention applies, deletion of associated personal data may not result in immediate deletion of the retained financial document. By default, the plugin retains commercial and tax document metadata during WordPress Personal Data Erasure requests while immediately purging non-statutory caches and session tokens. Store administrators and developers can customize this retention behavior using the ogpsr_privacy_retain_financial_records filter hook. Merchants remain solely responsible for determining their applicable tax, accounting, privacy, and document-retention obligations.', 'semiauto-invoicing' ) . '</p>';
            $content .= '<h3>' . esc_html__( 'Third-Party Services & External Data Flows', 'semiauto-invoicing' ) . '</h3>';
            $content .= '<p>' . esc_html__( 'Core Free Edition: Operates 100% locally on your WordPress server and does not transmit customer or order data to external third-party services for document generation.', 'semiauto-invoicing' ) . '</p>';
            $content .= '<p>' . esc_html__( 'License & Support Telemetry (Freemius): If the store administrator explicitly opts in during plugin activation, Freemius processes site administrator contact details (name and email address), site technical metadata (WordPress and PHP versions, site URL, and active locale), and plugin licensing status to deliver security updates, license validation, and support in accordance with the Freemius Privacy Policy (https://freemius.com/privacy/). No store customer or order transactional data is ever transmitted to or processed by Freemius.', 'semiauto-invoicing' ) . '</p>';
            return $content;
        }

        /**
         * Adds suggested privacy policy content to the WordPress Privacy Guide.
         */
        public static function add_privacy_policy_content() {
            if ( !function_exists( 'wp_add_privacy_policy_content' ) ) {
                return;
            }
            wp_add_privacy_policy_content( esc_html__( 'SemiAuto Invoicing', 'semiauto-invoicing' ), wp_kses_post( self::get_privacy_policy_html() ) );
        }

        /**
         * Register our custom personal data exporter.
         *
         * @param array $exporters
         * @return array
         */
        public static function register_exporter( $exporters ) {
            $exporters['semiauto-invoicing'] = [
                'exporter_friendly_name' => __( 'SemiAuto Invoicing Data', 'semiauto-invoicing' ),
                'callback'               => [__CLASS__, 'customer_data_exporter'],
            ];
            return $exporters;
        }

        /**
         * Export order metadata and generated document references for a customer.
         *
         * @param string $email_address
         * @param int    $page
         * @return array
         */
        public static function customer_data_exporter( $email_address, $page = 1 ) {
            $data_to_export = [];
            $orders = wc_get_orders( [
                'billing_email' => $email_address,
                'limit'         => 50,
                'page'          => $page,
            ] );
            if ( !empty( $orders ) ) {
                foreach ( $orders as $order ) {
                    $order_id = $order->get_id();
                    $meta_items = [];
                    $thermal_path = $order->get_meta( '_ogpsr_thermal_path' );
                    if ( !empty( $thermal_path ) ) {
                        $meta_items[] = [
                            'name'  => __( 'Thermal Receipt Document', 'semiauto-invoicing' ),
                            'value' => basename( $thermal_path ),
                        ];
                    }
                    $a4_path = $order->get_meta( '_ogpsr_a4_path' );
                    if ( !empty( $a4_path ) ) {
                        $meta_items[] = [
                            'name'  => __( 'A4 Invoice Document', 'semiauto-invoicing' ),
                            'value' => basename( $a4_path ),
                        ];
                    }
                    $packaging_path = $order->get_meta( '_ogpsr_packaging_path' );
                    if ( !empty( $packaging_path ) ) {
                        $meta_items[] = [
                            'name'  => __( 'Packaging Slip Document', 'semiauto-invoicing' ),
                            'value' => basename( $packaging_path ),
                        ];
                    }
                    $invoice_number = ( $order->get_meta( '_ogpsr_invoice_number' ) ?: $order->get_meta( '_invoice_number' ) );
                    if ( !empty( $invoice_number ) ) {
                        $meta_items[] = [
                            'name'  => __( 'Invoice Number', 'semiauto-invoicing' ),
                            'value' => $invoice_number,
                        ];
                    }
                    if ( !empty( $meta_items ) ) {
                        $data_to_export[] = [
                            'group_id'    => 'ogpsr_documents',
                            'group_label' => __( 'SemiAuto Invoicing Documents & Tax IDs', 'semiauto-invoicing' ),
                            'item_id'     => 'order-' . $order_id,
                            'data'        => $meta_items,
                        ];
                    }
                }
            }
            $done = count( $orders ) < 50;
            return [
                'data' => $data_to_export,
                'done' => $done,
            ];
        }

        /**
         * Register personal data eraser.
         *
         * @param array $erasers
         * @return array
         */
        public static function register_eraser( $erasers ) {
            $erasers['semiauto-invoicing'] = [
                'eraser_friendly_name' => __( 'SemiAuto Invoicing Data', 'semiauto-invoicing' ),
                'callback'             => [__CLASS__, 'customer_data_eraser'],
            ];
            return $erasers;
        }

        /**
         * Erase/Anonymize non-financial cache and document references for a customer.
         *
         * @param string $email_address
         * @param int    $page
         * @return array
         */
        public static function customer_data_eraser( $email_address, $page = 1 ) {
            $items_removed = 0;
            $items_retained = 0;
            $messages = [];
            $orders = wc_get_orders( [
                'billing_email' => $email_address,
                'limit'         => 50,
                'page'          => $page,
            ] );
            if ( !empty( $orders ) ) {
                global $wp_filesystem;
                if ( empty( $wp_filesystem ) ) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    WP_Filesystem();
                }
                $upload_dir = wp_upload_dir();
                $storage_root = wp_normalize_path( $upload_dir['basedir'] . '/semiauto-invoicing' );
                foreach ( $orders as $order ) {
                    $order_id = $order->get_id();
                    $has_statutory_doc = false;
                    // Check for statutory commercial invoice or tax document records
                    $doc_meta_keys = [
                        '_ogpsr_a4_path',
                        '_ogpsr_thermal_path',
                        '_ogpsr_packaging_path',
                        '_ogpsr_invoice_number'
                    ];
                    foreach ( $doc_meta_keys as $key ) {
                        if ( !empty( $order->get_meta( $key ) ) ) {
                            $has_statutory_doc = true;
                            break;
                        }
                    }
                    // Delete non-statutory temporary and cached metadata
                    $non_statutory_keys = [
                        '_ogpsr_preview_cache',
                        '_ogpsr_temp_token',
                        '_ogpsr_temp_signature',
                        '_ogpsr_render_lock'
                    ];
                    $order_meta_removed = 0;
                    foreach ( $non_statutory_keys as $key ) {
                        if ( $order->meta_exists( $key ) ) {
                            $order->delete_meta_data( $key );
                            $order_meta_removed++;
                        }
                    }
                    if ( $order_meta_removed > 0 ) {
                        $order->save_meta_data();
                        $items_removed++;
                    }
                    $should_retain = $has_statutory_doc;
                    /**
                     * Filter whether to retain commercial and financial document metadata for an order during personal data erasure.
                     *
                     * @param bool     $should_retain Default true if statutory invoice/document metadata exists.
                     * @param WC_Order $order         The WooCommerce order object.
                     */
                    $should_retain = apply_filters( 'ogpsr_privacy_retain_financial_records', $should_retain, $order );
                    if ( $should_retain ) {
                        // Commercial documents and invoice records are retained in accordance with merchant document retention configuration.
                        $items_retained++;
                        /* translators: %s: Order ID */
                        $retention_msg_fmt = __( 'Commercial and invoice records for Order #%s were retained in accordance with store document retention configuration. Merchants are responsible for determining applicable statutory retention obligations.', 'semiauto-invoicing' );
                        $messages[] = sprintf( $retention_msg_fmt, $order_id );
                    } else {
                        // Non-statutory or merchant-erased order: delete physical files and anonymize metadata
                        $physical_paths = [$order->get_meta( '_ogpsr_a4_path' ), $order->get_meta( '_ogpsr_thermal_path' ), $order->get_meta( '_ogpsr_packaging_path' )];
                        $files_deleted = 0;
                        foreach ( $physical_paths as $p ) {
                            if ( !empty( $p ) ) {
                                $p_norm = wp_normalize_path( $p );
                                if ( class_exists( 'OG_PSR_Security' ) && OG_PSR_Security::is_path_within( $p_norm, $storage_root ) ) {
                                    if ( $wp_filesystem && $wp_filesystem->exists( $p_norm ) ) {
                                        if ( $wp_filesystem->delete( $p_norm ) ) {
                                            $files_deleted++;
                                        }
                                    }
                                }
                            }
                        }
                        $docs_removed = 0;
                        foreach ( $doc_meta_keys as $key ) {
                            if ( $order->meta_exists( $key ) ) {
                                $order->delete_meta_data( $key );
                                $docs_removed++;
                            }
                        }
                        if ( $docs_removed > 0 || $files_deleted > 0 ) {
                            $order->save_meta_data();
                            $items_removed++;
                            /* translators: 1: Order ID, 2: Deleted files count, 3: Removed meta records count */
                            $erasure_msg_fmt = __( 'Document metadata (%2$d records) and physical stored files (%1$d files) for Order #%3$s were removed from database and disk.', 'semiauto-invoicing' );
                            $messages[] = sprintf(
                                $erasure_msg_fmt,
                                $files_deleted,
                                $docs_removed,
                                $order_id
                            );
                        }
                    }
                }
            }
            $done = count( $orders ) < 50;
            return [
                'items_removed'  => $items_removed,
                'items_retained' => $items_retained,
                'messages'       => $messages,
                'done'           => $done,
            ];
        }

    }

}
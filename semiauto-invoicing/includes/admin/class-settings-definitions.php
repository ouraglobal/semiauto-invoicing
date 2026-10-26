<?php

/**
 * Settings Tab Schema Definitions
 * 
 * Defines the WooCommerce setting fields for all tabs.
 * 
 * @package SemiAutoInvoicing
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
class OG_PSR_Settings_Definitions {
    /**
     * General Settings: Document formats and business identity.
     *
     * @return array
     */
    public static function get_general_settings() {
        $settings = array();
        $settings[] = array(
            'title' => __( 'Document Types', 'semiauto-invoicing' ),
            'type'  => 'title',
            'desc'  => __( 'Choose which PDF document types to generate for your orders.', 'semiauto-invoicing' ),
            'id'    => 'ogpsr_format_options',
        );
        $settings[] = array(
            'name'     => __( 'Enable Formats', 'semiauto-invoicing' ),
            'type'     => 'multiselect',
            'id'       => 'ogpsr_receipt_formats',
            'options'  => array(
                'thermal'   => __( 'Thermal Receipt', 'semiauto-invoicing' ),
                'a4'        => __( 'A4 Documents', 'semiauto-invoicing' ),
                'packaging' => __( 'Packaging Slip', 'semiauto-invoicing' ),
            ),
            'default'  => array('thermal'),
            'class'    => 'wc-enhanced-select',
            'desc'     => __( 'Select which formats to generate.', 'semiauto-invoicing' ),
            'desc_tip' => true,
        );
        $settings[] = array(
            'type' => 'sectionend',
            'id'   => 'ogpsr_format_options',
        );
        $settings[] = array(
            'title' => __( 'Business Identity', 'semiauto-invoicing' ),
            'type'  => 'title',
            'desc'  => __( 'Your business details shown on all documents.', 'semiauto-invoicing' ),
            'id'    => 'ogpsr_business_identity',
        );
        $settings[] = array(
            'name' => __( 'Logo', 'semiauto-invoicing' ),
            'type' => 'ogpsr_image_upload',
            'id'   => 'ogpsr_logo_attachment_id',
            'desc' => __( 'Upload your business logo via the WordPress Media Library. Supported formats: PNG, WebP, JPEG (min 600px width for 300 DPI print quality). Vector SVGs are not supported to guarantee strict PDF/A print fidelity and server security.', 'semiauto-invoicing' ),
        );
        $biz_name_desc = __( 'Your company name to be displayed on receipts and invoices.', 'semiauto-invoicing' ) . ' <button type="button" id="ogpsr_sync_woo_btn" class="button button-secondary ogpsr-sync-btn"><span class="dashicons dashicons-update ogpsr-v-middle"></span> ' . __( 'Sync from WooCommerce', 'semiauto-invoicing' ) . '</button><span id="ogpsr_sync_msg" class="ogpsr-sync-status-msg"></span>';
        $settings[] = array(
            'name'     => __( 'Business Name', 'semiauto-invoicing' ),
            'type'     => 'text',
            'id'       => 'ogpsr_business_name',
            'default'  => get_bloginfo( 'name' ),
            'desc'     => $biz_name_desc,
            'desc_tip' => false,
        );
        $settings[] = array(
            'name'     => __( 'Business Name Format', 'semiauto-invoicing' ),
            'type'     => 'select',
            'id'       => 'ogpsr_business_name_format',
            'options'  => array(
                'none'      => __( 'As Entered', 'semiauto-invoicing' ),
                'uppercase' => __( 'UPPERCASE', 'semiauto-invoicing' ),
            ),
            'default'  => 'none',
            'desc'     => __( 'Apply a text transformation to your business name on documents.', 'semiauto-invoicing' ),
            'desc_tip' => true,
        );
        $settings[] = array(
            'name'     => __( 'Business Address', 'semiauto-invoicing' ),
            'type'     => 'textarea',
            'id'       => 'ogpsr_business_address',
            'desc'     => __( 'Your company address.', 'semiauto-invoicing' ),
            'desc_tip' => true,
        );
        $settings[] = array(
            'name'     => __( 'Business City', 'semiauto-invoicing' ),
            'type'     => 'text',
            'id'       => 'ogpsr_business_city',
            'desc'     => __( 'Your company city.', 'semiauto-invoicing' ),
            'desc_tip' => true,
        );
        $settings[] = array(
            'name'     => __( 'Business Postcode', 'semiauto-invoicing' ),
            'type'     => 'text',
            'id'       => 'ogpsr_business_postcode',
            'desc'     => __( 'Your company postal code.', 'semiauto-invoicing' ),
            'desc_tip' => true,
        );
        $settings[] = array(
            'name'     => __( 'Store Contact Details', 'semiauto-invoicing' ),
            'type'     => 'textarea',
            'id'       => 'ogpsr_business_contact',
            'desc'     => __( 'Your store contact details (Phone, Email, Website, Operating Hours, etc.). Supports multiple lines.', 'semiauto-invoicing' ),
            'desc_tip' => true,
        );
        $settings[] = array(
            'name'              => __( 'Customer Details Visibility', 'semiauto-invoicing' ),
            'type'              => 'multiselect',
            'id'                => 'ogpsr_customer_info_fields',
            'options'           => array(
                'first_name'    => __( 'First Name', 'semiauto-invoicing' ),
                'last_name'     => __( 'Last Name', 'semiauto-invoicing' ),
                'company'       => __( 'Company Name', 'semiauto-invoicing' ),
                'address_1'     => __( 'Address', 'semiauto-invoicing' ),
                'city'          => __( 'City', 'semiauto-invoicing' ),
                'postcode'      => __( 'Postcode', 'semiauto-invoicing' ),
                'country'       => __( 'Country', 'semiauto-invoicing' ),
                'phone'         => __( 'Phone', 'semiauto-invoicing' ),
                'email'         => __( 'Email', 'semiauto-invoicing' ),
                'custom_fields' => __( 'Custom Checkout Fields', 'semiauto-invoicing' ),
            ),
            'default'           => array(
                'first_name',
                'last_name',
                'company',
                'address_1',
                'city',
                'postcode',
                'country',
                'phone',
                'email'
            ),
            'class'             => 'wc-enhanced-select',
            'desc'              => __( 'Select which customer details to show on documents.', 'semiauto-invoicing' ),
            'desc_tip'          => true,
            'sanitize_callback' => array('OG_PSR_Settings', 'sanitize_array_setting'),
        );
        $settings[] = array(
            'name'     => __( 'Currency Display Mode', 'semiauto-invoicing' ),
            'type'     => 'select',
            'id'       => 'ogpsr_currency_display_mode',
            'options'  => array(
                'symbol' => __( 'Symbol (e.g. $, د.إ)', 'semiauto-invoicing' ),
                'code'   => __( 'ISO Code (e.g. USD, AED)', 'semiauto-invoicing' ),
            ),
            'default'  => 'symbol',
            'desc'     => __( 'Choose how to display currency. Use ISO Code if symbols do not render correctly.', 'semiauto-invoicing' ),
            'desc_tip' => true,
        );
        $settings[] = array(
            'type' => 'sectionend',
            'id'   => 'ogpsr_business_identity',
        );
        return apply_filters( 'ogpsr_general_settings', $settings );
    }

    /**
     * Thermal Receipt Settings.
     *
     * @return array
     */
    public static function get_thermal_settings() {
        $settings = array();
        $settings[] = array(
            'title' => __( 'Thermal Receipt Settings', 'semiauto-invoicing' ),
            'type'  => 'title',
            'id'    => 'ogpsr_thermal_settings_section',
        );
        $settings[] = array(
            'name'     => __( 'Generation Trigger', 'semiauto-invoicing' ),
            'type'     => 'select',
            'id'       => 'ogpsr_thermal_generation_trigger',
            'options'  => OG_PSR_Settings::get_order_statuses(),
            'default'  => 'processing',
            'desc'     => __( 'Automatically generate thermal receipt when order reaches this status.', 'semiauto-invoicing' ),
            'desc_tip' => true,
        );
        $settings[] = array(
            'name'              => __( 'Attach to Emails', 'semiauto-invoicing' ),
            'type'              => 'multiselect',
            'id'                => 'ogpsr_email_ids',
            'options'           => OG_PSR_Settings::get_wc_emails(),
            'default'           => array('new_order'),
            'class'             => 'wc-enhanced-select',
            'desc'              => __( 'Emails to include the thermal receipt.', 'semiauto-invoicing' ),
            'desc_tip'          => true,
            'sanitize_callback' => array('OG_PSR_Settings', 'sanitize_array_setting'),
        );
        $settings[] = array(
            'name'    => __( 'Paper Width', 'semiauto-invoicing' ),
            'type'    => 'select',
            'id'      => 'ogpsr_paper_width',
            'options' => array(
                '44' => '44mm',
                '57' => '57mm',
                '58' => '58mm',
                '76' => '76mm',
                '80' => '80mm',
            ),
            'default' => '57',
        );
        $settings[] = array(
            'name'     => __( 'Header Text', 'semiauto-invoicing' ),
            'type'     => 'text',
            'id'       => 'ogpsr_header_text',
            'desc'     => __( 'Text to display at the very top of your thermal receipt.', 'semiauto-invoicing' ),
            'desc_tip' => true,
        );
        $settings[] = array(
            'name'     => __( 'Footer Text', 'semiauto-invoicing' ),
            'type'     => 'textarea',
            'id'       => 'ogpsr_footer_text',
            'default'  => 'Thank you for your order!',
            'desc'     => __( 'Text to display at the bottom of the receipt.', 'semiauto-invoicing' ),
            'desc_tip' => true,
        );
        $settings[] = array(
            'name'     => __( 'Custom Terms & Conditions', 'semiauto-invoicing' ),
            'type'     => 'textarea',
            'id'       => 'ogpsr_thermal_terms_and_conditions',
            'default'  => '',
            'desc'     => __( 'Add custom legal terms or any other notice to the thermal receipt footer.', 'semiauto-invoicing' ),
            'desc_tip' => true,
        );
        $settings[] = array(
            'name'     => __( 'Show "All prices include Tax"', 'semiauto-invoicing' ),
            'type'     => 'checkbox',
            'id'       => 'ogpsr_show_tax_inclusion_line',
            'default'  => 'yes',
            'desc'     => __( 'Display "All prices include Tax" line above the pricing section.', 'semiauto-invoicing' ),
            'desc_tip' => true,
        );
        $settings[] = array(
            'name'     => __( 'Show Coupon Codes', 'semiauto-invoicing' ),
            'type'     => 'checkbox',
            'id'       => 'ogpsr_thermal_show_coupon_codes',
            'default'  => 'yes',
            'desc'     => __( 'Display the names of any applied coupon codes alongside the discount total on the thermal receipt.', 'semiauto-invoicing' ),
            'desc_tip' => true,
        );
        $settings[] = array(
            'type'     => 'ogpsr_compact_showcase_banner',
            'showcase' => 'thermal',
            'id'       => 'ogpsr_thermal_compact_showcase',
        );
        $settings[] = array(
            'type' => 'sectionend',
            'id'   => 'ogpsr_thermal_settings_section',
        );
        return apply_filters( 'ogpsr_thermal_settings', $settings );
    }

    /**
     * A4 Invoice Settings.
     *
     * @return array
     */
    public static function get_a4_settings() {
        $settings = array();
        // Section 1: General Settings
        $settings[] = array(
            'title' => __( 'A4 Documents Settings', 'semiauto-invoicing' ),
            'type'  => 'title',
            'desc'  => __( 'Configure basic trigger and attachment rules for A4 Documents (Invoices & Credit Notes).', 'semiauto-invoicing' ),
            'id'    => 'ogpsr_a4_general_settings_section',
        );
        $settings[] = array(
            'name'     => __( 'Generation Trigger', 'semiauto-invoicing' ),
            'type'     => 'select',
            'id'       => 'ogpsr_a4_generation_trigger',
            'options'  => OG_PSR_Settings::get_order_statuses(),
            'default'  => 'completed',
            'desc'     => __( 'Automatically generate A4 invoice when order reaches this status.', 'semiauto-invoicing' ),
            'desc_tip' => true,
        );
        $settings[] = array(
            'name'              => __( 'Attach to Emails', 'semiauto-invoicing' ),
            'type'              => 'multiselect',
            'id'                => 'ogpsr_a4_email_ids',
            'options'           => OG_PSR_Settings::get_wc_emails(),
            'default'           => array(),
            'class'             => 'wc-enhanced-select',
            'desc'              => __( 'Emails to include the A4 invoice.', 'semiauto-invoicing' ),
            'desc_tip'          => true,
            'sanitize_callback' => array('OG_PSR_Settings', 'sanitize_array_setting'),
        );
        $settings[] = array(
            'type' => 'sectionend',
            'id'   => 'ogpsr_a4_general_settings_section',
        );
        // Section 2: Payment Terms & Due Dates
        $settings[] = array(
            'title' => __( 'Payment Terms & Due Dates', 'semiauto-invoicing' ),
            'type'  => 'title',
            'desc'  => __( 'Configure payment window limits and terms for dynamic due date calculations.', 'semiauto-invoicing' ),
            'id'    => 'ogpsr_a4_due_date_section',
        );
        $settings[] = array(
            'name'     => __( 'Show Due Date', 'semiauto-invoicing' ),
            'type'     => 'checkbox',
            'id'       => 'ogpsr_a4_show_due_date',
            'default'  => 'yes',
            'desc'     => __( 'Display the due date on the invoice.', 'semiauto-invoicing' ),
            'desc_tip' => true,
        );
        $settings[] = array(
            'name'     => __( 'Show Payment Terms', 'semiauto-invoicing' ),
            'type'     => 'checkbox',
            'id'       => 'ogpsr_a4_show_payment_terms',
            'default'  => 'yes',
            'desc'     => __( 'Display payment terms (e.g. Net 30 or 30 Days) in the invoice summary bar.', 'semiauto-invoicing' ),
            'desc_tip' => true,
        );
        $settings[] = array(
            'name'     => __( 'Payment Terms (Days)', 'semiauto-invoicing' ),
            'type'     => 'number',
            'id'       => 'ogpsr_a4_payment_terms',
            'default'  => '30',
            'desc'     => __( 'Number of days for payment terms. Used to calculate Due Date (Issue Date + Terms).', 'semiauto-invoicing' ),
            'desc_tip' => true,
        );
        $settings[] = array(
            'name'     => __( 'Payment Terms Format', 'semiauto-invoicing' ),
            'type'     => 'select',
            'id'       => 'ogpsr_a4_payment_terms_format',
            'options'  => array(
                'net'  => __( 'Net {days} (e.g. Net 30)', 'semiauto-invoicing' ),
                'days' => __( '{days} Days (e.g. 30 Days)', 'semiauto-invoicing' ),
            ),
            'default'  => 'net',
            'desc'     => __( 'Choose whether to display terms as "Net {days}" or "{days} Days".', 'semiauto-invoicing' ),
            'desc_tip' => true,
        );
        $settings[] = array(
            'type' => 'sectionend',
            'id'   => 'ogpsr_a4_due_date_section',
        );
        // Section 3: Payment Gateway & Bank Details
        $settings[] = array(
            'title' => __( 'Payment Gateway & Bank Details', 'semiauto-invoicing' ),
            'type'  => 'title',
            'desc'  => __( 'Configure bank transfer account displays on generated invoices.', 'semiauto-invoicing' ),
            'id'    => 'ogpsr_a4_gateway_bank_section',
        );
        $settings[] = array(
            'name'     => __( 'Show Bank Details', 'semiauto-invoicing' ),
            'type'     => 'checkbox',
            'id'       => 'ogpsr_show_bank_details',
            'default'  => 'no',
            'desc'     => __( 'Display bank account details (BACS) on the invoice.', 'semiauto-invoicing' ),
            'desc_tip' => true,
        );
        $settings[] = array(
            'name'     => __( 'Always Show Bank Details', 'semiauto-invoicing' ),
            'type'     => 'checkbox',
            'id'       => 'ogpsr_force_bank_details',
            'default'  => 'no',
            'desc'     => __( 'Display bank details for all payment methods. By default, WooCommerce only shows them for Direct Bank Transfer (BACS).', 'semiauto-invoicing' ),
            'desc_tip' => true,
        );
        $settings[] = array(
            'name'              => __( 'Select Bank Accounts', 'semiauto-invoicing' ),
            'type'              => 'multiselect',
            'id'                => 'ogpsr_selected_bank_accounts',
            'options'           => OG_PSR_Settings::get_bacs_account_options(),
            'default'           => array(),
            'class'             => 'wc-enhanced-select',
            'desc'              => __( 'Select which bank accounts to display. Leave empty to show all.', 'semiauto-invoicing' ),
            'desc_tip'          => true,
            'sanitize_callback' => array('OG_PSR_Settings', 'sanitize_array_setting'),
        );
        $settings[] = array(
            'type' => 'sectionend',
            'id'   => 'ogpsr_a4_gateway_bank_section',
        );
        // Section 4: Additional Display Options
        $settings[] = array(
            'title' => __( 'Additional Display Options', 'semiauto-invoicing' ),
            'type'  => 'title',
            'desc'  => __( 'Configure meta indicators and terms displayed on the document body.', 'semiauto-invoicing' ),
            'id'    => 'ogpsr_a4_additional_display_section',
        );
        $settings[] = array(
            'name'     => __( 'Show Product EAN/GTIN', 'semiauto-invoicing' ),
            'type'     => 'checkbox',
            'id'       => 'ogpsr_a4_show_product_ean',
            'default'  => 'no',
            'desc'     => __( 'Display product EAN or GTIN under the product name in the invoice.', 'semiauto-invoicing' ),
            'desc_tip' => true,
        );
        $settings[] = array(
            'name'     => __( 'Show Product SKU', 'semiauto-invoicing' ),
            'type'     => 'checkbox',
            'id'       => 'ogpsr_a4_show_product_sku',
            'default'  => 'no',
            'desc'     => __( 'Display product SKU under the product name in the invoice.', 'semiauto-invoicing' ),
            'desc_tip' => true,
        );
        $settings[] = array(
            'name'     => __( 'Show Coupon Codes', 'semiauto-invoicing' ),
            'type'     => 'checkbox',
            'id'       => 'ogpsr_a4_show_coupon_codes',
            'default'  => 'yes',
            'desc'     => __( 'Display the names of any applied coupon codes alongside the discount total on A4 Invoices.', 'semiauto-invoicing' ),
            'desc_tip' => true,
        );
        $settings[] = array(
            'name'     => __( 'Custom Terms & Conditions', 'semiauto-invoicing' ),
            'type'     => 'textarea',
            'id'       => 'ogpsr_a4_terms_and_conditions',
            'default'  => '',
            'desc'     => __( 'Add custom terms and conditions or any additional notice to the A4 invoice footer.', 'semiauto-invoicing' ),
            'desc_tip' => true,
        );
        $settings[] = array(
            'type'     => 'ogpsr_compact_showcase_banner',
            'showcase' => 'a4',
            'id'       => 'ogpsr_a4_compact_showcase',
        );
        $settings[] = array(
            'type' => 'sectionend',
            'id'   => 'ogpsr_a4_additional_display_section',
        );
        return apply_filters( 'ogpsr_a4_settings', $settings );
    }

    /**
     * Packaging Slip Settings.
     *
     * @return array
     */
    public static function get_packaging_settings() {
        $settings = array();
        $settings[] = array(
            'title' => __( 'Packaging Slip Settings', 'semiauto-invoicing' ),
            'type'  => 'title',
            'id'    => 'ogpsr_packaging_settings_section',
        );
        $settings[] = array(
            'name'     => __( 'Generation Trigger', 'semiauto-invoicing' ),
            'type'     => 'select',
            'id'       => 'ogpsr_packaging_generation_trigger',
            'options'  => OG_PSR_Settings::get_order_statuses(),
            'default'  => 'processing',
            'desc'     => __( 'Automatically generate packaging slip when order reaches this status.', 'semiauto-invoicing' ),
            'desc_tip' => true,
        );
        $settings[] = array(
            'name'              => __( 'Attach to Emails', 'semiauto-invoicing' ),
            'type'              => 'multiselect',
            'id'                => 'ogpsr_packaging_email_ids',
            'options'           => OG_PSR_Settings::get_wc_emails(),
            'default'           => array(),
            'class'             => 'wc-enhanced-select',
            'desc'              => __( 'Emails to include the packaging slip.', 'semiauto-invoicing' ),
            'desc_tip'          => true,
            'sanitize_callback' => array('OG_PSR_Settings', 'sanitize_array_setting'),
        );
        $settings[] = array(
            'type' => 'sectionend',
            'id'   => 'ogpsr_packaging_settings_section',
        );
        return apply_filters( 'ogpsr_packaging_settings', $settings );
    }

    /**
     * XML Invoice (Peppol) Settings.
     *
     * @return array
     */
    public static function get_xml_settings() {
        return array(array(
            'type'     => 'ogpsr_showcase_banner',
            'id'       => 'ogpsr_xml_showcase',
            'showcase' => 'xml',
        ));
    }

    /**
     * Design & Style Settings — Typography, fonts, document styling.
     *
     * @return array
     */
    public static function get_design_settings() {
        $settings = array();
        $settings[] = array(
            'title' => __( 'Global Typography & Styling', 'semiauto-invoicing' ),
            'type'  => 'title',
            'desc'  => __( 'Control the typography and color appearance of all generated documents.', 'semiauto-invoicing' ),
            'id'    => 'ogpsr_typography_options',
        );
        $settings[] = array(
            'name'     => __( 'Font Family', 'semiauto-invoicing' ),
            'type'     => 'select',
            'id'       => 'ogpsr_font_family',
            'options'  => array(
                'DejaVu Sans' => __( 'DejaVu Sans (Recommended)', 'semiauto-invoicing' ),
                'Courier'     => __( 'Courier', 'semiauto-invoicing' ),
                'Helvetica'   => __( 'Helvetica', 'semiauto-invoicing' ),
                'Times'       => __( 'Times', 'semiauto-invoicing' ),
                'Verdana'     => __( 'Verdana', 'semiauto-invoicing' ),
                'Arial'       => __( 'Arial', 'semiauto-invoicing' ),
            ),
            'default'  => 'DejaVu Sans',
            'desc'     => __( 'DejaVu Sans is the high-fidelity default for international compliance and character rendering.', 'semiauto-invoicing' ),
            'desc_tip' => false,
        );
        $settings[] = array(
            'name'              => __( 'Base Font Size (pt)', 'semiauto-invoicing' ),
            'type'              => 'number',
            'id'                => 'ogpsr_font_size',
            'default'           => '9',
            'custom_attributes' => array(
                'min' => '6',
                'max' => '24',
            ),
            'desc'              => __( 'Set the base font size in points (pt).', 'semiauto-invoicing' ),
            'desc_tip'          => true,
        );
        $settings[] = array(
            'name'              => __( 'Line Height', 'semiauto-invoicing' ),
            'type'              => 'number',
            'id'                => 'ogpsr_line_height',
            'default'           => '1.2',
            'custom_attributes' => array(
                'step' => '0.1',
                'min'  => '1',
                'max'  => '3',
            ),
            'desc'              => __( 'Adjust the vertical line spacing multiplier.', 'semiauto-invoicing' ),
            'desc_tip'          => true,
        );
        $settings[] = array(
            'name'     => __( 'Accent Color', 'semiauto-invoicing' ),
            'type'     => 'color',
            'id'       => 'ogpsr_accent_color',
            'default'  => '#1e293b',
            'desc'     => __( 'Choose the accent color for table headers, borders, and document highlights.', 'semiauto-invoicing' ),
            'desc_tip' => true,
        );
        $settings[] = array(
            'name'     => __( 'Base Font Color', 'semiauto-invoicing' ),
            'type'     => 'color',
            'id'       => 'ogpsr_font_color',
            'default'  => '#1e293b',
            'desc'     => __( 'Choose the primary font color for the document text.', 'semiauto-invoicing' ),
            'desc_tip' => true,
        );
        $settings[] = array(
            'type' => 'sectionend',
            'id'   => 'ogpsr_typography_options',
        );
        $settings[] = array(
            'title' => __( 'Thermal Receipt Appearance', 'semiauto-invoicing' ),
            'type'  => 'title',
            'desc'  => __( 'Customize colors and fonts specifically for thermal POS receipt styling.', 'semiauto-invoicing' ),
            'id'    => 'ogpsr_thermal_appearance_section',
        );
        $settings[] = array(
            'name'     => __( 'Thermal Accent Color', 'semiauto-invoicing' ),
            'type'     => 'color',
            'id'       => 'ogpsr_thermal_accent_color',
            'default'  => '#333333',
            'desc'     => __( 'Thermal printers typically only support black ink. Use black (#333333) for maximum print fidelity.', 'semiauto-invoicing' ),
            'desc_tip' => false,
        );
        $settings[] = array(
            'name'     => __( 'Thermal Font Override', 'semiauto-invoicing' ),
            'type'     => 'select',
            'id'       => 'ogpsr_thermal_font_family',
            'options'  => array(
                'DejaVu Sans' => __( 'DejaVu Sans (Recommended)', 'semiauto-invoicing' ),
                'Courier'     => __( 'Courier (POS Monospace)', 'semiauto-invoicing' ),
                'Helvetica'   => __( 'Helvetica', 'semiauto-invoicing' ),
            ),
            'default'  => 'DejaVu Sans',
            'desc'     => __( 'Courier is the traditional monospace font used on thermal POS receipts.', 'semiauto-invoicing' ),
            'desc_tip' => false,
        );
        $settings[] = array(
            'type' => 'sectionend',
            'id'   => 'ogpsr_thermal_appearance_section',
        );
        return apply_filters( 'ogpsr_design_settings', $settings );
    }

    /**
     * Barcode & QR Code Settings — Specialized tab for scanning features.
     *
     * @return array
     */
    public static function get_barcode_settings() {
        return array(array(
            'type'     => 'ogpsr_showcase_banner',
            'id'       => 'ogpsr_barcode_showcase',
            'showcase' => 'barcode',
        ));
    }

    /**
     * Storage & Maintenance Settings.
     *
     * @return array
     */
    public static function get_storage_settings() {
        $settings = array();
        $settings[] = array(
            'type'     => 'ogpsr_showcase_banner',
            'showcase' => 'storage',
            'id'       => 'ogpsr_storage_showcase_banner',
        );
        // Uninstall Options
        $settings[] = array(
            'title' => __( 'Uninstall Options', 'semiauto-invoicing' ),
            'type'  => 'title',
            'desc'  => __( 'Configure what happens when the plugin is uninstalled.', 'semiauto-invoicing' ),
            'id'    => 'ogpsr_uninstall_options',
        );
        $settings[] = array(
            'name'     => __( 'Delete All Data on Uninstall', 'semiauto-invoicing' ),
            'type'     => 'checkbox',
            'id'       => 'ogpsr_delete_on_uninstall',
            'desc'     => __( 'When enabled, all plugin settings, cached PDFs, and related data will be permanently deleted when the plugin is uninstalled. This action cannot be undone. Issued invoices and credit notes, their numbers and the numbering counters are kept, because invoices must be retained.', 'semiauto-invoicing' ),
            'default'  => 'no',
            'desc_tip' => true,
        );
        $settings[] = array(
            'type' => 'sectionend',
            'id'   => 'ogpsr_uninstall_options',
        );
        // Compliance Assistant & Legal Disclaimer with Privacy Policy Link
        $settings[] = array(
            'type' => 'ogpsr_compliance_disclaimer',
            'id'   => 'ogpsr_storage_compliance_disclaimer',
        );
        return apply_filters( 'ogpsr_storage_settings', $settings );
    }

    /**
     * Extras Settings — Custom checkout fees and specialized add-on features.
     *
     * @return array
     */
    public static function get_extras_settings() {
        return array(array(
            'type'     => 'ogpsr_showcase_banner',
            'id'       => 'ogpsr_extras_showcase',
            'showcase' => 'extras',
        ));
    }

    /**
     * Peppol Gateways Settings.
     *
     * @return array
     */
    public static function get_billing_settings() {
        return array(array(
            'type'     => 'ogpsr_showcase_banner',
            'id'       => 'ogpsr_billing_showcase',
            'showcase' => 'billing',
        ));
    }

    /**
     * Peppol Gateways settings (PRO feature delegation; Free shows the Feature Showcase).
     *
     * @return array
     */
    public static function get_gateways_settings() {
        return array(array(
            'type'     => 'ogpsr_showcase_banner',
            'id'       => 'ogpsr_gateways_showcase',
            'showcase' => 'gateways',
        ));
    }

    /**
     * Status & Diagnostics Settings.
     *
     * @return array
     */
    public static function get_status_settings() {
        $settings = array();
        $settings[] = array(
            'title' => __( 'System Status & Diagnostics', 'semiauto-invoicing' ),
            'type'  => 'title',
            'id'    => 'ogpsr_status_settings_section',
            'desc'  => __( 'A comprehensive overview of your server environment, active integrations, and system health. Use these tools to ensure your invoicing engine is running optimally.', 'semiauto-invoicing' ),
        );
        $settings[] = array(
            'type' => 'ogpsr_system_status',
        );
        $settings[] = array(
            'type' => 'sectionend',
            'id'   => 'ogpsr_status_settings_section',
        );
        $settings[] = array(
            'title' => __( 'Diagnostic Logging', 'semiauto-invoicing' ),
            'type'  => 'title',
            'id'    => 'ogpsr_logging_settings_section',
            'desc'  => __( 'Configure real-time activity tracking for advanced troubleshooting.', 'semiauto-invoicing' ),
        );
        $settings[] = array(
            'name'     => __( 'Enable Activity Logging', 'semiauto-invoicing' ),
            'type'     => 'checkbox',
            'id'       => 'ogpsr_enable_activity_logging',
            'default'  => 'no',
            'desc'     => __( 'Capture document generations and administrative actions in the Live Activity Logs.', 'semiauto-invoicing' ),
            'desc_tip' => __( 'Default: Off (Recommended). Only critical errors are logged to save server resources. On: Enables full activity tracking for real-time diagnostics.', 'semiauto-invoicing' ),
        );
        $settings[] = array(
            'type' => 'sectionend',
            'id'   => 'ogpsr_logging_settings_section',
        );
        return apply_filters( 'ogpsr_status_settings', $settings );
    }

    /**
     * Helper: Returns a list of all registered setting option IDs across all sections.
     *
     * @return array
     */
    public static function get_all_setting_ids() {
        $ids = array();
        $methods = array(
            'get_general_settings',
            'get_thermal_settings',
            'get_a4_settings',
            'get_packaging_settings',
            'get_xml_settings',
            'get_barcode_settings',
            'get_design_settings',
            'get_extras_settings',
            'get_storage_settings',
            'get_gateways_settings',
            'get_status_settings'
        );
        foreach ( $methods as $method ) {
            if ( method_exists( __CLASS__, $method ) ) {
                $section_fields = self::$method();
                if ( is_array( $section_fields ) ) {
                    foreach ( $section_fields as $field ) {
                        if ( !empty( $field['id'] ) ) {
                            $ids[] = $field['id'];
                        }
                    }
                }
            }
        }
        return array_values( array_unique( $ids ) );
    }

}

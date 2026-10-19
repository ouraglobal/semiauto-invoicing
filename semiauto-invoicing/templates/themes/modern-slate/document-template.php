<?php

/**
 * Universal Presentation Template: Modern Corporate Slate Theme
 * 
 * Master dynamic presentation template capable of rendering all 5 document types:
 * (Invoice, Credit Note, Packaging Slip, Thermal Receipt, Kitchen Copy)
 * across all paper formats (A4/Letter and 44mm/57mm/58mm/76mm/80mm Thermal Roll).
 * 
 * @package SemiAutoInvoicing
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- View presentation template scope.
$doc = ( isset( $doc ) && is_array( $doc ) ? $doc : [] );
$doc_type = $doc['doc_type'] ?? 'invoice';
$page_format = $doc['page_format'] ?? 'a4';
$is_thermal = !empty( $doc['is_thermal'] );
$font_family = $doc['font_family'] ?? 'DejaVu Sans';
$accent_color = $doc['accent_color'] ?? $doc['brand_color'] ?? '#1e293b';
$font_color = $doc['font_color'] ?? '#1e293b';
$font_size = $doc['font_size'] ?? 9;
$line_height = $doc['line_height'] ?? 1.2;
$seller = $doc['seller'] ?? [];
$buyer = $doc['buyer'] ?? [];
$shipping = $doc['shipping'] ?? [
    'enabled' => false,
];
$totals = $doc['totals'] ?? [];
$items = $doc['line_items'] ?? [];
$paper_width_mm = (int) ($doc['paper_width_mm'] ?? 80);
$roll_class = ( $is_thermal ? ' ogpsr-roll-' . $paper_width_mm . 'mm' : '' );
?>
<!DOCTYPE html>
<html lang="<?php 
echo esc_attr( get_locale() );
?>" class="<?php 
echo esc_attr( (( $is_thermal ? 'ogpsr-format-thermal' : 'ogpsr-format-a4' )) . $roll_class );
?>">
<head>
    <meta charset="utf-8">
    <title><?php 
echo esc_html( $doc['title'] . ' ' . $doc['document_number'] );
?></title>
</head>
<body class="<?php 
echo esc_attr( (( $is_thermal ? 'ogpsr-format-thermal' : 'ogpsr-format-a4' )) . $roll_class );
?>">

<?php 
if ( $is_thermal ) {
    ?>
    <!-- ================================================================== -->
    <!-- THERMAL POS ROLL & KITCHEN COPY LAYOUT (44mm, 57mm, 58mm, 76mm, 80mm) -->
    <!-- ================================================================== -->
    <div class="ogpsr-thermal-header">
        <?php 
    $render_thermal_logo = !empty( $seller['logo_url'] );
    if ( $render_thermal_logo ) {
        ?>
            <img src="<?php 
        echo esc_url( $seller['logo_url'] );
        ?>" class="ogpsr-thermal-logo" alt="Logo">
        <?php 
    }
    ?>

        <div class="ogpsr-thermal-title"><?php 
    echo esc_html( $seller['name'] );
    ?></div>
        
        <?php 
    $render_thermal_address = !empty( $seller['address_1'] );
    if ( $render_thermal_address ) {
        ?>
            <div class="ogpsr-thermal-meta-row"><?php 
        echo nl2br( esc_html( $seller['address_1'] ) );
        ?></div>
        <?php 
    }
    ?>
        <?php 
    $render_thermal_city = !empty( $seller['city'] );
    if ( $render_thermal_city ) {
        ?>
            <div class="ogpsr-thermal-meta-row"><?php 
        echo esc_html( trim( $seller['city'] . ' ' . $seller['postcode'] ) );
        if ( !empty( $seller['country'] ) ) {
            echo ', ' . esc_html( $seller['country'] );
        }
        ?></div>
        <?php 
    }
    ?>
        <?php 
    $render_thermal_contact = !empty( $seller['contact'] );
    if ( $render_thermal_contact ) {
        ?>
            <div class="ogpsr-thermal-meta-row"><?php 
        echo nl2br( esc_html( $seller['contact'] ) );
        ?></div>
        <?php 
    }
    ?>
        <?php 
    ?>

        <?php 
    if ( !empty( $doc['header_text'] ) ) {
        ?>
            <div class="ogpsr-thermal-meta-row"><?php 
        echo esc_html( $doc['header_text'] );
        ?></div>
        <?php 
    }
    ?>

        <div class="ogpsr-dashed-divider"></div>
        <div class="ogpsr-fw-bold ogpsr-uppercase"><?php 
    echo esc_html( $doc['title'] );
    ?></div>
        <div class="ogpsr-thermal-meta-row"><?php 
    echo esc_html( $doc['document_number'] );
    ?></div>
        <?php 
    ?>
        <div class="ogpsr-thermal-meta-row">
            <?php 
    echo esc_html( $doc['date_created'] );
    ?>
            <?php 
    if ( !empty( $doc['payment_method_title'] ) ) {
        ?> | <?php 
        echo esc_html( $doc['payment_method_title'] );
    }
    ?>
        </div>
        <?php 
    if ( !empty( $doc['delivery_pickup']['has_info'] ) ) {
        ?>
            <div class="ogpsr-thermal-meta-row ogpsr-fw-bold">
                <?php 
        esc_html_e( 'Order Type:', 'semiauto-invoicing' );
        ?> <?php 
        echo esc_html( $doc['delivery_pickup']['label'] );
        ?>
                <?php 
        if ( !empty( $doc['delivery_pickup']['time'] ) ) {
            ?> | <?php 
            echo esc_html( $doc['delivery_pickup']['time'] );
        }
        ?>
            </div>
        <?php 
    }
    ?>
        <?php 
    ?>
        <?php 
    if ( !empty( $doc['show_tax_inclusion'] ) ) {
        ?>
            <div class="ogpsr-muted-text ogpsr-text-center ogpsr-mt-4"><?php 
        esc_html_e( 'All prices include Tax', 'semiauto-invoicing' );
        ?></div>
        <?php 
    }
    ?>
    </div>

    <!-- Thermal Customer Details Section (Legacy Parity) -->
    <?php 
    $render_customer_details = true;
    if ( $render_customer_details ) {
        ?>
        <?php 
        $has_cust = !empty( $buyer['name'] ) || !empty( $buyer['company'] ) || !empty( $buyer['address_1'] ) || !empty( $buyer['city'] ) || !empty( $buyer['phone'] ) || !empty( $buyer['email'] ) || !empty( $doc['custom_fields'] );
        ?>
        <?php 
        if ( $has_cust ) {
            ?>
            <div class="ogpsr-dashed-divider"></div>
            <div class="ogpsr-thermal-customer-box">
                <div class="ogpsr-fw-bold ogpsr-customer-title"><?php 
            esc_html_e( 'Customer Details:', 'semiauto-invoicing' );
            ?></div>
                <?php 
            if ( !empty( $buyer['company'] ) ) {
                ?>
                    <div class="ogpsr-fw-bold"><?php 
                echo esc_html( $buyer['company'] );
                ?></div>
                    <?php 
                if ( !empty( $buyer['name'] ) ) {
                    ?>
                        <div><?php 
                    echo esc_html( $buyer['name'] );
                    ?></div>
                    <?php 
                }
                ?>
                <?php 
            } elseif ( !empty( $buyer['name'] ) ) {
                ?>
                    <div class="ogpsr-fw-bold"><?php 
                echo esc_html( $buyer['name'] );
                ?></div>
                <?php 
            }
            ?>
                <?php 
            if ( !empty( $buyer['address_1'] ) ) {
                ?>
                    <div><?php 
                echo esc_html( $buyer['address_1'] );
                ?></div>
                <?php 
            }
            ?>
                <?php 
            if ( !empty( $buyer['address_2'] ) ) {
                ?>
                    <div><?php 
                echo esc_html( $buyer['address_2'] );
                ?></div>
                <?php 
            }
            ?>
                <?php 
            if ( !empty( $buyer['city'] ) || !empty( $buyer['postcode'] ) ) {
                ?>
                    <div><?php 
                echo esc_html( trim( $buyer['city'] . ' ' . $buyer['postcode'] ) );
                ?></div>
                <?php 
            }
            ?>
                <?php 
            if ( !empty( $buyer['country'] ) ) {
                ?>
                    <div><?php 
                echo esc_html( $buyer['country'] );
                ?></div>
                <?php 
            }
            ?>
                <?php 
            if ( !empty( $buyer['phone'] ) ) {
                ?>
                    <div><?php 
                esc_html_e( 'Tel:', 'semiauto-invoicing' );
                ?> <?php 
                echo esc_html( $buyer['phone'] );
                ?></div>
                <?php 
            }
            ?>
                <?php 
            if ( !empty( $buyer['email'] ) ) {
                ?>
                    <div><?php 
                esc_html_e( 'Email:', 'semiauto-invoicing' );
                ?> <?php 
                echo esc_html( $buyer['email'] );
                ?></div>
                <?php 
            }
            ?>
                <?php 
            ?>
                <?php 
            if ( !empty( $doc['custom_fields'] ) ) {
                ?>
                    <?php 
                foreach ( $doc['custom_fields'] as $cf ) {
                    ?>
                        <div><strong><?php 
                    echo esc_html( $cf['label'] );
                    ?>:</strong> <?php 
                    echo esc_html( $cf['value'] );
                    ?></div>
                    <?php 
                }
                ?>
                <?php 
            }
            ?>
            </div>
        <?php 
        }
        ?>
    <?php 
    }
    ?>

    <!-- Thermal Line Items Table -->
    <table class="ogpsr-thermal-items-table">
        <thead>
            <tr>
                <th class="ogpsr-text-left"><?php 
    esc_html_e( 'Item', 'semiauto-invoicing' );
    ?></th>
                <th class="ogpsr-thermal-qty-col"><?php 
    esc_html_e( 'Qty', 'semiauto-invoicing' );
    ?></th>
                <?php 
    $render_thermal_price_col = true;
    if ( $render_thermal_price_col ) {
        ?>
                    <th class="ogpsr-thermal-price-col"><?php 
        esc_html_e( 'Price', 'semiauto-invoicing' );
        ?></th>
                <?php 
    }
    ?>
            </tr>
        </thead>
        <tbody>
            <?php 
    foreach ( $items as $item ) {
        ?>
                <tr>
                    <td class="ogpsr-text-left">
                        <span class="ogpsr-fw-bold"><?php 
        echo esc_html( $item['name'] );
        ?></span>
                        <?php 
        if ( !empty( $item['sku'] ) ) {
            ?>
                            <div class="ogpsr-item-sku"><?php 
            esc_html_e( 'SKU:', 'semiauto-invoicing' );
            ?> <?php 
            echo esc_html( $item['sku'] );
            ?></div>
                        <?php 
        }
        ?>
                        <?php 
        ?>
                        <?php 
        if ( !empty( $doc['show_product_meta'] ) ) {
            ?>
                            <?php 
            if ( !empty( $item['meta'] ) ) {
                ?>
                                <div class="ogpsr-item-meta"><?php 
                echo esc_html( $item['meta'] );
                ?></div>
                            <?php 
            }
            ?>
                            <?php 
            if ( !empty( $item['addons'] ) ) {
                ?>
                                <?php 
                foreach ( $item['addons'] as $addon_text ) {
                    ?>
                                    <div class="ogpsr-item-meta"><?php 
                    echo wp_kses_post( $addon_text );
                    ?></div>
                                <?php 
                }
                ?>
                            <?php 
            }
            ?>
                        <?php 
        }
        ?>
                    </td>
                    <td class="ogpsr-thermal-qty-col"><?php 
        echo esc_html( $item['qty'] );
        ?></td>
                    <?php 
        $render_thermal_subtotal = true;
        if ( $render_thermal_subtotal ) {
            ?>
                        <td class="ogpsr-thermal-price-col"><?php 
            echo wp_kses_post( $item['subtotal'] );
            ?></td>
                    <?php 
        }
        ?>
                </tr>
            <?php 
    }
    ?>
        </tbody>
    </table>

    <!-- Thermal Totals Section (Suppressed on Kitchen Copy) -->
    <?php 
    $render_thermal_totals = true;
    if ( $render_thermal_totals ) {
        ?>
        <table class="ogpsr-thermal-totals-table">
            <?php 
        if ( !empty( $doc['order_item_totals'] ) ) {
            ?>
                <?php 
            foreach ( $doc['order_item_totals'] as $key => $total ) {
                ?>
                    <?php 
                $is_grand_total = $key === 'order_total';
                ?>
                    <tr class="<?php 
                echo esc_attr( ( $is_grand_total ? 'ogpsr-thermal-grand-total' : '' ) );
                ?>">
                        <td class="<?php 
                echo esc_attr( ( $is_grand_total ? 'ogpsr-fw-bold' : 'ogpsr-muted-text' ) );
                ?>"><?php 
                echo esc_html( rtrim( $total['label'], ':' ) );
                ?></td>
                        <td class="ogpsr-text-right <?php 
                echo esc_attr( ( $is_grand_total ? 'ogpsr-fw-bold' : '' ) );
                ?>"><?php 
                echo wp_kses_post( $total['value'] );
                ?></td>
                    </tr>
                <?php 
            }
            ?>
            <?php 
        } else {
            ?>
                <tr>
                    <td class="ogpsr-muted-text"><?php 
            esc_html_e( 'Subtotal', 'semiauto-invoicing' );
            ?></td>
                    <td class="ogpsr-text-right"><?php 
            echo wp_kses_post( $totals['subtotal'] );
            ?></td>
                </tr>
                <tr class="ogpsr-thermal-grand-total">
                    <td class="ogpsr-fw-bold">
                        <?php 
            $thermal_total_label = __( 'TOTAL', 'semiauto-invoicing' );
            echo esc_html( $thermal_total_label );
            ?>
                    </td>
                    <td class="ogpsr-text-right ogpsr-fw-bold"><?php 
            echo wp_kses_post( $totals['grand_total'] );
            ?></td>
                </tr>
            <?php 
        }
        ?>
            <?php 
        ?>
        </table>
    <?php 
    }
    ?>

    <!-- Thermal Footer Notes & Barcode/QR -->
    <div class="ogpsr-thermal-footer">
        <?php 
    if ( !empty( $doc['customer_note'] ) ) {
        ?>
            <div class="ogpsr-text-left ogpsr-mb-6">
                <strong><?php 
        esc_html_e( 'Note:', 'semiauto-invoicing' );
        ?></strong> <?php 
        echo nl2br( esc_html( $doc['customer_note'] ) );
        ?>
            </div>
        <?php 
    }
    ?>

        <?php 
    ?>

        <?php 
    $render_thermal_terms = !empty( $doc['terms_html'] );
    if ( $render_thermal_terms ) {
        ?>
            <div class="ogpsr-muted-text ogpsr-text-center ogpsr-mb-4"><?php 
        echo nl2br( esc_html( $doc['terms_html'] ) );
        ?></div>
        <?php 
    }
    ?>

        <?php 
    $render_thermal_footer_text = !empty( $doc['footer_text'] );
    if ( $render_thermal_footer_text ) {
        ?>
            <div><?php 
        echo nl2br( esc_html( $doc['footer_text'] ) );
        ?></div>
        <?php 
    }
    ?>
    </div>

<?php 
} else {
    ?>
    <!-- ================================================================== -->
    <!-- A4 MULTI-COLUMN LAYOUT (Invoice, Credit Note, Packaging)           -->
    <!-- ================================================================== -->

    <!-- 1. Header Section -->
    <table class="ogpsr-header-table">
        <tr>
            <td class="ogpsr-w-50 ogpsr-v-top">
                <?php 
    if ( !empty( $seller['logo_url'] ) ) {
        ?>
                    <img src="<?php 
        echo esc_url( $seller['logo_url'] );
        ?>" class="ogpsr-logo-img" alt="Logo">
                <?php 
    }
    ?>
                <?php 
    if ( !empty( $seller['name'] ) ) {
        ?>
                    <div class="ogpsr-business-title"><?php 
        echo esc_html( $seller['name'] );
        ?></div>
                <?php 
    }
    ?>
                <div class="ogpsr-muted-text">
                    <?php 
    if ( !empty( $seller['address_1'] ) ) {
        echo nl2br( esc_html( $seller['address_1'] ) ) . '<br>';
    }
    ?>
                    <?php 
    if ( !empty( $seller['address_2'] ) ) {
        echo nl2br( esc_html( $seller['address_2'] ) ) . '<br>';
    }
    ?>
                    <?php 
    echo esc_html( trim( $seller['city'] . ' ' . $seller['state'] . ' ' . $seller['postcode'] ) );
    ?>
                    <?php 
    if ( !empty( $seller['country'] ) ) {
        echo ', ' . esc_html( $seller['country'] );
    }
    ?>
                    <?php 
    ?>
                    <?php 
    if ( !empty( $seller['contact'] ) ) {
        ?>
                        <?php 
        echo '<br>' . nl2br( esc_html( $seller['contact'] ) );
        ?>
                    <?php 
    }
    ?>
                </div>
            </td>
            <td class="ogpsr-w-50 ogpsr-v-top ogpsr-text-right">
                <div class="ogpsr-doc-title-badge ogpsr-brand-text"><?php 
    echo esc_html( $doc['title'] );
    ?></div>
                <div class="ogpsr-doc-number-sub"><?php 
    echo esc_html( $doc['document_number'] );
    ?></div>
                <div class="ogpsr-muted-text ogpsr-mt-4">
                    <?php 
    echo esc_html__( 'Date:', 'semiauto-invoicing' ) . ' ' . esc_html( $doc['date_created'] );
    ?>
                </div>
                <?php 
    ?>
            </td>
        </tr>
    </table>

    <!-- 2. Metric Cards Grid -->
    <?php 
    $has_first_metric = false;
    if ( $doc_type === 'invoice' ) {
        $has_first_metric = !empty( $doc['show_payment_terms'] ) && !empty( $doc['payment_terms'] );
    } elseif ( $doc_type === 'packaging_slip' ) {
        $has_first_metric = true;
    }
    $has_delivery_metric = !empty( $doc['delivery_pickup']['has_info'] ) && (!empty( $doc['delivery_pickup']['time'] ) || !empty( $doc['delivery_pickup']['type'] ));
    $has_third_metric = false;
    if ( $doc_type === 'packaging_slip' ) {
        $has_third_metric = true;
    } elseif ( $doc_type === 'invoice' ) {
        $has_third_metric = !empty( $doc['due_date'] );
    }
    $metric_card_count = 2;
    // Issue Date and Order Ref are always active
    if ( $has_first_metric ) {
        $metric_card_count++;
    }
    if ( $has_delivery_metric ) {
        $metric_card_count++;
    }
    if ( $has_third_metric ) {
        $metric_card_count++;
    }
    $metric_col_class = 'ogpsr-w-25';
    if ( $metric_card_count >= 5 ) {
        $metric_col_class = 'ogpsr-w-20';
    } elseif ( $metric_card_count === 4 ) {
        $metric_col_class = 'ogpsr-w-25';
    } elseif ( $metric_card_count === 3 ) {
        $metric_col_class = 'ogpsr-w-33';
    } elseif ( $metric_card_count === 2 ) {
        $metric_col_class = 'ogpsr-w-50';
    }
    ?>
    <table class="ogpsr-metric-cards-table">
        <tr>
            <?php 
    if ( $has_first_metric ) {
        ?>
                <td class="<?php 
        echo esc_attr( $metric_col_class );
        ?>">
                    <div class="ogpsr-metric-card">
                        <div class="ogpsr-metric-label">
                            <?php 
        $rendered_first_label = false;
        if ( !$rendered_first_label ) {
            if ( $doc_type === 'packaging_slip' ) {
                esc_html_e( 'Slip #', 'semiauto-invoicing' );
            } else {
                esc_html_e( 'Payment Terms', 'semiauto-invoicing' );
            }
        }
        ?>
                        </div>
                        <div class="ogpsr-metric-val">
                            <?php 
        if ( $doc_type === 'invoice' ) {
            echo esc_html( $doc['payment_terms'] );
        } else {
            echo esc_html( $doc['document_number'] );
        }
        ?>
                        </div>
                    </div>
                </td>
            <?php 
    }
    ?>
            <td class="<?php 
    echo esc_attr( $metric_col_class );
    ?>">
                <div class="ogpsr-metric-card">
                    <div class="ogpsr-metric-label"><?php 
    esc_html_e( 'Issue Date', 'semiauto-invoicing' );
    ?></div>
                    <div class="ogpsr-metric-val"><?php 
    echo esc_html( $doc['date_created'] );
    ?></div>
                </div>
            </td>
            <?php 
    if ( $has_delivery_metric ) {
        ?>
                <td class="<?php 
        echo esc_attr( $metric_col_class );
        ?>">
                    <div class="ogpsr-metric-card ogpsr-metric-card-delivery">
                        <div class="ogpsr-metric-label">
                            <?php 
        if ( !empty( $doc['delivery_pickup']['type'] ) ) {
            if ( $doc['delivery_pickup']['type'] === 'pickup' ) {
                echo esc_html( ( !empty( $doc['delivery_pickup']['time'] ) ? __( 'Pickup Time', 'semiauto-invoicing' ) : __( 'Pickup', 'semiauto-invoicing' ) ) );
            } else {
                echo esc_html( ( !empty( $doc['delivery_pickup']['time'] ) ? __( 'Delivery Time', 'semiauto-invoicing' ) : __( 'Delivery', 'semiauto-invoicing' ) ) );
            }
        } else {
            echo esc_html( ( !empty( $doc['delivery_pickup']['label'] ) ? $doc['delivery_pickup']['label'] : __( 'Delivery Time', 'semiauto-invoicing' ) ) );
        }
        ?>
                        </div>
                        <div class="ogpsr-metric-val">
                            <?php 
        if ( !empty( $doc['delivery_pickup']['date'] ) && !empty( $doc['delivery_pickup']['time_slot'] ) ) {
            echo esc_html( $doc['delivery_pickup']['date'] ) . '<br>' . esc_html( $doc['delivery_pickup']['time_slot'] );
        } elseif ( !empty( $doc['delivery_pickup']['time'] ) ) {
            echo esc_html( $doc['delivery_pickup']['time'] );
        } else {
            echo esc_html( $doc['delivery_pickup']['label'] );
        }
        ?>
                        </div>
                    </div>
                </td>
            <?php 
    }
    ?>
            <?php 
    if ( $has_third_metric ) {
        ?>
                <td class="<?php 
        echo esc_attr( $metric_col_class );
        ?>">
                    <div class="ogpsr-metric-card">
                        <div class="ogpsr-metric-label">
                            <?php 
        $rendered_third_label = false;
        if ( !$rendered_third_label ) {
            if ( $doc_type === 'packaging_slip' ) {
                if ( !$has_delivery_metric && !empty( $doc['delivery_pickup']['has_info'] ) ) {
                    echo esc_html( $doc['delivery_pickup']['label'] );
                } else {
                    esc_html_e( 'Shipping Method', 'semiauto-invoicing' );
                }
            } else {
                esc_html_e( 'Due Date', 'semiauto-invoicing' );
            }
        }
        ?>
                        </div>
                        <div class="ogpsr-metric-val">
                            <?php 
        if ( $doc_type === 'packaging_slip' ) {
            if ( !$has_delivery_metric && !empty( $doc['delivery_pickup']['has_info'] ) && !empty( $doc['delivery_pickup']['time'] ) ) {
                echo esc_html( $doc['delivery_pickup']['time'] );
            } else {
                echo esc_html( ( $doc['order']->get_shipping_method() ?: __( 'Standard Delivery', 'semiauto-invoicing' ) ) );
            }
        } else {
            echo esc_html( ( !empty( $doc['due_date'] ) ? $doc['due_date'] : $doc['date_created'] ) );
        }
        ?>
                        </div>
                    </div>
                </td>
            <?php 
    }
    ?>
            <td class="<?php 
    echo esc_attr( $metric_col_class );
    ?>">
                <div class="ogpsr-metric-card">
                    <div class="ogpsr-metric-label"><?php 
    esc_html_e( 'Order Ref', 'semiauto-invoicing' );
    ?></div>
                    <div class="ogpsr-metric-val">#<?php 
    echo esc_html( $doc['order_id'] );
    ?></div>
                </div>
            </td>
        </tr>
    </table>

    <!-- 3. Address Strip Cards -->
    <table class="ogpsr-address-table">
        <tr>
            <td class="ogpsr-w-50 ogpsr-v-top ogpsr-pr-5">
                <div class="ogpsr-address-card">
                    <div class="ogpsr-address-card-title ogpsr-brand-text"><?php 
    esc_html_e( 'Billed To', 'semiauto-invoicing' );
    ?></div>
                    <?php 
    if ( !empty( $buyer['company'] ) ) {
        ?>
                        <div class="ogpsr-fw-bold ogpsr-buyer-company"><?php 
        echo esc_html( $buyer['company'] );
        ?></div>
                        <?php 
        if ( !empty( $buyer['name'] ) ) {
            ?>
                            <div class="ogpsr-buyer-contact"><?php 
            echo esc_html( $buyer['name'] );
            ?></div>
                        <?php 
        }
        ?>
                    <?php 
    } elseif ( !empty( $buyer['name'] ) ) {
        ?>
                        <div class="ogpsr-fw-bold"><?php 
        echo esc_html( $buyer['name'] );
        ?></div>
                    <?php 
    }
    ?>
                    <div class="ogpsr-muted-text">
                        <?php 
    if ( !empty( $buyer['address_1'] ) ) {
        echo esc_html( $buyer['address_1'] ) . '<br>';
    }
    ?>
                        <?php 
    if ( !empty( $buyer['address_2'] ) ) {
        echo esc_html( $buyer['address_2'] ) . '<br>';
    }
    ?>
                        <?php 
    echo esc_html( trim( $buyer['city'] . ' ' . $buyer['state'] . ' ' . $buyer['postcode'] ) );
    ?>
                        <?php 
    if ( !empty( $buyer['country'] ) ) {
        echo ', ' . esc_html( $buyer['country'] );
    }
    ?>
                        <?php 
    ?>
                        <?php 
    if ( !empty( $buyer['phone'] ) ) {
        echo '<br>' . esc_html__( 'Tel:', 'semiauto-invoicing' ) . ' ' . esc_html( $buyer['phone'] );
    }
    ?>
                        <?php 
    if ( !empty( $buyer['email'] ) ) {
        echo '<br>' . esc_html( $buyer['email'] );
    }
    ?>
                        <?php 
    if ( !empty( $doc['custom_fields'] ) ) {
        ?>
                            <?php 
        foreach ( $doc['custom_fields'] as $cf ) {
            ?>
                                <br><strong><?php 
            echo esc_html( $cf['label'] );
            ?>:</strong> <?php 
            echo esc_html( $cf['value'] );
            ?>
                            <?php 
        }
        ?>
                        <?php 
    }
    ?>
                    </div>
                </div>
            </td>
            <td class="ogpsr-w-50 ogpsr-v-top ogpsr-pl-5">
                <?php 
    if ( $shipping['enabled'] ) {
        ?>
                    <div class="ogpsr-address-card">
                        <div class="ogpsr-address-card-title ogpsr-brand-text"><?php 
        esc_html_e( 'Shipped To', 'semiauto-invoicing' );
        ?></div>
                        <?php 
        if ( !empty( $shipping['company'] ) ) {
            ?>
                            <div class="ogpsr-fw-bold ogpsr-shipping-company"><?php 
            echo esc_html( $shipping['company'] );
            ?></div>
                            <?php 
            if ( !empty( $shipping['name'] ) ) {
                ?>
                                <div class="ogpsr-shipping-contact"><?php 
                echo esc_html( $shipping['name'] );
                ?></div>
                            <?php 
            }
            ?>
                        <?php 
        } elseif ( !empty( $shipping['name'] ) ) {
            ?>
                            <div class="ogpsr-fw-bold"><?php 
            echo esc_html( $shipping['name'] );
            ?></div>
                        <?php 
        }
        ?>
                        <div class="ogpsr-muted-text">
                            <?php 
        if ( !empty( $shipping['address_1'] ) ) {
            echo esc_html( $shipping['address_1'] ) . '<br>';
        }
        ?>
                            <?php 
        if ( !empty( $shipping['address_2'] ) ) {
            echo esc_html( $shipping['address_2'] ) . '<br>';
        }
        ?>
                            <?php 
        echo esc_html( trim( $shipping['city'] . ' ' . $shipping['state'] . ' ' . $shipping['postcode'] ) );
        ?>
                            <?php 
        if ( !empty( $shipping['country'] ) ) {
            echo ', ' . esc_html( $shipping['country'] );
        }
        ?>
                            <?php 
        ?>
                        </div>
                    </div>
                <?php 
    } else {
        ?>
                    <div class="ogpsr-address-card">
                        <div class="ogpsr-address-card-title ogpsr-brand-text"><?php 
        esc_html_e( 'Order Context', 'semiauto-invoicing' );
        ?></div>
                        <div class="ogpsr-muted-text">
                            <strong><?php 
        esc_html_e( 'Status:', 'semiauto-invoicing' );
        ?></strong> <?php 
        echo esc_html( strtoupper( $doc['order']->get_status() ) );
        ?><br>
                            <?php 
        if ( $doc_type !== 'packaging_slip' ) {
            ?>
                                <strong><?php 
            esc_html_e( 'Currency:', 'semiauto-invoicing' );
            ?></strong> <?php 
            echo esc_html( $totals['currency'] );
            ?><br>
                            <?php 
        }
        ?>
                            <?php 
        if ( !empty( $doc['delivery_pickup']['has_info'] ) ) {
            ?>
                                <strong><?php 
            esc_html_e( 'Schedule:', 'semiauto-invoicing' );
            ?></strong> <?php 
            echo esc_html( $doc['delivery_pickup']['label'] );
            if ( !empty( $doc['delivery_pickup']['time'] ) ) {
                ?> (<?php 
                echo esc_html( $doc['delivery_pickup']['time'] );
                ?>)<?php 
            }
            ?><br>
                            <?php 
        }
        ?>
                            <?php 
        ?>
                        </div>
                    </div>
                <?php 
    }
    ?>
            </td>
        </tr>
    </table>

    <?php 
    ?>

    <!-- 5. Line Items Table -->
    <table class="ogpsr-items-table">
        <thead>
            <tr class="ogpsr-brand-bg">
                <th class="ogpsr-w-50"><?php 
    esc_html_e( 'Item Description', 'semiauto-invoicing' );
    ?></th>
                <th class="ogpsr-text-center ogpsr-w-10"><?php 
    esc_html_e( 'Qty', 'semiauto-invoicing' );
    ?></th>
                <?php 
    if ( $doc_type === 'packaging_slip' ) {
        ?>
                    <th class="ogpsr-text-center ogpsr-w-20"><?php 
        esc_html_e( 'Verified', 'semiauto-invoicing' );
        ?></th>
                <?php 
    } else {
        ?>
                    <th class="ogpsr-text-right ogpsr-w-20"><?php 
        esc_html_e( 'Unit Price', 'semiauto-invoicing' );
        ?></th>
                    <th class="ogpsr-text-right ogpsr-w-20"><?php 
        esc_html_e( 'Amount', 'semiauto-invoicing' );
        ?></th>
                <?php 
    }
    ?>
            </tr>
        </thead>
        <tbody>
            <?php 
    foreach ( $items as $item ) {
        ?>
                <tr>
                    <td class="ogpsr-v-top">
                        <?php 
        if ( $doc_type === 'packaging_slip' ) {
            ?>
                            <span class="ogpsr-checkbox-box"></span>
                        <?php 
        }
        ?>
                        <span class="ogpsr-fw-bold"><?php 
        echo esc_html( $item['name'] );
        ?></span>
                        <?php 
        if ( !empty( $item['description'] ) ) {
            ?>
                            <div class="ogpsr-item-description"><?php 
            echo wp_kses_post( $item['description'] );
            ?></div>
                        <?php 
        }
        ?>
                        <?php 
        if ( !empty( $doc['show_sku'] ) && !empty( $item['sku'] ) ) {
            ?>
                            <div class="ogpsr-item-sku"><?php 
            esc_html_e( 'SKU:', 'semiauto-invoicing' );
            ?> <?php 
            echo esc_html( $item['sku'] );
            ?></div>
                        <?php 
        }
        ?>
                        <?php 
        if ( !empty( $doc['show_ean'] ) && !empty( $item['ean'] ) ) {
            ?>
                            <div class="ogpsr-item-sku"><?php 
            esc_html_e( 'EAN/GTIN:', 'semiauto-invoicing' );
            ?> <?php 
            echo esc_html( $item['ean'] );
            ?></div>
                        <?php 
        }
        ?>
                        <?php 
        if ( !empty( $item['meta'] ) ) {
            ?>
                            <div class="ogpsr-item-meta"><?php 
            echo esc_html( $item['meta'] );
            ?></div>
                        <?php 
        }
        ?>
                        <?php 
        if ( !empty( $item['addons'] ) ) {
            ?>
                            <?php 
            foreach ( $item['addons'] as $addon_text ) {
                ?>
                                <div class="ogpsr-item-meta"><?php 
                echo wp_kses_post( $addon_text );
                ?></div>
                            <?php 
            }
            ?>
                        <?php 
        }
        ?>
                    </td>
                    <td class="ogpsr-text-center ogpsr-v-top ogpsr-fw-bold"><?php 
        echo esc_html( $item['qty'] );
        ?></td>
                    <?php 
        if ( $doc_type === 'packaging_slip' ) {
            ?>
                        <td class="ogpsr-text-center ogpsr-v-top">[ &nbsp; ]</td>
                    <?php 
        } else {
            ?>
                        <td class="ogpsr-text-right ogpsr-v-top"><?php 
            echo wp_kses_post( $item['unit_price'] );
            ?></td>
                        <td class="ogpsr-text-right ogpsr-v-top ogpsr-fw-bold"><?php 
            echo wp_kses_post( $item['subtotal'] );
            ?></td>
                    <?php 
        }
        ?>
                </tr>
            <?php 
    }
    ?>
        </tbody>
    </table>

    <!-- 6. Summary & Totals Layout (Suppressed on Packaging Slips) -->
    <?php 
    if ( $doc_type !== 'packaging_slip' ) {
        ?>
        <table class="ogpsr-summary-table">
            <tr>
                <td class="ogpsr-w-50 ogpsr-v-top ogpsr-pr-15">
                    <?php 
        if ( !empty( $doc['bank_details'] ) ) {
            ?>
                        <div class="ogpsr-bank-details-box">
                            <div class="ogpsr-fw-bold ogpsr-brand-text ogpsr-mb-4"><?php 
            esc_html_e( 'Bank Transfer Details:', 'semiauto-invoicing' );
            ?></div>
                            <?php 
            foreach ( $doc['bank_details'] as $bank ) {
                ?>
                                <div class="ogpsr-bank-account-item">
                                    <?php 
                if ( !empty( $bank['bank_name'] ) ) {
                    ?><strong><?php 
                    echo esc_html( $bank['bank_name'] );
                    ?></strong><br><?php 
                }
                ?>
                                    <?php 
                if ( !empty( $bank['account_name'] ) ) {
                    ?><span><?php 
                    esc_html_e( 'Account Name:', 'semiauto-invoicing' );
                    ?> <?php 
                    echo esc_html( $bank['account_name'] );
                    ?></span><br><?php 
                }
                ?>
                                    <?php 
                if ( !empty( $bank['account_number'] ) ) {
                    ?><span><?php 
                    esc_html_e( 'Account Number:', 'semiauto-invoicing' );
                    ?> <?php 
                    echo esc_html( $bank['account_number'] );
                    ?></span><br><?php 
                }
                ?>
                                    <?php 
                if ( !empty( $bank['sort_code'] ) ) {
                    ?><span><?php 
                    esc_html_e( 'Sort Code:', 'semiauto-invoicing' );
                    ?> <?php 
                    echo esc_html( $bank['sort_code'] );
                    ?></span><br><?php 
                }
                ?>
                                    <?php 
                if ( !empty( $bank['iban'] ) ) {
                    ?><span><?php 
                    esc_html_e( 'IBAN:', 'semiauto-invoicing' );
                    ?> <?php 
                    echo esc_html( $bank['iban'] );
                    ?></span><br><?php 
                }
                ?>
                                    <?php 
                if ( !empty( $bank['bic'] ) ) {
                    ?><span><?php 
                    esc_html_e( 'BIC/SWIFT:', 'semiauto-invoicing' );
                    ?> <?php 
                    echo esc_html( $bank['bic'] );
                    ?></span><br><?php 
                }
                ?>
                                </div>
                            <?php 
            }
            ?>
                        </div>
                    <?php 
        }
        ?>
                </td>
                <td class="ogpsr-w-50 ogpsr-v-top">
                    <?php 
        $rendered_totals = false;
        ?>
                    <?php 
        ?>
                    <?php 
        if ( !$rendered_totals ) {
            ?>
                        <!-- Standard WooCommerce Native Order Totals Card (Exact Match with Order Details & Emails) -->
                        <table class="ogpsr-totals-card">
                            <?php 
            if ( !empty( $doc['order_item_totals'] ) ) {
                ?>
                                <?php 
                foreach ( $doc['order_item_totals'] as $key => $total ) {
                    ?>
                                    <?php 
                    $is_grand_total = $key === 'order_total';
                    $row_class = ( $is_grand_total ? 'ogpsr-grand-total-row ogpsr-brand-bg' : '' );
                    ?>
                                    <tr class="<?php 
                    echo esc_attr( $row_class );
                    ?>">
                                        <td class="<?php 
                    echo esc_attr( ( $is_grand_total ? 'ogpsr-fw-bold' : 'ogpsr-muted-text' ) );
                    ?>"><?php 
                    echo esc_html( rtrim( $total['label'], ':' ) );
                    ?></td>
                                        <td class="ogpsr-text-right <?php 
                    echo esc_attr( ( $is_grand_total ? 'ogpsr-fw-bold' : '' ) );
                    ?>"><?php 
                    echo wp_kses_post( $total['value'] );
                    ?></td>
                                    </tr>
                                <?php 
                }
                ?>
                            <?php 
            } else {
                ?>
                                <tr>
                                    <td class="ogpsr-muted-text"><?php 
                esc_html_e( 'Subtotal', 'semiauto-invoicing' );
                ?></td>
                                    <td class="ogpsr-text-right ogpsr-fw-bold"><?php 
                echo wp_kses_post( $totals['subtotal'] );
                ?></td>
                                </tr>
                                <tr class="ogpsr-grand-total-row ogpsr-brand-bg">
                                    <td>
                                        <?php 
                $a4_total_label = __( 'Grand Total', 'semiauto-invoicing' );
                echo esc_html( $a4_total_label );
                ?>
                                    </td>
                                    <td class="ogpsr-text-right"><?php 
                echo wp_kses_post( $totals['grand_total'] );
                ?></td>
                                </tr>
                            <?php 
            }
            ?>
                        </table>
                    <?php 
        }
        ?>
                </td>
            </tr>
        </table>
    <?php 
    }
    ?>

    <!-- 7. Footer Section -->
    <table class="ogpsr-footer-table">
        <tr>
            <td class="ogpsr-w-50 ogpsr-v-top">
                <?php 
    if ( $doc['show_payment_methods'] === 'yes' && !empty( $doc['payment_methods'] ) && $doc_type !== 'packaging_slip' ) {
        ?>
                    <div class="ogpsr-muted-text ogpsr-mb-6">
                        <strong><?php 
        esc_html_e( 'Accepted Payment Methods:', 'semiauto-invoicing' );
        ?></strong>
                        <?php 
        echo esc_html( implode( ', ', $doc['payment_methods'] ) );
        ?>
                    </div>
                <?php 
    }
    ?>

                <?php 
    ?>
            </td>
            <?php 
    ?>
        </tr>
    </table>

    <?php 
    if ( !empty( $doc['terms_html'] ) && $doc_type !== 'packaging_slip' ) {
        ?>
        <div class="ogpsr-notes-box">
            <strong><?php 
        esc_html_e( 'Terms & Conditions:', 'semiauto-invoicing' );
        ?></strong><br>
            <?php 
        echo wp_kses_post( nl2br( $doc['terms_html'] ) );
        ?>
        </div>
    <?php 
    }
    ?>

    <?php 
    if ( !empty( $doc['footer_text'] ) ) {
        ?>
        <div class="ogpsr-notes-box">
            <?php 
        echo nl2br( esc_html( $doc['footer_text'] ) );
        ?>
        </div>
    <?php 
    }
    ?>

<?php 
    // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
}
?>

</body>
</html>


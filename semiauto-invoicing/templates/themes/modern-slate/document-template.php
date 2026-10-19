<?php

/**
 * Universal Presentation Template: Modern Corporate Slate Theme
 * 
 * Master dynamic presentation template for rendering WooCommerce documents
 * (Invoices, Packaging Slips, Thermal Receipts) across all supported paper formats
 * (A4/Letter and 44mm/57mm/58mm/76mm/80mm Thermal Roll).
 * 
 * @package SemiAutoInvoicing
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
$ogpsr_doc = ( isset( $args['doc'] ) && is_array( $args['doc'] ) ? $args['doc'] : array() );
$ogpsr_doc_type = $ogpsr_doc['doc_type'] ?? 'invoice';
$ogpsr_is_thermal = !empty( $ogpsr_doc['is_thermal'] );
$ogpsr_seller = $ogpsr_doc['seller'] ?? array();
$ogpsr_buyer = $ogpsr_doc['buyer'] ?? array();
$ogpsr_shipping = $ogpsr_doc['shipping'] ?? array(
    'enabled' => false,
);
$ogpsr_doc_totals = $ogpsr_doc['totals'] ?? array();
$ogpsr_items = $ogpsr_doc['line_items'] ?? array();
$ogpsr_paper_width_mm = (int) ($ogpsr_doc['paper_width_mm'] ?? 80);
$ogpsr_roll_class = ( $ogpsr_is_thermal ? ' ogpsr-roll-' . $ogpsr_paper_width_mm . 'mm' : '' );
?>
<!DOCTYPE html>
<html lang="<?php 
echo esc_attr( get_locale() );
?>" class="<?php 
echo esc_attr( (( $ogpsr_is_thermal ? 'ogpsr-format-thermal' : 'ogpsr-format-a4' )) . $ogpsr_roll_class );
?>">
<head>
	<meta charset="utf-8">
	<title><?php 
echo esc_html( $ogpsr_doc['title'] . ' ' . $ogpsr_doc['document_number'] );
?></title>
</head>
<body class="<?php 
echo esc_attr( (( $ogpsr_is_thermal ? 'ogpsr-format-thermal' : 'ogpsr-format-a4' )) . $ogpsr_roll_class );
?>">

<?php 
if ( $ogpsr_is_thermal ) {
    ?>
	<!-- ================================================================== -->
	<!-- THERMAL POS ROLL & KITCHEN COPY LAYOUT (44mm, 57mm, 58mm, 76mm, 80mm) -->
	<!-- ================================================================== -->
	<div class="ogpsr-thermal-header">
		<?php 
    $ogpsr_render_thermal_logo = !empty( $ogpsr_seller['logo_url'] );
    if ( $ogpsr_render_thermal_logo ) {
        ?>
			<img src="<?php 
        echo esc_url( $ogpsr_seller['logo_url'], array('data') );
        ?>" class="ogpsr-thermal-logo" alt="Logo">
		<?php 
    }
    ?>

		<div class="ogpsr-thermal-title"><?php 
    echo esc_html( $ogpsr_seller['name'] );
    ?></div>
		
		<?php 
    $ogpsr_render_thermal_address = !empty( $ogpsr_seller['address_1'] );
    if ( $ogpsr_render_thermal_address ) {
        ?>
			<div class="ogpsr-thermal-meta-row"><?php 
        echo nl2br( esc_html( $ogpsr_seller['address_1'] ) );
        ?></div>
		<?php 
    }
    ?>
		<?php 
    $ogpsr_render_thermal_city = !empty( $ogpsr_seller['city'] );
    if ( $ogpsr_render_thermal_city ) {
        ?>
			<div class="ogpsr-thermal-meta-row"><?php 
        echo esc_html( trim( $ogpsr_seller['city'] . ' ' . $ogpsr_seller['postcode'] ) );
        ?>
			<?php 
        if ( !empty( $ogpsr_seller['country'] ) ) {
            echo ', ' . esc_html( $ogpsr_seller['country'] );
        }
        ?>
			</div>
		<?php 
    }
    ?>
		<?php 
    $ogpsr_render_thermal_contact = !empty( $ogpsr_seller['contact'] );
    if ( $ogpsr_render_thermal_contact ) {
        ?>
			<div class="ogpsr-thermal-meta-row"><?php 
        echo nl2br( esc_html( $ogpsr_seller['contact'] ) );
        ?></div>
			<?php 
    }
    ?>

		<?php 
    if ( !empty( $ogpsr_doc['header_text'] ) ) {
        ?>
			<div class="ogpsr-thermal-meta-row"><?php 
        echo esc_html( $ogpsr_doc['header_text'] );
        ?></div>
		<?php 
    }
    ?>

		<div class="ogpsr-dashed-divider"></div>
		<div class="ogpsr-fw-bold ogpsr-uppercase"><?php 
    echo esc_html( $ogpsr_doc['title'] );
    ?></div>
		<?php 
    echo '<div class="ogpsr-thermal-meta-row">' . esc_html( $ogpsr_doc['document_number'] ) . '</div>';
    ?>
		<div class="ogpsr-thermal-meta-row">
			<?php 
    echo esc_html( $ogpsr_doc['date_created'] );
    ?>
			<?php 
    if ( !empty( $ogpsr_doc['payment_method_title'] ) ) {
        ?>
				| <?php 
        echo esc_html( $ogpsr_doc['payment_method_title'] );
    }
    ?>
		</div>
		<?php 
    if ( !empty( $ogpsr_doc['delivery_pickup']['has_info'] ) ) {
        ?>
			<div class="ogpsr-thermal-meta-row ogpsr-fw-bold">
				<?php 
        esc_html_e( 'Order Type:', 'semiauto-invoicing' );
        ?> <?php 
        echo esc_html( $ogpsr_doc['delivery_pickup']['label'] );
        ?>
				<?php 
        if ( !empty( $ogpsr_doc['delivery_pickup']['time'] ) ) {
            ?>
					| <?php 
            echo esc_html( $ogpsr_doc['delivery_pickup']['time'] );
        }
        ?>
			</div>
			<?php 
    }
    ?>
		<?php 
    if ( !empty( $ogpsr_doc['show_tax_inclusion'] ) ) {
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
    $ogpsr_render_customer_details = true;
    if ( $ogpsr_render_customer_details ) {
        ?>
		<?php 
        $ogpsr_has_cust = !empty( $ogpsr_buyer['name'] ) || !empty( $ogpsr_buyer['company'] ) || !empty( $ogpsr_buyer['address_1'] ) || !empty( $ogpsr_buyer['city'] ) || !empty( $ogpsr_buyer['phone'] ) || !empty( $ogpsr_buyer['email'] ) || !empty( $ogpsr_doc['custom_fields'] );
        ?>
		<?php 
        if ( $ogpsr_has_cust ) {
            ?>
			<div class="ogpsr-dashed-divider"></div>
			<div class="ogpsr-thermal-customer-box">
				<div class="ogpsr-fw-bold ogpsr-customer-title"><?php 
            esc_html_e( 'Customer Details:', 'semiauto-invoicing' );
            ?></div>
				<?php 
            if ( !empty( $ogpsr_buyer['company'] ) ) {
                ?>
					<div class="ogpsr-fw-bold"><?php 
                echo esc_html( $ogpsr_buyer['company'] );
                ?></div>
					<?php 
                if ( !empty( $ogpsr_buyer['name'] ) ) {
                    ?>
						<div><?php 
                    echo esc_html( $ogpsr_buyer['name'] );
                    ?></div>
					<?php 
                }
                ?>
				<?php 
            } elseif ( !empty( $ogpsr_buyer['name'] ) ) {
                ?>
					<div class="ogpsr-fw-bold"><?php 
                echo esc_html( $ogpsr_buyer['name'] );
                ?></div>
				<?php 
            }
            ?>
				<?php 
            if ( !empty( $ogpsr_buyer['address_1'] ) ) {
                ?>
					<div><?php 
                echo esc_html( $ogpsr_buyer['address_1'] );
                ?></div>
				<?php 
            }
            ?>
				<?php 
            if ( !empty( $ogpsr_buyer['address_2'] ) ) {
                ?>
					<div><?php 
                echo esc_html( $ogpsr_buyer['address_2'] );
                ?></div>
				<?php 
            }
            ?>
				<?php 
            if ( !empty( $ogpsr_buyer['city'] ) || !empty( $ogpsr_buyer['postcode'] ) ) {
                ?>
					<div><?php 
                echo esc_html( trim( $ogpsr_buyer['city'] . ' ' . $ogpsr_buyer['postcode'] ) );
                ?></div>
				<?php 
            }
            ?>
				<?php 
            if ( !empty( $ogpsr_buyer['country'] ) ) {
                ?>
					<div><?php 
                echo esc_html( $ogpsr_buyer['country'] );
                ?></div>
				<?php 
            }
            ?>
				<?php 
            if ( !empty( $ogpsr_buyer['phone'] ) ) {
                ?>
					<div><?php 
                esc_html_e( 'Tel:', 'semiauto-invoicing' );
                ?> <?php 
                echo esc_html( $ogpsr_buyer['phone'] );
                ?></div>
				<?php 
            }
            ?>
				<?php 
            if ( !empty( $ogpsr_buyer['email'] ) ) {
                ?>
					<div><?php 
                esc_html_e( 'Email:', 'semiauto-invoicing' );
                ?> <?php 
                echo esc_html( $ogpsr_buyer['email'] );
                ?></div>
					<?php 
            }
            ?>
				<?php 
            if ( !empty( $ogpsr_doc['custom_fields'] ) ) {
                ?>
					<?php 
                foreach ( $ogpsr_doc['custom_fields'] as $ogpsr_cf ) {
                    ?>
						<div><strong><?php 
                    echo esc_html( $ogpsr_cf['label'] );
                    ?>:</strong> <?php 
                    echo esc_html( $ogpsr_cf['value'] );
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
    $ogpsr_render_thermal_price_col = true;
    if ( $ogpsr_render_thermal_price_col ) {
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
    foreach ( $ogpsr_items as $ogpsr_item ) {
        ?>
				<tr>
					<td class="ogpsr-text-left">
						<span class="ogpsr-fw-bold"><?php 
        echo esc_html( $ogpsr_item['name'] );
        ?></span>
						<?php 
        if ( !empty( $ogpsr_item['sku'] ) ) {
            ?>
							<div class="ogpsr-item-sku"><?php 
            esc_html_e( 'SKU:', 'semiauto-invoicing' );
            ?> <?php 
            echo esc_html( $ogpsr_item['sku'] );
            ?></div>
							<?php 
        }
        ?>
						<?php 
        if ( !empty( $ogpsr_doc['show_product_meta'] ) ) {
            ?>
							<?php 
            if ( !empty( $ogpsr_item['meta'] ) ) {
                ?>
								<div class="ogpsr-item-meta"><?php 
                echo esc_html( $ogpsr_item['meta'] );
                ?></div>
							<?php 
            }
            ?>
							<?php 
            if ( !empty( $ogpsr_item['addons'] ) ) {
                ?>
								<?php 
                foreach ( $ogpsr_item['addons'] as $ogpsr_addon_text ) {
                    ?>
									<div class="ogpsr-item-meta"><?php 
                    echo wp_kses_post( $ogpsr_addon_text );
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
        echo esc_html( $ogpsr_item['qty'] );
        ?></td>
					<?php 
        $ogpsr_render_thermal_subtotal = true;
        if ( $ogpsr_render_thermal_subtotal ) {
            ?>
						<td class="ogpsr-thermal-price-col"><?php 
            echo wp_kses_post( $ogpsr_item['subtotal'] );
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
    $ogpsr_render_thermal_totals = true;
    if ( $ogpsr_render_thermal_totals ) {
        ?>
		<table class="ogpsr-thermal-totals-table">
			<?php 
        if ( !empty( $ogpsr_doc['order_item_totals'] ) ) {
            ?>
				<?php 
            foreach ( $ogpsr_doc['order_item_totals'] as $ogpsr_key => $ogpsr_total ) {
                ?>
					<?php 
                $ogpsr_is_grand_total = 'order_total' === $ogpsr_key;
                ?>
					<tr class="<?php 
                echo esc_attr( ( $ogpsr_is_grand_total ? 'ogpsr-thermal-grand-total' : '' ) );
                ?>">
						<td class="<?php 
                echo esc_attr( ( $ogpsr_is_grand_total ? 'ogpsr-fw-bold' : 'ogpsr-muted-text' ) );
                ?>"><?php 
                echo esc_html( rtrim( $ogpsr_total['label'], ':' ) );
                ?></td>
						<td class="ogpsr-text-right <?php 
                echo esc_attr( ( $ogpsr_is_grand_total ? 'ogpsr-fw-bold' : '' ) );
                ?>"><?php 
                echo wp_kses_post( $ogpsr_total['value'] );
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
            echo wp_kses_post( $ogpsr_doc_totals['subtotal'] );
            ?></td>
				</tr>
				<tr class="ogpsr-thermal-grand-total">
					<td class="ogpsr-fw-bold">
						<?php 
            $ogpsr_thermal_total_label = __( 'TOTAL', 'semiauto-invoicing' );
            echo esc_html( $ogpsr_thermal_total_label );
            ?>
					</td>
					<td class="ogpsr-text-right ogpsr-fw-bold"><?php 
            echo wp_kses_post( $ogpsr_doc_totals['grand_total'] );
            ?></td>
				</tr>
				<?php 
        }
        ?>
		</table>
	<?php 
    }
    ?>

	<!-- Thermal Footer Notes & Barcode/QR -->
	<div class="ogpsr-thermal-footer">
		<?php 
    if ( !empty( $ogpsr_doc['customer_note'] ) ) {
        ?>
			<div class="ogpsr-text-left ogpsr-mb-6">
				<strong><?php 
        esc_html_e( 'Note:', 'semiauto-invoicing' );
        ?></strong> <?php 
        echo nl2br( esc_html( $ogpsr_doc['customer_note'] ) );
        ?>
			</div>
			<?php 
    }
    ?>

		<?php 
    $ogpsr_render_thermal_terms = !empty( $ogpsr_doc['terms_html'] );
    if ( $ogpsr_render_thermal_terms ) {
        ?>
			<div class="ogpsr-muted-text ogpsr-text-center ogpsr-mb-4"><?php 
        echo nl2br( esc_html( $ogpsr_doc['terms_html'] ) );
        ?></div>
		<?php 
    }
    ?>

		<?php 
    $ogpsr_render_thermal_footer_text = !empty( $ogpsr_doc['footer_text'] );
    if ( $ogpsr_render_thermal_footer_text ) {
        ?>
			<div><?php 
        echo nl2br( esc_html( $ogpsr_doc['footer_text'] ) );
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
    if ( !empty( $ogpsr_seller['logo_url'] ) ) {
        ?>
					<img src="<?php 
        echo esc_url( $ogpsr_seller['logo_url'], array('data') );
        ?>" class="ogpsr-logo-img" alt="Logo">
				<?php 
    }
    ?>
				<?php 
    if ( !empty( $ogpsr_seller['name'] ) ) {
        ?>
					<div class="ogpsr-business-title"><?php 
        echo esc_html( $ogpsr_seller['name'] );
        ?></div>
				<?php 
    }
    ?>
				<div class="ogpsr-muted-text">
					<?php 
    if ( !empty( $ogpsr_seller['address_1'] ) ) {
        echo nl2br( esc_html( $ogpsr_seller['address_1'] ) ) . '<br>';
    }
    ?>
					<?php 
    if ( !empty( $ogpsr_seller['address_2'] ) ) {
        echo nl2br( esc_html( $ogpsr_seller['address_2'] ) ) . '<br>';
    }
    ?>
					<?php 
    echo esc_html( trim( $ogpsr_seller['city'] . ' ' . $ogpsr_seller['state'] . ' ' . $ogpsr_seller['postcode'] ) );
    ?>
					<?php 
    if ( !empty( $ogpsr_seller['country'] ) ) {
        echo ', ' . esc_html( $ogpsr_seller['country'] );
    }
    ?>
					<?php 
    if ( !empty( $ogpsr_seller['contact'] ) ) {
        ?>
						<?php 
        echo '<br>' . nl2br( esc_html( $ogpsr_seller['contact'] ) );
        ?>
					<?php 
    }
    ?>
				</div>
			</td>
			<td class="ogpsr-w-50 ogpsr-v-top ogpsr-text-right">
				<div class="ogpsr-doc-title-badge ogpsr-brand-text"><?php 
    echo esc_html( $ogpsr_doc['title'] );
    ?></div>
				<div class="ogpsr-doc-number-sub"><?php 
    echo esc_html( $ogpsr_doc['document_number'] );
    ?></div>
				<?php 
    echo '<div class="ogpsr-muted-text ogpsr-mt-4">' . esc_html__( 'Date:', 'semiauto-invoicing' ) . ' ' . esc_html( $ogpsr_doc['date_created'] ) . '</div>';
    ?>
			</td>
		</tr>
	</table>

	<!-- 2. Metric Cards Grid -->
	<?php 
    $ogpsr_has_first_metric = false;
    if ( 'invoice' === $ogpsr_doc_type ) {
        $ogpsr_has_first_metric = !empty( $ogpsr_doc['show_payment_terms'] ) && !empty( $ogpsr_doc['payment_terms'] );
    } elseif ( 'packaging_slip' === $ogpsr_doc_type ) {
        $ogpsr_has_first_metric = true;
    }
    $ogpsr_has_delivery_metric = !empty( $ogpsr_doc['delivery_pickup']['has_info'] ) && (!empty( $ogpsr_doc['delivery_pickup']['time'] ) || !empty( $ogpsr_doc['delivery_pickup']['type'] ));
    $ogpsr_has_third_metric = false;
    if ( 'packaging_slip' === $ogpsr_doc_type ) {
        $ogpsr_has_third_metric = true;
    } elseif ( 'invoice' === $ogpsr_doc_type ) {
        $ogpsr_has_third_metric = !empty( $ogpsr_doc['due_date'] );
    }
    $ogpsr_metric_card_count = 2;
    // Issue Date and Order Ref are always active
    if ( $ogpsr_has_first_metric ) {
        ++$ogpsr_metric_card_count;
    }
    if ( $ogpsr_has_delivery_metric ) {
        ++$ogpsr_metric_card_count;
    }
    if ( $ogpsr_has_third_metric ) {
        ++$ogpsr_metric_card_count;
    }
    $ogpsr_metric_col_class = 'ogpsr-w-25';
    if ( $ogpsr_metric_card_count >= 5 ) {
        $ogpsr_metric_col_class = 'ogpsr-w-20';
    } elseif ( 4 === $ogpsr_metric_card_count ) {
        $ogpsr_metric_col_class = 'ogpsr-w-25';
    } elseif ( 3 === $ogpsr_metric_card_count ) {
        $ogpsr_metric_col_class = 'ogpsr-w-33';
    } elseif ( 2 === $ogpsr_metric_card_count ) {
        $ogpsr_metric_col_class = 'ogpsr-w-50';
    }
    ?>
	<table class="ogpsr-metric-cards-table">
		<tr>
			<?php 
    if ( $ogpsr_has_first_metric ) {
        ?>
				<td class="<?php 
        echo esc_attr( $ogpsr_metric_col_class );
        ?>">
					<div class="ogpsr-metric-card">
						<div class="ogpsr-metric-label">
							<?php 
        $ogpsr_rendered_first_label = false;
        if ( !$ogpsr_rendered_first_label ) {
            if ( 'packaging_slip' === $ogpsr_doc_type ) {
                esc_html_e( 'Slip #', 'semiauto-invoicing' );
            } else {
                esc_html_e( 'Payment Terms', 'semiauto-invoicing' );
            }
        }
        ?>
						</div>
						<div class="ogpsr-metric-val">
							<?php 
        if ( 'invoice' === $ogpsr_doc_type ) {
            echo esc_html( $ogpsr_doc['payment_terms'] );
        } else {
            echo esc_html( $ogpsr_doc['document_number'] );
        }
        ?>
						</div>
					</div>
				</td>
			<?php 
    }
    ?>
			<td class="<?php 
    echo esc_attr( $ogpsr_metric_col_class );
    ?>">
				<div class="ogpsr-metric-card">
					<div class="ogpsr-metric-label"><?php 
    esc_html_e( 'Issue Date', 'semiauto-invoicing' );
    ?></div>
					<div class="ogpsr-metric-val"><?php 
    echo esc_html( $ogpsr_doc['date_created'] );
    ?></div>
				</div>
			</td>
			<?php 
    if ( $ogpsr_has_delivery_metric ) {
        ?>
				<td class="<?php 
        echo esc_attr( $ogpsr_metric_col_class );
        ?>">
					<div class="ogpsr-metric-card ogpsr-metric-card-delivery">
						<div class="ogpsr-metric-label">
							<?php 
        if ( !empty( $ogpsr_doc['delivery_pickup']['type'] ) ) {
            if ( 'pickup' === $ogpsr_doc['delivery_pickup']['type'] ) {
                echo esc_html( ( !empty( $ogpsr_doc['delivery_pickup']['time'] ) ? __( 'Pickup Time', 'semiauto-invoicing' ) : __( 'Pickup', 'semiauto-invoicing' ) ) );
            } else {
                echo esc_html( ( !empty( $ogpsr_doc['delivery_pickup']['time'] ) ? __( 'Delivery Time', 'semiauto-invoicing' ) : __( 'Delivery', 'semiauto-invoicing' ) ) );
            }
        } else {
            echo esc_html( ( !empty( $ogpsr_doc['delivery_pickup']['label'] ) ? $ogpsr_doc['delivery_pickup']['label'] : __( 'Delivery Time', 'semiauto-invoicing' ) ) );
        }
        ?>
						</div>
						<div class="ogpsr-metric-val">
							<?php 
        if ( !empty( $ogpsr_doc['delivery_pickup']['date'] ) && !empty( $ogpsr_doc['delivery_pickup']['time_slot'] ) ) {
            echo esc_html( $ogpsr_doc['delivery_pickup']['date'] ) . '<br>' . esc_html( $ogpsr_doc['delivery_pickup']['time_slot'] );
        } elseif ( !empty( $ogpsr_doc['delivery_pickup']['time'] ) ) {
            echo esc_html( $ogpsr_doc['delivery_pickup']['time'] );
        } else {
            echo esc_html( $ogpsr_doc['delivery_pickup']['label'] );
        }
        ?>
						</div>
					</div>
				</td>
			<?php 
    }
    ?>
			<?php 
    if ( $ogpsr_has_third_metric ) {
        ?>
				<td class="<?php 
        echo esc_attr( $ogpsr_metric_col_class );
        ?>">
					<div class="ogpsr-metric-card">
						<div class="ogpsr-metric-label">
							<?php 
        $ogpsr_rendered_third_label = false;
        if ( !$ogpsr_rendered_third_label ) {
            if ( 'packaging_slip' === $ogpsr_doc_type ) {
                if ( !$ogpsr_has_delivery_metric && !empty( $ogpsr_doc['delivery_pickup']['has_info'] ) ) {
                    echo esc_html( $ogpsr_doc['delivery_pickup']['label'] );
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
        if ( 'packaging_slip' === $ogpsr_doc_type ) {
            if ( !$ogpsr_has_delivery_metric && !empty( $ogpsr_doc['delivery_pickup']['has_info'] ) && !empty( $ogpsr_doc['delivery_pickup']['time'] ) ) {
                echo esc_html( $ogpsr_doc['delivery_pickup']['time'] );
            } else {
                echo esc_html( ( $ogpsr_doc['order']->get_shipping_method() ? $ogpsr_doc['order']->get_shipping_method() : __( 'Standard Delivery', 'semiauto-invoicing' ) ) );
            }
        } else {
            echo esc_html( ( !empty( $ogpsr_doc['due_date'] ) ? $ogpsr_doc['due_date'] : $ogpsr_doc['date_created'] ) );
        }
        ?>
						</div>
					</div>
				</td>
			<?php 
    }
    ?>
			<td class="<?php 
    echo esc_attr( $ogpsr_metric_col_class );
    ?>">
				<div class="ogpsr-metric-card">
					<div class="ogpsr-metric-label"><?php 
    esc_html_e( 'Order Ref', 'semiauto-invoicing' );
    ?></div>
					<div class="ogpsr-metric-val">#<?php 
    echo esc_html( $ogpsr_doc['order_id'] );
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
    if ( !empty( $ogpsr_buyer['company'] ) ) {
        ?>
						<div class="ogpsr-fw-bold ogpsr-buyer-company"><?php 
        echo esc_html( $ogpsr_buyer['company'] );
        ?></div>
						<?php 
        if ( !empty( $ogpsr_buyer['name'] ) ) {
            ?>
							<div class="ogpsr-buyer-contact"><?php 
            echo esc_html( $ogpsr_buyer['name'] );
            ?></div>
						<?php 
        }
        ?>
					<?php 
    } elseif ( !empty( $ogpsr_buyer['name'] ) ) {
        ?>
						<div class="ogpsr-fw-bold"><?php 
        echo esc_html( $ogpsr_buyer['name'] );
        ?></div>
					<?php 
    }
    ?>
					<div class="ogpsr-muted-text">
						<?php 
    if ( !empty( $ogpsr_buyer['address_1'] ) ) {
        echo esc_html( $ogpsr_buyer['address_1'] ) . '<br>';
    }
    ?>
						<?php 
    if ( !empty( $ogpsr_buyer['address_2'] ) ) {
        echo esc_html( $ogpsr_buyer['address_2'] ) . '<br>';
    }
    ?>
						<?php 
    echo esc_html( trim( $ogpsr_buyer['city'] . ' ' . $ogpsr_buyer['state'] . ' ' . $ogpsr_buyer['postcode'] ) );
    ?>
						<?php 
    if ( !empty( $ogpsr_buyer['country'] ) ) {
        echo ', ' . esc_html( $ogpsr_buyer['country'] );
    }
    ?>
						<?php 
    if ( !empty( $ogpsr_buyer['phone'] ) ) {
        echo '<br>' . esc_html__( 'Tel:', 'semiauto-invoicing' ) . ' ' . esc_html( $ogpsr_buyer['phone'] );
    }
    ?>
						<?php 
    if ( !empty( $ogpsr_buyer['email'] ) ) {
        echo '<br>' . esc_html( $ogpsr_buyer['email'] );
    }
    ?>
						<?php 
    if ( !empty( $ogpsr_doc['custom_fields'] ) ) {
        ?>
							<?php 
        foreach ( $ogpsr_doc['custom_fields'] as $ogpsr_cf ) {
            ?>
								<br><strong><?php 
            echo esc_html( $ogpsr_cf['label'] );
            ?>:</strong> <?php 
            echo esc_html( $ogpsr_cf['value'] );
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
    if ( $ogpsr_shipping['enabled'] ) {
        ?>
					<div class="ogpsr-address-card">
						<div class="ogpsr-address-card-title ogpsr-brand-text"><?php 
        esc_html_e( 'Shipped To', 'semiauto-invoicing' );
        ?></div>
						<?php 
        if ( !empty( $ogpsr_shipping['company'] ) ) {
            ?>
							<div class="ogpsr-fw-bold ogpsr-shipping-company"><?php 
            echo esc_html( $ogpsr_shipping['company'] );
            ?></div>
							<?php 
            if ( !empty( $ogpsr_shipping['name'] ) ) {
                ?>
								<div class="ogpsr-shipping-contact"><?php 
                echo esc_html( $ogpsr_shipping['name'] );
                ?></div>
							<?php 
            }
            ?>
						<?php 
        } elseif ( !empty( $ogpsr_shipping['name'] ) ) {
            ?>
							<div class="ogpsr-fw-bold"><?php 
            echo esc_html( $ogpsr_shipping['name'] );
            ?></div>
						<?php 
        }
        ?>
						<div class="ogpsr-muted-text">
							<?php 
        if ( !empty( $ogpsr_shipping['address_1'] ) ) {
            echo esc_html( $ogpsr_shipping['address_1'] ) . '<br>';
        }
        ?>
							<?php 
        if ( !empty( $ogpsr_shipping['address_2'] ) ) {
            echo esc_html( $ogpsr_shipping['address_2'] ) . '<br>';
        }
        ?>
							<?php 
        echo esc_html( trim( $ogpsr_shipping['city'] . ' ' . $ogpsr_shipping['state'] . ' ' . $ogpsr_shipping['postcode'] ) );
        ?>
							<?php 
        if ( !empty( $ogpsr_shipping['country'] ) ) {
            echo ', ' . esc_html( $ogpsr_shipping['country'] );
        }
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
        echo esc_html( strtoupper( $ogpsr_doc['order']->get_status() ) );
        ?><br>
							<?php 
        if ( 'packaging_slip' !== $ogpsr_doc_type ) {
            ?>
								<strong><?php 
            esc_html_e( 'Currency:', 'semiauto-invoicing' );
            ?></strong> <?php 
            echo esc_html( $ogpsr_doc_totals['currency'] );
            ?><br>
							<?php 
        }
        ?>
							<?php 
        if ( !empty( $ogpsr_doc['delivery_pickup']['has_info'] ) ) {
            ?>
								<strong><?php 
            esc_html_e( 'Schedule:', 'semiauto-invoicing' );
            ?></strong> <?php 
            echo esc_html( $ogpsr_doc['delivery_pickup']['label'] );
            ?>
								<?php 
            if ( !empty( $ogpsr_doc['delivery_pickup']['time'] ) ) {
                ?>
									(<?php 
                echo esc_html( $ogpsr_doc['delivery_pickup']['time'] );
                ?>)<?php 
            }
            ?><br>
								<?php 
        }
        ?>
						</div>
					</div>
				<?php 
    }
    ?>
			</td>
		</tr>
	<?php 
    echo '</table>';
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
    if ( 'packaging_slip' === $ogpsr_doc_type ) {
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
    foreach ( $ogpsr_items as $ogpsr_item ) {
        ?>
				<tr>
					<td class="ogpsr-v-top">
						<?php 
        if ( 'packaging_slip' === $ogpsr_doc_type ) {
            ?>
							<span class="ogpsr-checkbox-box"></span>
						<?php 
        }
        ?>
						<span class="ogpsr-fw-bold"><?php 
        echo esc_html( $ogpsr_item['name'] );
        ?></span>
						<?php 
        if ( !empty( $ogpsr_item['description'] ) ) {
            ?>
							<div class="ogpsr-item-description"><?php 
            echo wp_kses_post( $ogpsr_item['description'] );
            ?></div>
						<?php 
        }
        ?>
						<?php 
        if ( !empty( $ogpsr_doc['show_sku'] ) && !empty( $ogpsr_item['sku'] ) ) {
            ?>
							<div class="ogpsr-item-sku"><?php 
            esc_html_e( 'SKU:', 'semiauto-invoicing' );
            ?> <?php 
            echo esc_html( $ogpsr_item['sku'] );
            ?></div>
						<?php 
        }
        ?>
						<?php 
        if ( !empty( $ogpsr_doc['show_ean'] ) && !empty( $ogpsr_item['ean'] ) ) {
            ?>
							<div class="ogpsr-item-sku"><?php 
            esc_html_e( 'EAN/GTIN:', 'semiauto-invoicing' );
            ?> <?php 
            echo esc_html( $ogpsr_item['ean'] );
            ?></div>
						<?php 
        }
        ?>
						<?php 
        if ( !empty( $ogpsr_item['meta'] ) ) {
            ?>
							<div class="ogpsr-item-meta"><?php 
            echo esc_html( $ogpsr_item['meta'] );
            ?></div>
						<?php 
        }
        ?>
						<?php 
        if ( !empty( $ogpsr_item['addons'] ) ) {
            ?>
							<?php 
            foreach ( $ogpsr_item['addons'] as $ogpsr_addon_text ) {
                ?>
								<div class="ogpsr-item-meta"><?php 
                echo wp_kses_post( $ogpsr_addon_text );
                ?></div>
							<?php 
            }
            ?>
						<?php 
        }
        ?>
					</td>
					<td class="ogpsr-text-center ogpsr-v-top ogpsr-fw-bold"><?php 
        echo esc_html( $ogpsr_item['qty'] );
        ?></td>
					<?php 
        if ( 'packaging_slip' === $ogpsr_doc_type ) {
            ?>
						<td class="ogpsr-text-center ogpsr-v-top">[ &nbsp; ]</td>
					<?php 
        } else {
            ?>
						<td class="ogpsr-text-right ogpsr-v-top"><?php 
            echo wp_kses_post( $ogpsr_item['unit_price'] );
            ?></td>
						<td class="ogpsr-text-right ogpsr-v-top ogpsr-fw-bold"><?php 
            echo wp_kses_post( $ogpsr_item['subtotal'] );
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
    if ( 'packaging_slip' !== $ogpsr_doc_type ) {
        ?>
		<table class="ogpsr-summary-table">
			<tr>
				<td class="ogpsr-w-50 ogpsr-v-top ogpsr-pr-15">
					<?php 
        if ( !empty( $ogpsr_doc['bank_details'] ) ) {
            ?>
						<div class="ogpsr-bank-details-box">
							<div class="ogpsr-fw-bold ogpsr-brand-text ogpsr-mb-4"><?php 
            esc_html_e( 'Bank Transfer Details:', 'semiauto-invoicing' );
            ?></div>
							<?php 
            foreach ( $ogpsr_doc['bank_details'] as $ogpsr_bank ) {
                ?>
								<div class="ogpsr-bank-account-item">
									<?php 
                if ( !empty( $ogpsr_bank['bank_name'] ) ) {
                    ?>
										<strong><?php 
                    echo esc_html( $ogpsr_bank['bank_name'] );
                    ?></strong><br><?php 
                }
                ?>
									<?php 
                if ( !empty( $ogpsr_bank['account_name'] ) ) {
                    ?>
										<span><?php 
                    esc_html_e( 'Account Name:', 'semiauto-invoicing' );
                    ?> <?php 
                    echo esc_html( $ogpsr_bank['account_name'] );
                    ?></span><br><?php 
                }
                ?>
									<?php 
                if ( !empty( $ogpsr_bank['account_number'] ) ) {
                    ?>
										<span><?php 
                    esc_html_e( 'Account Number:', 'semiauto-invoicing' );
                    ?> <?php 
                    echo esc_html( $ogpsr_bank['account_number'] );
                    ?></span><br><?php 
                }
                ?>
									<?php 
                if ( !empty( $ogpsr_bank['sort_code'] ) ) {
                    ?>
										<span><?php 
                    esc_html_e( 'Sort Code:', 'semiauto-invoicing' );
                    ?> <?php 
                    echo esc_html( $ogpsr_bank['sort_code'] );
                    ?></span><br><?php 
                }
                ?>
									<?php 
                if ( !empty( $ogpsr_bank['iban'] ) ) {
                    ?>
										<span><?php 
                    esc_html_e( 'IBAN:', 'semiauto-invoicing' );
                    ?> <?php 
                    echo esc_html( $ogpsr_bank['iban'] );
                    ?></span><br><?php 
                }
                ?>
									<?php 
                if ( !empty( $ogpsr_bank['bic'] ) ) {
                    ?>
										<span><?php 
                    esc_html_e( 'BIC/SWIFT:', 'semiauto-invoicing' );
                    ?> <?php 
                    echo esc_html( $ogpsr_bank['bic'] );
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
        $ogpsr_rendered_totals = false;
        ?>
					<?php 
        if ( !$ogpsr_rendered_totals ) {
            ?>
						<!-- Standard WooCommerce Native Order Totals Card (Exact Match with Order Details & Emails) -->
						<table class="ogpsr-totals-card">
							<?php 
            if ( !empty( $ogpsr_doc['order_item_totals'] ) ) {
                ?>
								<?php 
                foreach ( $ogpsr_doc['order_item_totals'] as $ogpsr_key => $ogpsr_total ) {
                    ?>
									<?php 
                    $ogpsr_is_grand_total = 'order_total' === $ogpsr_key;
                    $ogpsr_row_class = ( $ogpsr_is_grand_total ? 'ogpsr-grand-total-row ogpsr-brand-bg' : '' );
                    ?>
									<tr class="<?php 
                    echo esc_attr( $ogpsr_row_class );
                    ?>">
										<td class="<?php 
                    echo esc_attr( ( $ogpsr_is_grand_total ? 'ogpsr-fw-bold' : 'ogpsr-muted-text' ) );
                    ?>"><?php 
                    echo esc_html( rtrim( $ogpsr_total['label'], ':' ) );
                    ?></td>
										<td class="ogpsr-text-right <?php 
                    echo esc_attr( ( $ogpsr_is_grand_total ? 'ogpsr-fw-bold' : '' ) );
                    ?>"><?php 
                    echo wp_kses_post( $ogpsr_total['value'] );
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
                echo wp_kses_post( $ogpsr_doc_totals['subtotal'] );
                ?></td>
								</tr>
								<tr class="ogpsr-grand-total-row ogpsr-brand-bg">
									<td>
										<?php 
                $ogpsr_a4_total_label = __( 'Grand Total', 'semiauto-invoicing' );
                echo esc_html( $ogpsr_a4_total_label );
                ?>
									</td>
									<td class="ogpsr-text-right"><?php 
                echo wp_kses_post( $ogpsr_doc_totals['grand_total'] );
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
    if ( 'yes' === $ogpsr_doc['show_payment_methods'] && !empty( $ogpsr_doc['payment_methods'] ) && 'packaging_slip' !== $ogpsr_doc_type ) {
        ?>
					<div class="ogpsr-muted-text ogpsr-mb-6">
						<strong><?php 
        esc_html_e( 'Accepted Payment Methods:', 'semiauto-invoicing' );
        ?></strong>
						<?php 
        echo esc_html( implode( ', ', $ogpsr_doc['payment_methods'] ) );
        ?>
					</div>
					<?php 
    }
    echo '</td>';
    ?>
		</tr>
	</table>

	<?php 
    if ( !empty( $ogpsr_doc['terms_html'] ) && 'packaging_slip' !== $ogpsr_doc_type ) {
        ?>
		<div class="ogpsr-notes-box">
			<strong><?php 
        esc_html_e( 'Terms & Conditions:', 'semiauto-invoicing' );
        ?></strong><br>
			<?php 
        echo wp_kses_post( nl2br( $ogpsr_doc['terms_html'] ) );
        ?>
		</div>
	<?php 
    }
    ?>

	<?php 
    if ( !empty( $ogpsr_doc['footer_text'] ) ) {
        ?>
		<div class="ogpsr-notes-box">
			<?php 
        echo nl2br( esc_html( $ogpsr_doc['footer_text'] ) );
        ?>
		</div>
	<?php 
    }
    ?>

<?php 
}
?>

</body>
</html>


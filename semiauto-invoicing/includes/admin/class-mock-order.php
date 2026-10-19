<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Mock Order Class for Live Preview Engine
 * 
 * Implements necessary WC_Order methods with high-fidelity demo data
 * to allow for lightning-fast template rendering without DB overhead.
 */
class OG_PSR_Mock_Order {
    public $is_mock = true;

    private $data;

    public function __construct() {
        $this->data = [
            'id'                   => 12345,
            'number'               => '12345',
            'status'               => 'completed',
            'currency'             => ( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD' ),
            'date_created'         => ( class_exists( 'WC_DateTime' ) ? new WC_DateTime(gmdate( 'Y-m-d H:i:s' )) : gmdate( 'Y-m-d H:i:s' ) ),
            'billing'              => [
                'first_name' => 'John',
                'last_name'  => 'Doe',
                'company'    => 'Sample Store Co.',
                'address_1'  => '123 Innovation Drive',
                'address_2'  => 'Suite 404',
                'city'       => 'Metropolis',
                'state'      => 'NY',
                'postcode'   => '10001',
                'country'    => 'US',
                'email'      => 'john.doe@example.com',
                'phone'      => '+1 (555) 123-4567',
            ],
            'shipping'             => [
                'first_name' => 'John',
                'last_name'  => 'Doe',
                'company'    => 'Sample Store Co.',
                'address_1'  => '123 Innovation Drive',
                'address_2'  => 'Suite 404',
                'city'       => 'Metropolis',
                'state'      => 'NY',
                'postcode'   => '10001',
                'country'    => 'US',
            ],
            'payment_method'       => 'bacs',
            'payment_method_title' => 'Direct Bank Transfer',
            'items'                => [[
                'name'     => 'Premium Silk Scarf',
                'qty'      => 1,
                'subtotal' => 120.0,
                'total'    => 120.0,
                'sku'      => 'SILK-001',
                'ean'      => '9780201379624',
                'taxes'    => [
                    'total' => [
                        1 => 6.0,
                    ],
                ],
            ], [
                'name'     => 'Luxury Leather Wallet',
                'qty'      => 2,
                'subtotal' => 300.0,
                'total'    => 300.0,
                'sku'      => 'LTHR-W-02',
                'ean'      => '8435123456789',
                'taxes'    => [
                    'total' => [
                        1 => 15.0,
                    ],
                ],
            ]],
            'shipping_total'       => 20.0,
            'shipping_tax'         => 1.0,
            'discount_total'       => 10.0,
            'total_tax'            => 22.0,
            'total'                => 452.0,
            'meta'                 => [],
        ];
    }

    public function get_id() {
        return $this->data['id'];
    }

    public function get_order_number() {
        return $this->data['number'];
    }

    public function get_status() {
        return $this->data['status'];
    }

    public function get_currency() {
        return $this->data['currency'];
    }

    public function get_date_created() {
        return $this->data['date_created'];
    }

    public function get_type() {
        return 'shop_order';
    }

    public function get_customer_note() {
        return '';
    }

    public function get_fees() {
        $fees = [];
        return $fees;
    }

    public function get_billing_first_name() {
        return $this->data['billing']['first_name'];
    }

    public function get_billing_last_name() {
        return $this->data['billing']['last_name'];
    }

    public function get_billing_company() {
        return $this->data['billing']['company'];
    }

    public function get_billing_address_1() {
        return $this->data['billing']['address_1'];
    }

    public function get_billing_address_2() {
        return $this->data['billing']['address_2'];
    }

    public function get_billing_city() {
        return $this->data['billing']['city'];
    }

    public function get_billing_state() {
        return $this->data['billing']['state'];
    }

    public function get_billing_postcode() {
        return $this->data['billing']['postcode'];
    }

    public function get_billing_country() {
        return $this->data['billing']['country'];
    }

    public function get_billing_email() {
        return $this->data['billing']['email'];
    }

    public function get_billing_phone() {
        return $this->data['billing']['phone'];
    }

    public function get_shipping_first_name() {
        return $this->data['shipping']['first_name'];
    }

    public function get_shipping_last_name() {
        return $this->data['shipping']['last_name'];
    }

    public function get_shipping_company() {
        return $this->data['shipping']['company'];
    }

    public function get_shipping_address_1() {
        return $this->data['shipping']['address_1'];
    }

    public function get_shipping_address_2() {
        return $this->data['shipping']['address_2'];
    }

    public function get_shipping_city() {
        return $this->data['shipping']['city'];
    }

    public function get_shipping_state() {
        return $this->data['shipping']['state'];
    }

    public function get_shipping_postcode() {
        return $this->data['shipping']['postcode'];
    }

    public function get_shipping_country() {
        return $this->data['shipping']['country'];
    }

    public function get_shipping_method() {
        return __( 'Standard Shipping', 'semiauto-invoicing' );
    }

    public function get_payment_method() {
        return $this->data['payment_method'];
    }

    public function get_payment_method_title() {
        return $this->data['payment_method_title'];
    }

    public function get_transaction_id() {
        return 'txn_mock_123456';
    }

    public function get_subtotal() {
        return 420.0;
    }

    public function get_shipping_total() {
        return $this->data['shipping_total'];
    }

    public function get_shipping_tax() {
        return $this->data['shipping_tax'];
    }

    public function get_discount_total() {
        return $this->data['discount_total'];
    }

    public function get_discount_tax() {
        return 0.0;
    }

    public function get_total_tax() {
        return $this->data['total_tax'];
    }

    public function get_total() {
        return $this->data['total'];
    }

    public function get_tax_totals() {
        return [
            1 => (object) [
                'label'       => 'VAT (5%)',
                'amount'      => $this->data['total_tax'],
                'is_compound' => false,
                'tax_rate_id' => 1,
            ],
        ];
    }

    public function get_items( $types = 'line_item' ) {
        $items = [];
        foreach ( $this->data['items'] as $index => $item_data ) {
            $items[$index] = new class($item_data) {
                private $item;

                public function __construct( $d ) {
                    $this->item = $d;
                }

                public function get_id() {
                    return 100 + wp_rand( 1, 99 );
                }

                public function get_name() {
                    return $this->item['name'];
                }

                public function get_quantity() {
                    return $this->item['qty'];
                }

                public function get_subtotal() {
                    return $this->item['subtotal'];
                }

                public function get_total() {
                    return $this->item['total'];
                }

                public function get_subtotal_tax() {
                    return $this->item['taxes']['total'][1] ?? 0.0;
                }

                public function get_total_tax() {
                    return $this->item['taxes']['total'][1] ?? 0.0;
                }

                public function get_taxes() {
                    return $this->item['taxes'];
                }

                public function get_product() {
                    return new class($this->item) {
                        private $item;

                        public function __construct( $d ) {
                            $this->item = $d;
                        }

                        public function get_sku() {
                            return $this->item['sku'];
                        }

                        public function get_image_id() {
                            return 0;
                        }

                        public function get_type() {
                            return 'simple';
                        }

                        public function is_type( $type ) {
                            return $type === 'simple';
                        }

                        public function get_name() {
                            return $this->item['name'];
                        }

                        public function get_description() {
                            return '';
                        }

                        public function get_short_description() {
                            return '';
                        }

                        public function get_price() {
                            return $this->item['subtotal'] / $this->item['qty'];
                        }

                        public function get_regular_price() {
                            return $this->get_price();
                        }

                        public function get_sale_price() {
                            return '';
                        }

                        public function get_meta( $key ) {
                            if ( in_array( $key, [
                                '_ean',
                                '_gtin',
                                '_barcode',
                                '_wpm_gtin'
                            ], true ) ) {
                                return $this->item['ean'] ?? '';
                            }
                            return '';
                        }

                    }
;
                }

                public function get_meta_data() {
                    return [];
                }

                public function get_meta( $key ) {
                    return '';
                }

                public function get_formatted_meta_data( $hide_prefix = '_' ) {
                    return [];
                }

                public function get_all_formatted_meta_data() {
                    return [];
                }

            }
;
        }
        return $items;
    }

    public function get_meta( $key, $single = true, $context = 'view' ) {
        return $this->data['meta'][$key] ?? '';
    }

    public function get_meta_data() {
        $meta_objects = [];
        foreach ( $this->data['meta'] as $k => $v ) {
            $meta_objects[] = (object) [
                'key'   => $k,
                'value' => $v,
            ];
        }
        return $meta_objects;
    }

    public function get_formatted_billing_address() {
        return implode( "\n", array_filter( [
            $this->data['billing']['first_name'] . ' ' . $this->data['billing']['last_name'],
            $this->data['billing']['company'],
            $this->data['billing']['address_1'],
            $this->data['billing']['address_2'],
            $this->data['billing']['city'] . ', ' . $this->data['billing']['state'] . ' ' . $this->data['billing']['postcode'],
            $this->data['billing']['country']
        ] ) );
    }

    public function get_formatted_shipping_address() {
        return implode( "\n", array_filter( [
            $this->data['shipping']['first_name'] . ' ' . $this->data['shipping']['last_name'],
            $this->data['shipping']['company'],
            $this->data['shipping']['address_1'],
            $this->data['shipping']['address_2'],
            $this->data['shipping']['city'] . ', ' . $this->data['shipping']['state'] . ' ' . $this->data['shipping']['postcode'],
            $this->data['shipping']['country']
        ] ) );
    }

    public function get_item_count() {
        $count = 0;
        foreach ( $this->data['items'] as $item ) {
            $count += $item['qty'];
        }
        return $count;
    }

    public function get_coupon_codes() {
        return ['SAVE10'];
    }

    public function get_order_item_totals() {
        $currency = $this->get_currency();
        $totals = [
            'cart_subtotal' => [
                'label' => __( 'Subtotal:', 'semiauto-invoicing' ),
                'value' => wc_price( 420.0, [
                    'currency' => $currency,
                ] ),
            ],
            'discount'      => [
                'label' => __( 'Discount:', 'semiauto-invoicing' ),
                'value' => '-' . wc_price( 10.0, [
                    'currency' => $currency,
                ] ),
            ],
            'shipping'      => [
                'label' => __( 'Shipping:', 'semiauto-invoicing' ),
                'value' => wc_price( 20.0, [
                    'currency' => $currency,
                ] ) . ' <small>' . __( 'via Standard Shipping', 'semiauto-invoicing' ) . '</small>',
            ],
        ];
        $totals['tax'] = [
            'label' => __( 'VAT (5%):', 'semiauto-invoicing' ),
            'value' => wc_price( 22.0, [
                'currency' => $currency,
            ] ),
        ];
        $totals['order_total'] = [
            'label' => __( 'Total:', 'semiauto-invoicing' ),
            'value' => wc_price( 452.0, [
                'currency' => $currency,
            ] ),
        ];
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        return apply_filters( 'woocommerce_get_order_item_totals', $totals, $this );
    }

    public function get_coupons() {
        return [];
    }

    public function get_taxes() {
        return [];
    }

    public function get_shipping_lines() {
        return [];
    }

    public function get_fee_lines() {
        return [];
    }

    public function get_checkout_payment_url() {
        return ( function_exists( 'site_url' ) ? site_url( '/checkout/order-pay/12345/?pay_for_order=true&key=wc_order_mock' ) : 'https://example.com/checkout' );
    }

    public function get_view_order_url() {
        return ( function_exists( 'site_url' ) ? site_url( '/my-account/view-order/12345/' ) : 'https://example.com/my-account/view-order/12345/' );
    }

    public function update_meta_data( $key, $value ) {
        $this->data['meta'][$key] = $value;
    }

    public function delete_meta_data( $key ) {
        unset($this->data['meta'][$key]);
    }

    public function save() {
        return $this->get_id();
    }

    /**
     * Sample fallback BACS accounts for Live Preview rendering when store has none configured.
     *
     * @return array
     */
    public function get_bacs_accounts() {
        return [[
            'bank_name'      => 'Apex Global Commercial Bank',
            'account_name'   => 'Sample Store Co.',
            'account_number' => '9876543210',
            'sort_code'      => '12-34-56',
            'iban'           => 'AE290331234567890123456',
            'bic'            => 'APEXAEAD',
        ]];
    }

}

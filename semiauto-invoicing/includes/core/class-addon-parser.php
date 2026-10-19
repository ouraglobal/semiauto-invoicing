<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Addon Parser Class
 * Extracts and formats product addons from order items.
 */
class OG_PSR_Addon_Parser
{

    /**
     * Get parsed addons for a product item
     *
     * @param WC_Order_Item_Product|object $item
     * @return array List of formatted addon strings
     */
    public static function get_addons($item)
    {
        $addons = [];

        if (!is_object($item) || !method_exists($item, 'get_meta_data')) {
            return $addons;
        }

        $meta_data = $item->get_meta_data();
        if (!is_iterable($meta_data)) {
            return $addons;
        }

        foreach ($meta_data as $meta) {
            if (!is_object($meta) || !isset($meta->key) || strpos((string) $meta->key, '_') === 0) {
                continue;
            }

            $value = $meta->value ?? null;

            if (is_array($value)) {
                foreach ($value as $row) {
                    if (is_array($row) && isset($row['label'])) {
                        $order = method_exists($item, 'get_order') ? $item->get_order() : null;
                        $currency = ($order && method_exists($order, 'get_currency')) ? $order->get_currency() : (function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD');
                        $price = isset($row['price']) && function_exists('wc_price') ? wc_price($row['price'], ['currency' => $currency]) : '';
                        $addons[] = trim($row['label'] . ' ' . $price);
                    }
                }
                continue;
            }

            if (is_string($value) && trim($value) !== '') {
                $display_key = $meta->display_key ?? $meta->key;
                $addons[] = wp_strip_all_tags(
                    $display_key . ': ' . $value
                );
            }
        }

        return $addons;
    }
}

<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Addon Parser Class
 * Extracts and formats product addons from order items.
 */
class OG_PSR_Addon_Parser {

	/**
	 * Determine whether an order-item meta key is a public YITH WAPO add-on key.
	 *
	 * @param string $meta_key Order-item metadata key.
	 * @return bool
	 */
	public static function is_yith_addon_meta_key( $meta_key ) {
		return is_string( $meta_key ) && (bool) preg_match( '/^ywapo-addon-\d+(?:-\d+)*$/i', $meta_key );
	}

	/**
	 * Get parsed add-ons for a product item.
	 * Structured YITH data is preferred; public metadata is a fallback for older orders.
	 *
	 * @param WC_Order_Item_Product|object $item Order item.
	 * @return array List of formatted add-on strings.
	 */
	public static function get_addons( $item ) {
		$addons = array();
		if ( ! is_object( $item ) || ! method_exists( $item, 'get_meta_data' ) ) {
			return $addons;
		}

		foreach ( self::get_yith_addons( $item ) as $addon ) {
			$addons[] = $addon;
		}

		$meta_data = $item->get_meta_data();
		if ( ! is_iterable( $meta_data ) ) {
			return $addons;
		}

		foreach ( $meta_data as $meta ) {
			if ( ! is_object( $meta ) || ! isset( $meta->key ) ) {
				continue;
			}

			$meta_key = (string) $meta->key;
			if ( self::is_yith_addon_meta_key( $meta_key ) || '_ywapo_meta_data' === $meta_key || 0 === strpos( $meta_key, '_' ) ) {
				continue;
			}

			$value = $meta->value ?? null;
			if ( is_array( $value ) ) {
				foreach ( $value as $row ) {
					if ( is_array( $row ) && isset( $row['label'] ) ) {
						$order    = method_exists( $item, 'get_order' ) ? $item->get_order() : null;
						$currency = ( $order && method_exists( $order, 'get_currency' ) ) ? $order->get_currency() : ( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD' );
						$price    = isset( $row['price'] ) && function_exists( 'wc_price' ) ? wc_price( $row['price'], array( 'currency' => $currency ) ) : '';
						$addons[] = trim( self::clean_display_text( $row['label'] ) . ' ' . wp_strip_all_tags( (string) $price ) );
					}
				}
				continue;
			}

			if ( is_string( $value ) && '' !== trim( $value ) ) {
				$display_key = $meta->display_key ?? $meta->key;
				$addons[]    = self::clean_display_text( $display_key . ': ' . $value );
			}
		}

		return $addons;
	}

	/**
	 * Read saved YITH records first, falling back to WooCommerce formatted metadata.
	 *
	 * @param object $item Order item.
	 * @return array
	 */
	private static function get_yith_addons( $item ) {
		$addons   = array();
		$seen_ids = array();

		if ( method_exists( $item, 'get_meta' ) ) {
			$structured = $item->get_meta( '_ywapo_meta_data', true );
			if ( is_array( $structured ) ) {
				self::collect_yith_records( $structured, $addons, $seen_ids );
			}
		}

		if ( ! empty( $addons ) ) {
			return array_values( $addons );
		}

		if ( method_exists( $item, 'get_formatted_meta_data' ) ) {
			foreach ( $item->get_formatted_meta_data( '_' ) as $meta ) {
				if ( ! is_object( $meta ) || ! isset( $meta->key ) || ! self::is_yith_addon_meta_key( (string) $meta->key ) ) {
					continue;
				}

				$value = self::clean_display_text( $meta->display_value ?? $meta->value ?? '' );
				if ( '' === $value ) {
					continue;
				}

				$label = isset( $meta->display_key ) ? self::clean_display_text( $meta->display_key ) : '';
				if ( '' === $label || self::is_yith_addon_meta_key( $label ) ) {
					$addons[] = $value;
				} else {
					$addons[] = $label . ': ' . $value;
				}
			}
		}

		return array_values( $addons );
	}

	/**
	 * Recursively collect YITH saved display_label/display_value records.
	 * Group keys are not treated as a guaranteed parent/child hierarchy.
	 *
	 * @param array $data Structured YITH metadata.
	 * @param array $addons Collected display strings.
	 * @param array $seen_ids IDs already emitted.
	 * @return void
	 */
	private static function collect_yith_records( $data, &$addons, &$seen_ids ) {
		foreach ( $data as $value ) {
			if ( ! is_array( $value ) ) {
				continue;
			}

			if ( isset( $value['display_label'] ) && ( isset( $value['display_value'] ) || isset( $value['addon_value'] ) ) ) {
				$addon_id  = isset( $value['addon_id'] ) ? (string) $value['addon_id'] : '';
				$option_id = isset( $value['option_id'] ) ? (string) $value['option_id'] : '';
				$identity  = ( '' !== $addon_id && '' !== $option_id ) ? $addon_id . '-' . $option_id : '';
				if ( '' !== $identity && isset( $seen_ids[ $identity ] ) ) {
					continue;
				}

				$label         = self::clean_display_text( $value['display_label'] );
				$display_value = self::clean_display_text( $value['display_value'] ?? $value['addon_value'] );
				if ( '' !== $display_value ) {
					$addons[] = ( '' !== $label ? $label . ': ' : '' ) . $display_value;
					if ( '' !== $identity ) {
						$seen_ids[ $identity ] = true;
					}
				}
				continue;
			}

			self::collect_yith_records( $value, $addons, $seen_ids );
		}
	}

	/**
	 * Convert saved display text to plain text without trusting embedded markup.
	 *
	 * @param mixed $value Value to clean.
	 * @return string
	 */
	private static function clean_display_text( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$text    = wp_strip_all_tags( (string) $value );
		$charset = function_exists( 'get_bloginfo' ) ? get_bloginfo( 'charset' ) : 'UTF-8';
		if ( ! is_string( $charset ) || '' === $charset ) {
			$charset = 'UTF-8';
		}

		return trim( html_entity_decode( $text, ENT_QUOTES, $charset ) );
	}
}

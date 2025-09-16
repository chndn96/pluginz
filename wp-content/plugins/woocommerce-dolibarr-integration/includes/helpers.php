<?php
/**
 * Helper Functions
 *
 * @package WC_Dolibarr_Integration
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Convert WooCommerce customer to Dolibarr format
 *
 * @param WC_Customer $customer WooCommerce customer
 * @since 1.0.0
 * @return array
 */
function wc_dolibarr_format_customer_data( $customer ) {
	$country_code = $customer->get_billing_country();
	$country_id = wc_dolibarr_get_country_id($country_code);
	$data = array(
		'name' => $customer->get_display_name(),
		'firstname' => $customer->get_first_name(),
		'lastname' => $customer->get_last_name(),
		'email' => $customer->get_email(),
		'phone' => $customer->get_billing_phone(),
		'address' => $customer->get_billing_address_1(),
		'zip' => $customer->get_billing_postcode(),
		'town' => $customer->get_billing_city(),
		'country_id' => $country_id,
		'state_code' => $customer->get_billing_state(),
		'client' => 1, // Mark as customer
		'status' => 1, // Active
		'code_client' => 'WC' . $customer->get_id() . '-' . time(),
	);

	// Add company information if available
	if ($customer->get_billing_company()) {
		$data['name'] = $customer->get_billing_company();
		$data['name_alias'] = $customer->get_display_name();
		$data['client'] = 2; // Company customer
	}

	return apply_filters('wc_dolibarr_customer_data', $data, $customer);
}

/**
 * Convert WooCommerce order to Dolibarr format
 *
 * @param WC_Order $order WooCommerce order
 * @since 1.0.0
 * @return array
 */
function wc_dolibarr_format_order_data( $order ) {
	$data = array(
		'socid' => 0, // Will be set based on customer sync
		'date' => $order->get_date_created()->getTimestamp(),
		'type' => 0, // Standard order
		'ref_ext' => 'WC-' . $order->get_id(),
		'note_private' => sprintf(__('WooCommerce Order #%d', 'wc-dolibarr'), $order->get_id()),
		'lines' => array(),
	);

	// Add order lines
	foreach ($order->get_items() as $item_id => $item) {
		$product = $item->get_product();
		$line_data = array(
			'desc' => $item->get_name(),
			'subprice' => wc_dolibarr_format_price($item->get_subtotal() / $item->get_quantity()),
			'qty' => $item->get_quantity(),
			'tva_tx' => 0, // Tax rate - will be calculated if tax sync is enabled
			'product_type' => 0,
		);

		// Add product reference if available
		if ($product && $product->get_sku()) {
			$line_data['product_ref'] = $product->get_sku();
		}

		// Add tax information if enabled
		if (wc_dolibarr_get_option('enable_tax_sync', false)) {
			$tax_total = $item->get_subtotal_tax();
			$subtotal = $item->get_subtotal();
			if ($subtotal > 0) {
				$line_data['tva_tx'] = round(($tax_total / $subtotal) * 100, 2);
			}
		}

		$data['lines'][] = apply_filters('wc_dolibarr_order_line_data', $line_data, $item, $order);
	}

	// Add shipping if present
	foreach ($order->get_shipping_methods() as $shipping_item) {
		$shipping_line = array(
			'desc' => $shipping_item->get_name(),
			'subprice' => wc_dolibarr_format_price($shipping_item->get_total()),
			'qty' => 1,
			'product_type' => 1, // Service
		);

		$data['lines'][] = apply_filters('wc_dolibarr_shipping_line_data', $shipping_line, $shipping_item, $order);
	}

	return apply_filters('wc_dolibarr_order_data', $data, $order);
}

/**
 * Convert Dolibarr product to WooCommerce format
 *
 * @param array $dolibarr_product Dolibarr product data
 * @since 1.0.0
 * @return array
 */
function wc_dolibarr_format_wc_product_data( $dolibarr_product ) {
	$data = array(
		'name' => $dolibarr_product['label'] ?? '',
		'description' => $dolibarr_product['description'] ?? '',
		'short_description' => $dolibarr_product['note'] ?? '',
		'sku' => $dolibarr_product['ref'] ?? '',
		'regular_price' => isset($dolibarr_product['price']) ? wc_dolibarr_format_price($dolibarr_product['price']) : '',
		'manage_stock' => true,
		'stock_quantity' => $dolibarr_product['stock_reel'] ?? 0,
		'status' => isset($dolibarr_product['status']) && $dolibarr_product['status'] ? 'publish' : 'draft',
	);

	// Set product type
	$product_type = isset($dolibarr_product['type']) && $dolibarr_product['type'] == 1 ? 'simple' : 'simple';
	$data['type'] = $product_type;

	return apply_filters('wc_dolibarr_wc_product_data', $data, $dolibarr_product);
}

/**
 * Get Dolibarr customer ID from WooCommerce customer
 *
 * @param int|WC_Customer $customer Customer ID or object
 * @since 1.0.0
 * @return string|null
 */
function wc_dolibarr_get_customer_dolibarr_id( $customer ) {
	if (is_numeric($customer)) {
		$customer = new WC_Customer($customer);
	}

	if (!$customer || !$customer->get_id()) {
		return null;
	}

	return $customer->get_meta('_dolibarr_customer_id', true) ?: null;
}

/**
 * Find a Dolibarr customer ID by email.
 *
 * @param string $email Customer email address.
 * @return int|null Dolibarr customer ID if found, null otherwise.
 */
function wc_dolibarr_find_customer_by_email( $email ) {
	if ( empty( $email ) ) {
		return null;
	}

	$api = new WC_Dolibarr_API();
	$customer = $api->find_customer_by_email( $email );

	return ( ! is_wp_error( $customer ) && ! empty( $customer['id'] ) )
		? (int) $customer['id']
		: null;
}


/**
 * Set Dolibarr customer ID for WooCommerce customer
 *
 * @param int|WC_Customer $customer    Customer ID or object
 * @param string          $dolibarr_id Dolibarr customer ID
 * @since 1.0.0
 * @return void
 */
function wc_dolibarr_set_customer_dolibarr_id( $customer, $dolibarr_id ) {
	if (is_numeric($customer)) {
		$customer = new WC_Customer($customer);
	}

	if ($customer && $customer->get_id()) {
		$customer->update_meta_data('_dolibarr_customer_id', $dolibarr_id);
		$customer->save();
	}
}

/**
 * Get Dolibarr order ID from WooCommerce order
 *
 * @param int|WC_Order $order Order ID or object
 * @since 1.0.0
 * @return string|null
 */
function wc_dolibarr_get_order_dolibarr_id( $order ) {
	return wc_dolibarr_get_order_meta($order, '_dolibarr_order_id', true) ?: null;
}

/**
 * Set Dolibarr order ID for WooCommerce order
 *
 * @param int|WC_Order $order       Order ID or object
 * @param string       $dolibarr_id Dolibarr order ID
 * @since 1.0.0
 * @return void
 */
function wc_dolibarr_set_order_dolibarr_id( $order, $dolibarr_id ) {
	wc_dolibarr_update_order_meta($order, '_dolibarr_order_id', $dolibarr_id);
}

/**
 * Get Dolibarr invoice ID from WooCommerce order
 *
 * @param int|WC_Order $order Order ID or object
 * @since 1.0.0
 * @return string|null
 */
function wc_dolibarr_get_order_dolibarr_invoice_id( $order ) {
	return wc_dolibarr_get_order_meta($order, '_dolibarr_invoice_id', true) ?: null;
}

/**
 * Set Dolibarr invoice ID for WooCommerce order
 *
 * @param int|WC_Order $order       Order ID or object
 * @param string       $dolibarr_id Dolibarr invoice ID
 * @since 1.0.0
 * @return void
 */
function wc_dolibarr_set_order_dolibarr_invoice_id( $order, $dolibarr_id ) {
	wc_dolibarr_update_order_meta($order, '_dolibarr_invoice_id', $dolibarr_id);
}

/**
 * Get Dolibarr product ID from WooCommerce product
 *
 * @param int|WC_Product $product Product ID or object
 * @since 1.0.0
 * @return string|null
 */
function wc_dolibarr_get_product_dolibarr_id( $product ) {
	if (is_numeric($product)) {
		$product = wc_get_product($product);
	}

	if (!$product) {
		return null;
	}

	return $product->get_meta('_dolibarr_product_id', true) ?: null;
}

/**
 * Set Dolibarr product ID for WooCommerce product
 *
 * @param int|WC_Product $product     Product ID or object
 * @param string         $dolibarr_id Dolibarr product ID
 * @since 1.0.0
 * @return void
 */
function wc_dolibarr_set_product_dolibarr_id( $product, $dolibarr_id ) {
	if (is_numeric($product)) {
		$product = wc_get_product($product);
	}

	if ($product) {
		$product->update_meta_data('_dolibarr_product_id', $dolibarr_id);
		$product->save();
	}
}

/**
 * Check if order should be synced
 *
 * @param WC_Order $order Order object
 * @since 1.0.0
 * @return bool
 */
function wc_dolibarr_should_sync_order( $order ) {
	// Don't sync if order sync is disabled
	if (!wc_dolibarr_get_option('sync_orders', false)) {
		return false;
	}
	// Don't sync failed or cancelled orders
	$excluded_statuses = apply_filters('wc_dolibarr_excluded_order_statuses', array( 'failed', 'cancelled', 'refunded' ));
	if (in_array($order->get_status(), $excluded_statuses)) {
		return false;
	}

	// Check if order is already synced and hasn't changed
	$last_sync = wc_dolibarr_get_order_meta($order, '_dolibarr_last_sync', true);
	$order_modified = $order->get_date_modified();
	
	if ($last_sync && $order_modified && strtotime($last_sync) >= $order_modified->getTimestamp()) {
		return false;
	}

	return apply_filters('wc_dolibarr_should_sync_order', true, $order);
}

/**
 * Check if customer should be synced
 *
 * @param WC_Customer $customer Customer object
 * @since 1.0.0
 * @return bool
 */
function wc_dolibarr_should_sync_customer( $customer ) {
	// Don't sync if customer sync is disabled
	if (!wc_dolibarr_get_option('sync_customers', false)) {
		return false;
	}

	// Don't sync customers without email
	if (!$customer->get_email()) {
		return false;
	}

	return apply_filters('wc_dolibarr_should_sync_customer', true, $customer);
}

/**
 * Check if product should be synced
 *
 * @param WC_Product $product Product object
 * @since 1.0.0
 * @return bool
 */
function wc_dolibarr_should_sync_product( $product ) {
	// Don't sync if product sync is disabled
	if (!wc_dolibarr_get_option('sync_products', false)) {
		return false;
	}

	// Don't sync variable products (sync variations instead)
	if ($product->is_type('variable')) {
		return false;
	}

	return apply_filters('wc_dolibarr_should_sync_product', true, $product);
}

/**
 * Get sync status display
 *
 * @param string $status Sync status
 * @since 1.0.0
 * @return string
 */
function wc_dolibarr_get_sync_status_display( $status ) {
	$statuses = array(
		'pending' => __('Pending', 'wc-dolibarr'),
		'success' => __('Success', 'wc-dolibarr'),
		'error' => __('Error', 'wc-dolibarr'),
		'skipped' => __('Skipped', 'wc-dolibarr'),
	);

	return isset($statuses[$status]) ? $statuses[$status] : ucfirst($status);
}

/**
 * Get WooCommerce order statuses that should be synced
 *
 * @since 1.0.0
 * @return array
 */
function wc_dolibarr_get_syncable_order_statuses() {
	$default_statuses = array( 'processing', 'completed', 'on-hold' );
	return apply_filters('wc_dolibarr_syncable_order_statuses', $default_statuses);
}

/**
 * Map WooCommerce order status to Dolibarr status
 *
 * @param string $wc_status WooCommerce order status
 * @since 1.0.0
 * @return int
 */
function wc_dolibarr_map_order_status( $wc_status ) {
	$status_mapping = array(
		'pending' => 0,    // Draft
		'processing' => 1, // Validated
		'on-hold' => 0,    // Draft
		'completed' => 3,  // Shipped
		'cancelled' => -1, // Cancelled
		'refunded' => -1,  // Cancelled
		'failed' => -1,    // Cancelled
	);

	$mapped_status = isset($status_mapping[$wc_status]) ? $status_mapping[$wc_status] : 0;
	return apply_filters('wc_dolibarr_mapped_order_status', $mapped_status, $wc_status);
}

/**
 * Get country code for Dolibarr
 *
 * @param string $country_code WooCommerce country code
 * @since 1.0.0
 * @return int
 */
function wc_dolibarr_get_country_id( $country_code ) {
	// This would typically involve a lookup table or API call
	// For now, return a default value
	$country_mapping = array(
		'US' => 1,
		'FR' => 2,
		'DE' => 3,
		'GB' => 4,
		'ES' => 5,
		'IT' => 6,
		'IN' => 7,
	);

	return isset($country_mapping[$country_code]) ? $country_mapping[$country_code] : 0;
}

/**
 * Clean and validate data for API request
 *
 * @param array $data Data to clean
 * @since 1.0.0
 * @return array
 */
function wc_dolibarr_clean_api_data( $data ) {
	// Remove empty values
	$data = array_filter($data, function( $value ) {
		return $value !== '' && $value !== null;
	});

	// Sanitize strings
	array_walk_recursive($data, function( &$value ) {
		if (is_string($value)) {
			$value = sanitize_text_field($value);
		}
	});

	return $data;
}

/**
 * Handle API response errors
 *
 * @param WP_Error|array $response API response
 * @param string         $context  Context for error logging
 * @since 1.0.0
 * @return WP_Error|array
 */
function wc_dolibarr_handle_api_response( $response, $context = '' ) {
	if (is_wp_error($response)) {
		wc_dolibarr_log(
			sprintf('API Error in %s: %s', $context, $response->get_error_message()),
			'error'
		);
		return $response;
	}

	// Check for Dolibarr API error format
	if (isset($response['error'])) {
		$error_message = isset($response['error']['message']) ? $response['error']['message'] : 'Unknown API error';
		wc_dolibarr_log(
			sprintf('Dolibarr API Error in %s: %s', $context, $error_message),
			'error'
		);
		return new WP_Error('dolibarr_api_error', $error_message);
	}

	return $response;
}

/**
 * Get plugin version
 *
 * @since 1.0.0
 * @return string
 */
function wc_dolibarr_get_version() {
	return WC_DOLIBARR_VERSION;
}

/**
 * Check if plugin is active and configured
 *
 * @since 1.0.0
 * @return bool
 */
function wc_dolibarr_is_active() {
	return wc_dolibarr_is_configured() && class_exists('WC_Dolibarr_API');
}

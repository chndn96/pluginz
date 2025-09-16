<?php
/**
 * Dolibarr API Handler
 *
 * @package WC_Dolibarr_Integration
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class WC_Dolibarr_API {
	/**
	 * API base URL
	 *
	 * @var string
	 */
	private $api_url;

	/**
	 * API key
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Logger instance
	 *
	 * @var WC_Dolibarr_Logger
	 */
	private $logger;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->api_url = wc_dolibarr_get_option('api_url');
		$this->api_key = wc_dolibarr_get_option('api_key');
		
		if (class_exists('WC_Dolibarr_Logger')) {
			$this->logger = new WC_Dolibarr_Logger();
		}
	}

	/**
	 * Make API request to Dolibarr
	 *
	 * @param string $endpoint API endpoint
	 * @param string $method   HTTP method (GET, POST, PUT, DELETE)
	 * @param array  $data     Request data
	 * @param array  $headers  Additional headers
	 * @since 1.0.0
	 * @return array|WP_Error
	 */
	public function request( $endpoint, $method = 'GET', $data = array(), $headers = array() ) {
		if (empty($this->api_url) || empty($this->api_key)) {
			return new WP_Error('missing_credentials', __('Dolibarr API credentials not configured.', 'wc-dolibarr'));
		}

		$url = trailingslashit($this->api_url) . 'api/index.php' . $endpoint;

		$default_headers = array(
			'DOLAPIKEY' => $this->api_key,
			'Content-Type' => 'application/json',
			'Accept' => 'application/json',
		);

		$headers = array_merge($default_headers, $headers);

		$args = array(
			'method' => $method,
			'headers' => $headers,
			'timeout' => 30,
			'sslverify' => wc_dolibarr_get_option('ssl_verify', true),
		);

		if (in_array($method, array('POST', 'PUT', 'PATCH')) && !empty($data)) {
			$args['body'] = wp_json_encode($data);
		}

		if (wc_dolibarr_is_debug_mode() && $this->logger) {
			$this->logger->log(
				sprintf('API Request: %s %s', $method, $url),
				'debug',
				array(
					'headers' => $this->sanitize_headers_for_log($headers),
					'body' => $method !== 'GET' ? $data : null,
				)
			);
		}

		$response = wp_remote_request($url, $args);

		if (is_wp_error($response)) {
			if ($this->logger) {
				$this->logger->log(
					sprintf('API Request Error: %s', $response->get_error_message()),
					'error',
					array(
						'endpoint' => $endpoint,
						'method' => $method,
					)
				);
			}
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code($response);
		$response_body = wp_remote_retrieve_body($response);

		if (wc_dolibarr_is_debug_mode() && $this->logger) {
			$this->logger->log(
				sprintf('API Response: %d', $response_code),
				'debug',
				array(
					'endpoint' => $endpoint,
					'response_body' => $response_body,
				)
			);
		}

		if ($response_code >= 400) {
			$error_message = $this->parse_error_message($response_body, $response_code);
			
			if ($this->logger) {
				$this->logger->log(
					sprintf('API Error %d: %s', $response_code, $error_message),
					'error',
					array(
						'endpoint' => $endpoint,
						'method' => $method,
						'response_body' => $response_body,
					)
				);
			}

			return new WP_Error(
				'api_error',
				$error_message,
				array( 'status' => $response_code )
			);
		}

		$decoded_response = json_decode($response_body, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			if ($this->logger) {
				$this->logger->log(
					'API Response JSON decode error: ' . json_last_error_msg(),
					'error',
					array(
						'endpoint' => $endpoint,
						'response_body' => $response_body,
					)
				);
			}
			return new WP_Error('json_decode_error', __('Invalid JSON response from Dolibarr API.', 'wc-dolibarr'));
		}

		return $decoded_response;
	}

	/**
	 * Test API connection
	 *
	 * @since 1.0.0
	 * @return array|WP_Error
	 */
	public function test_connection() {
		$response = $this->request('/status');

		if (is_wp_error($response)) {
			return $response;
		}

		if (isset($response['success']['dolibarr_version'])) {
			return array(
				'success' => true,
				'version' => $response['success']['dolibarr_version'],
				'message' => sprintf(__('Connection successful! Dolibarr %s is accessible.', 'wc-dolibarr'), $response['success']['dolibarr_version']),
			);
		}

		return new WP_Error('invalid_response', __('Invalid response format from Dolibarr API.', 'wc-dolibarr'));
	}

	/**
	 * Get Dolibarr warehouses
	 *
	 * @since 1.0.0
	 * @return array|WP_Error
	 */
	public function get_warehouses() {
		$response = $this->request('/warehouses');

		if (is_wp_error($response)) {
			return $response;
		}

		// Transform response to match expected format
		$warehouses = array();
		foreach ($response as $warehouse) {
			$warehouses[] = array(
				'id' => $warehouse['id'],
				'ref' => $warehouse['ref'] ?? '',
				'label' => $warehouse['label'] ?? $warehouse['name'] ?? "Warehouse {$warehouse['id']}",
				'description' => $warehouse['description'] ?? '',
				'address' => $warehouse['address'] ?? '',
				'zip' => $warehouse['zip'] ?? '',
				'town' => $warehouse['town'] ?? '',
				'country' => $warehouse['country'] ?? '',
			);
		}

		return $warehouses;
	}

	/**
	 * Get Dolibarr payment methods
	 *
	 * @param string $lang Language code
	 * @since 1.0.0
	 * @return array|WP_Error
	 */
	public function get_payment_methods( $lang = 'en_US' ) {
		$response = $this->request('/setup/dictionary/payment_types?lang=' . $lang);

		if (is_wp_error($response)) {
			return $response;
		}

		// Transform response to match expected format
		$methods = array();
		foreach ($response as $method) {
			$methods[] = array(
				'id' => $method['id'],
				'code' => $method['code'],
				'type' => $method['type'] ?? '',
				'label' => $method['label'],
				'module' => $method['module'] ?? null,
			);
		}

		return $methods;
	}

	/**
	 * Get Dolibarr bank accounts
	 *
	 * @since 1.0.0
	 * @return array|WP_Error
	 */
	public function get_bank_accounts() {
		$response = $this->request('/bankaccounts');

		if (is_wp_error($response)) {
			return $response;
		}

		// Transform response to match expected format
		$accounts = array();
		foreach ($response as $account) {
			$accounts[] = array(
				'id' => $account['id'],
				'ref' => $account['ref'] ?? '',
				'label' => $account['label'],
				'bank' => $account['bank'] ?? '',
				'account_number' => $account['account_number'] ?? '',
				'currency_code' => $account['currency_code'] ?? '',
				'active' => $account['active'] ?? '1',
			);
		}

		return $accounts;
	}

	/**
	 * Get Dolibarr customers
	 *
	 * @param array $params Query parameters
	 * @since 1.0.0
	 * @return array|WP_Error
	 */
	public function get_customers( $params = array() ) {
		$endpoint = '/thirdparties';
		
		if (!empty($params)) {
			$endpoint .= '?' . http_build_query($params);
		}

		return $this->request($endpoint);
	}

	/**
	 * Get Dolibarr customer by ID
	 *
	 * @param int $customer_id Customer ID
	 * @since 1.0.0
	 * @return array|WP_Error
	 */
	public function get_customer( $customer_id ) {
		return $this->request('/thirdparties/' . $customer_id);
	}

	/**
	 * Create Dolibarr customer
	 *
	 * @param array $customer_data Customer data
	 * @since 1.0.0
	 * @return array|WP_Error
	 */
	public function create_customer( $customer_data ) {
		return $this->request('/thirdparties', 'POST', $customer_data);
	}

	/**
	 * Update Dolibarr customer
	 *
	 * @param int   $customer_id   Customer ID
	 * @param array $customer_data Customer data
	 * @since 1.0.0
	 * @return array|WP_Error
	 */
	public function update_customer( $customer_id, $customer_data ) {
		return $this->request('/thirdparties/' . $customer_id, 'PUT', $customer_data);
	}

	/**
	 * Get Dolibarr products
	 *
	 * @param array $params Query parameters
	 * @since 1.0.0
	 * @return array|WP_Error
	 */
	public function get_products( $params = array() ) {
		$endpoint = '/products';
		
		if (!empty($params)) {
			$endpoint .= '?' . http_build_query($params);
		}

		return $this->request($endpoint);
	}

	/**
	 * Get Dolibarr product by ID
	 *
	 * @param int $product_id Product ID
	 * @since 1.0.0
	 * @return array|WP_Error
	 */
	public function get_product( $product_id ) {
		return $this->request('/products/' . $product_id);
	}

	/**
	 * Create Dolibarr product
	 *
	 * @param array $product_data Product data
	 * @since 1.0.0
	 * @return array|WP_Error
	 */
	public function create_product( $product_data ) {
		return $this->request('/products', 'POST', $product_data);
	}

	/**
	 * Update Dolibarr product
	 *
	 * @param int   $product_id   Product ID
	 * @param array $product_data Product data
	 * @since 1.0.0
	 * @return array|WP_Error
	 */
	public function update_product( $product_id, $product_data ) {
		return $this->request('/products/' . $product_id, 'PUT', $product_data);
	}

	/**
	 * Get Dolibarr orders
	 *
	 * @param array $params Query parameters
	 * @since 1.0.0
	 * @return array|WP_Error
	 */
	public function get_orders( $params = array() ) {
		$endpoint = '/orders';
		
		if (!empty($params)) {
			$endpoint .= '?' . http_build_query($params);
		}

		return $this->request($endpoint);
	}

	/**
	 * Get Dolibarr order by ID
	 *
	 * @param int $order_id Order ID
	 * @since 1.0.0
	 * @return array|WP_Error
	 */
	public function get_order( $order_id ) {
		return $this->request('/orders/' . $order_id);
	}

	/**
	 * Create Dolibarr order
	 *
	 * @param array $order_data Order data
	 * @since 1.0.0
	 * @return array|WP_Error
	 */
	public function create_order( $order_data ) {
		return $this->request('/orders', 'POST', $order_data);
	}

	/**
	 * Update Dolibarr order
	 *
	 * @param int   $order_id   Order ID
	 * @param array $order_data Order data
	 * @since 1.0.0
	 * @return array|WP_Error
	 */
	public function update_order( $order_id, $order_data ) {
		return $this->request('/orders/' . $order_id, 'PUT', $order_data);
	}

	/**
	 * Get product inventory
	 *
	 * @param int $product_id   Product ID
	 * @param int $warehouse_id Warehouse ID (optional)
	 * @since 1.0.0
	 * @return array|WP_Error
	 */
	public function get_product_inventory( $product_id, $warehouse_id = null ) {
		$endpoint = '/products/' . $product_id . '/stock';
		
		if ($warehouse_id) {
			$endpoint .= '?warehouse=' . $warehouse_id;
		}

		return $this->request($endpoint);
	}

	/**
	 * Update product inventory
	 *
	 * @param int   $product_id   Product ID
	 * @param int   $warehouse_id Warehouse ID
	 * @param float $quantity     Quantity
	 * @since 1.0.0
	 * @return array|WP_Error
	 */
	public function update_product_inventory( $product_id, $warehouse_id, $wc_quantity, $wc_price ) {
		$result = [];
	
		// 1. Get current product from Dolibarr
		$product = $this->request('/products/' . $product_id, 'GET');
		if ( is_wp_error($product) ) {
			return $product; 
		}
		$dolibarr_stock = isset($product['stock_reel']) ? (int) $product['stock_reel'] : 0;
		$dolibarr_price = isset($product['price']) ? (float) $product['price'] : 0;
	
		// 2. Stock sync
		$diff = $wc_quantity - $dolibarr_stock;
		if ($diff != 0) {
			$movement = $diff > 0 ? 'input' : 'output';
			$stock_data = [
				'product_id'   => $product_id,
				'warehouse_id' => $warehouse_id,
				'qty'          => $diff,
				'movement'     => $movement,
				'label'        => 'WooCommerce Sync',
			];
			$response = $this->request('/stockmovements', 'POST', $stock_data);
			$result['stock'] = $response;
		} else {
			$result['stock'] = "No stock change (Dolibarr=$dolibarr_stock, Woo=$wc_quantity)";
		}
	
		// 3. Price sync (only if $wc_price passed)
		if ( $wc_price !== null && $wc_price != $dolibarr_price ) {
			$data = [
				'price'          => (float) $wc_price,
				'price_ttc'      => (float) $wc_price, // or adjust for tax
				'price_base_type'=> 'HT',
			];
			$response = $this->update_product( $product_id, $data );
			$result['price'] = $response;
		} else {
			$result['price'] = "No price change (Dolibarr=$dolibarr_price, Woo=$wc_price)";
		}
		return $result;
	}
	
	
	

	/**
	 * Find customer by email
	 *
	 * @param string $email Customer email
	 * @since 1.0.0
	 * @return array|WP_Error|null
	 */
	public function find_customer_by_email( $email ) {
		$customers = $this->get_customers();

		if (is_wp_error($customers)) {
			return $customers;
		}

		foreach ($customers as $customer) {
			if (isset($customer['email']) && strtolower($customer['email']) === strtolower($email)) {
				return $customer;
			}
		}

		return null;
	}

	/**
	 * Parse error message from response
	 *
	 * @param string $response_body Response body
	 * @param int    $status_code   HTTP status code
	 * @since 1.0.0
	 * @return string
	 */
	private function parse_error_message( $response_body, $status_code ) {
		$decoded = json_decode($response_body, true);

		if (json_last_error() === JSON_ERROR_NONE && isset($decoded['error']['message'])) {
			return $decoded['error']['message'];
		}

		switch ($status_code) {
			case 401:
				return __('Invalid API key. Please check your Dolibarr API configuration.', 'wc-dolibarr');
			case 403:
				return __('Access denied. Please check your Dolibarr API permissions.', 'wc-dolibarr');
			case 404:
				return __('Resource not found.', 'wc-dolibarr');
			case 500:
				return __('Internal server error in Dolibarr.', 'wc-dolibarr');
			default:
				return sprintf(__('HTTP error %d: %s', 'wc-dolibarr'), $status_code, $response_body);
		}
	}

	/**
	 * Sanitize headers for logging (remove sensitive data)
	 *
	 * @param array $headers Headers array
	 * @since 1.0.0
	 * @return array
	 */
	private function sanitize_headers_for_log( $headers ) {
		$sanitized = $headers;
		
		if (isset($sanitized['DOLAPIKEY'])) {
			$sanitized['DOLAPIKEY'] = '***REDACTED***';
		}

		return $sanitized;
	}

	/**
	 * Check if API is configured
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function is_configured() {
		return !empty($this->api_url) && !empty($this->api_key);
	}

	/**
	 * Get API URL
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_api_url() {
		return $this->api_url;
	}

	/**
	 * Set API credentials
	 *
	 * @param string $api_url API URL
	 * @param string $api_key API key
	 * @since 1.0.0
	 * @return void
	 */
	public function set_credentials( $api_url, $api_key ) {
		$this->api_url = $api_url;
		$this->api_key = $api_key;
	}
}

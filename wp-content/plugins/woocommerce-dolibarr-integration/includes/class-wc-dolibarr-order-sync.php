<?php
/**
 * Dolibarr Order Sync
 *
 * @package WC_Dolibarr_Integration
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class WC_Dolibarr_Order_Sync {
	/**
	 * API instance
	 *
	 * @var WC_Dolibarr_API
	 */
	private $api;

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
		$this->api = new WC_Dolibarr_API();
		$this->logger = new WC_Dolibarr_Logger();
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		add_action('woocommerce_order_status_changed', array( $this, 'sync_order_on_status_change' ), 10, 3);
		add_action('woocommerce_new_order', array( $this, 'sync_order_on_create' ));
	}

	/**
	 * Sync order when status changes
	 *
	 * @param int    $order_id   Order ID
	 * @param string $old_status Old status
	 * @param string $new_status New status
	 * @since 1.0.0
	 */
	public function sync_order_on_status_change( $order_id, $old_status, $new_status ) {
		$syncable_statuses = wc_dolibarr_get_syncable_order_statuses();
		
		if (in_array($new_status, $syncable_statuses)) {
			$this->sync_order($order_id);
		}
	}

	/**
	 * Sync order when created
	 *
	 * @param int $order_id Order ID
	 * @since 1.0.0
	 */
	public function sync_order_on_create( $order_id ) {
		// Delay sync to ensure order is fully saved
		wp_schedule_single_event(time() + 60, 'wc_dolibarr_sync_single_order', array( $order_id ));
	}

	/**
	 * Sync single order
	 *
	 * @param int|WC_Order $order Order ID or object
	 * @since 1.0.0
	 * @return array|WP_Error
	 */
	public function sync_order( $order ) {
		if (is_numeric($order)) {
			$order = wc_get_order($order);
		}

		if (!$order) {
			return new WP_Error('invalid_order', __('Invalid order.', 'wc-dolibarr'));
		}

		// Check if order should be synced
		if (!wc_dolibarr_should_sync_order($order)) {
			$this->logger->log_sync('order', $order->get_id(), null, 'skipped', 'Order sync disabled or not eligible');
			return array( 'status' => 'skipped', 'message' => 'Order sync disabled or not eligible' );
		}
		// Ensure customer is synced first
		$customer_result = $this->ensure_customer_synced($order);
		if (is_wp_error($customer_result)) {
			return $customer_result;
		}
		foreach ($order->get_items() as $item_id => $item) {
			$product = null;
			if ($item instanceof WC_Order_Item_Product) {
				$product = $item->get_product();
				if (!$product) {
					$product_id = $item->get_product_id();
					if ($product_id) {
						$product = wc_get_product($product_id);
					}
				}
			}
			if ($product) {
				$product_result = $this->ensure_product_synced($product);
				if (is_wp_error($product_result)) {
					return $product_result;
				}
			}
		}
		$dolibarr_order_id = wc_dolibarr_get_order_dolibarr_id($order);
		$order_data = wc_dolibarr_format_order_data($order);
		// Set customer ID from sync result
		if (isset($customer_result['dolibarr_id'])) {
			$order_data['socid'] = $customer_result['dolibarr_id'];
		}

		try {
			if ($dolibarr_order_id) {
				// Update existing order
				$result = $this->api->update_order($dolibarr_order_id, $order_data);
				$action = 'updated';
			} else {
				// Create new order
				$result = $this->api->create_order($order_data);
				$action = 'created';
			}

			if (is_wp_error($result)) {
				$this->logger->log_sync('order', $order->get_id(), $dolibarr_order_id, 'error', $result->get_error_message());
				return $result;
			}

			// Extract order ID from response
			if (!$dolibarr_order_id && isset($result)) {
				$dolibarr_order_id = (int) $result;
			}

			// Save Dolibarr ID to order meta
			wc_dolibarr_set_order_dolibarr_id($order, $dolibarr_order_id);
			wc_dolibarr_update_order_meta($order, '_dolibarr_last_sync', wc_dolibarr_get_current_timestamp());

			// Persist to order sync history table for dashboard
			$this->save_sync_history($order->get_id(), $dolibarr_order_id, 'success', 'order','');

			$message = sprintf(__('Order %s successfully.', 'wc-dolibarr'), $action);
			$this->logger->log_sync('order', $order->get_id(), $dolibarr_order_id, 'success', $message);

			return array(
				'status' => 'success',
				'message' => $message,
				'dolibarr_id' => $dolibarr_order_id,
				'action' => $action,
			);

		} catch (Exception $e) {
			$error_message = sprintf(__('Order sync failed: %s', 'wc-dolibarr'), $e->getMessage());
			$this->logger->log_sync('order', $order->get_id(), $dolibarr_order_id, 'error', $error_message);
			$this->save_sync_history($order->get_id(), $dolibarr_order_id, 'error', 'order', $e->getMessage());
			return new WP_Error('sync_error', $error_message);
		}
	}

	/**
	 * Save order sync history for dashboard
	 */
	private function save_sync_history( $wc_order_id, $dolibarr_order_id, $status, $sync_type, $error_message = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wc_dolibarr_order_sync_history';
		$wpdb->replace(
			$table,
			array(
				'order_id' => $wc_order_id,
				'dolibarr_order_id' => $dolibarr_order_id,
				'sync_status' => $status,
				'sync_type' => $sync_type,
				'error_message' => $error_message,
				'last_sync_at' => wc_dolibarr_get_current_timestamp(),
			),
			array(
				'%d','%s','%s','%s','%s','%s'
			)
		);
	}

	/**
	 * Ensure customer is synced before order sync
	 *
	 * @param WC_Order $order Order object
	 * @since 1.0.0
	 * @return array|WP_Error
	 */
	private function ensure_customer_synced( $order ) {
		$customer_id = $order->get_customer_id();
		if ($customer_id) {
			$customer = new WC_Customer($customer_id);
			$dolibarr_customer_id = wc_dolibarr_get_customer_dolibarr_id($customer);
			if (!$dolibarr_customer_id) {

				// Sync customer first
				$customer_sync = new WC_Dolibarr_Customer_Sync();
				$customer_result = $customer_sync->sync_customer($customer);
				
				if (is_wp_error($customer_result)) {
					return $customer_result;
				}
				
				return $customer_result;
			}
			return array( 'dolibarr_id' => $dolibarr_customer_id );
		}
		$email = $order->get_billing_email();
		if ( $email ) {
			$dolibarr_customer_id = wc_dolibarr_find_customer_by_email( $email );
			if ( $dolibarr_customer_id ) {
				return array( 'dolibarr_id' => $dolibarr_customer_id );
			}
		}

		// Guest customer - create minimal customer record
		return $this->create_guest_customer($order);
	}

	/**
 * Ensure product is synced in Dolibarr before order sync
 *
 * @param WC_Product $product
 * @return array|WP_Error Array with dolibarr_id or WP_Error
 */
	private function ensure_product_synced( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return new WP_Error( 'invalid_product', __( 'Invalid WooCommerce product.', 'wc-dolibarr' ) );
		}

		// Check if already synced
		$dolibarr_product_id = wc_dolibarr_get_product_dolibarr_id( $product );
		if ( $dolibarr_product_id ) {
			return array( 'dolibarr_id' => $dolibarr_product_id );
		}

		// Not yet synced → export to Dolibarr
		$product_sync = new WC_Dolibarr_Product_Sync();
		$data = $product_sync->map_wc_to_dolibarr_product( $product );
		$response = $this->api->create_product( $data );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ($response) {
			update_post_meta( $product->get_id(), '_dolibarr_product_id', (int) $response );
			return array( 'dolibarr_id' => (int) $response );
		}

		return new WP_Error( 'sync_failed', __( 'Failed to sync product to Dolibarr.', 'wc-dolibarr' ) );
	}


	/**
	 * Create guest customer for order
	 *
	 * @param WC_Order $order Order object
	 * @since 1.0.0
	 * @return array|WP_Error
	 */
	private function create_guest_customer( $order ) {
		$country_code = $order->get_billing_country();
		$country_id = wc_dolibarr_get_country_id($country_code);
		$customer_data = array(
			'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'firstname' => $order->get_billing_first_name(),
			'lastname' => $order->get_billing_last_name(),
			'email' => $order->get_billing_email(),
			'phone' => $order->get_billing_phone(),
			'address' => $order->get_billing_address_1(),
			'zip' => $order->get_billing_postcode(),
			'town' => $order->get_billing_city(),
			'country_id' => $country_id,
			'client' => 1,
			'status' => 1,
			'code_client' => 'WCG' . $order->get_id() . '-' . time(),
		);

		if ($order->get_billing_company()) {
			$customer_data['name'] = $order->get_billing_company();
			$customer_data['name_alias'] = $customer_data['firstname'] . ' ' . $customer_data['lastname'];
			$customer_data['client'] = 2;
		}

		$result = $this->api->create_customer($customer_data);
		if (is_wp_error($result)) {
			return $result;
		}else{
			$this->save_sync_history($order->get_id(), (int) $result,'success','customer', '');
			$this->logger->log_sync('customer', $result, $result, 'success', 'Guest customer created sucessfully');
		}

		return array( 'dolibarr_id' => (int) ($result ?? 0) );
	}

	/**
	 * Sync all orders
	 *
	 * @param int $limit  Number of orders to sync (0 for all)
	 * @param int $offset Offset for pagination
	 * @since 1.0.0
	 * @return array|WP_Error
	 */
	public function sync_all_orders( $limit = 0, $offset = 0 ) {
		if (!WC_Dolibarr_Connection_Validator::is_connection_valid()) {
			return new WP_Error('connection_invalid', __('Dolibarr connection is not valid.', 'wc-dolibarr'));
		}

		$args = array(
			'status' => wc_dolibarr_get_syncable_order_statuses(),
			'limit' => $limit > 0 ? $limit : -1,
			'offset' => $offset,
			'orderby' => 'date',
			'order' => 'DESC',
		);

		$orders = wc_get_orders($args);
		$results = array(
			'total' => count($orders),
			'synced' => 0,
			'errors' => 0,
			'skipped' => 0,
			'details' => array(),
		);

		$this->logger->log(sprintf('Starting bulk order sync for %d orders.', count($orders)), 'info');
		foreach ($orders as $order) {
			$result = $this->sync_order($order);

			if (is_wp_error($result)) {
				$results['errors']++;
				$results['details'][] = array(
					'order_id' => $order->get_id(),
					'status' => 'error',
					'message' => $result->get_error_message(),
				);
			} else {
				if ($result['status'] === 'success') {
					$results['synced']++;
				} elseif ($result['status'] === 'skipped') {
					$results['skipped']++;
				}
				
				$results['details'][] = array(
					'order_id' => $order->get_id(),
					'status' => $result['status'],
					'message' => $result['message'],
					'dolibarr_id' => $result['dolibarr_id'] ?? null,
				);
			}

			// Prevent timeout and memory issues
			if (function_exists('wp_suspend_cache_addition')) {
				wp_suspend_cache_addition(true);
			}
		}

		$summary = sprintf(
			__('Order sync completed. Total: %d, Synced: %d, Errors: %d, Skipped: %d', 'wc-dolibarr'),
			$results['total'],
			$results['synced'],
			$results['errors'],
			$results['skipped']
		);

		$this->logger->log($summary, 'info');

		return $results;
	}
}

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
			if (!$dolibarr_order_id && isset($result['id'])) {
				$dolibarr_order_id = $result['id'];
			}

			// Save Dolibarr ID to order meta
			wc_dolibarr_set_order_dolibarr_id($order, $dolibarr_order_id);
			wc_dolibarr_update_order_meta($order, '_dolibarr_last_sync', wc_dolibarr_get_current_timestamp());

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
			return new WP_Error('sync_error', $error_message);
		}
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

		// Guest customer - create minimal customer record
		return $this->create_guest_customer($order);
	}

	/**
	 * Create guest customer for order
	 *
	 * @param WC_Order $order Order object
	 * @since 1.0.0
	 * @return array|WP_Error
	 */
	private function create_guest_customer( $order ) {
		$customer_data = array(
			'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'firstname' => $order->get_billing_first_name(),
			'lastname' => $order->get_billing_last_name(),
			'email' => $order->get_billing_email(),
			'phone' => $order->get_billing_phone(),
			'address' => $order->get_billing_address_1(),
			'zip' => $order->get_billing_postcode(),
			'town' => $order->get_billing_city(),
			'country_code' => $order->get_billing_country(),
			'client' => 1,
			'status' => 1,
		);

		if ($order->get_billing_company()) {
			$customer_data['name'] = $order->get_billing_company();
			$customer_data['name_alias'] = $customer_data['firstname'] . ' ' . $customer_data['lastname'];
			$customer_data['client'] = 2;
		}

		$result = $this->api->create_customer($customer_data);
		
		if (is_wp_error($result)) {
			return $result;
		}

		return array( 'dolibarr_id' => $result['id'] ?? null );
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

<?php
/**
 * Dolibarr Customer Sync
 *
 * @package WC_Dolibarr_Integration
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class WC_Dolibarr_Customer_Sync {
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
		// Sync customer when created or updated
		add_action('woocommerce_customer_save_address', array( $this, 'sync_customer_on_address_save' ), 10, 2);
		add_action('woocommerce_save_account_details', array( $this, 'sync_customer_on_account_save' ));
		add_action('user_register', array( $this, 'sync_customer_on_register' ));
	}

	/**
	 * Sync customer when address is saved
	 *
	 * @param int    $user_id User ID
	 * @param string $load_address Address type
	 * @since 1.0.0
	 */
	public function sync_customer_on_address_save( $user_id, $load_address ) {
		if ($load_address === 'billing') {
			$this->sync_customer($user_id);
		}
	}

	/**
	 * Sync customer when account details are saved
	 *
	 * @param int $user_id User ID
	 * @since 1.0.0
	 */
	public function sync_customer_on_account_save( $user_id ) {
		$this->sync_customer($user_id);
	}

	/**
	 * Sync customer when user registers
	 *
	 * @param int $user_id User ID
	 * @since 1.0.0
	 */
	public function sync_customer_on_register( $user_id ) {
		// Delay sync to ensure customer data is fully saved
		wp_schedule_single_event(time() + 30, 'wc_dolibarr_sync_single_customer', array( $user_id ));
	}

	/**
	 * Sync single customer
	 *
	 * @param int|WC_Customer $customer Customer ID or object
	 * @since 1.0.0
	 * @return array|WP_Error
	 */
	public function sync_customer( $customer ) {
		if (is_numeric($customer)) {
			$customer = new WC_Customer($customer);
		}

		if (!$customer || !$customer->get_id()) {
			return new WP_Error('invalid_customer', __('Invalid customer.', 'wc-dolibarr'));
		}

		// Check if customer should be synced
		if (!wc_dolibarr_should_sync_customer($customer)) {
			$this->logger->log_sync('customer', $customer->get_id(), null, 'skipped', 'Customer sync disabled or invalid data');
			return array( 'status' => 'skipped', 'message' => 'Customer sync disabled or invalid data' );
		}

		// Check if customer already exists in Dolibarr
		$dolibarr_id = wc_dolibarr_get_customer_dolibarr_id($customer);
		$customer_data = wc_dolibarr_format_customer_data($customer);
		try {
			if ($dolibarr_id) {
				// Update existing customer
				$result = $this->api->update_customer($dolibarr_id, $customer_data);
				$action = 'updated';
			} else {
				// Check if customer exists by email
				$existing_customer = $this->api->find_customer_by_email($customer->get_email());
				if ($existing_customer && !is_wp_error($existing_customer)) {
					// Customer exists, update it
					$dolibarr_id = $existing_customer['id'];
					$result = $this->api->update_customer($dolibarr_id, $customer_data);
					$action = 'updated';
				} else {
					// Create new customer
					$result = $this->api->create_customer($customer_data);
					$action = 'created';
				}
			}

			if (is_wp_error($result)) {
				$this->logger->log_sync('customer', $customer->get_id(), $dolibarr_id, 'error', $result->get_error_message());
				return $result;
			}

			// Extract customer ID from response
			if (!$dolibarr_id && isset($result)) {
				$dolibarr_id = (int) $result;
			}

			// Save Dolibarr ID to customer meta
			wc_dolibarr_set_customer_dolibarr_id($customer, $dolibarr_id);

			$message = sprintf(__('Customer %s successfully.', 'wc-dolibarr'), $action);
			$this->logger->log_sync('customer', $customer->get_id(), $dolibarr_id, 'success', $message);

			return array(
				'status' => 'success',
				'message' => $message,
				'dolibarr_id' => $dolibarr_id,
				'action' => $action,
			);

		} catch (Exception $e) {
			$error_message = sprintf(__('Customer sync failed: %s', 'wc-dolibarr'), $e->getMessage());
			$this->logger->log_sync('customer', $customer->get_id(), $dolibarr_id, 'error', $error_message);
			return new WP_Error('sync_error', $error_message);
		}
	}

	/**
	 * Sync all customers
	 *
	 * @param int $limit  Number of customers to sync (0 for all)
	 * @param int $offset Offset for pagination
	 * @since 1.0.0
	 * @return array|WP_Error
	 */
	public function sync_all_customers( $limit = 0, $offset = 0 ) {
		if (!WC_Dolibarr_Connection_Validator::is_connection_valid()) {
			return new WP_Error('connection_invalid', __('Dolibarr connection is not valid.', 'wc-dolibarr'));
		}

		$args = array(
			'role__in' => array( 'customer' ),
			'meta_query' => array(
				array(
					'key' => 'billing_email',
					'value' => '',
					'compare' => '!=',
				),
			),
		);

		if ($limit > 0) {
			$args['number'] = $limit;
			$args['offset'] = $offset;
		}

		$users = get_users($args);
		$results = array(
			'total' => count($users),
			'synced' => 0,
			'errors' => 0,
			'skipped' => 0,
			'details' => array(),
		);
		$this->logger->log(sprintf('Starting bulk customer sync for %d customers.', count($users)), 'info');

		foreach ($users as $user) {
			$customer = new WC_Customer($user->ID);
			$result = $this->sync_customer($customer);
			if (is_wp_error($result)) {
				$results['errors']++;
				$results['details'][] = array(
					'customer_id' => $user->ID,
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
					'customer_id' => $user->ID,
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
			__('Customer sync completed. Total: %d, Synced: %d, Errors: %d, Skipped: %d', 'wc-dolibarr'),
			$results['total'],
			$results['synced'],
			$results['errors'],
			$results['skipped']
		);

		$this->logger->log($summary, 'info');

		return $results;
	}

	/**
	 * Get customers that need syncing
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_customers_needing_sync() {
		$args = array(
			'role__in' => array( 'customer' ),
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key' => 'billing_email',
					'value' => '',
					'compare' => '!=',
				),
				array(
					'key' => '_dolibarr_customer_id',
					'compare' => 'NOT EXISTS',
				),
			),
		);

		return get_users($args);
	}

	/**
	 * Get sync statistics
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_sync_stats() {
		global $wpdb;

		$stats = array(
			'total_customers' => 0,
			'synced_customers' => 0,
			'pending_sync' => 0,
			'last_sync' => null,
		);

		// Total customers
		$stats['total_customers'] = count_users()['avail_roles']['customer'] ?? 0;

		// Synced customers (those with Dolibarr ID)
		$synced = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = '_dolibarr_customer_id' AND meta_value != ''"
		);
		$stats['synced_customers'] = intval($synced);

		// Pending sync
		$stats['pending_sync'] = $stats['total_customers'] - $stats['synced_customers'];

		// Last sync
		$last_sync = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT created_at FROM {$wpdb->prefix}wc_dolibarr_sync_log 
				 WHERE sync_type = %s AND status = %s 
				 ORDER BY created_at DESC LIMIT 1",
				'customer',
				'success'
			)
		);
		$stats['last_sync'] = $last_sync;

		return $stats;
	}

	/**
	 * Delete customer from Dolibarr
	 *
	 * @param int $customer_id Customer ID
	 * @since 1.0.0
	 * @return bool|WP_Error
	 */
	public function delete_customer( $customer_id ) {
		$customer = new WC_Customer($customer_id);
		$dolibarr_id = wc_dolibarr_get_customer_dolibarr_id($customer);

		if (!$dolibarr_id) {
			return true; // Nothing to delete
		}

		// Note: Dolibarr API might not support customer deletion
		// This would depend on the specific Dolibarr API implementation
		$this->logger->log(sprintf('Customer deletion requested for WC ID: %d, Dolibarr ID: %s', $customer_id, $dolibarr_id), 'info');

		// Remove Dolibarr ID from customer meta
		$customer->delete_meta_data('_dolibarr_customer_id');
		$customer->save();

		return true;
	}

	/**
	 * Validate customer data before sync
	 *
	 * @param WC_Customer $customer Customer object
	 * @since 1.0.0
	 * @return bool|WP_Error
	 */
	private function validate_customer_data( $customer ) {
		$errors = array();

		// Check required fields
		if (!$customer->get_email()) {
			$errors[] = __('Customer email is required.', 'wc-dolibarr');
		}

		if (!$customer->get_first_name() && !$customer->get_last_name() && !$customer->get_billing_company()) {
			$errors[] = __('Customer name or company is required.', 'wc-dolibarr');
		}

		if (!empty($errors)) {
			return new WP_Error('validation_failed', implode(' ', $errors));
		}

		return true;
	}
}

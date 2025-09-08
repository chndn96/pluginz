<?php
/**
 * Dolibarr Cron Jobs
 *
 * @package WC_Dolibarr_Integration
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class WC_Dolibarr_Cron {
	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		add_action('wc_dolibarr_inventory_sync', array( $this, 'run_inventory_sync' ));
		add_action('wc_dolibarr_product_sync', array( $this, 'run_product_sync' ));
		add_action('wc_dolibarr_customer_sync', array( $this, 'run_customer_sync' ));
		add_action('wc_dolibarr_order_sync', array( $this, 'run_order_sync' ));
		add_action('wc_dolibarr_sync_single_customer', array( $this, 'sync_single_customer' ));
		add_action('wc_dolibarr_sync_single_order', array( $this, 'sync_single_order' ));
	}

	/**
	 * Schedule cron events
	 *
	 * @since 1.0.0
	 */
	public function scheduleEvents() {
		// Only schedule if connection is valid
		if (!WC_Dolibarr_Connection_Validator::is_connection_valid()) {
			return;
		}

		// Schedule inventory sync
		if (wc_dolibarr_get_option('sync_inventory', false)) {
			$interval = wc_dolibarr_get_option('inventory_sync_interval', 'hourly');
			
			if (!wp_next_scheduled('wc_dolibarr_inventory_sync')) {
				wp_schedule_event(time(), $interval, 'wc_dolibarr_inventory_sync');
			}
		}

		// Schedule product sync
		if (wc_dolibarr_get_option('sync_products', false)) {
			if (!wp_next_scheduled('wc_dolibarr_product_sync')) {
				wp_schedule_event(time(), 'daily', 'wc_dolibarr_product_sync');
			}
		}
	}

	/**
	 * Check and disable crons if connection is invalid
	 *
	 * @since 1.0.0
	 */
	public function checkAndDisableCronsIfNeeded() {
		if (!WC_Dolibarr_Connection_Validator::is_connection_valid()) {
			$this->clearScheduledEvents();
		}
	}

	/**
	 * Clear all scheduled events
	 *
	 * @since 1.0.0
	 */
	public function clearScheduledEvents() {
		wp_clear_scheduled_hook('wc_dolibarr_inventory_sync');
		wp_clear_scheduled_hook('wc_dolibarr_product_sync');
		wp_clear_scheduled_hook('wc_dolibarr_customer_sync');
		wp_clear_scheduled_hook('wc_dolibarr_order_sync');
	}

	/**
	 * Run inventory sync
	 *
	 * @since 1.0.0
	 */
	public function run_inventory_sync() {
		if (class_exists('WC_Dolibarr_Product_Sync')) {
			$product_sync = new WC_Dolibarr_Product_Sync();
			$product_sync->sync_inventory();
		}
	}

	/**
	 * Run product sync
	 *
	 * @since 1.0.0
	 */
	public function run_product_sync() {
		if (class_exists('WC_Dolibarr_Product_Sync')) {
			$product_sync = new WC_Dolibarr_Product_Sync();
			$product_sync->sync_all_products();
		}
	}

	/**
	 * Run customer sync
	 *
	 * @since 1.0.0
	 */
	public function run_customer_sync() {
		if (class_exists('WC_Dolibarr_Customer_Sync')) {
			$customer_sync = new WC_Dolibarr_Customer_Sync();
			$customer_sync->sync_all_customers(50); // Limit to 50 per run
		}
	}

	/**
	 * Run order sync
	 *
	 * @since 1.0.0
	 */
	public function run_order_sync() {
		if (class_exists('WC_Dolibarr_Order_Sync')) {
			$order_sync = new WC_Dolibarr_Order_Sync();
			$order_sync->sync_all_orders(25); // Limit to 25 per run
		}
	}

	/**
	 * Sync single customer (scheduled event)
	 *
	 * @param int $customer_id Customer ID
	 * @since 1.0.0
	 */
	public function sync_single_customer( $customer_id ) {
		if (class_exists('WC_Dolibarr_Customer_Sync')) {
			$customer_sync = new WC_Dolibarr_Customer_Sync();
			$customer_sync->sync_customer($customer_id);
		}
	}

	/**
	 * Sync single order (scheduled event)
	 *
	 * @param int $order_id Order ID
	 * @since 1.0.0
	 */
	public function sync_single_order( $order_id ) {
		if (class_exists('WC_Dolibarr_Order_Sync')) {
			$order_sync = new WC_Dolibarr_Order_Sync();
			$order_sync->sync_order($order_id);
		}
	}
}

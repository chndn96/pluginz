<?php
/**
 * Dolibarr Cache Manager
 *
 * @package WC_Dolibarr_Integration
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class WC_Dolibarr_Cache_Manager {
	/**
	 * Cache expiration times
	 */
	const CACHE_WAREHOUSES = 'wc_dolibarr_warehouses';
	const CACHE_PAYMENT_METHODS = 'wc_dolibarr_payment_methods';
	const CACHE_BANK_ACCOUNTS = 'wc_dolibarr_bank_accounts';
	const CACHE_PRODUCTS = 'wc_dolibarr_products';
	const CACHE_CUSTOMERS = 'wc_dolibarr_customers';

	/**
	 * Default cache expiration (1 hour)
	 */
	const DEFAULT_EXPIRATION = 3600;

	/**
	 * Get cached data
	 *
	 * @param string $cache_key Cache key
	 * @since 1.0.0
	 * @return mixed|false
	 */
	public static function get( $cache_key ) {
		return get_transient($cache_key);
	}

	/**
	 * Set cached data
	 *
	 * @param string $cache_key  Cache key
	 * @param mixed  $data       Data to cache
	 * @param int    $expiration Expiration time in seconds
	 * @since 1.0.0
	 * @return bool
	 */
	public static function set( $cache_key, $data, $expiration = self::DEFAULT_EXPIRATION ) {
		return set_transient($cache_key, $data, $expiration);
	}

	/**
	 * Delete cached data
	 *
	 * @param string $cache_key Cache key
	 * @since 1.0.0
	 * @return bool
	 */
	public static function delete( $cache_key ) {
		return delete_transient($cache_key);
	}

	/**
	 * Get cached warehouses
	 *
	 * @since 1.0.0
	 * @return array|false
	 */
	public static function get_warehouses() {
		return self::get(self::CACHE_WAREHOUSES);
	}

	/**
	 * Cache warehouses
	 *
	 * @param array $warehouses Warehouses data
	 * @since 1.0.0
	 * @return bool
	 */
	public static function cache_warehouses( $warehouses ) {
		return self::set(self::CACHE_WAREHOUSES, $warehouses, DAY_IN_SECONDS);
	}

	/**
	 * Get cached payment methods
	 *
	 * @since 1.0.0
	 * @return array|false
	 */
	public static function get_payment_methods() {
		return self::get(self::CACHE_PAYMENT_METHODS);
	}

	/**
	 * Cache payment methods
	 *
	 * @param array $methods Payment methods data
	 * @since 1.0.0
	 * @return bool
	 */
	public static function cache_payment_methods( $methods ) {
		return self::set(self::CACHE_PAYMENT_METHODS, $methods, DAY_IN_SECONDS);
	}

	/**
	 * Get cached bank accounts
	 *
	 * @since 1.0.0
	 * @return array|false
	 */
	public static function get_bank_accounts() {
		return self::get(self::CACHE_BANK_ACCOUNTS);
	}

	/**
	 * Cache bank accounts
	 *
	 * @param array $accounts Bank accounts data
	 * @since 1.0.0
	 * @return bool
	 */
	public static function cache_bank_accounts( $accounts ) {
		return self::set(self::CACHE_BANK_ACCOUNTS, $accounts, DAY_IN_SECONDS);
	}

	/**
	 * Get cached products
	 *
	 * @since 1.0.0
	 * @return array|false
	 */
	public static function get_products() {
		return self::get(self::CACHE_PRODUCTS);
	}

	/**
	 * Cache products
	 *
	 * @param array $products Products data
	 * @since 1.0.0
	 * @return bool
	 */
	public static function cache_products( $products ) {
		return self::set(self::CACHE_PRODUCTS, $products, HOUR_IN_SECONDS);
	}

	/**
	 * Get cached customers
	 *
	 * @since 1.0.0
	 * @return array|false
	 */
	public static function get_customers() {
		return self::get(self::CACHE_CUSTOMERS);
	}

	/**
	 * Cache customers
	 *
	 * @param array $customers Customers data
	 * @since 1.0.0
	 * @return bool
	 */
	public static function cache_customers( $customers ) {
		return self::set(self::CACHE_CUSTOMERS, $customers, HOUR_IN_SECONDS);
	}

	/**
	 * Clear all plugin caches
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function clear_all_cache() {
		$cache_keys = array(
			self::CACHE_WAREHOUSES,
			self::CACHE_PAYMENT_METHODS,
			self::CACHE_BANK_ACCOUNTS,
			self::CACHE_PRODUCTS,
			self::CACHE_CUSTOMERS,
		);

		foreach ($cache_keys as $key) {
			self::delete($key);
		}

		// Clear connection cache
		WC_Dolibarr_Connection_Validator::clear_connection_cache();
	}

	/**
	 * Schedule daily cache refresh
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function schedule_cache_refresh() {
		if (!wp_next_scheduled('wc_dolibarr_daily_cache_refresh')) {
			wp_schedule_event(time(), 'daily', 'wc_dolibarr_daily_cache_refresh');
		}
	}

	/**
	 * Handle daily cache refresh (cron job)
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_daily_cache_refresh() {
		// Only refresh if connection is valid
		if (!WC_Dolibarr_Connection_Validator::is_connection_valid()) {
			return;
		}

		$api = new WC_Dolibarr_API();
		$logger = new WC_Dolibarr_Logger();

		// Refresh warehouses
		$warehouses = $api->get_warehouses();
		if (!is_wp_error($warehouses)) {
			self::cache_warehouses($warehouses);
		}

		// Refresh payment methods
		$methods = $api->get_payment_methods();
		if (!is_wp_error($methods)) {
			self::cache_payment_methods($methods);
		}

		// Refresh bank accounts
		$accounts = $api->get_bank_accounts();
		if (!is_wp_error($accounts)) {
			self::cache_bank_accounts($accounts);
		}

		$logger->log('Daily cache refresh completed.', 'info');
	}

	/**
	 * Unschedule cache refresh
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function unschedule_cache_refresh() {
		wp_clear_scheduled_hook('wc_dolibarr_daily_cache_refresh');
	}

	/**
	 * Get cache statistics
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public static function get_cache_stats() {
		$cache_keys = array(
			'warehouses' => self::CACHE_WAREHOUSES,
			'payment_methods' => self::CACHE_PAYMENT_METHODS,
			'bank_accounts' => self::CACHE_BANK_ACCOUNTS,
			'products' => self::CACHE_PRODUCTS,
			'customers' => self::CACHE_CUSTOMERS,
		);

		$stats = array();
		foreach ($cache_keys as $name => $key) {
			$data = self::get($key);
			$stats[$name] = array(
				'cached' => $data !== false,
				'count' => $data !== false ? count($data) : 0,
			);
		}

		return $stats;
	}
}

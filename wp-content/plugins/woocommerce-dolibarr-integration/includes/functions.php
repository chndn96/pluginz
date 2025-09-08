<?php
/**
 * Plugin Functions
 *
 * @package WC_Dolibarr_Integration
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Check plugin requirements
 *
 * @since 1.0.0
 * @return void
 */
function Wc_Dolibarr_Check_requirements() {
	// Check PHP version
	if (version_compare(PHP_VERSION, '7.4', '<')) {
		deactivate_plugins(plugin_basename(WC_DOLIBARR_PLUGIN_FILE));
		wp_die(
			esc_html__('WooCommerce Dolibarr Integration requires PHP 7.4 or higher. Please upgrade your PHP version.', 'wc-dolibarr'),
			esc_html__('Plugin Activation Error', 'wc-dolibarr'),
			array( 'back_link' => true )
		);
	}

	// Check if WooCommerce is active
	if (!class_exists('WooCommerce')) {
		deactivate_plugins(plugin_basename(WC_DOLIBARR_PLUGIN_FILE));
		wp_die(
			esc_html__('WooCommerce Dolibarr Integration requires WooCommerce to be installed and active.', 'wc-dolibarr'),
			esc_html__('Plugin Activation Error', 'wc-dolibarr'),
			array( 'back_link' => true )
		);
	}

	// Check WooCommerce version
	if (defined('WC_VERSION') && version_compare(WC_VERSION, '5.0', '<')) {
		deactivate_plugins(plugin_basename(WC_DOLIBARR_PLUGIN_FILE));
		wp_die(
			esc_html__('WooCommerce Dolibarr Integration requires WooCommerce 5.0 or higher.', 'wc-dolibarr'),
			esc_html__('Plugin Activation Error', 'wc-dolibarr'),
			array( 'back_link' => true )
		);
	}
}

/**
 * Create plugin database tables
 *
 * @since 1.0.0
 * @return void
 */
function Wc_Dolibarr_Create_tables() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	// Create sync log table
	$sync_log_table = $wpdb->prefix . 'wc_dolibarr_sync_log';
	$sql = "CREATE TABLE $sync_log_table (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		sync_type varchar(50) NOT NULL,
		wc_id bigint(20) NOT NULL,
		dolibarr_id varchar(50) DEFAULT NULL,
		status varchar(20) NOT NULL DEFAULT 'pending',
		message text DEFAULT NULL,
		sync_direction varchar(20) NOT NULL DEFAULT 'wc_to_dolibarr',
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY sync_type (sync_type),
		KEY wc_id (wc_id),
		KEY dolibarr_id (dolibarr_id),
		KEY status (status),
		KEY created_at (created_at)
	) $charset_collate;";

	// Create order sync history table
	$order_sync_table = $wpdb->prefix . 'wc_dolibarr_order_sync_history';
	$sql .= "CREATE TABLE $order_sync_table (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		order_id bigint(20) NOT NULL,
		dolibarr_order_id varchar(50) DEFAULT NULL,
		dolibarr_invoice_id varchar(50) DEFAULT NULL,
		sync_status varchar(20) NOT NULL DEFAULT 'pending',
		sync_type varchar(50) NOT NULL DEFAULT 'order',
		error_message text DEFAULT NULL,
		last_sync_at datetime DEFAULT CURRENT_TIMESTAMP,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY order_id (order_id),
		KEY dolibarr_order_id (dolibarr_order_id),
		KEY sync_status (sync_status),
		KEY last_sync_at (last_sync_at)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta($sql);

	// Set plugin version
	update_option('wc_dolibarr_version', WC_DOLIBARR_VERSION);
}

/**
 * Plugin deactivation
 *
 * @since 1.0.0
 * @return void
 */
function Wc_Dolibarr_deactivate() {
	// Clear scheduled cron jobs
	wp_clear_scheduled_hook('wc_dolibarr_inventory_sync');
	wp_clear_scheduled_hook('wc_dolibarr_product_sync');
	wp_clear_scheduled_hook('wc_dolibarr_order_sync');
	wp_clear_scheduled_hook('wc_dolibarr_customer_sync');
	wp_clear_scheduled_hook('wc_dolibarr_connection_monitor');
	wp_clear_scheduled_hook('wc_dolibarr_daily_cache_refresh');

	// Clear transients
	delete_transient('wc_dolibarr_valid_credentials');
	delete_transient('wc_dolibarr_invalid_credentials');
	delete_transient('wc_dolibarr_connection_status');
}

/**
 * Initialize plugin
 *
 * @since 1.0.0
 * @return void
 */
function Wc_Dolibarr_init() {
	// Check if WooCommerce is active
	if (!class_exists('WooCommerce')) {
		add_action('admin_notices', 'Wc_Dolibarr_woocommerce_missing_notice');
		return;
	}

	// Load plugin classes
	Wc_Dolibarr_load_classes();

	// Initialize plugin
	WC_Dolibarr_Integration::getInstance();
}

/**
 * Load plugin classes
 *
 * @since 1.0.0
 * @return void
 */
function Wc_Dolibarr_load_classes() {
	$includes_dir = WC_DOLIBARR_PLUGIN_DIR . 'includes/';

	// Core classes
	require_once $includes_dir . 'helpers.php';
	require_once $includes_dir . 'class-wc-dolibarr-logger.php';
	require_once $includes_dir . 'class-wc-dolibarr-api.php';
	require_once $includes_dir . 'class-wc-dolibarr-settings.php';
	require_once $includes_dir . 'class-wc-dolibarr-connection-validator.php';
	require_once $includes_dir . 'class-wc-dolibarr-cache-manager.php';
	require_once $includes_dir . 'class-wc-dolibarr-optimizer.php';
	require_once $includes_dir . 'class-wc-dolibarr-batch-processor.php';
	require_once $includes_dir . 'class-wc-dolibarr-dashboard.php';

	// Sync classes
	require_once $includes_dir . 'class-wc-dolibarr-customer-sync.php';
	require_once $includes_dir . 'class-wc-dolibarr-order-sync.php';
	require_once $includes_dir . 'class-wc-dolibarr-product-sync.php';
	require_once $includes_dir . 'class-wc-dolibarr-cron.php';
}

/**
 * WooCommerce missing notice
 *
 * @since 1.0.0
 * @return void
 */
function Wc_Dolibarr_woocommerce_missing_notice() {
	echo '<div class="notice notice-error"><p>';
	echo '<strong>' . esc_html__('WooCommerce Dolibarr Integration', 'wc-dolibarr') . '</strong> ';
	echo esc_html__('requires WooCommerce to be installed and active.', 'wc-dolibarr');
	echo '</p></div>';
}

/**
 * Get plugin option with default value
 *
 * @param string $option_name Option name
 * @param mixed  $default     Default value
 * @since 1.0.0
 * @return mixed
 */
function wc_dolibarr_get_option( $option_name, $default = '' ) {
	return get_option('wc_dolibarr_' . $option_name, $default);
}

/**
 * Update plugin option
 *
 * @param string $option_name Option name
 * @param mixed  $value       Option value
 * @since 1.0.0
 * @return bool
 */
function wc_dolibarr_update_option( $option_name, $value ) {
	return update_option('wc_dolibarr_' . $option_name, $value);
}

/**
 * Delete plugin option
 *
 * @param string $option_name Option name
 * @since 1.0.0
 * @return bool
 */
function wc_dolibarr_delete_option( $option_name ) {
	return delete_option('wc_dolibarr_' . $option_name);
}

/**
 * Log message to WooCommerce logs
 *
 * @param string $message Log message
 * @param string $level   Log level (emergency, alert, critical, error, warning, notice, info, debug)
 * @param string $source  Log source
 * @since 1.0.0
 * @return void
 */
function wc_dolibarr_log( $message, $level = 'info', $source = 'wc-dolibarr' ) {
	if (class_exists('WC_Logger')) {
		$logger = wc_get_logger();
		$logger->log($level, $message, array( 'source' => $source ));
	}
}

/**
 * Check if debug mode is enabled
 *
 * @since 1.0.0
 * @return bool
 */
function wc_dolibarr_is_debug_mode() {
	return wc_dolibarr_get_option('debug_mode', false);
}

/**
 * Format price for Dolibarr
 *
 * @param float $price Price to format
 * @since 1.0.0
 * @return float
 */
function wc_dolibarr_format_price( $price ) {
	return round(floatval($price), 2);
}

/**
 * Get WooCommerce order meta (HPOS compatible)
 *
 * @param int|WC_Order $order Order ID or object
 * @param string       $key   Meta key
 * @param bool         $single Return single value
 * @since 1.0.0
 * @return mixed
 */
function wc_dolibarr_get_order_meta( $order, $key, $single = true ) {
	if (is_numeric($order)) {
		$order = wc_get_order($order);
	}

	if (!$order) {
		return $single ? '' : array();
	}

	return $order->get_meta($key, $single);
}

/**
 * Update WooCommerce order meta (HPOS compatible)
 *
 * @param int|WC_Order $order Order ID or object
 * @param string       $key   Meta key
 * @param mixed        $value Meta value
 * @since 1.0.0
 * @return void
 */
function wc_dolibarr_update_order_meta( $order, $key, $value ) {
	if (is_numeric($order)) {
		$order = wc_get_order($order);
	}

	if ($order) {
		$order->update_meta_data($key, $value);
		$order->save();
	}
}

/**
 * Delete WooCommerce order meta (HPOS compatible)
 *
 * @param int|WC_Order $order Order ID or object
 * @param string       $key   Meta key
 * @since 1.0.0
 * @return void
 */
function wc_dolibarr_delete_order_meta( $order, $key ) {
	if (is_numeric($order)) {
		$order = wc_get_order($order);
	}

	if ($order) {
		$order->delete_meta_data($key);
		$order->save();
	}
}

/**
 * Normalize domain by removing protocol
 *
 * @param string $domain Domain with or without protocol
 * @since 1.0.0
 * @return string
 */
function wc_dolibarr_normalize_domain( $domain ) {
	return preg_replace('/^https?:\/\//', '', trim($domain));
}

/**
 * Check if string is valid JSON
 *
 * @param string $string String to check
 * @since 1.0.0
 * @return bool
 */
function wc_dolibarr_is_json( $string ) {
	json_decode($string);
	return json_last_error() === JSON_ERROR_NONE;
}

/**
 * Sanitize array recursively
 *
 * @param array $array Array to sanitize
 * @since 1.0.0
 * @return array
 */
function wc_dolibarr_sanitize_array( $array ) {
	foreach ($array as $key => $value) {
		if (is_array($value)) {
			$array[$key] = wc_dolibarr_sanitize_array($value);
		} else {
			$array[$key] = sanitize_text_field($value);
		}
	}
	return $array;
}

/**
 * Get current timestamp in MySQL format
 *
 * @since 1.0.0
 * @return string
 */
function wc_dolibarr_get_current_timestamp() {
	return current_time('mysql');
}

/**
 * Check if plugin is properly configured
 *
 * @since 1.0.0
 * @return bool
 */
function wc_dolibarr_is_configured() {
	$api_url = wc_dolibarr_get_option('api_url');
	$api_key = wc_dolibarr_get_option('api_key');
	
	return !empty($api_url) && !empty($api_key);
}

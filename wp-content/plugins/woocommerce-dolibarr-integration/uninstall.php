<?php
/**
 * Uninstall Dolibarr Integration Plugin
 * 
 * This file is executed when the plugin is deleted from WordPress.
 * It removes all plugin data including settings, options, transients,
 * cron jobs, database tables, and any other traces of the plugin.
 * 
 * @package WC_Dolibarr_Integration
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

// Only proceed if we're uninstalling this specific plugin
if (plugin_basename(__FILE__) !== 'woocommerce-dolibarr-integration/uninstall.php') {
	return;
}

/**
 * Comprehensive cleanup function
 */
function wc_dolibarr_complete_cleanup() {
	global $wpdb;
	
	// ========================================
	// 1. REMOVE ALL CRON JOBS
	// ========================================
	
	// Clear all scheduled cron jobs
	wp_clear_scheduled_hook('wc_dolibarr_inventory_sync');
	wp_clear_scheduled_hook('wc_dolibarr_product_sync');
	wp_clear_scheduled_hook('wc_dolibarr_order_sync');
	wp_clear_scheduled_hook('wc_dolibarr_customer_sync');
	wp_clear_scheduled_hook('wc_dolibarr_connection_monitor');
	wp_clear_scheduled_hook('wc_dolibarr_daily_cache_refresh');
	wp_clear_scheduled_hook('wc_dolibarr_sync_single_order');
	wp_clear_scheduled_hook('wc_dolibarr_sync_single_customer');
	wp_clear_scheduled_hook('wc_dolibarr_periodic_credential_validation');
	
	// Clear any other potential cron jobs
	$cron_jobs = array(
		'wc_dolibarr_batch_sync',
		'wc_dolibarr_sync_products',
		'wc_dolibarr_sync_inventory',
		'wc_dolibarr_sync_customers',
		'wc_dolibarr_sync_orders',
		'wc_dolibarr_credential_validation',
		'wc_dolibarr_cleanup_logs',
		'wc_dolibarr_optimize_database',
		'wc_dolibarr_cache_refresh',
		'wc_dolibarr_batch_processor',
	);
	
	foreach ($cron_jobs as $cron_job) {
		wp_clear_scheduled_hook($cron_job);
	}
	
	// ========================================
	// 2. REMOVE ALL CACHE
	// ========================================
	
	// Clear cache manager cron job
	if (class_exists('WC_Dolibarr_Cache_Manager')) {
		WC_Dolibarr_Cache_Manager::unschedule_cache_refresh();
	}
	
	// Delete all wc_dolibarr transients
	$transients_to_delete = array(
		'wc_dolibarr_valid_credentials',
		'wc_dolibarr_invalid_credentials',
		'wc_dolibarr_customer_sync_flags',
		'wc_dolibarr_connection_status',
		'wc_dolibarr_api_response_cache',
		'wc_dolibarr_inventory_cache',
		'wc_dolibarr_product_cache',
		'wc_dolibarr_customer_cache',
		'wc_dolibarr_order_cache',
		'wc_dolibarr_warehouses',
		'wc_dolibarr_payment_methods',
		'wc_dolibarr_bank_accounts',
		'wc_dolibarr_products',
		'wc_dolibarr_customers',
	);
	
	foreach ($transients_to_delete as $transient) {
		delete_transient($transient);
	}
	
	// Delete all batch-related transients (these use dynamic names)
	$batch_transients = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			'_transient_wc_dolibarr_batch_%',
			'_transient_wc_dolibarr_batch_meta_%'
		)
	);
	
	foreach ($batch_transients as $transient) {
		$transient_name = str_replace('_transient_', '', $transient->option_name);
		delete_transient($transient_name);
	}
	
	// Delete any other wc_dolibarr transients
	$other_transients = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
			'_transient_wc_dolibarr_%'
		)
	);
	
	foreach ($other_transients as $transient) {
		$transient_name = str_replace('_transient_', '', $transient->option_name);
		delete_transient($transient_name);
	}
	
	// Clear any object cache entries
	if (function_exists('wp_cache_flush')) {
		wp_cache_flush();
	}
	
	// Clear any transients that might be cached
	if (function_exists('wp_cache_delete')) {
		$transients = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_wc_dolibarr_%' ) );
		foreach ($transients as $transient) {
			$transient_name = str_replace('_transient_', '', $transient);
			wp_cache_delete($transient_name, 'transient');
		}
	}
	
	// ========================================
	// 3. REMOVE ALL OPTIONS
	// ========================================
	
	// API Settings
	$api_options = array(
		'wc_dolibarr_api_url',
		'wc_dolibarr_api_key',
		'wc_dolibarr_ssl_verify',
		'wc_dolibarr_debug_mode',
		'wc_dolibarr_log_level',
		'wc_dolibarr_enable_wc_logging',
	);
	
	// Configuration Settings
	$config_options = array(
		'wc_dolibarr_default_warehouse',
		'wc_dolibarr_default_payment_method',
		'wc_dolibarr_default_bank_account',
		'wc_dolibarr_currency',
		'wc_dolibarr_company',
		'wc_dolibarr_default_customer',
		'wc_dolibarr_add_shipping_as_item',
		'wc_dolibarr_item_code_prefix',
		'wc_dolibarr_item_group',
		'wc_dolibarr_default_uom',
		'wc_dolibarr_default_hsn_code',
	);
	
	// Sync Settings
	$sync_options = array(
		'wc_dolibarr_sync_customers',
		'wc_dolibarr_sync_orders',
		'wc_dolibarr_sync_products',
		'wc_dolibarr_sync_inventory',
		'wc_dolibarr_inventory_sync_interval',
		'wc_dolibarr_enable_tax_sync',
		'wc_dolibarr_product_sync_enabled',
		'wc_dolibarr_product_sync_interval',
		'wc_dolibarr_product_sync_batch_size',
		'wc_dolibarr_product_sync_auto_update',
		'wc_dolibarr_product_sync_create_categories',
		'wc_dolibarr_product_sync_skip_disabled',
		'wc_dolibarr_attribute_type',
		'wc_dolibarr_create_global_attributes',
		'wc_dolibarr_use_batch_for_cron',
		'wc_dolibarr_batch_size',
		'wc_dolibarr_max_execution_time',
	);
	
	// Order Sync Settings
	$order_sync_options = array(
		'wc_dolibarr_order_sync_enabled',
		'wc_dolibarr_sync_order_status_updates',
		'wc_dolibarr_default_territory',
		'wc_dolibarr_default_customer_group',
		'wc_dolibarr_auto_detect_customer_type',
		'wc_dolibarr_create_quotes',
		'wc_dolibarr_create_sales_orders',
		'wc_dolibarr_create_invoices',
		'wc_dolibarr_enable_past_customer_sync',
		'wc_dolibarr_enable_past_order_sync',
		'wc_dolibarr_past_sync_date_limit',
		'wc_dolibarr_auto_submit_orders',
	);
	
	// Batch Sync Settings
	$batch_options = array(
		'wc_dolibarr_batch_sync_previous_orders',
		'wc_dolibarr_batch_sync_previous_customers',
		'wc_dolibarr_batch_sync_order_limit',
		'wc_dolibarr_batch_sync_customer_limit',
		'wc_dolibarr_batch_sync_order_statuses',
		'wc_dolibarr_batch_sync_skip_existing',
		'wc_dolibarr_batch_sync_all_order_statuses',
		'wc_dolibarr_batch_sync_debug_mode',
		'wc_dolibarr_batch_sync_max_retries',
	);
	
	// Additional options that might exist
	$additional_options = array(
		'wc_dolibarr_last_inventory_sync',
		'wc_dolibarr_last_credential_check',
		'wc_dolibarr_last_batch_sync',
		'wc_dolibarr_last_inventory_batch_sync',
		'wc_dolibarr_last_price_batch_sync',
		'wc_dolibarr_customer_sync_flags',
		'wc_dolibarr_order_status_mapping',
		'wc_dolibarr_auto_create_invoice_with_sales_order',
		'wc_dolibarr_consolidate_taxes',
		'wc_dolibarr_inventory_last_update',
		'wc_dolibarr_connection_status',
		'wc_dolibarr_connection_last_status',
		'wc_dolibarr_api_response_cache',
		'wc_dolibarr_cache_customer_groups',
		'wc_dolibarr_cache_item_groups',
		'wc_dolibarr_cache_warehouses',
		'wc_dolibarr_cache_payment_methods',
		'wc_dolibarr_version',
	);
	
	// Combine all options
	$all_options = array_merge($api_options, $config_options, $sync_options, $order_sync_options, $batch_options, $additional_options);
	
	// Delete all options
	foreach ($all_options as $option) {
		delete_option($option);
	}
	
	// Delete any remaining options that start with wc_dolibarr_
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			'wc_dolibarr_%'
		)
	);
	
	// ========================================
	// 4. REMOVE ALL META KEYS FOR CUSTOMERS, ORDERS, PRODUCTS
	// ========================================
	
	// Remove customer meta keys
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
			'wc_dolibarr_%'
		)
	);
	
	// Remove customer meta keys for Dolibarr IDs
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s",
			'_dolibarr_customer_id'
		)
	);
	
	// Remove order meta keys (for both HPOS and traditional orders)
	$wpdb->query(
		"DELETE pm FROM {$wpdb->postmeta} pm 
		 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
		 WHERE p.post_type = 'shop_order' 
		 AND (pm.meta_key LIKE 'wc_dolibarr_%' OR pm.meta_key LIKE '_dolibarr_%')"
	);
	
	// Remove product meta keys
	$wpdb->query(
		"DELETE pm FROM {$wpdb->postmeta} pm 
		 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
		 WHERE p.post_type IN ('product', 'product_variation') 
		 AND (pm.meta_key LIKE 'wc_dolibarr_%' OR pm.meta_key LIKE '_dolibarr_%')"
	);
	
	// Remove any other post meta that might have been added by the plugin
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
			'wc_dolibarr_%',
			'_dolibarr_%'
		)
	);
	
	// Remove any comment meta that might have been added by the plugin
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->commentmeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
			'wc_dolibarr_%',
			'_dolibarr_%'
		)
	);
	
	// Remove any term meta that might have been added by the plugin
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
			'wc_dolibarr_%',
			'_dolibarr_%'
		)
	);
	
	// ========================================
	// 5. DELETE DATABASE TABLES
	// ========================================
	
	// Drop custom tables
	$tables_to_drop = array(
		$wpdb->prefix . 'wc_dolibarr_sync_log',
		$wpdb->prefix . 'wc_dolibarr_order_sync_history',
	);
	
	foreach ($tables_to_drop as $table) {
		$table = esc_sql( $table );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( 'DROP TABLE IF EXISTS `' . $table . '`' );
	}
	
	// ========================================
	// 6. CLEAN UP ANY LOG FILES
	// ========================================
	
	// Delete any log files created by the plugin
	$log_dir = WP_CONTENT_DIR . '/logs/';
	if (is_dir($log_dir)) {
		$log_files = glob($log_dir . 'wc_dolibarr_*.log');
		foreach ($log_files as $log_file) {
			if (is_file($log_file)) {
				unlink($log_file);
			}
		}
	}
	
	// Also check for logs in wp-content/uploads/
	$uploads_log_dir = WP_CONTENT_DIR . '/uploads/wc-logs/';
	if (is_dir($uploads_log_dir)) {
		$log_files = glob($uploads_log_dir . 'wc_dolibarr_*.log');
		foreach ($log_files as $log_file) {
			if (is_file($log_file)) {
				unlink($log_file);
			}
		}
	}
	
	// ========================================
	// 7. CLEAN UP ANY REMAINING TRANSIENTS
	// ========================================
	
	// Delete any remaining transients that start with wc_dolibarr_
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			'_transient_wc_dolibarr_%',
			'_transient_timeout_wc_dolibarr_%'
		)
	);
	
	// ========================================
	// 8. CLEAN UP ANY REMAINING FILES
	// ========================================
	
	// Delete any uploaded files or assets created by the plugin
	$upload_dir        = wp_upload_dir();
	$plugin_upload_dir = $upload_dir['basedir'] . '/wc-dolibarr/';
	
	if (is_dir($plugin_upload_dir)) {
		// Recursively delete the directory
		function wc_dolibarr_delete_directory( $dir ) {
			if (!is_dir($dir)) {
				return false;
			}
			
			$files = array_diff(scandir($dir), array( '.', '..' ));
			foreach ($files as $file) {
				$path = $dir . '/' . $file;
				if (is_dir($path)) {
					wc_dolibarr_delete_directory($path);
				} else {
					unlink($path);
				}
			}
			
			return rmdir($dir);
		}
		
		wc_dolibarr_delete_directory($plugin_upload_dir);
	}
	
	// ========================================
	// 9. CLEAN UP ANY SCHEDULED EVENTS
	// ========================================
	
	// Clear any scheduled events
	$scheduled_events = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
			'_transient_cron'
		)
	);
	
	if (!empty($scheduled_events)) {
		$cron_array = get_option('cron');
		if (is_array($cron_array)) {
			foreach ($cron_array as $timestamp => $cron) {
				if (is_array($cron)) {
					foreach ($cron as $hook => $events) {
						if (strpos($hook, 'wc_dolibarr') !== false) {
							unset($cron_array[$timestamp][$hook]);
						}
					}
				}
			}
			update_option('cron', $cron_array);
		}
	}
	
	// ========================================
	// 10. FINAL CLEANUP
	// ========================================
	
	// Force WordPress to refresh its cache
	if (function_exists('wp_cache_flush')) {
		wp_cache_flush();
	}
	
	// Clear any remaining transients
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_wc_dolibarr_%' ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_timeout_wc_dolibarr_%' ) );
	
	// Clear any remaining options
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", 'wc_dolibarr_%' ) );
	
	// Clear any remaining post meta
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s OR meta_key LIKE %s", 'wc_dolibarr_%', '_dolibarr_%' ) );
	
	// Clear any remaining user meta
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s OR meta_key LIKE %s", 'wc_dolibarr_%', '_dolibarr_%' ) );
	
	// Clear any remaining comment meta
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->commentmeta} WHERE meta_key LIKE %s OR meta_key LIKE %s", 'wc_dolibarr_%', '_dolibarr_%' ) );
	
	// Clear any remaining term meta
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE %s OR meta_key LIKE %s", 'wc_dolibarr_%', '_dolibarr_%' ) );
	
	// ========================================
	// 11. VERIFY CLEANUP
	// ========================================
	
	// Verify that no traces remain
	$remaining_options = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
			'wc_dolibarr_%'
		)
	);
	
	$remaining_transients = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
			'_transient_wc_dolibarr_%'
		)
	);
	
	$remaining_postmeta = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
			'wc_dolibarr_%',
			'_dolibarr_%'
		)
	);
	
	$remaining_usermeta = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
			'wc_dolibarr_%',
			'_dolibarr_%'
		)
	);
	
	// Log verification results
	error_log('WC Dolibarr Cleanup Verification:');
	error_log("- Remaining options: {$remaining_options}");
	error_log("- Remaining transients: {$remaining_transients}");
	error_log("- Remaining post meta: {$remaining_postmeta}");
	error_log("- Remaining user meta: {$remaining_usermeta}");
	
	if ( 0 === (int) $remaining_options && 0 === (int) $remaining_transients && 0 === (int) $remaining_postmeta && 0 === (int) $remaining_usermeta ) {
		error_log('WC Dolibarr Integration Plugin: Cleanup completed successfully - no traces remain');
	} else {
		error_log('WC Dolibarr Integration Plugin: Cleanup completed with some traces remaining');
	}
}

// Execute the cleanup
wc_dolibarr_complete_cleanup();

// Final message
// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
error_log('WC Dolibarr Integration Plugin: Uninstall process completed at ' . gmdate()); 

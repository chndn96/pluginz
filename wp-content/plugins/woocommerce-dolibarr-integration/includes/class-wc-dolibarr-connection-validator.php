<?php
/**
 * Dolibarr Connection Validator
 *
 * @package WC_Dolibarr_Integration
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class WC_Dolibarr_Connection_Validator {
	/**
	 * Validate Dolibarr connection
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function is_connection_valid() {
		$api = new WC_Dolibarr_API();
		
		if (!$api->is_configured()) {
			return false;
		}

		// Check cache first
		$cached_status = get_transient('wc_dolibarr_connection_status');
		if ($cached_status !== false) {
			return $cached_status === 'valid';
		}

		// Test connection
		$result = $api->test_connection();
		$is_valid = !is_wp_error($result) && isset($result['success']) && $result['success'];

		// Cache result for 5 minutes
		set_transient('wc_dolibarr_connection_status', $is_valid ? 'valid' : 'invalid', 5 * MINUTE_IN_SECONDS);

		return $is_valid;
	}

	/**
	 * Get connection status with details
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public static function get_connection_status() {
		$api_url = wc_dolibarr_get_option('api_url');
		$api_key = wc_dolibarr_get_option('api_key');

		$status = array(
			'has_settings' => !empty($api_url) && !empty($api_key),
			'is_valid' => false,
			'message' => '',
			'version' => '',
		);

		if (!$status['has_settings']) {
			$status['message'] = __('API credentials not configured.', 'wc-dolibarr');
			return $status;
		}

		$api = new WC_Dolibarr_API();
		$result = $api->test_connection();

		if (is_wp_error($result)) {
			$status['message'] = $result->get_error_message();
		} else {
			$status['is_valid'] = true;
			$status['message'] = $result['message'] ?? __('Connection successful.', 'wc-dolibarr');
			$status['version'] = $result['version'] ?? '';
		}

		return $status;
	}

	/**
	 * Schedule connection monitoring
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function schedule_connection_monitoring() {
		if (!wp_next_scheduled('wc_dolibarr_connection_monitor')) {
			wp_schedule_event(time(), 'hourly', 'wc_dolibarr_connection_monitor');
		}
	}

	/**
	 * Monitor connection status (cron job)
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function monitor_connection_status() {
		// Clear cache to force fresh check
		delete_transient('wc_dolibarr_connection_status');

		$is_valid = self::is_connection_valid();

		// Log status changes
		$previous_status = get_option('wc_dolibarr_connection_last_status', null);
		
		if ($previous_status !== null && $previous_status !== $is_valid) {
			$logger = new WC_Dolibarr_Logger();
			$message = $is_valid 
				? __('Dolibarr connection restored.', 'wc-dolibarr')
				: __('Dolibarr connection lost.', 'wc-dolibarr');
			
			$logger->log($message, $is_valid ? 'info' : 'error');
		}

		update_option('wc_dolibarr_connection_last_status', $is_valid);
	}

	/**
	 * Clear connection cache
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function clear_connection_cache() {
		delete_transient('wc_dolibarr_connection_status');
	}
}

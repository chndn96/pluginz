<?php
/**
 * Dolibarr Optimizer
 *
 * @package WC_Dolibarr_Integration
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class WC_Dolibarr_Optimizer {
	/**
	 * Check memory usage
	 *
	 * @param int $threshold Memory usage threshold percentage
	 * @since 1.0.0
	 * @return bool
	 */
	public static function check_memory_usage( $threshold = 80 ) {
		$memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
		$memory_usage = memory_get_usage(true);
		$usage_percentage = ($memory_usage / $memory_limit) * 100;

		return $usage_percentage < $threshold;
	}

	/**
	 * Optimize for bulk operations
	 *
	 * @since 1.0.0
	 */
	public static function optimize_for_bulk() {
		// Suspend cache additions
		if (function_exists('wp_suspend_cache_addition')) {
			wp_suspend_cache_addition(true);
		}

		// Increase memory limit if possible
		if (function_exists('ini_set')) {
			ini_set('memory_limit', '512M');
		}

		// Increase execution time
		if (function_exists('set_time_limit')) {
			set_time_limit(300); // 5 minutes
		}
	}
}

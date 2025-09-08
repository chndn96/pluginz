<?php
/**
 * Dolibarr Logger
 *
 * @package WC_Dolibarr_Integration
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class WC_Dolibarr_Logger {
	/**
	 * WooCommerce logger instance
	 *
	 * @var WC_Logger
	 */
	private $logger;

	/**
	 * Log source
	 *
	 * @var string
	 */
	private $source = 'wc-dolibarr';

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		if (class_exists('WC_Logger')) {
			$this->logger = wc_get_logger();
		}
	}

	/**
	 * Log a message
	 *
	 * @param string $message Log message
	 * @param string $level   Log level (emergency, alert, critical, error, warning, notice, info, debug)
	 * @param array  $context Additional context data
	 * @since 1.0.0
	 * @return void
	 */
	public function log( $message, $level = 'info', $context = array() ) {
		if (!$this->logger) {
			return;
		}

		// Only log debug messages if debug mode is enabled
		if ($level === 'debug' && !wc_dolibarr_is_debug_mode()) {
			return;
		}

		// Add context to message if provided
		if (!empty($context)) {
			$message .= ' | Context: ' . wp_json_encode($context);
		}

		$this->logger->log($level, $message, array( 'source' => $this->source ));
	}

	/**
	 * Log emergency message
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context data
	 * @since 1.0.0
	 * @return void
	 */
	public function emergency( $message, $context = array() ) {
		$this->log($message, 'emergency', $context);
	}

	/**
	 * Log alert message
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context data
	 * @since 1.0.0
	 * @return void
	 */
	public function alert( $message, $context = array() ) {
		$this->log($message, 'alert', $context);
	}

	/**
	 * Log critical message
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context data
	 * @since 1.0.0
	 * @return void
	 */
	public function critical( $message, $context = array() ) {
		$this->log($message, 'critical', $context);
	}

	/**
	 * Log error message
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context data
	 * @since 1.0.0
	 * @return void
	 */
	public function error( $message, $context = array() ) {
		$this->log($message, 'error', $context);
	}

	/**
	 * Log warning message
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context data
	 * @since 1.0.0
	 * @return void
	 */
	public function warning( $message, $context = array() ) {
		$this->log($message, 'warning', $context);
	}

	/**
	 * Log notice message
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context data
	 * @since 1.0.0
	 * @return void
	 */
	public function notice( $message, $context = array() ) {
		$this->log($message, 'notice', $context);
	}

	/**
	 * Log info message
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context data
	 * @since 1.0.0
	 * @return void
	 */
	public function info( $message, $context = array() ) {
		$this->log($message, 'info', $context);
	}

	/**
	 * Log debug message
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context data
	 * @since 1.0.0
	 * @return void
	 */
	public function debug( $message, $context = array() ) {
		$this->log($message, 'debug', $context);
	}

	/**
	 * Log sync operation
	 *
	 * @param string $sync_type   Sync type (customer, order, product, inventory)
	 * @param int    $wc_id       WooCommerce ID
	 * @param string $dolibarr_id Dolibarr ID
	 * @param string $status      Sync status (success, error, pending)
	 * @param string $message     Additional message
	 * @since 1.0.0
	 * @return void
	 */
	public function log_sync( $sync_type, $wc_id, $dolibarr_id = null, $status = 'pending', $message = '' ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wc_dolibarr_sync_log';

		$wpdb->insert(
			$table_name,
			array(
				'sync_type' => $sync_type,
				'wc_id' => $wc_id,
				'dolibarr_id' => $dolibarr_id,
				'status' => $status,
				'message' => $message,
				'created_at' => wc_dolibarr_get_current_timestamp(),
				'updated_at' => wc_dolibarr_get_current_timestamp(),
			),
			array(
				'%s',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);

		// Also log to WooCommerce logs
		$log_message = sprintf(
			'[%s] %s ID: %d | Dolibarr ID: %s | Status: %s | %s',
			strtoupper($sync_type),
			ucfirst($sync_type),
			$wc_id,
			$dolibarr_id ?: 'N/A',
			strtoupper($status),
			$message
		);

		$log_level = $status === 'error' ? 'error' : 'info';
		$this->log($log_message, $log_level);
	}

	/**
	 * Update sync log entry
	 *
	 * @param string $sync_type   Sync type
	 * @param int    $wc_id       WooCommerce ID
	 * @param string $dolibarr_id Dolibarr ID
	 * @param string $status      Sync status
	 * @param string $message     Additional message
	 * @since 1.0.0
	 * @return void
	 */
	public function update_sync_log( $sync_type, $wc_id, $dolibarr_id = null, $status = 'pending', $message = '' ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wc_dolibarr_sync_log';

		$wpdb->update(
			$table_name,
			array(
				'dolibarr_id' => $dolibarr_id,
				'status' => $status,
				'message' => $message,
				'updated_at' => wc_dolibarr_get_current_timestamp(),
			),
			array(
				'sync_type' => $sync_type,
				'wc_id' => $wc_id,
			),
			array(
				'%s',
				'%s',
				'%s',
				'%s',
			),
			array(
				'%s',
				'%d',
			)
		);
	}

	/**
	 * Get sync logs
	 *
	 * @param array $args Query arguments
	 * @since 1.0.0
	 * @return array
	 */
	public function get_sync_logs( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'sync_type' => '',
			'status' => '',
			'limit' => 100,
			'offset' => 0,
			'orderby' => 'created_at',
			'order' => 'DESC',
		);

		$args = wp_parse_args($args, $defaults);

		$table_name = $wpdb->prefix . 'wc_dolibarr_sync_log';
		$where_clauses = array();
		$where_values = array();

		if (!empty($args['sync_type'])) {
			$where_clauses[] = 'sync_type = %s';
			$where_values[] = $args['sync_type'];
		}

		if (!empty($args['status'])) {
			$where_clauses[] = 'status = %s';
			$where_values[] = $args['status'];
		}

		$where_sql = '';
		if (!empty($where_clauses)) {
			$where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
		}

		$orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
		$limit = absint($args['limit']);
		$offset = absint($args['offset']);

		$sql = $wpdb->prepare(
			"SELECT * FROM {$table_name} {$where_sql} ORDER BY {$orderby} LIMIT %d OFFSET %d",
			array_merge($where_values, array( $limit, $offset ))
		);

		return $wpdb->get_results($sql, ARRAY_A);
	}

	/**
	 * Clean old sync logs
	 *
	 * @param int $days_to_keep Number of days to keep logs
	 * @since 1.0.0
	 * @return int Number of deleted records
	 */
	public function clean_old_logs( $days_to_keep = 30 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wc_dolibarr_sync_log';
		$cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_to_keep} days"));

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE created_at < %s",
				$cutoff_date
			)
		);

		if ($deleted > 0) {
			$this->info(sprintf('Cleaned %d old sync log entries older than %d days.', $deleted, $days_to_keep));
		}

		return $deleted;
	}

	/**
	 * Get log file path
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_log_file_path() {
		if (!$this->logger) {
			return '';
		}

		$log_files = $this->logger->get_log_file_path($this->source);
		return $log_files;
	}

	/**
	 * Clear all logs
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function clear_logs() {
		if (!$this->logger) {
			return false;
		}

		// Clear WooCommerce logs
		$this->logger->clear($this->source);

		// Clear database sync logs
		global $wpdb;
		$table_name = $wpdb->prefix . 'wc_dolibarr_sync_log';
		$wpdb->query("TRUNCATE TABLE {$table_name}");

		return true;
	}
}

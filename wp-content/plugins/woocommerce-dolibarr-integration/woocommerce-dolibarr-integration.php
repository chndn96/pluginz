<?php
/**
 * Plugin Name: WooCommerce Dolibarr Integration
 * Plugin URI: https://techmarbles.com/products/docs/woocommerce-dolibarr-integration/
 * Description: Complete integration between WooCommerce and Dolibarr ERP system for syncing customers, orders, products, and inventory.
 * Version: 1.0.0
 * Author: Techmarbles
 * Author URI: https://techmarbles.com
 * Text Domain: wc-dolibarr
 * Requires at least: 5.0
 * Tested up to: 6.8
 * WC requires at least: 5.0
 * WC tested up to: 10.0.4
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

// Define plugin constants
define('WC_DOLIBARR_VERSION', '1.0.0');
define('WC_DOLIBARR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_DOLIBARR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_DOLIBARR_PLUGIN_FILE', __FILE__);

add_action( 'before_woocommerce_init', function() {
	// HPOS Compatibility
	if (!defined('WC_DOLIBARR_HPOS_ENABLED')) {
		define('WC_DOLIBARR_HPOS_ENABLED', class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled());
	}
	if (class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

// Load functions file
require_once WC_DOLIBARR_PLUGIN_DIR . 'includes/functions.php';

// Register hooks that use functions from the functions file
register_activation_hook(__FILE__, 'Wc_Dolibarr_Check_requirements');
register_activation_hook(__FILE__, 'Wc_Dolibarr_Create_tables');
register_deactivation_hook(__FILE__, 'Wc_Dolibarr_deactivate');
add_action('plugins_loaded', 'Wc_Dolibarr_init', 20);

class WC_Dolibarr_Integration {
	/**
	 * Plugin instance
	 *
	 * @var WC_Dolibarr_Integration
	 */
	private static $_instance = null;

	/**
	 * API handler
	 *
	 * @var WC_Dolibarr_API
	 */
	public $api;

	/**
	 * Settings handler
	 *
	 * @var WC_Dolibarr_Settings
	 */
	public $settings;

	/**
	 * Customer sync handler
	 *
	 * @var WC_Dolibarr_Customer_Sync
	 */
	public $customer_sync;

	/**
	 * Order sync handler
	 *
	 * @var WC_Dolibarr_Order_Sync
	 */
	public $order_sync;

	/**
	 * Product sync handler
	 *
	 * @var WC_Dolibarr_Product_Sync
	 */
	public $product_sync;

	/**
	 * Inventory sync handler (aliased to product sync)
	 *
	 * @var WC_Dolibarr_Product_Sync
	 */
	public $inventory_sync;

	/**
	 * Cron handler
	 *
	 * @var WC_Dolibarr_Cron
	 */
	public $cron;

	/**
	 * Batch processor handler
	 *
	 * @var WC_Dolibarr_Batch_Processor
	 */
	public $batch_processor;

	/**
	 * Logger handler
	 *
	 * @var WC_Dolibarr_Logger
	 */
	public $logger;

	/**
	 * Get plugin instance
	 *
	 * @since  1.0.0
	 * @return WC_Dolibarr_Integration
	 */
	public static function getInstance() {
		if (null === self::$_instance) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->_initHooks();
		$this->_initClasses();
	}

	/**
	 * Initialize WordPress hooks
	 *
	 * @since 1.0.0
	 */
	private function _initHooks() {
		// Load textdomain at the proper time (init hook)
		add_action('init', array( $this, 'loadTextdomain' ));
		add_action('admin_enqueue_scripts', array( $this, 'adminScripts' ));
		add_action('admin_notices', array( $this, 'admin_notices' ));
		add_filter('plugin_action_links_' . plugin_basename(WC_DOLIBARR_PLUGIN_FILE), array( $this, 'pluginActionLinks' ));

		// Check connection status and reschedule crons when connection is restored
		add_action('wp_loaded', array( $this, 'checkAndRescheduleCrons' ));

		// Periodically check connection status and disable crons if needed
		add_action('wp_loaded', array( $this, 'checkAndDisableCronsIfNeeded' ));

		// Schedule connection monitoring
		add_action('wp_loaded', array( $this, 'scheduleConnectionMonitoring' ));

		// Hook for connection monitoring cron
		add_action('wc_dolibarr_connection_monitor', array( $this, 'monitorConnectionStatus' ));

		// Hook for daily cache refresh cron
		add_action('wc_dolibarr_daily_cache_refresh', array( 'WC_Dolibarr_Cache_Manager', 'handle_daily_cache_refresh' ));
	}

	/**
	 * Initialize plugin classes
	 *
	 * @since 1.0.0
	 */
	private function _initClasses() {
		try {
			// Initialize logger first
			if (class_exists('WC_Dolibarr_Logger')) {
				$this->logger = new WC_Dolibarr_Logger();
			}

			// Initialize API
			if (class_exists('WC_Dolibarr_API')) {
				$this->api = new WC_Dolibarr_API();
			}

			// Initialize settings
			if (class_exists('WC_Dolibarr_Settings')) {
				$this->settings = new WC_Dolibarr_Settings();
			}

			// Initialize product settings (removed; class not present)

			// Initialize batch processor
			if (class_exists('WC_Dolibarr_Batch_Processor')) {
				$this->batch_processor = new WC_Dolibarr_Batch_Processor();
			}

			// Initialize cache manager and schedule daily refresh
			if (class_exists('WC_Dolibarr_Cache_Manager')) {
				WC_Dolibarr_Cache_Manager::schedule_cache_refresh();
			}

			// Initialize sync classes only if API and logger are available
			// Use lazy loading to prevent memory issues
			if ($this->api && $this->logger) {
				// Only initialize sync classes when needed
				add_action('init', array( $this, 'initSyncClasses' ), 20);
			}
		} catch (Exception $e) {
			error_log('WC Dolibarr: Error initializing classes: ' . $e->getMessage());
		}
	}

	/**
	 * Initialize sync classes on demand
	 *
	 * @since 1.0.0
	 */
	public function initSyncClasses() {
		try {
			// Check memory usage before initializing
			if (class_exists('WC_Dolibarr_Optimizer') && !WC_Dolibarr_Optimizer::check_memory_usage(80)) {
				error_log('WC Dolibarr: High memory usage detected, skipping sync class initialization');
				return;
			}

			if (class_exists('WC_Dolibarr_Customer_Sync')) {
				$this->customer_sync = new WC_Dolibarr_Customer_Sync();
			}

			if (class_exists('WC_Dolibarr_Order_Sync')) {
				$this->order_sync = new WC_Dolibarr_Order_Sync();
			}

			if (class_exists('WC_Dolibarr_Product_Sync')) {
				$this->product_sync = new WC_Dolibarr_Product_Sync();
			}
			// Alias inventory sync to product sync for unified handling
			if ($this->product_sync) {
				$this->inventory_sync = $this->product_sync;
			}

			if (class_exists('WC_Dolibarr_Cron')) {
				$this->cron = new WC_Dolibarr_Cron();

				// Force schedule events after cron class is initialized
				if ($this->cron) {
					$this->cron->scheduleEvents();
				}
			}
		} catch (Exception $e) {
			error_log('WC Dolibarr: Error initializing sync classes: ' . $e->getMessage());
		}
	}

	/**
	 * Reschedule cron jobs
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function rescheduleCronJobs() {
		if ($this->cron) {
			$this->cron->scheduleEvents();
		}
	}

	/**
	 * Check and reschedule crons if connection is restored
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function checkAndRescheduleCrons() {
		if (class_exists('WC_Dolibarr_Connection_Validator')) {
			// Check if connection is now valid
			if (WC_Dolibarr_Connection_Validator::is_connection_valid()) {
				// Reschedule crons if they're not already scheduled
				if ($this->cron) {
					$this->cron->scheduleEvents();
				}

				// Log that crons have been rescheduled
				// if ($this->logger) {
				//     $this->logger->log('Dolibarr connection restored - cron jobs rescheduled', 'info');
				// }
			}
		}
	}

	/**
	 * Check and disable crons if connection becomes invalid
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function checkAndDisableCronsIfNeeded() {
		if ($this->cron) {
			$this->cron->checkAndDisableCronsIfNeeded();
		}
	}

	/**
	 * Schedule connection monitoring
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function scheduleConnectionMonitoring() {
		if (class_exists('WC_Dolibarr_Connection_Validator')) {
			WC_Dolibarr_Connection_Validator::schedule_connection_monitoring();
		}
	}

	/**
	 * Monitor connection status (called by WordPress cron)
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function monitorConnectionStatus() {
		if (class_exists('WC_Dolibarr_Connection_Validator')) {
			WC_Dolibarr_Connection_Validator::monitor_connection_status();
		}
	}

	/**
	 * Load plugin textdomain
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function loadTextdomain() {
		load_plugin_textdomain('wc-dolibarr', false, dirname(plugin_basename(__FILE__)) . '/languages');
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @param  string $hook Current admin page hook.
	 * @since  1.0.0
	 * @return void
	 */
	public function adminScripts( $hook ) {
		if (strpos($hook, 'wc-dolibarr') !== false) {
			$active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';
			// Enqueue Select2
			wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
			wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array( 'jquery' ), '4.1.0', true);

			wp_enqueue_style('wc-dolibarr-admin', WC_DOLIBARR_PLUGIN_URL . 'assets/css/admin.css', array(), WC_DOLIBARR_VERSION);
			// Dashboard/DataTables assets
			if ($active_tab === 'dashboard' || $active_tab === 'logs') {
				wp_enqueue_style('datatables', 'https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css');
				wp_enqueue_script('datatables', 'https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js', array( 'jquery' ), '1.13.7', true);
			}
			wp_enqueue_script('wc-dolibarr-admin', WC_DOLIBARR_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'select2' ), WC_DOLIBARR_VERSION, true);
			wp_localize_script(
				'wc-dolibarr-admin',
				'wc_dolibarr_ajax',
				array(
					'ajax_url' => admin_url('admin-ajax.php'),
					'nonce' => wp_create_nonce('wc_dolibarr_nonce'),
					'strings' => array(
						'sync_started' => __('Sync started...', 'wc-dolibarr'),
						'sync_completed' => __('Sync completed successfully!', 'wc-dolibarr'),
						'sync_error' => __('Sync failed. Please check the logs.', 'wc-dolibarr'),
					),
				)
			);
		}
	}

	/**
	 * Add settings link to plugin action links
	 *
	 * @param  array $links Plugin action links.
	 * @since  1.0.0
	 * @return array
	 */
	public function pluginActionLinks( $links ) {
		$settings_link = '<a href="' . admin_url('admin.php?page=wc-dolibarr-settings') . '">' . __('Settings', 'wc-dolibarr') . '</a>';
		array_unshift($links, $settings_link);
		return $links;
	}

	/**
	 * Display admin notices
	 */
	public function admin_notices() {
		// HPOS compatibility notice
		if (WC_DOLIBARR_HPOS_ENABLED) {
			echo '<div class="notice notice-info is-dismissible"><p>' .
				esc_html__('WooCommerce Dolibarr Integration: High-Performance Order Storage (HPOS) is enabled and fully supported.', 'wc-dolibarr') .
				'</p></div>';
		}

		// Dolibarr connection status notice
		if (class_exists('WC_Dolibarr_Connection_Validator')) {
			$connection_status = WC_Dolibarr_Connection_Validator::get_connection_status();

			if (!$connection_status['has_settings']) {
				echo '<div class="notice notice-warning is-dismissible"><p>' .
					'<strong>' . esc_html__('Dolibarr Integration:', 'wc-dolibarr') . '</strong> ' .
					esc_html__('API settings are not configured. Please configure the Dolibarr API settings to enable synchronization.', 'wc-dolibarr') .
					' <a href="' . esc_url(admin_url('admin.php?page=wc-dolibarr-settings&tab=api')) . '">' . esc_html__('Configure Now', 'wc-dolibarr') . '</a>' .
					'</p></div>';
			} elseif (!$connection_status['is_valid']) {
				echo '<div class="notice notice-error is-dismissible"><p>' .
					'<strong>' . esc_html__('Dolibarr Integration:', 'wc-dolibarr') . '</strong> ' .
					esc_html__('Connection to Dolibarr failed. Automatic synchronization is disabled until connection is restored.', 'wc-dolibarr') .
					' <a href="' . esc_url(admin_url('admin.php?page=wc-dolibarr-settings&tab=api')) . '">' . esc_html__('Check Settings', 'wc-dolibarr') . '</a>' .
					'</p></div>';
			}
		}
	}
}

<?php
/**
 * Dolibarr Settings
 *
 * @package WC_Dolibarr_Integration
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class WC_Dolibarr_Settings {
	/**
	 * Settings tabs
	 *
	 * @var array
	 */
	private $tabs;

	/**
	 * Current tab
	 *
	 * @var string
	 */
	private $current_tab;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->init_tabs();
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		add_action('admin_menu', array( $this, 'add_admin_menu' ));
		add_action('admin_init', array( $this, 'init_settings' ));
		add_action('wp_ajax_wc_dolibarr_test_connection', array( $this, 'ajax_test_connection' ));
		add_action('wp_ajax_wc_dolibarr_sync_customers', array( $this, 'ajax_sync_customers' ));
		add_action('wp_ajax_wc_dolibarr_sync_orders', array( $this, 'ajax_sync_orders' ));
		// add_action('wp_ajax_wc_dolibarr_sync_products', array( $this, 'ajax_sync_products' ));
		add_action('wp_ajax_wc_dolibarr_sync_products', array($this, 'ajax_sync_products')); // Export
		add_action('wp_ajax_wc_dolibarr_sync_import_products', array($this, 'ajax_import_products')); // Import
		add_action('wp_ajax_wc_dolibarr_sync_inventory', array( $this, 'ajax_sync_inventory' )); //export
		add_action('wp_ajax_wc_dolibarr_sync_import_inventory', array( $this, 'ajax_import_inventory' )); //import

		// Dashboard & logs AJAX endpoints
		add_action('wp_ajax_wc_dolibarr_get_dashboard_stats', array( $this, 'get_dashboard_stats' ));
		add_action('wp_ajax_wc_dolibarr_get_order_sync_history', array( $this, 'get_order_sync_history' ));
		add_action('wp_ajax_wc_dolibarr_resync_order', array( $this, 'resync_order' ));

		
		// Order Sync AJAX endpoints
		add_action('wp_ajax_wc_dolibarr_batch_sync_previous_orders', array( $this, 'batch_sync_previous_orders' ));
		add_action('wp_ajax_wc_dolibarr_batch_sync_previous_customers', array( $this, 'batch_sync_previous_customers' ));

	}

	/**
	 * Initialize tabs
	 *
	 * @since 1.0.0
	 */
	private function init_tabs() {
		$this->tabs = array(
			'dashboard' => __('Dashboard', 'wc-dolibarr'),
			'api' => __('API Settings', 'wc-dolibarr'),
			'configurations' => __('Configurations', 'wc-dolibarr'),
			'order_sync' => __('Order Sync', 'wc-dolibarr'),
			'product_inventory' => __('Product & Inventory', 'wc-dolibarr'),
									'logs' => __('Sync Logs', 'wc-dolibarr'),
					);

		$this->current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'dashboard';
	}

	/**
	 * Add admin menu
	 *
	 * @since 1.0.0
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'woocommerce',
			__('Dolibarr Integration', 'wc-dolibarr'),
			__('Dolibarr Integration', 'wc-dolibarr'),
			'manage_woocommerce',
			'wc-dolibarr-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Initialize settings
	 *
	 * @since 1.0.0
	 */
	public function init_settings() {
		// Register settings for each tab
		foreach ($this->tabs as $tab_key => $tab_name) {
			$method_name = "init_{$tab_key}_settings";
			if (method_exists($this, $method_name)) {
				$this->$method_name();
			}
		}
		
		// Initialize Order Sync settings
		$this->init_order_sync_settings();
	}

	/**
	 * Initialize API settings
	 *
	 * @since 1.0.0
	 */
	private function init_api_settings() {
		register_setting('wc_dolibarr_api_settings', 'wc_dolibarr_api_url');
		register_setting('wc_dolibarr_api_settings', 'wc_dolibarr_api_key');
		register_setting('wc_dolibarr_api_settings', 'wc_dolibarr_ssl_verify');
		register_setting('wc_dolibarr_api_settings', 'wc_dolibarr_debug_mode');

		add_settings_section(
			'wc_dolibarr_api_section',
			__('Dolibarr API Configuration', 'wc-dolibarr'),
			array( $this, 'api_section_callback' ),
			'wc_dolibarr_api_settings'
		);

		add_settings_field(
			'wc_dolibarr_api_url',
			__('Dolibarr URL', 'wc-dolibarr'),
			array( $this, 'api_url_callback' ),
			'wc_dolibarr_api_settings',
			'wc_dolibarr_api_section'
		);

		add_settings_field(
			'wc_dolibarr_api_key',
			__('API Key', 'wc-dolibarr'),
			array( $this, 'api_key_callback' ),
			'wc_dolibarr_api_settings',
			'wc_dolibarr_api_section'
		);

		add_settings_field(
			'wc_dolibarr_ssl_verify',
			__('SSL Verification', 'wc-dolibarr'),
			array( $this, 'ssl_verify_callback' ),
			'wc_dolibarr_api_settings',
			'wc_dolibarr_api_section'
		);

		add_settings_field(
			'wc_dolibarr_debug_mode',
			__('Debug Mode', 'wc-dolibarr'),
			array( $this, 'debug_mode_callback' ),
			'wc_dolibarr_api_settings',
			'wc_dolibarr_api_section'
		);
	}

	/**
	 * Initialize company settings
	 *
	 * @since 1.0.0
	 */
	private function init_configurations_settings() {
		// Group all configuration options under one settings group
		$group = 'wc_dolibarr_configurations_settings';

		// Company Settings (existing)
		register_setting($group, 'wc_dolibarr_default_warehouse');
		register_setting($group, 'wc_dolibarr_default_payment_method');
		register_setting($group, 'wc_dolibarr_default_bank_account');
		register_setting($group, 'wc_dolibarr_currency');

		// Account & Tax Settings (new)
		register_setting($group, 'wc_dolibarr_debtors_account');
		register_setting($group, 'wc_dolibarr_income_account');
		register_setting($group, 'wc_dolibarr_cost_center');
		register_setting($group, 'wc_dolibarr_tax_template');
		register_setting($group, 'wc_dolibarr_sales_tax_account');
		register_setting($group, 'wc_dolibarr_shipping_account');
		register_setting($group, 'wc_dolibarr_shipping_rule');

		// Order Settings (new)
		register_setting($group, 'wc_dolibarr_order_naming_series');
		register_setting($group, 'wc_dolibarr_default_customer');
		register_setting($group, 'wc_dolibarr_add_shipping_as_item');
		register_setting($group, 'wc_dolibarr_item_code_prefix');
		register_setting($group, 'wc_dolibarr_item_group');
		register_setting($group, 'wc_dolibarr_default_uom');
		register_setting($group, 'wc_dolibarr_default_hsn_code');
	}

	/**
	 * Initialize Order Sync settings
	 *
	 * @since 1.0.0
	 */
	private function init_order_sync_settings() {
		// Order Sync Settings
		register_setting('wc_dolibarr_order_sync_settings', 'wc_dolibarr_sync_customers');
		register_setting('wc_dolibarr_order_sync_settings', 'wc_dolibarr_order_sync_enabled');
		register_setting('wc_dolibarr_order_sync_settings', 'wc_dolibarr_sync_order_status_updates');
		register_setting('wc_dolibarr_order_sync_settings', 'wc_dolibarr_default_customer_group');
		register_setting('wc_dolibarr_order_sync_settings', 'wc_dolibarr_auto_detect_customer_type');

		// Dolibarr Document Creation Settings
		register_setting('wc_dolibarr_order_sync_settings', 'wc_dolibarr_create_quotes');
		register_setting('wc_dolibarr_order_sync_settings', 'wc_dolibarr_create_sales_orders');
		register_setting('wc_dolibarr_order_sync_settings', 'wc_dolibarr_create_invoices');

		// Batch Sync Settings for Previous Data
		register_setting('wc_dolibarr_order_sync_settings', 'wc_dolibarr_batch_sync_previous_orders');
		register_setting('wc_dolibarr_order_sync_settings', 'wc_dolibarr_batch_sync_previous_customers');
		register_setting('wc_dolibarr_order_sync_settings', 'wc_dolibarr_batch_sync_order_limit');
		register_setting('wc_dolibarr_order_sync_settings', 'wc_dolibarr_batch_sync_customer_limit');
		register_setting('wc_dolibarr_order_sync_settings', 'wc_dolibarr_batch_sync_order_statuses');
		register_setting('wc_dolibarr_order_sync_settings', 'wc_dolibarr_batch_sync_skip_existing');
		register_setting('wc_dolibarr_order_sync_settings', 'wc_dolibarr_batch_sync_all_order_statuses');
		register_setting('wc_dolibarr_order_sync_settings', 'wc_dolibarr_batch_sync_debug_mode');
		register_setting('wc_dolibarr_order_sync_settings', 'wc_dolibarr_batch_sync_max_retries');
	}

	/**
	 * Initialize sync settings
	 *
	 * @since 1.0.0
	 */
	private function init_product_inventory_settings() {
		// Automatic Product Sync settings
		register_setting('wc_dolibarr_product_inventory_settings', 'wc_dolibarr_auto_product_sync_enabled');
		register_setting('wc_dolibarr_product_inventory_settings', 'wc_dolibarr_auto_product_sync_interval');
		register_setting('wc_dolibarr_product_inventory_settings', 'wc_dolibarr_auto_product_sync_update_existing');
		register_setting('wc_dolibarr_product_inventory_settings', 'wc_dolibarr_auto_product_sync_create_categories');
		register_setting('wc_dolibarr_product_inventory_settings', 'wc_dolibarr_auto_product_sync_skip_disabled');

		// Batch Processing settings
		register_setting('wc_dolibarr_product_inventory_settings', 'wc_dolibarr_auto_product_batch_enable');
		register_setting('wc_dolibarr_product_inventory_settings', 'wc_dolibarr_auto_product_batch_size');
		register_setting('wc_dolibarr_product_inventory_settings', 'wc_dolibarr_auto_product_batch_max_time');

		// Automatic Inventory & Price Sync settings
		register_setting('wc_dolibarr_product_inventory_settings', 'wc_dolibarr_auto_inventory_sync_enabled');
		register_setting('wc_dolibarr_product_inventory_settings', 'wc_dolibarr_auto_inventory_sync_interval');
	}

	private function init_sync_settings() {
		register_setting('wc_dolibarr_sync_settings', 'wc_dolibarr_sync_customers');
		register_setting('wc_dolibarr_sync_settings', 'wc_dolibarr_sync_orders');
		register_setting('wc_dolibarr_sync_settings', 'wc_dolibarr_sync_products');
		register_setting('wc_dolibarr_sync_settings', 'wc_dolibarr_sync_inventory');
		register_setting('wc_dolibarr_sync_settings', 'wc_dolibarr_inventory_sync_interval');
		register_setting('wc_dolibarr_sync_settings', 'wc_dolibarr_enable_tax_sync');

		add_settings_section(
			'wc_dolibarr_sync_section',
			__('Synchronization Settings', 'wc-dolibarr'),
			array( $this, 'sync_section_callback' ),
			'wc_dolibarr_sync_settings'
		);

		add_settings_field(
			'wc_dolibarr_sync_customers',
			__('Customer Sync', 'wc-dolibarr'),
			array( $this, 'sync_customers_callback' ),
			'wc_dolibarr_sync_settings',
			'wc_dolibarr_sync_section'
		);

		add_settings_field(
			'wc_dolibarr_sync_orders',
			__('Order Sync', 'wc-dolibarr'),
			array( $this, 'sync_orders_callback' ),
			'wc_dolibarr_sync_settings',
			'wc_dolibarr_sync_section'
		);

		add_settings_field(
			'wc_dolibarr_sync_products',
			__('Product Sync', 'wc-dolibarr'),
			array( $this, 'sync_products_callback' ),
			'wc_dolibarr_sync_settings',
			'wc_dolibarr_sync_section'
		);

		add_settings_field(
			'wc_dolibarr_sync_inventory',
			__('Inventory Sync', 'wc-dolibarr'),
			array( $this, 'sync_inventory_callback' ),
			'wc_dolibarr_sync_settings',
			'wc_dolibarr_sync_section'
		);

		add_settings_field(
			'wc_dolibarr_inventory_sync_interval',
			__('Inventory Sync Interval', 'wc-dolibarr'),
			array( $this, 'inventory_sync_interval_callback' ),
			'wc_dolibarr_sync_settings',
			'wc_dolibarr_sync_section'
		);

		add_settings_field(
			'wc_dolibarr_enable_tax_sync',
			__('Tax Sync', 'wc-dolibarr'),
			array( $this, 'enable_tax_sync_callback' ),
			'wc_dolibarr_sync_settings',
			'wc_dolibarr_sync_section'
		);
	}

	/**
	 * Render settings page
	 *
	 * @since 1.0.0
	 */
	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html(get_admin_page_title()); ?></h1>

			<nav class="nav-tab-wrapper">
				<?php foreach ($this->tabs as $tab_key => $tab_name) : ?>
					<a href="<?php echo esc_url(admin_url('admin.php?page=wc-dolibarr-settings&tab=' . $tab_key)); ?>" 
					   class="nav-tab <?php echo $this->current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html($tab_name); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="tab-content">
				<?php
				switch ($this->current_tab) {
					case 'dashboard':
						$this->render_dashboard_tab();
						break;
					case 'api':
						$this->render_api_tab();
						break;
				case 'configurations':
					$this->render_configurations_tab();
						break;
					case 'order_sync':
						$this->render_order_sync_tab();
						break;
					case 'product_inventory':
						$this->render_product_inventory_tab();
						break;
															case 'logs':
						$this->render_logs_tab();
						break;
										default:
						$this->render_api_tab();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Dashboard tab (mirrors ERPNext layout)
	 */
	private function render_dashboard_tab() {
		?>
		<div class="wc-dolibarr-dashboard">
			<div class="wc-dolibarr-stats-container">
				<div class="wc-dolibarr-stat-card">
					<div style="display: flex; align-items: center; justify-content: space-between;">
						<div>
							<h3 id="wc-dolibarr-total-orders-synced">-</h3>
							<p><?php esc_html_e('Total Orders Synced', 'wc-dolibarr'); ?></p>
						</div>
						<div class="wc-dolibarr-stat-icon"><span class="dashicons dashicons-cart" style="font-size:24px;"></span></div>
					</div>
				</div>
				<div class="wc-dolibarr-stat-card">
					<div style="display: flex; align-items: center; justify-content: space-between;">
						<div>
							<h3 id="wc-dolibarr-total-customers-synced">-</h3>
							<p><?php esc_html_e('Total Customers Synced', 'wc-dolibarr'); ?></p>
						</div>
						<div class="wc-dolibarr-stat-icon"><span class="dashicons dashicons-groups" style="font-size:24px;"></span></div>
					</div>
				</div>
				<div class="wc-dolibarr-stat-card">
					<div style="display: flex; align-items: center; justify-content: space-between;">
						<div>
							<h3 id="wc-dolibarr-inventory-last-update">-</h3>
							<p><?php esc_html_e('Inventory Last Update', 'wc-dolibarr'); ?></p>
						</div>
						<div class="wc-dolibarr-stat-icon"><span class="dashicons dashicons-chart-line" style="font-size:24px;"></span></div>
					</div>
				</div>
			</div>

			<div class="wc-dolibarr-table-container">
				<h3><?php esc_html_e('Order Sync History', 'wc-dolibarr'); ?></h3>
				<table id="wc-dolibarr-order-sync-history-table" class="display" style="width:100%;">
					<thead>
						<tr>
							<th style="width: 15%;"><?php esc_html_e('WC Order ID', 'wc-dolibarr'); ?></th>
							<th style="width: 20%;"><?php esc_html_e('Dolibarr Order ID', 'wc-dolibarr'); ?></th>
							<th style="width: 25%;"><?php esc_html_e('Synced At', 'wc-dolibarr'); ?></th>
							<th style="width: 15%;"><?php esc_html_e('Status', 'wc-dolibarr'); ?></th>
							<th style="width: 25%;"><?php esc_html_e('Actions', 'wc-dolibarr'); ?></th>
						</tr>
					</thead>
					<tbody></tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Dashboard: Get stats
	 */
	public function get_dashboard_stats() {
		check_ajax_referer('wc_dolibarr_nonce', 'nonce');

		if (!current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('Insufficient permissions.', 'wc-dolibarr'));
		}

		global $wpdb;
		$table_history = $wpdb->prefix . 'wc_dolibarr_order_sync_history';
		$table_logs = $wpdb->prefix . 'wc_dolibarr_sync_log';

		// Ensure tables exist
		$history_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_history));
		$logs_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_logs));
		if (!$history_exists || !$logs_exists) {
			wp_send_json_error(array( 'message' => 'Required tables not found' ));
			return;
		}

		$total_orders_synced = (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT COUNT(*) FROM `{$table_history}` WHERE sync_status = 'success'"
		);
		$total_customers_synced = (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM `{$table_logs}` WHERE sync_type = %s AND status = %s",
				'customer',
				'success'
			)
		);
	
		$inventory_last_update = get_option( 'wc_dolibarr_inventory_last_update', '' );

		if ( $inventory_last_update ) {
			$inventory_last_update = wp_date( 'Y-m-d H:i:s', $inventory_last_update );
		}		

		wp_send_json_success(array(
			'total_orders_synced' => $total_orders_synced,
			'total_customers_synced' => $total_customers_synced,
			'inventory_last_update' => $inventory_last_update,
		));
	}

	/**
	 * Dashboard: Order history for DataTables
	 */
	public function get_order_sync_history() {
		check_ajax_referer('wc_dolibarr_nonce', 'nonce');

		if (!current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('Insufficient permissions.', 'wc-dolibarr'));
		}

		global $wpdb;
		$table = $wpdb->prefix . 'wc_dolibarr_order_sync_history';

		$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
		$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
		$length = isset($_POST['length']) ? intval($_POST['length']) : 10;
		$search = isset($_POST['search']['value']) ? sanitize_text_field(wp_unslash($_POST['search']['value'])) : '';

		if (!empty($search)) {
			$like = '%' . $wpdb->esc_like($search) . '%';
			$total_records = (int) $wpdb->get_var(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT COUNT(*) FROM `{$table}` WHERE (order_id LIKE %s OR dolibarr_order_id LIKE %s)",
					$like,
					$like
				)
			);
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT * FROM `{$table}` WHERE (order_id LIKE %s OR dolibarr_order_id LIKE %s) ORDER BY last_sync_at DESC LIMIT %d OFFSET %d",
					$like,
					$like,
					$length,
					$start
				)
			);
		} else {
			$total_records = (int) $wpdb->get_var(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM `{$table}`"
			);
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT * FROM `{$table}` ORDER BY last_sync_at DESC LIMIT %d OFFSET %d",
					$length,
					$start
				)
			);
		}

		$data = array();
		foreach ($rows as $row) {
			$wc_order_link = admin_url('post.php?post=' . $row->order_id . '&action=edit');
			$dolibarr_link = '';
			if (!empty($row->dolibarr_order_id)) {
				$api_url = wc_dolibarr_get_option('api_url', '');
				if ($api_url) {
					$api_url = rtrim($api_url, '/');
					$param_key = ctype_digit((string) $row->dolibarr_order_id) ? 'id' : 'ref';
					// Sales orders in Dolibarr live under /commande/card.php
					$dolibarr_link = $api_url . '/commande/card.php?' . $param_key . '=' . rawurlencode($row->dolibarr_order_id);
				}
			}

			$status_class = ($row->sync_status === 'success') ? 'success' : 'error';
			$status_text = ($row->sync_status === 'success') ? __('Success', 'wc-dolibarr') : __('Failure', 'wc-dolibarr');
			$status_html = '<span class="sync-status sync-status-' . esc_attr($status_class) . '"' . (!empty($row->error_message) ? ' title="' . esc_attr($row->error_message) . '"' : '') . '>' . esc_html($status_text) . '</span>';

			$actions = array();
			$actions[] = '<a href="' . esc_url($wc_order_link) . '" target="_blank" class="wc-erpnext-action-btn wc-logo-link" title="' . esc_attr__('View in WooCommerce', 'wc-dolibarr') . '"><span class="dashicons dashicons-welcome-view-site"></span></a>';
			if ($dolibarr_link) {
				$actions[] = '<a href="' . esc_url($dolibarr_link) . '" target="_blank" class="wc-erpnext-action-btn erpnext-logo-link" title="' . esc_attr__('View in Dolibarr', 'wc-dolibarr') . '"><span class="dashicons dashicons-external"></span></a>';
			}
			$actions[] = '<button type="button" class="wc-erpnext-action-btn wc-dolibarr-resync-order" data-order-id="' . esc_attr($row->order_id) . '" title="' . esc_attr__('Resync Order', 'wc-dolibarr') . '"><span class="dashicons dashicons-update"></span></button>';

			$data[] = array(
				'<a href="' . esc_url($wc_order_link) . '" target="_blank">#' . esc_html($row->order_id) . '</a>',
				$row->dolibarr_order_id ? '<a href="' . esc_url($dolibarr_link) . '" target="_blank">' . esc_html($row->dolibarr_order_id) . '</a>' : '-',
				esc_html($row->last_sync_at),
				$status_html,
				implode(' ', $actions),
			);
		}

		wp_send_json(array(
			'draw' => $draw,
			'recordsTotal' => intval($total_records),
			'recordsFiltered' => intval($total_records),
			'data' => $data,
		));
	}

	/**
	 * Dashboard: Resync a specific order
	 */
	public function resync_order() {
		check_ajax_referer('wc_dolibarr_nonce', 'nonce');

		if (!current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('Insufficient permissions.', 'wc-dolibarr'));
		}

		$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
		if (!$order_id) {
			wp_send_json_error(__('Invalid order ID.', 'wc-dolibarr'));
		}

		$plugin = WC_Dolibarr_Integration::getInstance();
		if (!$plugin || !isset($plugin->order_sync)) {
			wp_send_json_error(__('Order sync class not available.', 'wc-dolibarr'));
		}

		$result = $plugin->order_sync->sync_order($order_id);
		if (is_wp_error($result)) {
			wp_send_json_error($result->get_error_message());
		}
		if (is_array($result) && isset($result['status']) && $result['status'] === 'success') {
			wp_send_json_success(__('Order resynced successfully.', 'wc-dolibarr'));
		}
		wp_send_json_error(__('Order resync failed. Please check the logs.', 'wc-dolibarr'));
	}

	/**
	 * Render API tab
	 *
	 * @since 1.0.0
	 */
	private function render_api_tab() {
		?>
		<form method="post" action="options.php">
			<?php
			settings_fields('wc_dolibarr_api_settings');
			do_settings_sections('wc_dolibarr_api_settings');
			?>
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row"></th>
						<td>
							<button type="button" id="test-connection" class="button button-secondary">
								<?php esc_html_e('Test Connection', 'wc-dolibarr'); ?>
							</button>
							<div id="connection-result" style="margin-top: 10px;"></div>
						</td>
					</tr>
				</tbody>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Render configurations tab
	 *
	 * Groups: Company Settings, Account & Tax Settings, Order Settings
	 */
	private function render_configurations_tab() {
		?>
		<form method="post" action="options.php">
			<?php
			settings_fields('wc_dolibarr_configurations_settings');
			do_settings_sections('wc_dolibarr_configurations_settings');
			?>

			<h3><?php esc_html_e('Company Settings', 'wc-dolibarr'); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e('Default Warehouse', 'wc-dolibarr'); ?></th>
					<td>
						<?php $this->default_warehouse_callback(); ?>
						<p class="description"><?php esc_html_e('Default warehouse for inventory and order operations.', 'wc-dolibarr'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Default Payment Method', 'wc-dolibarr'); ?></th>
					<td>
						<?php $this->default_payment_method_callback(); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Default Bank Account', 'wc-dolibarr'); ?></th>
					<td>
						<?php $this->default_bank_account_callback(); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Currency', 'wc-dolibarr'); ?></th>
					<td>
						<?php $this->currency_callback(); ?>
					</td>
				</tr>
			</table>

			<h3><?php esc_html_e('Account & Tax Settings', 'wc-dolibarr'); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e('Debtors Account', 'wc-dolibarr'); ?></th>
					<td>
					<?php $this->bank_account_dropdown('wc_dolibarr_debtors_account'); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Income Account', 'wc-dolibarr'); ?></th>
					<td>
						<?php $this->bank_account_dropdown('wc_dolibarr_income_account'); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Cost Center', 'wc-dolibarr'); ?></th>
					<td>
						<input type="text" name="wc_dolibarr_cost_center" value="<?php echo esc_attr(get_option('wc_dolibarr_cost_center', '')); ?>" class="regular-text" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Tax Template', 'wc-dolibarr'); ?></th>
					<td>
						<input type="text" name="wc_dolibarr_tax_template" value="<?php echo esc_attr(get_option('wc_dolibarr_tax_template', '')); ?>" class="regular-text" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Sales Tax Account', 'wc-dolibarr'); ?></th>
					<td>
						<?php $this->bank_account_dropdown('wc_dolibarr_sales_tax_account'); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Shipping Account', 'wc-dolibarr'); ?></th>
					<td>
						<?php $this->bank_account_dropdown('wc_dolibarr_shipping_account'); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Shipping Rule', 'wc-dolibarr'); ?></th>
					<td>
						<input type="text" name="wc_dolibarr_shipping_rule" value="<?php echo esc_attr(get_option('wc_dolibarr_shipping_rule', '')); ?>" class="regular-text" />
					</td>
				</tr>
			</table>

			<h3><?php esc_html_e('Order Settings', 'wc-dolibarr'); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e('Order Naming Series', 'wc-dolibarr'); ?></th>
					<td>
						<input type="text" name="wc_dolibarr_order_naming_series" value="<?php echo esc_attr(get_option('wc_dolibarr_order_naming_series', 'SO-WC-')); ?>" class="regular-text" placeholder="SO-WC-" />
						<p class="description"><?php esc_html_e('Prefix for Dolibarr order references created from WooCommerce orders.', 'wc-dolibarr'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Default Customer', 'wc-dolibarr'); ?></th>
					<td>
						<?php $this->default_customer_text_field(); ?>
						<p class="description"><?php esc_html_e('Used for guest orders without a matched Dolibarr customer. Enter a name or code to use for a guest customer.', 'wc-dolibarr'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Add Shipping as Item', 'wc-dolibarr'); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wc_dolibarr_add_shipping_as_item" value="1" <?php checked(get_option('wc_dolibarr_add_shipping_as_item', false)); ?> />
							<?php esc_html_e('Add shipping as a separate line item on orders.', 'wc-dolibarr'); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Item Code Prefix', 'wc-dolibarr'); ?></th>
					<td>
						<input type="text" name="wc_dolibarr_item_code_prefix" value="<?php echo esc_attr(get_option('wc_dolibarr_item_code_prefix', 'ITEM-')); ?>" class="regular-text" placeholder="ITEM-" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Item Group', 'wc-dolibarr'); ?></th>
					<td>
						<input type="text" name="wc_dolibarr_item_group" value="<?php echo esc_attr(get_option('wc_dolibarr_item_group', '')); ?>" class="regular-text" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Default UOM', 'wc-dolibarr'); ?></th>
					<td>
						<input type="text" name="wc_dolibarr_default_uom" value="<?php echo esc_attr(get_option('wc_dolibarr_default_uom', '')); ?>" class="regular-text" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Default HSN Code', 'wc-dolibarr'); ?></th>
					<td>
						<input type="text" name="wc_dolibarr_default_hsn_code" value="<?php echo esc_attr(get_option('wc_dolibarr_default_hsn_code', '')); ?>" class="regular-text" placeholder="e.g., 998314" />
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Render Order Sync tab
	 *
	 * @since 1.0.0
	 */
	private function render_order_sync_tab() {
		?>
		<form method="post" action="options.php">
			<?php
			settings_fields('wc_dolibarr_order_sync_settings');
			do_settings_sections('wc_dolibarr_order_sync_settings');
			?>
			
			<div class="wc-dolibarr-realtime-sync-info">
				<div class="notice notice-info">
					<p><strong><?php esc_html_e('Real-time Synchronization', 'wc-dolibarr'); ?></strong></p>
					<p><?php esc_html_e('Orders and customers will be synchronized from WooCommerce to Dolibarr in real-time when they are created or updated.', 'wc-dolibarr'); ?></p>
				</div>
			</div>
			
			<h3><?php esc_html_e('Customer Synchronization Settings', 'wc-dolibarr'); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e('Enable Customer Sync', 'wc-dolibarr'); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wc_dolibarr_sync_customers" value="1" <?php checked(get_option('wc_dolibarr_sync_customers', true)); ?> />
							<?php esc_html_e('Enable real-time customer synchronization to Dolibarr', 'wc-dolibarr'); ?>
						</label>
						<p class="description"><?php esc_html_e('Customers will be automatically synced to Dolibarr when they register or update their information. This includes new customer creation and profile updates. <strong>Note:</strong> Customers will also be synced when they place orders, regardless of this setting, to ensure order processing works correctly.', 'wc-dolibarr'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Default Customer Group', 'wc-dolibarr'); ?></th>
					<td>
						<input type="text" name="wc_dolibarr_default_customer_group" value="<?php echo esc_attr(get_option('wc_dolibarr_default_customer_group', '')); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e('Default customer group for new customers in Dolibarr', 'wc-dolibarr'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Customer Type Detection', 'wc-dolibarr'); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wc_dolibarr_auto_detect_customer_type" value="1" <?php checked(get_option('wc_dolibarr_auto_detect_customer_type', true)); ?> />
							<?php esc_html_e('Automatically detect customer type based on billing address', 'wc-dolibarr'); ?>
						</label>
						<p class="description"><?php esc_html_e('When enabled, customers with a company name in their billing address will be set as "Company" type, otherwise "Individual" type will be used.', 'wc-dolibarr'); ?></p>
					</td>
				</tr>
			</table>

			<h3><?php esc_html_e('Order Synchronization Settings', 'wc-dolibarr'); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e('Enable Order Sync', 'wc-dolibarr'); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wc_dolibarr_order_sync_enabled" value="1" <?php checked(get_option('wc_dolibarr_order_sync_enabled', true)); ?> />
							<?php esc_html_e('Enable real-time order synchronization to Dolibarr', 'wc-dolibarr'); ?>
						</label>
						<p class="description"><?php esc_html_e('Orders will be automatically synced to Dolibarr when they are placed or updated in WooCommerce. This includes new orders and order status updates. <strong>Note:</strong> When orders are synced, customer data will also be synced to Dolibarr to ensure proper order processing.', 'wc-dolibarr'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Order Status Updates', 'wc-dolibarr'); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wc_dolibarr_sync_order_status_updates" value="1" <?php checked(get_option('wc_dolibarr_sync_order_status_updates', true)); ?> />
							<?php esc_html_e('Sync order status changes to Dolibarr', 'wc-dolibarr'); ?>
						</label>
						<p class="description"><?php esc_html_e('Order status changes in WooCommerce will be reflected in Dolibarr in real-time.', 'wc-dolibarr'); ?></p>
					</td>
				</tr>
			</table>

			<h3><?php esc_html_e('Dolibarr Document Creation Settings', 'wc-dolibarr'); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e('Create Quotes', 'wc-dolibarr'); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wc_dolibarr_create_quotes" value="1" <?php checked(get_option('wc_dolibarr_create_quotes', false)); ?> />
							<?php esc_html_e('Create Quotation in Dolibarr for new orders', 'wc-dolibarr'); ?>
						</label>
						<p class="description"><?php esc_html_e('When enabled, a Quotation document will be created in Dolibarr for each new WooCommerce order.', 'wc-dolibarr'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Create Sales Orders', 'wc-dolibarr'); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wc_dolibarr_create_sales_orders" value="1" <?php checked(get_option('wc_dolibarr_create_sales_orders', true)); ?> />
							<?php esc_html_e('Create Sales Order in Dolibarr for new orders', 'wc-dolibarr'); ?>
						</label>
						<p class="description"><?php esc_html_e('When enabled, a Sales Order document will be created in Dolibarr for each new WooCommerce order.', 'wc-dolibarr'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Create Invoices', 'wc-dolibarr'); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wc_dolibarr_create_invoices" value="1" <?php checked(get_option('wc_dolibarr_create_invoices', false)); ?> />
							<?php esc_html_e('Create Invoice in Dolibarr for new orders', 'wc-dolibarr'); ?>
						</label>
						<p class="description"><?php esc_html_e('When enabled, a Sales Invoice document will be created in Dolibarr for each new WooCommerce order. This works independently of Sales Order creation.', 'wc-dolibarr'); ?></p>
					</td>
				</tr>
			</table>

			<h3><?php esc_html_e('Batch Sync Settings for Previous Data', 'wc-dolibarr'); ?></h3>
			<div class="wc-dolibarr-batch-sync-info">
				<div class="notice notice-warning">
					<p><strong><?php esc_html_e('Important:', 'wc-dolibarr'); ?></strong></p>
					<p><?php esc_html_e('These settings allow you to sync existing orders and customers that were created before the integration was set up. This process uses batch processing to handle large datasets efficiently.', 'wc-dolibarr'); ?></p>
				</div>
			</div>
			
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e('Sync Previous Orders', 'wc-dolibarr'); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wc_dolibarr_batch_sync_previous_orders" value="1" <?php checked(get_option('wc_dolibarr_batch_sync_previous_orders', false)); ?> />
							<?php esc_html_e('Enable batch sync for previous orders', 'wc-dolibarr'); ?>
						</label>
						<p class="description"><?php esc_html_e('When enabled, you can manually trigger a batch sync of existing orders to Dolibarr. This will process orders in batches to avoid timeouts.', 'wc-dolibarr'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Sync Previous Customers', 'wc-dolibarr'); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wc_dolibarr_batch_sync_previous_customers" value="1" <?php checked(get_option('wc_dolibarr_batch_sync_previous_customers', false)); ?> />
							<?php esc_html_e('Enable batch sync for previous customers', 'wc-dolibarr'); ?>
						</label>
						<p class="description"><?php esc_html_e('When enabled, you can manually trigger a batch sync of existing customers to Dolibarr. This will process customers in batches to avoid timeouts.', 'wc-dolibarr'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Order Sync Limit', 'wc-dolibarr'); ?></th>
					<td>
						<input type="number" name="wc_dolibarr_batch_sync_order_limit" value="<?php echo esc_attr(get_option('wc_dolibarr_batch_sync_order_limit', 1000)); ?>" min="100" max="10000" />
						<p class="description"><?php esc_html_e('Maximum number of orders to sync in a single batch operation (100-10000). Higher limits may take longer to process.', 'wc-dolibarr'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Customer Sync Limit', 'wc-dolibarr'); ?></th>
					<td>
						<input type="number" name="wc_dolibarr_batch_sync_customer_limit" value="<?php echo esc_attr(get_option('wc_dolibarr_batch_sync_customer_limit', 500)); ?>" min="50" max="5000" />
						<p class="description"><?php esc_html_e('Maximum number of customers to sync in a single batch operation (50-5000). Higher limits may take longer to process.', 'wc-dolibarr'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Order Statuses to Sync', 'wc-dolibarr'); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wc_dolibarr_batch_sync_all_order_statuses" value="1" <?php checked(get_option('wc_dolibarr_batch_sync_all_order_statuses', false)); ?> />
							<?php esc_html_e('Sync all order statuses (overrides selection below)', 'wc-dolibarr'); ?>
						</label>
						<br><br>
						<?php
						$selected_statuses = get_option('wc_dolibarr_batch_sync_order_statuses', array( 'wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed' ));
						// Ensure we have an array
						if (!is_array($selected_statuses)) {
							$selected_statuses = array( 'wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed' );
						}
						// Ensure all statuses are valid
						$valid_statuses    = array_keys(wc_get_order_statuses());
						$selected_statuses = array_intersect($selected_statuses, $valid_statuses);
						if (empty($selected_statuses)) {
							$selected_statuses = array( 'wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed' );
						}
						$order_statuses = wc_get_order_statuses();
						?>
						<select name="wc_dolibarr_batch_sync_order_statuses[]" multiple style="width: 400px; height: 120px;">
							<?php foreach ($order_statuses as $status_key => $status_label) : ?>
								<option value="<?php echo esc_attr($status_key); ?>" <?php echo in_array($status_key, $selected_statuses) ? 'selected' : ''; ?>>
									<?php echo esc_html($status_label); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e('Select which order statuses should be included in the batch sync. Hold Ctrl/Cmd to select multiple statuses. If "Sync all order statuses" is enabled above, this selection will be ignored.', 'wc-dolibarr'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Skip Existing Records', 'wc-dolibarr'); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wc_dolibarr_batch_sync_skip_existing" value="1" <?php checked(get_option('wc_dolibarr_batch_sync_skip_existing', true)); ?> />
							<?php esc_html_e('Skip orders and customers that already exist in Dolibarr', 'wc-dolibarr'); ?>
						</label>
						<p class="description"><?php esc_html_e('When enabled, the batch sync will skip records that have already been synced to Dolibarr, making the process faster and avoiding duplicates.', 'wc-dolibarr'); ?></p>
						<br>
						<label>
							<input type="checkbox" name="wc_dolibarr_batch_sync_debug_mode" value="1" <?php checked(get_option('wc_dolibarr_batch_sync_debug_mode', false)); ?> />
							<?php esc_html_e('Debug mode (temporarily disable skip existing check)', 'wc-dolibarr'); ?>
						</label>
						<p class="description"><?php esc_html_e('When enabled, the batch sync will include all orders regardless of sync status. Use this for testing.', 'wc-dolibarr'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Max Retry Attempts', 'wc-dolibarr'); ?></th>
					<td>
						<input type="number" name="wc_dolibarr_batch_sync_max_retries" value="<?php echo esc_attr(get_option('wc_dolibarr_batch_sync_max_retries', 3)); ?>" min="1" max="10" />
						<p class="description"><?php esc_html_e('Maximum number of retry attempts for failed syncs (1-10). Higher values increase reliability but may take longer.', 'wc-dolibarr'); ?></p>
					</td>
				</tr>
			</table>

			<div class="wc-dolibarr-batch-sync-actions" style="margin-top: 20px; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
				<div style="display: flex; justify-content: space-between; align-items: flex-start;">
					<div style="flex: 1;">
						<h3 style="margin: 0 0 8px 0; color: #23282d; font-size: 16px;">🔄 <?php esc_html_e('Batch Sync Operations', 'wc-dolibarr'); ?></h3>
						<p style="margin: 0; color: #666; font-size: 13px; line-height: 1.4;"><?php esc_html_e('Manually trigger batch sync operations for existing data. These operations use batch processing to handle large datasets efficiently.', 'wc-dolibarr'); ?></p>
					</div>
					<div style="display: flex; gap: 8px; align-items: center; flex-shrink: 0;">
						<button type="button" id="batch-sync-orders" class="button button-primary">
							<span class="dashicons dashicons-cart"></span>
							<?php esc_html_e('Sync Previous Orders', 'wc-dolibarr'); ?>
						</button>
						<button type="button" id="batch-sync-customers" class="button button-primary">
							<span class="dashicons dashicons-groups"></span>
							<?php esc_html_e('Sync Previous Customers', 'wc-dolibarr'); ?>
						</button>
					</div>
				</div>
				<div id="batch-sync-progress" style="margin-top: 15px; display: none;"></div>
			</div>

			
			<script type="text/javascript">
			jQuery(function($){
				var ajaxUrl = window.ajaxurl || '<?php echo esc_url( admin_url('admin-ajax.php') ); ?>';
				var $progress = $('#batch-sync-progress');

				function runSync(action, label) {
					$progress.show().html('<div class="notice notice-info"><p>' + label + ' started...</p></div>');
					$.post(ajaxUrl, {
						action: action,
						nonce: '<?php echo esc_js( wp_create_nonce('wc_dolibarr_nonce') ); ?>'
					})
					.done(function(resp){
						if (resp && resp.success) {
							var msg = (resp.data && resp.data.message) ? resp.data.message : (label + ' completed successfully.');
							$progress.html('<div class="notice notice-success"><p>' + msg + '</p></div>');
						} else {
							var err = (resp && resp.data) ? resp.data : 'Unknown error';
							$progress.html('<div class="notice notice-error"><p>' + err + '</p></div>');
						}
					})
					.fail(function(){
						$progress.html('<div class="notice notice-error"><p>Request failed. Please try again.</p></div>');
					});
				}

				$('#batch-sync-orders').on('click', function(){
					runSync('wc_dolibarr_sync_orders', 'Order sync');
				});
				$('#batch-sync-customers').on('click', function(){
					runSync('wc_dolibarr_sync_customers', 'Customer sync');
				});

				// Product & Inventory sync handlers (same functionality as Tools tab)
				var $piProgress = $('#product-inventory-sync-progress');
				function runPI(action, label) {
					$piProgress.show().html('<div class="notice notice-info"><p>' + label + ' started...</p></div>');
					$.post(ajaxUrl, {
						action: action,
						nonce: '<?php echo esc_js( wp_create_nonce('wc_dolibarr_nonce') ); ?>'
					})
					.done(function(resp){
						if (resp && resp.success) {
							var msg = (resp.data && resp.data.message) ? resp.data.message : (label + ' completed successfully.');
							$piProgress.html('<div class="notice notice-success"><p>' + msg + '</p></div>');
						} else {
							var err = (resp && resp.data) ? resp.data : 'Unknown error';
							$piProgress.html('<div class="notice notice-error"><p>' + err + '</p></div>');
						}
					})
					.fail(function(){
						$piProgress.html('<div class="notice notice-error"><p>Request failed. Please try again.</p></div>');
					});
				}

				$('#export-products').on('click', function(){
					runPI('wc_dolibarr_sync_products', 'Product export');
				});
				$('#import-products').on('click', function(){
					runPI('wc_dolibarr_sync_import_products', 'Product import');
				});
				$('#export-inventory').on('click', function(){
					runPI('wc_dolibarr_sync_inventory', 'Inventory export');
				});
				$('#import-inventory').on('click', function(){
					runPI('wc_dolibarr_sync_import_inventory', 'Inventory import');
				});
			});
			</script>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Render company tab
	 *
	 * @since 1.0.0
	 */
	private function render_product_inventory_tab() {
		?>
		<form method="post" action="options.php" style="margin-bottom:20px;">
			<?php settings_fields('wc_dolibarr_product_inventory_settings'); ?>

			<h3><?php esc_html_e('Automatic Product Synchronization Settings', 'wc-dolibarr'); ?></h3>
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e('Enable Automatic Product Sync', 'wc-dolibarr'); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wc_dolibarr_auto_product_sync_enabled" value="1" <?php checked(get_option('wc_dolibarr_auto_product_sync_enabled', false)); ?> />
								<?php esc_html_e('Enable automatic product synchronization from ERPNext (cron jobs)', 'wc-dolibarr'); ?>
							</label>
							<p class="description"><?php esc_html_e('This setting controls automatic/cron-based product sync. Manual sync buttons will work regardless of this setting.', 'wc-dolibarr'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Sync Interval', 'wc-dolibarr'); ?></th>
						<td>
							<?php $pi_interval = get_option('wc_dolibarr_auto_product_sync_interval', 'daily'); ?>
							<select name="wc_dolibarr_auto_product_sync_interval">
								<option value="hourly" <?php selected($pi_interval, 'hourly'); ?>><?php esc_html_e('Hourly', 'wc-dolibarr'); ?></option>
								<option value="twicedaily" <?php selected($pi_interval, 'twicedaily'); ?>><?php esc_html_e('Twice Daily', 'wc-dolibarr'); ?></option>
								<option value="daily" <?php selected($pi_interval, 'daily'); ?>><?php esc_html_e('Daily', 'wc-dolibarr'); ?></option>
								<option value="weekly" <?php selected($pi_interval, 'weekly'); ?>><?php esc_html_e('Weekly', 'wc-dolibarr'); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">&nbsp;</th>
						<td>
							<label style="display:block; margin-bottom:6px;">
								<input type="checkbox" name="wc_dolibarr_auto_product_sync_update_existing" value="1" <?php checked(get_option('wc_dolibarr_auto_product_sync_update_existing', true)); ?> />
								<strong><?php esc_html_e('Auto Update', 'wc-dolibarr'); ?></strong> <?php esc_html_e('Automatically update existing products', 'wc-dolibarr'); ?>
							</label>
							<label style="display:block; margin-bottom:6px;">
								<input type="checkbox" name="wc_dolibarr_auto_product_sync_create_categories" value="1" <?php checked(get_option('wc_dolibarr_auto_product_sync_create_categories', true)); ?> />
								<strong><?php esc_html_e('Create Categories', 'wc-dolibarr'); ?></strong> <?php esc_html_e('Automatically create product categories from ERPNext item groups', 'wc-dolibarr'); ?>
							</label>
							<label style="display:block;">
								<input type="checkbox" name="wc_dolibarr_auto_product_sync_skip_disabled" value="1" <?php checked(get_option('wc_dolibarr_auto_product_sync_skip_disabled', true)); ?> />
								<strong><?php esc_html_e('Skip Disabled Products', 'wc-dolibarr'); ?></strong> <?php esc_html_e('Skip products that are disabled in ERPNext', 'wc-dolibarr'); ?>
							</label>
						</td>
					</tr>
				</tbody>
			</table>

			<h3><?php esc_html_e('Batch Processing Settings', 'wc-dolibarr'); ?></h3>
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e('Use Batch Processing for Cron', 'wc-dolibarr'); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wc_dolibarr_auto_product_batch_enable" value="1" <?php checked(get_option('wc_dolibarr_auto_product_batch_enable', true)); ?> />
								<?php esc_html_e('Enable batch processing for automatic sync (recommended for large datasets)', 'wc-dolibarr'); ?>
							</label>
							<p class="description"><?php esc_html_e('Batch processing helps manage memory usage and prevents timeouts for large product catalogs.', 'wc-dolibarr'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Batch Size', 'wc-dolibarr'); ?></th>
						<td>
							<input type="number" min="10" max="200" name="wc_dolibarr_auto_product_batch_size" value="<?php echo esc_attr( (int) get_option('wc_dolibarr_auto_product_batch_size', 50) ); ?>" />
							<p class="description"><?php esc_html_e('Number of items to process in each batch (10-200). Lower values use less memory but take longer.', 'wc-dolibarr'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Max Execution Time (seconds)', 'wc-dolibarr'); ?></th>
						<td>
							<input type="number" min="60" max="1800" name="wc_dolibarr_auto_product_batch_max_time" value="<?php echo esc_attr( (int) get_option('wc_dolibarr_auto_product_batch_max_time', 300) ); ?>" />
							<p class="description"><?php esc_html_e("Maximum time to spend processing each batch (60-1800 seconds). Should be less than your server's PHP max_execution_time.", 'wc-dolibarr'); ?></p>
						</td>
					</tr>
				</tbody>
			</table>

			<h3><?php esc_html_e('Automatic Inventory & Price Synchronization Settings', 'wc-dolibarr'); ?></h3>
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e('Enable Automatic Inventory & Price Sync', 'wc-dolibarr'); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wc_dolibarr_auto_inventory_sync_enabled" value="1" <?php checked(get_option('wc_dolibarr_auto_inventory_sync_enabled', false)); ?> />
								<?php esc_html_e('Enable automatic inventory and price synchronization from ERPNext (cron jobs)', 'wc-dolibarr'); ?>
							</label>
							<p class="description"><?php esc_html_e('This setting controls automatic/cron-based inventory sync. Manual sync buttons will work regardless of this setting.', 'wc-dolibarr'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Sync Interval', 'wc-dolibarr'); ?></th>
						<td>
							<?php $inv_interval = get_option('wc_dolibarr_auto_inventory_sync_interval', 'hourly'); ?>
							<select name="wc_dolibarr_auto_inventory_sync_interval">
								<option value="hourly" <?php selected($inv_interval, 'hourly'); ?>><?php esc_html_e('Hourly', 'wc-dolibarr'); ?></option>
								<option value="twicedaily" <?php selected($inv_interval, 'twicedaily'); ?>><?php esc_html_e('Twice Daily', 'wc-dolibarr'); ?></option>
								<option value="daily" <?php selected($inv_interval, 'daily'); ?>><?php esc_html_e('Daily', 'wc-dolibarr'); ?></option>
							</select>
						</td>
					</tr>
				</tbody>
			</table>

			<?php submit_button(); ?>
		</form>

		<div class="postbox">
			<h2 class="hndle"><?php esc_html_e('Product & Inventory', 'wc-dolibarr'); ?></h2>
			<div class="inside">
				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e('Product Sync', 'wc-dolibarr'); ?></th>
							<td>
								<button type="button" id="export-products" class="button button-secondary">
									<?php esc_html_e('Export Products to Dolibarr', 'wc-dolibarr'); ?>
								</button>
								<button type="button" id="import-products" class="button button-primary">
									<?php esc_html_e('Import Products from Dolibarr', 'wc-dolibarr'); ?>
								</button>
								<p class="description"><?php esc_html_e('Export WooCommerce products to Dolibarr or Import Dolibarr products into WooCommerce.', 'wc-dolibarr'); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e('Inventory Sync', 'wc-dolibarr'); ?></th>
							<td>
								<button type="button" id="export-inventory" class="button button-secondary">
									<?php esc_html_e('Export Inventory to Dolibarr', 'wc-dolibarr'); ?>
								</button>
								<button type="button" id="import-inventory" class="button button-primary">
									<?php esc_html_e('Import Inventory from Dolibarr', 'wc-dolibarr'); ?>
								</button>
								<p class="description"><?php esc_html_e('Choose whether to export WooCommerce stock levels to Dolibarr, or import Dolibarr inventory into WooCommerce.', 'wc-dolibarr'); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
				<div id="product-inventory-sync-progress" style="margin-top: 20px;"></div>
			</div>
		</div>

		<script type="text/javascript">
		jQuery(function($){
			var ajaxUrl = window.ajaxurl || '<?php echo esc_url( admin_url('admin-ajax.php') ); ?>';
			var $progress = $('#product-inventory-sync-progress');
			function runPI(action, label) {
				$progress.show().html('<div class="notice notice-info"><p>' + label + ' started...</p></div>');
				$.post(ajaxUrl, {
					action: action,
					nonce: '<?php echo esc_js( wp_create_nonce('wc_dolibarr_nonce') ); ?>'
				})
				.done(function(resp){
					if (resp && resp.success) {
						var msg = (resp.data && resp.data.message) ? resp.data.message : (label + ' completed successfully.');
						$progress.html('<div class="notice notice-success"><p>' + msg + '</p></div>');
					} else {
						var err = (resp && resp.data) ? resp.data : 'Unknown error';
						$progress.html('<div class="notice notice-error"><p>' + err + '</p></div>');
					}
				})
				.fail(function(){
					$progress.html('<div class="notice notice-error"><p>Request failed. Please try again.</p></div>');
				});
			}
			$('#export-products').on('click', function(){ runPI('wc_dolibarr_sync_products', 'Product export'); });
			$('#import-products').on('click', function(){ runPI('wc_dolibarr_sync_import_products', 'Product import'); });
			$('#export-inventory').on('click', function(){ runPI('wc_dolibarr_sync_inventory', 'Inventory export'); });
			$('#import-inventory').on('click', function(){ runPI('wc_dolibarr_sync_import_inventory', 'Inventory import'); });
		});
		</script>
		<?php
	}
	
	private function render_company_tab() {
		?>
		<form method="post" action="options.php">
			<?php
			settings_fields('wc_dolibarr_company_settings');
			do_settings_sections('wc_dolibarr_company_settings');
			submit_button();
			?>
		</form>
		<?php
	}

	/**
	 * Render sync tab
	 *
	 * @since 1.0.0
	 */
	private function render_sync_tab() {
		?>
		<form method="post" action="options.php">
			<?php
			settings_fields('wc_dolibarr_sync_settings');
			do_settings_sections('wc_dolibarr_sync_settings');
			submit_button();
			?>
		</form>
		<?php
	}

	/**
	 * Render field mapping tab
	 *
	 * @since 1.0.0
	 */
	private function render_field_mapping_tab() {
		?>
		<div class="postbox">
			<h2 class="hndle"><?php esc_html_e('Field Mapping', 'wc-dolibarr'); ?></h2>
			<div class="inside">
				<p><?php esc_html_e('Configure how WooCommerce fields map to Dolibarr fields.', 'wc-dolibarr'); ?></p>
				<p><em><?php esc_html_e('This feature will be available in a future version.', 'wc-dolibarr'); ?></em></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render logs tab
	 *
	 * @since 1.0.0
	 */
	private function render_logs_tab() {
		$logger = new WC_Dolibarr_Logger();
		$logs = $logger->get_sync_logs(array( 'limit' => 50 ));
		?>
		<div class="postbox">
			<h2 class="hndle"><?php esc_html_e('Recent Sync Logs', 'wc-dolibarr'); ?></h2>
			<div class="inside">
				<?php if (empty($logs)) : ?>
					<p><?php esc_html_e('No sync logs found.', 'wc-dolibarr'); ?></p>
				<?php else : ?>
					<table class="widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e('Date', 'wc-dolibarr'); ?></th>
								<th><?php esc_html_e('Type', 'wc-dolibarr'); ?></th>
								<th><?php esc_html_e('WC ID', 'wc-dolibarr'); ?></th>
								<th><?php esc_html_e('Dolibarr ID', 'wc-dolibarr'); ?></th>
								<th><?php esc_html_e('Status', 'wc-dolibarr'); ?></th>
								<th><?php esc_html_e('Message', 'wc-dolibarr'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($logs as $log) : ?>
								<tr>
									<td><?php echo esc_html($log['created_at']); ?></td>
									<td><?php echo esc_html(ucfirst($log['sync_type'])); ?></td>
									<td><?php echo esc_html($log['wc_id']); ?></td>
									<td><?php echo esc_html($log['dolibarr_id'] ?: 'N/A'); ?></td>
									<td>
										<span class="status-<?php echo esc_attr($log['status']); ?>">
											<?php echo esc_html(ucfirst($log['status'])); ?>
										</span>
									</td>
									<td><?php echo esc_html($log['message']); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render tools tab
	 *
	 * @since 1.0.0
	 */
	private function render_tools_tab() {
		?>
		<div class="postbox">
			<h2 class="hndle"><?php esc_html_e('Bulk Sync Operations', 'wc-dolibarr'); ?></h2>
			<div class="inside">
				<p><?php esc_html_e('Use these tools to perform bulk synchronization operations.', 'wc-dolibarr'); ?></p>
				
				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e('Sync All Customers', 'wc-dolibarr'); ?></th>
							<td>
								<button type="button" id="sync-customers" class="button button-secondary">
									<?php esc_html_e('Sync Customers', 'wc-dolibarr'); ?>
								</button>
								<p class="description">
									<?php esc_html_e('Sync all WooCommerce customers to Dolibarr.', 'wc-dolibarr'); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e('Sync All Orders', 'wc-dolibarr'); ?></th>
							<td>
								<button type="button" id="sync-orders" class="button button-secondary">
									<?php esc_html_e('Sync Orders', 'wc-dolibarr'); ?>
								</button>
								<p class="description">
									<?php esc_html_e('Sync all WooCommerce orders to Dolibarr.', 'wc-dolibarr'); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e('Product Sync', 'wc-dolibarr'); ?></th>
							<td>
								<button type="button" id="export-products" class="button button-secondary">
									<?php esc_html_e('Export Products to Dolibarr', 'wc-dolibarr'); ?>
								</button>
								<button type="button" id="import-products" class="button button-primary">
									<?php esc_html_e('Import Products from Dolibarr', 'wc-dolibarr'); ?>
								</button>
								<p class="description">
									<?php esc_html_e('Export WooCommerce products to Dolibarr or Import Dolibarr products into WooCommerce.', 'wc-dolibarr'); ?>
								</p>
								<!-- <div id="sync-result"></div> -->
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Inventory Sync', 'wc-dolibarr' ); ?></th>
							<td>
								<!-- Export Inventory to Dolibarr -->
								<button type="button" id="export-inventory" class="button button-secondary">
									<?php esc_html_e( 'Export Inventory to Dolibarr', 'wc-dolibarr' ); ?>
								</button>

								<!-- Import Inventory from Dolibarr -->
								<button type="button" id="import-inventory" class="button button-primary">
									<?php esc_html_e( 'Import Inventory from Dolibarr', 'wc-dolibarr' ); ?>
								</button>

								<p class="description">
									<?php esc_html_e( 'Choose whether to export WooCommerce stock levels to Dolibarr, or import Dolibarr inventory into WooCommerce.', 'wc-dolibarr' ); ?>
								</p>
							</td>
						</tr>

					</tbody>
				</table>
				
				<div id="sync-result" style="margin-top: 20px;"></div>
			</div>
		</div>

		<div class="postbox">
			<h2 class="hndle"><?php esc_html_e('Maintenance Tools', 'wc-dolibarr'); ?></h2>
			<div class="inside">
				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e('Clear Logs', 'wc-dolibarr'); ?></th>
							<td>
								<button type="button" id="clear-logs" class="button button-secondary">
									<?php esc_html_e('Clear All Logs', 'wc-dolibarr'); ?>
								</button>
								<p class="description">
									<?php esc_html_e('Clear all synchronization logs.', 'wc-dolibarr'); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	// Callback functions for settings fields
	public function api_section_callback() {
		echo '<p>' . esc_html__('Configure your Dolibarr API connection settings.', 'wc-dolibarr') . '</p>';
	}

	public function api_url_callback() {
		$value = wc_dolibarr_get_option('api_url', '');
		echo '<input type="url" id="wc_dolibarr_api_url" name="wc_dolibarr_api_url" value="' . esc_attr($value) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html__('Your Dolibarr installation URL (e.g., https://yourdomain.com/dolibarr)', 'wc-dolibarr') . '</p>';
	}

	public function api_key_callback() {
		$value = wc_dolibarr_get_option('api_key', '');
		echo '<input type="password" id="wc_dolibarr_api_key" name="wc_dolibarr_api_key" value="' . esc_attr($value) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html__('Your Dolibarr API key generated from user settings.', 'wc-dolibarr') . '</p>';
	}

	public function ssl_verify_callback() {
		$value = wc_dolibarr_get_option('ssl_verify', true);
		echo '<input type="checkbox" id="wc_dolibarr_ssl_verify" name="wc_dolibarr_ssl_verify" value="1" ' . checked(1, $value, false) . ' />';
		echo '<label for="wc_dolibarr_ssl_verify">' . esc_html__('Verify SSL certificate', 'wc-dolibarr') . '</label>'; 
	}

	public function debug_mode_callback() {
		$value = wc_dolibarr_get_option('debug_mode', false);
		echo '<input type="checkbox" id="wc_dolibarr_debug_mode" name="wc_dolibarr_debug_mode" value="1" ' . checked(1, $value, false) . ' />';
		echo '<label for="wc_dolibarr_debug_mode">' . esc_html__('Enable debug logging', 'wc-dolibarr') . '</label>';
	}

	// Company settings callbacks
	public function company_section_callback() {
		echo '<p>' . esc_html__('Configure default company and operational settings.', 'wc-dolibarr') . '</p>';
	}

	private function default_customer_text_field() {
		$value = get_option('wc_dolibarr_default_customer', 'Guest');
		echo '<input type="text" id="wc_dolibarr_default_customer" name="wc_dolibarr_default_customer" value="' . esc_attr($value) . '" class="regular-text" placeholder="Guest" />';
	}

	public function default_warehouse_callback() {
		$value = wc_dolibarr_get_option('default_warehouse', '');
		$warehouses = $this->get_dolibarr_warehouses();
		
		echo '<select id="wc_dolibarr_default_warehouse" name="wc_dolibarr_default_warehouse">';
		echo '<option value="">' . esc_html__('Select a warehouse', 'wc-dolibarr') . '</option>';
		foreach ($warehouses as $warehouse) {
			echo '<option value="' . esc_attr($warehouse['id']) . '" ' . selected($warehouse['id'], $value, false) . '>';
			echo esc_html($warehouse['label'] . ' (' . $warehouse['ref'] . ')');
			echo '</option>';
		}
		echo '</select>';
	}

	public function default_payment_method_callback() {
		$value = wc_dolibarr_get_option('default_payment_method', '');
		$methods = $this->get_dolibarr_payment_methods();
		
		echo '<select id="wc_dolibarr_default_payment_method" name="wc_dolibarr_default_payment_method">';
		echo '<option value="">' . esc_html__('Select a payment method', 'wc-dolibarr') . '</option>';
		foreach ($methods as $method) {
			echo '<option value="' . esc_attr($method['id']) . '" ' . selected($method['id'], $value, false) . '>';
			echo esc_html($method['label'] . ' (' . $method['code'] . ')');
			echo '</option>';
		}
		echo '</select>';
	}

	public function default_bank_account_callback() {
		$value = wc_dolibarr_get_option('default_bank_account', '');
		$accounts = $this->get_dolibarr_bank_accounts();
		
		echo '<select id="wc_dolibarr_default_bank_account" name="wc_dolibarr_default_bank_account">';
		echo '<option value="">' . esc_html__('Select a bank account', 'wc-dolibarr') . '</option>';
		foreach ($accounts as $account) {
			echo '<option value="' . esc_attr($account['id']) . '" ' . selected($account['id'], $value, false) . '>';
			echo esc_html($account['label'] . ' (' . $account['ref'] . ')');
			echo '</option>';
		}
		echo '</select>';
	}

	public function currency_callback() {
		$value = wc_dolibarr_get_option('currency', get_woocommerce_currency());
		echo '<input type="text" id="wc_dolibarr_currency" name="wc_dolibarr_currency" value="' . esc_attr($value) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html__('Currency code (e.g., EUR, USD)', 'wc-dolibarr') . '</p>';
	}

	// Sync settings callbacks
	public function sync_section_callback() {
		echo '<p>' . esc_html__('Configure synchronization settings between WooCommerce and Dolibarr.', 'wc-dolibarr') . '</p>';
	}

	public function sync_customers_callback() {
		$value = wc_dolibarr_get_option('sync_customers', false);
		echo '<input type="checkbox" id="wc_dolibarr_sync_customers" name="wc_dolibarr_sync_customers" value="1" ' . checked(1, $value, false) . ' />';
		echo '<label for="wc_dolibarr_sync_customers">' . esc_html__('Enable customer synchronization', 'wc-dolibarr') . '</label>';
	}

	public function sync_orders_callback() {
		$value = wc_dolibarr_get_option('sync_orders', false);
		echo '<input type="checkbox" id="wc_dolibarr_sync_orders" name="wc_dolibarr_sync_orders" value="1" ' . checked(1, $value, false) . ' />';
		echo '<label for="wc_dolibarr_sync_orders">' . esc_html__('Enable order synchronization', 'wc-dolibarr') . '</label>';
	}

	public function sync_products_callback() {
		$value = wc_dolibarr_get_option('sync_products', false);
		echo '<input type="checkbox" id="wc_dolibarr_sync_products" name="wc_dolibarr_sync_products" value="1" ' . checked(1, $value, false) . ' />';
		echo '<label for="wc_dolibarr_sync_products">' . esc_html__('Enable product synchronization', 'wc-dolibarr') . '</label>';
	}

	public function sync_inventory_callback() {
		$value = wc_dolibarr_get_option('sync_inventory', false);
		echo '<input type="checkbox" id="wc_dolibarr_sync_inventory" name="wc_dolibarr_sync_inventory" value="1" ' . checked(1, $value, false) . ' />';
		echo '<label for="wc_dolibarr_sync_inventory">' . esc_html__('Enable inventory synchronization', 'wc-dolibarr') . '</label>';
	}

	public function inventory_sync_interval_callback() {
		$value = wc_dolibarr_get_option('inventory_sync_interval', 'hourly');
		$intervals = array(
			'hourly' => __('Hourly', 'wc-dolibarr'),
			'twicedaily' => __('Twice Daily', 'wc-dolibarr'),
			'daily' => __('Daily', 'wc-dolibarr'),
		);
		
		echo '<select id="wc_dolibarr_inventory_sync_interval" name="wc_dolibarr_inventory_sync_interval">';
		foreach ($intervals as $key => $label) {
			echo '<option value="' . esc_attr($key) . '" ' . selected($key, $value, false) . '>';
			echo esc_html($label);
			echo '</option>';
		}
		echo '</select>';
	}

	public function enable_tax_sync_callback() {
		$value = wc_dolibarr_get_option('enable_tax_sync', false);
		echo '<input type="checkbox" id="wc_dolibarr_enable_tax_sync" name="wc_dolibarr_enable_tax_sync" value="1" ' . checked(1, $value, false) . ' />';
		echo '<label for="wc_dolibarr_enable_tax_sync">' . esc_html__('Enable tax synchronization', 'wc-dolibarr') . '</label>';
	}

	// Helper methods
	private function get_dolibarr_warehouses() {
		if (!class_exists('WC_Dolibarr_API')) {
			return array();
		}

		$api = new WC_Dolibarr_API();
		if (!$api->is_configured()) {
			return array();
		}

		$warehouses = $api->get_warehouses();
		return is_wp_error($warehouses) ? array() : $warehouses;
	}

	private function get_dolibarr_payment_methods() {
		if (!class_exists('WC_Dolibarr_API')) {
			return array();
		}

		$api = new WC_Dolibarr_API();
		if (!$api->is_configured()) {
			return array();
		}

		$methods = $api->get_payment_methods();
		return is_wp_error($methods) ? array() : $methods;
	}

	private function get_dolibarr_bank_accounts() {
		if (!class_exists('WC_Dolibarr_API')) {
			return array();
		}

		$api = new WC_Dolibarr_API();
		if (!$api->is_configured()) {
			return array();
		}

		$accounts = $api->get_bank_accounts();
		return is_wp_error($accounts) ? array() : $accounts;
	}

	private function bank_account_dropdown( $option_name ) {
		$value = get_option($option_name, '');
		$accounts = $this->get_dolibarr_bank_accounts();
		if (!empty($accounts)) {
			echo '<select name="' . esc_attr($option_name) . '" id="' . esc_attr($option_name) . '">';
			echo '<option value="">' . esc_html__('Select a bank account', 'wc-dolibarr') . '</option>';
			foreach ($accounts as $account) {
				$acc_id = isset($account['id']) ? $account['id'] : '';
				$label = isset($account['label']) ? $account['label'] : '';
				$ref = isset($account['ref']) ? $account['ref'] : '';
				$display = trim($ref) !== '' ? ($label . ' (' . $ref . ')') : $label;
				echo '<option value="' . esc_attr($acc_id) . '" ' . selected($acc_id, $value, false) . '>' . esc_html($display) . '</option>';
			}
			echo '</select>';
		} else {
			echo '<input type="text" name="' . esc_attr($option_name) . '" id="' . esc_attr($option_name) . '" value="' . esc_attr($value) . '" class="regular-text" />';
		}
	}

	private function get_dolibarr_accounting_accounts() {
		if (!class_exists('WC_Dolibarr_API')) {
			return array();
		}

		$api = new WC_Dolibarr_API();
		if (!$api->is_configured()) {
			return array();
		}

		$accounts = $api->get_accounting_accounts();
		return is_wp_error($accounts) ? array() : $accounts;
	}

	private function accounting_account_dropdown( $option_name ) {
		$value = get_option($option_name, '');
		$accounts = $this->get_dolibarr_accounting_accounts();
		if (!empty($accounts)) {
			echo '<select name="' . esc_attr($option_name) . '" id="' . esc_attr($option_name) . '">';
			echo '<option value="">' . esc_html__('Select an account', 'wc-dolibarr') . '</option>';
			foreach ($accounts as $account) {
				$acc_id = isset($account['id']) ? $account['id'] : '';
				$code = isset($account['code']) ? $account['code'] : '';
				$label = isset($account['label']) ? $account['label'] : '';
				$display = trim($code) !== '' ? ($code . ' - ' . $label) : $label;
				echo '<option value="' . esc_attr($acc_id) . '" ' . selected($acc_id, $value, false) . '>' . esc_html($display) . '</option>';
			}
			echo '</select>';
		} else {
			echo '<input type="text" name="' . esc_attr($option_name) . '" id="' . esc_attr($option_name) . '" value="' . esc_attr($value) . '" class="regular-text" />';
		}
	}

	// AJAX handlers
	public function ajax_test_connection() {
		check_ajax_referer('wc_dolibarr_nonce', 'nonce');

		if (!current_user_can('manage_woocommerce')) {
			wp_die(__('Insufficient permissions.', 'wc-dolibarr'));
		}

		$api = new WC_Dolibarr_API();
		$result = $api->test_connection();

		if (is_wp_error($result)) {
			wp_send_json_error($result->get_error_message());
		} else {
			wp_send_json_success($result);
		}
	}

	public function ajax_sync_customers() {
		check_ajax_referer('wc_dolibarr_nonce', 'nonce');

		if (!current_user_can('manage_woocommerce')) {
			wp_die(__('Insufficient permissions.', 'wc-dolibarr'));
		}

		// Trigger customer sync
		if (class_exists('WC_Dolibarr_Customer_Sync')) {
			$customer_sync = new WC_Dolibarr_Customer_Sync();
			$result = $customer_sync->sync_all_customers();
			if (is_wp_error($result)) {
				wp_send_json_error($result->get_error_message());
			} else {
				wp_send_json_success(__('Customer sync completed successfully.', 'wc-dolibarr'));
			}
		} else {
			wp_send_json_error(__('Customer sync class not available.', 'wc-dolibarr'));
		}
	}

	public function ajax_sync_orders() {
		check_ajax_referer('wc_dolibarr_nonce', 'nonce');

		if (!current_user_can('manage_woocommerce')) {
			wp_die(__('Insufficient permissions.', 'wc-dolibarr'));
		}

		// Trigger order sync
		if (class_exists('WC_Dolibarr_Order_Sync')) {
			$order_sync = new WC_Dolibarr_Order_Sync();
			$result = $order_sync->sync_all_orders();
			
			if (is_wp_error($result)) {
				wp_send_json_error($result->get_error_message());
			} else {
				wp_send_json_success(__('Order sync completed successfully.', 'wc-dolibarr'));
			}
		} else {
			wp_send_json_error(__('Order sync class not available.', 'wc-dolibarr'));
		}
	}

	public function ajax_sync_products() {
		check_ajax_referer('wc_dolibarr_nonce', 'nonce');

		if (!current_user_can('manage_woocommerce')) {
			wp_die(__('Insufficient permissions.', 'wc-dolibarr'));
		}

		if (class_exists('WC_Dolibarr_Product_Sync')) {
			$product_sync = new WC_Dolibarr_Product_Sync();
			$result = $product_sync->export_all_products();
			if (is_wp_error($result)) {
				wp_send_json_error($result->get_error_message());
			} else {
				wp_send_json_success(__('Product export to Dolibarr completed successfully.', 'wc-dolibarr'));
			}
		} else {
			wp_send_json_error(__('Product sync class not available.', 'wc-dolibarr'));
		}
	}
	public function ajax_import_products() {
		check_ajax_referer('wc_dolibarr_nonce', 'nonce');

		if (!current_user_can('manage_woocommerce')) {
			wp_die(__('Insufficient permissions.', 'wc-dolibarr'));
		}

		if (class_exists('WC_Dolibarr_Product_Sync')) {
			$product_sync = new WC_Dolibarr_Product_Sync();
			$result = $product_sync->import_all_products();
			if (is_wp_error($result)) {
				wp_send_json_error($result->get_error_message());
			} else {
				wp_send_json_success(__('Products imported from Dolibarr successfully.', 'wc-dolibarr'));
			}
		} else {
			wp_send_json_error(__('Product import class not available.', 'wc-dolibarr'));
		}
	}

	/**
	 * Export inventory to Dolibarr
	 */
	public function ajax_sync_inventory() {
		check_ajax_referer('wc_dolibarr_nonce', 'nonce');

		if ( ! current_user_can('manage_woocommerce') ) {
			wp_die(__('Insufficient permissions.', 'wc-dolibarr'));
		}

		if ( class_exists('WC_Dolibarr_Product_Sync') ) {
			$product_sync = new WC_Dolibarr_Product_Sync();
			$result = $product_sync->export_inventory(); // Export logic

			if ( is_wp_error($result) ) {
				wp_send_json_error($result->get_error_message());
			} else {
				wp_send_json_success(__('Inventory exported to Dolibarr successfully.', 'wc-dolibarr'));
			}
		} else {
			wp_send_json_error(__('Product sync class not available.', 'wc-dolibarr'));
		}
	}

	/**
	 * Import inventory from Dolibarr
	 */
	public function ajax_import_inventory() {
		check_ajax_referer('wc_dolibarr_nonce', 'nonce');

		if ( ! current_user_can('manage_woocommerce') ) {
			wp_die(__('Insufficient permissions.', 'wc-dolibarr'));
		}

		if ( class_exists('WC_Dolibarr_Product_Sync') ) {
			$product_sync = new WC_Dolibarr_Product_Sync();
			$result = $product_sync->import_inventory(); // Import logic
			if ( is_wp_error($result) ) {
				wp_send_json_error($result->get_error_message());
			} else {
				wp_send_json_success(__('Inventory imported from Dolibarr successfully.', 'wc-dolibarr'));
			}
		} else {
			wp_send_json_error(__('Product sync class not available.', 'wc-dolibarr'));
		}
	}

	/**
	 * Batch sync previous orders
	 *
	 * @since 1.0.0
	 */
	public function batch_sync_previous_orders() {
		check_ajax_referer('wc_dolibarr_nonce', 'nonce');

		if (!current_user_can('manage_woocommerce')) {
			wp_die(__('Insufficient permissions.', 'wc-dolibarr'));
		}

		// Check if batch sync is enabled
		if (!get_option('wc_dolibarr_batch_sync_previous_orders', false)) {
			wp_send_json_error(__('Batch sync for previous orders is not enabled.', 'wc-dolibarr'));
		}

		// Get sync parameters
		$limit = get_option('wc_dolibarr_batch_sync_order_limit', 1000);
		$skip_existing = get_option('wc_dolibarr_batch_sync_skip_existing', true);
		$debug_mode = get_option('wc_dolibarr_batch_sync_debug_mode', false);
		$max_retries = get_option('wc_dolibarr_batch_sync_max_retries', 3);
		$all_statuses = get_option('wc_dolibarr_batch_sync_all_order_statuses', false);
		$selected_statuses = get_option('wc_dolibarr_batch_sync_order_statuses', array('wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed'));

		// Ensure we have an array of statuses
		if (!is_array($selected_statuses)) {
			$selected_statuses = array('wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed');
		}

		// Get order statuses to sync
		$statuses_to_sync = $all_statuses ? array_keys(wc_get_order_statuses()) : $selected_statuses;

		// Get orders to sync
		$args = array(
			'status' => $statuses_to_sync,
			'limit' => $limit,
			'orderby' => 'date',
			'order' => 'ASC',
		);

		$orders = wc_get_orders($args);
		$total_orders = count($orders);
		$synced_count = 0;
		$error_count = 0;
		$skipped_count = 0;

		// Initialize order sync class
		if (!class_exists('WC_Dolibarr_Order_Sync')) {
			wp_send_json_error(__('Order sync class not available.', 'wc-dolibarr'));
		}

		$order_sync = new WC_Dolibarr_Order_Sync();

		foreach ($orders as $order) {
			$order_id = $order->get_id();

			// Check if order already exists in Dolibarr (unless debug mode is enabled)
			if (!$debug_mode && $skip_existing) {
				global $wpdb;
				$table = $wpdb->prefix . 'wc_dolibarr_order_sync_history';
				$existing = $wpdb->get_var($wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE order_id = %d AND sync_status = 'success'",
					$order_id
				));

				if ($existing > 0) {
					$skipped_count++;
					continue;
				}
			}

			// Attempt to sync the order
			$retry_count = 0;
			$sync_success = false;

			while ($retry_count < $max_retries && !$sync_success) {
				$result = $order_sync->sync_order($order_id);
				
				if (is_wp_error($result)) {
					$retry_count++;
					if ($retry_count >= $max_retries) {
						$error_count++;
						break;
					}
					// Wait before retry
					sleep(1);
				} else {
					$sync_success = true;
					$synced_count++;
				}
			}
		}

		wp_send_json_success(array(
			'message' => sprintf(
				__('Batch sync completed. Total: %d, Synced: %d, Skipped: %d, Errors: %d', 'wc-dolibarr'),
				$total_orders,
				$synced_count,
				$skipped_count,
				$error_count
			),
			'total' => $total_orders,
			'synced' => $synced_count,
			'skipped' => $skipped_count,
			'errors' => $error_count,
		));
	}

	/**
	 * Batch sync previous customers
	 *
	 * @since 1.0.0
	 */
	public function batch_sync_previous_customers() {
		check_ajax_referer('wc_dolibarr_nonce', 'nonce');

		if (!current_user_can('manage_woocommerce')) {
			wp_die(__('Insufficient permissions.', 'wc-dolibarr'));
		}

		// Check if batch sync is enabled
		if (!get_option('wc_dolibarr_batch_sync_previous_customers', false)) {
			wp_send_json_error(__('Batch sync for previous customers is not enabled.', 'wc-dolibarr'));
		}

		// Get sync parameters
		$limit = get_option('wc_dolibarr_batch_sync_customer_limit', 500);
		$skip_existing = get_option('wc_dolibarr_batch_sync_skip_existing', true);
		$debug_mode = get_option('wc_dolibarr_batch_sync_debug_mode', false);
		$max_retries = get_option('wc_dolibarr_batch_sync_max_retries', 3);

		// Get customers to sync
		$args = array(
			'role' => 'customer',
			'number' => $limit,
			'orderby' => 'registered',
			'order' => 'ASC',
		);

		$customers = get_users($args);
		$total_customers = count($customers);
		$synced_count = 0;
		$error_count = 0;
		$skipped_count = 0;

		// Initialize customer sync class
		if (!class_exists('WC_Dolibarr_Customer_Sync')) {
			wp_send_json_error(__('Customer sync class not available.', 'wc-dolibarr'));
		}

		$customer_sync = new WC_Dolibarr_Customer_Sync();

		foreach ($customers as $customer) {
			$customer_id = $customer->ID;

			// Check if customer already exists in Dolibarr (unless debug mode is enabled)
			if (!$debug_mode && $skip_existing) {
				global $wpdb;
				$table = $wpdb->prefix . 'wc_dolibarr_sync_log';
				$existing = $wpdb->get_var($wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE wc_id = %d AND sync_type = 'customer' AND status = 'success'",
					$customer_id
				));

				if ($existing > 0) {
					$skipped_count++;
					continue;
				}
			}

			// Attempt to sync the customer
			$retry_count = 0;
			$sync_success = false;

			while ($retry_count < $max_retries && !$sync_success) {
				$result = $customer_sync->sync_customer($customer_id);
				
				if (is_wp_error($result)) {
					$retry_count++;
					if ($retry_count >= $max_retries) {
						$error_count++;
						break;
					}
					// Wait before retry
					sleep(1);
				} else {
					$sync_success = true;
					$synced_count++;
				}
			}
		}

		wp_send_json_success(array(
			'message' => sprintf(
				__('Batch sync completed. Total: %d, Synced: %d, Skipped: %d, Errors: %d', 'wc-dolibarr'),
				$total_customers,
				$synced_count,
				$skipped_count,
				$error_count
			),
			'total' => $total_customers,
			'synced' => $synced_count,
			'skipped' => $skipped_count,
			'errors' => $error_count,
		));
	}
}

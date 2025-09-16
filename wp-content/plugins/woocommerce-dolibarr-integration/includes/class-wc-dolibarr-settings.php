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
			'company' => __('Company Settings', 'wc-dolibarr'),
			'sync' => __('Sync Settings', 'wc-dolibarr'),
			'field_mapping' => __('Field Mapping', 'wc-dolibarr'),
			'logs' => __('Sync Logs', 'wc-dolibarr'),
			'tools' => __('Tools', 'wc-dolibarr'),
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
	private function init_company_settings() {
		register_setting('wc_dolibarr_company_settings', 'wc_dolibarr_default_warehouse');
		register_setting('wc_dolibarr_company_settings', 'wc_dolibarr_default_payment_method');
		register_setting('wc_dolibarr_company_settings', 'wc_dolibarr_default_bank_account');
		register_setting('wc_dolibarr_company_settings', 'wc_dolibarr_currency');

		add_settings_section(
			'wc_dolibarr_company_section',
			__('Company & Default Settings', 'wc-dolibarr'),
			array( $this, 'company_section_callback' ),
			'wc_dolibarr_company_settings'
		);

		add_settings_field(
			'wc_dolibarr_default_warehouse',
			__('Default Warehouse', 'wc-dolibarr'),
			array( $this, 'default_warehouse_callback' ),
			'wc_dolibarr_company_settings',
			'wc_dolibarr_company_section'
		);

		add_settings_field(
			'wc_dolibarr_default_payment_method',
			__('Default Payment Method', 'wc-dolibarr'),
			array( $this, 'default_payment_method_callback' ),
			'wc_dolibarr_company_settings',
			'wc_dolibarr_company_section'
		);

		add_settings_field(
			'wc_dolibarr_default_bank_account',
			__('Default Bank Account', 'wc-dolibarr'),
			array( $this, 'default_bank_account_callback' ),
			'wc_dolibarr_company_settings',
			'wc_dolibarr_company_section'
		);

		add_settings_field(
			'wc_dolibarr_currency',
			__('Currency', 'wc-dolibarr'),
			array( $this, 'currency_callback' ),
			'wc_dolibarr_company_settings',
			'wc_dolibarr_company_section'
		);
	}

	/**
	 * Initialize sync settings
	 *
	 * @since 1.0.0
	 */
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
					case 'company':
						$this->render_company_tab();
						break;
					case 'sync':
						$this->render_sync_tab();
						break;
					case 'field_mapping':
						$this->render_field_mapping_tab();
						break;
					case 'logs':
						$this->render_logs_tab();
						break;
					case 'tools':
						$this->render_tools_tab();
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
	 * Render company tab
	 *
	 * @since 1.0.0
	 */
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

}

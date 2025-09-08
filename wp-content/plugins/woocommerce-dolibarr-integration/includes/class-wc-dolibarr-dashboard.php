<?php
/**
 * Dolibarr Dashboard
 *
 * @package WC_Dolibarr_Integration
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class WC_Dolibarr_Dashboard {
	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action('wp_dashboard_setup', array( $this, 'add_dashboard_widgets' ));
	}

	/**
	 * Add dashboard widgets
	 *
	 * @since 1.0.0
	 */
	public function add_dashboard_widgets() {
		if (current_user_can('manage_woocommerce')) {
			wp_add_dashboard_widget(
				'wc_dolibarr_sync_status',
				__('Dolibarr Integration Status', 'wc-dolibarr'),
				array( $this, 'render_sync_status_widget' )
			);
		}
	}

	/**
	 * Render sync status widget
	 *
	 * @since 1.0.0
	 */
	public function render_sync_status_widget() {
		$connection_status = WC_Dolibarr_Connection_Validator::get_connection_status();
		
		echo '<div class="wc-dolibarr-dashboard-widget">';
		
		if ($connection_status['is_valid']) {
			echo '<p><span class="dashicons dashicons-yes-alt" style="color: green;"></span> ';
			echo esc_html__('Connected to Dolibarr', 'wc-dolibarr') . '</p>';
			
			if ($connection_status['version']) {
				echo '<p><small>' . sprintf(esc_html__('Version: %s', 'wc-dolibarr'), $connection_status['version']) . '</small></p>';
			}
		} else {
			echo '<p><span class="dashicons dashicons-warning" style="color: orange;"></span> ';
			echo esc_html__('Not connected to Dolibarr', 'wc-dolibarr') . '</p>';
			echo '<p><small>' . esc_html($connection_status['message']) . '</small></p>';
		}

		echo '<p><a href="' . esc_url(admin_url('admin.php?page=wc-dolibarr-settings')) . '" class="button button-primary">';
		echo esc_html__('Manage Integration', 'wc-dolibarr') . '</a></p>';
		
		echo '</div>';
	}
}

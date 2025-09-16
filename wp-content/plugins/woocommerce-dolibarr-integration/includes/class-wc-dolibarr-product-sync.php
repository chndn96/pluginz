<?php
/**
 * Dolibarr Product Sync (Import/Export)
 *
 * @package WC_Dolibarr_Integration
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class WC_Dolibarr_Product_Sync {
	private $api;
	private $logger;

	public function __construct() {
		$this->api = new WC_Dolibarr_API();
		$this->logger = new WC_Dolibarr_Logger();
		$this->init_hooks();
	}

	private function init_hooks() {
		add_action('woocommerce_product_set_stock', array($this, 'sync_product_stock'));
	}

	/**
	 * Sync WooCommerce product stock to Dolibarr
	 */
	public function sync_product_stock($product) {
		if (wc_dolibarr_get_option('sync_inventory', false)) {
			$this->export_inventory($product);
		}
	}

	/**
	 * Export all WooCommerce products to Dolibarr
	 */
	public function export_all_products() {
		if (!WC_Dolibarr_Connection_Validator::is_connection_valid()) {
			return new WP_Error('connection_invalid', __('Dolibarr connection is not valid.', 'wc-dolibarr'));
		}

		$args = array('status' => 'publish', 'limit' => -1);
		$products = wc_get_products($args);

		$total = count($products);
		$synced = 0;
		$errors = 0;
		foreach ($products as $product) {
			$data = $this->map_wc_to_dolibarr_product($product);
			$dolibarr_id = wc_dolibarr_get_product_dolibarr_id($product);
			if ($dolibarr_id) {
				$response = $this->api->update_product($dolibarr_id, $data);
			} else {
				$response = $this->api->create_product($data);
				if (!is_wp_error($response) && isset($response) && !empty($response)) {
					 $this->logger->log_sync('Product', $product->get_id(), $response, 'success', 'Product synced successfully to dolibarr ');
					update_post_meta($product->get_id(), '_dolibarr_product_id', (int) $response);
				}
			}

			if (is_wp_error($response)) {
				$errors++;
				$this->logger->log('Export error: ' . $response->get_error_message(), 'error');
			} else {
				$synced++;
			}
		}

		return compact('total', 'synced', 'errors');
	}

	/**
	 * Import all Dolibarr products into WooCommerce
	 */
	public function import_all_products() {
		if (!WC_Dolibarr_Connection_Validator::is_connection_valid()) {
			return new WP_Error('connection_invalid', __('Dolibarr connection is not valid.', 'wc-dolibarr'));
		}

		$response = $this->api->get_products();
		if (is_wp_error($response)) {
			return $response;
		}
		$total    = count($response);
		$imported = 0;
		$errors   = 0;

		foreach ($response as $dol_product) {

			$wc_product_id = $this->find_wc_product_by_dolibarr_id($dol_product['id']);

			if ($wc_product_id) {
				$wc_product = wc_get_product($wc_product_id);
				$result = $this->update_wc_product_from_dolibarr($wc_product, $dol_product);
			} else {
				$result = $this->create_wc_product_from_dolibarr($dol_product);
			}

			if (is_wp_error($result)) {
				$errors++;
			} else {
				$imported++;
			}
			}

			return array(
				'total'    => $total,
				'imported' => $imported,
				'errors'   => $errors,
				'message'  => sprintf(__('Imported %d/%d products with %d errors.', 'wc-dolibarr'), $imported, $total, $errors)
			);
    }

	/**
	 * Map WooCommerce product -> Dolibarr product
	 */
	public function map_wc_to_dolibarr_product($product) {
		return array(
			'ref' => $product->get_sku() ?: 'WC-' . $product->get_id(),
			'label' => $product->get_name(),
			'description' => $product->get_description(),
			'status' => $product->get_status() === 'publish' ? 1 : 0,
			'price' => $product->get_regular_price(),
			'tosell' => 1,
			'tobuy' => 1,
		);
	}

	/**
	 * Create WooCommerce product from Dolibarr
	 */
	public function create_wc_product_from_dolibarr($dol_product) {
	 	try {
			$product = new WC_Product();
			$product->set_name($dol_product['label'] ?? '');
			$product->set_sku($dol_product['ref'] ?? '');
			$product->set_regular_price(isset($dol_product['price']) ? floatval($dol_product['price']) : 0);
			$product->set_description($dol_product['description'] ?? '');
			$product_id = $product->save();
			if (isset($dol_product['stock_reel'])) {
				$product->set_manage_stock(true);
				$product->set_stock_quantity((int) $dol_product['stock_reel']);
			}
			if ($product_id) {
				update_post_meta($product_id, '_dolibarr_product_id', $dol_product['id']);
				$this->logger->log_sync('Product', $dol_product['id'], $product_id, 'success', 'Product synced successfully from dolibarr ');
					return true;
				} else {
					return new WP_Error('create_failed', __('Could not save WooCommerce product.', 'wc-dolibarr'));
				}

        } catch (Exception $e) {
            $this->logger->log("Failed to create WC product from Dolibarr ID {$dol_product['id']}: " . $e->getMessage(), 'error');
            return new WP_Error('create_failed', $e->getMessage());
        }
    }

	/**
	 * Update WooCommerce product from Dolibarr
	 */
	public function update_wc_product_from_dolibarr($wc_product, $dol_product) {
		try {
			$wc_product->set_name($dol_product['label'] ?? '');
			$wc_product->set_sku($dol_product['ref'] ?? '');
			$wc_product->set_regular_price(isset($dol_product['price']) ? floatval($dol_product['price']) : 0);
			$wc_product->set_description($dol_product['description'] ?? '');
				$wc_product->save();

				$this->logger->log_sync('Product', $dol_product['id'], $wc_product->get_id(), 'success', 'Product synced successfully from dolibarr ');
				return true;

        } catch (Exception $e) {
            $this->logger->log("Failed to update WC product from Dolibarr ID {$dol_product['id']}: " . $e->getMessage(), 'error');
            return new WP_Error('update_failed', $e->getMessage());
        }
    }

	/**
	 * Find WooCommerce product by Dolibarr ID
	 */
	public function find_wc_product_by_dolibarr_id($dolibarr_id) {
		$query = new WP_Query(array(
			'post_type' => 'product',
			'meta_query' => array(
				array(
					'key' => '_dolibarr_product_id',
					'value' => $dolibarr_id,
					'compare' => '=',
				),
			),
			'fields' => 'ids',
		));

		return !empty($query->posts) ? $query->posts[0] : false;
	}

	/**
	 * Sync single product inventory Woo → Doli
	 */
	public function sync_product_inventory($product) {
		$dolibarr_id = wc_dolibarr_get_product_dolibarr_id($product);

		if (!$dolibarr_id) {
			return false;
		}

		$this->logger->log(sprintf('Syncing inventory for WC product ID: %d', $product->get_id()), 'info');

		// TODO: push stock qty to Dolibarr
		return true;
	}
	public function export_inventory($product = '') {
		if ( ! WC_Dolibarr_Connection_Validator::is_connection_valid() ) {
			return new WP_Error('connection_invalid', __('Dolibarr connection is not valid.', 'wc-dolibarr'));
		}

		$products = [];
		if ($product) {
			// Fetch single product by ID if provided
			$single_product = wc_get_product($product);
			if ($single_product && $single_product->get_status() === 'publish') {
				$products = [$single_product];
			}
		} else {
			// Fetch all published products if no product ID is provided
			$args = array('status' => 'publish', 'limit' => -1);
			$products = wc_get_products($args);
		}
		$total  = count($products);
		$synced = 0;
		$errors = 0;
		foreach ($products as $product) {
			$dolibarr_id = (int) wc_dolibarr_get_product_dolibarr_id($product);

			if (! $dolibarr_id) {
				continue; // skip if not yet synced to Dolibarr
			}

			// Only stock data
			$quantity = (int) $product->get_stock_quantity();
			$warehouse_id =  get_option('wc_dolibarr_default_warehouse'); // or however you store it
			$price = (int) $product->get_price(); 
			$response = $this->api->update_product_inventory($dolibarr_id, $warehouse_id, $quantity, $price);
			if (is_wp_error($response) || empty($response)) {
				$errors++;
				$this->logger->log("Stock export error for product {$product->get_id()}: " . $response->get_error_message(), 'error');
			} else {
				$synced++;
				$this->logger->log(sprintf('Synced inventory successfully for product ID: %d', $response), 'info');
			}
		}

		return compact('total', 'synced', 'errors');
	}

	public function import_inventory() {
		if ( ! WC_Dolibarr_Connection_Validator::is_connection_valid() ) {
			return new WP_Error('connection_invalid', __('Dolibarr connection is not valid.', 'wc-dolibarr'));
		}

		$response = $this->api->get_products();
		if (is_wp_error($response)) {
			return $response;
		}

		$total    = count($response);
		$imported = 0;
		$errors   = 0;

		foreach ($response as $dol_product) {
			$wc_product_id = $this->find_wc_product_by_dolibarr_id($dol_product['id']);
			if (! $wc_product_id) {
				continue;
			}

			$wc_product = wc_get_product($wc_product_id);

			if (isset($dol_product['stock_reel'])) {
				try {
					$wc_product->set_manage_stock(true);
					$wc_product->set_stock_quantity((int) $dol_product['stock_reel']);
					$wc_product->save();
					$imported++;
				} catch (Exception $e) {
					$errors++;
					$this->logger->log("Stock import error for product {$wc_product_id}: " . $e->getMessage(), 'error');
				}
			}
		}

		return compact('total', 'imported', 'errors');
	}

	
}

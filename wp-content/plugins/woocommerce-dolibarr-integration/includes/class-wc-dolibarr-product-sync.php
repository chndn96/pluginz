<?php
/**
 * Dolibarr Product Sync
 *
 * @package WC_Dolibarr_Integration
 * @since 1.0.0
 */

// Prevent direct access
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
        // Sync stock when changed
        add_action('woocommerce_product_set_stock', [$this, 'sync_product_stock']);
    }

    /**
     * Sync a single product stock
     */
    public function sync_product_stock($product) {
        if (wc_dolibarr_get_option('sync_inventory', false)) {
            $this->sync_product_inventory($product);
        }
    }

    /**
     * Format product data for Dolibarr API
     */
    private function format_product_data($product) {
        return [
            'ref' => $product->get_sku(),
            'label' => $product->get_name(),
            'description' => $product->get_description(),
            'price' => $product->get_price(),
            'status' => 1,
        ];
    }

    /**
     * Sync a single product
     */
    public function sync_product($product) {
        $dolibarr_id = wc_dolibarr_get_product_dolibarr_id($product);
        $data = $this->format_product_data($product);

        try {
            if ($dolibarr_id) {
                $result = $this->api->update_product($dolibarr_id, $data);
                $action = 'updated';
            } else {
                $result = $this->api->create_product($data);
                $action = 'created';
                if (!is_wp_error($result) && isset($result['id'])) {
                    wc_dolibarr_set_product_dolibarr_id($product, $result['id']);
                }
            }

            if (is_wp_error($result)) {
                $this->logger->log("Product sync {$action} failed: ".$result->get_error_message(), 'error');
                return $result;
            }

            if (wc_dolibarr_get_option('sync_inventory', false)) {
                $this->sync_product_inventory($product);
            }

            $this->logger->log("Product {$action} successfully: ".$product->get_id(), 'info');

            return [
                'status' => 'success',
                'message' => "Product {$action} successfully",
                'dolibarr_id' => $result['id'] ?? $dolibarr_id,
                'action' => $action,
            ];

        } catch (Exception $e) {
            $error = "Product sync failed: ".$e->getMessage();
            $this->logger->log($error, 'error');
            return new WP_Error('sync_error', $error);
        }
    }

    /**
     * Sync all WooCommerce products
     */
    public function sync_all_products() {
        if (!WC_Dolibarr_Connection_Validator::is_connection_valid()) {
            return new WP_Error('connection_invalid', __('Dolibarr connection is not valid.', 'wc-dolibarr'));
        }

        $products = wc_get_products(['limit' => -1]);
        $results = [
            'total' => count($products),
            'synced' => 0,
            'errors' => 0,
            'details' => [],
        ];

        foreach ($products as $product) {
            $result = $this->sync_product($product);
            if (is_wp_error($result)) {
                $results['errors']++;
                $results['details'][] = [
                    'product_id' => $product->get_id(),
                    'status' => 'error',
                    'message' => $result->get_error_message(),
                ];
            } else {
                $results['synced']++;
                $results['details'][] = [
                    'product_id' => $product->get_id(),
                    'status' => $result['status'],
                    'message' => $result['message'],
                    'dolibarr_id' => $result['dolibarr_id'] ?? null,
                ];
            }
        }

        $this->logger->log(sprintf(
            'Product sync completed. Total: %d, Synced: %d, Errors: %d',
            $results['total'], $results['synced'], $results['errors']
        ), 'info');

        return $results;
    }

    /**
     * Sync inventory for a single product
     */
    private function sync_product_inventory($product) {
        $dolibarr_id = wc_dolibarr_get_product_dolibarr_id($product);
        if (!$dolibarr_id) return false;

        $stock = $product->get_stock_quantity();
        $warehouse_id = wc_dolibarr_get_option('default_warehouse_id');

        $result = $this->api->update_product_inventory($dolibarr_id, $warehouse_id, $stock);

        if (is_wp_error($result)) {
            $this->logger->log("Inventory sync failed for product {$product->get_id()}: ".$result->get_error_message(), 'error');
            return false;
        }

        $this->logger->log("Inventory synced for product {$product->get_id()}: {$stock}", 'info');
        return true;
    }
}


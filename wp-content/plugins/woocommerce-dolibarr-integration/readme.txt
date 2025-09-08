=== WooCommerce Dolibarr Integration ===
Contributors: techmarbles
Tags: woocommerce, dolibarr, erp, integration, sync
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
WC tested up to: 10.0.4

Complete integration between WooCommerce and Dolibarr ERP system for syncing customers, orders, products, and inventory.

== Description ==

WooCommerce Dolibarr Integration provides seamless synchronization between your WooCommerce store and Dolibarr ERP system. This powerful plugin helps you manage your business operations efficiently by keeping your e-commerce and ERP data in perfect sync.

### Key Features

* **Customer Synchronization**: Automatically sync customer data from WooCommerce to Dolibarr
* **Order Management**: Sync orders from WooCommerce to Dolibarr as Sales Orders
* **Product Sync**: Pull products from Dolibarr and sync them to WooCommerce
* **Inventory Management**: Real-time inventory synchronization with configurable cron jobs
* **Invoice Creation**: Automatically create Sales Invoices in Dolibarr for completed orders
* **Tax Integration**: Map WooCommerce taxes to Dolibarr tax templates
* **Warehouse Management**: Configure default warehouses for order fulfillment
* **Payment Method Mapping**: Map WooCommerce payment methods to Dolibarr payment types
* **Bank Account Integration**: Configure default bank accounts for payment processing
* **Bulk Operations**: Manual bulk  sync for customers, orders, and products
* **Comprehensive Logging**: Detailed sync logs with status tracking
* **Field Mapping**: Customize field mappings between WooCommerce and Dolibarr

### Sync Capabilities

**Customer Sync:**
- Customer details and contact information
- Billing and shipping addresses
- Company information for B2B customers
- Custom field mapping
- Guest customer handling

**Order Sync:**
- Order details and line items
- Customer information (existing or guest)
- Shipping and billing addresses
- Tax calculations and rates
- Payment method mapping
- Order status synchronization
- Shipping costs as service items

**Product Sync:**
- Product information from Dolibarr to WooCommerce
- Pricing from Dolibarr price lists
- Product categories and attributes
- Product images and descriptions
- Stock management settings
- SKU synchronization

**Inventory Sync:**
- Real-time stock levels from Dolibarr warehouses
- Configurable sync intervals (hourly, twice daily, daily)
- Low stock notifications
- Multi-warehouse support
- Stock status updates

### Advanced Features

* **API Connection Management**: Secure API connection with SSL verification options
* **Cron Job Management**: Automated synchronization with customizable intervals
* **Error Handling**: Robust error handling with detailed logging
* **Status Mapping**: Map WooCommerce order statuses to Dolibarr statuses
* **Connection Monitoring**: Automatic connection health checks
* **Cache Management**: Intelligent caching for improved performance
* **Settings Export/Import**: Easy configuration management
* **Cleanup Tools**: Automatic cleanup of old logs and sync data
* **Dashboard Integration**: Quick status overview in WordPress dashboard

### Based on Proven Technology

This plugin incorporates integration patterns and API handling techniques from our successful Shopify Dolibarr Integration app, ensuring reliable and efficient synchronization between your WooCommerce store and Dolibarr ERP system.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/woocommerce-dolibarr-integration` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Make sure WooCommerce is installed and activated
4. Go to WooCommerce > Dolibarr Integration to configure the plugin
5. Enter your Dolibarr API credentials and configure sync settings

### Requirements

* WordPress 5.0+
* WooCommerce 5.0+ (HPOS supported); tested up to WooCommerce 10.0.4
* PHP 7.4+ (tested up to PHP 8.3)
* Dolibarr instance with API access enabled
* SSL certificate recommended for secure API communication

### HPOS Compatibility

This plugin is fully compatible with WooCommerce's High-Performance Order Storage (HPOS) feature. HPOS is a new order storage system that improves performance and scalability for stores with large numbers of orders.

**HPOS Support:**
* ✅ Fully compatible with HPOS-enabled stores
* ✅ Works with traditional post-based order storage
* ✅ Automatic detection of HPOS status
* ✅ Seamless migration support
* ✅ No data loss during HPOS migration

**Benefits of HPOS:**
* Improved performance for large order volumes
* Better database scalability
* Reduced database load
* Faster order queries and operations

== Configuration ==

### API Settings
1. Enter your Dolibarr installation URL
2. Generate API Key in Dolibarr user settings
3. Test the connection to verify credentials

### Company Settings
1. Configure default warehouse for order fulfillment
2. Set default payment method for orders
3. Configure default bank account for payments
4. Set currency matching your WooCommerce store

### Sync Settings
1. Enable/disable sync options for customers, orders, products, and inventory
2. Configure inventory sync interval (hourly, twice daily, or daily)
3. Enable tax synchronization for accurate financial reporting

### Dolibarr API Setup
1. Log into your Dolibarr admin panel
2. Go to Setup → Users & Groups → Users
3. Edit your user or create a dedicated API user
4. Generate an API key in the user settings
5. Ensure the user has appropriate permissions for the modules you want to sync

== Frequently Asked Questions ==

= Does this plugin work with all versions of Dolibarr? =

This plugin is compatible with Dolibarr v13 and later versions. It uses the standard Dolibarr REST API which is available in modern Dolibarr installations.

= Can I sync existing data? =

Yes, the plugin provides bulk sync options for customers, orders, and products. You can manually trigger sync operations from the settings page to sync your existing data.

= How often does inventory sync run? =

Inventory sync can be configured to run hourly, twice daily, or daily based on your needs. You can also trigger manual sync operations at any time.

= What happens if there's a sync error? =

All sync operations are logged with detailed error messages. Failed syncs can be retried, and you'll receive notifications about any issues. The plugin includes robust error handling to prevent data corruption.

= Can I customize field mappings? =

The plugin includes intelligent field mapping between WooCommerce and Dolibarr. Advanced field mapping customization will be available in future versions.

= Is the connection secure? =

Yes, all API communications use HTTPS with API key authentication. SSL verification can be enabled for additional security. The plugin follows WordPress security best practices.

= Does it support multi-warehouse setups? =

Yes, you can configure a default warehouse for order fulfillment, and the plugin supports Dolibarr's multi-warehouse functionality for inventory management.

= Can it handle guest customers? =

Yes, the plugin can create customer records in Dolibarr for guest checkout orders, ensuring all order data is properly synchronized.

== Screenshots ==

1. API Settings configuration page with connection testing
2. Company and default settings for warehouses and payment methods
3. Sync settings with enable/disable options for different data types
4. Comprehensive sync logs with status tracking
5. Bulk sync operations and maintenance tools
6. Dashboard widget showing connection status
7. Order synchronization in action with detailed logging

== Changelog ==

= 1.0.0 =
* Initial release
* Customer synchronization from WooCommerce to Dolibarr
* Order synchronization with Sales Order creation in Dolibarr
* Product synchronization from Dolibarr to WooCommerce (framework)
* Inventory synchronization with configurable cron jobs
* Sales Invoice creation for completed orders
* Comprehensive logging and error handling
* Warehouse, payment method, and bank account configuration
* Tax synchronization support
* Bulk sync operations for customers and orders
* Connection monitoring and health checks
* HPOS (High-Performance Order Storage) compatibility
* Dashboard integration with status widgets
* SSL verification and secure API communication
* Cache management for improved performance
* Guest customer handling for orders without registered users

== Upgrade Notice ==

= 1.0.0 =
Initial release of WooCommerce Dolibarr Integration plugin. This plugin brings proven integration patterns from our Shopify Dolibarr Integration app to the WooCommerce ecosystem.

== Support ==

For support, feature requests, or bug reports, please contact Techmarbles via our website.

### Documentation

Detailed documentation is available at: https://techmarbles.com/products/docs/woocommerce-dolibarr-integration/

### Contributing

This plugin is open source and contributions are welcome. The codebase follows WordPress coding standards and includes comprehensive error handling and logging.

### Professional Services

Need help with setup, customization, or integration with your specific Dolibarr configuration? Techmarbles offers professional services for WooCommerce and Dolibarr integrations.

== License ==

This plugin is free software, licensed under the GPL-2.0-or-later. You can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation. See the License URI above for details. © Techmarbles.

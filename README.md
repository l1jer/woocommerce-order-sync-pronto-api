=== WooCommerce Order Sync Pronto API ===
Contributors: Jerry Li
Tags: woocommerce, order sync, API, pronto
Requires at least: 5.0
Tested up to: 6.5.3
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sync WooCommerce orders with the Pronto API automatically upon successful processing. Includes a manual sync button, sync logs, status page, and automatic status checks.

== Description ==

The WooCommerce Order Sync Pronto API plugin automatically syncs WooCommerce orders with the Pronto API upon successful processing. The plugin includes features like a manual sync button, order status sync logs, a sync status page, and automatic status checks to retrieve Pronto Order numbers.

### Key Features:
- Automatic order syncing upon processing or completion.
- Manual sync button for orders in the WooCommerce admin.
- Sync status logging, including Pronto Order numbers.
- Sync logs management, including a "Clear Sync Records" feature.
- Automatic periodic status checks to retrieve Pronto Order numbers.
- New column in the WooCommerce Orders admin page to display Pronto Order numbers.

== Installation ==

1. Upload the `wcospa` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Configure your API credentials in the `wcospa-credentials.php` file located in `includes/`.

== Changelog ==
= 1.3.1 =

- **Enhancement:** Updated the "Pronto Order No." column to display `"-"` when no data is available and `"Pending"` only if the sync is in progress.
- **Enhancement:** The sync button now immediately changes to `"Already Synced"` once the sync process starts, instead of showing `"Pending"`.
- **Enhancement:** Updated the `customer_reference` in the API request to be formatted as `"order number / shipping last name"`.
- **Improvement:** General code refactoring for better performance and readability.

= 1.3.0

- **Feature:** Added a cron job to automatically check the status of synced orders every minute for up to 10 minutes.
- **Feature:** New column "Pronto Order No." added to the WooCommerce Orders admin page, displaying the Pronto Order number once retrieved.
- **Enhancement:** The sync button now shows "Pending" after syncing and stores the transaction UUID with the order.
- **Enhancement:** The "Pronto Order No." column will show "Not Synced Yet" if an order has not been synced, or "Pending" if the sync is in progress.
- **Enhancement:** Improved handling of pending orders by automatically retrieving and updating the Pronto Order number once the transaction is complete.
- **Enhancement:** Added robust error handling and logging throughout the sync process.

= 1.2.1 =

- **Bug Fix:** Resolved a `403 Forbidden` error when clicking the "Sync" button due to incorrect nonce verification.
- **Enhancement:** Improved nonce handling for AJAX requests to prevent unauthorized access errors.
- **Enhancement:** Added visual feedback for successful sync operations in the WooCommerce admin order actions.

= 1.2.0 =

- **Feature:** Added a "Sync Status" page under the WooCommerce menu.
  - Displays all sync logs, including order details, sync date/time, and Pronto Order number.
  - Includes a "Clear Sync Records" button to delete all sync logs with a confirmation prompt.
- **Enhancement:** Automatically retrieves and logs the Pronto Order number after a successful sync using the API.
- **Enhancement:** Improved the logging system to store sync events, including transaction UUID and Pronto Order number.

= 1.1.0 =

- **Feature:** Updated the sync button in WooCommerce admin:
  - The button is disabled and greyed out for orders that are not in "processing" or "completed" status.
  - Added a tooltip "Unable to sync Cancelled and On-hold orders" when hovering over the disabled button.
  - The sync button is disabled after an order is successfully synced, displaying "Already Synced".
- **Enhancement:** Improved the API request process:
  - After syncing an order, the API returns a transaction UUID.
  - The plugin fetches the Pronto Order number using the UUID and logs it for future reference.

= 1.0.0 =

- **Initial Release:**
  - Automatically syncs WooCommerce orders with the Pronto API upon successful processing.
  - Added a manual sync button to the WooCommerce admin order actions.
  - Logging for successful API requests, including order details and sync timestamps.

== Frequently Asked Questions ==

= How do I set up the plugin? =
1. After installing and activating the plugin, you need to configure your API credentials by editing the `wcospa-credentials.php` file located in the `includes/` directory.

= Can I manually sync an order? =
Yes, you can manually sync an order by clicking the "Sync" button in the WooCommerce admin order actions. After syncing, the button will display "Pending" until the Pronto Order number is retrieved.

= What happens if an order is already synced? =
Once an order is synced, the "Sync" button will be disabled and display "Already Synced" along with the Pronto Order number.

= What does the "Clear Sync Records" button do? =
The "Clear Sync Records" button on the Sync Status page will permanently delete all sync logs. This action cannot be undone.

= How does the plugin handle pending orders? =
If the transaction status is "Pending", the plugin will automatically check the status every minute for up to 10 minutes. Once the status changes to "Complete", the Pronto Order number will be retrieved and displayed.

== Upgrade Notice ==

= 1.3.0 =
- Major update introducing automatic status checks and Pronto Order number display in the WooCommerce Orders admin page.

= 1.2.1 =
- Recommended update for improved security and bug fixes related to the sync button functionality.

= 1.2.0 =
- Major update with new features: sync status logging and the Sync Status page.

= 1.1.0 =
- Update to improve the sync process and button functionality.

== License ==

This plugin is licensed under the GPLv2 or later. For more information, see https://www.gnu.org/licenses/gpl-2.0.html.



wp-content/plugins/wcospa
│
├── includes
│   ├── class-wcospa-order-handler.php
│   ├── class-wcospa-api-client.php
│   ├── class-wcospa-order-sync-button.php
│   ├── class-wcospa-order-data-formatter.php
│   └── wcospa-credentials.php
│       └── wcospa-credentials-sample.php
│
├── assets
│   └── js
│       └── wcospa-sync-button.js
│
├── wcospa.php
└── uninstall.php


This is the plugin code, please review, analyse and update the code to meet the following criteria:
1. debtor code needs to be 210942 **DONE**
2. status_code is 30 when order is processing **DONE but not working**
3. status_code is 80 when order is completed **DONE but not working**
4. add sync logs, inc page and GET Pronto order number **DONE but not working**
5. customer_reference is "order number / shipping last name", e.g. 19763 / VAN VUUREN
6.             "delivery_instructions": {
                "del_inst_1": "No invoice&packing slip",
                "del_inst_2": "franzwa@brisbanewestre.com",
                "del_inst_3": "",
5a. add uppercase "No invoice & packing slip" to del_inst_1
5b. Add customer email from shipping (if different to billing email) to del_inst_2
5c. if there is any Order Notes, add this to "delivery_instructions" -> "del_inst_3", limit to 30 characters only; if nothing in Order Notes, then leave it empty
1. remove price_ex_tax
2. need a calculation each product price from woocommerce need to divided by 1.1, this is the price needs to send to API
3. in the following part:
       'delivery_address' => [
            'address1' => $shipping_address['address_1'],
            'address2' => $shipping_address['address_2'],
            'address3' => '',
            'address4' => $shipping_address['city'],
            'address5' => $shipping_address['state'],
            'address6' => $shipping_address['country'],
            'address7' => '',
then
address1 should go with customer first name and last name, capitalised
address2 is $shipping_address['address_1']
address3 is $shipping_address['address_2']
=== WooCommerce Order Sync Pronto API ===
Contributors: Jerry Li
Tags: woocommerce, order sync, API, pronto
Requires at least: 5.0
Tested up to: 6.5.3
Stable tag: 1.3.2.3
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
= 1.4.4 =

- **Improvement:** Updated `format_order_items` method to calculate `price_ex_tax` per product item, storing it in each product’s `price_ex_tax` field by dividing the `price_inc_tax` by 1.1.
- **Improvement:** Updated `amount` field in `format_order` method to use the sum of all `price_inc_tax` values across products, ensuring the correct total amount in `payment`.
- **Improvement:** Moved discount note entry in the `lines` array to follow all product items, clarifying line item sequence.
- **Improvement:** Replaced direct `get_meta('_shipping_company')` calls with WooCommerce's recommended `get_shipping_company()` method to avoid internal meta key usage errors and align with WooCommerce’s best practices.
- **Improvement:** `price_inc_tax_per_item` and `price_ex_tax_per_item` are now calculated based on each product's single-unit price, unaffected by product quantity.
- **Improvement:** `total_price_inc_tax` now accumulates all `price_inc_tax` values for accurate final payment calculation.

= 1.4.3 =

- **Improvement:** When order has coupon code or any product on sale, add a note item in order indicates the order has coupon.

= 1.4.2 =

- **Improvement:** Include the bank_code in the payment section of the API when submitting an order to meet Pronto transaction requirements, as this indicates the payment method for each transaction. Without it, the accountant won’t be able to identify the appropriate payment method for each transaction.

= 1.4.1 =

- **Feature:** Added a new custom order status `Pronto Received` (`wc-pronto-received`), which tracks orders that have been successfully synced with the Pronto API.
- **Improvement:** Updated delivery instructions for more accurate and clearer formatting.
- **Improvement:** Amended the delivery address structure
- **Improvement:** The "Fetch" button in the admin order page has been hidden.

= 1.4.0 =

- **Feature:** Automatically sync orders upon successful processing in WooCommerce without requiring manual actions.
- **Improvement:** Remove Sync button from the WooCommerce order admin.
- **Improvement:**Remove Fetch button countdown timer.
- **Improvement:** Delivery address formatting updated to capitalize customer names.
- **Improvement:** Delivery instructions include customer email and order notes (if available) in the API request.
- **Improvement:** Remove manual sync log display; logs are now stored directly in `debug.log` for simplicity.
- **Bug Fix:** Pronto Order number fetched and displayed automatically after syncing.

= 1.3.2.5 =

- **Bug Fix:** Resolved an error where the Fetch button was throwing a JSON parsing error due to HTML responses returned by the API. Now properly handles and logs invalid JSON or HTML responses.

= 1.3.2.4 =

- **Bug Fix:** Fixed an issue where the Transaction UUID was not correctly extracted from the sync response, even though the UUID was present.
- **Improvement:** Added `try-catch` blocks around `JSON.parse()` in the JavaScript to prevent crashes when the API response is not valid JSON.
- **Improvement:** Added logging for raw API responses to the debug log to capture the full body of responses, including potential HTML redirects.
- **Improvement:** Updated the countdown timer logic for the Fetch button to handle page reloads and ensure consistent behavior when the timer is active.

= 1.3.2.3 =

- **Change:** Commented out all CRON job-related code for checking transactions and Pronto Order numbers.
- **Feature:** Added a "Fetch" button next to the "Sync" button in the WooCommerce orders list.
  - The "Fetch" button retrieves the Pronto Order number using the transaction UUID after sync.
  - The "Fetch" button is activated 2 minutes after the "Sync" button is clicked, with a countdown timer displayed using AJAX.
- **Improvement:** Placed "Sync" and "Fetch" buttons on a new line, separated from other order action buttons for better clarity.

= 1.3.2.2 =

- **Change:** Removed `price_ex_tax` from the API request.
- **Change:** Updated price calculation for API: product prices are now divided by `1.1` before being sent, rounded to 2 decimal places.
- **Change:** Updated delivery address formatting:
  - `address1` now includes the customer's first and last name, capitalized.
  - `address2` corresponds to the original `address_1`.
  - `address3` corresponds to the original `address_2`.

= 1.3.2.1 =

- **Feature:** Updated delivery instructions handling:
  - `del_inst_1` now includes "NO INVOICE & PACKING SLIP" in uppercase.
  - `del_inst_2` adds the customer email from shipping if different from the billing email.
  - `del_inst_3` includes the first 30 characters of the Order Notes, if available.
- **Feature:** Pronto Order No. field now displays `"-"` until the Sync button is clicked, and `"Pending"` after the button is clicked until the Pronto order number is fetched.
- **Enhancement:** Sync button now displays `"Syncing..."` while the sync is in progress and updates the Pronto Order No. field accordingly.
- **Improvement:** General code refactoring and enhancements for better performance and usability.
- **Improvement:** The Sync button's JavaScript has been rewritten using plain JavaScript (Vanilla JS) instead of jQuery.

= 1.3.2 =

- **Feature:** Added a new "Clear All Sync Data" button to the Sync Status page.
  - Clicking this button will reset the sync status for all orders, allowing them to be re-synced.
  - All related metadata such as the transaction UUID, Pronto Order number, and sync status will be cleared.
- **Improvement:** General code refactoring and enhancements for better performance and usability.

= 1.3.1 =

- **Enhancement:** Updated the "Pronto Order No." column to display `"-"` when no data is available and `"Pending"` only if the sync is in progress.
- **Enhancement:** The sync button now immediately changes to `"Already Synced"` once the sync process starts, instead of showing `"Pending"`.
- **Enhancement:** Updated the `customer_reference` in the API request to be formatted as `"order number / shipping last name"`.
- **Improvement:** General code refactoring for better performance and readability.

= 1.3.0 =

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
After installing and activating the plugin, you need to configure your API credentials by editing the `wcospa-credentials.php` file located in the `includes/` directory.

= Can I manually sync an order? =
Yes, you can manually sync an order by clicking the "Sync" button in the WooCommerce admin order actions. After syncing, the button will display "Pending" until the Pronto Order number is retrieved.

= What happens if an order is already synced? =
Once an order is synced, the "Sync" button will be disabled and display "Already Synced" along with the Pronto Order number.

= How does the plugin handle pending orders? =
If the transaction status is "Pending", the plugin will automatically check the status every minute for up to 10 minutes. Once the status changes to "Complete", the Pronto Order number will be retrieved and displayed.

= Order Processing Workflow =

1. **Order Sync Initiation**

   - Triggered by `handle_order_sync()` method
   - Sync request sent to Pronto API
   - On success, order status updated to 'Pronto Received'
   - Scheduled task created to fetch Pronto order number after 120 seconds

2. **Pronto Order Number Retrieval**

   - Scheduled task `scheduled_fetch_pronto_order()` executes
   - Checks for existing Pronto order number in meta data
   - If no number exists, fetches from API
   - Stores number using: `update_post_meta($order_id, '_wcospa_pronto_order_number', $pronto_order_number)`

3. **Order Meta Data Storage**
   - Pronto order number stored with key: `_wcospa_pronto_order_number`
   - Visible in WooCommerce order admin interface
   - Displayed in custom column in orders list

== License ==

This plugin is licensed under the GPLv2 or later. For more information, see https://www.gnu.org/licenses/gpl-2.0.html.

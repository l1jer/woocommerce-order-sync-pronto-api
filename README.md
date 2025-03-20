### WooCommerce Order Sync Pronto API Plugin

This plugin automatically syncs WooCommerce orders with the Pronto API upon successful processing. The plugin includes features like a manual sync button, order status sync logs, a sync status page, and automatic status checks to retrieve Pronto Order numbers.

### Installation

1. Upload the `wcospa` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Configure your API credentials in the `wcospa-credentials.php` file located in `includes/`.

### Configuration

#### API Credentials

Create a `wcospa-credentials.php` file in the `includes/` directory by copying the sample file and updating the values:

```php
<?php
class WCOSPA_Credentials {
    public static function get_api_credentials() {
        return [
            'api_url' => 'https://your-api-url.com/api/json/order/v6.json/',
            'username' => 'your-username',
            'password' => 'your-password'
        ];
    }
}
```

#### Debtor Code Configuration

The plugin automatically selects the appropriate debtor code based on the website domain:

- example1.com.au: SITE1_DEBTOR_CODE
- example2.com.au: SITE2_DEBTOR_CODE
- shop.example3.com: SITE3_DEBTOR_CODE

For development/testing environments, you can:

1. Define a custom debtor code for testing in wp-config.php:
   ```php
   define('WCOSPA_DEV_DEBTOR_CODE', 'YOUR_TEST_CODE'); // Replace with your test debtor code
   ```

2. If no custom code is defined, the plugin will use the default test code 'DEFAULT_DEV_CODE' for any development environment.

3. Development environments are automatically detected for domains containing:
   - localhost
   - 127.0.0.1
   - .test, .local, .dev
   - dev.
   - Any staging domain pattern (staging, staging2, staging10, etc.)

### API Environment Selection

The plugin provides the ability to choose which API environment (Test or Production) to use on a per-order basis:

1. **Per-Order Environment Selection**:
   - When viewing an order in the WooCommerce admin, you'll see a "Pronto API Environment" section
   - Select either "Production Environment" or "Test Environment" 
   - Click "Save Environment Setting" to apply the change
   - A confirmation message will appear when the setting is saved

2. **Environment Persistence**:
   - The selected environment is stored with the order and persists between page loads
   - Each order can have its own environment setting independent of other orders
   - This allows testing with specific orders without affecting the entire system

3. **Visual Indicators**:
   - The system logs which environment is being used for each API operation
   - Check the WooCommerce logs for detailed information about which environment was used

4. **Global Default**:
   - If no per-order setting is specified, the system will use the global setting
   - Define `WCOSPA_TEST_MODE` as true in wp-config.php to use test mode globally
   - Example: `define('WCOSPA_TEST_MODE', true);`

### Order Processing Workflow

1. **Order Sync Initiation**
   - Triggered by `handle_order_sync()` method
   - Sync request sent to Pronto API
   - On success, order status updated to 'Preparing to Ship'
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

### Key Configurations

#### Time Intervals and Limits

The plugin operates with the following configured time intervals:

- Initial Wait Period: 120 seconds before first Pronto order number fetch
- Retry Interval: 30 seconds between retry attempts
- Request Delay: 3 seconds between different orders
- Maximum Retry Count: 5 attempts
- Cron Job Interval: Every 60 seconds for pending orders processing

#### Order Status Management

The following order statuses are excluded from processing:

- Shipped
- Delivered
- Cancelled
- On Hold
- Completed
- Refunded
- Failed

#### Meta Data Fields

The plugin utilises these meta fields for order tracking:

- `_wcospa_transaction_uuid`: Transaction identifier
- `_wcospa_sync_time`: Synchronisation timestamp
- `_wcospa_fetch_retry_count`: Number of fetch attempts
- `_wcospa_pronto_order_number`: Pronto order reference
- `_wcospa_shipment_number`: Shipping tracking number

#### Shipment Tracking

Advanced Shipment Tracking integration:

- Provider Name: "Australia Post"
- Automatic status update to "Completed" upon tracking number receipt
- Tracking information added automatically after successful fetch

#### Key Action Hooks

The plugin responds to these WordPress hooks:

- `woocommerce_order_status_processing`: Triggers order synchronisation
- `wcospa_fetch_pronto_order_number`: Initiates Pronto order number fetch
- `wcospa_process_pending_orders`: Processes pending order queue
- `wcospa_pronto_order_number_received`: Handles successful order number receipt

The plugin introduces a custom order status:

- Status Name: "Preparing to Ship" (`wc-preparing-to-ship`)
- Visual Indicator: Orange background with white text
- Automatically applied after successful API synchronisation

#### Core Functionality Flow

1. Order Synchronisation:
   - Triggered when order status changes to "processing"
   - Obtains transaction UUID
   - Initiates 120-second wait period
   - Attempts to fetch Pronto order number
   - Immediately attempts to fetch shipment number upon success

2. Pronto Order Number Retrieval:
   - Maximum of 5 retry attempts
   - 30-second interval between retries
   - 3-second delay between different orders

3. Shipment Number Processing:
   - Automatic retrieval upon Pronto order number receipt
   - Manual retrieval via "Get Shipping" button
   - Automatic integration with Advanced Shipment Tracking
   - Updates order status upon successful tracking addition

### License

This plugin is licensed under the GPLv2 or later. For more information, see https://www.gnu.org/licenses/gpl-2.0.html.

### Changelog

#### 1.5
- **Major Release:** Consolidated and stabilized features from the 1.4.x development cycle
- **Enhancement:** Refined per-order environment selection functionality
  - Improved UI/UX for toggling between production and test environments
  - Enhanced error handling and feedback messages
  - Added comprehensive logging of environment selection changes
- **Optimization:** Improved dynamic debtor code detection and management
  - Strengthened domain detection logic for multi-site setups
  - Enhanced staging environment pattern recognition
  - Better fallback handling for edge cases
- **Reliability:** Enhanced error handling throughout API communication
  - Better response parsing for invalid or unexpected API responses
  - Improved logging with environment context
  - More descriptive error messages for troubleshooting
- **Documentation:** Updated documentation with thorough explanations
  - Added detailed section on API environment selection
  - Included setup instructions for multiple domain configurations
  - Updated configuration examples with latest best practices

#### 1.4.13
- **Improvement:** Updated plugin version for maintenance release
- **Maintenance:** General code updates and compatibility checks
- **Enhancement:** Implemented dynamic debtor code selection based on website domain
  - Added support for multiple websites using domain-specific debtor codes
  - Created configuration in credentials file for easy management
  - Supports multiple domains with automatic detection
  - Added development environment detection for local testing
  - Implemented configurable test debtor code via `WCOSPA_DEV_DEBTOR_CODE` constant
  - Provided fallback test debtor code for development environments
- **Feature:** Added environment selection for each order
  - Added radio buttons to toggle between test and production environments
  - Implemented per-order environment setting for API requests
  - Added visual confirmation and feedback for environment changes
  - Environment selection persists between page loads
- **Documentation:** Updated documentation for clarity and completeness
  - Added detailed configuration instructions
  - Included development environment setup guidance

#### 1.4.12
- **Enhancement:** Changed order status name from "Pronto Received" to "Preparing to Ship" for better clarity
  - Updated status slug from `wc-pronto-received` to `wc-preparing-to-ship`
  - Updated all related functions and CSS classes
  - Maintained existing functionality while improving status naming
  - Updated admin column labels for consistency
- **Improvement:** Enhanced code readability with consistent status naming
- **Refactor:** Centralised order status management for better maintainability
- **Compatibility:** Tested and confirmed compatibility with WordPress 6.7.2, WooCommerce 9.6.0, and PHP 8.2+
  - Updated plugin metadata to reflect supported versions
  - Ensured all functionality works correctly with latest platform versions
  - Added support for WooCommerce HPOS (High-Performance Order Storage)
  - Implemented HPOS-compatible order meta handling with fallback to legacy methods
  - Created new utility methods for backwards-compatible meta operations
  - Refactored order meta access across the entire plugin for future-proof compatibility

#### 1.4.11
- **Bug Fix**: Fixed issue with shipment numbers being incorrectly stored when status code is not 80 or 90
- **Improvement**: Enhanced shipment number processing with better validation and error handling
- **Improvement**: Added context-aware logging for shipment tracking operations
- **Refactor**: Centralized shipment number processing logic for better maintainability

#### 1.4.10
- **Feature**: Added weekend order handling with special Monday morning processing
- **Feature**: Implemented 30-minute retry interval for weekend orders
- **Improvement**: Enhanced logging for weekend order processing
- **Improvement**: Added Sydney timezone support for all time-based operations
- **Bug Fix**: Fixed issue with order processing outside working hours
- **Security**: Added request locking mechanism for AJAX operations

= 1.4.9 =

- Updated version number
- Improved WooCommerce dependency handling
- Added better error handling and logging

= 1.4.8 =

- **Enhancement:** Added weekend processing control for Pronto order number fetching.
  - System now skips processing during weekends (Saturday and Sunday).
  - Processing automatically resumes at 6 AM on the next working day.
  - Added comprehensive logging for skipped weekend processing.
- **Bug Fix:** Fixed SQL query ambiguity with post_id field.
  - Resolved database error in pending orders query.
  - Improved query performance and reliability.
- **Improvement:** Enhanced working hours management.
  - Processing limited to 6 AM - 7 PM on weekdays.
  - Better alignment with Pronto system operational hours.

= 1.4.7 =

- **Documentation:** Added comprehensive configuration documentation.
  - Detailed time intervals and limits documentation.
  - Order status management reference.
  - Meta data fields documentation.
  - Action hooks documentation.
  - Core functionality flow explanation.
  - File structure overview.

= 1.4.6 =

- **Enhancement:** Optimised cron job scheduling for better performance.
  - Changed processing interval from 3 seconds to 60 seconds.
  - Removed unnecessary three-second interval definition.
  - Improved compatibility with WordPress cron system.
  - Better server resource utilisation.
- **Improvement:** Enhanced error handling and logging.
  - Added detailed debug logging for shipment tracking.
  - Improved error messages for tracking integration.
  - Better handling of API response errors.

= 1.4.5 =

- **Feature:** Added automatic shipment tracking integration with Advanced Shipment Tracking plugin.
  - Automatically adds tracking information when shipment number is received.
  - Uses "Australia Post" as the shipping provider.
  - Updates order status to "Completed" after tracking is added.
- **Improvement:** Unified shipment number handling for both automatic and manual processes.
  - Same behaviour when getting shipment number via cron job or "Get Shipping" button.
  - Consistent order status updates and tracking information addition.
  - Improved reliability of shipment number processing.

= 1.4.4 =

- **Improvement:** Updated `format_order_items` method to calculate `price_ex_tax` per product item, storing it in each product's `price_ex_tax` field by dividing the `price_inc_tax` by 1.1.
- **Improvement:** Updated `amount` field in `format_order` method to use the sum of all `price_inc_tax` values across products, ensuring the correct total amount in `payment`.
- **Improvement:** Moved discount note entry in the `lines` array to follow all product items, clarifying line item sequence.
- **Improvement:** Replaced direct `get_meta('_shipping_company')` calls with WooCommerce's recommended `get_shipping_company()` method to avoid internal meta key usage errors and align with WooCommerce's best practices.
- **Improvement:** `price_inc_tax_per_item` and `price_ex_tax_per_item` are now calculated based on each product's single-unit price, unaffected by product quantity.
- **Improvement:** `total_price_inc_tax` now accumulates all `price_inc_tax` values for accurate final payment calculation.

= 1.4.3 =

- **Improvement:** When order has coupon code or any product on sale, add a note item in order indicates the order has coupon.

= 1.4.2 =

- **Improvement:** Include the bank_code in the payment section of the API when submitting an order to meet Pronto transaction requirements, as this indicates the payment method for each transaction. Without it, the accountant won't be able to identify the appropriate payment method for each transaction.

= 1.4.1 =

- **Feature:** Added a new custom order status `Preparing to Ship` (`wc-preparing-to-ship`), which tracks orders that have been successfully synced with the Pronto API.
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
  - `
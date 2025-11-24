### WooCommerce Order Sync Pronto API Plugin

This plugin automatically syncs WooCommerce orders with the Pronto API upon successful processing. The plugin includes features like a manual sync button, order status sync logs, a sync status page, and automatic status checks to retrieve Pronto Order numbers.

### Installation

1. Upload the `wcospa` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Configure your API credentials in the `wcospa-credentials.php` file located in `includes/`.

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

#### Supported Payment Methods

The plugin supports the following payment gateways with automatic mapping to Pronto API:

| Payment Gateway | WooCommerce ID | Pronto Code | Bank Code | Description |
|---|---|---|---|---|
| PayPal | `ppcp` | `PP` | `PAYPAL` | PayPal |
| AfterPay | `afterpay` | `CC` | Dynamic* | AfterPay |
| Stripe Credit Card | `stripe_cc` | `CC` | `STRIPE` | Stripe - Credit Card |
| ZIP | `stripe_zip` | `CC` | `STRIPE` | ZIP |
| Apple Pay | `stripe_applepay` | `CC` | `STRIPE` | Apple Pay |

*AfterPay bank code is dynamically determined based on the site domain (configured in admin settings).

All payment methods automatically add a descriptive line item to the order for accountant reference.

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

#### 1.6.9b
- **Fix:** Improved API response logging quality and completeness
  - Replaced `print_r()` with `wp_json_encode()` for structured logging
  - Fixed truncated log entries for large API responses
  - Updated all deprecated `self::log()` calls to use new `WCOSPA_Logger` with order context
  - JSON-formatted logs are easier to read and won't be cut off mid-response
  - Better debugging capability for API communication issues

#### 1.6.9a
- **Fix:** Reduced excessive debug logging in production
  - Removed verbose DEBUG logs from `init()` method that were being called on every page load
  - Now only logs when actually setting up new schedules (INFO level)
  - Eliminated "Shipment tracking events already scheduled, skipping setup" repetitive messages
  - Significantly reduced log file size and improved performance
  - Only meaningful events are now logged (schedule creation, processing runs, errors)

#### 1.6.9
- **Performance Enhancement:** Increased shipment tracking processing capacity and speed
  - **Doubled processing capacity**: Increased from 5 to 10 orders per scheduled run
  - **Faster processing**: Reduced delay between orders from 3 seconds to 1 second
  - **Improved throughput**: Can now process up to 10 orders in ~10 seconds (vs 5 orders in ~15 seconds previously)
  - **Better handling of backlogs**: Weekly capacity increased from 90 orders to 180 orders maximum
  - **Monday-Thursday capacity**: 40 orders per day (4 runs × 10 orders) vs previous 20 orders
  - **Friday capacity**: 20 orders per day (2 runs × 10 orders) vs previous 10 orders
  - **API rate compliance**: Still well within 10 calls/second limit (~1 call/second during processing)
  - All orders still benefit from automatic recovery, retry mechanisms, and comprehensive logging

#### 1.6.8
- **Critical Fix:** Resolved orphaned shipment tracking issue for orders stuck in "Preparing to Ship" status
  - **Root cause identified**: Orders missing `_wcospa_shipment_tracking_start` meta key were invisible to scheduled shipment processing
  - **Automatic recovery system**: Added `recover_orphaned_orders()` function that runs before each scheduled shipment check
  - **Improved query robustness**: Modified `process_pending_shipments()` to use LEFT JOIN instead of INNER JOIN, finding orders even without tracking start meta
  - **Safety check implemented**: Added fallback in `scheduled_fetch_pronto_order()` to ensure tracking meta is always set after receiving Pronto order number
  - **Admin recovery tool**: Added "Recover Orphaned Orders" button in admin interface for manual recovery
  - **Comprehensive logging**: All recovery actions are logged with order-specific details for troubleshooting
  - **Prevention mechanism**: Multiple safety checks ensure orders are never orphaned again
  - Orders that were previously stuck will now be automatically recovered and processed during the next scheduled run

#### 1.6.7
- **Enhancement:** Added support for additional payment methods
  - **ZIP payment support**: Added `stripe_zip` payment gateway mapping
  - **Apple Pay support**: Added `stripe_applepay` payment gateway mapping
  - Both methods map to Pronto `CC` (Credit Card) code with `STRIPE` bank code
  - Payment method descriptions automatically added to order line items
  - Updated documentation with comprehensive payment method reference table

#### 1.6.6a
- **Critical Fix:** Corrected incorrect implementation of shipment tracking schedule times
  - **Fixed schedule implementation**: Corrected all scheduling methods to match task 1.6.6 requirements exactly
  - **Updated init() method**: Now uses correct times (Monday-Thursday: 9:00 AM, 12:00 PM, 2:00 PM, 5:30 PM; Friday: 9:00 AM, 12:00 PM)
  - **Updated setup_initial_schedule() method**: Fixed activation schedule setup with correct times
  - **Updated schedule_next_check() method**: Fixed recurring schedule logic with correct times
  - **Removed obsolete logic**: Eliminated Friday 5:00 PM filtering since 5:00 PM is now 5:30 PM and only occurs Monday-Thursday
  - **Updated documentation**: Corrected all comments and documentation to reflect accurate schedule times

#### 1.6.6
- **Fix:** Reviewed and debugged scheduled event system to ensure proper execution
  - **Fixed critical bug**: Missing `$check_time` variable initialization in shipment handler's `init()` method
  - **Added comprehensive logging**: All scheduled events now have detailed logging for tracking execution
  - **Added proper activation/deactivation hooks**: Scheduled events are now properly set up during plugin activation and cleaned up during deactivation  
  - **Added debug functionality**: New admin interface with buttons to debug, test, and reset scheduled events
  - **Enhanced timezone handling**: Improved Sydney timezone calculations and WordPress cron integration
  - **Added scheduled event validation**: System now verifies WordPress cron status, timezone settings, and event registration
  - **Implemented comprehensive error tracking**: All shipment processing failures are logged with order-specific details
  - **Added manual testing capabilities**: Admin can now manually trigger shipment processing and view real-time results
  - **Enhanced shipment tracking schedule**: Monday-Thursday at 9:00 AM, 12:00 PM, 2:00 PM, 5:30 PM; Friday at 9:00 AM, 12:00 PM (Sydney time)
  - **Fixed WordPress cron integration**: Proper cleanup and registration of scheduled events to prevent conflicts

#### 1.6.6a
- **Critical Fix:** Corrected incorrect implementation of shipment tracking schedule times
  - **Fixed schedule implementation**: Corrected all scheduling methods to match task 1.6.6 requirements exactly
  - **Updated init() method**: Now uses correct times (Monday-Thursday: 9:00 AM, 12:00 PM, 2:00 PM, 5:30 PM; Friday: 9:00 AM, 12:00 PM)
  - **Updated setup_initial_schedule() method**: Fixed activation schedule setup with correct times
  - **Updated schedule_next_check() method**: Fixed recurring schedule logic with correct times
  - **Removed obsolete logic**: Eliminated Friday 5:00 PM filtering since 5:00 PM is now 5:30 PM and only occurs Monday-Thursday
  - **Updated documentation**: Corrected all comments and documentation to reflect accurate schedule times

#### 1.6.5
- **Feature:** Implemented dedicated plugin logging system with performance optimization and order-specific logging
  - Created lightweight `WCOSPA_Logger` class with minimal CPU and memory usage
  - Dedicated log files in plugin directory (`wp-content/plugins/woocommerce-order-sync-pronto-api/logs/`)
  - **Order-specific logging**: Each order gets its own log file (`logs/orders/XXXX/order-XXXX.log`)
  - Automatic log rotation when files exceed 10MB with optional compression
  - **14-day automatic log retention** with scheduled cleanup (increased from 7 days)
  - Buffered logging system (batch writes) to minimize file I/O operations
  - Security features: .htaccess protection, directory indexing prevention
  - Log level support: DEBUG, INFO, WARNING, ERROR, CRITICAL
  - Performance optimizations: periodic cleanup checks, async scheduling, minimal overhead
  - Admin interface integration showing detailed log statistics (general vs order-specific)
  - Order grouping: Orders are organized into subdirectories (0000-0099, 0100-0199, etc.) for efficient file management
  - Updated all existing logging calls throughout codebase to use new system with order-specific logging

#### 1.6.4
- **Feature:** Added manual "Obtain Shipping Number" button for bulk shipment number retrieval
  - Button appears next to Filter button on WooCommerce Orders admin page
  - Only shows when eligible orders exist (orders in "Preparing to Ship" status without shipment numbers)
  - Displays count of orders to be processed
  - Implements chunked processing system (2 orders per batch) to avoid SiteGround 120-second timeout
  - Uses existing `WCOSPA_Shipment_Handler::fetch_shipment_number()` function
  - Provides real-time progress tracking with individual order results
  - Respects API rate limits with 1-second delays between batches and 200ms delays between individual requests
  - Beautiful modal interface with progress bar and results display

- **Critical Fix:** Implemented robust 524 timeout error detection and handling for SiteGround's 120-second timeout limit
  - Added comprehensive timeout error detection in all API client methods (`sync_order`, `fetch_order_status`, `get_pronto_order_details`)
  - Updated HTTP request timeout from 20 seconds to 110 seconds to avoid SiteGround's 120-second limit
  - Implemented specific error handling for 524 timeout errors, server errors (502, 503, 504, 522, 523), and other timeout indicators
  - Enhanced `fetch_shipment_number` function with detailed timeout error handling and logging
  - Updated AJAX handlers to provide specific user feedback for timeout and server errors
  - Enhanced bulk shipment processing with timeout-aware error handling and status tracking
  - Added visual indicators in the admin interface for timeout errors (orange) and server errors (red)
  - Implemented comprehensive logging with `[TIMEOUT ERROR]`, `[524 TIMEOUT ERROR]`, and `[SERVER ERROR]` prefixes for easy identification
  - Added timeout error tracking in order meta data for debugging purposes
  - Updated JavaScript frontend to display timeout and server error statuses with appropriate styling
  - Added informational notices about timeout behavior in the admin interface
  - All timeout errors now prevent further processing and provide clear error messages to administrators

- **Enhancement:** Comprehensive error handling, detailed logging, and improved user feedback throughout the system

#### 1.6.3b
- **Enhancement:** Updated shipment tracking schedule with new business hours
  - **Monday-Thursday**: 8:00 AM, 9:00 AM, 10:00 AM, 11:00 AM, 1:00 PM, 5:00 PM (Sydney time)
  - **Friday**: 8:00 AM, 9:00 AM, 10:00 AM, 11:00 AM, 1:00 PM only (no 5:00 PM)
  - **Weekends**: No processing (Saturday and Sunday)
  - Improved scheduling logic to handle different schedules for weekdays vs Friday
  - Enhanced next check time calculation for proper day transitions

#### 1.6.3a
- **Refactor:** Removed morning sync check and weekend order processing functionality
  - Removed daily morning sync at 6:00 AM Sydney time (weekdays)
  - Removed weekend order handling with special Monday morning processing
  - Removed weekend order marking and retry logic
  - Simplified order processing to focus on core functionality
  - Maintains enhanced shipment tracking schedule from 1.6.3

#### 1.6.3
- **Enhancement:** Expanded shipment tracking schedule for improved responsiveness
  - Increased from 2 daily checks to 7 daily checks on weekdays
  - New schedule: 9:00 AM, 10:00 AM, 11:00 AM, 1:00 PM, 2:00 PM, 3:00 PM, 4:00 PM (Sydney time)
  - Improved logic for determining next check time in schedule_next_check() method
  - Enhanced shipment tracking retrieval frequency for better order fulfilment tracking
  - Maintains existing weekday-only processing and error handling mechanisms

#### 1.6.2
- **Feature:** Added bulk "Sync Processing Orders" button to the Orders admin page (Task 1.6.2)
  - New button appears next to the Filter button on the WooCommerce orders list page
  - Syncs all orders currently in "Processing" status that haven't been synced yet
  - Uses existing sync functionality without creating new functions
  - Provides comprehensive user feedback with success/error notices
  - Includes confirmation dialog and loading states for better UX
  - Updates order status to "Preparing to Ship" after successful sync
  - Includes proper error handling and logging for traceability

#### 1.6.1
- **Feature:** Added manual sync button for individual orders with "Processing" status (Task 1.6.1)
  - New "Sync Order" button appears in the order list for Processing orders that haven't been synced yet
  - Button triggers the existing order synchronization process for that specific order
  - After successful sync, the button is hidden and status shows "Awaiting Pronto Order Number"
  - Includes proper error handling and user feedback
  - Uses existing `handle_ajax_sync()` functionality without creating new functions
  - Added appropriate CSS styling and JavaScript event handling

#### 1.5.3
- **Feature:** Implemented a scheduled task that runs every weekday morning (6:00 AM Sydney time) to process all orders in the 'Preparing to Ship' status.
  - For each such order, if the Pronto order number is missing, it attempts to retrieve it using the existing logic.
  - If the shipment number is missing, it attempts to retrieve it using the existing logic.
  - Ensures all past orders lacking either a Pronto order number or a shipment number are included in the process.
  - Added detailed logging for traceability and debugging.
  - Updated version references throughout the plugin for consistency.

#### 1.5.2
- **Improvement:** Enhanced weekend and Monday morning operations for better API synchronization
  - Improved time detection logic for weekend and Monday morning processing
  - Added detailed debug logging for time-related operations
  - Updated timestamp handling to use consistent Sydney timezone (AEST) calculations
  - Fixed issues with weekend order detection and Monday morning processing queue
  - Ensured past orders without Pronto order numbers are properly processed on Monday mornings
  - Updated version references throughout the plugin for consistency

#### 1.5.0
- **Feature:** Added dynamic environment toggle for switching between Production and Test environments
  - New UI on the Sync Status page for toggling between environments
  - Environment state persists using transients without database modifications
  - Visual indicators for current environment
- **Feature:** Implemented dynamic debtor code configuration
  - Default debtor code (210942) for zerotech.com.au and store.zerotechoptics.com
  - Support for site-specific codes (211023 for nitecorewebsite.com.au)
  - UI for viewing and editing the current debtor code
  - Non-database persistence using transients
- **Feature:** Added dynamic Afterpay code configuration
  - Support for site-specific Afterpay codes:
    - AFTER for zerotech.com.au and store.zerotechoptics.com
    - AFPNIT for nitecoreaustralia.com.au
    - AFPSKY for skywatcheraustralia.com.au
  - UI for viewing and editing the current Afterpay code
- **Improvement:** Enhanced UI with visual indicators for environment status
- **Improvement:** Added comprehensive error handling and user feedback
- **Compatibility:** Updated for PHP 8.2+, WooCommerce 9.7.1, and WordPress 6.7.2

#### 1.4.12
- **Enhancement:** Changed order status name from "Pronto Received" to "Preparing to Ship" for better clarity
  - Updated status slug from `wc-pronto-received` to `wc-preparing-to-ship`
  - Updated all related functions and CSS classes
  - Maintained existing functionality while improving status naming
  - Updated admin column labels for consistency
- **Improvement:** Enhanced code readability with consistent status naming
- **Refactor:** Centralised order status management for better maintainability

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

# Project Extension Instructions

### Pre-Requisites

- **Review Code:** Analyze all project files and code.
- General Requirements

  - **Email Content:** Must include all order details—products, total payment, dealer’s name, and timestamps (dealer’s local time and Australian time).
  - **Backend Enhancements:** Update the WooCommerce order list with additional meta data for troubleshooting.
  - **Coding Standards:**

    - Use unique class names to prevent conflicts with other plugins.
    - Ensure compatibility with PHP 8.2+ (and 7.4+), and the latest versions of WordPress and WooCommerce.
  - **Testing:** Verify all files load correctly, check for errors, and retest thoroughly.
  - **Separation:** Integrate these changes in the `INT` branch without modifying existing files or folders.
  - Ensure all files are loaded correctly and definately
  - Creat individual folder for this extension

~~### Step 1: JSON File for Dealers~~

- **Objective:** Create a JSON file that lists countries with associated dealer emails.
- Prevent automatic progression from "Processing" to "Pronto Received".
- **Details:**
  - Each country must have a dealer email.
  - Default email: `jerry@tasco.com.au`.

~~### Step 2: New Order Email Notification~~

- **Objective:** Replace POST requests to Pronto with email notifications.
- **Details:**
  - For each new order, select the dealer’s email from the JSON file based on the shipping country.
  - Compose an email using WooCommerce’s "New Order" template that includes:
    - Customer name and contact details
    - Shipping address (if different from billing)
    - Order items (displayed in a table)
    - Order total
  - Include two buttons in the email:
    - **Accept & Fulfill** (records acceptance in order meta data)
    - **Decline Order** (records decline in order meta data)

~~### Step 3: Order Reception & Dealer Workflow~~

- **Objective:** Implement a dealer-driven order processing workflow.
- **Details:**
  - **Order Handling:**
    - Upon order receipt, send the dealer email (dealer selected from the JSON file based on shipping country).
    - Ensure: Prevent automatic progression from "Processing" to "Pronto Received" unless other functions call it.
  - **On Successful Email Send:**
    - Update order status to "Await Dealer Decision".
    - Save the dealer’s email in the order meta data.
    - Add a "Dealer's Email" column on the Order page.
    - Start a 48-hour decision timer:
      - **No response or "Decline Order":** Sync the order to Pronto.
      - **On "Accept & Fulfill":**
        - Stop the decision timer and update the status to "Dealer Accept".
        - Start a 48-hour shipping timer:
          - **If order is marked "Shipped":**
            - Cancel the shipping timer.
          - **If not shipped within 48 hours:**
            - Email `jhead@zerotech.com.au` and `jli@zerotech.com.au` alerting that the order was accepted but not shipped on time.
  - **On Email Failure:**
    - Retry sending the email up to three times at short intervals.
    - If still unsuccessful:
      - Email `jheads@zerotech.com.au` and `jli@zerotech.com.au` with the failure details.
      - Update order status to "Email Dealer Failed" and stop further actions.

### Step 4: Fixing Guest Access for Accept/Reject URLs

- **Issue:**

  - Clicking the "Accept" or "Reject" button in incognito mode results in an "Invalid request" error.
  - Dealers without an account cannot use the provided links.
- **Solution:**

  - Review and identify the issue preventing guest access.
  - Ensure that the URLs from "Accept" and "Reject" buttons function correctly for non-logged-in users.
  - Implement a secure yet accessible verification method to allow guest users to take action.
  - Apply best practices to prevent unauthorized access while ensuring the URLs remain functional.

### ~~Step 5: Prevent Multiple Actions on Accept/Reject Buttons~~

- **Requirement:**

  - The "Accept" and "Reject" buttons in a single order email must be actionable only once.
  - After an action is taken, both buttons should become inactive.
- **Implementation:**

  - Once an order is accepted or rejected, disable further API calls via the links.
  - Clicking an already-used link should display:
    - `"You have already accepted/rejected order XXX at XX:XX on XX/XX/XXXX. If further action is needed, please contact jheads@zerotech.com.au."`
  - Ensure backend validation prevents duplicate actions, even if the URL is accessed again.

~~### Step 6: Sync Order to Pronto on Rejection~~

- **Requirement:**

  - If the "Decline" button is clicked, trigger an automatic order sync to Pronto.
  - Update the order status accordingly.
- **Implementation:**

  - When the "Reject" action is confirmed:
    - Sync the order data with Pronto.
    - Update the order status to `"Pronto Received"`.
  - Ensure this process is error-handled and logged for tracking.

### Step 7: Enhance Redirect Page and Email Notifications

#### Redirect Page Update

- **Objective:**
  - Update the redirect page displayed after a dealer accepts or declines an order.
- **Details:**
  - Display the order number and billing name with a confirmation message (e.g., "has been accepted" or "has been declined")
  - Include timestamps in both:
    - Dealer local time (formatted as XX:XX, XX/XX/XXXX)
    - AUS Eastern Standard Time in a modern format
  - Add debug logs on each necesasry actions been triggered

#### New Email Notifications

- **Objective:**
  - Send new notification email(s) when an order is accepted or declined.
- **Details:**
  - Email content must state:

    - "Dealer XXX has accepted/declined order XXX at XX:XX, XX/XX/XXXX" (showing both dealer local time and AUS Eastern Standard Time)
  - Recipients:

    - The dealer (as the main recipient)
    - BCC: `jerry@tasco.com.au`
  - Add debug logs on each necesasry actions been triggered

#### Additional Requirement

- Apply the default WooCommerce email header and footer template to all other emails sent by the system.

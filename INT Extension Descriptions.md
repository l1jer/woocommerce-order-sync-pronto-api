# Project Extension Instructions

## Pre-Requisites

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

## Step 1: JSON File for Dealers

- **Objective:** Create a JSON file that lists countries with associated dealer emails.
- **Details:**
  - Each country must have a dealer email.
  - Default email: `jerry@tasco.com.au`.

## Step 2: New Order Email Notification

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

## Step 3: Order Reception & Dealer Workflow

- **Objective:** Implement a dealer-driven order processing workflow.
- **Details:**
  - **Order Handling:**
    - Prevent automatic progression from "Processing" to "Pronto Received".
    - Upon order receipt, send the dealer email (dealer selected from the JSON file based on shipping country).
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
            - Record "Dealer" in a new "Shipping Responder" column on the Order page.
          - **If not shipped within 48 hours:**
            - Email `jhead@zerotech.com.au` and `jli@zerotech.com.au` alerting that the order was accepted but not shipped on time.
  - **On Email Failure:**
    - Retry sending the email up to three times at short intervals.
    - If still unsuccessful:
      - Email `jheads@zerotech.com.au` and `jli@zerotech.com.au` with the failure details.
      - Update order status to "Email Dealer Failed" and stop further actions.

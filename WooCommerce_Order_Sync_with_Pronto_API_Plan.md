Project Plan: WooCommerce Order Sync with Pronto API
Author: Jerry Li
Version: 1.5

Objective:
The project will integrate a feature within WooCommerce to sync orders with Pronto API, including stock validation and order submission. It will involve checking product stock through Pronto's API and, if stock is available, posting the order to Pronto. The plan includes debugging mechanisms for troubleshooting, handling API responses, and appropriate button states depending on the order status.

Feature Overview:
On the WooCommerce order list page, a new column "Pronto Order Number" will be added, featuring a "Sync to Pronto" button. This button will trigger a stock validation for the products in the order using the Pronto API. If stock is sufficient, the order details will be sent to Pronto. If stock is insufficient, the user will receive a warning. Successfully synced orders will display a transaction UUID and timestamp, and the sync button will be disabled.

Detailed Requirements:

New Column and Button:

Add a new column labeled "Pronto Order Number" on the WooCommerce order list page.
A "Sync to Pronto" button will appear for each order in Processing status.
If an order has been successfully synced, the button will change to "Already Synced" with a tooltip showing the transaction UUID, sync date, and time.
The button is greyed out for orders that are not in the Processing state, displaying a tooltip: "Only available to Processing orders".
Stock Validation (GET Request):

When the "Sync to Pronto" button is clicked, the system will first validate stock availability for each product in the order via a GET request to Pronto.

API Endpoint: https://tasco-750-test.prontoavenue.biz/api/json/product/v4.json

API Method: GET

Parameters: Query parameter ?code=[SKU] where [SKU] is each product's SKU from the WooCommerce order.

Stock Validation Process:

GET Request: Send a GET request to the Pronto API for each product SKU in the WooCommerce order.
Inventory Check: Once the API response is received, the system will parse the JSON data and locate the inventory_quantities array within each product object.
Warehouse Selection:
Within inventory_quantities, locate the entry where "warehouse" is 1.
The system will then retrieve the "quantity" field under this entry.
Stock Comparison:
Compare the "quantity" field from Pronto's warehouse 1 against the required quantity for each product in the WooCommerce order.
If the quantity in Pronto's warehouse 1 is less than the required amount for any SKU, show a pop-up message that says, "Insufficient stock on [SKU]", and stop the order sync process for that order.
Successful Stock Validation:
If all products have sufficient stock in warehouse 1, proceed to the order submission step (see next section).
Example API Response JSON Schema:

json
Copy code
{
  "products": [
    {
      "code": "SKU1234",
      "description": "Sample Product",
      "inventory_quantities": [
        {
          "warehouse": "1",
          "quantity": "10"
        },
        {
          "warehouse": "2",
          "quantity": "5"
        }
      ]
    }
  ],
  "count": "1",
  "pages": "1"
}
In this example, the system would search within inventory_quantities, find the warehouse with "warehouse": "1", and retrieve the quantity available, which is "10" for this particular SKU.
Order Submission (POST Request):

If the stock check passes, the system will send a POST request to the Pronto API to submit the order details.

API Endpoint: https://tasco-750-test.prontoavenue.biz/api/json/order/v6.json/

API Method: POST

Authentication: Use the credentials provided by get_api_credentials().

WooCommerce Order Data Mapping:

Extract customer reference, debtor details, delivery address, payment method, and order items from the WooCommerce order.
Format this data into the required JSON structure for the Pronto API.
Order Data Format Example:

json
Copy code
{
  "customer_reference": "19312 / BOYER",
  "debtor": "210942",
  "delivery_address": {
    "address1": "TYLAR BOYER",
    "address2": "241 honour ave",
    "address3": "",
    "address4": "COROWA;NSW",
    "address5": "NSW",
    "address6": "AU",
    "address7": "",
    "postcode": "2646",
    "phone": "0460309630"
  },
  "delivery_instructions": "NO INVOICE & PACKING SLIP, tylarboyer@hotmail.com, ",
  "payment": {
    "method": "CC",
    "reference": "9UJ9969432300491N",
    "amount": "299.00",
    "currency_code": "AUD"
  },
  "lines": [
    {
      "type": "SN",
      "item_code": "THDRS28L",
      "quantity": "1",
      "uom": "EA",
      "price_inc_tax": "271.82"
    }
  ]
}
Handling API Responses:

After the POST request, the response body will contain a UUID. This UUID will be extracted and stored in the WooCommerce order as wcospa_transaction_uuid.
The "Sync to Pronto" button will be greyed out, and the text will be updated to "Already Synced", with a tooltip that displays the Transaction UUID, sync date, and sync time.
Example response body:
html
Copy code
<html><body>You are being <a href="https://tasco-750-test.prontoavenue.biz/api/json/transaction/v4.json?uuid=bd982971-4479-43cc-955f-8c25680463e2">redirected</a>.</body></html>
Data Storage:

The Transaction UUID will be stored in the WooCommerce order as wcospa_transaction_uuid.
Additionally, the system will store the sync date and time in WooCommerce as transaction_uuid_date and transaction_uuid_time, respectively.
Button States and Tooltips:

Initial State: The "Sync to Pronto" button will only be available for orders in Processing status. For orders in other statuses, it will be greyed out with the tooltip: "Only available to Processing orders".
After Sync: Once successfully synced, the button will change to "Already Synced" and display a tooltip with the Transaction UUID, Sync Date, and Sync Time.
Debugging and Logging:

Add debugging logs at key steps for future troubleshooting:
Log API requests and responses when checking stock availability.
Log formatted WooCommerce order data before submitting it to Pronto.
Log the full API response body after successful or failed submissions.
Display successful API responses in the browser console for further review.
Order Data Formatter Class:

Class Name: WCOSPA_Order_Data_Formatter
Purpose: To format WooCommerce order data into the Pronto API’s expected structure, including:
Delivery address formatting
Delivery instructions
Payment information
Order line details
Sample Code:
php
Copy code
class WCOSPA_Order_Data_Formatter {
    public static function format_order($order, $customer_reference) {
        $order_data = $order->get_data();
        $shipping_address = $order_data['shipping'];
        $billing_email = $order->get_billing_email();
        $shipping_email = $order->get_meta('_shipping_email');
        $customer_provided_note = $order->get_customer_note();

        $delivery_instructions = implode(', ', [
            'NO INVOICE & PACKING SLIP',
            $billing_email !== $shipping_email && $shipping_email ? $shipping_email : $billing_email,
            !empty($customer_provided_note) ? substr($customer_provided_note, 0, 30) : '',
        ]);

        return [
            'customer_reference' => $customer_reference,
            'debtor' => '210942',
            'delivery_address' => [
                'address1' => strtoupper($order->get_shipping_first_name().' '.$order->get_shipping_last_name()),
                'address2' => $shipping_address['address_1'],
                'address3' => $shipping_address['address_2'],
                'address4' => $shipping_address['city'],
                'address5' => $shipping_address['state'],
                'address6' => $shipping_address['country'],
                'postcode' => $shipping_address['postcode'],
                'phone' => $order->get_billing_phone(),
            ],
            'delivery_instructions' => $delivery_instructions,
            'payment' => [
                'method' => self::convert_payment_method($order->get_payment_method()),
                'reference' => $order->get_transaction_id(),
                'amount' => $order->get_total(),
                'currency_code' => $order->get_currency(),
            ],
            'lines' => self::format_order_items($order->get_items()),
        ];
    }
}
Conclusion:
This project enables seamless synchronization between WooCommerce and the Pronto system using API requests for stock validation and order submission. It provides user-friendly feedback on the order sync status and includes robust logging and debugging to ensure easy maintenance.
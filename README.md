# woocommerce-order-sync-pronto-api

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
1. debtor code needs to be 210942
2. status_code is 30 when order is processing
3. status_code is 80 when order is completed
4. customer_reference is "order number / shipping last name", e.g. 19763 / VAN VUUREN
5.             "delivery_instructions": {
                "del_inst_1": "No invoice&packing slip",
                "del_inst_2": "franzwa@brisbanewestre.com",
                "del_inst_3": "",
5a. add uppercase "No invoice & packing slip" to del_inst_1
5b. Add customer email from shipping (if different to billing email) to del_inst_2
5c. if there is any Order Notes, add this to "delivery_instructions" -> "del_inst_3", limit to 30 characters only; if nothing in Order Notes, then leave it empty
6. remove price_ex_tax
7. need a calculation each product price from woocommerce need to divided by 1.1, this is the price needs to send to API
8. in the following part:
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
~~### Issue 1~~

I need you to analyze a problem with fetching the correct shipping number based on status codes and consignment notes. Here’s the situation: when the status code is neither 80 nor 90, the data sometimes includes a **consignment\_note**. However, this **consignment\_note** is not the shipping number we want. We only need the shipping number when the status code is specifically 80 or 90. Currently, the system fetches incorrect data in other cases, and I’m unsure why this happens or what’s causing the confusion.

Your task is to:

1. **Identify the Error**: Review the logic where the shipping number is fetched. Check if it’s mistakenly pulling the **consignment\_note** (or another field) when the status code isn’t 80 or 90.
2. **Understand the Context**: Use the attached debug log to trace the data flow. Look for patterns—e.g., what fields are returned for status codes outside 80/90, and why the **consignment\_note** appears. Consider if this is a data structure issue, a misidentification of fields, or a logic flaw.
3. **Resolve with Best Practice**: Update the code to strictly fetch the shipping number only when the status code is 80 or 90. Implement a condition to filter out unwanted cases (e.g., ignore **consignment\_note** unless explicitly needed). Ensure the solution is robust—add validation, logging, or comments to clarify intent and prevent future errors.

For reference, here’s the debug log to analyze:

```
[24-Feb-2025 02:30:11 UTC] [WCOSPA] Transaction URL: https://sales.tasco.net.au/api/json/transaction/v5.json/?uuid=df9acfb4-e53a-4354-920f-d145ef558952
[24-Feb-2025 02:30:11 UTC] [WCOSPA] Raw Response Body: {"apitransactions":[{"uuid":"df9acfb4-e53a-4354-920f-d145ef558952","status":"Complete","errors":null,"warnings":null,"result_url":"/api/xml/order/v4?number=177023"}]}
[24-Feb-2025 02:30:11 UTC] [WCOSPA] Successfully extracted Pronto Order Number: 177023
[24-Feb-2025 02:30:11 UTC] [WCOSPA] Order URL: https://sales.tasco.net.au/api/json/order/v4.json/?number=177023
[24-Feb-2025 02:30:11 UTC] [WCOSPA] Pronto Order response body: Array
(
    [orders] => Array
        (
            [0] => Array
                (
                    [customer_reference] => 23002 / KIRBY
                    [debtor] => 210942
                    [pronto_order_number] => 177023
                    [suffix] => 
                    [status_code] => 40
                    [status_desc] => PSlip Prt'd
                    [carrier_code] => POST
                    [consignment_note] => 23599
                    [currency_code] => 
                    [delivery_address] => Array
                        (
                            [address1] => RYAN KIRBY
                            [address2] => 97 Victoria St
                            [address3] => Peterborough SA
                            [address4] => 
                            [address5] => 
                            [address6] => 
                            [address7] => 
                            [postcode] => 5422
                            [phone] => +61432834079
                        )

                    [delivery_instructions] => Array
                        (
                            [del_inst_1] => *NO INVOICE AND PACKING SLIP*
                            [del_inst_2] => kirbs629@gmail.com
                            [del_inst_3] => 
                            [del_inst_4] => 
                            [del_inst_5] => 
                            [del_inst_6] => 
                            [del_inst_7] => 
                        )

                    [payment_method] => CC
                    [payment_reference] => SPay AFTERPAY 23002 / KIRBY
                    [lines] => Array
                        (
                            [0] => Array
                                (
                                    [type] => SN
                                    [stock_code] => VG3124
                                    [ordered_qty] => 1.0
                                    [backordered_qty] => 0.0
                                    [shipped_qty] => 1.0
                                    [uom] => EA
                                    [price_ex_tax] => 226.36
                                    [price_inc_tax] => 249.0
                                )

                            [1] => Array
                                (
                                    [type] => DN
                                    [stock_code] => 
                                    [ordered_qty] => 0.0
                                    [backordered_qty] => 0.0
                                    [shipped_qty] => 0.0
                                    [uom] => 
                                    [price_ex_tax] => 0.0
                                    [price_inc_tax] => 0.0
                                )

                        )

                )

        )

    [count] => 1
    [pages] => 1
)

[24-Feb-2025 02:30:15 UTC] Successfully added tracking number 23599 to order 23002
[24-Feb-2025 02:30:15 UTC] Successfully fetched Shipment Number: 23599 for order: 23002
```

### Issue 2

The 11:55 AM and 4:55 PM schedulers aren’t working correctly. By the end of today, over 5 orders are missing shipment numbers, though one order between those times does have a shipment number. Something’s off with the scheduling or data assignment logic. Please:

1. **Identify the Error**: Review the schedulers’ execution logs or code to pinpoint why most orders lack shipment numbers while one succeeded.
2. **Understand the Context**: Analyze the timing, order data, and any differences between the successful order and the failed ones to determine the root cause (e.g., scheduler failure, data mismatch, or timing issues).
3. **Resolve with Best Practices**: Fix the issue by ensuring the schedulers consistently assign shipment numbers to all orders at 11:55 AM and 4:55 PM. Implement a solution with proper checks.

Ensure the fix is reliable, logs errors for debugging, and avoids partial failures. Briefly explain your findings and how the solution addresses them.

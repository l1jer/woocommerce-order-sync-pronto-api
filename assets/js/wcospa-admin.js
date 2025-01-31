document.addEventListener("DOMContentLoaded", function () {
    // Clear Sync Logs button
    // var clearLogsButton = document.getElementById("wcospa-clear-logs");
    // if (clearLogsButton) {
    //     clearLogsButton.addEventListener("click", function () {
    //         if (
    //             confirm(
    //                 "Are you sure you want to clear all sync logs? This action cannot be undone."
    //             )
    //         ) {
    //             var xhr = new XMLHttpRequest();
    //             xhr.open("POST", ajaxurl, true);
    //             xhr.setRequestHeader(
    //                 "Content-Type",
    //                 "application/x-www-form-urlencoded"
    //             );
    //             xhr.onload = function () {
    //                 try {
    //                     var response = JSON.parse(xhr.responseText);
    //                     if (response.success) {
    //                         fetchButton.textContent = "Fetched";
    //                         prontoOrderDisplay.textContent =
    //                             response.data.pronto_order_number; // Update the Pronto Order number display
    //                         console.log("Fetch successful: ", response);
    //                     } else {
    //                         fetchButton.textContent = "Fetch";
    //                         fetchButton.disabled = false;
    //                         console.log("Fetch failed: ", response.data);
    //                     }
    //                 } catch (error) {
    //                     console.error("Error parsing JSON response:", error);
    //                     console.error("Response text:", xhr.responseText); // Log raw response for debugging
    //                 }
    //             };

    //             xhr.send("action=wcospa_clear_sync_logs");
    //         }
    //     });
    // }

    // Clear All Sync Data button
    var clearAllSyncDataButton = document.getElementById(
        "wcospa-clear-all-sync-data"
    );
    if (clearAllSyncDataButton) {
        clearAllSyncDataButton.addEventListener("click", function () {
            if (
                confirm(
                    "Are you sure you want to clear all sync data? This will reset the sync status for all orders and cannot be undone."
                )
            ) {
                var xhr = new XMLHttpRequest();
                xhr.open("POST", ajaxurl, true);
                xhr.setRequestHeader(
                    "Content-Type",
                    "application/x-www-form-urlencoded"
                );
                xhr.onload = function () {
                    if (xhr.status === 200) {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            alert("All sync data cleared successfully.");
                            location.reload();
                        } else {
                            alert("Failed to clear all sync data: " + response.data);
                        }
                    } else {
                        alert("An error occurred: " + xhr.statusText);
                    }
                };
                xhr.send("action=wcospa_clear_all_sync_data");
            }
        });
    }

    // Sync Order Buttons
    // var syncButtons = document.querySelectorAll(".sync-order-button");
    var fetchButtons = document.querySelectorAll(".fetch-order-button");
    // syncButtons.forEach(function (button) {
    //     var orderId = button.getAttribute("data-order-id");
    //     var syncStatus = localStorage.getItem("sync_status_" + orderId);
    //     if (syncStatus === "true") {
    //         button.textContent = "Synced";
    //         button.disabled = true;
    //         button.title = "Synced on " + new Date().toLocaleString(); // Add sync timestamp to tooltip
    //     }
    // });

    // syncButtons.forEach(function (button) {
    //     button.addEventListener("click", function () {
    //         var orderId = button.getAttribute("data-order-id");
    //         var nonce = button.getAttribute("data-nonce");
    //         var fetchButton = document.querySelector('.fetch-order-button[data-order-id="' + orderId + '"]');

    //         var xhr = new XMLHttpRequest();
    //         xhr.open("POST", ajaxurl, true);
    //         xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    //         xhr.onload = function () {
    //             if (xhr.status === 200) {
    //                 var response = JSON.parse(xhr.responseText);
    //                 if (response.success) {
    //                     var countdown = 120;
    //                     var countdownInterval = setInterval(function () {
    //                         if (countdown > 0) {
    //                             fetchButton.textContent = countdown + "s";
    //                             fetchButton.disabled = true;
    //                             countdown--;
    //                         } else {
    //                             clearInterval(countdownInterval);
    //                             fetchButton.textContent = "Fetch";
    //                             fetchButton.disabled = false;
    //                         }
    //                     }, 1000);
    //                 }
    //             }
    //         };

    //         xhr.send("action=wcospa_sync_order&order_id=" + encodeURIComponent(orderId) + "&security=" + encodeURIComponent(nonce));
    //     });
    // });

    function handleFetchButton(button) {
        var orderId = button.getAttribute("data-order-id");
        var syncTime = button.getAttribute("data-sync-time");
        var nonce = button.getAttribute("data-nonce");
        var prontoOrderDisplay = document.querySelector('.pronto-order-number');

        // Handle countdown logic
        if (syncTime) {
            handleCountdown(button, syncTime);
        }

        // Handle click events
        button.addEventListener("click", function() {
            handleFetchButtonClick(orderId, nonce, prontoOrderDisplay);
        });
    }

    fetchButtons.forEach(handleFetchButton);

    // Function to bind event listeners to all buttons
    function bindButtonEvents() {
        // Bind Fetch Order buttons
        document.querySelectorAll('.fetch-order-button').forEach(function(button) {
            button.removeEventListener('click', handleFetchClick);
            button.addEventListener('click', handleFetchClick);
        });

        // Bind Get Shipping buttons
        document.querySelectorAll('.get-shipping-button').forEach(function(button) {
            button.removeEventListener('click', handleGetShippingClick);
            button.addEventListener('click', handleGetShippingClick);
        });
    }

    // Initial binding
    bindButtonEvents();

    // Rebind events every 2 seconds to catch dynamically added buttons
    setInterval(bindButtonEvents, 2000);

    // Define the click handler for Get Shipping button
    function handleGetShippingClick(e) {
        e.preventDefault();
        const button = this;
        const orderId = button.getAttribute('data-order-id');
        const nonce = button.getAttribute('data-nonce');
        const shipmentNumberDiv = button.closest('.wcospa-order-column').querySelector('.shipment-number');

        console.log('Get Shipping clicked for order:', orderId); // Debug log

        // Add loading state
        button.classList.add('loading');
        button.disabled = true;
        shipmentNumberDiv.textContent = 'Fetching shipment number...';

        // Prepare form data
        const formData = new FormData();
        formData.append('action', 'wcospa_get_shipping');
        formData.append('order_id', orderId);
        formData.append('security', nonce);

        // Make the AJAX request
        fetch(ajaxurl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => {
            console.log('Response received:', response); // Debug log
            return response.json();
        })
        .then(data => {
            console.log('Data received:', data); // Debug log
            if (data.success && data.data.shipment_number) {
                // Success! Update the display and remove the button
                shipmentNumberDiv.textContent = data.data.shipment_number;
                button.closest('.wcospa-fetch-button-wrapper').remove();
            } else {
                // Failed to fetch
                const errorMessage = data.data || 'Failed to fetch shipment number';
                shipmentNumberDiv.textContent = errorMessage;
                button.classList.remove('loading');
                button.disabled = false;
                console.error('Fetch failed:', errorMessage); // Debug log
            }
        })
        .catch(error => {
            console.error('Error:', error);
            shipmentNumberDiv.textContent = 'Error fetching shipment number';
            button.classList.remove('loading');
            button.disabled = false;
        });
    }
});

// Define the click handler outside to prevent duplicates
function handleFetchClick() {
    if (this.classList.contains('loading')) {
        return;
    }

    const button = this;
    const orderId = button.getAttribute('data-order-id');
    const nonce = button.getAttribute('data-nonce');
    const orderColumn = button.closest('.wcospa-order-column');
    const orderNumberDiv = orderColumn.querySelector('.pronto-order-number');
    let retryCount = 0;
    const MAX_RETRIES = 5;
    const RETRY_DELAY = 30000; // 30 seconds in milliseconds

    function fetchOrderNumber() {
        // Add loading state
        button.classList.add('loading');
        button.disabled = true;
        orderNumberDiv.textContent = `Fetching... (Attempt ${retryCount + 1}/${MAX_RETRIES})`;

        // Prepare form data
        const formData = new FormData();
        formData.append('action', 'wcospa_fetch_pronto_order');
        formData.append('order_id', orderId);
        formData.append('security', nonce);

        // Make the AJAX request
        fetch(ajaxurl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.pronto_order_number) {
                // Success! Update the display and remove the button
                orderNumberDiv.textContent = data.data.pronto_order_number;
                button.closest('.wcospa-fetch-button-wrapper').remove();
            } else {
                retryCount++;
                if (retryCount < MAX_RETRIES) {
                    // Schedule next retry
                    orderNumberDiv.textContent = `Waiting ${RETRY_DELAY/1000}s for next attempt... (${retryCount}/${MAX_RETRIES})`;
                    button.classList.remove('loading');
                    button.disabled = true;
                    setTimeout(fetchOrderNumber, RETRY_DELAY);
                } else {
                    // Max retries reached
                    orderNumberDiv.textContent = 'Failed to fetch after 5 attempts';
                    button.classList.remove('loading');
                    button.disabled = false;
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            retryCount++;
            if (retryCount < MAX_RETRIES) {
                // Schedule next retry
                orderNumberDiv.textContent = `Error occurred. Retrying in ${RETRY_DELAY/1000}s... (${retryCount}/${MAX_RETRIES})`;
                button.classList.remove('loading');
                button.disabled = true;
                setTimeout(fetchOrderNumber, RETRY_DELAY);
            } else {
                // Max retries reached
                orderNumberDiv.textContent = 'Failed to fetch after 5 attempts';
                button.classList.remove('loading');
                button.disabled = false;
            }
        });
    }

    // Start the first fetch attempt
    fetchOrderNumber();
}

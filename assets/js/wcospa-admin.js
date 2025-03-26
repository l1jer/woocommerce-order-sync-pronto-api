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
                xhr.send("action=wcospa_clear_all_sync_data&nonce=" + encodeURIComponent(wcospaAdmin.nonce));
            }
        });
    }

    // Environment Toggle Button
    var toggleEnvButton = document.getElementById("wcospa-toggle-environment");
    if (toggleEnvButton) {
        toggleEnvButton.addEventListener("click", function () {
            const currentEnv = this.getAttribute("data-current");
            const nonce = this.getAttribute("data-nonce");
            const button = this;
            
            // Disable button during request
            button.disabled = true;
            button.classList.add("wcospa-loading");
            
            // Prepare form data
            const formData = new FormData();
            formData.append('action', 'wcospa_toggle_environment');
            formData.append('current', currentEnv);
            formData.append('nonce', nonce);
            
            // Make the AJAX request
            fetch(ajaxurl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI
                    const envLabel = document.querySelector('.wcospa-env-label');
                    envLabel.textContent = data.data.is_production ? 'Production' : 'Test';
                    envLabel.classList.remove('env-production', 'env-test');
                    envLabel.classList.add(data.data.is_production ? 'env-production' : 'env-test');
                    
                    // Update button text and data
                    button.textContent = data.data.is_production ? 'Switch to Test Environment' : 'Switch to Production Environment';
                    button.setAttribute('data-current', data.data.environment);
                    
                    // Show success message
                    showMessage('success', data.data.message);
                } else {
                    showMessage('error', data.data || 'Failed to update environment');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('error', 'Error updating environment settings');
            })
            .finally(() => {
                // Re-enable button
                button.disabled = false;
                button.classList.remove("wcospa-loading");
            });
        });
    }

    // Debtor Code Update Button
    var updateDebtorButton = document.getElementById("wcospa-update-debtor-code");
    if (updateDebtorButton) {
        updateDebtorButton.addEventListener("click", function () {
            const codeInput = document.getElementById('wcospa-debtor-code');
            const code = codeInput.value.trim();
            const nonce = this.getAttribute("data-nonce");
            const button = this;
            
            if (!code) {
                showMessage('error', 'Debtor code cannot be empty');
                return;
            }
            
            // Disable button during request
            button.disabled = true;
            button.classList.add("wcospa-loading");
            
            // Prepare form data
            const formData = new FormData();
            formData.append('action', 'wcospa_update_debtor_code');
            formData.append('code', code);
            formData.append('nonce', nonce);
            
            // Make the AJAX request
            fetch(ajaxurl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI
                    document.getElementById('current-debtor-code').textContent = data.data.code;
                    
                    // Show success message
                    showMessage('success', data.data.message);
                } else {
                    showMessage('error', data.data || 'Failed to update debtor code');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('error', 'Error updating debtor code');
            })
            .finally(() => {
                // Re-enable button
                button.disabled = false;
                button.classList.remove("wcospa-loading");
            });
        });
    }

    // Afterpay Code Update Button
    var updateAfterpayButton = document.getElementById("wcospa-update-afterpay-code");
    if (updateAfterpayButton) {
        updateAfterpayButton.addEventListener("click", function () {
            const codeInput = document.getElementById('wcospa-afterpay-code');
            const code = codeInput.value.trim();
            const nonce = this.getAttribute("data-nonce");
            const button = this;
            
            if (!code) {
                showMessage('error', 'Afterpay code cannot be empty');
                return;
            }
            
            // Disable button during request
            button.disabled = true;
            button.classList.add("wcospa-loading");
            
            // Prepare form data
            const formData = new FormData();
            formData.append('action', 'wcospa_update_afterpay_code');
            formData.append('code', code);
            formData.append('nonce', nonce);
            
            // Make the AJAX request
            fetch(ajaxurl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI
                    document.getElementById('current-afterpay-code').textContent = data.data.code;
                    
                    // Show success message
                    showMessage('success', data.data.message);
                } else {
                    showMessage('error', data.data || 'Failed to update Afterpay code');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('error', 'Error updating Afterpay code');
            })
            .finally(() => {
                // Re-enable button
                button.disabled = false;
                button.classList.remove("wcospa-loading");
            });
        });
    }

    // Function to show messages
    function showMessage(type, text) {
        // Remove any existing messages
        const existingMessages = document.querySelectorAll('.wcospa-message');
        existingMessages.forEach(msg => msg.remove());
        
        // Create new message
        const message = document.createElement('div');
        message.className = `wcospa-message ${type}`;
        message.textContent = text;
        
        // Insert after the h1
        const h1 = document.querySelector('.wcospa-sync-status h1');
        if (h1) {
            h1.insertAdjacentElement('afterend', message);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                message.remove();
            }, 5000);
        }
    }

    // Fetch Order Buttons handling
    var fetchButtons = document.querySelectorAll(".fetch-order-button");
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
            // Check if we got a successful response with a Pronto order number
            if (data.success && data.data.pronto_order_number) {
                // Success! Update the display and remove the button
                orderNumberDiv.textContent = data.data.pronto_order_number;
                button.closest('.wcospa-fetch-button-wrapper').remove();
            } else {
                // Failed, check if we should retry
                retryCount++;
                if (retryCount < MAX_RETRIES) {
                    orderNumberDiv.textContent = `Retry in ${RETRY_DELAY/1000}s (${retryCount}/${MAX_RETRIES})`;
                    button.classList.remove('loading');
                    
                    // Schedule retry
                    setTimeout(fetchOrderNumber, RETRY_DELAY);
                } else {
                    // Max retries reached
                    const errorMessage = data.data || 'Failed to fetch order number after multiple attempts';
                    orderNumberDiv.textContent = 'Fetch failed';
                    button.classList.remove('loading');
                    button.disabled = false;
                    console.error('Fetch failed after max retries:', errorMessage);
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            orderNumberDiv.textContent = 'Error';
            button.classList.remove('loading');
            button.disabled = false;
            
            // Retry on network errors
            retryCount++;
            if (retryCount < MAX_RETRIES) {
                setTimeout(fetchOrderNumber, RETRY_DELAY);
            }
        });
    }

    // Start the fetch process
    fetchOrderNumber();
}

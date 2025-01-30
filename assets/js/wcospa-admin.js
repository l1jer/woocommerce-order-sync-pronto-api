document.addEventListener("DOMContentLoaded", function () {

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

    // Handle Fetch Order buttons
    document.querySelectorAll('.fetch-order-button').forEach(function(button) {
        button.addEventListener('click', function() {
            if (button.classList.contains('loading')) {
                return;
            }

            const orderId = button.getAttribute('data-order-id');
            const nonce = button.getAttribute('data-nonce');
            const orderColumn = button.closest('.wcospa-order-column');
            const orderNumberDiv = orderColumn.querySelector('.pronto-order-number');

            // Add loading state
            button.classList.add('loading');
            button.disabled = true;
            orderNumberDiv.textContent = 'Fetching...';

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
                if (data.success) {
                    // Update the order number display
                    orderNumberDiv.textContent = data.data.pronto_order_number;
                    // Remove the fetch button as it's no longer needed
                    button.closest('.wcospa-fetch-button-wrapper').remove();
                } else {
                    // Show error in the order number div
                    orderNumberDiv.textContent = 'Fetch failed. Try again.';
                    // Remove loading state
                    button.classList.remove('loading');
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                orderNumberDiv.textContent = 'Fetch failed. Try again.';
                button.classList.remove('loading');
                button.disabled = false;
            });
        });
    });
});

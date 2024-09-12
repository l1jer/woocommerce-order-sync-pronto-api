document.addEventListener('DOMContentLoaded', function () {

    // Clear Sync Logs button
    var clearLogsButton = document.getElementById('wcospa-clear-logs');
    if (clearLogsButton) {
        clearLogsButton.addEventListener('click', function () {
            if (confirm('Are you sure you want to clear all sync logs? This action cannot be undone.')) {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', ajaxurl, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function () {
                    if (xhr.status === 200) {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            alert('Sync logs cleared successfully.');
                            location.reload();
                        } else {
                            alert('Failed to clear sync logs: ' + response.data);
                        }
                    } else {
                        alert('An error occurred: ' + xhr.statusText);
                    }
                };
                xhr.send('action=wcospa_clear_sync_logs');
            }
        });
    }

    // Clear All Sync Data button
    var clearAllSyncDataButton = document.getElementById('wcospa-clear-all-sync-data');
    if (clearAllSyncDataButton) {
        clearAllSyncDataButton.addEventListener('click', function () {
            if (confirm('Are you sure you want to clear all sync data? This will reset the sync status for all orders and cannot be undone.')) {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', ajaxurl, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function () {
                    if (xhr.status === 200) {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            alert('All sync data cleared successfully.');
                            location.reload();
                        } else {
                            alert('Failed to clear all sync data: ' + response.data);
                        }
                    } else {
                        alert('An error occurred: ' + xhr.statusText);
                    }
                };
                xhr.send('action=wcospa_clear_all_sync_data');
            }
        });
    }

    // Sync Order Buttons
    var syncButtons = document.querySelectorAll('.sync-order-button');
    var fetchButtons = document.querySelectorAll('.fetch-order-button');
    syncButtons.forEach(function (button) {
        var orderId = button.getAttribute('data-order-id');
        var syncStatus = localStorage.getItem('sync_status_' + orderId);
        if (syncStatus === 'true') {
            button.textContent = 'Synced';
            button.disabled = true;
            button.title = 'Synced on ' + new Date().toLocaleString(); // Add sync timestamp to tooltip
        }
    });

    syncButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var orderId = button.getAttribute('data-order-id');
            var nonce = button.getAttribute('data-nonce');
            var fetchButton = document.querySelector('.fetch-order-button[data-order-id="' + orderId + '"]');
            var prontoOrderDisplay = document.querySelector('.pronto-order-number[data-order-id="' + orderId + '"]');

            button.textContent = 'Syncing...';
            button.disabled = true;

            var xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxurl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function () {
                if (xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        button.textContent = 'Synced';
                        button.disabled = true;
                        button.title = 'Synced on ' + new Date().toLocaleString(); // Add sync timestamp to tooltip
                        console.log('Sync successful: ', response);

                        // Save sync status in localStorage
                        localStorage.setItem('sync_status_' + orderId, 'true');

                        // Start 120-second countdown for Fetch button
                        var countdown = 120;
                        fetchButton.title = "This button will be activated 120 seconds after a successful sync."; // Add tooltip
                        var countdownInterval = setInterval(function () {
                            if (countdown > 0) {
                                fetchButton.textContent = countdown + 's';
                                fetchButton.disabled = true;
                                countdown--;
                            } else {
                                clearInterval(countdownInterval);
                                fetchButton.textContent = 'Fetch';
                                fetchButton.disabled = false;
                                fetchButton.title = ''; // Remove tooltip when enabled
                            }
                        }, 1000);
                    } else {
                        button.textContent = 'Failed: ' + response.data;
                        button.disabled = false;
                        console.log('Sync failed: ', response.data);
                    }
                } else {
                    button.textContent = 'Retry Sync';
                    button.disabled = false;
                    console.error('Sync error: ', xhr.statusText);
                }
            };

            var data = 'action=wcospa_sync_order&order_id=' + encodeURIComponent(orderId) + '&security=' + encodeURIComponent(nonce);
            xhr.send(data);
        });
    });

    fetchButtons.forEach(function (button) {
        var orderId = button.getAttribute('data-order-id');
        var syncTime = button.getAttribute('data-sync-time');
        var nonce = button.getAttribute('data-nonce');
        var prontoOrderDisplay = document.querySelector('.pronto-order-number[data-order-id="' + orderId + '"]');

        // If there's a sync time, handle the countdown even after reload
        if (syncTime) {
            var remainingTime = 120 - Math.floor(Date.now() / 1000 - syncTime);
            if (remainingTime > 0) {
                button.disabled = true;
                var countdownInterval = setInterval(function () {
                    if (remainingTime > 0) {
                        button.textContent = remainingTime + 's';
                        remainingTime--;
                    } else {
                        clearInterval(countdownInterval);
                        button.textContent = 'Fetch';
                        button.disabled = false;
                        button.title = ''; // Remove tooltip
                    }
                }, 1000);
            }
        }

        button.addEventListener('click', function () {
            button.textContent = 'Fetching...';
            button.disabled = true;

            var xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxurl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function () {
                if (xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        button.textContent = 'Fetched';
                        prontoOrderDisplay.textContent = response.data; // Update the Pronto Order number display
                        console.log('Fetch successful: ', response);
                    } else {
                        button.textContent = 'Fetch';
                        button.disabled = false;
                        console.log('Fetch failed: ', response.data);
                    }
                } else {
                    button.textContent = 'Fetch';
                    button.disabled = false;
                    console.error('Fetch error: ', xhr.statusText);
                }
            };

            var data = 'action=wcospa_fetch_pronto_order&order_id=' + encodeURIComponent(orderId) + '&security=' + encodeURIComponent(nonce);
            xhr.send(data);
        });
    });
});

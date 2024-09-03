document.addEventListener('DOMContentLoaded', function () {
    var syncButtons = document.querySelectorAll('.sync-order-button');
    var fetchButtons = document.querySelectorAll('.fetch-order-button');

    syncButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var orderId = button.getAttribute('data-order-id');
            var nonce = button.getAttribute('data-nonce');

            button.textContent = 'Syncing...';
            button.disabled = true;

            var xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxurl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function () {
                if (xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        button.textContent = 'Already Synced';
                        console.log('Sync successful: ', response);
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

        if (syncTime) {
            var remainingTime = 120 - (Date.now() / 1000 - syncTime);

            if (remainingTime > 0) {
                button.disabled = true;
                updateFetchButton(button, remainingTime, orderId, nonce);
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

    function updateFetchButton(button, remainingTime, orderId, nonce) {
        if (remainingTime > 0) {
            button.textContent = 'Fetch in ' + remainingTime + 's';
            setTimeout(function () {
                updateFetchButton(button, remainingTime - 1, orderId, nonce);
            }, 1000);
        } else {
            button.textContent = 'Fetch';
            button.disabled = false;
        }
    }
});

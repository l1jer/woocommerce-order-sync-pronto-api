document.addEventListener('DOMContentLoaded', function () {
    var syncButtons = document.querySelectorAll('.sync-order-button');

    syncButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var orderId = button.getAttribute('data-order-id');
            var nonce = button.getAttribute('data-nonce');
            var prontoOrderField = document.querySelector('#post-' + orderId + ' .column-pronto_order_number');

            button.textContent = 'Syncing...';
            button.disabled = true;

            prontoOrderField.textContent = 'Pending';

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
                        prontoOrderField.textContent = '-';
                        console.log('Sync failed: ', response.data);
                    }
                } else {
                    button.textContent = 'Retry Sync';
                    button.disabled = false;
                    prontoOrderField.textContent = '-';
                    console.error('Sync error: ', xhr.statusText);
                }
            };

            var data = 'action=wcospa_sync_order&order_id=' + encodeURIComponent(orderId) + '&security=' + encodeURIComponent(nonce);
            xhr.send(data);
        });
    });
});

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
});

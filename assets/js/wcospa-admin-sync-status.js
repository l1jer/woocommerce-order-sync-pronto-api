jQuery(document).ready(function ($) {
    // Existing Clear Sync Logs button
    $('#wcospa-clear-logs').click(function () {
        if (confirm('Are you sure you want to clear all sync logs? This action cannot be undone.')) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wcospa_clear_sync_logs'
                },
                success: function (response) {
                    if (response.success) {
                        alert('Sync logs cleared successfully.');
                        location.reload();
                    } else {
                        alert('Failed to clear sync logs: ' + response.data);
                    }
                },
                error: function (xhr, status, error) {
                    alert('An error occurred: ' + error);
                }
            });
        }
    });

    // New Clear All Sync Data button
    $('#wcospa-clear-all-sync-data').click(function () {
        if (confirm('Are you sure you want to clear all sync data? This will reset the sync status for all orders and cannot be undone.')) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wcospa_clear_all_sync_data'
                },
                success: function (response) {
                    if (response.success) {
                        alert('All sync data cleared successfully.');
                        location.reload();
                    } else {
                        alert('Failed to clear all sync data: ' + response.data);
                    }
                },
                error: function (xhr, status, error) {
                    alert('An error occurred: ' + error);
                }
            });
        }
    });
});

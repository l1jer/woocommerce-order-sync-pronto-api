jQuery(document).ready(function ($) {
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
});

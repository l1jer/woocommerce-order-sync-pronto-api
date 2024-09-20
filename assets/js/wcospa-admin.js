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

    fetchButtons.forEach(function (button) {
        var orderId = button.getAttribute("data-order-id");
        var syncTime = button.getAttribute("data-sync-time");
        var nonce = button.getAttribute("data-nonce");
        var prontoOrderDisplay = document.querySelector(
            '.pronto-order-number[data-order-id="' + orderId + '"]'
        );

        if (syncTime) {
            var remainingTime = 120 - Math.floor(Date.now() / 1000 - syncTime);
            if (remainingTime > 0) {
                button.disabled = true;
                var countdownInterval = setInterval(function () {
                    if (remainingTime > 0) {
                        button.textContent = remainingTime + "s";
                        remainingTime--;
                    } else {
                        clearInterval(countdownInterval);
                        button.textContent = "Fetch";
                        button.disabled = false;
                        button.title = "";
                    }
                }, 1000);
            }
        }

        fetchButtons.forEach(function (button) {
            button.addEventListener("click", function () {
                var orderId = button.getAttribute("data-order-id");
                var nonce = button.getAttribute("data-nonce");
                var prontoOrderDisplay = document.querySelector('.pronto-order-number');

                var xhr = new XMLHttpRequest();
                xhr.open("POST", ajaxurl, true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.onload = function () {
                    if (xhr.status === 200) {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            prontoOrderDisplay.textContent = "Pronto Order Number: " + response.pronto_order_number;
                        }
                    }
                };

                xhr.send("action=wcospa_fetch_pronto_order&order_id=" + encodeURIComponent(orderId) + "&security=" + encodeURIComponent(nonce));
            });
        });

    });
});

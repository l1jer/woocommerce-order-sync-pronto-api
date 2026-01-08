jQuery(document).ready(function($) {
    'use strict';

    // Global variables for tracking progress
    let isProcessing = false;
    let afterOrderId = 0;
    let processedCount = 0;
    let totalEligible = 0;
    let totalResults = [];
    let progressModal = null;

    // Initialize bulk shipment button
    $('#wcospa-bulk-shipment').on('click', function(e) {
        e.preventDefault();
        
        if (isProcessing) {
            return;
        }

        // Reset variables
        isProcessing = true;
        afterOrderId = 0;
        processedCount = 0;
        totalEligible = parseInt($(this).data('eligible-count'), 10) || 0;
        totalResults = [];
        
        // Show progress modal
        showProgressModal();
        
        // Start processing
        processNextOrder();
    });

    /**
     * Process the next eligible order (one order per request)
     */
    function processNextOrder() {
        if (!isProcessing) {
            return;
        }

        // Update progress display
        updateProgressDisplay();
        updateProgressBar(processedCount, totalEligible);

        $.ajax({
            url: wcospaBulkShipment.ajaxurl,
            type: 'POST',
            data: {
                action: 'wcospa_bulk_shipment',
                after_order_id: afterOrderId,
                nonce: wcospaBulkShipment.nonce
            },
            success: function(response) {
                if (response.success) {
                    handleOrderResponse(response.data);
                } else {
                    handleError(response.data || wcospaBulkShipment.strings.error);
                }
            },
            error: function(xhr, status, error) {
                handleError('AJAX Error: ' + error);
            }
        });
    }

    /**
     * Handle single-order response
     */
    function handleOrderResponse(data) {
        if (!data) {
            handleError('Invalid response from server');
            return;
        }

        // If orders became ineligible between page load and click, backend may complete with no result.
        if (data.status === 'complete' && !data.result) {
            showFinalSummary();
            return;
        }

        if (!data.result) {
            handleError('Invalid response from server');
            return;
        }

        // Track results
        totalResults.push(data.result);
        processedCount++;
        
        // Update progress
        updateProgressBar(processedCount, totalEligible);

        // Show individual result
        showChunkResults([data.result], data.order_processing_time || 0);
        
        // Prepare for next request
        afterOrderId = parseInt(data.after_order_id, 10) || afterOrderId;
        
        if (data.status === 'complete') {
            showFinalSummary();
            return;
        }

        if (data.has_more) {
            // Respect API rate limits (server call itself may take > 1 second)
            setTimeout(processNextOrder, 200);
            return;
        }

            handleError('Unexpected processing state');
    }

    /**
     * Show progress modal
     */
    function showProgressModal() {
        const modalHtml = `
            <div id="wcospa-bulk-shipment-modal" class="wcospa-modal">
                <div class="wcospa-modal-content">
                    <div class="wcospa-modal-header">
                        <h3>${wcospaBulkShipment.strings.processing}</h3>
                        <button type="button" class="wcospa-modal-close">&times;</button>
                    </div>
                    <div class="wcospa-modal-body">
                        <div class="wcospa-progress-container">
                            <div class="wcospa-progress-bar">
                                <div class="wcospa-progress-fill" style="width: 0%"></div>
                            </div>
                            <div class="wcospa-progress-text">0 / ${totalEligible} orders processed</div>
                        </div>
                        <div class="wcospa-results-container">
                            <h4>Processing Results:</h4>
                            <div class="wcospa-results-list"></div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(modalHtml);
        progressModal = $('#wcospa-bulk-shipment-modal');
        
        // Handle close button
        progressModal.find('.wcospa-modal-close').on('click', function() {
            if (confirm('Are you sure you want to stop processing?')) {
                isProcessing = false;
                hideProgressModal();
            }
        });
    }

    /**
     * Hide progress modal
     */
    function hideProgressModal() {
        if (progressModal) {
            progressModal.remove();
            progressModal = null;
        }
    }

    /**
     * Update progress display
     */
    function updateProgressDisplay() {
        if (progressModal) {
            progressModal.find('.wcospa-modal-header h3').text(wcospaBulkShipment.strings.processing);
        }
    }

    /**
     * Update progress bar
     */
    function updateProgressBar(processed, total) {
        if (progressModal) {
            const percentage = total > 0 ? Math.round((processed / total) * 100) : 0;
            progressModal.find('.wcospa-progress-fill').css('width', percentage + '%');
            progressModal.find('.wcospa-progress-text').text(`${processed} / ${total} orders processed`);
        }
    }

    /**
     * Show chunk results
     */
    function showChunkResults(results, processingTime) {
        if (!progressModal) return;

        const resultsContainer = progressModal.find('.wcospa-results-list');
        
        results.forEach(function(result) {
            // Determine status display text and CSS class
            let statusText = wcospaBulkShipment.strings.failed;
            let statusClass = result.status;
            
            if (result.status === 'success') {
                statusText = wcospaBulkShipment.strings.success;
            } else if (result.status === 'timeout') {
                statusText = 'Timeout (524)';
                statusClass = 'timeout';
            } else if (result.status === 'server_error') {
                statusText = 'Server Error';
                statusClass = 'server-error';
            }
            
            const resultHtml = `
                <div class="wcospa-result-item wcospa-result-${statusClass}">
                    <div class="wcospa-result-header">
                        <span class="wcospa-result-order">Order #${result.order_id}</span>
                        <span class="wcospa-result-status">${statusText}</span>
                        <span class="wcospa-result-time">${result.processing_time}ms</span>
                    </div>
                    <div class="wcospa-result-details">
                        <div class="wcospa-result-pronto">Pronto: ${result.pronto_order_number}</div>
                        ${result.shipment_number ? `<div class="wcospa-result-shipment">Shipment: ${result.shipment_number}</div>` : ''}
                        <div class="wcospa-result-message">${result.message}</div>
                        ${result.error_type ? `<div class="wcospa-result-error-type">Error Type: ${result.error_type}</div>` : ''}
                    </div>
                </div>
            `;
            
            resultsContainer.append(resultHtml);
        });

        // Scroll to bottom to show latest results
        resultsContainer.scrollTop(resultsContainer[0].scrollHeight);
    }

    /**
     * Show final summary
     */
    function showFinalSummary() {
        if (!progressModal) return;

        const successCount = totalResults.filter(r => r.status === 'success').length;
        const errorCount = totalResults.filter(r => r.status === 'error').length;
        const timeoutCount = totalResults.filter(r => r.status === 'timeout').length;
        const serverErrorCount = totalResults.filter(r => r.status === 'server_error').length;
        const totalTime = totalResults.reduce((sum, r) => sum + r.processing_time, 0);

        const summaryHtml = `
            <div class="wcospa-summary">
                <h4>Processing Complete!</h4>
                <div class="wcospa-summary-stats">
                    <div class="wcospa-summary-stat">
                        <span class="wcospa-summary-label">Total Orders:</span>
                        <span class="wcospa-summary-value">${totalResults.length}</span>
                    </div>
                    <div class="wcospa-summary-stat">
                        <span class="wcospa-summary-label">Successful:</span>
                        <span class="wcospa-summary-value wcospa-success">${successCount}</span>
                    </div>
                    <div class="wcospa-summary-stat">
                        <span class="wcospa-summary-label">Failed:</span>
                        <span class="wcospa-summary-value wcospa-error">${errorCount}</span>
                    </div>
                    ${timeoutCount > 0 ? `<div class="wcospa-summary-stat">
                        <span class="wcospa-summary-label">Timeouts (524):</span>
                        <span class="wcospa-summary-value wcospa-timeout">${timeoutCount}</span>
                    </div>` : ''}
                    ${serverErrorCount > 0 ? `<div class="wcospa-summary-stat">
                        <span class="wcospa-summary-label">Server Errors:</span>
                        <span class="wcospa-summary-value wcospa-server-error">${serverErrorCount}</span>
                    </div>` : ''}
                    <div class="wcospa-summary-stat">
                        <span class="wcospa-summary-label">Total Time:</span>
                        <span class="wcospa-summary-value">${Math.round(totalTime)}ms</span>
                    </div>
                </div>
                ${timeoutCount > 0 ? '<div class="wcospa-timeout-notice"><strong>Note:</strong> Timeout errors (524) indicate that the server took longer than 2 minutes to respond. These orders may still be processing on the server.</div>' : ''}
            </div>
        `;

        progressModal.find('.wcospa-modal-header h3').text(wcospaBulkShipment.strings.complete);
        progressModal.find('.wcospa-results-container').prepend(summaryHtml);

        // Add close button functionality
        progressModal.find('.wcospa-modal-close').off('click').on('click', function() {
            hideProgressModal();
            location.reload(); // Refresh page to show updated data
        });

        isProcessing = false;
    }

    /**
     * Handle errors
     */
    function handleError(message) {
        console.error('Bulk shipment error:', message);
        
        if (progressModal) {
            progressModal.find('.wcospa-modal-header h3').text('Error');
            progressModal.find('.wcospa-results-container').prepend(`
                <div class="wcospa-error-message">
                    <strong>Error:</strong> ${message}
                </div>
            `);
        }

        isProcessing = false;
        
        // Show error notice
        if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
            wp.data.dispatch('core/notices').createErrorNotice(message);
        }
    }
}); 
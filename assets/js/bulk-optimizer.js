// File: assets/js/bulk-optimizer.js

jQuery(document).ready(function($) {
    var MIOBulkOptimizer = {
        totalImages: 0,
        processedImages: 0,
        currentOffset: 0,
        isRunning: false,

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('#mio-start-bulk-optimization').on('click', this.startOptimization.bind(this));
        },

        startOptimization: function() {
            if (this.isRunning) return;

            this.isRunning = true;
            this.showProgressContainer();
            this.processNextBatch();
        },

        processNextBatch: function() {
            $.ajax({
                url: mio_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'mio_bulk_optimize_batch',
                    nonce: mio_ajax.nonce,
                    offset: this.currentOffset
                },
                success: this.handleBatchResponse.bind(this),
                error: this.handleError.bind(this)
            });
        },

        handleBatchResponse: function(response) {
            if (response.success) {
                this.updateProgress(response.data);
                if (response.data.has_more) {
                    this.currentOffset = response.data.next_offset;
                    setTimeout(this.processNextBatch.bind(this), 1000);
                } else {
                    this.completeOptimization();
                }
            } else {
                this.handleError(response.data.message);
            }
        },

        updateProgress: function(data) {
            var percentage = (data.processed / data.total) * 100;
            $('.mio-progress-bar').css('width', percentage + '%').text(Math.round(percentage) + '%');
            $('#mio-processed-count').text(data.processed);
            $('#mio-total-count').text(data.total);
        },

        showProgressContainer: function() {
            $('.mio-progress-container').show();
            $('#mio-start-bulk-optimization').hide();
        },

        completeOptimization: function() {
            this.isRunning = false;
            $('.mio-progress-bar').addClass('complete');
            $('#mio-optimization-status').text('Optimization completed!');
        },

        handleError: function(message) {
            this.isRunning = false;
            alert('Error: ' + (message || 'Unknown error occurred'));
        }
    };

    MIOBulkOptimizer.init();
});

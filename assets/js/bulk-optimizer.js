// File: assets/js/bulk-optimizer.js

jQuery(document).ready(function($) {
    const BulkOptimizer = {
        isRunning: false,
        isPaused: false,
        totalImages: 0,
        processedImages: 0,
        currentOffset: 0,
        sessionSavings: 0,
        startTime: null,

        elements: {
            startBtn: $('#mio-start-optimization'),
            pauseBtn: $('#mio-pause-optimization'),
            progressContainer: $('#mio-progress-container'),
            progressBar: $('#mio-progress-bar'),
            progressText: $('.mio-progress-text'),
            progressCount: $('#mio-progress-count'),
            currentImage: $('#mio-current-image'),
            sessionSavings: $('#mio-session-savings'),
            optimizationSpeed: $('#mio-optimization-speed'),
            log: $('#mio-log')
        },

        init() {
            this.bindEvents();
            this.loadInitialStats();
        },

        bindEvents() {
            this.elements.startBtn.on('click', () => this.startOptimization());
            this.elements.pauseBtn.on('click', () => this.pauseOptimization());
        },

        async loadInitialStats() {
            try {
                const response = await this.makeAjaxRequest('mio_get_bulk_stats');
                if (response.success) {
                    this.totalImages = response.data.unoptimized_images;
                }
            } catch (error) {
                console.error('Failed to load stats:', error);
            }
        },

        async startOptimization() {
            if (this.isRunning) return;

            this.isRunning = true;
            this.isPaused = false;
            this.startTime = Date.now();
            this.sessionSavings = 0;
            this.processedImages = 0;
            this.currentOffset = 0;

            this.updateUI('starting');

            if (this.totalImages === 0) {
                await this.loadInitialStats();
                if (this.totalImages === 0) {
                    this.showMessage(mio_bulk.strings.no_images, 'notice-info');
                    this.isRunning = false;
                    return;
                }
            }

            this.elements.progressContainer.show();
            this.processNextBatch();
        },

        async processNextBatch() {
            if (this.isPaused || !this.isRunning) return;

            try {
                const response = await this.makeAjaxRequest('mio_bulk_optimize_batch', {
                    offset: this.currentOffset
                });

                if (response.success) {
                    this.handleBatchResponse(response.data);
                } else {
                    throw new Error(response.data?.message || 'Unknown error');
                }
            } catch (error) {
                this.handleError(error);
            }
        },

        handleBatchResponse(data) {
            const results = data.batch_results;

            this.processedImages += data.processed_count;
            this.sessionSavings += results.total_savings;
            this.currentOffset = data.next_offset;

            this.updateProgress();
            this.updateLog(results.log);
            this.updateStats();

            if (data.has_more && !this.isPaused) {
                setTimeout(() => this.processNextBatch(), 1000);
            } else {
                this.completeOptimization();
            }
        },

        updateProgress() {
            const percentage = this.totalImages > 0 ?
                Math.round((this.processedImages / this.totalImages) * 100) : 0;

            this.elements.progressBar.css('width', percentage + '%');
            this.elements.progressText.text(percentage + '%');
            this.elements.progressCount.text(`${this.processedImages} / ${this.totalImages}`);
        },

        updateLog(logEntries) {
            logEntries.forEach(entry => {
                const logClass = `mio-log-${entry.type}`;
                this.elements.log.append(`<div class="${logClass}">${entry.message}</div>`);
            });

            this.elements.log.scrollTop(this.elements.log[0].scrollHeight);
        },

        updateStats() {
            this.elements.sessionSavings.text(this.formatFileSize(this.sessionSavings));

            if (this.startTime && this.processedImages > 0) {
                const elapsed = (Date.now() - this.startTime) / 1000 / 60; // minutes
                const speed = Math.round(this.processedImages / elapsed);
                this.elements.optimizationSpeed.text(`${speed} img/min`);
            }
        },

        updateUI(state) {
            switch (state) {
                case 'starting':
                    this.elements.startBtn.hide();
                    this.elements.pauseBtn.show();
                    this.elements.currentImage.text(mio_bulk.strings.starting);
                    break;
                case 'paused':
                    this.elements.pauseBtn.hide();
                    this.elements.startBtn.show().text('Resume Optimization');
                    this.elements.currentImage.text(mio_bulk.strings.paused);
                    break;
                case 'completed':
                    this.elements.pauseBtn.hide();
                    this.elements.startBtn.show().text('Start New Optimization');
                    this.elements.currentImage.text(mio_bulk.strings.completed);
                    break;
            }
        },

        async pauseOptimization() {
            if (!confirm(mio_bulk.strings.confirm_pause)) return;

            this.isPaused = true;
            this.isRunning = false;

            try {
                await this.makeAjaxRequest('mio_pause_bulk_optimization');
                this.updateUI('paused');
            } catch (error) {
                console.error('Failed to pause:', error);
            }
        },

        completeOptimization() {
            this.isRunning = false;
            this.updateUI('completed');
            this.showMessage(mio_bulk.strings.completed, 'notice-success');
        },

        handleError(error) {
            this.isRunning = false;
            this.elements.pauseBtn.hide();
            this.elements.startBtn.show();
            this.showMessage(`${mio_bulk.strings.error}: ${error.message}`, 'notice-error');
            console.error('Bulk optimization error:', error);
        },

        showMessage(message, type = 'notice-info') {
            const notice = $(`<div class="notice ${type} is-dismissible"><p>${message}</p></div>`);
            $('.wrap h1').after(notice);
            setTimeout(() => notice.fadeOut(), 5000);
        },

        formatFileSize(bytes) {
            const units = ['B', 'KB', 'MB', 'GB'];
            let size = bytes;
            let unitIndex = 0;

            while (size >= 1024 && unitIndex < units.length - 1) {
                size /= 1024;
                unitIndex++;
            }

            return `${Math.round(size * 100) / 100} ${units[unitIndex]}`;
        },

        makeAjaxRequest(action, data = {}) {
            return new Promise((resolve, reject) => {
                $.post(mio_bulk.ajax_url, {
                    action,
                    nonce: mio_bulk.nonce,
                    ...data
                })
                .done(resolve)
                .fail((xhr, status, error) => reject(new Error(error)));
            });
        }
    };

    BulkOptimizer.init();
});

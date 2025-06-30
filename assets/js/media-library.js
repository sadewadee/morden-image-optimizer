// File: assets/js/media-library.js

jQuery(document).ready(function($) {
    const MediaLibraryOptimizer = {
        init() {
            this.bindEvents();
        },

        bindEvents() {
            $(document).on('click', '.mio-optimize-btn', (e) => this.handleOptimize(e));
            $(document).on('click', '.mio-restore-btn', (e) => this.handleRestore(e));
        },

        async handleOptimize(e) {
            e.preventDefault();

            const $btn = $(e.target);
            const attachmentId = $btn.data('id');
            const nonce = $btn.data('nonce');

            if (!attachmentId || !nonce) return;

            const originalText = $btn.text();
            $btn.prop('disabled', true).text(mio_media.strings.optimizing);

            try {
                const response = await this.makeAjaxRequest('mio_optimize_single_media', {
                    attachment_id: attachmentId,
                    nonce: nonce
                });

                if (response.success) {
                    this.updateColumnContent(attachmentId, response.data.html);
                    this.showNotice(response.data.message, 'success');
                } else {
                    throw new Error(response.data.message);
                }
            } catch (error) {
                this.showNotice(`${mio_media.strings.error}: ${error.message}`, 'error');
                $btn.prop('disabled', false).text(originalText);
            }
        },

        async handleRestore(e) {
            e.preventDefault();

            if (!confirm(mio_media.strings.confirm_restore)) return;

            const $btn = $(e.target);
            const attachmentId = $btn.data('id');
            const nonce = $btn.data('nonce');

            if (!attachmentId || !nonce) return;

            const originalText = $btn.text();
            $btn.prop('disabled', true).text(mio_media.strings.restoring);

            try {
                const response = await this.makeAjaxRequest('mio_restore_single_media', {
                    attachment_id: attachmentId,
                    nonce: nonce
                });

                if (response.success) {
                    this.updateColumnContent(attachmentId, response.data.html);
                    this.showNotice(response.data.message, 'success');
                } else {
                    throw new Error(response.data.message);
                }
            } catch (error) {
                this.showNotice(`${mio_media.strings.error}: ${error.message}`, 'error');
                $btn.prop('disabled', false).text(originalText);
            }
        },

        updateColumnContent(attachmentId, html) {
            const $row = $(`tr#post-${attachmentId}`);
            const $column = $row.find('.column-mio_optimization');
            $column.html(html);
        },

        showNotice(message, type = 'info') {
            const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            const $notice = $(`<div class="notice ${noticeClass} is-dismissible"><p>${message}</p></div>`);

            $('.wrap h1').first().after($notice);

            setTimeout(() => {
                $notice.fadeOut(() => $notice.remove());
            }, 4000);
        },

        makeAjaxRequest(action, data = {}) {
            return new Promise((resolve, reject) => {
                $.post(mio_media.ajax_url, {
                    action,
                    ...data
                })
                .done(resolve)
                .fail((xhr, status, error) => reject(new Error(error)));
            });
        }
    };

    MediaLibraryOptimizer.init();
});


jQuery(document).ready(function($) {
    const OnboardingHandler = {
        init() {
            this.bindEvents();
        },

        bindEvents() {
            $('.mio-setup-action').on('click', this.handleSetupAction.bind(this));
            $('.mio-dismiss-notice').on('click', this.handleDismissNotice.bind(this));
        },

        async handleSetupAction(e) {
            e.preventDefault();

            const $btn = $(e.target);
            const stepId = $btn.data('step');
            const action = $btn.data('action');

            switch (action) {
                case 'mark_completed':
                    await this.markStepCompleted(stepId);
                    break;
                case 'open_settings':
                    window.location.href = 'options-general.php?page=morden_optimizer';
                    break;
                case 'enable_backup':
                    await this.enableBackup(stepId);
                    break;
                case 'run_test':
                    await this.runTest(stepId);
                    break;
            }
        },

        async markStepCompleted(stepId) {
            try {
                await this.makeAjaxRequest('mio_complete_setup_step', { step_id: stepId });
                this.updateStepUI(stepId, true);
                this.updateProgress();
            } catch (error) {
                console.error('Failed to mark step as completed:', error);
            }
        },

        async enableBackup(stepId) {
            try {
                // Enable backup setting via AJAX
                await this.makeAjaxRequest('mio_enable_backup_setting');
                await this.markStepCompleted(stepId);
            } catch (error) {
                console.error('Failed to enable backup:', error);
            }
        },

        async runTest(stepId) {
            try {
                // Run optimization test
                await this.makeAjaxRequest('mio_run_optimization_test');
                await this.markStepCompleted(stepId);
            } catch (error) {
                console.error('Failed to run test:', error);
            }
        },

        updateStepUI(stepId, completed) {
            const $step = $(`.mio-setup-step[data-step="${stepId}"]`);
            if (completed) {
                $step.addClass('completed');
                $step.find('.mio-step-number').replaceWith('<span class="dashicons dashicons-yes-alt"></span>');
                $step.find('.mio-setup-action').remove();
            }
        },

        updateProgress() {
            const totalSteps = $('.mio-setup-step').length;
            const completedSteps = $('.mio-setup-step.completed').length;
            const percentage = (completedSteps / totalSteps) * 100;

            $('.mio-progress-fill').css('width', percentage + '%');
            $('.mio-progress-text').text(`${completedSteps} of ${totalSteps} setup steps completed`);
        },

        makeAjaxRequest(action, data = {}) {
            return new Promise((resolve, reject) => {
                $.post(ajaxurl, {
                    action,
                    nonce: mio_onboarding.nonce,
                    ...data
                })
                .done(resolve)
                .fail((xhr, status, error) => reject(new Error(error)));
            });
        }
    };

    OnboardingHandler.init();
});
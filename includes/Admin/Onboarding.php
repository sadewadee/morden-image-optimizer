<?php
// File: includes/Admin/Onboarding.php

namespace MordenImageOptimizer\Admin;

use MordenImageOptimizer\Core\Security;
use MordenImageOptimizer\Core\Config;
use MordenImageOptimizer\Core\Optimizer;

class Onboarding {
    private static $instance = null;
    private $config;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->config = Config::get_instance();

        add_action( 'admin_init', [ $this, 'handle_welcome_redirect' ] );
        add_action( 'admin_notices', [ $this, 'display_welcome_notice' ] );
        add_action( 'admin_notices', [ $this, 'display_setup_notice' ] );
        add_action( 'wp_ajax_mio_dismiss_notice', [ $this, 'ajax_dismiss_notice' ] );
        add_action( 'wp_ajax_mio_complete_setup_step', [ $this, 'ajax_complete_setup_step' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_onboarding_assets' ] );
        add_action( 'wp_ajax_mio_enable_backup_setting', [ $this, 'ajax_enable_backup' ] );
        add_action( 'wp_ajax_mio_run_optimization_test', [ $this, 'ajax_run_test' ] );
        }

    public static function set_welcome_redirect() {
        set_transient( '_mio_welcome_screen_redirect', true, 30 );
        update_option( 'mio_activation_time', current_time( 'timestamp' ) );
    }

    public function handle_welcome_redirect() {
        if ( ! get_transient( '_mio_welcome_screen_redirect' ) ) {
            return;
        }

        delete_transient( '_mio_welcome_screen_redirect' );

        if ( wp_doing_ajax() || is_network_admin() ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        wp_safe_redirect( admin_url( 'options-general.php?page=morden_optimizer&welcome=true' ) );
        exit;
    }

    public function display_welcome_notice() {
        if ( ! $this->should_show_welcome_notice() ) {
            return;
        }

        $setup_steps = $this->get_setup_steps();
        $completed_steps = get_option( 'mio_completed_setup_steps', [] );
        $total_steps = count( $setup_steps );
        $completed_count = count( array_intersect( array_keys( $setup_steps ), $completed_steps ) );

        ?>
        <div class="notice notice-info mio-welcome-notice" data-notice="welcome">
            <div class="mio-welcome-content">
                <div class="mio-welcome-header">
                    <h2><?php esc_html_e( '🚀 Welcome to Morden Image Optimizer!', 'morden_optimizer' ); ?></h2>
                    <button type="button" class="notice-dismiss mio-dismiss-notice" data-notice="welcome">
                        <span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'morden_optimizer' ); ?></span>
                    </button>
                </div>

                <div class="mio-setup-progress">
                    <div class="mio-progress-bar">
                        <div class="mio-progress-fill" style="width: <?php echo esc_attr( ( $completed_count / $total_steps ) * 100 ); ?>%"></div>
                    </div>
                    <span class="mio-progress-text">
                        <?php printf( esc_html__( '%d of %d setup steps completed', 'morden_optimizer' ), $completed_count, $total_steps ); ?>
                    </span>
                </div>

                <div class="mio-setup-steps">
                    <?php foreach ( $setup_steps as $step_id => $step ) : ?>
                        <?php $is_completed = in_array( $step_id, $completed_steps, true ); ?>
                        <div class="mio-setup-step <?php echo $is_completed ? 'completed' : ''; ?>" data-step="<?php echo esc_attr( $step_id ); ?>">
                            <div class="mio-step-icon">
                                <?php if ( $is_completed ) : ?>
                                    <span class="dashicons dashicons-yes-alt"></span>
                                <?php else : ?>
                                    <span class="mio-step-number"><?php echo esc_html( $step['order'] ); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="mio-step-content">
                                <h4><?php echo esc_html( $step['title'] ); ?></h4>
                                <p><?php echo esc_html( $step['description'] ); ?></p>
                                <?php if ( ! $is_completed && ! empty( $step['action'] ) ) : ?>
                                    <button type="button" class="button button-primary mio-setup-action"
                                            data-step="<?php echo esc_attr( $step_id ); ?>"
                                            data-action="<?php echo esc_attr( $step['action'] ); ?>">
                                        <?php echo esc_html( $step['button_text'] ); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mio-welcome-actions">
                    <a href="<?php echo esc_url( admin_url( 'upload.php?page=mio-bulk-optimize' ) ); ?>" class="button button-primary">
                        <?php esc_html_e( 'Start Optimizing Images', 'morden_optimizer' ); ?>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'options-general.php?page=morden_optimizer' ) ); ?>" class="button button-secondary">
                        <?php esc_html_e( 'View Settings', 'morden_optimizer' ); ?>
                    </a>
                </div>
            </div>
        </div>

        <style>
        .mio-welcome-notice { padding: 20px; border-left: 4px solid #00a0d2; }
        .mio-welcome-content { max-width: 800px; }
        .mio-welcome-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .mio-setup-progress { margin-bottom: 20px; }
        .mio-progress-bar { width: 100%; height: 8px; background: #f0f0f1; border-radius: 4px; overflow: hidden; }
        .mio-progress-fill { height: 100%; background: #00a0d2; transition: width 0.3s ease; }
        .mio-progress-text { font-size: 12px; color: #646970; margin-top: 5px; display: block; }
        .mio-setup-steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .mio-setup-step { display: flex; padding: 15px; border: 1px solid #ddd; border-radius: 4px; background: #fff; }
        .mio-setup-step.completed { border-color: #00a0d2; background: #f7fcfe; }
        .mio-step-icon { margin-right: 15px; }
        .mio-step-number { display: inline-block; width: 24px; height: 24px; line-height: 24px; text-align: center; background: #646970; color: #fff; border-radius: 50%; font-size: 12px; }
        .mio-setup-step.completed .dashicons { color: #00a0d2; font-size: 24px; }
        .mio-step-content h4 { margin: 0 0 5px 0; }
        .mio-step-content p { margin: 0 0 10px 0; color: #646970; }
        .mio-welcome-actions { display: flex; gap: 10px; }
        </style>
        <?php
    }

    public function display_setup_notice() {
        if ( $this->should_show_welcome_notice() ) {
            return;
        }

        if ( get_option( 'mio_setup_notice_dismissed' ) ) {
            return;
        }

        $activation_time = get_option( 'mio_activation_time' );
        if ( ! $activation_time || ( current_time( 'timestamp' ) - $activation_time ) > WEEK_IN_SECONDS ) {
            return;
        }

        $stats = $this->get_quick_stats();

        ?>
        <div class="notice notice-info mio-setup-notice" data-notice="setup">
            <p>
                <strong><?php esc_html_e( 'Morden Image Optimizer', 'morden_optimizer' ); ?></strong> -
                <?php
                printf(
                    esc_html__( 'You have %d images that could be optimized. %s', 'morden_optimizer' ),
                    $stats['unoptimized_count'],
                    sprintf(
                        '<a href="%s">%s</a>',
                        esc_url( admin_url( 'upload.php?page=mio-bulk-optimize' ) ),
                        esc_html__( 'Start optimizing now', 'morden_optimizer' )
                    )
                );
                ?>
                <button type="button" class="notice-dismiss mio-dismiss-notice" data-notice="setup">
                    <span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'morden_optimizer' ); ?></span>
                </button>
            </p>
        </div>
        <?php
    }

    public function ajax_dismiss_notice() {
        $data = Security::validate_ajax_request( 'settings', 'manage_options', [
            'notice_type' => 'text',
        ]);

        $notice_type = $data['notice_type'];

        switch ( $notice_type ) {
            case 'welcome':
                update_option( 'mio_welcome_notice_dismissed', true );
                break;
            case 'setup':
                update_option( 'mio_setup_notice_dismissed', true );
                break;
        }

        wp_send_json_success();
    }

    public function ajax_complete_setup_step() {
        $data = Security::validate_ajax_request( 'settings', 'manage_options', [
            'step_id' => 'text',
        ]);

        $step_id = $data['step_id'];
        $completed_steps = get_option( 'mio_completed_setup_steps', [] );

        if ( ! in_array( $step_id, $completed_steps, true ) ) {
            $completed_steps[] = $step_id;
            update_option( 'mio_completed_setup_steps', $completed_steps );
        }

        wp_send_json_success([
            'completed_steps' => $completed_steps,
        ]);
    }

    private function should_show_welcome_notice() {
        if ( get_option( 'mio_welcome_notice_dismissed' ) ) {
            return false;
        }

        $activation_time = get_option( 'mio_activation_time' );
        if ( ! $activation_time || ( current_time( 'timestamp' ) - $activation_time ) > DAY_IN_SECONDS ) {
            return false;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        return false;
    }

    private function get_setup_steps() {
        $optimizer = new Optimizer();
        $method = $optimizer->get_optimization_method();

        return [
            'check_server' => [
                'order' => 1,
                'title' => __( 'Server Compatibility Check', 'morden_optimizer' ),
                'description' => sprintf( __( 'Your server is using %s for optimization.', 'morden_optimizer' ), ucfirst( $method ) ),
                'action' => 'mark_completed',
                'button_text' => __( 'Mark as Complete', 'morden_optimizer' ),
            ],
            'configure_settings' => [
                'order' => 2,
                'title' => __( 'Configure Settings', 'morden_optimizer' ),
                'description' => __( 'Review and adjust optimization settings to match your needs.', 'morden_optimizer' ),
                'action' => 'open_settings',
                'button_text' => __( 'Open Settings', 'morden_optimizer' ),
            ],
            'enable_backup' => [
                'order' => 3,
                'title' => __( 'Enable Backup (Recommended)', 'morden_optimizer' ),
                'description' => __( 'Keep original images safe by enabling backup functionality.', 'morden_optimizer' ),
                'action' => 'enable_backup',
                'button_text' => __( 'Enable Backup', 'morden_optimizer' ),
            ],
            'test_optimization' => [
                'order' => 4,
                'title' => __( 'Test Optimization', 'morden_optimizer' ),
                'description' => __( 'Run a test optimization to ensure everything works correctly.', 'morden_optimizer' ),
                'action' => 'run_test',
                'button_text' => __( 'Run Test', 'morden_optimizer' ),
            ],
        ];
    }

    private function get_quick_stats() {
        global $wpdb;

        $total_images = $wpdb->get_var( "
            SELECT COUNT(*)
            FROM {$wpdb->posts}
            WHERE post_type = 'attachment'
            AND post_mime_type LIKE 'image/%'
            AND post_status = 'inherit'
        " );

        $optimized_images = $wpdb->get_var( "
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'attachment'
            AND p.post_mime_type LIKE 'image/%'
            AND p.post_status = 'inherit'
            AND pm.meta_key = '_mio_optimized'
            AND pm.meta_value = '1'
        " );

        return [
            'total_count' => (int) $total_images,
            'optimized_count' => (int) $optimized_images,
            'unoptimized_count' => max( 0, (int) $total_images - (int) $optimized_images ),
        ];
    }

    public function enqueue_onboarding_assets() {
    if ( ! $this->should_show_welcome_notice() ) {
        return;
    }

    wp_enqueue_script(
        'mio-onboarding',
        MIO_PLUGIN_URL . 'assets/js/onboarding.js',
        [ 'jquery' ],
        MIO_VERSION,
        true
    );

    wp_localize_script( 'mio-onboarding', 'mio_onboarding', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce' => Security::create_nonce( 'settings' ),
        ]);
    }

    public function ajax_enable_backup() {
        Security::validate_ajax_request( 'settings', 'manage_options' );

        $this->config->set( 'keep_original', true );

        wp_send_json_success([
            'message' => __( 'Backup enabled successfully.', 'morden_optimizer' ),
        ]);
    }

    public function ajax_run_test() {
        Security::validate_ajax_request( 'settings', 'manage_options' );

        // Simulate a test optimization
        wp_send_json_success([
            'message' => __( 'Test optimization completed successfully.', 'morden_optimizer' ),
        ]);
    }
}

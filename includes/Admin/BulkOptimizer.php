<?php
// File: includes/Admin/BulkOptimizer.php

namespace MordenImageOptimizer\Admin;

use MordenImageOptimizer\Core\Security;
use MordenImageOptimizer\Core\Optimizer;
use MordenImageOptimizer\Core\Logger;
use MordenImageOptimizer\Core\DatabaseManager;
use MordenImageOptimizer\Utils\FileHelper;

class BulkOptimizer {
    private static $instance = null;
    private $logger;
    private $db_manager;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->logger = Logger::get_instance();
        $this->db_manager = DatabaseManager::get_instance();

        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_mio_bulk_optimize_batch', [ $this, 'ajax_optimize_batch' ] );
        add_action( 'wp_ajax_mio_get_bulk_stats', [ $this, 'ajax_get_bulk_stats' ] );
        add_action( 'wp_ajax_mio_pause_bulk_optimization', [ $this, 'ajax_pause_optimization' ] );
    }

    public function add_admin_menu() {
        $page_hook = add_media_page(
            __( 'Bulk Optimize Images', 'morden-image-optimize' ),
            __( 'Bulk Optimize', 'morden-image-optimize' ),
            'upload_files',
            'mio-bulk-optimize',
            [ $this, 'render_bulk_optimize_page' ]
        );

        add_action( "load-$page_hook", [ $this, 'add_contextual_help' ] );
    }

    public function enqueue_assets( $hook_suffix ) {
        if ( 'media_page_mio-bulk-optimize' !== $hook_suffix ) {
            return;
        }

        wp_enqueue_style( 'mio-admin-styles', MIO_PLUGIN_URL . 'assets/css/admin-styles.css', [], MIO_VERSION );
        wp_enqueue_script( 'mio-bulk-optimizer', MIO_PLUGIN_URL . 'assets/js/bulk-optimizer.js', [ 'jquery' ], MIO_VERSION, true );

        wp_localize_script( 'mio-bulk-optimizer', 'mio_bulk', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => Security::create_nonce( 'bulk_optimize' ),
            'strings' => [
                'starting' => __( 'Starting optimization...', 'morden-image-optimize' ),
                'processing' => __( 'Processing images...', 'morden-image-optimize' ),
                'completed' => __( 'Optimization completed!', 'morden-image-optimize' ),
                'paused' => __( 'Optimization paused', 'morden-image-optimize' ),
                'error' => __( 'An error occurred', 'morden-image-optimize' ),
                'confirm_pause' => __( 'Are you sure you want to pause the optimization?', 'morden-image-optimize' ),
                'no_images' => __( 'No unoptimized images found.', 'morden-image-optimize' ),
            ],
        ]);
    }

public function render_bulk_optimize_page() {
    if ( ! current_user_can( 'upload_files' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'morden-image-optimize' ) );
    }

    $stats = $this->get_optimization_stats();
    $system_stats = $this->get_system_stats();
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Bulk Optimize Images', 'morden-image-optimize' ); ?></h1>

        <div class="mio-bulk-container">
                <div class="mio-bulk-main">
                    <div class="mio-stats-overview">
                        <div class="mio-stat-card">
                            <h3><?php esc_html_e( 'Total Images', 'morden-image-optimize' ); ?></h3>
                            <span class="mio-stat-number"><?php echo esc_html( number_format( $stats['total_images'] ) ); ?></span>
                        </div>
                        <div class="mio-stat-card">
                            <h3><?php esc_html_e( 'Optimized', 'morden-image-optimize' ); ?></h3>
                            <span class="mio-stat-number"><?php echo esc_html( number_format( $stats['optimized_images'] ) ); ?></span>
                        </div>
                        <div class="mio-stat-card">
                            <h3><?php esc_html_e( 'Remaining', 'morden-image-optimize' ); ?></h3>
                            <span class="mio-stat-number"><?php echo esc_html( number_format( $stats['unoptimized_images'] ) ); ?></span>
                        </div>
                        <div class="mio-stat-card">
                            <h3><?php esc_html_e( 'Total Savings', 'morden-image-optimize' ); ?></h3>
                            <span class="mio-stat-number"><?php echo esc_html( FileHelper::format_file_size( $stats['total_savings'] ) ); ?></span>
                        </div>
                    </div>

                    <div class="mio-bulk-controls">
                        <?php if ( $stats['unoptimized_images'] > 0 ) : ?>
                            <button id="mio-start-optimization" class="button button-primary button-hero">
                                <?php esc_html_e( 'Start Bulk Optimization', 'morden-image-optimize' ); ?>
                            </button>
                            <button id="mio-pause-optimization" class="button button-secondary" style="display:none;">
                                <?php esc_html_e( 'Pause', 'morden-image-optimize' ); ?>
                            </button>
                        <?php else : ?>
                            <div class="notice notice-success inline">
                                <p><?php esc_html_e( 'All images are already optimized!', 'morden-image-optimize' ); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div id="mio-progress-container" style="display:none;">
                        <div class="mio-progress-wrapper">
                            <div class="mio-progress-bar-container">
                                <div id="mio-progress-bar" class="mio-progress-bar" style="width: 0%;">
                                    <span class="mio-progress-text">0%</span>
                                </div>
                            </div>

                            <div class="mio-progress-stats">
                                <div class="mio-progress-stat">
                                    <strong><?php esc_html_e( 'Progress:', 'morden-image-optimize' ); ?></strong>
                                    <span id="mio-progress-count">0 / 0</span>
                                </div>
                                <div class="mio-progress-stat">
                                    <strong><?php esc_html_e( 'Current:', 'morden-image-optimize' ); ?></strong>
                                    <span id="mio-current-image"><?php esc_html_e( 'Preparing...', 'morden-image-optimize' ); ?></span>
                                </div>
                                <div class="mio-progress-stat">
                                    <strong><?php esc_html_e( 'Savings:', 'morden-image-optimize' ); ?></strong>
                                    <span id="mio-session-savings">0 B</span>
                                </div>
                                <div class="mio-progress-stat">
                                    <strong><?php esc_html_e( 'Speed:', 'morden-image-optimize' ); ?></strong>
                                    <span id="mio-optimization-speed">0 img/min</span>
                                </div>
                            </div>
                        </div>

                        <div class="mio-log-container">
                            <h4><?php esc_html_e( 'Optimization Log', 'morden-image-optimize' ); ?></h4>
                            <div id="mio-log" class="mio-log-scroll"></div>
                        </div>
                    </div>
                </div>
<div class="mio-bulk-sidebar">
                <!-- System status card -->

                    <div class="mio-info-box">
                        <h3><?php esc_html_e( 'Tips', 'morden-image-optimize' ); ?></h3>
                        <ul>
                            <li><?php esc_html_e( 'Keep this page open during optimization', 'morden-image-optimize' ); ?></li>
                            <li><?php esc_html_e( 'Larger images take more time to process', 'morden-image-optimize' ); ?></li>
                            <li><?php esc_html_e( 'You can continue using WordPress normally', 'morden-image-optimize' ); ?></li>
                        </ul>
                    </div>

                    <div class="mio-info-box">
                        <h3><?php esc_html_e( 'Need Help?', 'morden-image-optimize' ); ?></h3>
                        <p>
                            <a href="<?php echo esc_url( admin_url( 'options-general.php?page=morden_optimizer' ) ); ?>" class="button button-secondary">
                                <?php esc_html_e( 'Plugin Settings', 'morden-image-optimize' ); ?>
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function ajax_optimize_batch() {
        $data = Security::validate_ajax_request( 'bulk_optimize', 'upload_files', [
            'offset' => 'int',
        ]);

        $offset = $data['offset'];
        $limit = apply_filters( 'mio_bulk_optimize_limit', 3 );

        $args = [
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'post_mime_type' => 'image',
            'posts_per_page' => $limit,
            'offset' => $offset,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_mio_optimized',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => '_mio_optimized',
                    'value' => '',
                    'compare' => '=',
                ],
            ],
        ];

        $image_query = new \WP_Query( $args );
        $attachment_ids = $image_query->posts;

        $optimizer = new Optimizer();
        $results = [
            'optimized_count' => 0,
            'failed_count' => 0,
            'skipped_count' => 0,
            'total_savings' => 0,
            'log' => [],
            'processed_files' => [],
        ];

        foreach ( $attachment_ids as $attachment_id ) {
            $file_path = get_attached_file( $attachment_id );
            $filename = basename( $file_path );

            if ( ! $file_path || ! file_exists( $file_path ) ) {
                $results['skipped_count']++;
                $results['log'][] = [
                    'type' => 'skipped',
                    'message' => sprintf( __( 'Skipped %s (file not found)', 'morden-image-optimize' ), $filename ),
                ];
                continue;
            }

            $original_size = filesize( $file_path );
            $metadata = wp_get_attachment_metadata( $attachment_id );

            $optimizer->process_attachment_optimization( $attachment_id, $metadata );

            if ( get_post_meta( $attachment_id, '_mio_optimized', true ) ) {
                $savings = get_post_meta( $attachment_id, '_mio_savings', true );
                $optimized_size = get_post_meta( $attachment_id, '_mio_optimized_size', true );

                $results['optimized_count']++;
                $results['total_savings'] += absint( $savings );
                $results['log'][] = [
                    'type' => 'success',
                    'message' => sprintf(
                        __( 'Optimized %s - Saved %s', 'morden-image-optimize' ),
                        $filename,
                        FileHelper::format_file_size( $savings )
                    ),
                ];
                $results['processed_files'][] = [
                    'id' => $attachment_id,
                    'filename' => $filename,
                    'original_size' => $original_size,
                    'optimized_size' => $optimized_size,
                    'savings' => $savings,
                ];
            } else {
                $results['failed_count']++;
                $error = get_post_meta( $attachment_id, '_mio_optimization_error', true );
                $results['log'][] = [
                    'type' => 'error',
                    'message' => sprintf(
                        __( 'Failed to optimize %s%s', 'morden-image-optimize' ),
                        $filename,
                        $error ? ' (' . $error . ')' : ''
                    ),
                ];
            }
        }

        $total_remaining = max( 0, $image_query->found_posts - count( $attachment_ids ) );

        wp_send_json_success([
            'batch_results' => $results,
            'next_offset' => $offset + count( $attachment_ids ),
            'has_more' => $total_remaining > 0,
            'total_remaining' => $total_remaining,
            'processed_count' => count( $attachment_ids ),
        ]);
    }

    public function ajax_get_bulk_stats() {
        Security::validate_ajax_request( 'bulk_optimize', 'upload_files' );

        $stats = $this->get_optimization_stats();
        wp_send_json_success( $stats );
    }

    public function ajax_pause_optimization() {
        Security::validate_ajax_request( 'bulk_optimize', 'upload_files' );

        set_transient( 'mio_bulk_optimization_paused', true, HOUR_IN_SECONDS );

        wp_send_json_success([
            'message' => __( 'Optimization paused successfully.', 'morden-image-optimize' ),
        ]);
    }

    private function get_optimization_stats() {
        $stats = get_transient( 'mio_bulk_optimizer_stats' );
        if ( false !== $stats ) {
            return $stats;
        }

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

        $total_savings = $wpdb->get_var( "
            SELECT SUM(CAST(pm.meta_value AS UNSIGNED))
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'attachment'
            AND p.post_mime_type LIKE 'image/%'
            AND p.post_status = 'inherit'
            AND pm.meta_key = '_mio_savings'
        " );

        $stats = [
            'total_images' => (int) $total_images,
            'optimized_images' => (int) $optimized_images,
            'unoptimized_images' => max( 0, (int) $total_images - (int) $optimized_images ),
            'total_savings' => (int) $total_savings,
        ];

        // Cache for 1 minute
        set_transient( 'mio_bulk_optimizer_stats', $stats, MINUTE_IN_SECONDS );

        return $stats;
    }

    public function add_contextual_help() {
        $screen = get_current_screen();

        $screen->add_help_tab([
            'id' => 'mio_bulk_overview',
            'title' => __( 'Overview', 'morden-image-optimize' ),
            'content' => '<p>' . __( 'The Bulk Optimizer allows you to optimize all existing images in your Media Library at once.', 'morden-image-optimize' ) . '</p>',
        ]);

        $screen->add_help_tab([
            'id' => 'mio_bulk_process',
            'title' => __( 'How It Works', 'morden-image-optimize' ),
            'content' => '<p>' . __( 'Images are processed in small batches to prevent server timeouts. The process can be paused and resumed at any time.', 'morden-image-optimize' ) . '</p>',
        ]);
    }
    private function get_system_stats() {
    $optimizer = new \MordenImageOptimizer\Core\Optimizer();

    return [
        'method' => $optimizer->get_optimization_method(),
        'memory_usage' => size_format( memory_get_usage() ),
        'peak_memory' => size_format( memory_get_peak_usage() ),
    ];
}
}

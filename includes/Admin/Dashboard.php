<?php
// File: includes/Admin/Dashboard.php

namespace MordenImageOptimizer\Admin;

use MordenImageOptimizer\Core\DatabaseManager;
use MordenImageOptimizer\Core\Config;
use MordenImageOptimizer\Utils\FileHelper;
use MordenImageOptimizer\Admin\BackupManager;

class Dashboard {
    private static $instance = null;
    private $db_manager;
    private $config;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->db_manager = DatabaseManager::get_instance();
        $this->config = Config::get_instance();

        add_action( 'wp_dashboard_setup', [ $this, 'add_dashboard_widgets' ] );
        add_action( 'admin_menu', [ $this, 'add_dashboard_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_dashboard_assets' ] );
    }

    public function add_dashboard_widgets() {
        if ( ! current_user_can( 'upload_files' ) ) {
            return;
        }

        wp_add_dashboard_widget(
            'mio_dashboard_stats',
            __( 'Image Optimization Stats', 'morden-image-optimize' ),
            [ $this, 'render_dashboard_widget' ]
        );
    }

    public function enqueue_dashboard_assets( $hook_suffix ) {
        if ( ! in_array( $hook_suffix, [ 'index.php', 'settings_page_mio-dashboard' ], true ) ) {
            return;
        }

        wp_enqueue_style( 'mio-admin-styles', MIO_PLUGIN_URL . 'assets/css/admin-styles.css', [], MIO_VERSION );
        wp_enqueue_script( 'mio-dashboard', MIO_PLUGIN_URL . 'assets/js/dashboard.js', [ 'jquery' ], MIO_VERSION, true );
    }

    public function render_dashboard_widget() {
        $stats = $this->get_optimization_stats();
        $recent_optimizations = $this->get_recent_optimizations( 5 );

        ?>
        <div class="mio-dashboard-widget">
            <div class="mio-stats-grid">
                <div class="mio-stat-item">
                    <span class="mio-stat-number"><?php echo esc_html( number_format( $stats['total_optimizations'] ) ); ?></span>
                    <span class="mio-stat-label"><?php esc_html_e( 'Images Optimized', 'morden-image-optimize' ); ?></span>
                </div>
                <div class="mio-stat-item">
                    <span class="mio-stat-number"><?php echo esc_html( FileHelper::format_file_size( $stats['total_savings'] ) ); ?></span>
                    <span class="mio-stat-label"><?php esc_html_e( 'Space Saved', 'morden-image-optimize' ); ?></span>
                </div>
            </div>

            <?php if ( ! empty( $recent_optimizations ) ) : ?>
                <div class="mio-recent-optimizations">
                    <h4><?php esc_html_e( 'Recent Optimizations', 'morden-image-optimize' ); ?></h4>
                    <ul>
                        <?php foreach ( $recent_optimizations as $optimization ) : ?>
                            <li>
                                <strong><?php echo esc_html( $optimization['filename'] ); ?></strong>
                                <span class="mio-savings"><?php echo esc_html( FileHelper::format_file_size( $optimization['savings'] ) ); ?></span>
                                <span class="mio-date"><?php echo esc_html( human_time_diff( strtotime( $optimization['timestamp'] ) ) ); ?> ago</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="mio-widget-actions">
                <a href="<?php echo esc_url( admin_url( 'upload.php?page=mio-bulk-optimize' ) ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Bulk Optimize', 'morden-image-optimize' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'options-general.php?page=mio-dashboard' ) ); ?>" class="button button-secondary">
                    <?php esc_html_e( 'View Details', 'morden-image-optimize' ); ?>
                </a>
            </div>
        </div>

        <style>
        .mio-dashboard-widget { font-size: 13px; }
        .mio-stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        .mio-stat-item { text-align: center; padding: 10px; background: #f9f9f9; border-radius: 4px; }
        .mio-stat-number { display: block; font-size: 24px; font-weight: bold; color: #2271b1; }
        .mio-stat-label { display: block; font-size: 11px; color: #646970; text-transform: uppercase; }
        .mio-recent-optimizations ul { margin: 0; }
        .mio-recent-optimizations li { display: flex; justify-content: space-between; align-items: center; padding: 5px 0; border-bottom: 1px solid #f0f0f1; }
        .mio-recent-optimizations li:last-child { border-bottom: none; }
        .mio-savings { color: #00a32a; font-weight: bold; }
        .mio-date { color: #646970; font-size: 11px; }
        .mio-widget-actions { margin-top: 15px; display: flex; gap: 10px; }
        .mio-widget-actions .button { flex: 1; text-align: center; }
        </style>
        <?php
    }

    public function render_dashboard_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'morden-image-optimize' ) );
        }

        $stats = $this->get_optimization_stats();
        $backup_stats = BackupManager::get_instance()->get_backup_stats();
        $system_info = $this->get_system_info();
        $recent_optimizations = $this->get_recent_optimizations( 20 );

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Morden Image Optimizer Dashboard', 'morden-image-optimize' ); ?></h1>

            <div class="mio-dashboard-container">
                <div class="mio-dashboard-main">
                    <div class="mio-stats-overview">
                        <div class="mio-stat-card large">
                            <h3><?php esc_html_e( 'Total Optimizations', 'morden-image-optimize' ); ?></h3>
                            <span class="mio-stat-number"><?php echo esc_html( number_format( $stats['total_optimizations'] ) ); ?></span>
                            <span class="mio-stat-change">
                                <?php printf( esc_html__( '%d successful, %d failed', 'morden-image-optimize' ), $stats['successful_optimizations'], $stats['failed_optimizations'] ); ?>
                            </span>
                        </div>

                        <div class="mio-stat-card large">
                            <h3><?php esc_html_e( 'Total Savings', 'morden-image-optimize' ); ?></h3>
                            <span class="mio-stat-number"><?php echo esc_html( FileHelper::format_file_size( $stats['total_savings'] ) ); ?></span>
                            <span class="mio-stat-change">
                                <?php printf( esc_html__( 'Average: %s per image', 'morden-image-optimize' ), FileHelper::format_file_size( $stats['average_savings'] ) ); ?>
                            </span>
                        </div>

                        <div class="mio-stat-card">
                            <h3><?php esc_html_e( 'Backup Files', 'morden-image-optimize' ); ?></h3>
                            <span class="mio-stat-number"><?php echo esc_html( number_format( $backup_stats['total_files'] ) ); ?></span>
                            <span class="mio-stat-change"><?php echo esc_html( FileHelper::format_file_size( $backup_stats['total_size'] ) ); ?></span>
                        </div>

                        <div class="mio-stat-card">
                            <h3><?php esc_html_e( 'Optimization Method', 'morden-image-optimize' ); ?></h3>
                            <span class="mio-stat-text"><?php echo esc_html( ucfirst( $system_info['optimization_method'] ) ); ?></span>
                            <span class="mio-stat-change"><?php echo esc_html( $system_info['method_description'] ); ?></span>
                        </div>
                    </div>

                    <div class="mio-dashboard-section">
                        <h2><?php esc_html_e( 'Recent Activity', 'morden-image-optimize' ); ?></h2>

                        <?php if ( ! empty( $recent_optimizations ) ) : ?>
                            <div class="mio-recent-table">
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e( 'Image', 'morden-image-optimize' ); ?></th>
                                            <th><?php esc_html_e( 'Method', 'morden-image-optimize' ); ?></th>
                                            <th><?php esc_html_e( 'Savings', 'morden-image-optimize' ); ?></th>
                                            <th><?php esc_html_e( 'Status', 'morden-image-optimize' ); ?></th>
                                            <th><?php esc_html_e( 'Date', 'morden-image-optimize' ); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ( $recent_optimizations as $optimization ) : ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo esc_html( $optimization['filename'] ); ?></strong>
                                                    <br>
                                                    <small><?php echo esc_html( FileHelper::format_file_size( $optimization['original_size'] ) ); ?> → <?php echo esc_html( FileHelper::format_file_size( $optimization['optimized_size'] ) ); ?></small>
                                                </td>
                                                <td><?php echo esc_html( ucfirst( $optimization['method'] ) ); ?></td>
                                                <td>
                                                    <span class="mio-savings-amount"><?php echo esc_html( FileHelper::format_file_size( $optimization['savings'] ) ); ?></span>
                                                    <br>
                                                    <small><?php echo esc_html( round( ( $optimization['savings'] / $optimization['original_size'] ) * 100, 1 ) ); ?>%</small>
                                                </td>
                                                <td>
                                                    <span class="mio-status-badge mio-status-<?php echo esc_attr( $optimization['status'] ); ?>">
                                                        <?php echo esc_html( ucfirst( $optimization['status'] ) ); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $optimization['timestamp'] ) ) ); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else : ?>
                            <div class="mio-empty-state">
                                <p><?php esc_html_e( 'No optimizations yet. Start by optimizing some images!', 'morden-image-optimize' ); ?></p>
                                <a href="<?php echo esc_url( admin_url( 'upload.php?page=mio-bulk-optimize' ) ); ?>" class="button button-primary">
                                    <?php esc_html_e( 'Start Bulk Optimization', 'morden-image-optimize' ); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mio-dashboard-sidebar">
                    <div class="mio-info-box">
                        <h3><?php esc_html_e( 'System Information', 'morden-image-optimize' ); ?></h3>
                        <table class="mio-info-table">
                            <tr>
                                <td><?php esc_html_e( 'Plugin Version:', 'morden-image-optimize' ); ?></td>
                                <td><?php echo esc_html( MIO_VERSION ); ?></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e( 'Optimization Method:', 'morden-image-optimize' ); ?></td>
                                <td><?php echo esc_html( ucfirst( $system_info['optimization_method'] ) ); ?></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e( 'Backup Enabled:', 'morden-image-optimize' ); ?></td>
                                <td><?php echo $this->config->is_enabled( 'keep_original' ) ? '✅' : '❌'; ?></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e( 'Auto Optimization:', 'morden-image-optimize' ); ?></td>
                                <td><?php echo $this->config->is_enabled( 'auto_optimize' ) ? '✅' : '❌'; ?></td>
                            </tr>
                        </table>
                    </div>

                    <div class="mio-info-box">
                        <h3><?php esc_html_e( 'Quick Actions', 'morden-image-optimize' ); ?></h3>
                        <div class="mio-action-buttons">
                            <a href="<?php echo esc_url( admin_url( 'upload.php?page=mio-bulk-optimize' ) ); ?>" class="button button-primary">
                                <?php esc_html_e( 'Bulk Optimize', 'morden-image-optimize' ); ?>
                            </a>
                            <a href="<?php echo esc_url( admin_url( 'options-general.php?page=morden_optimizer' ) ); ?>" class="button button-secondary">
                                <?php esc_html_e( 'Settings', 'morden-image-optimize' ); ?>
                            </a>
                            <a href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>" class="button button-secondary">
                                <?php esc_html_e( 'Media Library', 'morden-image-optimize' ); ?>
                            </a>
                        </div>
                    </div>

                    <?php if ( $backup_stats['total_files'] > 0 ) : ?>
                        <div class="mio-info-box">
                            <h3><?php esc_html_e( 'Backup Management', 'morden-image-optimize' ); ?></h3>
                            <p><?php printf( esc_html__( 'You have %d backup files using %s of storage.', 'morden-image-optimize' ), $backup_stats['total_files'], FileHelper::format_file_size( $backup_stats['total_size'] ) ); ?></p>
                            <button type="button" class="button button-secondary mio-cleanup-backups">
                                <?php esc_html_e( 'Cleanup Old Backups', 'morden-image-optimize' ); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <style>
        .mio-dashboard-container { display: flex; gap: 20px; }
        .mio-dashboard-main { flex: 1; }
        .mio-dashboard-sidebar { width: 300px; }
        .mio-stats-overview { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .mio-stat-card { background: #fff; padding: 20px; border: 1px solid #c3c4c7; border-radius: 4px; text-align: center; }
        .mio-stat-card.large { grid-column: span 1; }
        .mio-stat-card h3 { margin: 0 0 10px 0; font-size: 14px; color: #646970; }
        .mio-stat-card .mio-stat-number { display: block; font-size: 32px; font-weight: bold; color: #2271b1; margin-bottom: 5px; }
        .mio-stat-card .mio-stat-text { display: block; font-size: 18px; font-weight: bold; color: #2271b1; margin-bottom: 5px; }
        .mio-stat-card .mio-stat-change { font-size: 12px; color: #646970; }
        .mio-dashboard-section { background: #fff; padding: 20px; border: 1px solid #c3c4c7; border-radius: 4px; margin-bottom: 20px; }
        .mio-dashboard-section h2 { margin-top: 0; }
        .mio-info-box { background: #fff; padding: 15px; border: 1px solid #c3c4c7; border-radius: 4px; margin-bottom: 20px; }
        .mio-info-box h3 { margin-top: 0; }
        .mio-info-table { width: 100%; }
        .mio-info-table td { padding: 5px 0; border-bottom: 1px solid #f0f0f1; }
        .mio-info-table td:first-child { font-weight: bold; }
        .mio-action-buttons { display: flex; flex-direction: column; gap: 10px; }
        .mio-action-buttons .button { text-align: center; }
        .mio-savings-amount { color: #00a32a; font-weight: bold; }
        .mio-status-badge { padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .mio-status-success { background: #d1e7dd; color: #0f5132; }
        .mio-status-failed { background: #f8d7da; color: #721c24; }
        .mio-empty-state { text-align: center; padding: 40px 20px; }
        </style>
        <?php
    }

    private function get_optimization_stats() {
        return $this->db_manager->get_optimization_stats();
    }

    private function get_recent_optimizations( $limit = 10 ) {
        global $wpdb;

        $log_table = $wpdb->prefix . 'mio_optimization_log';

        $results = $wpdb->get_results( $wpdb->prepare( "
            SELECT l.*, p.post_title
            FROM $log_table l
            LEFT JOIN {$wpdb->posts} p ON l.attachment_id = p.ID
            ORDER BY l.timestamp DESC
            LIMIT %d
        ", $limit ), ARRAY_A );

        $optimizations = [];
        foreach ( $results as $row ) {
            $file_path = get_attached_file( $row['attachment_id'] );
            $filename = $file_path ? basename( $file_path ) : ( $row['post_title'] ?: 'Unknown' );

            $optimizations[] = [
                'attachment_id' => $row['attachment_id'],
                'filename' => $filename,
                'method' => $row['optimization_method'],
                'status' => $row['optimization_status'],
                'original_size' => $row['original_size'],
                'optimized_size' => $row['optimized_size'],
                'savings' => $row['savings_bytes'],
                'timestamp' => $row['timestamp'],
            ];
        }

        return $optimizations;
    }

    private function get_system_info() {
        $optimizer = new \MordenImageOptimizer\Core\Optimizer();
        $method = $optimizer->get_optimization_method();

        $descriptions = [
            'imagick' => __( 'Best quality optimization', 'morden-image-optimize' ),
            'gd' => __( 'Good quality optimization', 'morden-image-optimize' ),
            'api' => __( 'External API optimization', 'morden-image-optimize' ),
        ];

        return [
            'optimization_method' => $method,
            'method_description' => $descriptions[ $method ] ?? __( 'Unknown method', 'morden-image-optimize' ),
        ];
    }
}

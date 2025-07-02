<?php
// File: includes/Admin/BackupManager.php

namespace MordenImageOptimizer\Admin;

use MordenImageOptimizer\Core\Security;
use MordenImageOptimizer\Core\Logger;
use MordenImageOptimizer\Core\Config;
use MordenImageOptimizer\Utils\FileHelper;

class BackupManager {
    private static $instance = null;
    private $logger;
    private $config;
    private $backup_dir;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->logger = Logger::get_instance();
        $this->config = Config::get_instance();

        $upload_dir = wp_get_upload_dir();
        $this->backup_dir = trailingslashit( $upload_dir['basedir'] ) . '.mio-backups';

        add_action( 'admin_action_mio_restore_image', [ $this, 'handle_restore_action' ] );
        add_action( 'wp_ajax_mio_restore_single_image', [ $this, 'ajax_restore_single' ] );
        add_action( 'wp_ajax_mio_cleanup_backups', [ $this, 'ajax_cleanup_backups' ] );
        add_action( 'mio_cleanup_old_backups', [ $this, 'cleanup_old_backups' ] );

        if ( ! wp_next_scheduled( 'mio_cleanup_old_backups' ) ) {
            wp_schedule_event( time(), 'daily', 'mio_cleanup_old_backups' );
        }
    }

    public static function create_backup( $attachment_id, $file_path ) {
        $instance = self::get_instance();
        return $instance->create_backup_file( $attachment_id, $file_path );
    }

    private function create_backup_file( $attachment_id, $file_path ) {
        if ( ! $this->config->is_enabled( 'keep_original' ) ) {
            return true;
        }

        if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
            $this->logger->error( 'Cannot backup file: not found or not readable', [ 'file' => $file_path ] );
            return false;
        }

        $this->ensure_backup_directory();

        $relative_path = $this->get_relative_upload_path( $file_path );
        $backup_file_path = $this->backup_dir . '/' . $relative_path;

        $backup_parent_dir = dirname( $backup_file_path );
        if ( ! is_dir( $backup_parent_dir ) ) {
            wp_mkdir_p( $backup_parent_dir );
        }

        if ( copy( $file_path, $backup_file_path ) ) {
            update_post_meta( $attachment_id, '_mio_backup_path', $backup_file_path );
            update_post_meta( $attachment_id, '_mio_backup_created', current_time( 'mysql' ) );

            $this->logger->info( 'Backup created successfully', [
                'attachment_id' => $attachment_id,
                'backup_path' => $backup_file_path,
            ]);

            return true;
        }

        $this->logger->error( 'Failed to create backup', [
            'attachment_id' => $attachment_id,
            'source' => $file_path,
            'destination' => $backup_file_path,
        ]);

        return false;
    }

    public static function restore_image( $attachment_id ) {
        $instance = self::get_instance();
        return $instance->restore_image_file( $attachment_id );
    }

    private function restore_image_file( $attachment_id ) {
        $backup_path = get_post_meta( $attachment_id, '_mio_backup_path', true );
        $current_path = get_attached_file( $attachment_id );

        if ( ! $backup_path || ! file_exists( $backup_path ) ) {
            $this->logger->error( 'Backup file not found', [
                'attachment_id' => $attachment_id,
                'backup_path' => $backup_path,
            ]);
            return false;
        }

        if ( ! $current_path || ! is_writable( dirname( $current_path ) ) ) {
            $this->logger->error( 'Current file path not writable', [
                'attachment_id' => $attachment_id,
                'current_path' => $current_path,
            ]);
            return false;
        }

        if ( copy( $backup_path, $current_path ) ) {
            $this->clear_optimization_metadata( $attachment_id );

            // Invalidate stats caches
            delete_transient( 'mio_optimization_stats' );
            delete_transient( 'mio_quick_stats' );
            delete_transient( 'mio_bulk_optimizer_stats' );

            wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $current_path ) );

            $this->logger->info( 'Image restored successfully', [
                'attachment_id' => $attachment_id,
                'restored_from' => $backup_path,
            ]);

            return true;
        }

        $this->logger->error( 'Failed to restore image', [
            'attachment_id' => $attachment_id,
            'backup_path' => $backup_path,
            'current_path' => $current_path,
        ]);

        return false;
    }

    public function handle_restore_action() {
        if ( ! Security::check_permissions( 'upload_files' ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'morden-image-optimize' ) );
        }

        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_key( $_GET['_wpnonce'] ) : '';
        if ( ! Security::verify_nonce( $nonce, 'restore_image' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'morden-image-optimize' ) );
        }

        $attachment_id = isset( $_GET['attachment_id'] ) ? absint( $_GET['attachment_id'] ) : 0;
        if ( ! $attachment_id ) {
            wp_die( esc_html__( 'Invalid attachment ID.', 'morden-image-optimize' ) );
        }

        $restored = $this->restore_image_file( $attachment_id );

        $redirect_url = wp_get_referer() ?: admin_url( 'upload.php' );
        $redirect_url = add_query_arg(
            'mio_message',
            $restored ? 'restored' : 'restore_failed',
            $redirect_url
        );

        wp_safe_redirect( $redirect_url );
        exit;
    }

    public function ajax_restore_single() {
        $data = Security::validate_ajax_request( 'restore_image', 'upload_files', [
            'attachment_id' => 'int',
        ]);

        $attachment_id = $data['attachment_id'];

        if ( ! wp_attachment_is_image( $attachment_id ) ) {
            wp_send_json_error( [
                'message' => __( 'Invalid attachment or not an image.', 'morden-image-optimize' ),
            ]);
        }

        $restored = $this->restore_image_file( $attachment_id );

        if ( $restored ) {
            wp_send_json_success( [
                'message' => __( 'Image restored successfully.', 'morden-image-optimize' ),
            ]);
        } else {
            wp_send_json_error( [
                'message' => __( 'Failed to restore image.', 'morden-image-optimize' ),
            ]);
        }
    }

    public function ajax_cleanup_backups() {
        Security::validate_ajax_request( 'settings', 'manage_options' );

        $cleaned = $this->cleanup_old_backups();

        wp_send_json_success( [
            'message' => sprintf( __( 'Cleaned up %d old backup files.', 'morden-image-optimize' ), $cleaned ),
            'cleaned_count' => $cleaned,
        ]);
    }

    public function cleanup_old_backups() {
        $retention_days = $this->config->get( 'backup_retention_days', 30 );
        $cutoff_time = time() - ( $retention_days * DAY_IN_SECONDS );

        $cleaned_count = 0;

        if ( ! is_dir( $this->backup_dir ) ) {
            return $cleaned_count;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $this->backup_dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ( $iterator as $file ) {
            if ( $file->isFile() && $file->getMTime() < $cutoff_time ) {
                if ( unlink( $file->getPathname() ) ) {
                    $cleaned_count++;
                }
            }
        }

        $this->cleanup_empty_directories( $this->backup_dir );

        $this->logger->info( "Cleaned up $cleaned_count old backup files" );

        return $cleaned_count;
    }

    public function get_backup_stats() {
        if ( ! is_dir( $this->backup_dir ) ) {
            return [
                'total_files' => 0,
                'total_size' => 0,
                'oldest_backup' => null,
                'newest_backup' => null,
            ];
        }

        $total_files = 0;
        $total_size = 0;
        $oldest_time = PHP_INT_MAX;
        $newest_time = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $this->backup_dir, \RecursiveDirectoryIterator::SKIP_DOTS )
        );

        foreach ( $iterator as $file ) {
            if ( $file->isFile() ) {
                $total_files++;
                $total_size += $file->getSize();
                $mtime = $file->getMTime();

                if ( $mtime < $oldest_time ) {
                    $oldest_time = $mtime;
                }
                if ( $mtime > $newest_time ) {
                    $newest_time = $mtime;
                }
            }
        }

        return [
            'total_files' => $total_files,
            'total_size' => $total_size,
            'oldest_backup' => $oldest_time !== PHP_INT_MAX ? date( 'Y-m-d H:i:s', $oldest_time ) : null,
            'newest_backup' => $newest_time > 0 ? date( 'Y-m-d H:i:s', $newest_time ) : null,
        ];
    }

    public function get_backup_dir() {
        return $this->backup_dir;
    }

    private function ensure_backup_directory() {
        if ( ! is_dir( $this->backup_dir ) ) {
            wp_mkdir_p( $this->backup_dir );

            file_put_contents( $this->backup_dir . '/.htaccess', "Order deny,allow\nDeny from all" );
            file_put_contents( $this->backup_dir . '/index.php', '<?php // Silence is golden.' );
        }
    }

    public function get_relative_upload_path( $file_path ) {
        $upload_dir = wp_get_upload_dir();
        return str_replace( $upload_dir['basedir'] . '/', '', $file_path );
    }

    private function clear_optimization_metadata( $attachment_id ) {
        $meta_keys = [
            '_mio_optimized',
            '_mio_optimization_method',
            '_mio_original_size',
            '_mio_optimized_size',
            '_mio_savings',
            '_mio_optimization_date',
            '_mio_optimization_error',
        ];

        foreach ( $meta_keys as $key ) {
            delete_post_meta( $attachment_id, $key );
        }
    }

    private function cleanup_empty_directories( $dir ) {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ( $iterator as $file ) {
            if ( $file->isDir() && $this->is_directory_empty( $file->getPathname() ) ) {
                rmdir( $file->getPathname() );
            }
        }
    }

    private function is_directory_empty( $dir ) {
        $handle = opendir( $dir );
        while ( false !== ( $entry = readdir( $handle ) ) ) {
            if ( $entry !== '.' && $entry !== '..' ) {
                closedir( $handle );
                return false;
            }
        }
        closedir( $handle );
        return true;
    }
}

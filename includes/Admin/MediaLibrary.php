<?php
// File: includes/Admin/MediaLibrary.php

namespace MordenImageOptimizer\Admin;

use MordenImageOptimizer\Core\Security;
use MordenImageOptimizer\Core\Optimizer;
use MordenImageOptimizer\Utils\FileHelper;
use MordenImageOptimizer\Utils\ImageHelper;

class MediaLibrary {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter( 'manage_media_columns', [ $this, 'add_optimization_column' ] );
        add_action( 'manage_media_custom_column', [ $this, 'render_optimization_column' ], 10, 2 );
        add_filter( 'attachment_fields_to_edit', [ $this, 'add_optimization_fields' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_media_scripts' ] );
        add_action( 'admin_notices', [ $this, 'show_admin_notices' ] );

        add_action( 'wp_ajax_mio_optimize_single_media', [ $this, 'ajax_optimize_single' ] );
        add_action( 'wp_ajax_mio_restore_single_media', [ $this, 'ajax_restore_single' ] );
    }

    public function add_optimization_column( $columns ) {
        $new_columns = [];

        foreach ( $columns as $key => $value ) {
            $new_columns[ $key ] = $value;

            if ( 'title' === $key ) {
                $new_columns['mio_optimization'] = __( 'Optimization', 'morden-image-optimize' );
            }
        }

        return $new_columns;
    }

    public function render_optimization_column( $column_name, $attachment_id ) {
        if ( 'mio_optimization' !== $column_name ) {
            return;
        }

        if ( ! wp_attachment_is_image( $attachment_id ) ) {
            echo '<span class="mio-status-na">' . esc_html__( 'N/A', 'morden-image-optimize' ) . '</span>';
            return;
        }

        $is_optimized = get_post_meta( $attachment_id, '_mio_optimized', true );
        $file_path = get_attached_file( $attachment_id );

        if ( ! $file_path || ! file_exists( $file_path ) ) {
            echo '<span class="mio-status-error">' . esc_html__( 'File Missing', 'morden-image-optimize' ) . '</span>';
            return;
        }

        if ( $is_optimized ) {
            $this->render_optimized_status( $attachment_id );
        } else {
            $this->render_unoptimized_status( $attachment_id );
        }
    }

    private function render_optimized_status( $attachment_id ) {
        $savings = get_post_meta( $attachment_id, '_mio_savings', true );
        $method = get_post_meta( $attachment_id, '_mio_optimization_method', true );
        $original_size = get_post_meta( $attachment_id, '_mio_original_size', true );
        $optimized_size = get_post_meta( $attachment_id, '_mio_optimized_size', true );

        $savings_formatted = FileHelper::format_file_size( $savings );
        $percentage = ImageHelper::calculate_savings_percentage( $original_size, $optimized_size );

        echo '<div class="mio-optimization-status mio-status-optimized">';
        echo '<span class="mio-status-badge mio-optimized">' . esc_html__( 'Optimized', 'morden-image-optimize' ) . '</span>';
        echo '<div class="mio-optimization-details">';
        echo '<div class="mio-savings">' . sprintf( esc_html__( 'Saved: %s (%s%%)', 'morden-image-optimize' ), $savings_formatted, $percentage ) . '</div>';
        echo '<div class="mio-method">' . sprintf( esc_html__( 'Method: %s', 'morden-image-optimize' ), ucfirst( $method ) ) . '</div>';
        echo '</div>';

        echo '<div class="mio-actions">';
        echo $this->get_action_button( 'reoptimize', $attachment_id );

        $backup_path = get_post_meta( $attachment_id, '_mio_backup_path', true );
        if ( $backup_path && file_exists( $backup_path ) ) {
            echo $this->get_action_button( 'restore', $attachment_id );
        }
        echo '</div>';
        echo '</div>';
    }

    private function render_unoptimized_status( $attachment_id ) {
        $file_path = get_attached_file( $attachment_id );
        $file_size = FileHelper::get_file_size( $file_path );

        echo '<div class="mio-optimization-status mio-status-pending">';
        echo '<span class="mio-status-badge mio-pending">' . esc_html__( 'Not Optimized', 'morden-image-optimize' ) . '</span>';
        echo '<div class="mio-optimization-details">';
        echo '<div class="mio-file-size">' . sprintf( esc_html__( 'Size: %s', 'morden-image-optimize' ), FileHelper::format_file_size( $file_size ) ) . '</div>';
        echo '</div>';

        echo '<div class="mio-actions">';
        echo $this->get_action_button( 'optimize', $attachment_id );
        echo '</div>';
        echo '</div>';
    }

    private function get_action_button( $action, $attachment_id ) {
        $nonce = Security::create_nonce( 'single_optimize' );

        switch ( $action ) {
            case 'optimize':
                return sprintf(
                    '<button type="button" class="button button-small mio-optimize-btn" data-id="%d" data-nonce="%s">%s</button>',
                    $attachment_id,
                    $nonce,
                    esc_html__( 'Optimize', 'morden-image-optimize' )
                );

            case 'reoptimize':
                return sprintf(
                    '<button type="button" class="button button-small mio-optimize-btn" data-id="%d" data-nonce="%s">%s</button>',
                    $attachment_id,
                    $nonce,
                    esc_html__( 'Re-optimize', 'morden-image-optimize' )
                );

            case 'restore':
                $restore_nonce = Security::create_nonce( 'restore_image' );
                return sprintf(
                    '<button type="button" class="button button-small mio-restore-btn" data-id="%d" data-nonce="%s">%s</button>',
                    $attachment_id,
                    $restore_nonce,
                    esc_html__( 'Restore', 'morden-image-optimize' )
                );
        }

        return '';
    }

    public function add_optimization_fields( $form_fields, $post ) {
        if ( ! wp_attachment_is_image( $post->ID ) ) {
            return $form_fields;
        }

        $is_optimized = get_post_meta( $post->ID, '_mio_optimized', true );

        if ( $is_optimized ) {
            $savings = get_post_meta( $post->ID, '_mio_savings', true );
            $method = get_post_meta( $post->ID, '_mio_optimization_method', true );
            $optimization_date = get_post_meta( $post->ID, '_mio_optimization_date', true );
            $backup_path = get_post_meta( $post->ID, '_mio_backup_path', true );

            // Generate nonces
            $optimize_nonce = Security::create_nonce( 'single_optimize' );
            $restore_nonce = Security::create_nonce( 'restore_image' );

            $html = sprintf(
                '<div class="mio-optimization-info">
                    <p><strong>%s:</strong> %s</p>
                    <p><strong>%s:</strong> %s</p>
                    <p><strong>%s:</strong> %s</p>
                    <p><strong>%s:</strong> %s</p>
                    <div class="mio-attachment-actions">
                        <button type="button" class="button button-secondary mio-optimize-btn" data-id="%d" data-nonce="%s" data-action="reoptimize">%s</button>
                        %s
                    </div>
                </div>',
                esc_html__( 'Status', 'morden-image-optimize' ),
                esc_html__( 'Optimized', 'morden-image-optimize' ),
                esc_html__( 'Savings', 'morden-image-optimize' ),
                FileHelper::format_file_size( $savings ),
                esc_html__( 'Method', 'morden-image-optimize' ),
                ucfirst( $method ),
                esc_html__( 'Date', 'morden-image-optimize' ),
                $optimization_date ? date_i18n( get_option( 'date_format' ), strtotime( $optimization_date ) ) : __( 'Unknown', 'morden-image-optimize' ),
                $post->ID,
                $optimize_nonce,
                esc_html__( 'Re-optimize', 'morden-image-optimize' ),
                ( $backup_path && file_exists( $backup_path ) ) ?
                    sprintf( '<button type="button" class="button button-secondary mio-restore-btn" data-id="%d" data-nonce="%s" data-action="restore">%s</button>',
                        $post->ID,
                        $restore_nonce,
                        esc_html__( 'Restore Original', 'morden-image-optimize' )
                    ) : ''
            );

            $form_fields['mio_optimization_info'] = [
                'label' => __( 'Morden Optimizer', 'morden-image-optimize' ),
                'input' => 'html',
                'html' => $html,
            ];
        } else {
            $optimize_nonce = Security::create_nonce( 'single_optimize' );

            $html = sprintf(
                '<div class="mio-optimization-info">
                    <p>%s</p>
                    <div class="mio-attachment-actions">
                        <button type="button" class="button button-primary mio-optimize-btn" data-id="%d" data-nonce="%s" data-action="optimize">%s</button>
                    </div>
                </div>',
                esc_html__( 'This image has not been optimized yet.', 'morden-image-optimize' ),
                $post->ID,
                $optimize_nonce,
                esc_html__( 'Optimize Now', 'morden-image-optimize' )
            );

            $form_fields['mio_optimization_info'] = [
                'label' => __( 'Morden Optimizer', 'morden-image-optimize' ),
                'input' => 'html',
                'html' => $html,
            ];
        }

        return $form_fields;
    }

    public function enqueue_media_scripts( $hook_suffix ) {
        if ( ! in_array( $hook_suffix, [ 'upload.php', 'post.php', 'post-new.php' ], true ) ) {
            return;
        }

        wp_enqueue_style( 'mio-admin-styles', MIO_PLUGIN_URL . 'assets/css/admin-styles.css', [], MIO_VERSION );
        wp_enqueue_script( 'mio-media-library', MIO_PLUGIN_URL . 'assets/js/media-library.js', [ 'jquery' ], MIO_VERSION, true );

        wp_localize_script( 'mio-media-library', 'mio_media', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'strings' => [
                'optimizing' => __( 'Optimizing...', 'morden-image-optimize' ),
                'restoring' => __( 'Restoring...', 'morden-image-optimize' ),
                'success' => __( 'Success!', 'morden-image-optimize' ),
                'error' => __( 'Error occurred', 'morden-image-optimize' ),
                'confirm_restore' => __( 'Are you sure you want to restore the original image?', 'morden-image-optimize' ),
            ],
        ]);
    }

    public function show_admin_notices() {
        if ( ! isset( $_GET['mio_message'] ) ) {
            return;
        }

        $message_type = sanitize_key( $_GET['mio_message'] );

        switch ( $message_type ) {
            case 'restored':
                echo '<div class="notice notice-success is-dismissible"><p>' .
                     esc_html__( 'Image restored successfully!', 'morden-image-optimize' ) .
                     '</p></div>';
                break;

            case 'restore_failed':
                echo '<div class="notice notice-error is-dismissible"><p>' .
                     esc_html__( 'Failed to restore image. Please try again.', 'morden-image-optimize' ) .
                     '</p></div>';
                break;
        }
    }

    public function ajax_optimize_single() {
        $data = Security::validate_ajax_request( 'single_optimize', 'upload_files', [
            'attachment_id' => 'int',
        ]);

        $attachment_id = $data['attachment_id'];

        if ( ! wp_attachment_is_image( $attachment_id ) ) {
            wp_send_json_error( [
                'message' => __( 'Invalid attachment or not an image.', 'morden-image-optimize' ),
            ]);
        }

        // It's a re-optimization, so clear old meta.
        delete_post_meta( $attachment_id, '_mio_optimized' );
        delete_post_meta( $attachment_id, '_mio_optimization_error' );

        $optimizer = new Optimizer();
        $metadata = wp_get_attachment_metadata( $attachment_id );
        $success = $optimizer->process_attachment_optimization( $attachment_id, $metadata );

        if ( $success ) {
            $savings = get_post_meta( $attachment_id, '_mio_savings', true );
            wp_send_json_success( [
                'message' => __( 'Image optimized successfully.', 'morden-image-optimize' ),
                'savings' => FileHelper::format_file_size( $savings ),
                'html' => $this->get_updated_column_html( $attachment_id ),
            ]);
        } else {
            $error = get_post_meta( $attachment_id, '_mio_optimization_error', true );
            wp_send_json_error( [
                'message' => __( 'Failed to optimize image.', 'morden-image-optimize' ) . ( $error ? ' (' . $error . ')' : '' ),
            ]);
        }
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

        $restored = BackupManager::restore_image( $attachment_id );

        if ( $restored ) {
            wp_send_json_success( [
                'message' => __( 'Image restored successfully.', 'morden-image-optimize' ),
                'html' => $this->get_updated_column_html( $attachment_id ),
            ]);
        } else {
            wp_send_json_error( [
                'message' => __( 'Failed to restore image.', 'morden-image-optimize' ),
            ]);
        }
    }

    private function get_updated_column_html( $attachment_id ) {
        ob_start();
        $this->render_optimization_column( 'mio_optimization', $attachment_id );
        return ob_get_clean();
    }
}

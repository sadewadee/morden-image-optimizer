<?php
// File: includes/Admin/MediaLibrary.php

namespace MordenImageOptimizer\\Admin;

/**
 * Handles Media Library integration and customization.
 */
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
    }

    public function add_optimization_column( $columns ) {
        $columns['mio_optimization'] = __( 'Optimization', 'morden_optimizer' );
        return $columns;
    }

    public function render_optimization_column( $column_name, $attachment_id ) {
        if ( 'mio_optimization' === $column_name ) {
            // TODO: Implement optimization column content
            echo '<span class="mio-status-label mio-status-pending">Pending</span>';
        }
    }
}

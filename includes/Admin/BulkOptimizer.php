<?php
// File: includes/Admin/BulkOptimizer.php

namespace MordenImageOptimizer\\Admin;

use MordenImageOptimizer\\Core\\Security;

/**
 * Handles the Bulk Optimization user interface and process.
 */
class BulkOptimizer {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_mio_bulk_optimize_batch', [ $this, 'ajax_optimize_batch' ] );
    }

    public function add_admin_menu() {
        add_media_page(
            __( 'Bulk Optimize Images', 'morden_optimizer' ),
            __( 'Bulk Optimize', 'morden_optimizer' ),
            'manage_options',
            'mio-bulk-optimize',
            [ $this, 'render_bulk_optimize_page' ]
        );
    }

    public function enqueue_assets( $hook_suffix ) {
        if ( 'media_page_mio-bulk-optimize' !== $hook_suffix ) {
            return;
        }

        wp_enqueue_style( 'mio-admin-styles', MIO_PLUGIN_URL . 'assets/css/admin-styles.css', [], MIO_VERSION );
        wp_enqueue_script( 'mio-bulk-optimizer', MIO_PLUGIN_URL . 'assets/js/bulk-optimizer.js', [ 'jquery' ], MIO_VERSION, true );

        wp_localize_script( 'mio-bulk-optimizer', 'mio_ajax', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => Security::create_nonce( 'mio_bulk_optimize_nonce' )
        ]);
    }

    public function render_bulk_optimize_page() {
        // TODO: Implement bulk optimize page template
        echo '<div class="wrap"><h1>Bulk Optimize Images</h1></div>';
    }

    public function ajax_optimize_batch() {
        // TODO: Implement AJAX batch optimization
        wp_send_json_error( 'Not implemented yet' );
    }
}

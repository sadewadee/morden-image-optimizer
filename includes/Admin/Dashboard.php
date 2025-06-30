<?php
// File: includes/Admin/Dashboard.php

namespace MordenImageOptimizer\\Admin;

/**
 * Manages the admin dashboard and statistics.
 */
class Dashboard {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_dashboard_setup', [ $this, 'add_dashboard_widget' ] );
    }

    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'mio_dashboard_stats',
            __( 'Image Optimization Stats', 'morden_optimizer' ),
            [ $this, 'render_dashboard_widget' ]
        );
    }

    public function render_dashboard_widget() {
        // TODO: Implement dashboard widget
        echo '<div class="mio-dashboard-card">Statistics coming soon...</div>';
    }
}

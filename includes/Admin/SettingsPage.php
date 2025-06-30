<?php
// File: includes/Admin/SettingsPage.php

namespace MordenImageOptimizer\\Admin;

use MordenImageOptimizer\\Core\\Config;
use MordenImageOptimizer\\Core\\Security;

/**
 * Manages the plugin's admin settings page.
 */
class SettingsPage {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function add_admin_menu() {
        add_options_page(
            __( 'Morden Image Optimizer', 'morden_optimizer' ),
            __( 'Morden Optimizer', 'morden_optimizer' ),
            'manage_options',
            'morden_optimizer',
            [ $this, 'render_settings_page' ]
        );
    }

    public function register_settings() {
        register_setting( 'mio_settings_group', 'mio_settings', [ $this, 'sanitize_settings' ] );
        // TODO: Add settings sections and fields
    }

    public function enqueue_assets( $hook_suffix ) {
        if ( 'settings_page_morden_optimizer' !== $hook_suffix ) {
            return;
        }

        wp_enqueue_style( 'mio-admin-styles', MIO_PLUGIN_URL . 'assets/css/admin-styles.css', [], MIO_VERSION );
        wp_enqueue_script( 'mio-settings-js', MIO_PLUGIN_URL . 'assets/js/settings-page.js', [ 'jquery' ], MIO_VERSION, true );

        wp_localize_script( 'mio-settings-js', 'mio_ajax', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => Security::create_nonce( 'mio_settings_nonce' )
        ]);
    }

    public function render_settings_page() {
        // TODO: Implement settings page template
        echo '<div class="wrap"><h1>Morden Image Optimizer Settings</h1></div>';
    }

    public function sanitize_settings( $input ) {
        // TODO: Implement settings sanitization
        return $input;
    }
}

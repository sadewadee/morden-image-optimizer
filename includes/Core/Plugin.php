<?php
// File: includes/Core/Plugin.php

namespace MordenImageOptimizer\Core;

use MordenImageOptimizer\Admin\SettingsPage;
use MordenImageOptimizer\Admin\BulkOptimizer;
use MordenImageOptimizer\Admin\BackupManager;
use MordenImageOptimizer\Admin\MediaLibrary;
use MordenImageOptimizer\Admin\Onboarding;
//use MordenImageOptimizer\Admin\Dashboard;

/**
 * Main plugin class - orchestrates the entire plugin.
 *
 * This class is the central nervous system that coordinates all components
 * and ensures they work together seamlessly.
 *
 * @package MordenImageOptimizer\Core
 * @since 1.0.0
 */
final class Plugin {

    /**
     * Single instance of the plugin.
     *
     * @var Plugin|null
     */
    private static $instance = null;

    /**
     * Plugin version.
     *
     * @var string
     */
    private $version;

    /**
     * Gets the single instance of the plugin.
     *
     * @return Plugin
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to enforce singleton pattern.
     */
    private function __construct() {
        $this->version = MIO_VERSION;
        $this->init_hooks();
    }

    /**
     * Initializes WordPress hooks.
     */
    private function init_hooks() {
        add_action( 'plugins_loaded', [ $this, 'on_plugins_loaded' ] );
        add_action( 'init', [ $this, 'init_components' ] );
        add_action( 'admin_notices', [ $this, 'display_admin_notices' ] );
    }

    /**
     * Handles the plugins_loaded action.
     * Loads text domain for internationalization.
     */
    public function on_plugins_loaded() {
        load_plugin_textdomain(
            'morden-image-optimize',
            false,
            dirname( plugin_basename( MIO_PLUGIN_DIR . 'morden-image-optimizer.php' ) ) . '/languages'
        );
    }

    /**
     * Initializes all plugin components.
     * This is where the magic happens - all components come together.
     */
    public function init_components() {
        // Initialize core infrastructure first
        $this->init_core_components();

        // Initialize admin components if in admin area
        if ( is_admin() ) {
            $this->init_admin_components();
        }

        // Initialize optimization engine
        $this->init_optimization_engine();

        // Log successful initialization
        Logger::get_instance()->info( 'Plugin components initialized successfully' );
    }

    /**
     * Initializes core infrastructure components.
     */
    private function init_core_components() {
        DatabaseManager::get_instance();
        Config::get_instance();
        Security::get_instance();
        Logger::get_instance();
        Updater::get_instance();
    }

    /**
     * Initializes admin-specific components.
     */
    private function init_admin_components() {
        SettingsPage::get_instance();
        BulkOptimizer::get_instance();
        BackupManager::get_instance();
        MediaLibrary::get_instance();
        Onboarding::get_instance();
        //Dashboard::get_instance();
    }

    /**
     * Initializes the optimization engine.
     */
    private function init_optimization_engine() {
        $optimizer = new Optimizer();

        // Hook into WordPress media upload process
        add_filter( 'wp_generate_attachment_metadata', [ $optimizer, 'hook_optimize_attachment' ], 10, 2 );

        // Hook for manual optimization triggers
        add_action( 'wp_ajax_mio_optimize_single', [ $optimizer, 'ajax_optimize_single' ] );
    }

    /**
     * Displays admin notices if needed.
     */
    public function display_admin_notices() {
        // Check if plugin requirements are met
        if ( ! $this->check_requirements() ) {
            $this->display_requirements_notice();
        }
    }

    /**
     * Checks if plugin requirements are met.
     *
     * @return bool True if requirements are met, false otherwise.
     */
    private function check_requirements() {
        // Check PHP version
        if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
            return false;
        }

        // Check WordPress version
        if ( version_compare( get_bloginfo( 'version' ), '5.8', '<' ) ) {
            return false;
        }

        // Check if at least one optimization method is available
        if ( ! extension_loaded( 'imagick' ) && ! extension_loaded( 'gd' ) ) {
            // API fallback is available, so this is not a hard requirement
            Logger::get_instance()->info( 'No local image libraries available, will use API fallback' );
        }

        return true;
    }

    /**
     * Displays a notice about unmet requirements.
     */
    private function display_requirements_notice() {
        $message = sprintf(
            /* translators: %1$s: Plugin name, %2$s: Required PHP version, %3$s: Required WordPress version */
            __( '%1$s requires PHP %2$s or higher and WordPress %3$s or higher.', 'morden-image-optimize' ),
            '<strong>Morden Image Optimizer</strong>',
            '7.4',
            '5.8'
        );

        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            wp_kses_post( $message )
        );
    }

    /**
     * Gets the plugin version.
     *
     * @return string
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * Prevents cloning of the instance.
     */
    private function __clone() {}

    /**
     * Prevents unserialization of the instance.
     */
    public function __wakeup() {}
}

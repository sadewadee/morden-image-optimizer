<?php
/**
 * Morden Image Optimizer
 *
 * @package           MordenImageOptimizer
 * @author            AI Assistant
 * @copyright         2025
 * @license           GPL-3.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Morden Image Optimizer
 * Plugin URI:        https://mordenhost.com/morden-image-optimizer
 * Description:       A modern, user-friendly image optimizer with advanced features including bulk optimization, backups, and auto-updates.
 * Version:           1.0.2
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Morden Team
 * Author URI:        https://mordenhost.com
 *
 * License:           GPL v3 or later
 * Text Domain:       morden_optimizer
 * Domain Path:       /languages
 */

// Exit if accessed directly for security.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants.
define( 'MIO_VERSION', '1.0.2' );
define( 'MIO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MIO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load the autoloader - this is the magic that makes everything work.
require_once MIO_PLUGIN_DIR . 'lib/autoload.php';

// Import the main plugin class.
use MordenImageOptimizer\Core\Plugin;
use MordenImageOptimizer\Admin\Onboarding;

/**
 * Begins execution of the plugin.
 *
 * Since everything is now class-based and autoloaded, we only need to
 * instantiate the main plugin class and let it handle the rest.
 *
 * @since 1.0.0
 */
function mio_run_plugin() {
    Plugin::get_instance();
}

// Initialize the plugin after WordPress is fully loaded.
add_action( 'plugins_loaded', 'mio_run_plugin' );

/**
 * Activation hook: Sets up the plugin for the first time.
 * For the user, this means a guided start with welcome screen.
 *
 * @since 1.0.0
 */
function mio_activate() {
    Onboarding::set_welcome_redirect();
}
register_activation_hook( __FILE__, 'mio_activate' );

/**
 * Deactivation hook: Clean up temporary data.
 *
 * @since 1.0.0
 */
function mio_deactivate() {
    delete_transient( '_mio_welcome_screen_redirect' );
    delete_transient( 'mio_update_info' );
}
register_deactivation_hook( __FILE__, 'mio_deactivate' );

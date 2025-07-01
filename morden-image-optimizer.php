<?php
/**
 * Plugin Name:       Morden Image Optimizer
 * Plugin URI:        https://mordenhost.com/morden-image-optimizer
 * Description:       A modern, user-friendly image optimizer with advanced features including bulk optimization, backups, and auto-updates.
 * Version:           1.0.2
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Morden Team
 * Author URI:        https://mordenhost.com
 * License:           GPL v3 or later
 * Text Domain:       morden_optimizer
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MIO_VERSION', '1.0.2' );
define( 'MIO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MIO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once MIO_PLUGIN_DIR . 'lib/autoload.php';

use MordenImageOptimizer\Core\Plugin;
use MordenImageOptimizer\Core\DatabaseManager;
use MordenImageOptimizer\Admin\Onboarding;

function mio_run_plugin() {
    Plugin::get_instance();
}
add_action( 'plugins_loaded', 'mio_run_plugin' );

function mio_activate() {
    $db_manager = DatabaseManager::get_instance();
    $db_manager->create_tables();

    Onboarding::set_welcome_redirect();
}
register_activation_hook( __FILE__, 'mio_activate' );

function mio_deactivate() {
    delete_transient( '_mio_welcome_screen_redirect' );
    delete_transient( 'mio_update_info' );
}
register_deactivation_hook( __FILE__, 'mio_deactivate' );

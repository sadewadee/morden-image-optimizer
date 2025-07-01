<?php
/**
 * Plugin Name:       Morden Image Optimizer
 * Plugin URI:        https://github.com/sadewadee/morden-image-optimizer
 * Description:       A modern, user-friendly image optimizer with bulk optimization, backups, and auto-updates. Reduce image file sizes by up to 60% automatically.
 * Version:           1.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Morden Team
 * Author URI:        https://mordenhost.com
 * License:           GPL v3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       morden_optimizer
 * Domain Path:       /languages
 * Network:           false
 * Update URI:        https://github.com/sadewadee/morden-image-optimizer
 *
 * @package           MordenImageOptimizer
 * @author            Morden Team
 * @copyright         2025 Morden Team
 * @license           GPL-3.0-or-later
 * @since             1.0.0
 */

// Exit if accessed directly for security.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MIO_VERSION', '1.1.0' );
define( 'MIO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MIO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MIO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

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

<?php
/**
 * Morden Image Optimizer Uninstall
 *
 * @package MordenImageOptimizer
 * @since 1.0.0
 */

// Exit if accessed directly and not during uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Clean up plugin data
use MordenImageOptimizer\\Core\\DatabaseManager;

// Remove custom tables
$db_manager = new DatabaseManager();
$db_manager->drop_tables();

// Remove options
delete_option( 'mio_settings' );
delete_option( 'mio_db_version' );

// Clean up post meta
global $wpdb;
$wpdb->query( "DELETE FROM $wpdb->postmeta WHERE meta_key LIKE '_mio_%'" );

// Clean up transients
delete_transient( 'mio_update_info' );
delete_transient( '_mio_welcome_screen_redirect' );

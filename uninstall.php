<?php
// File: uninstall.php

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

// Manual cleanup without using classes
global $wpdb;

// Remove custom tables
$tables = [
    $wpdb->prefix . 'mio_optimization_log',
    $wpdb->prefix . 'mio_optimization_queue'
];

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS $table" );
}

// Remove options
$options = [
    'mio_settings',
    'mio_db_version',
    'mio_activation_time',
    'mio_welcome_notice_dismissed',
    'mio_setup_notice_dismissed',
    'mio_completed_setup_steps'
];

foreach ( $options as $option ) {
    delete_option( $option );
}

// Clean up post meta
$wpdb->query( "DELETE FROM $wpdb->postmeta WHERE meta_key LIKE '_mio_%'" );

// Clean up transients
$transients = [
    'mio_update_info',
    '_mio_welcome_screen_redirect',
    'mio_bulk_optimization_paused'
];

foreach ( $transients as $transient ) {
    delete_transient( $transient );
}

// Clean up scheduled events
wp_clear_scheduled_hook( 'mio_cleanup_old_backups' );

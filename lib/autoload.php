<?php
// File: lib/autoload.php

/**
 * Morden Image Optimizer PSR-4-style Autoloader.
 *
 * This autoloader handles all classes for the plugin, including
 * internal classes and third-party libraries, following PSR-4 specification.
 *
 * @package MordenImageOptimizer\Core
 * @since 1.0.0
 */

spl_autoload_register(function ( $class ) {
    /**
     * A map of namespace prefixes to their base directories.
     *
     * This allows us to load our own plugin's classes and third-party
     * libraries from a single autoloader.
     *
     * @var array
     */
    $prefix_map = [
        // Plugin internal classes - maps MordenImageOptimizer\ to includes/
        'MordenImageOptimizer\\' => MIO_PLUGIN_DIR . 'includes/',

        // Third-party libraries - maps Tinify\ to lib/tinify/
        'Tinify\\' => MIO_PLUGIN_DIR . 'lib/tinify/',
    ];

    // Iterate through the namespace map to find the correct loader
    foreach ( $prefix_map as $prefix => $base_dir ) {
        // Does the class use this namespace prefix?
        $len = strlen( $prefix );
        if ( strncmp( $prefix, $class, $len ) !== 0 ) {
            // No, move to the next registered prefix
            continue;
        }

        // Get the relative class name by removing the namespace prefix
        $relative_class = substr( $class, $len );

        // Replace namespace separators with directory separators
        // and append .php extension
        $file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

        // If the file exists, require it and stop processing
        if ( file_exists( $file ) ) {
            require_once $file;
            return;
        }
    }
});

/**
 * Additional autoloader for legacy WordPress-style class names.
 *
 * This handles classes that don't use namespaces but follow
 * WordPress naming conventions (e.g., MIO_Legacy_Class).
 */
spl_autoload_register(function ( $class ) {
    // Only handle classes with MIO_ prefix
    if ( strpos( $class, 'MIO_' ) !== 0 ) {
        return;
    }

    $file_name = strtolower( str_replace( '_', '-', $class ) ) . '.php';
    $file_path = MIO_PLUGIN_DIR . 'includes/legacy/' . $file_name;

    if ( file_exists( $file_path ) ) {
        require_once $file_path;
    }
});

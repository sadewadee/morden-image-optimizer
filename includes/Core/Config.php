<?php
// File: includes/Core/Config.php

namespace MordenImageOptimizer\Core;

/**
 * Manages plugin configuration and default settings.
 *
 * This class provides a centralized way to manage all plugin settings
 * with proper defaults, validation, and caching for optimal performance.
 *
 * @package MordenImageOptimizer\Core
 * @since 1.0.0
 */
class Config {

    /**
     * Single instance of the class.
     *
     * @var Config|null
     */
    private static $instance = null;

    /**
     * Cached settings array.
     *
     * @var array
     */
    private $settings = [];

    /**
     * Default plugin settings.
     *
     * @var array
     */
    private $defaults = [
        'compression_level'     => 82,
        'api_service'          => 'resmushit',
        'tinypng_api_key'      => '',
        'keep_original'        => false,
        'enable_webp'          => false,
        'async_optimization'   => false,
        'max_width'            => 0,
        'max_height'           => 0,
        'backup_retention_days' => 30,
        'auto_optimize'        => true,
        'optimize_thumbnails'  => true,
        'exclude_sizes'        => [],
        'quality_jpeg'         => 82,
        'quality_png'          => 90,
        'quality_webp'         => 80,
    ];

    /**
     * Settings that require validation.
     *
     * @var array
     */
    private $validation_rules = [
        'compression_level' => [ 'type' => 'int', 'min' => 1, 'max' => 100 ],
        'quality_jpeg'      => [ 'type' => 'int', 'min' => 1, 'max' => 100 ],
        'quality_png'       => [ 'type' => 'int', 'min' => 1, 'max' => 100 ],
        'quality_webp'      => [ 'type' => 'int', 'min' => 1, 'max' => 100 ],
        'max_width'         => [ 'type' => 'int', 'min' => 0, 'max' => 10000 ],
        'max_height'        => [ 'type' => 'int', 'min' => 0, 'max' => 10000 ],
        'backup_retention_days' => [ 'type' => 'int', 'min' => 1, 'max' => 365 ],
        'api_service'       => [ 'type' => 'string', 'options' => [ 'resmushit', 'tinypng' ] ],
    ];

    /**
     * Gets the single instance of the class.
     *
     * @return Config
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor.
     */
    private function __construct() {
        $this->load_settings();
    }

    /**
     * Loads settings from database with defaults fallback.
     */
    private function load_settings() {
        $saved_settings = get_option( 'mio_settings', [] );
        $this->settings = wp_parse_args( $saved_settings, $this->defaults );

        // Validate loaded settings
        $this->settings = $this->validate_settings( $this->settings );
    }

    /**
     * Gets a configuration value.
     *
     * @param string $key Configuration key.
     * @param mixed  $default Default value if key doesn't exist.
     * @return mixed Configuration value.
     */
    public function get( $key, $default = null ) {
        if ( isset( $this->settings[ $key ] ) ) {
            return $this->settings[ $key ];
        }

        if ( isset( $this->defaults[ $key ] ) ) {
            return $this->defaults[ $key ];
        }

        return $default;
    }

    /**
     * Sets a configuration value.
     *
     * @param string $key Configuration key.
     * @param mixed  $value Configuration value.
     * @return bool True on success, false on failure.
     */
    public function set( $key, $value ) {
        // Validate the value
        $validated_value = $this->validate_single_setting( $key, $value );

        if ( false === $validated_value ) {
            Logger::get_instance()->error( "Invalid configuration value for key: $key", [ 'value' => $value ] );
            return false;
        }

        $this->settings[ $key ] = $validated_value;
        return $this->save_settings();
    }

    /**
     * Gets all configuration values.
     *
     * @return array All configuration values.
     */
    public function get_all() {
        return $this->settings;
    }

    /**
     * Gets default configuration values.
     *
     * @return array Default configuration values.
     */
    public function get_defaults() {
        return $this->defaults;
    }

    /**
     * Updates multiple configuration values at once.
     *
     * @param array $new_settings Array of key-value pairs.
     * @return bool True on success, false on failure.
     */
    public function update( $new_settings ) {
        if ( ! is_array( $new_settings ) ) {
            return false;
        }

        $validated_settings = $this->validate_settings( $new_settings );
        $this->settings = wp_parse_args( $validated_settings, $this->settings );

        return $this->save_settings();
    }

    /**
     * Resets configuration to defaults.
     *
     * @return bool True on success, false on failure.
     */
    public function reset_to_defaults() {
        $this->settings = $this->defaults;
        return $this->save_settings();
    }

    /**
     * Saves settings to database.
     *
     * @return bool True on success, false on failure.
     */
    private function save_settings() {
        $result = update_option( 'mio_settings', $this->settings );

        if ( $result ) {
            Logger::get_instance()->info( 'Configuration updated successfully' );
        } else {
            Logger::get_instance()->error( 'Failed to update configuration' );
        }

        return $result;
    }

    /**
     * Validates all settings according to validation rules.
     *
     * @param array $settings Settings to validate.
     * @return array Validated settings.
     */
    private function validate_settings( $settings ) {
        $validated = [];

        foreach ( $settings as $key => $value ) {
            $validated_value = $this->validate_single_setting( $key, $value );

            if ( false !== $validated_value ) {
                $validated[ $key ] = $validated_value;
            } elseif ( isset( $this->defaults[ $key ] ) ) {
                $validated[ $key ] = $this->defaults[ $key ];
                Logger::get_instance()->warning( "Invalid value for $key, using default", [ 'value' => $value ] );
            }
        }

        return $validated;
    }

    /**
     * Validates a single setting value.
     *
     * @param string $key Setting key.
     * @param mixed  $value Setting value.
     * @return mixed Validated value or false if invalid.
     */
    private function validate_single_setting( $key, $value ) {
        if ( ! isset( $this->validation_rules[ $key ] ) ) {
            // No validation rule, return as-is
            return $value;
        }

        $rule = $this->validation_rules[ $key ];

        switch ( $rule['type'] ) {
            case 'int':
                $value = absint( $value );
                if ( isset( $rule['min'] ) && $value < $rule['min'] ) {
                    return $rule['min'];
                }
                if ( isset( $rule['max'] ) && $value > $rule['max'] ) {
                    return $rule['max'];
                }
                return $value;

            case 'string':
                $value = sanitize_text_field( $value );
                if ( isset( $rule['options'] ) && ! in_array( $value, $rule['options'], true ) ) {
                    return false;
                }
                return $value;

            case 'bool':
                return (bool) $value;

            case 'array':
                return is_array( $value ) ? $value : [];

            default:
                return $value;
        }
    }

    /**
     * Checks if a feature is enabled.
     *
     * @param string $feature Feature name.
     * @return bool True if enabled, false otherwise.
     */
    public function is_enabled( $feature ) {
        return (bool) $this->get( $feature, false );
    }

    /**
     * Gets optimization quality for a specific format.
     *
     * @param string $format Image format (jpeg, png, webp).
     * @return int Quality value.
     */
    public function get_quality_for_format( $format ) {
        $format = strtolower( $format );

        switch ( $format ) {
            case 'jpeg':
            case 'jpg':
                return $this->get( 'quality_jpeg', 82 );
            case 'png':
                return $this->get( 'quality_png', 90 );
            case 'webp':
                return $this->get( 'quality_webp', 80 );
            default:
                return $this->get( 'compression_level', 82 );
        }
    }

    /**
     * Exports configuration for backup or migration.
     *
     * @return array Configuration export data.
     */
    public function export() {
        return [
            'version' => MIO_VERSION,
            'timestamp' => current_time( 'timestamp' ),
            'settings' => $this->settings,
        ];
    }

    /**
     * Imports configuration from backup.
     *
     * @param array $import_data Import data.
     * @return bool True on success, false on failure.
     */
    public function import( $import_data ) {
        if ( ! is_array( $import_data ) || ! isset( $import_data['settings'] ) ) {
            return false;
        }

        $imported_settings = $import_data['settings'];

        // Validate imported settings
        $validated_settings = $this->validate_settings( $imported_settings );

        if ( empty( $validated_settings ) ) {
            return false;
        }

        $this->settings = wp_parse_args( $validated_settings, $this->defaults );

        return $this->save_settings();
    }
}

<?php
// File: includes/Core/Updater.php

namespace MordenImageOptimizer\\Core;

/**
 * Handles the plugin's auto-update process from GitHub.
 */
final class Updater {
    private static $instance = null;
    private $plugin_file;
    private $plugin_slug;
    private $github_repo_url;
    private $transient_key;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->plugin_file = MIO_PLUGIN_DIR . 'morden-image-optimizer.php';
        $this->plugin_slug = plugin_basename( $this->plugin_file );
        $this->github_repo_url = 'https://api.github.com/repos/YOUR_USERNAME/morden-image-optimizer';
        $this->transient_key = 'mio_update_info';

        $this->init_hooks();
    }

    private function init_hooks() {
        add_filter( 'site_transient_update_plugins', [ $this, 'check_for_updates' ] );
        add_filter( 'plugins_api', [ $this, 'plugin_api_info' ], 10, 3 );
    }

    public function check_for_updates( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $remote_info = $this->get_remote_info();
        if ( ! $remote_info ) {
            return $transient;
        }

        $current_version = get_plugin_data( $this->plugin_file )['Version'];

        if ( version_compare( $remote_info->version, $current_version, '>' ) ) {
            $transient->response[ $this->plugin_slug ] = $remote_info;
        }

        return $transient;
    }

    public function plugin_api_info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action || $this->plugin_slug !== $args->slug ) {
            return $result;
        }

        return $this->get_remote_info();
    }

    private function get_remote_info() {
        $remote_info = get_transient( $this->transient_key );

        if ( false === $remote_info ) {
            $response = wp_remote_get( $this->github_repo_url . '/releases/latest' );

            if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
                return false;
            }

            $release_data = json_decode( wp_remote_retrieve_body( $response ) );

            if ( ! $release_data ) {
                return false;
            }

            $remote_info = (object) [
                'slug' => $this->plugin_slug,
                'version' => ltrim( $release_data->tag_name, 'v' ),
                'new_version' => ltrim( $release_data->tag_name, 'v' ),
                'package' => $release_data->zipball_url,
                'tested' => '6.5',
                'requires_php' => '7.4',
            ];

            set_transient( $this->transient_key, $remote_info, 12 * HOUR_IN_SECONDS );
        }

        return $remote_info;
    }
}

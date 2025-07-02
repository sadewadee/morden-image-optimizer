<?php
// File: includes/Core/Updater.php

namespace MordenImageOptimizer\Core;

/**
 * Handles the plugin's auto-update process from GitHub.
 *
 * This class provides seamless auto-update functionality by checking
 * GitHub releases and integrating with WordPress update system.
 *
 * @package MordenImageOptimizer\Core
 * @since 1.0.0
 */
final class Updater {

    /**
     * Single instance of the class.
     *
     * @var Updater|null
     */
    private static $instance = null;

    /**
     * Path to the main plugin file.
     *
     * @var string
     */
    private $plugin_file;

    /**
     * Plugin slug (basename).
     *
     * @var string
     */
    private $plugin_slug;

    /**
     * GitHub repository URL.
     *
     * @var string
     */
    private $github_repo_url;

    /**
     * Transient key for caching update info.
     *
     * @var string
     */
    private $transient_key;

    /**
     * Logger instance.
     *
     * @var Logger
     */
    private $logger;

    /**
     * Gets the single instance of the class.
     *
     * @return Updater
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
        $this->plugin_file = MIO_PLUGIN_DIR . 'morden-image-optimizer.php';
        $this->plugin_slug = plugin_basename( $this->plugin_file );
        $this->github_repo_url = 'https://api.github.com/repos/sadewadee/morden-image-optimizer';
        $this->transient_key = 'mio_update_info';
        $this->logger = Logger::get_instance();

        $this->init_hooks();
    }

    /**
     * Initializes WordPress hooks for the update system.
     */
    private function init_hooks() {
        add_filter( 'site_transient_update_plugins', [ $this, 'check_for_updates' ] );
        add_filter( 'plugins_api', [ $this, 'plugin_api_info' ], 10, 3 );
        add_action( 'upgrader_process_complete', [ $this, 'after_update' ], 10, 2 );
        add_action( 'admin_notices', [ $this, 'show_update_notice' ] );

        // Add custom update message
        add_action( "in_plugin_update_message-{$this->plugin_slug}", [ $this, 'show_upgrade_notification' ], 10, 2 );
    }

    /**
     * Checks for plugin updates.
     *
     * @param object $transient The update_plugins transient.
     * @return object Modified transient.
     */
    public function check_for_updates( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        // Get remote version info
        $remote_info = $this->get_remote_info();
        if ( ! $remote_info ) {
            return $transient;
        }

        $current_version = $this->get_current_version();

        // Compare versions
        if ( version_compare( $remote_info->version, $current_version, '>' ) ) {
            $transient->response[ $this->plugin_slug ] = $remote_info;

            $this->logger->info( 'New plugin version available', [
                'current_version' => $current_version,
                'new_version' => $remote_info->version,
            ]);
        } else {
            // Remove from response if current version is up to date
            unset( $transient->response[ $this->plugin_slug ] );
        }

        return $transient;
    }

    /**
     * Provides plugin information for the "View details" popup.
     *
     * @param bool|object|array $result The result object.
     * @param string            $action The type of information being requested.
     * @param object            $args   Plugin API arguments.
     * @return object|bool Plugin information object or original result.
     */
    public function plugin_api_info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action ) {
            return $result;
        }

        if ( ! isset( $args->slug ) || $args->slug !== dirname( $this->plugin_slug ) ) {
            return $result;
        }

        $remote_info = $this->get_remote_info();
        if ( ! $remote_info ) {
            return $result;
        }

        return $remote_info;
    }

    /**
     * Gets remote plugin information from GitHub.
     *
     * @return object|false Plugin information object or false on failure.
     */
    private function get_remote_info() {
        $remote_info = get_transient( $this->transient_key );

        if ( false === $remote_info ) {
            $remote_info = $this->fetch_remote_info();

            if ( $remote_info ) {
                // Cache for 12 hours
                set_transient( $this->transient_key, $remote_info, 12 * HOUR_IN_SECONDS );
            }
        }

        return $remote_info;
    }

    /**
     * Fetches remote information from GitHub API.
     *
     * @return object|false Plugin information object or false on failure.
     */
    private function fetch_remote_info() {
        $this->logger->debug( 'Fetching remote plugin information from GitHub' );

        // Try to get info.json first (preferred method)
        $info_response = wp_remote_get(
            'https://raw.githubusercontent.com/sadewadee/morden-image-optimizer/main/info.json',
            [
                'timeout' => 15,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'Morden Image Optimizer/' . MIO_VERSION . '; ' . get_bloginfo( 'url' ),
                ],
            ]
        );

        if ( ! is_wp_error( $info_response ) && 200 === wp_remote_retrieve_response_code( $info_response ) ) {
            $info_data = json_decode( wp_remote_retrieve_body( $info_response ), true );

            if ( $info_data && isset( $info_data['version'] ) ) {
                return $this->format_plugin_info( $info_data, 'info_json' );
            }
        }

        // Fallback to GitHub releases API
        $release_response = wp_remote_get(
            $this->github_repo_url . '/releases/latest',
            [
                'timeout' => 15,
                'headers' => [
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'Morden Image Optimizer/' . MIO_VERSION . '; ' . get_bloginfo( 'url' ),
                ],
            ]
        );

        if ( is_wp_error( $release_response ) ) {
            $this->logger->error( 'Failed to fetch release info from GitHub', [
                'error' => $release_response->get_error_message(),
            ]);
            return false;
        }

        if ( 200 !== wp_remote_retrieve_response_code( $release_response ) ) {
            $this->logger->error( 'GitHub API returned non-200 response', [
                'response_code' => wp_remote_retrieve_response_code( $release_response ),
            ]);
            return false;
        }

        $release_data = json_decode( wp_remote_retrieve_body( $release_response ), true );

        if ( ! $release_data || ! isset( $release_data['tag_name'] ) ) {
            $this->logger->error( 'Invalid release data from GitHub API' );
            return false;
        }

        return $this->format_plugin_info( $release_data, 'github_api' );
    }

    /**
     * Formats plugin information from different sources.
     *
     * @param array  $data Raw data from API.
     * @param string $source Data source ('info_json' or 'github_api').
     * @return object Formatted plugin information.
     */
    private function format_plugin_info( $data, $source ) {
        if ( 'info_json' === $source ) {
            return (object) [
                'slug' => dirname( $this->plugin_slug ),
                'plugin' => $this->plugin_slug,
                'version' => $data['version'],
                'new_version' => $data['version'],
                'tested' => $data['tested'] ?? '6.5',
                'requires_php' => $data['requires_php'] ?? '7.4',
                'requires' => $data['requires'] ?? '5.8',
                'package' => $data['download_url'] ?? '',
                'name' => $data['name'] ?? 'Morden Image Optimizer',
                'author' => $data['author'] ?? 'Morden Team',
                'homepage' => $data['homepage'] ?? 'https://mordenhost.com/morden-image-optimizer',
                'sections' => $data['sections'] ?? [],
                'banners' => $data['banners'] ?? [],
                'icons' => $data['icons'] ?? [],
            ];
        } else {
            // GitHub API format
            $version = ltrim( $data['tag_name'], 'v' );
            $download_url = '';

            // Look for plugin zip in assets
            if ( isset( $data['assets'] ) && is_array( $data['assets'] ) ) {
                foreach ( $data['assets'] as $asset ) {
                    if ( isset( $asset['name'] ) && strpos( $asset['name'], '.zip' ) !== false ) {
                        $download_url = $asset['browser_download_url'];
                        break;
                    }
                }
            }

            // Fallback to zipball if no zip asset found
            if ( empty( $download_url ) ) {
                $download_url = $data['zipball_url'] ?? '';
            }

            return (object) [
                'slug' => dirname( $this->plugin_slug ),
                'plugin' => $this->plugin_slug,
                'version' => $version,
                'new_version' => $version,
                'tested' => '6.5',
                'requires_php' => '7.4',
                'requires' => '5.8',
                'package' => $download_url,
                'name' => 'Morden Image Optimizer',
                'author' => 'Morden Team',
                'homepage' => 'https://mordenhost.com/morden-image-optimizer',
                'sections' => [
                    'description' => $data['body'] ?? 'A modern, user-friendly image optimizer.',
                    'changelog' => $data['body'] ?? '',
                ],
            ];
        }
    }

    /**
     * Gets the current plugin version.
     *
     * @return string Current version.
     */
    private function get_current_version() {
        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_data = get_plugin_data( $this->plugin_file );
        return $plugin_data['Version'] ?? MIO_VERSION;
    }

    /**
     * Handles actions after plugin update.
     *
     * @param \WP_Upgrader $upgrader WP_Upgrader instance.
     * @param array        $hook_extra Extra data for the hook.
     */
    public function after_update( $upgrader, $hook_extra ) {
        if ( ! isset( $hook_extra['action'] ) || 'update' !== $hook_extra['action'] ) {
            return;
        }

        if ( ! isset( $hook_extra['type'] ) || 'plugin' !== $hook_extra['type'] ) {
            return;
        }

        if ( ! isset( $hook_extra['plugins'] ) || ! is_array( $hook_extra['plugins'] ) ) {
            return;
        }

        if ( ! in_array( $this->plugin_slug, $hook_extra['plugins'], true ) ) {
            return;
        }

        // Clear update transient
        delete_transient( $this->transient_key );

        $new_version = $this->get_current_version();

        $this->logger->info( 'Plugin updated successfully', [
            'new_version' => $new_version,
        ]);

        // Set a transient to show update success message
        set_transient( 'mio_update_success', $new_version, 30 );
    }

    /**
     * Shows update notice in admin.
     */
    public function show_update_notice() {
        $updated_version = get_transient( 'mio_update_success' );

        if ( $updated_version ) {
            delete_transient( 'mio_update_success' );

            printf(
                '<div class="notice notice-success is-dismissible"><p><strong>%s</strong> %s</p></div>',
                esc_html__( 'Morden Image Optimizer', 'morden-image-optimize' ),
                sprintf(
                    /* translators: %s: Version number */
                    esc_html__( 'has been updated to version %s successfully!', 'morden-image-optimize' ),
                    esc_html( $updated_version )
                )
            );
        }
    }

    /**
     * Shows custom upgrade notification.
     *
     * @param array  $plugin_data Plugin data.
     * @param object $response    Update response.
     */
    public function show_upgrade_notification( $plugin_data, $response ) {
        if ( ! isset( $response->new_version ) ) {
            return;
        }

        printf(
            '<br><strong>%s:</strong> %s',
            esc_html__( 'Important', 'morden-image-optimize' ),
            esc_html__( 'Please backup your site before updating. This update may include database changes.', 'morden-image-optimize' )
        );
    }

    /**
     * Forces a check for updates (useful for debugging).
     *
     * @return object|false Update information or false.
     */
    public function force_check() {
        delete_transient( $this->transient_key );
        return $this->get_remote_info();
    }

    /**
     * Gets update information for display.
     *
     * @return array Update status information.
     */
    public function get_update_status() {
        $current_version = $this->get_current_version();
        $remote_info = $this->get_remote_info();

        $status = [
            'current_version' => $current_version,
            'latest_version' => $remote_info ? $remote_info->version : 'Unknown',
            'update_available' => false,
            'last_checked' => get_option( '_transient_timeout_' . $this->transient_key ),
        ];

        if ( $remote_info && version_compare( $remote_info->version, $current_version, '>' ) ) {
            $status['update_available'] = true;
        }

        return $status;
    }
}

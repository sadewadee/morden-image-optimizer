<?php
// File: includes/Admin/SettingsPage.php

namespace MordenImageOptimizer\Admin;

use MordenImageOptimizer\Core\Config;
use MordenImageOptimizer\Core\Security;
use MordenImageOptimizer\Core\Optimizer;
use MordenImageOptimizer\API\APIHandler;

/**
 * Manages the plugin's admin settings page.
 *
 * Provides a user-friendly interface for configuring all plugin options
 * with real-time validation and helpful guidance.
 *
 * @package MordenImageOptimizer\Admin
 * @since 1.0.0
 */
class SettingsPage {

    /**
     * Single instance of the class.
     *
     * @var SettingsPage|null
     */
    private static $instance = null;

    /**
     * Config instance.
     *
     * @var Config
     */
    private $config;

    /**
     * Gets the single instance of the class.
     *
     * @return SettingsPage
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
        $this->config = Config::get_instance();
        $this->init_hooks();
    }

    /**
     * Initializes WordPress hooks.
     */
    private function init_hooks() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // AJAX handlers
        add_action( 'wp_ajax_mio_test_api_connection', [ $this, 'ajax_test_api_connection' ] );
        add_action( 'wp_ajax_mio_reset_settings', [ $this, 'ajax_reset_settings' ] );
    }

    /**
     * Adds the options page to the WordPress admin menu.
     */
    public function add_admin_menu() {
        $page_hook = add_options_page(
            __( 'Morden Image Optimizer Settings', 'morden_optimizer' ),
            __( 'Morden Optimizer', 'morden_optimizer' ),
            'manage_options',
            'morden_optimizer',
            [ $this, 'render_settings_page' ]
        );

        // Add contextual help
        add_action( "load-$page_hook", [ $this, 'add_contextual_help' ] );
    }

    /**
     * Registers settings, sections, and fields using the Settings API.
     */
    public function register_settings() {
        register_setting(
            'mio_settings_group',
            'mio_settings',
            [ $this, 'sanitize_settings' ]
        );

        // General Settings Section
        add_settings_section(
            'mio_general_section',
            __( 'General Settings', 'morden_optimizer' ),
            [ $this, 'render_general_section_description' ],
            'morden_optimizer'
        );

        add_settings_field(
            'auto_optimize',
            __( 'Auto Optimization', 'morden_optimizer' ),
            [ $this, 'render_auto_optimize_field' ],
            'morden_optimizer',
            'mio_general_section'
        );

        add_settings_field(
            'compression_level',
            __( 'Compression Quality', 'morden_optimizer' ),
            [ $this, 'render_compression_level_field' ],
            'morden_optimizer',
            'mio_general_section'
        );

        add_settings_field(
            'optimize_thumbnails',
            __( 'Optimize Thumbnails', 'morden_optimizer' ),
            [ $this, 'render_optimize_thumbnails_field' ],
            'morden_optimizer',
            'mio_general_section'
        );

        // API Settings Section
        add_settings_section(
            'mio_api_section',
            __( 'API Settings', 'morden_optimizer' ),
            [ $this, 'render_api_section_description' ],
            'morden_optimizer'
        );

        add_settings_field(
            'api_service',
            __( 'API Service', 'morden_optimizer' ),
            [ $this, 'render_api_service_field' ],
            'morden_optimizer',
            'mio_api_section'
        );

        add_settings_field(
            'tinypng_api_key',
            __( 'TinyPNG API Key', 'morden_optimizer' ),
            [ $this, 'render_tinypng_api_key_field' ],
            'morden_optimizer',
            'mio_api_section'
        );

        // Advanced Settings Section
        add_settings_section(
            'mio_advanced_section',
            __( 'Advanced Settings', 'morden_optimizer' ),
            [ $this, 'render_advanced_section_description' ],
            'morden_optimizer'
        );

        add_settings_field(
            'keep_original',
            __( 'Backup Original Images', 'morden_optimizer' ),
            [ $this, 'render_keep_original_field' ],
            'morden_optimizer',
            'mio_advanced_section'
        );

        add_settings_field(
            'max_dimensions',
            __( 'Maximum Dimensions', 'morden_optimizer' ),
            [ $this, 'render_max_dimensions_field' ],
            'morden_optimizer',
            'mio_advanced_section'
        );

        add_settings_field(
            'server_status',
            __( 'Server Status', 'morden_optimizer' ),
            [ $this, 'render_server_status_field' ],
            'morden_optimizer',
            'mio_advanced_section'
        );
    }

    /**
     * Enqueues CSS and JS for the admin settings page.
     *
     * @param string $hook_suffix The suffix of the current admin page.
     */
    public function enqueue_assets( $hook_suffix ) {
        if ( 'settings_page_morden_optimizer' !== $hook_suffix ) {
            return;
        }

        wp_enqueue_style(
            'mio-admin-styles',
            MIO_PLUGIN_URL . 'assets/css/admin-styles.css',
            [],
            MIO_VERSION
        );

        wp_enqueue_script(
            'mio-settings-js',
            MIO_PLUGIN_URL . 'assets/js/settings-page.js',
            [ 'jquery' ],
            MIO_VERSION,
            true
        );

        wp_localize_script( 'mio-settings-js', 'mio_settings', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => Security::create_nonce( 'settings' ),
            'strings' => [
                'testing' => __( 'Testing...', 'morden_optimizer' ),
                'test_success' => __( 'Connection successful!', 'morden_optimizer' ),
                'test_failed' => __( 'Connection failed:', 'morden_optimizer' ),
                'reset_confirm' => __( 'Are you sure you want to reset all settings to defaults?', 'morden_optimizer' ),
                'reset_success' => __( 'Settings reset successfully!', 'morden_optimizer' ),
            ],
        ]);
    }

    /**
     * Renders the main settings page.
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'morden_optimizer' ) );
        }

        // Handle welcome parameter
        $show_welcome = isset( $_GET['welcome'] ) && 'true' === $_GET['welcome'];
        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e( 'Morden Image Optimizer Settings', 'morden_optimizer' ); ?>
                <span class="mio-version">v<?php echo esc_html( MIO_VERSION ); ?></span>
            </h1>

            <?php if ( $show_welcome ) : ?>
                <div class="notice notice-success mio-welcome-notice">
                    <h2><?php esc_html_e( '🎉 Welcome to Morden Image Optimizer!', 'morden_optimizer' ); ?></h2>
                    <p><?php esc_html_e( 'Thank you for installing our plugin! Here are some quick tips to get you started:', 'morden_optimizer' ); ?></p>
                    <ul>
                        <li><?php esc_html_e( '✅ Configure your optimization settings below', 'morden_optimizer' ); ?></li>
                        <li><?php esc_html_e( '✅ Enable backup for safety (recommended)', 'morden_optimizer' ); ?></li>
                        <li><?php esc_html_e( '✅ Use Bulk Optimize to optimize existing images', 'morden_optimizer' ); ?></li>
                    </ul>
                    <p>
                        <a href="<?php echo esc_url( admin_url( 'upload.php?page=mio-bulk-optimize' ) ); ?>" class="button button-primary">
                            <?php esc_html_e( 'Go to Bulk Optimizer', 'morden_optimizer' ); ?>
                        </a>
                        <button type="button" class="button button-secondary mio-dismiss-welcome">
                            <?php esc_html_e( 'Dismiss', 'morden_optimizer' ); ?>
                        </button>
                    </p>
                </div>
            <?php endif; ?>

            <?php settings_errors(); ?>

            <div class="mio-settings-container">
                <form method="post" action="options.php" class="mio-settings-form">
                    <?php
                    settings_fields( 'mio_settings_group' );
                    do_settings_sections( 'morden_optimizer' );
                    ?>

                    <div class="mio-form-actions">
                        <?php submit_button( __( 'Save Settings', 'morden_optimizer' ), 'primary', 'submit', false ); ?>
                        <button type="button" class="button button-secondary mio-reset-settings">
                            <?php esc_html_e( 'Reset to Defaults', 'morden_optimizer' ); ?>
                        </button>
                    </div>
                </form>

                <div class="mio-sidebar">
                    <div class="mio-info-box">
                        <h3><?php esc_html_e( 'Quick Actions', 'morden_optimizer' ); ?></h3>
                        <p>
                            <a href="<?php echo esc_url( admin_url( 'upload.php?page=mio-bulk-optimize' ) ); ?>" class="button button-secondary">
                                <?php esc_html_e( 'Bulk Optimize Images', 'morden_optimizer' ); ?>
                            </a>
                        </p>
                        <p>
                            <a href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>" class="button button-secondary">
                                <?php esc_html_e( 'View Media Library', 'morden_optimizer' ); ?>
                            </a>
                        </p>
                    </div>

                    <div class="mio-info-box">
                        <h3><?php esc_html_e( 'Need Help?', 'morden_optimizer' ); ?></h3>
                        <p><?php esc_html_e( 'Check out our documentation and support resources.', 'morden_optimizer' ); ?></p>
                        <p>
                            <a href="https://mordenhost.com/morden-image-optimizer/docs" target="_blank" class="button button-secondary">
                                <?php esc_html_e( 'Documentation', 'morden_optimizer' ); ?>
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    // Section descriptions
    public function render_general_section_description() {
        echo '<p>' . esc_html__( 'Configure basic optimization settings for your images.', 'morden_optimizer' ) . '</p>';
    }

    public function render_api_section_description() {
        echo '<p>' . esc_html__( 'Choose and configure external API services for image optimization.', 'morden_optimizer' ) . '</p>';
    }

    public function render_advanced_section_description() {
        echo '<p>' . esc_html__( 'Advanced settings for power users and specific use cases.', 'morden_optimizer' ) . '</p>';
    }

    // Field renderers
    public function render_auto_optimize_field() {
        $auto_optimize = $this->config->get( 'auto_optimize', true );
        ?>
        <label for="mio_auto_optimize">
            <input type="checkbox" id="mio_auto_optimize" name="mio_settings[auto_optimize]" value="1" <?php checked( $auto_optimize ); ?> />
            <?php esc_html_e( 'Automatically optimize images when uploaded', 'morden_optimizer' ); ?>
        </label>
        <p class="description">
            <?php esc_html_e( 'When enabled, images will be optimized automatically during upload. Disable this if you prefer manual optimization only.', 'morden_optimizer' ); ?>
        </p>
        <?php
    }

    public function render_compression_level_field() {
        $level = $this->config->get( 'compression_level', 82 );
        ?>
        <input type="range" id="mio_compression_level" name="mio_settings[compression_level]"
               min="1" max="100" value="<?php echo esc_attr( $level ); ?>" class="mio-range-slider" />
        <div class="mio-range-display">
            <span class="mio-range-value"><?php echo esc_html( $level ); ?></span>
            <span class="mio-range-label">
                <?php if ( $level >= 90 ) : ?>
                    <?php esc_html_e( '(Best Quality)', 'morden_optimizer' ); ?>
                <?php elseif ( $level >= 75 ) : ?>
                    <?php esc_html_e( '(Recommended)', 'morden_optimizer' ); ?>
                <?php elseif ( $level >= 60 ) : ?>
                    <?php esc_html_e( '(Good Balance)', 'morden_optimizer' ); ?>
                <?php else : ?>
                    <?php esc_html_e( '(Smaller Files)', 'morden_optimizer' ); ?>
                <?php endif; ?>
            </span>
        </div>
        <p class="description">
            <?php esc_html_e( 'Higher values preserve more quality but result in larger files. 75-85 is recommended for most websites.', 'morden_optimizer' ); ?>
        </p>
        <?php
    }

    public function render_optimize_thumbnails_field() {
        $optimize_thumbnails = $this->config->get( 'optimize_thumbnails', true );
        ?>
        <label for="mio_optimize_thumbnails">
            <input type="checkbox" id="mio_optimize_thumbnails" name="mio_settings[optimize_thumbnails]" value="1" <?php checked( $optimize_thumbnails ); ?> />
            <?php esc_html_e( 'Optimize thumbnail images', 'morden_optimizer' ); ?>
        </label>
        <p class="description">
            <?php esc_html_e( 'When enabled, all generated thumbnail sizes will also be optimized. This provides maximum space savings.', 'morden_optimizer' ); ?>
        </p>
        <?php
    }

    public function render_api_service_field() {
        $selected_api = $this->config->get( 'api_service', 'resmushit' );
        $services = APIHandler::get_available_services();
        ?>
        <select name="mio_settings[api_service]" id="mio_api_service" class="mio-api-selector">
            <?php foreach ( $services as $key => $service ) : ?>
                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $selected_api, $key ); ?>>
                    <?php echo esc_html( $service['name'] . ' - ' . $service['description'] ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="button button-secondary mio-test-api" data-service="<?php echo esc_attr( $selected_api ); ?>">
            <?php esc_html_e( 'Test Connection', 'morden_optimizer' ); ?>
        </button>
        <p class="description">
            <?php esc_html_e( 'Choose your preferred external API. Local optimization (Imagick/GD) will be prioritized when available.', 'morden_optimizer' ); ?>
        </p>
        <div id="mio-api-test-result"></div>
        <?php
    }

    public function render_tinypng_api_key_field() {
        $api_key = $this->config->get( 'tinypng_api_key', '' );
        $selected_api = $this->config->get( 'api_service', 'resmushit' );
        ?>
        <div class="mio-api-config mio-api-config-tinypng" <?php echo 'tinypng' !== $selected_api ? 'style="display:none;"' : ''; ?>>
            <input type="password" name="mio_settings[tinypng_api_key]" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Enter your TinyPNG API key', 'morden_optimizer' ); ?>" />
            <button type="button" class="button button-secondary mio-toggle-password">
                <?php esc_html_e( 'Show', 'morden_optimizer' ); ?>
            </button>
            <p class="description">
                <?php
                printf(
                    /* translators: %s: TinyPNG API key registration URL */
                    esc_html__( 'Get your free TinyPNG API key from %s. Free accounts get 500 compressions per month.', 'morden_optimizer' ),
                    '<a href="https://tinypng.com/developers" target="_blank">tinypng.com/developers</a>'
                );
                ?>
            </p>
        </div>
        <?php
    }

    public function render_keep_original_field() {
        $keep_original = $this->config->get( 'keep_original', false );
        ?>
        <label for="mio_keep_original">
            <input type="checkbox" id="mio_keep_original" name="mio_settings[keep_original]" value="1" <?php checked( $keep_original ); ?> />
            <?php esc_html_e( 'Keep backup copies of original images', 'morden_optimizer' ); ?>
        </label>
        <p class="description">
            <?php esc_html_e( 'Recommended for safety. Backups allow you to restore original images if needed. Requires additional disk space.', 'morden_optimizer' ); ?>
        </p>
        <?php
    }

    public function render_max_dimensions_field() {
        $max_width = $this->config->get( 'max_width', 0 );
        $max_height = $this->config->get( 'max_height', 0 );
        ?>
        <div class="mio-dimensions-group">
            <label for="mio_max_width"><?php esc_html_e( 'Max Width:', 'morden_optimizer' ); ?></label>
            <input type="number" id="mio_max_width" name="mio_settings[max_width]" value="<?php echo esc_attr( $max_width ); ?>" min="0" max="10000" class="small-text" />
            <span class="mio-unit">px</span>

            <label for="mio_max_height"><?php esc_html_e( 'Max Height:', 'morden_optimizer' ); ?></label>
            <input type="number" id="mio_max_height" name="mio_settings[max_height]" value="<?php echo esc_attr( $max_height ); ?>" min="0" max="10000" class="small-text" />
            <span class="mio-unit">px</span>
        </div>
        <p class="description">
            <?php esc_html_e( 'Automatically resize images larger than these dimensions. Set to 0 to disable. Useful for preventing huge uploads.', 'morden_optimizer' ); ?>
        </p>
        <?php
    }

    public function render_server_status_field() {
        $optimizer = new Optimizer();
        $method = $optimizer->get_optimization_method();
        $status_text = '';
        $status_class = '';

        switch ( $method ) {
            case 'imagick':
                $status_text = __( 'Imagick (Recommended)', 'morden_optimizer' );
                $status_class = 'mio-status-imagick';
                break;
            case 'gd':
                $status_text = __( 'GD Library', 'morden_optimizer' );
                $status_class = 'mio-status-gd';
                break;
            case 'api':
                $api_client = APIHandler::get_client();
                if ( $api_client ) {
                    $status_text = sprintf( __( 'API Fallback: %s', 'morden_optimizer' ), $api_client->get_service_name() );
                    $status_class = 'mio-status-api';
                } else {
                    $status_text = __( 'No optimization method available!', 'morden_optimizer' );
                    $status_class = 'mio-status-error';
                }
                break;
        }

        printf(
            '<span class="mio-status-label %s">%s</span>',
            esc_attr( $status_class ),
            esc_html( $status_text )
        );
        echo '<p class="description">' . esc_html__( 'This shows the primary optimization method that will be used. The plugin automatically selects the best available option.', 'morden_optimizer' ) . '</p>';
    }

    /**
     * Sanitizes the settings array before saving to the database.
     *
     * @param array $input The raw input from the settings form.
     * @return array The sanitized array.
     */
    public function sanitize_settings( $input ) {
        $sanitized = [];

        // Auto optimize
        $sanitized['auto_optimize'] = isset( $input['auto_optimize'] ) && '1' === $input['auto_optimize'];

        // Compression level
        if ( isset( $input['compression_level'] ) ) {
            $sanitized['compression_level'] = max( 1, min( 100, absint( $input['compression_level'] ) ) );
        }

        // Optimize thumbnails
        $sanitized['optimize_thumbnails'] = isset( $input['optimize_thumbnails'] ) && '1' === $input['optimize_thumbnails'];

        // API service
        if ( isset( $input['api_service'] ) ) {
            $allowed_services = array_keys( APIHandler::get_available_services() );
            $sanitized['api_service'] = in_array( $input['api_service'], $allowed_services, true ) ? $input['api_service'] : 'resmushit';
        }

        // TinyPNG API key
        if ( isset( $input['tinypng_api_key'] ) ) {
            $sanitized['tinypng_api_key'] = sanitize_text_field( $input['tinypng_api_key'] );
        }

        // Keep original
        $sanitized['keep_original'] = isset( $input['keep_original'] ) && '1' === $input['keep_original'];

        // Max dimensions
        if ( isset( $input['max_width'] ) ) {
            $sanitized['max_width'] = max( 0, min( 10000, absint( $input['max_width'] ) ) );
        }
        if ( isset( $input['max_height'] ) ) {
            $sanitized['max_height'] = max( 0, min( 10000, absint( $input['max_height'] ) ) );
        }

        return $sanitized;
    }

    /**
     * AJAX handler for testing API connection.
     */
    public function ajax_test_api_connection() {
        $data = Security::validate_ajax_request( 'settings', 'manage_options', [
            'service' => 'text',
        ]);

        $result = APIHandler::test_connection( $data['service'] );

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * AJAX handler for resetting settings.
     */
    public function ajax_reset_settings() {
        Security::validate_ajax_request( 'settings', 'manage_options' );

        $result = $this->config->reset_to_defaults();

        if ( $result ) {
            wp_send_json_success( [
                'message' => __( 'Settings have been reset to defaults successfully.', 'morden_optimizer' ),
            ]);
        } else {
            wp_send_json_error( [
                'message' => __( 'Failed to reset settings. Please try again.', 'morden_optimizer' ),
            ]);
        }
    }

    /**
     * Adds contextual help to the settings page.
     */
    public function add_contextual_help() {
        $screen = get_current_screen();

        $screen->add_help_tab([
            'id' => 'mio_overview',
            'title' => __( 'Overview', 'morden_optimizer' ),
            'content' => '<p>' . __( 'Morden Image Optimizer automatically optimizes your images to improve website performance while maintaining visual quality.', 'morden_optimizer' ) . '</p>',
        ]);

        $screen->add_help_tab([
            'id' => 'mio_settings_help',
            'title' => __( 'Settings Guide', 'morden_optimizer' ),
            'content' => '<p>' . __( 'Configure the plugin according to your needs. Most users can use the default settings for optimal results.', 'morden_optimizer' ) . '</p>',
        ]);

        $screen->set_help_sidebar(
            '<p><strong>' . __( 'For more information:', 'morden_optimizer' ) . '</strong></p>' .
            '<p><a href="https://mordenhost.com/morden-image-optimizer/docs" target="_blank">' . __( 'Documentation', 'morden_optimizer' ) . '</a></p>'
        );
    }
}

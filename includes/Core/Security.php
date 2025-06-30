<?php
// File: includes/Core/Security.php

namespace MordenImageOptimizer\Core;

/**
 * Handles security features like nonce verification, capability checks, and input sanitization.
 *
 * This class provides a centralized security layer to protect against
 * common vulnerabilities like CSRF, unauthorized access, and malicious input.
 *
 * @package MordenImageOptimizer\Core
 * @since 1.0.0
 */
class Security {

    /**
     * Single instance of the class.
     *
     * @var Security|null
     */
    private static $instance = null;

    /**
     * Nonce actions used throughout the plugin.
     *
     * @var array
     */
    private $nonce_actions = [
        'settings'        => 'mio_settings_nonce',
        'bulk_optimize'   => 'mio_bulk_optimize_nonce',
        'single_optimize' => 'mio_single_optimize_nonce',
        'restore_image'   => 'mio_restore_image_nonce',
        'test_api'        => 'mio_test_api_nonce',
        'export_settings' => 'mio_export_settings_nonce',
        'import_settings' => 'mio_import_settings_nonce',
    ];

    /**
     * Gets the single instance of the class.
     *
     * @return Security
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
        $this->init_security_hooks();
    }

    /**
     * Initializes security-related hooks.
     */
    private function init_security_hooks() {
        // Add security headers
        add_action( 'admin_init', [ $this, 'add_security_headers' ] );

        // Sanitize file uploads
        add_filter( 'wp_handle_upload_prefilter', [ $this, 'validate_file_upload' ] );

        // Rate limiting for AJAX requests
        add_action( 'wp_ajax_mio_bulk_optimize_batch', [ $this, 'check_rate_limit' ], 1 );
    }

    /**
     * Adds security headers for admin pages.
     */
    public function add_security_headers() {
        if ( ! is_admin() ) {
            return;
        }

        // Prevent clickjacking
        header( 'X-Frame-Options: SAMEORIGIN' );

        // Prevent MIME type sniffing
        header( 'X-Content-Type-Options: nosniff' );

        // XSS protection
        header( 'X-XSS-Protection: 1; mode=block' );
    }

    /**
     * Verifies a nonce for a specific action.
     *
     * @param string $nonce Nonce value to verify.
     * @param string $action Action name.
     * @return bool True if nonce is valid, false otherwise.
     */
    public static function verify_nonce( $nonce, $action ) {
        $nonce_action = self::get_nonce_action( $action );

        if ( ! $nonce_action ) {
            Logger::get_instance()->error( "Unknown nonce action: $action" );
            return false;
        }

        $result = wp_verify_nonce( $nonce, $nonce_action );

        if ( ! $result ) {
            Logger::get_instance()->warning( "Nonce verification failed for action: $action", [
                'nonce' => $nonce,
                'user_id' => get_current_user_id(),
                'ip' => self::get_client_ip(),
            ]);
        }

        return (bool) $result;
    }

    /**
     * Creates a nonce for a specific action.
     *
     * @param string $action Action name.
     * @return string Nonce value.
     */
    public static function create_nonce( $action ) {
        $nonce_action = self::get_nonce_action( $action );

        if ( ! $nonce_action ) {
            Logger::get_instance()->error( "Unknown nonce action: $action" );
            return '';
        }

        return wp_create_nonce( $nonce_action );
    }

    /**
     * Gets the nonce action string for a given action.
     *
     * @param string $action Action name.
     * @return string|false Nonce action string or false if not found.
     */
    private static function get_nonce_action( $action ) {
        $instance = self::get_instance();
        return isset( $instance->nonce_actions[ $action ] ) ? $instance->nonce_actions[ $action ] : false;
    }

    /**
     * Checks if current user has required capability.
     *
     * @param string $capability Required capability.
     * @return bool True if user has capability, false otherwise.
     */
    public static function check_permissions( $capability = 'manage_options' ) {
        $has_permission = current_user_can( $capability );

        if ( ! $has_permission ) {
            Logger::get_instance()->warning( "Permission denied for capability: $capability", [
                'user_id' => get_current_user_id(),
                'ip' => self::get_client_ip(),
            ]);
        }

        return $has_permission;
    }

    /**
     * Sanitizes input based on type.
     *
     * @param mixed  $input Input value to sanitize.
     * @param string $type Type of sanitization to apply.
     * @return mixed Sanitized value.
     */
    public static function sanitize_input( $input, $type = 'text' ) {
        switch ( $type ) {
            case 'email':
                return sanitize_email( $input );

            case 'url':
                return esc_url_raw( $input );

            case 'int':
                return absint( $input );

            case 'float':
                return (float) $input;

            case 'key':
                return sanitize_key( $input );

            case 'slug':
                return sanitize_title( $input );

            case 'textarea':
                return sanitize_textarea_field( $input );

            case 'html':
                return wp_kses_post( $input );

            case 'array':
                return is_array( $input ) ? array_map( 'sanitize_text_field', $input ) : [];

            case 'bool':
                return (bool) $input;

            case 'text':
            default:
                return sanitize_text_field( $input );
        }
    }

    /**
     * Validates file upload for security.
     *
     * @param array $file File upload array.
     * @return array Modified file array.
     */
    public function validate_file_upload( $file ) {
        // Only process image files
        if ( ! isset( $file['type'] ) || strpos( $file['type'], 'image/' ) !== 0 ) {
            return $file;
        }

        // Check file size (max 50MB)
        $max_size = 50 * 1024 * 1024; // 50MB
        if ( isset( $file['size'] ) && $file['size'] > $max_size ) {
            $file['error'] = __( 'File size exceeds maximum allowed size.', 'morden_optimizer' );
            Logger::get_instance()->warning( 'File upload rejected: size too large', [
                'file_size' => $file['size'],
                'max_size' => $max_size,
            ]);
        }

        // Validate file extension
        $allowed_extensions = [ 'jpg', 'jpeg', 'png', 'gif', 'webp' ];
        $file_extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

        if ( ! in_array( $file_extension, $allowed_extensions, true ) ) {
            $file['error'] = __( 'File type not allowed.', 'morden_optimizer' );
            Logger::get_instance()->warning( 'File upload rejected: invalid extension', [
                'extension' => $file_extension,
                'allowed' => $allowed_extensions,
            ]);
        }

        return $file;
    }

    /**
     * Implements rate limiting for AJAX requests.
     */
    public function check_rate_limit() {
        $user_id = get_current_user_id();
        $ip = self::get_client_ip();
        $key = "mio_rate_limit_{$user_id}_{$ip}";

        $requests = get_transient( $key );

        if ( false === $requests ) {
            $requests = 1;
        } else {
            $requests++;
        }

        // Allow maximum 60 requests per minute
        if ( $requests > 60 ) {
            Logger::get_instance()->warning( 'Rate limit exceeded', [
                'user_id' => $user_id,
                'ip' => $ip,
                'requests' => $requests,
            ]);

            wp_send_json_error( [
                'message' => __( 'Too many requests. Please wait before trying again.', 'morden_optimizer' ),
            ], 429 );
        }

        set_transient( $key, $requests, MINUTE_IN_SECONDS );
    }

    /**
     * Gets the client IP address.
     *
     * @return string Client IP address.
     */
    public static function get_client_ip() {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR',               // Standard
        ];

        foreach ( $ip_keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );

                // Handle comma-separated IPs (from proxies)
                if ( strpos( $ip, ',' ) !== false ) {
                    $ip = trim( explode( ',', $ip )[0] );
                }

                // Validate IP
                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Validates and sanitizes AJAX request data.
     *
     * @param string $nonce_action Nonce action to verify.
     * @param string $capability Required capability.
     * @param array  $required_fields Required POST fields.
     * @return array Sanitized POST data or dies with error.
     */
    public static function validate_ajax_request( $nonce_action, $capability = 'upload_files', $required_fields = [] ) {
        // Check nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! self::verify_nonce( $nonce, $nonce_action ) ) {
            wp_send_json_error( [
                'message' => __( 'Security check failed.', 'morden_optimizer' ),
            ], 403 );
        }

        // Check permissions
        if ( ! self::check_permissions( $capability ) ) {
            wp_send_json_error( [
                'message' => __( 'You do not have permission to perform this action.', 'morden_optimizer' ),
            ], 403 );
        }

        // Validate required fields
        $sanitized_data = [];
        foreach ( $required_fields as $field => $type ) {
            if ( ! isset( $_POST[ $field ] ) ) {
                wp_send_json_error( [
                    'message' => sprintf( __( 'Required field missing: %s', 'morden_optimizer' ), $field ),
                ], 400 );
            }

            $sanitized_data[ $field ] = self::sanitize_input( wp_unslash( $_POST[ $field ] ), $type );
        }

        return $sanitized_data;
    }

    /**
     * Logs security events.
     *
     * @param string $event Event type.
     * @param array  $context Event context.
     */
    public static function log_security_event( $event, $context = [] ) {
        $context['event'] = $event;
        $context['user_id'] = get_current_user_id();
        $context['ip'] = self::get_client_ip();
        $context['user_agent'] = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

        Logger::get_instance()->warning( "Security event: $event", $context );
    }
}

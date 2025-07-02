<?php
// File: includes/API/ReSmushItClient.php

namespace MordenImageOptimizer\API;

use MordenImageOptimizer\Core\Logger;

/**
 * reSmush.it API Client for Morden Image Optimizer.
 *
 * Implements the APIClientInterface for reSmush.it service.
 *
 * @package MordenImageOptimizer\API
 * @since 1.0.0
 */
class ReSmushItClient implements APIClientInterface {

    /**
     * The API endpoint URL.
     *
     * @var string
     */
    private const API_ENDPOINT = 'https://api.resmush.it/ws.php';

    /**
     * Logger instance.
     *
     * @var Logger
     */
    private $logger;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->logger = Logger::get_instance();
    }

    /**
     * Optimizes an image using the reSmush.it API.
     *
     * @param string $file_path Absolute path to the local image file.
     * @param array  $options   An associative array of optimization options, expects 'quality' (0-100).
     * @return bool True on successful optimization (file replaced), false on failure.
     */
    public function optimize( $file_path, $options = [] ) {
        if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
            $this->logger->error( 'File not found or not readable', [ 'file_path' => $file_path ] );
            return false;
        }

        $image_url = $this->get_image_url_from_path( $file_path );
        if ( ! $image_url ) {
            $this->logger->error( 'Could not get public URL for file', [ 'file_path' => $file_path ] );
            return false;
        }

        $quality = isset( $options['quality'] ) ? (int) $options['quality'] : 82;

        $args = [
            'method'    => 'POST',
            'timeout'   => 30,
            'headers'   => ['User-Agent' => 'WordPress ' . get_bloginfo('version') . '/Morden Image Optimizer ' . MIO_VERSION . ' - ' . get_bloginfo('url'),
            'Accept'     => 'application/json',
        ],
            'body'      => [
                'img'  => $image_url,
                'qlty' => $quality,
            ],
        ];

        $response = wp_remote_post( self::API_ENDPOINT, $args );

        if ( is_wp_error( $response ) ) {
            $this->logger->error( 'reSmush.it API request failed', [
                'error' => $response->get_error_message(),
                'file' => basename( $file_path ),
            ]);
            return false;
        }

        if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
            $this->logger->error( 'reSmush.it API returned non-200 response', [
                'response_code' => wp_remote_retrieve_response_code( $response ),
                'file' => basename( $file_path ),
            ]);
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! isset( $body['dest'] ) || isset( $body['error'] ) ) {
            $error_msg = isset( $body['error'] ) ? $body['error'] : 'Unknown API error';
            $this->logger->error( 'reSmush.it API error', [
                'error' => $error_msg,
                'file' => basename( $file_path ),
            ]);
            return false;
        }

        // Download the optimized image and replace the original
        $optimized_image_response = wp_remote_get( $body['dest'], [ 'timeout' => 30 ] );

        if ( is_wp_error( $optimized_image_response ) ) {
            $this->logger->error( 'Failed to download optimized image', [
                'error' => $optimized_image_response->get_error_message(),
                'file' => basename( $file_path ),
            ]);
            return false;
        }

        if ( 200 !== wp_remote_retrieve_response_code( $optimized_image_response ) ) {
            $this->logger->error( 'Failed to download optimized image - non-200 response', [
                'response_code' => wp_remote_retrieve_response_code( $optimized_image_response ),
                'file' => basename( $file_path ),
            ]);
            return false;
        }

        $image_data = wp_remote_retrieve_body( $optimized_image_response );

        // Overwrite the original file with the optimized version
        $file_saved = file_put_contents( $file_path, $image_data );

        if ( false === $file_saved ) {
            $this->logger->error( 'Failed to save optimized image', [
                'file' => basename( $file_path ),
            ]);
            return false;
        }

        $this->logger->info( 'Image optimized successfully with reSmush.it', [
            'file' => basename( $file_path ),
            'original_size' => $body['src_size'] ?? 'unknown',
            'optimized_size' => $body['dest_size'] ?? 'unknown',
            'savings_percent' => $body['percent'] ?? 'unknown',
        ]);

        return true;
    }

    /**
     * Gets the name of the API service.
     *
     * @return string The service name.
     */
    public function get_service_name() {
        return 'reSmush.it';
    }

    /**
     * Checks if the API service is configured and ready to use.
     *
     * reSmush.it does not require an API key, so it's always configured.
     *
     * @return bool True.
     */
    public function is_configured() {
        return true;
    }

    /**
     * Converts a local file path to a public URL.
     *
     * @param string $file_path Absolute path to the file.
     * @return string|false The public URL or false on failure.
     */
    private function get_image_url_from_path( $file_path ) {
        $upload_dir = wp_get_upload_dir();

        // Replace the base directory path with the base URL
        $url = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $file_path );

        if ( $url === $file_path ) {
            // If replacement fails, it's not in the uploads dir, can't get URL
            return false;
        }

        // Ensure the URL is accessible
        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return false;
        }

        return $url;
    }
}

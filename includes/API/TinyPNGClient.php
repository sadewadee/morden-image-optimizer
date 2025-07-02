<?php
// File: includes/API/TinyPNGClient.php

namespace MordenImageOptimizer\API;

use MordenImageOptimizer\Core\Config;
use MordenImageOptimizer\Core\Logger;

/**
 * TinyPNG API Client for Morden Image Optimizer.
 *
 * Implements the APIClientInterface for Tinify service.
 *
 * @package MordenImageOptimizer\API
 * @since 1.0.0
 */
class TinyPNGClient implements APIClientInterface {

    /**
     * Logger instance.
     *
     * @var Logger
     */
    private $logger;

    /**
     * Config instance.
     *
     * @var Config
     */
    private $config;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->logger = Logger::get_instance();
        $this->config = Config::get_instance();
    }

    /**
     * Optimizes an image using the TinyPNG API.
     *
     * @param string $file_path Absolute path to the local image file.
     * @param array  $options   Optional. Not used by TinyPNG for quality.
     * @return bool True on successful optimization (file replaced), false on failure.
     */
    public function optimize( $file_path, $options = [] ) {
        if ( ! $this->is_configured() ) {
            $this->logger->error( 'TinyPNG API key not configured' );
            return false;
        }

        if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
            $this->logger->error( 'File not found or not readable', [ 'file_path' => $file_path ] );
            return false;
        }

        try {
            $api_key = $this->config->get( 'tinypng_api_key' );
            \Tinify\setKey( $api_key );

            \Tinify\validate();

            $this->logger->info( 'Optimizing with TinyPNG API', [
                'file' => basename( $file_path ),
            ]);

            $source = \Tinify\fromFile( $file_path );
            $source->toFile( $file_path );

            $compression_count = \Tinify\getCompressionCount();
            if ( ! is_null( $compression_count ) ) {
                update_option( 'mio_tinypng_compression_count', $compression_count );
            }

            return true;

        } catch ( \Tinify\Exception $e ) {
            $this->logger->error( 'TinyPNG API optimization failed', [
                'file' => basename( $file_path ),
                'error' => $e->getMessage(),
                'status' => property_exists($e, 'status') ? $e->status : 'N/A',
            ]);
            return false;
        }
    }

    /**
     * Gets the name of the API service.
     *
     * @return string The service name.
     */
    public function get_service_name() {
        return 'TinyPNG';
    }

    /**
     * Checks if the API service is configured and ready to use.
     *
     * @return bool True if configured, false otherwise.
     */
    public function is_configured() {
        $api_key = $this->config->get( 'tinypng_api_key' );
        return ! empty( $api_key );
    }
}

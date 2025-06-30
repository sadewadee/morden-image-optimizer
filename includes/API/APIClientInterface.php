<?php
// File: includes/API/APIClientInterface.php

namespace MordenImageOptimizer\API;

/**
 * Interface for Morden Image Optimizer API Clients.
 *
 * Defines the contract for all external image optimization API implementations.
 *
 * @package MordenImageOptimizer\API
 * @since 1.0.0
 */
interface APIClientInterface {

    /**
     * Optimizes an image using the external API.
     *
     * @param string $file_path Absolute path to the local image file.
     * @param array  $options   An associative array of optimization options, e.g., 'quality'.
     * @return bool True on successful optimization (file replaced), false on failure.
     */
    public function optimize( $file_path, $options = [] );

    /**
     * Gets the name of the API service.
     *
     * @return string The service name (e.g., 'reSmush.it', 'TinyPNG').
     */
    public function get_service_name();

    /**
     * Checks if the API service is configured and ready to use.
     *
     * @return bool True if configured, false otherwise.
     */
    public function is_configured();
}

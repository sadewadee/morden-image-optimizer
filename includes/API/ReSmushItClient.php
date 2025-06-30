<?php
// File: includes/API/ReSmushItClient.php

namespace MordenImageOptimizer\\API;

/**
 * reSmush.it API Client for Morden Image Optimizer.
 */
class ReSmushItClient implements APIClientInterface {
    private const API_ENDPOINT = 'https://api.resmush.it/ws.php';

    public function optimize( $file_path, $options = [] ) {
        // TODO: Implement reSmush.it optimization
        return false;
    }

    public function get_service_name() {
        return 'reSmush.it';
    }

    public function is_configured() {
        return true; // reSmush.it doesn't require API key
    }
}

<?php
// File: includes/API/TinyPNGClient.php

namespace MordenImageOptimizer\\API;

use MordenImageOptimizer\\Core\\Config;

/**
 * TinyPNG API Client for Morden Image Optimizer.
 */
class TinyPNGClient implements APIClientInterface {
    public function optimize( $file_path, $options = [] ) {
        // TODO: Implement TinyPNG optimization
        return false;
    }

    public function get_service_name() {
        return 'TinyPNG';
    }

    public function is_configured() {
        $config = Config::get_instance();
        return ! empty( $config->get( 'tinypng_api_key' ) );
    }
}

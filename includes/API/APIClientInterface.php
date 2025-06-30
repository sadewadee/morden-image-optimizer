<?php
// File: includes/API/APIClientInterface.php

namespace MordenImageOptimizer\\API;

/**
 * Interface for Morden Image Optimizer API Clients.
 */
interface APIClientInterface {
    public function optimize( $file_path, $options = [] );
    public function get_service_name();
    public function is_configured();
}

<?php
// File: includes/API/APIHandler.php

namespace MordenImageOptimizer\\API;

use MordenImageOptimizer\\Core\\Config;

/**
 * Handles the selection and instantiation of the appropriate API client.
 */
class APIHandler {
    public static function get_client() {
        $config = Config::get_instance();
        $api_service = $config->get( 'api_service', 'resmushit' );

        switch ( $api_service ) {
            case 'tinypng':
                $client = new TinyPNGClient();
                if ( $client->is_configured() ) {
                    return $client;
                }
                break;
            case 'resmushit':
            default:
                return new ReSmushItClient();
        }

        return null;
    }
}

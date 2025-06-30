<?php
// File: includes/API/APIHandler.php

namespace MordenImageOptimizer\API;

use MordenImageOptimizer\Core\Config;
use MordenImageOptimizer\Core\Logger;

/**
 * Handles the selection and instantiation of the appropriate API client.
 *
 * This acts as a factory for API services.
 *
 * @package MordenImageOptimizer\API
 * @since 1.0.0
 */
class APIHandler {

    /**
     * Gets an instance of the selected API client.
     *
     * @return APIClientInterface|null An instance of the selected API client, or null if not found/configured.
     */
    public static function get_client() {
        $config = Config::get_instance();
        $logger = Logger::get_instance();

        $api_service = $config->get( 'api_service', 'resmushit' );

        switch ( $api_service ) {
            case 'tinypng':
                $client = new TinyPNGClient();
                if ( $client->is_configured() ) {
                    $logger->debug( 'Using TinyPNG API client' );
                    return $client;
                }
                $logger->warning( 'TinyPNG selected but not configured, falling back to reSmush.it' );
                // Fall through to default

            case 'resmushit':
            default:
                $logger->debug( 'Using reSmush.it API client' );
                return new ReSmushItClient();
        }
    }

    /**
     * Gets available API services.
     *
     * @return array Array of available API services.
     */
    public static function get_available_services() {
        return [
            'resmushit' => [
                'name' => 'reSmush.it',
                'description' => 'Free API, no key required',
                'requires_key' => false,
            ],
            'tinypng' => [
                'name' => 'TinyPNG',
                'description' => 'Free 500/month, key required',
                'requires_key' => true,
            ],
        ];
    }

    /**
     * Tests API connection.
     *
     * @param string $service API service name.
     * @return array Test result with success status and message.
     */
    public static function test_connection( $service ) {
        $logger = Logger::get_instance();

        switch ( $service ) {
            case 'tinypng':
                $client = new TinyPNGClient();
                break;
            case 'resmushit':
            default:
                $client = new ReSmushItClient();
                break;
        }

        if ( ! $client->is_configured() ) {
            return [
                'success' => false,
                'message' => sprintf( '%s is not properly configured', $client->get_service_name() ),
            ];
        }

        $logger->info( "Testing API connection for {$client->get_service_name()}" );

        return [
            'success' => true,
            'message' => sprintf( '%s is configured and ready', $client->get_service_name() ),
        ];
    }
}

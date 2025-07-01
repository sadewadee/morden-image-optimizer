<?php
// File: includes/Core/Optimizer.php

namespace MordenImageOptimizer\Core;

use MordenImageOptimizer\API\APIHandler;
use MordenImageOptimizer\Admin\BackupManager;
use MordenImageOptimizer\Utils\FileHelper;
use MordenImageOptimizer\Utils\ImageHelper;

/**
 * Handles the core logic of image optimization.
 *
 * This is the heart of the plugin - responsible for detecting the best
 * optimization method and executing the optimization process.
 *
 * @package MordenImageOptimizer\Core
 * @since 1.0.0
 */
class Optimizer {

    /**
     * The determined optimization method.
     *
     * @var string|null
     */
    private $optimization_method;

    /**
     * Configuration instance.
     *
     * @var Config
     */
    private $config;

    /**
     * Logger instance.
     *
     * @var Logger
     */
    private $logger;

    /**
     * Database manager instance.
     *
     * @var DatabaseManager
     */
    private $db_manager;

    /**
     * Supported image formats.
     *
     * @var array
     */
    private $supported_formats = [ 'jpeg', 'jpg', 'png', 'gif', 'webp' ];

    /**
     * Constructor.
     */
    public function __construct() {
        $this->config = Config::get_instance();
        $this->logger = Logger::get_instance();
        $this->db_manager = DatabaseManager::get_instance();
    }

    /**
     * Determines the best available optimization method.
     *
     * Priority: Imagick > GD > API Fallback.
     *
     * @return string The determined method ('imagick', 'gd', 'api').
     */
    public function get_optimization_method() {
        if ( ! empty( $this->optimization_method ) ) {
            return $this->optimization_method;
        }
        if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ) {
            $this->optimization_method = 'imagick';
            $this->logger->info( 'Optimization method determined: Imagick' );
        } elseif ( extension_loaded( 'gd' ) ) {
            $this->optimization_method = 'gd';
            $this->logger->info( 'Optimization method determined: GD Library' );
        } else {
            $this->optimization_method = 'api';
            $this->logger->info( 'Optimization method determined: API Fallback' );
        }

        return $this->optimization_method;
    }

    /**
     * Hooks into the media upload process to optimize images.
     *
     * @param array $metadata An array of attachment metadata.
     * @param int   $attachment_id Current attachment ID.
     * @return array The updated attachment metadata.
     */
    public function optimize_attachment( $metadata, $attachment_id ) {
        $start_time = microtime( true );

        // Only optimize if auto-optimization is enabled
        if ( ! $this->config->is_enabled( 'auto_optimize' ) ) {
            return $metadata;
        }

        // Only optimize images
        if ( ! wp_attachment_is_image( $attachment_id ) ) {
            return $metadata;
        }

        // Check if image is already optimized
        if ( get_post_meta( $attachment_id, '_mio_optimized', true ) ) {
            $this->logger->debug( "Attachment $attachment_id already optimized, skipping" );
            return $metadata;
        }

        $this->logger->info( "Starting optimization for attachment $attachment_id" );

        try {
            $result = $this->process_attachment_optimization( $attachment_id, $metadata );

            $this->logger->performance(
                "Attachment $attachment_id optimization",
                $start_time,
                [ 'result' => $result ? 'success' : 'failed' ]
            );

        } catch ( \Exception $e ) {
            $this->logger->error( "Exception during attachment optimization", [
                'attachment_id' => $attachment_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $metadata;
    }

    /**
     * Processes the optimization for a single attachment.
     *
     * @param int   $attachment_id Attachment ID.
     * @param array $metadata Attachment metadata.
     * @return bool True on success, false on failure.
     */
    private function process_attachment_optimization( $attachment_id, $metadata ) {
        $file_path = get_attached_file( $attachment_id );

        if ( ! $file_path || ! file_exists( $file_path ) ) {
            $this->logger->error( "File not found for attachment $attachment_id", [ 'file_path' => $file_path ] );
            return false;
        }

        // Check if optimization is needed
        if ( ! ImageHelper::is_optimization_needed( $file_path ) ) {
            $this->logger->info( "Optimization not needed for attachment $attachment_id (file too small)" );
            return false;
        }

        // Create backup if enabled
        if ( $this->config->is_enabled( 'keep_original' ) ) {
            if ( ! BackupManager::create_backup( $attachment_id, $file_path ) ) {
                $this->logger->warning( "Failed to create backup for attachment $attachment_id" );
            }
        }

        $total_savings = 0;
        $optimization_method = $this->get_optimization_method();

        // Optimize main image
        $main_result = $this->optimize_single_file( $file_path, $optimization_method );
        if ( $main_result['success'] ) {
            $total_savings += $main_result['savings'];
        }

        // Optimize thumbnails if enabled
        if ( $this->config->is_enabled( 'optimize_thumbnails' ) && isset( $metadata['sizes'] ) ) {
            $thumbnail_savings = $this->optimize_thumbnails( $file_path, $metadata['sizes'], $optimization_method );
            $total_savings += $thumbnail_savings;
        }

        // Update attachment metadata
        $this->update_attachment_metadata( $attachment_id, $optimization_method, $main_result, $total_savings );

        // Log optimization result
        $this->db_manager->log_optimization(
            $attachment_id,
            $main_result['success'] ? 'success' : 'failed',
            $optimization_method,
            $main_result['original_size'],
            $main_result['optimized_size'],
            $main_result['error'] ?? ''
        );

        return $main_result['success'];
    }

    /**
     * Optimizes a single file.
     *
     * @param string $file_path Path to the file.
     * @param string $method Optimization method to use.
     * @return array Optimization result.
     */
    private function optimize_single_file( $file_path, $method ) {
        $original_size = filesize( $file_path );

        $result = [
            'success' => false,
            'original_size' => $original_size,
            'optimized_size' => $original_size,
            'savings' => 0,
            'error' => '',
        ];

        $this->logger->debug( "Optimizing file: $file_path using method: $method" );

        try {
            switch ( $method ) {
                case 'imagick':
                    $success = $this->optimize_with_imagick( $file_path );
                    break;
                case 'gd':
                    $success = $this->optimize_with_gd( $file_path );
                    break;
                case 'api':
                    $success = $this->optimize_with_api( $file_path );
                    break;
                default:
                    $success = false;
                    $result['error'] = "Unknown optimization method: $method";
            }

            if ( $success ) {
                clearstatcache( true, $file_path );
                $optimized_size = filesize( $file_path );
                $savings = max( 0, $original_size - $optimized_size );

                $result['success'] = true;
                $result['optimized_size'] = $optimized_size;
                $result['savings'] = $savings;

                $this->logger->info( "File optimized successfully", [
                    'file' => basename( $file_path ),
                    'original_size' => FileHelper::format_file_size( $original_size ),
                    'optimized_size' => FileHelper::format_file_size( $optimized_size ),
                    'savings' => FileHelper::format_file_size( $savings ),
                    'percentage' => ImageHelper::calculate_savings_percentage( $original_size, $optimized_size ),
                ]);
            } else {
                $result['error'] = "Optimization failed with method: $method";
                $this->logger->warning( "File optimization failed", [
                    'file' => basename( $file_path ),
                    'method' => $method,
                ]);
            }

        } catch ( \Exception $e ) {
            $result['error'] = $e->getMessage();
            $this->logger->error( "Exception during file optimization", [
                'file' => basename( $file_path ),
                'method' => $method,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Optimizes thumbnails for an attachment.
     *
     * @param string $main_file_path Path to the main file.
     * @param array  $sizes Thumbnail sizes array.
     * @param string $method Optimization method.
     * @return int Total savings from thumbnail optimization.
     */
    private function optimize_thumbnails( $main_file_path, $sizes, $method ) {
        $total_savings = 0;
        $base_dir = dirname( $main_file_path );
        $excluded_sizes = $this->config->get( 'exclude_sizes', [] );

        foreach ( $sizes as $size_name => $size_data ) {
            // Skip excluded sizes
            if ( in_array( $size_name, $excluded_sizes, true ) ) {
                continue;
            }

            if ( empty( $size_data['file'] ) ) {
                continue;
            }

            $thumbnail_path = trailingslashit( $base_dir ) . $size_data['file'];

            if ( ! file_exists( $thumbnail_path ) ) {
                continue;
            }

            $result = $this->optimize_single_file( $thumbnail_path, $method );
            $total_savings += $result['savings'];
        }

        return $total_savings;
    }

    /**
     * Updates attachment metadata after optimization.
     *
     * @param int    $attachment_id Attachment ID.
     * @param string $method Optimization method used.
     * @param array  $result Optimization result.
     * @param int    $total_savings Total savings including thumbnails.
     */
    private function update_attachment_metadata( $attachment_id, $method, $result, $total_savings ) {
        update_post_meta( $attachment_id, '_mio_optimized', true );
        update_post_meta( $attachment_id, '_mio_optimization_method', $method );
        update_post_meta( $attachment_id, '_mio_original_size', $result['original_size'] );
        update_post_meta( $attachment_id, '_mio_optimized_size', $result['optimized_size'] );
        update_post_meta( $attachment_id, '_mio_savings', $total_savings );
        update_post_meta( $attachment_id, '_mio_optimization_date', current_time( 'mysql' ) );

        if ( ! $result['success'] && ! empty( $result['error'] ) ) {
            update_post_meta( $attachment_id, '_mio_optimization_error', $result['error'] );
        }
    }

    /**
     * Optimizes an image using the Imagick library.
     *
     * @param string $file_path Absolute path to the image file.
     * @return bool True on success, false on failure.
     */
private function optimize_with_imagick( $file_path ) {
    if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick' ) ) {
        return false;
    }

    $file_size = filesize( $file_path );
    if ( $file_size > 5242880 ) { // 5MB limit
        $this->logger->warning( "File too large for Imagick processing", [
            'file_size' => FileHelper::format_file_size( $file_size ),
            'limit' => '5MB'
        ]);
        return false;
    }

    try {
        putenv( 'MAGICK_THREAD_LIMIT=1' );
        putenv( 'MAGICK_MEMORY_LIMIT=64MB' );
        putenv( 'MAGICK_MAP_LIMIT=128MB' );
        putenv( 'MAGICK_DISK_LIMIT=256MB' );
        putenv( 'MAGICK_TIME_LIMIT=30' );

        $imagick = new \Imagick( realpath( $file_path ) );

        // Set Imagick resource limits (GitHub issue #1306 solution)
        $imagick->setResourceLimit( \Imagick::RESOURCETYPE_MEMORY, 64 * 1024 * 1024 ); // 64MB
        $imagick->setResourceLimit( \Imagick::RESOURCETYPE_MAP, 128 * 1024 * 1024 ); // 128MB
        $imagick->setResourceLimit( \Imagick::RESOURCETYPE_DISK, 256 * 1024 * 1024 ); // 256MB
        $imagick->setResourceLimit( \Imagick::RESOURCETYPE_AREA, 25 * 1024 * 1024 ); // 25MP
        $imagick->setResourceLimit( \Imagick::RESOURCETYPE_FILE, 768 ); // Max 768 files

        $info = @getimagesize( $file_path );
        if ( $info && $info['mime'] === 'image/jpeg' ) {
            $max_dimension = max( $info[0], $info[1] );
            if ( $max_dimension > 2000 ) {
                $imagick->setOption( 'jpeg:size', '2000x2000' );
            }
        }

        $format = strtolower( $imagick->getImageFormat() );

        if ( $format === 'gif' ) {
            $imagick->destroy();
            $this->logger->info( "Skipping GIF optimization due to memory concerns" );
            return false;
        }

        if ( ! in_array( $format, $this->supported_formats, true ) ) {
            $imagick->destroy();
            return false;
        }

        $imagick->stripImage();

        if ( $imagick->getNumberImages() > 1 && $format !== 'gif' ) {
            $imagick = $imagick->coalesceImages();
        }

        switch ( $format ) {
            case 'jpeg':
            case 'jpg':
                $quality = $this->config->get_quality_for_format( 'jpeg' );
                $imagick->setImageCompression( \Imagick::COMPRESSION_JPEG );
                $imagick->setImageCompressionQuality( $quality );
                $imagick->setInterlaceScheme( \Imagick::INTERLACE_PLANE );

                if ( $file_size > 1048576 ) {
                    $imagick->setInterlaceScheme( \Imagick::INTERLACE_PLANE );
                }
                break;

            case 'png':
                $imagick->setImageFormat( 'PNG' );
                $imagick->setOption( 'png:compression-level', 9 );
                $imagick->setOption( 'png:compression-filter', 5 );

                if ( $imagick->getImageType() === \Imagick::IMGTYPE_PALETTE ) {
                    $imagick->quantizeImage( 256, \Imagick::COLORSPACE_RGB, 0, false, false );
                }
                break;

            case 'webp':
                $quality = $this->config->get_quality_for_format( 'webp' );
                $imagick->setImageFormat( 'WEBP' );
                $imagick->setImageCompressionQuality( $quality );
                break;
        }

        $this->apply_resize_if_needed( $imagick );

        $result = $imagick->writeImage( $file_path );

        $imagick->destroy();

        if ( function_exists( 'gc_collect_cycles' ) ) {
            gc_collect_cycles();
        }

        return $result;

    } catch ( \Exception $e ) {
        $this->logger->error( "Imagick optimization error", [
            'file' => basename( $file_path ),
            'error' => $e->getMessage(),
            'file_size' => FileHelper::format_file_size( $file_size ),
        ]);

        if ( isset( $imagick ) && $imagick instanceof \Imagick ) {
            $imagick->destroy();
        }

        return false;
    }
}


    /**
     * Optimizes an image using the GD library.
     *
     * @param string $file_path Absolute path to the image file.
     * @return bool True on success, false on failure.
     */
    private function optimize_with_gd( $file_path ) {
        if ( ! extension_loaded( 'gd' ) ) {
            return false;
        }

        $info = @getimagesize( $file_path );
        if ( false === $info ) {
            return false;
        }

        $mime = $info['mime'];
        $resource = null;

        try {
            switch ( $mime ) {
                case 'image/jpeg':
                    $resource = @imagecreatefromjpeg( $file_path );
                    break;
                case 'image/png':
                    $resource = @imagecreatefrompng( $file_path );
                    if ( $resource ) {
                        imagealphablending( $resource, false );
                        imagesavealpha( $resource, true );
                    }
                    break;
                case 'image/gif':
                    $resource = @imagecreatefromgif( $file_path );
                    break;
                case 'image/webp':
                    if ( function_exists( 'imagecreatefromwebp' ) ) {
                        $resource = @imagecreatefromwebp( $file_path );
                    }
                    break;
                default:
                    return false;
            }

            if ( ! $resource ) {
                return false;
            }

            // Apply resize if needed
            $resource = $this->apply_gd_resize_if_needed( $resource, $info[0], $info[1] );

            $result = false;
            switch ( $mime ) {
                case 'image/jpeg':
                    $quality = $this->config->get_quality_for_format( 'jpeg' );
                    $result = imagejpeg( $resource, $file_path, $quality );
                    break;
                case 'image/png':
                    $quality = $this->config->get_quality_for_format( 'png' );
                    $png_quality = (int) round( ( 100 - $quality ) / 10 );
                    $result = imagepng( $resource, $file_path, $png_quality );
                    break;
                case 'image/gif':
                    $result = imagegif( $resource, $file_path );
                    break;
                case 'image/webp':
                    if ( function_exists( 'imagewebp' ) ) {
                        $quality = $this->config->get_quality_for_format( 'webp' );
                        $result = imagewebp( $resource, $file_path, $quality );
                    }
                    break;
            }

            imagedestroy( $resource );
            return $result;

        } catch ( \Exception $e ) {
            if ( $resource ) {
                imagedestroy( $resource );
            }
            $this->logger->error( "GD optimization error", [
                'file' => basename( $file_path ),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Optimizes an image using external API.
     *
     * @param string $file_path Absolute path to the image file.
     * @return bool True on success, false on failure.
     */
    private function optimize_with_api( $file_path ) {
        $api_client = APIHandler::get_client();

        if ( ! $api_client ) {
            $this->logger->error( "No API client available for optimization" );
            return false;
        }

        $quality = $this->config->get( 'compression_level', 82 );
        return $api_client->optimize( $file_path, [ 'quality' => $quality ] );
    }

    /**
     * Applies resize if maximum dimensions are configured (Imagick).
     *
     * @param \Imagick $imagick Imagick instance.
     */
    private function apply_resize_if_needed( $imagick ) {
        $max_width = $this->config->get( 'max_width', 0 );
        $max_height = $this->config->get( 'max_height', 0 );

        if ( $max_width <= 0 && $max_height <= 0 ) {
            return;
        }

        $current_width = $imagick->getImageWidth();
        $current_height = $imagick->getImageHeight();

        // Calculate memory requirement for resize operation
        $memory_required = ( $current_width * $current_height * 4 ) / 1024 / 1024; // MB

        if ( $memory_required > 32 ) { // Skip resize if requires >32MB
            $this->logger->warning( "Skipping resize due to memory requirements", [
                'memory_required' => round( $memory_required, 2 ) . 'MB',
                'dimensions' => "{$current_width}x{$current_height}"
            ]);
            return;
        }

        if ( ( $max_width > 0 && $current_width > $max_width ) ||
            ( $max_height > 0 && $current_height > $max_height ) ) {

            $imagick->thumbnailImage( $max_width ?: 0, $max_height ?: 0, true, true );

            $this->logger->info( "Image resized with Imagick", [
                'original' => "{$current_width}x{$current_height}",
                'new' => $imagick->getImageWidth() . 'x' . $imagick->getImageHeight(),
            ]);
        }
    }


    /**
     * Applies resize if maximum dimensions are configured (GD).
     *
     * @param resource $resource GD resource.
     * @param int      $width Current width.
     * @param int      $height Current height.
     * @return resource Resized resource or original if no resize needed.
     */
    private function apply_gd_resize_if_needed( $resource, $width, $height ) {
        $max_width = $this->config->get( 'max_width', 0 );
        $max_height = $this->config->get( 'max_height', 0 );

        if ( $max_width <= 0 && $max_height <= 0 ) {
            return $resource;
        }

        if ( ( $max_width > 0 && $width > $max_width ) ||
             ( $max_height > 0 && $height > $max_height ) ) {

            // Calculate new dimensions maintaining aspect ratio
            $ratio = min(
                $max_width > 0 ? $max_width / $width : PHP_INT_MAX,
                $max_height > 0 ? $max_height / $height : PHP_INT_MAX
            );

            $new_width = (int) round( $width * $ratio );
            $new_height = (int) round( $height * $ratio );

            $new_resource = imagecreatetruecolor( $new_width, $new_height );

            // Preserve transparency for PNG
            imagealphablending( $new_resource, false );
            imagesavealpha( $new_resource, true );

            imagecopyresampled( $new_resource, $resource, 0, 0, 0, 0, $new_width, $new_height, $width, $height );
            imagedestroy( $resource );

            $this->logger->info( "Image resized with GD", [
                'original' => "{$width}x{$height}",
                'new' => "{$new_width}x{$new_height}",
            ]);

            return $new_resource;
        }

        return $resource;
    }

    /**
     * AJAX handler for single image optimization.
     */
    public function ajax_optimize_single() {
        $data = Security::validate_ajax_request( 'single_optimize', 'upload_files', [
            'attachment_id' => 'int',
        ]);

        $attachment_id = $data['attachment_id'];

        if ( ! wp_attachment_is_image( $attachment_id ) ) {
            wp_send_json_error( [
                'message' => __( 'Invalid attachment or not an image.', 'morden_optimizer' ),
            ]);
        }

        $metadata = wp_get_attachment_metadata( $attachment_id );
        $result = $this->process_attachment_optimization( $attachment_id, $metadata );

        if ( $result ) {
            $savings = get_post_meta( $attachment_id, '_mio_savings', true );
            wp_send_json_success( [
                'message' => __( 'Image optimized successfully.', 'morden_optimizer' ),
                'savings' => FileHelper::format_file_size( $savings ),
            ]);
        } else {
            wp_send_json_error( [
                'message' => __( 'Failed to optimize image.', 'morden_optimizer' ),
            ]);
        }
    }
}
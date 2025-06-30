<?php
// File: includes/Utils/ImageHelper.php

namespace MordenImageOptimizer\Utils;

/**
 * Image utility functions.
 *
 * Provides image-specific operations and calculations.
 *
 * @package MordenImageOptimizer\Utils
 * @since 1.0.0
 */
class ImageHelper {

    /**
     * Gets image dimensions.
     *
     * @param string $file_path Path to the image file.
     * @return array|false Array with width and height, or false on failure.
     */
    public static function get_image_dimensions( $file_path ) {
        if ( ! file_exists( $file_path ) ) {
            return false;
        }

        $image_info = @getimagesize( $file_path );
        return $image_info ? [ 'width' => $image_info[0], 'height' => $image_info[1] ] : false;
    }

    /**
     * Calculates savings percentage.
     *
     * @param int $original_size Original file size.
     * @param int $optimized_size Optimized file size.
     * @return float Savings percentage.
     */
    public static function calculate_savings_percentage( $original_size, $optimized_size ) {
        if ( $original_size <= 0 ) {
            return 0;
        }

        return round( ( ( $original_size - $optimized_size ) / $original_size ) * 100, 2 );
    }

    /**
     * Checks if optimization is needed for an image.
     *
     * @param string $file_path Path to the image file.
     * @return bool True if optimization is needed, false otherwise.
     */
    public static function is_optimization_needed( $file_path ) {
        // Check if file exists and is readable
        if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
            return false;
        }

        // Check if it's actually an image
        if ( ! FileHelper::is_image( $file_path ) ) {
            return false;
        }

        // Only optimize files larger than 10KB
        $file_size = FileHelper::get_file_size( $file_path );
        return $file_size > 10240; // 10KB
    }

    /**
     * Gets supported image formats.
     *
     * @return array Array of supported MIME types.
     */
    public static function get_supported_formats() {
        return [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ];
    }

    /**
     * Checks if image format is supported.
     *
     * @param string $file_path Path to the image file.
     * @return bool True if supported, false otherwise.
     */
    public static function is_supported_format( $file_path ) {
        $mime_type = FileHelper::get_mime_type( $file_path );
        return in_array( $mime_type, self::get_supported_formats(), true );
    }

    /**
     * Calculates optimal dimensions for resize.
     *
     * @param int $current_width Current image width.
     * @param int $current_height Current image height.
     * @param int $max_width Maximum allowed width (0 for no limit).
     * @param int $max_height Maximum allowed height (0 for no limit).
     * @return array Array with new width and height.
     */
    public static function calculate_resize_dimensions( $current_width, $current_height, $max_width = 0, $max_height = 0 ) {
        if ( $max_width <= 0 && $max_height <= 0 ) {
            return [ 'width' => $current_width, 'height' => $current_height ];
        }

        $ratio = min(
            $max_width > 0 ? $max_width / $current_width : PHP_INT_MAX,
            $max_height > 0 ? $max_height / $current_height : PHP_INT_MAX
        );

        if ( $ratio >= 1 ) {
            return [ 'width' => $current_width, 'height' => $current_height ];
        }

        return [
            'width' => (int) round( $current_width * $ratio ),
            'height' => (int) round( $current_height * $ratio ),
        ];
    }

    /**
     * Gets image quality recommendation based on file size.
     *
     * @param int $file_size File size in bytes.
     * @return int Recommended quality (1-100).
     */
    public static function get_recommended_quality( $file_size ) {
        if ( $file_size > 2097152 ) { // > 2MB
            return 75;
        } elseif ( $file_size > 1048576 ) { // > 1MB
            return 80;
        } elseif ( $file_size > 524288 ) { // > 512KB
            return 85;
        } else {
            return 90;
        }
    }
}

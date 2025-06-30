<?php
// File: includes/Utils/ImageHelper.php

namespace MordenImageOptimizer\\Utils;

/**
 * Image utility functions.
 */
class ImageHelper {
    public static function get_image_dimensions( $file_path ) {
        if ( ! file_exists( $file_path ) ) {
            return false;
        }

        $image_info = getimagesize( $file_path );
        return $image_info ? [ 'width' => $image_info[0], 'height' => $image_info[1] ] : false;
    }

    public static function calculate_savings_percentage( $original_size, $optimized_size ) {
        if ( $original_size <= 0 ) {
            return 0;
        }

        return round( ( ( $original_size - $optimized_size ) / $original_size ) * 100, 2 );
    }

    public static function is_optimization_needed( $file_path ) {
        // Check if file is already optimized or if it's too small to optimize
        $file_size = FileHelper::get_file_size( $file_path );
        return $file_size > 10240; // Only optimize files larger than 10KB
    }
}

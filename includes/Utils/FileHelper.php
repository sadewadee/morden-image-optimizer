<?php
// File: includes/Utils/FileHelper.php

namespace MordenImageOptimizer\\Utils;

/**
 * File utility functions.
 */
class FileHelper {
    public static function get_file_size( $file_path ) {
        return file_exists( $file_path ) ? filesize( $file_path ) : 0;
    }

    public static function format_file_size( $bytes ) {
        $units = [ 'B', 'KB', 'MB', 'GB' ];
        $bytes = max( $bytes, 0 );
        $pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
        $pow = min( $pow, count( $units ) - 1 );

        $bytes /= pow( 1024, $pow );

        return round( $bytes, 2 ) . ' ' . $units[ $pow ];
    }

    public static function is_image( $file_path ) {
        $image_types = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ];
        $file_type = wp_check_filetype( $file_path );
        return in_array( $file_type['type'], $image_types, true );
    }
}

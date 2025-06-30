<?php
// File: includes/Utils/FileHelper.php

namespace MordenImageOptimizer\Utils;

/**
 * File utility functions.
 *
 * Provides common file operations and utilities used throughout the plugin.
 *
 * @package MordenImageOptimizer\Utils
 * @since 1.0.0
 */
class FileHelper {

    /**
     * Gets file size safely.
     *
     * @param string $file_path Path to the file.
     * @return int File size in bytes, 0 if file doesn't exist.
     */
    public static function get_file_size( $file_path ) {
        return file_exists( $file_path ) ? filesize( $file_path ) : 0;
    }

    /**
     * Formats file size into human readable format.
     *
     * @param int $bytes File size in bytes.
     * @return string Formatted file size.
     */
    public static function format_file_size( $bytes ) {
        $units = [ 'B', 'KB', 'MB', 'GB', 'TB' ];
        $bytes = max( $bytes, 0 );
        $pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
        $pow = min( $pow, count( $units ) - 1 );

        $bytes /= pow( 1024, $pow );

        return round( $bytes, 2 ) . ' ' . $units[ $pow ];
    }

    /**
     * Checks if file is an image.
     *
     * @param string $file_path Path to the file.
     * @return bool True if file is an image, false otherwise.
     */
    public static function is_image( $file_path ) {
        $image_types = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ];
        $file_type = wp_check_filetype( $file_path );
        return in_array( $file_type['type'], $image_types, true );
    }

    /**
     * Gets file extension safely.
     *
     * @param string $file_path Path to the file.
     * @return string File extension in lowercase.
     */
    public static function get_file_extension( $file_path ) {
        return strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
    }

    /**
     * Checks if file is writable.
     *
     * @param string $file_path Path to the file.
     * @return bool True if writable, false otherwise.
     */
    public static function is_writable( $file_path ) {
        return file_exists( $file_path ) && is_writable( $file_path );
    }

    /**
     * Creates a backup of a file.
     *
     * @param string $source_path Source file path.
     * @param string $backup_path Backup file path.
     * @return bool True on success, false on failure.
     */
    public static function create_backup( $source_path, $backup_path ) {
        if ( ! file_exists( $source_path ) ) {
            return false;
        }

        $backup_dir = dirname( $backup_path );
        if ( ! is_dir( $backup_dir ) ) {
            wp_mkdir_p( $backup_dir );
        }

        return copy( $source_path, $backup_path );
    }

    /**
     * Gets MIME type of a file.
     *
     * @param string $file_path Path to the file.
     * @return string|false MIME type or false on failure.
     */
    public static function get_mime_type( $file_path ) {
        if ( ! file_exists( $file_path ) ) {
            return false;
        }

        $file_info = wp_check_filetype( $file_path );
        return $file_info['type'] ?: false;
    }
}

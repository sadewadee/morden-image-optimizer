<?php
// File: includes/Core/Logger.php

namespace MordenImageOptimizer\Core;

/**
 * Handles logging for debugging and monitoring.
 *
 * This class provides comprehensive logging capabilities with multiple
 * log levels, file rotation, and performance monitoring.
 *
 * @package MordenImageOptimizer\Core
 * @since 1.0.0
 */
class Logger {

    /**
     * Single instance of the class.
     *
     * @var Logger|null
     */
    private static $instance = null;

    /**
     * Path to the log file.
     *
     * @var string
     */
    private $log_file;

    /**
     * Log directory path.
     *
     * @var string
     */
    private $log_dir;

    /**
     * Maximum log file size in bytes (5MB).
     *
     * @var int
     */
    private $max_file_size = 5242880;

    /**
     * Log levels and their numeric values.
     *
     * @var array
     */
    private $log_levels = [
        'emergency' => 0,
        'alert'     => 1,
        'critical'  => 2,
        'error'     => 3,
        'warning'   => 4,
        'notice'    => 5,
        'info'      => 6,
        'debug'     => 7,
    ];

    /**
     * Gets the single instance of the class.
     *
     * @return Logger
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor.
     */
    private function __construct() {
        $upload_dir = wp_get_upload_dir();
        $this->log_dir = trailingslashit( $upload_dir['basedir'] ) . '.mio-logs';
        $this->log_file = trailingslashit( $this->log_dir ) . 'mio.log';
        $this->ensure_log_directory();
    }

    /**
     * Ensures the log directory exists and is protected.
     */
    private function ensure_log_directory() {
        if ( ! is_dir( $this->log_dir ) ) {
            wp_mkdir_p( $this->log_dir );

            // Create .htaccess to protect logs
            $htaccess_content = "Order deny,allow\nDeny from all\n<Files ~ \"^.*\\.([Ll][Oo][Gg])\">\nOrder allow,deny\nDeny from all\n</Files>";
            file_put_contents( trailingslashit( $this->log_dir ) . '.htaccess', $htaccess_content );

            // Create index.php to prevent directory listing
            file_put_contents( trailingslashit( $this->log_dir ) . 'index.php', '<?php // Silence is golden.' );
        }
    }

    /**
     * Main logging method.
     *
     * @param string $level Log level.
     * @param string $message Log message.
     * @param array  $context Additional context data.
     */
    public function log( $level, $message, $context = [] ) {
        // Only log if debugging is enabled
        if ( ! WP_DEBUG || ! WP_DEBUG_LOG ) {
            return;
        }

        // Check if log level is valid
        if ( ! isset( $this->log_levels[ $level ] ) ) {
            $level = 'info';
        }

        // Check file size and rotate if necessary
        $this->rotate_log_if_needed();

        // Format the log entry
        $formatted_message = $this->format_log_entry( $level, $message, $context );

        // Write to log file
        $this->write_to_file( $formatted_message );

        // Also log critical errors to WordPress error log
        if ( in_array( $level, [ 'emergency', 'alert', 'critical', 'error' ], true ) ) {
            error_log( "MIO Plugin - $level: $message" );
        }
    }

    /**
     * Formats a log entry.
     *
     * @param string $level Log level.
     * @param string $message Log message.
     * @param array  $context Additional context data.
     * @return string Formatted log entry.
     */
    private function format_log_entry( $level, $message, $context ) {
        $timestamp = current_time( 'Y-m-d H:i:s' );
        $memory_usage = $this->format_bytes( memory_get_usage() );
        $peak_memory = $this->format_bytes( memory_get_peak_usage() );

        $entry = sprintf(
            "[%s] [%s] [MEM: %s/%s] %s",
            $timestamp,
            strtoupper( $level ),
            $memory_usage,
            $peak_memory,
            $message
        );

        // Add context if provided
        if ( ! empty( $context ) ) {
            $entry .= ' | Context: ' . wp_json_encode( $context );
        }

        // Add user and IP information for security-related logs
        if ( in_array( $level, [ 'emergency', 'alert', 'critical', 'error', 'warning' ], true ) ) {
            $user_id = get_current_user_id();
            $ip = Security::get_client_ip();
            $entry .= sprintf( ' | User: %d | IP: %s', $user_id, $ip );
        }

        return $entry . "\n";
    }

    /**
     * Writes log entry to file.
     *
     * @param string $message Formatted log message.
     */
    private function write_to_file( $message ) {
        // Use file locking to prevent corruption in concurrent writes
        $handle = fopen( $this->log_file, 'a' );
        if ( $handle ) {
            if ( flock( $handle, LOCK_EX ) ) {
                fwrite( $handle, $message );
                flock( $handle, LOCK_UN );
            }
            fclose( $handle );
        }
    }

    /**
     * Rotates log file if it exceeds maximum size.
     */
    private function rotate_log_if_needed() {
        if ( ! file_exists( $this->log_file ) ) {
            return;
        }

        if ( filesize( $this->log_file ) > $this->max_file_size ) {
            $backup_file = $this->log_file . '.' . date( 'Y-m-d-H-i-s' );
            rename( $this->log_file, $backup_file );

            // Keep only the last 5 backup files
            $this->cleanup_old_logs();
        }
    }

    /**
     * Cleans up old log files.
     */
    private function cleanup_old_logs() {
        $log_files = glob( $this->log_file . '.*' );

        if ( count( $log_files ) > 5 ) {
            // Sort by modification time (oldest first)
            usort( $log_files, function( $a, $b ) {
                return filemtime( $a ) - filemtime( $b );
            });

            // Remove oldest files, keep only 5
            $files_to_remove = array_slice( $log_files, 0, count( $log_files ) - 5 );
            foreach ( $files_to_remove as $file ) {
                unlink( $file );
            }
        }
    }

    /**
     * Formats bytes into human readable format.
     *
     * @param int $bytes Number of bytes.
     * @return string Formatted string.
     */
    private function format_bytes( $bytes ) {
        $units = [ 'B', 'KB', 'MB', 'GB' ];
        $bytes = max( $bytes, 0 );
        $pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
        $pow = min( $pow, count( $units ) - 1 );

        $bytes /= pow( 1024, $pow );

        return round( $bytes, 2 ) . ' ' . $units[ $pow ];
    }

    // Convenience methods for different log levels
    public function emergency( $message, $context = [] ) {
        $this->log( 'emergency', $message, $context );
    }

    public function alert( $message, $context = [] ) {
        $this->log( 'alert', $message, $context );
    }

    public function critical( $message, $context = [] ) {
        $this->log( 'critical', $message, $context );
    }

    public function error( $message, $context = [] ) {
        $this->log( 'error', $message, $context );
    }

    public function warning( $message, $context = [] ) {
        $this->log( 'warning', $message, $context );
    }

    public function notice( $message, $context = [] ) {
        $this->log( 'notice', $message, $context );
    }

    public function info( $message, $context = [] ) {
        $this->log( 'info', $message, $context );
    }

    public function debug( $message, $context = [] ) {
        $this->log( 'debug', $message, $context );
    }

    /**
     * Logs performance metrics.
     *
     * @param string $operation Operation name.
     * @param float  $start_time Start time (from microtime(true)).
     * @param array  $context Additional context.
     */
    public function performance( $operation, $start_time, $context = [] ) {
        $execution_time = microtime( true ) - $start_time;
        $context['execution_time'] = round( $execution_time * 1000, 2 ) . 'ms';

        $this->info( "Performance: $operation completed", $context );
    }

    /**
     * Gets recent log entries.
     *
     * @param int $lines Number of lines to retrieve.
     * @return array Array of log entries.
     */
    public function get_recent_logs( $lines = 100 ) {
        if ( ! file_exists( $this->log_file ) ) {
            return [];
        }

        $handle = fopen( $this->log_file, 'r' );
        if ( ! $handle ) {
            return [];
        }

        $log_lines = [];
        $line_count = 0;

        // Read file backwards to get recent entries
        fseek( $handle, -1, SEEK_END );
        $pos = ftell( $handle );
        $line = '';

        while ( $pos >= 0 && $line_count < $lines ) {
            $char = fgetc( $handle );
            if ( $char === "\n" || $pos === 0 ) {
                if ( ! empty( trim( $line ) ) ) {
                    $log_lines[] = strrev( $line );
                    $line_count++;
                }
                $line = '';
            } else {
                $line .= $char;
            }
            fseek( $handle, --$pos );
        }

        fclose( $handle );

        return array_reverse( $log_lines );
    }

    /**
     * Clears all log files.
     *
     * @return bool True on success, false on failure.
     */
    public function clear_logs() {
        $success = true;

        // Remove main log file
        if ( file_exists( $this->log_file ) ) {
            $success = unlink( $this->log_file ) && $success;
        }

        // Remove backup log files
        $backup_files = glob( $this->log_file . '.*' );
        foreach ( $backup_files as $file ) {
            $success = unlink( $file ) && $success;
        }

        if ( $success ) {
            $this->info( 'Log files cleared successfully' );
        } else {
            $this->error( 'Failed to clear some log files' );
        }

        return $success;
    }

    /**
     * Gets log file statistics.
     *
     * @return array Log statistics.
     */
    public function get_log_stats() {
        $stats = [
            'file_exists' => file_exists( $this->log_file ),
            'file_size' => 0,
            'file_size_formatted' => '0 B',
            'last_modified' => null,
            'backup_files' => 0,
        ];

        if ( $stats['file_exists'] ) {
            $stats['file_size'] = filesize( $this->log_file );
            $stats['file_size_formatted'] = $this->format_bytes( $stats['file_size'] );
            $stats['last_modified'] = date( 'Y-m-d H:i:s', filemtime( $this->log_file ) );
        }

        $backup_files = glob( $this->log_file . '.*' );
        $stats['backup_files'] = count( $backup_files );

        return $stats;
    }
}

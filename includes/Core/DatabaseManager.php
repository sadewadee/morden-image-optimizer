<?php
// File: includes/Core/DatabaseManager.php

namespace MordenImageOptimizer\Core;

/**
 * Manages database creation and updates for the plugin.
 *
 * This class handles all database operations including table creation,
 * updates, and cleanup. It ensures data integrity and proper schema versioning.
 *
 * @package MordenImageOptimizer\Core
 * @since 1.0.0
 */
class DatabaseManager {

    /**
     * Single instance of the class.
     *
     * @var DatabaseManager|null
     */
    private static $instance = null;

    /**
     * Current database schema version.
     *
     * @var string
     */
    private $db_version = '1.0.0';

    /**
     * WordPress database object.
     *
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Gets the single instance of the class.
     *
     * @return DatabaseManager
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
        global $wpdb;
        $this->wpdb = $wpdb;

        add_action( 'init', [ $this, 'check_database_version' ] );
    }

    /**
     * Checks if database needs to be created or updated.
     */
    public function check_database_version() {
        $installed_version = get_option( 'mio_db_version', '0.0.0' );

        if ( version_compare( $installed_version, $this->db_version, '<' ) ) {
            $this->create_tables();
            $this->update_database_version();

            Logger::get_instance()->info(
                'Database updated',
                [
                    'from_version' => $installed_version,
                    'to_version' => $this->db_version
                ]
            );
        }
    }

    /**
     * Creates or updates database tables.
     */
    public function create_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();

        $this->create_optimization_log_table( $charset_collate );
        $this->create_optimization_queue_table( $charset_collate );
    }

    /**
     * Creates the optimization log table.
     *
     * @param string $charset_collate Database charset and collation.
     */
    private function create_optimization_log_table( $charset_collate ) {
        $table_name = $this->wpdb->prefix . 'mio_optimization_log';

        $sql = "CREATE TABLE $table_name (
            log_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            attachment_id bigint(20) unsigned NOT NULL,
            optimization_status varchar(20) NOT NULL DEFAULT 'pending',
            optimization_method varchar(50) NOT NULL,
            original_size int(10) unsigned NOT NULL DEFAULT 0,
            optimized_size int(10) unsigned NOT NULL DEFAULT 0,
            savings_bytes int(10) unsigned NOT NULL DEFAULT 0,
            error_message text,
            timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (log_id),
            KEY attachment_id (attachment_id),
            KEY optimization_status (optimization_status),
            KEY timestamp (timestamp)
        ) $charset_collate;";

        $this->execute_table_creation( $sql, 'optimization_log' );
    }

    /**
     * Creates the optimization queue table.
     *
     * @param string $charset_collate Database charset and collation.
     */
    private function create_optimization_queue_table( $charset_collate ) {
        $table_name = $this->wpdb->prefix . 'mio_optimization_queue';

        $sql = "CREATE TABLE $table_name (
            queue_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            attachment_id bigint(20) unsigned NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            priority tinyint(1) unsigned NOT NULL DEFAULT 5,
            added_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            started_at datetime NULL,
            completed_at datetime NULL,
            retries tinyint(1) unsigned NOT NULL DEFAULT 0,
            max_retries tinyint(1) unsigned NOT NULL DEFAULT 3,
            error_message text,
            PRIMARY KEY (queue_id),
            UNIQUE KEY attachment_id (attachment_id),
            KEY status (status),
            KEY priority (priority),
            KEY added_at (added_at)
        ) $charset_collate;";

        $this->execute_table_creation( $sql, 'optimization_queue' );
    }

    /**
     * Executes table creation SQL with proper error handling.
     *
     * @param string $sql SQL statement to execute.
     * @param string $table_name Table name for logging.
     */
    private function execute_table_creation( $sql, $table_name ) {
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $result = dbDelta( $sql );

        if ( ! empty( $result ) ) {
            Logger::get_instance()->info(
                "Database table created/updated: $table_name",
                [ 'result' => $result ]
            );
        }
    }

    /**
     * Updates the database version option.
     */
    private function update_database_version() {
        update_option( 'mio_db_version', $this->db_version );
    }

    /**
     * Drops all plugin tables (used during uninstall).
     */
    public function drop_tables() {
        $tables = [
            $this->wpdb->prefix . 'mio_optimization_log',
            $this->wpdb->prefix . 'mio_optimization_queue'
        ];

        foreach ( $tables as $table ) {
            $sql = "DROP TABLE IF EXISTS $table";
            $this->wpdb->query( $sql );

            Logger::get_instance()->info( "Dropped table: $table" );
        }
    }

    /**
     * Gets optimization statistics from the database.
     *
     * @return array Array of statistics.
     */
    public function get_optimization_stats() {
        $log_table = $this->wpdb->prefix . 'mio_optimization_log';

        $stats = $this->wpdb->get_row( "
            SELECT
                COUNT(*) as total_optimizations,
                SUM(savings_bytes) as total_savings,
                AVG(savings_bytes) as average_savings,
                COUNT(CASE WHEN optimization_status = 'success' THEN 1 END) as successful_optimizations,
                COUNT(CASE WHEN optimization_status = 'failed' THEN 1 END) as failed_optimizations
            FROM $log_table
        ", ARRAY_A );

        return $stats ?: [
            'total_optimizations' => 0,
            'total_savings' => 0,
            'average_savings' => 0,
            'successful_optimizations' => 0,
            'failed_optimizations' => 0
        ];
    }

    /**
     * Logs an optimization operation.
     *
     * @param int    $attachment_id Attachment ID.
     * @param string $status Operation status.
     * @param string $method Optimization method used.
     * @param int    $original_size Original file size.
     * @param int    $optimized_size Optimized file size.
     * @param string $error_message Error message if any.
     * @return int|false Insert ID on success, false on failure.
     */
    public function log_optimization( $attachment_id, $status, $method, $original_size = 0, $optimized_size = 0, $error_message = '' ) {
        $log_table = $this->wpdb->prefix . 'mio_optimization_log';

        $data = [
            'attachment_id' => $attachment_id,
            'optimization_status' => $status,
            'optimization_method' => $method,
            'original_size' => $original_size,
            'optimized_size' => $optimized_size,
            'savings_bytes' => max( 0, $original_size - $optimized_size ),
            'error_message' => $error_message,
        ];

        $result = $this->wpdb->insert( $log_table, $data );

        if ( false === $result ) {
            Logger::get_instance()->error(
                'Failed to log optimization',
                [ 'attachment_id' => $attachment_id, 'wpdb_error' => $this->wpdb->last_error ]
            );
        }

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Adds an item to the optimization queue.
     *
     * @param int $attachment_id Attachment ID.
     * @param int $priority Priority level (1-10, lower is higher priority).
     * @return bool True on success, false on failure.
     */
    public function add_to_queue( $attachment_id, $priority = 5 ) {
        $queue_table = $this->wpdb->prefix . 'mio_optimization_queue';

        $data = [
            'attachment_id' => $attachment_id,
            'priority' => max( 1, min( 10, $priority ) ),
        ];

        $result = $this->wpdb->insert( $queue_table, $data );

        return false !== $result;
    }

    /**
     * Gets the next item from the optimization queue.
     *
     * @return object|null Queue item or null if queue is empty.
     */
    public function get_next_queue_item() {
        $queue_table = $this->wpdb->prefix . 'mio_optimization_queue';

        return $this->wpdb->get_row( "
            SELECT * FROM $queue_table
            WHERE status = 'pending'
            AND retries < max_retries
            ORDER BY priority ASC, added_at ASC
            LIMIT 1
        " );
    }

    /**
     * Updates queue item status.
     *
     * @param int    $queue_id Queue item ID.
     * @param string $status New status.
     * @param string $error_message Error message if any.
     * @return bool True on success, false on failure.
     */
    public function update_queue_item( $queue_id, $status, $error_message = '' ) {
        $queue_table = $this->wpdb->prefix . 'mio_optimization_queue';

        $data = [ 'status' => $status ];

        if ( 'processing' === $status ) {
            $data['started_at'] = current_time( 'mysql' );
        } elseif ( in_array( $status, [ 'completed', 'failed' ], true ) ) {
            $data['completed_at'] = current_time( 'mysql' );
            if ( 'failed' === $status ) {
                $data['retries'] = new \stdClass(); // Will be incremented by SQL
                $data['error_message'] = $error_message;
            }
        }

        $where = [ 'queue_id' => $queue_id ];

        if ( 'failed' === $status ) {
            // Increment retries
            return $this->wpdb->query(
                $this->wpdb->prepare(
                    "UPDATE $queue_table SET status = %s, retries = retries + 1, error_message = %s, completed_at = %s WHERE queue_id = %d",
                    $status,
                    $error_message,
                    current_time( 'mysql' ),
                    $queue_id
                )
            );
        }

        return false !== $this->wpdb->update( $queue_table, $data, $where );
    }

    /**
     * Removes completed items from the queue.
     *
     * @param int $days_old Remove items older than this many days.
     * @return int Number of items removed.
     */
    public function cleanup_queue( $days_old = 7 ) {
        $queue_table = $this->wpdb->prefix . 'mio_optimization_queue';

        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM $queue_table WHERE status IN ('completed', 'failed') AND completed_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days_old
            )
        );

        if ( $result > 0 ) {
            Logger::get_instance()->info( "Cleaned up $result old queue items" );
        }

        return $result;
    }
}
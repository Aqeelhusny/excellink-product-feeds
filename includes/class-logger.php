<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Error logging system for feed generation and plugin operations.
 * Logs to WordPress debug.log when WP_DEBUG is enabled, and maintains
 * an internal log for admin display.
 */
class ELF_Logger {

    private const LOG_OPTION = 'elf_error_log';
    private const MAX_LOG_ENTRIES = 100;

    /**
     * Check if logging is enabled in plugin settings.
     */
    public static function is_enabled(): bool {
        $settings = get_option( 'elf_settings', [] );
        return ( $settings['enable_logging'] ?? 'no' ) === 'yes';
    }

    /**
     * Log an error message.
     *
     * @param string $message The error message
     * @param string $context Context (e.g., 'feed_generation', 'cron', 'ajax')
     * @param array $data Additional data to log
     */
    public static function error( string $message, string $context = 'general', array $data = [] ): void {
        self::log( 'error', $message, $context, $data );
    }

    /**
     * Log a warning message.
     *
     * @param string $message The warning message
     * @param string $context Context (e.g., 'feed_generation', 'cron', 'ajax')
     * @param array $data Additional data to log
     */
    public static function warning( string $message, string $context = 'general', array $data = [] ): void {
        self::log( 'warning', $message, $context, $data );
    }

    /**
     * Log an info message.
     *
     * @param string $message The info message
     * @param string $context Context (e.g., 'feed_generation', 'cron', 'ajax')
     * @param array $data Additional data to log
     */
    public static function info( string $message, string $context = 'general', array $data = [] ): void {
        self::log( 'info', $message, $context, $data );
    }

    /**
     * Core logging method.
     *
     * @param string $level Log level (error, warning, info)
     * @param string $message The message to log
     * @param string $context Context identifier
     * @param array $data Additional data
     */
    private static function log( string $level, string $message, string $context, array $data ): void {
        if ( ! self::is_enabled() ) {
            return;
        }

        $timestamp = current_time( 'mysql' );
        $entry = [
            'timestamp' => $timestamp,
            'level'     => $level,
            'context'   => $context,
            'message'   => $message,
            'data'      => $data,
        ];

        // Log to WordPress debug.log if WP_DEBUG is enabled
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $log_message = sprintf(
                '[ELF %s] [%s] %s',
                $timestamp,
                strtoupper( $level ),
                $message
            );
            if ( ! empty( $data ) ) {
                $log_message .= ' ' . json_encode( $data );
            }
            error_log( $log_message );
        }

        // Store in internal log for admin display
        self::store_log_entry( $entry );
    }

    /**
     * Store a log entry in the database.
     *
     * @param array $entry The log entry
     */
    private static function store_log_entry( array $entry ): void {
        $log = get_option( self::LOG_OPTION, [] );
        
        // Add new entry at the beginning
        array_unshift( $log, $entry );

        // Keep only the most recent entries
        if ( count( $log ) > self::MAX_LOG_ENTRIES ) {
            $log = array_slice( $log, 0, self::MAX_LOG_ENTRIES );
        }

        update_option( self::LOG_OPTION, $log );
    }

    /**
     * Get all log entries.
     *
     * @param string $level Optional filter by level
     * @param string $context Optional filter by context
     * @return array Log entries
     */
    public static function get_logs( string $level = '', string $context = '' ): array {
        $log = get_option( self::LOG_OPTION, [] );
        
        if ( ! is_array( $log ) ) {
            return [];
        }

        // Filter by level if specified
        if ( $level ) {
            $log = array_filter( $log, function( $entry ) use ( $level ) {
                return $entry['level'] === $level;
            } );
        }

        // Filter by context if specified
        if ( $context ) {
            $log = array_filter( $log, function( $entry ) use ( $context ) {
                return $entry['context'] === $context;
            } );
        }

        return $log;
    }

    /**
     * Clear all log entries.
     */
    public static function clear_logs(): void {
        delete_option( self::LOG_OPTION );
    }

    /**
     * Get log entry count by level.
     *
     * @return array Counts by level
     */
    public static function get_log_counts(): array {
        $log = get_option( self::LOG_OPTION, [] );
        
        if ( ! is_array( $log ) ) {
            return [ 'error' => 0, 'warning' => 0, 'info' => 0 ];
        }

        $counts = [ 'error' => 0, 'warning' => 0, 'info' => 0 ];
        
        foreach ( $log as $entry ) {
            if ( isset( $counts[ $entry['level'] ] ) ) {
                $counts[ $entry['level'] ]++;
            }
        }

        return $counts;
    }
}

<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Rate limiting for AJAX endpoints to prevent abuse.
 * Uses WordPress transients to track request counts per user/IP.
 */
class ELF_Rate_Limiter {

    private const TRANSIENT_PREFIX = 'elf_rate_limit_';
    private const DEFAULT_LIMIT = 60; // requests per hour
    private const DEFAULT_WINDOW = HOUR_IN_SECONDS;

    /**
     * Check if a request is allowed based on rate limits.
     *
     * @param string $identifier Unique identifier (user ID or IP)
     * @param string $action The action being performed
     * @param int $limit Max requests allowed
     * @param int $window Time window in seconds
     * @return bool True if allowed, false if rate limited
     */
    public static function is_allowed( string $identifier, string $action, int $limit = self::DEFAULT_LIMIT, int $window = self::DEFAULT_WINDOW ): bool {
        $key = self::get_transient_key( $identifier, $action );
        $data = get_transient( $key );

        if ( false === $data ) {
            // First request or expired
            $data = [
                'count' => 1,
                'start' => time(),
            ];
            set_transient( $key, $data, $window );
            return true;
        }

        // Check if window has expired
        if ( time() - $data['start'] > $window ) {
            // Reset counter
            $data = [
                'count' => 1,
                'start' => time(),
            ];
            set_transient( $key, $data, $window );
            return true;
        }

        // Check if limit exceeded
        if ( $data['count'] >= $limit ) {
            return false;
        }

        // Increment counter
        $data['count']++;
        set_transient( $key, $data, $window );
        return true;
    }

    /**
     * Get remaining requests for a user/action.
     *
     * @param string $identifier Unique identifier
     * @param string $action The action being performed
     * @param int $limit Max requests allowed
     * @return int Remaining requests
     */
    public static function get_remaining( string $identifier, string $action, int $limit = self::DEFAULT_LIMIT ): int {
        $key = self::get_transient_key( $identifier, $action );
        $data = get_transient( $key );

        if ( false === $data ) {
            return $limit;
        }

        return max( 0, $limit - $data['count'] );
    }

    /**
     * Reset rate limit for a user/action.
     *
     * @param string $identifier Unique identifier
     * @param string $action The action being performed
     */
    public static function reset( string $identifier, string $action ): void {
        $key = self::get_transient_key( $identifier, $action );
        delete_transient( $key );
    }

    /**
     * Get transient key for rate limiting.
     *
     * @param string $identifier Unique identifier
     * @param string $action The action being performed
     * @return string Transient key
     */
    private static function get_transient_key( string $identifier, string $action ): string {
        return self::TRANSIENT_PREFIX . md5( $identifier . '_' . $action );
    }

    /**
     * Get user identifier for rate limiting.
     * Uses user ID for logged-in users, IP for guests.
     *
     * @return string User identifier
     */
    public static function get_user_identifier(): string {
        if ( is_user_logged_in() ) {
            return 'user_' . get_current_user_id();
        }
        
        return 'ip_' . self::get_client_ip();
    }

    /**
     * Get client IP address.
     *
     * @return string IP address
     */
    private static function get_client_ip(): string {
        $ip = '';
        
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }
        
        return $ip ?: 'unknown';
    }

    /**
     * Check AJAX request rate limit and send error if exceeded.
     *
     * @param string $action The AJAX action
     * @param int $limit Max requests allowed
     * @param int $window Time window in seconds
     * @return bool True if allowed, sends JSON error if not
     */
    public static function check_ajax_limit( string $action, int $limit = self::DEFAULT_LIMIT, int $window = self::DEFAULT_WINDOW ): bool {
        $identifier = self::get_user_identifier();
        
        if ( ! self::is_allowed( $identifier, $action, $limit, $window ) ) {
            $remaining = self::get_remaining( $identifier, $action, $limit );
            $retry_after = $window;
            
            // translators: %d is the number of API requests remaining before the rate limit resets.
            wp_send_json_error( [
                'message' => sprintf(
                    __( 'Rate limit exceeded. Please try again later. %d requests remaining.', 'excellink-product-feeds' ),
                    $remaining
                ),
                'retry_after' => $retry_after,
            ] );
        }
        
        return true;
    }
}

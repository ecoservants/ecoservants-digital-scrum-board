<?php
/**
 * Centralized Security Middleware for EcoServants REST API
 *
 * Provides nonce verification, rate limiting, enum validation,
 * and ownership checks for all API endpoints.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EcoServants_API_Security {

    // ──────────────────────────────────────────────
    //  NONCE VERIFICATION
    // ──────────────────────────────────────────────

    /**
     * Verify the X-WP-Nonce header on write requests.
     *
     * WordPress REST API already handles nonce verification when
     * the X-WP-Nonce header is present. This method provides an
     * explicit check for write operations as an extra safety net.
     *
     * @param WP_REST_Request $request
     * @return true|WP_Error
     */
    public static function verify_nonce( $request ) {
        // Check the actual X-WP-Nonce header value against 'wp_rest' action.
        // This provides explicit validation beyond WP core's cookie-based flow.
        $nonce = $request->get_header( 'X-WP-Nonce' );

        if ( $nonce && wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return true;
        }

        // Fallback: if authenticated via Application Passwords or OAuth
        // (no cookie/nonce), user ID will be set by those auth handlers.
        if ( get_current_user_id() > 0 && ! $nonce ) {
            return true;
        }

        return new WP_Error(
            'rest_cookie_invalid_nonce',
            __( 'Invalid or missing security nonce.', 'es-scrum' ),
            array( 'status' => 403 )
        );
    }

    // ──────────────────────────────────────────────
    //  RATE LIMITING (transient-based)
    // ──────────────────────────────────────────────

    /**
     * Check if the current user has exceeded the rate limit.
     *
     * @param string $action   Unique action identifier (e.g., 'create_task').
     * @param int    $limit    Maximum requests allowed in the window.
     * @param int    $window   Time window in seconds (default: 3600 = 1 hour).
     * @return true|WP_Error   True if allowed, WP_Error if rate limited.
     */
    public static function check_rate_limit( $action, $limit = 30, $window = 3600 ) {
        $user_id = get_current_user_id();
        if ( $user_id === 0 ) {
            return true; // Anonymous users handled by permission checks
        }

        // Admins bypass rate limiting
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        $key      = "es_scrum_rl_{$action}_{$user_id}";
        $lock_key = $key . '_lock';

        // Check if persistent object cache is active
        $use_cache = wp_using_ext_object_cache();

        // Attempt to acquire lock, retry up to 3 times
        $lock_acquired = false;
        for ( $i = 0; $i < 3; $i++ ) {
            if ( $use_cache ) {
                // Atomic in Redis/Memcache
                $lock_acquired = wp_cache_add( $lock_key, 1, '', 5 );
            } else {
                // Atomic in MySQL (fails if row already exists)
                // Autoload=no ensures we don't pollute the autoload cache
                $lock_acquired = add_option( $lock_key, time() + 5, '', 'no' );
                
                // Check for orphaned/expired database lock
                if ( ! $lock_acquired ) {
                    $expires = (int) get_option( $lock_key );
                    if ( $expires > 0 && $expires < time() ) {
                        // Lock is expired, take it
                        update_option( $lock_key, time() + 5, 'no' );
                        $lock_acquired = true;
                    }
                }
            }

            if ( $lock_acquired ) {
                break;
            }
            usleep( 50000 ); // 50ms backoff
        }

        if ( ! $lock_acquired ) {
            return new WP_Error(
                'rate_limit_contention',
                __( 'Server busy, please try again in a moment.', 'es-scrum' ),
                array( 'status' => 429 )
            );
        }

        $count = (int) get_transient( $key );

        if ( $count >= $limit ) {
            // Clean up lock
            if ( $use_cache ) {
                wp_cache_delete( $lock_key );
            } else {
                delete_option( $lock_key );
            }
            
            return new WP_Error(
                'rate_limit_exceeded',
                sprintf(
                    __( 'Rate limit exceeded. Maximum %d requests per hour for this action.', 'es-scrum' ),
                    $limit
                ),
                array( 'status' => 429 )
            );
        }

        set_transient( $key, $count + 1, $window );
        
        // Clean up lock
        if ( $use_cache ) {
            wp_cache_delete( $lock_key );
        } else {
            delete_option( $lock_key );
        }

        return true;
    }

    // ──────────────────────────────────────────────
    //  ENUM / WHITELIST VALIDATION
    // ──────────────────────────────────────────────

    /**
     * Validate that a value is within an allowed set.
     *
     * @param string $value      The value to check.
     * @param array  $allowed    Array of allowed values.
     * @param string $field_name Human-readable field name for error message.
     * @return true|WP_Error
     */
    public static function validate_enum( $value, $allowed, $field_name = 'value' ) {
        if ( in_array( $value, $allowed, true ) ) {
            return true;
        }

        return new WP_Error(
            'invalid_' . sanitize_key( $field_name ),
            sprintf(
                __( 'Invalid %1$s. Allowed values: %2$s', 'es-scrum' ),
                $field_name,
                implode( ', ', $allowed )
            ),
            array( 'status' => 400 )
        );
    }

    // ──────────────────────────────────────────────
    //  OWNERSHIP CHECK
    // ──────────────────────────────────────────────

    /**
     * Verify the current user owns the resource or is an admin.
     *
     * @param int    $resource_user_id  The user ID who owns the resource.
     * @param string $resource_type     Human-readable type for error message.
     * @return true|WP_Error
     */
    public static function check_ownership( $resource_user_id, $resource_type = 'resource' ) {
        $current_user = get_current_user_id();

        // Admins can modify anything
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        if ( (int) $resource_user_id === $current_user ) {
            return true;
        }

        return new WP_Error(
            'forbidden',
            sprintf(
                __( 'You do not have permission to modify this %s.', 'es-scrum' ),
                $resource_type
            ),
            array( 'status' => 403 )
        );
    }

    // ──────────────────────────────────────────────
    //  ALLOWED VALUE CONSTANTS
    // ──────────────────────────────────────────────

    /** @return array Valid task statuses */
    public static function task_statuses() {
        return array( 'backlog', 'todo', 'in-progress', 'done' );
    }

    /** @return array Valid task priorities */
    public static function task_priorities() {
        return array( 'low', 'medium', 'high' );
    }

    /** @return array Valid task types */
    public static function task_types() {
        return array( 'task', 'bug', 'story' );
    }

    /** @return array Valid sprint statuses */
    public static function sprint_statuses() {
        return array( 'planned', 'active', 'completed', 'archived' );
    }
}

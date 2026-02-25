<?php
/**
 * Role & Capability Registration for EcoServants Scrum Board
 *
 * Manages custom capabilities across WordPress roles:
 *   - es_scrum_view  : read-only access (interns / subscribers)
 *   - es_scrum_edit  : create & modify tasks and sprints (captains / editors)
 *   - es_scrum_admin : unrestricted access, settings management (admins)
 *
 * Program-group enforcement is NOT done here; it lives in the API controllers
 * so that it is always evaluated at request time with fresh user meta.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EcoServants_Scrum_Roles {

    // ──────────────────────────────────────────────
    //  CAPABILITY MAP
    // ──────────────────────────────────────────────

    /**
     * Returns the capability → wp_role mapping.
     *
     * Structure: [ capability => [ wp_role_slug, … ] ]
     */
    private static function capability_map() {
        return array(
            // Any logged-in user can view the board
            'es_scrum_view'  => array( 'subscriber', 'contributor', 'author', 'editor', 'administrator' ),

            // Captains (author / editor) and above can create and modify
            'es_scrum_edit'  => array( 'author', 'editor', 'administrator' ),

            // Only administrators have full scrum admin access
            'es_scrum_admin' => array( 'administrator' ),
        );
    }

    // ──────────────────────────────────────────────
    //  ACTIVATION — Add capabilities
    // ──────────────────────────────────────────────

    /**
     * Register all custom capabilities on plugin activation.
     *
     * Safe to call multiple times (idempotent) — WP_Role::add_cap() is a no-op
     * if the capability is already present.
     */
    public static function register_capabilities() {
        $map = self::capability_map();

        foreach ( $map as $cap => $role_slugs ) {
            foreach ( $role_slugs as $role_slug ) {
                $role = get_role( $role_slug );
                if ( $role instanceof WP_Role ) {
                    $role->add_cap( $cap );
                }
            }
        }
    }

    // ──────────────────────────────────────────────
    //  DEACTIVATION — Remove capabilities
    // ──────────────────────────────────────────────

    /**
     * Strip all custom capabilities on plugin deactivation.
     *
     * This prevents orphaned capabilities lingering in the DB after removal.
     */
    public static function remove_capabilities() {
        $map = self::capability_map();

        foreach ( $map as $cap => $role_slugs ) {
            foreach ( $role_slugs as $role_slug ) {
                $role = get_role( $role_slug );
                if ( $role instanceof WP_Role ) {
                    $role->remove_cap( $cap );
                }
            }
        }
    }

    // ──────────────────────────────────────────────
    //  HELPERS FOR PERMISSION CALLBACKS
    // ──────────────────────────────────────────────

    /**
     * Check if the current user has the es_scrum_view capability.
     *
     * @return true|WP_Error
     */
    public static function require_view() {
        if ( current_user_can( 'es_scrum_view' ) ) {
            return true;
        }

        return new WP_Error(
            'rest_forbidden',
            __( 'You do not have permission to view the Scrum board.', 'es-scrum' ),
            array( 'status' => 403 )
        );
    }

    /**
     * Check if the current user has the es_scrum_edit capability.
     *
     * @return true|WP_Error
     */
    public static function require_edit() {
        if ( current_user_can( 'es_scrum_edit' ) ) {
            return true;
        }

        return new WP_Error(
            'rest_forbidden',
            __( 'You do not have permission to modify Scrum data.', 'es-scrum' ),
            array( 'status' => 403 )
        );
    }

    /**
     * Check if the current user has the es_scrum_admin capability.
     *
     * @return true|WP_Error
     */
    public static function require_admin() {
        if ( current_user_can( 'es_scrum_admin' ) ) {
            return true;
        }

        return new WP_Error(
            'rest_forbidden',
            __( 'You do not have administrator-level Scrum access.', 'es-scrum' ),
            array( 'status' => 403 )
        );
    }

    /**
     * Return true if the current user is an intern:
     * has es_scrum_view but NOT es_scrum_edit.
     *
     * Interns are restricted to tasks within their own program group.
     *
     * @return bool
     */
    public static function current_user_is_intern() {
        return current_user_can( 'es_scrum_view' ) && ! current_user_can( 'es_scrum_edit' );
    }

    /**
     * Get the program group slug for the current user.
     *
     * Thin wrapper around the global helper so controllers can call it
     * without needing to know about es_scrum_get_user_program_group().
     *
     * @return string|null
     */
    public static function current_user_program_group() {
        return es_scrum_get_user_program_group( get_current_user_id() );
    }
}

<?php
/**
 * REST API Controller for EcoServants Activity Log
 *
 * Provides read access to activity logs with pagination and filtering.
 * Replaces the inline function previously in the main plugin file.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EcoServants_Activity_Log_API extends WP_REST_Controller {

    public function __construct() {
        $this->namespace = 'es-scrum/v1';
        $this->rest_base = 'activity';
    }

    /**
     * Register the routes
     */
    public function register_routes() {
        // Collection routes
        register_rest_route( $this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_items' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'create_item' ),
                'permission_callback' => array( $this, 'create_item_permissions_check' ),
            ),
        ) );
    }

    // ──────────────────────────────────────────────
    //  PERMISSIONS
    // ──────────────────────────────────────────────

    public function get_items_permissions_check( $request ) {
        return EcoServants_Scrum_Roles::require_view();
    }

    /**
     * Permission check for POST /activity.
     *
     * Restricted to users with es_scrum_edit (captains and above).
     * Interns (view-only) cannot write activity log entries directly.
     * All write requests must also carry a valid WP REST nonce.
     */
    public function create_item_permissions_check( $request ) {
        $nonce = EcoServants_API_Security::verify_nonce( $request );
        if ( is_wp_error( $nonce ) ) return $nonce;
        return EcoServants_Scrum_Roles::require_edit();
    }

    // ──────────────────────────────────────────────
    //  GET /activity — List activity with pagination & filters
    // ──────────────────────────────────────────────

    public function get_items( $request ) {
        $db    = es_scrum_db();
        $table = es_scrum_table_name( 'activity_log' );
        $pag   = EcoServants_API_Response::parse_pagination( $request );

        $where = 'WHERE 1=1';
        $args  = array();

        // Filter by task_id (required for task-level view)
        $task_id = $request->get_param( 'task_id' );
        if ( ! empty( $task_id ) ) {
            $where .= ' AND task_id = %d';
            $args[] = absint( $task_id );
        }

        // Filter by user_id
        $user_id = $request->get_param( 'user_id' );
        if ( ! empty( $user_id ) ) {
            $where .= ' AND user_id = %d';
            $args[] = absint( $user_id );
        }

        // Filter by action type
        $action = $request->get_param( 'action' );
        if ( ! empty( $action ) ) {
            $where .= ' AND action = %s';
            $args[] = sanitize_text_field( $action );
        }

        // Data query
        $sql    = "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $args[] = $pag['per_page'];
        $args[] = $pag['offset'];

        $activity = $db->get_results( $db->prepare( $sql, $args ) );

        // Count query
        $count_args = array();
        if ( ! empty( $task_id ) ) {
            $count_args[] = absint( $task_id );
        }
        if ( ! empty( $user_id ) ) {
            $count_args[] = absint( $user_id );
        }
        if ( ! empty( $action ) ) {
            $count_args[] = sanitize_text_field( $action );
        }

        $count_sql = "SELECT COUNT(*) FROM {$table} {$where}";
        if ( ! empty( $count_args ) ) {
            $total = (int) $db->get_var( $db->prepare( $count_sql, $count_args ) );
        } else {
            $total = (int) $db->get_var( "SELECT COUNT(*) FROM {$table}" );
        }

        return EcoServants_API_Response::paginated( $activity, $total, $pag['page'], $pag['per_page'] );
    }

    // ──────────────────────────────────────────────
    //  POST /activity — Create a manual log entry
    // ──────────────────────────────────────────────

    public function create_item( $request ) {
        // Rate limit: 120 log entries per hour
        $rate = EcoServants_API_Security::check_rate_limit( 'create_activity', 120 );
        if ( is_wp_error( $rate ) ) return $rate;

        $params = $request->get_json_params();

        $task_id = ! empty( $params['task_id'] ) ? absint( $params['task_id'] ) : null;
        $action  = ! empty( $params['action'] ) ? sanitize_text_field( $params['action'] ) : '';

        if ( empty( $action ) ) {
            return EcoServants_API_Response::error( 'missing_action', 'Action is required' );
        }

        $db    = es_scrum_db();
        $table = es_scrum_table_name( 'activity_log' );

        $data = array(
            'task_id'    => $task_id,
            'user_id'    => get_current_user_id(),
            'action'     => $action,
            'from_value' => isset( $params['from_value'] ) ? sanitize_text_field( $params['from_value'] ) : '',
            'to_value'   => isset( $params['to_value'] ) ? sanitize_text_field( $params['to_value'] ) : '',
            'created_at' => current_time( 'mysql' ),
        );

        $result = $db->insert( $table, $data );

        if ( false === $result ) {
            return EcoServants_API_Response::error( 'db_error', 'Could not create activity log entry', 500 );
        }

        $new_id = $db->insert_id;
        $entry  = $db->get_row( $db->prepare( "SELECT * FROM {$table} WHERE id = %d", $new_id ) );

        return EcoServants_API_Response::success( $entry, 201 );
    }
}

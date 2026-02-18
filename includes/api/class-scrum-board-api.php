<?php
/**
 * REST API Controller for EcoServants Scrum Board Tasks
 *
 * Provides complete CRUD operations for tasks with real database queries,
 * pagination, filtering, and standardized responses.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EcoServants_Scrum_Board_API extends WP_REST_Controller {

    public function __construct() {
        $this->namespace = 'es-scrum/v1';
        $this->rest_base = 'tasks';
    }

    /**
     * Register the routes
     */
    public function register_routes() {
        // Collection routes: GET (list) and POST (create)
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

        // Single item routes: GET, PATCH, DELETE
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_item' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
                'args'                => array(
                    'id' => array(
                        'validate_callback' => function( $param ) {
                            return is_numeric( $param );
                        },
                    ),
                ),
            ),
            array(
                'methods'             => array( 'PUT', 'PATCH' ),
                'callback'            => array( $this, 'update_item' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ),
                'args'                => array(
                    'id' => array(
                        'validate_callback' => function( $param ) {
                            return is_numeric( $param );
                        },
                    ),
                ),
            ),
            array(
                'methods'             => 'DELETE',
                'callback'            => array( $this, 'delete_item' ),
                'permission_callback' => array( $this, 'delete_item_permissions_check' ),
                'args'                => array(
                    'id' => array(
                        'validate_callback' => function( $param ) {
                            return is_numeric( $param );
                        },
                    ),
                ),
            ),
        ) );
    }

    // ──────────────────────────────────────────────
    //  PERMISSIONS
    // ──────────────────────────────────────────────

    public function get_items_permissions_check( $request ) {
        return es_scrum_rest_permission_check();
    }

    public function create_item_permissions_check( $request ) {
        $nonce = EcoServants_API_Security::verify_nonce( $request );
        if ( is_wp_error( $nonce ) ) return $nonce;
        return current_user_can( 'edit_posts' );
    }

    public function update_item_permissions_check( $request ) {
        $nonce = EcoServants_API_Security::verify_nonce( $request );
        if ( is_wp_error( $nonce ) ) return $nonce;
        return current_user_can( 'edit_posts' );
    }

    public function delete_item_permissions_check( $request ) {
        $nonce = EcoServants_API_Security::verify_nonce( $request );
        if ( is_wp_error( $nonce ) ) return $nonce;
        return current_user_can( 'edit_posts' );
    }

    // ──────────────────────────────────────────────
    //  GET /tasks — List tasks with pagination & filters
    // ──────────────────────────────────────────────

    public function get_items( $request ) {
        $db    = es_scrum_db();
        $table = es_scrum_table_name( 'tasks' );
        $pag   = EcoServants_API_Response::parse_pagination( $request, 50 );

        // Build WHERE clause with optional filters
        $where = 'WHERE 1=1';
        $args  = array();

        $filters = array(
            'status'       => '%s',
            'program_slug' => '%s',
            'sprint_id'    => '%d',
            'assignee_id'  => '%d',
            'priority'     => '%s',
            'type'         => '%s',
        );

        foreach ( $filters as $param => $format ) {
            $value = $request->get_param( $param );
            if ( ! empty( $value ) ) {
                $where .= " AND {$param} = {$format}";
                $args[] = $value;
            }
        }

        // Data query
        $sql = "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $args[] = $pag['per_page'];
        $args[] = $pag['offset'];

        $tasks = $db->get_results( $db->prepare( $sql, $args ) );

        // Count query (must use same WHERE, without LIMIT/OFFSET)
        $count_args = array();
        foreach ( $filters as $param => $format ) {
            $value = $request->get_param( $param );
            if ( ! empty( $value ) ) {
                $count_args[] = $value;
            }
        }

        $count_sql = "SELECT COUNT(*) FROM {$table} {$where}";
        if ( ! empty( $count_args ) ) {
            $total = (int) $db->get_var( $db->prepare( $count_sql, $count_args ) );
        } else {
            $total = (int) $db->get_var( "SELECT COUNT(*) FROM {$table}" );
        }

        return EcoServants_API_Response::paginated( $tasks, $total, $pag['page'], $pag['per_page'] );
    }

    // ──────────────────────────────────────────────
    //  GET /tasks/{id} — Get single task
    // ──────────────────────────────────────────────

    public function get_item( $request ) {
        $db    = es_scrum_db();
        $table = es_scrum_table_name( 'tasks' );
        $id    = absint( $request->get_param( 'id' ) );

        $task = $db->get_row( $db->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

        if ( ! $task ) {
            return EcoServants_API_Response::error( 'not_found', 'Task not found', 404 );
        }

        return rest_ensure_response( $task );
    }

    // ──────────────────────────────────────────────
    //  POST /tasks — Create a new task
    // ──────────────────────────────────────────────

    public function create_item( $request ) {
        // Rate limit: 30 tasks per hour
        $rate = EcoServants_API_Security::check_rate_limit( 'create_task', 30 );
        if ( is_wp_error( $rate ) ) return $rate;

        $params = $request->get_json_params();

        if ( empty( $params['title'] ) ) {
            return EcoServants_API_Response::error( 'missing_title', 'Title is required' );
        }

        // Validate enum fields
        if ( isset( $params['status'] ) ) {
            $check = EcoServants_API_Security::validate_enum( $params['status'], EcoServants_API_Security::task_statuses(), 'status' );
            if ( is_wp_error( $check ) ) return $check;
        }
        if ( isset( $params['priority'] ) ) {
            $check = EcoServants_API_Security::validate_enum( $params['priority'], EcoServants_API_Security::task_priorities(), 'priority' );
            if ( is_wp_error( $check ) ) return $check;
        }
        if ( isset( $params['type'] ) ) {
            $check = EcoServants_API_Security::validate_enum( $params['type'], EcoServants_API_Security::task_types(), 'type' );
            if ( is_wp_error( $check ) ) return $check;
        }

        $db    = es_scrum_db();
        $table = es_scrum_table_name( 'tasks' );

        // Resolve program_slug from user's group, fall back to param or default
        $user_id      = get_current_user_id();
        $program_slug = ! empty( $params['program_slug'] )
            ? sanitize_text_field( $params['program_slug'] )
            : es_scrum_get_user_program_group( $user_id );

        if ( empty( $program_slug ) ) {
            $program_slug = 'default-program';
        }

        $now = current_time( 'mysql', 1 );

        $data = array(
            'title'        => sanitize_text_field( $params['title'] ),
            'description'  => isset( $params['description'] ) ? sanitize_textarea_field( $params['description'] ) : '',
            'program_slug' => $program_slug,
            'sprint_id'    => ! empty( $params['sprint_id'] ) ? absint( $params['sprint_id'] ) : null,
            'status'       => isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : 'backlog',
            'priority'     => isset( $params['priority'] ) ? sanitize_text_field( $params['priority'] ) : 'medium',
            'type'         => isset( $params['type'] ) ? sanitize_text_field( $params['type'] ) : 'task',
            'reporter_id'  => $user_id,
            'assignee_id'  => ! empty( $params['assignee_id'] ) ? absint( $params['assignee_id'] ) : null,
            'story_points' => isset( $params['story_points'] ) ? absint( $params['story_points'] ) : null,
            'tags'         => isset( $params['tags'] ) ? sanitize_text_field( $params['tags'] ) : null,
            'due_date'     => ! empty( $params['due_date'] ) ? sanitize_text_field( $params['due_date'] ) : null,
            'created_at'   => $now,
            'updated_at'   => $now,
        );

        $result = $db->insert( $table, $data );

        if ( false === $result ) {
            return EcoServants_API_Response::error( 'db_error', 'Could not create task', 500 );
        }

        $new_id = $db->insert_id;

        // Log activity
        es_scrum_log_activity( $new_id, $user_id, 'created', '', $data['status'] );

        // Return the full record from DB
        $task = $db->get_row( $db->prepare( "SELECT * FROM {$table} WHERE id = %d", $new_id ) );

        return EcoServants_API_Response::success( $task, 201 );
    }

    // ──────────────────────────────────────────────
    //  PATCH /tasks/{id} — Update a task
    // ──────────────────────────────────────────────

    public function update_item( $request ) {
        $db    = es_scrum_db();
        $table = es_scrum_table_name( 'tasks' );
        $id    = absint( $request->get_param( 'id' ) );

        // Verify task exists
        $existing = $db->get_row( $db->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
        if ( ! $existing ) {
            return EcoServants_API_Response::error( 'not_found', 'Task not found', 404 );
        }

        $params = $request->get_json_params();
        if ( empty( $params ) ) {
            return EcoServants_API_Response::error( 'no_data', 'No update data provided' );
        }

        // Validate enum fields if present
        if ( isset( $params['status'] ) ) {
            $check = EcoServants_API_Security::validate_enum( $params['status'], EcoServants_API_Security::task_statuses(), 'status' );
            if ( is_wp_error( $check ) ) return $check;
        }
        if ( isset( $params['priority'] ) ) {
            $check = EcoServants_API_Security::validate_enum( $params['priority'], EcoServants_API_Security::task_priorities(), 'priority' );
            if ( is_wp_error( $check ) ) return $check;
        }
        if ( isset( $params['type'] ) ) {
            $check = EcoServants_API_Security::validate_enum( $params['type'], EcoServants_API_Security::task_types(), 'type' );
            if ( is_wp_error( $check ) ) return $check;
        }

        // Build update data from allowed fields
        $allowed_fields = array(
            'title'        => 'sanitize_text_field',
            'description'  => 'sanitize_textarea_field',
            'status'       => 'sanitize_text_field',
            'priority'     => 'sanitize_text_field',
            'type'         => 'sanitize_text_field',
            'program_slug' => 'sanitize_text_field',
            'tags'         => 'sanitize_text_field',
            'due_date'     => 'sanitize_text_field',
        );

        $update_data = array();

        foreach ( $allowed_fields as $field => $sanitizer ) {
            if ( isset( $params[ $field ] ) ) {
                $update_data[ $field ] = call_user_func( $sanitizer, $params[ $field ] );
            }
        }

        // Integer fields handled separately
        $int_fields = array( 'assignee_id', 'sprint_id', 'story_points' );
        foreach ( $int_fields as $field ) {
            if ( isset( $params[ $field ] ) ) {
                $update_data[ $field ] = $params[ $field ] === null ? null : absint( $params[ $field ] );
            }
        }

        if ( empty( $update_data ) ) {
            return EcoServants_API_Response::error( 'no_valid_fields', 'No valid fields to update' );
        }

        // Always update the timestamp
        $update_data['updated_at'] = current_time( 'mysql', 1 );

        $updated = $db->update( $table, $update_data, array( 'id' => $id ) );

        if ( false === $updated ) {
            return EcoServants_API_Response::error( 'db_error', 'Could not update task', 500 );
        }

        // Log status change if applicable
        if ( isset( $update_data['status'] ) && $update_data['status'] !== $existing->status ) {
            es_scrum_log_activity( $id, get_current_user_id(), 'status_change', $existing->status, $update_data['status'] );
        }

        // Return the updated record
        $task = $db->get_row( $db->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

        return rest_ensure_response( $task );
    }

    // ──────────────────────────────────────────────
    //  DELETE /tasks/{id} — Delete a task
    // ──────────────────────────────────────────────

    public function delete_item( $request ) {
        $db    = es_scrum_db();
        $table = es_scrum_table_name( 'tasks' );
        $id    = absint( $request->get_param( 'id' ) );

        // Verify task exists
        $existing = $db->get_row( $db->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
        if ( ! $existing ) {
            return EcoServants_API_Response::error( 'not_found', 'Task not found', 404 );
        }

        // Cascade delete: remove associated comments
        $comments_table = es_scrum_table_name( 'comments' );
        $db->delete( $comments_table, array( 'task_id' => $id ), array( '%d' ) );

        // Cascade delete: remove associated activity log entries
        $activity_table = es_scrum_table_name( 'activity_log' );
        $db->delete( $activity_table, array( 'task_id' => $id ), array( '%d' ) );

        // Delete the task itself
        $deleted = $db->delete( $table, array( 'id' => $id ), array( '%d' ) );

        if ( false === $deleted ) {
            return EcoServants_API_Response::error( 'db_error', 'Could not delete task', 500 );
        }

        return new WP_REST_Response( array(
            'deleted' => true,
            'id'      => $id,
        ), 200 );
    }
}

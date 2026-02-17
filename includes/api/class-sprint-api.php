<?php
/**
 * REST API Controller for EcoServants Sprints
 *
 * Provides complete CRUD operations for sprints with real database queries,
 * pagination, filtering, and standardized responses.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EcoServants_Sprint_API extends WP_REST_Controller {

    public function __construct() {
        $this->namespace = 'es-scrum/v1';
        $this->rest_base = 'sprints';
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

        // Single item routes
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

        // Analytics route
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/analytics', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_analytics' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
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
    //  GET /sprints/{id}/analytics — Sprint stats
    // ──────────────────────────────────────────────

    public function get_analytics( $request ) {
        $db           = es_scrum_db();
        $sprint_table = es_scrum_table_name( 'sprints' );
        $tasks_table  = es_scrum_table_name( 'tasks' );
        $id           = absint( $request->get_param( 'id' ) );

        // Verify sprint exists
        $sprint = $db->get_row( $db->prepare( "SELECT * FROM {$sprint_table} WHERE id = %d", $id ) );
        if ( ! $sprint ) {
            return EcoServants_API_Response::error( 'not_found', 'Sprint not found', 404 );
        }

        // Get tasks in this sprint
        $tasks = $db->get_results( $db->prepare(
            "SELECT status, story_points FROM {$tasks_table} WHERE sprint_id = %d",
            $id
        ) );

        $total          = count( $tasks );
        $completed      = 0;
        $in_progress    = 0;
        $backlog        = 0;
        $todo           = 0;
        $total_points   = 0;
        $done_points    = 0;

        foreach ( $tasks as $task ) {
            $points = absint( $task->story_points );
            $total_points += $points;

            switch ( $task->status ) {
                case 'done':
                    $completed++;
                    $done_points += $points;
                    break;
                case 'in-progress':
                    $in_progress++;
                    break;
                case 'todo':
                    $todo++;
                    break;
                default:
                    $backlog++;
                    break;
            }
        }

        $completion_rate = $total > 0 ? round( ( $completed / $total ) * 100, 1 ) : 0;

        return rest_ensure_response( array(
            'sprint_id'              => $id,
            'sprint_name'            => $sprint->name,
            'sprint_status'          => $sprint->status,
            'total_tasks'            => $total,
            'completed'              => $completed,
            'in_progress'            => $in_progress,
            'todo'                   => $todo,
            'backlog'                => $backlog,
            'total_story_points'     => $total_points,
            'completed_story_points' => $done_points,
            'velocity'               => $done_points,
            'completion_rate'        => $completion_rate,
        ) );
    }

    // ──────────────────────────────────────────────
    //  PERMISSIONS
    // ──────────────────────────────────────────────

    public function get_items_permissions_check( $request ) {
        return es_scrum_rest_permission_check();
    }

    public function create_item_permissions_check( $request ) {
        return current_user_can( 'edit_posts' );
    }

    public function update_item_permissions_check( $request ) {
        return current_user_can( 'edit_posts' );
    }

    public function delete_item_permissions_check( $request ) {
        return current_user_can( 'edit_posts' );
    }

    // ──────────────────────────────────────────────
    //  GET /sprints — List sprints with optional filters
    // ──────────────────────────────────────────────

    public function get_items( $request ) {
        $db    = es_scrum_db();
        $table = es_scrum_table_name( 'sprints' );
        $pag   = EcoServants_API_Response::parse_pagination( $request, 20 );

        $where = 'WHERE 1=1';
        $args  = array();

        // Optional filters
        $status = $request->get_param( 'status' );
        if ( ! empty( $status ) ) {
            $where .= ' AND status = %s';
            $args[] = $status;
        }

        $program_slug = $request->get_param( 'program_slug' );
        if ( ! empty( $program_slug ) ) {
            $where .= ' AND program_slug = %s';
            $args[] = $program_slug;
        }

        // Data query
        $sql    = "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $args[] = $pag['per_page'];
        $args[] = $pag['offset'];

        $sprints = $db->get_results( $db->prepare( $sql, $args ) );

        // Count query
        $count_args = array();
        if ( ! empty( $status ) ) {
            $count_args[] = $status;
        }
        if ( ! empty( $program_slug ) ) {
            $count_args[] = $program_slug;
        }

        $count_sql = "SELECT COUNT(*) FROM {$table} {$where}";
        if ( ! empty( $count_args ) ) {
            $total = (int) $db->get_var( $db->prepare( $count_sql, $count_args ) );
        } else {
            $total = (int) $db->get_var( "SELECT COUNT(*) FROM {$table}" );
        }

        return EcoServants_API_Response::paginated( $sprints, $total, $pag['page'], $pag['per_page'] );
    }

    // ──────────────────────────────────────────────
    //  GET /sprints/{id} — Get single sprint
    // ──────────────────────────────────────────────

    public function get_item( $request ) {
        $db    = es_scrum_db();
        $table = es_scrum_table_name( 'sprints' );
        $id    = absint( $request->get_param( 'id' ) );

        $sprint = $db->get_row( $db->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

        if ( ! $sprint ) {
            return EcoServants_API_Response::error( 'not_found', 'Sprint not found', 404 );
        }

        return rest_ensure_response( $sprint );
    }

    // ──────────────────────────────────────────────
    //  POST /sprints — Create a sprint
    // ──────────────────────────────────────────────

    public function create_item( $request ) {
        $params = $request->get_json_params();

        if ( empty( $params['name'] ) ) {
            return EcoServants_API_Response::error( 'missing_name', 'Sprint name is required' );
        }

        $db    = es_scrum_db();
        $table = es_scrum_table_name( 'sprints' );

        // Resolve program_slug
        $user_id      = get_current_user_id();
        $program_slug = ! empty( $params['program_slug'] )
            ? sanitize_text_field( $params['program_slug'] )
            : es_scrum_get_user_program_group( $user_id );

        if ( empty( $program_slug ) ) {
            $program_slug = 'default-program';
        }

        $data = array(
            'name'         => sanitize_text_field( $params['name'] ),
            'program_slug' => $program_slug,
            'start_date'   => ! empty( $params['start_date'] ) ? sanitize_text_field( $params['start_date'] ) : null,
            'end_date'     => ! empty( $params['end_date'] ) ? sanitize_text_field( $params['end_date'] ) : null,
            'status'       => isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : 'planned',
            'goal'         => ! empty( $params['goal'] ) ? sanitize_textarea_field( $params['goal'] ) : '',
            'created_by'   => $user_id,
            'created_at'   => current_time( 'mysql', 1 ),
        );

        $result = $db->insert( $table, $data );

        if ( false === $result ) {
            return EcoServants_API_Response::error( 'db_error', 'Could not create sprint', 500 );
        }

        $new_id = $db->insert_id;
        $sprint = $db->get_row( $db->prepare( "SELECT * FROM {$table} WHERE id = %d", $new_id ) );

        return EcoServants_API_Response::success( $sprint, 201 );
    }

    // ──────────────────────────────────────────────
    //  PATCH /sprints/{id} — Update a sprint
    // ──────────────────────────────────────────────

    public function update_item( $request ) {
        $db    = es_scrum_db();
        $table = es_scrum_table_name( 'sprints' );
        $id    = absint( $request->get_param( 'id' ) );

        // Verify sprint exists
        $existing = $db->get_row( $db->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
        if ( ! $existing ) {
            return EcoServants_API_Response::error( 'not_found', 'Sprint not found', 404 );
        }

        $params = $request->get_json_params();
        if ( empty( $params ) ) {
            return EcoServants_API_Response::error( 'no_data', 'No update data provided' );
        }

        // Prevent re-activating archived sprints
        if ( $existing->status === 'archived' && isset( $params['status'] ) && $params['status'] !== 'archived' ) {
            return EcoServants_API_Response::error(
                'invalid_transition',
                'Archived sprints cannot be re-activated'
            );
        }

        $allowed_fields = array(
            'name'       => 'sanitize_text_field',
            'status'     => 'sanitize_text_field',
            'start_date' => 'sanitize_text_field',
            'end_date'   => 'sanitize_text_field',
            'goal'       => 'sanitize_textarea_field',
        );

        $update_data = array();
        foreach ( $allowed_fields as $field => $sanitizer ) {
            if ( isset( $params[ $field ] ) ) {
                $update_data[ $field ] = call_user_func( $sanitizer, $params[ $field ] );
            }
        }

        if ( empty( $update_data ) ) {
            return EcoServants_API_Response::error( 'no_valid_fields', 'No valid fields to update' );
        }

        $updated = $db->update( $table, $update_data, array( 'id' => $id ) );

        if ( false === $updated ) {
            return EcoServants_API_Response::error( 'db_error', 'Could not update sprint', 500 );
        }

        // If archiving, unassign tasks from this sprint
        if ( isset( $update_data['status'] ) && $update_data['status'] === 'archived' ) {
            $tasks_table = es_scrum_table_name( 'tasks' );
            $db->update(
                $tasks_table,
                array( 'sprint_id' => null ),
                array( 'sprint_id' => $id ),
                array( '%s' ),
                array( '%d' )
            );
        }

        $sprint = $db->get_row( $db->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

        return rest_ensure_response( $sprint );
    }

    // ──────────────────────────────────────────────
    //  DELETE /sprints/{id} — Delete a sprint
    // ──────────────────────────────────────────────

    public function delete_item( $request ) {
        $db    = es_scrum_db();
        $table = es_scrum_table_name( 'sprints' );
        $id    = absint( $request->get_param( 'id' ) );

        $existing = $db->get_row( $db->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
        if ( ! $existing ) {
            return EcoServants_API_Response::error( 'not_found', 'Sprint not found', 404 );
        }

        // Unassign tasks from this sprint before deleting
        $tasks_table = es_scrum_table_name( 'tasks' );
        $db->update(
            $tasks_table,
            array( 'sprint_id' => null ),
            array( 'sprint_id' => $id ),
            array( '%s' ),
            array( '%d' )
        );

        $deleted = $db->delete( $table, array( 'id' => $id ), array( '%d' ) );

        if ( false === $deleted ) {
            return EcoServants_API_Response::error( 'db_error', 'Could not delete sprint', 500 );
        }

        return new WP_REST_Response( array(
            'deleted' => true,
            'id'      => $id,
        ), 200 );
    }
}
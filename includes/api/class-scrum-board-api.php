<?php
/**
 * REST API Controller for EcoServants Scrum Board
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

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
            array(
                'methods'             => 'PATCH',
                'callback'            => array( $this, 'update_item' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ),
            ),
            array(
                'methods'             => 'DELETE',
                'callback'            => array( $this, 'delete_item' ),
                'permission_callback' => array( $this, 'delete_item_permissions_check' ),
            ),
        ) );
    }

    /**
     * PERMISSIONS
     */
    public function get_items_permissions_check( $request ) {
        return true; 
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

    /**
     * CALLBACKS
     */
    public function get_items( $request ) {
        // MOCK DATA: Simulating a database query
        $mock_tasks = array(
            array(
                'id'          => 1,
                'title'       => 'Fix Login Bug',
                'description' => 'Users cannot reset password.',
                'status'      => 'todo',
                'assignee'    => 'tousif-patel',
                'priority'    => 'high'
            ),
            array(
                'id'          => 2,
                'title'       => 'Setup React Repo',
                'description' => 'Initialize the frontend project.',
                'status'      => 'in-progress',
                'assignee'    => 'gattiuday',
                'priority'    => 'critical'
            ),
            array(
                'id'          => 3,
                'title'       => 'Design API Schema',
                'description' => 'Define the JSON structure.',
                'status'      => 'done',
                'assignee'    => 'mehlikaiclal',
                'priority'    => 'medium'
            ),
        );

        return new WP_REST_Response( $mock_tasks, 200 );
    }

    /**
     * CREATE ITEM (POST)
     */
    public function create_item( $request ) {
        $params = $request->get_json_params();

        if ( empty( $params['title'] ) ) {
            return new WP_Error( 'missing_title', 'Title is required', array( 'status' => 400 ) );
        }

        $db = es_scrum_db();
        $table_name = es_scrum_table_name( 'tasks' );

        $data = array(
            'title' => sanitize_text_field( $params['title'] ),
            'description' => isset( $params['description'] ) ? sanitize_textarea_field( $params['description'] ) : '',
            'program_slug' => 'default-program', // TODO: This should be dynamic
            'status' => isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : 'todo',
            'reporter_id' => get_current_user_id(),
            'created_at' => current_time( 'mysql', 1 ),
            'updated_at' => current_time( 'mysql', 1 ),
        );

        $result = $db->insert( $table_name, $data );

        if ( $result === false ) {
            return new WP_Error( 'db_error', 'Could not create task', array( 'status' => 500 ) );
        }

        $new_task_id = $db->insert_id;

        $new_task = array(
            'id' => $new_task_id,
            'title' => $data['title'],
            'description' => $data['description'],
            'status' => $data['status'],
            'message'   => 'Task created successfully'
        );

        return new WP_REST_Response( $new_task, 201 );
    }

    public function update_item( $request ) {
        return new WP_REST_Response( array( 'message' => 'Task updated' ), 200 );
    }

    public function delete_item( $request ) {
        return new WP_REST_Response( array( 'message' => 'Task deleted' ), 200 );
    }
}

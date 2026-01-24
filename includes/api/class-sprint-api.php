<?php
/**
 * REST API Controller for EcoServants Sprints
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EcoServants_Sprint_API extends WP_REST_Controller {

    public function __construct() {
        $this->namespace = 'es-scrum/v1';
        $this->rest_base = 'sprints';
    }

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
    }

    public function get_items_permissions_check( $request ) {
        return true; 
    }

    public function create_item_permissions_check( $request ) {
        return current_user_can( 'edit_posts' );
    }

    public function get_items( $request ) {
        // MOCK DATA
        $mock_sprints = array(
            array(
                'id'        => 101,
                'name'      => 'Sprint Alpha',
                'status'    => 'active',
                'startDate' => '2025-10-01',
                'endDate'   => '2025-10-14',
                'goal'      => 'Setup Core Infrastructure'
            ),
            array(
                'id'        => 102,
                'name'      => 'Sprint Beta',
                'status'    => 'future',
                'startDate' => '2025-10-15',
                'endDate'   => '2025-10-28',
                'goal'      => 'User Authentication'
            ),
        );

        return new WP_REST_Response( array( 'success' => true, 'data' => $mock_sprints ), 200 );
    }

    public function create_item( $request ) {
        $params = $request->get_json_params();

        if ( empty( $params['name'] ) ) {
            return new WP_Error( 'missing_name', 'Sprint Name is required', array( 'status' => 400 ) );
        }

        $db = es_scrum_db();
        $table_name = es_scrum_table_name( 'sprints' );

        $data = array(
            'name' => sanitize_text_field( $params['name'] ),
            'program_slug' => 'default-program', // TODO: This should be dynamic
            'start_date' => ! empty( $params['start_date'] ) ? sanitize_text_field( $params['start_date'] ) : null,
            'end_date' => ! empty( $params['end_date'] ) ? sanitize_text_field( $params['end_date'] ) : null,
            'goal' => ! empty( $params['goal'] ) ? sanitize_text_field( $params['goal'] ) : '',
            'created_by' => get_current_user_id(),
            'created_at' => current_time( 'mysql', 1 ),
        );

        $result = $db->insert( $table_name, $data );

        if ( $result === false ) {
            return new WP_Error( 'db_error', 'Could not create sprint', array( 'status' => 500 ) );
        }

        $new_sprint_id = $db->insert_id;

        $new_sprint = array(
            'id' => $new_sprint_id,
            'name' => $data['name'],
            'status' => 'planned',
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'goal' => $data['goal'],
            'message'   => 'Sprint created successfully'
        );

        return new WP_REST_Response( $new_sprint, 201 );
    }
}
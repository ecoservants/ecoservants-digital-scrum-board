<?php
class EcoServants_Comment_API extends WP_REST_Controller {
    public function __construct() {
        $this->namespace = 'es-scrum/v1';
        $this->rest_base = 'comments';
    }

    public function register_routes() {
        register_rest_route( $this->namespace, '/' . $this->rest_base, array(
            array(
                'methods' => 'GET',
                'callback' => array( $this, 'get_items' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
            ),
            array(
                'methods' => 'POST',
                'callback' => array( $this, 'create_item' ),
                'permission_callback' => array( $this, 'create_item_permissions_check' ),
            ),
        ) );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
            array(
                'methods' => array( 'PUT', 'PATCH' ),
                'callback' => array( $this, 'update_item' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ),
                'args' => array(
                    'id' => array(
                        'validate_callback' => function($param, $request, $key) {
                            return is_numeric( $param );
                        }
                    ),
                ),
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array( $this, 'delete_item' ),
                'permission_callback' => array( $this, 'delete_item_permissions_check' ),
                'args' => array(
                    'id' => array(
                        'validate_callback' => function($param, $request, $key) {
                            return is_numeric( $param );
                        }
                    ),
                ),
            ),
        ) );
    }

    public function get_items_permissions_check( $request ) {
        return es_scrum_rest_permission_check(); // Reusing the global permission check
    }

    public function create_item_permissions_check( $request ) {
        return es_scrum_rest_permission_check(); // Reusing the global permission check
    }

    public function update_item_permissions_check( $request ) {
        return es_scrum_rest_permission_check(); // Reusing the global permission check
    }

    public function delete_item_permissions_check( $request ) {
        return es_scrum_rest_permission_check(); // Reusing the global permission check
    }

    public function get_items( $request ) {
        $db = es_scrum_db();
        $table = es_scrum_table_name('comments');

        $task_id = $request->get_param('task_id');
        if (!$task_id) {
            return new WP_Error('missing_param', 'Task ID is required', array('status' => 400));
        }

        $sql = $db->prepare("SELECT * FROM {$table} WHERE task_id = %d ORDER BY created_at ASC", $task_id);
        $comments = $db->get_results($sql);

        return rest_ensure_response($comments);
    }

    public function create_item( $request ) {
        $db = es_scrum_db();
        $table = es_scrum_table_name('comments');

        $task_id = $request->get_param('task_id');
        $body = wp_kses_post($request->get_param('body'));
        $user_id = get_current_user_id();

        if (!$task_id || empty($body)) {
            return new WP_Error('missing_data', 'Task ID and Body are required', array('status' => 400));
        }

        $data = array(
            'task_id' => absint($task_id),
            'user_id' => $user_id,
            'body' => $body,
            'created_at' => current_time('mysql'),
        );

        $inserted = $db->insert($table, $data);

        if (!$inserted) {
            return new WP_Error('db_error', 'Could not create comment', array('status' => 500));
        }

        $new_id = $db->insert_id;
        $comment = $db->get_row($db->prepare("SELECT * FROM {$table} WHERE id = %d", $new_id));

        return rest_ensure_response($comment);
    }

    public function update_item( $request ) {
        $db = es_scrum_db();
        $table = es_scrum_table_name('comments');
        $id = $request->get_param('id');
        $body = wp_kses_post($request->get_param('body'));

        if (empty($body)) {
            return new WP_Error('missing_data', 'Body is required', array('status' => 400));
        }

        $data = array('body' => $body);
        $where = array('id' => $id);

        $updated = $db->update($table, $data, $where);

        if ( false === $updated ) {
            return new WP_Error('db_error', 'Could not update comment', array('status' => 500));
        }

        $comment = $db->get_row($db->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        return rest_ensure_response($comment);
    }

    public function delete_item( $request ) {
        $db = es_scrum_db();
        $table = es_scrum_table_name('comments');
        $id = $request->get_param('id');

        $deleted = $db->delete($table, array('id' => $id));

        if ( ! $deleted ) {
            return new WP_Error( 'db_error', 'Could not delete comment', array( 'status' => 500 ) );
        }

        return new WP_REST_Response( true, 204 );
    }
}
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
        $nonce = EcoServants_API_Security::verify_nonce( $request );
        if ( is_wp_error( $nonce ) ) return $nonce;
        return es_scrum_rest_permission_check();
    }

    public function update_item_permissions_check( $request ) {
        $nonce = EcoServants_API_Security::verify_nonce( $request );
        if ( is_wp_error( $nonce ) ) return $nonce;
        return es_scrum_rest_permission_check();
    }

    public function delete_item_permissions_check( $request ) {
        $nonce = EcoServants_API_Security::verify_nonce( $request );
        if ( is_wp_error( $nonce ) ) return $nonce;
        return es_scrum_rest_permission_check();
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
        // Rate limit: 60 comments per hour
        $rate = EcoServants_API_Security::check_rate_limit( 'create_comment', 60 );
        if ( is_wp_error( $rate ) ) return $rate;

        $db = es_scrum_db();
        $table = es_scrum_table_name('comments');

        $task_id = $request->get_param('task_id');
        $body = wp_kses_post($request->get_param('body'));
        $parent_id = $request->get_param('parent_id');
        $user_id = get_current_user_id();

        if ( !$task_id || empty( $body ) ) {
            return new WP_Error( 'missing_data', 'Task ID and Body are required', array( 'status' => 400 ) );
        }

        $task_id_int   = absint( $task_id );
        $parent_id_int = ! empty( $parent_id ) ? absint( $parent_id ) : null;

        // Validate that the parent comment exists and belongs to the same task, if provided.
        if ( null !== $parent_id_int ) {
            $sql = $db->prepare(
                "SELECT COUNT(1) FROM {$table} WHERE id = %d AND task_id = %d",
                $parent_id_int,
                $task_id_int
            );
            $parent_exists = (int) $db->get_var( $sql );

            if ( $parent_exists === 0 ) {
                return new WP_Error(
                    'invalid_parent',
                    'Parent comment does not exist or does not belong to this task',
                    array( 'status' => 400 )
                );
            }
        }

        $data = array(
            'task_id'    => $task_id_int,
            'user_id'    => $user_id,
            'parent_id'  => $parent_id_int, // Store parent_id, null if not provided
            'body'       => $body,
            'created_at' => current_time( 'mysql' ),
        );

        $inserted = $db->insert( $table, $data );

        if (!$inserted) {
            return new WP_Error('db_error', 'Could not create comment', array('status' => 500));
        }

        $new_id = $db->insert_id;
        $comment = $db->get_row($db->prepare("SELECT * FROM {$table} WHERE id = %d", $new_id));

        // Mention parsing
        $mentioned_user_ids = [];
        if (preg_match_all('/@([a-zA-Z0-9_-]+)/', $body, $matches)) {
            foreach ($matches[1] as $username) {
                $mentioned_id = $this->_get_user_id_from_username($username);
                if ($mentioned_id && $mentioned_id !== $user_id) { // Don't notify self
                    $mentioned_user_ids[] = $mentioned_id;
                }
            }
        }
        if (!empty($mentioned_user_ids)) {
            do_action('es_scrum_comment_mentions', $new_id, array_unique($mentioned_user_ids));
        }

        return rest_ensure_response($comment);
    }

    public function update_item( $request ) {
        $db = es_scrum_db();
        $table = es_scrum_table_name('comments');
        $id = $request->get_param('id');
        $body = wp_kses_post($request->get_param('body'));
        $user_id = get_current_user_id();

        if (empty($body)) {
            return new WP_Error('missing_data', 'Body is required', array('status' => 400));
        }

        // Ownership check: only author or admin can edit
        $comment = $db->get_row($db->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        if ( empty( $comment ) ) {
            return new WP_Error( 'comment_not_found', 'Comment not found', array( 'status' => 404 ) );
        }
        $ownership = EcoServants_API_Security::check_ownership( $comment->user_id, 'comment' );
        if ( is_wp_error( $ownership ) ) return $ownership;

        $data = array('body' => $body);
        $where = array('id' => $id);

        $updated = $db->update($table, $data, $where);

        if ( false === $updated ) {
            return new WP_Error('db_error', 'Could not update comment', array('status' => 500));
        }

        // Re-fetch after update
        $comment = $db->get_row($db->prepare("SELECT * FROM {$table} WHERE id = %d", $id));

        // Mention parsing for update
        $mentioned_user_ids = [];
        if (preg_match_all('/@([a-zA-Z0-9_-]+)/', $body, $matches)) {
            foreach ($matches[1] as $username) {
                $mentioned_id = $this->_get_user_id_from_username($username);
                if ($mentioned_id && $mentioned_id !== $user_id) { // Don't notify self
                    $mentioned_user_ids[] = $mentioned_id;
                }
            }
        }
        if (!empty($mentioned_user_ids)) {
            do_action('es_scrum_comment_mentions', $id, array_unique($mentioned_user_ids));
        }

        return rest_ensure_response($comment);
    }

    public function delete_item( $request ) {
        $db = es_scrum_db();
        $table = es_scrum_table_name('comments');
        $id = $request->get_param('id');

        // Ownership check: only author or admin can delete
        $comment = $db->get_row($db->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        if ( empty( $comment ) ) {
            return new WP_Error( 'not_found', 'Comment not found', array( 'status' => 404 ) );
        }
        $ownership = EcoServants_API_Security::check_ownership( $comment->user_id, 'comment' );
        if ( is_wp_error( $ownership ) ) return $ownership;

        $deleted = $db->delete($table, array('id' => $id));

        if ( false === $deleted ) {
            return new WP_Error( 'db_error', 'Could not delete comment', array( 'status' => 500 ) );
        }

        return new WP_REST_Response( true, 200 );
    }

    /**
     * Helper to get user ID from username (login or nicename)
     *
     * @param string $username
     * @return int|null
     */
    private function _get_user_id_from_username( $username ) {
        if ( empty( $username ) ) {
            return null;
        }

        // Try to get user by login (username)
        $user = get_user_by( 'login', $username );

        // If not found, try by nicename (slug) - sometimes used in mentions
        if ( ! $user ) {
            $user = get_user_by( 'nicename', $username );
        }

        return $user ? $user->ID : null;
    }
}
<?php
if (!defined('ABSPATH')) {
    exit;
}

class EcoServants_Subtasks_API extends WP_REST_Controller
{
    public function __construct()
    {
        $this->namespace = 'es-scrum/v1';
        $this->rest_base = 'subtasks';
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'  => 'GET',
                'callback' => array($this, 'get_items'),
                'permission_callback' => '__return_true',
            ),
            array(
                'methods'  => 'POST',
                'callback' => array($this, 'create_item'),
                'permission_callback' => array($this, 'modify_permissions_check'),
            ),
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
            array(
                'methods'  => 'PATCH',
                'callback' => array($this, 'update_item'),
                'permission_callback' => array($this, 'modify_permissions_check'),
            ),
            array(
                'methods'  => 'DELETE',
                'callback' => array($this, 'delete_item'),
                'permission_callback' => array($this, 'modify_permissions_check'),
            ),
        ));

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)/levelup',
            array(
                array(
                    'methods'  => 'POST',
                    'callback' => array($this, 'level_up'),
                    'permission_callback' => array($this, 'modify_permissions_check'),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)/leveldown',
            array(
                array(
                    'methods'  => 'POST',
                    'callback' => array($this, 'level_down'),
                    'permission_callback' => array($this, 'modify_permissions_check'),
                ),
            )
        );
    }

    /* ========================
       PERMISSIONS
    ======================== */

    public function modify_permissions_check($request)
    {
        return current_user_can('edit_posts');
    }

    /* ========================
       GET ITEMS
    ======================== */

    public function get_items($request)
    {
        $db = es_scrum_db();
        $table = es_scrum_table_name('subtasks');

        $page = $request->get_param('page') ? absint($request->get_param('page')) : 1;
        $per_page = $request->get_param('per_page') ? absint($request->get_param('per_page')) : 50;
        $offset = ($page - 1) * $per_page;

        $parent_task = $request->get_param('parent_task');

        $where = "WHERE 1=1";
        $args = array();

        if (!empty($parent_task)) {
            $where .= " AND parent_task = %d";
            $args[] = absint($parent_task);
        }

        $orderby = "ORDER BY sort_order ASC, created_at ASC";
        $limit = "LIMIT %d OFFSET %d";
        $args[] = $per_page;
        $args[] = $offset;

        $sql = "SELECT * FROM {$table} {$where} {$orderby} {$limit}";
        $sql = $db->prepare($sql, $args);

        $items = $db->get_results($sql);

        // Count total
        if (!empty($parent_task)) {
            $count_query = "SELECT COUNT(*) FROM {$table} WHERE parent_task = %d";
            $total = $db->get_var($db->prepare($count_query, $parent_task));
        } else {
            $total = $db->get_var("SELECT COUNT(*) FROM {$table}");
        }

        $max_pages = ceil($total / $per_page);

        $response = new WP_REST_Response($items, 200);
        $response->header('X-WP-Total', (int) $total);
        $response->header('X-WP-TotalPages', (int) $max_pages);

        return $response;
    }

    /* ========================
       CREATE
    ======================== */

    public function create_item($request)
    {
        $params = $request->get_json_params();

        if (empty($params['title']) || empty($params['parent_task'])) {
            return new WP_Error('missing_fields', 'Title and parent_task are required', array('status' => 400));
        }

        $db = es_scrum_db();
        $table = es_scrum_table_name('subtasks');

        $data = array(
            'parent_task' => absint($params['parent_task']),
            'title' => sanitize_text_field($params['title']),
            'notes' => isset($params['notes']) ? sanitize_textarea_field($params['notes']) : '',
            'reporter_id' => get_current_user_id(),
            'is_completed' => 0,
            'sort_order' => isset($params['sort_order']) ? absint($params['sort_order']) : 0,
            'created_at' => current_time('mysql', 1),
            'updated_at' => current_time('mysql', 1),
        );

        $result = $db->insert($table, $data);

        if ($result === false) {
            return new WP_Error('db_error', 'Could not create subtask', array('status' => 500));
        }

        return new WP_REST_Response(array(
            'id' => $db->insert_id,
            'title' => $data['title'],
            'notes' => $data['notes'],
            'is_completed' => 0,
            'sort_order' => $data['sort_order'],
            'message' => 'Subtask created'
        ), 201);
    }

    /* ========================
       UPDATE
    ======================== */

    public function update_item($request)
    {
        $id = absint($request->get_param('id'));
        $params = $request->get_json_params();

        $db = es_scrum_db();
        $table = es_scrum_table_name('subtasks');

        $exists = $db->get_var($db->prepare("SELECT id FROM {$table} WHERE id = %d", $id));
        if (!$exists) {
            return new WP_Error('not_found', 'Subtask not found', array('status' => 404));
        }

        $data = array();
        $format = array();

        if (isset($params['title'])) {
            $data['title'] = sanitize_text_field($params['title']);
            $format[] = '%s';
        }

        if (isset($params['notes'])) {
            $data['notes'] = sanitize_textarea_field($params['notes']);
            $format[] = '%s';
        }

        if (isset($params['is_completed'])) {
            $data['is_completed'] = absint($params['is_completed']);
            $format[] = '%d';
        }

        if (isset($params['sort_order'])) {
            $data['sort_order'] = absint($params['sort_order']);
            $format[] = '%d';
        }

        if (empty($data)) {
            return new WP_Error('no_data', 'No data to update', array('status' => 400));
        }

        $data['updated_at'] = current_time('mysql', 1);
        $format[] = '%s';

        $db->update($table, $data, array('id' => $id), $format, array('%d'));

        return new WP_REST_Response(array(
            'message' => 'Subtask updated',
            'id' => $id
        ), 200);
    }

    /* ========================
       DELETE
    ======================== */

    public function delete_item($request)
    {
        $id = absint($request->get_param('id'));

        $db = es_scrum_db();
        $table = es_scrum_table_name('subtasks');

        $exists = $db->get_var($db->prepare("SELECT id FROM {$table} WHERE id = %d", $id));
        if (!$exists) {
            return new WP_Error('not_found', 'Subtask not found', array('status' => 404));
        }

        $db->delete($table, array('id' => $id), array('%d'));

        return new WP_REST_Response(array(
            'message' => 'Subtask deleted',
            'id' => $id
        ), 200);
    }


    /* ========================
       MOVE SORT_ORDER UP
    ======================== */
    public function level_up($request)
    {
        global $wpdb;

        $id = absint($request->get_param('id'));
        $table = es_scrum_table_name('subtasks');

        // Get current subtask
        $current = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id)
        );

        if (!$current) {
            return new WP_Error('not_found', 'Subtask not found', array('status' => 404));
        }

        // Cannot move above 0
        if ($current->sort_order <= 0) {
            return new WP_REST_Response(array(
                'message' => 'Already at top position'
            ), 200);
        }

        // Find previous item (same parent_task)
        $previous = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                WHERE parent_task = %d
                AND sort_order = %d",
                $current->parent_task,
                $current->sort_order - 1
            )
        );

        if (!$previous) {
            return new WP_Error('invalid_order', 'Cannot move up', array('status' => 400));
        }

        // Swap
        $wpdb->update(
            $table,
            array('sort_order' => $current->sort_order),
            array('id' => $previous->id),
            array('%d'),
            array('%d')
        );

        $wpdb->update(
            $table,
            array('sort_order' => $previous->sort_order),
            array('id' => $current->id),
            array('%d'),
            array('%d')
        );

        return new WP_REST_Response(array(
            'message' => 'Subtask moved up'
        ), 200);
    }

    /* ========================
       MOVE SORT_ORDER DOWN
    ======================== */
    public function level_down($request)
    {
        global $wpdb;

        $id = absint($request->get_param('id'));
        $table = es_scrum_table_name('subtasks');

        $current = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id)
        );

        if (!$current) {
            return new WP_Error('not_found', 'Subtask not found', array('status' => 404));
        }

        // Find max sort_order in same parent_task
        $max_sort = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT MAX(sort_order)
                FROM {$table}
                WHERE parent_task = %d",
                $current->parent_task
            )
        );

        if ($current->sort_order >= $max_sort) {
            return new WP_REST_Response(array(
                'message' => 'Already at bottom position'
            ), 200);
        }

        // Find next item
        $next = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                WHERE parent_task = %d
                AND sort_order = %d",
                $current->parent_task,
                $current->sort_order + 1
            )
        );

        if (!$next) {
            return new WP_Error('invalid_order', 'Cannot move down', array('status' => 400));
        }

        // Swap
        $wpdb->update(
            $table,
            array('sort_order' => $current->sort_order),
            array('id' => $next->id),
            array('%d'),
            array('%d')
        );

        $wpdb->update(
            $table,
            array('sort_order' => $next->sort_order),
            array('id' => $current->id),
            array('%d'),
            array('%d')
        );

        return new WP_REST_Response(array(
            'message' => 'Subtask moved down'
        ), 200);
    }
}
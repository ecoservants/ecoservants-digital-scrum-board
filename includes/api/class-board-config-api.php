<?php

/**
 * Class EcoServants_Board_Config_API
 * Handles REST API requests for board customization
 */
class EcoServants_Board_Config_API
{
    /**
     * Register routes
     */
    public function register_routes()
    {
        register_rest_route(
            'es-scrum/v1',
            '/config',
            array(
                array(
                    'methods' => 'GET',
                    'callback' => array($this, 'get_config'),
                    'permission_callback' => array($this, 'permission_check_read'),
                ),
                array(
                    'methods' => 'POST',
                    'callback' => array($this, 'save_config'),
                    'permission_callback' => array($this, 'permission_check_write'),
                ),
            )
        );
    }

    /**
     * Permission check: Read
     */
    public function permission_check_read()
    {
        return is_user_logged_in();
    }

    /**
     * Permission check: Write
     * For now, any logged-in user with 'read' capability can customize their group's board.
     * Tighter controls (e.g. only group admins) could be added later.
     */
    public function permission_check_write()
    {
        return current_user_can('read');
    }

    /**
     * GET /config
     * Returns the config JSON for the current user's program group
     */
    public function get_config(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        $group = es_scrum_get_user_program_group($user_id);

        if (!$group) {
            return new WP_Error('no_group', 'User does not belong to a program group.', array('status' => 400));
        }

        $db = es_scrum_db();
        $table = es_scrum_table_name('board_configs');

        // Look for existing config
        $row = $db->get_row($db->prepare(
            "SELECT config_json FROM {$table} WHERE program_slug = %s",
            $group
        ));

        if ($row) {
            $data = json_decode($row->config_json, true);
            return rest_ensure_response($data);
        }

        // Return empty/null to signal using defaults on frontend
        return rest_ensure_response(null);
    }

    /**
     * POST /config
     * Saves the config JSON for the current user's program group
     */
    public function save_config(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        $group = es_scrum_get_user_program_group($user_id);

        if (!$group) {
            return new WP_Error('no_group', 'User does not belong to a program group.', array('status' => 400));
        }

        $params = $request->get_json_params();
        if (empty($params)) {
            return new WP_Error('invalid_json', 'Invalid JSON body.', array('status' => 400));
        }

        // Validate basic structure (optional but good practice)
        if (!isset($params['columns']) || !is_array($params['columns'])) {
            return new WP_Error('invalid_schema', 'Missing columns array.', array('status' => 400));
        }

        $json_str = wp_json_encode($params);

        $db = es_scrum_db();
        $table = es_scrum_table_name('board_configs');

        // Check if exists
        $exists = $db->get_var($db->prepare(
            "SELECT id FROM {$table} WHERE program_slug = %s",
            $group
        ));

        if ($exists) {
            // Update
            $result = $db->update(
                $table,
                array(
                    'config_json' => $json_str,
                    'updated_at' => current_time('mysql'),
                ),
                array('program_slug' => $group),
                array('%s', '%s'),
                array('%s')
            );
        } else {
            // Insert
            $result = $db->insert(
                $table,
                array(
                    'program_slug' => $group,
                    'config_json' => $json_str,
                    'updated_at' => current_time('mysql'),
                ),
                array('%s', '%s', '%s')
            );
        }

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to save configuration.', array('status' => 500));
        }

        return rest_ensure_response(array('success' => true, 'config' => $params));
    }
}

<?php
/**
 * REST API Controller for User Profiles
 */

if (!defined('ABSPATH')) {
    exit;
}

class EcoServants_User_Profile_API extends WP_REST_Controller
{

    public function __construct()
    {
        $this->namespace = 'es-scrum/v1';
        $this->rest_base = 'users'; // changed from 'tasks' to match plan
    }

    /**
     * Register the routes
     */
    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/profile', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_user_profile'),
                'permission_callback' => array($this, 'get_item_permissions_check'),
            ),
        ));
    }

    /**
     * Permission check
     */
    public function get_item_permissions_check($request)
    {
        return is_user_logged_in();
    }

    /**
     * Get User Profile Data
     */
    public function get_user_profile($request)
    {
        $user_id = $request->get_param('id');
        $user = get_userdata($user_id);

        if (!$user) {
            return new WP_Error('not_found', 'User not found', array('status' => 404));
        }

        // Basic Info
        $profile_data = array(
            'id' => $user->ID,
            'display_name' => $user->display_name,
            'email' => $user->user_email,
            'avatar_url' => get_avatar_url($user->ID),
            'roles' => $user->roles,
            'program_group' => es_scrum_get_user_program_group($user->ID)
        );

        // Fetch Stats & Tasks & Activity
        $db = es_scrum_db();
        $table_tasks = es_scrum_table_name('tasks');
        $table_activity = es_scrum_table_name('activity_log');

        // 1. Assigned Tasks
        // We get ALL assigned tasks, but frontend can filter/paginate if needed.
        // For now, limiting to 50 recent ones or just all (scrum boards usually small enough).
        $tasks_sql = $db->prepare("SELECT * FROM {$table_tasks} WHERE assignee_id = %d ORDER BY created_at DESC LIMIT 50", $user_id);
        $tasks = $db->get_results($tasks_sql);

        // 2. Stats
        $stats = array(
            'total_assigned' => count($tasks),
            'completed' => 0,
            'in_progress' => 0,
            'overdue' => 0
        );

        $now = current_time('timestamp');

        foreach ($tasks as $t) {
            if ($t->status === 'done' || $t->status === 'completed') {
                $stats['completed']++;
            } elseif ($t->status === 'in_progress') {
                $stats['in_progress']++;
            }

            // Simple overdue check: if due_date < now AND status != done
            if (!empty($t->due_date) && $t->status !== 'done' && $t->status !== 'completed') {
                $due = strtotime($t->due_date);
                if ($due < $now) {
                    $stats['overdue']++;
                }
            }
        }

        // 3. Activity Log
        // Actions performed by this user
        $activity_sql = $db->prepare("SELECT * FROM {$table_activity} WHERE user_id = %d ORDER BY created_at DESC LIMIT 20", $user_id);
        $activity = $db->get_results($activity_sql);

        return new WP_REST_Response(array(
            'user' => $profile_data,
            'stats' => $stats,
            'tasks' => $tasks,
            'activity' => $activity
        ), 200);
    }
}

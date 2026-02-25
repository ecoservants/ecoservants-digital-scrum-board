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

        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/tasks', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_user_tasks'),
                'permission_callback' => array($this, 'get_item_permissions_check'),
            ),
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/activity', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_user_activity'),
                'permission_callback' => array($this, 'get_item_permissions_check'),
            ),
        ));
    }

    /**
     * Permission check — requires es_scrum_view.
     *
     * DC-03: Interns (view only) can only access their own profile.
     * Captains and admins can view any user's profile.
     *
     * @param WP_REST_Request $request
     * @return true|WP_Error
     */
    public function get_item_permissions_check( $request )
    {
        // Must have at least view capability
        $check = EcoServants_Scrum_Roles::require_view();
        if ( is_wp_error( $check ) ) {
            return $check;
        }

        // Interns can only view their own profile
        if ( EcoServants_Scrum_Roles::current_user_is_intern() ) {
            $requested_id = (int) $request->get_param( 'id' );
            if ( $requested_id !== get_current_user_id() ) {
                return new WP_Error(
                    'es_scrum_forbidden_profile',
                    __( 'You can only view your own profile.', 'es-scrum' ),
                    array( 'status' => 403 )
                );
            }
        }

        return true;
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
        // For now, limiting to 5 recent ones for the initial view.
        // Frontend can call /tasks endpoint for more.
        $tasks_sql = $db->prepare("SELECT * FROM {$table_tasks} WHERE assignee_id = %d ORDER BY created_at DESC LIMIT 5", $user_id);
        $tasks = $db->get_results($tasks_sql);

        // 2. Stats
        $total_assigned_count = $db->get_var($db->prepare("SELECT COUNT(*) FROM {$table_tasks} WHERE assignee_id = %d", $user_id));

        $stats = array(
            'total_assigned' => (int) $total_assigned_count,
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
        // Actions performed by this user (limit 5)
        $activity_sql = $db->prepare("SELECT * FROM {$table_activity} WHERE user_id = %d ORDER BY created_at DESC LIMIT 5", $user_id);
        $activity = $db->get_results($activity_sql);

        return new WP_REST_Response(array(
            'user' => $profile_data,
            'stats' => $stats,
            'tasks' => $tasks,
            'activity' => $activity
        ), 200);
    }

    /**
     * Get User Tasks (Paginated)
     */
    public function get_user_tasks($request)
    {
        $user_id = $request->get_param('id');
        $raw_page = $request->get_param('page');
        $page = max(1, (int) $raw_page);
        $per_page = $request->get_param('per_page') ? absint($request->get_param('per_page')) : 10;
        $offset = ($page - 1) * $per_page;

        $db = es_scrum_db();
        $table_tasks = es_scrum_table_name('tasks');

        $sql = $db->prepare("SELECT * FROM {$table_tasks} WHERE assignee_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d", $user_id, $per_page, $offset);
        $tasks = $db->get_results($sql);

        $total = $db->get_var($db->prepare("SELECT COUNT(*) FROM {$table_tasks} WHERE assignee_id = %d", $user_id));
        $max_pages = ceil($total / $per_page);

        $response = rest_ensure_response($tasks);
        $response->header('X-WP-Total', (int) $total);
        $response->header('X-WP-TotalPages', (int) $max_pages);

        return $response;
    }

    /**
     * Get User Activity (Paginated)
     */
    public function get_user_activity($request)
    {
        $user_id = $request->get_param('id');
        $raw_page = $request->get_param('page');
        $page = max(1, (int) $raw_page);
        $per_page = $request->get_param('per_page') ? absint($request->get_param('per_page')) : 10;
        $offset = ($page - 1) * $per_page;

        $db = es_scrum_db();
        $table_activity = es_scrum_table_name('activity_log');

        $sql = $db->prepare("SELECT * FROM {$table_activity} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d", $user_id, $per_page, $offset);
        $activity = $db->get_results($sql);

        $total = $db->get_var($db->prepare("SELECT COUNT(*) FROM {$table_activity} WHERE user_id = %d", $user_id));
        $max_pages = ceil($total / $per_page);

        $response = rest_ensure_response($activity);
        $response->header('X-WP-Total', (int) $total);
        $response->header('X-WP-TotalPages', (int) $max_pages);

        return $response;
    }
}

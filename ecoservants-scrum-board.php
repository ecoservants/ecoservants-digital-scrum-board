<?php
/*
Plugin Name: EcoServants Digital Scrum Board
Description: EcoServants branded digital Scrum board that integrates with WordPress users and supports an optional external database for Scrum data.
Version: 1.0.0
Author: EcoServants
*/

if (!defined('ABSPATH')) {
    exit;
}

define('ES_SCRUM_VERSION', '1.0.1');
define('ES_SCRUM_PLUGIN_FILE', __FILE__);
define('ES_SCRUM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ES_SCRUM_PLUGIN_URL', plugin_dir_url(__FILE__));

// EcoServants logo URL
define(
    'ES_SCRUM_LOGO_URL',
    'https://ecoservantsproject.org/wp-content/uploads/2024/10/EcoServants-Project.webp'
);

/**
 * Activation hook – create local tables for Scrum data
 */
function es_scrum_activate()
{
    error_log('[EcoServants Scrum] Plugin activation started.');

    es_scrum_install_local_tables();

    update_option('es_scrum_db_version', ES_SCRUM_VERSION);

    error_log('[EcoServants Scrum] Plugin activation completed. DB Version: ' . ES_SCRUM_VERSION);
}
register_activation_hook(ES_SCRUM_PLUGIN_FILE, 'es_scrum_activate');

/**
 * Check for DB updates on plugin load
 */
function es_scrum_update_db_check()
{
    if (get_option('es_scrum_db_version') !== ES_SCRUM_VERSION) {
        error_log('[EcoServants Scrum] DB version mismatch. Running upgrade from ' . get_option('es_scrum_db_version') . ' to ' . ES_SCRUM_VERSION);
        es_scrum_install_local_tables();
        update_option('es_scrum_db_version', ES_SCRUM_VERSION);
    }
}
add_action('plugins_loaded', 'es_scrum_update_db_check');

/**
 * Install tables in the local WordPress database
 */
function es_scrum_install_local_tables()
{
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();

    $prefix = $wpdb->prefix . 'es_scrum_';
    $table_tasks = $prefix . 'tasks';
    $table_sprints = $prefix . 'sprints';
    $table_comments = $prefix . 'comments';
    $table_activity = $prefix . 'activity_log';

    $sql_tasks = "CREATE TABLE {$table_tasks} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        description LONGTEXT NULL,
        program_slug VARCHAR(100) NOT NULL,
        sprint_id BIGINT(20) UNSIGNED NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'backlog',
        priority VARCHAR(20) NOT NULL DEFAULT 'medium',
        type VARCHAR(20) NOT NULL DEFAULT 'task',
        reporter_id BIGINT(20) UNSIGNED NOT NULL,
        assignee_id BIGINT(20) UNSIGNED NULL,
        story_points INT(11) NULL,
        tags TEXT NULL,
        due_date DATETIME NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY program_slug (program_slug),
        KEY sprint_id (sprint_id),
        KEY assignee_id (assignee_id),
        KEY status (status)
    ) $charset_collate;";

    $sql_sprints = "CREATE TABLE {$table_sprints} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        program_slug VARCHAR(100) NOT NULL,
        start_date DATETIME NULL,
        end_date DATETIME NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'planned',
        goal TEXT NULL,
        created_by BIGINT(20) UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY program_slug (program_slug),
        KEY status (status)
    ) $charset_collate;";

    $sql_comments = "CREATE TABLE {$table_comments} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        task_id BIGINT(20) UNSIGNED NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        parent_id BIGINT(20) UNSIGNED NULL,
        body LONGTEXT NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY task_id (task_id),
        KEY user_id (user_id),
        KEY parent_id (parent_id)
    ) $charset_collate;";

    $sql_activity = "CREATE TABLE {$table_activity} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        task_id BIGINT(20) UNSIGNED NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        action VARCHAR(100) NOT NULL,
        from_value TEXT NULL,
        to_value TEXT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY task_id (task_id),
        KEY user_id (user_id),
        KEY action (action)
    ) $charset_collate;";

    error_log('[EcoServants Scrum] Running dbDelta...');

    dbDelta($sql_tasks);
    dbDelta($sql_sprints);
    dbDelta($sql_comments);
    dbDelta($sql_activity);

    error_log('[EcoServants Scrum] dbDelta complete.');
}

/**
 * Get options array for the plugin
 */
function es_scrum_get_options()
{
    $defaults = array(
        'db_mode' => 'local', // local or external
        'db_host' => '',
        'db_name' => '',
        'db_user' => '',
        'db_pass' => '',
        'db_prefix' => '',
    );

    $options = get_option('es_scrum_options', array());
    if (!is_array($options)) {
        $options = array();
    }

    return wp_parse_args($options, $defaults);
}

/**
 * Return wpdb instance for Scrum data.
 * If external mode is configured and valid, use external DB.
 * Otherwise fall back to local $wpdb.
 */
function es_scrum_db()
{
    static $db = null;

    if ($db instanceof wpdb) {
        return $db;
    }

    global $wpdb;
    $options = es_scrum_get_options();
    $mode = isset($options['db_mode']) ? $options['db_mode'] : 'local';

    if ($mode === 'external') {
        $host = trim($options['db_host']);
        $name = trim($options['db_name']);
        $user = trim($options['db_user']);
        $pass = $options['db_pass'];

        if ($host && $name && $user) {
            $external = new wpdb($user, $pass, $name, $host);

            if (empty($external->error)) {
                $db = $external;
                return $db;
            }
        }
    }

    $db = $wpdb;
    return $db;
}

/**
 * Get table prefix for Scrum tables
 */
function es_scrum_table_prefix()
{
    global $wpdb;

    $options = es_scrum_get_options();
    $mode = isset($options['db_mode']) ? $options['db_mode'] : 'local';

    if ($mode === 'external' && !empty($options['db_prefix'])) {
        return $options['db_prefix'];
    }

    // Default local prefix
    return $wpdb->prefix . 'es_scrum_';
}

/**
 * Get full table name for a logical slug
 *
 * @param string $slug tasks|sprints|comments|activity_log
 * @return string
 */
function es_scrum_table_name($slug)
{
    $prefix = es_scrum_table_prefix();

    switch ($slug) {
        case 'tasks':
            return $prefix . 'tasks';
        case 'sprints':
            return $prefix . 'sprints';
        case 'comments':
            return $prefix . 'comments';
        case 'activity_log':
            return $prefix . 'activity_log';
        default:
            return $prefix . $slug;
    }
}

/**
 * Admin menu – EcoServants Scrum Board and Settings
 */
function es_scrum_register_admin_menu()
{
    $capability = 'read'; // Any logged-in user can see board; control actions via REST

    // Top level menu
    add_menu_page(
        'EcoServants Scrum Board',
        'EcoServants Scrum',
        $capability,
        'es-scrum-board',
        'es_scrum_render_board_page',
        'dashicons-clipboard',
        56
    );

    // Subpage for settings (admins only)
    add_submenu_page(
        'es-scrum-board',
        'Scrum Board Settings',
        'Settings',
        'manage_options',
        'es-scrum-settings',
        'es_scrum_render_settings_page'
    );
}
add_action('admin_menu', 'es_scrum_register_admin_menu');

/**
 * Enqueue admin scripts/styles for the Scrum board page
 */
function es_scrum_admin_assets($hook)
{
    if ($hook !== 'toplevel_page_es-scrum-board') {
        return;
    }

    // Optionally enqueue a small CSS file here later.

    // Enqueue React app
    $asset_file_path = ES_SCRUM_PLUGIN_DIR . 'build/index.asset.php';
    if (file_exists($asset_file_path)) {
        $asset_file = include($asset_file_path);
        wp_enqueue_script(
            'es-scrum-app',
            ES_SCRUM_PLUGIN_URL . 'build/index.js',
            $asset_file['dependencies'],
            $asset_file['version'],
            true
        );
    } else {
        // Fallback for dev/manual setup
        wp_enqueue_script(
            'es-scrum-app',
            ES_SCRUM_PLUGIN_URL . 'build/index.js',
            array('wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch'),
            ES_SCRUM_VERSION,
            true
        );
    }

    // Localize REST info.
    wp_localize_script(
        'es-scrum-app',
        'ESScrumConfig',
        array(
            'restUrl' => esc_url_raw(rest_url('es-scrum/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
        )
    );
}
add_action('admin_enqueue_scripts', 'es_scrum_admin_assets');

/**
 * Render the main Scrum Board admin page
 */
function es_scrum_render_board_page()
{
    if (!current_user_can('read')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'es-scrum'));
    }

    ?>
    <div class="wrap es-scrum-board-wrap" style="max-width: 1200px;">
        <div style="display:flex; align-items:center; gap:16px; margin-bottom:20px;">
            <img src="<?php echo esc_url(ES_SCRUM_LOGO_URL); ?>" alt="EcoServants Logo"
                style="height:60px; width:auto; border-radius:4px; background:#fff; padding:4px; box-shadow:0 1px 3px rgba(0,0,0,.1);" />
            <div>
                <h1 style="margin-bottom:4px;">EcoServants Digital Scrum Board</h1>
                <p style="margin-top:0; color:#555;">
                    Centralized task and sprint management for EcoServants teams using WordPress users and program groups.
                </p>
            </div>
        </div>

        <div id="es-scrum-board-app"
            style="border:1px solid #ddd; background:#fff; padding:20px; border-radius:6px; min-height: 200px;">
            <p>
                Loading EcoServants Scrum Board…
            </p>
        </div>
    </div>
    <?php
}

/**
 * Register settings for the plugin
 */
function es_scrum_register_settings()
{
    register_setting(
        'es_scrum_settings_group',
        'es_scrum_options',
        array(
            'type' => 'array',
            'sanitize_callback' => 'es_scrum_sanitize_options',
            'default' => es_scrum_get_options(),
        )
    );

    add_settings_section(
        'es_scrum_db_section',
        'Database Configuration',
        'es_scrum_db_section_description',
        'es-scrum-settings'
    );

    add_settings_field(
        'es_scrum_db_mode',
        'Storage Mode',
        'es_scrum_field_db_mode',
        'es-scrum-settings',
        'es_scrum_db_section'
    );

    add_settings_field(
        'es_scrum_db_host',
        'External DB Host',
        'es_scrum_field_db_host',
        'es-scrum-settings',
        'es_scrum_db_section'
    );

    add_settings_field(
        'es_scrum_db_name',
        'External DB Name',
        'es_scrum_field_db_name',
        'es-scrum-settings',
        'es_scrum_db_section'
    );

    add_settings_field(
        'es_scrum_db_user',
        'External DB User',
        'es_scrum_field_db_user',
        'es-scrum-settings',
        'es_scrum_db_section'
    );

    add_settings_field(
        'es_scrum_db_pass',
        'External DB Password',
        'es_scrum_field_db_pass',
        'es-scrum-settings',
        'es_scrum_db_section'
    );

    add_settings_field(
        'es_scrum_db_prefix',
        'External DB Table Prefix',
        'es_scrum_field_db_prefix',
        'es-scrum-settings',
        'es_scrum_db_section'
    );
}
add_action('admin_init', 'es_scrum_register_settings');

/**
 * Settings section description
 */
function es_scrum_db_section_description()
{
    ?>
    <p>
        Choose whether to store Scrum data in the local WordPress database or an external MySQL database.
        User accounts and program groups stay in WordPress. Only Scrum tables move to the external database.
    </p>
    <?php
}

/**
 * Sanitize options
 */
function es_scrum_sanitize_options($input)
{
    $output = es_scrum_get_options();
    $allowed = array('local', 'external');

    if (isset($input['db_mode']) && in_array($input['db_mode'], $allowed, true)) {
        $output['db_mode'] = $input['db_mode'];
    }

    if (isset($input['db_host'])) {
        $output['db_host'] = sanitize_text_field($input['db_host']);
    }

    if (isset($input['db_name'])) {
        $output['db_name'] = sanitize_text_field($input['db_name']);
    }

    if (isset($input['db_user'])) {
        $output['db_user'] = sanitize_text_field($input['db_user']);
    }

    if (isset($input['db_pass'])) {
        // Store as is; do not trim to avoid accidental space removal if deliberate
        $output['db_pass'] = wp_unslash($input['db_pass']);
    }

    if (isset($input['db_prefix'])) {
        $output['db_prefix'] = sanitize_text_field($input['db_prefix']);
    }

    return $output;
}

/**
 * Field renderers
 */
function es_scrum_field_db_mode()
{
    $options = es_scrum_get_options();
    ?>
    <label>
        <input type="radio" name="es_scrum_options[db_mode]" value="local" <?php checked($options['db_mode'], 'local'); ?> />
        Local WordPress database (default)
    </label>
    <br />
    <label>
        <input type="radio" name="es_scrum_options[db_mode]" value="external" <?php checked($options['db_mode'], 'external'); ?> />
        External MySQL database for Scrum data
    </label>
    <p class="description">
        If you choose external, fill in the connection details below. User data remains in your WordPress database.
    </p>
    <?php
}

function es_scrum_field_db_host()
{
    $options = es_scrum_get_options();
    ?>
    <input type="text" class="regular-text" name="es_scrum_options[db_host]"
        value="<?php echo esc_attr($options['db_host']); ?>" placeholder="127.0.0.1 or mysql.example.com" />
    <?php
}

function es_scrum_field_db_name()
{
    $options = es_scrum_get_options();
    ?>
    <input type="text" class="regular-text" name="es_scrum_options[db_name]"
        value="<?php echo esc_attr($options['db_name']); ?>" placeholder="ecoservants_scrum_db" />
    <?php
}

function es_scrum_field_db_user()
{
    $options = es_scrum_get_options();
    ?>
    <input type="text" class="regular-text" name="es_scrum_options[db_user]"
        value="<?php echo esc_attr($options['db_user']); ?>" placeholder="scrum_user" />
    <?php
}

function es_scrum_field_db_pass()
{
    $options = es_scrum_get_options();
    ?>
    <input type="password" class="regular-text" name="es_scrum_options[db_pass]"
        value="<?php echo esc_attr($options['db_pass']); ?>" autocomplete="off" />
    <p class="description">
        Saved in the WordPress options table. For higher security you can move credentials to wp-config.php and leave this
        blank.
    </p>
    <?php
}

function es_scrum_field_db_prefix()
{
    $options = es_scrum_get_options();
    ?>
    <input type="text" class="regular-text" name="es_scrum_options[db_prefix]"
        value="<?php echo esc_attr($options['db_prefix']); ?>" placeholder="es_scrum_" />
    <p class="description">
        Prefix for Scrum tables in the external database, for example <code>es_scrum_</code> leading to
        <code>es_scrum_tasks</code>, <code>es_scrum_sprints</code> and so on.
    </p>
    <?php
}

/**
 * Render settings page
 */
function es_scrum_render_settings_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'es-scrum'));
    }

    ?>
    <div class="wrap">
        <h1>EcoServants Scrum Board Settings</h1>

        <form method="post" action="options.php">
            <?php
            settings_fields('es_scrum_settings_group');
            do_settings_sections('es-scrum-settings');
            submit_button();
            ?>
        </form>

        <hr />

        <h2>Where your data lives</h2>
        <p>
            EcoServants users and program assignments (using the <code>es_program_groups</code> user meta)
            always live in your main WordPress database.
        </p>
        <p>
            Scrum data (tasks, sprints, comments, activity log) is stored in dedicated tables such as
            <code>wp_es_scrum_tasks</code> when using the local database, or in the external database you configure here.
        </p>
    </div>
    <?php
}

/**
 * Register REST API routes for the plugin
 */
function es_scrum_register_rest_routes()
{
    // 1. Task API: Use dedicated class
    require_once plugin_dir_path(__FILE__) . 'includes/api/class-scrum-board-api.php';
    $task_api = new EcoServants_Scrum_Board_API();
    $task_api->register_routes();

    // 2. Sprint API: Use dedicated class
    require_once plugin_dir_path(__FILE__) . 'includes/api/class-sprint-api.php';
    $sprint_api = new EcoServants_Sprint_API();
    $sprint_api->register_routes();

    // Ping route
    register_rest_route(
        'es-scrum/v1',
        '/ping',
        array(
            'methods'             => 'GET',
            'callback'            => 'es_scrum_rest_ping',
            'permission_callback' => '__return_true', // Public ping for connectivity check
        )
    );

    // 3. Comment API: Use dedicated class
    require_once plugin_dir_path(__FILE__) . 'includes/api/class-comment-api.php';
    $comment_api = new EcoServants_Comment_API();
    $comment_api->register_routes();

    // Activity Log Collection
    register_rest_route(
        'es-scrum/v1',
        '/activity',
        array(
            array(
                'methods'             => 'GET',
                'callback'            => 'es_scrum_rest_get_activity',
                'permission_callback' => 'es_scrum_rest_permission_check',
            ),
        )
    );
}
add_action('rest_api_init', 'es_scrum_register_rest_routes');

/**
 * Permission check for Scrum API
 */
function es_scrum_rest_permission_check()
{
    return current_user_can('read'); // Adjust capability as needed
}

/**
 * REST callback – simple ping
 */
function es_scrum_rest_ping(WP_REST_Request $request)
{
    return array(
        'status' => 'ok',
        'message' => 'EcoServants Scrum API is online',
        'version' => ES_SCRUM_VERSION,
    );
}





/**
 * REST callback – Get Activity Log
 */
function es_scrum_rest_get_activity(WP_REST_Request $request)
{
    $db = es_scrum_db();
    $table = es_scrum_table_name('activity_log');

    $task_id = $request->get_param('task_id');
    if (!$task_id) {
        return new WP_Error('missing_param', 'Task ID is required', array('status' => 400));
    }

    $sql = $db->prepare("SELECT * FROM {$table} WHERE task_id = %d ORDER BY created_at DESC", $task_id);
    $activity = $db->get_results($sql);

    return rest_ensure_response($activity);
}

/**
 * Handles comment mentions by sending email notifications.
 *
 * @param int   $comment_id         The ID of the comment where mentions occurred.
 * @param array $mentioned_user_ids An array of user IDs who were mentioned.
 */
function es_scrum_handle_comment_mentions( $comment_id, $mentioned_user_ids ) {
    if ( empty( $mentioned_user_ids ) ) {
        return;
    }

    $comment = es_scrum_db()->get_row(
        es_scrum_db()->prepare(
            "SELECT c.body, c.task_id, u.display_name as commenter_name FROM " . es_scrum_table_name('comments') . " c LEFT JOIN {$GLOBALS['wpdb']->users} u ON c.user_id = u.ID WHERE c.id = %d",
            $comment_id
        )
    );

    if ( ! $comment ) {
        error_log( "EcoServants Scrum: Comment not found for mention notification (ID: $comment_id)" );
        return;
    }

    $task = es_scrum_db()->get_row(
        es_scrum_db()->prepare(
            "SELECT title FROM " . es_scrum_table_name('tasks') . " WHERE id = %d",
            $comment->task_id
        )
    );

    $task_title = $task ? $task->title : 'Unknown Task';

    foreach ( $mentioned_user_ids as $user_id ) {
        $user_info = get_userdata( $user_id );
        if ( ! $user_info ) {
            error_log( "EcoServants Scrum: Mentioned user not found (ID: $user_id)" );
            continue;
        }

        $to = $user_info->user_email;
        $subject = sprintf( '[EcoServants Scrum] You were mentioned in a comment on task "%s"', $task_title );
        $body_text = sprintf(
            'Hi %1$s,

            %2$s mentioned you in a comment on task "%3$s".

            Comment: "%4$s"

            You can view the task here: %5$s

            Best regards,
            EcoServants Scrum Board',
            $user_info->display_name,
            $comment->commenter_name,
            $task_title,
            $comment->body,
            admin_url( 'admin.php?page=es-scrum-board' ) // Link to the scrum board
        );

        $headers = array('Content-Type: text/plain; charset=UTF-8');

        // Send email
        $sent = wp_mail( $to, $subject, $body_text, $headers );

        if ( ! $sent ) {
            error_log( "EcoServants Scrum: Failed to send mention email to {$user_info->user_email} for comment $comment_id." );
        }
    }
}
add_action( 'es_scrum_comment_mentions', 'es_scrum_handle_comment_mentions', 10, 2 );

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

/* ------------------------------------------------------------------ */
/*  Encryption helpers – AES-256-CBC using wp_salt('auth')             */
/* ------------------------------------------------------------------ */

/**
 * Encrypt a plaintext string for safe storage in wp_options.
 *
 * @param  string $plaintext
 * @return string  Base-64 encoded ciphertext (IV prepended).
 */
function es_scrum_encrypt( $plaintext ) {
    if ( empty( $plaintext ) ) {
        return '';
    }
    $method = 'aes-256-cbc';
    $key    = hash( 'sha256', wp_salt( 'auth' ), true );
    $iv     = openssl_random_pseudo_bytes( openssl_cipher_iv_length( $method ) );
    $cipher = openssl_encrypt( $plaintext, $method, $key, OPENSSL_RAW_DATA, $iv );
    return base64_encode( $iv . $cipher );
}

/**
 * Decrypt a ciphertext produced by es_scrum_encrypt().
 *
 * @param  string $ciphertext  Base-64 encoded.
 * @return string  Plaintext or empty string on failure.
 */
function es_scrum_decrypt( $ciphertext ) {
    if ( empty( $ciphertext ) ) {
        return '';
    }
    $method    = 'aes-256-cbc';
    $key       = hash( 'sha256', wp_salt( 'auth' ), true );
    $data      = base64_decode( $ciphertext, true );
    if ( false === $data ) {
        return $ciphertext; // Legacy plaintext – not yet encrypted.
    }
    $iv_length = openssl_cipher_iv_length( $method );
    if ( strlen( $data ) <= $iv_length ) {
        return $ciphertext; // Too short to be encrypted – treat as plaintext.
    }
    $iv        = substr( $data, 0, $iv_length );
    $cipher    = substr( $data, $iv_length );
    $decrypted = openssl_decrypt( $cipher, $method, $key, OPENSSL_RAW_DATA, $iv );
    return ( false === $decrypted ) ? $ciphertext : $decrypted;
}

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
 * Returns the CREATE TABLE SQL strings for all Scrum tables.
 *
 * Single source of truth used by both the local and external table installers.
 *
 * @param  string   $prefix   Table prefix, e.g. "wp_es_scrum_"
 * @param  string   $charset  Charset/collation string from wpdb.
 * @return string[]
 */
function es_scrum_get_table_schemas( $prefix, $charset ) {
    $table_tasks    = $prefix . 'tasks';
    $table_sprints  = $prefix . 'sprints';
    $table_comments = $prefix . 'comments';
    $table_activity = $prefix . 'activity_log';
    $table_configs  = $prefix . 'board_configs';

    return [
        "CREATE TABLE {$table_tasks} (
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
            KEY status (status),
            KEY program_status (program_slug, status),
            KEY assignee_status (assignee_id, status)
        ) {$charset};",

        "CREATE TABLE {$table_sprints} (
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
        ) {$charset};",

        "CREATE TABLE {$table_comments} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            task_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            parent_id BIGINT(20) UNSIGNED NULL,
            body LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY task_id (task_id),
            KEY user_id (user_id),
            KEY parent_id (parent_id)
        ) {$charset};",

        "CREATE TABLE {$table_activity} (
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
            KEY action (action),
            KEY task_created (task_id, created_at)
        ) {$charset};",

        "CREATE TABLE {$table_configs} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            program_slug VARCHAR(100) NOT NULL,
            config_json LONGTEXT NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY program_slug (program_slug)
        ) {$charset};",
    ];
}

/**
 * Install tables in the local WordPress database.
 */
function es_scrum_install_local_tables() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $prefix  = $wpdb->prefix . 'es_scrum_';
    $charset = $wpdb->get_charset_collate();

    error_log( '[EcoServants Scrum] Running dbDelta for local tables...' );
    foreach ( es_scrum_get_table_schemas( $prefix, $charset ) as $sql ) {
        dbDelta( $sql );
    }
    error_log( '[EcoServants Scrum] dbDelta complete.' );
}

/**
 * Get options array for the plugin.
 *
 * wp-config.php constants override stored values when defined:
 *   ES_SCRUM_DB_HOST, ES_SCRUM_DB_NAME, ES_SCRUM_DB_USER,
 *   ES_SCRUM_DB_PASS, ES_SCRUM_DB_PREFIX
 */
function es_scrum_get_options()
{
    $defaults = array(
        'db_mode'   => 'local', // local or external
        'db_host'   => '',
        'db_name'   => '',
        'db_user'   => '',
        'db_pass'   => '',
        'db_prefix' => '',
    );

    $options = get_option('es_scrum_options', array());
    if (!is_array($options)) {
        $options = array();
    }

    $options = wp_parse_args($options, $defaults);

    // Decrypt password from DB storage.
    if ( ! empty( $options['db_pass'] ) ) {
        $options['db_pass'] = es_scrum_decrypt( $options['db_pass'] );
    }

    // wp-config.php constants take precedence.
    if ( defined( 'ES_SCRUM_DB_HOST' ) )   { $options['db_host']   = ES_SCRUM_DB_HOST;   }
    if ( defined( 'ES_SCRUM_DB_NAME' ) )   { $options['db_name']   = ES_SCRUM_DB_NAME;   }
    if ( defined( 'ES_SCRUM_DB_USER' ) )   { $options['db_user']   = ES_SCRUM_DB_USER;   }
    if ( defined( 'ES_SCRUM_DB_PASS' ) )   { $options['db_pass']   = ES_SCRUM_DB_PASS;   }
    if ( defined( 'ES_SCRUM_DB_PREFIX' ) ) { $options['db_prefix'] = ES_SCRUM_DB_PREFIX; }

    return $options;
}

/**
 * Return wpdb instance for Scrum data.
 *
 * If external mode is configured and the connection succeeds, the
 * external wpdb instance is returned.  On failure the plugin falls
 * back to the local $wpdb and records the event so an admin notice
 * can surface it.
 */
function es_scrum_db()
{
    static $db = null;

    if ( $db instanceof wpdb ) {
        return $db;
    }

    global $wpdb;
    $options = es_scrum_get_options();
    $mode    = isset( $options['db_mode'] ) ? $options['db_mode'] : 'local';

    if ( $mode === 'external' ) {
        $host = trim( $options['db_host'] );
        $name = trim( $options['db_name'] );
        $user = trim( $options['db_user'] );
        $pass = $options['db_pass'];

        if ( $host && $name && $user ) {
            $external = @new wpdb( $user, $pass, $name, $host );

            if ( empty( $external->error ) ) {
                error_log( '[EcoServants Scrum] External DB connection established to ' . $host . '/' . $name );
                // Clear any previous fallback notice.
                delete_transient( 'es_scrum_db_fallback' );
                $db = $external;
                return $db;
            }

            // Connection failed — log and record for admin notice.
            $err_msg = is_string( $external->error ) ? $external->error : 'Unknown error';
            error_log( '[EcoServants Scrum] External DB connection FAILED (' . $host . '/' . $name . '): ' . $err_msg . '. Falling back to local DB.' );
            set_transient( 'es_scrum_db_fallback', $err_msg, HOUR_IN_SECONDS );
        } else {
            error_log( '[EcoServants Scrum] External mode selected but credentials are incomplete. Falling back to local DB.' );
            set_transient( 'es_scrum_db_fallback', 'Incomplete external DB credentials.', HOUR_IN_SECONDS );
        }
    }

    $db = $wpdb;
    return $db;
}

/**
 * Admin notice when external DB falls back to local.
 */
function es_scrum_admin_fallback_notice() {
    $fallback = get_transient( 'es_scrum_db_fallback' );
    if ( $fallback && current_user_can( 'manage_options' ) ) {
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>EcoServants Scrum Board:</strong> External database connection failed — using local database. ';
        echo 'Error: <code>' . esc_html( $fallback ) . '</code></p>';
        echo '</div>';
    }
}
add_action( 'admin_notices', 'es_scrum_admin_fallback_notice' );

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
        case 'board_configs':
            return $prefix . 'board_configs';
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
 * Sanitize options — encrypts password before storage.
 */
function es_scrum_sanitize_options($input)
{
    $output  = es_scrum_get_options();
    $allowed = array('local', 'external');
    $old_mode = $output['db_mode'];

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
        // Encrypt before saving to wp_options.
        $output['db_pass'] = es_scrum_encrypt( wp_unslash($input['db_pass']) );
    }

    if (isset($input['db_prefix'])) {
        $output['db_prefix'] = sanitize_text_field($input['db_prefix']);
    }

    // When switching to external mode, attempt to install tables on the remote DB.
    if ( $output['db_mode'] === 'external' && $old_mode !== 'external' ) {
        // Schedule table install for after the option is saved (next page load).
        set_transient( 'es_scrum_install_external', true, 60 );
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
        <input type="radio" name="es_scrum_options[db_mode]" value="local" <?php checked($options['db_mode'], 'local'); ?>
        />
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
 * Render settings page — enhanced with Test Connection and show/hide UX.
 */
function es_scrum_render_settings_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'es-scrum'));
    }

    $options = es_scrum_get_options();
    $is_external = ( $options['db_mode'] === 'external' );
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

        <!-- Test Connection -->
        <h2>Test External Connection</h2>
        <p>
            <button type="button" id="es-scrum-test-connection" class="button button-secondary"
                <?php echo $is_external ? '' : 'disabled'; ?>>
                🔌 Test Connection
            </button>
            <span id="es-scrum-test-result" style="margin-left:12px; font-weight:600;"></span>
        </p>

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
        <p class="description">
            <strong>Tip:</strong> For production deployments you can define credentials in <code>wp-config.php</code>
            using constants <code>ES_SCRUM_DB_HOST</code>, <code>ES_SCRUM_DB_NAME</code>, <code>ES_SCRUM_DB_USER</code>,
            <code>ES_SCRUM_DB_PASS</code>, and <code>ES_SCRUM_DB_PREFIX</code>. These will override the values above.
        </p>
    </div>

    <script>
    (function(){
        /* Show/hide external fields based on radio selection */
        var radios = document.querySelectorAll('input[name="es_scrum_options[db_mode]"]');
        var externalRows = [];
        ['db_host','db_name','db_user','db_pass','db_prefix'].forEach(function(id){
            var input = document.querySelector('[name="es_scrum_options[' + id + ']"]');
            if(input) externalRows.push(input.closest('tr'));
        });
        function toggleRows(){
            var isExternal = document.querySelector('input[name="es_scrum_options[db_mode]"]:checked').value === 'external';
            externalRows.forEach(function(row){ if(row) row.style.display = isExternal ? '' : 'none'; });
            var btn = document.getElementById('es-scrum-test-connection');
            if(btn) btn.disabled = !isExternal;
        }
        radios.forEach(function(r){ r.addEventListener('change', toggleRows); });
        toggleRows();

        /* Test Connection AJAX */
        var btn = document.getElementById('es-scrum-test-connection');
        var result = document.getElementById('es-scrum-test-result');
        if(btn){
            btn.addEventListener('click', function(){
                result.textContent = 'Testing…';
                result.style.color = '#666';
                btn.disabled = true;

                var data = new FormData();
                data.append('action', 'es_scrum_test_db_connection');
                data.append('_ajax_nonce', '<?php echo wp_create_nonce("es_scrum_test_conn"); ?>');

                fetch(ajaxurl, { method: 'POST', body: data, credentials: 'same-origin' })
                    .then(function(r){ return r.json(); })
                    .then(function(json){
                        if(json.success){
                            result.textContent = '✅ ' + json.data;
                            result.style.color = '#0a7b24';
                        } else {
                            result.textContent = '❌ ' + json.data;
                            result.style.color = '#b32d2e';
                        }
                        btn.disabled = false;
                    })
                    .catch(function(){
                        result.textContent = '❌ Request failed.';
                        result.style.color = '#b32d2e';
                        btn.disabled = false;
                    });
            });
        }
    })();
    </script>
    <?php
}

/* ------------------------------------------------------------------ */
/*  AJAX: Test external DB connection                                  */
/* ------------------------------------------------------------------ */

/**
 * AJAX handler — test external DB connection with the saved credentials.
 */
function es_scrum_ajax_test_connection() {
    check_ajax_referer( 'es_scrum_test_conn' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permission denied.' );
    }

    $options = es_scrum_get_options();
    $host    = trim( $options['db_host'] );
    $name    = trim( $options['db_name'] );
    $user    = trim( $options['db_user'] );
    $pass    = $options['db_pass'];

    if ( ! $host || ! $name || ! $user ) {
        wp_send_json_error( 'Incomplete credentials. Please fill in host, database name, and user.' );
    }

    $test_db = @new wpdb( $user, $pass, $name, $host );

    if ( ! empty( $test_db->error ) ) {
        $err = is_string( $test_db->error ) ? $test_db->error : 'Unknown connection error.';
        error_log( '[EcoServants Scrum] Test connection FAILED: ' . $err );
        wp_send_json_error( $err );
    }

    // Quick liveness check.
    $result = $test_db->query( 'SELECT 1' );
    if ( false === $result ) {
        wp_send_json_error( 'Connected but query failed: ' . $test_db->last_error );
    }

    wp_send_json_success( 'Connection successful to ' . $host . '/' . $name );
}
add_action( 'wp_ajax_es_scrum_test_db_connection', 'es_scrum_ajax_test_connection' );

/* ------------------------------------------------------------------ */
/*  External table installation                                        */
/* ------------------------------------------------------------------ */

/**
 * Create Scrum tables on the external database using the same schema
 * as es_scrum_install_local_tables(), but targeting es_scrum_db().
 */
function es_scrum_install_external_tables() {
    $db = es_scrum_db();
    global $wpdb;
    if ( $db === $wpdb ) {
        error_log( '[EcoServants Scrum] Skipping external table install — not connected to external DB.' );
        return false;
    }

    $prefix  = es_scrum_table_prefix();
    $charset = $db->get_charset_collate();

    // NOTE: We intentionally use $db->query() with CREATE TABLE IF NOT EXISTS
    // instead of dbDelta(). WordPress dbDelta() internally uses the global $wpdb
    // for schema inspection, which would target the local WP database — not the
    // external connection. Direct queries via $db ensure tables are created on
    // the correct external database.
    $errors = 0;
    error_log( '[EcoServants Scrum] Creating external tables via direct queries...' );

    foreach ( es_scrum_get_table_schemas( $prefix, $charset ) as $sql ) {
        // Prepend IF NOT EXISTS for safe re-runs.
        $sql = str_replace( 'CREATE TABLE ', 'CREATE TABLE IF NOT EXISTS ', $sql );
        $result = $db->query( $sql );
        if ( false === $result ) {
            error_log( '[EcoServants Scrum] External table creation error: ' . $db->last_error );
            $errors++;
        }
    }

    if ( $errors === 0 ) {
        error_log( '[EcoServants Scrum] External tables created/verified successfully.' );
    }

    return ( $errors === 0 );
}

/**
 * On plugins_loaded, check if we need to install external tables
 * (triggered by mode switch via the transient flag).
 */
function es_scrum_maybe_install_external_tables() {
    if ( get_transient( 'es_scrum_install_external' ) ) {
        delete_transient( 'es_scrum_install_external' );
        es_scrum_install_external_tables();
    }
}
add_action( 'plugins_loaded', 'es_scrum_maybe_install_external_tables', 20 );

/**
 * Register REST API routes for the plugin
 */
function es_scrum_register_rest_routes()
{
    // Load shared response helper
    require_once plugin_dir_path(__FILE__) . 'includes/api/class-api-response.php';

    // Load security middleware
    require_once plugin_dir_path(__FILE__) . 'includes/api/class-api-security.php';

    // 1. Task API
    require_once plugin_dir_path(__FILE__) . 'includes/api/class-scrum-board-api.php';
    $task_api = new EcoServants_Scrum_Board_API();
    $task_api->register_routes();

    // 2. Sprint API
    require_once plugin_dir_path(__FILE__) . 'includes/api/class-sprint-api.php';
    $sprint_api = new EcoServants_Sprint_API();
    $sprint_api->register_routes();

    // 3. Board Config API
    if (file_exists(plugin_dir_path(__FILE__) . 'includes/api/class-board-config-api.php')) {
        require_once plugin_dir_path(__FILE__) . 'includes/api/class-board-config-api.php';
        $config_api = new EcoServants_Board_Config_API();
        $config_api->register_routes();
    }

    // 4. User Profile API
    if (file_exists(plugin_dir_path(__FILE__) . 'includes/api/class-user-profile-api.php')) {
        require_once plugin_dir_path(__FILE__) . 'includes/api/class-user-profile-api.php';
        $profile_api = new EcoServants_User_Profile_API();
        $profile_api->register_routes();
    }

    // 5. Comment API
    require_once plugin_dir_path(__FILE__) . 'includes/api/class-comment-api.php';
    $comment_api = new EcoServants_Comment_API();
    $comment_api->register_routes();

    // 6. Activity Log API (registers GET + POST /activity)
    require_once plugin_dir_path(__FILE__) . 'includes/api/class-activity-log-api.php';
    $activity_api = new EcoServants_Activity_Log_API();
    $activity_api->register_routes();

    // DC-11: Recommendations
    register_rest_route(
        'es-scrum/v1',
        '/recommendations',
        array(
            'methods' => 'GET',
            'callback' => 'es_scrum_rest_get_recommendations',
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        )
    );

    // DC-11: Claim Task
    register_rest_route(
        'es-scrum/v1',
        '/tasks/(?P<id>\d+)/claim',
        array(
            'methods' => 'POST',
            'callback' => 'es_scrum_rest_claim_task',
            'permission_callback' => function () {
                return current_user_can('read');
            },
        )
    );

    // NOTE: /activity routes (GET + POST) are registered by EcoServants_Activity_Log_API above.

    // Ping route (public)
    register_rest_route(
        'es-scrum/v1',
        '/ping',
        array(
            'methods'             => 'GET',
            'callback'            => 'es_scrum_rest_ping',
            'permission_callback' => '__return_true',
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
 * Log an activity entry for a task.
 *
 * @param int    $task_id    The task ID.
 * @param int    $user_id    The user performing the action.
 * @param string $action     The action name (e.g., 'created', 'status_change').
 * @param string $from_value Previous value (if applicable).
 * @param string $to_value   New value (if applicable).
 */
function es_scrum_log_activity( $task_id, $user_id, $action, $from_value = '', $to_value = '' ) {
    $db    = es_scrum_db();
    $table = es_scrum_table_name( 'activity_log' );

    $db->insert( $table, array(
        'task_id'    => absint( $task_id ),
        'user_id'    => absint( $user_id ),
        'action'     => sanitize_text_field( $action ),
        'from_value' => sanitize_text_field( $from_value ),
        'to_value'   => sanitize_text_field( $to_value ),
        'created_at' => current_time( 'mysql' ),
    ) );
}

/**
 * Handles comment mentions by sending email notifications.
 *
 * @param int   $comment_id         The ID of the comment where mentions occurred.
 * @param array $mentioned_user_ids An array of user IDs who were mentioned.
 */
function es_scrum_handle_comment_mentions($comment_id, $mentioned_user_ids)
{
    if (empty($mentioned_user_ids)) {
        return;
    }

    $comment = es_scrum_db()->get_row(
        es_scrum_db()->prepare(
            "SELECT c.body, c.task_id, u.display_name as commenter_name FROM " . es_scrum_table_name('comments') . " c LEFT JOIN {$GLOBALS['wpdb']->users} u ON c.user_id = u.ID WHERE c.id = %d",
            $comment_id
        )
    );

    if (!$comment) {
        error_log("EcoServants Scrum: Comment not found for mention notification (ID: $comment_id)");
        return;
    }

    $task = es_scrum_db()->get_row(
        es_scrum_db()->prepare(
            "SELECT title FROM " . es_scrum_table_name('tasks') . " WHERE id = %d",
            $comment->task_id
        )
    );

    $task_title = $task ? $task->title : 'Unknown Task';

    foreach ($mentioned_user_ids as $user_id) {
        $user_info = get_userdata($user_id);
        if (!$user_info) {
            error_log("EcoServants Scrum: Mentioned user not found (ID: $user_id)");
            continue;
        }

        $to = $user_info->user_email;
        $subject = sprintf('[EcoServants Scrum] You were mentioned in a comment on task "%s"', $task_title);
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
            admin_url('admin.php?page=es-scrum-board') // Link to the scrum board
        );

        $headers = array('Content-Type: text/plain; charset=UTF-8');

        // Send email
        $sent = wp_mail($to, $subject, $body_text, $headers);

        if (!$sent) {
            error_log("EcoServants Scrum: Failed to send mention email to {$user_info->user_email} for comment $comment_id.");
        }
    }
}
add_action('es_scrum_comment_mentions', 'es_scrum_handle_comment_mentions', 10, 2);

/**
 * REST callback: DC-11 Get recommended tasks
 */
function es_scrum_rest_get_recommendations(WP_REST_Request $request)
{
    $user_id = get_current_user_id();
    $group = es_scrum_get_user_program_group($user_id);

    if (!$group) {
        return array(); // No group, no recommendations
    }

    $db = es_scrum_db();
    $table_tasks = es_scrum_table_name('tasks');

    // Recommend tasks in 'backlog' for this program group, not yet assigned
    $sql = $db->prepare(
        "SELECT * FROM {$table_tasks}
         WHERE program_slug = %s
         AND status = 'backlog'
         AND assignee_id IS NULL
         ORDER BY priority DESC, created_at ASC
         LIMIT 5",
        $group
    );

    $tasks = $db->get_results($sql);

    return $tasks;
}

/**
 * REST callback: DC-11 Claim a task
 */
function es_scrum_rest_claim_task(WP_REST_Request $request)
{
    $task_id = $request->get_param('id');
    $user_id = get_current_user_id();

    // Verify task exists and is claimable
    $db = es_scrum_db();
    $table_tasks = es_scrum_table_name('tasks');

    $task = $db->get_row($db->prepare("SELECT * FROM {$table_tasks} WHERE id = %d", $task_id));

    if (!$task) {
        return new WP_Error('not_found', 'Task not found', array('status' => 404));
    }

    if (!empty($task->assignee_id)) {
        return new WP_Error('already_assigned', 'Task is already assigned', array('status' => 400));
    }

    // Update task
    $updated = $db->update(
        $table_tasks,
        array(
            'assignee_id' => $user_id,
            'status' => 'in_progress', // Move to In Progress automatically
            'updated_at' => current_time('mysql'),
        ),
        array('id' => $task_id),
        array('%d', '%s', '%s'),
        array('%d')
    );

    if (false === $updated) {
        return new WP_Error('db_error', 'Could not update task', array('status' => 500));
    }

    // Log activity
    es_scrum_log_activity($task_id, $user_id, 'claimed', 'backlog', 'in_progress');

    return array(
        'success' => true,
        'message' => 'Task claimed successfully',
        'task_id' => $task_id,
    );
}

/**
 * DC-11: Cron job for daily automated tasks
 */
// Schedule cron on activation if not exists
function es_scrum_schedule_cron()
{
    if (!wp_next_scheduled('es_scrum_daily_event')) {
        wp_schedule_event(time(), 'daily', 'es_scrum_daily_event');
    }
}
register_activation_hook(ES_SCRUM_PLUGIN_FILE, 'es_scrum_schedule_cron');

// Hook into the daily event
add_action('es_scrum_daily_event', 'es_scrum_run_daily_jobs');

function es_scrum_run_daily_jobs()
{
    es_scrum_detect_stuck_tasks();
    es_scrum_send_daily_digest();
}

/**
 * Identify tasks in 'in_progress' with no updates for > 3 days
 */
function es_scrum_detect_stuck_tasks()
{
    $db = es_scrum_db();
    $table_tasks = es_scrum_table_name('tasks');

    // 3 days ago
    $threshold = date('Y-m-d H:i:s', strtotime('-3 days'));

    // Find tasks
    $sql = $db->prepare(
        "SELECT id, tags FROM {$table_tasks}
         WHERE status = 'in_progress'
         AND updated_at < %s",
        $threshold
    );

    $stuck_tasks = $db->get_results($sql);

    foreach ($stuck_tasks as $task) {
        $tags = $task->tags ? explode(',', $task->tags) : array();
        if (!in_array('Stuck', $tags)) {
            $tags[] = 'Stuck';
            $new_tags = implode(',', $tags);

            $db->update(
                $table_tasks,
                array('tags' => $new_tags),
                array('id' => $task->id),
                array('%s'),
                array('%d')
            );
        }
    }
}

/**
 * Send daily digest to admins/captains
 */
function es_scrum_send_daily_digest()
{
    // Count stuck tasks
    $db = es_scrum_db();
    $table_tasks = es_scrum_table_name('tasks');

    $stuck_count = $db->get_var("SELECT COUNT(*) FROM {$table_tasks} WHERE tags LIKE '%Stuck%'");

    if ($stuck_count > 0) {
        $to = get_option('admin_email'); // Simple start
        $subject = 'Daily Scrum Digest: Stuck Tasks Alert';
        $message = "There are currently {$stuck_count} stuck tasks on the board that require attention.\n\nPlease log in to review.";

        wp_mail($to, $subject, $message);
    }
}
/**
 * REST callback – Get Activity Log (Paginated)
 */
function es_scrum_rest_get_activity(WP_REST_Request $request)
{
    $db = es_scrum_db();
    $table = es_scrum_table_name('activity_log');

    $task_id = $request->get_param('task_id');
    if (!$task_id) {
        return new WP_Error('missing_param', 'Task ID is required', array('status' => 400));
    }

    $raw_page = $request->get_param('page');
    $page = max(1, (int) $raw_page);
    $per_page = $request->get_param('per_page') ? absint($request->get_param('per_page')) : 20;
    $offset = ($page - 1) * $per_page;

    $sql = $db->prepare("SELECT * FROM {$table} WHERE task_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d", $task_id, $per_page, $offset);
    $activity = $db->get_results($sql);

    // Get total count for headers
    $total = $db->get_var($db->prepare("SELECT COUNT(*) FROM {$table} WHERE task_id = %d", $task_id));
    $max_pages = ceil($total / $per_page);

    $response = rest_ensure_response($activity);
    $response->header('X-WP-Total', (int) $total);
    $response->header('X-WP-TotalPages', (int) $max_pages);

    return $response;
}

/**
 * Get the program group slug associated with a user.
 * 
 * @param int $user_id User ID
 * @return string|null Slug of program group, or null if none.
 */
function es_scrum_get_user_program_group($user_id)
{
    $groups = get_user_meta($user_id, 'es_program_groups', true);
    if (is_array($groups) && !empty($groups)) {
        return $groups[0];
    }
    return $groups;
}

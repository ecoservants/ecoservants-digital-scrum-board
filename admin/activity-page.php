<?php
if ( ! defined('ABSPATH') ) exit;

add_action('admin_menu', function () {
    add_submenu_page(
        'es-scrum-board',
        'Activity Logs',
        'Activity Logs',
        'manage_options',
        'es-scrum-activity',
        'es_scrum_render_activity_admin_page'
    );
});

function es_scrum_render_activity_admin_page() {
    if ( ! current_user_can('manage_options') ) {
        wp_die('Unauthorized');
    }
    echo '<div class="wrap"><h1>Activity Logs</h1><div id="es-scrum-activity-admin"></div></div>';
}

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'es-scrum_page_es-scrum-activity') return;

    wp_enqueue_script(
        'es-scrum-activity-admin',
        ES_SCRUM_PLUGIN_URL . 'public/js/activity-admin.js',
        ['wp-element','wp-components','wp-api-fetch'],
        ES_SCRUM_VERSION,
        true
    );

    wp_add_inline_script(
        'wp-api-fetch',
        'wp.apiFetch.use( wp.apiFetch.createNonceMiddleware("' . esc_js(wp_create_nonce('wp_rest')) . '") );',
        'after'
    );
});

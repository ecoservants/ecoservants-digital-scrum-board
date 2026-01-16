<?php
if ( ! defined('ABSPATH') ) exit;

function es_scrum_activity_install() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $db     = es_scrum_db();
    $table  = es_scrum_table_name('activity_log');
    $collate = $wpdb->get_charset_collate();

    // Professional schema: supports tasks, sprints, comments, attachments
    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        created_at DATETIME NOT NULL,
        user_id BIGINT UNSIGNED NULL,
        action_type VARCHAR(100) NOT NULL,
        entity_type VARCHAR(50) NOT NULL,
        entity_id BIGINT UNSIGNED NULL,
        program_slug VARCHAR(100) NULL,
        sprint_id BIGINT UNSIGNED NULL,
        task_id BIGINT UNSIGNED NULL,
        metadata LONGTEXT NULL,
        PRIMARY KEY (id),
        KEY idx_created_at (created_at),
        KEY idx_user_id (user_id),
        KEY idx_action_type (action_type),
        KEY idx_entity (entity_type, entity_id),
        KEY idx_program (program_slug),
        KEY idx_sprint (sprint_id),
        KEY idx_task (task_id)
    ) {$collate};";

    dbDelta($sql);

    // store schema version for upgrades
    update_option('es_scrum_activity_schema_version', '1.0');
}

function es_scrum_activity_maybe_upgrade() {
    $current = get_option('es_scrum_activity_schema_version', '');
    if ( $current !== '1.0' ) {
        es_scrum_activity_install();
    }
}

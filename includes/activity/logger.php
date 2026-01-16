<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Central activity logger.
 * Call this after successful DB changes (task update, sprint change, comment create, etc.)
 */
function es_scrum_log_activity( array $args ) {
    $db    = es_scrum_db();
    $table = es_scrum_table_name('activity_log');

    $user_id = isset($args['user_id']) ? absint($args['user_id'])
              : (is_user_logged_in() ? get_current_user_id() : 0);

    $row = array(
        'created_at'  => gmdate('Y-m-d H:i:s'),
        'user_id'     => $user_id ?: null,
        'action_type' => sanitize_text_field($args['action_type'] ?? ''),
        'entity_type' => sanitize_text_field($args['entity_type'] ?? ''),
        'entity_id'   => isset($args['entity_id']) ? absint($args['entity_id']) : null,
        'program_slug'=> isset($args['program_slug']) ? sanitize_text_field($args['program_slug']) : null,
        'sprint_id'   => isset($args['sprint_id']) ? absint($args['sprint_id']) : null,
        'task_id'     => isset($args['task_id']) ? absint($args['task_id']) : null,
        'metadata'    => isset($args['metadata']) ? wp_json_encode($args['metadata']) : null,
    );

    if ( empty($row['action_type']) || empty($row['entity_type']) ) {
        return new WP_Error('invalid_log', 'action_type and entity_type are required');
    }

    $ok = $db->insert($table, $row);
    if ( ! $ok ) {
        return new WP_Error('log_failed', 'Failed to insert log', array('db_error' => $db->last_error));
    }

    return (int) $db->insert_id;
}

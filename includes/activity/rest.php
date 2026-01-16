<?php
if ( ! defined('ABSPATH') ) exit;

add_action('rest_api_init', function () {
    register_rest_route('es-scrum/v1', '/activity', [
        'methods' => 'GET',
        'callback' => 'es_scrum_activity_rest_list',
        'permission_callback' => function () {
            return current_user_can('manage_options'); // admin-only
        },
    ]);

    register_rest_route('es-scrum/v1', '/activity/export', [
        'methods' => 'GET',
        'callback' => 'es_scrum_activity_rest_export_csv',
        'permission_callback' => function () {
            return current_user_can('manage_options'); // admin-only
        },
    ]);
});

function es_scrum_activity_rest_list( WP_REST_Request $req ) {
    $db    = es_scrum_db();
    $table = es_scrum_table_name('activity_log');

    $limit  = min(200, max(1, (int)($req['limit'] ?? 50)));
    $offset = max(0, (int)($req['offset'] ?? 0));

    $where = [];
    $params = [];

    if (!empty($req['action_type'])) { $where[]="action_type=%s"; $params[]=sanitize_text_field($req['action_type']); }
    if (!empty($req['entity_type'])) { $where[]="entity_type=%s"; $params[]=sanitize_text_field($req['entity_type']); }
    if (!empty($req['user_id']))     { $where[]="user_id=%d";     $params[]=absint($req['user_id']); }

    if (!empty($req['q'])) {
        $like = '%'.sanitize_text_field($req['q']).'%';
        $where[]="(action_type LIKE %s OR entity_type LIKE %s OR metadata LIKE %s)";
        $params[]=$like; $params[]=$like; $params[]=$like;
    }

    $sql_where = $where ? ("WHERE ".implode(" AND ", $where)) : "";

    $count_sql = "SELECT COUNT(*) FROM {$table} {$sql_where}";
    $count = (int)$db->get_var($db->prepare($count_sql, $params));

    $rows_sql = "SELECT * FROM {$table} {$sql_where} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";
    $rows_params = array_merge($params, [$limit, $offset]);
    $rows = $db->get_results($db->prepare($rows_sql, $rows_params), ARRAY_A);

    return [
        'count' => $count,
        'limit' => $limit,
        'offset'=> $offset,
        'rows'  => $rows
    ];
}

function es_scrum_activity_rest_export_csv( WP_REST_Request $req ) {
    // Reuse same filters by calling list logic but increase limit (or re-run query here)
    $req->set_param('limit', 5000);
    $req->set_param('offset', 0);
    $data = es_scrum_activity_rest_list($req);

    $fh = fopen('php://temp', 'w+');
    fputcsv($fh, ['id','created_at','user_id','action_type','entity_type','entity_id','program_slug','sprint_id','task_id','metadata']);

    foreach ($data['rows'] as $r) {
        fputcsv($fh, [
            $r['id'], $r['created_at'], $r['user_id'], $r['action_type'],
            $r['entity_type'], $r['entity_id'], $r['program_slug'], $r['sprint_id'],
            $r['task_id'], $r['metadata']
        ]);
    }
    rewind($fh);
    $csv = stream_get_contents($fh);
    fclose($fh);

    $resp = new WP_REST_Response($csv);
    $resp->header('Content-Type', 'text/csv; charset=utf-8');
    $resp->header('Content-Disposition', 'attachment; filename="activity_logs.csv"');
    return $resp;
}

<?php
/**
 * Activity log over audit_log (docs/ADMIN.md principle 2). Needs activity.view (owner, manager);
 * ?mine=1 is open to every role and is always limited to the signed-in admin's own rows.
 *
 * GET filters: page (1…), per (10–100, default 25), actor (admin id | "system"), entity (entity_type),
 *   type (exact action "order.cancel" or a prefix ending in "." such as "order."), from / to (YYYY-MM-DD),
 *   q (summary, action, entity id, actor name), format=csv (all matching rows, max 5000).
 * -> {rows:[{id, created_at, admin_id, actor_name, actor_role, action, entity_type, entity_id, summary, before, after, ip}],
 *     total, page, per, facets:{actors, entities, actions}}
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../../core/validation.php';

checkLogin(true);
json_headers();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Invalid request method', 405);
}

$mine = ($_GET['mine'] ?? '') === '1';
if (!$mine) {
    require_can('activity.view');
}
$me = (int) $_SESSION['admin_id'];

$where = [];
$params = [];
if ($mine) {
    $where[] = 'a.admin_id = ?';
    $params[] = $me;
} elseif (($actor = (string) ($_GET['actor'] ?? '')) !== '') {
    if ($actor === 'system') {
        $where[] = 'a.admin_id IS NULL';
    } elseif (ctype_digit($actor)) {
        $where[] = 'a.admin_id = ?';
        $params[] = (int) $actor;
    }
}
if (($entity = v_str($_GET['entity'] ?? '', 40)) !== null && $entity !== '') {
    $where[] = 'a.entity_type = ?';
    $params[] = $entity;
}
if (($type = v_str($_GET['type'] ?? '', 60)) !== null && $type !== '') {
    if (str_ends_with($type, '.')) {
        $where[] = 'a.action LIKE ?';
        $params[] = addcslashes($type, '%_\\') . '%';
    } else {
        $where[] = 'a.action = ?';
        $params[] = $type;
    }
}
$from = v_date($_GET['from'] ?? '');
$to = v_date($_GET['to'] ?? '');
if ($from === false || $to === false) {
    json_error('Dates must be YYYY-MM-DD', 422);
}
if ($from) {
    $where[] = 'a.created_at >= ?';
    $params[] = $from . ' 00:00:00';
}
if ($to) {
    $where[] = 'a.created_at < ?';
    $params[] = date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00';
}
if (($q = v_str($_GET['q'] ?? '', 100)) !== null && trim($q) !== '') {
    $like = '%' . addcslashes(trim($q), '%_\\') . '%';
    $where[] = '(a.summary LIKE ? OR a.action LIKE ? OR a.entity_id LIKE ? OR ad.name LIKE ?)';
    array_push($params, $like, $like, $like, $like);
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$from_sql = "FROM audit_log a LEFT JOIN admins ad ON ad.id = a.admin_id $whereSql";
$select = "SELECT a.id, a.created_at, a.admin_id, ad.name AS actor_name, ad.role AS actor_role, a.action, a.entity_type, a.entity_id,
        a.summary, a.`before`, a.`after`, a.ip $from_sql ORDER BY a.created_at DESC, a.id DESC";

$decode = static function (array $r): array {
    foreach (['before', 'after'] as $k) {
        $v = $r[$k] === null ? null : json_decode((string) $r[$k], true);
        $r[$k] = is_array($v) ? $v : ($r[$k] === null ? null : ['value' => $v]);
    }
    $r['id'] = (int) $r['id'];
    $r['admin_id'] = $r['admin_id'] === null ? null : (int) $r['admin_id'];
    return $r;
};

try {
    if (($_GET['format'] ?? '') === 'csv') {
        $st = $conn->prepare($select . ' LIMIT 5000');
        $st->execute($params);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="techzone-activity-' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        $cell = static function ($v): string {
            $s = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) ($v ?? '');
            return preg_match('/^[=+\-@\t\r]/', $s) ? "'" . $s : $s;   // no spreadsheet formulas
        };
        fputcsv($out, ['Time', 'Actor', 'Role', 'Action', 'Entity', 'Entity ID', 'Summary', 'Before', 'After', 'IP']);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $r = $decode($r);
            fputcsv($out, array_map($cell, [$r['created_at'], $r['actor_name'] ?? 'System', $r['actor_role'] ?? '', $r['action'], $r['entity_type'],
                $r['entity_id'], $r['summary'], $r['before'], $r['after'], $r['ip']]));
        }
        fclose($out);
        exit();
    }

    $per = v_int($_GET['per'] ?? 25, 10, 100) ?? 25;
    $page = v_int($_GET['page'] ?? 1, 1, 100000) ?? 1;
    $st = $conn->prepare("SELECT COUNT(*) $from_sql");
    $st->execute($params);
    $total = (int) $st->fetchColumn();
    $st = $conn->prepare($select . ' LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per));
    $st->execute($params);
    $rows = array_map($decode, $st->fetchAll(PDO::FETCH_ASSOC));

    $facets = ['actors' => [], 'entities' => [], 'actions' => []];
    if (!$mine) {
        $facets['actors'] = array_map(static fn($r) => ['id' => (int) $r['id'], 'name' => $r['name'], 'role' => $r['role']],
            $conn->query('SELECT id, name, role FROM admins ORDER BY name')->fetchAll(PDO::FETCH_ASSOC));
        $facets['entities'] = $conn->query('SELECT DISTINCT entity_type FROM audit_log ORDER BY entity_type')->fetchAll(PDO::FETCH_COLUMN);
        $facets['actions'] = $conn->query('SELECT DISTINCT action FROM audit_log ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
    }
    json_response(['success' => true, 'rows' => $rows, 'total' => $total, 'page' => $page, 'per' => $per, 'facets' => $facets]);
} catch (PDOException $e) {
    json_error(safe_error_message($e, 'Could not load the activity log.'), 500);
}

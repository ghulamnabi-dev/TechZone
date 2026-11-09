<?php
/**
 * Today's rates API (core/catalog.php). Read-only, public.
 *
 * GET ?view=board[&limit=8][&series=1]       → {success, updated_at, rows:[product + change + key_specs (+ series)]}
 * GET ?view=drops[&limit=8][&days=7]          → {success, rows:[…]}
 * GET ?view=series&id=55[&weeks=12]           → {success, id, points:[{date, price}], low, high, change}
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../includes/format.php';
require_once __DIR__ . '/../../core/validation.php';
require_once __DIR__ . '/../../core/catalog.php';

json_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Invalid request method', 405);
}

$view = v_enum($_GET['view'] ?? 'board', ['board', 'drops', 'series']);
$limit = v_int($_GET['limit'] ?? 8, 1, 50) ?? 8;

try {
    if ($view === null) {
        json_error('Unknown view', 422);
    }
    if ($view === 'series') {
        $id = v_id($_GET['id'] ?? 0);
        $weeks = v_int($_GET['weeks'] ?? 12, 1, 52) ?? 12;
        $points = $id ? price_series($conn, $id, $weeks) : [];
        if (!$points) {
            json_error('No rate history for that product', 404);
        }
        json_response(['success' => true, 'id' => $id, 'points' => $points] + price_range($points)
            + ['change' => price_change($conn, [$id])[$id] ?? null]);
    }

    $rows = $view === 'board' ? rate_board($conn, $limit) : price_drops($conn, $limit, v_int($_GET['days'] ?? 7, 1, 90) ?? 7);
    $series = !empty($_GET['series']) ? price_series_many($conn, array_column($rows, 'id')) : [];
    $out = [];
    foreach ($rows as $r) {
        $extra = $series ? ['series' => $series[(int) $r['id']] ?? []] : [];
        $out[] = product_json($r, $extra);
    }
    $updated = $conn->query('SELECT MAX(recorded_at) FROM price_history')->fetchColumn();
    json_response(['success' => true, 'view' => $view, 'updated_at' => $updated ?: null, 'rows' => $out]);
} catch (PDOException $e) {
    log_exception($e, 'rates');
    json_error('Rates are unavailable right now. Please try again.', 500);
}

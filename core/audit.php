<?php
/**
 * Audit trail (docs/ADMIN.md principle 2: every write is attributable).
 *
 * audit() appends one row to audit_log: who (session admin), what (action + entity), a one-line
 * human summary and optional before/after snapshots as JSON. Call it from inside the same
 * transaction as the write it describes so a rolled-back change leaves no trace.
 *
 * Conventions: action = "<entity>.<verb>" in lower case ("order.cancel", "product.update",
 * "setting.update", "inventory.adjust"); summary is past tense ("Cancelled order #12").
 */

require_once __DIR__ . '/errors.php';
require_once __DIR__ . '/http.php';

/**
 * Record a staff action in audit_log.
 *
 * Uses the global PDO connection ($conn). The actor is $_SESSION['admin_id'] (NULL for CLI /
 * system jobs) and the IP comes from client_ip(). Never throws for logging failures outside a
 * transaction; inside one, a failure propagates so the caller's write is rolled back with it.
 *
 * @param string          $action     e.g. "order.cancel"
 * @param string          $entityType e.g. "order", "product", "setting"
 * @param int|string      $entityId   primary key or setting key
 * @param string          $summary    one line, past tense, shown on the Activity page
 * @param array|null      $before     state before the change (only the fields that changed)
 * @param array|null      $after      state after the change
 */
function audit(string $action, string $entityType, int|string $entityId, string $summary, ?array $before = null, ?array $after = null): void
{
    $conn = $GLOBALS['conn'] ?? null;
    if (!$conn instanceof PDO) {
        return;
    }
    $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
    $ip = PHP_SAPI === 'cli' ? null : client_ip();
    $enc = static fn(?array $v): ?string => $v === null ? null : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

    try {
        $st = $conn->prepare('INSERT INTO audit_log (admin_id, action, entity_type, entity_id, summary, `before`, `after`, ip) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $st->execute([
            $adminId ?: null,
            substr($action, 0, 60),
            substr($entityType, 0, 40),
            substr((string) $entityId, 0, 64),
            mb_substr($summary, 0, 255),
            $enc($before),
            $enc($after),
            $ip,
        ]);
    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            throw $e;
        }
        log_exception($e, 'audit');
    }
}

/**
 * Keys whose values differ between two rows, as [before, after] pairs ready for audit().
 *
 * @param array    $old  row before
 * @param array    $new  row after (only these keys are compared)
 * @return array{0: array, 1: array}  [$before, $after] with only the changed keys
 */
function audit_diff(array $old, array $new): array
{
    $before = [];
    $after = [];
    foreach ($new as $k => $v) {
        $o = $old[$k] ?? null;
        if ((string) $o !== (string) $v) {
            $before[$k] = $o;
            $after[$k] = $v;
        }
    }
    return [$before, $after];
}

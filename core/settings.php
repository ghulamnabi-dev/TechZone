<?php
/**
 * Store settings: a small key/value store in the `settings` table (setting_key, value JSON).
 *
 * Known keys (seeded): store_name, store_address, store_phone, store_email, currency,
 * cod_enabled, jazzcash_enabled, low_stock_threshold. Values round-trip their PHP type
 * (bool/int/string/array) through JSON. All rows are loaded once per request.
 */

require_once __DIR__ . '/errors.php';
require_once __DIR__ . '/audit.php';

/**
 * Per-request cache of every setting (key => decoded value). Pass $reset to drop it.
 *
 * @return array<string, mixed>
 */
function settings_all(bool $reset = false): array
{
    static $cache = null;
    if ($reset) {
        $cache = null;
        return [];
    }
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    $conn = $GLOBALS['conn'] ?? null;
    if (!$conn instanceof PDO) {
        return $cache;
    }
    try {
        foreach ($conn->query('SELECT setting_key, value FROM settings')->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $decoded = json_decode((string) $r['value'], true);
            $cache[$r['setting_key']] = json_last_error() === JSON_ERROR_NONE ? $decoded : $r['value'];
        }
    } catch (PDOException $e) {
        log_exception($e, 'settings');   // table missing before migrate: everything falls back to defaults
    }
    return $cache;
}

/**
 * A setting's value, or $default when it is not set.
 *
 * @param string $key
 * @param mixed  $default
 * @return mixed
 */
function setting(string $key, $default = null)
{
    $all = settings_all();
    return array_key_exists($key, $all) ? $all[$key] : $default;
}

/**
 * Create or update a setting and write an audit_log row ("setting.update") with old/new value.
 *
 * @param string $key   max 64 chars
 * @param mixed  $value any JSON-encodable value
 * @param bool   $mask  record "••••1234" instead of the values in audit_log (merchant ids, keys)
 * @throws RuntimeException when no database connection is available
 */
function set_setting(string $key, $value, bool $mask = false): void
{
    $conn = $GLOBALS['conn'] ?? null;
    if (!$conn instanceof PDO) {
        throw new RuntimeException('No database connection');
    }
    $old = setting($key);
    $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
    $st = $conn->prepare('INSERT INTO settings (setting_key, value, updated_by) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE value = VALUES(value), updated_by = VALUES(updated_by)');
    $st->execute([substr($key, 0, 64), json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $adminId]);
    settings_all(true);
    if ($old !== $value) {
        $show = $mask ? 'setting_mask' : static fn($v) => $v;
        audit('setting.update', 'setting', $key, 'Changed ' . str_replace('_', ' ', $key), [$key => $show($old)], [$key => $show($value)]);
    }
}

/** "••••4821" for secrets shown back to the owner or written to the audit log; null/'' stay as they are. */
function setting_mask($value): ?string
{
    $v = (string) ($value ?? '');
    if ($v === '') {
        return $value === null ? null : '';
    }
    return '••••' . (strlen($v) > 4 ? substr($v, -4) : '');
}

/**
 * Store-details keys edited on Settings → Store, with their defaults (used when the row is missing).
 *
 * @return array<string, mixed>
 */
function store_setting_defaults(): array
{
    return [
        'store_name' => 'TechZone',
        'store_legal_name' => '',
        'store_phone' => '',
        'store_whatsapp' => '',
        'store_email' => '',
        'store_address' => '',
        'store_city' => 'Lahore',
        'store_hours' => '',
        'low_stock_threshold' => 5,
        'currency' => 'PKR',
        'store_notice' => 'Cash on delivery across Pakistan. Free delivery on orders over Rs 5,000.',
    ];
}

/**
 * Payment methods for a checkout of $total (Settings → Payments).
 *
 * cod_limit (Rs, 0 = no limit) caps cash on delivery; a method that is switched off is not offered,
 * one over the limit is offered disabled with a reason. Used by the storefront checkout for both the
 * radio list and the server-side check.
 *
 * @return array<string, array{label:string, enabled:bool, available:bool, reason:?string}>
 */
function payment_options(float $total): array
{
    $codLimit = (float) setting('cod_limit', 0);
    $cod = (bool) setting('cod_enabled', true);
    $jazz = (bool) setting('jazzcash_enabled', true);
    $overLimit = $codLimit > 0 && $total > $codLimit;
    return [
        'cash_on_delivery' => [
            'label' => 'Cash on delivery',
            'enabled' => $cod,
            'available' => $cod && !$overLimit,
            'reason' => $cod && $overLimit ? 'Available on orders up to Rs ' . number_format($codLimit) : null,
        ],
        'jazzcash' => [
            'label' => 'JazzCash',
            'enabled' => $jazz,
            'available' => $jazz,
            'reason' => null,
        ],
    ];
}

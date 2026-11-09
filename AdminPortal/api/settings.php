<?php
/**
 * Settings → Store and Settings → Payments (core/settings.php). Owner only (settings.write /
 * payments.write); every changed key is written with set_setting(), which audits it.
 *
 *   GET                      -> {store:{…}, payments:{cod_enabled, jazzcash_enabled, cod_limit, jazzcash_merchant_id (masked), has_merchant_id}}
 *   PUT {section:"store", values:{…}}     -> 422 {errors:{field: message}} or {changed:[keys]}
 *   PUT {section:"payments", values:{…}}  -> same; jazzcash_merchant_id "" keeps the stored id, clear_merchant_id:true removes it
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/validation.php';
require_once __DIR__ . '/../../core/settings.php';

checkLogin(true);
json_headers();
require_can('settings.write');

function settings_payload(): array
{
    $store = [];
    foreach (store_setting_defaults() as $k => $def) {
        $store[$k] = setting($k, $def);
    }
    $store['low_stock_threshold'] = (int) $store['low_stock_threshold'];
    $store['currency'] = 'PKR';
    $mid = (string) setting('jazzcash_merchant_id', '');
    return [
        'store' => $store,
        'payments' => [
            'cod_enabled' => (bool) setting('cod_enabled', true),
            'jazzcash_enabled' => (bool) setting('jazzcash_enabled', true),
            'cod_limit' => (int) setting('cod_limit', 0),
            'jazzcash_merchant_id' => $mid === '' ? '' : setting_mask($mid),
            'has_merchant_id' => $mid !== '',
        ],
    ];
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'GET') {
        json_response(['success' => true] + settings_payload());
    }
    if ($method !== 'PUT') {
        json_error('Invalid request method', 405);
    }
    enforce_csrf_json();
    $data = request_json();
    $values = is_array($data['values'] ?? null) ? $data['values'] : [];
    $errors = [];
    $set = [];

    if (($data['section'] ?? '') === 'store') {
        $text = [
            'store_name' => [80, true, 'Store name'], 'store_legal_name' => [120, false, 'Legal name'],
            'store_email' => [150, false, 'Email'], 'store_address' => [255, false, 'Address'],
            'store_city' => [60, false, 'City'], 'store_hours' => [200, false, 'Opening hours'],
            'store_notice' => [160, false, 'Store notice'],
        ];
        foreach ($text as $k => [$max, $required, $label]) {
            if (!array_key_exists($k, $values)) {
                continue;
            }
            $v = v_str($values[$k], $max);
            if ($v === null) {
                $errors[$k] = $label . ' must be at most ' . $max . ' characters.';
            } elseif ($required && $v === '') {
                $errors[$k] = $label . ' is required.';
            } else {
                $set[$k] = $v;
            }
        }
        if (isset($set['store_email']) && $set['store_email'] !== '' && v_email($set['store_email']) === null) {
            $errors['store_email'] = 'Enter a valid email address.';
        }
        foreach (['store_phone' => 'Phone', 'store_whatsapp' => 'WhatsApp number'] as $k => $label) {
            if (!array_key_exists($k, $values)) {
                continue;
            }
            $v = v_str($values[$k], 30);
            if ($v === null || ($v !== '' && !preg_match('/^\+?[0-9][0-9 \-]{6,18}[0-9]$/', $v))) {
                $errors[$k] = $label . ' should look like 042-35761234 or +92 300 1234567.';
            } else {
                $set[$k] = $v;
            }
        }
        if (array_key_exists('low_stock_threshold', $values)) {
            $v = v_int($values['low_stock_threshold'], 0, 1000);
            if ($v === null) {
                $errors['low_stock_threshold'] = 'Use a whole number from 0 to 1000.';
            } else {
                $set['low_stock_threshold'] = $v;
            }
        }
    } elseif (($data['section'] ?? '') === 'payments') {
        require_can('payments.write');
        foreach (['cod_enabled', 'jazzcash_enabled'] as $k) {
            if (array_key_exists($k, $values)) {
                $set[$k] = filter_var($values[$k], FILTER_VALIDATE_BOOLEAN);
            }
        }
        $cod = $set['cod_enabled'] ?? (bool) setting('cod_enabled', true);
        $jazz = $set['jazzcash_enabled'] ?? (bool) setting('jazzcash_enabled', true);
        if (!$cod && !$jazz) {
            $errors['cod_enabled'] = 'Keep at least one payment method on, or customers cannot check out.';
        }
        if (array_key_exists('cod_limit', $values)) {
            $raw = $values['cod_limit'];
            $v = ($raw === '' || $raw === null) ? 0 : v_int(is_string($raw) ? str_replace(',', '', $raw) : $raw, 0, 10000000);
            if ($v === null) {
                $errors['cod_limit'] = 'Use a whole rupee amount (0 means no limit).';
            } else {
                $set['cod_limit'] = $v;
            }
        }
        if (!empty($values['clear_merchant_id'])) {
            $set['jazzcash_merchant_id'] = '';
        } elseif (array_key_exists('jazzcash_merchant_id', $values) && trim((string) $values['jazzcash_merchant_id']) !== '') {
            $v = v_str($values['jazzcash_merchant_id'], 40);
            if ($v === null || !preg_match('/^[A-Za-z0-9_\-]{4,40}$/', $v)) {
                $errors['jazzcash_merchant_id'] = 'Merchant ID is 4–40 letters, digits, dashes or underscores.';
            } else {
                $set['jazzcash_merchant_id'] = $v;
            }
        }
        if ($jazz && !isset($errors['jazzcash_merchant_id']) && (string) ($set['jazzcash_merchant_id'] ?? setting('jazzcash_merchant_id', '')) === ''
            && ($set['jazzcash_enabled'] ?? false) && !(bool) setting('jazzcash_enabled', true)) {
            // turning JazzCash on without a merchant id is allowed (demo store) but flagged to the UI
            $warn = 'JazzCash is on without a merchant ID; payments are recorded manually.';
        }
    } else {
        json_error('Unknown settings section', 422);
    }

    if ($errors) {
        json_response(['success' => false, 'message' => 'Check the highlighted fields.', 'errors' => $errors], 422);
    }
    $changed = [];
    $conn->beginTransaction();
    foreach ($set as $k => $v) {
        $before = setting($k);
        set_setting($k, $v, $k === 'jazzcash_merchant_id');
        if ($before !== $v) {
            $changed[] = $k;
        }
    }
    $conn->commit();
    json_response(['success' => true, 'message' => $changed ? 'Settings saved' : 'Nothing changed', 'changed' => $changed, 'warning' => $warn ?? null] + settings_payload());
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    json_error(safe_error_message($e, 'Could not save settings.'), 500);
}

<?php
/**
 * Product specifications editor (admin product page, "Specifications" card).
 *
 * product_attributes rows feed three things: the specs table on the product page, the catalogue
 * facets, and the PC builder's compatibility rules (core/builder.php). This file defines which
 * fields each part type has, validates what staff type, and writes the rows.
 *
 *  - Builder part types (cpu, motherboard, memory, gpu, psu, case, cooler, storage) have a fixed
 *    field list. Fields the builder reads are marked 'builder' and are required before the product
 *    can be Active; values the rules compare (socket, memory type, form factor…) are fixed choices
 *    so a typo cannot silently break compatibility.
 *  - Other categories (monitors, mice, chairs…) get the keys their sibling products already use,
 *    most common first, so a new monitor is offered size, refresh rate, resolution and panel.
 *  - key_spec_1 / key_spec_2 are the two short "card highlights" (core/catalog.php key_specs()).
 *  - Anything else is a free key/value row ("More specifications").
 *
 * Data and validation only: no output.
 */

require_once __DIR__ . '/catalog.php';

/** Values the builder compares; offered as fixed choices. */
const ATTR_SOCKETS = ['LGA1851', 'LGA1700', 'LGA1200', 'AM5', 'AM4'];
const ATTR_FORM_FACTORS = ['E-ATX', 'ATX', 'mATX', 'ITX'];
const ATTR_MEMORY_TYPES = ['DDR4', 'DDR5'];
const ATTR_PSU_EFFICIENCY = ['80 Plus White', '80 Plus Bronze', '80 Plus Silver', '80 Plus Gold', '80 Plus Platinum', '80 Plus Titanium'];

/** Highest number of rows one product may carry (fields + highlights + extras). */
const ATTR_MAX_ROWS = 40;

/**
 * Field list per builder part type: [key, type, options|null, builder?]
 * type: int | decimal | flag | enum | multi (comma list of options) | text
 */
const ATTR_SCHEMA = [
    'cpu' => [
        ['socket', 'enum', ATTR_SOCKETS, true], ['cores', 'int', null, false], ['threads', 'int', null, false],
        ['base_ghz', 'decimal', null, false], ['boost_ghz', 'decimal', null, false], ['tdp_w', 'int', null, true],
        ['igpu', 'flag', null, true], ['bundled_cooler', 'flag', null, false],
    ],
    'motherboard' => [
        ['socket', 'enum', ATTR_SOCKETS, true], ['chipset', 'text', null, false], ['memory_type', 'enum', ATTR_MEMORY_TYPES, true],
        ['form_factor', 'enum', ATTR_FORM_FACTORS, true], ['m2_slots', 'int', null, false], ['wifi', 'flag', null, false],
    ],
    'memory' => [
        ['memory_type', 'enum', ATTR_MEMORY_TYPES, true], ['capacity_gb', 'int', null, false], ['modules', 'int', null, false],
        ['speed_mts', 'int', null, false],
    ],
    'gpu' => [
        ['chip', 'text', null, false], ['brand', 'text', null, false], ['vram_gb', 'int', null, false],
        ['tdp_w', 'int', null, true], ['length_mm', 'int', null, true], ['min_psu_w', 'int', null, false],
    ],
    'psu' => [
        ['wattage', 'int', null, true], ['efficiency', 'enum', ATTR_PSU_EFFICIENCY, false], ['modular', 'flag', null, false],
    ],
    'case' => [
        ['form_factors', 'multi', ATTR_FORM_FACTORS, true], ['max_gpu_mm', 'int', null, true], ['max_cooler_mm', 'int', null, true],
    ],
    'cooler' => [
        ['type', 'enum', ['air', 'aio'], true], ['sockets', 'multi', ATTR_SOCKETS, true], ['max_tdp_w', 'int', null, true],
        ['height_mm', 'int', null, false], ['radiator_mm', 'int', null, false],
    ],
    'storage' => [
        ['interface', 'text', null, false], ['capacity_gb', 'int', null, false], ['read_mbs', 'int', null, false],
        ['internal', 'flag', null, true],
    ],
];

/** Labels for fixed choices whose stored value is a code. */
const ATTR_OPTION_LABELS = ['type' => ['air' => 'Air', 'aio' => 'All-in-one liquid']];

/** Short help under a field. */
const ATTR_HINTS = [
    'igpu' => 'Without it the builder asks for a graphics card.',
    'tdp_w' => 'Used for the power estimate and cooler check.',
    'length_mm' => 'Checked against the case’s maximum.',
    'max_cooler_mm' => 'Checked against air-cooler height.',
    'height_mm' => 'Air coolers: checked against the case. 0 for liquid.',
    'internal' => 'Only internal drives appear in the builder.',
    'capacity_gb' => 'In GB: 1000 shows as 1 TB.',
    'sockets' => 'Every socket the mounting kit supports.',
    'form_factors' => 'Every motherboard size the case takes.',
];

/** Display names of part types (card subtitle). */
const ATTR_PART_NAMES = ['cpu' => 'Processor', 'motherboard' => 'Motherboard', 'memory' => 'Memory', 'gpu' => 'Graphics card',
    'psu' => 'Power supply', 'case' => 'Case', 'cooler' => 'CPU cooler', 'storage' => 'Storage'];

/** Turn a field tuple into the shape the editor uses. */
function attribute_field(string $key, string $type, ?array $options, bool $builder, array $suggest = []): array
{
    $labels = ATTR_OPTION_LABELS[$key] ?? [];
    return [
        'key' => $key,
        'label' => CATALOG_ATTR_LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key)),
        'type' => $type,
        'unit' => ltrim(trim(CATALOG_ATTR_UNITS[$key] ?? ''), '-') ?: null,   // "-inch" -> "inch"
        'options' => $options === null ? null : array_map(static fn($o) => ['value' => $o, 'label' => $labels[$o] ?? $o], $options),
        'builder' => $builder,
        'hint' => ATTR_HINTS[$key] ?? null,
        'suggest' => array_values($suggest),
    ];
}

/**
 * The editor's field list for a category.
 *
 * @return array{part_type:string, part_name:?string, builder:bool, fields:array<int,array>}
 */
function attribute_form(PDO $conn, ?int $categoryId): array
{
    $partType = 'other';
    if ($categoryId) {
        $st = $conn->prepare('SELECT part_type FROM categories WHERE id = ?');
        $st->execute([$categoryId]);
        $partType = (string) ($st->fetchColumn() ?: 'other');
    }
    $fields = [];
    if (isset(ATTR_SCHEMA[$partType])) {
        // free-text fields suggest the values already in the catalogue for that part type
        $st = $conn->prepare("SELECT a.attr_key, a.attr_value, COUNT(*) AS n FROM product_attributes a
            JOIN products p ON p.id = a.product_id JOIN categories c ON c.id = p.category_id
            WHERE c.part_type = ? GROUP BY a.attr_key, a.attr_value ORDER BY n DESC, a.attr_value");
        $st->execute([$partType]);
        $seen = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $seen[$r['attr_key']][] = (string) $r['attr_value'];
        }
        foreach (ATTR_SCHEMA[$partType] as [$key, $type, $options, $builder]) {
            $fields[] = attribute_field($key, $type, $options, $builder, $type === 'text' ? array_slice($seen[$key] ?? [], 0, 30) : []);
        }
    } elseif ($categoryId) {
        // learned from sibling products: keys most used in this category first
        $st = $conn->prepare("SELECT a.attr_key, a.attr_value, COUNT(*) AS n FROM product_attributes a
            JOIN products p ON p.id = a.product_id WHERE p.category_id = ? AND a.attr_key NOT IN ('key_spec_1', 'key_spec_2')
            GROUP BY a.attr_key, a.attr_value");
        $st->execute([$categoryId]);
        $byKey = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $byKey[$r['attr_key']]['n'] = ($byKey[$r['attr_key']]['n'] ?? 0) + (int) $r['n'];
            $byKey[$r['attr_key']]['values'][] = (string) $r['attr_value'];
        }
        // most used first; ties in the specs table's own order (size, refresh rate, resolution, panel…)
        $rank = array_flip(array_keys(CATALOG_ATTR_LABELS));
        uksort($byKey, static fn($a, $b) => [$byKey[$b]['n'], $rank[$a] ?? 999, $a] <=> [$byKey[$a]['n'], $rank[$b] ?? 999, $b]);
        foreach ($byKey as $key => $info) {
            if (count($fields) >= 12) {
                break;
            }
            $numeric = array_filter($info['values'], static fn($v) => !is_numeric($v)) === [];
            $type = in_array($key, CATALOG_ATTR_FLAGS, true) ? 'flag'
                : ($numeric && isset(CATALOG_ATTR_UNITS[$key]) ? (array_filter($info['values'], static fn($v) => !ctype_digit($v)) ? 'decimal' : 'int') : 'text');
            $fields[] = attribute_field($key, $type, null, false, $type === 'text' ? array_slice($info['values'], 0, 30) : []);
        }
    }
    return [
        'part_type' => $partType,
        'part_name' => ATTR_PART_NAMES[$partType] ?? null,
        'builder' => isset(ATTR_SCHEMA[$partType]),
        'fields' => $fields,
        // names the specs table already has labels for: suggestions for "More specifications"
        'known' => array_values(array_diff(array_keys(CATALOG_ATTR_LABELS), ['key_spec_1', 'key_spec_2'])),
    ];
}

/**
 * Validate the editor's rows against the form.
 *
 * @param array $form   attribute_form() for the product's (new) category
 * @param mixed $rows   list of ['key' => string, 'value' => string] in display order, as sent
 * @param bool  $active the product will be Active after this save (builder fields become required)
 * @return array{0: array<string,string>, 1: array<string,string>} [clean key => value in order, errors keyed 'attributes.<index>']
 */
function attribute_validate(array $form, $rows, bool $active): array
{
    $errors = [];
    if (!is_array($rows) || !array_is_list($rows)) {
        return [[], ['attributes' => 'Specifications could not be read; reload the page and try again.']];
    }
    if (count($rows) > ATTR_MAX_ROWS + count($form['fields'])) {
        return [[], ['attributes' => 'Too many specifications (' . ATTR_MAX_ROWS . ' at most).']];
    }
    $defs = [];
    foreach ($form['fields'] as $f) {
        $defs[$f['key']] = $f;
    }
    $clean = [];
    foreach ($rows as $i => $row) {
        $at = "attributes.$i";
        $key = is_array($row) && is_string($row['key'] ?? null) ? strtolower(trim($row['key'])) : null;
        $value = is_array($row) && is_scalar($row['value'] ?? null) ? trim(preg_replace('/\s+/u', ' ', (string) $row['value'])) : null;
        if ($key === null || $value === null) {
            $errors[$at] = 'This row could not be read.';
            continue;
        }
        $key = preg_replace('/[\s-]+/', '_', $key);
        if ($key === '' && $value === '') {
            continue;   // an empty extra row
        }
        if (!preg_match('/^[a-z][a-z0-9_]{0,39}$/', $key)) {
            $errors[$at] = $key === '' ? 'Name this specification, or clear its value.' : 'Use letters, numbers and underscores for the name (up to 40).';
            continue;
        }
        if (array_key_exists($key, $clean)) {
            $errors[$at] = isset($defs[$key]) ? 'This is already a field above.' : 'Each specification can appear once.';
            continue;
        }
        $def = $defs[$key] ?? null;
        if ($value === '') {
            if ($def && $def['builder'] && $active) {
                $errors[$at] = 'Needed by the PC builder before the product is Active.';
            }
            continue;
        }
        if (mb_strlen($value) > 255) {
            $errors[$at] = 'Keep it under 255 characters.';
            continue;
        }
        $type = $def['type'] ?? 'text';
        if ($type === 'int') {
            if (!ctype_digit($value) || strlen($value) > 7) {
                $errors[$at] = 'Enter a whole number' . ($def['unit'] ? ' in ' . $def['unit'] : '') . '.';
                continue;
            }
            $value = (string) (int) $value;
        } elseif ($type === 'decimal') {
            if (!preg_match('/^\d{1,5}(\.\d{1,2})?$/', $value)) {
                $errors[$at] = 'Enter a number like 4.4' . ($def['unit'] ? ' (' . $def['unit'] . ')' : '') . '.';
                continue;
            }
            $value = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');   // "4.40" -> "4.4", "5.0" -> "5"
        } elseif ($type === 'flag') {
            if (!in_array($value, ['0', '1'], true)) {
                $errors[$at] = 'Choose Yes or No.';
                continue;
            }
        } elseif ($type === 'enum' || $type === 'multi') {
            $allowed = array_column($def['options'], 'value');
            $picked = $type === 'multi' ? array_filter(array_map('trim', explode(',', $value)), 'strlen') : [$value];
            $canon = [];
            foreach ($picked as $p) {
                $hit = null;
                foreach ($allowed as $a) {
                    if (strcasecmp($a, $p) === 0) {
                        $hit = $a;
                    }
                }
                if ($hit === null) {
                    $errors[$at] = 'Choose from the list.';
                    continue 2;
                }
                $canon[] = $hit;
            }
            // multi: stored in the list's own order (ATX,mATX,ITX), the way the builder reads it
            $value = $type === 'multi' ? implode(',', array_values(array_intersect($allowed, $canon))) : $canon[0];
            if ($value === '') {
                if ($def['builder'] && $active) {
                    $errors[$at] = 'Needed by the PC builder before the product is Active.';
                }
                continue;
            }
        }
        $clean[$key] = $value;
    }
    // cross-field: an air cooler needs a height the case check can use
    if ($active && ($form['part_type'] ?? '') === 'cooler' && ($clean['type'] ?? '') === 'air' && (int) ($clean['height_mm'] ?? 0) <= 0) {
        foreach ($rows as $i => $row) {
            if (is_array($row) && ($row['key'] ?? '') === 'height_mm') {
                $errors["attributes.$i"] = 'Air coolers need a height so the builder can check the case.';
            }
        }
    }
    return [$clean, $errors];
}

/**
 * Replace a product's rows with $clean (order = sort_order) and return what changed, for the audit log.
 *
 * @param array<string,string> $clean
 * @return array{0: array<string,?string>, 1: array<string,?string>} [before, after] of changed keys only (null = absent)
 */
function attribute_save(PDO $conn, int $productId, array $clean): array
{
    $old = product_attributes($conn, $productId);
    $before = $after = [];
    foreach ($old as $k => $v) {
        if (!array_key_exists($k, $clean)) {
            $before[$k] = $v;
            $after[$k] = null;
        }
    }
    foreach ($clean as $k => $v) {
        if (($old[$k] ?? null) !== $v) {
            $before[$k] = $old[$k] ?? null;
            $after[$k] = $v;
        }
    }
    $orderChanged = array_keys(array_intersect_key($old, $clean)) !== array_keys(array_intersect_key($clean, $old));
    if (!$after && !$orderChanged) {
        return [[], []];
    }
    if ($gone = array_keys(array_diff_key($old, $clean))) {
        $st = $conn->prepare('DELETE FROM product_attributes WHERE product_id = ? AND attr_key IN (' . implode(', ', array_fill(0, count($gone), '?')) . ')');
        $st->execute([$productId, ...$gone]);
    }
    $up = $conn->prepare('INSERT INTO product_attributes (product_id, attr_key, attr_value, sort_order) VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE attr_value = VALUES(attr_value), sort_order = VALUES(sort_order)');
    $n = 0;
    foreach ($clean as $k => $v) {
        $up->execute([$productId, $k, $v, $n += 10]);
    }
    return [$before, $after];
}

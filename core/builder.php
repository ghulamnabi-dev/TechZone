<?php
/**
 * PC builder (docs/STOREFRONT.md "Builder rules"). Data only: no HTML.
 *
 * A build is slot => product id, shared by URL as "cpu:46,mb:8,ram:53,gpu:55,storage:72,psu:61,case:64,cooler:68".
 * Rules, exactly as specified:
 *   - Motherboard.socket = CPU.socket
 *   - Memory.memory_type = Motherboard.memory_type
 *   - Case.form_factors ∋ Motherboard.form_factor; Graphics.length_mm ≤ Case.max_gpu_mm;
 *     Cooler.height_mm ≤ Case.max_cooler_mm (air coolers)
 *   - Cooler.sockets ∋ CPU.socket; Cooler.max_tdp_w ≥ CPU.tdp_w
 *   - Estimated draw = CPU.tdp_w + GPU.tdp_w (or 0) + 75;
 *     recommended PSU ≥ max(GPU.min_psu_w, ceil(draw × 1.4 / 50) × 50);
 *     a PSU below the recommendation is a warning, below the draw is blocking
 *   - No graphics card and CPU.igpu = 0 → blocking
 * Graphics is optional when the CPU has integrated graphics; the cooler is optional when the CPU
 * ships with one (bundled_cooler = 1).
 */

require_once __DIR__ . '/catalog.php';

/** Watts added to CPU + GPU for the board, drives, fans and RAM. */
const BUILDER_BASE_DRAW_W = 75;
/** Headroom factor for the recommended PSU. */
const BUILDER_PSU_HEADROOM = 1.4;

/**
 * The builder's slots in order.
 *
 * @return array<string, array{label:string, part_type:string, optional:bool}>
 *         optional = can be skipped in principle (graphics, cooler); whether it may be skipped for a
 *         given CPU is reported by builder_evaluate()['missing'].
 */
function builder_slots(): array
{
    return [
        'cpu'     => ['label' => 'Processor', 'part_type' => 'cpu', 'optional' => false],
        'mb'      => ['label' => 'Motherboard', 'part_type' => 'motherboard', 'optional' => false],
        'ram'     => ['label' => 'Memory', 'part_type' => 'memory', 'optional' => false],
        'gpu'     => ['label' => 'Graphics', 'part_type' => 'gpu', 'optional' => true],
        'storage' => ['label' => 'Storage', 'part_type' => 'storage', 'optional' => false],
        'psu'     => ['label' => 'Power supply', 'part_type' => 'psu', 'optional' => false],
        'case'    => ['label' => 'Case', 'part_type' => 'case', 'optional' => false],
        'cooler'  => ['label' => 'Cooler', 'part_type' => 'cooler', 'optional' => true],
    ];
}

/**
 * Parse a share string ("cpu:46,mb:8") into slot => id. Unknown slots and bad ids are dropped.
 *
 * @return array<string, int>
 */
function builder_parse(string $b): array
{
    $slots = builder_slots();
    $out = [];
    foreach (explode(',', substr($b, 0, 400)) as $pair) {
        $kv = explode(':', trim($pair), 2);
        if (count($kv) === 2 && isset($slots[$kv[0]]) && ctype_digit($kv[1]) && (int) $kv[1] > 0) {
            $out[$kv[0]] = (int) $kv[1];
        }
    }
    return $out;
}

/** slot => id back to "cpu:46,mb:8" in slot order. */
function builder_encode(array $build): string
{
    $parts = [];
    foreach (array_keys(builder_slots()) as $slot) {
        if (!empty($build[$slot])) {
            $parts[] = $slot . ':' . (int) $build[$slot];
        }
    }
    return implode(',', $parts);
}

/**
 * Load the chosen parts. A part is kept only when it exists, is on sale and belongs in its slot.
 *
 * @param array<string, int> $build slot => product id
 * @return array{parts: array<string, array>, invalid: string[]}  parts: slot => product row + attrs
 */
function builder_load(PDO $conn, array $build): array
{
    $slots = builder_slots();
    $build = array_intersect_key($build, $slots);
    $rows = builder_fetch($conn, 'p.id IN (' . (catalog_placeholders(array_values($build)) ?: 'NULL') . ')', array_values(array_map('intval', $build)));
    $byId = [];
    foreach ($rows as $r) {
        $byId[(int) $r['id']] = $r;
    }
    $parts = [];
    $invalid = [];
    foreach ($build as $slot => $id) {
        $row = $byId[(int) $id] ?? null;
        if ($row && $row['part_type'] === $slots[$slot]['part_type'] && builder_slot_accepts($slot, $row['attrs'])) {
            $parts[$slot] = $row;
        } else {
            $invalid[] = $slot;
        }
    }
    return ['parts' => $parts, 'invalid' => $invalid];
}

/**
 * Compatible candidates for one slot given the rest of the build: in stock first, then price.
 *
 * A candidate is listed when it causes no blocking compatibility issue with the other chosen
 * parts. Candidates that only trigger a warning (a PSU under the recommendation) are listed with
 * their warnings. Completeness rules (e.g. "no graphics") do not filter candidates.
 *
 * @param array<string, int> $build slot => product id (the slot itself is ignored)
 * @return array<int, array> rows: id, name, price, inventory, in_stock, seller_id, category_name,
 *         attrs, key_specs, warnings (string[])
 */
function builder_candidates(PDO $conn, array $build, string $slot): array
{
    $slots = builder_slots();
    if (!isset($slots[$slot])) {
        return [];
    }
    $loaded = builder_load($conn, $build)['parts'];
    unset($loaded[$slot]);
    $rows = builder_fetch($conn, 'c.part_type = ?', [$slots[$slot]['part_type']]);
    $out = [];
    foreach ($rows as $r) {
        if (!builder_slot_accepts($slot, $r['attrs'])) {
            continue;
        }
        $issues = builder_rules($loaded + [$slot => $r]);
        $mine = array_filter($issues, static fn($i) => $i['kind'] === 'compat' && in_array($slot, $i['slots'], true));
        if (array_filter($mine, static fn($i) => $i['level'] === 'blocking')) {
            continue;
        }
        $r['warnings'] = array_values(array_map(static fn($i) => $i['message'], $mine));
        $out[] = $r;
    }
    usort($out, static fn($a, $b) => [$b['in_stock'], (float) $a['price'], (int) $a['id']] <=> [$a['in_stock'], (float) $b['price'], (int) $b['id']]);
    return $out;
}

/**
 * Parts of the slot's type that do NOT fit the rest of the build, each with the reasons
 * (the blocking compatibility messages). The builder lists them behind "Show parts that don't fit".
 *
 * @param array<string, int> $build slot => product id (the slot itself is ignored)
 * @return array<int, array> rows as builder_candidates(), plus reasons (string[]); cheapest first
 */
function builder_rejected(PDO $conn, array $build, string $slot): array
{
    $slots = builder_slots();
    if (!isset($slots[$slot])) {
        return [];
    }
    $loaded = builder_load($conn, $build)['parts'];
    unset($loaded[$slot]);
    $out = [];
    foreach (builder_fetch($conn, 'c.part_type = ?', [$slots[$slot]['part_type']]) as $r) {
        if (!builder_slot_accepts($slot, $r['attrs'])) {
            continue;
        }
        $reasons = [];
        foreach (builder_rules($loaded + [$slot => $r]) as $i) {
            if ($i['kind'] === 'compat' && $i['level'] === 'blocking' && in_array($slot, $i['slots'], true)) {
                $reasons[] = $i['message'];
            }
        }
        if ($reasons) {
            $r['reasons'] = $reasons;
            $out[] = $r;
        }
    }
    usort($out, static fn($a, $b) => [(float) $a['price'], (int) $a['id']] <=> [(float) $b['price'], (int) $b['id']]);
    return $out;
}

/**
 * Evaluate a (partial) build.
 *
 * @param array<string, int> $build slot => product id
 * @return array{
 *   build: string, parts: array<string, array>, invalid: string[], missing: string[],
 *   issues: array<int, array{level:string, code:string, message:string, slots:string[], kind:string}>,
 *   wattage: int, recommended_psu_w: int, total: float, out_of_stock: string[], complete: bool, ok: bool
 * }
 *   wattage = estimated draw; missing = required slots not chosen yet (graphics / cooler only when needed);
 *   complete = nothing missing and no blocking issue; ok = no blocking issue so far.
 */
function builder_evaluate(PDO $conn, array $build): array
{
    $loaded = builder_load($conn, $build);
    $parts = $loaded['parts'];
    $issues = builder_rules($parts);
    [$draw, $recommended] = builder_power($parts);

    $missing = [];
    foreach (builder_slots() as $slot => $def) {
        if (isset($parts[$slot])) {
            continue;
        }
        $cpu = $parts['cpu']['attrs'] ?? null;
        if ($slot === 'gpu' && ($cpu === null || ($cpu['igpu'] ?? '0') === '1')) {
            continue;   // integrated graphics (or no CPU yet): graphics optional
        }
        if ($slot === 'cooler' && ($cpu === null || ($cpu['bundled_cooler'] ?? '0') === '1')) {
            continue;   // cooler in the box
        }
        $missing[] = $slot;
    }

    $total = 0.0;
    $outOfStock = [];
    foreach ($parts as $slot => $p) {
        $total += (float) $p['price'];
        if (!$p['in_stock']) {
            $outOfStock[] = $slot;
        }
    }
    $blocking = (bool) array_filter($issues, static fn($i) => $i['level'] === 'blocking');
    return [
        'build' => builder_encode(array_map(static fn($p) => (int) $p['id'], $parts)),
        'parts' => $parts,
        'invalid' => $loaded['invalid'],
        'missing' => $missing,
        'issues' => $issues,
        'wattage' => $draw,
        'recommended_psu_w' => $recommended,
        'total' => round($total, 2),
        'out_of_stock' => $outOfStock,
        'complete' => !$missing && !$blocking,
        'ok' => !$blocking,
    ];
}

/**
 * [estimated draw, recommended PSU watts] for loaded parts.
 *
 * @return array{0:int, 1:int}
 */
function builder_power(array $parts): array
{
    $cpuW = (int) ($parts['cpu']['attrs']['tdp_w'] ?? 0);
    $gpuW = (int) ($parts['gpu']['attrs']['tdp_w'] ?? 0);
    $draw = $cpuW + $gpuW + BUILDER_BASE_DRAW_W;
    $recommended = max((int) ($parts['gpu']['attrs']['min_psu_w'] ?? 0), (int) (ceil($draw * BUILDER_PSU_HEADROOM / 50) * 50));
    return [$draw, $recommended];
}

/**
 * The rules over loaded parts (pure; no I/O). kind 'compat' = between two chosen parts,
 * kind 'missing' = a required part is absent.
 *
 * @param array<string, array> $parts slot => row with 'attrs' and 'name'
 * @return array<int, array{level:string, code:string, message:string, slots:string[], kind:string}>
 */
function builder_rules(array $parts): array
{
    $a = static fn(string $slot, string $key): ?string => isset($parts[$slot]['attrs'][$key]) ? (string) $parts[$slot]['attrs'][$key] : null;
    $csv = static fn(?string $v): array => $v === null ? [] : array_map('trim', explode(',', $v));
    $issues = [];
    $add = static function (string $level, string $code, string $message, array $slots, string $kind = 'compat') use (&$issues): void {
        $issues[] = ['level' => $level, 'code' => $code, 'message' => $message, 'slots' => $slots, 'kind' => $kind];
    };

    if (isset($parts['cpu'], $parts['mb']) && $a('mb', 'socket') !== $a('cpu', 'socket')) {
        $add('blocking', 'socket', sprintf('The %s fits %s boards; this motherboard is %s.', $parts['cpu']['name'], $a('cpu', 'socket'), $a('mb', 'socket')), ['cpu', 'mb']);
    }
    if (isset($parts['mb'], $parts['ram']) && $a('ram', 'memory_type') !== $a('mb', 'memory_type')) {
        $add('blocking', 'memory_type', sprintf('This motherboard takes %s memory; the kit is %s.', $a('mb', 'memory_type'), $a('ram', 'memory_type')), ['mb', 'ram']);
    }
    if (isset($parts['case'], $parts['mb']) && !in_array((string) $a('mb', 'form_factor'), $csv($a('case', 'form_factors')), true)) {
        $add('blocking', 'form_factor', sprintf('A %s motherboard does not fit this case (%s).', $a('mb', 'form_factor'), str_replace(',', ', ', (string) $a('case', 'form_factors'))), ['case', 'mb']);
    }
    if (isset($parts['case'], $parts['gpu']) && (int) $a('gpu', 'length_mm') > (int) $a('case', 'max_gpu_mm')) {
        $add('blocking', 'gpu_length', sprintf('The graphics card is %d mm long; this case fits up to %d mm.', (int) $a('gpu', 'length_mm'), (int) $a('case', 'max_gpu_mm')), ['case', 'gpu']);
    }
    if (isset($parts['case'], $parts['cooler']) && $a('cooler', 'type') === 'air' && (int) $a('cooler', 'height_mm') > (int) $a('case', 'max_cooler_mm')) {
        $add('blocking', 'cooler_height', sprintf('The cooler is %d mm tall; this case fits up to %d mm.', (int) $a('cooler', 'height_mm'), (int) $a('case', 'max_cooler_mm')), ['case', 'cooler']);
    }
    if (isset($parts['cpu'], $parts['cooler'])) {
        if (!in_array((string) $a('cpu', 'socket'), $csv($a('cooler', 'sockets')), true)) {
            $add('blocking', 'cooler_socket', sprintf('This cooler does not mount on %s.', $a('cpu', 'socket')), ['cpu', 'cooler']);
        }
        if ((int) $a('cooler', 'max_tdp_w') < (int) $a('cpu', 'tdp_w')) {
            $add('blocking', 'cooler_tdp', sprintf('This cooler handles %d W; the processor needs %d W.', (int) $a('cooler', 'max_tdp_w'), (int) $a('cpu', 'tdp_w')), ['cpu', 'cooler']);
        }
    }
    if (isset($parts['psu'])) {
        [$draw, $recommended] = builder_power($parts);
        $watts = (int) $a('psu', 'wattage');
        $powered = array_values(array_intersect(['cpu', 'gpu'], array_keys($parts)));
        if ($watts < $draw) {
            $add('blocking', 'psu_draw', sprintf('This %d W power supply is below the estimated draw of %d W.', $watts, $draw), array_merge(['psu'], $powered));
        } elseif ($watts < $recommended) {
            $add('warning', 'psu_recommended', sprintf('%d W will run, but %d W is recommended for this build.', $watts, $recommended), array_merge(['psu'], $powered));
        }
    }
    if (isset($parts['cpu']) && !isset($parts['gpu']) && $a('cpu', 'igpu') !== '1') {
        $add('blocking', 'no_graphics', sprintf('The %s has no integrated graphics: add a graphics card.', $parts['cpu']['name']), ['cpu', 'gpu'], 'missing');
    }
    return $issues;
}

/**
 * Whether a product of the right part type may go in the slot (storage: internal drives only).
 *
 * @internal
 */
function builder_slot_accepts(string $slot, array $attrs): bool
{
    return $slot !== 'storage' || ($attrs['internal'] ?? '0') === '1';
}

/**
 * Visible products matching $where (on aliases p, c), with attrs, key_specs and in_stock.
 *
 * @internal
 */
function builder_fetch(PDO $conn, string $where, array $params): array
{
    $st = $conn->prepare('SELECT p.id, p.name, p.price, p.inventory, p.seller_id, p.imagelink, p.category_id, c.name AS category_name, c.part_type
        FROM products p JOIN categories c ON c.id = p.category_id
        WHERE ' . catalog_visible_sql() . ' AND ' . $where);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $attrs = products_attributes($conn, array_column($rows, 'id'));
    foreach ($rows as &$r) {
        $r['attrs'] = $attrs[(int) $r['id']] ?? [];
        $r['key_specs'] = key_specs($r['attrs']);
        $r['in_stock'] = (int) $r['inventory'] > 0;
    }
    unset($r);
    return $rows;
}

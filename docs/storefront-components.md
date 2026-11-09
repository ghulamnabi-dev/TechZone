# Storefront components (v2 foundation)

Reference for the classes in `MarketingSite/assets/css/tokens.css` and the PHP helpers that feed them. The contract is [STOREFRONT.md](STOREFRONT.md). `tokens.css` loads before `style.css`, so legacy page rules still win until a page is moved over.

## Tokens

`--signal --black --graphite --paper --concrete --steel --line --drop --rise --hold` (colours), `--font-display` (Archivo), `--font-ui` (Manrope), `--type-13 … --type-104` (scale), `--wdth-condensed / --wdth-normal / --wdth-wide` (75 / 100 / 125 %), `--radius-field / -card / -control / -pill`, `--control-h` (48px), `--ring-signal`, `--shadow-pop`.

Archivo is self-hosted as one variable woff2 with both axes (`assets/fonts/archivo/`, OFL). Use `font-stretch` for width: `font-stretch: var(--wdth-condensed)`.

## Type

| Class | Use | Example |
|---|---|---|
| `.t-hero` | Archivo condensed 850, 104/56px | `<h1 class="t-hero">Aaj ka rate.</h1>` |
| `.t-display` | 800, 64/44px | `<p class="t-display">Build a PC</p>` |
| `.t-h1` `.t-h2` `.t-h3` | 44 / 32 / 24px headings | `<h2 class="t-h2">Price drops this week</h2>` |
| `.t-figure` | condensed 700, tabular numbers | `<span class="t-figure">98,500</span>` |
| `.t-wordmark` (+ `--mark`) | width 125, 800, signal square | `<a class="t-wordmark t-wordmark--mark">TechZone</a>` |
| `.t-body` `.t-small` `.t-muted` | Manrope body, 13px, steel | `<p class="t-body t-muted">…</p>` |

## Components

| Class | Use | Example |
|---|---|---|
| `.btn--primary` | black on white surfaces | `<a class="btn--primary" href="…">Build a PC</a>` |
| `.btn--secondary` | 1.5px outline | `<button class="btn--secondary">See today's rates</button>` |
| `.btn--on-black` | signal on black surfaces | `<a class="btn--on-black">Sell on TechZone</a>` |
| `.btn--block` | full width | `<button class="btn--primary btn--block">` |
| `.field` `__label` `__input` `__hint` `__error`, `.field--invalid` | form field | `<div class="field"><label class="field__label" for="e">Email</label><input class="field__input" id="e"></div>` |
| `.chip` (`.chip--on`, `[aria-pressed=true]`) | pill filter | `<button class="chip" aria-pressed="false">AM5</button>` |
| `.price-tag` `--sm/--md/--lg` `--figure` | signal tag, prices and drops only | `<?= price_tag(98500, 'md', 'price-tag--figure') ?>` |
| `.section-head` `__title` `__link` | h2 left, plain link right | `<div class="section-head"><h2 class="section-head__title">New arrivals</h2><a class="section-head__link" href="…">See all</a></div>` |
| `.rate-row` (`--spark`, `--on-black`) `__name` `__spec` `__price` | board / rates row | `<a class="rate-row rate-row--on-black rate-row--spark">…</a>` |
| `.delta--drop/--rise/--hold` | ▼ green, ▲ red, — | `<?= render_delta($row['change']) ?>` |
| `.sparkline` `__line` `__dot` | 64×20 rate history | `<?= render_sparkline(price_series($conn, 55)) ?>` |
| `.spec-tile` (`--sm`, `--static`) `__name` | product without a photo; fills a positioned box | `<div class="pcard__media"><?= render_spec_tile($p) ?></div>` |
| `.focus-ring` | 2px signal ring + black halo (also on every `:focus-visible`) | `<label class="focus-ring">` |

## PHP helpers

`MarketingSite/includes/format.php` (presentation, no I/O except the catalog folder listing):

| Helper | Returns | Example |
|---|---|---|
| `product_image_url(array $p): ?string` | `imagelink` (if the file exists) → `uploads/catalog/{id:02d}-*` → null | `$src = product_image_url($p);` |
| `render_product_media(array $p, array $opts)` | `<img>` (lazy, width/height) or spec tile | `<?= render_product_media($p, ['width' => 600]) ?>` |
| `render_spec_tile(array $p, string $size)` | concrete well + glyph + model name (`'md'`/`'sm'`) | `<?= render_spec_tile($p, 'sm') ?>` |
| `spec_glyph(string $type, int $size)` | outlined SVG glyph (cpu … storage, generic) | `<?= spec_glyph('gpu', 32) ?>` |
| `product_part_type(array $p)` | cpu, motherboard, …, monitor, other | `product_part_type($p) === 'cpu'` |
| `render_delta(?array $change)` | `.delta` span with screen-reader text | `<?= render_delta($chg[$id]) ?>` |
| `render_sparkline(array $points)` | `.sparkline` SVG | `<?= render_sparkline($series[$id] ?? []) ?>` |
| `product_json(array $p, array $extra)` | the APIs' product shape | `json_response(['item' => product_json($p)]);` |

`core/catalog.php`: `product_attributes($conn, $id)`, `products_attributes($conn, $ids)`, `key_specs($attrs)` (→ `['AM5', '6 cores']`), `spec_rows($attrs)` (specs table rows), `price_change($conn, $ids, 7)` (→ price, previous, delta, pct, direction), `price_series($conn, $id, 12)`, `price_series_many($conn, $ids)`, `price_range($series)`, `record_price_point($conn, $id, $price)`, `rate_board($conn, 8)`, `price_drops($conn, 8)`, `category_tree($conn)`, `category_with_children($conn, $id)`, `seller_for($conn, $productId)`, `sellers_by_id($conn)`, `catalog_visible_sql('p')`.

`core/builder.php`: `builder_slots()`, `builder_parse('cpu:46,mb:8')`, `builder_encode($build)`, `builder_candidates($conn, $build, 'ram')`, `builder_evaluate($conn, $build)` (issues, wattage, recommended_psu_w, total, missing, complete), `builder_rules($parts)`.

`core/wishlist.php`: `wishlist_ids`, `wishlist_has`, `wishlist_toggle`, `wishlist_add`, `wishlist_remove`, `wishlist_merge_session` (called by `api/login.php`). `core/stock_alerts.php`: `stock_alert_subscribe`, `stock_alerts_pending`, `stock_alerts_due`, `stock_alerts_mark_notified`. `core/throttle.php`: `throttle_allow($conn, 'scope', $max, $seconds)`.

## JSON APIs (`MarketingSite/api/`)

| Endpoint | Methods |
|---|---|
| `builder.php` | `GET ?b=cpu:46,mb:8[&slot=ram]` → slots, candidates, evaluation; `POST action=add_to_cart&b=…` (CSRF, throttled) |
| `wishlist.php` | `GET [?full=1]`; `POST product_id, action=toggle\|add\|remove` (CSRF, throttled) |
| `stock_alert.php` | `POST product_id, email` (CSRF, throttled) |
| `rates.php` | `GET ?view=board\|drops[&limit][&series=1]`, `GET ?view=series&id=55[&weeks=12]` |

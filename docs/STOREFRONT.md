# TechZone Storefront — design document

The storefront is a hardware marketplace for Pakistan. This document is the contract for its brand, pages, data and components. The admin contract is [ADMIN.md](ADMIN.md).

## The idea

Pakistani PC buyers check component prices the way traders check rates: walk Hafeez Centre or Hall Road, ask "aaj ka rate kya hai?", compare, then buy. Nobody online owns that habit. **TechZone is where you check today's rate, then buy** — from TechZone itself or from vetted shops in the same markets.

Everything distinctive follows from that: a live rate board as the hero, price history on every product, price-drop deals, a PC builder that totals a build at today's rates, and sellers from the real markets.

## Brand

**Signal yellow on black, like a market price board; white for shopping.**

| Token | Value | Use |
|---|---|---|
| `--signal` | `#FFD400` | Structural fields: hero field, rate-board figures, section markers, footer top band, price tags, focus ring, primary button on black |
| `--black` | `#000000` | The board, the footer, the seller band, primary button on white |
| `--graphite` | `#1B1D22` | Body text on white |
| `--paper` | `#FFFFFF` | Shopping surfaces |
| `--concrete` | `#EEF0F2` | Product image wells, inputs, alternating sections |
| `--steel` | `#5E6672` | Secondary text |
| `--line` | `#D9DCE1` | Hairlines |
| `--drop` | `#12823B` | Price went down (good for the buyer) ▼ |
| `--rise` | `#C8321E` | Price went up ▲; errors |
| `--hold` | `#8A6A00` | Low stock |

Rules: yellow is never a gradient, never a tint, never a background for body copy longer than two lines. Black is pure `#000` only on structural fields (board, footer, seller band); text is graphite. No shadows except dropdowns/offcanvas. Radius: 0 on structural fields (board, bands), 10px on cards and image wells, 8px on controls, pill only for chips.

**Type**
- **Archivo** (variable, width 62–125, weight 100–900), self-hosted. Display and figures.
  - Wordmark `TECHZONE`: width 125, weight 800, letter-spacing 0.02em. Mark: a 12px signal square before it (favicon is the square with a black `T`).
  - Headlines: width 100, weight 750–800, letter-spacing −0.02em, line-height 1.02 at display sizes.
  - Hero line: width 75 (condensed), weight 850, 104px desktop / 56px mobile.
  - Prices and board figures: width 75, weight 700, `font-variant-numeric: tabular-nums`.
- **Manrope** (already self-hosted) for UI and body: 15/16px, line-height 1.55, max 68ch.
- Scale: 13 / 15 / 16 / 20 / 24 / 32 / 44 / 64 / 104. Sentence case everywhere. No all-caps labels, no monospace data labels, no "→" on links, no middle-dot meta strings.

**Motion** — exactly one orchestrated moment: on home load the rate-board figures tick from last week's price to today's (600 ms, 40 ms stagger, ease-out). Everything else moves only in response to the user (hover, open, add to cart confirmation). `prefers-reduced-motion`: no tick.

**Imagery** — product photos on `--concrete` wells, `object-fit: contain`, 24px inset, never cropped. Catalogue photos are 1000×1000 WebP on white with an 8% margin, rendered with `mix-blend-mode: multiply` so the white drops into the well. Products without a photo render a **spec tile**: concrete well, a 64px outlined category glyph (inline SVG, 1.5px stroke, graphite), and the model name in Archivo condensed 600 — deliberate, not broken. `product_image_url($product)` resolves in this order: `products.imagelink` (the image the admin shows and replaces; skipped if the file is missing) → `AdminPortal/uploads/catalog/{id:02d}-*.{png,jpg,webp}` (a photo dropped in before `migrate.php` links it to the product) → spec tile.

## Information architecture

```
Header:  ■ TECHZONE   Components ▾  Peripherals  Monitors  Networking  Storage  Furniture   [ Search parts… ]   Build a PC   Track   Account   Cart (n)
Promo bar (black, signal text): store notice from settings — hidden when empty

/                      Home
/shop/products.php     Catalogue (filters by category, attributes, price, availability, seller)
/shop/product-details  Product
/builder.php           PC Builder
/rates.php             Today's rates — full board, sortable, by category, 12-week sparkline per row
/deals.php             Price drops this week
/sellers/              Sellers directory  →  /sellers/view.php?slug=…  seller page
/sell/                 Sell on TechZone (landing)  →  /sell/apply.php  application form
/wishlist.php  /compare.php   (session for guests, merged into the account on sign-in)
/track.php             Track your order (exists)
/help/                 Help centre: how to order, shipping, returns, warranty (claim form), FAQ, payments & installments
/store.php             Visit us — Hafeez Centre shop: hours, phone, WhatsApp, how to get there
```

## Home — layout

```
┌ promo bar ───────────────────────────────────────────────────────────────────────────────┐
├ header ──────────────────────────────────────────────────────────────────────────────────┤
│▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓ SIGNAL FIELD (full bleed, 640px desktop) ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓│
│▓ Aaj ka rate.                         ┌ BLACK BOARD ─────────────────────────────────────┐▓│
│▓ Lahore se.                           │ Today's rates · Hafeez Centre    Updated 10:40   │▓│
│▓ (Archivo condensed 850, 104px)       │──────────────────────────────────────────────────│▓│
│▓                                      │ RTX 4060 8GB · ASUS Dual      98,500   ▼ 1,500   │▓│
│▓ Live prices on 300+ PC parts from    │ Ryzen 5 7600 · AM5            54,000   ▲   800   │▓│
│▓ Lahore's markets. Order with cash on │ Core i5-13400F · LGA1700      54,000   —         │▓│
│▓ delivery, anywhere in Pakistan.      │ 32 GB DDR5-6000 · Kingston    38,500   ▼ 2,000   │▓│
│▓                                      │ … 8 rows; figures signal-yellow; rows link       │▓│
│▓ [ Search a part ……………… ] (56px)       │──────────────────────────────────────────────────│▓│
│▓ [Build a PC]  [See today's rates]     │ See all rates                    Prices in PKR   │▓│
│▓                                      └──────────────────────────────────────────────────┘▓│
│▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓│
├ trust row (white, hairlines): Cash on delivery nationwide | 7-day returns | Official warranty | Pickup at Hafeez Centre ┤
│                                                                                          │
│ Build a PC at today's rates                 ┌ build summary (concrete) ───────────────┐ │
│ Pick a processor and we'll only show        │ Processor   Ryzen 5 7600       54,000   │ │
│ parts that fit: socket, memory, power,      │ Motherboard ASRock B650M-HDV   44,500   │ │
│ case size.                                  │ Memory      32 GB DDR5-6000    38,500   │ │
│ [Ryzen 5 7600 ▾] → 6 boards fit · 4 RAM kits│ Graphics    RTX 4060 8GB       98,500   │ │
│ [Open the builder]                          │ Power       Corsair CX750      24,500   │ │
│                                             │ ▇ Total            Rs 260,000 (black)   │ │
│                                             └──────────────────────────────────────────┘ │
│                                                                                          │
│ Price drops this week                                                         See all   │
│ [card][card][card][card]   (cards show ▼ amount in a tag)                               │
│                                                                                          │
│ Shop by part   (data-forward list, 2×4: name · n products · from Rs X · small cutout)    │
│                                                                                          │
│█████ BLACK SELLER BAND ███████████████████████████████████████████████████████████████████│
│█ Sold on TechZone                          [seller][seller][seller]   Sell on TechZone ▮█│
│██████████████████████████████████████████████████████████████████████████████████████████│
│                                                                                          │
│ New arrivals                                                                  See all   │
│ [card][card][card][card]                                                                │
│                                                                                          │
│ ┌ SIGNAL block: Visit us ─────────────┐ ┌ store map (SVG, roads of Gulberg, one pin) ┐ │
│ │ Hafeez Centre, Shop 214, 2nd floor  │ │                                             │ │
│ │ Open today 11 am – 10 pm            │ │                                             │ │
│ │ 0301 1427871 · WhatsApp             │ │                                             │ │
│ └─────────────────────────────────────┘ └─────────────────────────────────────────────┘ │
│ Recently viewed (if any)                                                                 │
├▓ 6px signal band ▓────────────────────────────────────────────────────────────────────────┤
│ FOOTER (black): Shop · Help · Company · Sell on TechZone · contact · payment badges     │
└──────────────────────────────────────────────────────────────────────────────────────────┘
```

Mobile (390): signal field → headline (56px) → search → board (rows compact: name, price, delta) → buttons; sections stack; seller band scrolls horizontally; nothing overflows.

## Components

- **Product card** `render_product_card($p, $opts)`: concrete image well (1:1) with photo or spec tile; top-left price-drop tag if dropped this week ("▼ Rs 1,500"); wishlist heart top-right (toggle, works for guests); below: name (600, 2-line clamp), **two key specs** from attributes in steel ("AM5 · 6 cores", "27-inch · 144 Hz", "DDR5 · 32 GB"), price (Archivo condensed 700, 20px), stock line with dot, seller line in 13px steel ("Sold by Rizwan Computers"), compare checkbox on hover/focus. Add-to-cart appears on hover on fine pointers, always on touch.
- **Price tag** (existing CSS shape) — signal yellow, used only for prices and drops.
- **Rate row** — name + short spec, price, delta (▼ drop green, ▲ rise red, — hold), optional 12-week sparkline (inline SVG polyline, 64×20, graphite stroke, last point signal dot).
- **Section header** — h2 left (Archivo 32/44px), plain link right on the same baseline; never centered, no underline bars.
- **Buttons** — primary: black bg / white text on white surfaces, signal bg / black text on black surfaces; secondary: 1.5px border. 48px tall, 8px radius.

## Product page additions

Buy box: price (tag if dropped) + "▼ Rs 1,500 since last week" + **Rate history** sparkline with 12-week low/high; **installments** line ("or Rs 8,208 a month for 12 months · Meezan · HBL · Alfalah" — 0% markup plans at 3/6/12 months from settings; a small calculator popover); **seller box** (sold by, location, rating, "Ask on WhatsApp" link with prefilled product name — `https://wa.me/<number>?text=…`); wishlist + compare; **notify me** (email) when out of stock. Specs table from attributes. Compatibility hints for components ("Fits AM5 boards: 3 in stock").

## Data model additions (schema.sql + migrate.php, additive)

| Table / column | Purpose |
|---|---|
| `categories.parent_id`, `categories.slug`, `categories.part_type` | taxonomy; part_type ∈ cpu, motherboard, memory, gpu, psu, case, cooler, storage, other |
| `product_attributes (product_id, attr_key, attr_value, sort_order)` | specs and builder data. Keys: cpu: socket, cores, threads, base_ghz, boost_ghz, tdp_w, igpu (0/1); motherboard: socket, chipset, memory_type, form_factor, m2_slots, wifi; memory: memory_type, capacity_gb, modules, speed_mts; gpu: chip, vram_gb, tdp_w, length_mm, min_psu_w; psu: wattage, efficiency, modular; case: form_factors (csv: ATX,mATX,ITX), max_gpu_mm, max_cooler_mm; cooler: type (air/aio), sockets (csv), max_tdp_w, height_mm; storage: interface, capacity_gb, read_mbs; monitor: size_in, refresh_hz, resolution, panel; plus `key_spec_1/2` display hints |
| `price_history (product_id, price, recorded_at)` | weekly points; seed 12 weeks per product with a plausible walk ending at the current price |
| `sellers (id, slug, name, market, address, city, phone, whatsapp, rating, joined_at, status, commission_pct)` | id 1 = TechZone (house) |
| `products.seller_id` (default 1) | marketplace attribution |
| `seller_applications (id, shop_name, owner_name, phone, email, market, city, categories, ntn, message, status, reviewed_by, reviewed_at, created_at)` | Sell on TechZone form |
| `admins.seller_id` NULL | a seller's staff account (role `seller`) is scoped to their products/orders |
| `wishlists (customer_id NULL, session_id NULL, product_id, created_at)` | guests by session, merged on sign-in |
| `stock_alerts (product_id, email, created_at, notified_at)` | back-in-stock |
| `settings`: `installment_plans` (JSON: banks + months + markup), `whatsapp_number`, `store_hours`, `store_address_line` | |

Catalogue: add the 35 PC-builder parts listed in `techzone-products.xlsx` rows 41–75 (CPUs, motherboard, RAM, GPUs, PSUs, cases, coolers, NVMe) with realistic Pakistani-market prices (Nov 2026), stock, attributes, sellers. Move existing motherboards into Motherboards. Prices are demo data and the README says so.

## Builder rules (`core/builder.php`, also exposed as `MarketingSite/api/builder.php`)

Slots in order: CPU → Motherboard → Memory → Graphics (optional if CPU has igpu) → Storage → Power supply → Case → Cooler (optional if CPU ships with one — `bundled_cooler` attribute).

- Motherboard.socket = CPU.socket
- Memory.memory_type = Motherboard.memory_type
- Case.form_factors ∋ Motherboard.form_factor; Graphics.length_mm ≤ Case.max_gpu_mm; Cooler.height_mm ≤ Case.max_cooler_mm (air)
- Cooler.sockets ∋ CPU.socket; Cooler.max_tdp_w ≥ CPU.tdp_w
- Estimated draw = CPU.tdp_w + GPU.tdp_w (or 0) + 75; recommended PSU ≥ max(GPU.min_psu_w, ceil(draw × 1.4 / 50) × 50); a lower PSU is allowed with a warning, below draw is blocked
- No graphics + CPU.igpu = 0 → blocking issue

The API returns, for a partial build, each slot's compatible candidates (in stock first, then price), the running total at today's rates, issues (blocking / warning) and the estimated wattage. Builds are shareable by URL (`builder.php?b=cpu:46,mb:8,…`) and "Add all to cart" adds every part in one request (CSRF).

## Marketplace

- **Sell on TechZone** landing: black and signal; who it's for (shops in Hafeez Centre, Hall Road, Saddar), what you get (listing, orders, COD collection, payouts every Monday), the fee (8% per sale, no listing fee), how it works (apply → verify → list → sell), FAQ, apply CTA. Application form → `seller_applications` (CSRF, throttled).
- **Admin**: Sellers page (Customers group) — applications queue (approve → creates seller + seller account with temp password; reject with reason), sellers grid, seller detail with products and earnings. Audit everything.
- **Seller portal**: same admin shell, role `seller`: Home (their sales), Products (only theirs; create/edit, stock through the ledger), Orders (only orders containing their items, only their lines visible; can fulfil/ship their items), Payouts (gross − 8% commission, weekly statement). No access to customers' other orders, settings, other sellers.
- Storefront shows "Sold by …" on cards and products, seller pages list their products, and sellers are filterable in the catalogue.

## Quality floor

Responsive 375→1440 without horizontal scroll; visible 2px signal focus ring with a black halo; reduced motion respected; images lazy with width/height; Lighthouse-style hygiene (no layout shift from fonts: preload Archivo 800 condensed and Manrope 400); every form CSRF-protected; every list empty state written in plain language; copy in sentence case with Urdu used once (the hero) and never as decoration elsewhere.

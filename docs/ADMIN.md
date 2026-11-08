# TechZone Admin — design document

The admin is a back-office for a small hardware shop, modelled on how staff actually work: a queue of orders to fulfil, a catalogue to keep accurate, customers to look after, and settings that change rarely. It is not a dashboard with CRUD tables. This document is the contract between the pages, the `core/` layer and the database.

## Principles

1. **An order is a page, not a row.** Everything that happens to an order is an event on its timeline; staff act from the page (fulfil, record payment, ship, cancel) and the timeline records who did what.
2. **Every write is attributable.** `audit_log` records actor, action, entity and a before/after summary. The Activity page is the product's memory.
3. **Roles are real.** Owner, Manager and Staff see and can do different things; the server enforces it, the UI hides what you cannot do.
4. **Light, dense, calm.** The storefront is the brand; the admin is a tool. Neutral surfaces, semantic colour only for state, one brand mark.
5. **No framework, still disciplined.** Pages are thin: they call `core/` services (`orders.php`, `inventory.php`, `audit.php`, `permissions.php`, `settings.php`), which own the SQL and the rules.

## Information architecture

```
Home                 today vs yesterday, orders to fulfil, payments to collect, low stock, recent activity
Orders               tabs: Open · Unfulfilled · Unpaid · Completed · Cancelled · All   → Order page
  Order #1234        items + fulfil · payment · shipping/tracking · customer · notes · timeline · print
Products             grid (status, stock, price) → Product page (editor)
  Product            media · pricing (price, compare-at, cost) · inventory + adjustments · variants · status · SEO
Inventory            stock levels, low-stock, adjustment history
Customers            grid → Customer page: profile, tags, notes, lifetime value, order timeline
Discounts            list → Discount builder: type, applies to, minimum, usage limit, schedule, usage stats
Reviews              moderation queue
Messages             inbox with status
Analytics            sales, categories, payment mix, top products (existing, restyled)
Settings             Store · Shipping zones & rates · Payments · Staff & roles · Activity log
⌘K                   global search: orders (#, customer), products (name, SKU), customers (name, email, phone)
🔔                   notifications: new order, payment collected, low stock, review awaiting approval
```

## Data model additions (`database/schema.sql`, all additive)

| Table | Purpose |
|---|---|
| `order_events` | `order_id, type (placed, confirmed, payment_recorded, fulfilled, shipped, delivered, cancelled, note, refund_requested), actor_type (customer/staff/system), actor_id, payload JSON, created_at` — the timeline |
| `order_notes` | internal staff notes per order (`order_id, admin_id, body, created_at`) |
| `order_shipments` | `order_id, courier, tracking_number, shipped_at, delivered_at` |
| `inventory_adjustments` | `product_id, variation_id NULL, delta, reason (received, sold, returned, damaged, correction, cancelled_order), reference, admin_id, created_at` — stock is only changed through this |
| `audit_log` | `admin_id, action, entity_type, entity_id, summary, before JSON, after JSON, ip, created_at` |
| `admins.role` | `ENUM('owner','manager','staff')`, plus `last_login_at`, `is_active` |
| `customer_tags`, `customer_tag_links` | free-form tags (wholesale, gamer, corporate) |
| `customer_notes` | internal notes per customer |
| `shipping_zones`, `shipping_rates` | zone = set of cities (Lahore; Punjab; Nationwide), rate = flat amount + free-over threshold + ETA text |
| `settings` | key/value store (store name, address, phone, COD enabled, JazzCash enabled, low-stock threshold, currency) |
| `products` + | `status ENUM('draft','active','archived')`, `compare_at_price`, `cost_price`, `sku`, `seo_title`, `seo_description`, `low_stock_threshold` |
| `orders` + | `payment_status ENUM('pending','paid','refunded')`, `fulfillment_status ENUM('unfulfilled','partial','fulfilled')`, `shipping_zone_id`, `shipping_fee`, `discount_amount`, `subtotal`, `cancel_reason`, `cancelled_at` |
| `promotions` + | `kind ENUM('percent','fixed','free_shipping')`, `applies_to ENUM('all','category','product')`, `target_id`, `min_order`, `usage_limit`, `used_count`, `starts_at`, `ends_at` |

Order `status` (customer-facing) is derived: Pending → Processing (confirmed) → Shipped → Delivered, or Cancelled. `payment_status` and `fulfillment_status` are the staff-facing axes, exactly as Shopify separates them.

## Permissions

| Capability | Owner | Manager | Staff |
|---|---|---|---|
| View everything | ✓ | ✓ | ✓ |
| Fulfil / ship / record payment / notes | ✓ | ✓ | ✓ |
| Cancel order, refund | ✓ | ✓ | — |
| Edit products, inventory adjustments, discounts | ✓ | ✓ | — |
| Delete products, customers | ✓ | — | — |
| Settings, shipping, payments | ✓ | — | — |
| Staff & roles | ✓ | — | — |
| Activity log | ✓ | ✓ | — |

`core/permissions.php`: `can('orders.cancel')`, `require_can('settings.write')` (403 JSON or page). Capabilities map to roles in one array.

## Design tokens (light shell)

```
--canvas #f4f5f7   page background          --ink   #1a1d23   --ink-2 #5c6370   --ink-3 #8a909c
--surface #ffffff  cards, grid, modals      --line  #e1e3e8   hairlines           --line-2 #eef0f3 row dividers
--brand #ffd400    topbar mark, focus ring (2px) — never for large fills
--primary #1a1d23  primary buttons (ink), hover #2a2f38; secondary = white + --line border
--ok #0a7b4b / bg #e3f5ec  · --warn #9a6700 / bg #fff4d6  · --danger #b42318 / bg #fde8e6  · --info #175cd3 / bg #e6effc
type: Inter, system-ui; 13px body, 12px meta, 20px page titles, 600 for headings, tabular numerals for money
radius 8 (cards) / 6 (controls); card shadow 0 1px 2px rgba(16,24,40,.06) plus 1px --line border
layout: 240px sidebar (white, right hairline), 56px topbar (white) with ⌘K search centred, bell + avatar right;
        content max 1200px, 24px gutters; detail pages = 2 columns (main 2/3, side 1/3) of stacked cards
status pills: soft background + dark text (bg/ink pairs above), 12px, 22px tall, dot optional
```

Grid (AG Grid Community): white surface, --line borders, 44px rows, header 36px --ink-3 uppercase 11px letter-spacing .04em, hover --canvas, selected row --info bg at 40%, thin custom scrollbars (8px, --line-2 track) everywhere including modals.

## Interaction rules

- Every form page has a **contextual save bar** (slides in at the top when the form is dirty: "Unsaved changes — Discard · Save") and warns on navigation.
- Destructive actions confirm in a modal that states the consequence ("Cancelling restocks 3 items and emails the customer").
- Toasts confirm what happened in the past tense with the same verb as the button ("Order fulfilled").
- Empty states say what to do next ("No unfulfilled orders. New orders appear here the moment they're placed.").
- `/` or `⌘K` opens search; `G then O` goes to Orders, `G then P` Products (shown in the shortcuts sheet `?`).
- Printing an order page yields an invoice; "Packing slip" prints items only (print stylesheets).

## Storefront coupling

Checkout computes shipping from `shipping_zones` by the customer's city and applies free-over thresholds; the promo engine uses `promotions.kind/applies_to/min_order/usage_limit`. Order placement writes `order_events: placed` and `inventory_adjustments: sold` inside the existing transaction in `core/orders.php`.

# SORA Labs — Green Credit Redemption & User Dashboard

SORA Labs turns real-world tree-planting contributions into **Green Credit** that users can redeem for goods and vouchers.
This repository contains the web UI and server logic for:

* Browsing & redeeming partner products with Green Credit
* Submitting manual claims (vouchers/partners)
* Tracking credit balance, submissions, and orders
* Viewing contribution locations on a map

> Partner spotlight: **WearDHK** — users can redeem apparel and accessories directly with Green Credit.

---

## Highlights

* **Claim Shop (WearDHK)**

  * Product grid with credit pricing (no cash).
  * Secure “Redeem” flow collects shipping details and confirms deduction.
  * Atomic credit deduction with SQL transaction + ledger entry.
  * Automatic emails: receipt to user, fulfillment notice to ops (`info@soralabs.cc`).

* **Manual Claims**

  * Existing claim flow stays intact for non-product partners (e.g., vouchers).
  * Soft validation against current credit balance.

* **Dashboard**

  * Tabs for **Overview**, **History**, **Contribution Map**, and **Orders**.
  * **Orders** tab shows purchase history with status, totals, and a details modal (courier/tracking/address).
  * Map built with Leaflet; markers highlight verified/credited plantings.

* **Security & Reliability**

  * CSRF protection, honeypot field, rate-limiting.
  * Prepared statements; transactional balance updates.
  * Minimal PII (shipping address/phone) collected only on redemption.

* **Theming**

  * Dark, glassy UI consistent across pages (including tables/modals).
  * Mobile-first, responsive design; improved contrast in tab states and tables.

---

## Application Pages

### `claim.php`

* Shows WearDHK product list with **credit** cost per item.
* Modal-driven checkout collects:

  * Full name, phone
  * Address lines 1/2, city, postcode
  * Optional notes (e.g., size/color)
* On submit:

  * Validates product/quantity and address fields.
  * Locks the user row, checks balance, **deducts credits**, logs the delta, creates a `gc_orders` record.
  * Sends two emails (user receipt; ops for shipment).
* Includes **Manual claim** form (kept from the original program).

### `dashboard.php`

* **Overview:** Profile, balance, shortcuts, quick peek at latest order.
* **History:** Tree submission cards (verification status, confidence, points, GPS/map link).
* **Contribution Map:** Leaflet map with verified vs other submissions.
* **Orders:** Dark-themed table for all orders with a **details modal**:

  * Status, product/qty, credit totals
  * Ship-to address, courier, tracking code (if any)

> The Orders table follows the site’s dark theme; badges reflect status and remain readable.

---

## Data Model (High-Level)

### Users

* Holds account info and `green_credit` balance.

### Green Credit Ledger (`green_credit_log`)

* Append-only deltas (e.g., `+10` for verified tree, `-85` for redemption).
* Example reason: `WearDHK order WDHK-TEE-CLASSIC x1`.

### Orders (`gc_orders`)

Stores all product redemptions:

| Column              | Type / Notes                                                 |
| ------------------- | ------------------------------------------------------------ |
| `id`                | PK                                                           |
| `user_id`           | FK to users                                                  |
| `user_email`        | Snapshot of user email at purchase time                      |
| `sku`               | Product SKU (e.g., `WDHK-TEE-CLASSIC`)                       |
| `product_name`      | Snapshot of product title                                    |
| `unit_credits`      | Credits per unit                                             |
| `qty`               | Quantity (1–5)                                               |
| `total_credits`     | `unit_credits * qty`                                         |
| `full_name`         | Ship-to name                                                 |
| `phone`             | Ship-to phone                                                |
| `address_line1/2`   | Shipping address lines                                       |
| `city`, `postcode`  | Shipping city/postcode                                       |
| `notes`             | Delivery preferences, size/color, etc.                       |
| `status`            | `new` | `processing` | `shipped` | `delivered` | `cancelled` |
| `courier`           | Optional (set by ops)                                        |
| `tracking_code`     | Optional (set by ops)                                        |
| `created_at`        | Timestamp                                                    |
| `status_updated_at` | Timestamp (when status last changed)                         |

> The `claim.php` file ensures this table exists (`CREATE TABLE IF NOT EXISTS`) on first run.

---

## Order Lifecycle

1. **new** — Created on successful redemption; credits already deducted.
2. **processing** — Ops preparing fulfillment (size/color confirmation, packing).
3. **shipped** — Courier & tracking code assigned.
4. **delivered** — Optional final state for completed deliveries.
5. **cancelled** — If a redemption is voided; any credit adjustments must be made explicitly in ledger/admin.

> Status and tracking metadata are visible in the Orders tab and the order details modal.

---

## Emails

* **To User** — Order receipt with summary (product, quantity, total credits, ship-to).
* **To Ops** (`info@soralabs.cc`) — Fulfillment notice with the same summary.
* Both use `send_templated_mail(...)` from the existing codebase.

---

## Partner Products

* WearDHK items are defined inline in `claim.php` (`$wearDhkProducts`) with:

  * `sku`, `name`, `credits`, `img`, `desc`
* The grid links out to **View all at WearDHK** for browsing the full catalog.

> To add more products or partners, you can extend this array or move to a dedicated products table and load them dynamically.

---

## UX & Accessibility

* High-contrast dark UI for tables, badges, and modals.
* Keyboard-friendly forms; clear validation feedback.
* Responsive layouts; cards and grids adapt to mobile/desktop.
* Map markers and legend aid quick understanding of contribution status.

---

## Code Quality & Practices

* **Security:** CSRF tokens, rate limiting, honeypot spam trap, prepared statements.
* **Reliability:** SQL transactions for balance updates and order creation.
* **Separation of concerns:** UI components styled consistently (glass, badges, tables).
* **Extensibility:** Clearly defined order statuses, schema fields, and email hooks.

---

## Project Structure (Selected Files)

```
/assets/partners/weardhk/      # Product images used in the grid
claim.php                      # Claim Shop + Manual Claim + Order creation/emails
dashboard.php                  # User dashboard (Overview, History, Map, Orders)
config.php                     # DB connection, helpers (csrf, route, mail, etc.)
```

---

## Roadmap Ideas

* Admin UI for order management (status updates, courier/tracking).
* Product catalog in DB with stock/variants (size/color).
* Multi-partner marketplace support and filtering.
* Email templates per status update (e.g., shipped/delivered).
* Internationalization (i18n) for UI strings.
* Webhooks to/from logistics partners.

---



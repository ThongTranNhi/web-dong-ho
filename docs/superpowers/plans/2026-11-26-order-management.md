# Order Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add safe WooCommerce order management to the standalone auction dashboard.

**Architecture:** Extend `Luxury_Auction_Dashboard` with order query and mutation services, then render list/detail views in the existing dashboard template. WooCommerce remains the source of truth; every mutation uses its public order APIs and appends an audit note.

**Tech Stack:** PHP, WordPress, WooCommerce CRUD APIs, vanilla JavaScript, existing dashboard CSS.

**Spec:** `docs/superpowers/specs/2026-11-26-order-management-design.md`

## Global Constraints

- Preserve existing auction and product behavior.
- Require `manage_woocommerce`, an order-specific nonce and a reason of at least 5 characters for every mutation.
- Never move or refund money; do not expose a fake refund action.
- Do not commit or push unless the user asks.

## Review Focus

- Forged order IDs or status values must be rejected without changing an order.
- A customer account must never query or mutate orders through dashboard services.
- Repeated POST after redirect must not apply the transition twice.
- Mark-paid must use WooCommerce payment completion and preserve an optional transaction ID.
- Empty searches and unknown statuses must not break pagination.

---

### Task 1: Order mutation service

**Files:**
- Modify: `wordpress/wp-content/plugins/luxury-auctions/includes/class-luxury-auction-dashboard.php`
- Create: `scripts/tests/auction_dashboard_orders.php`

**Interfaces:**
- Produces: `Luxury_Auction_Dashboard::update_order($order_id, array $input)` returning order ID or `WP_Error`.

- [ ] Write tests for permissions, reason validation, status allowlist, paid completion, transaction ID and audit notes.
- [ ] Run the test and confirm it fails because `update_order()` is missing.
- [ ] Implement the service using WooCommerce CRUD methods.
- [ ] Run the test and confirm it passes.

### Task 2: Order queries and dashboard routing

**Files:**
- Modify: `wordpress/wp-content/plugins/luxury-auctions/includes/class-luxury-auction-dashboard.php`
- Modify: `scripts/tests/auction_dashboard_orders.php`

**Interfaces:**
- Produces: `order_list($search, $status, $page, $per_page)` and `managed_order($order_id)`.
- Consumes: `update_order()` from Task 1.

- [ ] Add failing tests for search, filtering, pagination and inaccessible orders.
- [ ] Implement query helpers and POST routing with order-specific nonces and redirect-after-POST.
- [ ] Run the order test and confirm it passes.

### Task 3: Order list and detail UI

**Files:**
- Modify: `wordpress/wp-content/plugins/luxury-auctions/templates/auction-dashboard.php`
- Modify: `wordpress/wp-content/plugins/luxury-auctions/assets/dashboard.css`
- Modify: `wordpress/wp-content/plugins/luxury-auctions/assets/dashboard.js`
- Modify: `scripts/tests/auction_dashboard_orders.php`

**Interfaces:**
- Consumes: order query results and update messages from Task 2.

- [ ] Add failing HTML assertions for menu, list, detail, status form, reason field and confirmation hook.
- [ ] Render responsive order list/detail views and clear warnings that refund state does not transfer money.
- [ ] Add client confirmation without replacing server validation.
- [ ] Run the order test and confirm it passes.

### Task 4: Regression verification

**Files:**
- Test: `scripts/tests/auction_dashboard.php`
- Test: `scripts/tests/auction_dashboard_products.php`
- Test: `scripts/tests/auction_dashboard_orders.php`

- [ ] Run PHP syntax checks for every changed PHP file.
- [ ] Run all auction test scripts.
- [ ] Open the order views at desktop and mobile widths and verify no horizontal page overflow.


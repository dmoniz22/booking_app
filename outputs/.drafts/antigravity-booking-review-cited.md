# Antigravity Booking WordPress Plugin — Deep-Dive Code Review

**Target:** `/home/dmoniz/projects/antigravity/booking_app/antigravity-booking/`
**Reviewed:** All 18 non-vendor PHP files (~5,700 LOC) + `public/js/antigravity-booking-public.js` (461 LOC) + `vendor/` excluded
**Date:** 2026-09-19 · **Method:** static read of every call site; file:line cited from source actually read

---

## Executive Summary

The plugin is a booking system built on a custom post type (`booking`) with a front-end shortcode, AJAX booking/availability endpoints, a Google OAuth calendar sync, and an admin dashboard. The code is generally clean and follows WordPress conventions for **output escaping** (esc_html/esc_attr/esc_textarea are used consistently across dashboard, CPT, and settings render methods). However, there are **three Critical-severity defects**:

1. **A fatal PHP error** — `get_calendar_events()` is called on a class that does not define it, so the `check_availability` / `get_calendar_events` public AJAX endpoints crash.
2. **Unauthenticated, unthrottled public AJAX endpoints** (`wp_ajax_nopriv_check_availability`, `wp_ajax_nopriv_get_calendar_events`) with no nonce and no rate limiting.
3. **Two directly-web-accessible utility scripts with no authentication** (`clear-cache.php`, `diagnostic-check.php`) that expose server paths and allow unauthenticated cache/rate-limit flushing.

Plus several High-severity issues: an **IDOR** in the inline-edit/checklist AJAX handlers (capability checked is `edit_posts`, not `edit_post($id)`), a **rate-limit bypass** via spoofable `X-Forwarded-For`, **plaintext access-token storage** and **verbose secret logging** in OAuth, and no uninstall/deactivation cleanup.

### Severity-ranked findings table

| # | SEVERITY | FILE:LINES | ISSUE | FIX |
|---|----------|------------|-------|-----|
| 1 | CRITICAL | class-antigravity-booking.php:113 | `$this->availability->get_calendar_events()` calls a method that does not exist on `Antigravity_Booking_Availability` (only `get_timezone`, `check_availability`, `is_overnight_booking`, `get_overnight_end`). Fatal `Error` on every `get_calendar_events`/`check_availability` request. | Implement `get_calendar_events($start,$end)` on the Availability class (return booked ranges) or remove the two dead AJAX hooks (lines 76–80, 108–115). |
| 2 | CRITICAL | class-antigravity-booking.php:76–77, 99–106 | `wp_ajax_nopriv_check_availability` + `wp_ajax_nopriv_get_calendar_events` are anonymous, have **no nonce** and **no rate limit**, and read raw `$_POST` via `sanitize_text_field`. `check_availability` is CPU-bound (runs `is_date_blacked_out` WP_Query per call). | Add `check_ajax_referer` + reuse `Antigravity_Booking_API::is_rate_limited()` (or remove the endpoints if unused by the front-end JS — the JS only uses `antigravity_get_availability`). |
| 3 | CRITICAL | clear-cache.php:1–29, 102–120 | Direct-URL utility script (`yoursite.com/wp-content/plugins/…/clear-cache.php`) with **no auth check**. Any anonymous visitor can `wp_cache_flush()`, delete rate-limit transients (bypassing booking abuse protection), and `flush_rewrite_rules()`. | Delete the file, or guard every action behind `current_user_can('manage_options')` + `check_admin_referer` (as `clear-oauth-cache.php` does at lines 10–12). |
| 4 | HIGH | class-antigravity-booking-dashboard.php:1149 & 1251 | IDOR: `ajax_update_booking_inline` and `ajax_update_checklist_item` only check `current_user_can('edit_posts')` — any editor/author/contributor can modify *any* booking's customer name/email/phone/dates/status (they call `update_post_meta`/`wp_update_post` on an arbitrary `booking_id`). Contrast `handle_status_change`/`handle_bulk_action` which correctly use `edit_post($booking_id)` (lines 985, 1032). | Replace `current_user_can('edit_posts')` with `current_user_can('edit_post', $booking_id)` in both AJAX handlers. |
| 5 | HIGH | class-antigravity-booking-api.php:74–89 | Rate-limit IP detection trusts `HTTP_X_FORWARDED_FOR`/`HTTP_X_REAL_IP` before validating, returning the first attacker-supplied header value. Rotating that header resets the 30-request/15-min budget per request → rate limit trivially bypassed. | Use only `$_SERVER['REMOTE_ADDR']` (or validate header origin against a trusted proxy) and hash the key. |
| 6 | HIGH | class-antigravity-booking-google-oauth.php:89 vs 92 | Access token stored **plaintext** in options (`antigravity_gcal_oauth_access_token`), while only the refresh token is encrypted. Also logs OAuth flow excessively: client_id (line 21), full auth URL + client_id (55–56), token-exchange params (130), and raw token-exchange response body (143). | Encrypt access token too (reuse `encrypt_token`); strip client_secret from all logs; drop `error_log` of request/response bodies in production. |
| 7 | HIGH | diagnostic-check.php:12–15, 336–342 | Direct-URL script with **no `ABSPATH` guard** (explicitly sets `DIAGNOSTIC_MODE`), reads plugin source via `file_get_contents` and echoes absolute server paths `__DIR__`. Information disclosure + source/path leakage to anonymous visitors. | Gate behind login + capability, or delete from the plugin bundle entirely. |
| 8 | MEDIUM | class-antigravity-booking.php:83–97 | `ajax_calculate_cost` (logged-in `wp_ajax_`) has **no nonce** and **no capability check** — any authenticated user (including subscribers) can call it. Low impact (returns a cost figure) but an unsecured endpoint. | Add `check_ajax_referer` + `current_user_can('edit_posts')` (it backs the admin meta-box). |
| 9 | MEDIUM | class-antigravity-booking-dashboard.php:1264 | `ajax_update_checklist_item`: meta key is built by concatenation `'_checklist_' . $item` from a `sanitize_text_field`-only (not whitelisted) `$item`. An editor could write arbitrary `_checklist_*` meta keys. | Whitelist `$item` against the 5 known checklist keys before writing. |
| 10 | MEDIUM | class-antigravity-booking-google-oauth.php:255–262 | Crypto uses `wp_salt('auth')` directly as an AES key (not a proper key derivation; `wp_salt` may be empty in hardened setups), and the `openssl` fallback silently stores the token as base64 (trivially reversible). | Derive an encryption key via `hash_hkdf` from `wp_salt('auth')`; fail closed (don't fall back to base64) if `openssl` is unavailable. |
| 11 | MEDIUM | class-antigravity-booking-settings.php:800–808 | `render_oauth_status_field` echoes debug info in the admin UI: full OAuth auth URL (line 806), first 30 chars of client_id (802), and `strlen(client_secret)` (804). Leaks credential metadata to any `manage_options` holder and via any admin XSS. | Remove the "Debug Information" `<details>` block (or gate it behind `WP_DEBUG` + `is_super_admin()`). |
| 12 | MEDIUM | antigravity-booking.php:52 + activator/deactivator (6-line stubs) | No `register_activation_hook` logic (empty `activate()`/`deactivate()`), no `uninstall.php`, and `wp_schedule_event('antigravity_send_reminders')` (emails:19) is never cleared via `wp_clear_scheduled_hook`. Orphan cron + no CPT/flush on activation. | Implement activation flush (`flush_rewrite_rules()`), deactivation cron cleanup, and an `uninstall.php` to purge options/booking posts if intended. |
| 13 | LOW | class-antigravity-booking-google-calendar.php:58 | `make_api_request` logs the full request URL (incl. calendar ID) but not the token — token is correctly kept in the header. Informational only; ensure URL logging is disabled in production. | Remove/`WP_DEBUG`-gate the URL log. |
| 14 | LOW | class-antigravity-booking-dashboard.php:345 | Client-side `.html('<strong>'+data.customer_name+'</strong>')` writes the (admin-entered) name via `.html()` after an inline save — self-XSS only (actor = victim). Prefer `.text()` or escape. | Use `.text()` or escape the value before injection. |
| 15 | LOW | clear-oauth-cache.php:8 | Loads WordPress via brittle relative path `require_once('../../../wp-load.php')` (breaks if not in default WP tree). | Use the same wp-load discovery loop as `clear-cache.php:12–16` and add an explicit admin-referer check. |
| 16 | INFO | class-antigravity-booking-settings.php:577 | References a cron hook `antigravity_check_expired_bookings` that is **never scheduled or handled** anywhere in the codebase (dead reference; "expiry" feature is not implemented). | Either implement the expiry cron or remove the misleading status line. |

---

## Security Surface Map

**Public (anonymous) attack surface**
- `admin_post_*` / `admin-post.php`: `change_booking_status`, `bulk_booking_action`, `export_bookings_csv` — all nonce + `edit_post`/`edit_posts` protected ✓
- `wp_ajax_nopriv_antigravity_get_availability` / `antigravity_create_booking` — nonce-checked (`check_ajax_referer('antigravity_booking_nonce', …)` at api:271,432) + per-IP transient rate limit (30/15min) — **rate limit bypassable via `X-Forwarded-For` spoofing (finding 5)**
- `wp_ajax_nopriv_check_availability` / `get_calendar_events` (main class:76–80) — **NO nonce, NO rate limit, and `get_calendar_events` is a fatal error (findings 1–2)**
- `clear-cache.php`, `diagnostic-check.php`, `clear-oauth-cache.php` — direct-URL scripts (findings 3, 7)

**Front-end data flow**
- Nonce `antigravity_booking_nonce` generated on every shortcode render via `wp_create_nonce` (shortcode:39) — for logged-out users this is sessionless and effectively shared/guessable by WP's own design; combined with the spoofable rate limit, booking spam is feasible. Not separately a plug-in defect, but the rate-limit weakness of finding 5 is what makes it exploitable.
- Cost: computed **server-side** and overwritten (`calculate_cost` at api:511–513), so client-side cost display (JS:313–324) is not a tampering vector for the persisted record. ✓ Booking meta is re-sanitized server-side (`sanitize_booking_input`, api:168–257).

**Admin surface**
- Dashboard: `edit_posts`-gated menu (line 30); status-change/bulk correctly use `edit_post($id)`. **The two inline AJAX handlers are the IDOR gap (finding 4, 9).**
- Settings page: `manage_options`-gated (line 514); `register_setting` sanitization callbacks are all present and reasonable (floatval/intval/sanitize_email/sanitize_array/trim). OAuth secret uses `trim` only (settings:276) which is intentional to preserve special chars but means no escaping — acceptable given `password` input + esc_attr on render.

**Secrets / credentials**
- `antigravity_gcal_oauth_client_secret` (settings option), `antigravity_gcal_oauth_access_token` (plaintext), `antigravity_gcal_oauth_refresh_token` (encrypted via AES-256-CBC with `wp_salt('auth')`). Findings 6, 10, 11.

**Data storage**
- Bookings = `booking` CPT + postmeta; blackout dates = `blackout_date` CPT. No raw `$wpdb` calls with user input anywhere — **the "no `prepare()`" lead is confirmed but is NOT an SQL-injection issue**: the only `$wpdb->query()` calls are static `DELETE … LIKE` statements (clear-cache.php:103–109, clear-oauth-cache.php:30–31) with no interpolated user values. All dynamic queries go through `WP_Query`/`get_posts` with sanitized/intval'd meta values. → **SQLi: NOT FOUND (verified).**

---

## Verification Checks Performed

| Check | Result |
|-------|--------|
| `$wpdb` usage (~grep) | 8 hits total, all static `DELETE … LIKE` in the two cache scripts; no `prepare()` calls exist, but **no query interpolates user input** → no SQLi |
| `prepare(` presence | 0 occurrences — consistent with above (WP_Query used for all dynamic data) |
| Booking persistence | CPT `booking` via `wp_insert_post` + `update_post_meta` (api:493–513); no raw SQL |
| Public endpoint nonces | `antigravity_get_availability` (`check_ajax_referer` ✓), `antigravity_create_booking` (✓); `check_availability` & `get_calendar_events` (✗ none) |
| `get_calendar_events` method exists | ✗ CONFIRMED MISSING — fatal error site |
| IDOR — per-post capability | `handle_status_change`/`handle_bulk_action` use `edit_post($id)` ✓; `ajax_update_booking_inline`/`ajax_update_checklist_item` use only `edit_posts` ✗ |
| Output escaping (dashboard/CPT/settings) | `esc_html`/`esc_attr`/`esc_textarea` used consistently; only unescaped echo is numeric/status-map values (safe) |
| `wp_mail` header injection | None — customer email validated via `is_email`+`FILTER_VALIDATE_EMAIL` (api:143, 207); subjects/templates admin-configured; plain-text bodies |
| Cron schedule/cleanup | `wp_schedule_event` (reminders) present; **no `wp_clear_scheduled_hook` anywhere**; dead `antigravity_check_expired_bookings` reference |
| Uninstall / activation hooks | `register_activation_hook`/`register_deactivation_hook` registered (main file:45–46) but `activate()`/`deactivate()` are empty 6-line stubs; **no `uninstall.php`** |
| Client-side XSS sinks | `.html()` used for server-controlled messages (labels/times — safe); one self-XSS path at dashboard:345 |

---

## Open Questions

1. **Are `check_availability` / `get_calendar_events` actually used anywhere?** The front-end JS only calls `antigravity_get_availability`. If these two nopriv hooks are dead code, finding #1/#2 reduce to "delete them" rather than "fix them" — worth confirming against the theme/production before patching.
2. **`antigravity_gcal_credentials_json` / `_file` options** are read in settings (lines 555–556) but the modern flow uses OAuth `client_id`/`client_secret`. Are the legacy JSON/service-account options still used by any active code path, or are they stale (and should be purged from storage)?
3. **`clear-cache.php` / `diagnostic-check.php`** are "upload this file" helper scripts. Were they intentionally deployed to production, or are they leftover dev artifacts? (They are the source of findings 3 and 7.)
4. The `HOTFIX_v1.2.0.md` "bookings disappearing after save — status taxonomy" note suggests a prior status-handling bug; the `force_custom_status` filter (cpt:518–539) now coerces WP statuses. Is there a regression test confirming approved/pending statuses persist through `wp_insert_post`?

---

### BLOCKED / NOT VERIFIED

- **Live runtime behavior** (fatal error, rate-limit bypass) was confirmed by static analysis only — the plugin was not executed against a running WordPress instance in this review.
- **`vendor/` (composer deps — google/apiclient, guzzle, monolog, phpseclib)** were intentionally out of scope; only the plugin's own 18 files + the one JS file were reviewed.

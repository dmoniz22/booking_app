# Changelog

## 1.3.0 — Security hardening (2026-09-19)

Security fixes from deep-dive code review. No functional changes to the
booking flow, dashboard, or Google Calendar sync.

### Critical
- Removed dead public AJAX endpoints `check_availability` and
  `get_calendar_events` (front-end JS never called them; the
  `get_calendar_events()` method did not exist → fatal error on request).
  The booking form uses the nonce-protected + rate-limited
  `antigravity_get_availability` / `antigravity_create_booking` endpoints.
- Removed `clear-cache.php` and `diagnostic-check.php` utility scripts from
  the bundle (they were directly web-accessible with no auth gate).
  `clear-oauth-cache.php` retains its `manage_options` guard but is also
  removed from the bundle — both remain in the git history / project folder
  if you need them during development. Re-upload ONLY the `clear-oauth-cache.php`
  copy (it is admin-gated) if you hit OAuth issues and want the manual reset.

### High
- Inline booking edit + checklist AJAX now require `edit_post($booking_id)`
  capability instead of `edit_posts` (prevents an editor from modifying any
  other booking). Admins are unaffected.
- Checklist meta key writes are now whitelisted (only the 5 known
  `_checklist_*` keys).
- Rate-limit IP detection now uses only `REMOTE_ADDR` — `X-Forwarded-For`
  is attacker-spoofable on shared hosting. (If you ever put the site behind
  Cloudflare/a proxy, revisit this.)
- Google OAuth access token is now stored encrypted (same AES-256-CBC scheme
  as the refresh token, with HKDF-derived key; legacy plaintext tokens are
  still readable until re-authorized). Excessive credential logging removed.

### Medium / Low
- `calculate_booking_cost` AJAX now requires a nonce + `edit_posts`
  (caller JS updated to send the nonce) — fixes the admin meta-box cost calc.
- Removed OAuth "Debug Information" block from the settings page (was leaking
  client ID / secret length / auth URL to any admin).
- GCal API request logging gated behind `WP_DEBUG`.
- Dashboard inline update refreshes the customer name via `.text()` (no HTML
  injection).
- Uninstall hook added: deletes all plugin options + clears cron hooks on
  plugin delete. Booking/blackout posts are intentionally preserved.
- Activation now flushes rewrite rules (registers CPTs properly); deactivation
  clears scheduled events.

## 1.2.0 (previous)
- Booking disappearing after save (status taxonomy) hotfix — see HOTFIX_v1.2.0.md

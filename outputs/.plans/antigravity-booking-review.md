# Deep-Dive Code Review Plan: antigravity-booking

**Project:** /home/dmoniz/projects/antigravity/booking_app (WP plugin, ~5,500 LOC PHP)
**Date:** 2026-09-19
**Method:** delegated review (deepseek-v4-pro, high-stakes pin) + parent verification
**User request:** "deep-dive code review of the app" — the DEEPRESEARCH_WORKFLOW pattern adapted for code.

## Key Questions
1. **Security**: Are the 4 public (nopriv) AJAX endpoints (get_availability, create_booking, check_availability, get_calendar_events) protected against abuse? Nonce + CSRF + capability checks correct?
2. **Injection**: Any unsanitized `$_POST/$_GET` into SQL ($wpdb), options, email headers, redirects? (grep shows 0 `prepare(` calls — needs manual check)
3. **IDOR / authz**: Can a user edit/delete/approve another user's booking? Are `edit_post` checks using the right post type + current user?
4. **Secrets**: Google OAuth client_id/secret handling — stored in options (unencrypted?), logging of credentials (`error_log` of client ID first 20 chars at class-antigravity-booking-google-oauth.php:21), token storage/refresh, exposure in admin UI?
5. **Email**: Booking confirmation emails — header injection, escaping, HTML vs plaintext, spammability?
6. **Correctness**: Rate limiting (30/15min) bypassable? Inline editing/checklist flows? Availability overlap logic? Blackout handling?
7. **WP standards**: proper escaping (esc_html/esc_attr/esc_url), sanitization (sanitize_text_field etc.), nonce patterns, capability checks on ALL admin handlers.
8. **Pitfalls in changelog**: v1.2.0 HOTFIX + OAUTH_TROUBLESHOOTING + GOOGLE_OAUTH_SETUP docs hint at past OAuth pain — check code for leftover issues.

## Evidence needed
- Read all PHP under antigravity-booking/ (exclude vendor/, *.zip)
- Grep all $_POST/$_GET/$_REQUEST/SQL/curl/wp_remote/error_log
- Trace each AJAX handler end-to-end (nonce → caps → data → SQL → response)
- Check public/js for client-side authz bypasses
- Check composer.lock for vulnerable deps (brief scan)
- Review docs (OAUTH_TROUBLESHOOTING, HOTFIX_v1.2.0, CHANGELOG*) for known-issue context

## Scale decision
Single codebase review, one reviewer agent (high-stakes pin). No parallel split — files interlock (API ↔ Dashboard ↔ Settings). Parent does a spot-verification pass on top findings afterward.

## Task ledger
- [ ] Write plan artifact (this file) — DONE
- [ ] Delegate review (deepseek-v4-pro) with explicit file list + focus areas
- [ ] Parent verification of top findings (read the cited lines)
- [ ] Deliver summary + file report

## Verification log
- grep -rE security terms across plugin (done: sanitize_ 7 files, esc_ 4, nonce 4, caps 6, no eval/exec/shell_exec, no curl_)
- public nopriv handlers: 4 endpoints found
- pending: reviewer confirmation + parent spot-checks

## Decision log
- User says "deep-dive code review" → high-stakes pin (deepseek-v4-pro) per delegation-routes
- Code is local on Hermes LXC — no laptop SSH needed

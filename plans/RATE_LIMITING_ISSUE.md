# 🔴 CRITICAL ISSUE #3: Rate Limiting Too Aggressive

**Date:** 2026-01-22  
**Severity:** CRITICAL  
**Impact:** Prevents legitimate users from creating bookings

---

## Problem Description

Users are getting "Too many requests. Please wait a few minutes before trying again" error when trying to create bookings, even on their first attempt.

---

## Root Cause Analysis

### Location
**File:** [`class-antigravity-booking-api.php`](../antigravity-booking/includes/class-antigravity-booking-api.php:15-25)

### Current Configuration

```php
/**
 * Rate limit window in seconds (15 minutes)
 */
const RATE_LIMIT_WINDOW = 900;

/**
 * Maximum requests per window
 */
const MAX_REQUESTS_PER_WINDOW = 10;
```

### The Problem

The rate limiting is applied to **BOTH** endpoints:
1. `get_availability` - Called when user selects a date (line 263)
2. `create_booking` - Called when user submits booking (line 424)

**Typical User Flow:**
1. User loads booking form → 1 request
2. User selects a date → 1 request (get_availability)
3. User changes date to check availability → 1 request per date change
4. User selects time slot → 0 requests (client-side)
5. User fills form and submits → 1 request (create_booking)

**Problem Scenario:**
- User checks 3-4 different dates to find availability = 4 requests
- User refreshes page or navigates back = 1-2 more requests
- User tries to submit booking = 1 request
- **Total: 6-7 requests just for normal usage**

If the user makes a mistake or the form has validation errors, they could easily hit 10 requests within 15 minutes, especially if:
- They're comparing multiple dates
- They refresh the page
- They have multiple tabs open
- They're testing the booking process

---

## Why This Happens

### Rate Limit Tracking Issue

The rate limiting uses IP address as the key:

```php
private function is_rate_limited()
{
    $user_ip = $this->get_client_ip();
    $cache_key = self::RATE_LIMIT_KEY . $user_ip;
    
    $request_count = get_transient($cache_key);
    
    if ($request_count === false) {
        // First request or cache expired
        set_transient($cache_key, 1, self::RATE_LIMIT_WINDOW);
        return false;
    }
    
    if ($request_count >= self::MAX_REQUESTS_PER_WINDOW) {
        return true;  // BLOCKED!
    }
    
    // Increment request count
    set_transient($cache_key, $request_count + 1, self::RATE_LIMIT_WINDOW);
    return false;
}
```

**Issues:**
1. **Shared IP Addresses:** Multiple users behind the same NAT/proxy share the same IP
2. **Too Low Limit:** 10 requests is too restrictive for legitimate use
3. **No Differentiation:** Doesn't distinguish between availability checks and booking submissions
4. **No User Feedback:** User doesn't know how many requests they have left

---

## Additional Complications

### 1. Shared IP Scenarios

**Corporate Networks:**
- Multiple employees in same office share one public IP
- One person checking availability blocks everyone else

**Public WiFi:**
- Coffee shop, library, airport WiFi
- All users share the same IP address

**Mobile Networks:**
- Carrier-grade NAT (CGNAT) means thousands of users share IPs
- Very common with mobile data

### 2. Development/Testing

During development or testing:
- Developer refreshes page multiple times
- Automated tests hit the limit immediately
- Makes debugging very difficult

---

## Impact Assessment

### User Experience Impact: SEVERE

**Symptoms:**
- ❌ Users can't complete bookings
- ❌ Error message is vague ("wait a few minutes")
- ❌ No indication of how long to wait
- ❌ No way to know how many requests remain
- ❌ Legitimate users are blocked

**Business Impact:**
- Lost bookings
- Frustrated customers
- Negative reviews
- Support tickets

---

## Recommended Solutions

### Option 1: Increase Limits (Quick Fix)

**Recommended Settings:**

```php
/**
 * Rate limit window in seconds (15 minutes)
 */
const RATE_LIMIT_WINDOW = 900;

/**
 * Maximum requests per window
 * Increased to allow normal user behavior:
 * - 10-15 availability checks (checking different dates)
 * - 3-5 booking attempts (form validation errors)
 * - Buffer for page refreshes
 */
const MAX_REQUESTS_PER_WINDOW = 30;  // Changed from 10 to 30
```

**Pros:**
- Simple one-line change
- Allows normal user behavior
- Still protects against abuse

**Cons:**
- Doesn't solve shared IP problem
- Still blocks legitimate users in some scenarios

---

### Option 2: Separate Limits by Endpoint (Better)

```php
/**
 * Rate limits per endpoint
 */
const AVAILABILITY_LIMIT = 50;  // Allow many date checks
const BOOKING_LIMIT = 5;        // Stricter on actual submissions

private function is_rate_limited($endpoint = 'general')
{
    $user_ip = $this->get_client_ip();
    $cache_key = self::RATE_LIMIT_KEY . $endpoint . '_' . $user_ip;
    
    $max_requests = ($endpoint === 'booking') 
        ? self::BOOKING_LIMIT 
        : self::AVAILABILITY_LIMIT;
    
    $request_count = get_transient($cache_key);
    
    if ($request_count === false) {
        set_transient($cache_key, 1, self::RATE_LIMIT_WINDOW);
        return false;
    }
    
    if ($request_count >= $max_requests) {
        return true;
    }
    
    set_transient($cache_key, $request_count + 1, self::RATE_LIMIT_WINDOW);
    return false;
}

// Then in methods:
public function get_availability()
{
    if ($this->is_rate_limited('availability')) {
        wp_send_json_error('Too many availability checks. Please wait a moment.');
    }
    // ...
}

public function create_booking()
{
    if ($this->is_rate_limited('booking')) {
        wp_send_json_error('Too many booking attempts. Please wait 15 minutes.');
    }
    // ...
}
```

**Pros:**
- Allows many availability checks (browsing behavior)
- Strict on actual booking submissions (prevents spam)
- Better user experience

**Cons:**
- More complex
- Still has shared IP issue

---

### Option 3: User Session-Based Limiting (Best)

```php
/**
 * Get rate limit key based on user session
 */
private function get_rate_limit_key($endpoint)
{
    // Try to use WordPress session/user ID first
    if (is_user_logged_in()) {
        return self::RATE_LIMIT_KEY . $endpoint . '_user_' . get_current_user_id();
    }
    
    // For anonymous users, use a combination of IP + User Agent
    // This helps differentiate users behind same IP
    $user_ip = $this->get_client_ip();
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    $fingerprint = md5($user_ip . $user_agent);
    
    return self::RATE_LIMIT_KEY . $endpoint . '_' . $fingerprint;
}

private function is_rate_limited($endpoint = 'general')
{
    $cache_key = $this->get_rate_limit_key($endpoint);
    
    $max_requests = ($endpoint === 'booking') ? 5 : 50;
    
    $request_count = get_transient($cache_key);
    
    if ($request_count === false) {
        set_transient($cache_key, 1, self::RATE_LIMIT_WINDOW);
        return false;
    }
    
    if ($request_count >= $max_requests) {
        // Log for monitoring
        error_log("Antigravity Booking: Rate limit hit for $endpoint by " . $this->get_client_ip());
        return true;
    }
    
    set_transient($cache_key, $request_count + 1, self::RATE_LIMIT_WINDOW);
    return false;
}
```

**Pros:**
- Solves shared IP problem
- Better user tracking
- Allows logged-in users more flexibility
- More accurate rate limiting

**Cons:**
- Most complex solution
- User agent can be spoofed (but good enough for legitimate use)

---

### Option 4: Remove Rate Limiting for Availability Checks (Pragmatic)

```php
public function get_availability()
{
    // NO RATE LIMITING for availability checks
    // These are read-only operations with minimal server impact
    
    check_ajax_referer('antigravity_booking_nonce', 'nonce');
    // ... rest of code
}

public function create_booking()
{
    // KEEP rate limiting for booking submissions
    if ($this->is_rate_limited('booking')) {
        wp_send_json_error('Too many booking attempts. Please wait 15 minutes.');
    }
    
    check_ajax_referer('antigravity_booking_nonce', 'nonce');
    // ... rest of code
}
```

**Pros:**
- Simplest solution
- Solves the immediate problem
- Availability checks are low-impact (just database reads)
- Nonce protection still prevents CSRF

**Cons:**
- No protection against availability check spam
- Could be abused for reconnaissance

---

## Recommended Implementation

### Immediate Fix (Deploy Today)

**Option 1: Increase limits to 30**

This is the quickest fix that will immediately solve the problem for most users.

```php
// Line 25 in class-antigravity-booking-api.php
const MAX_REQUESTS_PER_WINDOW = 30;  // Changed from 10
```

### Short-term Fix (This Week)

**Option 2: Separate limits by endpoint**

Implement different limits for availability vs booking:
- Availability: 50 requests per 15 minutes
- Booking: 5 requests per 15 minutes

### Long-term Fix (Next Version)

**Option 3: Session-based limiting**

Implement user fingerprinting to avoid shared IP issues.

---

## Testing Plan

### Before Fix
1. Open booking form
2. Check availability for 5 different dates
3. Refresh page 2-3 times
4. Try to submit booking
5. **Expected:** Should hit rate limit around 10th request

### After Fix (Option 1)
1. Repeat same test
2. **Expected:** Should complete successfully
3. Try 30+ requests
4. **Expected:** Should hit limit at 31st request

### After Fix (Option 2)
1. Check availability 20 times
2. **Expected:** Should work fine
3. Try to submit booking 6 times
4. **Expected:** Should block on 6th booking attempt

---

## Monitoring Recommendations

Add logging to track rate limit hits:

```php
if ($request_count >= $max_requests) {
    error_log(sprintf(
        'Antigravity Booking: Rate limit hit - IP: %s, Endpoint: %s, Count: %d',
        $this->get_client_ip(),
        $endpoint,
        $request_count
    ));
    return true;
}
```

This will help you:
- Identify if limits are still too low
- Detect actual abuse attempts
- Understand user behavior patterns

---

## Configuration Recommendation

Make rate limits configurable in settings:

```php
// In settings page
add_settings_field(
    'antigravity_booking_rate_limit',
    'Rate Limit (requests per 15 min)',
    array($this, 'render_rate_limit_field'),
    'antigravity-booking-settings',
    'antigravity_booking_general'
);

// In API class
const MAX_REQUESTS_PER_WINDOW = get_option('antigravity_booking_rate_limit', 30);
```

This allows you to:
- Adjust limits without code changes
- Test different values
- Respond to abuse quickly

---

## Summary

**Current State:**
- ❌ Rate limit: 10 requests per 15 minutes
- ❌ Blocks legitimate users
- ❌ Shared IP problem
- ❌ No differentiation between endpoints

**Recommended State:**
- ✅ Availability checks: 50 requests per 15 minutes
- ✅ Booking submissions: 5 requests per 15 minutes
- ✅ Session-based tracking (not just IP)
- ✅ Configurable limits
- ✅ Better error messages

**Priority:** CRITICAL - Fix immediately

**Estimated Fix Time:** 
- Option 1 (increase limit): 5 minutes
- Option 2 (separate limits): 30 minutes
- Option 3 (session-based): 1-2 hours

---

## Code Changes Required

### Immediate Fix (5 minutes)

**File:** `includes/class-antigravity-booking-api.php`

**Line 25:**
```php
// BEFORE:
const MAX_REQUESTS_PER_WINDOW = 10;

// AFTER:
const MAX_REQUESTS_PER_WINDOW = 30;
```

**That's it!** This single line change will fix the immediate problem.

---

## Additional Recommendations

1. **Add Rate Limit Headers** (for debugging):
   ```php
   header('X-RateLimit-Limit: ' . self::MAX_REQUESTS_PER_WINDOW);
   header('X-RateLimit-Remaining: ' . ($max_requests - $request_count));
   header('X-RateLimit-Reset: ' . (time() + self::RATE_LIMIT_WINDOW));
   ```

2. **Better Error Messages**:
   ```php
   $remaining_time = ceil(self::RATE_LIMIT_WINDOW / 60);
   wp_send_json_error("Too many requests. Please wait $remaining_time minutes before trying again.");
   ```

3. **Whitelist Admin Users**:
   ```php
   if (current_user_can('manage_options')) {
       return false; // Admins bypass rate limiting
   }
   ```

---

**End of Rate Limiting Analysis**

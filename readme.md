=== Antigravity Booking ===
Contributors: Antigravity
Tags: booking, schedule, appointments, calendar, availability
Stable tag: 1.1.0
Requires at least: 5.0
Tested up to: 6.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Custom booking plugin with time blocks, cost estimation, and Google Calendar sync.

== Description ==

A robust, secure, and user-friendly booking plugin for WordPress. Perfect for service-based businesses, consultants, and anyone who needs to manage appointments efficiently.

**Features:**

* **Easy Booking Flow**: Intuitive 3-step process (select date → select time → fill form)
* **Real-time Availability**: Check availability instantly without page reloads
* **Cost Estimation**: Automatic cost calculation based on hourly rate
* **Overnight Bookings**: Support for overnight stays with special pricing
* **Email Notifications**: Automated emails for submissions, approvals, and reminders
* **Google Calendar Sync**: Sync approved bookings to Google Calendar
* **Admin Dashboard**: Comprehensive booking management interface
* **Mobile Responsive**: Works perfectly on all devices
* **Accessibility**: Full keyboard navigation and screen reader support

**Security & Performance:**

* Rate limiting on API endpoints
* Comprehensive input validation
* CSRF protection
* Enhanced error handling
* Optimized database queries
* Caching support

**New in v1.1.0:**

* ✅ Rate limiting (10 requests per 15 minutes)
* ✅ Enhanced input validation
* ✅ Real-time form validation
* ✅ Mobile-responsive design
* ✅ Accessibility features (ARIA labels, keyboard nav)
* ✅ Improved error messages
* ✅ Loading states
* ✅ Better error handling and logging

== Installation ==

1. Upload the `antigravity-booking` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **Simplified Booking > Settings** to configure
4. Add the shortcode `[antigravity_booking_calendar]` to any page or post

**Manual Installation:**

1. Download the plugin ZIP file
2. Extract the contents
3. Upload the `antigravity-booking` folder to `/wp-content/plugins/`
4. Activate the plugin in WordPress admin

== Configuration ==

**Basic Setup:**

1. Go to **Simplified Booking > Settings**
2. Set your **Hourly Rate** (e.g., 100)
3. Configure **Business Hours** for each day
4. Set **Booking Cutoff Time** (hours before booking)
5. Configure **Timezone**

**Email Notifications:**

1. Set **Admin Notification Email**
2. Customize **Customer Instructions**
3. Configure **Reminder Settings** (1 week and 48 hours before)
4. Customize **Approval Email** template

**Google Calendar Integration (Optional):**

1. Install Google API Client: `composer require google/apiclient:^2.0`
2. Create Google Service Account (see GOOGLE_CALENDAR_SETUP.md)
3. Upload credentials JSON file path in settings
4. Set Calendar ID
5. Choose which statuses to sync

**Shortcode Options:**

Basic usage:
```
[antigravity_booking_calendar]
```

The plugin automatically detects the shortcode and loads only necessary assets.

== Frequently Asked Questions ==

= How do I set business hours? =

Go to **Simplified Booking > Settings > Availability Settings**. You can configure different hours for each day of the week.

= Can I block specific dates? =

Yes! In **Availability Settings**, add dates to the "Blackout Dates" field (one date per line, format: YYYY-MM-DD).

= How does overnight pricing work? =

Overnight bookings (starting after 10 PM) are charged at 12x the hourly rate and extend until 10 AM the next day. You can configure which days allow overnight bookings in the settings.

= Can I customize email templates? =

Yes! All email templates are customizable in the settings page. You can use placeholders like {customer_name}, {start_date}, {end_date}, {cost}.

= Is the plugin GDPR compliant? =

The plugin only collects necessary booking information (name, email, phone, guest count, event description). All data is stored in your WordPress database. You should include this in your privacy policy.

= How do I handle booking conflicts? =

The plugin automatically checks for overlapping bookings. If a slot is already booked, it won't appear as available. Admins can manage bookings in the dashboard.

= Can I export bookings? =

Yes! Use the "Export to CSV" button in the bookings dashboard to export filtered bookings.

= What happens if a booking is pending? =

Pending bookings only block time slots if they're within the booking cutoff window (default: 48 hours). This allows flexibility for approval workflows.

== Upgrade Notice ==

= 1.1.0 =
Major security and UX improvements. Please update for enhanced protection and better user experience.

== Changelog ==

= 1.1.0 (2026-01-21) =
* **Security:**
  * Added rate limiting to API endpoints (10 requests per 15 minutes)
  * Enhanced input validation for dates, times, emails, and phone numbers
  * Improved CSRF protection
  * Added capability checks for admin actions
* **Performance:**
  * Optimized database queries
  * Added request timeout handling (10 seconds)
  * Improved error handling and logging
* **User Experience:**
  * Added real-time form validation
  * Improved error messages with actionable guidance
  * Added loading states with spinner animation
  * Enhanced mobile responsiveness (touch-friendly targets)
  * Added success state management with auto-redirect
* **Accessibility:**
  * Added ARIA labels and roles throughout
  * Implemented keyboard navigation (Tab, Enter, Escape)
  * Added focus management
  * High contrast mode support
* **Code Quality:**
  * Added comprehensive PHPDoc blocks
  * Enhanced error logging
  * Added validation helper methods
  * Improved code organization
* **Frontend:**
  * Updated CSS with mobile-first responsive design
  * Added loading spinner styles
  * Improved form styling
  * Better focus states for accessibility

= 1.0.0 (Initial Release) =
* Basic booking functionality
* Availability checking
* Email notifications
* Google Calendar integration
* Admin dashboard
* Settings page

== Support ==

For support, please refer to the IMPLEMENTATION_GUIDE.md file included with the plugin.

**Documentation:**
* [Implementation Guide](IMPLEMENTATION_GUIDE.md)
* [Google Calendar Setup](GOOGLE_CALENDAR_SETUP.md)

**Common Issues:**
1. **Rate limiting errors**: Wait 15 minutes and try again
2. **Google Calendar sync**: Ensure Google API Client is installed
3. **Emails not sending**: Install SMTP plugin (e.g., WP Mail SMTP)
4. **Form validation errors**: Check all required fields

**Debug Mode:**
Add to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check logs at: `wp-content/debug.log`

== Credits ==

* Flatpickr date picker: https://flatpickr.js.org/
* Google API Client Library for PHP: https://github.com/googleapis/google-api-php-client

== Translations ==

* English (default)

To contribute translations, please contact the plugin author.

== Privacy Policy ==

This plugin collects and stores the following information when a booking is made:
* Customer name
* Customer email
* Customer phone (optional)
* Guest count
* Event description
* Booking dates and times

This data is stored in your WordPress database and is only accessible to users with appropriate permissions (administrators and editors).

The plugin does not:
* Send data to external servers (except Google Calendar if configured)
* Use cookies for tracking
* Collect analytics data

For Google Calendar integration, data is sent to Google's servers according to their privacy policy.

For more information, see our [Privacy Policy](https://example.com/privacy-policy).

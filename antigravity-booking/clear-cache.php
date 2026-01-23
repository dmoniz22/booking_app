<?php
/**
 * Cache Clearing Utility
 * 
 * Upload this file to: wp-content/plugins/antigravity-booking/
 * Access via: yoursite.com/wp-content/plugins/antigravity-booking/clear-cache.php
 * 
 * This will clear all WordPress caches and force reload of plugin files.
 */

// Find WordPress
$wp_load_paths = array(
    __DIR__ . '/../../../../wp-load.php',
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../wp-load.php',
);

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die('Could not find WordPress. Please run this from the plugin directory.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Clear Cache - Antigravity Booking</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background: #f0f0f1;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1d2327;
            border-bottom: 2px solid #2271b1;
            padding-bottom: 10px;
        }
        .result {
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .success {
            background: #f0f6fc;
            border-left: 4px solid #00a32a;
            color: #00a32a;
        }
        .info {
            background: #f6f7f7;
            border-left: 4px solid #2271b1;
        }
        code {
            background: #f0f0f1;
            padding: 2px 6px;
            border-radius: 3px;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background: #2271b1;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 10px 5px;
        }
        .button:hover {
            background: #135e96;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧹 Clear Cache - Antigravity Booking</h1>
        
        <?php
        $cleared = array();
        
        // Clear WordPress object cache
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
            $cleared[] = 'WordPress Object Cache';
        }
        
        // Clear transients related to our plugin
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_antigravity_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_antigravity_%'");
        $cleared[] = 'Plugin Transients';
        
        // Clear rate limiting transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_antigravity_rate_limit_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_antigravity_rate_limit_%'");
        $cleared[] = 'Rate Limiting Cache';
        
        // Try to clear opcache if available
        if (function_exists('opcache_reset')) {
            opcache_reset();
            $cleared[] = 'PHP OpCache';
        }
        
        // Clear rewrite rules
        flush_rewrite_rules();
        $cleared[] = 'Rewrite Rules';
        
        ?>
        
        <div class="result success">
            <h2>✅ Cache Cleared Successfully</h2>
            <p>The following caches have been cleared:</p>
            <ul>
                <?php foreach ($cleared as $item): ?>
                    <li><?php echo $item; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <div class="result info">
            <h3>📝 What This Did</h3>
            <ul>
                <li><strong>WordPress Object Cache:</strong> Cleared all cached data</li>
                <li><strong>Plugin Transients:</strong> Removed temporary data stored by the plugin</li>
                <li><strong>Rate Limiting Cache:</strong> Reset all rate limit counters</li>
                <?php if (function_exists('opcache_reset')): ?>
                    <li><strong>PHP OpCache:</strong> Forced PHP to reload all files</li>
                <?php else: ?>
                    <li><strong>PHP OpCache:</strong> Not available (may need server restart)</li>
                <?php endif; ?>
                <li><strong>Rewrite Rules:</strong> Refreshed WordPress URL routing</li>
            </ul>
        </div>
        
        <div class="result info">
            <h3>🔄 Next Steps</h3>
            <ol>
                <li><strong>Clear your browser cache:</strong> Press Ctrl+Shift+Delete (or Cmd+Shift+Delete on Mac)</li>
                <li><strong>Test Google Calendar:</strong> Go to Settings > Google Calendar > Test Connection</li>
                <li><strong>Test booking approval:</strong> Create and approve a test booking</li>
                <li><strong>If issues persist:</strong> Check error logs at <code>wp-content/debug.log</code></li>
            </ol>
        </div>
        
        <?php if (!function_exists('opcache_reset')): ?>
        <div class="result info" style="border-left-color: #dba617;">
            <h3>⚠️ PHP OpCache Not Cleared</h3>
            <p>PHP OpCache could not be cleared automatically. You may need to:</p>
            <ul>
                <li>Contact your hosting provider to restart PHP-FPM</li>
                <li>Or restart your web server</li>
                <li>Or wait 5-10 minutes for cache to expire</li>
            </ul>
            <p><strong>This is likely why the fixes aren't working yet!</strong></p>
        </div>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="<?php echo admin_url('admin.php?page=antigravity-booking-settings'); ?>" class="button">Go to Settings</a>
            <a href="<?php echo admin_url('admin.php?page=antigravity-booking'); ?>" class="button">Go to Dashboard</a>
            <a href="diagnostic-check.php" class="button">Run Diagnostic Again</a>
        </div>
        
        <hr style="margin: 40px 0;">
        <p style="text-align: center; color: #666;">
            <small>Cache Clearing Utility v1.0 | Antigravity Booking v1.1.13</small>
        </p>
    </div>
</body>
</html>

<?php
/**
 * Diagnostic Check Script for Antigravity Booking v1.1.13
 * 
 * Upload this file to wp-content/plugins/antigravity-booking/
 * Then access it via: yoursite.com/wp-content/plugins/antigravity-booking/diagnostic-check.php
 * 
 * This will help identify if the fixes were properly applied.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // Allow direct access for diagnostic purposes
    define('DIAGNOSTIC_MODE', true);
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Antigravity Booking - Diagnostic Check</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            max-width: 1200px;
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
        h2 {
            color: #2271b1;
            margin-top: 30px;
        }
        .check {
            padding: 15px;
            margin: 10px 0;
            border-left: 4px solid #ccc;
            background: #f6f7f7;
        }
        .check.pass {
            border-left-color: #00a32a;
            background: #f0f6fc;
        }
        .check.fail {
            border-left-color: #d63638;
            background: #fcf0f1;
        }
        .check.warning {
            border-left-color: #dba617;
            background: #fcf9e8;
        }
        .status {
            font-weight: bold;
            margin-right: 10px;
        }
        .pass .status { color: #00a32a; }
        .fail .status { color: #d63638; }
        .warning .status { color: #dba617; }
        code {
            background: #f0f0f1;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: Consolas, Monaco, monospace;
        }
        pre {
            background: #1d2327;
            color: #f0f0f1;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
        .file-content {
            max-height: 300px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Antigravity Booking - Diagnostic Check</h1>
        <p><strong>Purpose:</strong> This diagnostic script checks if v1.1.13 fixes were properly applied.</p>
        
        <?php
        $checks = array();
        $all_pass = true;
        
        // Check 1: Version Number
        $main_file = __DIR__ . '/antigravity-booking.php';
        if (file_exists($main_file)) {
            $content = file_get_contents($main_file);
            if (preg_match('/Version:\s*([0-9.]+)/', $content, $matches)) {
                $version = $matches[1];
                if ($version === '1.1.13') {
                    $checks[] = array(
                        'status' => 'pass',
                        'title' => 'Plugin Version',
                        'message' => "Version is correct: <code>$version</code>"
                    );
                } else {
                    $checks[] = array(
                        'status' => 'fail',
                        'title' => 'Plugin Version',
                        'message' => "Version is <code>$version</code> but should be <code>1.1.13</code>. The new files may not have uploaded correctly."
                    );
                    $all_pass = false;
                }
            }
        }
        
        // Check 2: Rate Limiting Fix
        $api_file = __DIR__ . '/includes/class-antigravity-booking-api.php';
        if (file_exists($api_file)) {
            $content = file_get_contents($api_file);
            if (preg_match('/const\s+MAX_REQUESTS_PER_WINDOW\s*=\s*(\d+)/', $content, $matches)) {
                $limit = $matches[1];
                if ($limit == 30) {
                    $checks[] = array(
                        'status' => 'pass',
                        'title' => 'Rate Limiting Fix',
                        'message' => "Rate limit is correct: <code>$limit</code> requests per 15 minutes"
                    );
                } else {
                    $checks[] = array(
                        'status' => 'fail',
                        'title' => 'Rate Limiting Fix',
                        'message' => "Rate limit is <code>$limit</code> but should be <code>30</code>. Fix not applied."
                    );
                    $all_pass = false;
                }
            }
        }
        
        // Check 3: Output Buffering in Dashboard
        $dashboard_file = __DIR__ . '/includes/class-antigravity-booking-dashboard.php';
        if (file_exists($dashboard_file)) {
            $content = file_get_contents($dashboard_file);
            $has_ob_start = strpos($content, 'ob_start()') !== false;
            $has_ob_clean = strpos($content, 'ob_end_clean()') !== false;
            
            if ($has_ob_start && $has_ob_clean) {
                $checks[] = array(
                    'status' => 'pass',
                    'title' => 'Output Buffering Fix (Dashboard)',
                    'message' => "Output buffering code found in dashboard file"
                );
            } else {
                $checks[] = array(
                    'status' => 'fail',
                    'title' => 'Output Buffering Fix (Dashboard)',
                    'message' => "Output buffering code NOT found. Fix not applied."
                );
                $all_pass = false;
            }
        }
        
        // Check 4: Google Calendar Init Method
        $gcal_file = __DIR__ . '/includes/class-antigravity-booking-google-calendar.php';
        if (file_exists($gcal_file)) {
            $content = file_get_contents($gcal_file);
            $has_init_method = strpos($content, 'public function init()') !== false;
            
            if ($has_init_method) {
                $checks[] = array(
                    'status' => 'pass',
                    'title' => 'Google Calendar Init Method',
                    'message' => "Init method found in Google Calendar class"
                );
            } else {
                $checks[] = array(
                    'status' => 'fail',
                    'title' => 'Google Calendar Init Method',
                    'message' => "Init method NOT found. Fix not applied."
                );
                $all_pass = false;
            }
        }
        
        // Check 5: Output Buffering in Settings AJAX
        $settings_file = __DIR__ . '/includes/class-antigravity-booking-settings.php';
        if (file_exists($settings_file)) {
            $content = file_get_contents($settings_file);
            // Check if ob_start is in ajax_test_gcal_connection method
            if (preg_match('/function\s+ajax_test_gcal_connection.*?ob_start\(\)/s', $content)) {
                $checks[] = array(
                    'status' => 'pass',
                    'title' => 'Output Buffering Fix (Settings AJAX)',
                    'message' => "Output buffering found in AJAX test handler"
                );
            } else {
                $checks[] = array(
                    'status' => 'fail',
                    'title' => 'Output Buffering Fix (Settings AJAX)',
                    'message' => "Output buffering NOT found in AJAX handler. Fix not applied."
                );
                $all_pass = false;
            }
        }
        
        // Check 6: Google API Client Library
        $vendor_autoload = __DIR__ . '/vendor/autoload.php';
        if (file_exists($vendor_autoload)) {
            $checks[] = array(
                'status' => 'pass',
                'title' => 'Google API Client Library',
                'message' => "Composer vendor directory exists"
            );
            
            // Try to load and check for Google_Client
            require_once $vendor_autoload;
            if (class_exists('Google_Client')) {
                $checks[] = array(
                    'status' => 'pass',
                    'title' => 'Google_Client Class',
                    'message' => "Google_Client class is available"
                );
            } else {
                $checks[] = array(
                    'status' => 'fail',
                    'title' => 'Google_Client Class',
                    'message' => "Google_Client class NOT found. Composer dependencies may not be installed."
                );
                $all_pass = false;
            }
        } else {
            $checks[] = array(
                'status' => 'fail',
                'title' => 'Google API Client Library',
                'message' => "Vendor directory NOT found. Run <code>composer install</code> in plugin directory."
            );
            $all_pass = false;
        }
        
        // Check 7: WordPress Environment
        if (defined('ABSPATH')) {
            $checks[] = array(
                'status' => 'pass',
                'title' => 'WordPress Environment',
                'message' => "Running within WordPress"
            );
        } else {
            $checks[] = array(
                'status' => 'warning',
                'title' => 'WordPress Environment',
                'message' => "Not running within WordPress (diagnostic mode)"
            );
        }
        
        // Display Results
        ?>
        
        <h2>📊 Diagnostic Results</h2>
        
        <?php if ($all_pass): ?>
            <div class="check pass">
                <span class="status">✅ ALL CHECKS PASSED</span>
                <p>All v1.1.13 fixes appear to be properly applied.</p>
            </div>
        <?php else: ?>
            <div class="check fail">
                <span class="status">❌ SOME CHECKS FAILED</span>
                <p>One or more fixes were not properly applied. See details below.</p>
            </div>
        <?php endif; ?>
        
        <h2>🔍 Detailed Checks</h2>
        
        <?php foreach ($checks as $check): ?>
            <div class="check <?php echo $check['status']; ?>">
                <div>
                    <span class="status">
                        <?php 
                        echo $check['status'] === 'pass' ? '✅' : 
                             ($check['status'] === 'fail' ? '❌' : '⚠️'); 
                        ?>
                        <?php echo strtoupper($check['status']); ?>
                    </span>
                    <strong><?php echo $check['title']; ?></strong>
                </div>
                <p><?php echo $check['message']; ?></p>
            </div>
        <?php endforeach; ?>
        
        <h2>🔧 Troubleshooting Steps</h2>
        
        <?php if (!$all_pass): ?>
            <div class="check warning">
                <h3>If checks failed:</h3>
                <ol>
                    <li><strong>Re-upload the plugin:</strong> The files may not have uploaded correctly</li>
                    <li><strong>Clear all caches:</strong> WordPress object cache, page cache, browser cache</li>
                    <li><strong>Check file permissions:</strong> Ensure files are readable (644 for files, 755 for directories)</li>
                    <li><strong>Verify ZIP extraction:</strong> Make sure the ZIP file was fully extracted</li>
                </ol>
            </div>
        <?php endif; ?>
        
        <h2>📝 Next Steps</h2>
        
        <?php if ($all_pass): ?>
            <div class="check pass">
                <p><strong>All fixes are applied correctly.</strong> If you're still experiencing issues:</p>
                <ol>
                    <li><strong>Check error logs:</strong> <code>wp-content/debug.log</code></li>
                    <li><strong>Enable WordPress debug mode</strong> in <code>wp-config.php</code></li>
                    <li><strong>Test Google Calendar:</strong> Go to Settings > Google Calendar > Test Connection</li>
                    <li><strong>Test booking approval:</strong> Create and approve a test booking</li>
                </ol>
            </div>
        <?php else: ?>
            <div class="check fail">
                <p><strong>Please re-upload the plugin files.</strong> The fixes were not properly applied.</p>
                <ol>
                    <li>Download <code>antigravity-booking-v1.1.13.zip</code> again</li>
                    <li>Deactivate the plugin in WordPress</li>
                    <li>Delete the plugin folder via FTP/SFTP</li>
                    <li>Upload the new ZIP file via WordPress Admin</li>
                    <li>Activate the plugin</li>
                    <li>Run this diagnostic again</li>
                </ol>
            </div>
        <?php endif; ?>
        
        <h2>📄 File Locations</h2>
        <pre><?php
        echo "Plugin Directory: " . __DIR__ . "\n";
        echo "Main File: " . ($main_file ?? 'Not found') . "\n";
        echo "API File: " . ($api_file ?? 'Not found') . "\n";
        echo "Dashboard File: " . ($dashboard_file ?? 'Not found') . "\n";
        echo "Google Calendar File: " . ($gcal_file ?? 'Not found') . "\n";
        echo "Settings File: " . ($settings_file ?? 'Not found') . "\n";
        echo "Vendor Autoload: " . ($vendor_autoload ?? 'Not found') . "\n";
        ?></pre>
        
        <hr style="margin: 40px 0;">
        <p style="text-align: center; color: #666;">
            <small>Diagnostic Check v1.0 | Antigravity Booking v1.1.13</small>
        </p>
    </div>
</body>
</html>

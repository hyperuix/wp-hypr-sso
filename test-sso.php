<?php
/**
 * HYPR SSO Test Script
 * Place this in your WordPress root directory and access it via browser
 * to test if the SSO plugin is working correctly
 */

// Load WordPress for Bedrock setup
$wp_config_path = __DIR__ . '/../../../../wp-config.php';
if (file_exists($wp_config_path)) {
    require_once($wp_config_path);
} else {
    // Try alternative paths for different Bedrock setups
    $alternative_paths = [
        __DIR__ . '/../../../wp-config.php',
        __DIR__ . '/../../../../../../wp-config.php',
        dirname(__DIR__) . '/../../../../wp-config.php'
    ];
    
    $loaded = false;
    foreach ($alternative_paths as $path) {
        if (file_exists($path)) {
            require_once($path);
            $loaded = true;
            break;
        }
    }
    
    if (!$loaded) {
        die('Could not find wp-config.php. Please check the file path.');
    }
}

echo "<h1>HYPR SSO Plugin Test</h1>";

// Check if plugin class exists
if (class_exists('HYPR_SSO_Authentication')) {
    echo "<p style='color: green;'>✅ HYPR SSO Plugin class found</p>";
} else {
    echo "<p style='color: red;'>❌ HYPR SSO Plugin class not found</p>";
}

// Check if user exists
$user = get_user_by('login', 'hypradmin');
if ($user) {
    echo "<p style='color: green;'>✅ User 'hypradmin' exists (ID: {$user->ID})</p>";
    echo "<p>User role: " . implode(', ', $user->roles) . "</p>";
    echo "<p>User meta: " . get_user_meta($user->ID, 'hypr_sso_user', true) . "</p>";
} else {
    echo "<p style='color: orange;'>⚠️ User 'hypradmin' does not exist yet</p>";
}

// Test server connectivity
echo "<h2>Server Connectivity Test</h2>";
$auth_url = 'https://hypr:hypr@hosting.hyperuix.com.au/scripts/sso/hypr-sso.php';

$response = wp_remote_post($auth_url, array(
    'body' => array(
        'action' => 'authenticate',
        'username' => 'hypradmin',
        'password' => 'hypradmin',
        'site_url' => get_site_url(),
        'timestamp' => time()
    ),
    'timeout' => 30,
    'sslverify' => true
));

if (is_wp_error($response)) {
    echo "<p style='color: red;'>❌ Server communication error: " . $response->get_error_message() . "</p>";
} else {
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    echo "<p>Server response: " . htmlspecialchars($body) . "</p>";
    
    if ($data && isset($data['success'])) {
        if ($data['success']) {
            echo "<p style='color: green;'>✅ Server authentication successful</p>";
        } else {
            echo "<p style='color: red;'>❌ Server authentication failed: " . ($data['message'] ?? 'Unknown error') . "</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Invalid server response format</p>";
    }
}

// Check WordPress debug log
echo "<h2>Debug Information</h2>";
if (defined('WP_DEBUG') && WP_DEBUG) {
    echo "<p style='color: green;'>✅ WordPress debug mode is enabled</p>";
    
    $debug_log = WP_CONTENT_DIR . '/debug.log';
    if (file_exists($debug_log)) {
        echo "<p>Debug log exists at: " . $debug_log . "</p>";
        echo "<p>Debug log size: " . filesize($debug_log) . " bytes</p>";
        
        // Show last 10 lines of debug log
        $lines = file($debug_log);
        if ($lines) {
            echo "<h3>Last 10 debug log entries:</h3>";
            echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 200px; overflow-y: auto;'>";
            $last_lines = array_slice($lines, -10);
            foreach ($last_lines as $line) {
                echo htmlspecialchars($line);
            }
            echo "</pre>";
        }
    } else {
        echo "<p style='color: orange;'>⚠️ Debug log file not found</p>";
    }
} else {
    echo "<p style='color: orange;'>⚠️ WordPress debug mode is disabled</p>";
    echo "<p>To enable debug mode, add these lines to wp-config.php:</p>";
    echo "<pre>define('WP_DEBUG', true);\ndefine('WP_DEBUG_LOG', true);</pre>";
}

echo "<h2>Plugin Installation Check</h2>";
$mu_plugins_dir = WPMU_PLUGIN_DIR;
if (is_dir($mu_plugins_dir)) {
    echo "<p>mu-plugins directory: " . $mu_plugins_dir . "</p>";
    
    $plugin_file = $mu_plugins_dir . '/hypr-sso.php';
    if (file_exists($plugin_file)) {
        echo "<p style='color: green;'>✅ Plugin file found at: " . $plugin_file . "</p>";
        echo "<p>File size: " . filesize($plugin_file) . " bytes</p>";
        echo "<p>Last modified: " . date('Y-m-d H:i:s', filemtime($plugin_file)) . "</p>";
    } else {
        echo "<p style='color: red;'>❌ Plugin file not found at: " . $plugin_file . "</p>";
        echo "<p>Current test script location: " . __DIR__ . "</p>";
        echo "<p>Expected plugin location: " . $plugin_file . "</p>";
    }
} else {
    echo "<p style='color: red;'>❌ mu-plugins directory not found</p>";
    echo "<p>Expected mu-plugins directory: " . $mu_plugins_dir . "</p>";
}

// Additional Bedrock-specific checks
echo "<h3>Bedrock Structure Check</h3>";
$bedrock_paths = [
    'web/app/mu-plugins/hypr-sso/hypr-sso.php',
    'web/app/plugins/hypr-sso/hypr-sso.php',
    'app/mu-plugins/hypr-sso/hypr-sso.php',
    'mu-plugins/hypr-sso/hypr-sso.php'
];

$root_dir = dirname(dirname(dirname(dirname(__DIR__))));
echo "<p>Root directory: " . $root_dir . "</p>";

foreach ($bedrock_paths as $path) {
    $full_path = $root_dir . '/' . $path;
    if (file_exists($full_path)) {
        echo "<p style='color: green;'>✅ Found plugin at: " . $full_path . "</p>";
    } else {
        echo "<p style='color: gray;'>❌ Not found: " . $full_path . "</p>";
    }
}

echo "<hr>";
echo "<p><strong>Next steps:</strong></p>";
echo "<ol>";
echo "<li>If the plugin class is not found, ensure the plugin file is in the correct mu-plugins directory</li>";
echo "<li>If server connectivity fails, check the server URL and credentials</li>";
echo "<li>Enable debug mode to see detailed logs</li>";
echo "<li>Try logging in with username 'hypradmin' and password 'hypradmin'</li>";
echo "</ol>"; 
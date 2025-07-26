<?php
/**
 * Plugin Name: HYPR SSO Authentication
 * Description: Single Sign-On authentication for HYPR admin access
 * Version: 1.0.0
 * Author: HYPR
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class HYPR_SSO_Authentication {
    
    private $auth_server_url = 'https://hypr:hypr@hosting.hyperuix.com.au/scripts/sso/hypr-sso.php';
    private $admin_username = 'hypradmin';
    
    public function __construct() {
        // Hook into authentication with high priority
        add_filter('authenticate', array($this, 'authenticate_user'), 1, 3);
        
        // Also hook into user lookup to handle non-existent users
        add_filter('authenticate', array($this, 'handle_nonexistent_user'), 1, 3);
        
        // Hook into login form processing
        add_action('wp_authenticate', array($this, 'pre_authenticate'), 1, 2);
        
        add_action('wp_login', array($this, 'on_successful_login'), 10, 2);
        add_action('init', array($this, 'init_plugin'));
    }
    
    /**
     * Initialize plugin
     */
    public function init_plugin() {
        // Add debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('HYPR SSO: Plugin initialized');
        }
    }
    
    /**
     * Handle authentication for HYPR SSO users
     */
    public function authenticate_user($user, $username, $password) {
        // Only process if username is hypradmin
        if ($username !== $this->admin_username) {
            return $user;
        }
        
        // Add debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('HYPR SSO: Attempting authentication for username: ' . $username);
        }
        
        // Check if authentication is successful
        if ($this->authenticate_with_server($username, $password)) {
            // Get or create the user with the password
            $user = $this->get_or_create_user($username, $password);
            if ($user) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('HYPR SSO: Authentication successful for user: ' . $username);
                }
                return $user;
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('HYPR SSO: Authentication failed for user: ' . $username);
        }
        
        return new WP_Error('authentication_failed', 'HYPR SSO authentication failed');
    }
    
    /**
     * Handle non-existent users for HYPR SSO
     */
    public function handle_nonexistent_user($user, $username, $password) {
        // Only process if username is hypradmin and user doesn't exist
        if ($username !== $this->admin_username) {
            return $user;
        }
        
        // Check if this is a WP_Error about user not existing
        if (is_wp_error($user) && $user->get_error_code() === 'invalid_username') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('HYPR SSO: User does not exist, attempting SSO authentication for: ' . $username);
            }
            
            // Try to authenticate with server
            if ($this->authenticate_with_server($username, $password)) {
                // Create the user with the password
                $new_user = $this->get_or_create_user($username, $password);
                if ($new_user) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('HYPR SSO: Created and authenticated user: ' . $username);
                    }
                    return $new_user;
                }
            }
        }
        
        return $user;
    }
    
    /**
     * Pre-authentication hook to ensure user exists
     */
    public function pre_authenticate($username, $password) {
        // Only process if username is hypradmin
        if ($username !== $this->admin_username) {
            return;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('HYPR SSO: Pre-authentication check for: ' . $username);
        }
        
        // Check if user exists, if not, create them
        $user = get_user_by('login', $username);
        if (!$user) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('HYPR SSO: User does not exist, creating: ' . $username);
            }
            
            // Create the user with the password (we'll authenticate later)
            $this->get_or_create_user($username, $password);
        }
    }
    
    /**
     * Authenticate credentials with remote server
     */
    private function authenticate_with_server($username, $password) {
        $response = wp_remote_post($this->auth_server_url, array(
            'body' => array(
                'action' => 'authenticate',
                'username' => $username,
                'password' => $password,
                'site_url' => get_site_url(),
                'timestamp' => time()
            ),
            'timeout' => 30,
            'sslverify' => true
        ));
        
        if (is_wp_error($response)) {
            error_log('HYPR SSO: Server communication error: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data && isset($data['success']) && $data['success'] === true) {
            return true;
        }
        
        error_log('HYPR SSO: Authentication failed - ' . ($data['message'] ?? 'Unknown error'));
        return false;
    }
    
    /**
     * Get or create WordPress user
     */
    private function get_or_create_user($username, $password = null) {
        $user = get_user_by('login', $username);
        
        if (!$user) {
            // Use the provided password or generate a random one
            $user_password = $password ?: wp_generate_password();
            
            // Create new user with proper email
            $user_id = wp_create_user($username, $user_password, 'secure-sso@hyperuix.com.au');
            
            if (is_wp_error($user_id)) {
                error_log('HYPR SSO: Failed to create user: ' . $user_id->get_error_message());
                return false;
            }
            
            // Set user role to administrator
            $user = new WP_User($user_id);
            $user->set_role('administrator');
            
            // Update user profile information
            wp_update_user(array(
                'ID' => $user_id,
                'user_url' => 'https://www.hyperuix.com.au',
                'display_name' => 'HYPR Admin',
                'first_name' => 'HYPR',
                'last_name' => 'Admin'
            ));
            
            // Update user meta
            update_user_meta($user_id, 'hypr_sso_user', true);
            update_user_meta($user_id, 'hypr_sso_created', current_time('mysql'));
            
            error_log('HYPR SSO: Created new user: ' . $username . ' with password: ' . ($password ? 'provided' : 'generated'));
        }
        
        return $user;
    }
    
    /**
     * Log successful login
     */
    public function on_successful_login($user_login, $user) {
        if ($user_login === $this->admin_username) {
            error_log('HYPR SSO: Successful login for ' . $user_login . ' from IP: ' . $_SERVER['REMOTE_ADDR']);
        }
    }
}

// Initialize the plugin
if (!function_exists('hypr_sso_init')) {
    function hypr_sso_init() {
        new HYPR_SSO_Authentication();
    }
    add_action('plugins_loaded', 'hypr_sso_init');
}

// Also initialize immediately for mu-plugins
new HYPR_SSO_Authentication();

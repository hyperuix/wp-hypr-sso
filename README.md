# HYPR SSO Authentication System

A WordPress mu-plugin that provides single sign-on authentication for HYPR admin access across multiple client websites.

## Overview

This system consists of two main components:

1. **WordPress mu-plugin** (`hypr-sso.php`) - Installed on client WordPress sites
2. **Server authentication script** (`server-auth.php`) - Hosted on your authentication server

## Features

- Single sign-on authentication using 'hypradmin' credentials
- Automatic WordPress user creation with administrator privileges
- Secure communication between client sites and authentication server
- Comprehensive logging of authentication attempts
- Protection against replay attacks
- Domain-based access control

## Installation

### 1. WordPress mu-plugin Installation

## Installation of Plugin via Composer

```bash
# 1. Get it ready (to use a repo outside of packagist)
composer config repositories.wp-hypr-sso git https://github.com/hyperuix/wp-hypr-sso.git

# 2. Install the Plugin - we want all updates from this major version (while non-breaking)
composer require hyperuix/wp-hypr-sso
```

1. Copy `hypr-sso.php` to your WordPress site's `wp-content/mu-plugins/` directory
2. If the `mu-plugins` directory doesn't exist, create it
3. The plugin will be automatically loaded by WordPress

**Note**: mu-plugins are loaded before regular plugins and cannot be disabled from the WordPress admin.

### 2. Server Authentication Script Installation

1. Upload `server-auth.php` to your server at: `hosting.hyperuix.com.au/scripts/sso/hypr-sso.php`
2. Ensure the script directory is writable for logging
3. Configure the authentication method in the script

## Configuration

### Server Authentication Script Configuration

Edit the `authenticate_credentials()` function in `server-auth.php` to implement your preferred authentication method:

#### Option 1: Simple Password Authentication (Default)
```php
function authenticate_credentials($username, $password, $site_url) {
    $expected_username = 'hypradmin';
    $expected_password = 'hypradmin'; // Change this to a secure password
    
    if ($username !== $expected_username || $password !== $expected_password) {
        return ['success' => false, 'message' => 'Invalid credentials'];
    }
    
    return ['success' => true, 'message' => 'Authentication successful'];
}
```

#### Option 2: Database Authentication
```php
function authenticate_credentials($username, $password, $site_url) {
    $db = new PDO('mysql:host=localhost;dbname=your_db', 'username', 'password');
    
    $stmt = $db->prepare('SELECT password_hash FROM users WHERE username = ? AND active = 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Invalid credentials'];
    }
    
    return ['success' => true, 'message' => 'Authentication successful'];
}
```

#### Option 3: File-based Authentication
Create a JSON file with credentials:
```json
{
    "hypradmin": "your_secure_password"
}
```

### Domain Access Control

Configure allowed domains in the server script:

```php
$allowed_domains = [
    'client1.com',
    'client2.com',
    'client3.com'
    // Add your client domains here
];
```

## Usage

1. Navigate to any client WordPress site's login page
2. Enter username: `hypradmin`
3. Enter password: `hypradmin` (or your configured password)
4. The system will authenticate against your server and log you in

## Security Features

- **Timestamp validation**: Prevents replay attacks (5-minute tolerance)
- **Domain validation**: Only allows authentication from authorized domains
- **SSL verification**: Ensures secure communication
- **Comprehensive logging**: Tracks all authentication attempts
- **Error handling**: Graceful failure handling and logging

## Logging

### WordPress Site Logs
Authentication attempts are logged to the WordPress error log:
- `wp-content/debug.log` (if WP_DEBUG_LOG is enabled)
- Server error logs

### Server Authentication Logs
Authentication attempts are logged to `auth_log.txt` in the script directory:
```
[2024-01-15 10:30:45] SUCCESS - Username: hypradmin, Site: https://client1.com, IP: 192.168.1.100
[2024-01-15 10:31:12] FAILED - Username: hypradmin, Site: https://client2.com, IP: 192.168.1.101
```

## Troubleshooting

### Common Issues

1. **Plugin not loading**: Ensure the file is in `wp-content/mu-plugins/` directory
2. **Authentication fails**: Check server script URL and credentials
3. **SSL errors**: Verify SSL certificate on authentication server
4. **Permission errors**: Ensure log directory is writable

### Debug Mode

Enable WordPress debug logging by adding to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Testing

Test the authentication by:
1. Attempting login with correct credentials
2. Checking server logs for authentication attempts
3. Verifying user creation in WordPress admin

## Customization

### Changing Default Username
Edit the `$admin_username` property in the WordPress plugin:
```php
private $admin_username = 'your_admin_username';
```

### Changing User Role
Modify the user creation in the WordPress plugin:
```php
$user->set_role('editor'); // or 'author', 'contributor', etc.
```

### Adding Additional Security
Consider implementing:
- IP whitelisting
- Two-factor authentication
- Rate limiting
- API key authentication

## Support

For issues or questions:
1. Check the logs for error messages
2. Verify server connectivity
3. Test authentication credentials
4. Review domain access control settings

## Security Recommendations

1. **Change default password**: Update the password in the server script
2. **Use HTTPS**: Ensure all communication uses SSL/TLS
3. **Regular audits**: Review authentication logs regularly
4. **Backup credentials**: Keep secure backups of authentication data
5. **Monitor access**: Set up alerts for failed authentication attempts
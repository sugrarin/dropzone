# Security Recommendations

## Important Security Notes

### 🔐 Password Security

1. **Change the default password immediately** after installation
2. Use a strong password with at least 12 characters
3. Include uppercase, lowercase, numbers, and special characters
4. Never commit your password hash to public repositories

### 🌐 HTTPS

**Always use HTTPS in production.** File links are not encrypted over HTTP.

To enable HTTPS:
- Get a free SSL certificate from [Let's Encrypt](https://letsencrypt.org/)
- Or use your hosting provider's SSL option
- Update `.htaccess` to force HTTPS:

```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### 📁 File Access Control

**Important:** Files in `/s/` directory are publicly accessible by anyone with the link.

- Do not upload sensitive/confidential documents without additional encryption
- Consider adding IP restrictions if needed
- Regularly review uploaded files

### 🔒 Additional Security Measures

#### IP Whitelist (Optional)

Add to `.htaccess` to restrict admin access by IP:

```apache
<Files "index.php">
    Order Deny,Allow
    Deny from all
    Allow from 123.456.789.0
    Allow from 192.168.1.0/24
</Files>

<Files "api.php">
    Order Deny,Allow
    Deny from all
    Allow from 123.456.789.0
    Allow from 192.168.1.0/24
</Files>
```

#### Session Security

The application uses PHP sessions with these settings:
- Session-based authentication
- Secure logout that destroys sessions
- Progressive lockout after failed attempts

Consider adding to `auth.php`:

```php
// Add session timeout (optional)
ini_set('session.gc_maxlifetime', 3600); // 1 hour
session_set_cookie_params(3600);
```

#### File Upload Validation

Current validation:
- ✅ File size limit (100MB default)
- ✅ Unique file IDs
- ❌ No file type restrictions

To add file type restrictions, edit `api.php`:

```php
$allowedExtensions = ['pdf', 'jpg', 'png', 'doc', 'docx', 'txt'];
$extension = pathinfo($originalName, PATHINFO_EXTENSION);

if (!in_array(strtolower($extension), $allowedExtensions)) {
    echo json_encode(['success' => false, 'error' => 'File type not allowed']);
    exit;
}
```

### 🗄️ Backup

**Regular backups are essential:**

```bash
# Backup data directory
tar -czf backup-$(date +%Y%m%d).tar.gz data/ s/

# Or use rsync
rsync -av data/ s/ /path/to/backup/
```

### 🔍 Monitoring

Monitor these files for suspicious activity:
- `data/files.json` - Check for unusual uploads
- Apache access logs - Monitor for brute force attempts
- Apache error logs - Check for errors

### ⚠️ Known Limitations

1. **No file encryption** - Files are stored as-is
2. **No user management** - Single password for all users
3. **No audit log** - No tracking of who uploaded what
4. **Public file access** - Anyone with link can download

### 🛡️ Best Practices

- [ ] Use HTTPS
- [ ] Change default password
- [ ] Set strong password
- [ ] Enable regular backups
- [ ] Monitor access logs
- [ ] Keep PHP updated
- [ ] Restrict file types if needed
- [ ] Consider IP whitelist for admin
- [ ] Don't upload sensitive files
- [ ] Review uploaded files regularly

## Reporting Security Issues

If you find a security vulnerability, please report it via GitHub issues.

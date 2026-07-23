# Production Subdomain Setup Guide

This guide explains how to deploy and configure subdomains for your multi-tenant CRM application on a live/production server.

## Prerequisites

- Domain name (e.g., `yourdomain.com`)
- Server with root/sudo access
- Web server configured (Nginx or Apache)
- SSL certificate (wildcard or multi-domain recommended)

---

## Step 1: DNS Configuration

You need to configure DNS to support wildcard subdomains. This allows any subdomain (e.g., `nard.yourdomain.com`, `company2.yourdomain.com`) to point to your server.

### Add Wildcard DNS Record

In your domain's DNS management panel (Cloudflare, GoDaddy, Namecheap, etc.), add:

**Type**: `A` Record (or `CNAME` if using a subdomain)
**Name/Host**: `*` (wildcard)
**Value/Target**: Your server's IP address (or domain if using CNAME)
**TTL**: 3600 (or default)

**Example**:
```
Type: A
Name: *
Value: 192.0.2.100
TTL: 3600
```

**Note**: Some DNS providers use `@` or leave the field empty for the root domain. Use `*` specifically for wildcard subdomains.

### Verify DNS Configuration

After adding the DNS record, verify it's working:

```bash
# Check if wildcard resolves
dig *.yourdomain.com
nslookup *.yourdomain.com

# Test specific subdomain
dig nard.yourdomain.com
nslookup nard.yourdomain.com
```

**DNS propagation can take 24-48 hours**, but usually works within a few minutes to an hour.

---

## Step 2: SSL Certificate Setup

For subdomains to work with HTTPS, you need a valid SSL certificate.

### Option A: Wildcard SSL Certificate (Recommended)

A wildcard certificate covers all subdomains: `*.yourdomain.com`

**Using Let's Encrypt (Free)**:
```bash
# Install Certbot
sudo apt update
sudo apt install certbot python3-certbot-nginx  # For Nginx
# or
sudo apt install certbot python3-certbot-apache # For Apache

# Generate wildcard certificate
sudo certbot certonly --manual --preferred-challenges dns -d *.yourdomain.com -d yourdomain.com

# Follow the prompts and add TXT record to DNS when requested
# Certbot will provide instructions
```

**Using Paid Wildcard Certificate**:
- Purchase from providers like DigiCert, GoDaddy, etc.
- Follow their installation instructions

### Option B: Multi-Domain SSL Certificate

If you know all subdomains in advance, you can use a multi-domain (SAN) certificate.

---

## Step 3: Web Server Configuration

### Nginx Configuration

Create or edit your Nginx server block:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name yourdomain.com *.yourdomain.com;
    
    # Redirect HTTP to HTTPS
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name yourdomain.com *.yourdomain.com;

    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    
    # SSL Settings (recommended)
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    root /var/www/yourdomain.com/public;
    index index.php index.html index.htm;

    # Logs
    access_log /var/log/nginx/yourdomain.com-access.log;
    error_log /var/log/nginx/yourdomain.com-error.log;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

**Important Notes**:
- Replace `yourdomain.com` with your actual domain
- Update PHP-FPM socket path if different (check with `php-fpm -v`)
- Update `root` path to your Laravel `public` directory
- Restart Nginx after changes: `sudo systemctl restart nginx`

### Apache Configuration

Create or edit your Apache virtual host:

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    ServerAlias *.yourdomain.com
    Redirect permanent / https://yourdomain.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName yourdomain.com
    ServerAlias *.yourdomain.com
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/yourdomain.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/yourdomain.com/privkey.pem

    DocumentRoot /var/www/yourdomain.com/public

    <Directory /var/www/yourdomain.com/public>
        AllowOverride All
        Require all granted
    </Directory>

    # PHP Configuration
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/var/run/php/php8.2-fpm.sock|fcgi://localhost"
    </FilesMatch>

    ErrorLog ${APACHE_LOG_DIR}/yourdomain.com-error.log
    CustomLog ${APACHE_LOG_DIR}/yourdomain.com-access.log combined
</VirtualHost>
```

**Important Notes**:
- Enable required modules: `sudo a2enmod ssl rewrite proxy_fcgi`
- Update PHP-FPM socket path if different
- Update `DocumentRoot` to your Laravel `public` directory
- Restart Apache: `sudo systemctl restart apache2`

---

## Step 4: Laravel Configuration

### Update Environment Variables

Edit your `.env` file on the production server:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Session Configuration (important for subdomains)
SESSION_DOMAIN=.yourdomain.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

# Cookie Configuration
SANCTUM_STATEFUL_DOMAINS=yourdomain.com,*.yourdomain.com
```

**Critical Settings**:
- `SESSION_DOMAIN=.yourdomain.com` - The leading dot allows cookies to work across all subdomains
- `SESSION_SECURE_COOKIE=true` - Required for HTTPS
- `APP_URL` - Set to your main domain (without subdomain)

### Verify Middleware Code

The `IdentifyCompanyBySubdomain` middleware should already work correctly in production. It extracts subdomains from the host automatically.

The middleware logic at lines 73-79 handles production domains:
```php
$parts = explode('.', $host);
if (count($parts) <= 2) {
    return null; // Main domain (yourdomain.com)
}
return strtolower($parts[0]); // Subdomain (nard from nard.yourdomain.com)
```

---

## Step 5: Update URL Building Logic (If Needed)

Check that your controllers build URLs correctly. The `buildSubdomainUrl` method in `LoginController` and `RegisterController` should work, but verify:

```php
private function buildSubdomainUrl(string $subdomain, string $path = '/'): string
{
    $baseUrl = config('app.url'); // Should be https://yourdomain.com
    $parsed = parse_url($baseUrl);
    $host = $subdomain . '.' . ($parsed['host'] ?? 'yourdomain.com');
    
    $scheme = $parsed['scheme'] ?? 'https';
    return "{$scheme}://{$host}{$path}";
}
```

---

## Step 6: Database Migration

Ensure your database has the subdomain column:

```bash
php artisan migrate
```

Verify companies have subdomains:
```bash
php artisan tinker
>>> App\Models\Company::pluck('subdomain');
```

---

## Step 7: Cache Configuration

Clear and optimize Laravel caches:

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Cache for production (recommended)
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Step 8: Testing

### Test Main Domain
```
https://yourdomain.com
```
Should show login/register page.

### Test Subdomain
```
https://nard.yourdomain.com
```
Should show company-specific dashboard (if company exists with subdomain "nard").

### Test Registration Flow
1. Register at `https://yourdomain.com/register`
2. Should create company with subdomain
3. Should redirect to `https://{subdomain}.yourdomain.com/`

### Test Login Flow
1. Login at `https://yourdomain.com/login`
2. Should redirect to `https://{subdomain}.yourdomain.com/dashboard`
3. Or login directly at `https://{subdomain}.yourdomain.com/`

### Test Logout
1. Logout should clear sessions
2. Should redirect to appropriate login page

---

## Step 9: Security Considerations

### Session Security
- ✅ `SESSION_SECURE_COOKIE=true` - Cookies only sent over HTTPS
- ✅ `SESSION_DOMAIN=.yourdomain.com` - Shared across subdomains
- ✅ `SESSION_HTTP_ONLY=true` - Prevents JavaScript access (default)

### CSRF Protection
Laravel's CSRF protection works automatically across subdomains when `SESSION_DOMAIN` is configured correctly.

### Rate Limiting
Consider adding rate limiting for login/registration endpoints:
```php
// In routes/web.php
Route::post('/login', [LoginController::class, 'login'])
    ->middleware('throttle:5,1'); // 5 attempts per minute
```

---

## Troubleshooting

### Subdomain Returns 404

**Check**:
1. DNS propagation - `dig nard.yourdomain.com`
2. Company exists in database with matching subdomain
3. Web server configuration includes wildcard `*.yourdomain.com`
4. Nginx/Apache error logs: `sudo tail -f /var/log/nginx/error.log`

### SSL Certificate Errors

**Check**:
1. Certificate includes wildcard: `*.yourdomain.com`
2. Certificate is valid and not expired
3. Web server SSL configuration points to correct certificate paths
4. Test with: `openssl s_client -connect nard.yourdomain.com:443`

### Sessions Not Working Across Subdomains

**Check**:
1. `.env` has `SESSION_DOMAIN=.yourdomain.com` (with leading dot)
2. `SESSION_SECURE_COOKIE=true` for HTTPS
3. Clear browser cookies and try again
4. Check browser console for cookie errors

### Cookies Not Shared

**Solutions**:
- Ensure `SESSION_DOMAIN` starts with a dot: `.yourdomain.com`
- Verify `SESSION_SECURE_COOKIE` matches your protocol (true for HTTPS)
- Check browser doesn't block third-party cookies

---

## Common Issues

### Issue: "Company not found" Error

**Solution**: Verify the company exists in database:
```sql
SELECT id, name, subdomain FROM companies WHERE subdomain = 'nard';
```

### Issue: Redirects to HTTP Instead of HTTPS

**Solution**: 
1. Ensure web server redirects HTTP to HTTPS
2. Set `APP_URL=https://yourdomain.com` in `.env`
3. Clear config cache: `php artisan config:clear`

### Issue: Mixed Content Warnings

**Solution**: Ensure all assets use HTTPS:
- Update `APP_URL` to use `https://`
- Check asset URLs in views
- Use `secure_asset()` helper for assets

---

## Recommended Production Settings

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_DOMAIN=.yourdomain.com
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
```

---

## Additional Resources

- [Laravel Documentation - Configuration](https://laravel.com/docs/configuration)
- [Nginx Documentation](https://nginx.org/en/docs/)
- [Apache Documentation](https://httpd.apache.org/docs/)
- [Let's Encrypt Documentation](https://letsencrypt.org/docs/)
- [DNS Wildcard Records](https://en.wikipedia.org/wiki/Wildcard_DNS_record)

---

## Support

If you encounter issues, check:
1. Laravel logs: `storage/logs/laravel.log`
2. Web server logs: `/var/log/nginx/` or `/var/log/apache2/`
3. PHP error logs: Check `php.ini` for `error_log` location
4. Browser console for JavaScript errors
5. Network tab for failed requests


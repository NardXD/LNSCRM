# Fix for 413 "Content Too Large" Error

The 413 error occurs when the screen recording file exceeds the server's maximum POST size limit. Here are the solutions in order of preference:

## Quick Fixes Already Applied

1. **Reduced Recording Quality** (Already implemented)
   - Resolution reduced from 1920x1080 to 1280x720
   - Bitrate reduced to 2.5 Mbps (from default ~8-10 Mbps)
   - This should reduce file size by approximately 60-70%

2. **Apache .htaccess Configuration** (Already added)
   - Added PHP directives to `public/.htaccess`
   - Note: This only works if using Apache with mod_php

## Server Configuration Required

### For Apache Servers

The `.htaccess` file has been updated, but if it doesn't work, you may need to configure PHP directly:

1. **Edit php.ini** (location varies):
   ```ini
   upload_max_filesize = 100M
   post_max_size = 100M
   max_execution_time = 300
   max_input_time = 300
   ```

2. **Restart Apache**:
   ```bash
   sudo systemctl restart apache2
   # or
   sudo service apache2 restart
   ```

### For Nginx Servers

If using Nginx, the `.htaccess` file won't work. You need to:

1. **Edit nginx configuration** (usually `/etc/nginx/nginx.conf` or site-specific config):
   ```nginx
   http {
       client_max_body_size 100M;
       # ... other config
   }
   ```

2. **Also check PHP-FPM configuration**:
   ```ini
   upload_max_filesize = 100M
   post_max_size = 100M
   ```

3. **Restart services**:
   ```bash
   sudo systemctl restart nginx
   sudo systemctl restart php8.2-fpm  # Adjust version as needed
   ```

## Verify Configuration

After making changes, verify PHP settings:

```bash
php -i | grep -E "(upload_max_filesize|post_max_size|max_execution_time)"
```

Or create a test PHP file:
```php
<?php
phpinfo();
```

Look for:
- `upload_max_filesize` should be 100M
- `post_max_size` should be 100M

## Expected File Sizes

With the quality reduction applied:
- **Before**: ~15-30 MB for 30 seconds at 1080p
- **After**: ~5-10 MB for 30 seconds at 720p with reduced bitrate

The reduced quality should work with most default server configurations (typically 8-16MB limits).

## Alternative: Chunked Upload (Future Enhancement)

If files are still too large, we can implement chunked uploads that break the file into smaller pieces. This is more complex but handles any file size.

## Contact Your Hosting Provider

If you don't have server access, contact your hosting provider to:
1. Increase `post_max_size` to at least 100M
2. Increase `upload_max_filesize` to at least 100M
3. Increase `client_max_body_size` (for Nginx) or equivalent (for Apache)


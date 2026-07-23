# Local Subdomain Setup Guide

This guide explains how to access subdomains locally for testing your multi-tenant CRM application.

## Quick Access (Easiest Method)

Modern browsers support subdomains on `localhost` without any configuration:

### Access your subdomain:
```
http://nard.localhost:8000
```

Replace `nard` with any subdomain you've created, and `8000` with your Laravel development server port.

**No hosts file changes needed!** Just make sure your Laravel server is running:
```bash
php artisan serve
# or
php artisan serve --port=8000
```

---

## Alternative Methods

### Method 1: Using .test Domain (Requires hosts file)

1. **Edit hosts file** (Windows):
   - Open Notepad as Administrator
   - Open file: `C:\Windows\System32\drivers\etc\hosts`
   - Add these lines:
     ```
     127.0.0.1 nard.test
     127.0.0.1 *.test
     ```
   - Save the file

2. **Access your subdomain**:
   ```
   http://nard.test:8000
   ```

### Method 2: Using .local Domain (Requires hosts file)

1. **Edit hosts file** (same as above):
   ```
   127.0.0.1 nard.local
   ```

2. **Access your subdomain**:
   ```
   http://nard.local:8000
   ```

---

## How It Works

1. The `IdentifyCompanyBySubdomain` middleware automatically extracts the subdomain from the URL
2. It looks up the company in the database by subdomain
3. The company is made available throughout your application via:
   - `request('company')` - in controllers/requests
   - `app('company')` - anywhere in the app
   - `App\Providers\AppServiceProvider::currentCompany()` - helper method

## Testing

After setting up, you can access:
- Main domain (no subdomain): `http://localhost:8000` - Shows login/register
- Your subdomain: `http://nard.localhost:8000` - Shows company-specific dashboard

The middleware will:
- ✅ Identify the company from the subdomain
- ✅ Skip subdomain check for admin routes (`/admin/*`)
- ✅ Skip subdomain check for auth routes (`/login`, `/register`)
- ✅ Return 404 if subdomain doesn't exist in database

## Troubleshooting

**Subdomain not working?**
1. Make sure the company exists in database with the subdomain
2. Check that your Laravel server is running
3. Clear your browser cache
4. Try a different browser

**Getting 404?**
- Verify the subdomain exists in the `companies` table
- Check the `subdomain` column value matches exactly (case-insensitive)


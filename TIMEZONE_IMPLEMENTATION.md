# Company Timezone Implementation Guide

## Overview
The application now uses company-specific timezones across all modules. Each company can set their timezone in Company Settings, and all date/time operations will use that timezone.

## How It Works

### 1. **Global Timezone Setting (Middleware)**
- **File**: `app/Http/Middleware/SetCompanyTimezone.php`
- **Registered in**: `bootstrap/app.php`
- **Function**: Automatically sets the application timezone on every request for authenticated users
- **Default**: Falls back to `'America/New_York'` if no company timezone is set

### 2. **TimezoneService**
- **File**: `app/Services/TimezoneService.php`
- **Purpose**: Central service for all timezone operations

**Available Methods:**
```php
// Get company timezone
TimezoneService::getCompanyTimezone(): string

// Set application timezone
TimezoneService::setApplicationTimezone(): void

// Convert date to company timezone
TimezoneService::toCompanyTimezone($dateTime, $fromTimezone = null): Carbon

// Get current date/time in company timezone
TimezoneService::now(): Carbon

// Get today's date in company timezone
TimezoneService::today(): Carbon

// Format a date using company timezone
TimezoneService::format($dateTime, string $format = 'Y-m-d H:i:s'): string
```

### 3. **Blade Directives**
Available in all Blade templates:

```blade
{{-- Format a date --}}
@companyDate($record->created_at)

{{-- Get current date/time --}}
@companyNow('Y-m-d H:i:s')
```

## Modules Updated

### ✅ Time Tracking Module
- **Controller**: `TimeTrackingController`
- **Status**: Fully updated
- **Uses**: TimezoneService for all date/time operations

### ✅ Payroll Module
- **Controller**: `PayrollController`
- **Status**: Updated
- **Uses**: TimezoneService for time formatting

### ✅ Employee Monitoring Module
- **Controller**: `EmployeeMonitoringController`
- **Status**: Updated
- **Uses**: TimezoneService for date filtering and display

### ✅ Project Management Module
- **Controller**: `ProjectManagementController`
- **Status**: Uses global timezone (via middleware)
- **Note**: Date formatting uses Carbon which respects the global timezone

### ✅ Leave Management Module
- **Controller**: `LeaveManagementController`
- **Status**: Uses global timezone (via middleware)

### ✅ User Management Module
- **Controller**: `UserManagementController`
- **Status**: Updated
- **Function**: Saves timezone to `companies` table

## Database Structure

### Companies Table
```sql
ALTER TABLE companies ADD COLUMN timezone VARCHAR(255) DEFAULT 'America/New_York';
```

- Each company has a `timezone` column
- Default value: `'America/New_York'`
- Can be updated via Company Settings page

## Usage Examples

### In Controllers
```php
use App\Services\TimezoneService;

// Get current time
$now = TimezoneService::now();

// Parse a date from request
$date = TimezoneService::toCompanyTimezone($request->date)->format('Y-m-d');

// Format for display
$formatted = TimezoneService::format($record->created_at, 'M d, Y');
```

### In Blade Templates
```blade
{{-- Using Blade directive --}}
@companyDate($record->created_at)

{{-- Using service directly --}}
{{ \App\Services\TimezoneService::format($date, 'Y-m-d H:i:s') }}

{{-- Current date/time --}}
@companyNow('Y-m-d H:i:s')
```

### Automatic (via Middleware)
Since the middleware sets the global timezone, standard Carbon operations will automatically use the company timezone:

```php
// These will use company timezone automatically
Carbon::now()
Carbon::today()
Carbon::parse($date)
```

## Important Notes

1. **Database Storage**: Dates are stored in UTC/database timezone, but displayed in company timezone
2. **User Input**: All user-provided dates/times are converted to company timezone before saving
3. **Display**: All dates/times are converted to company timezone before displaying
4. **Default**: If no timezone is set, defaults to `'America/New_York'`

## Migration Required

Run the migration to add timezone column to companies table:
```bash
php artisan migrate
```

This will:
- Add `timezone` column to `companies` table
- Set default value to `'America/New_York'` for all existing companies

## Testing

To verify timezone is working:
1. Set a company timezone in Company Settings
2. Check that all date/time displays use that timezone
3. Verify time tracking records use the correct timezone
4. Check payroll calculations use company timezone

# Quick Reference Guide - CRM V3

A quick cheat sheet for common development tasks.

## 🚀 Quick Commands

### Setup
```bash
composer install && npm install
php artisan key:generate
php artisan migrate
npm run build
```

### Development
```bash
composer dev                    # Start dev server (server + vite + logs)
php artisan serve               # PHP server only
npm run dev                     # Vite dev server only
```

### Code Style
```bash
./vendor/bin/pint               # Format PHP code
```

---

## 📁 File Locations

| What | Where |
|------|-------|
| Routes | `routes/web.php` |
| Controllers | `app/Http/Controllers/` |
| Models | `app/Models/` |
| Migrations | `database/migrations/` |
| Views | `resources/views/` |
| CSS | `resources/css/app.css` or `resources/views/partials/dashboard-styles.blade.php` |
| JavaScript | `resources/js/app.js` or `resources/views/partials/dashboard-scripts.blade.php` |
| Layout | `resources/views/layouts/app.blade.php` |

---

## 🎨 CSS Variables (Use These!)

```css
--bg-primary: #fafafa;
--bg-card: #ffffff;
--accent: #5f61e6;
--accent-hover: #4f51d6;
--text-primary: #111827;
--text-secondary: #6b7280;
--text-muted: #9ca3af;
--border: #e5e7eb;
```

---

## 🏗️ Creating New Features

### 1. Create Database Table
```bash
php artisan make:migration create_items_table
```
Edit migration → Run: `php artisan migrate`

### 2. Create Model
```bash
php artisan make:model Item -m    # -m creates migration too
```

### 3. Create Controller
```bash
php artisan make:controller ItemController --resource
```

### 4. Add Route
```php
// routes/web.php
Route::get('/items', [ItemController::class, 'index'])->name('items');
Route::post('/items', [ItemController::class, 'store'])->name('items.store');
```

### 5. Create View
```blade
{{-- resources/views/dashboard/items.blade.php --}}
@extends('layouts.app')
@section('title', 'Items')
@section('content')
    <!-- Your content -->
@endsection
```

---

## 📝 Common Code Snippets

### Controller - Index (List)
```php
public function index()
{
    $items = Item::latest()->get();
    return view('dashboard.items', compact('items'));
}
```

### Controller - Store (Create)
```php
public function store(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email',
    ]);
    
    Item::create($validated);
    
    return redirect()->route('items')
        ->with('success', 'Item created successfully.');
}
```

### Controller - Update
```php
public function update(Request $request, Item $item)
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
    ]);
    
    $item->update($validated);
    
    return redirect()->route('items')
        ->with('success', 'Item updated successfully.');
}
```

### Controller - Destroy (Delete)
```php
public function destroy(Item $item)
{
    $item->delete();
    return redirect()->route('items')
        ->with('success', 'Item deleted successfully.');
}
```

### Model - Fillable Fields
```php
protected $fillable = [
    'name',
    'email',
    'status',
];
```

### Model - Relationship (Has Many)
```php
public function items()
{
    return $this->hasMany(Item::class);
}
```

### Model - Relationship (Belongs To)
```php
public function user()
{
    return $this->belongsTo(User::class);
}
```

### Migration - Basic Table
```php
Schema::create('items', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->text('description')->nullable();
    $table->boolean('is_active')->default(true);
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->timestamps();
});
```

---

## 🎨 Frontend Components

### Stat Card
```html
<div class="stat-card">
    <div class="stat-header">
        <span class="stat-label">Label</span>
        <div class="stat-icon blue"><!-- SVG --></div>
    </div>
    <div class="stat-value">123</div>
    <div class="stat-change positive">+10%</div>
</div>
```

### Button
```html
<button class="btn btn-primary">Primary</button>
<button class="btn btn-secondary">Secondary</button>
<button class="btn btn-outline">Outline</button>
```

### Form Input
```html
<div class="form-group">
    <label class="form-label">Label</label>
    <input type="text" class="form-input" name="field" required>
    <span class="form-help">Help text</span>
</div>
```

### Table
```html
<div class="table-container">
    <table class="table">
        <thead>
            <tr><th>Column 1</th><th>Column 2</th></tr>
        </thead>
        <tbody>
            <tr><td>Data 1</td><td>Data 2</td></tr>
        </tbody>
    </table>
</div>
```

### Card
```html
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Title</h3>
    </div>
    <div class="card-body">
        Content here
    </div>
</div>
```

---

## 🔌 AJAX Request (Axios)

```javascript
// POST Request
axios.post('/api/endpoint', {
    field1: 'value1',
    field2: 'value2'
})
.then(response => {
    console.log(response.data);
    // Handle success
})
.catch(error => {
    console.error(error);
    // Handle error
});

// GET Request
axios.get('/api/endpoint')
.then(response => {
    console.log(response.data);
});

// DELETE Request
axios.delete('/api/endpoint/' + id)
.then(response => {
    // Handle success
});
```

### CSRF Token (Already included in layout)
```javascript
// Automatically set via meta tag in layout
axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
```

---

## ✅ Validation Rules

```php
'required'                    // Required field
'string'                      // String
'integer'                     // Integer
'numeric'                     // Numeric
'email'                       // Valid email
'url'                         // Valid URL
'min:8'                       // Minimum 8 characters
'max:255'                     // Maximum 255 characters
'unique:users,email'          // Unique in users table, email column
'confirmed'                   // Must match confirmation field (password_confirmation)
'nullable'                    // Optional field
'boolean'                     // Boolean (true/false)
'date'                        // Valid date
'image'                       // Image file
'mimes:jpeg,png,pdf'         // Allowed file types
```

---

## 🔗 Route Helpers

### Named Routes
```php
// In route file
Route::get('/items', [ItemController::class, 'index'])->name('items');

// In view
<a href="{{ route('items') }}">Link</a>

// In controller
return redirect()->route('items');
```

### Route Parameters
```php
// Route
Route::get('/items/{id}', [ItemController::class, 'show'])->name('items.show');

// View
<a href="{{ route('items.show', $item->id) }}">View</a>

// Controller
public function show($id) { ... }
// or with model binding
public function show(Item $item) { ... }
```

---

## 🗄️ Common Database Queries

```php
// Get all
Item::all();

// Get with conditions
Item::where('status', 'active')->get();

// Get single
Item::find($id);
Item::where('email', $email)->first();

// Create
Item::create(['name' => 'Test']);

// Update
$item->update(['name' => 'Updated']);

// Delete
$item->delete();

// Count
Item::count();

// Latest first
Item::latest()->get();

// Paginate
Item::paginate(15);
```

---

## 🎯 Common Patterns

### Flash Messages
```php
// Set message
return redirect()->route('items')
    ->with('success', 'Item created!')
    ->with('error', 'Something went wrong!');

// Display in view
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
```

### Active Route Highlighting (Sidebar)
```html
<a href="{{ route('items') }}" 
   class="nav-item {{ request()->routeIs('items') ? 'active' : '' }}">
    Items
</a>
```

### Check Authentication
```php
// Controller
if (auth()->check()) {
    // User is logged in
}

// View
@auth
    <!-- Logged in content -->
@endauth

@guest
    <!-- Guest content -->
@endguest
```

### Get Current User
```php
// Controller
$user = auth()->user();

// View
{{ auth()->user()->name }}
```

---

## 📋 Migration Column Types Quick Reference

```php
$table->id()                          // Primary key
$table->string('name')                // VARCHAR(255)
$table->string('slug', 100)          // VARCHAR(100)
$table->text('description')           // TEXT
$table->integer('count')              // INTEGER
$table->bigInteger('amount')          // BIGINT
$table->decimal('price', 8, 2)       // DECIMAL(8,2)
$table->boolean('is_active')          // BOOLEAN
$table->date('published_at')          // DATE
$table->datetime('created_at')        // DATETIME
$table->timestamp('updated_at')       // TIMESTAMP
$table->json('metadata')              // JSON
$table->foreignId('user_id')          // Foreign key
$table->timestamps()                  // created_at, updated_at
$table->softDeletes()                 // deleted_at (soft delete)
$table->nullableTimestamps()          // Nullable timestamps
```

---

## 🛠️ Troubleshooting

### Clear Cache
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Fix Permission Issues (Linux/Mac)
```bash
chmod -R 775 storage bootstrap/cache
```

### Rebuild Assets
```bash
npm run build
# or
npm run dev
```

### Refresh Database
```bash
php artisan migrate:fresh        # Drop all tables and re-run migrations
php artisan migrate:fresh --seed # With seeders
```

---

## 📚 File Structure Reminder

```
views/
├── layouts/
│   └── app.blade.php          # Main layout
├── partials/
│   ├── header.blade.php       # Header component
│   ├── sidebar.blade.php      # Sidebar navigation
│   ├── dashboard-styles.blade.php
│   └── dashboard-scripts.blade.php
├── dashboard/
│   └── [module].blade.php     # Dashboard pages
├── admin/
│   ├── [module].blade.php     # Admin pages
│   ├── partials/
│   │   ├── styles.blade.php   # Admin styles
│   │   ├── scripts.blade.php # Admin scripts
│   │   └── modals.blade.php  # Admin modals
│   └── sections/              # Admin section partials
└── auth/
    ├── login.blade.php
    ├── register.blade.php
    └── forgot-password.blade.php
```

---

---

## 🔐 Admin Control Panel

### Admin Routes
```php
Route::get('/admin-control', ...)->name('admin-control');
Route::get('/admin/billing-management', ...)->name('admin.billing-management');
Route::get('/admin/company-access-control', ...)->name('admin.company-access-control');
Route::get('/admin/system-controls', ...)->name('admin.system-controls');
Route::get('/admin/support-override', ...)->name('admin.support-override');
Route::get('/admin/user-management', ...)->name('admin.user-management');
```

### Admin User Management (No Database)
The admin user management system uses **localStorage** for data persistence:

**Storage Keys:**
- `admin_users` - Admin users data
- `admin_roles` - Roles data
- `admin_permissions` - Permissions data

**JavaScript File:** `public/js/admin-user-management.js`

**Features:**
- Create, edit, delete admin users
- Manage roles with permissions
- Create and manage permissions
- Assign roles to users
- Search and filter users

**Default Data:**
- 2 sample admin users
- 4 default roles (Super Admin, Admin, Manager, Support)
- 12 default permissions

---

**Tip:** Keep this file open while coding for quick reference!


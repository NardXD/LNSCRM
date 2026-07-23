# CRM V3 Development Guide

## Table of Contents
1. [Project Overview](#project-overview)
2. [Technology Stack](#technology-stack)
3. [Project Structure](#project-structure)
4. [Frontend Development Guidelines](#frontend-development-guidelines)
5. [Backend Development Guidelines](#backend-development-guidelines)
6. [Database Development Guidelines](#database-development-guidelines)
7. [Coding Conventions](#coding-conventions)
8. [Common Patterns & Examples](#common-patterns--examples)
9. [Development Workflow](#development-workflow)

---

## Project Overview

This is a comprehensive CRM (Customer Relationship Management) system built with Laravel 12 and modern frontend technologies. The application provides various modules including user management, project management, billing, time tracking, client management, and more.

**Key Features:**
- Multi-module dashboard system
- **Admin Control Panel** with comprehensive management tools
  - Billing Management
  - Company Access Control
  - System Controls
  - Support & Override
  - **Admin User Management** (Roles & Permissions)
- User authentication
- Multiple business modules (projects, billing, clients, etc.)
- Modern, responsive UI

---

## Technology Stack

### Backend
- **Framework:** Laravel 12 (PHP 8.2+)
- **Database:** SQLite (default), MySQL/PostgreSQL supported
- **Authentication:** Laravel's built-in authentication
- **Package Manager:** Composer

### Frontend
- **CSS Framework:** Tailwind CSS 4.0
- **Build Tool:** Vite 7.0
- **JavaScript:** Vanilla JavaScript (ES6+)
- **Styling Approach:** Custom CSS with CSS Variables + Tailwind CSS
- **Font:** Inter (Google Fonts)

### Development Tools
- **Code Style:** Laravel Pint
- **Testing:** PHPUnit
- **Package Manager (Frontend):** npm

---

## Project Structure

```
CRM_V3/
├── app/
│   ├── Http/
│   │   └── Controllers/          # Application controllers
│   ├── Models/                   # Eloquent models
│   └── Providers/                # Service providers
├── database/
│   ├── migrations/               # Database migrations
│   ├── factories/                # Model factories
│   └── seeders/                  # Database seeders
├── public/                       # Public assets
│   └── build/                    # Compiled assets (CSS/JS)
├── resources/
│   ├── css/
│   │   └── app.css              # Main CSS file (Tailwind entry)
│   ├── js/
│   │   ├── app.js               # Main JavaScript file
│   │   └── bootstrap.js         # Bootstrap configuration
│   └── views/
│       ├── admin/                # Admin panel views
│       │   ├── partials/         # Admin partials (styles, scripts, modals)
│       │   └── sections/         # Admin section partials
│       ├── auth/                 # Authentication views
│       ├── dashboard/            # Dashboard module views
│       ├── layouts/
│       │   └── app.blade.php    # Main layout template
│       └── partials/             # Reusable partials
│           ├── header.blade.php
│           ├── sidebar.blade.php
│           ├── dashboard-styles.blade.php
│           └── dashboard-scripts.blade.php
├── routes/
│   └── web.php                  # Web routes
├── config/                      # Configuration files
├── storage/                     # Storage (logs, cache, sessions)
└── tests/                       # Test files
```

---

## Frontend Development Guidelines

### Layout Structure

The application uses a consistent layout structure:

**Main Layout:** `resources/views/layouts/app.blade.php`

All dashboard pages extend this layout:
```php
@extends('layouts.app')

@section('title', 'Page Title')

@section('content')
    <!-- Your page content here -->
@endsection
```

### CSS Architecture

#### 1. CSS Variables (Design System)

The application uses CSS custom properties defined in the layout:

```css
:root {
    --bg-primary: #fafafa;
    --bg-card: #ffffff;
    --accent: #5f61e6;
    --accent-hover: #4f51d6;
    --accent-light: #f0f0ff;
    --text-primary: #111827;
    --text-secondary: #6b7280;
    --text-muted: #9ca3af;
    --border: #e5e7eb;
    --sidebar-bg: #ffffff;
    --sidebar-width: 260px;
    --sidebar-collapsed: 80px;
}
```

**Usage:**
- Always use CSS variables for colors, spacing, and layout values
- Maintain consistency across all pages
- Use semantic variable names

#### 2. Tailwind CSS

Tailwind CSS 4.0 is configured and available. Use utility classes for rapid development:

```html
<div class="bg-white rounded-lg shadow-md p-6">
    <h2 class="text-2xl font-semibold text-gray-800">Title</h2>
</div>
```

#### 3. Custom Styles

Custom styles are defined in:
- `resources/views/partials/dashboard-styles.blade.php` (main dashboard styles)
- `resources/views/admin/partials/styles.blade.php` (admin panel styles)
- Inline `<style>` blocks in layout files (for layout-specific styles)

### Component Patterns

#### 1. Stat Cards

Used for displaying metrics:

```html
<div class="stat-card">
    <div class="stat-header">
        <span class="stat-label">Label</span>
        <div class="stat-icon blue">
            <!-- SVG Icon -->
        </div>
    </div>
    <div class="stat-value">Value</div>
    <div class="stat-change positive">Change text</div>
</div>
```

#### 2. Buttons

```html
<!-- Primary Button -->
<button class="btn btn-primary">Primary Action</button>

<!-- Secondary Button -->
<button class="btn btn-secondary">Secondary Action</button>

<!-- Outline Button -->
<button class="btn btn-outline">Outline</button>
```

#### 3. Forms

```html
<form class="form">
    <div class="form-group">
        <label class="form-label">Field Label</label>
        <input type="text" class="form-input" placeholder="Enter value">
        <span class="form-help">Help text</span>
    </div>
</form>
```

#### 4. Tables

```html
<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>Column 1</th>
                <th>Column 2</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Data 1</td>
                <td>Data 2</td>
            </tr>
        </tbody>
    </table>
</div>
```

#### 5. Cards

```html
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Card Title</h3>
    </div>
    <div class="card-body">
        <!-- Card content -->
    </div>
</div>
```

### JavaScript Patterns

JavaScript files are loaded through:
- `resources/js/app.js` - Main JavaScript file
- `resources/views/partials/dashboard-scripts.blade.php` - Dashboard-specific scripts
- Inline `<script>` tags in views (when needed)

**Common Patterns:**

```javascript
// CSRF Token setup (automatically included in layout)
axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

// Making AJAX requests
axios.post('/api/endpoint', {
    data: value
})
.then(response => {
    // Handle success
})
.catch(error => {
    // Handle error
});
```

### Responsive Design

- **Desktop:** Full sidebar navigation (260px width)
- **Tablet/Mobile:** Collapsible sidebar (80px collapsed, overlay on mobile)
- Use CSS media queries: `@media (max-width: 768px)`

### Icons

- Use inline SVG icons (following the pattern in sidebar.blade.php)
- Icons are from Feather Icons style (stroke-based, 24x24 viewBox)
- Include proper `stroke-width="2"`, `stroke-linecap="round"`, `stroke-linejoin="round"` attributes

---

## Admin Control Panel

### Overview

The Admin Control Panel provides comprehensive administrative tools for managing the CRM system. It includes modules for billing management, company access control, system settings, support operations, and admin user management.

### Admin Panel Structure

**Location:** `resources/views/admin/`

```
admin/
├── index.blade.php                    # Admin dashboard
├── billing-management.blade.php       # Billing management page
├── company-access-control.blade.php   # Company access control
├── system-controls.blade.php         # System controls
├── support-override.blade.php        # Support & override
├── user-management.blade.php         # Admin user management
├── partials/
│   ├── styles.blade.php              # Admin-specific styles
│   ├── scripts.blade.php             # Admin JavaScript
│   └── modals.blade.php              # Reusable modals
└── sections/
    ├── billing-management.blade.php
    ├── company-access-control.blade.php
    ├── system-controls.blade.php
    └── support-override.blade.php
```

### Admin User Management System

**Location:** `resources/views/admin/user-management.blade.php`  
**JavaScript:** `public/js/admin-user-management.js`

#### Features

The Admin User Management system provides:

1. **User Management**
   - Create, edit, and delete admin users
   - Assign roles to users
   - Set user status (active, inactive, suspended)
   - Search and filter users
   - View user statistics

2. **Role Management**
   - Create custom roles
   - Assign permissions to roles
   - Edit role descriptions
   - View role statistics (user count, permission count)
   - Delete roles (with validation to prevent deletion if users are assigned)

3. **Permission Management**
   - Create custom permissions
   - Organize permissions by category (users, roles, permissions, billing, system, other)
   - Edit permission details
   - Delete permissions (with validation to prevent deletion if used in roles)

#### Data Storage

**Important:** This system uses **localStorage** (browser storage) instead of a database. This means:

- ✅ Data persists across page refreshes
- ✅ No database setup required
- ✅ Fast and responsive
- ⚠️ Data is browser-specific (not shared across devices/browsers)
- ⚠️ Data can be cleared by clearing browser storage

**Storage Keys:**
- `admin_users` - Stores admin user data
- `admin_roles` - Stores role data
- `admin_permissions` - Stores permission data

**Default Data:**
The system includes sample data that initializes on first load:
- 2 sample admin users (John Doe, Jane Smith)
- 4 default roles (Super Admin, Admin, Manager, Support)
- 12 default permissions (users.create, users.edit, roles.create, etc.)

#### JavaScript API

**Main Functions:**

```javascript
// Data Management
initializeData()              // Initialize default data
getData(key)                  // Get data from localStorage
saveData(key, data)           // Save data to localStorage
getNextId(key)                // Get next available ID

// User Management
openUserModal(userId)         // Open user create/edit modal
saveUser(event)               // Save user (create or update)
deleteUser(id)                // Delete user
filterUsers()                 // Filter users by search term

// Role Management
openRoleModal(roleId)          // Open role create/edit modal
saveRole(event)                // Save role
deleteRole(id)                // Delete role

// Permission Management
openPermissionModal(permId)   // Open permission create/edit modal
savePermission(event)          // Save permission
deletePermission(id)           // Delete permission

// Rendering
renderUsers()                 // Render users table
renderRoles()                 // Render roles grid
renderPermissions()            // Render permissions grid
updateStats()                 // Update statistics cards
switchTab(tabName)            // Switch between tabs
```

#### Button Styles

The admin panel uses consistent button styling:

**Primary Button:**
```html
<button class="btn-primary">
    <svg>...</svg>
    Button Text
</button>
```

**Secondary Button:**
```html
<button class="btn-secondary">Button Text</button>
```

**Small Button:**
```html
<button class="btn-sm btn-secondary">Small Button</button>
```

**Button Features:**
- Consistent padding and spacing
- Proper icon alignment (18px × 18px for regular, 16px × 16px for small)
- Hover effects with subtle lift animation
- Active states for better feedback
- Responsive wrapping on mobile

#### Admin Styles

**Location:** `resources/views/admin/partials/styles.blade.php`

The admin styles include:
- Consistent button styling
- Modal components
- Table layouts
- Form controls
- Status badges
- Card components
- Responsive design

**CSS Variables Used:**
```css
--bg-primary: #fafafa;
--bg-card: #ffffff;
--accent: #5f61e6;
--accent-hover: #4f51d6;
--accent-light: #f0f0ff;
--text-primary: #111827;
--text-secondary: #6b7280;
--text-muted: #9ca3af;
--border: #e5e7eb;
```

### Admin Navigation

Admin sections are accessible via the sidebar under "Settings" → "Admin Control":

- **Billing Management** - Manage subscription plans and company billing
- **Company Access Control** - Control module access for companies
- **System Controls** - System settings and health monitoring
- **Support & Override** - Support tools and emergency access
- **User Management** - Admin users, roles, and permissions

### Creating New Admin Sections

1. **Create View:**
```bash
# Create admin view
touch resources/views/admin/new-section.blade.php
```

2. **Add Route:**
```php
// routes/web.php
Route::get('/admin/new-section', function () {
    return view('admin.new-section');
})->name('admin.new-section');
```

3. **Add to Sidebar:**
```blade
{{-- resources/views/partials/sidebar.blade.php --}}
<a href="{{ route('admin.new-section') }}" 
   class="nav-subitem {{ request()->routeIs('admin.new-section') ? 'active' : '' }}">
    <svg>...</svg>
    <span class="nav-text">New Section</span>
</a>
```

4. **Include Styles:**
```blade
@push('styles')
    @include('admin.partials.styles')
@endpush
```

5. **Include Scripts:**
```blade
@push('scripts')
    @include('admin.partials.scripts')
@endpush
```

---

## Backend Development Guidelines

### Controller Structure

#### Creating a Controller

```bash
php artisan make:controller ModuleNameController
```

**Example Controller:**

```php
<?php

namespace App\Http\Controllers;

use App\Models\YourModel;
use Illuminate\Http\Request;

class ModuleNameController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $items = YourModel::latest()->get();
        return view('dashboard.module-name', compact('items'));
    }

    /**
     * Store a newly created resource.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'field1' => 'required|string|max:255',
            'field2' => 'required|email',
        ]);

        YourModel::create($validated);

        return redirect()->route('module-name')
            ->with('success', 'Item created successfully.');
    }

    /**
     * Update the specified resource.
     */
    public function update(Request $request, YourModel $item)
    {
        $validated = $request->validate([
            'field1' => 'required|string|max:255',
        ]);

        $item->update($validated);

        return redirect()->route('module-name')
            ->with('success', 'Item updated successfully.');
    }

    /**
     * Remove the specified resource.
     */
    public function destroy(YourModel $item)
    {
        $item->delete();

        return redirect()->route('module-name')
            ->with('success', 'Item deleted successfully.');
    }
}
```

### Routing

**Route Definition (routes/web.php):**

```php
use App\Http\Controllers\ModuleNameController;

// Resource routes
Route::resource('module-name', ModuleNameController::class);

// Or individual routes
Route::get('/module-name', [ModuleNameController::class, 'index'])->name('module-name');
Route::post('/module-name', [ModuleNameController::class, 'store'])->name('module-name.store');
Route::put('/module-name/{item}', [ModuleNameController::class, 'update'])->name('module-name.update');
Route::delete('/module-name/{item}', [ModuleNameController::class, 'destroy'])->name('module-name.destroy');
```

**Route Naming Convention:**
- Use kebab-case: `module-name`
- Use dot notation for nested routes: `admin.billing-management`

### Admin Routes

All admin routes follow the pattern `/admin/{module-name}`:

```php
// Admin Control Panel Routes
Route::get('/admin-control', function () {
    return view('admin.index');
})->name('admin-control');

Route::get('/admin/billing-management', function () {
    return view('admin.billing-management');
})->name('admin.billing-management');

Route::get('/admin/company-access-control', function () {
    return view('admin.company-access-control');
})->name('admin.company-access-control');

Route::get('/admin/system-controls', function () {
    return view('admin.system-controls');
})->name('admin.system-controls');

Route::get('/admin/support-override', function () {
    return view('admin.support-override');
})->name('admin.support-override');

Route::get('/admin/user-management', function () {
    return view('admin.user-management');
})->name('admin.user-management');
```

### Request Validation

Always validate user input:

```php
$validated = $request->validate([
    'name' => 'required|string|max:255',
    'email' => 'required|email|unique:users,email',
    'password' => 'required|string|min:8|confirmed',
    'status' => 'nullable|boolean',
    'amount' => 'required|numeric|min:0',
]);
```

### Response Patterns

**Redirect with Flash Message:**
```php
return redirect()->route('module-name')
    ->with('success', 'Operation completed successfully.');
```

**Return JSON (for AJAX requests):**
```php
return response()->json([
    'success' => true,
    'message' => 'Operation completed',
    'data' => $item
]);
```

**View with Data:**
```php
return view('dashboard.module-name', [
    'items' => $items,
    'stats' => $stats
]);
```

---

## Database Development Guidelines

### Creating Migrations

```bash
php artisan make:migration create_table_name_table
```

**Migration Structure:**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('table_name', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->text('description')->nullable();
            $table->decimal('amount', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes(); // For soft deletes
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
};
```

### Common Column Types

- `$table->id()` - Auto-incrementing primary key
- `$table->string('name')` - VARCHAR
- `$table->text('description')` - TEXT
- `$table->integer('count')` - INTEGER
- `$table->decimal('price', 8, 2)` - DECIMAL
- `$table->boolean('is_active')` - BOOLEAN
- `$table->date('date')` - DATE
- `$table->datetime('created_at')` - DATETIME
- `$table->timestamp('published_at')` - TIMESTAMP
- `$table->json('metadata')` - JSON
- `$table->foreignId('user_id')` - Foreign key (unsignedBigInteger)

### Relationships

**Foreign Keys:**
```php
$table->foreignId('user_id')->constrained()->onDelete('cascade');
// or
$table->unsignedBigInteger('user_id');
$table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
```

### Model Creation

```bash
php artisan make:model ModelName -m  # With migration
```

**Example Model:**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class YourModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'description',
        'amount',
        'is_active',
        'user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
```

### Eloquent Relationships

**One-to-Many:**
```php
// In User model
public function projects()
{
    return $this->hasMany(Project::class);
}

// In Project model
public function user()
{
    return $this->belongsTo(User::class);
}
```

**Many-to-Many:**
```php
// In User model
public function roles()
{
    return $this->belongsToMany(Role::class);
}

// In Role model
public function users()
{
    return $this->belongsToMany(User::class);
}
```

**Has-One:**
```php
public function profile()
{
    return $this->hasOne(Profile::class);
}
```

### Database Seeders

```bash
php artisan make:seeder YourTableSeeder
```

**Example Seeder:**

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\YourModel;

class YourTableSeeder extends Seeder
{
    public function run(): void
    {
        YourModel::factory(10)->create();
    }
}
```

---

## Coding Conventions

### PHP/Laravel

1. **PSR-12 Coding Standards:** Use Laravel Pint (already configured)
   ```bash
   ./vendor/bin/pint
   ```

2. **Naming Conventions:**
   - Classes: `PascalCase` (e.g., `UserController`)
   - Methods: `camelCase` (e.g., `getUserData`)
   - Variables: `camelCase` (e.g., `$userData`)
   - Constants: `UPPER_SNAKE_CASE` (e.g., `MAX_ATTEMPTS`)
   - Database tables: `snake_case`, plural (e.g., `user_profiles`)
   - Database columns: `snake_case` (e.g., `created_at`)

3. **Controller Methods:**
   - Use resource controller methods: `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`

4. **Comments:**
   - Use PHPDoc blocks for classes and methods
   - Comment complex logic, not obvious code

### Blade Templates

1. **File Naming:**
   - Use kebab-case: `user-management.blade.php`
   - Group related views in directories

2. **Blade Syntax:**
   ```blade
   {{-- Comments --}}
   {{ $variable }}
   {!! $html !!}
   @if ($condition)
       ...
   @endif
   @foreach ($items as $item)
       ...
   @endforeach
   ```

3. **Sections:**
   ```blade
   @section('title', 'Page Title')
   @section('content')
       ...
   @endsection
   @stack('scripts')
   @push('scripts')
       <script>...</script>
   @endpush
   ```

### CSS/JavaScript

1. **CSS:**
   - Use CSS variables for design tokens
   - Use Tailwind utilities when appropriate
   - Keep custom styles organized in partials
   - Use semantic class names

2. **JavaScript:**
   - Use ES6+ syntax
   - Use `const` and `let`, avoid `var`
   - Use arrow functions when appropriate
   - Follow consistent indentation (2 or 4 spaces)

---

## Common Patterns & Examples

### Complete CRUD Example

**1. Create Migration:**
```bash
php artisan make:migration create_projects_table
```

**2. Create Model:**
```bash
php artisan make:model Project -m
```

**3. Create Controller:**
```bash
php artisan make:controller ProjectController --resource
```

**4. Add Routes (routes/web.php):**
```php
use App\Http\Controllers\ProjectController;

Route::get('/projects', [ProjectController::class, 'index'])->name('projects');
Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
```

**5. Create View (resources/views/dashboard/projects.blade.php):**
```blade
@extends('layouts.app')

@section('title', 'Projects')

@section('content')
    <div class="page-header">
        <h1 class="page-title">Projects</h1>
        <button class="btn btn-primary" onclick="openCreateModal()">New Project</button>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($projects as $project)
                            <tr>
                                <td>{{ $project->name }}</td>
                                <td>{{ $project->status }}</td>
                                <td>
                                    <button class="btn btn-sm btn-secondary" onclick="editProject({{ $project->id }})">Edit</button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteProject({{ $project->id }})">Delete</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
```

**6. Controller Implementation:**
```php
public function index()
{
    $projects = Project::latest()->get();
    return view('dashboard.projects', compact('projects'));
}

public function store(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'status' => 'required|in:active,completed,pending',
    ]);

    Project::create($validated);

    return redirect()->route('projects')
        ->with('success', 'Project created successfully.');
}
```

### Form Submission with AJAX

**View:**
```blade
<form id="projectForm">
    @csrf
    <input type="text" name="name" id="projectName" required>
    <button type="submit">Submit</button>
</form>

<script>
document.getElementById('projectForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    axios.post('{{ route("projects.store") }}', {
        name: document.getElementById('projectName').value
    })
    .then(response => {
        // Handle success
        window.location.reload();
    })
    .catch(error => {
        // Handle error
        console.error(error);
    });
});
</script>
```

---

## Development Workflow

### Initial Setup

```bash
# Install dependencies
composer install
npm install

# Set up environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Build assets
npm run build
```

### Daily Development

**Start Development Server:**
```bash
# Option 1: Using composer script (recommended)
composer dev

# Option 2: Manual
php artisan serve  # Terminal 1
npm run dev        # Terminal 2
```

**Code Style Checking:**
```bash
./vendor/bin/pint
```

**Running Migrations:**
```bash
# Create migration
php artisan make:migration create_table_name_table

# Run migrations
php artisan migrate

# Rollback last migration
php artisan migrate:rollback

# Reset all migrations
php artisan migrate:reset
```

**Creating Files:**
```bash
# Controller
php artisan make:controller ControllerName

# Model
php artisan make:model ModelName -m

# Migration only
php artisan make:migration create_table_name_table

# Seeder
php artisan make:seeder TableNameSeeder
```

### Building for Production

```bash
# Build assets
npm run build

# Optimize Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Testing

```bash
# Run tests
php artisan test

# Or
./vendor/bin/phpunit
```

---

## Useful Resources

### Laravel Documentation
- [Laravel 12 Docs](https://laravel.com/docs/12.x)
- [Eloquent ORM](https://laravel.com/docs/12.x/eloquent)
- [Blade Templates](https://laravel.com/docs/12.x/blade)
- [Validation](https://laravel.com/docs/12.x/validation)

### Frontend Resources
- [Tailwind CSS Docs](https://tailwindcss.com/docs)
- [Vite Docs](https://vitejs.dev/)
- [Inter Font](https://fonts.google.com/specimen/Inter)

### Tools
- [Laravel Pint](https://laravel.com/docs/12.x/pint) - Code style fixer
- [Laravel Debugbar](https://github.com/barryvdh/laravel-debugbar) - Debug toolbar (optional)

---

## Notes

- Always validate user input on both client and server side
- Use CSRF protection for all forms
- Follow Laravel's security best practices
- Keep controllers thin, business logic in models or services
- Use Eloquent relationships instead of manual joins when possible
- Cache expensive queries when appropriate
- Use migrations for all database changes
- Write tests for critical functionality
- Document complex business logic

---

---

## Recent Updates

### Admin User Management System
- Added comprehensive admin user management with roles and permissions
- Implemented localStorage-based data storage (no database required)
- Added role-based permission system
- Improved button styling and design consistency across admin panel
- Enhanced responsive design for mobile devices

### Design Improvements
- Unified button styles across all admin modules
- Improved form controls with better focus states
- Enhanced modal components
- Better spacing and alignment throughout admin panel
- Consistent icon sizing and positioning

---

**Last Updated:** January 2026
**Version:** 1.1


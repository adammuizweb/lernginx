# lernginx — Complete Feature & Flow Documentation

> **Note**: This file documents the entire application for maintainers. Remove from public repo if desired.

---

## 1. Architecture Overview

| Layer | Technology | Location |
|-------|-----------|----------|
| Web server | Nginx | `/etc/nginx/sites-available/lernginx.lan` |
| PHP | 8.4 (FPM) | `unix:/run/php/php8.4-fpm.sock` |
| Database | MySQL/MariaDB (InnoDB, utf8mb4) | `lernginx` database |
| Frontend | Vanilla PHP, Vanilla JS, CSS | `/var/www/lernginx.lan/public/` |
| Backend | Procedural PHP, PDO | `/var/www/lernginx.lan/app/path/` |

### 1.1 Request Flow

```
Browser → Nginx (port 80)
  ├── /assets/* → static files (cached 7d)
  ├── /*.php → PHP-FPM directly
  ├── /modul/* → rewrite rules → PHP
  └── /* (other) → try_files → @frontend rewrite → /index.php?page={slug}
```

### 1.2 Nginx Rewrite Rules

```
/modul/kategori/{slug}              → 301 redirect to /modul/topic/{slug}
/modul/topic/{slug}/                → /modul/topic/index.php?slug={slug}
/modul/topic/{path}/{slug}/         → /modul/post/detail.php?slug={slug}&cat_path={path}
/dashboard/post/{path}/{slug}/      → /dashboard/index.php?modul=post&cat_path={path}&slug={slug}
/dashboard/topic/{slug}/            → /dashboard/index.php?modul=topic&slug={slug}
/dashboard/programs/                → /dashboard/index.php?modul=programs
/dashboard/programs/{slug}/         → /dashboard/index.php?modul=programs&slug={slug}
/{slug}/                            → /index.php?page={slug}
```

Nginx config (`/etc/nginx/sites-available/lernginx.lan`) uses four location blocks:

| Location | Purpose |
|----------|---------|
| `~ \.php$` | Pass all PHP files to PHP-FPM |
| `/assets/` | Static file caching (7d, immutable) |
| `/modul/` | Module & post clean URLs via rewrites |
| `/dashboard/` | Dashboard sub-routes (post, topic, programs) then fall through to `@frontend` |
| `/` (default) | `try_files $uri $uri/ @frontend` — static files or frontend router |
| `@frontend` | Named location: rewrites `/{slug}/` → `index.php?page={slug}` |

**Priority order for `/dashboard/`:** Rewrite rules are evaluated before `try_files`, so `/dashboard/post/{path}/{slug}/` matches first. Then `try_files` checks for existing directories (e.g., `/dashboard/admin/`, `/dashboard/profile/`). Unmatched URLs fall through to `@frontend`.

**Apache equivalent** in `public/dashboard/.htaccess`:
```apache
RewriteRule ^post/(.+)/([^/]+)/?$ index.php?modul=post&cat_path=$1&slug=$2 [L,QSA]
RewriteRule ^topic/([^/]+)/?$ index.php?modul=topic&slug=$1 [L,QSA]
RewriteRule ^programs/?$ index.php?modul=programs [L,QSA]
RewriteRule ^programs/([^/]+)/?$ index.php?modul=programs&slug=$1 [L,QSA]
```

---

## 2. Database Schema

### 2.1 Key Tables

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `users` | All user accounts | id, username, email, password_hash, role (siswa/guru/admin), display_name, foto_profil, nomor_telpon, alamat, tanggal_lahir, asal_sekolah, tahun_masuk, jurusan, is_deleted |
| `sessions` | Auth sessions | session_id, user_id, expires_at |
| `categories` | Program/topic tree | id, name, slug, parent_id (null=main program), description, info, show_posts |
| `categories_closure` | Closure table for category tree | ancestor, descendant, depth |
| `posts` | Learning content | id, title, slug, content, category_id, author_id, status (draft/published), thumbnail, is_deleted |
| `pages` | Static frontend pages | id, title, slug, content, status, created_by |
| `tags` | Content tags | id, name, slug |
| `page_tag` | Many-to-many pages↔tags | page_id, tag_id |
| `modules` | Module registration | id, user_id, category_id, status (0=active,1=pending,2=inactive) |
| `user_modules` | Student module assignments | user_id, category_id, status |
| `media` | Uploaded media | id, filename, url, type, size, uploaded_by |
| `registration_policies` | Module registration config | id, max_parent_limit, default_status, review_mode |
| `menu` | Menu items | id, title, url, parent_id, order |
| `password_resets` | Password reset requests | id, user_id, token, status, created_at |

### 2.2 Category Hierarchy

```
Main Categories (parent_id = NULL)
  ├── Mathematics  (slug: mathematics)
  │   ├── Algebra  (slug: algebra)
  │   └── Geometry (slug: geometry)
  ├── Science (slug: science)
  │   ├── Physics  (slug: physics)
  │   └── Chemistry(slug: chemistry)
  ├── Languages (slug: languages)
  └── Creative Arts (slug: creative-arts)
```

---

## 3. Routing — Complete Reference

### 3.1 Frontend (Public) Routes

All handled by `theme/index.php` which receives `$_GET['page']` from Nginx rewrites.

**Static files** (`theme/partials/main/{slug}.php`) take priority, then **dynamic DB pages** (`pages` table), then **category program pages** (main categories rendered as program pages).

| URL | File / Source | Description |
|-----|--------------|-------------|
| `/` | `theme/partials/main/home.php` | Homepage with hero, featured content |
| `/login/` | `login/index.php` | Login form with reCAPTCHA |
| `/register/` | `register/index.php` | Registration form |
| `/reset-password/` | `reset-password/index.php` | Password reset request form |
| `/profile/` | `theme/partials/main/profile.php` | Public about/profile page |
| `/program/` | `theme/partials/main/program.php` | Program listing (dynamic from DB categories) |
| `/moduls/` | `theme/partials/main/moduls.php` | All modules listing |
| `/{main-category-slug}/` | `theme/partials/main/program-detail.php` | **Dynamic** program detail page. Renders from category data + child categories. E.g.: `/mathematics/`, `/science/`, `/languages/`, `/creative-arts/` |
| `/modul/topic/{slug}/` | `modul/topic/index.php` | Topic page — lists posts in a category |
| `/modul/topic/{path}/{slug}/` | `modul/post/detail.php` or `public-view.php` | Single post detail |
| `/{any-slug}/` | DB `pages` table | Custom static pages created in admin panel |

### 3.2 Dashboard (Authenticated) Routes

All prefixed with `/dashboard/`. Access requires valid session cookie (`lernginx_session`).

#### 3.2.1 Dashboard Router (`dashboard/index.php`)

The router dispatches based on role and `modul` GET parameter:

| modul param | Role | Description |
|-------------|------|-------------|
| (none) | any | Dashboard home (role-appropriate) |
| `admin` | admin/teacher | Redirects to `/dashboard/admin/` |
| `student` | student | Module registration page |
| `profile` | any | Profile edit form |

#### 3.2.2 Dashboard Clean URL Routes (Nginx Rewrites)

These are enabled by Nginx rewrite rules (see 1.2) and handled by `dashboard/index.php` router:

| URL | Handler | Description |
|-----|---------|-------------|
| `/dashboard/post/{cat-path}/{slug}/` | `dashboard/index.php?modul=post` | View a post detail within dashboard layout |
| `/dashboard/topic/{slug}/` | `dashboard/index.php?modul=topic` | Topic/category page with post listing |
| `/dashboard/programs/` | `dashboard/index.php?modul=programs` | Programs listing within dashboard |
| `/dashboard/programs/{slug}/` | `dashboard/index.php?modul=programs&slug={slug}` | Program detail within dashboard |

#### 3.2.3 Admin Routes

| URL | File | Role | Description |
|-----|------|------|-------------|
| `/dashboard/admin/` | `admin/index.php` | admin/teacher | Admin panel grid with action cards |
| `/dashboard/admin/content/` | `admin/content/index.php` | admin/teacher | Post management (list, filter, bulk actions) |
| `/dashboard/admin/content/add.php` | `admin/content/add.php` | admin/teacher | Create new post (Quill editor, AJAX save) |
| `/dashboard/admin/content/edit.php` | `admin/content/edit.php` | admin/teacher | Edit post (Quill editor, AJAX save) |
| `/dashboard/admin/content/delete.php` | `admin/content/delete.php` | admin/teacher | Delete post (soft delete → trash) |
| `/dashboard/admin/content/trash/` | `admin/content/trash/index.php` | admin/teacher | View/restore/permanently delete trashed posts |
| `/dashboard/admin/categories/` | `admin/categories/index.php` | admin/teacher | Category tree management |
| `/dashboard/admin/categories/add.php` | `admin/categories/add.php` | admin/teacher | Add category (name, parent, description, icon) |
| `/dashboard/admin/categories/edit.php` | `admin/categories/edit.php` | admin/teacher | Edit category |
| `/dashboard/admin/categories/delete.php` | `admin/categories/delete.php` | admin/teacher | Delete category (cascading) |
| `/dashboard/admin/tags/` | `admin/tags/index.php` | admin/teacher | Tag list |
| `/dashboard/admin/tags/add.php` | `admin/tags/add.php` | admin/teacher | Add tag |
| `/dashboard/admin/tags/edit.php` | `admin/tags/edit.php` | admin/teacher | Edit tag |
| `/dashboard/admin/tags/delete.php` | `admin/tags/delete.php` | admin/teacher | Delete tag |
| `/dashboard/admin/pages/` | `admin/pages/index.php` | admin/teacher | Static pages list |
| `/dashboard/admin/pages/add.php` | `admin/pages/add.php` | admin/teacher | Create static page (Quill + AJAX) |
| `/dashboard/admin/pages/edit.php` | `admin/pages/edit.php` | admin/teacher | Edit static page (Quill + AJAX) |
| `/dashboard/admin/assign/` | `admin/assign/index.php` | admin/teacher | Assign modules to students |
| `/dashboard/admin/assign/save_user.php` | `admin/assign/save_user.php` | admin/teacher | AJAX endpoint: save user module assignments |
| `/dashboard/admin/assign/get_user.php` | `admin/assign/get_user.php` | admin/teacher | AJAX endpoint: get user data for edit modal |
| `/dashboard/admin/menu/` | `admin/menu/index.php` | admin | Program editor (media + descriptions for program pages) |
| `/dashboard/admin/menu/save.php` | `admin/menu/save.php` | admin | Save program metadata to JSON |
| `/dashboard/admin/menu/sync.php` | `admin/menu/sync.php` | admin | Sync programs JSON from main categories |
| `/dashboard/admin/menu/cleanup.php` | `admin/menu/cleanup.php` | admin | Remove unused entries from programs JSON |
| `/dashboard/admin/submenu/` | `admin/submenu/index.php` | admin | Submenu editor (child category metadata) |
| `/dashboard/admin/submenu/save.php` | `admin/submenu/save.php` | admin | Save submenu metadata to JSON |
| `/dashboard/admin/submenu/sync.php` | `admin/submenu/sync.php` | admin | Sync submenus from child categories |
| `/dashboard/admin/submenu/cleanup.php` | `admin/submenu/cleanup.php` | admin | Remove unused submenu entries |
| `/dashboard/admin/modul/` | `admin/modul/index.php` | admin | Module registration policy config |
| `/dashboard/admin/monitoring/` | `admin/monitoring/index.php` | admin | Monitoring dashboard (stats, charts) |
| `/dashboard/admin/monitoring.php` | `admin/monitoring.php` | admin | Legacy monitoring page |
| `/dashboard/admin/media/` | `admin/media/index.php` | admin/teacher | Media library (grid, search, upload, delete) |
| `/dashboard/admin/user-setting/` | `admin/user-setting/index.php` | admin | User management (list, filter, edit, soft-delete) |
| `/dashboard/admin/user-setting/delete.php` | `admin/user-setting/delete.php` | admin | Delete user (soft delete) |
| `/dashboard/admin/user-setting/save.php` | `admin/user-setting/save.php` | admin | Save user edits |
| `/dashboard/admin/user-setting/trash/` | `admin/user-setting/trash/index.php` | admin | View trashed users |
| `/dashboard/admin/user-setting/trash/restore.php` | `admin/user-setting/trash/restore.php` | admin | Restore user from trash |
| `/dashboard/admin/user-setting/trash/delete-permanent.php` | `admin/user-setting/trash/delete-permanent.php` | admin | Permanently delete user |
| `/dashboard/admin/program.php` | `admin/program.php` | admin/teacher | Program editor shortcut page |
| `/dashboard/admin/publik.php` | `admin/publik.php` | admin/teacher | Public articles management |
| `/dashboard/admin/materi.php` | `admin/materi.php` | admin/teacher | Module material management |
| `/dashboard/admin/reset_confirm.php` | `admin/reset_confirm.php` | admin | Confirm password reset requests |
| `/dashboard/admin/upload_assets_img.php` | `admin/upload_assets_img.php` | admin/teacher | Image upload endpoint (re-encodes to WebP) |
| `/dashboard/admin/image-uploader.js` | `admin/image-uploader.js` | — | JS: reusable image upload component |

#### 3.2.4 Student Routes

| URL | File | Description |
|-----|------|-------------|
| `/dashboard/student/` | `student/index.php` | Student profile + module registration (checkbox tree) |
| `/dashboard/student/daftar.php` | `student/daftar.php` | POST handler: save module selections |
| `/dashboard/student/max_modul.js` | `student/max_modul.js` | JS: enforce max parent module limit |

#### 3.2.5 Profile Routes

| URL | File | Role | Description |
|-----|------|------|-------------|
| `/dashboard/profile/` | `profile/index.php` | any | Full profile edit form (name, photo, phone, address, DOB, school, major, password) |
| `/dashboard/profile/update_profile_ajax.php` | `profile/update_profile_ajax.php` | any | AJAX endpoint: save profile edits |
| `/dashboard/profile/upload_photo.php` | `profile/upload_photo.php` | any | AJAX endpoint: upload profile photo (validates type/size, re-encode to WebP, store in date-based folders) |
| `/dashboard/profile/upload_foto_del.php` | `profile/upload_foto_del.php` | any | Legacy photo upload endpoint (still active) |

---

## 4. Authentication System

### 4.1 Mechanism

- **Cookie-based sessions** stored in `sessions` table
- Cookie name: `lernginx_session` (HttpOnly, no Secure in dev)
- Session ID: SHA-256 hash
- Session lifetime: 7 days (`expires_at`)
- Auth check: `get_user_from_session($pdo)` — matches cookie hash against `sessions` table

### 4.2 Login Flow

```
GET /login/ → render login form (with reCAPTCHA)
POST /login/ (email, password, g-recaptcha-response)
  ├── Validate reCAPTCHA
  ├── Lookup user by email
  ├── Verify password_hash with password_verify()
  ├── Create session row in sessions table
  ├── Set cookie: setcookie('lernginx_session', $hash, time()+604800, '/', '', false, true)
  └── Redirect 302 → /dashboard/
```

### 4.3 Logout Flow

```
GET /logout/
  ├── Delete session row from DB
  ├── Clear cookie
  └── Redirect 302 → /login/
```

### 4.4 Role-Based Access Control

Access is checked at the top of each dashboard file:

```php
$user = get_user_from_session($pdo);
if (!$user || !in_array($user['role'], ['admin', 'teacher'])) {
    // deny access
}
```

Role hierarchy:
- **admin**: Full access to everything
- **teacher**: Content, categories, tags, pages, media, assign modules. No access to: user management, module settings, menu/submenu editors, monitoring
- **student**: Own profile, module registration, view assigned content

### 4.5 Credentials (Development)

| Name | Email | Password | Role |
|------|-------|----------|------|
| Admin | admin@lernginx.lan | password | admin |
| Teacher One | teacher1@lernginx.lan | password | teacher |
| Student One | student1@lernginx.lan | password | student |

---

## 5. Feature Flows

### 5.1 Program Pages (Dynamic from Categories)

```
Admin creates a main category in /dashboard/admin/categories/add.php
  → Category slug becomes a URL: /{slug}/
  → Header submenu auto-updates (DB-driven)
  → Program listing page (/program/) auto-updates
  → Program detail page renders dynamically via program-detail.php
```

**How it works**:
1. `theme/index.php` checks if the requested slug matches a main category (`parent_id IS NULL`)
2. If yes, it loads `theme/partials/main/program-detail.php` with `$categoryData` and `$childCategories`
3. The template renders the category name, description, and links to child categories as topics
4. No PHP files need to be created per program

**To add a new program**:
1. Go to `/dashboard/admin/categories/add.php`
2. Create a new category (leave "Parent" as "-- None --")
3. The program appears automatically on the frontend

**To edit program media/description**:
1. Go to `/dashboard/admin/menu/`
2. Click "Sync Programs from Categories" to sync the slug list
3. Edit the media URL, type (lottie/image/svg/gif), and description
4. Click "Save All"

### 5.2 Content (Posts) Management

```
Admin flow:
1. Log in as admin/teacher
2. Navigate to /dashboard/admin/content/
3. Click "Add New" to create a post
4. Fill in: title, content (Quill editor), category, tags, status (draft/published), thumbnail
5. Click "Publish" (AJAX POST → add.php)
6. Post appears in the content list
7. Click "Edit" to modify → edit.php (AJAX)
8. Select posts → bulk action: "Set as Draft" or "Move to Trash"
9. Trash: /dashboard/admin/content/trash/ → restore or permanently delete
```

**Quill Editor Image Upload**:
- Click image button in Quill toolbar
- File picker opens → validates type (png/jpg/webp/gif) and size (max 500KB)
- Image uploaded via AJAX → re-encoded to WebP → stored in `/{year}/{month}/`
- URL inserted into Quill editor at cursor position

### 5.3 Category Management

```
Admin flow:
1. Go to /dashboard/admin/categories/
2. Tree view shows all categories with parent/child relationships
3. Click "Add Category" → form with:
   - Name (required)
   - Parent (select from existing, "-- None --" for main category)
   - Description
   - Additional Info (JSON)
4. Click "Edit" to modify name, parent, description
5. Click "Delete" → confirms → cascading delete
6. Toggle "show_posts" switch to control post visibility
```

**Category → Program mapping**:
- Categories with `parent_id = NULL` ARE programs
- Their slug becomes a URL on the frontend
- Child categories become Topics/Submenus under that program

### 5.4 Tag Management

```
Admin flow:
1. Go to /dashboard/admin/tags/
2. Table lists all tags (name, slug)
3. Click "Add Tag" → enter name → POST → redirect
4. Click "Edit" → modify name → POST → redirect
5. Click "Delete" → JS confirm → POST with _method=DELETE → remove
```

### 5.5 Page Management (Static Pages)

```
Admin flow:
1. Go to /dashboard/admin/pages/
2. List of custom pages (title, slug, status, author, date)
3. Click "Add New" → Quill editor + title + slug + status + thumbnail
4. AJAX save → redirect to list
5. Click "Edit" → similar form, AJAX save
6. Bulk actions: publish/draft/move to trash
```

### 5.6 User Management (Admin Only)

```
Admin flow:
1. Go to /dashboard/admin/user-setting/
2. Table lists all users with role filter + search
3. Click user row → inline edit modal (role, display name, email, etc.)
4. Click "Delete" → soft delete (moves to trash)
5. Go to /dashboard/admin/user-setting/trash/ →
   - View deleted users
   - "Restore" → undo soft delete
   - "Delete Permanently" → hard delete from DB
```

### 5.7 Module Registration (Student)

```
Student flow:
1. Log in → redirected to /dashboard/?modul=student
2. See profile info + module checkboxes organized by program (category tree)
3. Check/uncheck modules under each program
4. Notice: "Maximum parent modules you can register: X / Y" counter
5. Click "Save Changes"
6. If review mode is ON:
   - Changes are submitted for teacher approval (status = pending)
   - Previously reviewed modules that are unchecked show a confirmation modal:
     "The following modules have been reviewed by a teacher and will be cancelled"
7. If review mode is OFF:
   - Changes take effect immediately

Policy config: /dashboard/admin/modul/ (admin only)
- max_parent_limit: max main categories a student can register
- default_status: 0 = active (immediate), 1 = pending (needs approval)
- review_mode: if enabled, students can only request new modules, not modify existing
```

### 5.8 Assign Modules (Admin/Teacher)

```
Teacher flow:
1. Go to /dashboard/admin/assign/
2. List of students with their current module status
3. Click "Edit" (pencil icon) on a student row
4. Modal opens showing all modules organized by program with checkboxes
5. Modify selections:
   - Check → assign module (status = active or pending per policy)
   - Uncheck → remove module (or request cancellation if reviewed)
6. Click "Save" → AJAX POST to save_user.php
7. Student's module list updates immediately
```

### 5.9 Profile Edit

```
Any authenticated user:
1. Go to /dashboard/?modul=profile or click "Profile" in sidebar
2. Edit form fields:
   - Full Name
   - New Password (leave empty to keep current)
   - Photo: URL input OR file picker (drag & drop)
     - Validates: image/png, image/jpeg, image/webp
     - Max size: 300 KB
     - Upload → re-encode to WebP → date-based folder
   - Phone Number (for WhatsApp password reset)
   - Home Address
   - Date of Birth
   - School
   - Enrollment Year
   - Major
3. Click "Save Changes" → AJAX POST → field-level validation → flash message
4. First-time users see a congratulations/first-login modal prompting profile completion
```

### 5.10 Media Library

```
Admin/Teacher flow:
1. Go to /dashboard/admin/media/
2. Grid view of all uploaded images
3. Search by filename
4. Click image → select/copy URL for use in content
5. Click "Delete" → confirm → removes file + DB record
6. Auto-refresh after delete
```

### 5.11 File Upload System

```
Two upload endpoints:

A. upload_assets_img.php (admin content)
   - Used by Quill editor image upload
   - Validates: image/png, image/jpeg, image/webp, image/gif
   - Max size: 500 KB
   - Re-encodes to WebP (if supported by GD)
   - Stores in /assets/img/{year}/{month}/
   - Returns JSON {success, url}

B. upload_photo.php / upload_foto_del.php (profile)
   - Used by profile photo upload
   - Validates: image/png, image/jpeg, image/webp
   - Max size: 300 KB
   - Re-encodes to WebP
   - Stores in /dashboard/profile/static_unchanged/based-registration/foto_profile/{year}/{month}/
   - Updates users.foto_profil in DB
   - Returns JSON {success, url}
```

### 5.12 Password Reset

```
Request flow:
1. User goes to /reset-password/ → enters email
2. System checks if email exists in users table
3. If found: creates entry in password_resets table with status='pending'
4. Notification stored for admin review
5. User sees: "Reset request sent to admin successfully"

Admin confirmation:
1. Admin goes to /dashboard/admin/reset_confirm.php
2. Sees list of pending reset requests
3. Clicks "Confirm" → password_resets.status = 'completed'
4. New password generated or user notified via WhatsApp (placeholder)
5. WhatsApp integration uses placeholder: 6281234567890
```

### 5.13 Monitoring Dashboard (Admin Only)

```
1. Go to /dashboard/admin/monitoring/
2. Statistics cards: Total Students, Active, Inactive
3. Charts (Chart.js): student registrations over time
4. Module registration stats: per-program breakdown
5. Data is read-only, aggregated from users + modules tables
```

### 5.14 Menu Editor (Admin Only)

```
The menu system stores program display metadata in:
/var/www/lernginx.lan/public/dashboard/admin/menu/programs.json

Structure:
{
  "program-slug": {
    "type": "lottie|image|svg|gif",
    "url": "https://...",
    "desc": "Display description"
  }
}

Sync flow:
1. "Sync Programs from Categories" → reads main categories from DB → creates JSON entries
2. Admin edits media type, URL, description per program
3. "Save All" → writes to programs.json
4. Frontend program detail pages use this for hero media
```

---

## 6. Dashboard Layout Components

| Component | File | Description |
|-----------|------|-------------|
| Layout wrapper | `dashboard/partials/layout.php` | Main HTML shell + sidebar + content area |
| Sidebar | `dashboard/partials/sidebar.php` | Role-based navigation menu |
| Header | `dashboard/partials/header-bak.php` | Top bar with user info + theme toggle |
| First-login modal | `dashboard/partials/first-login.php` | Prompt new users to complete profile |
| Congratulations | `dashboard/partials/congratulations.php` | Shown after profile completion |

---

## 7. Frontend Theme Components

| Component | File | Description |
|-----------|------|-------------|
| Header/Nav | `theme/partials/header.php` | Responsive nav with DB-driven program submenu |
| Footer | `theme/partials/footer.php` | Site footer with links |
| Style | `theme/partials/style.css` | Full CSS (responsive, animations) |
| Script | `theme/partials/script.js` | Frontend JS (nav toggle, animations) |
| Home | `theme/partials/main/home.php` | Landing page hero + features |
| Programs | `theme/partials/main/program.php` | Program listing (dynamic from DB) |
| Program Detail | `theme/partials/main/program-detail.php` | Dynamic program page (from category) |
| Moduls | `theme/partials/main/moduls.php` | Module listing |
| Profile | `theme/partials/main/profile.php` | Public profile page |
| 404 | `theme/partials/main/404.php` | Not found page |
| Page template | `theme/partials/main/page.php` | Template for DB pages |

---

## 8. JavaScript Modules

| File | Description |
|------|-------------|
| `assets/dashboard/modul.js` | Module registration UI (checkbox tree, status management, add/edit/delete modules) |
| `assets/dashboard/assign_modal.js` | Assign module modal (fetch user data, save assignments) |
| `assets/dashboard/toast.js` | Toast notification system (success/error/info) |
| `dashboard/admin/image-uploader.js` | Reusable image upload component (file picker, preview, AJAX upload) |
| `dashboard/admin/assign/assign.js` | Assign page logic (user search, edit modal, save, pagination) |
| `dashboard/partials/first-login.js` | First-login modal logic (profile photo upload, form validation, AJAX save) |
| `dashboard/student/max_modul.js` | Max parent module enforcement (checkbox counter + limit) |

---

## 9. Key Configuration

### 9.1 Environment Variables (`app/.env`)

```
DB_HOST=127.0.0.1
DB_NAME=lernginx
DB_USER=lernginx
DB_PASS=changeme
DB_PORT=
RECAPTCHA_SITE_KEY=...
RECAPTCHA_SECRET_KEY=...
DEV_DEBUG=true
```

### 9.2 Module Registration Policy (DB: `registration_policies`)

| Column | Default | Description |
|--------|---------|-------------|
| max_parent_limit | 2 | Max main categories a student can register |
| default_status | 0 | 0 = active, 1 = pending |
| review_mode | 0 | 0 = direct, 1 = needs teacher approval |

### 9.3 Nginx

- Config: `/etc/nginx/sites-available/lernginx.lan`
- Root: `/var/www/lernginx.lan/public`
- PHP: `unix:/run/php/php8.4-fpm.sock`
- Clean URLs via `@frontend` rewrite
- Module routing via `/modul/` location block

---

## 10. Directory Structure

```
/var/www/lernginx.lan/
├── AGENTS.md
├── app/
│   ├── .env                    # DB credentials, reCAPTCHA keys
│   ├── main.php                # Base URL helper
│   └── path/
│       ├── bootstrap.php       # Full bootstrap (env, DB, session, auth, helpers)
│       ├── auth.php            # Login/register/logout functions
│       ├── config.php          # Constants from env
│       ├── db.php              # PDO factory
│       ├── env_loader.php      # .env parser
│       ├── helpers.php         # All helper functions (categories, posts, tags, pages, etc.)
│       ├── pages_helper.php    # Pages + tags CRUD functions
│       └── session_helper.php  # Session read/write functions
├── database/
│   ├── schema.sql              # Full schema
│   ├── seed.sql                # Sample data
│   └── migration/
│       └── 001_initial.sql
├── public/
│   ├── index.php               # Entry point → theme/index.php
│   ├── .htaccess               # Apache rules (reference only)
│   ├── includes/
│   │   ├── bootstrap.php       # Bridge to app/path/bootstrap.php
│   │   └── bootstrap_front.php # Lightweight frontend bootstrap (no auth)
│   ├── theme/                  # Frontend theme
│   │   ├── index.php           # Frontend router
│   │   ├── partials/           # Header, footer, main pages, CSS, JS
│   │   └── components/         # Reusable components
│   ├── dashboard/              # Authenticated area
│   │   ├── index.php           # Dashboard router (by role + modul param)
│   │   ├── admin/              # Admin panel (all management)
│   │   ├── student/            # Student module enrollment
│   │   ├── profile/            # Profile edit + photo upload
│   │   ├── partials/           # Layout, sidebar, header, first-login
│   │   └── modules/            # Module registration logic
│   ├── login/                  # Login page
│   ├── register/               # Registration page
│   ├── logout/                 # Logout handler
│   ├── reset-password/         # Password reset
│   ├── modul/                  # Module content routing
│   └── assets/                 # CSS, JS, images, animations
└── test/                       # E2E tests (exclude from public repo)
    ├── all-features.spec.js
    ├── playwright.config.js
    └── FEATURE_FLOWS.md
```

# lernginx — Open Source LMS

A Learning Management System (LMS) for secondary education (SMP/SMA). Built with vanilla PHP, MySQL, and Nginx.

## Directory Structure

```
/var/www/lernginx.lan/
├── app/                    # Private backend (outside web root)
│   ├── .env                # Environment config (DB credentials, reCAPTCHA)
│   ├── main.php            # Base URL helper (app_url)
│   └── path/
│       ├── bootstrap.php   # Main bootstrap (env, config, DB, session, helpers)
│       ├── auth.php        # Authentication (register, login, require_login)
│       ├── config.php      # Constants from .env
│       ├── db.php          # PDO connection
│       ├── env_loader.php  # .env parser
│       ├── helpers.php     # Core helpers (categories, posts, slugs, render_dashboard)
│       ├── pages_helper.php    # Pages + tags CRUD
│       └── session_helper.php  # Session/cookie auth
│
├── public/                 # Web root (Nginx serves from here)
│   ├── index.php           # Entry point → include theme/index.php
│   ├── .htaccess           # Apache rules (legacy, for reference)
│   ├── includes/
│   │   ├── bootstrap.php       # Bridge to app/path/bootstrap.php
│   │   └── bootstrap_front.php # Lightweight bootstrap (frontend, no auth)
│   ├── theme/              # Frontend theme (renamed from "adam")
│   │   ├── index.php       # Frontend router
│   │   ├── partials/       # Header, footer, pages, style.css, script.js
│   │   └── components/     # Reusable components
│   ├── dashboard/          # Authenticated area
│   │   ├── index.php       # Dashboard router (by role)
│   │   ├── admin/          # Admin panel (content, categories, tags, media, users)
│   │   ├── student/        # Student module enrollment
│   │   ├── profile/        # Profile edit + photo upload
│   │   ├── partials/       # Layout, sidebar, header/footer
│   │   └── modules/        # Module registration logic
│   ├── login/              # Login (was "masuk")
│   ├── register/           # Registration (was "daftar")
│   ├── logout/             # Logout (was "keluar")
│   ├── reset-password/     # Password reset
│   ├── modul/              # Module content routing
│   │   ├── topic/          # Topic detail (post listing)
│   │   ├── post/           # Post detail
│   │   └── programs/       # Program listing
│   └── assets/             # CSS, JS, images, animations
│
├── database/
│   ├── schema.sql          # Full database schema (all tables)
│   ├── seed.sql            # Sample seed data
│   └── migration/
│       └── 001_initial.sql # Initial migration
│
└── AGENTS.md               # This file
```

## Tech Stack
- **PHP 8.x** (procedural + PDO, no framework, no Composer)
- **MySQL/MariaDB** (InnoDB, utf8mb4)
- **Nginx** (clean URLs via rewrite rules)
- **Google reCAPTCHA v2** (login/registration)
- **Quill** (rich text editor)
- **Vanilla JS** (no npm/Node.js/Webpack)
- **Google Fonts**: Poppins, Montserrat

## Database
- **DB Name**: `lernginx` (configurable via `.env`)
- **Tables**: users, sessions, categories, categories_closure, posts, pages, tags, page_tag, modules, media, registration_policies, menu, password_resets
- **Charset**: utf8mb4

## Key Entry Points
| File | Purpose |
|---|---|
| `public/index.php` | Entry → includes `theme/index.php` |
| `public/theme/index.php` | Frontend router (static + DB pages) |
| `public/dashboard/index.php` | Dashboard router (by role + modul param) |
| `public/includes/bootstrap.php` | Bridge to full bootstrap (auth, session) |
| `public/includes/bootstrap_front.php` | Lightweight frontend bootstrap |
| `app/path/bootstrap.php` | Core backend bootstrap |

## Routing
- **Frontend**: `GET /{slug}` → Nginx rewrite → `index.php?page={slug}` → render from `theme/partials/main/{slug}.php` (static) or `pages` DB table
- **Dashboard**: `GET /dashboard/?modul=...` → routing by role + modul param
- **Module content**: `GET /modul/topic/{slug}/` and `/modul/topic/{path}/{post-slug}/`

## Auth
- Cookie-based: `lernginx_session` (HttpOnly, SHA-256)
- Session lifetime: 7 days
- Roles: `student`, `teacher`, `admin` (DB stores: siswa/guru/admin — will be migrated)
- Session stored in `sessions` table

## Nginx Setup
- Config: `/etc/nginx/sites-available/lernginx.lan`
- PHP-FPM: `unix:/run/php/php8.4-fpm.sock`
- Root: `/var/www/lernginx.lan/public`

## Conventions
- **PHP**: Procedural with `require_once`, no autoloading/namespaces
- **Output buffering**: `ob_start()` / `ob_get_clean()` for template rendering
- **DB**: PDO with `ERRMODE_EXCEPTION`, `FETCH_ASSOC`
- **Output**: `htmlspecialchars()` for escaping
- **Security**: Prepared statements for all queries
- **Config**: Constants via `define()`, environment via `getenv()`

## Getting Started
1. Create database: `mysql -u root -e "CREATE DATABASE lernginx CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"`
2. Import schema: `mysql -u root lernginx < database/schema.sql`
3. Import seed data: `mysql -u root lernginx < database/seed.sql`
4. Copy `app/.env.example` to `app/.env` and configure DB credentials
5. Point Nginx to `public/` directory (config provided)

## Original Source
This project was forked from a private project and fully anonymized. All company-identifying references (names, logos, domains) have been removed. See `database/schema.sql` for the complete database structure.

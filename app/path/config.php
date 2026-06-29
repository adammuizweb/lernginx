<?php
// path/config.php
// Assumes load_env() already called by bootstrap.php

// DB
if (!getenv('DB_HOST')) {
    // defensive fallback, but ideally .env loaded before require config
    throw new Exception("Environment variables not loaded. Check bootstrap.");
}

define('DB_HOST', getenv('DB_HOST'));
define('DB_NAME', getenv('DB_NAME'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASS', getenv('DB_PASS'));
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

// reCAPTCHA
define('RECAPTCHA_SITE_KEY', getenv('RECAPTCHA_SITE_KEY') ?: '');
define('RECAPTCHA_SECRET_KEY', getenv('RECAPTCHA_SECRET_KEY') ?: '');

// Base path constant (optional)
define('LERNIGNX_PATH_BASE', __DIR__);

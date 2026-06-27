<?php
// GDPR Sheets Viewer Configuration

define('APP_NAME', 'GDPR Sheets Viewer');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('LOG_FILE', __DIR__ . '/logs/access_log.json');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['csv']);

// Fields considered as PII (Personally Identifiable Information)
define('PII_FIELDS', [
    'email', 'e-mail', 'mail',
    'phone', 'telephone', 'mobile',
    'name', 'first_name', 'last_name', 'fullname',
    'address', 'street', 'city', 'postcode', 'zipcode',
    'ip', 'ip_address',
    'ssn', 'national_id',
    'date_of_birth', 'dob', 'birthday'
]);

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.use_strict_mode', 1);

// Ensure directories exist
foreach ([UPLOAD_DIR, dirname(LOG_FILE)] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

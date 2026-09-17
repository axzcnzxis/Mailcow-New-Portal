<?php
// Portal Configuration Helper (Reads Docker Environment Variables)
function get_env_var($key, $default = '') {
    $val = getenv($key);
    return ($val !== false && $val !== '') ? $val : $default;
}

define('DB_HOST', get_env_var('DB_HOST', 'mysql-mailcow'));
define('DB_PORT', (int)get_env_var('DB_PORT', '3306'));
define('DB_NAME', get_env_var('DB_NAME', 'mailcow'));
define('DB_USER', get_env_var('DB_USER', 'mailcow'));
define('DB_PASS', get_env_var('DB_PASS', 'GkvoHmEZ1692OFhcdtrcBZNuInMu'));

define('IMAP_HOST', get_env_var('IMAP_HOST', 'dovecot-mailcow'));
define('IMAP_PORT', (int)get_env_var('IMAP_PORT', '143'));

define('SMTP_HOST', get_env_var('SMTP_HOST', 'postfix-mailcow'));
define('SMTP_PORT', (int)get_env_var('SMTP_PORT', '587'));

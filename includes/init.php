<?php
/**
 * Central bootstrap for every page.
 */

if (!defined('INIT_LOADED')) {
    define('INIT_LOADED', true);

    if (!defined('BASE_PATH')) {
        define('BASE_PATH', $_SERVER['DOCUMENT_ROOT']);
    }

    require_once BASE_PATH . '/includes/mail_config.php';
    require_once BASE_PATH . '/includes/db.php';
}

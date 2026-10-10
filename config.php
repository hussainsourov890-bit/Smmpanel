<?php
/*
 * SIB SMM PANEL
 * Database settings are created by install.php.
 * Do not put real passwords in this file.
 */
define('DEBUG', false);

$local = __DIR__ . '/config.local.php';
if (!is_file($local)) {
    // Let the application send the visitor to the installer.
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php') {
        header('Location: install.php');
        exit;
    }
} else {
    require_once $local;
}

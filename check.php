<?php
require __DIR__ . '/config.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);
$ok = fn($b) => $b ? '✅' : '❌';
echo '<pre style="font:16px monospace">';
echo $ok(version_compare(PHP_VERSION, '7.4', '>=')) . " PHP " . PHP_VERSION . " (7.4+ দরকার)\n";
echo $ok(extension_loaded('pdo_mysql')) . " pdo_mysql\n";
echo $ok(extension_loaded('curl')) . " curl\n";
if (!defined('DB_HOST')) {
    echo "⚠️ Database is not configured yet.\n";
    echo "Open /install.php and enter your cPanel MySQL details.\n";
    exit;
}
try {
    new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS);
    echo "✅ Database connected\n";
} catch (Throwable $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

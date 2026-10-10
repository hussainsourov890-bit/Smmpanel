<?php
declare(strict_types=1);

$configFile = __DIR__ . '/config.local.php';
$message = '';
$error = '';

if (is_file($configFile) && ($_GET['force'] ?? '') !== '1') {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim($_POST['host'] ?? 'localhost');
    $name = trim($_POST['name'] ?? '');
    $user = trim($_POST['user'] ?? '');
    $pass = (string)($_POST['pass'] ?? '');

    if ($name === '' || $user === '') {
        $error = 'Database name and username are required.';
    } else {
        try {
            $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            $cfg = "<?php\n"
                 . "define('DB_HOST', " . var_export($host, true) . ");\n"
                 . "define('DB_NAME', " . var_export($name, true) . ");\n"
                 . "define('DB_USER', " . var_export($user, true) . ");\n"
                 . "define('DB_PASS', " . var_export($pass, true) . ");\n";

            if (@file_put_contents($configFile, $cfg, LOCK_EX) === false) {
                throw new RuntimeException('Could not write config.local.php. Make the site folder writable temporarily.');
            }

            header('Location: index.php?installed=1');
            exit;
        } catch (Throwable $e) {
            $error = 'Database connection failed: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SIB SMM Panel Installer</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="land">
  <div class="hero">
    <h1>SIB <b>SMM PANEL</b></h1>
    <p>Database setup</p>
  </div>
  <?php if ($error): ?><div class="msg err"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>
  <form class="card" method="post">
    <h3>MySQL / MariaDB</h3>
    <label>Database host</label>
    <input name="host" value="<?= htmlspecialchars($_POST['host'] ?? 'localhost', ENT_QUOTES) ?>" required>
    <label>Database name</label>
    <input name="name" placeholder="cpaneluser_sibsmm" value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES) ?>" required>
    <label>Database username</label>
    <input name="user" placeholder="cpaneluser_sibuser" value="<?= htmlspecialchars($_POST['user'] ?? '', ENT_QUOTES) ?>" required>
    <label>Database password</label>
    <input type="password" name="pass" required>
    <button class="btn" type="submit">Install / Connect</button>
  </form>
  <div class="card">
    <b>Before installing:</b>
    <p>Create a MySQL database and user in cPanel and give the user <b>ALL PRIVILEGES</b>.</p>
    <p>After installation, delete <code>install.php</code> for extra safety.</p>
  </div>
</div>
</body>
</html>

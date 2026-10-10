<?php // হোম পেজ + লগইন + রেজিস্টার
require 'inc/core.php';
$msg = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  chk();
  if (isset($_POST['login'])) {
    $u = q("SELECT * FROM users WHERE username=?", [trim($_POST['username'])])->fetch(PDO::FETCH_ASSOC);
    if ($u && password_verify($_POST['password'], $u['password']) && !$u['banned']) { $_SESSION['uid'] = $u['id']; header('Location: ' . ($u['role'] == 'admin' ? 'admin.php' : 'dashboard.php')); exit; }
    $msg = 'Wrong username or password.';
  } elseif (isset($_POST['register']) && S('signup') == '1') {
    $n = trim($_POST['username']);
    if (strlen($n) < 4 || strlen($_POST['password']) < 6) $msg = 'Username 4+ and password 6+ characters.';
    elseif (q("SELECT 1 FROM users WHERE username=?", [$n])->fetch()) $msg = 'Username already taken.';
    else { q("INSERT INTO users(username,email,password,api_key) VALUES(?,?,?,?)", [$n, trim($_POST['email']), password_hash($_POST['password'], PASSWORD_DEFAULT), bin2hex(random_bytes(16))]); $_SESSION['uid'] = $db->lastInsertId(); header('Location: dashboard.php'); exit; }
  }
}
$cnt = q("SELECT COUNT(*) FROM services WHERE active=1")->fetchColumn();
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e(S('site_name')) ?></title><link rel="stylesheet" href="style.css"></head><body>
<div class="land">
<div class="hero"><h1>Grow your socials with <b><?= e(S('site_name')) ?></b></h1><p style="color:var(--mut);max-width:520px"><?= e(S('notice')) ?></p><p><?= $cnt ?> live services · Instagram, Facebook, YouTube, TikTok, Telegram · API ready</p></div>
<?php if ($msg) echo '<div class="msg err">' . e($msg) . '</div>'; ?>
<div class="two">
<form class="card" method="post"><h3>Log in</h3><?= csrf() ?><label>Username</label><input name="username" required><label>Password</label><input type="password" name="password" required><button class="btn" name="login">Log in</button></form>
<?php if (S('signup') == '1'): ?><form class="card" method="post"><h3>Create account</h3><?= csrf() ?><label>Username</label><input name="username" required><label>Email</label><input type="email" name="email" required><label>Password</label><input type="password" name="password" required><button class="btn" name="register">Sign up</button></form><?php endif; ?>
</div></div></body></html>

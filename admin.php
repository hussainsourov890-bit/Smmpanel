<?php // এডমিন প্যানেল — ইউজার, ক্যাটাগরি, সার্ভিস, API, অর্ডার, পেমেন্ট, সেটিংস সব কন্ট্রোল
require 'inc/core.php';
$u = need(true); chk(); $m = '';
$p = $_GET['p'] ?? 'home'; $P = $_POST;
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  if (isset($P['user_save'])) { q("UPDATE users SET balance=?,banned=?,role=? WHERE id=?", [(float)$P['balance'], (int)isset($P['banned']), $P['role'], (int)$P['id']]); if ($P['pw'] != '') q("UPDATE users SET password=? WHERE id=?", [password_hash($P['pw'], PASSWORD_DEFAULT), (int)$P['id']]); $m = 'User updated'; }
  if (isset($P['cat_add'])) q("INSERT INTO categories(name) VALUES(?)", [$P['name']]);
  if (isset($P['cat_save'])) q("UPDATE categories SET name=?,active=? WHERE id=?", [$P['name'], (int)isset($P['active']), (int)$P['id']]);
  if (isset($P['cat_del'])) { q("DELETE FROM categories WHERE id=?", [(int)$P['id']]); q("DELETE FROM services WHERE category_id=?", [(int)$P['id']]); }
  if (isset($P['sv_save'])) {
    $a = [(int)$P['category_id'], $P['name'], (float)$P['rate'], (int)$P['min'], (int)$P['max'], $P['description'], (int)$P['provider_id'], (int)$P['provider_service'], (int)isset($P['active'])];
    if ($P['id']) q("UPDATE services SET category_id=?,name=?,rate=?,min=?,max=?,description=?,provider_id=?,provider_service=?,active=? WHERE id=?", array_merge($a, [(int)$P['id']]));
    else q("INSERT INTO services(category_id,name,rate,min,max,description,provider_id,provider_service,active) VALUES(?,?,?,?,?,?,?,?,?)", $a);
    $m = 'Service saved';
  }
  if (isset($P['sv_del'])) q("DELETE FROM services WHERE id=?", [(int)$P['id']]);
  if (isset($P['pr_save'])) { if ($P['id']) q("UPDATE providers SET name=?,api_url=?,api_key=? WHERE id=?", [$P['name'], $P['api_url'], $P['api_key'], (int)$P['id']]); else q("INSERT INTO providers(name,api_url,api_key) VALUES(?,?,?)", [$P['name'], $P['api_url'], $P['api_key']]); $m = 'API saved'; }
  if (isset($P['pr_del'])) q("DELETE FROM providers WHERE id=?", [(int)$P['id']]);
  if (isset($P['pr_bal'])) { $r = provider_call((int)$P['id'], ['action' => 'balance']); $m = 'Provider balance: ' . json_encode($r); }
  if (isset($P['pr_import'])) { // প্রোভাইডারের সব সার্ভিস নিজের রেট+মার্কআপ দিয়ে ইমপোর্ট
    $list = provider_call((int)$P['id'], ['action' => 'services']); $n = 0;
    if (is_array($list)) foreach ($list as $s) {
      if (!isset($s['service'])) continue;
      $cn = $s['category'] ?? 'Imported'; $cid = q("SELECT id FROM categories WHERE name=?", [$cn])->fetchColumn();
      if (!$cid) { q("INSERT INTO categories(name) VALUES(?)", [$cn]); $cid = $db->lastInsertId(); }
      if (q("SELECT 1 FROM services WHERE provider_id=? AND provider_service=?", [(int)$P['id'], $s['service']])->fetch()) continue;
      q("INSERT INTO services(category_id,name,rate,min,max,provider_id,provider_service) VALUES(?,?,?,?,?,?,?)", [$cid, $s['name'], round($s['rate'] * (1 + (float)$P['markup'] / 100), 4), $s['min'], $s['max'], (int)$P['id'], $s['service']]); $n++;
    }
    $m = "$n services imported";
  }
  if (isset($P['ord_save'])) { $o = q("SELECT * FROM orders WHERE id=?", [(int)$P['id']])->fetch(PDO::FETCH_ASSOC);
    if ($P['status'] == 'Canceled' && $o['status'] != 'Canceled') q("UPDATE users SET balance=balance+? WHERE id=?", [$o['charge'], $o['user_id']]); // রিফান্ড
    q("UPDATE orders SET status=? WHERE id=?", [$P['status'], (int)$P['id']]); }
  if (isset($P['ord_sync'])) { foreach (q("SELECT o.id,o.provider_order,s.provider_id FROM orders o JOIN services s ON s.id=o.service_id WHERE o.provider_order>0 AND o.status IN('Pending','Processing')") as $o) { $r = provider_call($o['provider_id'], ['action' => 'status', 'order' => $o['provider_order']]); if (!empty($r['status'])) q("UPDATE orders SET status=? WHERE id=?", [$r['status'], $o['id']]); } $m = 'Synced with providers'; }
  if (isset($P['pay_ok'])) { $x = q("SELECT * FROM payments WHERE id=? AND status='Pending'", [(int)$P['id']])->fetch(PDO::FETCH_ASSOC); if ($x) { q("UPDATE payments SET status='Completed' WHERE id=?", [$x['id']]); q("UPDATE users SET balance=balance+? WHERE id=?", [$x['amount'], $x['user_id']]); } }
  if (isset($P['pay_no'])) q("UPDATE payments SET status='Rejected' WHERE id=? AND status='Pending'", [(int)$P['id']]);
  if (isset($P['rf_set'])) q("UPDATE refills SET status=? WHERE id=?", [$P['status'], (int)$P['id']]);
  if (isset($P['rf_sync'])) { foreach (q("SELECT r.id,r.provider_refill,s.provider_id FROM refills r JOIN orders o ON o.id=r.order_id JOIN services s ON s.id=o.service_id WHERE r.provider_refill!='' AND r.status IN('Pending','Processing')") as $r) { $x = provider_call($r['provider_id'], ['action' => 'refill_status', 'refill' => $r['provider_refill']]); if (!empty($x['status'])) q("UPDATE refills SET status=? WHERE id=?", [$x['status'], $r['id']]); } $m = 'Refills synced'; }
  if (isset($P['tk_reply']) && trim($P['body']) != '') { q("INSERT INTO ticket_msgs(ticket_id,is_admin,body) VALUES(?,1,?)", [(int)$P['tid'], $P['body']]); q("UPDATE tickets SET status='Answered',updated=NOW() WHERE id=?", [(int)$P['tid']]); $m = 'Reply sent'; }
  if (isset($P['tk_close'])) q("UPDATE tickets SET status='Closed' WHERE id=?", [(int)$P['tid']]);
  if (isset($P['set_save'])) { foreach (['site_name', 'currency', 'notice', 'contact', 'pay_info', 'signup'] as $k) setS($k, $P[$k]); $m = 'Settings saved'; }
}
$u = me();
page_head('Admin · ' . ucfirst($p), ['home' => '📊 Dashboard', 'users' => '👥 Users', 'cats' => '🗂 Categories', 'services' => '🧩 Services', 'providers' => '🔌 Service APIs', 'orders' => '📦 Orders', 'refills' => '🔁 Refills', 'tickets' => '🎫 Tickets', 'payments' => '💰 Payments', 'settings' => '⚙ Settings'], $u);
if ($m) echo '<div class="msg">' . e($m) . '</div>';
$cats = q("SELECT * FROM categories")->fetchAll(PDO::FETCH_ASSOC);
$provs = q("SELECT * FROM providers")->fetchAll(PDO::FETCH_ASSOC);

if ($p == 'home') { echo '<div class="grid">';
  foreach (['Users' => "SELECT COUNT(*) FROM users", 'Orders' => "SELECT COUNT(*) FROM orders", 'Pending orders' => "SELECT COUNT(*) FROM orders WHERE status='Pending'", 'Pending payments' => "SELECT COUNT(*) FROM payments WHERE status='Pending'", 'Revenue' => "SELECT COALESCE(SUM(charge),0) FROM orders WHERE status!='Canceled'"] as $k => $sql)
    echo "<div class='stat'><small>$k</small><b>" . ($k == 'Revenue' ? money(q($sql)->fetchColumn()) : q($sql)->fetchColumn()) . "</b></div>";
  echo '</div><div class="card">Tip: Add a Service API under <b>Service APIs</b>, press Import, and your panel is stocked in one click.</div>';
} elseif ($p == 'users') { echo '<div class="card"><table><tr><th>User</th><th>Balance</th><th>Role</th><th>Ban</th><th>New password</th><th></th></tr>';
  foreach (q("SELECT * FROM users") as $x) echo "<form method='post'>" . csrf() . "<tr><td><input type=hidden name=id value={$x['id']}>" . e($x['username']) . "<br><small>" . e($x['email']) . "</small></td><td><input name=balance value='{$x['balance']}' style='width:100px'></td><td><select name=role><option>user</option><option" . ($x['role'] == 'admin' ? ' selected' : '') . ">admin</option></select></td><td><input type=checkbox name=banned" . ($x['banned'] ? ' checked' : '') . " style='width:auto'></td><td><input name=pw placeholder='leave empty'></td><td><button class='btn sm' name=user_save>Save</button></td></tr></form>";
  echo '</table></div>';
} elseif ($p == 'cats') { ?>
<form class="card row" method="post"><?= csrf() ?><input name="name" placeholder="New category name" required><button class="btn" name="cat_add" style="margin:0">Add category</button></form>
<div class="card"><table><?php foreach ($cats as $c) echo "<form method='post'>" . csrf() . "<tr><td><input type=hidden name=id value={$c['id']}><input name=name value='" . e($c['name']) . "'></td><td><label style='margin:0'><input type=checkbox name=active style='width:auto'" . ($c['active'] ? ' checked' : '') . "> Active</label></td><td><button class='btn sm' name=cat_save>Save</button> <button class='btn sm red' name=cat_del onclick=\"return confirm('Delete category and its services?')\">Delete</button></td></tr></form>"; ?></table></div>
<?php } elseif ($p == 'services') {
  $edit = isset($_GET['edit']) ? q("SELECT * FROM services WHERE id=?", [(int)$_GET['edit']])->fetch(PDO::FETCH_ASSOC) : [];
  $v = fn($k, $d = '') => e($edit[$k] ?? $d); ?>
<form class="card" method="post"><?= csrf() ?><h3><?= $edit ? 'Edit service #' . $edit['id'] : 'Add new service' ?></h3><input type="hidden" name="id" value="<?= $v('id') ?>">
<div class="row"><div><label>Category</label><select name="category_id"><?php foreach ($cats as $c) echo "<option value='{$c['id']}'" . (($edit['category_id'] ?? 0) == $c['id'] ? ' selected' : '') . ">" . e($c['name']) . "</option>"; ?></select></div><div><label>Name</label><input name="name" value="<?= $v('name') ?>" required></div></div>
<div class="row"><div><label>Rate per 1000</label><input name="rate" value="<?= $v('rate') ?>" required></div><div><label>Min</label><input name="min" value="<?= $v('min', 100) ?>"></div><div><label>Max</label><input name="max" value="<?= $v('max', 10000) ?>"></div></div>
<label>Description</label><textarea name="description"><?= $v('description') ?></textarea>
<div class="row"><div><label>Provider API (optional)</label><select name="provider_id"><option value="0">Manual (no API)</option><?php foreach ($provs as $x) echo "<option value='{$x['id']}'" . (($edit['provider_id'] ?? 0) == $x['id'] ? ' selected' : '') . ">" . e($x['name']) . "</option>"; ?></select></div><div><label>Provider service ID</label><input name="provider_service" value="<?= $v('provider_service', 0) ?>"></div></div>
<label><input type="checkbox" name="active" style="width:auto" <?= ($edit['active'] ?? 1) ? 'checked' : '' ?>> Active</label><button class="btn" name="sv_save">Save service</button> <?= $edit ? '<a class="btn gh" href="?p=services">Cancel</a>' : '' ?></form>
<div class="card"><table><tr><th>ID</th><th>Name</th><th>Rate</th><th>API</th><th></th></tr><?php foreach (q("SELECT * FROM services ORDER BY id DESC") as $s) echo "<tr><td>{$s['id']}</td><td>" . e($s['name']) . ($s['active'] ? '' : ' <span class=tag>off</span>') . "</td><td>{$s['rate']}</td><td>" . ($s['provider_id'] ? "#{$s['provider_id']}/{$s['provider_service']}" : 'manual') . "</td><td><a class='btn sm' href='?p=services&edit={$s['id']}'>Edit</a> <form method=post style=display:inline>" . csrf() . "<input type=hidden name=id value={$s['id']}><button class='btn sm red' name=sv_del onclick=\"return confirm('Delete?')\">X</button></form></td></tr>"; ?></table></div>
<?php } elseif ($p == 'providers') { ?>
<form class="card" method="post"><?= csrf() ?><h3>Add service API (any standard SMM panel)</h3><input type="hidden" name="id" value=""><div class="row"><input name="name" placeholder="Name" required><input name="api_url" placeholder="API URL e.g. https://panel.com/api/v2" required><input name="api_key" placeholder="API key" required></div><button class="btn" name="pr_save">Add API</button></form>
<?php foreach ($provs as $x) { ?><div class="card"><form method="post"><?= csrf() ?><input type="hidden" name="id" value="<?= $x['id'] ?>"><div class="row"><input name="name" value="<?= e($x['name']) ?>"><input name="api_url" value="<?= e($x['api_url']) ?>"><input name="api_key" value="<?= e($x['api_key']) ?>"></div>
<button class="btn sm" name="pr_save">Save</button> <button class="btn sm gh" name="pr_bal">Check balance</button> <button class="btn sm red" name="pr_del" onclick="return confirm('Delete?')">Delete</button>
<hr style="border-color:var(--line)"><div class="row"><input name="markup" value="30" style="max-width:120px" title="Markup %"><button class="btn sm" name="pr_import" style="flex:0">Import all services (+markup %)</button></div></form></div><?php }
} elseif ($p == 'orders') { echo '<form method=post>' . csrf() . '<button class="btn sm" name=ord_sync style="margin-bottom:12px">Sync status from providers</button></form><div class="card"><table><tr><th>ID</th><th>User</th><th>Service</th><th>Link</th><th>Qty</th><th>Charge</th><th>Status</th></tr>';
  foreach (q("SELECT o.*,u.username,s.name sn FROM orders o LEFT JOIN users u ON u.id=o.user_id LEFT JOIN services s ON s.id=o.service_id ORDER BY o.id DESC LIMIT 200") as $o) {
    $sel = ''; foreach (['Pending', 'Processing', 'Completed', 'Canceled'] as $st) $sel .= "<option" . ($o['status'] == $st ? ' selected' : '') . ">$st</option>";
    echo "<tr><td>{$o['id']}</td><td>" . e($o['username']) . "</td><td>" . e($o['sn']) . "</td><td>" . e(substr($o['link'], 0, 28)) . "</td><td>{$o['quantity']}</td><td>" . money($o['charge']) . "</td><td><form method=post>" . csrf() . "<input type=hidden name=id value={$o['id']}><select name=status onchange=this.form.submit() style='width:130px'>$sel</select><input type=hidden name=ord_save value=1></form></td></tr>"; }
  echo '</table></div>';
} elseif ($p == 'payments') { echo '<div class="card"><table><tr><th>User</th><th>Method</th><th>Amount</th><th>TRX</th><th>Status</th><th></th></tr>';
  foreach (q("SELECT p.*,u.username FROM payments p LEFT JOIN users u ON u.id=p.user_id ORDER BY p.id DESC LIMIT 200") as $x) echo "<tr><td>" . e($x['username']) . "</td><td>" . e($x['method']) . "</td><td>" . money($x['amount']) . "</td><td>" . e($x['trx']) . "</td><td><span class='tag {$x['status']}'>{$x['status']}</span></td><td>" . ($x['status'] == 'Pending' ? "<form method=post>" . csrf() . "<input type=hidden name=id value={$x['id']}><button class='btn sm' name=pay_ok>Approve</button> <button class='btn sm red' name=pay_no>Reject</button></form>" : '') . "</td></tr>";
  echo '</table></div>';
} elseif ($p == 'refills') { echo '<form method=post>' . csrf() . '<button class="btn sm" name=rf_sync style="margin-bottom:12px">Sync from providers</button></form><div class="card"><table><tr><th>ID</th><th>Order</th><th>User</th><th>Provider ref</th><th>Status</th></tr>';
  foreach (q("SELECT r.*,u.username FROM refills r LEFT JOIN users u ON u.id=r.user_id ORDER BY r.id DESC LIMIT 200") as $r) { $sel = ''; foreach (['Pending', 'Processing', 'Completed', 'Rejected'] as $st) $sel .= "<option" . ($r['status'] == $st ? ' selected' : '') . ">$st</option>";
    echo "<tr><td>{$r['id']}</td><td>#{$r['order_id']}</td><td>" . e($r['username']) . "</td><td>" . e($r['provider_refill']) . "</td><td><form method=post>" . csrf() . "<input type=hidden name=id value={$r['id']}><input type=hidden name=rf_set value=1><select name=status onchange=this.form.submit() style='width:130px'>$sel</select></form></td></tr>"; }
  echo '</table></div>';
} elseif ($p == 'tickets') { $tid = (int)($_GET['t'] ?? 0); $t = $tid ? q("SELECT t.*,u.username FROM tickets t LEFT JOIN users u ON u.id=t.user_id WHERE t.id=?", [$tid])->fetch(PDO::FETCH_ASSOC) : null;
  if ($t) { echo '<div class="card"><h3>#' . $t['id'] . ' ' . e($t['subject']) . ' — ' . e($t['username']) . ' <span class="tag">' . $t['status'] . '</span></h3>';
    foreach (q("SELECT * FROM ticket_msgs WHERE ticket_id=? ORDER BY id", [$tid]) as $x) echo '<div class="card" style="' . ($x['is_admin'] ? 'border-color:var(--a)' : '') . '"><small>' . ($x['is_admin'] ? 'Support' : e($t['username'])) . ' · ' . $x['created'] . '</small><br>' . nl2br(e($x['body'])) . '</div>';
    echo '<form method="post">' . csrf() . '<input type="hidden" name="tid" value="' . $tid . '"><textarea name="body" required placeholder="Reply to user"></textarea><button class="btn" name="tk_reply">Send reply</button> <button class="btn red" name="tk_close">Close ticket</button></form><p><a href="?p=tickets">← All tickets</a></p></div>';
  } else { echo '<div class="card"><table><tr><th>ID</th><th>User</th><th>Subject</th><th>Status</th><th>Updated</th></tr>';
    foreach (q("SELECT t.*,u.username FROM tickets t LEFT JOIN users u ON u.id=t.user_id ORDER BY (t.status='Open') DESC,t.updated DESC LIMIT 200") as $t) echo "<tr><td>{$t['id']}</td><td>" . e($t['username']) . "</td><td><a href='?p=tickets&t={$t['id']}'>" . e($t['subject']) . "</a></td><td><span class='tag'>{$t['status']}</span></td><td>{$t['updated']}</td></tr>";
    echo '</table></div>'; }
} else { ?>
<form class="card" method="post"><?= csrf() ?><label>Panel name</label><input name="site_name" value="<?= e(S('site_name')) ?>"><div class="row"><div><label>Currency symbol</label><input name="currency" value="<?= e(S('currency')) ?>"></div><div><label>Contact</label><input name="contact" value="<?= e(S('contact')) ?>"></div><div><label>Open registration</label><select name="signup"><option value="1">Yes</option><option value="0"<?= S('signup') == '0' ? ' selected' : '' ?>>No</option></select></div></div>
<label>Home / dashboard notice</label><textarea name="notice"><?= e(S('notice')) ?></textarea><label>Payment instructions</label><textarea name="pay_info"><?= e(S('pay_info')) ?></textarea><button class="btn" name="set_save">Save settings</button></form>
<?php }
page_foot();

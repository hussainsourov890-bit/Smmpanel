<?php // ইউজার প্যানেল
require 'inc/core.php';
$u = need(); chk(); $m = '';
$p = $_GET['p'] ?? 'order';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  if (isset($_POST['order'])) { [$ok, $r] = place_order($u, (int)$_POST['service'], trim($_POST['link']), $_POST['qty']); $m = $ok ? "✔ Order #$r placed." : "✖ $r"; $u = me(); }
  if (isset($_POST['fund'])) { q("INSERT INTO payments(user_id,amount,method,trx) VALUES(?,?,?,?)", [$u['id'], (float)$_POST['amount'], $_POST['method'], trim($_POST['trx'])]); $m = '✔ Submitted. Admin will approve soon.'; }
  if (isset($_POST['pw']) && strlen($_POST['new']) >= 6) { q("UPDATE users SET password=? WHERE id=?", [password_hash($_POST['new'], PASSWORD_DEFAULT), $u['id']]); $m = '✔ Password changed.'; }
  if (isset($_POST['refill_req'])) { [$ok, $r] = request_refill($u, (int)$_POST['oid']); $m = $ok ? "✔ Refill #$r requested." : "✖ $r"; }
  if (isset($_POST['ticket_new']) && trim($_POST['body']) != '') { q("INSERT INTO tickets(user_id,subject) VALUES(?,?)", [$u['id'], $_POST['subject']]); $tn = $db->lastInsertId(); q("INSERT INTO ticket_msgs(ticket_id,body) VALUES(?,?)", [$tn, $_POST['body']]); $m = '✔ Ticket created.'; }
  if (isset($_POST['ticket_reply']) && q("SELECT 1 FROM tickets WHERE id=? AND user_id=?", [(int)$_POST['tid'], $u['id']])->fetch()) { q("INSERT INTO ticket_msgs(ticket_id,body) VALUES(?,?)", [(int)$_POST['tid'], $_POST['body']]); q("UPDATE tickets SET status='Open',updated=NOW() WHERE id=?", [(int)$_POST['tid']]); $m = '✔ Reply sent.'; }
  if (isset($_POST['newkey'])) { q("UPDATE users SET api_key=? WHERE id=?", [bin2hex(random_bytes(16)), $u['id']]); $u = me(); }
}
page_head(['order' => 'New order', 'services' => 'Services', 'orders' => 'My orders', 'funds' => 'Add funds', 'tickets' => 'Support tickets', 'account' => 'Account & API'][$p] ?? 'Panel',
  ['order' => '🛒 New order', 'services' => '📋 Services', 'orders' => '📦 My orders', 'funds' => '💳 Add funds', 'tickets' => '🎫 Tickets', 'account' => '⚙ Account & API'], $u);
if ($m) echo '<div class="msg">' . e($m) . '</div>';
$cats = q("SELECT * FROM categories WHERE active=1")->fetchAll(PDO::FETCH_ASSOC);
$svs = q("SELECT s.*,c.name cn FROM services s JOIN categories c ON c.id=s.category_id WHERE s.active=1 AND c.active=1 ORDER BY c.id,s.id")->fetchAll(PDO::FETCH_ASSOC);

if ($p == 'order') { ?>
<div class="card notice"><?= nl2br(e(S('notice'))) ?></div>
<form class="card" method="post"><?= csrf() ?>
<label>Category</label><select id="cat"><?php foreach ($cats as $c) echo "<option value='{$c['id']}'>" . e($c['name']) . "</option>"; ?></select>
<label>Service</label><select name="service" id="svc"></select>
<div class="card" id="info" style="margin:12px 0 0;color:var(--mut)"></div>
<label>Link</label><input name="link" placeholder="https://" required>
<label>Quantity</label><input type="number" name="qty" id="qty" required>
<label>Charge</label><input id="chg" readonly value="0.00">
<button class="btn" name="order">Place order</button></form>
<script>
const S=<?= json_encode($svs) ?>,cat=document.getElementById('cat'),svc=document.getElementById('svc'),qty=document.getElementById('qty');
function fill(){svc.innerHTML=S.filter(s=>s.category_id==cat.value).map(s=>`<option value="${s.id}">${s.id} - ${s.name} — ${s.rate}/1000</option>`).join('');show()}
function show(){const s=S.find(x=>x.id==svc.value);if(!s)return;document.getElementById('info').innerText=`Min ${s.min} · Max ${s.max} · ${s.description||''}`;calc()}
function calc(){const s=S.find(x=>x.id==svc.value);if(s)document.getElementById('chg').value=((s.rate*qty.value)/1000).toFixed(2)}
cat.onchange=fill;svc.onchange=show;qty.oninput=calc;fill();
</script>
<?php } elseif ($p == 'services') { echo '<div class="card"><table><tr><th>ID</th><th>Service</th><th>Rate/1000</th><th>Min</th><th>Max</th></tr>'; $last = '';
  foreach ($svs as $s) { if ($s['cn'] != $last) { echo '<tr><td colspan=5><b style="color:var(--a)">' . e($s['cn']) . '</b></td></tr>'; $last = $s['cn']; }
    echo "<tr><td>{$s['id']}</td><td>" . e($s['name']) . "</td><td>" . money($s['rate']) . "</td><td>{$s['min']}</td><td>{$s['max']}</td></tr>"; }
  echo '</table></div>';
} elseif ($p == 'orders') { echo '<div class="card"><table><tr><th>ID</th><th>Service</th><th>Link</th><th>Qty</th><th>Charge</th><th>Status</th><th>Refill</th></tr>';
  foreach (q("SELECT o.*,s.name sn FROM orders o LEFT JOIN services s ON s.id=o.service_id WHERE user_id=? ORDER BY o.id DESC LIMIT 100", [$u['id']]) as $o)
    echo "<tr><td>{$o['id']}</td><td>" . e($o['sn']) . "</td><td>" . e(substr($o['link'], 0, 30)) . "</td><td>{$o['quantity']}</td><td>" . money($o['charge']) . "</td><td><span class='tag {$o['status']}'>{$o['status']}</span></td><td>" . ($o['status'] == 'Completed' ? "<form method=post>" . csrf() . "<input type=hidden name=oid value={$o['id']}><button class='btn sm gh' name=refill_req>Refill</button></form>" : '') . "</td></tr>";
  echo '</table></div>';
} elseif ($p == 'funds') { ?>
<div class="card"><h3>How to pay</h3><pre><?= e(S('pay_info')) ?></pre></div>
<form class="card" method="post"><?= csrf() ?><div class="row"><div><label>Method</label><select name="method"><option>bKash</option><option>Nagad</option><option>Rocket</option><option>Binance</option></select></div><div><label>Amount</label><input type="number" step="0.01" name="amount" required></div></div><label>Transaction ID</label><input name="trx" required><button class="btn" name="fund">Submit payment</button></form>
<div class="card"><table><tr><th>Date</th><th>Method</th><th>Amount</th><th>Status</th></tr><?php foreach (q("SELECT * FROM payments WHERE user_id=? ORDER BY id DESC", [$u['id']]) as $x) echo "<tr><td>{$x['created']}</td><td>" . e($x['method']) . "</td><td>" . money($x['amount']) . "</td><td><span class='tag {$x['status']}'>{$x['status']}</span></td></tr>"; ?></table></div>
<?php } elseif ($p == 'tickets') {
  $tid = (int)($_GET['t'] ?? 0); $t = $tid ? q("SELECT * FROM tickets WHERE id=? AND user_id=?", [$tid, $u['id']])->fetch(PDO::FETCH_ASSOC) : null;
  if ($t) { echo '<div class="card"><h3>#' . $t['id'] . ' ' . e($t['subject']) . ' <span class="tag">' . $t['status'] . '</span></h3>';
    foreach (q("SELECT * FROM ticket_msgs WHERE ticket_id=? ORDER BY id", [$tid]) as $x) echo '<div class="card" style="' . ($x['is_admin'] ? 'border-color:var(--a)' : '') . '"><small>' . ($x['is_admin'] ? 'Support' : 'You') . ' · ' . $x['created'] . '</small><br>' . nl2br(e($x['body'])) . '</div>';
    if ($t['status'] != 'Closed') echo '<form method="post">' . csrf() . '<input type="hidden" name="tid" value="' . $tid . '"><textarea name="body" required placeholder="Write a reply"></textarea><button class="btn" name="ticket_reply">Send reply</button></form>';
    echo '<p><a href="?p=tickets">← All tickets</a></p></div>';
  } else { ?>
<form class="card" method="post"><?= csrf() ?><h3>Open a ticket</h3><label>Subject</label><input name="subject" required><label>Message</label><textarea name="body" required placeholder="Describe your issue (include order ID)"></textarea><button class="btn" name="ticket_new">Create ticket</button></form>
<div class="card"><table><tr><th>ID</th><th>Subject</th><th>Status</th><th>Updated</th></tr><?php foreach (q("SELECT * FROM tickets WHERE user_id=? ORDER BY updated DESC", [$u['id']]) as $t) echo "<tr><td>{$t['id']}</td><td><a href='?p=tickets&t={$t['id']}'>" . e($t['subject']) . "</a></td><td><span class='tag'>{$t['status']}</span></td><td>{$t['updated']}</td></tr>"; ?></table></div>
<?php }
} else { $base = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/api/v2.php'; ?>
<div class="card"><h3>Your API</h3><p>URL: <code><?= e($base) ?></code></p><p>Key: <code><?= e($u['api_key']) ?></code></p><p style="color:var(--mut)">Actions: services, add, status, balance (POST: key, action)</p><form method="post"><?= csrf() ?><button class="btn sm gh" name="newkey">Regenerate key</button></form></div>
<form class="card" method="post"><?= csrf() ?><h3>Change password</h3><input type="password" name="new" placeholder="New password (6+)" required><button class="btn" name="pw">Update</button></form>
<?php }
page_foot();

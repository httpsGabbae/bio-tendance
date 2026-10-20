<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/layout.php';
require_login();
$msg='';
if ($_SERVER['REQUEST_METHOD']==='POST') { csrf_check(); handle_row_ops();
  if (($_POST['op']??'')==='resetkey' && (int)($_POST['id']??0)) { $nk=bin2hex(random_bytes(16)); db()->prepare('UPDATE devices SET api_key_hash=? WHERE id=?')->execute([hash('sha256',$nk),(int)$_POST['id']]); audit('device_key_reset','id='.(int)$_POST['id']); $msg='Key reset. SECRET (copy once): '.$nk; }
  $code=trim($_POST['device_code']??''); $name=trim($_POST['device_name']??''); $loc=trim($_POST['location']??'');
  $vt = $_POST['vendor_type'] ?? 'Generic'; $vt = in_array($vt, ['ZKTeco','Suprema','Anviz','Hikvision','Generic'], true) ? $vt : 'Generic';
  if ($code!==''&&$name!=='') {
    $secret=bin2hex(random_bytes(16));
    try { db()->prepare("INSERT INTO devices (device_code,device_name,location,api_key_hash,vendor_type) VALUES (?,?,?,?,?)")->execute([$code,$name,$loc,hash('sha256',$secret),$vt]); $msg='Saved. SECRET (copy once): '.$secret; audit('device_added',$code); }
    catch (Throwable $e){ $msg='Error: could not save.'; }
  }
}
$rows=db()->query("SELECT id,device_code,device_name,location,vendor_type,status,last_seen_at FROM devices ORDER BY device_code")->fetchAll();
?>
<?php layout_start('Devices','devices'); ?><div class="card"><?=flash($msg)?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input name="device_code" placeholder="DEVICE-002" required><input name="device_name" placeholder="Name" required><input name="location" placeholder="Location"><select name="vendor_type"><option>Generic</option><option>ZKTeco</option><option>Suprema</option><option>Anviz</option><option>Hikvision</option></select><button class="btn">Add (generates secret)</button></form><div class="tw"><table><tr><th>Code</th><th>Name</th><th>Loc</th><th>Vendor</th><th>Status</th><th>Last seen</th><th></th></tr><?php foreach($rows as $r): ?><tr><td><?=e($r['device_code'])?></td><td><?=e($r['device_name'])?></td><td><?=e($r['location'])?></td><td><?=e($r['vendor_type'])?></td><td><?=badge($r['status'])?></td><td><?=e((string)$r['last_seen_at'])?></td><td><?=row_actions('devices',$r)?></td></tr><?php endforeach; ?></table></div></div><?php layout_end(); ?>

<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/face.php';
require_login();
$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT id, employee_no, first_name, last_name FROM employees WHERE id=?'); $st->execute([$id]); $emp = $st->fetch();
if (!$emp) { header('Location: employees.php'); exit; }
$name = $emp['first_name'].' '.$emp['last_name']; $has = in_array($id, face_registered_ids(), true);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json'); $j = json_decode(file_get_contents('php://input'), true) ?: [];
  if (!hash_equals(csrf_token(), (string)($j['csrf'] ?? ''))) { http_response_code(403); echo json_encode(['ok'=>false,'message'=>'Session expired. Reload the page.']); exit; }
  if (($j['op'] ?? '') === 'remove') {
    db()->prepare('DELETE FROM face_templates WHERE employee_id=?')->execute([$id]); db()->prepare('DELETE FROM face_profiles WHERE employee_id=?')->execute([$id]);
    @unlink(__DIR__."/../../storage/faces/$id.jpg"); audit('face_removed', $emp['employee_no']); echo json_encode(['ok'=>true]); exit;
  }
  $ds = $j['descriptors'] ?? [];
  if (empty($j['consent']) || !is_array($ds) || count($ds) < 3 || count($ds) > 6) { echo json_encode(['ok'=>false,'message'=>'Consent and 3-6 face samples are required.']); exit; }
  foreach ($ds as $d) { if (!face_valid($d)) { echo json_encode(['ok'=>false,'message'=>'Invalid face data.']); exit; } $m = face_match($d, $id); if ($m && $m['distance'] < FACE_DUP_MAX) { echo json_encode(['ok'=>false,'message'=>'This face already matches another employee.']); exit; } }
  $pdo = db(); $pdo->beginTransaction();
  $pdo->prepare('DELETE FROM face_templates WHERE employee_id=?')->execute([$id]);
  $ins = $pdo->prepare('INSERT INTO face_templates (employee_id,descriptor) VALUES (?,?)'); foreach ($ds as $d) $ins->execute([$id, json_encode(array_map('floatval', $d))]);
  $pdo->prepare('REPLACE INTO face_profiles (employee_id,consent_at,enrolled_by) VALUES (?,NOW(),?)')->execute([$id, $_SESSION['admin_id'] ?? null]);
  $pdo->commit(); face_save_photo($id, (string)($j['photo'] ?? '')); audit('face_enrolled', $emp['employee_no'].' ('.count($ds).' samples)'); echo json_encode(['ok'=>true]); exit;
}
$lib = face_lib('../assets/'); layout_start('Register face', 'employees');
?>
<div class="two"><div class="card"><div class="card-h">Camera <small id="st">Loading face models…</small></div>
<video id="v" autoplay muted playsinline style="width:100%;border-radius:12px;background:#0F172A;transform:scaleX(-1)"></video>
<p style="margin:12px 0 0;display:flex;gap:10px;flex-wrap:wrap"><button class="btn" id="cap" disabled>📸 Capture sample</button><label class="btn" style="background:#fff;color:var(--ink);border-color:#D5D9E6">Upload photo<input id="up" type="file" accept="image/*" hidden></label></p>
<p style="color:var(--ink2);font-size:13px">Capture 3–6 samples: look straight, then slightly left and right. Good, even lighting; no sunglasses or masks. Camera needs <b>HTTPS or localhost</b>.</p></div>
<div class="card"><div class="card-h"><?=avatar($name)?> <small><?=e($emp['employee_no'])?></small></div>
<?php if ($has): ?><p><?=badge('active')?> Face registered. <img src="face_photo.php?id=<?=$id?>" alt="" style="height:48px;border-radius:8px;vertical-align:middle"> Capturing again replaces it.</p><?php endif; ?>
<div id="th" style="display:flex;gap:8px;flex-wrap:wrap;margin:10px 0;min-height:64px"></div><div id="msg"></div>
<label class="chk" style="align-items:flex-start;margin:8px 0"><input type="checkbox" id="cs"> <span>The employee has given consent to store their facial data for attendance.</span></label>
<button class="btn" id="save" disabled>Save face data</button> <?php if ($has): ?><button id="rm">Remove face data</button><?php endif; ?> <a href="employees.php" style="margin-left:8px">Back</a></div></div>
<script src="<?=e($lib['js'])?>"></script>
<script>
var CSRF=<?=json_encode(csrf_token())?>,S=[],v=document.getElementById('v'),$=function(i){return document.getElementById(i)};
var opt=new faceapi.TinyFaceDetectorOptions({inputSize:320,scoreThreshold:.5});
function note(t,bad){$('msg').innerHTML='<div class="alert'+(bad?' err':'')+'">'+t+'</div>'}
function sync(){$('save').disabled=!(S.length>=3&&$('cs').checked)}$('cs').onchange=sync;
function post(o){o.csrf=CSRF;return fetch(location.href,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(o)}).then(function(r){return r.json()})}
async function add(src,w,h){var r=await faceapi.detectAllFaces(src,opt).withFaceLandmarks().withFaceDescriptors();
 if(r.length!==1){note(r.length?'More than one face found. One person only.':'No face found. Face the camera in good light.',1);return}
 var c=document.createElement('canvas');c.width=320;c.height=Math.round(320*h/w);c.getContext('2d').drawImage(src,0,0,c.width,c.height);
 S.push({d:Array.from(r[0].descriptor),p:c.toDataURL('image/jpeg',.85)});c.style.cssText='height:64px;border-radius:8px';$('th').appendChild(c);note('Sample '+S.length+' captured.');sync()}
(async function(){try{await Promise.all([faceapi.nets.tinyFaceDetector.loadFromUri(<?=json_encode($lib['models'])?>),faceapi.nets.faceLandmark68Net.loadFromUri(<?=json_encode($lib['models'])?>),faceapi.nets.faceRecognitionNet.loadFromUri(<?=json_encode($lib['models'])?>)]);
 $('st').textContent='Ready';$('cap').disabled=false;v.srcObject=await navigator.mediaDevices.getUserMedia({video:{width:640,height:480,facingMode:'user'}})}catch(e){$('st').textContent='Camera/model error';note('Could not start camera or load models ('+e.message+'). You can still upload photos.',1)}})();
$('cap').onclick=function(){add(v,v.videoWidth,v.videoHeight)};
$('up').onchange=function(e){var f=e.target.files[0];if(!f)return;var im=new Image();im.onload=function(){add(im,im.naturalWidth,im.naturalHeight)};im.src=URL.createObjectURL(f)};
$('save').onclick=async function(){$('save').disabled=true;var r=await post({descriptors:S.map(function(x){return x.d}),photo:S[0].p,consent:$('cs').checked});r.ok?location.href='employees.php':(note(r.message,1),sync())};
if($('rm'))$('rm').onclick=async function(){if(confirm('Delete this employee\'s face data?')){await post({op:'remove'});location.reload()}};
</script>
<?php layout_end(); ?>

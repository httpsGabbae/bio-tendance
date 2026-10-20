<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/face.php';
require_login();
$devs = db()->query("SELECT device_code, device_name, location FROM devices WHERE status='active' ORDER BY device_code")->fetchAll();
$dev = (string)($_GET['device'] ?? ''); $ok = false; foreach ($devs as $d) if ($d['device_code'] === $dev) $ok = true;
$lib = face_lib('assets/');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Kiosk · AttendX</title>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="assets/style.css">
<style>body{background:#0B1020;color:#fff;display:grid;place-items:center;min-height:100vh;text-align:center}.k{width:min(760px,94vw)}.k img.l{height:38px;margin-bottom:14px}
.cam{position:relative;border-radius:22px;overflow:hidden;background:#000;aspect-ratio:4/3;border:3px solid #4F46E5;transition:.2s}.cam video{width:100%;height:100%;object-fit:cover;transform:scaleX(-1)}
.cam.ok{border-color:#22C55E}.cam.bad{border-color:#EF4444}.res{position:absolute;inset:auto 0 0 0;padding:18px;background:linear-gradient(transparent,rgba(0,0,0,.85));min-height:96px}
.res b{font-size:26px;display:block}.t{font-size:44px;font-weight:700;font-variant-numeric:tabular-nums;margin:14px 0 2px}.k .btn{margin:6px}.k select{max-width:100%}</style></head><body><div class="k">
<img class="l" src="assets/logo-light.svg" alt="AttendX">
<?php if (!$ok): ?><div class="card" style="color:var(--ink);text-align:left"><h3>Start face kiosk</h3><p style="color:var(--ink2)">Choose the device this kiosk records under. It records IN on the first scan of the day and OUT after that.</p>
<form method="get"><select name="device" required><?php foreach ($devs as $d): ?><option value="<?=e($d['device_code'])?>"><?=e($d['device_code'].' — '.$d['device_name'].' · '.$d['location'])?></option><?php endforeach; ?></select><button class="btn">Start</button></form>
<?php if (!$devs): ?><p>No active devices. Add one under Devices first.</p><?php endif; ?><a href="admin/dashboard.php">← Back to admin</a></div>
<?php else: ?><div class="cam" id="cam"><video id="v" autoplay muted playsinline></video><div class="res" id="res"><b>Loading…</b></div></div>
<div class="t" id="clk"></div><div id="dbg" style="color:#64748B;font-size:12px;min-height:16px"></div><div style="color:#94A3B8"><?=e(date('l, F j, Y'))?> · <?=e($dev)?> · <a href="admin/dashboard.php" style="color:#94A3B8">Exit kiosk</a></div>
<script src="<?=e($lib['js'])?>"></script><script>
var $=function(i){return document.getElementById(i)},v=$('v'),busy=true,CSRF=<?=json_encode(csrf_token())?>,DEV=<?=json_encode($dev)?>,M=<?=json_encode($lib['models'])?>;
var opt=new faceapi.TinyFaceDetectorOptions({inputSize:224,scoreThreshold:.6});
setInterval(function(){$('clk').textContent=new Date().toLocaleTimeString([],{hour:'2-digit',minute:'2-digit',second:'2-digit'})},1000);
setInterval(function(){fetch('api/face_scan.php?ping=1').then(function(r){if(r.status===401){busy=true;$('cam').className='cam bad';$('res').innerHTML='<b>Session expired</b><a href="index.php" style="color:#93C5FD">Sign in again</a>'}}).catch(function(){})},240000);
function show(cls,html,ms){$('cam').className='cam '+cls;$('res').innerHTML=html;setTimeout(function(){$('cam').className='cam';$('res').innerHTML='<b>Look at the camera</b>';busy=false},ms)}
async function loop(){
 try{if(!busy&&v.readyState>=2&&v.videoWidth>0){
  var pre=await faceapi.detectSingleFace(v,opt);
  if(pre&&pre.box.width>v.videoWidth*.22){busy=true;var t0=performance.now();
   var r=await faceapi.detectSingleFace(v,opt).withFaceLandmarks().withFaceDescriptor();
   if(!r){busy=false}else{var t1=performance.now();
    var x=await fetch('api/face_scan.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf:CSRF,device:DEV,descriptor:Array.from(r.descriptor)})});
    var j=await x.json();$('dbg').textContent='face '+Math.round(t1-t0)+' ms · server '+Math.round(performance.now()-t1)+' ms';
    if(j.success&&j.repeat)show('ok','<b>'+j.name+'</b>'+j.message,3000);
    else if(j.success){var lt=j.late>0?' · '+j.late+' min late':'';show('ok','<b>'+(j.direction==='IN'?'Welcome, ':'Goodbye, ')+j.name+'</b>Time '+j.direction.toLowerCase()+' '+j.time+' · '+j.status.replace('_',' ').toLowerCase()+lt,4000)}
    else show('bad','<b>'+j.message+'</b>',2500)}}}
 }catch(e){busy=false;$('dbg').textContent='error: '+e.message}
 setTimeout(loop,400)}
(async function(){try{var t=performance.now();await Promise.all([faceapi.nets.tinyFaceDetector.loadFromUri(M),faceapi.nets.faceLandmark68Net.loadFromUri(M),faceapi.nets.faceRecognitionNet.loadFromUri(M)]);
 $('dbg').textContent='models loaded in '+Math.round(performance.now()-t)+' ms · backend '+faceapi.tf.getBackend();
 v.srcObject=await navigator.mediaDevices.getUserMedia({video:{width:640,height:480,facingMode:'user'}});await v.play();busy=false;$('res').innerHTML='<b>Look at the camera</b>';loop()}
 catch(e){$('res').innerHTML='<b>Camera/model error</b>'+e.message}})();
</script><?php endif; ?></div></body></html>

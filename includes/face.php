<?php
// Face matching runs on the SERVER: browsers send a 128-number descriptor, never download templates.
const FACE_MATCH_MAX = 0.5;   // lower = stricter
const FACE_DUP_MAX = 0.4;     // block enrolling the same face under two employees
function face_lib(string $assets): array {
  if (is_file(__DIR__.'/../public/assets/vendor/face-api/face-api.js')) return ['js'=>$assets.'vendor/face-api/face-api.js','models'=>$assets.'vendor/face-api/model'];
  $b = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.12'; return ['js'=>$b.'/dist/face-api.js','models'=>$b.'/model'];
}
function face_valid($d): bool { if (!is_array($d) || count($d) !== 128) return false; foreach ($d as $v) if (!is_numeric($v)) return false; return true; }
function face_match(array $probe, ?int $exclude = null): ?array {
  $best = [];
  foreach (db()->query("SELECT t.employee_id, t.descriptor FROM face_templates t JOIN employees e ON e.id=t.employee_id WHERE e.status='active'") as $r) {
    $id = (int)$r['employee_id']; if ($id === $exclude) continue;
    $t = json_decode($r['descriptor'], true); $s = 0.0;
    for ($i = 0; $i < 128; $i++) { $x = $probe[$i] - $t[$i]; $s += $x * $x; }
    $d = sqrt($s); if (!isset($best[$id]) || $d < $best[$id]) $best[$id] = $d;
  }
  if (!$best) return null; asort($best); $ids = array_keys($best);
  return ['employee_id'=>$ids[0], 'distance'=>$best[$ids[0]], 'second'=>isset($ids[1]) ? $best[$ids[1]] : null];
}
function face_registered_ids(): array { static $ids = null; if ($ids === null) { try { $ids = array_map('intval', db()->query('SELECT employee_id FROM face_profiles')->fetchAll(PDO::FETCH_COLUMN)); } catch (Throwable $e) { $ids = []; } } return $ids; }
function face_cell(int $id): string {
  return (in_array($id, face_registered_ids(), true) ? '<span class="badge b-active">Registered</span> ' : '<span class="badge">None</span> ').'<a href="face_enroll.php?id='.$id.'">'.(in_array($id, face_registered_ids(), true) ? 'Manage' : 'Register').'</a>';
}
function face_save_photo(int $id, string $dataUrl): void {
  if (!preg_match('#^data:image/jpeg;base64,#', $dataUrl)) return;
  $raw = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
  if (!$raw || strlen($raw) > 400000 || !($i = @getimagesizefromstring($raw)) || $i['mime'] !== 'image/jpeg') return;
  $dir = __DIR__.'/../storage/faces'; if (!is_dir($dir)) mkdir($dir, 0775, true);
  if (function_exists('imagecreatefromstring') && ($im = @imagecreatefromstring($raw))) { imagejpeg($im, "$dir/$id.jpg", 85); imagedestroy($im); } else file_put_contents("$dir/$id.jpg", $raw);
}

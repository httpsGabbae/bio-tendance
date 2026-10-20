<?php
// Printable ID badge with Code128 barcode (for the R45 scanner).
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/layout.php';
require_login();
$no = trim($_GET['no'] ?? '');
$emp = null;
if ($no !== '') {
  $st = db()->prepare("SELECT e.*, d.department_code FROM employees e LEFT JOIN departments d ON d.id=e.department_id WHERE e.employee_no=? LIMIT 1");
  $st->execute([$no]);
  $emp = $st->fetch() ?: null;
}
?>
<?php layout_start('ID Badge','employees'); ?>
<div class="card noprint"><a href="employees.php">← Back to Employees</a>
<?php if ($emp): ?> <button class="btn" onclick="window.print()">Print badge</button><?php endif; ?></div>
<?php if (!$emp): ?>
<div class="card">Employee not found. <a href="employees.php">Back to Employees</a></div>
<?php else: ?>
<div class="card" style="max-width:420px;margin:0 auto;text-align:center">
<h2 style="margin:4px 0"><?=e($emp['first_name'] . ' ' . $emp['last_name'])?></h2>
<p style="margin:4px 0"><?=e($emp['employee_no'])?><?php if (!empty($emp['department_code'])): ?> · <?=e($emp['department_code'])?><?php endif; ?></p>
<svg id="barcode"></svg>
<p><small>Scan at the R45 terminal to clock IN/OUT</small></p>
</div>
<script src="../assets/JsBarcode.all.min.js"></script>
<script>JsBarcode("#barcode", <?=json_encode($emp['employee_no'])?>, {format:"CODE128", width:2, height:80, displayValue:true});</script>
<?php endif; ?>
<?php layout_end(); ?>

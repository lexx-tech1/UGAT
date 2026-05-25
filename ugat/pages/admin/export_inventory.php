<?php
// ============================================================
//  admin/export_inventory.php
// ============================================================

ini_set('session.cookie_path', '/');
session_name('ugat_admin');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../config/db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../auth/Login.html'); exit;
}

$type   = $_GET['type']   ?? 'value';
$format = $_GET['format'] ?? 'pdf';
$date   = date('F j, Y');
$fname  = 'UGAT_Inventory_' . ucfirst($type) . '_' . date('Y-m-d');

$title_map = [
    'value'     => 'Stock Value by Item',
    'reorder'   => 'Reorder Summary',
    'movements' => 'Stock Movement Summary',
    'all'       => 'Complete Inventory Report',
];
$report_title = $title_map[$type] ?? 'Inventory Report';

// ── Fetch data ────────────────────────────────────────────
$value_rows = $reorder_rows = $movement_rows = [];
$total_val  = 0;

if ($type === 'value' || $type === 'all') {
    $r = $conn->query("SELECT name, category, unit, unit_price, quantity AS stock, (unit_price * quantity) AS stock_value FROM inventory ORDER BY (unit_price * quantity) DESC");
    while ($row = $r->fetch_assoc()) { $value_rows[] = $row; $total_val += (float)$row['stock_value']; }
}
if ($type === 'reorder' || $type === 'all') {
    $r = $conn->query("SELECT name, unit, quantity AS stock, low_stock_at AS reorder_point, max_stock, unit_price, GREATEST(max_stock - quantity, low_stock_at * 2) AS suggested_qty FROM inventory WHERE quantity <= low_stock_at ORDER BY quantity ASC");
    while ($row = $r->fetch_assoc()) $reorder_rows[] = $row;
}
if ($type === 'movements' || $type === 'all') {
    $r = $conn->query("SELECT i.name, i.unit, COALESCE(SUM(CASE WHEN t.type IN ('in','po') THEN t.qty ELSE 0 END),0) AS total_in, COALESCE(SUM(CASE WHEN t.type='out' THEN t.qty ELSE 0 END),0) AS total_out FROM inventory i LEFT JOIN inventory_transactions t ON t.item_id=i.id GROUP BY i.id ORDER BY i.name ASC");
    while ($row = $r->fetch_assoc()) {
        $row['net']      = $row['total_in'] - $row['total_out'];
        $row['turnover'] = $row['total_in'] > 0 ? round(($row['total_out']/$row['total_in'])*100).'%' : '—';
        $movement_rows[] = $row;
    }
}
$conn->close();

// ── CSV ───────────────────────────────────────────────────
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"$fname.csv\"");
    $out = fopen('php://output', 'w');

    if ($type === 'value' || $type === 'all') {
        if ($type === 'all') fputcsv($out, ['=== STOCK VALUE BY ITEM ===']);
        fputcsv($out, ['Item','Category','Unit','On Hand','Unit Price','Stock Value','% of Total']);
        $tv = array_sum(array_column($value_rows, 'stock_value'));
        foreach ($value_rows as $row) {
            $pct = $tv > 0 ? round(($row['stock_value']/$tv)*100,1).'%' : '0%';
            fputcsv($out, [$row['name'], $row['category']==='farm'?'Farm Product':'Training Kit', $row['unit'], $row['stock'], number_format($row['unit_price'],2), number_format($row['stock_value'],2), $pct]);
        }
        fputcsv($out, ['Total','','','','',number_format($tv,2),'100%']);
        if ($type === 'all') fputcsv($out, []);
    }
    if ($type === 'reorder' || $type === 'all') {
        if ($type === 'all') fputcsv($out, ['=== REORDER SUMMARY ===']);
        fputcsv($out, ['Item','Current Stock','Unit','Reorder Point','Suggested Qty','Est. Cost','Priority']);
        foreach ($reorder_rows as $row) {
            fputcsv($out, [$row['name'],$row['stock'],$row['unit'],$row['reorder_point'],$row['suggested_qty'],number_format($row['suggested_qty']*$row['unit_price'],2),$row['stock']==0?'Critical':'Medium']);
        }
        if ($type === 'all') fputcsv($out, []);
    }
    if ($type === 'movements' || $type === 'all') {
        if ($type === 'all') fputcsv($out, ['=== STOCK MOVEMENT SUMMARY ===']);
        fputcsv($out, ['Item','Unit','Total In','Total Out','Net Movement','Turnover Rate']);
        foreach ($movement_rows as $row) {
            fputcsv($out, [$row['name'],$row['unit'],$row['total_in'],$row['total_out'],($row['net']>=0?'+':'').$row['net'],$row['turnover']]);
        }
    }
    fclose($out); exit;
}

// ── HTML helpers ──────────────────────────────────────────
function tbl_value($rows, $tv) {
    if (empty($rows)) return '<p>No data.</p>';
    $h = '<table><thead><tr><th>#</th><th>Item</th><th>Category</th><th>On Hand</th><th>Unit Price</th><th>Stock Value</th><th>% of Total</th></tr></thead><tbody>';
    foreach ($rows as $i => $r) {
        $pct = $tv > 0 ? round(($r['stock_value']/$tv)*100,1).'%' : '0%';
        $bg  = $i%2===0?'#f9fff5':'#fff';
        $cat = $r['category']==='farm'?'Farm Product':'Training Kit';
        $h  .= "<tr style='background:$bg'><td>".($i+1)."</td><td>".htmlspecialchars($r['name'])."</td><td>$cat</td><td>{$r['stock']} {$r['unit']}</td><td>&#8369;".number_format($r['unit_price'],2)."</td><td style='font-weight:700;color:#4B8423'>&#8369;".number_format($r['stock_value'],2)."</td><td>$pct</td></tr>";
    }
    $h .= "<tr style='background:#f0f8e8;font-weight:700'><td colspan='5' style='text-align:right'>Total:</td><td style='color:#4B8423'>&#8369;".number_format($tv,2)."</td><td>100%</td></tr></tbody></table>";
    return $h;
}
function tbl_reorder($rows) {
    if (empty($rows)) return '<p style="padding:10px;color:#4B8423;background:#f0f8e8;border-radius:4px">&#10003; All items above reorder points!</p>';
    $h = '<table><thead><tr><th>#</th><th>Item</th><th>Current Stock</th><th>Reorder Pt.</th><th>Suggested Qty</th><th>Est. Cost</th><th>Priority</th></tr></thead><tbody>';
    foreach ($rows as $i => $r) {
        $cost=$r['suggested_qty']*$r['unit_price']; $bg=$i%2===0?'#fff8f0':'#fff';
        $pclr=$r['stock']==0?'#e74c3c':'#f4a523'; $prio=$r['stock']==0?'Critical':'Medium';
        $h .= "<tr style='background:$bg'><td>".($i+1)."</td><td>".htmlspecialchars($r['name'])."</td><td style='color:$pclr;font-weight:700'>{$r['stock']} {$r['unit']}</td><td>{$r['reorder_point']} {$r['unit']}</td><td style='font-weight:700'>{$r['suggested_qty']} {$r['unit']}</td><td>&#8369;".number_format($cost,2)."</td><td style='color:$pclr;font-weight:700'>$prio</td></tr>";
    }
    return $h.'</tbody></table>';
}
function tbl_movements($rows) {
    if (empty($rows)) return '<p>No transactions yet.</p>';
    $h = '<table><thead><tr><th>#</th><th>Item</th><th>Total In</th><th>Total Out</th><th>Net</th><th>Turnover</th></tr></thead><tbody>';
    foreach ($rows as $i => $r) {
        $bg=$i%2===0?'#f9fff5':'#fff'; $net=($r['net']>=0?'+':'').$r['net'];
        $h .= "<tr style='background:$bg'><td>".($i+1)."</td><td>".htmlspecialchars($r['name'])."</td><td style='color:#4B8423;font-weight:700'>+{$r['total_in']}</td><td style='color:#e74c3c;font-weight:700'>-{$r['total_out']}</td><td style='font-weight:700'>$net {$r['unit']}</td><td>{$r['turnover']}</td></tr>";
    }
    return $h.'</tbody></table>';
}

// Build content
$content = '';
if ($type === 'all') {
    $content .= '<h2 class="sh">1. Stock Value by Item</h2>'.tbl_value($value_rows,$total_val);
    $content .= '<h2 class="sh" style="margin-top:22px">2. Reorder Summary</h2>'.tbl_reorder($reorder_rows);
    $content .= '<h2 class="sh" style="margin-top:22px">3. Stock Movement Summary</h2>'.tbl_movements($movement_rows);
} elseif ($type === 'value') {
    $content = tbl_value($value_rows,$total_val);
} elseif ($type === 'reorder') {
    $content = tbl_reorder($reorder_rows);
} else {
    $content = tbl_movements($movement_rows);
}

// ── HTML output ───────────────────────────────────────────
// mode: 'print' = auto-print, 'pdf' = auto save-as-pdf, default = show page
$mode = $_GET['mode'] ?? 'print';
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($report_title) ?> — UGAT</title>
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  html,body{height:100%;font-family:Arial,sans-serif;font-size:11px;color:#222;background:#f4f4f4}
  .page-wrap{max-width:900px;margin:40px auto;background:#fff;padding:32px 36px;border-radius:8px;box-shadow:0 4px 24px rgba(0,0,0,0.08)}
  .header{background:#4B8423;color:white;padding:18px 20px;border-radius:6px;margin-bottom:14px;text-align:center}
  .header h1{font-size:18px;margin-bottom:4px}.header p{font-size:11px;opacity:.85}
  .meta{background:#f0f8e8;border:1px solid #c8e6a0;border-radius:4px;padding:8px 14px;margin-bottom:14px;font-size:11px;color:#4B8423}
  .sh{color:#4B8423;font-size:13px;font-weight:700;margin-bottom:8px;padding-bottom:4px;border-bottom:2px solid #4B8423}
  table{width:100%;border-collapse:collapse;margin-bottom:14px}
  th{background:#4B8423;color:white;padding:8px 10px;text-align:left;font-size:10px}
  td{padding:7px 10px;border-bottom:1px solid #eee}
  .footer{text-align:center;color:#888;font-size:9px;margin-top:20px;border-top:1px solid #eee;padding-top:10px}
  @media print{
    html,body{background:#fff}
    .page-wrap{margin:0;padding:16px;box-shadow:none;border-radius:0}
  }
</style>
</head>
<body>
<div class="page-wrap">
<div class="header">
  <h1>&#127807; UGAT Integrated Farm</h1>
  <p>Inventory Report &mdash; <?= htmlspecialchars($report_title) ?> &nbsp;&middot;&nbsp; <?= $date ?></p>
</div>
<div class="meta"><strong>Report:</strong> <?= htmlspecialchars($report_title) ?> &nbsp;|&nbsp; <strong>Generated:</strong> <?= $date ?></div>
<?= $content ?>
<div class="footer">UGAT TrainTrack System &nbsp;&middot;&nbsp; Confidential &nbsp;&middot;&nbsp; <?= $date ?></div>
</div><!-- /page-wrap -->
<script>
  // Auto-trigger based on mode
  window.addEventListener('load', function() {
    <?php if ($mode === 'print'): ?>
      window.print();
    <?php elseif ($mode === 'pdf'): ?>
      // For PDF: set print destination hint then print
      window.print();
    <?php endif; ?>
  });
</script>
</body>
</html>
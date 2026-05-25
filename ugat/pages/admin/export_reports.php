<?php
// ============================================================
//  admin/export_reports.php — Professional Print/PDF Report
// ============================================================
session_name('ugat_admin');
session_start();
require_once '../../config/db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../auth/Login.html'); exit;
}

$sections     = explode(',', $_GET['sections'] ?? 'program,attendance,certifications,inventory');
$format       = $_GET['format']  ?? 'pdf';
$period       = $_GET['period']  ?? 'all';
$from         = $_GET['from']    ?? null;
$to           = $_GET['to']      ?? null;
$generated    = date('F j, Y · g:i A');
$report_date  = date('F j, Y');

$period_labels = [
    'month'   => 'This Month — ' . date('F Y'),
    'quarter' => 'This Quarter (Last 3 Months)',
    'year'    => 'This Year — ' . date('Y'),
    'custom'  => 'Custom: ' . ($from ?? '—') . ' to ' . ($to ?? '—'),
    'all'     => 'All Time',
];
$period_label = $period_labels[$period] ?? 'All Time';

// ── Period WHERE helper ───────────────────────────────────────
function pw($col, $period, $from, $to) {
    switch ($period) {
        case 'month':   return "AND ($col IS NOT NULL AND $col >= DATE_FORMAT(NOW(),'%Y-%m-01') AND $col <= NOW())";
        case 'quarter': return "AND ($col IS NOT NULL AND $col >= DATE_SUB(NOW(), INTERVAL 3 MONTH))";
        case 'year':    return "AND ($col IS NOT NULL AND YEAR($col) = YEAR(NOW()))";
        case 'custom':
            $f = $from ? date('Y-m-d', strtotime($from)) : '1970-01-01';
            $t = $to   ? date('Y-m-d 23:59:59', strtotime($to)) : date('Y-m-d 23:59:59');
            return "AND ($col IS NOT NULL AND $col BETWEEN '$f' AND '$t')";
        default: return '';
    }
}
function safeQ($conn, $sql) { $r = $conn->query($sql); return $r ? $r->fetch_assoc() : null; }
function safeRows($conn, $sql) { $r = $conn->query($sql); if (!$r) return []; $out = []; while ($row = $r->fetch_assoc()) $out[] = $row; return $out; }

// Detect columns
$has_enr_at = $conn->query("SHOW COLUMNS FROM enrollments LIKE 'enrolled_at'")->num_rows > 0;
$has_enr_ca = $conn->query("SHOW COLUMNS FROM enrollments LIKE 'created_at'")->num_rows > 0;
$enr_col    = $has_enr_at ? 'enrolled_at' : ($has_enr_ca ? 'created_at' : null);
$att_col    = '';
foreach (['recorded_at','created_at','date'] as $c) {
    if ($conn->query("SHOW COLUMNS FROM attendance LIKE '$c'")->num_rows > 0) { $att_col = $c; break; }
}
$has_sms = $conn->query("SHOW COLUMNS FROM certificates LIKE 'sms_sent'")->num_rows > 0;
$has_tp  = $conn->query("SHOW TABLES LIKE 'trainee_profiles'")->num_rows > 0;
$name_sel = $has_tp ? "COALESCE(CONCAT(tp.first_name,' ',tp.last_name), SUBSTRING_INDEX(u.email,'@',1))" : "SUBSTRING_INDEX(u.email,'@',1)";
$tp_join  = $has_tp ? "LEFT JOIN trainee_profiles tp ON tp.user_id = u.id" : "";

$pw_user   = pw('created_at',  $period, $from, $to);
$pw_enroll = $enr_col ? pw($enr_col, $period, $from, $to) : '';
$pw_att    = $att_col ? pw($att_col, $period, $from, $to) : '';
$pw_cert   = pw('issued_at',   $period, $from, $to);

// ── Fetch data ────────────────────────────────────────────────
$prog = $att = $cert = $inv = [];

if (in_array('program', $sections)) {
    $prog['trainees']       = (int)(safeQ($conn, "SELECT COUNT(*) AS n FROM users WHERE role='trainee' $pw_user")['n'] ?? 0);
    $prog['active_ws']      = (int)(safeQ($conn, "SELECT COUNT(*) AS n FROM workshops WHERE status IN ('upcoming','ongoing')")['n'] ?? 0);
    $prog['upcoming']       = (int)(safeQ($conn, "SELECT COUNT(*) AS n FROM workshops WHERE status='upcoming'")['n'] ?? 0);
    $prog['ongoing']        = (int)(safeQ($conn, "SELECT COUNT(*) AS n FROM workshops WHERE status='ongoing'")['n'] ?? 0);
    $prog['completed']      = (int)(safeQ($conn, "SELECT COUNT(*) AS n FROM workshops WHERE status='completed'")['n'] ?? 0);
    $prog['sessions']       = (int)(safeQ($conn, "SELECT COUNT(*) AS n FROM workshop_sessions")['n'] ?? 0);
    $prog['certs']          = (int)(safeQ($conn, "SELECT COUNT(*) AS n FROM certificates WHERE status='issued' $pw_cert")['n'] ?? 0);
    $prog['enrolled']       = (int)(safeQ($conn, "SELECT COUNT(*) AS n FROM enrollments WHERE status='enrolled' $pw_enroll")['n'] ?? 0);
    $prog['workshops']      = safeRows($conn, "
        SELECT w.title, w.category, w.max_slots, w.status,
               COUNT(DISTINCT e.user_id) AS enrolled,
               COUNT(DISTINCT ws.id) AS total_sessions,
               SUM(CASE WHEN ws.status='completed' THEN 1 ELSE 0 END) AS done
        FROM workshops w
        LEFT JOIN enrollments e ON e.workshop_id = w.id
        LEFT JOIN workshop_sessions ws ON ws.workshop_id = w.id
        WHERE 1=1 $pw_enroll GROUP BY w.id ORDER BY w.title");
}

if (in_array('attendance', $sections)) {
    $ak = safeQ($conn, "SELECT COUNT(*) AS t, SUM(status='present') AS p, SUM(status='late') AS l, SUM(status='absent') AS a FROM attendance WHERE 1=1 $pw_att");
    $att['total']   = (int)($ak['t'] ?? 0);
    $att['present'] = (int)($ak['p'] ?? 0);
    $att['late']    = (int)($ak['l'] ?? 0);
    $att['absent']  = (int)($ak['a'] ?? 0);
    $att['rate']    = $att['total'] > 0 ? round((($att['present']+$att['late'])/$att['total'])*100) : 0;
    $att['by_ws']   = safeRows($conn, "
        SELECT w.title, SUM(a.status='present') AS p, SUM(a.status='late') AS l,
               SUM(a.status='absent') AS ab, COUNT(a.id) AS t
        FROM attendance a
        JOIN workshop_sessions ws ON ws.id = a.session_id
        JOIN workshops w ON w.id = ws.workshop_id
        WHERE 1=1 $pw_att GROUP BY w.id ORDER BY t DESC");
    $att['sessions'] = safeRows($conn, "
        SELECT w.title, ws.session_no, ws.session_date,
               SUM(a.status='present') AS p, SUM(a.status='late') AS l,
               SUM(a.status='absent') AS ab, COUNT(a.id) AS t
        FROM workshop_sessions ws
        JOIN workshops w ON w.id = ws.workshop_id
        LEFT JOIN attendance a ON a.session_id = ws.id
        WHERE 1=1 $pw_att GROUP BY ws.id ORDER BY ws.session_date DESC LIMIT 20");
}

if (in_array('certifications', $sections)) {
    $cert['issued']   = (int)(safeQ($conn, "SELECT COUNT(*) AS n FROM certificates WHERE status='issued' $pw_cert")['n'] ?? 0);
    $cert['eligible'] = (int)(safeQ($conn, "SELECT COUNT(*) AS n FROM certificates WHERE status='eligible'")['n'] ?? 0);
    $cert['enrolled'] = (int)(safeQ($conn, "SELECT COUNT(*) AS n FROM enrollments")['n'] ?? 0);
    $cert['rate']     = $cert['enrolled'] > 0 ? round(($cert['issued']/$cert['enrolled'])*100) : 0;
    $sms_col          = $has_sms ? 'c.sms_sent' : '0';
    $cert['log']      = safeRows($conn, "
        SELECT $name_sel AS name, w.title AS workshop,
               c.certificate_number, c.issued_at, c.attendance_rate, $sms_col AS sms_sent
        FROM certificates c
        JOIN users u ON u.id = c.user_id $tp_join
        JOIN workshops w ON w.id = c.workshop_id
        WHERE c.status = 'issued' $pw_cert ORDER BY c.issued_at DESC");
}

if (in_array('inventory', $sections)) {
    $ik = safeQ($conn, "SELECT SUM(unit_price*quantity) AS v, COUNT(*) AS s, SUM(quantity=0) AS o, SUM(quantity>0 AND quantity<=low_stock_at) AS l FROM inventory");
    $inv['value']    = (float)($ik['v'] ?? 0);
    $inv['skus']     = (int)($ik['s'] ?? 0);
    $inv['out']      = (int)($ik['o'] ?? 0);
    $inv['low']      = (int)($ik['l'] ?? 0);
    $inv['items']    = safeRows($conn, "SELECT name, category, unit, quantity AS stock, unit_price, (unit_price*quantity) AS value, CASE WHEN quantity=0 THEN 'out' WHEN quantity<=low_stock_at THEN 'low' ELSE 'ok' END AS status FROM inventory ORDER BY value DESC");
    $inv['reorder']  = safeRows($conn, "SELECT name, unit, quantity AS stock, low_stock_at AS rp, max_stock, unit_price FROM inventory WHERE quantity<=low_stock_at ORDER BY quantity ASC");
    $pw_tx   = pw('created_at', $period, $from, $to);
    $tx_sub  = "SELECT item_id, type, qty FROM inventory_transactions WHERE 1=1 " . $pw_tx;
    $mv_sql  = "SELECT i.name, i.unit, i.quantity AS cur,
                COALESCE(SUM(CASE WHEN t.type IN ('in','po') THEN t.qty ELSE 0 END),0) AS tin,
                COALESCE(SUM(CASE WHEN t.type='out' THEN t.qty ELSE 0 END),0) AS tout
                FROM inventory i LEFT JOIN (" . $tx_sub . ") AS t ON t.item_id=i.id
                GROUP BY i.id ORDER BY i.name";
    $inv['movements'] = safeRows($conn, $mv_sql);
}
$conn->close();

// ── CSV export mode ───────────────────────────────────────────
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="ugat-report-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel

    fputcsv($out, ['UGAT TrainTrack Report', 'Generated: ' . $generated, 'Period: ' . $period_label]);
    fputcsv($out, []);

    if (in_array('program', $sections) && !empty($prog)) {
        fputcsv($out, ['PROGRAM SUMMARY']);
        fputcsv($out, ['Total Trainees', $prog['trainees']]);
        fputcsv($out, ['Enrolled', $prog['enrolled']]);
        fputcsv($out, ['Certificates Issued', $prog['certs']]);
        fputcsv($out, ['Active Workshops', $prog['active_ws']]);
        fputcsv($out, ['Sessions Logged', $prog['sessions']]);
        fputcsv($out, []);
        fputcsv($out, ['Workshop', 'Category', 'Status', 'Enrolled', 'Max Slots', 'Fill %', 'Total Sessions', 'Completed %']);
        foreach ($prog['workshops'] as $w) {
            $fill = $w['max_slots'] > 0 ? round(($w['enrolled']/$w['max_slots'])*100) : 0;
            $comp = $w['total_sessions'] > 0 ? round(($w['done']/$w['total_sessions'])*100) : 0;
            fputcsv($out, [$w['title'], $w['category'] ?? '', ucfirst($w['status']), $w['enrolled'], $w['max_slots'], $fill . '%', $w['total_sessions'], $comp . '%']);
        }
        fputcsv($out, []);
    }

    if (in_array('attendance', $sections) && !empty($att)) {
        fputcsv($out, ['ATTENDANCE SUMMARY']);
        fputcsv($out, ['Total Records', 'Present', 'Late', 'Absent', 'Overall Rate']);
        fputcsv($out, [$att['total'], $att['present'], $att['late'], $att['absent'], $att['rate'] . '%']);
        fputcsv($out, []);
        fputcsv($out, ['Attendance by Workshop']);
        fputcsv($out, ['Workshop', 'Present', 'Late', 'Absent', 'Total']);
        foreach ($att['by_ws'] as $w) {
            fputcsv($out, [$w['title'], $w['p'], $w['l'], $w['ab'], $w['t']]);
        }
        fputcsv($out, []);
        fputcsv($out, ['Session Log (Last 20)']);
        fputcsv($out, ['Workshop', 'Session #', 'Date', 'Present', 'Late', 'Absent', 'Total']);
        foreach ($att['sessions'] as $s) {
            fputcsv($out, [$s['title'], $s['session_no'], $s['session_date'], $s['p'], $s['l'], $s['ab'], $s['t']]);
        }
        fputcsv($out, []);
    }

    if (in_array('certifications', $sections) && !empty($cert)) {
        fputcsv($out, ['CERTIFICATIONS']);
        fputcsv($out, ['Issued', 'Eligible', 'Total Enrolled', 'Completion Rate']);
        fputcsv($out, [$cert['issued'], $cert['eligible'], $cert['enrolled'], $cert['rate'] . '%']);
        fputcsv($out, []);
        fputcsv($out, ['Trainee', 'Workshop', 'Certificate Number', 'Issued At', 'Attendance Rate', 'SMS Sent']);
        foreach ($cert['log'] as $c) {
            fputcsv($out, [$c['name'], $c['workshop'], $c['certificate_number'], $c['issued_at'], number_format((float)$c['attendance_rate'], 1) . '%', $c['sms_sent'] ? 'Yes' : 'No']);
        }
        fputcsv($out, []);
    }

    if (in_array('inventory', $sections) && !empty($inv)) {
        fputcsv($out, ['INVENTORY']);
        fputcsv($out, ['Total Value', 'Total SKUs', 'Out of Stock', 'Low Stock']);
        fputcsv($out, ['PHP ' . number_format($inv['value'], 2), $inv['skus'], $inv['out'], $inv['low']]);
        fputcsv($out, []);
        fputcsv($out, ['Item', 'Category', 'Unit', 'Stock', 'Unit Price (PHP)', 'Total Value (PHP)', 'Status']);
        foreach ($inv['items'] as $i) {
            fputcsv($out, [$i['name'], $i['category'] ?? '', $i['unit'], $i['stock'], number_format($i['unit_price'], 2), number_format($i['value'], 2), ucfirst($i['status'])]);
        }
        fputcsv($out, []);
        if (!empty($inv['reorder'])) {
            fputcsv($out, ['Reorder List']);
            fputcsv($out, ['Item', 'Unit', 'Current Stock', 'Reorder Point', 'Max Stock', 'Unit Price (PHP)']);
            foreach ($inv['reorder'] as $i) {
                fputcsv($out, [$i['name'], $i['unit'], $i['stock'], $i['rp'], $i['max_stock'], number_format($i['unit_price'], 2)]);
            }
            fputcsv($out, []);
        }
        if (!empty($inv['movements'])) {
            fputcsv($out, ['Stock Movements']);
            fputcsv($out, ['Item', 'Unit', 'Current Stock', 'Total In', 'Total Out']);
            foreach ($inv['movements'] as $m) {
                fputcsv($out, [$m['name'], $m['unit'], $m['cur'], $m['tin'], $m['tout']]);
            }
        }
    }

    fclose($out);
    exit;
}

// Section number helper
$sec_num = 0;
function secNum() { global $sec_num; return ++$sec_num; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UGAT Report — <?= $report_date ?></title>
<style>
/* ── Reset ── */
* { margin:0; padding:0; box-sizing:border-box; }

/* ── Screen: centered A4-like page ── */
body {
  font-family: 'Segoe UI', Arial, sans-serif;
  font-size: 11pt;
  color: #1a1a1a;
  background: #e8e8e8;
  padding: 24px;
}

.page {
  width: 794px;
  min-height: 1123px;
  margin: 0 auto 32px;
  background: #fff;
  padding: 48px 52px;
  box-shadow: 0 4px 32px rgba(0,0,0,.18);
  position: relative;
}

/* ── Print button (screen only) ── */
.print-bar {
  width: 794px;
  margin: 0 auto 16px;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
.btn-print {
  background: #4B8423; color: #fff; border: none;
  padding: 10px 22px; border-radius: 6px;
  font-size: 13px; font-weight: 700; cursor: pointer;
  display: flex; align-items: center; gap: 6px;
}
.btn-print:hover { background: #3a6b1a; }
.btn-close {
  background: #fff; color: #555; border: 1.5px solid #ccc;
  padding: 10px 18px; border-radius: 6px;
  font-size: 13px; cursor: pointer;
}

/* ── Document header ── */
.doc-header {
  background: linear-gradient(135deg, #4B8423 0%, #2d5a12 100%);
  color: #fff;
  padding: 28px 32px;
  border-radius: 8px;
  margin-bottom: 24px;
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
}
.doc-header-left h1 {
  font-size: 20pt;
  font-weight: 800;
  letter-spacing: -0.3px;
  margin-bottom: 4px;
}
.doc-header-left p {
  font-size: 9.5pt;
  opacity: .85;
  margin-top: 2px;
}
.doc-header-right {
  text-align: right;
  font-size: 9pt;
  opacity: .85;
  line-height: 1.6;
}
.doc-header-right strong {
  display: block;
  font-size: 11pt;
  opacity: 1;
}

/* ── Meta bar ── */
.meta-bar {
  background: #f0f8e8;
  border: 1px solid #c8e6a0;
  border-radius: 6px;
  padding: 9px 16px;
  margin-bottom: 22px;
  font-size: 9pt;
  color: #3a6b1a;
  display: flex;
  justify-content: space-between;
}

/* ── Section title ── */
.sec-title {
  font-size: 13pt;
  font-weight: 800;
  color: #4B8423;
  margin: 28px 0 14px;
  padding-bottom: 6px;
  border-bottom: 2.5px solid #4B8423;
  display: flex;
  align-items: center;
  gap: 8px;
}
.sec-title:first-of-type { margin-top: 0; }

/* ── KPI row ── */
.kpi-row {
  display: grid;
  gap: 12px;
  margin-bottom: 16px;
}
.kpi-row-5 { grid-template-columns: repeat(5,1fr); }
.kpi-row-4 { grid-template-columns: repeat(4,1fr); }
.kpi-row-3 { grid-template-columns: repeat(3,1fr); }

.kpi {
  background: #f8fbf5;
  border: 1.5px solid #d4edba;
  border-radius: 8px;
  padding: 12px 14px;
  text-align: center;
}
.kpi-num {
  font-size: 18pt;
  font-weight: 800;
  color: #4B8423;
  line-height: 1.1;
}
.kpi-lbl {
  font-size: 8pt;
  color: #666;
  margin-top: 3px;
  font-weight: 600;
}
.kpi.danger  { background:#fff5f5; border-color:#ffc9c9; }
.kpi.danger .kpi-num { color:#c0392b; }
.kpi.warning { background:#fffbf0; border-color:#ffe08a; }
.kpi.warning .kpi-num { color:#b8860b; }

/* ── Tables ── */
table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 18px;
  font-size: 9.5pt;
}
thead tr {
  background: #4B8423;
}
thead th {
  color: #fff;
  padding: 8px 10px;
  text-align: left;
  font-weight: 700;
  font-size: 9pt;
  letter-spacing: 0.2px;
}
tbody td {
  padding: 7px 10px;
  border-bottom: 1px solid #eef2e8;
  vertical-align: top;
}
tbody tr:nth-child(even) td { background: #f8fbf5; }
tbody tr:hover td { background: #f0f8e6; }
.num { font-weight: 700; }
.green  { color: #4B8423; font-weight:700; }
.red    { color: #c0392b; font-weight:700; }
.orange { color: #b8860b; font-weight:700; }
.mono   { font-family: 'Courier New', monospace; color: #4B8423; font-size:9pt; }

/* ── Stacked bar ── */
.bar-wrap { display:flex; height:8px; border-radius:4px; overflow:hidden; background:#eee; width:100%; }
.bar-p { background:#4B8423; }
.bar-l { background:#f4a523; }
.bar-a { background:#e74c3c; }

/* ── Footer ── */
.doc-footer {
  position: absolute;
  bottom: 24px;
  left: 52px;
  right: 52px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 8pt;
  color: #aaa;
  border-top: 1px solid #e8e8e8;
  padding-top: 8px;
}

/* ── Print styles ── */
@media print {
  body { background: #fff; padding: 0; }
  .print-bar { display: none !important; }
  .page {
    width: 100%;
    min-height: auto;
    margin: 0;
    padding: 24px 28px 60px;
    box-shadow: none;
  }
  .doc-footer { position: fixed; bottom: 0; left:28px; right:28px; }
  thead { display: table-header-group; }
  tr    { page-break-inside: avoid; }
  .sec-title { page-break-before: auto; }
  .no-break  { page-break-inside: avoid; }
}
</style>
</head>
<body>

<!-- Print / Close bar (screen only) -->
<div class="print-bar">
  <button class="btn-close" onclick="window.close()">✕ Close</button>
  <button class="btn-print" onclick="window.print()">⬇ Save as PDF</button>
</div>

<div class="page">

  <!-- Document header -->
  <div class="doc-header">
    <div class="doc-header-left">
      <h1>🌿 UGAT Integrated Farm</h1>
      <p>Program & Operations Report &nbsp;·&nbsp; San Isidro, Daet, Camarines Norte</p>
      <p style="margin-top:6px;font-size:10pt;opacity:1;font-weight:700">
        <?= htmlspecialchars(implode(' · ', array_map('ucfirst', $sections))) ?>
      </p>
    </div>
    <div class="doc-header-right">
      <strong><?= $report_date ?></strong>
      Period: <?= htmlspecialchars($period_label) ?><br>
      Generated: <?= $generated ?>
    </div>
  </div>

  <!-- Meta bar -->
  <div class="meta-bar">
    <span><strong>Report Sections:</strong> <?= implode(', ', array_map('ucfirst', $sections)) ?></span>
    <span><strong>System:</strong> UGAT TrainTrack &nbsp;·&nbsp; <strong>Confidential</strong></span>
  </div>


  <?php if (in_array('program', $sections)): $n = secNum(); ?>
  <!-- ═══ PROGRAM SUMMARY ═══ -->
  <div class="sec-title"><?= $n ?>. Program Summary</div>

  <div class="kpi-row kpi-row-5">
    <div class="kpi"><div class="kpi-num"><?= $prog['trainees'] ?></div><div class="kpi-lbl">Trainees</div></div>
    <div class="kpi"><div class="kpi-num"><?= $prog['enrolled'] ?></div><div class="kpi-lbl">Enrolled</div></div>
    <div class="kpi"><div class="kpi-num"><?= $prog['certs'] ?></div><div class="kpi-lbl">Certified</div></div>
    <div class="kpi"><div class="kpi-num"><?= $prog['active_ws'] ?></div><div class="kpi-lbl">Active Workshops</div></div>
    <div class="kpi"><div class="kpi-num"><?= $prog['sessions'] ?></div><div class="kpi-lbl">Sessions Logged</div></div>
  </div>

  <table>
    <thead><tr>
      <th>#</th><th>Workshop</th><th>Category</th><th>Enrolled</th><th>Max</th>
      <th>Fill Rate</th><th>Sessions</th><th>Completed</th><th>Status</th>
    </tr></thead>
    <tbody>
    <?php foreach ($prog['workshops'] as $i => $w):
      $fill = $w['max_slots'] > 0 ? round(($w['enrolled']/$w['max_slots'])*100) : 0;
      $comp = $w['total_sessions'] > 0 ? round(($w['done']/$w['total_sessions'])*100) : 0;
      $st_clr = $w['status']==='ongoing' ? 'green' : ($w['status']==='completed' ? 'green' : 'orange');
    ?>
    <tr>
      <td><?= $i+1 ?></td>
      <td><strong><?= htmlspecialchars($w['title']) ?></strong></td>
      <td><?= htmlspecialchars($w['category'] ?? '—') ?></td>
      <td class="num"><?= $w['enrolled'] ?></td>
      <td><?= $w['max_slots'] ?></td>
      <td><?= $fill ?>%</td>
      <td><?= $w['total_sessions'] ?></td>
      <td><?= $comp ?>%</td>
      <td class="<?= $st_clr ?>"><?= ucfirst($w['status']) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($prog['workshops'])): ?>
    <tr><td colspan="9" style="text-align:center;color:#aaa;padding:16px">No workshops for this period.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  <?php endif; ?>


  <?php if (in_array('attendance', $sections)): $n = secNum(); ?>
  <!-- ═══ ATTENDANCE ═══ -->
  <div class="sec-title"><?= $n ?>. Attendance Report</div>

  <div class="kpi-row kpi-row-4">
    <div class="kpi"><div class="kpi-num"><?= $att['total'] ?></div><div class="kpi-lbl">Total Records</div></div>
    <div class="kpi"><div class="kpi-num"><?= $att['present'] ?></div><div class="kpi-lbl">Present</div></div>
    <div class="kpi warning"><div class="kpi-num"><?= $att['late'] ?></div><div class="kpi-lbl">Late</div></div>
    <div class="kpi <?= $att['rate'] >= 80 ? '' : 'warning' ?>">
      <div class="kpi-num"><?= $att['rate'] ?>%</div><div class="kpi-lbl">Overall Rate</div>
    </div>
  </div>

  <?php if (!empty($att['by_ws'])): ?>
  <table>
    <thead><tr><th>#</th><th>Workshop</th><th>Present</th><th>Late</th><th>Absent</th><th>Total</th><th>Rate</th><th style="width:120px">Breakdown</th></tr></thead>
    <tbody>
    <?php foreach ($att['by_ws'] as $i => $w):
      $t    = max(1, (int)$w['t']);
      $rate = round((((int)$w['p']+(int)$w['l'])/$t)*100);
      $pp   = round(((int)$w['p']/$t)*100);
      $lp   = round(((int)$w['l']/$t)*100);
      $ap   = round(((int)$w['ab']/$t)*100);
    ?>
    <tr>
      <td><?= $i+1 ?></td>
      <td><strong><?= htmlspecialchars($w['title']) ?></strong></td>
      <td class="green"><?= $w['p'] ?></td>
      <td class="orange"><?= $w['l'] ?></td>
      <td class="red"><?= $w['ab'] ?></td>
      <td><?= $w['t'] ?></td>
      <td class="num <?= $rate>=80?'green':'orange' ?>"><?= $rate ?>%</td>
      <td>
        <div class="bar-wrap">
          <div class="bar-p" style="width:<?= $pp ?>%"></div>
          <div class="bar-l" style="width:<?= $lp ?>%"></div>
          <div class="bar-a" style="width:<?= $ap ?>%"></div>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?><p style="color:#aaa;padding:8px 0 16px">No attendance data for this period.</p><?php endif; ?>

  <?php if (!empty($att['sessions'])): ?>
  <p style="font-weight:700;color:#4B8423;margin:12px 0 8px;font-size:10pt">Session Log</p>
  <table>
    <thead><tr><th>#</th><th>Workshop</th><th>Session</th><th>Date</th><th>Present</th><th>Late</th><th>Absent</th><th>Rate</th></tr></thead>
    <tbody>
    <?php foreach ($att['sessions'] as $i => $s):
      $t = max(1,(int)$s['t']);
      $r = round((((int)$s['p']+(int)$s['l'])/$t)*100);
    ?>
    <tr>
      <td><?= $i+1 ?></td>
      <td><?= htmlspecialchars($s['title']) ?></td>
      <td>Session <?= $s['session_no'] ?></td>
      <td><?= $s['session_date'] ? date('M j, Y', strtotime($s['session_date'])) : '—' ?></td>
      <td class="green"><?= $s['p'] ?></td>
      <td class="orange"><?= $s['l'] ?></td>
      <td class="red"><?= $s['ab'] ?></td>
      <td class="num <?= $r>=80?'green':'orange' ?>"><?= $r ?>%</td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
  <?php endif; ?>


  <?php if (in_array('certifications', $sections)): $n = secNum(); ?>
  <!-- ═══ CERTIFICATIONS ═══ -->
  <div class="sec-title"><?= $n ?>. Certification Report</div>

  <div class="kpi-row kpi-row-3">
    <div class="kpi"><div class="kpi-num"><?= $cert['issued'] ?></div><div class="kpi-lbl">Certificates Issued</div></div>
    <div class="kpi warning"><div class="kpi-num"><?= $cert['eligible'] ?></div><div class="kpi-lbl">Eligible — Pending</div></div>
    <div class="kpi"><div class="kpi-num"><?= $cert['rate'] ?>%</div><div class="kpi-lbl">Certification Rate</div></div>
  </div>

  <?php if (!empty($cert['log'])): ?>
  <table>
    <thead><tr><th>#</th><th>Trainee</th><th>Workshop</th><th>Certificate No.</th><th>Issued On</th><th>Attendance</th><th>SMS</th></tr></thead>
    <tbody>
    <?php foreach ($cert['log'] as $i => $c): ?>
    <tr>
      <td><?= $i+1 ?></td>
      <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
      <td><?= htmlspecialchars($c['workshop']) ?></td>
      <td class="mono"><?= htmlspecialchars($c['certificate_number'] ?? '—') ?></td>
      <td><?= $c['issued_at'] ? date('M j, Y', strtotime($c['issued_at'])) : '—' ?></td>
      <td><?= $c['attendance_rate'] !== null ? $c['attendance_rate'].'%' : '—' ?></td>
      <td class="<?= $c['sms_sent'] ? 'green' : 'orange' ?>"><?= $c['sms_sent'] ? 'Sent' : 'Pending' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?><p style="color:#aaa;padding:8px 0 16px">No certificates issued for this period.</p><?php endif; ?>
  <?php endif; ?>


  <?php if (in_array('inventory', $sections)): $n = secNum(); ?>
  <!-- ═══ INVENTORY ═══ -->
  <div class="sec-title"><?= $n ?>. Inventory Summary</div>

  <div class="kpi-row kpi-row-4">
    <div class="kpi"><div class="kpi-num">₱<?= number_format($inv['value'],0) ?></div><div class="kpi-lbl">Total Stock Value</div></div>
    <div class="kpi"><div class="kpi-num"><?= $inv['skus'] ?></div><div class="kpi-lbl">Total SKUs</div></div>
    <div class="kpi warning"><div class="kpi-num"><?= $inv['low'] ?></div><div class="kpi-lbl">Low Stock</div></div>
    <div class="kpi danger"><div class="kpi-num"><?= $inv['out'] ?></div><div class="kpi-lbl">Out of Stock</div></div>
  </div>

  <table>
    <thead><tr><th>#</th><th>Item</th><th>Category</th><th>On Hand</th><th>Unit Price</th><th>Stock Value</th><th>% of Total</th><th>Status</th></tr></thead>
    <tbody>
    <?php
    $tv = array_sum(array_column($inv['items'], 'value'));
    foreach ($inv['items'] as $i => $item):
      $pct = $tv > 0 ? round(($item['value']/$tv)*100,1) : 0;
      $sc  = $item['status']==='out' ? 'red' : ($item['status']==='low' ? 'orange' : 'green');
      $sl  = $item['status']==='out' ? 'Out of Stock' : ($item['status']==='low' ? 'Low Stock' : 'In Stock');
    ?>
    <tr>
      <td><?= $i+1 ?></td>
      <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
      <td><?= $item['category']==='farm'?'Farm Product':'Training Kit' ?></td>
      <td><?= $item['stock'] ?> <?= $item['unit'] ?></td>
      <td>₱<?= number_format($item['unit_price'],2) ?></td>
      <td class="num green">₱<?= number_format($item['value'],2) ?></td>
      <td><?= $pct ?>%</td>
      <td class="<?= $sc ?>"><?= $sl ?></td>
    </tr>
    <?php endforeach; ?>
    <tr style="background:#f0f8e6;font-weight:700">
      <td colspan="5" style="text-align:right;padding-right:12px">Total:</td>
      <td class="green">₱<?= number_format($tv,2) ?></td>
      <td>100%</td><td></td>
    </tr>
    </tbody>
  </table>

  <?php if (!empty($inv['reorder'])): ?>
  <p style="font-weight:700;color:#c0392b;margin:12px 0 8px;font-size:10pt">⚠ Reorder Alerts</p>
  <table>
    <thead><tr><th>#</th><th>Item</th><th>Current Stock</th><th>Reorder Point</th><th>Suggested Qty</th><th>Est. Cost</th><th>Priority</th></tr></thead>
    <tbody>
    <?php foreach ($inv['reorder'] as $i => $item):
      $sug  = max($item['max_stock']-$item['stock'], $item['rp']*2);
      $cost = $sug * $item['unit_price'];
      $pr   = $item['stock']==0 ? 'Critical' : 'Medium';
      $pc   = $item['stock']==0 ? 'red' : 'orange';
    ?>
    <tr>
      <td><?= $i+1 ?></td>
      <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
      <td class="<?= $pc ?>"><?= $item['stock'] ?> <?= $item['unit'] ?></td>
      <td><?= $item['rp'] ?> <?= $item['unit'] ?></td>
      <td class="num"><?= $sug ?> <?= $item['unit'] ?></td>
      <td>₱<?= number_format($cost,2) ?></td>
      <td class="<?= $pc ?> num"><?= $pr ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <?php if (!empty($inv['movements'])): ?>
  <p style="font-weight:700;color:#4B8423;margin:12px 0 8px;font-size:10pt">Stock Movements — <?= htmlspecialchars($period_label) ?></p>
  <table>
    <thead><tr><th>#</th><th>Item</th><th>Opening Stock</th><th>Total In</th><th>Total Out</th><th>Current Stock</th><th>Net</th></tr></thead>
    <tbody>
    <?php foreach ($inv['movements'] as $i => $m):
      $open = max(0, $m['cur'] - $m['tin'] + $m['tout']);
      $net  = (int)$m['tin'] - (int)$m['tout'];
    ?>
    <tr>
      <td><?= $i+1 ?></td>
      <td><strong><?= htmlspecialchars($m['name']) ?></strong></td>
      <td><?= $open ?> <?= $m['unit'] ?></td>
      <td class="green">+<?= $m['tin'] ?></td>
      <td class="red">-<?= $m['tout'] ?></td>
      <td class="num"><?= $m['cur'] ?> <?= $m['unit'] ?></td>
      <td class="num <?= $net>=0?'green':'red' ?>"><?= $net>=0?'+':'' ?><?= $net ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
  <?php endif; ?>


  <!-- Document footer -->
  <div class="doc-footer">
    <span>UGAT TrainTrack System &nbsp;·&nbsp; Confidential</span>
    <span><?= $generated ?></span>
    <span>Page 1</span>
  </div>

</div><!-- /page -->

<script>
// Auto-trigger print after a short delay so page renders fully
window.addEventListener('load', function() {
  setTimeout(function() {
    // Don't auto-print — let user click the button
  }, 500);
});
</script>
</body>
</html>
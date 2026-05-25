<?php
// ============================================================
//  admin/export_certificates.php
//  Generates a filtered PDF report of issued certificates.
// ============================================================

ini_set('session.cookie_path', '/');
session_name('ugat_admin');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../config/db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../auth/Login.html');
    exit;
}

// ── Filters ───────────────────────────────────────────────
$scope      = $_GET['scope']     ?? 'all';
$fromDate   = $_GET['from']      ?? '';
$toDate     = $_GET['to']        ?? '';
$workshopId = (int)($_GET['workshop_id'] ?? 0);
$userIdsRaw = $_GET['user_ids']  ?? '';

// Parse multiple user IDs
$userIds = [];
if ($userIdsRaw) {
    foreach (explode(',', $userIdsRaw) as $id) {
        $id = (int)trim($id);
        if ($id > 0) $userIds[] = $id;
    }
}

// ── Build WHERE ───────────────────────────────────────────
$where = ["c.status = 'issued'"];

if ($fromDate) $where[] = "DATE(c.issued_at) >= '" . $conn->real_escape_string($fromDate) . "'";
if ($toDate)   $where[] = "DATE(c.issued_at) <= '" . $conn->real_escape_string($toDate)   . "'";

if ($scope === 'workshop' && $workshopId)
    $where[] = "c.workshop_id = $workshopId";

if ($scope === 'trainee' && !empty($userIds))
    $where[] = "c.user_id IN (" . implode(',', $userIds) . ")";

$whereSQL = implode(' AND ', $where);

// ── Fetch ─────────────────────────────────────────────────
$r = $conn->query(
    "SELECT c.certificate_number AS cert_no, c.issued_at,
            CONCAT(t.first_name, ' ', t.last_name) AS trainee_name,
            u.email, t.phone,
            w.title AS workshop, w.id AS workshop_id, c.user_id,
            (SELECT COUNT(*) FROM workshop_sessions ws WHERE ws.workshop_id = w.id) AS total_sessions,
            (SELECT COUNT(*) FROM attendance a
             JOIN workshop_sessions ws2 ON ws2.id = a.session_id
             WHERE a.user_id = u.id AND ws2.workshop_id = w.id
             AND a.status = 'present') AS sessions_attended
     FROM certificates c
     JOIN users u            ON u.id = c.user_id
     JOIN trainee_profiles t ON t.user_id = u.id
     JOIN workshops w        ON w.id = c.workshop_id
     WHERE $whereSQL
     ORDER BY c.issued_at DESC"
);

$certs = [];
while ($row = $r->fetch_assoc()) $certs[] = $row;

// Fetch workshops and trainees for dropdowns in modal (returned as JSON if requested)
if (isset($_GET['get_options'])) {
    $ws = $conn->query("SELECT id, title FROM workshops ORDER BY title");
    $workshops = [];
    while ($row = $ws->fetch_assoc()) $workshops[] = $row;

    $tr = $conn->query(
        "SELECT DISTINCT u.id, CONCAT(t.first_name,' ',t.last_name) AS name, u.email
         FROM certificates c
         JOIN users u ON u.id = c.user_id
         JOIN trainee_profiles t ON t.user_id = u.id
         WHERE c.status='issued'
         ORDER BY name"
    );
    $trainees = [];
    while ($row = $tr->fetch_assoc()) $trainees[] = $row;

    header('Content-Type: application/json');
    echo json_encode(['workshops' => $workshops, 'trainees' => $trainees]);
    $conn->close();
    exit;
}

// ── CSV export ────────────────────────────────────────────
$format = $_GET['format'] ?? 'pdf';
if ($format === 'csv') {
    $conn->close();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="ugat-certificates-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['UGAT Certificate Export', 'Generated: ' . date('F j, Y'), 'Total: ' . count($certs)]);
    fputcsv($out, []);
    fputcsv($out, ['Certificate No.', 'Trainee Name', 'Email', 'Phone', 'Workshop', 'Sessions Attended', 'Total Sessions', 'Attendance %', 'Issued At']);
    foreach ($certs as $c) {
        $rate = $c['total_sessions'] > 0 ? round(($c['sessions_attended'] / $c['total_sessions']) * 100, 1) : 0;
        fputcsv($out, [$c['cert_no'], $c['trainee_name'], $c['email'], $c['phone'] ?? '', $c['workshop'], $c['sessions_attended'], $c['total_sessions'], $rate . '%', $c['issued_at']]);
    }
    fclose($out);
    exit;
}

$conn->close();

// ── Build filter label ────────────────────────────────────
$selected_count = count($userIds);
$scope_label = match($scope) {
    'workshop' => 'Specific Workshop',
    'trainee'  => $selected_count > 0 ? "$selected_count Trainee(s) Selected" : 'All Trainees',
    default    => 'All Certificates',
};
$date_label = '';
if ($fromDate || $toDate)
    $date_label = ' | ' . ($fromDate ?: 'start') . ' → ' . ($toDate ?: 'today');

$date  = date('F j, Y');
$total = count($certs);

// ── HTML Report ───────────────────────────────────────────
header('Content-Type: text/html; charset=utf-8');
$rows = '';
foreach ($certs as $i => $c) {
    $issued   = $c['issued_at'] ? date('M j, Y', strtotime($c['issued_at'])) : '—';
    $sessions = ($c['sessions_attended'] ?? 0) . ' / ' . ($c['total_sessions'] ?? 0);
    $bg       = ($i % 2 === 0) ? '#f9fff5' : '#ffffff';
    $rows .= "
    <tr style='background:$bg'>
        <td style='text-align:center'>" . ($i + 1) . "</td>
        <td>" . htmlspecialchars($c['trainee_name']) . "</td>
        <td style='color:#666;font-size:10px'>" . htmlspecialchars($c['email']) . "</td>
        <td>" . htmlspecialchars($c['workshop']) . "</td>
        <td style='text-align:center;font-family:monospace;color:#4B8423;font-weight:bold'>" . htmlspecialchars($c['cert_no']) . "</td>
        <td style='text-align:center'>$issued</td>
        <td style='text-align:center'>$sessions</td>
    </tr>";
}

if (!$rows) {
    $rows = "<tr><td colspan='7' style='text-align:center;padding:20px;color:#888'>No certificates found for the selected filters.</td></tr>";
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>UGAT Certificate Report</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: Arial, sans-serif; font-size: 11px; color: #222; padding: 20px; }
  .header { background: #4B8423; color: white; padding: 16px 20px; border-radius: 6px; margin-bottom: 8px; text-align: center; }
  .header h1 { font-size: 18px; margin-bottom: 4px; }
  .header p  { font-size: 11px; opacity: 0.85; }
  .filter-bar { background: #f0f8e8; border: 1px solid #c8e6a0; border-radius: 4px; padding: 8px 14px; margin-bottom: 14px; font-size: 11px; color: #4B8423; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
  th { background: #4B8423; color: white; padding: 8px 10px; text-align: left; font-size: 10px; }
  td { padding: 7px 10px; border-bottom: 1px solid #e8f5e0; }
  .summary { text-align: right; font-weight: bold; color: #4B8423; margin-bottom: 20px; }
  .footer { text-align: center; color: #888; font-size: 9px; margin-top: 20px; border-top: 1px solid #eee; padding-top: 10px; }
  .print-btn { background:#4B8423; color:white; border:none; padding:8px 20px; border-radius:4px; cursor:pointer; font-size:13px; }
  @media print { .no-print { display:none; } body { padding: 10px; } }
</style>
</head>
<body>

<div class="no-print" style="margin-bottom:16px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
  <button class="print-btn" onclick="window.print()">🖨 Print</button>
  <button class="print-btn" style="background:#1a56db" onclick="downloadAsPDF()">⬇ Download PDF</button>
</div>
<script>
function downloadAsPDF() {
  document.querySelector('.no-print').style.display = 'none';
  document.title = 'UGAT_Certificates_<?= date("Y-m-d") ?>';
  window.print();
  setTimeout(() => { document.querySelector('.no-print').style.display = 'flex'; }, 1000);
}
if (new URLSearchParams(window.location.search).get('download') === '1') {
  window.addEventListener('load', () => {
    document.querySelector('.no-print').style.display = 'none';
    window.print();
  });
}
</script>

<div class="header">
  <h1>🌱 UGAT Integrated Farm</h1>
  <p>Certificate Issuance Report &nbsp;·&nbsp; Generated: <?= $date ?></p>
</div>

<div class="filter-bar">
  <strong>Scope:</strong> <?= htmlspecialchars($scope_label) ?>
  <?= $date_label ? ' &nbsp;|&nbsp; <strong>Date range:</strong> ' . htmlspecialchars($date_label) : '' ?>
  &nbsp;|&nbsp; <strong>Total shown:</strong> <?= $total ?>
</div>

<table>
  <thead>
    <tr>
      <th>#</th>
      <th>Trainee Name</th>
      <th>Email</th>
      <th>Workshop</th>
      <th>Certificate No.</th>
      <th>Issued On</th>
      <th>Sessions</th>
    </tr>
  </thead>
  <tbody><?= $rows ?></tbody>
</table>

<div class="summary">Total Certificates: <?= $total ?></div>
<div class="footer">UGAT TrainTrack System &nbsp;·&nbsp; Confidential &nbsp;·&nbsp; <?= $date ?></div>

</body>
</html>
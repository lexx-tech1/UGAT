<?php
// seed_barangays.php — UGAT TrainTrack v4 (fixed: false vs null)
// Visit with ?force=1 to wipe and re-seed.

ini_set('display_errors', 0);
ini_set('max_execution_time', 300);
error_reporting(0);
require_once '../../config/db.php';
set_time_limit(300);

$log   = [];
$force = isset($_GET['force']);

$check = $conn->query("SHOW TABLES LIKE 'barangays'");
if (!$check || $check->num_rows === 0) {
    die(page(['❌ Run seed_address.php first — barangays table missing.']));
}

$countRes = $conn->query("SELECT COUNT(*) AS cnt FROM barangays");
$count    = $countRes ? (int)$countRes->fetch_assoc()['cnt'] : 0;

if ($count > 0 && !$force) {
    die(page([
        "ℹ️ Already has $count barangays.",
        "To re-seed, visit with <code>?force=1</code>",
        "🎉 Done! Delete this file when finished.",
    ]));
}

if ($force && $count > 0) {
    $conn->query("TRUNCATE TABLE barangays");
    $log[] = "🗑️ Cleared $count old barangays";
}

// ── Load cities with multi-key index ──────────────────────────
$citiesRes = $conn->query("SELECT id, name FROM cities");
$cityMap   = [];
while ($row = $citiesRes->fetch_assoc()) {
    $id = (int)$row['id'];
    foreach ([
        $row['name'],
        preg_replace('/\s+city$/i',               '', $row['name']),
        preg_replace('/^city\s+of\s+/i',          '', $row['name']),
        preg_replace('/^municipality\s+of\s+/i',  '', $row['name']),
        preg_replace('/\s+(city|municipality)$/i','', $row['name']),
    ] as $v) {
        $k = normalize($v);
        if ($k !== '' && !isset($cityMap[$k])) $cityMap[$k] = $id;
    }
}
$totalCities = (int)$conn->query("SELECT COUNT(*) AS c FROM cities")->fetch_assoc()['c'];
$log[] = "✅ Loaded $totalCities cities → " . count($cityMap) . " lookup keys";

// ── Fetch & match PSGC cities ─────────────────────────────────
$log[] = "⏳ Fetching city/municipality codes from PSGC API…";
$psgcCities = fetchJson('https://psgc.gitlab.io/api/cities-municipalities/');
if ($psgcCities === null) {
    die(page(array_merge($log, ['❌ Cannot reach PSGC API.'])));
}

$codeToDbId = [];
$unmatched  = 0;
foreach ($psgcCities as $pc) {
    $rawName = $pc['name'] ?? '';
    $code    = $pc['code'] ?? null;
    if (!$code) continue;

    $dbId = null;
    foreach ([
        normalize($rawName),
        normalize(preg_replace('/\s+city$/i',               '', $rawName)),
        normalize(preg_replace('/^city\s+of\s+/i',          '', $rawName)),
        normalize(preg_replace('/^municipality\s+of\s+/i',  '', $rawName)),
        normalize(preg_replace('/\s+(city|municipality)$/i','', $rawName)),
    ] as $attempt) {
        if (isset($cityMap[$attempt])) { $dbId = $cityMap[$attempt]; break; }
    }

    if ($dbId === null) { $unmatched++; continue; }
    $codeToDbId[$code] = $dbId;
}
$log[] = "✅ Matched " . count($codeToDbId) . " / " . count($psgcCities) . " PSGC cities (unmatched: $unmatched)";

// ── Fetch all barangays ───────────────────────────────────────
$log[] = "⏳ Fetching 42,000+ barangays from PSGC (30–90 sec)…";
$psgcBarangays = fetchJson('https://psgc.gitlab.io/api/barangays/');
if ($psgcBarangays === null) {
    die(page(array_merge($log, ['❌ Could not fetch barangays.'])));
}
$log[] = "✅ Fetched " . count($psgcBarangays) . " barangays from PSGC";

// ── Insert ────────────────────────────────────────────────────
$inserted = 0;
$skipped  = 0;

$conn->begin_transaction();
$stmt = $conn->prepare("INSERT INTO barangays (psgc_code, name, city_id) VALUES (?, ?, ?)");

foreach ($psgcBarangays as $brgy) {

    // ╔══════════════════════════════════════════════════════╗
    // ║  THE FIX: API returns false (not null) when a field  ║
    // ║  doesn't apply. PHP ?? only skips null, not false.   ║
    // ║  So we must explicitly skip false/empty values.      ║
    // ╚══════════════════════════════════════════════════════╝
    $rawCode = null;
    foreach (['cityCode', 'municipalityCode', 'subMunicipalityCode'] as $field) {
        $val = $brgy[$field] ?? null;
        if ($val !== null && $val !== false && $val !== '' && $val !== '0') {
            $rawCode = (string)$val;
            break;
        }
    }

    if (!$rawCode || !isset($codeToDbId[$rawCode])) {
        $skipped++;
        continue;
    }

    $dbId = $codeToDbId[$rawCode];
    $name = $brgy['name'] ?? '';
    $code = $brgy['code'] ?? null;

    $stmt->bind_param("ssi", $code, $name, $dbId);
    $stmt->execute();
    $inserted++;
}

$stmt->close();
$conn->commit();

$log[] = "✅ Inserted $inserted barangays";
if ($skipped > 0) $log[] = "ℹ️ Skipped $skipped (sub-municipality NCR or unmatched)";
$log[] = "🎉 Done! <strong>Delete this file now.</strong>";
echo page($log);

// ── Helpers ───────────────────────────────────────────────────
function fetchJson(string $url): ?array {
    $opts = [
        'http' => ['method'=>'GET','timeout'=>90,'header'=>"User-Agent: UGAT/4.0\r\n"],
        'ssl'  => ['verify_peer'=>false,'verify_peer_name'=>false],
    ];
    $raw = @file_get_contents($url, false, stream_context_create($opts));
    if ($raw === false) return null;
    $d = json_decode($raw, true);
    return is_array($d) ? $d : null;
}

function normalize(string $s): string {
    return strtolower(trim(preg_replace('/\s+/', ' ', $s)));
}

function page(array $log): string {
    $items = implode('', array_map(fn($l) => "<li style='margin:.4rem 0'>$l</li>", $log));
    return "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Barangay Seeder v4</title>
    <style>body{font-family:system-ui;max-width:700px;margin:3rem auto;padding:0 1rem}
    h2{color:#4B8423}.box{background:#f9f9f9;border:1px solid #ddd;border-radius:8px;padding:1rem 1.5rem}
    code{background:#eee;padding:2px 6px;border-radius:4px;font-size:.85rem}</style></head><body>
    <h2>🌱 UGAT Barangay Seeder v4</h2><div class='box'><ul>$items</ul></div></body></html>";
}
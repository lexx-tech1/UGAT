<?php
// ============================================================
//  get_address.php — UGAT TrainTrack
//  Cascading address dropdowns: Region → Province → City → Barangay
//
//  Expected DB tables (PSGC-style):
//    regions    (id, psgc_code, name)
//    provinces  (id, psgc_code, name, region_id)
//    cities     (id, psgc_code, name, province_id)   ← cities/municipalities
//    barangays  (id, psgc_code, name, city_id)
//
//  Usage:
//    get_address.php?type=regions
//    get_address.php?type=provinces&region_id=5
//    get_address.php?type=cities&province_id=12
//    get_address.php?type=barangays&city_id=47
// ============================================================
ini_set('display_errors', 0);
error_reporting(0);
session_name('ugat_admin');
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');
header('Cache-Control: public, max-age=86400'); // Cache for 1 day (address data rarely changes)

// ── Helper: send JSON response ────────────────────────────────
function respond(bool $success, array $data = [], string $message = ''): void {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

$type = trim($_GET['type'] ?? '');

// ── REGIONS ──────────────────────────────────────────────────
if ($type === 'regions') {
    $result = $conn->query('SELECT id, name FROM regions ORDER BY name ASC');
    if (!$result) respond(false, [], 'Failed to fetch regions.');

    $regions = [];
    while ($row = $result->fetch_assoc()) {
        $regions[] = ['id' => (int)$row['id'], 'name' => $row['name']];
    }
    respond(true, ['regions' => $regions]);
}

// ── PROVINCES ────────────────────────────────────────────────
if ($type === 'provinces') {
    $region_id = (int)($_GET['region_id'] ?? 0);
    if (!$region_id) respond(false, [], 'region_id is required.');

    $stmt = $conn->prepare('SELECT id, name FROM provinces WHERE region_id = ? ORDER BY name ASC');
    $stmt->bind_param('i', $region_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $provinces = [];
    while ($row = $result->fetch_assoc()) {
        $provinces[] = ['id' => (int)$row['id'], 'name' => $row['name']];
    }
    $stmt->close();
    respond(true, ['provinces' => $provinces]);
}

// ── CITIES / MUNICIPALITIES ───────────────────────────────────
if ($type === 'cities') {
    $province_id = (int)($_GET['province_id'] ?? 0);
    if (!$province_id) respond(false, [], 'province_id is required.');

    $stmt = $conn->prepare('SELECT id, name FROM cities WHERE province_id = ? ORDER BY name ASC');
    $stmt->bind_param('i', $province_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $cities = [];
    while ($row = $result->fetch_assoc()) {
        $cities[] = ['id' => (int)$row['id'], 'name' => $row['name']];
    }
    $stmt->close();
    respond(true, ['cities' => $cities]);
}

// ── BARANGAYS ────────────────────────────────────────────────
if ($type === 'barangays') {
    $city_id = (int)($_GET['city_id'] ?? 0);
    if (!$city_id) respond(false, [], 'city_id is required.');

    $stmt = $conn->prepare('SELECT id, name FROM barangays WHERE city_id = ? ORDER BY name ASC');
    $stmt->bind_param('i', $city_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $barangays = [];
    while ($row = $result->fetch_assoc()) {
        $barangays[] = ['id' => (int)$row['id'], 'name' => $row['name']];
    }
    $stmt->close();
    respond(true, ['barangays' => $barangays]);
}

// ── BARANGAYS BY CITY NAME (used by register page) ───────────
if ($type === 'city_barangays') {
    $city_name = trim($_GET['city_name'] ?? '');
    if (!$city_name) respond(false, [], 'city_name is required.');

    $stmt = $conn->prepare('
        SELECT b.name
        FROM barangays b
        JOIN cities c ON c.id = b.city_id
        WHERE c.name = ?
        ORDER BY b.name ASC
    ');
    $stmt->bind_param('s', $city_name);
    $stmt->execute();
    $result = $stmt->get_result();

    $barangays = [];
    while ($row = $result->fetch_assoc()) {
        $barangays[] = ['name' => $row['name']];
    }
    $stmt->close();
    respond(true, ['barangays' => $barangays]);
}

// ── Unknown type ──────────────────────────────────────────────
respond(false, [], "Unknown type: '{$type}'. Use regions, provinces, cities, or barangays.");
<?php
// ============================================================
//  get_shipping_fee.php — UGAT TrainTrack
//  Calculates shipping fee based on total weight + delivery zone
//
//  POST body (JSON):
//    { "city_id": 123, "total_weight_kg": 1.5, "subtotal": 300 }
//
//  Returns:
//    { "success": true, "shipping_fee": 70, "zone": "Same Municipality", "is_free": false }
// ============================================================
ini_set('display_errors', 0);
error_reporting(0);
session_name('ugat_trainee');

session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data            = json_decode(file_get_contents('php://input'), true);
$city_id         = (int)($data['city_id']          ?? 0);
$total_weight_kg = (float)($data['total_weight_kg'] ?? 0);
$subtotal        = (float)($data['subtotal']         ?? 0);

if (!$city_id || $total_weight_kg <= 0) {
    echo json_encode([
        'success'      => true,
        'shipping_fee' => 50,
        'zone'         => 'Standard',
        'zone_id'      => 1,
        'is_free'      => false,
    ]);
    exit;
}

// ── Get UGAT location dynamically from settings ──────────────
$ugat_city_id     = 0;
$ugat_province_id = 0;

$setting = $conn->query("SELECT value FROM settings WHERE `key` = 'org_address' LIMIT 1");
if ($setting && $row = $setting->fetch_assoc()) {
    // org_address = "San Isidro, Daet, Camarines Norte"
    // Try each comma-separated part as a city name
    $parts = array_map('trim', explode(',', $row['value']));
    foreach ($parts as $part) {
        $s = $conn->prepare("SELECT id, province_id FROM cities WHERE name = ? LIMIT 1");
        $s->bind_param('s', $part);
        $s->execute();
        $found = $s->get_result()->fetch_assoc();
        $s->close();
        if ($found) {
            $ugat_city_id     = (int)$found['id'];
            $ugat_province_id = (int)$found['province_id'];
            break;
        }
    }
}
// Fallback to hardcoded if lookup fails
if (!$ugat_city_id)     $ugat_city_id     = 602; // Daet
if (!$ugat_province_id) $ugat_province_id = 29;  // Camarines Norte

// ── Get province of delivery city ────────────────────────────
$stmt = $conn->prepare("SELECT province_id FROM cities WHERE id = ?");
$stmt->bind_param('i', $city_id);
$stmt->execute();
$city_row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$delivery_province_id = $city_row ? (int)$city_row['province_id'] : 0;

// ── Determine zone ────────────────────────────────────────────
// zone 1 = Same Municipality, zone 2 = Same Province, zone 3 = Inter-Province
$zone_id = 3;
if ($city_id === $ugat_city_id) {
    $zone_id = 1;
} elseif ($delivery_province_id === $ugat_province_id) {
    $zone_id = 2;
}

// ── Lookup shipping rate ──────────────────────────────────────
$stmt = $conn->prepare("
    SELECT rate, free_threshold
    FROM shipping_rates
    WHERE zone_id = ?
      AND min_weight_kg <= ?
      AND max_weight_kg >= ?
    LIMIT 1
");
$stmt->bind_param('idd', $zone_id, $total_weight_kg, $total_weight_kg);
$stmt->execute();
$rate_row = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fallback: highest bracket in zone
if (!$rate_row) {
    $stmt = $conn->prepare("
        SELECT rate, free_threshold FROM shipping_rates
        WHERE zone_id = ? ORDER BY max_weight_kg DESC LIMIT 1
    ");
    $stmt->bind_param('i', $zone_id);
    $stmt->execute();
    $rate_row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$rate           = $rate_row ? (float)$rate_row['rate']    : 50;
$free_threshold = $rate_row ? $rate_row['free_threshold']  : null;
$is_free        = $free_threshold !== null && $subtotal >= (float)$free_threshold;

$zone_names = [1 => 'Same Municipality', 2 => 'Same Province', 3 => 'Inter-Province'];
$zone_name  = $zone_names[$zone_id] ?? 'Standard';

echo json_encode([
    'success'        => true,
    'shipping_fee'   => $is_free ? 0 : $rate,
    'original_fee'   => $rate,
    'zone'           => $zone_name,
    'zone_id'        => $zone_id,
    'is_free'        => $is_free,
    'free_threshold' => $free_threshold,
]);
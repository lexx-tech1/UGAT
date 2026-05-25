<?php
// ============================================================
//  place_order_gcash_qr.php
//  Handles manual GCash QR payment orders (multipart/form-data)
//  POST fields: address_id, items (JSON string), notes, gcash_ref
//  POST file:   receipt (required image)
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

$user_id        = (int)$_SESSION['user_id'];
$address_id     = (int)($_POST['address_id'] ?? 0);
$gcash_ref      = trim($_POST['gcash_ref'] ?? '');
$notes          = trim($_POST['notes']     ?? '');
$items          = json_decode($_POST['items'] ?? '[]', true);

if (!$address_id || empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Address and items are required.']);
    exit;
}
if (empty($gcash_ref)) {
    echo json_encode(['success' => false, 'message' => 'GCash reference number is required.']);
    exit;
}

// ── Validate & save receipt file ──────────────────────────────
if (empty($_FILES['receipt']['tmp_name'])) {
    echo json_encode(['success' => false, 'message' => 'GCash receipt image is required.']);
    exit;
}

$file     = $_FILES['receipt'];
$ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed  = ['jpg','jpeg','png','gif','webp','heic'];
if (!in_array($ext, $allowed)) {
    echo json_encode(['success' => false, 'message' => 'Receipt must be an image file (JPG, PNG, etc.)']);
    exit;
}
if ($file['size'] > 8 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'Receipt file must be under 8 MB.']);
    exit;
}

$receipts_dir = __DIR__ . '/../../uploads/receipts/';
if (!is_dir($receipts_dir)) mkdir($receipts_dir, 0755, true);

$filename       = 'receipt_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
$receipt_path   = 'uploads/receipts/' . $filename;
$dest           = $receipts_dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save receipt. Please try again.']);
    exit;
}

// ── Validate address ──────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM user_addresses WHERE id = ? AND user_id = ?");
$stmt->bind_param('ii', $address_id, $user_id);
$stmt->execute();
$addr = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$addr) {
    echo json_encode(['success' => false, 'message' => 'Invalid address.']);
    exit;
}

$addr_snapshot = json_encode([
    'full_name'      => $addr['full_name'],
    'contact_number' => $addr['contact_number'],
    'address_line'   => $addr['address_line'],
    'barangay_id'    => $addr['barangay_id'],
    'city_id'        => $addr['city_id'],
    'province_id'    => $addr['province_id'],
    'region_id'      => $addr['region_id'],
]);

// ── Validate & price items ────────────────────────────────────
$subtotal     = 0;
$total_weight = 0;
$order_items  = [];

foreach ($items as $item) {
    $prod_id  = (int)($item['product_id'] ?? 0);
    $quantity = (int)($item['quantity']   ?? 1);
    if (!$prod_id || $quantity < 1) continue;

    $stmt = $conn->prepare("SELECT id, name, unit_price, quantity AS stock, weight_kg FROM inventory WHERE id = ? AND quantity >= ?");
    $stmt->bind_param('ii', $prod_id, $quantity);
    $stmt->execute();
    $prod = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$prod) {
        echo json_encode(['success' => false, 'message' => 'Product ID ' . $prod_id . ' is unavailable or has insufficient stock.']);
        exit;
    }

    $weight_kg     = (float)($prod['weight_kg'] ?? 0);
    $subtotal     += (float)$prod['unit_price'] * $quantity;
    $total_weight += $weight_kg * $quantity;
    $order_items[] = ['product_id' => $prod_id, 'quantity' => $quantity, 'unit_price' => (float)$prod['unit_price'], 'weight_kg' => $weight_kg];
}

if (empty($order_items)) {
    echo json_encode(['success' => false, 'message' => 'No valid items in order.']);
    exit;
}

// ── Shipping fee (same logic as place_order.php) ──────────────
$ugat_city_id = 602; $ugat_province_id = 29;
$setting = $conn->query("SELECT value FROM settings WHERE `key` = 'org_address' LIMIT 1");
if ($setting && $srow = $setting->fetch_assoc()) {
    foreach (array_map('trim', explode(',', $srow['value'])) as $part) {
        $s = $conn->prepare("SELECT id, province_id FROM cities WHERE name = ? LIMIT 1");
        $s->bind_param('s', $part); $s->execute();
        $found = $s->get_result()->fetch_assoc(); $s->close();
        if ($found) { $ugat_city_id = (int)$found['id']; $ugat_province_id = (int)$found['province_id']; break; }
    }
}

$city_id = (int)($addr['city_id'] ?? 0);
$zone_id = 3;
if ($city_id) {
    $stmt = $conn->prepare("SELECT province_id FROM cities WHERE id = ?");
    $stmt->bind_param('i', $city_id); $stmt->execute();
    $cr = $stmt->get_result()->fetch_assoc(); $stmt->close();
    $dp = $cr ? (int)$cr['province_id'] : 0;
    if ($city_id === $ugat_city_id)  $zone_id = 1;
    elseif ($dp === $ugat_province_id) $zone_id = 2;
}

$stmt = $conn->prepare("SELECT rate, free_threshold FROM shipping_rates WHERE zone_id = ? AND min_weight_kg <= ? AND max_weight_kg >= ? LIMIT 1");
$stmt->bind_param('idd', $zone_id, $total_weight, $total_weight);
$stmt->execute();
$rate_row = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$rate_row) {
    $stmt = $conn->prepare("SELECT rate, free_threshold FROM shipping_rates WHERE zone_id = ? ORDER BY max_weight_kg DESC LIMIT 1");
    $stmt->bind_param('i', $zone_id); $stmt->execute();
    $rate_row = $stmt->get_result()->fetch_assoc(); $stmt->close();
}

$shipping_fee   = $rate_row ? (float)$rate_row['rate'] : 50;
$free_threshold = $rate_row ? $rate_row['free_threshold'] : null;
if ($free_threshold !== null && $subtotal >= (float)$free_threshold) $shipping_fee = 0;
$total = $subtotal + $shipping_fee;

$payment_method = 'gcash';

// ── Insert order ──────────────────────────────────────────────
$conn->begin_transaction();
try {
    $stmt = $conn->prepare("
        INSERT INTO orders
            (user_id, address_id, address_snapshot, payment_method, gcash_ref,
             gcash_screenshot, subtotal, shipping_fee, total, notes, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->bind_param('iissssddds',
        $user_id, $address_id, $addr_snapshot,
        $payment_method, $gcash_ref, $receipt_path,
        $subtotal, $shipping_fee, $total, $notes
    );
    $stmt->execute();
    $order_id = (int)$conn->insert_id;
    $stmt->close();

    foreach ($order_items as $oi) {
        $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price, weight_kg) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('iiidd', $order_id, $oi['product_id'], $oi['quantity'], $oi['unit_price'], $oi['weight_kg']);
        $stmt->execute(); $stmt->close();

        $stmt = $conn->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?");
        $stmt->bind_param('ii', $oi['quantity'], $oi['product_id']);
        $stmt->execute(); $stmt->close();
    }

    // Log
    $stmt = $conn->prepare("INSERT INTO order_status_logs (order_id, status, changed_by, notes) VALUES (?, 'pending', ?, 'Order placed via GCash QR — receipt uploaded')");
    $stmt->bind_param('ii', $order_id, $user_id); $stmt->execute(); $stmt->close();

    $conn->commit();
    echo json_encode(['success' => true, 'order_id' => $order_id, 'total' => $total, 'shipping_fee' => $shipping_fee]);

} catch (Exception $e) {
    $conn->rollback();
    // Remove uploaded receipt on failure
    if (file_exists($dest)) unlink($dest);
    error_log('GCash QR order error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Order failed. Please try again.']);
}

$conn->close();

<?php
// ============================================================
//  place_order.php — UGAT TrainTrack
//  Saves a new order to the database
//
//  POST body (JSON):
//  {
//    "address_id":     1,
//    "payment_method": "cod" | "gcash",
//    "gcash_ref":      "optional ref number",
//    "items":          [{ "product_id": 5, "quantity": 2 }],
//    "notes":          "optional notes"
//  }
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

$user_id = (int)$_SESSION['user_id'];
$data    = json_decode(file_get_contents('php://input'), true);

$address_id     = (int)($data['address_id']     ?? 0);
$payment_method = in_array($data['payment_method'] ?? '', ['cod', 'gcash'])
                    ? $data['payment_method'] : 'cod';
$gcash_ref      = trim($data['gcash_ref'] ?? '');
$notes          = trim($data['notes']     ?? '');
$items          = $data['items']          ?? [];

if (!$address_id || empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Address and items are required.']);
    exit;
}

// ── Validate address belongs to user ─────────────────────────
$stmt = $conn->prepare("SELECT * FROM user_addresses WHERE id = ? AND user_id = ?");
$stmt->bind_param('ii', $address_id, $user_id);
$stmt->execute();
$addr = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$addr) {
    echo json_encode(['success' => false, 'message' => 'Invalid address.']);
    exit;
}

// ── Snapshot address (in case user edits it later) ───────────
$addr_snapshot = json_encode([
    'full_name'      => $addr['full_name'],
    'contact_number' => $addr['contact_number'],
    'address_line'   => $addr['address_line'],
    'barangay_id'    => $addr['barangay_id'],
    'city_id'        => $addr['city_id'],
    'province_id'    => $addr['province_id'],
    'region_id'      => $addr['region_id'],
]);

// ── Validate & price each item ────────────────────────────────
$subtotal     = 0;
$total_weight = 0;
$order_items  = [];

foreach ($items as $item) {
    $prod_id  = (int)($item['product_id'] ?? 0);
    $quantity = (int)($item['quantity']   ?? 1);
    if (!$prod_id || $quantity < 1) continue;

    $stmt = $conn->prepare("
        SELECT id, name, unit_price, quantity AS stock, weight_kg
        FROM inventory
        WHERE id = ? AND quantity >= ?
    ");
    $stmt->bind_param('ii', $prod_id, $quantity);
    $stmt->execute();
    $prod = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$prod) {
        echo json_encode([
            'success' => false,
            'message' => 'Product ID ' . $prod_id . ' is unavailable or has insufficient stock.'
        ]);
        exit;
    }

    $weight_kg     = isset($prod['weight_kg']) ? (float)$prod['weight_kg'] : 0;
    $subtotal     += (float)$prod['unit_price'] * $quantity;
    $total_weight += $weight_kg * $quantity;
    $order_items[] = [
        'product_id' => $prod_id,
        'quantity'   => $quantity,
        'unit_price' => (float)$prod['unit_price'],
        'weight_kg'  => $weight_kg,
    ];
}

if (empty($order_items)) {
    echo json_encode(['success' => false, 'message' => 'No valid items in order.']);
    exit;
}

// ── Get UGAT location dynamically from settings ───────────────
$ugat_city_id     = 0;
$ugat_province_id = 0;

$setting = $conn->query("SELECT value FROM settings WHERE `key` = 'org_address' LIMIT 1");
if ($setting && $srow = $setting->fetch_assoc()) {
    $parts = array_map('trim', explode(',', $srow['value']));
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
if (!$ugat_city_id)     $ugat_city_id     = 602; // Daet fallback
if (!$ugat_province_id) $ugat_province_id = 29;  // Camarines Norte fallback

// ── Determine delivery zone ───────────────────────────────────
$city_id = (int)($addr['city_id'] ?? 0);
$zone_id = 3; // default: inter-province

if ($city_id) {
    $stmt = $conn->prepare("SELECT province_id FROM cities WHERE id = ?");
    $stmt->bind_param('i', $city_id);
    $stmt->execute();
    $city_row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $delivery_province = $city_row ? (int)$city_row['province_id'] : 0;

    if ($city_id === $ugat_city_id) {
        $zone_id = 1; // same municipality
    } elseif ($delivery_province === $ugat_province_id) {
        $zone_id = 2; // same province
    }
}

// ── Lookup shipping rate ──────────────────────────────────────
$stmt = $conn->prepare("
    SELECT rate, free_threshold FROM shipping_rates
    WHERE zone_id = ? AND min_weight_kg <= ? AND max_weight_kg >= ?
    LIMIT 1
");
$stmt->bind_param('idd', $zone_id, $total_weight, $total_weight);
$stmt->execute();
$rate_row = $stmt->get_result()->fetch_assoc();
$stmt->close();

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

$shipping_fee   = $rate_row ? (float)$rate_row['rate']   : 50;
$free_threshold = $rate_row ? $rate_row['free_threshold'] : null;
if ($free_threshold !== null && $subtotal >= (float)$free_threshold) {
    $shipping_fee = 0;
}
$total = $subtotal + $shipping_fee;

// ── Begin transaction ─────────────────────────────────────────
$conn->begin_transaction();
try {
    // Insert order
    $stmt = $conn->prepare("
        INSERT INTO orders
            (user_id, address_id, address_snapshot, payment_method, gcash_ref,
             subtotal, shipping_fee, total, notes, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->bind_param('iisssddds',
        $user_id, $address_id, $addr_snapshot,
        $payment_method, $gcash_ref,
        $subtotal, $shipping_fee, $total, $notes
    );
    $stmt->execute();
    $order_id = (int)$conn->insert_id;
    $stmt->close();

    // Insert order items & deduct stock
    foreach ($order_items as $oi) {
        $stmt = $conn->prepare("
            INSERT INTO order_items (order_id, product_id, quantity, unit_price, weight_kg)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('iiidd',
            $order_id, $oi['product_id'], $oi['quantity'],
            $oi['unit_price'], $oi['weight_kg']
        );
        $stmt->execute();
        $stmt->close();

        // Deduct from inventory
        $stmt = $conn->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?");
        $stmt->bind_param('ii', $oi['quantity'], $oi['product_id']);
        $stmt->execute();
        $stmt->close();
    }

    // Log initial status
    $stmt = $conn->prepare("
        INSERT INTO order_status_logs (order_id, status, changed_by, notes)
        VALUES (?, 'pending', ?, 'Order placed by trainee')
    ");
    $stmt->bind_param('ii', $order_id, $user_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    // Send order placed notification (SMS + email)
    try {
        require_once '../../config/sms_helpers.php';
        require_once '../../config/email_service.php';
        require_once '../../config/email.php';

        $order_no = str_pad($order_id, 6, '0', STR_PAD_LEFT);
        $amount   = '₱' . number_format($total, 2);

        sendSmsForEvent('order_placed', $user_id, [
            'order_id' => $order_no,
            'total'    => $amount,
        ]);

        $uq = $conn->query(
            "SELECT COALESCE(NULLIF(np.email,''), u.email) AS email,
                    CONCAT(COALESCE(tp.first_name,''), ' ', COALESCE(tp.last_name,'')) AS name
             FROM users u
             LEFT JOIN trainee_profiles tp ON tp.user_id = u.id
             LEFT JOIN notification_preferences np ON np.user_id = u.id
             WHERE u.id = $user_id LIMIT 1"
        );
        $uinfo = $uq ? $uq->fetch_assoc() : null;
        if ($uinfo && $uinfo['email']) {
            $name     = trim($uinfo['name']) ?: 'Trainee';
            $template = getEmailTemplate('order_placed', [
                'name'     => $name,
                'order_id' => $order_no,
                'total'    => $amount,
                'link'     => '',
            ]);
            getEmailService($conn)->sendEmail($uinfo['email'], $template['subject'], $template['body']);
        }
    } catch (\Throwable $e) {
        error_log('Order placed notification error: ' . $e->getMessage());
    }

    echo json_encode([
        'success'      => true,
        'order_id'     => $order_id,
        'subtotal'     => $subtotal,
        'shipping_fee' => $shipping_fee,
        'total'        => $total,
        'message'      => 'Order placed successfully.',
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Order failed: ' . $e->getMessage()]);
}
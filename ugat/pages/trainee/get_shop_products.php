<?php
session_name('ugat_trainee');

session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Ensure reviews table exists
$conn->query("
    CREATE TABLE IF NOT EXISTS product_reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL, user_id INT NOT NULL,
        rating TINYINT NOT NULL, comment TEXT, created_at DATETIME DEFAULT NOW(),
        UNIQUE KEY uq_review (product_id, user_id)
    )
");

$r = $conn->query("
    SELECT i.id, i.name, i.sku, i.description, i.image, i.category, i.unit,
           i.unit_price, i.quantity, i.max_stock, i.low_stock_at, i.weight_kg,
           ROUND(AVG(pr.rating),1)                               AS avg_rating,
           COUNT(DISTINCT pr.id)                                 AS review_count,
           COALESCE(SUM(CASE WHEN o.status != 'cancelled' THEN oi.quantity ELSE 0 END), 0) AS total_sold
    FROM inventory i
    LEFT JOIN product_reviews pr ON pr.product_id = i.id
    LEFT JOIN order_items oi     ON oi.product_id = i.id
    LEFT JOIN orders o           ON o.id = oi.order_id
    WHERE i.quantity > 0
    GROUP BY i.id
    ORDER BY i.created_at DESC
");

$products = [];
while ($row = $r->fetch_assoc()) {
    $products[] = [
        'id'           => 'prod-' . $row['id'],
        'raw_id'       => (int)$row['id'],
        'name'         => $row['name'],
        'sku'          => $row['sku'],
        'desc'         => $row['description'] ?? '',
        'image'        => $row['image'] ?? '',
        'cat'          => $row['category'],
        'type'         => $row['category'] === 'kit' ? 'Training Kit' : 'Farm Product',
        'price'        => (float)$row['unit_price'],
        'stock'        => (int)$row['quantity'],
        'maxStock'     => (int)$row['max_stock'],
        'unit'         => $row['unit'],
        'reorderPoint' => (int)$row['low_stock_at'],
        'weight_kg'    => (float)($row['weight_kg'] ?? 0),
        'avg_rating'   => $row['avg_rating'] ? (float)$row['avg_rating'] : 0,
        'review_count' => (int)$row['review_count'],
        'total_sold'   => (int)$row['total_sold'],
    ];
}

// Fetch GCash settings
$gcash_number = '09XX XXX XXXX';
$setting = $conn->query("SELECT value FROM settings WHERE `key` = 'org_phone' LIMIT 1");
if ($setting && $srow = $setting->fetch_assoc()) {
    if (!empty($srow['value'])) $gcash_number = $srow['value'];
}

$gcash_account_name = 'UGAT Integrated Farm';
$setting2 = $conn->query("SELECT value FROM settings WHERE `key` = 'gcash_account_name' LIMIT 1");
if ($setting2 && $srow2 = $setting2->fetch_assoc()) {
    if (!empty($srow2['value'])) $gcash_account_name = $srow2['value'];
}

$gcash_qr_path = '';
$setting3 = $conn->query("SELECT value FROM settings WHERE `key` = 'gcash_qr_path' LIMIT 1");
if ($setting3 && $srow3 = $setting3->fetch_assoc()) {
    $candidate = __DIR__ . '/../../' . $srow3['value'];
    if (!empty($srow3['value']) && file_exists($candidate)) {
        $gcash_qr_path = $srow3['value'];
    }
}

echo json_encode([
    'success'            => true,
    'products'           => $products,
    'gcash_number'       => $gcash_number,
    'gcash_account_name' => $gcash_account_name,
    'gcash_qr_path'      => $gcash_qr_path,
]);
<?php
session_name('ugat_admin');

session_start();
require_once '../../config/db.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$id          = isset($data['id']) ? (int)$data['id'] : 0;
$sku         = trim($data['sku']         ?? '');
$name        = trim($data['name']        ?? '');
$description = trim($data['description'] ?? '');
$image       = trim($data['image']       ?? '');
$category    = trim($data['category']    ?? '');
$unit        = trim($data['unit']        ?? '');
$unit_price  = (float)($data['unit_price']  ?? 0);
$quantity    = (int)($data['quantity']    ?? 0);
$max_stock   = (int)($data['max_stock']   ?? 50);
$low_stock_at = (int)($data['low_stock_at'] ?? 5);
$supplier    = trim($data['supplier']    ?? '');

if (!$name || !$sku) {
    echo json_encode(['success' => false, 'message' => 'Name and SKU are required.']);
    exit;
}

if ($id > 0) {
    // UPDATE
    $stmt = $conn->prepare("
        UPDATE inventory SET
            sku = ?, name = ?, description = ?, image = ?,
            category = ?, unit = ?, unit_price = ?,
            quantity = ?, max_stock = ?, low_stock_at = ?, supplier = ?
        WHERE id = ?
    ");
    $stmt->bind_param('ssssssdiiiis',
        $sku, $name, $description, $image,
        $category, $unit, $unit_price,
        $quantity, $max_stock, $low_stock_at, $supplier, $id
    );
} else {
    // INSERT
    $stmt = $conn->prepare("
        INSERT INTO inventory
            (sku, name, description, image, category, unit, unit_price,
             quantity, max_stock, low_stock_at, supplier)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('ssssssdiii s',
        $sku, $name, $description, $image,
        $category, $unit, $unit_price,
        $quantity, $max_stock, $low_stock_at, $supplier
    );
}

if ($stmt->execute()) {
    $newId = $id > 0 ? $id : $conn->insert_id;
    echo json_encode(['success' => true, 'id' => $newId]);
} else {
    echo json_encode(['success' => false, 'message' => $conn->error]);
}
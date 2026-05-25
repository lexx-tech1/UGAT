<?php

session_name('ugat_admin');
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized.']);
    exit;
}

$action = $_GET['action'] ?? 'items';

switch ($action) {

    // ── All inventory items ───────────────────────────────────
case 'items':
    $r = $conn->query(
        "SELECT
            id,
            sku,
            name,
            description,
            image,
            category,
            unit,
            unit_price,
            quantity          AS stock,
            max_stock,
            low_stock_at      AS reorder_point,
            supplier,
            weight_kg,
            created_at,
            updated_at
         FROM inventory
         ORDER BY id DESC"
    );

        if (!$r) {
            echo json_encode(['success' => false, 'message' => 'Query failed: ' . $conn->error]);
            break;
        }

        $items = [];
        while ($row = $r->fetch_assoc()) $items[] = $row;
        echo json_encode(['success' => true, 'items' => $items]);
        break;

    // ── Transactions ──────────────────────────────────────────
    case 'transactions':
        // NOTE: users table only has: id, email, password_hash, role, is_active,
        //       created_at, updated_at — NO first_name/last_name/name column.
        // We use SUBSTRING_INDEX(u.email, '@', 1) to derive a readable label
        // e.g. "admin@ugat.com" → "admin". Falls back to 'Admin' if no user matched.
        $r = $conn->query(
            "SELECT
                t.id,
                t.item_id,
                t.type,
                t.qty,
                t.before_qty,
                t.after_qty,
                t.unit_price,
                t.note,
                t.reference,
                t.supplier,
                t.recorded_by,
                t.created_at,
                i.name AS item_name,
                i.unit,
                COALESCE(
                    SUBSTRING_INDEX(u.email, '@', 1),
                    'Admin'
                ) AS recorded_by_name
             FROM inventory_transactions t
             JOIN  inventory i ON i.id = t.item_id
             LEFT JOIN users u ON u.id = t.recorded_by
             ORDER BY t.created_at DESC
             LIMIT 200"
        );

        if (!$r) {
            echo json_encode(['success' => false, 'message' => 'Query failed: ' . $conn->error]);
            break;
        }

        $transactions = [];
        while ($row = $r->fetch_assoc()) $transactions[] = $row;
        echo json_encode(['success' => true, 'transactions' => $transactions]);
        break;

    // ── Purchase Orders ───────────────────────────────────────
    case 'purchase_orders':
        // FIX: COALESCE qty_received so JS never gets null (avoids NaN in arithmetic)
        $r = $conn->query(
            "SELECT
                po.id,
                po.po_number,
                po.item_id,
                po.supplier,
                po.qty_ordered,
                COALESCE(po.qty_received, 0) AS qty_received,
                po.unit_price,
                po.expected_date,
                po.priority,
                po.note,
                po.status,
                po.raised_by,
                po.created_at,
                i.name AS item_name,
                i.unit
             FROM purchase_orders po
             JOIN inventory i ON i.id = po.item_id
             ORDER BY po.created_at DESC"
        );

        if (!$r) {
            echo json_encode(['success' => false, 'message' => 'Query failed: ' . $conn->error]);
            break;
        }

        $orders = [];
        while ($row = $r->fetch_assoc()) $orders[] = $row;
        echo json_encode(['success' => true, 'orders' => $orders]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action: ' . htmlspecialchars($action)]);
}

$conn->close();
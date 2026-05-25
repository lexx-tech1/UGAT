<?php
// ============================================================
//  admin/save_inventory.php
//  Handles: add_item, edit_item, delete_item,
//           stock_in, stock_out, submit_po, receive_po, cancel_po
//
//  FIXES:
//    - Bug 3: submit_po bind_param had a space in type string
//             'siididss i' → 'sisidsssi'  (PHP threw silent error,
//             no POs were ever saved → Purchase Orders tab empty)
//    - Bug 4: stock_in log bind_param had wrong type for unit_price
//             'iiiiidssi' → 'iiiidsssi'  (float was bound as int)
// ============================================================
session_name('ugat_admin');

session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input   = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action  = $input['action'] ?? '';
$user_id = (int)$_SESSION['user_id'];

switch ($action) {

    // ── Add Item ──────────────────────────────────────────────
   case 'add_item':
    $name      = trim($input['name']          ?? '');
    $desc      = trim($input['description']   ?? '');
    $image     = trim($input['image']         ?? '');
    $cat       = $input['category']           ?? 'farm';
    $unit      = $input['unit']               ?? 'pcs';
    $price     = (float)($input['unit_price']    ?? 0);
    $stock     = (int)($input['opening_stock']   ?? 0);
    $max_stock = (int)($input['max_stock']       ?? 50);
    $reorder   = (int)($input['reorder_point']   ?? 10);
    $supplier  = trim($input['supplier']         ?? '');
$weight_kg = (float)($input['weight_kg'] ?? 0);  

    if (!$name) {
        echo json_encode(['success' => false, 'message' => 'Item name is required.']);
        break;
    }

    // Auto-generate SKU based on category
    $prefix   = $cat === 'kit' ? 'KIT' : 'FARM';
    $cat_safe = $conn->real_escape_string($cat);
    $r_sku    = $conn->query("SELECT COUNT(*)+1 AS next FROM inventory WHERE category = '$cat_safe'");
    $next_n   = (int)$r_sku->fetch_assoc()['next'];
    $sku      = $prefix . '-' . str_pad($next_n, 3, '0', STR_PAD_LEFT);

    // Ensure SKU is unique
    $check = $conn->prepare('SELECT id FROM inventory WHERE sku = ? LIMIT 1');
    $check->bind_param('s', $sku);
    $check->execute();
    $check->store_result();
    while ($check->num_rows > 0) {
        $check->close();
        $next_n++;
        $sku   = $prefix . '-' . str_pad($next_n, 3, '0', STR_PAD_LEFT);
        $check = $conn->prepare('SELECT id FROM inventory WHERE sku = ? LIMIT 1');
        $check->bind_param('s', $sku);
        $check->execute();
        $check->store_result();
    }
    $check->close();

    $stmt = $conn->prepare(
        'INSERT INTO inventory
         (sku, name, description, image, category, unit, unit_price,
          quantity, max_stock, low_stock_at, supplier, weight_kg)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
$stmt->bind_param('ssssssdiisdd',
    $sku, $name, $desc, $image, $cat, $unit,
    $price, $stock, $max_stock, $reorder, $supplier, $weight_kg
);

    if ($stmt->execute()) {
        $item_id = $conn->insert_id;

        // Log opening stock as a transaction
        if ($stock > 0) {
            $logStmt = $conn->prepare(
                'INSERT INTO inventory_transactions
                    (item_id, type, qty, before_qty, after_qty, unit_price, note, recorded_by)
                 VALUES (?, "in", ?, 0, ?, ?, "Opening stock", ?)'
            );
            $logStmt->bind_param('iiddi', $item_id, $stock, $stock, $price, $user_id);
            $logStmt->execute();
            $logStmt->close();
        }

        echo json_encode([
            'success'  => true,
            'message'  => "\"$name\" added!",
            'item_id'  => $item_id,
            'sku'      => $sku,
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add item: ' . $conn->error]);
    }
    $stmt->close();
    break;

    // ── Edit Item ─────────────────────────────────────────────
case 'edit_item':
    $id        = (int)($input['id']             ?? 0);
    $name      = trim($input['name']            ?? '');
    $sku       = trim($input['sku']             ?? '');
    $desc      = trim($input['description']     ?? '');
    $image     = trim($input['image']           ?? '');
    $cat       = $input['category']             ?? 'farm';
    $unit      = $input['unit']                 ?? 'pcs';
    $price     = (float)($input['unit_price']   ?? 0);
    $max_stock = (int)($input['max_stock']      ?? 50);
    $reorder   = (int)($input['reorder_point']  ?? 10);
    $supplier  = trim($input['supplier']        ?? '');
$weight_kg = (float)($input['weight_kg'] ?? 0); 

$stmt = $conn->prepare(
    'UPDATE inventory
     SET name=?, sku=?, description=?, image=?, category=?, unit=?,
         unit_price=?, max_stock=?, low_stock_at=?, supplier=?, weight_kg=?
     WHERE id=?'
);
$stmt->bind_param('ssssssdiisdi',
    $name, $sku, $desc, $image, $cat, $unit,
    $price, $max_stock, $reorder, $supplier, $weight_kg, $id
);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => "\"$name\" updated!"]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update failed: ' . $conn->error]);
    }
    $stmt->close();
    break;

    // ── Delete Item ───────────────────────────────────────────
    case 'delete_item':
        $id   = (int)($input['id'] ?? 0);
        $stmt = $conn->prepare('DELETE FROM inventory WHERE id = ?');
        $stmt->bind_param('i', $id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Item deleted.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $conn->error]);
        }
        $stmt->close();
        break;

    // ── Stock In ──────────────────────────────────────────────
    case 'stock_in':
        $item_id  = (int)($input['item_id']      ?? 0);
        $qty      = (int)($input['qty']          ?? 0);
        $price    = (float)($input['unit_price'] ?? 0);
        $supplier = trim($input['supplier']      ?? '');
        $ref      = trim($input['reference']     ?? '');
        $note     = trim($input['note']          ?? 'Stock In');

        if (!$item_id || $qty < 1) {
            echo json_encode(['success' => false, 'message' => 'Invalid item or quantity.']);
            break;
        }

        $cur = $conn->prepare('SELECT quantity, unit_price FROM inventory WHERE id = ? LIMIT 1');
        $cur->bind_param('i', $item_id);
        $cur->execute();
        $cur_row = $cur->get_result()->fetch_assoc();
        $cur->close();

        if (!$cur_row) {
            echo json_encode(['success' => false, 'message' => 'Item not found.']);
            break;
        }

        $before    = (int)$cur_row['quantity'];
        $after     = $before + $qty;
        $use_price = $price ?: (float)$cur_row['unit_price'];

        $upd = $conn->prepare('UPDATE inventory SET quantity = ? WHERE id = ?');
        $upd->bind_param('ii', $after, $item_id);
        $upd->execute();
        $upd->close();

        // FIX Bug 4: was 'iiiiidssi' — unit_price ($use_price) is a double, must be 'd'
        // Correct type string: i i i i d s s s i
        $log = $conn->prepare(
            'INSERT INTO inventory_transactions
                (item_id, type, qty, before_qty, after_qty, unit_price, note, reference, supplier, recorded_by)
             VALUES (?, "in", ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $log->bind_param('iiiidsssi', $item_id, $qty, $before, $after, $use_price, $note, $ref, $supplier, $user_id);
        $log->execute();
        $log->close();

        echo json_encode([
            'success'   => true,
            'message'   => "+$qty added. New stock: $after",
            'new_stock' => $after,
        ]);
        break;

    // ── Stock Out ─────────────────────────────────────────────
    case 'stock_out':
        $item_id = (int)($input['item_id']   ?? 0);
        $qty     = (int)($input['qty']       ?? 0);
        $reason  = trim($input['reason']     ?? 'other');
        $ref     = trim($input['reference']  ?? '');
        $note    = trim($input['note']       ?? '');

        if (!$item_id || $qty < 1) {
            echo json_encode(['success' => false, 'message' => 'Invalid item or quantity.']);
            break;
        }

        $cur = $conn->prepare('SELECT quantity, unit_price, name FROM inventory WHERE id = ? LIMIT 1');
        $cur->bind_param('i', $item_id);
        $cur->execute();
        $cur_row = $cur->get_result()->fetch_assoc();
        $cur->close();

        if (!$cur_row) {
            echo json_encode(['success' => false, 'message' => 'Item not found.']);
            break;
        }

        $before = (int)$cur_row['quantity'];
        if ($qty > $before) {
            echo json_encode(['success' => false, 'message' => "Only $before in stock."]);
            break;
        }

        $after = $before - $qty;
        $price = (float)$cur_row['unit_price'];
        $reason_labels = [
            'workshop'   => 'Used in workshop',
            'sold'       => 'Sold — Farm Shop',
            'damaged'    => 'Damaged/expired',
            'adjustment' => 'Inventory adjustment',
            'other'      => 'Other',
        ];
        $full_note = ($reason_labels[$reason] ?? $reason) . ($note ? ": $note" : '');

        $upd = $conn->prepare('UPDATE inventory SET quantity = ? WHERE id = ?');
        $upd->bind_param('ii', $after, $item_id);
        $upd->execute();
        $upd->close();

        $log = $conn->prepare(
            'INSERT INTO inventory_transactions
                (item_id, type, qty, before_qty, after_qty, unit_price, note, reference, recorded_by)
             VALUES (?, "out", ?, ?, ?, ?, ?, ?, ?)'
        );
        $log->bind_param('iiiidssi', $item_id, $qty, $before, $after, $price, $full_note, $ref, $user_id);
        $log->execute();
        $log->close();

        echo json_encode([
            'success'   => true,
            'message'   => "-$qty removed. New stock: $after",
            'new_stock' => $after,
        ]);
        break;

    // ── Submit Purchase Order ─────────────────────────────────
    case 'submit_po':
        $item_id  = (int)($input['item_id']      ?? 0);
        $supplier = trim($input['supplier']      ?? '');   // optional
        $qty      = (int)($input['qty']          ?? 0);
        $price    = (float)($input['unit_price'] ?? 0);
        $date     = $input['expected_date']      ?? null;
        $priority = $input['priority']           ?? 'normal';
        $note     = trim($input['note']          ?? '');

        // supplier is optional — removed from required check
        if (!$item_id || !$qty || !$price || !$date) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
            break;
        }

        // Generate unique PO number
        $r      = $conn->query("SELECT COUNT(*)+1 AS next FROM purchase_orders");
        $next   = (int)$r->fetch_assoc()['next'];
        $po_no  = 'PO-' . date('Y') . '-' . str_pad($next, 3, '0', STR_PAD_LEFT);

        // FIX Bug 3: original had 'siididss i' — space in type string caused
        // bind_param to fail silently → no PO ever inserted → empty tab.
        // Correct types: s=po_no, i=item_id, s=supplier, i=qty, d=price,
        //                s=date, s=priority, s=note, i=user_id  → 'sisidsssi'
        $stmt = $conn->prepare(
            'INSERT INTO purchase_orders
                (po_number, item_id, supplier, qty_ordered, unit_price, expected_date, priority, note, raised_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('sisidsssi', $po_no, $item_id, $supplier, $qty, $price, $date, $priority, $note, $user_id);

        if ($stmt->execute()) {
            echo json_encode([
                'success'   => true,
                'message'   => "Purchase Order $po_no raised!",
                'po_number' => $po_no,
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create PO: ' . $conn->error]);
        }
        $stmt->close();
        break;

    // ── Receive PO ────────────────────────────────────────────
    case 'receive_po':
        $po_id = (int)($input['po_id'] ?? 0);
        $qty   = (int)($input['qty']   ?? 0);
        $note  = trim($input['note']  ?? 'PO receipt');

        $po_stmt = $conn->prepare('SELECT * FROM purchase_orders WHERE id = ? LIMIT 1');
        $po_stmt->bind_param('i', $po_id);
        $po_stmt->execute();
        $po = $po_stmt->get_result()->fetch_assoc();
        $po_stmt->close();

        if (!$po || $qty < 1) {
            echo json_encode(['success' => false, 'message' => 'Invalid PO or quantity.']);
            break;
        }

        $already_received = (int)($po['qty_received'] ?? 0);
        $remaining        = (int)$po['qty_ordered'] - $already_received;

        if ($qty > $remaining) {
            echo json_encode(['success' => false, 'message' => "Max receivable: $remaining"]);
            break;
        }

        // Get current stock
        $cur = $conn->prepare('SELECT quantity FROM inventory WHERE id = ? LIMIT 1');
        $cur->bind_param('i', $po['item_id']);
        $cur->execute();
        $before = (int)$cur->get_result()->fetch_assoc()['quantity'];
        $cur->close();
        $after  = $before + $qty;

        // Update inventory stock
        $upd = $conn->prepare('UPDATE inventory SET quantity = ? WHERE id = ?');
        $upd->bind_param('ii', $after, $po['item_id']);
        $upd->execute();
        $upd->close();

        // Update PO status
        $new_received = $already_received + $qty;
        $new_status   = $new_received >= (int)$po['qty_ordered'] ? 'received' : 'partial';
        $upd2 = $conn->prepare('UPDATE purchase_orders SET qty_received=?, status=? WHERE id=?');
        $upd2->bind_param('isi', $new_received, $new_status, $po_id);
        $upd2->execute();
        $upd2->close();

        // Log as a 'po' type transaction
        $log = $conn->prepare(
            'INSERT INTO inventory_transactions
                (item_id, type, qty, before_qty, after_qty, unit_price, note, reference, supplier, recorded_by)
             VALUES (?, "po", ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $log->bind_param(
            'iiiidsssi',
            $po['item_id'], $qty, $before, $after,
            $po['unit_price'], $note, $po['po_number'], $po['supplier'], $user_id
        );
        $log->execute();
        $log->close();

        echo json_encode([
            'success'   => true,
            'message'   => "Received $qty. PO is now " . ucfirst($new_status) . ".",
            'new_stock' => $after,
            'po_status' => $new_status,
        ]);
        break;

    // ── Cancel PO ─────────────────────────────────────────────
    case 'cancel_po':
        $po_id = (int)($input['po_id'] ?? 0);
        $stmt  = $conn->prepare("UPDATE purchase_orders SET status='cancelled' WHERE id=? AND status='pending'");
        $stmt->bind_param('i', $po_id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Purchase order cancelled.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Could not cancel PO (already received or not found).']);
        }
        $stmt->close();
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action: ' . htmlspecialchars($action)]);
}

$conn->close();
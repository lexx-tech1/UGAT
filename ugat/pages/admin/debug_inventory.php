<?php
// ============================================================
//  debug_inventory.php  — TEMPORARY DIAGNOSTIC TOOL
//  Place in: C:\xampp\htdocs\UGAT\pages\admin\
//  Open at:  http://localhost/UGAT/pages/admin/debug_inventory.php
//  DELETE THIS FILE after you finish debugging!
// ============================================================
session_name('ugat_admin');
session_start();
require_once '../../config/db.php';

// Force-set session for testing (bypass login check)
// Comment this out if you are already logged in
$_SESSION['user_id'] = 1;
$_SESSION['role']    = 'admin';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Inventory Diagnostic</title>
<style>
  body { font-family: monospace; padding: 2rem; background: #f5f5f5; }
  h2   { color: #333; border-bottom: 2px solid #4B8423; padding-bottom: 6px; }
  h3   { color: #4B8423; margin-top: 1.5rem; }
  .ok  { background: #e8f5e9; border-left: 4px solid #4caf50; padding: 10px 14px; margin: 8px 0; border-radius: 4px; }
  .err { background: #ffebee; border-left: 4px solid #f44336; padding: 10px 14px; margin: 8px 0; border-radius: 4px; }
  .warn{ background: #fff8e1; border-left: 4px solid #ff9800; padding: 10px 14px; margin: 8px 0; border-radius: 4px; }
  pre  { background: #fff; border: 1px solid #ddd; padding: 12px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
  table{ border-collapse: collapse; width: 100%; background: #fff; margin: 8px 0; }
  th   { background: #4B8423; color: #fff; padding: 8px 10px; text-align: left; font-size: 12px; }
  td   { padding: 6px 10px; border-bottom: 1px solid #eee; font-size: 12px; }
  tr:nth-child(even) td { background: #f9f9f9; }
  .badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:bold; }
  .badge-ok  { background:#c8e6c9; color:#1b5e20; }
  .badge-err { background:#ffcdd2; color:#b71c1c; }
</style>
</head>
<body>
<h2>🔍 UGAT Inventory — Diagnostic Report</h2>
<p style="color:#888;font-size:12px">Generated: <?= date('Y-m-d H:i:s') ?> &nbsp;|&nbsp; DELETE THIS FILE AFTER USE</p>

<?php

// ── 1. DB connection ─────────────────────────────────────────
echo '<h3>1. Database Connection</h3>';
if ($conn && !$conn->connect_error) {
    echo '<div class="ok">✅ Connected to MySQL — database: <strong>' . $conn->query("SELECT DATABASE()")->fetch_row()[0] . '</strong></div>';
} else {
    echo '<div class="err">❌ Connection failed: ' . $conn->connect_error . '</div>';
    exit;
}

// ── 2. Table existence ────────────────────────────────────────
echo '<h3>2. Required Tables</h3>';
$required = ['inventory', 'inventory_transactions', 'purchase_orders', 'users'];
foreach ($required as $tbl) {
    $r = $conn->query("SHOW TABLES LIKE '$tbl'");
    if ($r->num_rows > 0) {
        $count = $conn->query("SELECT COUNT(*) AS n FROM `$tbl`")->fetch_assoc()['n'];
        echo "<div class='ok'>✅ <strong>$tbl</strong> — $count rows</div>";
    } else {
        echo "<div class='err'>❌ <strong>$tbl</strong> — TABLE DOES NOT EXIST</div>";
    }
}

// ── 3. users table columns ────────────────────────────────────
echo '<h3>3. users Table — Column Names</h3>';
$cols = $conn->query("SHOW COLUMNS FROM users");
if ($cols) {
    echo '<table><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>';
    $user_cols = [];
    while ($c = $cols->fetch_assoc()) {
        $user_cols[] = $c['Field'];
        echo "<tr><td>{$c['Field']}</td><td>{$c['Type']}</td><td>{$c['Null']}</td><td>{$c['Key']}</td><td>{$c['Default']}</td></tr>";
    }
    echo '</table>';

    // Check for name columns
    $has_first = in_array('first_name', $user_cols);
    $has_last  = in_array('last_name',  $user_cols);
    $has_name  = in_array('name',       $user_cols);
    $has_uname = in_array('username',   $user_cols);

    if ($has_first && $has_last) {
        echo '<div class="ok">✅ users has first_name + last_name — JOIN will work correctly</div>';
        $name_expr = "CONCAT(u.first_name, ' ', u.last_name)";
    } elseif ($has_name) {
        echo '<div class="warn">⚠️ users has <strong>name</strong> (not first_name/last_name) — need to update get_inventory.php JOIN</div>';
        $name_expr = "u.name";
    } elseif ($has_uname) {
        echo '<div class="warn">⚠️ users has <strong>username</strong> (not first_name/last_name) — need to update get_inventory.php JOIN</div>';
        $name_expr = "u.username";
    } else {
        echo '<div class="err">❌ users table has no recognisable name column — check column list above</div>';
        $name_expr = "NULL";
    }
} else {
    echo '<div class="err">❌ Could not read users table columns: ' . $conn->error . '</div>';
    $name_expr = "NULL";
}

// ── 4. Test the transactions query ───────────────────────────
echo '<h3>4. Transactions Query Test</h3>';
$tx_sql = "
    SELECT
        t.id, t.item_id, t.type, t.qty, t.before_qty, t.after_qty,
        t.unit_price, t.note, t.reference, t.supplier, t.recorded_by, t.created_at,
        i.name AS item_name, i.unit,
        COALESCE($name_expr, 'Admin') AS recorded_by_name
    FROM inventory_transactions t
    JOIN  inventory i ON i.id = t.item_id
    LEFT JOIN users u ON u.id = t.recorded_by
    ORDER BY t.created_at DESC
    LIMIT 10";

echo '<pre style="font-size:11px">' . htmlspecialchars($tx_sql) . '</pre>';
$r = $conn->query($tx_sql);
if (!$r) {
    echo '<div class="err">❌ Query FAILED: <strong>' . $conn->error . '</strong></div>';
} else {
    $rows = [];
    while ($row = $r->fetch_assoc()) $rows[] = $row;
    if (count($rows) === 0) {
        echo '<div class="warn">⚠️ Query OK but returned 0 rows — inventory_transactions table is empty.<br>
        This is expected if you haven\'t done a Stock In or Stock Out yet.<br>
        <strong>Fix:</strong> Go to Products tab → click ↑ In on any item → that will create the first transaction.</div>';
    } else {
        echo '<div class="ok">✅ Query returned ' . count($rows) . ' row(s)</div>';
        echo '<table><tr>';
        foreach (array_keys($rows[0]) as $col) echo "<th>$col</th>";
        echo '</tr>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $v) echo '<td>' . htmlspecialchars((string)$v) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
}

// ── 5. Test purchase orders query ────────────────────────────
echo '<h3>5. Purchase Orders Query Test</h3>';
$po_sql = "
    SELECT po.id, po.po_number, po.item_id, po.supplier, po.qty_ordered,
           COALESCE(po.qty_received, 0) AS qty_received,
           po.unit_price, po.expected_date, po.priority, po.status, po.created_at,
           i.name AS item_name, i.unit
    FROM purchase_orders po
    JOIN inventory i ON i.id = po.item_id
    ORDER BY po.created_at DESC
    LIMIT 10";

$r = $conn->query($po_sql);
if (!$r) {
    echo '<div class="err">❌ PO Query FAILED: <strong>' . $conn->error . '</strong></div>';
} else {
    $rows = [];
    while ($row = $r->fetch_assoc()) $rows[] = $row;
    if (count($rows) === 0) {
        echo '<div class="warn">⚠️ Query OK but 0 purchase orders in DB yet.<br>
        <strong>Fix:</strong> Go to Purchase Orders tab → click "+ New Purchase Order" → fill the form → Submit.</div>';
    } else {
        echo '<div class="ok">✅ PO Query returned ' . count($rows) . ' row(s)</div>';
        echo '<table><tr>';
        foreach (array_keys($rows[0]) as $col) echo "<th>$col</th>";
        echo '</tr>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $v) echo '<td>' . htmlspecialchars((string)$v) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
}

// ── 6. Test save: dummy stock_in ─────────────────────────────
echo '<h3>6. save_inventory.php — bind_param Test (Stock In)</h3>';

// Find first inventory item
$first_item = $conn->query("SELECT id, quantity, unit_price FROM inventory LIMIT 1")->fetch_assoc();
if (!$first_item) {
    echo '<div class="err">❌ No items in inventory table to test with.</div>';
} else {
    $item_id   = (int)$first_item['id'];
    $qty       = 1;
    $before    = (int)$first_item['quantity'];
    $after     = $before + $qty;
    $use_price = (float)$first_item['unit_price'];
    $note      = 'DIAGNOSTIC TEST — safe to delete';
    $ref       = 'debug_inventory.php';
    $supplier  = '';
    $user_id   = (int)($_SESSION['user_id'] ?? 1);

    // Test the fixed bind_param (iiiidsssi)
    $log = $conn->prepare(
        'INSERT INTO inventory_transactions
            (item_id, type, qty, before_qty, after_qty, unit_price, note, reference, supplier, recorded_by)
         VALUES (?, "in", ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    if (!$log) {
        echo '<div class="err">❌ prepare() failed: ' . $conn->error . '</div>';
    } else {
        $log->bind_param('iiiidsssi', $item_id, $qty, $before, $after, $use_price, $note, $ref, $supplier, $user_id);
        if ($log->execute()) {
            $new_id = $conn->insert_id;
            echo '<div class="ok">✅ bind_param is correct — test transaction inserted! (id=' . $new_id . ')<br>
            Stock In bind_param string <strong>\'iiiidsssi\'</strong> works.<br>
            The diagnostic row is in your DB — you can delete it from phpMyAdmin if you like.</div>';

            // Also update inventory quantity to match
            $upd = $conn->prepare('UPDATE inventory SET quantity = ? WHERE id = ?');
            $upd->bind_param('ii', $after, $item_id);
            $upd->execute();
            $upd->close();
            echo '<div class="ok">✅ Inventory quantity updated from ' . $before . ' → ' . $after . '</div>';
        } else {
            echo '<div class="err">❌ execute() failed: ' . $log->error . '</div>';
        }
        $log->close();
    }
}

// ── 7. Test save: dummy submit_po ────────────────────────────
echo '<h3>7. save_inventory.php — bind_param Test (Submit PO)</h3>';
$first_item2 = $conn->query("SELECT id FROM inventory LIMIT 1")->fetch_assoc();
if ($first_item2) {
    $po_no    = 'PO-TEST-' . date('His');
    $item_id  = (int)$first_item2['id'];
    $supplier = 'DEBUG Supplier';
    $qty      = 5;
    $price    = 99.99;
    $date     = date('Y-m-d', strtotime('+7 days'));
    $priority = 'normal';
    $note     = 'DIAGNOSTIC TEST — safe to delete';
    $user_id  = (int)($_SESSION['user_id'] ?? 1);

    $stmt = $conn->prepare(
        'INSERT INTO purchase_orders
            (po_number, item_id, supplier, qty_ordered, unit_price, expected_date, priority, note, raised_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    if (!$stmt) {
        echo '<div class="err">❌ prepare() failed: ' . $conn->error . '</div>';
    } else {
        $stmt->bind_param('sisidsssi', $po_no, $item_id, $supplier, $qty, $price, $date, $priority, $note, $user_id);
        if ($stmt->execute()) {
            echo '<div class="ok">✅ bind_param is correct — test PO inserted! (po_number=' . $po_no . ')<br>
            submit_po bind_param string <strong>\'sisidsssi\'</strong> works.</div>';
        } else {
            echo '<div class="err">❌ execute() failed: ' . $stmt->error . '</div>';
        }
        $stmt->close();
    }
}

// ── 8. inventory low_stock_at check ──────────────────────────
echo '<h3>8. inventory — low_stock_at (reorder_point) Values</h3>';
$r = $conn->query("SELECT id, name, quantity, low_stock_at FROM inventory ORDER BY name");
echo '<table><tr><th>ID</th><th>Name</th><th>Quantity</th><th>low_stock_at</th><th>Status</th></tr>';
while ($row = $r->fetch_assoc()) {
    $rp  = $row['low_stock_at'];
    $qty = $row['quantity'];
    if ($rp === null || $rp === '') {
        $status = '<span class="badge badge-err">NULL — set a value!</span>';
    } elseif ($qty == 0) {
        $status = '<span class="badge badge-err">Out of stock</span>';
    } elseif ($qty <= $rp) {
        $status = '<span class="badge" style="background:#fff3cd;color:#856404">Low stock</span>';
    } else {
        $status = '<span class="badge badge-ok">OK</span>';
    }
    echo "<tr><td>{$row['id']}</td><td>{$row['name']}</td><td>{$qty}</td><td>" . ($rp ?? '<em>NULL</em>') . "</td><td>$status</td></tr>";
}
echo '</table>';

// Fix suggestion
$null_rp = $conn->query("SELECT COUNT(*) AS n FROM inventory WHERE low_stock_at IS NULL OR low_stock_at = 0")->fetch_assoc()['n'];
if ($null_rp > 0) {
    echo '<div class="warn">⚠️ ' . $null_rp . ' items have no reorder point set. Run this in phpMyAdmin to fix:<br>
    <code>UPDATE inventory SET low_stock_at = 10 WHERE low_stock_at IS NULL OR low_stock_at = 0;</code></div>';
}

// ── 9. SKU duplicates ────────────────────────────────────────
echo '<h3>9. SKU Duplicate Check</h3>';
$r = $conn->query("SELECT sku, COUNT(*) AS cnt, GROUP_CONCAT(name SEPARATOR ' | ') AS items FROM inventory GROUP BY sku HAVING cnt > 1");
if ($r->num_rows === 0) {
    echo '<div class="ok">✅ No duplicate SKUs found.</div>';
} else {
    while ($row = $r->fetch_assoc()) {
        echo '<div class="err">❌ Duplicate SKU <strong>' . $row['sku'] . '</strong> used by: ' . $row['items'] . '</div>';
    }
    echo '<div class="warn">Fix in phpMyAdmin SQL tab — example:<br>
    <code>UPDATE inventory SET sku = \'FARM-005\' WHERE name = \'Vermicast compost (1kg)\';</code></div>';
}

$conn->close();
?>

<hr style="margin:2rem 0;border:1px solid #ddd">
<p style="color:#f44336;font-weight:bold;font-size:13px">⚠️ SECURITY REMINDER: Delete this file from your server when done!<br>
Path: C:\xampp\htdocs\UGAT\pages\admin\debug_inventory.php</p>
</body>
</html>
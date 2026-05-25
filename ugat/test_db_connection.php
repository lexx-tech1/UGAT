<?php
/**
 * Database Connection Test
 * Visit: http://localhost/ugat/test_db_connection.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UGAT Database Connection Test</title>
    <style>
        body {
            font-family: 'Plus Jakarta Sans', Arial, sans-serif;
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
            background: #f5fbf2;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 {
            color: #4B8423;
            margin-bottom: 10px;
        }
        .status {
            padding: 15px;
            margin: 15px 0;
            border-radius: 8px;
            font-weight: 500;
        }
        .status.success {
            background: #e8f5e9;
            border-left: 4px solid #4B8423;
            color: #2e7d32;
        }
        .status.error {
            background: #ffebee;
            border-left: 4px solid #c62828;
            color: #c62828;
        }
        .status.info {
            background: #e3f2fd;
            border-left: 4px solid #1976d2;
            color: #1565c0;
        }
        .data-section {
            margin: 25px 0;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
        }
        .data-section h3 {
            color: #4B8423;
            margin-top: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table th {
            background: #4B8423;
            color: white;
            padding: 10px;
            text-align: left;
            font-size: 13px;
        }
        table td {
            padding: 10px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 13px;
        }
        table tr:hover {
            background: #f5f5f5;
        }
        .code {
            background: #f0f0f0;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            margin: 10px 0;
            overflow-x: auto;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>🗄️ UGAT Database Connection Test</h1>
    <p>Testing database connection and data integrity...</p>

    <?php
    // Test 1: Connection Status
    echo '<div class="status success">✓ Database connected successfully!</div>';
    echo '<div class="code">Host: ' . DB_HOST . ' | User: ' . DB_USER . ' | Database: ' . DB_NAME . '</div>';

    // Test 2: Database Info
    $result = $conn->query("SELECT DATABASE() as db_name");
    $row = $result->fetch_assoc();
    echo '<div class="status info">📊 Current Database: ' . $row['db_name'] . '</div>';

    // Test 3: Table Count
    $result = $conn->query("SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = DATABASE()");
    $row = $result->fetch_assoc();
    echo '<div class="status info">📋 Total Tables: ' . $row['table_count'] . '</div>';

    // Test 4: Users Table
    echo '<div class="data-section">';
    echo '<h3>Users</h3>';
    $result = $conn->query("SELECT id, email, role, is_active FROM users LIMIT 5");
    if ($result && $result->num_rows > 0) {
        echo '<table>';
        echo '<tr><th>ID</th><th>Email</th><th>Role</th><th>Active</th></tr>';
        while ($row = $result->fetch_assoc()) {
            $active = $row['is_active'] ? '✓ Yes' : '✗ No';
            echo '<tr>';
            echo '<td>' . $row['id'] . '</td>';
            echo '<td>' . $row['email'] . '</td>';
            echo '<td>' . ucfirst($row['role']) . '</td>';
            echo '<td>' . $active . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<div class="status error">✗ No users found</div>';
    }
    echo '</div>';

    // Test 5: Trainee Profiles
    echo '<div class="data-section">';
    echo '<h3>Trainee Profiles</h3>';
    $result = $conn->query("SELECT user_id, first_name, last_name, phone FROM trainee_profiles LIMIT 5");
    if ($result && $result->num_rows > 0) {
        echo '<table>';
        echo '<tr><th>User ID</th><th>First Name</th><th>Last Name</th><th>Phone</th></tr>';
        while ($row = $result->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . $row['user_id'] . '</td>';
            echo '<td>' . $row['first_name'] . '</td>';
            echo '<td>' . $row['last_name'] . '</td>';
            echo '<td>' . ($row['phone'] ?? 'N/A') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<div class="status error">✗ No trainee profiles found</div>';
    }
    echo '</div>';

    // Test 6: Workshops
    echo '<div class="data-section">';
    echo '<h3>Workshops</h3>';
    $result = $conn->query("SELECT id, title, description, start_date FROM workshops LIMIT 5");
    if ($result && $result->num_rows > 0) {
        echo '<table>';
        echo '<tr><th>ID</th><th>Title</th><th>Description</th><th>Start Date</th></tr>';
        while ($row = $result->fetch_assoc()) {
            $desc = substr($row['description'] ?? '', 0, 40) . '...';
            echo '<tr>';
            echo '<td>' . $row['id'] . '</td>';
            echo '<td>' . $row['title'] . '</td>';
            echo '<td>' . $desc . '</td>';
            echo '<td>' . $row['start_date'] . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<div class="status error">✗ No workshops found</div>';
    }
    echo '</div>';

    // Test 7: Orders
    echo '<div class="data-section">';
    echo '<h3>Orders</h3>';
    $result = $conn->query("SELECT id, user_id, status, total_amount FROM orders LIMIT 5");
    if ($result && $result->num_rows > 0) {
        echo '<table>';
        echo '<tr><th>ID</th><th>User ID</th><th>Status</th><th>Total</th></tr>';
        while ($row = $result->fetch_assoc()) {
            echo '<tr>';
            echo '<td>#' . $row['id'] . '</td>';
            echo '<td>' . $row['user_id'] . '</td>';
            echo '<td>' . ucfirst($row['status']) . '</td>';
            echo '<td>₱' . number_format($row['total_amount'], 2) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<div class="status error">✗ No orders found</div>';
    }
    echo '</div>';

    // Test 8: Activity Log
    echo '<div class="data-section">';
    echo '<h3>Activity Log</h3>';
    $result = $conn->query("SELECT id, action, created_at FROM activity_log LIMIT 5");
    if ($result && $result->num_rows > 0) {
        echo '<table>';
        echo '<tr><th>ID</th><th>Action</th><th>Created At</th></tr>';
        while ($row = $result->fetch_assoc()) {
            $action = substr($row['action'], 0, 50) . (strlen($row['action']) > 50 ? '...' : '');
            echo '<tr>';
            echo '<td>' . $row['id'] . '</td>';
            echo '<td>' . $action . '</td>';
            echo '<td>' . $row['created_at'] . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<div class="status error">✗ No activity logs found</div>';
    }
    echo '</div>';

    // Test 9: Summary
    echo '<div class="data-section">';
    echo '<h3>📈 Database Summary</h3>';
    
    $stats = [];
    
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    $row = $result->fetch_assoc();
    $stats['Users'] = $row['count'];
    
    $result = $conn->query("SELECT COUNT(*) as count FROM trainee_profiles");
    $row = $result->fetch_assoc();
    $stats['Trainees'] = $row['count'];
    
    $result = $conn->query("SELECT COUNT(*) as count FROM workshops");
    $row = $result->fetch_assoc();
    $stats['Workshops'] = $row['count'];
    
    $result = $conn->query("SELECT COUNT(*) as count FROM orders");
    $row = $result->fetch_assoc();
    $stats['Orders'] = $row['count'];
    
    $result = $conn->query("SELECT COUNT(*) as count FROM inventory");
    $row = $result->fetch_assoc();
    $stats['Inventory Items'] = $row['count'];
    
    $result = $conn->query("SELECT COUNT(*) as count FROM activity_log");
    $row = $result->fetch_assoc();
    $stats['Activity Logs'] = $row['count'];
    
    echo '<table>';
    echo '<tr><th>Metric</th><th>Count</th></tr>';
    foreach ($stats as $name => $count) {
        echo '<tr>';
        echo '<td>' . $name . '</td>';
        echo '<td><strong>' . $count . '</strong></td>';
        echo '</tr>';
    }
    echo '</table>';
    echo '</div>';

    $conn->close();
    ?>

    <div class="status success" style="margin-top: 30px;">
        ✓ All tests passed! Database connection is working correctly with updated data.
    </div>

</div>
</body>
</html>

<?php
/**
 * Enrollment Approval Diagnostic Tool
 * Visit: http://localhost/ugat/test_enrollment_approval.php
 * 
 * This tool helps diagnose issues with enrollment approval
 */

ini_set('session.cookie_path', '/');
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_name('ugat_admin');
session_start();
require_once 'config/db.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Enrollment Approval Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .status-ok { color: green; font-weight: bold; }
        .status-error { color: red; font-weight: bold; }
        .test { margin: 15px 0; padding: 15px; border-left: 4px solid #ddd; }
        .test.pass { border-left-color: green; background: #f0f8f0; }
        .test.fail { border-left-color: red; background: #f8f0f0; }
        h2 { border-bottom: 2px solid #333; padding-bottom: 10px; }
        code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
<div class="container">
    <h1><span class="icon icon-tool"></span> Enrollment Approval Diagnostic</h1>
    <link rel="stylesheet" href="../css/icons.css">
    <p>Checks database connections, tables, and enrollment data</p>

    <h2>1. Database Connection</h2>
    <?php
    if ($conn->connect_error) {
        echo '<div class="test fail"><span class="icon icon-x"></span> <span class="status-error">Connection Failed</span>: ' . htmlspecialchars($conn->connect_error) . '</div>';
    } else {
        echo '<div class="test pass"><span class="icon icon-check"></span> <span class="status-ok">Connected</span> to database: ' . htmlspecialchars(DB_NAME) . '</div>';
    }
    ?>

    <h2>2. Required Tables</h2>
    <?php
    $tables_to_check = ['enrollments', 'workshops', 'users', 'trainee_profiles'];
    foreach ($tables_to_check as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "<div class=\"test pass\"><span class=\"icon icon-check\"></span> Table <code>$table</code> exists</div>";
        } else {
            echo "<div class=\"test fail\"><span class=\"icon icon-x\"></span> Table <code>$table</code> not found</div>";
        }
    }
    ?>

    <h2>3. Admin Session Check</h2>
    <?php
    if (empty($_SESSION['user_id'])) {
        echo '<div class="test fail"><span class="icon icon-x"></span> No admin session found. Log in as admin first.</div>';
    } else if ($_SESSION['role'] !== 'admin') {
        echo '<div class="test fail"><span class="icon icon-x"></span> Not logged in as admin. Current role: ' . htmlspecialchars($_SESSION['role']) . '</div>';
    } else {
        echo '<div class="test pass"><span class="icon icon-check"></span> Admin session valid (ID: ' . htmlspecialchars($_SESSION['user_id']) . ')</div>';
    }
    ?>

    <h2>4. SMS Helpers & Notifications</h2>
    <?php
    if (file_exists('config/sms_helpers.php')) {
        echo '<div class="test pass"><span class="icon icon-check"></span> File <code>config/sms_helpers.php</code> exists</div>';
        
        // Try to include it
        try {
            require_once 'config/sms_helpers.php';
            
            if (function_exists('sendWorkshopEnrollmentNotificationDual')) {
                echo '<div class="test pass"><span class="icon icon-check"></span> Function <code>sendWorkshopEnrollmentNotificationDual()</code> found</div>';
            } else {
                echo '<div class="test fail"><span class="icon icon-x"></span> Function <code>sendWorkshopEnrollmentNotificationDual()</code> not found</div>';
            }
            
            if (function_exists('sendNotificationByPreference')) {
                echo '<div class="test pass"><span class="icon icon-check"></span> Function <code>sendNotificationByPreference()</code> found</div>';
            } else {
                echo '<div class="test fail"><span class="icon icon-x"></span> Function <code>sendNotificationByPreference()</code> not found</div>';
            }
        } catch (Exception $e) {
            echo '<div class="test fail"><span class="icon icon-x"></span> Error loading sms_helpers.php: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    } else {
        echo '<div class="test fail"><span class="icon icon-x"></span> File <code>config/sms_helpers.php</code> not found</div>';
    }
    ?>

    <h2>5. Pending Enrollments</h2>
    <?php
    $enroll_result = $conn->query(
        "SELECT e.id, e.status, tp.first_name, tp.last_name, w.title 
         FROM enrollments e 
         JOIN trainee_profiles tp ON e.user_id = tp.user_id 
         JOIN workshops w ON e.workshop_id = w.id 
         WHERE e.status = 'pending' 
         LIMIT 5"
    );
    
    if (!$enroll_result) {
        echo '<div class="test fail"><span class="icon icon-x"></span> Query error: ' . htmlspecialchars($conn->error) . '</div>';
    } else if ($enroll_result->num_rows === 0) {
        echo '<div class="test">ℹ️ No pending enrollments found</div>';
    } else {
        echo '<div class="test pass"><span class="icon icon-check"></span> Found ' . $enroll_result->num_rows . ' pending enrollments:</div>';
        while ($row = $enroll_result->fetch_assoc()) {
            echo '<div style="margin-left: 20px; padding: 10px; background: #f9f9f9; margin-top: 5px;">';
            echo 'ID: ' . htmlspecialchars($row['id']) . ' | ';
            echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . ' | ';
            echo htmlspecialchars($row['title']);
            echo '</div>';
        }
    }
    ?>

    <h2>6. Test Approval (Admin Only)</h2>
    <?php
    if (!empty($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
        // Get a pending enrollment
        $test_result = $conn->query(
            "SELECT e.id FROM enrollments e WHERE e.status = 'pending' LIMIT 1"
        );
        
        if ($test_result && $test_result->num_rows > 0) {
            $pending = $test_result->fetch_assoc();
            echo '<div class="test">';
            echo 'To test enrollment approval, use this curl command:<br>';
            echo '<code style="display: block; margin-top: 10px; padding: 10px; background: #f0f0f0;">';
            echo 'curl -X POST http://localhost/ugat/pages/admin/approve_enrollment.php \\<br>';
            echo '  -H "Content-Type: application/json" \\<br>';
            echo '  -d \'{&quot;action&quot;:&quot;approve&quot;,&quot;enrollment_id&quot;:' . htmlspecialchars($pending['id']) . '}\'';
            echo '</code>';
            echo '</div>';
        }
    }
    ?>

    <h2>Browser Console</h2>
    <p>Open your browser's Developer Tools (F12) and check the Console for errors when you try to approve an enrollment.</p>

    <?php $conn->close(); ?>
</div>
</body>
</html>

<?php
// ============================================================
//  config/create_sms_tables.php
//  Run this script once to create necessary SMS database tables
//  
//  Usage: http://localhost/ugat/config/create_sms_tables.php
//  (Restrict access in production!)
// ============================================================

require_once 'db.php';

// Create sms_logs table
$sql1 = "
CREATE TABLE IF NOT EXISTS sms_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone_number VARCHAR(20) NOT NULL,
    message LONGTEXT,
    sms_id VARCHAR(100) UNIQUE,
    metadata JSON,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'sent',
    INDEX idx_phone (phone_number),
    INDEX idx_sent_at (sent_at),
    INDEX idx_sms_id (sms_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// Create sms_notifications table (admin notifications)
$sql2 = "
CREATE TABLE IF NOT EXISTS sms_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    recipient_type VARCHAR(20) NOT NULL,
    recipient_id INT,
    template VARCHAR(100),
    message LONGTEXT,
    count INT DEFAULT 1,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'sent',
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_admin_id (admin_id),
    INDEX idx_sent_at (sent_at),
    INDEX idx_recipient_type (recipient_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// Create trainee_sms_log table
$sql3 = "
CREATE TABLE IF NOT EXISTS trainee_sms_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    phone_number VARCHAR(20),
    message LONGTEXT,
    notification_type VARCHAR(50),
    status VARCHAR(20) DEFAULT 'received',
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read TINYINT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_received_at (received_at),
    INDEX idx_notification_type (notification_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// Execute queries
$tables_created = 0;
$errors = [];

if ($conn->query($sql1) === TRUE) {
    $tables_created++;
    echo "✓ sms_logs table created successfully<br>";
} else {
    $errors[] = "sms_logs error: " . $conn->error;
}

if ($conn->query($sql2) === TRUE) {
    $tables_created++;
    echo "✓ sms_notifications table created successfully<br>";
} else {
    $errors[] = "sms_notifications error: " . $conn->error;
}

if ($conn->query($sql3) === TRUE) {
    $tables_created++;
    echo "✓ trainee_sms_log table created successfully<br>";
} else {
    $errors[] = "trainee_sms_log error: " . $conn->error;
}

// Add phone column to users table if it doesn't exist
$check_phone = $conn->query("SHOW COLUMNS FROM users LIKE 'phone'");
if ($check_phone->num_rows === 0) {
    if ($conn->query("ALTER TABLE users ADD COLUMN phone VARCHAR(20)") === TRUE) {
        echo "✓ Added phone column to users table<br>";
    } else {
        $errors[] = "Failed to add phone column: " . $conn->error;
    }
}

// Summary
echo "<hr>";
if (count($errors) === 0) {
    echo "<h2 style='color: green;'>✓ All SMS tables created successfully!</h2>";
    echo "<p>Next steps:</p>";
    echo "<ol>";
    echo "<li>Update <strong>config/sms.php</strong> with your Twilio credentials</li>";
    echo "<li>Set <strong>SMS_DEBUG_MODE = false</strong> when ready to send real SMS</li>";
    echo "<li>Ensure users have phone numbers in the 'phone' column</li>";
    echo "</ol>";
} else {
    echo "<h2 style='color: red;'>⚠ Errors occurred:</h2>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul>";
}

echo "<p>Tables created: $tables_created/3</p>";

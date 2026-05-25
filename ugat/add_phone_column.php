<?php
require_once 'config/db.php';

$sql = 'ALTER TABLE users ADD COLUMN phone VARCHAR(20) AFTER email';

if ($conn->query($sql)) {
    echo '<span class="icon icon-check"></span> Phone column added to users table successfully!';
} else {
    if (strpos($conn->error, 'Duplicate column') !== false) {
        echo '<span class="icon icon-warning"></span> Phone column already exists';
    } else {
        echo '<span class="icon icon-x"></span> Error: ' . htmlspecialchars($conn->error);
    }
}
?>

<?php
ini_set('session.cookie_path', '/');
session_start();
header('Content-Type: application/json');
echo json_encode([
    'user_id' => $_SESSION['user_id'] ?? null,
    'email'   => $_SESSION['email']   ?? null,
    'role'    => $_SESSION['role']    ?? null,
]);
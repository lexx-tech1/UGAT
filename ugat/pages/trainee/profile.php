<?php
ini_set('session.cookie_path', '/');
session_name('ugat_trainee');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['user_id'])) {
    header('Location: ../auth/Login.html');
    exit;
}
include __DIR__ . '/TraineeProfile.html';

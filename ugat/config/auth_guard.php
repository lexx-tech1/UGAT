<?php
// ============================================================
//  config/auth_guard.php
//  Include at the TOP of every protected HTML/PHP page.
//
//  Usage:
//    require_once '../../config/auth_guard.php';
//    requireRole('admin');   // or requireRole('trainee')
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_path', '/');
    
    // Try to resume existing session with correct session name
    // First, check for admin session cookie
    if (isset($_COOKIE['ugat_admin'])) {
        session_name('ugat_admin');
    }
    // Then check for trainee session cookie
    elseif (isset($_COOKIE['ugat_trainee'])) {
        session_name('ugat_trainee');
    }
    // Default to trainee if no cookie found (login page will set correct one)
    else {
        session_name('ugat_trainee');
    }
    
    session_start();
}

/**
 * Redirect to login if not authenticated.
 * Optionally restrict to a specific role.
 *
 * @param string|null $role  'admin' | 'trainee' | null (any logged-in user)
 */
function requireRole(?string $role = null): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: /pages/auth/Login.html');
        exit;
    }

    if ($role !== null && ($_SESSION['role'] ?? '') !== $role) {
        // Wrong role — redirect to their own dashboard
        $redirect = $_SESSION['role'] === 'admin'
            ? '/pages/admin/AdminDashboard.html'
            : '/pages/trainee/TraineeDashboard.html';
        header('Location: ' . $redirect);
        exit;
    }
}
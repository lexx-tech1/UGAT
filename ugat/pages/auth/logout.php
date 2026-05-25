<?php
ini_set('session.cookie_path', '/');

// Destroy admin session
session_name('ugat_admin');
session_start();
session_unset();
session_destroy();
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie('ugat_admin', '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

// Destroy trainee session
session_name('ugat_trainee');
session_start();
session_unset();
session_destroy();
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie('ugat_trainee', '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

header('Location: Login.html');
exit;
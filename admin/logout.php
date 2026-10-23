<?php
require_once __DIR__ . '/../db.php';

// Unset admin session variables
unset($_SESSION['admin_id'], $_SESSION['admin_username']);

// Clear session cookie if used
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy the session completely
session_destroy();

header('Location: login.php');
exit;
?>
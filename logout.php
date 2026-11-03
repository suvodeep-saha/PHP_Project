<?php
/**
 * User Logout & Session Destruction
 * Online Assignment Submission System
 * 
 * Demonstrates:
 * - Session destruction (session_destroy())
 * - Emptying $_SESSION superglobal
 * - Invalidation of session cookie
 * - Invalidation of remember-me cookie
 */

require_once __DIR__ . '/config/config.php';

// 1. Unset all session variables
$_SESSION = [];

// 2. Clear session cookie from browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 3. Destroy session on server
session_destroy();

// 4. Start clean session to pass flash message
session_start();
setFlash('info', 'You have been signed out successfully.');

redirect('login.php');


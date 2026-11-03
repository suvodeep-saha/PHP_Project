<?php
/**
 * Role-Based Access Control (RBAC) Guard Middleware
 * Online Assignment Submission System
 * 
 * Demonstrates:
 * - Session verification
 * - Role authorization enforcement
 * - Redirection of unauthorized guests and users
 * - Flash notification dispatch
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Enforces authentication and authorization.
 * 
 * @param array|string $allowedRoles Single role string or array of allowed roles
 */
function requireAuth(array|string $allowedRoles = []): void {
    // 1. Check if user is logged in
    if (!isLoggedIn()) {
        setFlash('warning', 'Please sign in to access this page.');
        // Store requested URI for post-login redirect
        $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect('login.php');
    }

    // 2. If specific roles are required, enforce them
    if (!empty($allowedRoles)) {
        if (is_string($allowedRoles)) {
            $allowedRoles = [$allowedRoles];
        }

        $userRole = strtolower($_SESSION['user_role'] ?? '');
        $allowedNormalized = array_map('strtolower', $allowedRoles);

        if (!in_array($userRole, $allowedNormalized, true)) {
            setFlash('danger', 'Access denied. You do not have permission to access that area.');

            // Redirect to their own dashboard
            $target = match ($userRole) {
                'admin'   => 'admin/dashboard.php',
                'teacher' => 'teacher/dashboard.php',
                'student' => 'student/dashboard.php',
                default   => 'index.php',
            };
            redirect($target);
        }
    }
}

/**
 * Restricts guests only (e.g. login and registration pages).
 * If user is already authenticated, redirect to their role dashboard.
 */
function requireGuest(): void {
    if (isLoggedIn()) {
        $role = strtolower($_SESSION['user_role'] ?? '');
        $target = match ($role) {
            'admin'   => 'admin/dashboard.php',
            'teacher' => 'teacher/dashboard.php',
            'student' => 'student/dashboard.php',
            default   => 'index.php',
        };
        redirect($target);
    }
}


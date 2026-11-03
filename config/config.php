<?php
/**
 * Global Configuration & Helper Utilities
 * Online Assignment Submission System
 * 
 * Demonstrates:
 * - Session Management & Secure Cookie Flags (httponly, samesite)
 * - CSRF (Cross-Site Request Forgery) Protection
 * - XSS Prevention via output escaping
 * - Dynamic URL resolution
 * - Flash notification messaging
 */

// Set local timezone
date_default_timezone_set('Asia/Kolkata');

// 1. Session Configuration & Initialization
if (session_status() === PHP_SESSION_NONE) {
    // Configure secure session cookie settings
    session_set_cookie_params([
        'lifetime' => 0,              // Session cookie expires on browser close
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // True if HTTPS
        'httponly' => true,            // Mitigates XSS cookie theft
        'samesite' => 'Lax'            // Mitigates CSRF
    ]);
    session_start();
}

// 2. Application Constants
define('APP_NAME', 'EduSubmit Portal');
define('APP_TAGLINE', 'Online Assignment Submission & Evaluation System');

// Determine Base URL dynamically
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));

// Normalize root path for subdirectory projects like /suvo/PROJECT
// If inside /student, /teacher, /admin, strip one level back to base
$basePath = preg_replace('#/(student|teacher|admin|includes|config|database)$#', '', $scriptDir);
$basePath = rtrim($basePath, '/');

define('BASE_URL', $basePath);
define('ROOT_PATH', realpath(__DIR__ . '/..'));

// Upload Directories
define('UPLOAD_PATH_ASSIGNMENTS', ROOT_PATH . '/uploads/assignments/');
define('UPLOAD_PATH_SUBMISSIONS', ROOT_PATH . '/uploads/submissions/');
define('UPLOAD_PATH_AVATARS', ROOT_PATH . '/uploads/avatars/');

// File Upload Constraints
define('MAX_FILE_SIZE_BYTES', 10 * 1024 * 1024); // 10 MB limit
const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'zip', 'txt', 'png', 'jpg', 'jpeg'];
const ALLOWED_MIME_TYPES = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/zip',
    'application/x-zip-compressed',
    'text/plain',
    'image/jpeg',
    'image/png'
];

// Include database connection
require_once __DIR__ . '/database.php';

// ========================================================
// Security & Output Escaping Helpers
// ========================================================

/**
 * Escapes output string to prevent Cross-Site Scripting (XSS).
 *
 * @param mixed $data
 * @return string
 */
function e($data): string {
    return htmlspecialchars((string)($data ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Generates or retrieves existing CSRF token for the active session.
 *
 * @return string
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Returns hidden HTML input field with current CSRF token.
 *
 * @return string
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Validates submitted CSRF token using timing-attack safe comparison.
 *
 * @param string|null $token
 * @return bool
 */
function verify_csrf_token(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// ========================================================
// Flash Messaging System
// ========================================================

/**
 * Sets a flash message for display on next page request.
 *
 * @param string $type ('success', 'danger', 'warning', 'info')
 * @param string $message
 */
function setFlash(string $type, string $message): void {
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][] = [
        'type'    => $type,
        'message' => $message
    ];
}

/**
 * Retrieves and clears all pending flash messages.
 *
 * @return array
 */
function getFlashes(): array {
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/**
 * Renders pending flash messages as dismissible Bootstrap alerts.
 *
 * @return string
 */
function renderFlashMessages(): string {
    $flashes = getFlashes();
    if (empty($flashes)) {
        return '';
    }

    $html = '<div class="flash-messages-container mb-4">';
    foreach ($flashes as $f) {
        $alertType = e($f['type']);
        $icon = match ($alertType) {
            'success' => 'bi-check-circle-fill',
            'danger'  => 'bi-exclamation-triangle-fill',
            'warning' => 'bi-exclamation-circle-fill',
            default   => 'bi-info-circle-fill',
        };

        $html .= sprintf(
            '<div class="alert alert-%s alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">' .
            '<i class="bi %s me-2 fs-5"></i>' .
            '<div>%s</div>' .
            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' .
            '</div>',
            $alertType,
            $icon,
            e($f['message'])
        );
    }
    $html .= '</div>';
    return $html;
}

// ========================================================
// Authentication & Session State Helpers
// ========================================================

/**
 * Checks whether a user is currently logged in.
 *
 * @return bool
 */
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['user_role']);
}

/**
 * Returns authenticated user session payload or null.
 *
 * @return array|null
 */
function currentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['user_name'] ?? 'User',
        'email' => $_SESSION['user_email'] ?? '',
        'role'  => $_SESSION['user_role'] ?? '',
    ];
}

/**
 * Checks if current user possesses specified role.
 *
 * @param string $role
 * @return bool
 */
function hasRole(string $role): bool {
    return isLoggedIn() && strtolower($_SESSION['user_role']) === strtolower($role);
}

/**
 * Redirects browser to given relative or absolute URL and exits.
 *
 * @param string $url
 */
function redirect(string $url): void {
    if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
        $url = BASE_URL . '/' . ltrim($url, '/');
    }
    header("Location: {$url}");
    exit;
}

// ========================================================
// Presentation & Formatting Helpers
// ========================================================

/**
 * Formats datetime string cleanly.
 *
 * @param string|null $datetime
 * @param string $format
 * @return string
 */
function formatDate(?string $datetime, string $format = 'd M Y, h:i A'): string {
    if (empty($datetime)) {
        return '—';
    }
    $dt = new DateTime($datetime);
    return $dt->format($format);
}

/**
 * Returns colored Bootstrap badge HTML for submission statuses.
 *
 * @param string $status
 * @return string
 */
function renderStatusBadge(string $status): string {
    return match (strtolower($status)) {
        'completed' => '<span class="badge bg-success text-white px-2 py-1"><i class="bi bi-check-circle me-1"></i>Completed</span>',
        'reviewed'  => '<span class="badge bg-info text-dark px-2 py-1"><i class="bi bi-eye me-1"></i>Reviewed</span>',
        'pending'   => '<span class="badge bg-warning text-dark px-2 py-1"><i class="bi bi-clock-history me-1"></i>Pending</span>',
        default     => '<span class="badge bg-secondary">' . e($status) . '</span>',
    };
}

/**
 * Returns deadline badge indicating remaining time or if overdue.
 *
 * @param string $deadline
 * @return string
 */
function renderDeadlineBadge(string $deadline): string {
    $now = new DateTime();
    $dl = new DateTime($deadline);
    $diff = $now->diff($dl);

    if ($now > $dl) {
        return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-exclamation-octagon me-1"></i>Overdue</span>';
    }

    if ($diff->days === 0) {
        return '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle"><i class="bi bi-alarm me-1"></i>Due Today (' . $diff->h . 'h left)</span>';
    }

    return '<span class="badge bg-light text-dark border"><i class="bi bi-calendar-event me-1"></i>' . $diff->days . ' days left</span>';
}


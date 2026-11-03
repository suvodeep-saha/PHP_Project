<?php
/**
 * Secure File Download & In-Browser Viewer Controller
 * Online Assignment Submission System
 * 
 * Demonstrates:
 * - Session-based authorization for file access
 * - Inline browser preview vs attachment download (Content-Disposition)
 * - Directory traversal attack prevention (basename() / realpath())
 * - Proper HTTP headers for forced download and browser rendering
 * - Role-based authorization: Students can only access their own submissions;
 *   Teachers & Admins can access all submissions.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';

// Must be authenticated to access any system file
requireAuth();

$type = trim($_GET['type'] ?? ''); // 'assignment' or 'submission'
$id = (int)($_GET['id'] ?? 0);
$action = trim($_GET['action'] ?? 'download'); // 'view' (inline preview) or 'download' (attachment)

if (!$id || !in_array($type, ['assignment', 'submission'], true)) {
    die('Invalid file request parameters.');
}

$pdo = getDBConnection();
$user = currentUser();
$userId = (int)$user['id'];
$userRole = strtolower($user['role']);

$filePath = null;
$originalFileName = null;

if ($type === 'assignment') {
    // 1. Fetch assignment record
    $stmt = $pdo->prepare("SELECT file, title FROM assignments WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $assign = $stmt->fetch();

    if (!$assign || empty($assign['file'])) {
        die('Assignment file not found or no attachment was uploaded.');
    }

    $safeFileName = basename($assign['file']);
    $filePath = UPLOAD_PATH_ASSIGNMENTS . $safeFileName;
    $originalFileName = $safeFileName;

} elseif ($type === 'submission') {
    // 2. Fetch submission record
    $stmt = $pdo->prepare("SELECT s.file, s.student_id, a.title FROM submissions s JOIN assignments a ON s.assignment_id = a.id WHERE s.id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $sub = $stmt->fetch();

    if (!$sub || empty($sub['file'])) {
        die('Submission file not found.');
    }

    // Role-based authorization: Students can ONLY access their own submission
    if ($userRole === 'student' && (int)$sub['student_id'] !== $userId) {
        die('Unauthorized! You can only access your own submitted files.');
    }

    $safeFileName = basename($sub['file']);
    $filePath = UPLOAD_PATH_SUBMISSIONS . $safeFileName;
    $originalFileName = $safeFileName;
}

// Ensure the resolved real path is within the designated upload directory
$realPath = realpath($filePath);
$expectedBaseDir = ($type === 'assignment') ? realpath(UPLOAD_PATH_ASSIGNMENTS) : realpath(UPLOAD_PATH_SUBMISSIONS);

if (!$realPath || !file_exists($realPath) || !str_starts_with($realPath, $expectedBaseDir)) {
    die('The requested file does not exist on the server storage.');
}

// Determine MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $realPath) ?: 'application/octet-stream';
finfo_close($finfo);

// Choose disposition: inline allows browser to preview PDF/images/text; attachment forces download
$disposition = ($action === 'view') ? 'inline' : 'attachment';

// Send clean HTTP response headers
header('Content-Description: File Transfer');
header('Content-Type: ' . $mimeType);
header('Content-Disposition: ' . $disposition . '; filename="' . basename($originalFileName) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . filesize($realPath));

// Clean output buffer before streaming binary file
if (ob_get_level()) {
    ob_end_clean();
}
readfile($realPath);
exit;

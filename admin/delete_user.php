<?php
/**
 * Administrator Delete User (CRUD Delete)
 * Online Assignment Submission System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAuth('admin');

$adminId = (int)$_SESSION['user_id'];
$targetUserId = (int)($_GET['id'] ?? 0);

if (!$targetUserId) {
    setFlash('warning', 'Invalid user selection.');
    redirect('admin/users.php');
}

if ($targetUserId === $adminId) {
    setFlash('danger', 'Self-deletion prohibited! You cannot delete your own active administrator account.');
    redirect('admin/users.php');
}

$pdo = getDBConnection();

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $targetUserId]);
$targetUser = $stmt->fetch();

if (!$targetUser) {
    setFlash('danger', 'User not found.');
    redirect('admin/users.php');
}

try {
    // If user was a teacher, gather question files to delete
    if ($targetUser['role'] === 'teacher') {
        $stmtFiles = $pdo->prepare("SELECT file FROM assignments WHERE teacher_id = :tid");
        $stmtFiles->execute(['tid' => $targetUserId]);
        foreach ($stmtFiles->fetchAll(PDO::FETCH_COLUMN) as $f) {
            if (!empty($f) && file_exists(UPLOAD_PATH_ASSIGNMENTS . $f)) {
                @unlink(UPLOAD_PATH_ASSIGNMENTS . $f);
            }
        }
    }

    // If user was a student, gather submission files to delete
    if ($targetUser['role'] === 'student') {
        $stmtSubs = $pdo->prepare("SELECT file FROM submissions WHERE student_id = :sid");
        $stmtSubs->execute(['sid' => $targetUserId]);
        foreach ($stmtSubs->fetchAll(PDO::FETCH_COLUMN) as $sf) {
            if (!empty($sf) && file_exists(UPLOAD_PATH_SUBMISSIONS . $sf)) {
                @unlink(UPLOAD_PATH_SUBMISSIONS . $sf);
            }
        }
    }

    // Delete user from MySQL (FK cascading deletes child rows)
    $stmtDel = $pdo->prepare("DELETE FROM users WHERE id = :id");
    $stmtDel->execute(['id' => $targetUserId]);

    setFlash('success', 'User account "' . e($targetUser['name']) . '" (' . e($targetUser['role']) . ') was permanently deleted.');
} catch (PDOException $e) {
    setFlash('danger', 'Database error deleting user: ' . $e->getMessage());
}

redirect('admin/users.php');


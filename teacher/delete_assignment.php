<?php
/**
 * Delete Assignment (CRUD Delete)
 * Online Assignment Submission System
 * 
 * Demonstrates:
 * - Ownership verification before deletion
 * - Cascade deletion and filesystem file cleanup
 * - Parameterized DELETE statement
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAuth('teacher');

$teacherId = (int)$_SESSION['user_id'];
$assignmentId = (int)($_GET['id'] ?? 0);

if (!$assignmentId) {
    setFlash('warning', 'Invalid assignment selection.');
    redirect('teacher/assignments.php');
}

$pdo = getDBConnection();

// Fetch assignment to verify ownership and get file name
$stmt = $pdo->prepare("SELECT * FROM assignments WHERE id = :id AND teacher_id = :tid LIMIT 1");
$stmt->execute(['id' => $assignmentId, 'tid' => $teacherId]);
$assignment = $stmt->fetch();

if (!$assignment) {
    setFlash('danger', 'Assignment not found or permission denied.');
    redirect('teacher/assignments.php');
}

// Find all student submission files associated with this assignment to clean up disk storage
$stmtSubs = $pdo->prepare("SELECT file FROM submissions WHERE assignment_id = :aid");
$stmtSubs->execute(['aid' => $assignmentId]);
$submissionFiles = $stmtSubs->fetchAll(PDO::FETCH_COLUMN);

try {
    // Delete from MySQL (submissions cascade delete automatically due to FK constraint)
    $stmtDel = $pdo->prepare("DELETE FROM assignments WHERE id = :id AND teacher_id = :tid");
    $stmtDel->execute(['id' => $assignmentId, 'tid' => $teacherId]);

    // Clean up assignment file
    if (!empty($assignment['file']) && file_exists(UPLOAD_PATH_ASSIGNMENTS . $assignment['file'])) {
        @unlink(UPLOAD_PATH_ASSIGNMENTS . $assignment['file']);
    }

    // Clean up submitted solution files from disk
    foreach ($submissionFiles as $subFile) {
        if (!empty($subFile) && file_exists(UPLOAD_PATH_SUBMISSIONS . $subFile)) {
            @unlink(UPLOAD_PATH_SUBMISSIONS . $subFile);
        }
    }

    setFlash('success', 'Assignment "' . e($assignment['title']) . '" and all associated submissions were permanently deleted.');
} catch (PDOException $e) {
    setFlash('danger', 'Database error deleting assignment: ' . $e->getMessage());
}

redirect('teacher/assignments.php');


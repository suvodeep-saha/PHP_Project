<?php
/**
 * Administrator Submission Status Management
 * Online Assignment Submission System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAuth('admin');

$adminId = (int)$_SESSION['user_id'];
$submissionId = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));

if (!$submissionId) {
    setFlash('warning', 'Invalid submission ID.');
    redirect('admin/submissions.php');
}

$pdo = getDBConnection();

// Fetch Submission details
$stmt = $pdo->prepare("
    SELECT s.*, 
           u.name AS student_name, 
           u.email AS student_email,
           a.title AS assignment_title, 
           a.subject, 
           a.max_marks,
           t.name AS teacher_name
    FROM submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN users u ON s.student_id = u.id
    JOIN users t ON a.teacher_id = t.id
    WHERE s.id = :id
    LIMIT 1
");
$stmt->execute(['id' => $submissionId]);
$sub = $stmt->fetch();

if (!$sub) {
    setFlash('danger', 'Submission not found.');
    redirect('admin/submissions.php');
}

$errors = [];
$status = $sub['status'];
$marks = $sub['marks'] ?? '';
$feedback = $sub['feedback'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'CSRF validation failed. Please try again.';
    } else {
        $statusInput = trim($_POST['status'] ?? 'Pending');
        $marksInput = trim($_POST['marks'] ?? '');
        $feedbackInput = trim($_POST['feedback'] ?? '');

        if (!in_array($statusInput, ['Pending', 'Reviewed', 'Completed'], true)) {
            $errors[] = 'Invalid submission status selected.';
        }

        $marksVal = null;
        if ($marksInput !== '') {
            if (!is_numeric($marksInput)) {
                $errors[] = 'Marks must be a valid numerical value.';
            } else {
                $marksVal = (float)$marksInput;
                if ($marksVal < 0 || $marksVal > (float)$sub['max_marks']) {
                    $errors[] = 'Marks must be between 0 and ' . (int)$sub['max_marks'] . '.';
                }
            }
        }

        if (empty($errors)) {
            try {
                $stmtUpdate = $pdo->prepare("
                    UPDATE submissions 
                    SET status = :status, marks = :marks, feedback = :feedback, 
                        evaluated_at = NOW(), evaluated_by = :aid, updated_at = NOW()
                    WHERE id = :id
                ");
                $stmtUpdate->execute([
                    'status'   => $statusInput,
                    'marks'    => $marksVal,
                    'feedback' => !empty($feedbackInput) ? $feedbackInput : null,
                    'aid'      => $adminId,
                    'id'       => $submissionId,
                ]);

                setFlash('success', 'Submission status updated successfully to ' . strtoupper($statusInput) . '.');
                redirect('admin/submissions.php');
            } catch (PDOException $e) {
                $errors[] = 'Database error updating submission status: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Manage Submission #' . $sub['id'];
$isDashboard = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <?php echo renderFlashMessages(); ?>

            <div class="mb-3">
                <a href="<?php echo BASE_URL; ?>/admin/submissions.php" class="text-decoration-none text-muted small">
                    <i class="bi bi-arrow-left me-1"></i>Back to All Submissions
                </a>
            </div>

            <div class="row g-4 justify-content-center">
                <div class="col-lg-7">
                    <div class="custom-card">
                        <div class="custom-card-header">
                            <div>
                                <h4 class="fw-bold text-dark mb-0"><i class="bi bi-sliders me-2 text-primary"></i>Administrative Status Override</h4>
                                <p class="text-muted small mb-0">Update submission workflow state, marks, or administrative notes.</p>
                            </div>
                        </div>

                        <div class="custom-card-body">
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger mb-4">
                                    <h6 class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Validation Notice:</h6>
                                    <ul class="mb-0 ps-3 small">
                                        <?php foreach ($errors as $err): ?>
                                            <li><?php echo e($err); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="<?php echo e($_SERVER['PHP_SELF']); ?>?id=<?php echo (int)$submissionId; ?>">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo (int)$submissionId; ?>">

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="status" class="form-label small fw-semibold text-secondary">Submission Status <span class="text-danger">*</span></label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="Pending" <?php echo $status === 'Pending' ? 'selected' : ''; ?>>Pending Review</option>
                                            <option value="Reviewed" <?php echo $status === 'Reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                            <option value="Completed" <?php echo $status === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="marks" class="form-label small fw-semibold text-secondary">Marks Awarded (Max: <?php echo (int)$sub['max_marks']; ?>)</label>
                                        <div class="input-group">
                                            <input type="number" step="0.5" class="form-control" id="marks" name="marks" value="<?php echo e($marks); ?>" min="0" max="<?php echo (int)$sub['max_marks']; ?>" placeholder="Optional">
                                            <span class="input-group-text bg-light">/ <?php echo (int)$sub['max_marks']; ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label for="feedback" class="form-label small fw-semibold text-secondary">Feedback / Remarks</label>
                                    <textarea class="form-control" id="feedback" name="feedback" rows="4"><?php echo e($feedback); ?></textarea>
                                </div>

                                <div class="d-flex justify-content-between align-items-center border-top pt-3">
                                    <a href="<?php echo BASE_URL; ?>/admin/submissions.php" class="btn btn-outline-secondary">
                                        Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary shadow-sm">
                                        <i class="bi bi-save me-1"></i>Save Status Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="custom-card">
                        <div class="custom-card-header">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-info-circle me-2 text-primary"></i>Submission Summary</h6>
                        </div>
                        <div class="custom-card-body p-0">
                            <ul class="list-group list-group-flush small">
                                <li class="list-group-item p-3">
                                    <span class="text-muted d-block mb-1">Student:</span>
                                    <strong class="fs-6 text-dark"><?php echo e($sub['student_name']); ?></strong>
                                    <div class="text-muted"><?php echo e($sub['student_email']); ?></div>
                                </li>
                                <li class="list-group-item p-3">
                                    <span class="text-muted d-block mb-1">Assignment:</span>
                                    <strong class="text-dark"><?php echo e($sub['assignment_title']); ?></strong>
                                    <div class="badge bg-light text-secondary border mt-1"><?php echo e($sub['subject']); ?></div>
                                </li>
                                <li class="list-group-item p-3">
                                    <span class="text-muted d-block mb-1">Instructor:</span>
                                    <span class="fw-semibold"><?php echo e($sub['teacher_name']); ?></span>
                                </li>
                                <li class="list-group-item p-3">
                                    <span class="text-muted d-block mb-1">Solution File:</span>
                                    <div class="p-2 border rounded bg-light d-flex align-items-center justify-content-between">
                                        <span class="text-truncate fw-semibold" style="max-width: 170px;"><?php echo e(basename($sub['file'])); ?></span>
                                        <a href="<?php echo BASE_URL; ?>/download.php?type=submission&id=<?php echo (int)$sub['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-download me-1"></i>Download
                                        </a>
                                    </div>
                                </li>
                                <li class="list-group-item d-flex justify-content-between p-3">
                                    <span class="text-muted">Submitted Date:</span>
                                    <span><?php echo formatDate($sub['submit_date']); ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


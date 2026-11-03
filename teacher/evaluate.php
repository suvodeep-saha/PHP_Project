<?php
/**
 * Evaluate Student Submission
 * Online Assignment Submission System
 * 
 * Demonstrates:
 * - Dynamic form processing with marks validation
 * - Workflow state transition (Pending -> Reviewed -> Completed)
 * - In-browser document preview & safe download linking
 * - Universal faculty evaluation capability
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAuth('teacher');

$teacherId = (int)$_SESSION['user_id'];
$submissionId = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
$returnTo = trim($_GET['return_to'] ?? ($_POST['return_to'] ?? ''));

if (!$submissionId) {
    setFlash('warning', 'Invalid submission ID.');
    redirect('teacher/submissions.php');
}

$pdo = getDBConnection();

// Fetch Submission (allows any logged-in teacher to evaluate)
$stmt = $pdo->prepare("
    SELECT s.*, 
           u.name AS student_name, 
           u.email AS student_email, 
           u.phone AS student_phone,
           a.id AS assignment_id,
           a.title AS assignment_title, 
           a.subject, 
           a.max_marks, 
           a.deadline,
           a.teacher_id AS assignment_teacher_id,
           t.name AS assignment_author_name
    FROM submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN users u ON s.student_id = u.id
    JOIN users t ON a.teacher_id = t.id
    WHERE s.id = :sid
    LIMIT 1
");
$stmt->execute(['sid' => $submissionId]);
$sub = $stmt->fetch();

if (!$sub) {
    setFlash('danger', 'Submission not found.');
    redirect('teacher/submissions.php');
}

$errors = [];
$marks = $sub['marks'] ?? '';
$status = $sub['status'] === 'Pending' ? 'Reviewed' : $sub['status'];
$feedback = $sub['feedback'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'CSRF validation failed. Please try again.';
    }

    $marksInput = trim($_POST['marks'] ?? '');
    $statusInput = trim($_POST['status'] ?? 'Reviewed');
    $feedbackInput = trim($_POST['feedback'] ?? '');

    if ($marksInput === '' || !is_numeric($marksInput)) {
        $errors[] = 'Please provide a valid numerical score for marks.';
    } else {
        $marksFloat = (float)$marksInput;
        if ($marksFloat < 0 || $marksFloat > (float)$sub['max_marks']) {
            $errors[] = 'Marks must be between 0 and ' . (int)$sub['max_marks'] . '.';
        }
    }

    if (!in_array($statusInput, ['Pending', 'Reviewed', 'Completed'], true)) {
        $errors[] = 'Invalid evaluation status selected.';
    }

    if (empty($errors)) {
        try {
            $sqlUpdate = "UPDATE submissions 
                          SET marks = :marks, feedback = :feedback, status = :status, 
                              evaluated_at = NOW(), evaluated_by = :tid, updated_at = NOW()
                          WHERE id = :id";
            $stmtUpdate = $pdo->prepare($sqlUpdate);
            $stmtUpdate->execute([
                'marks'    => (float)$marksInput,
                'feedback' => !empty($feedbackInput) ? $feedbackInput : null,
                'status'   => $statusInput,
                'tid'      => $teacherId,
                'id'       => $submissionId,
            ]);

            setFlash('success', 'Evaluation saved! Marks and remarks published for student ' . e($sub['student_name']) . '.');

            if (!empty($returnTo) && !str_starts_with($returnTo, 'http')) {
                redirect($returnTo);
            } else {
                redirect('teacher/assignment_details.php?id=' . (int)$sub['assignment_id']);
            }
        } catch (PDOException $e) {
            $errors[] = 'Database update error: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Evaluate: ' . $sub['student_name'];
$isDashboard = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <?php echo renderFlashMessages(); ?>

            <div class="mb-3 d-flex justify-content-between align-items-center">
                <a href="<?php echo BASE_URL; ?>/teacher/assignment_details.php?id=<?php echo (int)$sub['assignment_id']; ?>" class="text-decoration-none text-muted small">
                    <i class="bi bi-arrow-left me-1"></i>Back to Assignment Submissions
                </a>
                <a href="<?php echo BASE_URL; ?>/teacher/submissions.php" class="text-decoration-none text-muted small">
                    All Submissions Queue &rarr;
                </a>
            </div>

            <div class="row g-4 justify-content-center">
                <!-- Evaluation Form Card -->
                <div class="col-lg-7">
                    <div class="custom-card">
                        <div class="custom-card-header">
                            <div>
                                <h4 class="fw-bold text-dark mb-0"><i class="bi bi-pencil-square me-2 text-primary"></i>Grade &amp; Provide Remarks</h4>
                                <p class="text-muted small mb-0">Record scores, provide detailed remarks, and transition the submission status.</p>
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
                                <input type="hidden" name="return_to" value="<?php echo e($returnTo); ?>">

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="marks" class="form-label fw-semibold text-secondary small">
                                            Marks Awarded (Max: <?php echo (int)$sub['max_marks']; ?>) <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <input type="number" step="0.5" class="form-control form-control-lg" id="marks" name="marks" value="<?php echo e($marks); ?>" min="0" max="<?php echo (int)$sub['max_marks']; ?>" required placeholder="e.g. 85.0">
                                            <span class="input-group-text bg-light">/ <?php echo (int)$sub['max_marks']; ?></span>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="status" class="form-label fw-semibold text-secondary small">
                                            Workflow Status <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select form-select-lg" id="status" name="status" required>
                                            <option value="Reviewed" <?php echo $status === 'Reviewed' ? 'selected' : ''; ?>>
                                                Reviewed (Remarks &amp; Feedback Given)
                                            </option>
                                            <option value="Completed" <?php echo $status === 'Completed' ? 'selected' : ''; ?>>
                                                Completed (Finalized Grade)
                                            </option>
                                            <option value="Pending" <?php echo $status === 'Pending' ? 'selected' : ''; ?>>
                                                Pending (Needs Further Review)
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label for="feedback" class="form-label fw-semibold text-secondary small">
                                        Faculty Remarks &amp; Feedback <span class="text-primary fw-normal">(Visible to student)</span>
                                    </label>
                                    <textarea class="form-control" id="feedback" name="feedback" rows="5" placeholder="Enter constructive feedback, praise, and areas for improvement for this student..."><?php echo e($feedback); ?></textarea>
                                </div>

                                <div class="d-flex justify-content-between align-items-center border-top pt-3">
                                    <a href="<?php echo BASE_URL; ?>/teacher/assignment_details.php?id=<?php echo (int)$sub['assignment_id']; ?>" class="btn btn-outline-secondary">
                                        Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary btn-lg shadow-sm">
                                        <i class="bi bi-check2-circle me-1"></i>Publish Remarks &amp; Score
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Submission & Student Info Card -->
                <div class="col-lg-5">
                    <div class="custom-card mb-4">
                        <div class="custom-card-header">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Submission Metadata</h6>
                        </div>
                        <div class="custom-card-body p-0">
                            <ul class="list-group list-group-flush small">
                                <li class="list-group-item p-3">
                                    <span class="text-muted d-block mb-1">Student:</span>
                                    <strong class="fs-6 text-dark"><?php echo e($sub['student_name']); ?></strong>
                                    <div class="text-muted"><?php echo e($sub['student_email']); ?></div>
                                    <?php if (!empty($sub['student_phone'])): ?>
                                        <div class="text-secondary small mt-1"><i class="bi bi-telephone me-1"></i><?php echo e($sub['student_phone']); ?></div>
                                    <?php endif; ?>
                                </li>
                                <li class="list-group-item p-3">
                                    <span class="text-muted d-block mb-1">Assignment:</span>
                                    <strong class="text-dark"><?php echo e($sub['assignment_title']); ?></strong>
                                    <div class="d-flex gap-2 mt-1">
                                        <span class="badge bg-light text-secondary border"><?php echo e($sub['subject']); ?></span>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Faculty: <?php echo e($sub['assignment_author_name']); ?></span>
                                    </div>
                                </li>
                                <li class="list-group-item p-3">
                                    <span class="text-muted d-block mb-1">Uploaded Solution File:</span>
                                    <div class="p-2 border rounded bg-light">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <span class="text-truncate fw-semibold" style="max-width: 200px;"><?php echo e(basename($sub['file'])); ?></span>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">Ready to view</span>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <a href="<?php echo BASE_URL; ?>/download.php?type=submission&id=<?php echo (int)$sub['id']; ?>&action=view" target="_blank" class="btn btn-sm btn-primary flex-fill">
                                                <i class="bi bi-eye me-1"></i>View File in Browser
                                            </a>
                                            <a href="<?php echo BASE_URL; ?>/download.php?type=submission&id=<?php echo (int)$sub['id']; ?>&action=download" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-download me-1"></i>Download
                                            </a>
                                        </div>
                                    </div>
                                </li>
                                <li class="list-group-item d-flex justify-content-between p-3">
                                    <span class="text-muted">Submitted Date:</span>
                                    <span><?php echo formatDate($sub['submit_date']); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between p-3">
                                    <span class="text-muted">Current Status:</span>
                                    <span><?php echo renderStatusBadge($sub['status']); ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <?php if (!empty($sub['student_notes'])): ?>
                        <div class="card border bg-light rounded-3">
                            <div class="card-body small">
                                <h6 class="fw-bold text-dark mb-1"><i class="bi bi-chat-left-text me-1 text-primary"></i>Student Notes</h6>
                                <p class="text-muted mb-0 fst-italic">"<?php echo e($sub['student_notes']); ?>"</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

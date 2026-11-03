<?php
/**
 * Student Assignment Details Page
 * Online Assignment Submission System
 * 
 * Demonstrates:
 * - Parameterized PDO single-record retrieval
 * - Conditional view rendering based on submission state
 * - Secure download integration
 * - Real-time deadline analysis
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAuth('student');

$studentId = (int)$_SESSION['user_id'];
$assignmentId = (int)($_GET['id'] ?? 0);

if (!$assignmentId) {
    setFlash('warning', 'Invalid assignment selection.');
    redirect('student/assignments.php');
}

$pdo = getDBConnection();

// Fetch Assignment with Teacher info
$stmt = $pdo->prepare("
    SELECT a.*, u.name AS teacher_name, u.email AS teacher_email
    FROM assignments a
    JOIN users u ON a.teacher_id = u.id
    WHERE a.id = :id
    LIMIT 1
");
$stmt->execute(['id' => $assignmentId]);
$assignment = $stmt->fetch();

if (!$assignment) {
    setFlash('danger', 'Assignment not found.');
    redirect('student/assignments.php');
}

// Fetch this student's submission for this assignment
$stmtSub = $pdo->prepare("
    SELECT s.*, u.name AS evaluator_name
    FROM submissions s
    LEFT JOIN users u ON s.evaluated_by = u.id
    WHERE s.assignment_id = :aid AND s.student_id = :sid
    LIMIT 1
");
$stmtSub->execute(['aid' => $assignmentId, 'sid' => $studentId]);
$submission = $stmtSub->fetch();

$isOverdue = (new DateTime()) > (new DateTime($assignment['deadline']));

$pageTitle = $assignment['title'];
$isDashboard = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <?php echo renderFlashMessages(); ?>

            <div class="mb-3">
                <a href="<?php echo BASE_URL; ?>/student/assignments.php" class="text-decoration-none text-muted small">
                    <i class="bi bi-arrow-left me-1"></i>Back to All Assignments
                </a>
            </div>

            <div class="row g-4">
                <!-- Main Assignment Details -->
                <div class="col-lg-8">
                    <div class="custom-card">
                        <div class="custom-card-header">
                            <div>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle mb-1">
                                    <?php echo e($assignment['subject']); ?>
                                </span>
                                <h3 class="fw-bold text-dark mb-0"><?php echo e($assignment['title']); ?></h3>
                            </div>
                            <?php echo renderDeadlineBadge($assignment['deadline']); ?>
                        </div>

                        <div class="custom-card-body">
                            <h6 class="fw-bold text-secondary text-uppercase small mb-2">Assignment Brief &amp; Instructions</h6>
                            <div class="p-3 bg-light rounded-3 border text-dark mb-4" style="white-space: pre-line; line-height: 1.7;">
                                <?php echo e($assignment['description']); ?>
                            </div>

                            <!-- Attached Question File -->
                            <h6 class="fw-bold text-secondary text-uppercase small mb-2">Attached Material / Question Sheet</h6>
                            <?php if (!empty($assignment['file'])): ?>
                                <div class="p-3 border rounded-3 bg-white d-flex align-items-center justify-content-between mb-4">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-file-earmark-pdf-fill text-danger fs-2 me-3"></i>
                                        <div>
                                            <h6 class="mb-0 fw-semibold text-dark"><?php echo e($assignment['file']); ?></h6>
                                            <small class="text-muted">Instructor question paper</small>
                                        </div>
                                    </div>
                                    <a href="<?php echo BASE_URL; ?>/download.php?type=assignment&id=<?php echo (int)$assignment['id']; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-download me-1"></i>Download File
                                    </a>
                                </div>
                            <?php else: ?>
                                <p class="text-muted small fst-italic mb-4">No external reference file attached to this assignment.</p>
                            <?php endif; ?>

                            <!-- Submission Status Section -->
                            <div class="border-top pt-4">
                                <h5 class="fw-bold text-dark mb-3">Your Submission Status</h5>

                                <?php if ($submission): ?>
                                    <div class="alert alert-light border shadow-sm p-4 rounded-3 mb-0">
                                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                                            <div class="d-flex align-items-center">
                                                <span class="fw-bold me-2">Current Evaluation Status:</span>
                                                <?php echo renderStatusBadge($submission['status']); ?>
                                            </div>
                                            <span class="small text-muted">
                                                Submitted on: <?php echo formatDate($submission['submit_date']); ?>
                                            </span>
                                        </div>

                                        <div class="p-3 bg-white rounded-3 border mb-3">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-check-fill text-success fs-3 me-2"></i>
                                                    <div>
                                                        <h6 class="mb-0 fw-semibold"><?php echo e($submission['file']); ?></h6>
                                                        <small class="text-muted">Your uploaded solution document</small>
                                                    </div>
                                                </div>
                                                <a href="<?php echo BASE_URL; ?>/download.php?type=submission&id=<?php echo (int)$submission['id']; ?>" class="btn btn-sm btn-outline-success">
                                                    <i class="bi bi-download me-1"></i>Download My Submission
                                                </a>
                                            </div>
                                        </div>

                                        <?php if (!empty($submission['student_notes'])): ?>
                                            <div class="p-2 bg-light rounded border small text-secondary mb-3">
                                                <i class="bi bi-chat-left-dots text-primary me-1"></i>
                                                <strong>My Submitted Notes:</strong> "<?php echo e($submission['student_notes']); ?>"
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($submission['feedback']) || $submission['status'] === 'Completed' || $submission['status'] === 'Reviewed'): ?>
                                            <div class="card border-success border-opacity-25 bg-success-subtle bg-opacity-10 mt-3">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <h6 class="fw-bold text-success mb-0"><i class="bi bi-award-fill me-1"></i>Faculty Evaluation &amp; Remarks</h6>
                                                        <?php if ($submission['marks'] !== null): ?>
                                                            <span class="badge bg-success fs-6">
                                                                Score: <?php echo (float)$submission['marks']; ?> / <?php echo (int)$assignment['max_marks']; ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="p-2 bg-white rounded border small text-dark mb-2">
                                                        <strong>Instructor Feedback:</strong> 
                                                        <?php echo !empty($submission['feedback']) ? e($submission['feedback']) : 'No written remarks.'; ?>
                                                    </div>
                                                    <small class="text-muted">Evaluated by: <strong><?php echo e($submission['evaluator_name'] ?? 'Instructor'); ?></strong> <?php if (!empty($submission['evaluated_at'])): ?>on <?php echo formatDate($submission['evaluated_at']); ?><?php endif; ?></small>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-warning mb-0 py-2 small">
                                                <i class="bi bi-hourglass-split me-1"></i>Your submission has been safely received and is awaiting evaluation by your instructor.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center p-4 bg-light rounded-3 border">
                                        <i class="bi bi-cloud-arrow-up fs-1 text-primary d-block mb-2"></i>
                                        <h6 class="fw-bold text-dark">You have not submitted work for this assignment yet</h6>
                                        <p class="text-muted small mb-3">Make sure your file adheres to the instructions and submit before the deadline.</p>

                                        <?php if ($isOverdue): ?>
                                            <div class="alert alert-danger py-2 small d-inline-block">
                                                <i class="bi bi-exclamation-triangle-fill me-1"></i>Deadline has passed (Overdue). You may still submit, but it will be flagged as late.
                                            </div>
                                            <div class="mt-2">
                                                <a href="<?php echo BASE_URL; ?>/student/submit.php?id=<?php echo (int)$assignment['id']; ?>" class="btn btn-warning shadow-sm">
                                                    <i class="bi bi-upload me-1"></i>Submit Late Work
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <a href="<?php echo BASE_URL; ?>/student/submit.php?id=<?php echo (int)$assignment['id']; ?>" class="btn btn-primary btn-lg px-4 shadow-sm">
                                                <i class="bi bi-upload me-2"></i>Upload &amp; Submit Solution
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Assignment Metadata Sidebar -->
                <div class="col-lg-4">
                    <div class="custom-card mb-4">
                        <div class="custom-card-header">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-info-circle me-2 text-primary"></i>Summary Information</h6>
                        </div>
                        <div class="custom-card-body p-0">
                            <ul class="list-group list-group-flush small">
                                <li class="list-group-item d-flex justify-content-between p-3">
                                    <span class="text-muted">Subject:</span>
                                    <span class="fw-semibold"><?php echo e($assignment['subject']); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between p-3">
                                    <span class="text-muted">Instructor:</span>
                                    <span class="fw-semibold"><?php echo e($assignment['teacher_name']); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between p-3">
                                    <span class="text-muted">Total Marks:</span>
                                    <span class="badge bg-light text-dark border"><?php echo (int)$assignment['max_marks']; ?> points</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between p-3">
                                    <span class="text-muted">Deadline:</span>
                                    <span class="fw-semibold text-danger"><?php echo formatDate($assignment['deadline']); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between p-3">
                                    <span class="text-muted">Posted On:</span>
                                    <span><?php echo formatDate($assignment['created_at']); ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="card border bg-light rounded-3">
                        <div class="card-body small">
                            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-lightbulb text-warning me-1"></i>Submission Guidelines</h6>
                            <ul class="ps-3 mb-0 text-muted">
                                <li>Accepted formats: <code>.pdf</code>, <code>.docx</code>, <code>.zip</code>, <code>.txt</code>.</li>
                                <li>Max file size limit is <strong>10 MB</strong>.</li>
                                <li>Ensure your name and roll number are written inside the document.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


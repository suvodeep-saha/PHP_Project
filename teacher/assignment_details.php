<?php
/**
 * Teacher Assignment Details, Submissions Review & Remarks Hub
 * Online Assignment Submission System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAuth('teacher');

$teacherId = (int)$_SESSION['user_id'];
$assignmentId = (int)($_GET['id'] ?? ($_POST['assignment_id'] ?? 0));

if (!$assignmentId) {
    setFlash('warning', 'Invalid assignment ID.');
    redirect('teacher/assignments.php');
}

$pdo = getDBConnection();

// Fetch Assignment with Author Details
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
    redirect('teacher/assignments.php');
}

$isAuthor = ((int)$assignment['teacher_id'] === $teacherId);

// Handle Inline Evaluation / Remarks Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_evaluation') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Security validation failed (CSRF). Please reload and try again.');
    } else {
        $subId = (int)($_POST['submission_id'] ?? 0);
        $marksInput = trim($_POST['marks'] ?? '');
        $statusInput = trim($_POST['status'] ?? 'Reviewed');
        $feedbackInput = trim($_POST['feedback'] ?? '');

        $evalErrors = [];
        if ($marksInput === '' || !is_numeric($marksInput)) {
            $evalErrors[] = 'Please provide a valid numerical score for marks.';
        } else {
            $marksFloat = (float)$marksInput;
            if ($marksFloat < 0 || $marksFloat > (float)$assignment['max_marks']) {
                $evalErrors[] = 'Marks must be between 0 and ' . (int)$assignment['max_marks'] . '.';
            }
        }

        if (!in_array($statusInput, ['Pending', 'Reviewed', 'Completed'], true)) {
            $evalErrors[] = 'Invalid status selected.';
        }

        if (empty($evalErrors)) {
            try {
                $stmtUpdate = $pdo->prepare("
                    UPDATE submissions 
                    SET marks = :marks, feedback = :feedback, status = :status, 
                        evaluated_at = NOW(), evaluated_by = :tid, updated_at = NOW()
                    WHERE id = :id AND assignment_id = :aid
                ");
                $stmtUpdate->execute([
                    'marks'    => (float)$marksInput,
                    'feedback' => !empty($feedbackInput) ? $feedbackInput : null,
                    'status'   => $statusInput,
                    'tid'      => $teacherId,
                    'id'       => $subId,
                    'aid'      => $assignmentId,
                ]);

                setFlash('success', 'Review & remarks saved successfully!');
                redirect('teacher/assignment_details.php?id=' . $assignmentId);
            } catch (PDOException $e) {
                setFlash('danger', 'Database error saving review: ' . $e->getMessage());
            }
        } else {
            setFlash('danger', implode(' ', $evalErrors));
        }
    }
}

// Fetch all submissions for this assignment joined with student and evaluator details
$stmtSubs = $pdo->prepare("
    SELECT s.*, 
           u.name AS student_name, 
           u.email AS student_email, 
           u.phone AS student_phone,
           eval.name AS evaluator_name
    FROM submissions s
    JOIN users u ON s.student_id = u.id
    LEFT JOIN users eval ON s.evaluated_by = eval.id
    WHERE s.assignment_id = :aid
    ORDER BY (s.status = 'Pending') DESC, s.submit_date DESC
");
$stmtSubs->execute(['aid' => $assignmentId]);
$submissions = $stmtSubs->fetchAll();

// Calculate metrics
$totalSubs = count($submissions);
$pendingSubs = 0;
$completedSubs = 0;
$totalMarks = 0;
$gradedCount = 0;

foreach ($submissions as $s) {
    if ($s['status'] === 'Pending') {
        $pendingSubs++;
    } else {
        $completedSubs++;
        if ($s['marks'] !== null) {
            $totalMarks += (float)$s['marks'];
            $gradedCount++;
        }
    }
}
$avgScore = $gradedCount > 0 ? round($totalMarks / $gradedCount, 1) : null;

$pageTitle = 'Assignment Details: ' . $assignment['title'];
$isDashboard = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <?php echo renderFlashMessages(); ?>

            <div class="mb-3 d-flex justify-content-between align-items-center">
                <a href="<?php echo BASE_URL; ?>/teacher/assignments.php" class="text-decoration-none text-muted small">
                    <i class="bi bi-arrow-left me-1"></i>Back to Assignments Catalog
                </a>
                <div class="d-flex gap-2">
                    <?php if ($isAuthor): ?>
                        <a href="<?php echo BASE_URL; ?>/teacher/edit_assignment.php?id=<?php echo (int)$assignment['id']; ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-pencil me-1"></i>Edit Assignment
                        </a>
                    <?php else: ?>
                        <span class="badge bg-light text-secondary border d-flex align-items-center px-2 py-1">
                            <i class="bi bi-person-fill me-1"></i>Created by <?php echo e($assignment['teacher_name']); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Assignment Info Section -->
            <div class="row g-4 mb-4">
                <div class="col-lg-8">
                    <div class="custom-card mb-0 h-100">
                        <div class="custom-card-header">
                            <div>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle mb-1"><?php echo e($assignment['subject']); ?></span>
                                <h3 class="fw-bold mb-0 text-dark"><?php echo e($assignment['title']); ?></h3>
                                <small class="text-muted">Faculty In-charge: <strong><?php echo e($assignment['teacher_name']); ?></strong> (<?php echo e($assignment['teacher_email']); ?>)</small>
                            </div>
                            <?php echo renderDeadlineBadge($assignment['deadline']); ?>
                        </div>
                        <div class="custom-card-body">
                            <h6 class="fw-bold text-secondary text-uppercase small mb-2">Instructions &amp; Requirements</h6>
                            <div class="p-3 bg-light rounded-3 border text-dark mb-4" style="white-space: pre-line; line-height: 1.7;">
                                <?php echo e($assignment['description']); ?>
                            </div>

                            <h6 class="fw-bold text-secondary text-uppercase small mb-2">Attached Material</h6>
                            <?php if (!empty($assignment['file'])): ?>
                                <div class="p-3 border rounded-3 bg-white d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-file-earmark-pdf-fill text-danger fs-2 me-3"></i>
                                        <div>
                                            <h6 class="mb-0 fw-semibold"><?php echo e(basename($assignment['file'])); ?></h6>
                                            <small class="text-muted">Reference / Question Sheet</small>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="<?php echo BASE_URL; ?>/download.php?type=assignment&id=<?php echo (int)$assignment['id']; ?>&action=view" target="_blank" class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-eye me-1"></i>View
                                        </a>
                                        <a href="<?php echo BASE_URL; ?>/download.php?type=assignment&id=<?php echo (int)$assignment['id']; ?>&action=download" class="btn btn-light border btn-sm">
                                            <i class="bi bi-download me-1"></i>Download
                                        </a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <p class="text-muted small fst-italic mb-0">No external reference file attached.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="custom-card h-100">
                        <div class="custom-card-header">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-bar-chart-fill me-2 text-primary"></i>Submission Statistics</h6>
                        </div>
                        <div class="custom-card-body p-0">
                            <ul class="list-group list-group-flush small">
                                <li class="list-group-item d-flex justify-content-between p-3">
                                    <span class="text-muted">Total Submissions:</span>
                                    <span class="fw-bold text-dark fs-6"><?php echo $totalSubs; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between p-3">
                                    <span class="text-muted">Pending Evaluation:</span>
                                    <span class="badge bg-warning text-dark fs-6"><?php echo $pendingSubs; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between p-3">
                                    <span class="text-muted">Evaluations Completed:</span>
                                    <span class="badge bg-success fs-6"><?php echo $completedSubs; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between p-3">
                                    <span class="text-muted">Average Score:</span>
                                    <span class="fw-bold text-primary fs-6"><?php echo $avgScore !== null ? $avgScore . ' / ' . (int)$assignment['max_marks'] : 'N/A'; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between p-3">
                                    <span class="text-muted">Deadline:</span>
                                    <span class="fw-semibold text-danger"><?php echo formatDate($assignment['deadline']); ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Student Submissions Review Table -->
            <div class="custom-card">
                <div class="custom-card-header">
                    <div>
                        <h5 class="mb-0 fw-bold">Student Submissions &amp; Review Queue (<?php echo count($submissions); ?>)</h5>
                        <p class="text-muted small mb-0">View submitted files, student notes, enter marks, and provide evaluation remarks.</p>
                    </div>
                    <input type="text" class="form-control form-control-sm w-auto" placeholder="Search student name..." data-table-filter="#assignmentSubsTable">
                </div>
                <div class="table-responsive">
                    <table class="table table-custom align-middle" id="assignmentSubsTable">
                        <thead>
                            <tr>
                                <th>Student Details</th>
                                <th>Submitted Work</th>
                                <th>Submitted On</th>
                                <th>Status</th>
                                <th>Marks Awarded</th>
                                <th>Faculty Remarks</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($submissions)): ?>
                                <?php foreach ($submissions as $s): ?>
                                    <tr class="<?php echo $s['status'] === 'Pending' ? 'table-warning-subtle' : ''; ?>">
                                        <td>
                                            <div class="fw-bold text-dark fs-6"><?php echo e($s['student_name']); ?></div>
                                            <small class="text-muted d-block"><?php echo e($s['student_email']); ?></small>
                                            <?php if (!empty($s['student_notes'])): ?>
                                                <div class="p-1 px-2 mt-1 bg-light rounded border small text-secondary">
                                                    <i class="bi bi-chat-left-dots text-primary me-1"></i>
                                                    <strong>Student Note:</strong> "<?php echo e($s['student_notes']); ?>"
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column gap-1">
                                                <div class="d-flex gap-1">
                                                    <a href="<?php echo BASE_URL; ?>/download.php?type=submission&id=<?php echo (int)$s['id']; ?>&action=view" target="_blank" class="btn btn-sm btn-primary shadow-sm" title="View Document in Browser">
                                                        <i class="bi bi-eye me-1"></i>View File
                                                    </a>
                                                    <a href="<?php echo BASE_URL; ?>/download.php?type=submission&id=<?php echo (int)$s['id']; ?>&action=download" class="btn btn-sm btn-outline-secondary" title="Download Document to Computer">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                </div>
                                                <small class="text-muted text-truncate" style="max-width: 170px;">
                                                    <?php echo e(basename($s['file'])); ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold"><?php echo formatDate($s['submit_date']); ?></div>
                                        </td>
                                        <td>
                                            <?php echo renderStatusBadge($s['status']); ?>
                                        </td>
                                        <td>
                                            <?php if ($s['marks'] !== null): ?>
                                                <span class="badge bg-success-subtle text-success fs-6 border border-success-subtle fw-bold">
                                                    <?php echo (float)$s['marks']; ?> / <?php echo (int)$assignment['max_marks']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                                    <i class="bi bi-clock me-1"></i>Awaiting Grade
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="max-width: 250px;">
                                            <?php if (!empty($s['feedback'])): ?>
                                                <div class="small text-dark p-2 bg-light rounded border">
                                                    <i class="bi bi-chat-quote-fill text-primary me-1"></i>
                                                    "<?php echo e($s['feedback']); ?>"
                                                    <div class="text-muted mt-1" style="font-size: 0.75rem;">
                                                        By: <?php echo e($s['evaluator_name'] ?? 'Faculty'); ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small fst-italic">No remarks written yet</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" 
                                                    class="btn btn-sm <?php echo $s['status'] === 'Pending' ? 'btn-primary' : 'btn-outline-primary'; ?> shadow-sm" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#reviewModal_<?php echo (int)$s['id']; ?>">
                                                <i class="bi bi-pencil-square me-1"></i><?php echo $s['status'] === 'Pending' ? 'Review &amp; Remark' : 'Edit Remarks'; ?>
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- Review & Remarks Modal for Submission -->
                                    <div class="modal fade" id="reviewModal_<?php echo (int)$s['id']; ?>" tabindex="-1" aria-labelledby="modalLabel_<?php echo (int)$s['id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <form method="POST" action="<?php echo e($_SERVER['PHP_SELF']); ?>?id=<?php echo (int)$assignmentId; ?>">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="action" value="save_evaluation">
                                                    <input type="hidden" name="assignment_id" value="<?php echo (int)$assignmentId; ?>">
                                                    <input type="hidden" name="submission_id" value="<?php echo (int)$s['id']; ?>">

                                                    <div class="modal-header bg-primary text-white">
                                                        <h5 class="modal-title fw-bold" id="modalLabel_<?php echo (int)$s['id']; ?>">
                                                            <i class="bi bi-clipboard-check me-2"></i>Review &amp; Remark: <?php echo e($s['student_name']); ?>
                                                        </h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>

                                                    <div class="modal-body p-4">
                                                        <!-- Submission Summary -->
                                                        <div class="p-3 bg-light rounded-3 border mb-4">
                                                            <div class="row g-2 align-items-center">
                                                                <div class="col-md-6">
                                                                    <div class="small text-muted">Student:</div>
                                                                    <div class="fw-bold text-dark fs-6"><?php echo e($s['student_name']); ?></div>
                                                                    <small class="text-muted"><?php echo e($s['student_email']); ?></small>
                                                                </div>
                                                                <div class="col-md-6 text-md-end">
                                                                    <div class="small text-muted mb-1">Submitted Document:</div>
                                                                    <div class="d-flex gap-2 justify-content-md-end">
                                                                        <a href="<?php echo BASE_URL; ?>/download.php?type=submission&id=<?php echo (int)$s['id']; ?>&action=view" target="_blank" class="btn btn-sm btn-primary">
                                                                            <i class="bi bi-eye me-1"></i>View Document in Browser
                                                                        </a>
                                                                        <a href="<?php echo BASE_URL; ?>/download.php?type=submission&id=<?php echo (int)$s['id']; ?>&action=download" class="btn btn-sm btn-outline-secondary">
                                                                            <i class="bi bi-download me-1"></i>Download
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <?php if (!empty($s['student_notes'])): ?>
                                                                <div class="mt-3 pt-2 border-top">
                                                                    <span class="small fw-bold text-secondary">Student's Comment:</span>
                                                                    <div class="p-2 bg-white rounded border small text-dark fst-italic mt-1">
                                                                        "<?php echo e($s['student_notes']); ?>"
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>

                                                        <!-- Scoring & Status -->
                                                        <div class="row mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label small fw-bold text-secondary">Marks Awarded (Max: <?php echo (int)$assignment['max_marks']; ?>) <span class="text-danger">*</span></label>
                                                                <div class="input-group">
                                                                    <input type="number" step="0.5" class="form-control form-control-lg" name="marks" value="<?php echo e($s['marks'] ?? ''); ?>" min="0" max="<?php echo (int)$assignment['max_marks']; ?>" required placeholder="e.g. 85.0">
                                                                    <span class="input-group-text bg-light">/ <?php echo (int)$assignment['max_marks']; ?></span>
                                                                </div>
                                                            </div>

                                                            <div class="col-md-6">
                                                                <label class="form-label small fw-bold text-secondary">Workflow Status <span class="text-danger">*</span></label>
                                                                <select class="form-select form-select-lg" name="status" required>
                                                                    <option value="Reviewed" <?php echo $s['status'] === 'Reviewed' ? 'selected' : ''; ?>>Reviewed (Feedback Provided)</option>
                                                                    <option value="Completed" <?php echo $s['status'] === 'Completed' ? 'selected' : ''; ?>>Completed (Finalized Evaluation)</option>
                                                                    <option value="Pending" <?php echo $s['status'] === 'Pending' ? 'selected' : ''; ?>>Pending (Under Review)</option>
                                                                </select>
                                                            </div>
                                                        </div>

                                                        <!-- Remarks / Feedback Textarea -->
                                                        <div class="mb-3">
                                                            <label class="form-label small fw-bold text-secondary">Faculty Remarks &amp; Feedback</label>
                                                            <textarea class="form-control" name="feedback" rows="4" placeholder="Write remarks, strengths, corrective suggestions, and advice for the student..."><?php echo e($s['feedback'] ?? ''); ?></textarea>
                                                            <div class="form-text small text-muted">
                                                                These remarks will be published directly to the student's dashboard and submissions view.
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="modal-footer bg-light">
                                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary px-4 shadow-sm">
                                                            <i class="bi bi-check-circle-fill me-1"></i>Save Remarks &amp; Publish Grade
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                        No student submissions received for this assignment yet.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
/**
 * Student Submissions History Page
 * Online Assignment Submission System
 * 
 * Demonstrates:
 * - Dynamic SQL JOINs between submissions, assignments, and teachers
 * - Status filter tabs (All, Pending, Reviewed, Completed)
 * - Safe download links
 * - Feedback display with marks calculation
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAuth('student');

$studentId = (int)$_SESSION['user_id'];
$pdo = getDBConnection();

$filterStatus = trim($_GET['status'] ?? 'all');

$sql = "
    SELECT s.*, 
           a.title AS assignment_title, 
           a.subject, 
           a.deadline, 
           a.max_marks,
           u.name AS teacher_name,
           eval.name AS evaluator_name
    FROM submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN users u ON a.teacher_id = u.id
    LEFT JOIN users eval ON s.evaluated_by = eval.id
    WHERE s.student_id = :sid
";

$params = ['sid' => $studentId];

if (in_array(strtolower($filterStatus), ['pending', 'reviewed', 'completed'], true)) {
    $sql .= " AND LOWER(s.status) = :st";
    $params['st'] = strtolower($filterStatus);
}

$sql .= " ORDER BY s.submit_date DESC";

$submissions = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $submissions = $stmt->fetchAll();
} catch (PDOException $e) {
    // Handled
}

$pageTitle = 'My Submissions';
$isDashboard = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <?php echo renderFlashMessages(); ?>

            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h2 class="fw-bold text-dark mb-1">My Submissions History</h2>
                    <p class="text-muted small mb-0">Track all your submitted work, monitor evaluation progress, and read instructor feedback.</p>
                </div>
                <div class="mt-3 mt-md-0">
                    <a href="<?php echo BASE_URL; ?>/student/assignments.php" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-plus-circle me-1"></i>New Submission
                    </a>
                </div>
            </div>

            <!-- Filter Status Pills -->
            <ul class="nav nav-pills mb-4 border-bottom pb-3">
                <li class="nav-item">
                    <a class="nav-link <?php echo $filterStatus === 'all' ? 'active' : ''; ?>" href="<?php echo e($_SERVER['PHP_SELF']); ?>?status=all">
                        All Submissions
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $filterStatus === 'pending' ? 'active' : ''; ?>" href="<?php echo e($_SERVER['PHP_SELF']); ?>?status=pending">
                        <i class="bi bi-clock-history me-1"></i>Pending Review
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $filterStatus === 'reviewed' ? 'active' : ''; ?>" href="<?php echo e($_SERVER['PHP_SELF']); ?>?status=reviewed">
                        <i class="bi bi-eye me-1"></i>Reviewed
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $filterStatus === 'completed' ? 'active' : ''; ?>" href="<?php echo e($_SERVER['PHP_SELF']); ?>?status=completed">
                        <i class="bi bi-check-circle-fill me-1"></i>Completed
                    </a>
                </li>
            </ul>

            <!-- Submissions Table Card -->
            <div class="custom-card">
                <div class="custom-card-header">
                    <h5 class="mb-0 fw-bold">Records Found (<?php echo count($submissions); ?>)</h5>
                    <input type="text" class="form-control form-control-sm w-auto" placeholder="Filter in page..." data-table-filter="#submissionsTable">
                </div>

                <div class="table-responsive">
                    <table class="table table-custom align-middle" id="submissionsTable">
                        <thead>
                            <tr>
                                <th>Assignment &amp; Course</th>
                                <th>Submitted File</th>
                                <th>Submitted On</th>
                                <th>Status</th>
                                <th>Marks</th>
                                <th>Feedback</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($submissions)): ?>
                                <?php foreach ($submissions as $sub): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-light text-secondary border mb-1 small"><?php echo e($sub['subject']); ?></span>
                                            <h6 class="mb-0 fw-bold">
                                                <a href="<?php echo BASE_URL; ?>/student/assignment_details.php?id=<?php echo (int)$sub['assignment_id']; ?>" class="text-dark text-decoration-none">
                                                    <?php echo e($sub['assignment_title']); ?>
                                                </a>
                                            </h6>
                                            <small class="text-muted">Faculty: <?php echo e($sub['teacher_name']); ?></small>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column gap-1">
                                                <div class="d-flex gap-1">
                                                    <a href="<?php echo BASE_URL; ?>/download.php?type=submission&id=<?php echo (int)$sub['id']; ?>&action=view" target="_blank" class="btn btn-sm btn-outline-primary" title="View Document in Browser">
                                                        <i class="bi bi-eye me-1"></i>View
                                                    </a>
                                                    <a href="<?php echo BASE_URL; ?>/download.php?type=submission&id=<?php echo (int)$sub['id']; ?>&action=download" class="btn btn-sm btn-light border" title="Download File">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                </div>
                                                <small class="text-muted text-truncate" style="max-width: 140px;"><?php echo e(basename($sub['file'])); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold"><?php echo formatDate($sub['submit_date']); ?></div>
                                        </td>
                                        <td>
                                            <?php echo renderStatusBadge($sub['status']); ?>
                                        </td>
                                        <td>
                                            <?php if ($sub['marks'] !== null): ?>
                                                <span class="badge bg-success-subtle text-success fs-6 border border-success-subtle fw-bold">
                                                    <?php echo (float)$sub['marks']; ?> / <?php echo (int)$sub['max_marks']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted small fst-italic">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="max-width: 250px;">
                                            <?php if (!empty($sub['feedback'])): ?>
                                                <span class="small text-secondary" title="<?php echo e($sub['feedback']); ?>">
                                                    <i class="bi bi-chat-quote text-primary me-1"></i>
                                                    <?php echo e(mb_strimwidth($sub['feedback'], 0, 60, '...')); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted small">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?php echo BASE_URL; ?>/student/assignment_details.php?id=<?php echo (int)$sub['assignment_id']; ?>" class="btn btn-outline-primary" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="<?php echo BASE_URL; ?>/student/submit.php?id=<?php echo (int)$sub['assignment_id']; ?>" class="btn btn-outline-secondary" title="Resubmit / Edit File">
                                                    <i class="bi bi-pencil-square"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bi bi-cloud-slash fs-1 d-block mb-2"></i>
                                        No submissions recorded under this filter.
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


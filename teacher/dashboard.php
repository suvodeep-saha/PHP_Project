<?php
/**
 * Teacher Dashboard
 * Online Assignment Submission System
 * 
 * Demonstrates:
 * - Role-based authorization for instructors
 * - Cross-department and course-level submission visibility
 * - Fast evaluation pipeline for pending work
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAuth('teacher');

$teacherId = (int)$_SESSION['user_id'];
$pdo = getDBConnection();

// 1. Fetch Teacher Statistics (Both My Courses and Department-Wide)
$totalMyAssignments = 0;
$totalDeptAssignments = 0;
$totalMySubmissions = 0;
$totalDeptSubmissions = 0;
$pendingReviews = 0;
$completedReviews = 0;

try {
    $stmt1 = $pdo->prepare("SELECT COUNT(*) FROM assignments WHERE teacher_id = :tid");
    $stmt1->execute(['tid' => $teacherId]);
    $totalMyAssignments = (int)$stmt1->fetchColumn();

    $totalDeptAssignments = (int)$pdo->query("SELECT COUNT(*) FROM assignments")->fetchColumn();

    $stmt2 = $pdo->prepare("
        SELECT COUNT(s.id) 
        FROM submissions s 
        JOIN assignments a ON s.assignment_id = a.id 
        WHERE a.teacher_id = :tid
    ");
    $stmt2->execute(['tid' => $teacherId]);
    $totalMySubmissions = (int)$stmt2->fetchColumn();

    $totalDeptSubmissions = (int)$pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();

    // Department pending count
    $pendingReviews = (int)$pdo->query("SELECT COUNT(*) FROM submissions WHERE status = 'Pending'")->fetchColumn();

    $completedReviews = (int)$pdo->query("SELECT COUNT(*) FROM submissions WHERE status IN ('Reviewed', 'Completed')")->fetchColumn();
} catch (PDOException $e) {
    // Handled
}

// 2. Fetch Pending Submissions Needing Evaluation (Universal Department Queue)
$pendingSubmissions = [];
try {
    $stmtPending = $pdo->query("
        SELECT s.id, s.submit_date, s.file, s.status,
               u.name AS student_name, u.email AS student_email,
               a.id AS assignment_id, a.title AS assignment_title, a.max_marks, a.subject,
               a.teacher_id AS assignment_teacher_id,
               t.name AS teacher_name
        FROM submissions s
        JOIN assignments a ON s.assignment_id = a.id
        JOIN users u ON s.student_id = u.id
        JOIN users t ON a.teacher_id = t.id
        WHERE s.status = 'Pending'
        ORDER BY s.submit_date DESC
        LIMIT 10
    ");
    $pendingSubmissions = $stmtPending->fetchAll();
} catch (PDOException $e) {
    // Handled
}

// 3. Fetch Recent Assignments
$recentAssignments = [];
try {
    $stmtAssign = $pdo->query("
        SELECT a.*, 
               u.name AS teacher_name,
               (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) AS sub_count,
               (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id AND s.status = 'Pending') AS pending_count
        FROM assignments a
        JOIN users u ON a.teacher_id = u.id
        ORDER BY a.created_at DESC
        LIMIT 6
    ");
    $recentAssignments = $stmtAssign->fetchAll();
} catch (PDOException $e) {
    // Handled
}

$pageTitle = 'Teacher Dashboard';
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
                    <h2 class="fw-bold text-dark mb-1">Faculty Dashboard</h2>
                    <p class="text-muted small mb-0">
                        Welcome back, <strong><?php echo e($_SESSION['user_name']); ?></strong>! Oversee assignments, student submissions, and grading.
                    </p>
                </div>
                <div class="mt-3 mt-md-0 d-flex gap-2">
                    <a href="<?php echo BASE_URL; ?>/teacher/add_assignment.php" class="btn btn-primary shadow-sm">
                        <i class="bi bi-plus-circle me-1"></i>Create Assignment
                    </a>
                    <a href="<?php echo BASE_URL; ?>/teacher/submissions.php" class="btn btn-outline-secondary">
                        <i class="bi bi-clipboard-check me-1"></i>All Submissions
                    </a>
                </div>
            </div>

            <!-- Pending Review Notice Alert -->
            <?php if ($pendingReviews > 0): ?>
                <div class="alert alert-warning border-warning-subtle shadow-sm d-flex align-items-center justify-content-between mb-4">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-bell-fill fs-3 text-warning me-3"></i>
                        <div>
                            <h6 class="alert-heading fw-bold mb-1">Pending Student Submissions Awaiting Evaluation</h6>
                            <p class="mb-0 small text-secondary">
                                There are <strong><?php echo $pendingReviews; ?></strong> student submissions awaiting review in the faculty queue.
                            </p>
                        </div>
                    </div>
                    <a href="<?php echo BASE_URL; ?>/teacher/submissions.php?status=pending" class="btn btn-sm btn-warning text-dark fw-semibold shadow-sm">
                        Open Review Queue &rarr;
                    </a>
                </div>
            <?php endif; ?>

            <!-- Metric Cards -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small text-uppercase fw-semibold">Course Tasks</span>
                                <div class="stat-value text-primary"><?php echo $totalMyAssignments; ?></div>
                                <div class="small text-muted mt-1"><?php echo $totalDeptAssignments; ?> Department Tasks</div>
                            </div>
                            <div class="stat-icon bg-primary-subtle text-primary">
                                <i class="bi bi-collection-fill"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small text-uppercase fw-semibold">Total Submissions</span>
                                <div class="stat-value text-success"><?php echo $totalDeptSubmissions; ?></div>
                                <div class="small text-muted mt-1"><?php echo $totalMySubmissions; ?> for My Courses</div>
                            </div>
                            <div class="stat-icon bg-success-subtle text-success">
                                <i class="bi bi-inbox-fill"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small text-uppercase fw-semibold">Pending Grading</span>
                                <div class="stat-value text-warning"><?php echo $pendingReviews; ?></div>
                                <div class="small text-muted mt-1">Awaiting evaluation</div>
                            </div>
                            <div class="stat-icon bg-warning-subtle text-warning">
                                <i class="bi bi-hourglass-top"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small text-uppercase fw-semibold">Completed Reviews</span>
                                <div class="stat-value text-info"><?php echo $completedReviews; ?></div>
                                <div class="small text-muted mt-1">Evaluated &amp; Finalized</div>
                            </div>
                            <div class="stat-icon bg-info-subtle text-info">
                                <i class="bi bi-patch-check-fill"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Submissions Awaiting Evaluation -->
                <div class="col-lg-7">
                    <div class="custom-card">
                        <div class="custom-card-header">
                            <div class="d-flex align-items-center">
                                <h5 class="mb-0 fw-bold me-2"><i class="bi bi-clock-history me-2 text-warning"></i>Submissions Awaiting Grading</h5>
                                <span class="badge bg-warning text-dark"><?php echo count($pendingSubmissions); ?> Pending</span>
                            </div>
                            <a href="<?php echo BASE_URL; ?>/teacher/submissions.php?status=pending" class="btn btn-sm btn-link text-decoration-none">View All Queue &rarr;</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-custom align-middle">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Assignment &amp; Course</th>
                                        <th>Submitted</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($pendingSubmissions)): ?>
                                        <?php foreach ($pendingSubmissions as $p): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold text-dark"><?php echo e($p['student_name']); ?></div>
                                                    <small class="text-muted"><?php echo e($p['student_email']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-secondary border mb-1 small"><?php echo e($p['subject']); ?></span>
                                                    <div class="small fw-semibold text-dark"><?php echo e($p['assignment_title']); ?></div>
                                                    <small class="text-muted">
                                                        Faculty: <?php echo e($p['teacher_name']); ?>
                                                        <?php if ((int)$p['assignment_teacher_id'] === $teacherId): ?>
                                                            <span class="badge bg-success-subtle text-success ms-1">My Course</span>
                                                        <?php endif; ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <span class="small fw-semibold"><?php echo formatDate($p['submit_date'], 'd M, h:i A'); ?></span>
                                                </td>
                                                <td class="text-end">
                                                    <a href="<?php echo BASE_URL; ?>/teacher/evaluate.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-primary shadow-sm">
                                                        <i class="bi bi-pencil-square me-1"></i>Evaluate
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted">
                                                <i class="bi bi-check2-all text-success fs-3 d-block mb-1"></i>
                                                Great job! All submitted assignments have been evaluated.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Active Assignments Summary -->
                <div class="col-lg-5">
                    <div class="custom-card">
                        <div class="custom-card-header">
                            <h5 class="mb-0 fw-bold"><i class="bi bi-journals me-2 text-primary"></i>Department Assignments</h5>
                            <a href="<?php echo BASE_URL; ?>/teacher/assignments.php" class="btn btn-sm btn-link text-decoration-none">Manage All &rarr;</a>
                        </div>
                        <div class="custom-card-body p-0">
                            <?php if (!empty($recentAssignments)): ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($recentAssignments as $ra): ?>
                                        <li class="list-group-item p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="badge bg-light text-secondary border"><?php echo e($ra['subject']); ?></span>
                                                <?php echo renderDeadlineBadge($ra['deadline']); ?>
                                            </div>
                                            <h6 class="fw-bold mb-1">
                                                <a href="<?php echo BASE_URL; ?>/teacher/assignment_details.php?id=<?php echo (int)$ra['id']; ?>" class="text-dark text-decoration-none">
                                                    <?php echo e($ra['title']); ?>
                                                </a>
                                            </h6>
                                            <div class="d-flex justify-content-between align-items-center small text-muted mt-2">
                                                <span>
                                                    <i class="bi bi-inbox me-1"></i><strong><?php echo (int)$ra['sub_count']; ?></strong> Submissions
                                                    <?php if ((int)$ra['pending_count'] > 0): ?>
                                                        <span class="badge bg-warning text-dark ms-1"><?php echo (int)$ra['pending_count']; ?> Pending</span>
                                                    <?php endif; ?>
                                                </span>
                                                <div class="d-flex gap-1 align-items-center">
                                                    <small class="text-muted me-1"><?php echo ((int)$ra['teacher_id'] === $teacherId) ? 'By You' : e($ra['teacher_name']); ?></small>
                                                    <a href="<?php echo BASE_URL; ?>/teacher/assignment_details.php?id=<?php echo (int)$ra['id']; ?>" class="btn btn-sm btn-outline-primary py-0">
                                                        View
                                                    </a>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="text-center p-4 text-muted">
                                    <i class="bi bi-plus-circle fs-2 d-block mb-1 text-primary"></i>
                                    No assignments have been published yet.
                                    <div class="mt-2">
                                        <a href="<?php echo BASE_URL; ?>/teacher/add_assignment.php" class="btn btn-sm btn-primary">Create Assignment</a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

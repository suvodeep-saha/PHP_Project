<?php
/**
 * Student Dashboard
 * Online Assignment Submission System
 * 
 * Demonstrates:
 * - Role-based authorization guard (requireAuth('student'))
 * - Aggregate SQL queries (COUNT, AVG) via PDO
 * - Dynamic data visualization cards
 * - Real-time deadline tracking
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Enforce student role
requireAuth('student');

$studentId = (int)$_SESSION['user_id'];
$pdo = getDBConnection();

// 1. Fetch Student Statistics
$totalAssignments = 0;
$mySubmissions = 0;
$pendingReviews = 0;
$completedGraded = 0;
$avgMarks = null;

try {
    $totalAssignments = (int)$pdo->query("SELECT COUNT(*) FROM assignments")->fetchColumn();

    $stmtMySubs = $pdo->prepare("SELECT COUNT(*) FROM submissions WHERE student_id = :sid");
    $stmtMySubs->execute(['sid' => $studentId]);
    $mySubmissions = (int)$stmtMySubs->fetchColumn();

    $stmtPending = $pdo->prepare("SELECT COUNT(*) FROM submissions WHERE student_id = :sid AND status = 'Pending'");
    $stmtPending->execute(['sid' => $studentId]);
    $pendingReviews = (int)$stmtPending->fetchColumn();

    $stmtCompleted = $pdo->prepare("SELECT COUNT(*) FROM submissions WHERE student_id = :sid AND status IN ('Reviewed', 'Completed')");
    $stmtCompleted->execute(['sid' => $studentId]);
    $completedGraded = (int)$stmtCompleted->fetchColumn();

    $stmtAvg = $pdo->prepare("SELECT ROUND(AVG(marks), 1) FROM submissions WHERE student_id = :sid AND marks IS NOT NULL");
    $stmtAvg->execute(['sid' => $studentId]);
    $avgMarks = $stmtAvg->fetchColumn();
} catch (PDOException $e) {
    // Handled
}

// 2. Fetch Upcoming Assignments
$upcomingAssignments = [];
try {
    $stmtUpcoming = $pdo->prepare("
        SELECT a.id, a.title, a.subject, a.deadline, a.max_marks, u.name AS teacher_name,
               (SELECT s.id FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = :sid LIMIT 1) AS submission_id,
               (SELECT s.status FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = :sid LIMIT 1) AS submission_status
        FROM assignments a
        JOIN users u ON a.teacher_id = u.id
        WHERE a.deadline >= NOW()
        ORDER BY a.deadline ASC
        LIMIT 5
    ");
    $stmtUpcoming->execute(['sid' => $studentId]);
    $upcomingAssignments = $stmtUpcoming->fetchAll();
} catch (PDOException $e) {
    // Handled
}

// 3. Fetch Recent Evaluated Submissions
$recentEvaluations = [];
try {
    $stmtRecent = $pdo->prepare("
        SELECT s.id, s.marks, s.feedback, s.status, s.evaluated_at, a.title AS assignment_title, a.subject, a.max_marks, u.name AS teacher_name
        FROM submissions s
        JOIN assignments a ON s.assignment_id = a.id
        LEFT JOIN users u ON s.evaluated_by = u.id
        WHERE s.student_id = :sid AND s.status IN ('Reviewed', 'Completed')
        ORDER BY s.updated_at DESC
        LIMIT 5
    ");
    $stmtRecent->execute(['sid' => $studentId]);
    $recentEvaluations = $stmtRecent->fetchAll();
} catch (PDOException $e) {
    // Handled
}

$pageTitle = 'Student Dashboard';
$isDashboard = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container-fluid">
            <!-- Flash Alerts -->
            <?php echo renderFlashMessages(); ?>

            <!-- Dashboard Welcome Banner -->
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h2 class="fw-bold text-dark mb-1">Student Dashboard</h2>
                    <p class="text-muted small mb-0">
                        Welcome back, <strong><?php echo e($_SESSION['user_name']); ?></strong>! Here is your academic progress summary.
                    </p>
                </div>
                <div class="mt-3 mt-md-0">
                    <a href="<?php echo BASE_URL; ?>/student/assignments.php" class="btn btn-primary shadow-sm">
                        <i class="bi bi-journal-plus me-1"></i>Explore Assignments
                    </a>
                </div>
            </div>

            <!-- Metric Cards -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small text-uppercase fw-semibold">Available Tasks</span>
                                <div class="stat-value text-primary"><?php echo $totalAssignments; ?></div>
                            </div>
                            <div class="stat-icon bg-primary-subtle text-primary">
                                <i class="bi bi-journal-bookmark-fill"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small text-uppercase fw-semibold">My Submissions</span>
                                <div class="stat-value text-success"><?php echo $mySubmissions; ?></div>
                            </div>
                            <div class="stat-icon bg-success-subtle text-success">
                                <i class="bi bi-cloud-check-fill"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small text-uppercase fw-semibold">Awaiting Review</span>
                                <div class="stat-value text-warning"><?php echo $pendingReviews; ?></div>
                            </div>
                            <div class="stat-icon bg-warning-subtle text-warning">
                                <i class="bi bi-hourglass-split"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small text-uppercase fw-semibold">Average Score</span>
                                <div class="stat-value text-info"><?php echo $avgMarks !== null ? $avgMarks . '%' : 'N/A'; ?></div>
                            </div>
                            <div class="stat-icon bg-info-subtle text-info">
                                <i class="bi bi-trophy-fill"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Two-Column Grid: Upcoming Deadlines & Recent Evaluations -->
            <div class="row g-4">
                <!-- Upcoming Deadlines -->
                <div class="col-lg-7">
                    <div class="custom-card">
                        <div class="custom-card-header">
                            <h5 class="mb-0 fw-bold"><i class="bi bi-alarm me-2 text-primary"></i>Upcoming Assignment Deadlines</h5>
                            <a href="<?php echo BASE_URL; ?>/student/assignments.php" class="btn btn-sm btn-link text-decoration-none">View All &rarr;</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-custom align-middle">
                                <thead>
                                    <tr>
                                        <th>Subject &amp; Title</th>
                                        <th>Deadline</th>
                                        <th>Status</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($upcomingAssignments)): ?>
                                        <?php foreach ($upcomingAssignments as $assign): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-light text-secondary border mb-1 small"><?php echo e($assign['subject']); ?></span>
                                                    <h6 class="mb-0 fw-semibold">
                                                        <a href="<?php echo BASE_URL; ?>/student/assignment_details.php?id=<?php echo (int)$assign['id']; ?>" class="text-dark text-decoration-none">
                                                            <?php echo e($assign['title']); ?>
                                                        </a>
                                                    </h6>
                                                    <small class="text-muted">Faculty: <?php echo e($assign['teacher_name']); ?></small>
                                                </td>
                                                <td>
                                                    <div class="small fw-semibold"><?php echo formatDate($assign['deadline']); ?></div>
                                                    <?php echo renderDeadlineBadge($assign['deadline']); ?>
                                                </td>
                                                <td>
                                                    <?php if ($assign['submission_id']): ?>
                                                        <?php echo renderStatusBadge($assign['submission_status']); ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary-subtle text-secondary border">Not Submitted</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <?php if ($assign['submission_id']): ?>
                                                        <a href="<?php echo BASE_URL; ?>/student/assignment_details.php?id=<?php echo (int)$assign['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                            Details
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="<?php echo BASE_URL; ?>/student/submit.php?id=<?php echo (int)$assign['id']; ?>" class="btn btn-sm btn-primary">
                                                            Submit Work
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">
                                                <i class="bi bi-check2-circle text-success fs-3 d-block mb-1"></i>
                                                You have no pending deadlines right now!
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Marks & Feedback -->
                <div class="col-lg-5">
                    <div class="custom-card">
                        <div class="custom-card-header">
                            <h5 class="mb-0 fw-bold"><i class="bi bi-star-fill me-2 text-warning"></i>Recent Evaluations</h5>
                            <a href="<?php echo BASE_URL; ?>/student/submissions.php" class="btn btn-sm btn-link text-decoration-none">All Submissions &rarr;</a>
                        </div>
                        <div class="custom-card-body p-0">
                            <?php if (!empty($recentEvaluations)): ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($recentEvaluations as $rev): ?>
                                        <li class="list-group-item p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="badge bg-light text-secondary border"><?php echo e($rev['subject']); ?></span>
                                                <span class="badge bg-success-subtle text-success fs-6 fw-bold border border-success-subtle">
                                                    <?php echo (float)$rev['marks']; ?> / <?php echo (int)$rev['max_marks']; ?>
                                                </span>
                                            </div>
                                            <h6 class="fw-semibold text-dark mb-1"><?php echo e($rev['assignment_title']); ?></h6>
                                            <?php if (!empty($rev['feedback'])): ?>
                                                <div class="p-2 bg-light rounded-2 border small text-secondary fst-italic mb-2">
                                                    <i class="bi bi-chat-left-quote me-1 text-primary"></i>
                                                    "<?php echo e($rev['feedback']); ?>"
                                                </div>
                                            <?php endif; ?>
                                            <div class="d-flex justify-content-between align-items-center text-muted small">
                                                <span>Evaluated by: <?php echo e($rev['teacher_name'] ?? 'Instructor'); ?></span>
                                                <?php echo renderStatusBadge($rev['status']); ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="text-center text-muted p-4">
                                    <i class="bi bi-card-checklist fs-2 d-block mb-1 text-muted"></i>
                                    No evaluations recorded yet. As instructors review your submissions, marks and remarks will appear here.
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


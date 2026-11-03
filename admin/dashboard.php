<?php
/**
 * Administrator Dashboard
 * Online Assignment Submission System
 * 
 * Demonstrates:
 * - Role-based authorization for system administrators
 * - Cross-table system statistics and health metrics
 * - System monitoring & administrative actions
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAuth('admin');

$pdo = getDBConnection();

// Fetch System Metrics
$totalUsers = 0;
$totalStudents = 0;
$totalTeachers = 0;
$totalAssignments = 0;
$totalSubmissions = 0;
$pendingSubmissions = 0;
$completedSubmissions = 0;

try {
    $totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    $totalTeachers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
    $totalAssignments = (int)$pdo->query("SELECT COUNT(*) FROM assignments")->fetchColumn();
    $totalSubmissions = (int)$pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();
    $pendingSubmissions = (int)$pdo->query("SELECT COUNT(*) FROM submissions WHERE status = 'Pending'")->fetchColumn();
    $completedSubmissions = (int)$pdo->query("SELECT COUNT(*) FROM submissions WHERE status IN ('Reviewed', 'Completed')")->fetchColumn();
} catch (PDOException $e) {
    // Handled
}

// Fetch 5 Recent User Registrations
$recentUsers = [];
try {
    $recentUsers = $pdo->query("SELECT id, name, email, role, status, created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();
} catch (PDOException $e) {
    // Handled
}

// Fetch 5 Recent Submissions
$recentSubmissions = [];
try {
    $recentSubmissions = $pdo->query("
        SELECT s.id, s.submit_date, s.status, s.marks,
               u.name AS student_name,
               a.title AS assignment_title, a.max_marks,
               t.name AS teacher_name
        FROM submissions s
        JOIN users u ON s.student_id = u.id
        JOIN assignments a ON s.assignment_id = a.id
        JOIN users t ON a.teacher_id = t.id
        ORDER BY s.submit_date DESC
        LIMIT 5
    ")->fetchAll();
} catch (PDOException $e) {
    // Handled
}

$pageTitle = 'Admin Dashboard';
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
                    <h2 class="fw-bold text-dark mb-1">Administrative Control Center</h2>
                    <p class="text-muted small mb-0">System-wide monitoring, user governance, and academic workflow supervision.</p>
                </div>
                <div class="mt-3 mt-md-0 d-flex gap-2">
                    <a href="<?php echo BASE_URL; ?>/admin/users.php" class="btn btn-primary shadow-sm">
                        <i class="bi bi-person-plus me-1"></i>Manage Users
                    </a>
                    <a href="<?php echo BASE_URL; ?>/admin/assignments.php" class="btn btn-outline-secondary">
                        <i class="bi bi-journal-bookmark me-1"></i>All Assignments
                    </a>
                </div>
            </div>

            <!-- Top Level Metrics -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small text-uppercase fw-semibold">Total Users</span>
                                <div class="stat-value text-primary"><?php echo $totalUsers; ?></div>
                                <div class="small text-muted mt-1"><?php echo $totalStudents; ?> Students &bull; <?php echo $totalTeachers; ?> Faculty</div>
                            </div>
                            <div class="stat-icon bg-primary-subtle text-primary">
                                <i class="bi bi-people-fill"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small text-uppercase fw-semibold">Assignments</span>
                                <div class="stat-value text-success"><?php echo $totalAssignments; ?></div>
                                <div class="small text-muted mt-1">Across all departments</div>
                            </div>
                            <div class="stat-icon bg-success-subtle text-success">
                                <i class="bi bi-journals"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small text-uppercase fw-semibold">Submissions</span>
                                <div class="stat-value text-info"><?php echo $totalSubmissions; ?></div>
                                <div class="small text-muted mt-1"><?php echo $completedSubmissions; ?> Evaluated</div>
                            </div>
                            <div class="stat-icon bg-info-subtle text-info">
                                <i class="bi bi-cloud-check-fill"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small text-uppercase fw-semibold">Pending Reviews</span>
                                <div class="stat-value text-warning"><?php echo $pendingSubmissions; ?></div>
                                <div class="small text-muted mt-1">Awaiting evaluation</div>
                            </div>
                            <div class="stat-icon bg-warning-subtle text-warning">
                                <i class="bi bi-hourglass-split"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Two-Column Tables -->
            <div class="row g-4">
                <!-- Recent Registrations -->
                <div class="col-lg-6">
                    <div class="custom-card">
                        <div class="custom-card-header">
                            <h5 class="mb-0 fw-bold"><i class="bi bi-person-check me-2 text-primary"></i>Recent User Accounts</h5>
                            <a href="<?php echo BASE_URL; ?>/admin/users.php" class="btn btn-sm btn-link text-decoration-none">View All &rarr;</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-custom align-middle">
                                <thead>
                                    <tr>
                                        <th>Name &amp; Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th class="text-end">Registered</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recentUsers)): ?>
                                        <?php foreach ($recentUsers as $ru): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold text-dark"><?php echo e($ru['name']); ?></div>
                                                    <small class="text-muted"><?php echo e($ru['email']); ?></small>
                                                </td>
                                                <td>
                                                    <?php
                                                        $badgeColor = match ($ru['role']) {
                                                            'admin'   => 'bg-danger',
                                                            'teacher' => 'bg-primary',
                                                            default   => 'bg-success',
                                                        };
                                                    ?>
                                                    <span class="badge <?php echo $badgeColor; ?> text-uppercase"><?php echo e($ru['role']); ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $ru['status'] === 'active' ? 'success-subtle text-success border border-success-subtle' : 'danger-subtle text-danger border border-danger-subtle'; ?>">
                                                        <?php echo e($ru['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <span class="small text-muted"><?php echo formatDate($ru['created_at'], 'd M Y'); ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Submissions -->
                <div class="col-lg-6">
                    <div class="custom-card">
                        <div class="custom-card-header">
                            <h5 class="mb-0 fw-bold"><i class="bi bi-clipboard-data me-2 text-info"></i>Recent Submissions</h5>
                            <a href="<?php echo BASE_URL; ?>/admin/submissions.php" class="btn btn-sm btn-link text-decoration-none">View All &rarr;</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-custom align-middle">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Assignment</th>
                                        <th>Status</th>
                                        <th class="text-end">Submitted</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recentSubmissions)): ?>
                                        <?php foreach ($recentSubmissions as $rs): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold text-dark"><?php echo e($rs['student_name']); ?></div>
                                                </td>
                                                <td>
                                                    <div class="small fw-semibold"><?php echo e($rs['assignment_title']); ?></div>
                                                    <small class="text-muted">Faculty: <?php echo e($rs['teacher_name']); ?></small>
                                                </td>
                                                <td>
                                                    <?php echo renderStatusBadge($rs['status']); ?>
                                                </td>
                                                <td class="text-end">
                                                    <span class="small text-muted"><?php echo formatDate($rs['submit_date'], 'd M, h:i A'); ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


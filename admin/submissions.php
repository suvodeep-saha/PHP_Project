<?php
/**
 * Administrator Submissions Monitoring
 * Online Assignment Submission System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAuth('admin');

$pdo = getDBConnection();

$selectedAssignmentId = (int)($_GET['assignment_id'] ?? 0);
$filterStatus = trim($_GET['status'] ?? 'all');
$search = trim($_GET['search'] ?? '');

// Fetch assignments list for filter dropdown
$allAssignments = [];
try {
    $allAssignments = $pdo->query("SELECT id, title, subject FROM assignments ORDER BY title ASC")->fetchAll();
} catch (PDOException $e) {
    // Handled
}

// Build query
$sql = "
    SELECT s.*, 
           u.name AS student_name, 
           u.email AS student_email,
           a.title AS assignment_title, 
           a.subject, 
           a.max_marks,
           t.name AS teacher_name,
           eval.name AS evaluator_name
    FROM submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN users u ON s.student_id = u.id
    JOIN users t ON a.teacher_id = t.id
    LEFT JOIN users eval ON s.evaluated_by = eval.id
    WHERE 1=1
";
$params = [];

if ($selectedAssignmentId > 0) {
    $sql .= " AND a.id = :aid";
    $params['aid'] = $selectedAssignmentId;
}

if (in_array(strtolower($filterStatus), ['pending', 'reviewed', 'completed'], true)) {
    $sql .= " AND LOWER(s.status) = :st";
    $params['st'] = strtolower($filterStatus);
}

if (!empty($search)) {
    $sql .= " AND (u.name LIKE :search OR u.email LIKE :search OR a.title LIKE :search OR t.name LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

$sql .= " ORDER BY (s.status = 'Pending') DESC, s.submit_date DESC";

$submissions = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $submissions = $stmt->fetchAll();
} catch (PDOException $e) {
    // Handled
}

$pageTitle = 'All Submissions';
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
                    <h2 class="fw-bold text-dark mb-1">Global Submissions Registry</h2>
                    <p class="text-muted small mb-0">Oversee student submissions, track turnaround times, and manage workflow states.</p>
                </div>
            </div>

            <!-- Filters Bar -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-body p-3">
                    <form method="GET" action="<?php echo e($_SERVER['PHP_SELF']); ?>" class="row g-2 align-items-center">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" name="search" class="form-control border-start-0" placeholder="Search student, assignment, or faculty..." value="<?php echo e($search); ?>" data-table-filter="#adminSubmissionsTable">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <select name="assignment_id" class="form-select" onchange="this.form.submit()">
                                <option value="0">All Assignments &amp; Courses</option>
                                <?php foreach ($allAssignments as $aa): ?>
                                    <option value="<?php echo (int)$aa['id']; ?>" <?php echo $selectedAssignmentId === (int)$aa['id'] ? 'selected' : ''; ?>>
                                        [<?php echo e($aa['subject']); ?>] <?php echo e($aa['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="reviewed" <?php echo $filterStatus === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                <option value="completed" <?php echo $filterStatus === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>

                        <div class="col-md-2 d-grid">
                            <a href="<?php echo e($_SERVER['PHP_SELF']); ?>" class="btn btn-outline-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Table Card -->
            <div class="custom-card">
                <div class="custom-card-header">
                    <h5 class="mb-0 fw-bold">Submissions Found (<?php echo count($submissions); ?>)</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-custom align-middle" id="adminSubmissionsTable">
                        <thead>
                            <tr>
                                <th>Student Details</th>
                                <th>Assignment &amp; Instructor</th>
                                <th>Submitted File</th>
                                <th>Submitted On</th>
                                <th>Status</th>
                                <th>Marks</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($submissions)): ?>
                                <?php foreach ($submissions as $s): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo e($s['student_name']); ?></div>
                                            <small class="text-muted"><?php echo e($s['student_email']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-secondary border mb-1 small"><?php echo e($s['subject']); ?></span>
                                            <div class="fw-semibold text-dark small"><?php echo e($s['assignment_title']); ?></div>
                                            <small class="text-muted">Faculty: <?php echo e($s['teacher_name']); ?></small>
                                        </td>
                                        <td>
                                            <a href="<?php echo BASE_URL; ?>/download.php?type=submission&id=<?php echo (int)$s['id']; ?>" class="btn btn-sm btn-light border text-primary">
                                                <i class="bi bi-download me-1"></i><?php echo e(basename($s['file'])); ?>
                                            </a>
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
                                                    <?php echo (float)$s['marks']; ?> / <?php echo (int)$s['max_marks']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted small fst-italic">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?php echo BASE_URL; ?>/admin/update_status.php?id=<?php echo (int)$s['id']; ?>" class="btn btn-sm btn-outline-primary" title="Update Status or Marks">
                                                <i class="bi bi-sliders"></i> Manage
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                        No submissions recorded matching these parameters.
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


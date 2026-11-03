<?php
/**
 * Teacher Student Submissions Directory & Review Queue
 * Online Assignment Submission System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAuth('teacher');

$teacherId = (int)$_SESSION['user_id'];
$pdo = getDBConnection();

$scope = trim($_GET['scope'] ?? 'all'); // 'all' (department-wide) or 'my' (only current teacher)
$selectedAssignmentId = (int)($_GET['assignment_id'] ?? 0);
$filterStatus = trim($_GET['status'] ?? 'all');
$search = trim($_GET['search'] ?? '');

// Fetch all assignments for filter dropdown
$allAssignments = [];
try {
    $stmtAllAssign = $pdo->query("
        SELECT a.id, a.title, a.subject, a.teacher_id, u.name AS teacher_name 
        FROM assignments a 
        JOIN users u ON a.teacher_id = u.id 
        ORDER BY a.title ASC
    ");
    $allAssignments = $stmtAllAssign->fetchAll();
} catch (PDOException $e) {
    // Handled
}

// Dynamic Submissions query
$sql = "
    SELECT s.*, 
           u.name AS student_name, 
           u.email AS student_email,
           a.title AS assignment_title, 
           a.subject, 
           a.max_marks,
           a.teacher_id AS assignment_teacher_id,
           t.name AS teacher_name
    FROM submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN users u ON s.student_id = u.id
    JOIN users t ON a.teacher_id = t.id
    WHERE 1=1
";

$params = [];

// Apply Scope Filter
if ($scope === 'my') {
    $sql .= " AND a.teacher_id = :my_tid";
    $params['my_tid'] = $teacherId;
}

if ($selectedAssignmentId > 0) {
    $sql .= " AND a.id = :aid";
    $params['aid'] = $selectedAssignmentId;
}

if (in_array(strtolower($filterStatus), ['pending', 'reviewed', 'completed'], true)) {
    $sql .= " AND LOWER(s.status) = :st";
    $params['st'] = strtolower($filterStatus);
}

if (!empty($search)) {
    $sql .= " AND (u.name LIKE :search OR u.email LIKE :search OR a.title LIKE :search OR a.subject LIKE :search OR t.name LIKE :search)";
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

$pageTitle = 'Student Submissions';
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
                    <h2 class="fw-bold text-dark mb-1">Student Submissions Queue</h2>
                    <p class="text-muted small mb-0">Evaluate student work, download solution documents, and record grades and remarks.</p>
                </div>
                <div class="mt-3 mt-md-0 d-flex gap-2">
                    <div class="btn-group shadow-sm">
                        <a href="<?php echo e($_SERVER['PHP_SELF']); ?>?scope=all&status=<?php echo e($filterStatus); ?>&assignment_id=<?php echo $selectedAssignmentId; ?>" 
                           class="btn btn-sm <?php echo $scope === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="bi bi-collection me-1"></i>All Department Submissions
                        </a>
                        <a href="<?php echo e($_SERVER['PHP_SELF']); ?>?scope=my&status=<?php echo e($filterStatus); ?>&assignment_id=<?php echo $selectedAssignmentId; ?>" 
                           class="btn btn-sm <?php echo $scope === 'my' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="bi bi-person-check me-1"></i>My Courses Only
                        </a>
                    </div>
                </div>
            </div>

            <!-- Filters Bar -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-body p-3">
                    <form method="GET" action="<?php echo e($_SERVER['PHP_SELF']); ?>" class="row g-2 align-items-center">
                        <input type="hidden" name="scope" value="<?php echo e($scope); ?>">

                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" name="search" class="form-control border-start-0" placeholder="Search student name, email, task..." value="<?php echo e($search); ?>" data-table-filter="#teacherSubmissionsTable">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <select name="assignment_id" class="form-select" onchange="this.form.submit()">
                                <option value="0">All Assignments &amp; Courses</option>
                                <?php foreach ($allAssignments as $ma): ?>
                                    <?php $isMine = ((int)$ma['teacher_id'] === $teacherId); ?>
                                    <option value="<?php echo (int)$ma['id']; ?>" <?php echo $selectedAssignmentId === (int)$ma['id'] ? 'selected' : ''; ?>>
                                        <?php echo $isMine ? '[My Course] ' : '[' . e($ma['teacher_name']) . '] '; ?>
                                        <?php echo e($ma['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending Evaluation</option>
                                <option value="reviewed" <?php echo $filterStatus === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                <option value="completed" <?php echo $filterStatus === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>

                        <div class="col-md-2 d-grid">
                            <a href="<?php echo e($_SERVER['PHP_SELF']); ?>?scope=<?php echo e($scope); ?>" class="btn btn-outline-secondary">Reset Filters</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Submissions Table Card -->
            <div class="custom-card">
                <div class="custom-card-header">
                    <div class="d-flex align-items-center">
                        <h5 class="mb-0 fw-bold me-2">Submissions Found (<?php echo count($submissions); ?>)</h5>
                        <?php if ($scope === 'all'): ?>
                            <span class="badge bg-info-subtle text-info border border-info-subtle small">Showing All Department Tasks</span>
                        <?php else: ?>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle small">Showing My Courses</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-custom align-middle" id="teacherSubmissionsTable">
                        <thead>
                            <tr>
                                <th>Student Details</th>
                                <th>Assignment &amp; Course</th>
                                <th>Submitted Solution</th>
                                <th>Submit Date</th>
                                <th>Status</th>
                                <th>Marks</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($submissions)): ?>
                                <?php foreach ($submissions as $s): ?>
                                    <tr class="<?php echo $s['status'] === 'Pending' ? 'table-warning-subtle' : ''; ?>">
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo e($s['student_name']); ?></div>
                                            <small class="text-muted"><?php echo e($s['student_email']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-secondary border mb-1 small"><?php echo e($s['subject']); ?></span>
                                            <div class="fw-semibold small text-dark">
                                                <a href="<?php echo BASE_URL; ?>/teacher/assignment_details.php?id=<?php echo (int)$s['assignment_id']; ?>" class="text-dark text-decoration-none">
                                                    <?php echo e($s['assignment_title']); ?>
                                                </a>
                                            </div>
                                            <small class="text-muted">
                                                Faculty: <strong><?php echo e($s['teacher_name']); ?></strong>
                                                <?php if ((int)$s['assignment_teacher_id'] === $teacherId): ?>
                                                    <span class="badge bg-success-subtle text-success ms-1">You</span>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <a href="<?php echo BASE_URL; ?>/download.php?type=submission&id=<?php echo (int)$s['id']; ?>" class="btn btn-sm btn-light border text-primary" title="Download Student Document">
                                                <i class="bi bi-file-earmark-arrow-down me-1"></i><?php echo e(basename($s['file'])); ?>
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
                                                <span class="text-muted small fst-italic">Unassigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?php echo BASE_URL; ?>/teacher/evaluate.php?id=<?php echo (int)$s['id']; ?>" class="btn btn-sm btn-primary shadow-sm">
                                                <i class="bi bi-pencil-square me-1"></i><?php echo $s['status'] === 'Pending' ? 'Evaluate' : 'Edit Grade'; ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                        No student submissions found matching the selected filters.
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

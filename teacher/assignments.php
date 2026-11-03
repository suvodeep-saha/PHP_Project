<?php
/**
 * Teacher Assignments Management (CRUD Read)
 * Online Assignment Submission System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAuth('teacher');

$teacherId = (int)$_SESSION['user_id'];
$pdo = getDBConnection();

$scope = trim($_GET['scope'] ?? 'all'); // 'all' or 'my'
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT a.*, 
           u.name AS teacher_name,
           u.email AS teacher_email,
           (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) AS total_submissions,
           (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id AND s.status = 'Pending') AS pending_submissions,
           (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id AND s.status IN ('Reviewed', 'Completed')) AS graded_submissions
    FROM assignments a
    JOIN users u ON a.teacher_id = u.id
    WHERE 1=1
";

$params = [];

if ($scope === 'my') {
    $sql .= " AND a.teacher_id = :tid";
    $params['tid'] = $teacherId;
}

if (!empty($search)) {
    $sql .= " AND (a.title LIKE :search OR a.subject LIKE :search OR u.name LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

$sql .= " ORDER BY a.created_at DESC";

$assignments = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $assignments = $stmt->fetchAll();
} catch (PDOException $e) {
    // Handled
}

$pageTitle = 'Manage Assignments';
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
                    <h2 class="fw-bold text-dark mb-1">Course Assignments Catalog</h2>
                    <p class="text-muted small mb-0">Publish new tasks, inspect student submissions, and manage course instructions.</p>
                </div>
                <div class="mt-3 mt-md-0 d-flex gap-2">
                    <div class="btn-group shadow-sm">
                        <a href="<?php echo e($_SERVER['PHP_SELF']); ?>?scope=all" class="btn btn-sm <?php echo $scope === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="bi bi-collection me-1"></i>All Department Assignments
                        </a>
                        <a href="<?php echo e($_SERVER['PHP_SELF']); ?>?scope=my" class="btn btn-sm <?php echo $scope === 'my' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="bi bi-person-check me-1"></i>My Courses Only
                        </a>
                    </div>
                    <a href="<?php echo BASE_URL; ?>/teacher/add_assignment.php" class="btn btn-primary btn-sm shadow-sm">
                        <i class="bi bi-plus-circle me-1"></i>Create Assignment
                    </a>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-body p-3">
                    <form method="GET" action="<?php echo e($_SERVER['PHP_SELF']); ?>" class="row g-2 align-items-center">
                        <input type="hidden" name="scope" value="<?php echo e($scope); ?>">
                        <div class="col-md-9">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" name="search" class="form-control border-start-0" placeholder="Search assignments by title, subject, or faculty author..." value="<?php echo e($search); ?>" data-table-filter="#teacherAssignmentsTable">
                            </div>
                        </div>
                        <div class="col-md-3 d-grid">
                            <a href="<?php echo e($_SERVER['PHP_SELF']); ?>?scope=<?php echo e($scope); ?>" class="btn btn-outline-secondary">Reset Search</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Assignments Table -->
            <div class="custom-card">
                <div class="custom-card-header">
                    <div class="d-flex align-items-center">
                        <h5 class="mb-0 fw-bold me-2">Assignments List (<?php echo count($assignments); ?>)</h5>
                        <?php if ($scope === 'all'): ?>
                            <span class="badge bg-info-subtle text-info border border-info-subtle small">All Department Courses</span>
                        <?php else: ?>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle small">My Authored Courses</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-custom align-middle" id="teacherAssignmentsTable">
                        <thead>
                            <tr>
                                <th>Title &amp; Course</th>
                                <th>Instructor</th>
                                <th>Deadline</th>
                                <th>Attachment</th>
                                <th>Submissions Progress</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($assignments)): ?>
                                <?php foreach ($assignments as $a): ?>
                                    <?php $isMine = ((int)$a['teacher_id'] === $teacherId); ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle mb-1">
                                                <?php echo e($a['subject']); ?>
                                            </span>
                                            <h6 class="mb-1 fw-bold text-dark">
                                                <a href="<?php echo BASE_URL; ?>/teacher/assignment_details.php?id=<?php echo (int)$a['id']; ?>" class="text-dark text-decoration-none">
                                                    <?php echo e($a['title']); ?>
                                                </a>
                                            </h6>
                                            <small class="text-muted">Max: <?php echo (int)$a['max_marks']; ?> points</small>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark">
                                                <?php echo e($a['teacher_name']); ?>
                                                <?php if ($isMine): ?>
                                                    <span class="badge bg-success-subtle text-success ms-1">You</span>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted"><?php echo e($a['teacher_email']); ?></small>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold"><?php echo formatDate($a['deadline']); ?></div>
                                            <?php echo renderDeadlineBadge($a['deadline']); ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($a['file'])): ?>
                                                <a href="<?php echo BASE_URL; ?>/download.php?type=assignment&id=<?php echo (int)$a['id']; ?>" class="btn btn-sm btn-light border text-primary" title="Download Question Paper">
                                                    <i class="bi bi-paperclip me-1"></i><?php echo e(basename($a['file'])); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small">— None —</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <a href="<?php echo BASE_URL; ?>/teacher/submissions.php?assignment_id=<?php echo (int)$a['id']; ?>" class="badge bg-primary fs-6 text-decoration-none">
                                                    <?php echo (int)$a['total_submissions']; ?> Submissions
                                                </a>
                                                <?php if ((int)$a['pending_submissions'] > 0): ?>
                                                    <span class="badge bg-warning text-dark">
                                                        <?php echo (int)$a['pending_submissions']; ?> Pending
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?php echo BASE_URL; ?>/teacher/submissions.php?assignment_id=<?php echo (int)$a['id']; ?>" class="btn btn-outline-info" title="View Student Submissions">
                                                    <i class="bi bi-inbox me-1"></i>Submissions
                                                </a>
                                                <a href="<?php echo BASE_URL; ?>/teacher/assignment_details.php?id=<?php echo (int)$a['id']; ?>" class="btn btn-outline-primary" title="Assignment Details">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <?php if ($isMine): ?>
                                                    <a href="<?php echo BASE_URL; ?>/teacher/edit_assignment.php?id=<?php echo (int)$a['id']; ?>" class="btn btn-outline-secondary" title="Edit Assignment">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="<?php echo BASE_URL; ?>/teacher/delete_assignment.php?id=<?php echo (int)$a['id']; ?>" class="btn btn-outline-danger btn-confirm-delete" data-item-name="assignment '<?php echo e($a['title']); ?>'" title="Delete Assignment">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                        No assignments found matching the filter criteria.
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

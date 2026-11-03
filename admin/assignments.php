<?php
/**
 * Administrator All Assignments Overview
 * Online Assignment Submission System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAuth('admin');

$pdo = getDBConnection();

$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT a.*, 
           u.name AS teacher_name, 
           u.email AS teacher_email,
           (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) AS total_submissions,
           (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id AND s.status = 'Pending') AS pending_submissions
    FROM assignments a
    JOIN users u ON a.teacher_id = u.id
    WHERE 1=1
";
$params = [];

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

$pageTitle = 'All Assignments';
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
                    <h2 class="fw-bold text-dark mb-1">System-Wide Assignments Catalog</h2>
                    <p class="text-muted small mb-0">Audit all course assignments published by faculty across departments.</p>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-body p-3">
                    <form method="GET" action="<?php echo e($_SERVER['PHP_SELF']); ?>" class="row g-2 align-items-center">
                        <div class="col-md-9">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" name="search" class="form-control border-start-0" placeholder="Search by title, subject, or instructor name..." value="<?php echo e($search); ?>" data-table-filter="#adminAssignmentsTable">
                            </div>
                        </div>
                        <div class="col-md-3 d-grid">
                            <a href="<?php echo e($_SERVER['PHP_SELF']); ?>" class="btn btn-outline-secondary">Reset Search</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Assignments Table -->
            <div class="custom-card">
                <div class="custom-card-header">
                    <h5 class="mb-0 fw-bold">All Assignments (<?php echo count($assignments); ?>)</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-custom align-middle" id="adminAssignmentsTable">
                        <thead>
                            <tr>
                                <th>Assignment Details</th>
                                <th>Faculty Author</th>
                                <th>Deadline</th>
                                <th>Submissions</th>
                                <th>Attachment</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($assignments)): ?>
                                <?php foreach ($assignments as $a): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle mb-1">
                                                <?php echo e($a['subject']); ?>
                                            </span>
                                            <h6 class="mb-1 fw-bold text-dark"><?php echo e($a['title']); ?></h6>
                                            <p class="small text-muted mb-0 text-truncate" style="max-width: 320px;">
                                                <?php echo e($a['description']); ?>
                                            </p>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?php echo e($a['teacher_name']); ?></div>
                                            <small class="text-muted"><?php echo e($a['teacher_email']); ?></small>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold"><?php echo formatDate($a['deadline']); ?></div>
                                            <?php echo renderDeadlineBadge($a['deadline']); ?>
                                        </td>
                                        <td>
                                            <a href="<?php echo BASE_URL; ?>/admin/submissions.php?assignment_id=<?php echo (int)$a['id']; ?>" class="badge bg-primary fs-6 text-decoration-none">
                                                <?php echo (int)$a['total_submissions']; ?> Submissions
                                            </a>
                                            <?php if ((int)$a['pending_submissions'] > 0): ?>
                                                <div class="small text-warning mt-1">
                                                    <i class="bi bi-clock-history me-1"></i><?php echo (int)$a['pending_submissions']; ?> pending
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($a['file'])): ?>
                                                <a href="<?php echo BASE_URL; ?>/download.php?type=assignment&id=<?php echo (int)$a['id']; ?>" class="btn btn-sm btn-light border text-primary">
                                                    <i class="bi bi-paperclip me-1"></i>Download
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small">— None —</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?php echo BASE_URL; ?>/admin/submissions.php?assignment_id=<?php echo (int)$a['id']; ?>" class="btn btn-outline-primary" title="View Submissions">
                                                    <i class="bi bi-inbox"></i> Submissions
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                        No assignments found in the system database.
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


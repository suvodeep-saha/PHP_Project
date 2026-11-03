<?php
/**
 * Student Assignments Directory
 * Online Assignment Submission System
 * 
 * Demonstrates:
 * - Dynamic SQL Filtering and Sorting using Prepared Statements
 * - GET parameters handling and validation
 * - Dynamic joins between assignments, users, and submissions
 * - Responsive table with live JS filtering
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAuth('student');

$studentId = (int)$_SESSION['user_id'];
$pdo = getDBConnection();

// Retrieve & sanitize GET query parameters
$search = trim($_GET['search'] ?? '');
$filterStatus = trim($_GET['status'] ?? 'all');
$sortBy = trim($_GET['sort'] ?? 'deadline_asc');

// Build dynamic SQL query
$sql = "
    SELECT a.*, u.name AS teacher_name,
           s.id AS submission_id,
           s.status AS submission_status,
           s.marks AS submission_marks,
           s.submit_date
    FROM assignments a
    JOIN users u ON a.teacher_id = u.id
    LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = :sid
    WHERE 1=1
";

$params = ['sid' => $studentId];

// Search filter
if (!empty($search)) {
    $sql .= " AND (a.title LIKE :search OR a.subject LIKE :search OR u.name LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

// Submission status filter
if ($filterStatus === 'submitted') {
    $sql .= " AND s.id IS NOT NULL";
} elseif ($filterStatus === 'not_submitted') {
    $sql .= " AND s.id IS NULL";
}

// Sorting logic
$orderClause = match ($sortBy) {
    'deadline_desc' => 'a.deadline DESC',
    'title_asc'     => 'a.title ASC',
    'title_desc'    => 'a.title DESC',
    default         => 'a.deadline ASC',
};

$sql .= " ORDER BY {$orderClause}";

$assignments = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $assignments = $stmt->fetchAll();
} catch (PDOException $e) {
    // Handled
}

// Fetch distinct subjects for filter dropdown
$subjects = [];
try {
    $subjects = $pdo->query("SELECT DISTINCT subject FROM assignments ORDER BY subject ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Handled
}

$pageTitle = 'Available Assignments';
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
                    <h2 class="fw-bold text-dark mb-1">Browse Course Assignments</h2>
                    <p class="text-muted small mb-0">View all assignments, download question files, and submit your work before deadlines.</p>
                </div>
            </div>

            <!-- Search, Filter & Sort Form -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-body p-3">
                    <form method="GET" action="<?php echo e($_SERVER['PHP_SELF']); ?>" class="row g-2 align-items-center">
                        <div class="col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" name="search" class="form-control border-start-0" placeholder="Search by title, subject, or faculty..." value="<?php echo e($search); ?>" data-table-filter="#assignmentsTable">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All Submissions</option>
                                <option value="not_submitted" <?php echo $filterStatus === 'not_submitted' ? 'selected' : ''; ?>>Not Submitted Yet</option>
                                <option value="submitted" <?php echo $filterStatus === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <select name="sort" class="form-select" onchange="this.form.submit()">
                                <option value="deadline_asc" <?php echo $sortBy === 'deadline_asc' ? 'selected' : ''; ?>>Deadline (Earliest First)</option>
                                <option value="deadline_desc" <?php echo $sortBy === 'deadline_desc' ? 'selected' : ''; ?>>Deadline (Latest First)</option>
                                <option value="title_asc" <?php echo $sortBy === 'title_asc' ? 'selected' : ''; ?>>Title (A &rarr; Z)</option>
                                <option value="title_desc" <?php echo $sortBy === 'title_desc' ? 'selected' : ''; ?>>Title (Z &rarr; A)</option>
                            </select>
                        </div>

                        <div class="col-md-1 d-grid">
                            <a href="<?php echo e($_SERVER['PHP_SELF']); ?>" class="btn btn-outline-secondary" title="Reset Filters">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Assignments Table -->
            <div class="custom-card">
                <div class="custom-card-header">
                    <h5 class="mb-0 fw-bold">Assignment List (<?php echo count($assignments); ?>)</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-custom align-middle" id="assignmentsTable">
                        <thead>
                            <tr>
                                <th>Assignment Details</th>
                                <th>Faculty</th>
                                <th>Deadline</th>
                                <th>Max Marks</th>
                                <th>My Submission</th>
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
                                            <h6 class="mb-1 fw-bold text-dark">
                                                <a href="<?php echo BASE_URL; ?>/student/assignment_details.php?id=<?php echo (int)$a['id']; ?>" class="text-dark text-decoration-none">
                                                    <?php echo e($a['title']); ?>
                                                </a>
                                            </h6>
                                            <p class="text-muted small mb-0 text-truncate" style="max-width: 380px;">
                                                <?php echo e($a['description']); ?>
                                            </p>
                                        </td>
                                        <td>
                                            <span class="small fw-semibold text-secondary"><?php echo e($a['teacher_name']); ?></span>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold"><?php echo formatDate($a['deadline']); ?></div>
                                            <?php echo renderDeadlineBadge($a['deadline']); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?php echo (int)$a['max_marks']; ?> pts</span>
                                        </td>
                                        <td>
                                            <?php if ($a['submission_id']): ?>
                                                <?php echo renderStatusBadge($a['submission_status']); ?>
                                                <?php if ($a['submission_marks'] !== null): ?>
                                                    <div class="small fw-bold text-success mt-1">
                                                        Marks: <?php echo (float)$a['submission_marks']; ?>/<?php echo (int)$a['max_marks']; ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary border">Not Submitted</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-1">
                                                <a href="<?php echo BASE_URL; ?>/student/assignment_details.php?id=<?php echo (int)$a['id']; ?>" class="btn btn-sm btn-outline-primary" title="View Full Details">
                                                    <i class="bi bi-eye"></i> Details
                                                </a>
                                                <?php if (!$a['submission_id']): ?>
                                                    <a href="<?php echo BASE_URL; ?>/student/submit.php?id=<?php echo (int)$a['id']; ?>" class="btn btn-sm btn-primary" title="Submit Solution">
                                                        <i class="bi bi-upload"></i> Submit
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
                                        No assignments found matching your filter criteria.
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


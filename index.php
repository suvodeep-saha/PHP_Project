<?php
/**
 * Public Landing Page
 * Online Assignment Submission System
 * 
 * Demonstrates:
 * - Dynamic data retrieval from MySQL using PDO
 * - Dynamic calculation of system metrics
 * - Conditional view rendering (guest vs authenticated user)
 * - Clean UI with Bootstrap 5
 */

require_once __DIR__ . '/config/config.php';

$pageTitle = 'Home';
$pdo = getDBConnection();

// Fetch live metrics dynamically from MySQL
$statAssignments = 0;
$statSubmissions = 0;
$statUsers = 0;
$recentAssignments = [];

try {
    $statAssignments = (int)$pdo->query("SELECT COUNT(*) FROM assignments")->fetchColumn();
    $statSubmissions = (int)$pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();
    $statUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('student', 'teacher')")->fetchColumn();

    // Fetch up to 3 upcoming assignments for showcase
    $stmtRecent = $pdo->query("
        SELECT a.id, a.title, a.subject, a.deadline, a.max_marks, u.name AS teacher_name
        FROM assignments a
        JOIN users u ON a.teacher_id = u.id
        WHERE a.deadline >= NOW()
        ORDER BY a.deadline ASC
        LIMIT 3
    ");
    $recentAssignments = $stmtRecent->fetchAll();
} catch (PDOException $e) {
    // Graceful fallback if database is being initialized
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="py-5 bg-white border-bottom">
    <div class="container py-4">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 mb-3 rounded-pill fw-semibold">
                    <i class="bi bi-stars me-1"></i>Academic Year 2026-2027 Portal
                </span>
                <h1 class="display-4 fw-bold text-dark lh-sm mb-3">
                    Streamlined Online Assignment Submission &amp; Grading
                </h1>
                <p class="lead text-muted mb-4">
                    A centralized, database-driven platform built for students and educators. Submit work seamlessly, review submissions in real-time, and track grades transparently.
                </p>

                <div class="d-flex flex-wrap gap-3">
                    <?php if (isLoggedIn()): ?>
                        <?php
                            $user = currentUser();
                            $targetDash = match ($user['role']) {
                                'admin'   => 'admin/dashboard.php',
                                'teacher' => 'teacher/dashboard.php',
                                'student' => 'student/dashboard.php',
                                default   => 'index.php',
                            };
                        ?>
                        <a href="<?php echo BASE_URL . '/' . $targetDash; ?>" class="btn btn-primary btn-lg px-4 shadow-sm">
                            <i class="bi bi-speedometer2 me-2"></i>Go to My Dashboard &rarr;
                        </a>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>/register.php" class="btn btn-primary btn-lg px-4 shadow-sm">
                            <i class="bi bi-person-plus-fill me-2"></i>Register as Student
                        </a>
                        <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-outline-secondary btn-lg px-4">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo BASE_URL; ?>/members.php" class="btn btn-light btn-lg px-4 border">
                        <i class="bi bi-info-circle me-2"></i>Portal Overview
                    </a>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="card-header bg-dark text-white p-3 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-shield-check text-success fs-5 me-2"></i>
                            <span class="fw-semibold small">Live Evaluation Pipeline</span>
                        </div>
                        <span class="badge bg-success">Active</span>
                    </div>
                    <div class="card-body p-4 bg-light">
                        <div class="d-flex align-items-center mb-3 p-3 bg-white rounded-3 border">
                            <div class="stat-icon bg-warning-subtle text-warning me-3">
                                <i class="bi bi-clock-history"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold">Step 1: Submission</h6>
                                <small class="text-muted">Status: <span class="badge bg-warning text-dark">Pending</span></small>
                            </div>
                        </div>

                        <div class="d-flex align-items-center mb-3 p-3 bg-white rounded-3 border">
                            <div class="stat-icon bg-info-subtle text-info me-3">
                                <i class="bi bi-search"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold">Step 2: Faculty Evaluation</h6>
                                <small class="text-muted">Status: <span class="badge bg-info text-dark">Reviewed</span></small>
                            </div>
                        </div>

                        <div class="d-flex align-items-center p-3 bg-white rounded-3 border">
                            <div class="stat-icon bg-success-subtle text-success me-3">
                                <i class="bi bi-check2-all"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold">Step 3: Grade Published</h6>
                                <small class="text-muted">Status: <span class="badge bg-success">Completed</span></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Dynamic Live Metrics Bar -->
<section class="py-4 bg-white border-bottom">
    <div class="container">
        <div class="row g-4 text-center">
            <div class="col-md-4">
                <div class="p-3 border rounded-3 bg-light">
                    <h3 class="fw-bold text-primary mb-1"><?php echo $statAssignments; ?></h3>
                    <p class="text-muted small mb-0 text-uppercase fw-semibold">Published Assignments</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 border rounded-3 bg-light">
                    <h3 class="fw-bold text-success mb-1"><?php echo $statSubmissions; ?></h3>
                    <p class="text-muted small mb-0 text-uppercase fw-semibold">Total Submissions</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 border rounded-3 bg-light">
                    <h3 class="fw-bold text-info mb-1"><?php echo $statUsers; ?></h3>
                    <p class="text-muted small mb-0 text-uppercase fw-semibold">Enrolled Students &amp; Faculty</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Upcoming Assignments Showcase -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h3 class="fw-bold mb-1">Upcoming Assignment Deadlines</h3>
                <p class="text-muted small mb-0">Review active course assignments currently accepting submissions.</p>
            </div>
            <?php if (isLoggedIn() && hasRole('student')): ?>
                <a href="<?php echo BASE_URL; ?>/student/assignments.php" class="btn btn-outline-primary btn-sm">
                    View All Assignments &rarr;
                </a>
            <?php endif; ?>
        </div>

        <div class="row g-4">
            <?php if (!empty($recentAssignments)): ?>
                <?php foreach ($recentAssignments as $assign): ?>
                    <div class="col-md-4">
                        <div class="card h-100 border-0 shadow-sm rounded-3">
                            <div class="card-body p-4 d-flex flex-column">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                        <?php echo e($assign['subject']); ?>
                                    </span>
                                    <?php echo renderDeadlineBadge($assign['deadline']); ?>
                                </div>
                                <h5 class="card-title fw-bold text-dark mt-2 mb-2">
                                    <?php echo e($assign['title']); ?>
                                </h5>
                                <p class="text-muted small mb-3">
                                    <i class="bi bi-person me-1"></i>Instructor: <?php echo e($assign['teacher_name']); ?>
                                </p>
                                <div class="mt-auto pt-3 border-top d-flex align-items-center justify-content-between">
                                    <span class="small fw-semibold text-secondary">
                                        Max Marks: <?php echo (int)$assign['max_marks']; ?>
                                    </span>
                                    <?php if (isLoggedIn() && hasRole('student')): ?>
                                        <a href="<?php echo BASE_URL; ?>/student/assignment_details.php?id=<?php echo (int)$assign['id']; ?>" class="btn btn-sm btn-primary">
                                            View &amp; Submit &rarr;
                                        </a>
                                    <?php else: ?>
                                        <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-sm btn-outline-primary">
                                            Log in to Submit
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="text-center p-5 bg-white rounded-3 border">
                        <i class="bi bi-journal-check text-muted fs-1 mb-2 d-block"></i>
                        <h5 class="text-secondary fw-semibold">No active assignments right now</h5>
                        <p class="text-muted small">Teachers will post new tasks soon. Check back shortly!</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- System Features Section -->
<section class="py-5 bg-white border-top">
    <div class="container py-3">
        <div class="text-center max-w-2xl mx-auto mb-5">
            <span class="text-primary fw-semibold small text-uppercase tracking-wider">Academic Portal Highlights</span>
            <h2 class="fw-bold mt-1">Built to Meet Syllabus &amp; Enterprise Standards</h2>
            <p class="text-muted">Implementing robust web development practices using Core PHP and MySQL.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 border p-4 rounded-3">
                    <div class="stat-icon bg-primary-subtle text-primary mb-3">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>
                    <h5 class="fw-bold">Security &amp; Sanitization</h5>
                    <p class="text-muted small mb-0">
                        Zero SQL injection risks via PDO prepared statements. Cross-Site Scripting (XSS) mitigated with full output escaping, CSRF tokens, and bcrypt password hashing.
                    </p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card h-100 border p-4 rounded-3">
                    <div class="stat-icon bg-success-subtle text-success mb-3">
                        <i class="bi bi-file-earmark-arrow-up-fill"></i>
                    </div>
                    <h5 class="fw-bold">Validated File Handling</h5>
                    <p class="text-muted small mb-0">
                        Multi-layer file checks verifying real MIME signatures via PHP <code>finfo</code>, extension whitelisting, size restrictions (10MB), and execution-proof storage.
                    </p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card h-100 border p-4 rounded-3">
                    <div class="stat-icon bg-info-subtle text-info mb-3">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <h5 class="fw-bold">Role-Based Access Control</h5>
                    <p class="text-muted small mb-0">
                        Tailored workspaces and permissions for Students, Instructors, and Administrators with session persistence and secure cookie headers.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


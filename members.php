<?php
/**
 * Members Area / Roles Information & Testing Hub
 * Online Assignment Submission System
 */

require_once __DIR__ . '/config/config.php';

$pageTitle = 'Members Area';
$pdo = getDBConnection();

// Fetch sample faculty and active statistics
$instructors = [];
try {
    $stmt = $pdo->query("SELECT name, email, bio FROM users WHERE role = 'teacher' ORDER BY name ASC");
    $instructors = $stmt->fetchAll();
} catch (PDOException $e) {
    // Ignore
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="text-center max-w-2xl mx-auto mb-5">
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill fw-semibold mb-2">
            <i class="bi bi-people-fill me-1"></i>Academic Community
        </span>
        <h2 class="fw-bold mt-2">Members Area &amp; Role Directory</h2>
        <p class="text-muted">Explore how students, instructors, and administrators interact within the portal.</p>
    </div>

    <!-- 3 Roles Overview Cards -->
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm rounded-4">
                <div class="card-body p-4 text-center">
                    <div class="stat-icon bg-success-subtle text-success mx-auto mb-3" style="width: 60px; height: 60px; font-size: 1.8rem;">
                        <i class="bi bi-backpack-fill"></i>
                    </div>
                    <h5 class="fw-bold">Student Member</h5>
                    <p class="text-muted small">
                        Browse active assignments, download instructions, upload solutions in PDF/DOCX/ZIP, and track evaluations with detailed marks and instructor remarks.
                    </p>
                    <div class="mt-3">
                        <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-sm btn-outline-success">Student Sign In</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm rounded-4">
                <div class="card-body p-4 text-center">
                    <div class="stat-icon bg-primary-subtle text-primary mx-auto mb-3" style="width: 60px; height: 60px; font-size: 1.8rem;">
                        <i class="bi bi-person-workspace"></i>
                    </div>
                    <h5 class="fw-bold">Faculty Member</h5>
                    <p class="text-muted small">
                        Publish course assignments with deadlines and attachments, inspect student submissions, grade work out of 100, and update submission status.
                    </p>
                    <div class="mt-3">
                        <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-sm btn-outline-primary">Faculty Sign In</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm rounded-4">
                <div class="card-body p-4 text-center">
                    <div class="stat-icon bg-danger-subtle text-danger mx-auto mb-3" style="width: 60px; height: 60px; font-size: 1.8rem;">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>
                    <h5 class="fw-bold">Administrator</h5>
                    <p class="text-muted small">
                        Full administrative oversight: manage registered users, reassign roles, audit global assignments and submissions, and supervise academic metrics.
                    </p>
                    <div class="mt-3">
                        <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-sm btn-outline-danger">Admin Sign In</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Faculty Roster from Database -->
    <div class="custom-card mb-5">
        <div class="custom-card-header">
            <h5 class="mb-0 fw-bold"><i class="bi bi-mortarboard me-2 text-primary"></i>Faculty Members Directory</h5>
            <span class="badge bg-light text-dark border"><?php echo count($instructors); ?> Faculty Listed</span>
        </div>
        <div class="custom-card-body">
            <div class="row g-4">
                <?php if (!empty($instructors)): ?>
                    <?php foreach ($instructors as $inst): ?>
                        <div class="col-md-6">
                            <div class="p-3 border rounded-3 bg-light d-flex align-items-start">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width: 48px; height: 48px; font-weight: 700; font-size: 1.2rem;">
                                    <?php echo strtoupper(substr($inst['name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <h6 class="mb-1 fw-bold text-dark"><?php echo e($inst['name']); ?></h6>
                                    <p class="text-muted small mb-1"><i class="bi bi-envelope me-1"></i><?php echo e($inst['email']); ?></p>
                                    <p class="text-secondary small mb-0"><?php echo e($inst['bio'] ?? 'Faculty Member'); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted small mb-0">No instructors registered yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Examiner / Test Credentials Helper -->
    <div class="card border-primary border-opacity-25 bg-primary-subtle bg-opacity-10 rounded-4">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h5 class="fw-bold text-dark mb-1"><i class="bi bi-key-fill text-primary me-2"></i>Testing &amp; Evaluation Credentials</h5>
                    <p class="text-muted small mb-0">
                        Use the pre-seeded accounts to experience all three dashboard roles without creating new accounts.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                    <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-primary shadow-sm">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Proceed to Login
                    </a>
                </div>
            </div>

            <div class="table-responsive mt-3">
                <table class="table table-sm table-bordered bg-white small mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Role</th>
                            <th>Full Name</th>
                            <th>Email Address</th>
                            <th>Password</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="badge bg-danger">Admin</span></td>
                            <td>System Administrator</td>
                            <td><code>admin@assignments.edu</code></td>
                            <td><code>admin123</code></td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-primary">Teacher</span></td>
                            <td>Prof. Ramesh Sharma</td>
                            <td><code>prof.sharma@assignments.edu</code></td>
                            <td><code>teacher123</code></td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-success">Student</span></td>
                            <td>Suvodeep Mukherjee</td>
                            <td><code>suvo@student.edu</code></td>
                            <td><code>student123</code></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


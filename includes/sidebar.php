<?php
/**
 * Role-Based Dashboard Sidebar Navigation
 * Online Assignment Submission System
 */
require_once __DIR__ . '/../config/config.php';

$user = currentUser();
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$role = strtolower($user['role'] ?? '');
?>

<aside class="sidebar">
    <div class="px-3 pb-3 mb-2 border-bottom border-secondary border-opacity-25">
        <div class="d-flex align-items-center">
            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 42px; height: 42px; font-weight: 600;">
                <?php echo strtoupper(substr($user['name'] ?? 'U', 0, 1)); ?>
            </div>
            <div class="overflow-hidden">
                <h6 class="text-white mb-0 text-truncate font-monospace" style="font-size: 0.95rem;">
                    <?php echo e($user['name'] ?? 'User'); ?>
                </h6>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle text-uppercase" style="font-size: 0.65rem;">
                    <?php echo e($role); ?>
                </span>
            </div>
        </div>
    </div>

    <nav class="nav flex-column mb-auto">
        <?php if ($role === 'student'): ?>
            <div class="sidebar-heading">Student Portal</div>
            <a class="nav-link <?php echo $currentScript === 'dashboard.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/student/dashboard.php">
                <i class="bi bi-speedometer2"></i>Dashboard
            </a>
            <a class="nav-link <?php echo in_array($currentScript, ['assignments.php', 'assignment_details.php', 'submit.php']) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/student/assignments.php">
                <i class="bi bi-journal-text"></i>Browse Assignments
            </a>
            <a class="nav-link <?php echo $currentScript === 'submissions.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/student/submissions.php">
                <i class="bi bi-cloud-arrow-up"></i>My Submissions
            </a>
            <a class="nav-link <?php echo $currentScript === 'profile.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/student/profile.php">
                <i class="bi bi-person-gear"></i>My Profile
            </a>

        <?php elseif ($role === 'teacher'): ?>
            <div class="sidebar-heading">Instructor Hub</div>
            <a class="nav-link <?php echo $currentScript === 'dashboard.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/teacher/dashboard.php">
                <i class="bi bi-speedometer2"></i>Dashboard
            </a>
            <a class="nav-link <?php echo $currentScript === 'add_assignment.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/teacher/add_assignment.php">
                <i class="bi bi-plus-circle"></i>Create Assignment
            </a>
            <a class="nav-link <?php echo in_array($currentScript, ['assignments.php', 'edit_assignment.php']) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/teacher/assignments.php">
                <i class="bi bi-collection"></i>My Assignments
            </a>
            <a class="nav-link <?php echo in_array($currentScript, ['submissions.php', 'evaluate.php']) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/teacher/submissions.php">
                <i class="bi bi-clipboard-check"></i>Student Submissions
            </a>
            <a class="nav-link <?php echo $currentScript === 'profile.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/teacher/profile.php">
                <i class="bi bi-person-gear"></i>Teacher Profile
            </a>

        <?php elseif ($role === 'admin'): ?>
            <div class="sidebar-heading">System Administration</div>
            <a class="nav-link <?php echo $currentScript === 'dashboard.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/dashboard.php">
                <i class="bi bi-speedometer2"></i>Dashboard
            </a>
            <a class="nav-link <?php echo in_array($currentScript, ['users.php', 'edit_user.php']) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/users.php">
                <i class="bi bi-people"></i>User Management
            </a>
            <a class="nav-link <?php echo $currentScript === 'assignments.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/assignments.php">
                <i class="bi bi-journal-bookmark"></i>All Assignments
            </a>
            <a class="nav-link <?php echo in_array($currentScript, ['submissions.php', 'update_status.php']) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/submissions.php">
                <i class="bi bi-file-earmark-bar-graph"></i>All Submissions
            </a>
            <a class="nav-link <?php echo $currentScript === 'profile.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/profile.php">
                <i class="bi bi-shield-lock"></i>Admin Profile
            </a>
        <?php endif; ?>
    </nav>

    <div class="p-3 border-top border-secondary border-opacity-25 mt-auto">
        <a href="<?php echo BASE_URL; ?>/logout.php" class="btn btn-outline-danger btn-sm w-100 d-flex align-items-center justify-content-center">
            <i class="bi bi-box-arrow-right me-2"></i>Sign Out
        </a>
    </div>
</aside>


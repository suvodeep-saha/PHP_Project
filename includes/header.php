<?php
/**
 * Global Header Component
 * Online Assignment Submission System
 */
require_once __DIR__ . '/../config/config.php';

$pageTitle = $pageTitle ?? APP_NAME;
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?> | <?php echo APP_NAME; ?></title>

    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons 1.11.3 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom Stylesheet -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg portal-navbar sticky-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand text-primary d-flex align-items-center" href="<?php echo BASE_URL; ?>/index.php">
            <i class="bi bi-mortarboard-fill fs-3 me-2"></i>
            <span class="fw-bold tracking-tight"><?php echo APP_NAME; ?></span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3">
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['SCRIPT_NAME']) === 'index.php' ? 'active fw-bold' : ''; ?>" href="<?php echo BASE_URL; ?>/index.php">
                        <i class="bi bi-house-door me-1"></i>Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['SCRIPT_NAME']) === 'members.php' ? 'active fw-bold' : ''; ?>" href="<?php echo BASE_URL; ?>/members.php">
                        <i class="bi bi-people me-1"></i>Members Area
                    </a>
                </li>
                <?php if ($user): ?>
                    <?php
                        $dashUrl = match ($user['role']) {
                            'admin'   => BASE_URL . '/admin/dashboard.php',
                            'teacher' => BASE_URL . '/teacher/dashboard.php',
                            'student' => BASE_URL . '/student/dashboard.php',
                            default   => BASE_URL . '/index.php',
                        };
                    ?>
                    <li class="nav-item">
                        <a class="nav-link text-primary fw-semibold" href="<?php echo $dashUrl; ?>">
                            <i class="bi bi-speedometer2 me-1"></i>My Dashboard
                        </a>
                    </li>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">
                <?php if ($user): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-2 text-uppercase">
                                <?php echo e($user['role']); ?>
                            </span>
                            <span class="fw-semibold text-dark me-1"><?php echo e($user['name']); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                            <li><h6 class="dropdown-header small text-muted"><?php echo e($user['email']); ?></h6></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php
                                $profileUrl = match ($user['role']) {
                                    'admin'   => BASE_URL . '/admin/profile.php',
                                    'teacher' => BASE_URL . '/teacher/profile.php',
                                    'student' => BASE_URL . '/student/profile.php',
                                    default   => BASE_URL . '/index.php',
                                };
                            ?>
                            <li>
                                <a class="dropdown-item" href="<?php echo $profileUrl; ?>">
                                    <i class="bi bi-person me-2"></i>My Profile
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo $dashUrl; ?>">
                                    <i class="bi bi-grid me-2"></i>Dashboard
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>/logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i>Sign Out
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item me-2">
                        <a class="btn btn-outline-primary btn-sm px-3" href="<?php echo BASE_URL; ?>/login.php">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Sign In
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-primary btn-sm px-3 shadow-sm" href="<?php echo BASE_URL; ?>/register.php">
                            <i class="bi bi-person-plus me-1"></i>Create Account
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<?php if (empty($isDashboard)): ?>
    <div class="container mt-3">
        <?php echo renderFlashMessages(); ?>
    </div>
<?php endif; ?>


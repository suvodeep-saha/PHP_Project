<?php
/**
 * User Login & Authentication Page
 * Online Assignment Submission System
 * 
 * Demonstrates:
 * - Session-based Authentication
 * - Password Verification with password_verify()
 * - Role-Based Dispatching (Student / Teacher / Admin)
 * - Session Regeneration against Session Fixation
 * - Persistent Cookies (Remember Me)
 * - Input Sanitization & Prepared Queries
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';

// Only unauthenticated guests can view login page
requireGuest();

$error = '';
$email = $_COOKIE['remember_user'] ?? '';
$remember = !empty($email);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF Token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security validation failed (CSRF). Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']);

        if (empty($email) || empty($password)) {
            $error = 'Please enter both your email address and password.';
        } else {
            $pdo = getDBConnection();

            // 2. Fetch user record using parameterized prepared statement
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            // 3. Verify user existence and hashed password
            if ($user && password_verify($password, $user['password'])) {
                // Check if account status is active
                if (($user['status'] ?? 'active') !== 'active') {
                    $error = 'Your account has been deactivated. Please contact the administrator.';
                } else {
                    // Session Regeneration: prevents session fixation attacks
                    session_regenerate_id(true);

                    // Store authenticated user payload in $_SESSION
                    $_SESSION['user_id']    = (int)$user['id'];
                    $_SESSION['user_name']  = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role']  = strtolower($user['role']);
                    $_SESSION['logged_at']  = time();

                    // Manage Remember Me Cookie (demonstrating PHP Cookies)
                    if ($remember) {
                        setcookie('remember_user', $email, time() + (86400 * 30), '/', '', false, true); // 30 days, HttpOnly
                    } else {
                        setcookie('remember_user', '', time() - 3600, '/');
                    }

                    setFlash('success', 'Welcome back, ' . e($user['name']) . '!');

                    // Redirect to intended page or role dashboard
                    $intendedUrl = $_SESSION['intended_url'] ?? null;
                    unset($_SESSION['intended_url']);

                    if ($intendedUrl && !str_contains($intendedUrl, 'login.php') && !str_contains($intendedUrl, 'register.php')) {
                        header("Location: {$intendedUrl}");
                        exit;
                    }

                    $roleRedirect = match (strtolower($user['role'])) {
                        'admin'   => 'admin/dashboard.php',
                        'teacher' => 'teacher/dashboard.php',
                        'student' => 'student/dashboard.php',
                        default   => 'index.php',
                    };
                    redirect($roleRedirect);
                }
            } else {
                $error = 'Invalid email address or password. Please try again.';
            }
        }
    }
}

$pageTitle = 'Sign In';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5 col-xl-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-primary text-white p-4 text-center">
                    <i class="bi bi-shield-lock-fill fs-2 d-block mb-1"></i>
                    <h4 class="fw-bold mb-0">Sign In</h4>
                    <p class="text-white-50 small mb-0">Access Your Academic Portal</p>
                </div>

                <div class="card-body p-4 p-md-4">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger d-flex align-items-center mb-3 py-2" role="alert">
                            <i class="bi bi-exclamation-circle-fill me-2 fs-5"></i>
                            <div class="small"><?php echo e($error); ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?php echo e($_SERVER['PHP_SELF']); ?>" novalidate>
                        <?php echo csrf_field(); ?>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold small text-secondary">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo e($email); ?>" required autofocus placeholder="name@college.edu">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold small text-secondary">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-key"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required placeholder="Your password">
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1" <?php echo $remember ? 'checked' : ''; ?>>
                                <label class="form-check-label small text-secondary" for="remember">
                                    Remember me
                                </label>
                            </div>
                            <span class="small text-muted">Role auto-detected</span>
                        </div>

                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary btn-lg shadow-sm">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                            </button>
                        </div>

                        <div class="text-center small text-muted">
                            Don't have an account? <a href="<?php echo BASE_URL; ?>/register.php" class="fw-semibold text-primary">Register here</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Quick Demo Credentials Helper for Examiners -->
            <div class="card border bg-light mt-4 rounded-3">
                <div class="card-body p-3">
                    <h6 class="card-title fw-bold text-dark small mb-2">
                        <i class="bi bi-info-circle-fill text-primary me-1"></i>Quick Demo Logins
                    </h6>
                    <div class="d-flex flex-column gap-1 small text-muted">
                        <div><strong class="text-danger">Admin:</strong> <code>admin@assignments.edu</code> / <code>admin123</code></div>
                        <div><strong class="text-primary">Teacher:</strong> <code>prof.sharma@assignments.edu</code> / <code>teacher123</code></div>
                        <div><strong class="text-success">Student:</strong> <code>suvo@student.edu</code> / <code>student123</code></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


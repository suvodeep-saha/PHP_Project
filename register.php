<?php
/**
 * User Registration Page
 * Online Assignment Submission System
 * 
 * Demonstrates:
 * - HTML Form with POST method
 * - Server-side Form Validation
 * - Input sanitization & filter_var()
 * - CSRF token verification
 * - Prepared statement insertion into MySQL
 * - Password hashing using password_hash()
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';

// Only guests can register
requireGuest();

$errors = [];
$name = '';
$email = '';
$role = 'student';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF Token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token (CSRF). Please reload the page and try again.';
    }

    // 2. Sanitize and retrieve form inputs
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = trim($_POST['role'] ?? 'student');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // 3. Server-side Validation
    if (empty($name) || strlen($name) < 3) {
        $errors[] = 'Full Name must be at least 3 characters long.';
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }

    if (!in_array($role, ['student', 'teacher'], true)) {
        $errors[] = 'Invalid account role selected.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters in length.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    // 4. Check for duplicate email in MySQL using PDO prepared statement
    if (empty($errors)) {
        $pdo = getDBConnection();

        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmtCheck->execute(['email' => $email]);

        if ($stmtCheck->fetch()) {
            $errors[] = 'An account with this email address already exists. Please log in instead.';
        }
    }

    // 5. Insert new user into database
    if (empty($errors)) {
        try {
            // Password hashing using bcrypt algorithm
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            $sql = "INSERT INTO users (name, email, password, role, phone, status) 
                    VALUES (:name, :email, :password, :role, :phone, 'active')";
            
            $stmtInsert = $pdo->prepare($sql);
            $stmtInsert->execute([
                'name'     => $name,
                'email'    => $email,
                'password' => $passwordHash,
                'role'     => $role,
                'phone'    => !empty($phone) ? $phone : null,
            ]);

            setFlash('success', 'Registration successful! You can now sign in with your credentials.');
            redirect('login.php');
        } catch (PDOException $e) {
            $errors[] = 'A database error occurred during registration. Please try again later.';
        }
    }
}

$pageTitle = 'Create Account';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6 col-xl-5">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-primary text-white p-4 text-center">
                    <i class="bi bi-person-plus-fill fs-2 d-block mb-1"></i>
                    <h4 class="fw-bold mb-0">Create an Account</h4>
                    <p class="text-white-50 small mb-0">Join EduSubmit Portal</p>
                </div>

                <div class="card-body p-4 p-md-5">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger mb-4" role="alert">
                            <h6 class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Please fix the following:</h6>
                            <ul class="mb-0 ps-3 small">
                                <?php foreach ($errors as $err): ?>
                                    <li><?php echo e($err); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?php echo e($_SERVER['PHP_SELF']); ?>" novalidate>
                        <?php echo csrf_field(); ?>

                        <div class="mb-3">
                            <label for="name" class="form-label fw-semibold small text-secondary">Full Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo e($name); ?>" required placeholder="e.g. John Doe">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold small text-secondary">Email Address <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo e($email); ?>" required placeholder="name@college.edu">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label for="role" class="form-label fw-semibold small text-secondary">I am a <span class="text-danger">*</span></label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="student" <?php echo $role === 'student' ? 'selected' : ''; ?>>Student</option>
                                    <option value="teacher" <?php echo $role === 'teacher' ? 'selected' : ''; ?>>Teacher / Faculty</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label fw-semibold small text-secondary">Phone Number</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted"><i class="bi bi-telephone"></i></span>
                                    <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo e($phone); ?>" placeholder="Optional">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold small text-secondary">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required placeholder="Minimum 6 characters">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="confirm_password" class="form-label fw-semibold small text-secondary">Confirm Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-shield-check"></i></span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required placeholder="Re-type password">
                            </div>
                        </div>

                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary btn-lg shadow-sm">
                                <i class="bi bi-person-check-fill me-2"></i>Complete Registration
                            </button>
                        </div>

                        <div class="text-center small text-muted">
                            Already have an account? <a href="<?php echo BASE_URL; ?>/login.php" class="fw-semibold text-primary">Sign In here</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


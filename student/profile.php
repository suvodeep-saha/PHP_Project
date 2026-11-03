<?php
/**
 * Student Profile & Account Settings
 * Online Assignment Submission System
 * 
 * Demonstrates:
 * - Profile data retrieval & updating (CRUD Update)
 * - Password verification (password_verify) & hashing (password_hash)
 * - Prepared statement updates with input sanitization
 * - Security session sync
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAuth('student');

$userId = (int)$_SESSION['user_id'];
$pdo = getDBConnection();

$errors = [];
$success = '';

// Fetch current user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    die('User account not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'CSRF validation failed. Please reload and try again.';
    } else {
        $action = trim($_POST['action'] ?? '');

        // Action 1: Update Basic Profile
        if ($action === 'update_profile') {
            $name = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $bio = trim($_POST['bio'] ?? '');

            if (empty($name) || strlen($name) < 3) {
                $errors[] = 'Full Name must be at least 3 characters.';
            }

            if (empty($errors)) {
                try {
                    $stmtUpdate = $pdo->prepare("
                        UPDATE users 
                        SET name = :name, phone = :phone, bio = :bio, updated_at = NOW() 
                        WHERE id = :id
                    ");
                    $stmtUpdate->execute([
                        'name'  => $name,
                        'phone' => !empty($phone) ? $phone : null,
                        'bio'   => !empty($bio) ? $bio : null,
                        'id'    => $userId,
                    ]);

                    $_SESSION['user_name'] = $name;
                    setFlash('success', 'Profile details updated successfully!');
                    redirect('student/profile.php');
                } catch (PDOException $e) {
                    $errors[] = 'Database error updating profile: ' . $e->getMessage();
                }
            }

        // Action 2: Change Password
        } elseif ($action === 'change_password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($currentPassword) || !password_verify($currentPassword, $user['password'])) {
                $errors[] = 'Current password entered is incorrect.';
            }

            if (strlen($newPassword) < 6) {
                $errors[] = 'New password must be at least 6 characters long.';
            }

            if ($newPassword !== $confirmPassword) {
                $errors[] = 'New password and confirmation password do not match.';
            }

            if (empty($errors)) {
                try {
                    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $stmtPass = $pdo->prepare("UPDATE users SET password = :pass, updated_at = NOW() WHERE id = :id");
                    $stmtPass->execute(['pass' => $newHash, 'id' => $userId]);

                    setFlash('success', 'Password updated successfully! Please use your new password next time.');
                    redirect('student/profile.php');
                } catch (PDOException $e) {
                    $errors[] = 'Database error updating password.';
                }
            }
        }
    }
}

$pageTitle = 'My Profile';
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
                    <h2 class="fw-bold text-dark mb-1">Student Profile &amp; Settings</h2>
                    <p class="text-muted small mb-0">Manage your personal information, contact info, and login security.</p>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger mb-4">
                    <h6 class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Action Required:</h6>
                    <ul class="mb-0 ps-3 small">
                        <?php foreach ($errors as $err): ?>
                            <li><?php echo e($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Profile Information Card -->
                <div class="col-lg-7">
                    <div class="custom-card">
                        <div class="custom-card-header">
                            <h5 class="mb-0 fw-bold"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Personal Details</h5>
                        </div>
                        <div class="custom-card-body">
                            <form method="POST" action="<?php echo e($_SERVER['PHP_SELF']); ?>">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="update_profile">

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold text-secondary">Email Address</label>
                                    <input type="email" class="form-control bg-light" value="<?php echo e($user['email']); ?>" readonly disabled>
                                    <div class="form-text small">Email cannot be changed directly. Contact admin if you need an email update.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="name" class="form-label small fw-semibold text-secondary">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo e($user['name']); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="phone" class="form-label small fw-semibold text-secondary">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo e($user['phone'] ?? ''); ?>" placeholder="+91 ...">
                                </div>

                                <div class="mb-4">
                                    <label for="bio" class="form-label small fw-semibold text-secondary">Bio / Academic Notes</label>
                                    <textarea class="form-control" id="bio" name="bio" rows="3" placeholder="e.g. Roll No, Department, Year..."><?php echo e($user['bio'] ?? ''); ?></textarea>
                                </div>

                                <button type="submit" class="btn btn-primary shadow-sm">
                                    <i class="bi bi-save me-1"></i>Save Profile Changes
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Account Security & Password Change -->
                <div class="col-lg-5">
                    <div class="custom-card mb-4">
                        <div class="custom-card-header">
                            <h5 class="mb-0 fw-bold"><i class="bi bi-shield-lock me-2 text-danger"></i>Change Password</h5>
                        </div>
                        <div class="custom-card-body">
                            <form method="POST" action="<?php echo e($_SERVER['PHP_SELF']); ?>">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="change_password">

                                <div class="mb-3">
                                    <label for="current_password" class="form-label small fw-semibold text-secondary">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>

                                <div class="mb-3">
                                    <label for="new_password" class="form-label small fw-semibold text-secondary">New Password (Min 6 chars)</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                </div>

                                <div class="mb-4">
                                    <label for="confirm_password" class="form-label small fw-semibold text-secondary">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>

                                <button type="submit" class="btn btn-outline-danger w-100">
                                    <i class="bi bi-key-fill me-1"></i>Update Password
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Meta info -->
                    <div class="card border bg-light rounded-3">
                        <div class="card-body small text-muted">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Role:</span>
                                <span class="badge bg-success text-uppercase"><?php echo e($user['role']); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Account Status:</span>
                                <span class="badge bg-primary"><?php echo e($user['status']); ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Member Since:</span>
                                <span><?php echo formatDate($user['created_at'], 'd M Y'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


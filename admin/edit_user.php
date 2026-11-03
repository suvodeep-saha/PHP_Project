<?php
/**
 * Administrator Edit User (CRUD Update)
 * Online Assignment Submission System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAuth('admin');

$adminId = (int)$_SESSION['user_id'];
$targetUserId = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));

if (!$targetUserId) {
    setFlash('warning', 'Invalid user ID.');
    redirect('admin/users.php');
}

$pdo = getDBConnection();

// Fetch target user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $targetUserId]);
$targetUser = $stmt->fetch();

if (!$targetUser) {
    setFlash('danger', 'User not found.');
    redirect('admin/users.php');
}

$errors = [];
$name = $targetUser['name'];
$email = $targetUser['email'];
$role = $targetUser['role'];
$status = $targetUser['status'];
$phone = $targetUser['phone'] ?? '';
$bio = $targetUser['bio'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'CSRF validation failed. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? 'student');
        $status = trim($_POST['status'] ?? 'active');
        $phone = trim($_POST['phone'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';

        if (empty($name) || strlen($name) < 3) {
            $errors[] = 'Name must be at least 3 characters.';
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email is required.';
        }

        if (!in_array($role, ['admin', 'teacher', 'student'], true)) {
            $errors[] = 'Invalid role.';
        }

        if (!in_array($status, ['active', 'inactive'], true)) {
            $errors[] = 'Invalid status.';
        }

        // Prevent self-deactivation or demoting own admin role
        if ($targetUserId === $adminId) {
            if ($status !== 'active') {
                $errors[] = 'You cannot deactivate your own active administrator account.';
            }
            if ($role !== 'admin') {
                $errors[] = 'You cannot demote your own administrator privileges.';
            }
        }

        // Check email uniqueness excluding this user
        if (empty($errors)) {
            $stmtEmail = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1");
            $stmtEmail->execute(['email' => $email, 'id' => $targetUserId]);
            if ($stmtEmail->fetch()) {
                $errors[] = 'Another user already exists with this email address.';
            }
        }

        if (empty($errors)) {
            try {
                // If password reset provided
                if (!empty($newPassword)) {
                    if (strlen($newPassword) < 6) {
                        $errors[] = 'New password must be at least 6 characters.';
                    } else {
                        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
                        $stmtUpdate = $pdo->prepare("
                            UPDATE users 
                            SET name = :name, email = :email, role = :role, status = :status, 
                                phone = :phone, bio = :bio, password = :pass, updated_at = NOW()
                            WHERE id = :id
                        ");
                        $stmtUpdate->execute([
                            'name'   => $name,
                            'email'  => $email,
                            'role'   => $role,
                            'status' => $status,
                            'phone'  => !empty($phone) ? $phone : null,
                            'bio'    => !empty($bio) ? $bio : null,
                            'pass'   => $hash,
                            'id'     => $targetUserId,
                        ]);
                    }
                } else {
                    $stmtUpdate = $pdo->prepare("
                        UPDATE users 
                        SET name = :name, email = :email, role = :role, status = :status, 
                            phone = :phone, bio = :bio, updated_at = NOW()
                        WHERE id = :id
                    ");
                    $stmtUpdate->execute([
                        'name'   => $name,
                        'email'  => $email,
                        'role'   => $role,
                        'status' => $status,
                        'phone'  => !empty($phone) ? $phone : null,
                        'bio'    => !empty($bio) ? $bio : null,
                        'id'     => $targetUserId,
                    ]);
                }

                if (empty($errors)) {
                    // Update session if editing self
                    if ($targetUserId === $adminId) {
                        $_SESSION['user_name'] = $name;
                        $_SESSION['user_email'] = $email;
                    }

                    setFlash('success', 'User account for "' . e($name) . '" updated successfully!');
                    redirect('admin/users.php');
                }
            } catch (PDOException $e) {
                $errors[] = 'Database error updating user: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Edit User: ' . $targetUser['name'];
$isDashboard = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <?php echo renderFlashMessages(); ?>

            <div class="mb-3">
                <a href="<?php echo BASE_URL; ?>/admin/users.php" class="text-decoration-none text-muted small">
                    <i class="bi bi-arrow-left me-1"></i>Back to User List
                </a>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="custom-card">
                        <div class="custom-card-header">
                            <div>
                                <h4 class="fw-bold text-dark mb-0"><i class="bi bi-pencil-square me-2 text-primary"></i>Modify User Account</h4>
                                <p class="text-muted small mb-0">Update account role, status, and reset security credentials.</p>
                            </div>
                            <span class="badge bg-light text-dark border">User ID: #<?php echo (int)$targetUserId; ?></span>
                        </div>

                        <div class="custom-card-body">
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger mb-4">
                                    <h6 class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Please resolve:</h6>
                                    <ul class="mb-0 ps-3 small">
                                        <?php foreach ($errors as $err): ?>
                                            <li><?php echo e($err); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="<?php echo e($_SERVER['PHP_SELF']); ?>?id=<?php echo (int)$targetUserId; ?>">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo (int)$targetUserId; ?>">

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="name" class="form-label small fw-semibold text-secondary">Full Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="name" name="name" value="<?php echo e($name); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email" class="form-label small fw-semibold text-secondary">Email Address <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo e($email); ?>" required>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="role" class="form-label small fw-semibold text-secondary">Assigned Role <span class="text-danger">*</span></label>
                                        <select class="form-select" id="role" name="role" required>
                                            <option value="student" <?php echo $role === 'student' ? 'selected' : ''; ?>>Student</option>
                                            <option value="teacher" <?php echo $role === 'teacher' ? 'selected' : ''; ?>>Teacher / Faculty</option>
                                            <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="status" class="form-label small fw-semibold text-secondary">Account Status <span class="text-danger">*</span></label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active (Can Login)</option>
                                            <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive (Deactivated)</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="phone" class="form-label small fw-semibold text-secondary">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo e($phone); ?>" placeholder="+91 ...">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="new_password" class="form-label small fw-semibold text-secondary">Reset Password (Optional)</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Leave empty to keep existing password">
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label for="bio" class="form-label small fw-semibold text-secondary">Notes / Biography</label>
                                    <textarea class="form-control" id="bio" name="bio" rows="3"><?php echo e($bio); ?></textarea>
                                </div>

                                <div class="d-flex justify-content-between align-items-center border-top pt-3">
                                    <a href="<?php echo BASE_URL; ?>/admin/users.php" class="btn btn-outline-secondary">
                                        Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary shadow-sm">
                                        <i class="bi bi-save me-1"></i>Save User Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


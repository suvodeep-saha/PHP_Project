<?php
/**
 * Administrator User Management (CRUD Read & Create)
 * Online Assignment Submission System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAuth('admin');

$pdo = getDBConnection();
$currentAdminId = (int)$_SESSION['user_id'];

$search = trim($_GET['search'] ?? '');
$filterRole = trim($_GET['role'] ?? 'all');
$filterStatus = trim($_GET['status'] ?? 'all');

$errors = [];

// Handle "Add User" POST form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token invalid (CSRF).';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? 'student');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($name) || strlen($name) < 3) {
            $errors[] = 'Name must be at least 3 characters.';
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please provide a valid email address.';
        }

        if (!in_array($role, ['admin', 'teacher', 'student'], true)) {
            $errors[] = 'Invalid role selected.';
        }

        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }

        // Duplicate check
        if (empty($errors)) {
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $stmtCheck->execute(['email' => $email]);
            if ($stmtCheck->fetch()) {
                $errors[] = 'A user with this email address already exists.';
            }
        }

        if (empty($errors)) {
            try {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmtAdd = $pdo->prepare("INSERT INTO users (name, email, password, role, phone, status) VALUES (:name, :email, :pass, :role, :phone, 'active')");
                $stmtAdd->execute([
                    'name'  => $name,
                    'email' => $email,
                    'pass'  => $hash,
                    'role'  => $role,
                    'phone' => !empty($phone) ? $phone : null,
                ]);

                setFlash('success', 'User "' . e($name) . '" successfully created with role: ' . strtoupper($role) . '.');
                redirect('admin/users.php');
            } catch (PDOException $e) {
                $errors[] = 'Database insertion error: ' . $e->getMessage();
            }
        }
    }
}

// Build query
$sql = "
    SELECT u.*,
           (SELECT COUNT(*) FROM assignments a WHERE a.teacher_id = u.id) AS assignments_count,
           (SELECT COUNT(*) FROM submissions s WHERE s.student_id = u.id) AS submissions_count
    FROM users u
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (u.name LIKE :search OR u.email LIKE :search OR u.phone LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

if (in_array(strtolower($filterRole), ['admin', 'teacher', 'student'], true)) {
    $sql .= " AND u.role = :role";
    $params['role'] = strtolower($filterRole);
}

if (in_array(strtolower($filterStatus), ['active', 'inactive'], true)) {
    $sql .= " AND u.status = :status";
    $params['status'] = strtolower($filterStatus);
}

$sql .= " ORDER BY u.created_at DESC";

$users = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    // Handled
}

$pageTitle = 'Manage Users';
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
                    <h2 class="fw-bold text-dark mb-1">User Account Management</h2>
                    <p class="text-muted small mb-0">Manage system roles, account states, and provision new user accounts.</p>
                </div>
                <div class="mt-3 mt-md-0">
                    <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="bi bi-person-plus-fill me-1"></i>Add New User
                    </button>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger mb-4">
                    <h6 class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Error creating user:</h6>
                    <ul class="mb-0 ps-3 small">
                        <?php foreach ($errors as $err): ?>
                            <li><?php echo e($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Filter & Search Bar -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-body p-3">
                    <form method="GET" action="<?php echo e($_SERVER['PHP_SELF']); ?>" class="row g-2 align-items-center">
                        <div class="col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" name="search" class="form-control border-start-0" placeholder="Search by name, email, or phone..." value="<?php echo e($search); ?>" data-table-filter="#usersTable">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <select name="role" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $filterRole === 'all' ? 'selected' : ''; ?>>All Roles</option>
                                <option value="student" <?php echo $filterRole === 'student' ? 'selected' : ''; ?>>Students</option>
                                <option value="teacher" <?php echo $filterRole === 'teacher' ? 'selected' : ''; ?>>Teachers / Faculty</option>
                                <option value="admin" <?php echo $filterRole === 'admin' ? 'selected' : ''; ?>>Administrators</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="col-md-2 d-grid">
                            <a href="<?php echo e($_SERVER['PHP_SELF']); ?>" class="btn btn-outline-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users Table Card -->
            <div class="custom-card">
                <div class="custom-card-header">
                    <h5 class="mb-0 fw-bold">Registered Users (<?php echo count($users); ?>)</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-custom align-middle" id="usersTable">
                        <thead>
                            <tr>
                                <th>User Profile</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Activity Metric</th>
                                <th>Joined</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($users)): ?>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary-subtle text-primary fw-bold rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width: 40px; height: 40px;">
                                                    <?php echo strtoupper(substr($u['name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark"><?php echo e($u['name']); ?></div>
                                                    <small class="text-muted"><?php echo e($u['email']); ?></small>
                                                    <?php if (!empty($u['phone'])): ?>
                                                        <span class="small text-secondary ms-2">&bull; <?php echo e($u['phone']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                                $badgeClass = match ($u['role']) {
                                                    'admin'   => 'bg-danger',
                                                    'teacher' => 'bg-primary',
                                                    default   => 'bg-success',
                                                };
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?> text-uppercase"><?php echo e($u['role']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $u['status'] === 'active' ? 'success-subtle text-success border border-success-subtle' : 'danger-subtle text-danger border border-danger-subtle'; ?>">
                                                <?php echo e($u['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($u['role'] === 'teacher'): ?>
                                                <span class="small text-muted"><i class="bi bi-collection me-1"></i><?php echo (int)$u['assignments_count']; ?> Assignments</span>
                                            <?php elseif ($u['role'] === 'student'): ?>
                                                <span class="small text-muted"><i class="bi bi-cloud-arrow-up me-1"></i><?php echo (int)$u['submissions_count']; ?> Submissions</span>
                                            <?php else: ?>
                                                <span class="small text-muted">Admin Access</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="small text-muted"><?php echo formatDate($u['created_at'], 'd M Y'); ?></span>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?php echo BASE_URL; ?>/admin/edit_user.php?id=<?php echo (int)$u['id']; ?>" class="btn btn-outline-secondary" title="Edit User">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php if ((int)$u['id'] !== $currentAdminId): ?>
                                                    <a href="<?php echo BASE_URL; ?>/admin/delete_user.php?id=<?php echo (int)$u['id']; ?>" class="btn btn-outline-danger btn-confirm-delete" data-item-name="user '<?php echo e($u['name']); ?>'" title="Delete User">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="bi bi-people fs-1 d-block mb-2"></i>
                                        No users found matching the filter criteria.
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

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?php echo e($_SERVER['PHP_SELF']); ?>">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create_user">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="addUserModalLabel"><i class="bi bi-person-plus-fill me-2"></i>Provision New User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required placeholder="e.g. John Doe">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" required placeholder="user@college.edu">
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Role <span class="text-danger">*</span></label>
                            <select class="form-select" name="role" required>
                                <option value="student">Student</option>
                                <option value="teacher">Teacher / Faculty</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">Phone Number</label>
                            <input type="tel" class="form-control" name="phone" placeholder="+91 ...">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Temporary Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" required minlength="6" placeholder="Min 6 characters">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary shadow-sm"><i class="bi bi-check2 me-1"></i>Create Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


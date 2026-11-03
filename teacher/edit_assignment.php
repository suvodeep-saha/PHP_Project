<?php
/**
 * Edit Assignment (CRUD Update)
 * Online Assignment Submission System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAuth('teacher');

$teacherId = (int)$_SESSION['user_id'];
$assignmentId = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));

if (!$assignmentId) {
    setFlash('warning', 'Invalid assignment selection.');
    redirect('teacher/assignments.php');
}

$pdo = getDBConnection();

// Fetch Assignment (must be owned by this teacher)
$stmt = $pdo->prepare("SELECT * FROM assignments WHERE id = :id AND teacher_id = :tid LIMIT 1");
$stmt->execute(['id' => $assignmentId, 'tid' => $teacherId]);
$assignment = $stmt->fetch();

if (!$assignment) {
    setFlash('danger', 'Assignment not found or permission denied.');
    redirect('teacher/assignments.php');
}

$errors = [];
$title = $assignment['title'];
$subject = $assignment['subject'];
$description = $assignment['description'];
$deadline = (new DateTime($assignment['deadline']))->format('Y-m-d\TH:i');
$maxMarks = $assignment['max_marks'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'CSRF validation failed. Please try again.';
    }

    $title = trim($_POST['title'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $deadline = trim($_POST['deadline'] ?? '');
    $maxMarks = (int)($_POST['max_marks'] ?? 100);

    if (empty($title) || strlen($title) < 3) {
        $errors[] = 'Title must be at least 3 characters.';
    }

    if (empty($subject) || strlen($subject) < 2) {
        $errors[] = 'Subject must be at least 2 characters.';
    }

    if (empty($description) || strlen($description) < 10) {
        $errors[] = 'Description must be at least 10 characters.';
    }

    if (empty($deadline)) {
        $errors[] = 'Please specify a valid deadline.';
    }

    if ($maxMarks < 1 || $maxMarks > 500) {
        $errors[] = 'Max marks must be between 1 and 500.';
    }

    // Handle Optional Replacement File Upload
    $newFileName = $assignment['file'];
    if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['assignment_file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload error occurred.';
        }

        if ($file['size'] > MAX_FILE_SIZE_BYTES) {
            $errors[] = 'File exceeds maximum limit of 10 MB.';
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_EXTENSIONS, true)) {
            $errors[] = 'Invalid file extension (.' . e($ext) . ').';
        }

        if (empty($errors)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, ALLOWED_MIME_TYPES, true)) {
                $errors[] = 'File MIME type validation failed.';
            } else {
                $safeName = sprintf('assign_t%d_%s_%s.%s', $teacherId, time(), bin2hex(random_bytes(3)), $ext);
                $destination = UPLOAD_PATH_ASSIGNMENTS . $safeName;

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    // Delete old file if present
                    if (!empty($assignment['file']) && file_exists(UPLOAD_PATH_ASSIGNMENTS . $assignment['file'])) {
                        @unlink(UPLOAD_PATH_ASSIGNMENTS . $assignment['file']);
                    }
                    $newFileName = $safeName;
                } else {
                    $errors[] = 'Failed to move new file to server storage.';
                }
            }
        }
    }

    // Execute Update
    if (empty($errors)) {
        try {
            $sqlUpdate = "UPDATE assignments 
                          SET title = :title, subject = :subject, description = :desc, 
                              deadline = :deadline, max_marks = :max_marks, file = :file, updated_at = NOW()
                          WHERE id = :id AND teacher_id = :tid";
            $stmtUpdate = $pdo->prepare($sqlUpdate);
            $stmtUpdate->execute([
                'title'     => $title,
                'subject'   => $subject,
                'desc'      => $description,
                'deadline'  => $deadline,
                'max_marks' => $maxMarks,
                'file'      => $newFileName,
                'id'        => $assignmentId,
                'tid'       => $teacherId,
            ]);

            setFlash('success', 'Assignment updated successfully!');
            redirect('teacher/assignments.php');
        } catch (PDOException $e) {
            $errors[] = 'Database update error: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Edit Assignment';
$isDashboard = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <?php echo renderFlashMessages(); ?>

            <div class="mb-3">
                <a href="<?php echo BASE_URL; ?>/teacher/assignments.php" class="text-decoration-none text-muted small">
                    <i class="bi bi-arrow-left me-1"></i>Back to My Assignments
                </a>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <div class="custom-card">
                        <div class="custom-card-header">
                            <div>
                                <h4 class="fw-bold text-dark mb-0"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Assignment</h4>
                                <p class="text-muted small mb-0">Update assignment details, deadlines, or replacement material.</p>
                            </div>
                        </div>

                        <div class="custom-card-body">
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger mb-4">
                                    <h6 class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Please fix:</h6>
                                    <ul class="mb-0 ps-3 small">
                                        <?php foreach ($errors as $err): ?>
                                            <li><?php echo e($err); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="<?php echo e($_SERVER['PHP_SELF']); ?>?id=<?php echo (int)$assignmentId; ?>" enctype="multipart/form-data">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo (int)$assignmentId; ?>">

                                <div class="row mb-3">
                                    <div class="col-md-8">
                                        <label for="title" class="form-label fw-semibold text-secondary small">Assignment Title <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="title" name="title" value="<?php echo e($title); ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="subject" class="form-label fw-semibold text-secondary small">Course / Subject <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="subject" name="subject" value="<?php echo e($subject); ?>" required>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="deadline" class="form-label fw-semibold text-secondary small">Submission Deadline <span class="text-danger">*</span></label>
                                        <input type="datetime-local" class="form-control" id="deadline" name="deadline" value="<?php echo e($deadline); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="max_marks" class="form-label fw-semibold text-secondary small">Maximum Marks <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="max_marks" name="max_marks" value="<?php echo (int)$maxMarks; ?>" min="1" max="500" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label fw-semibold text-secondary small">Instructions &amp; Description <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="description" name="description" rows="5" required><?php echo e($description); ?></textarea>
                                </div>

                                <div class="mb-4">
                                    <label for="assignment_file" class="form-label fw-semibold text-secondary small">Attached Question Sheet</label>
                                    <?php if (!empty($assignment['file'])): ?>
                                        <div class="p-2 mb-2 bg-light rounded border d-flex align-items-center justify-content-between small">
                                            <span>Current file: <strong><?php echo e(basename($assignment['file'])); ?></strong></span>
                                            <a href="<?php echo BASE_URL; ?>/download.php?type=assignment&id=<?php echo (int)$assignment['id']; ?>" class="btn btn-sm btn-link py-0 text-decoration-none">
                                                Download Current File
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" class="form-control" id="assignment_file" name="assignment_file" data-max-size="10485760" data-allowed-ext="pdf,docx,doc,zip,txt,png,jpg,jpeg">
                                    <div class="form-text small text-muted">
                                        Leave empty to keep existing file. Upload a new document to replace it.
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center border-top pt-3">
                                    <a href="<?php echo BASE_URL; ?>/teacher/assignments.php" class="btn btn-outline-secondary">
                                        Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary btn-lg shadow-sm">
                                        <i class="bi bi-save me-2"></i>Save Changes
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


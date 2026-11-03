<?php
/**
 * Create New Assignment
 * Online Assignment Submission System
 * 
 * Demonstrates:
 * - Form processing with POST
 * - Server-side validation of dates and constraints
 * - Optional file upload handling with MIME validation
 * - Database insertion via PDO prepared statements
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAuth('teacher');

$teacherId = (int)$_SESSION['user_id'];
$pdo = getDBConnection();

$errors = [];
$title = '';
$subject = '';
$description = '';
$deadline = '';
$maxMarks = 100;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token invalid (CSRF). Please reload and try again.';
    }

    $title = trim($_POST['title'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $deadline = trim($_POST['deadline'] ?? '');
    $maxMarks = (int)($_POST['max_marks'] ?? 100);

    // Validation
    if (empty($title) || strlen($title) < 3) {
        $errors[] = 'Assignment title must be at least 3 characters long.';
    }

    if (empty($subject) || strlen($subject) < 2) {
        $errors[] = 'Subject name must be at least 2 characters long.';
    }

    if (empty($description) || strlen($description) < 10) {
        $errors[] = 'Description / instructions must be at least 10 characters long.';
    }

    if (empty($deadline)) {
        $errors[] = 'Please specify a submission deadline.';
    } else {
        $deadlineDt = new DateTime($deadline);
        $now = new DateTime();
        if ($deadlineDt <= $now) {
            $errors[] = 'The deadline must be a future date and time.';
        }
    }

    if ($maxMarks < 1 || $maxMarks > 500) {
        $errors[] = 'Maximum marks must be between 1 and 500.';
    }

    // Optional Question File Upload
    $uploadedFileName = null;
    if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['assignment_file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload failed with error code: ' . $file['error'];
        }

        if ($file['size'] > MAX_FILE_SIZE_BYTES) {
            $errors[] = 'Attached question file exceeds the 10 MB limit.';
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_EXTENSIONS, true)) {
            $errors[] = 'Invalid file extension (.' . e($ext) . '). Allowed: ' . implode(', ', ALLOWED_EXTENSIONS);
        }

        if (empty($errors)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, ALLOWED_MIME_TYPES, true)) {
                $errors[] = 'File MIME type validation failed (' . e($mime) . ').';
            } else {
                $safeName = sprintf('assign_t%d_%s_%s.%s', $teacherId, time(), bin2hex(random_bytes(3)), $ext);
                $destination = UPLOAD_PATH_ASSIGNMENTS . $safeName;

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $uploadedFileName = $safeName;
                } else {
                    $errors[] = 'Failed to move attached file to server directory.';
                }
            }
        }
    }

    // Insert into MySQL
    if (empty($errors)) {
        try {
            $sql = "INSERT INTO assignments (teacher_id, title, subject, description, deadline, file, max_marks)
                    VALUES (:tid, :title, :subject, :desc, :deadline, :file, :max_marks)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'tid'       => $teacherId,
                'title'     => $title,
                'subject'   => $subject,
                'desc'      => $description,
                'deadline'  => $deadline,
                'file'      => $uploadedFileName,
                'max_marks' => $maxMarks,
            ]);

            setFlash('success', 'Assignment "' . e($title) . '" created and published successfully!');
            redirect('teacher/assignments.php');
        } catch (PDOException $e) {
            if ($uploadedFileName && file_exists(UPLOAD_PATH_ASSIGNMENTS . $uploadedFileName)) {
                @unlink(UPLOAD_PATH_ASSIGNMENTS . $uploadedFileName);
            }
            $errors[] = 'Database insertion error: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Create Assignment';
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
                                <h4 class="fw-bold text-dark mb-0"><i class="bi bi-plus-circle me-2 text-primary"></i>Create New Assignment</h4>
                                <p class="text-muted small mb-0">Publish an academic task with deadlines and instructions for your students.</p>
                            </div>
                        </div>

                        <div class="custom-card-body">
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger mb-4">
                                    <h6 class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Please fix the following:</h6>
                                    <ul class="mb-0 ps-3 small">
                                        <?php foreach ($errors as $err): ?>
                                            <li><?php echo e($err); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="<?php echo e($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data">
                                <?php echo csrf_field(); ?>

                                <div class="row mb-3">
                                    <div class="col-md-8">
                                        <label for="title" class="form-label fw-semibold text-secondary small">Assignment Title <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="title" name="title" value="<?php echo e($title); ?>" required placeholder="e.g. Relational Schema Design & Normalization">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="subject" class="form-label fw-semibold text-secondary small">Course / Subject <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="subject" name="subject" value="<?php echo e($subject); ?>" required placeholder="e.g. DBMS, Web Tech">
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
                                    <label for="description" class="form-label fw-semibold text-secondary small">Description &amp; Task Instructions <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="description" name="description" rows="5" required placeholder="Detail the questions, formatting expectations, required deliverables, and evaluation rubric..."><?php echo e($description); ?></textarea>
                                </div>

                                <div class="mb-4">
                                    <label for="assignment_file" class="form-label fw-semibold text-secondary small">Attach Question Sheet / Reference Material (Optional)</label>
                                    <input type="file" class="form-control" id="assignment_file" name="assignment_file" data-max-size="10485760" data-allowed-ext="pdf,docx,doc,zip,txt,png,jpg,jpeg">
                                    <div class="form-text small text-muted">
                                        Accepted: <strong>PDF, DOCX, ZIP, TXT</strong>. Max size: 10 MB.
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center border-top pt-3">
                                    <a href="<?php echo BASE_URL; ?>/teacher/assignments.php" class="btn btn-outline-secondary">
                                        Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary btn-lg shadow-sm">
                                        <i class="bi bi-send-fill me-2"></i>Publish Assignment
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


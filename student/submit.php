<?php
/**
 * Submit Assignment Solution Page
 * Online Assignment Submission System
 * 
 * Demonstrates:
 * - HTML Multipart Form Handling (enctype="multipart/form-data")
 * - File Upload Validation (Error codes, size limits, extension whitelisting)
 * - Server-side MIME type verification using finfo_file()
 * - Unique filename generation and secure storage
 * - SQL INSERT / UPDATE prepared statements
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAuth('student');

$studentId = (int)$_SESSION['user_id'];
$assignmentId = (int)($_GET['id'] ?? ($_POST['assignment_id'] ?? 0));

if (!$assignmentId) {
    setFlash('warning', 'Please select an assignment to submit.');
    redirect('student/assignments.php');
}

$pdo = getDBConnection();

// Fetch Assignment
$stmt = $pdo->prepare("SELECT * FROM assignments WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $assignmentId]);
$assignment = $stmt->fetch();

if (!$assignment) {
    setFlash('danger', 'Assignment not found.');
    redirect('student/assignments.php');
}

// Check if already submitted
$stmtSub = $pdo->prepare("SELECT * FROM submissions WHERE assignment_id = :aid AND student_id = :sid LIMIT 1");
$stmtSub->execute(['aid' => $assignmentId, 'sid' => $studentId]);
$existingSubmission = $stmtSub->fetch();

$errors = [];
$studentNotes = $existingSubmission['student_notes'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF Token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token (CSRF). Please try again.';
    }

    $studentNotes = trim($_POST['student_notes'] ?? '');

    // 2. Validate File Upload
    if (!isset($_FILES['submission_file']) || $_FILES['submission_file']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Please select a solution file to upload.';
    } else {
        $file = $_FILES['submission_file'];

        // Check for PHP upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = match ($file['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Uploaded file exceeds the maximum allowed file size limit (10 MB).',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded. Please try again.',
                default => 'File upload error occurred (Code: ' . $file['error'] . ').',
            };
        }

        // Check byte size
        if ($file['size'] > MAX_FILE_SIZE_BYTES) {
            $errors[] = 'File is too large. Maximum size allowed is 10 MB.';
        }

        // Check file extension
        $originalExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($originalExt, ALLOWED_EXTENSIONS, true)) {
            $errors[] = 'Invalid file extension (.' . e($originalExt) . '). Allowed: ' . implode(', ', ALLOWED_EXTENSIONS);
        }

        // Check genuine MIME type using finfo (server-side MIME inspection)
        if (empty($errors)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, ALLOWED_MIME_TYPES, true)) {
                $errors[] = 'File MIME type validation failed (' . e($mimeType) . '). Please upload a valid document or archive.';
            }
        }
    }

    // 3. Process Upload & Store in Database
    if (empty($errors)) {
        // Generate secure unique filename
        $safeFileName = sprintf(
            'sub_a%d_s%d_%s_%s.%s',
            $assignmentId,
            $studentId,
            time(),
            bin2hex(random_bytes(4)),
            $originalExt
        );

        $destination = UPLOAD_PATH_SUBMISSIONS . $safeFileName;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            try {
                if ($existingSubmission) {
                    // Update existing submission & reset status to Pending
                    $sqlUpdate = "UPDATE submissions 
                                  SET file = :file, student_notes = :notes, status = 'Pending', submit_date = NOW()
                                  WHERE id = :id";
                    $stmtUpdate = $pdo->prepare($sqlUpdate);
                    $stmtUpdate->execute([
                        'file'  => $safeFileName,
                        'notes' => $studentNotes,
                        'id'    => $existingSubmission['id'],
                    ]);

                    // Remove old file if exists
                    if (!empty($existingSubmission['file']) && file_exists(UPLOAD_PATH_SUBMISSIONS . $existingSubmission['file'])) {
                        @unlink(UPLOAD_PATH_SUBMISSIONS . $existingSubmission['file']);
                    }

                    setFlash('success', 'Your submission has been updated and resubmitted successfully for review.');
                } else {
                    // Insert brand new submission
                    $sqlInsert = "INSERT INTO submissions (student_id, assignment_id, file, student_notes, status, submit_date)
                                  VALUES (:sid, :aid, :file, :notes, 'Pending', NOW())";
                    $stmtInsert = $pdo->prepare($sqlInsert);
                    $stmtInsert->execute([
                        'sid'   => $studentId,
                        'aid'   => $assignmentId,
                        'file'  => $safeFileName,
                        'notes' => $studentNotes,
                    ]);

                    setFlash('success', 'Assignment submitted successfully! Your instructor has been notified.');
                }

                redirect('student/submissions.php');
            } catch (PDOException $e) {
                // If database failed, clean up uploaded file
                @unlink($destination);
                $errors[] = 'Failed to save submission in database: ' . $e->getMessage();
            }
        } else {
            $errors[] = 'Failed to move uploaded file to server storage.';
        }
    }
}

$pageTitle = 'Submit Assignment: ' . $assignment['title'];
$isDashboard = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <?php echo renderFlashMessages(); ?>

            <div class="mb-3">
                <a href="<?php echo BASE_URL; ?>/student/assignment_details.php?id=<?php echo (int)$assignment['id']; ?>" class="text-decoration-none text-muted small">
                    <i class="bi bi-arrow-left me-1"></i>Back to Assignment Details
                </a>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="custom-card">
                        <div class="custom-card-header">
                            <div>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle mb-1"><?php echo e($assignment['subject']); ?></span>
                                <h4 class="fw-bold mb-0">Upload Assignment Submission</h4>
                            </div>
                            <?php echo renderDeadlineBadge($assignment['deadline']); ?>
                        </div>

                        <div class="custom-card-body">
                            <!-- Assignment summary reminder -->
                            <div class="p-3 bg-light rounded-3 border mb-4">
                                <h6 class="fw-bold text-dark mb-1"><?php echo e($assignment['title']); ?></h6>
                                <div class="d-flex flex-wrap gap-3 small text-muted">
                                    <span><i class="bi bi-calendar-event me-1"></i>Deadline: <?php echo formatDate($assignment['deadline']); ?></span>
                                    <span><i class="bi bi-award me-1"></i>Max Marks: <?php echo (int)$assignment['max_marks']; ?></span>
                                </div>
                            </div>

                            <?php if ($existingSubmission): ?>
                                <div class="alert alert-warning mb-4">
                                    <i class="bi bi-info-circle-fill me-2"></i>
                                    <strong>Resubmission Notice:</strong> You have previously submitted file <code><?php echo e($existingSubmission['file']); ?></code>. Uploading a new file will overwrite your prior submission and set status back to <strong>Pending</strong>.
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger mb-4">
                                    <h6 class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Submission Error:</h6>
                                    <ul class="mb-0 ps-3 small">
                                        <?php foreach ($errors as $err): ?>
                                            <li><?php echo e($err); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="<?php echo e($_SERVER['PHP_SELF']); ?>?id=<?php echo (int)$assignment['id']; ?>" enctype="multipart/form-data">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="assignment_id" value="<?php echo (int)$assignment['id']; ?>">

                                <div class="mb-4">
                                    <label for="submission_file" class="form-label fw-bold text-dark">
                                        Select File to Upload <span class="text-danger">*</span>
                                    </label>
                                    <input type="file" 
                                           class="form-control form-control-lg" 
                                           id="submission_file" 
                                           name="submission_file" 
                                           required 
                                           data-max-size="10485760" 
                                           data-allowed-ext="pdf,docx,doc,zip,txt,png,jpg,jpeg">
                                    <div class="form-text small text-muted mt-2">
                                        <i class="bi bi-shield-check text-success me-1"></i>
                                        Allowed file formats: <strong>PDF, DOCX, DOC, ZIP, TXT</strong>. Max file size: <strong>10 MB</strong>.
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label for="student_notes" class="form-label fw-semibold text-dark">
                                        Comments or Notes for Instructor (Optional)
                                    </label>
                                    <textarea class="form-control" id="student_notes" name="student_notes" rows="4" placeholder="Mention any specific assumptions, execution commands, or instructions for the teacher..."><?php echo e($studentNotes); ?></textarea>
                                </div>

                                <div class="d-flex justify-content-between align-items-center">
                                    <a href="<?php echo BASE_URL; ?>/student/assignment_details.php?id=<?php echo (int)$assignment['id']; ?>" class="btn btn-outline-secondary">
                                        Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary btn-lg shadow-sm">
                                        <i class="bi bi-cloud-arrow-up-fill me-2"></i>Submit Assignment
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


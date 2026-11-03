<?php
/**
 * Online Assignment Submission System - Automated Database Setup
 * 
 * This installer reads `database/schema.sql`, creates the database,
 * provisions all tables, foreign keys, indexes, and seeds test accounts.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$host = '127.0.0.1';
$port = '3306';
$user = 'root';
$pass = '';
$dbname = 'online_assignment_system';
$sqlFile = __DIR__ . '/database/schema.sql';

$messages = [];
$status = 'ready'; // ready, success, error

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' || (php_sapi_name() === 'cli')) {
    try {
        // Step 1: Connect to MySQL server (no database selected yet)
        $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $messages[] = ['type' => 'success', 'text' => "Connected to MySQL server at {$host}:{$port} successfully."];

        // Step 2: Read schema.sql
        if (!file_exists($sqlFile)) {
            throw new Exception("Schema file not found at: {$sqlFile}");
        }
        $sql = file_get_contents($sqlFile);
        $messages[] = ['type' => 'success', 'text' => "Loaded SQL schema file (" . strlen($sql) . " bytes)."];

        // Step 3: Execute SQL queries
        // Split SQL statements safely
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        $pdo->exec("USE `{$dbname}`;");
        $messages[] = ['type' => 'success', 'text' => "Database `{$dbname}` created and selected."];

        // Execute schema
        $pdo->exec($sql);
        $messages[] = ['type' => 'success', 'text' => "Tables (`users`, `assignments`, `submissions`) created and seeded with test records!"];

        // Verification query
        $stmtUsers = $pdo->query("SELECT COUNT(*) AS count FROM users");
        $userCount = $stmtUsers->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

        $stmtAssign = $pdo->query("SELECT COUNT(*) AS count FROM assignments");
        $assignCount = $stmtAssign->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

        $stmtSub = $pdo->query("SELECT COUNT(*) AS count FROM submissions");
        $subCount = $stmtSub->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

        $messages[] = ['type' => 'info', 'text' => "Verification: {$userCount} users, {$assignCount} assignments, and {$subCount} submissions initialized."];

        $status = 'success';
    } catch (Exception $e) {
        $status = 'error';
        $messages[] = ['type' => 'danger', 'text' => "Error: " . $e->getMessage()];
    }
}

if (php_sapi_name() === 'cli') {
    echo "=== Online Assignment System - CLI Installer ===\n";
    foreach ($messages as $msg) {
        echo strtoupper($msg['type']) . ": " . $msg['text'] . "\n";
    }
    exit($status === 'success' ? 0 : 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup | Online Assignment Submission System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f0f4fd 0%, #e2e8f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px 0;
        }
        .setup-card {
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            max-width: 680px;
            width: 100%;
        }
        .setup-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: #ffffff;
            padding: 24px 30px;
        }
    </style>
</head>
<body>

<div class="setup-card">
    <div class="setup-header text-center">
        <h3 class="mb-1"><i class="bi bi-database-fill-gear me-2"></i>Database Setup Wizard</h3>
        <p class="mb-0 text-white-50 small">Online Assignment Submission System (PHP 8 & MySQL)</p>
    </div>

    <div class="p-4 p-md-5">
        <?php if ($status === 'ready'): ?>
            <p class="text-muted">
                Welcome! This wizard will automatically configure your local MySQL database, generate the required tables, and populate them with seed accounts and test assignments.
            </p>

            <div class="card bg-light border-0 mb-4">
                <div class="card-body">
                    <h6 class="card-title fw-bold text-dark"><i class="bi bi-sliders me-2"></i>Configuration Settings</h6>
                    <ul class="list-unstyled mb-0 small text-secondary">
                        <li><strong>Host:</strong> <?php echo htmlspecialchars($host . ':' . $port); ?></li>
                        <li><strong>Database Name:</strong> <?php echo htmlspecialchars($dbname); ?></li>
                        <li><strong>User:</strong> <?php echo htmlspecialchars($user); ?> (Default XAMPP)</li>
                        <li><strong>Character Set:</strong> utf8mb4 (Full Unicode support)</li>
                    </ul>
                </div>
            </div>

            <form method="POST">
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg shadow-sm">
                        <i class="bi bi-play-circle-fill me-2"></i>Install & Initialize Database
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary">Back to Home</a>
                </div>
            </form>

        <?php elseif ($status === 'success'): ?>
            <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-check-circle-fill fs-3 me-3 text-success"></i>
                <div>
                    <h5 class="alert-heading mb-1 fw-bold">Installation Completed Successfully!</h5>
                    <p class="mb-0 small">The database and all tables are ready for use.</p>
                </div>
            </div>

            <div class="card border mb-4">
                <div class="card-header bg-white fw-bold text-primary">
                    <i class="bi bi-key-fill me-2"></i>Default Demo Credentials
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 small align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Role</th>
                                <th>Email</th>
                                <th>Password</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge bg-danger">Admin</span></td>
                                <td><code>admin@assignments.edu</code></td>
                                <td><code>admin123</code></td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-primary">Teacher</span></td>
                                <td><code>prof.sharma@assignments.edu</code></td>
                                <td><code>teacher123</code></td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-success">Student</span></td>
                                <td><code>suvo@student.edu</code></td>
                                <td><code>student123</code></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="d-grid gap-2">
                <a href="login.php" class="btn btn-primary btn-lg shadow-sm">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Go to Login Page
                </a>
                <a href="index.php" class="btn btn-outline-secondary">Go to Homepage</a>
            </div>

        <?php elseif ($status === 'error'): ?>
            <div class="alert alert-danger mb-4">
                <h5 class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Installation Failed</h5>
                <?php foreach ($messages as $msg): ?>
                    <p class="mb-1 small"><?php echo htmlspecialchars($msg['text']); ?></p>
                <?php endforeach; ?>
            </div>

            <div class="card bg-light border-0 mb-4">
                <div class="card-body small text-muted">
                    <h6 class="fw-bold text-danger">Troubleshooting Steps:</h6>
                    <ol class="mb-0 ps-3">
                        <li>Make sure <strong>MySQL</strong> is started in your <strong>XAMPP Control Panel</strong>.</li>
                        <li>Verify port <code>3306</code> is open and not blocked by another service.</li>
                        <li>If you have set a MySQL root password, configure it in <code>config/database.php</code>.</li>
                    </ol>
                </div>
            </div>

            <form method="POST">
                <button type="submit" class="btn btn-danger w-100">
                    <i class="bi bi-arrow-repeat me-2"></i>Retry Installation
                </button>
            </form>
        <?php endif; ?>

        <?php if (!empty($messages) && $status !== 'ready'): ?>
            <div class="mt-4 border-top pt-3">
                <p class="small text-muted fw-bold mb-2">Process Logs:</p>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($messages as $msg): ?>
                        <li class="list-group-item px-0 py-1 border-0 text-<?php echo $msg['type'] === 'danger' ? 'danger' : ($msg['type'] === 'success' ? 'success' : 'secondary'); ?>">
                            <i class="bi bi-<?php echo $msg['type'] === 'danger' ? 'x-circle' : ($msg['type'] === 'success' ? 'check2' : 'info-circle'); ?> me-1"></i>
                            <?php echo htmlspecialchars($msg['text']); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>

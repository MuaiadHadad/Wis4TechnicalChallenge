<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Simple routing
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);
$path = str_replace('/api', '', $path);

// Composer autoload for AWS SDK
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

// Database connection
$host = 'mariadb';
$dbname = 'wis4_db';
$username = 'wis4_user';
$password = 'wis4_password';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Start session
session_start();

function requireAuth() {
    if (!($_SESSION['logged_in'] ?? false)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        return false;
    }
    return true;
}

function requireRole($role) {
    if (($_SESSION['role'] ?? '') !== $role) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => "Insufficient permissions. {$role} role required."]);
        return false;
    }
    return true;
}

// Helper: create S3 client for presigning using public endpoint (no network calls)
function createS3ClientForPresign() {
    $publicEndpoint = getenv('S3_PUBLIC_ENDPOINT') ?: (getenv('S3_ENDPOINT') ?: 'http://s3ninja:9000');
    $region = getenv('S3_REGION') ?: 'us-east-1';
    $key = getenv('S3_KEY') ?: 'AKIAIOSFODNN7EXAMPLE';
    $secret = getenv('S3_SECRET') ?: 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY';
    $usePathStyle = filter_var(getenv('S3_USE_PATH_STYLE') ?: 'true', FILTER_VALIDATE_BOOLEAN);

    return new Aws\S3\S3Client([
        'version' => 'latest',
        'region' => $region,
        'endpoint' => $publicEndpoint,
        'use_path_style_endpoint' => $usePathStyle,
        'credentials' => [
            'key' => $key,
            'secret' => $secret,
        ],
    ]);
}

// Helper: from stored file_path build a short-lived presigned URL
function buildPresignedUrlFromFilePath($filePath, $expires = '+1 hour') {
    if (!$filePath) return null;
    $parts = parse_url($filePath);
    if (!isset($parts['path'])) return null;
    $path = ltrim($parts['path'], '/');
    if ($path === '') return null;
    $segments = explode('/', $path);
    $bucket = array_shift($segments);
    if (!$bucket || empty($segments)) return null;
    $key = implode('/', $segments);

    try {
        $s3 = createS3ClientForPresign();
        $cmd = $s3->getCommand('GetObject', ['Bucket' => $bucket, 'Key' => $key]);
        $req = $s3->createPresignedRequest($cmd, $expires);
        return (string) $req->getUri();
    } catch (Throwable $e) {
        return null;
    }
}

// Pattern route: GET /tasks/show/{id}
if (preg_match('#^/tasks/show/(\d+)$#', $path, $m)) {
    if (!requireAuth()) { return; }
    $taskId = (int)$m[1];
    try {
        $stmt = $pdo->prepare("SELECT tasks.*, users.name AS user_name, users.email AS user_email, te.file_path AS execution_file_path, te.file_name AS execution_file_name, te.status AS execution_status, te.submitted_at AS execution_submitted_at FROM tasks JOIN users ON users.id = tasks.user_id LEFT JOIN task_execution te ON te.task_id = tasks.id WHERE tasks.id = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$task) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Task not found']);
            return;
        }
        if (!empty($task['execution_file_path'])) {
            $task['execution_file_url'] = buildPresignedUrlFromFilePath($task['execution_file_path']);
        }
        echo json_encode(['success' => true, 'data' => $task]);
        return;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }
}

switch ($path) {
    case '/':
        echo json_encode(['success' => true, 'message' => 'WIS4 API is running', 'timestamp' => date('Y-m-d H:i:s')]);
        break;

    case '/auth/login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';

            if (!$email || !$password) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Email and password are required']);
                exit();
            }

            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['logged_in'] = true;

                    echo json_encode([
                        'success' => true,
                        'message' => 'Login successful',
                        'user' => [
                            'id' => $user['id'],
                            'email' => $user['email'],
                            'name' => $user['name'],
                            'role' => $user['role']
                        ]
                    ]);
                } else {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
                }
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
        }
        break;

    case '/auth/check':
        echo json_encode([
            'authenticated' => $_SESSION['logged_in'] ?? false,
            'user' => $_SESSION['logged_in'] ?? false ? [
                'id' => $_SESSION['user_id'],
                'email' => $_SESSION['email'],
                'name' => $_SESSION['name'],
                'role' => $_SESSION['role']
            ] : null
        ]);
        break;

    case '/auth/logout':
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Logout successful']);
        break;

    // List collaborators (admin only)
    case '/tasks/collaborators':
        if (!requireAuth() || !requireRole('administrator')) { break; }
        try {
            $stmt = $pdo->query("SELECT id, name, email, role FROM users WHERE role = 'collaborator' ORDER BY name");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $users]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        break;

    // List all tasks (admin only) and Create task (admin only)
    case '/tasks/':
        if (!requireAuth() || !requireRole('administrator')) { break; }
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            try {
                $stmt = $pdo->query("SELECT tasks.*, users.name AS user_name, users.email AS user_email, te.file_path AS execution_file_path, te.file_name AS execution_file_name, te.status AS execution_status, te.submitted_at AS execution_submitted_at FROM tasks JOIN users ON users.id = tasks.user_id LEFT JOIN task_execution te ON te.task_id = tasks.id ORDER BY tasks.created_at DESC");
                $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($tasks as &$t) {
                    if (!empty($t['execution_file_path'])) {
                        $t['execution_file_url'] = buildPresignedUrlFromFilePath($t['execution_file_path']);
                    }
                }
                echo json_encode(['success' => true, 'data' => $tasks]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = $_POST['user_id'] ?? null;
            $taskType = trim($_POST['task_type'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (!$userId || !$taskType || !$description) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'User ID, task type, and description are required']);
                break;
            }

            try {
                // Verify user exists and is collaborator
                $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$user) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'User not found']);
                    break;
                }
                if ($user['role'] !== 'collaborator') {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Tasks can only be assigned to collaborators']);
                    break;
                }

                $stmt = $pdo->prepare("INSERT INTO tasks (user_id, task_type, description, status) VALUES (?, ?, ?, 'pending')");
                $stmt->execute([$userId, $taskType, $description]);
                $taskId = (int)$pdo->lastInsertId();

                $stmt = $pdo->prepare("SELECT tasks.*, users.name AS user_name, users.email AS user_email FROM tasks JOIN users ON users.id = tasks.user_id WHERE tasks.id = ?");
                $stmt->execute([$taskId]);
                $task = $stmt->fetch(PDO::FETCH_ASSOC);

                http_response_code(201);
                echo json_encode(['success' => true, 'message' => 'Task created successfully', 'data' => $task]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
        } else {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        }
        break;

    // My tasks (collaborator only)
    case '/tasks/my-tasks':
        if (!requireAuth() || !requireRole('collaborator')) { break; }
        $userId = (int)($_SESSION['user_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("SELECT tasks.* FROM tasks WHERE tasks.user_id = ? AND tasks.status != 'completed' ORDER BY tasks.created_at DESC");
            $stmt->execute([$userId]);
            $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $tasks]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        break;

    // Submit task execution (collaborator only)
    case '/executions/submit':
        if (!requireAuth() || !requireRole('collaborator')) { break; }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
        }

        $taskId = (int)($_POST['task_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');

        if (!$taskId || !$description) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Task ID and description are required']);
            break;
        }

        // Enforce file is present
        if (empty($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'File is required for task execution']);
            break;
        }

        $collaboratorId = (int)$_SESSION['user_id'];

        try {
            // Verify task exists and assigned to this collaborator
            $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmt->execute([$taskId]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$task) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Task not found']);
                break;
            }
            if ((int)$task['user_id'] !== $collaboratorId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'You can only submit executions for tasks assigned to you']);
                break;
            }

            // Check if execution already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM task_execution WHERE task_id = ? AND collaborator_id = ?");
            $stmt->execute([$taskId, $collaboratorId]);
            if ((int)$stmt->fetchColumn() > 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Task execution already submitted']);
                break;
            }

            // Validate and upload file to S3 (S3Ninja)
            $filePath = null;
            $fileName = null;
            if (!empty($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
                // Enforce size limit: 100MB
                $maxFileSize = 100 * 1024 * 1024; // 100MB
                if ((int)$_FILES['file']['size'] > $maxFileSize) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'File too large. Max 100MB allowed.']);
                    break;
                }

                // Enforce file extensions (primary) and MIME (best-effort)
                $allowedExts = ['txt','pdf','doc','docx','xls','xlsx','csv','exl'];
                $allowedMime = [
                    'text/plain',
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'text/csv',
                    'application/csv',
                    'application/vnd.ms-excel.sheet.macroEnabled.12'
                ];
                $origName = $_FILES['file']['name'];
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                $mime = @mime_content_type($_FILES['file']['tmp_name']) ?: '';

                if (!in_array($ext, $allowedExts, true)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: .txt, .pdf, .doc, .docx, .xls, .xlsx, .csv']);
                    break;
                }
                // Only enforce MIME if it is known and not generic
                if ($mime && $mime !== 'application/octet-stream' && !in_array($mime, $allowedMime, true)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Invalid file content type.']);
                    break;
                }

                if (!class_exists('Aws\\S3\\S3Client')) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'S3 client not available. Please run composer install.']);
                    break;
                }

                // S3 configuration via env with sensible defaults (S3Ninja)
                $s3Endpoint = getenv('S3_ENDPOINT') ?: 'http://s3ninja:9000';
                $s3Bucket = getenv('S3_BUCKET') ?: 'wis4-documents';
                $s3Region = getenv('S3_REGION') ?: 'us-east-1';
                $s3Key = getenv('S3_KEY') ?: 'AKIAIOSFODNN7EXAMPLE';
                $s3Secret = getenv('S3_SECRET') ?: 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY';
                $usePathStyle = filter_var(getenv('S3_USE_PATH_STYLE') ?: 'true', FILTER_VALIDATE_BOOLEAN);

                $s3Client = new Aws\S3\S3Client([
                    'version' => 'latest',
                    'region' => $s3Region,
                    'endpoint' => $s3Endpoint,
                    'use_path_style_endpoint' => $usePathStyle,
                    'credentials' => [
                        'key' => $s3Key,
                        'secret' => $s3Secret,
                    ],
                ]);

                $bucket = $s3Bucket;
                // Ensure bucket exists
                try {
                    $s3Client->headBucket(['Bucket' => $bucket]);
                } catch (Aws\Exception\AwsException $e) {
                    if ($e->getStatusCode() === 404) {
                        $s3Client->createBucket(['Bucket' => $bucket]);
                    }
                }

                $safeBase = preg_replace('/[^A-Za-z0-9_.-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
                $random = bin2hex(random_bytes(8));
                $key = 'task-executions/' . $random . '_' . $safeBase . '.' . $ext;

                try {
                    $result = $s3Client->putObject([
                        'Bucket' => $bucket,
                        'Key' => $key,
                        'Body' => fopen($_FILES['file']['tmp_name'], 'rb'),
                        'ContentType' => $mime ?: 'application/octet-stream',
                    ]);
                    $publicEndpoint = getenv('S3_PUBLIC_ENDPOINT') ?: $s3Endpoint;
                    $filePath = rtrim($publicEndpoint, '/') . '/' . $bucket . '/' . $key;
                    $fileName = basename($key);
                } catch (Aws\Exception\AwsException $e) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'File upload to S3 failed']);
                    break;
                }
            }

            // Insert execution
            $stmt = $pdo->prepare("INSERT INTO task_execution (task_id, collaborator_id, description, file_path, file_name, status) VALUES (?, ?, ?, ?, ?, 'submitted')");
            $stmt->execute([$taskId, $collaboratorId, $description, $filePath, $fileName]);

            // Update task status to in_progress
            $stmt = $pdo->prepare("UPDATE tasks SET status = 'in_progress' WHERE id = ?");
            $stmt->execute([$taskId]);

            http_response_code(201);
            echo json_encode(['success' => true, 'message' => 'Task execution submitted successfully']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
        } catch (Throwable $t) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Unexpected error']);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
        break;
}
?>

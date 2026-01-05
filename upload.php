<?php
require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'SecurityHandler.php';
require_once 'FileHandler.php';

use MongoDB\Client;
use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: home');
    exit;
}

try {
    $config = require 'config.php';
    $mongoUri = $_ENV['MONGODB_URI'] ?? getenv('MONGODB_URI');
    $database = $_ENV['MONGODB_DATABASE'] ?? getenv('MONGODB_DATABASE');
    $client = new Client($mongoUri);
    $security = new SecurityHandler($client, $database, $config);
    $fileHandler = new FileHandler($client, $database, $config);

    // Validate CSRF token
    if (!$security->validateCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    $rateLimitCheck = $security->checkRateLimit();
    if (!$rateLimitCheck['allowed']) {
        http_response_code(429);
        echo json_encode($rateLimitCheck);
        exit;
    }

    // Record upload attempt
    $security->recordUpload($fileCount);

    if (!isset($_FILES['files']) || empty($_FILES['files']['name'][0])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No files uploaded']);
        exit;
    }
    $fileCount = is_array($_FILES['files']['name']) ? count($_FILES['files']['name']) : 1;
    
    if ($fileCount > $config['max_files_per_upload']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "Maximum {$config['max_files_per_upload']} files allowed per upload"
        ]);
        exit;
    }
    $isPrivate = isset($_POST['is_private']) && $_POST['is_private'] === '1';
    $title = isset($_POST['title']) ? $security->sanitizeText($_POST['title'], 200) : '';
    $expirySeconds = $security->validateExpiry($_POST['expiry_days'] ?? 31536000);
    
    if ($expirySeconds === null) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid expiry option'
        ]);
        exit;
    }
    $uploadedFiles = [];
    $totalSize = 0;
    $files = [];
    if (is_array($_FILES['files']['name'])) {
        for ($i = 0; $i < $fileCount; $i++) {
            $files[] = [
                'name' => $_FILES['files']['name'][$i],
                'type' => $_FILES['files']['type'][$i],
                'tmp_name' => $_FILES['files']['tmp_name'][$i],
                'error' => $_FILES['files']['error'][$i],
                'size' => $_FILES['files']['size'][$i],
            ];
        }
    } else {
        $files[] = [
            'name' => $_FILES['files']['name'],
            'type' => $_FILES['files']['type'],
            'tmp_name' => $_FILES['files']['tmp_name'],
            'error' => $_FILES['files']['error'],
            'size' => $_FILES['files']['size'],
        ];
    }
    foreach ($files as $file) {
        $totalSize += $file['size'];
    }
    










    if ($totalSize > $config['max_total_size_per_upload']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Total upload size exceeds maximum allowed size of ' . 
                        ($config['max_total_size_per_upload'] / 1024 / 1024) . ' MB'
        ]);
        exit;
    }
    




    $errors = [];
    foreach ($files as $index => $file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "File {$file['name']}: Upload error";
            continue;
        }



        $fileTitle = $title;
        if ($fileCount > 1 && !empty($title)) {
            $fileTitle = $title . ' - ' . ($index + 1);
        }
        
        $fileData = [
            'tmp_path' => $file['tmp_name'],
            'original_filename' => $file['name'],
            'title' => $fileTitle ?: pathinfo($file['name'], PATHINFO_FILENAME),
            'is_private' => $isPrivate,
            'expiry_seconds' => $expirySeconds,
        ];
        
        $result = $fileHandler->uploadFile($fileData, $security);
        
        if ($result['success']) {
            $uploadedFiles[] = $result;
        } else {
            $errors[] = "File {$file['name']}: {$result['message']}";
        }
    }
    

    
    if (!empty($uploadedFiles)) {
        $security->recordUpload(count($uploadedFiles));
    }
    





























    if (empty($uploadedFiles)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'No files were uploaded successfully',
            'errors' => $errors
        ]);
    } else {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => count($uploadedFiles) . ' file(s) uploaded successfully',
            'files' => $uploadedFiles,
            'errors' => $errors
        ]);
    }
    
} catch (\Exception $e) {
    error_log("Upload error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An internal error occurred. Please try again later.'
    ]);
}

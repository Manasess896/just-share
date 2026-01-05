<?php

 //file Deletion Endpoint







 
require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'SecurityHandler.php';
require_once 'FileHandler.php';
use MongoDB\Client;
use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Set JSON header for API responses
header('Content-Type: application/json');

//only allow POST requests to prevent loading the file in browser
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
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        $data = $_POST;
    }

    // Validate CSRF token
    if (!$security->validateCsrfToken($data['csrf_token'] ?? null)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $fileId = $data['file_id'] ?? '';
    $slug = $data['slug'] ?? $_GET['slug'] ?? '';
    $deletionPasscode = $data['deletion_passcode'] ?? '';
    
    if ((empty($fileId) && empty($slug)) || empty($deletionPasscode)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'File ID/Slug and deletion passcode are required'
        ]);
        exit;
    }
    if (empty($fileId) && !empty($slug)) {
        $file = $fileHandler->getFileBySlug($slug);
        
        // Check if file is expired
        if ($file && $fileHandler->isFileExpired($file)) {
            $file = null;
        }

        if ($file) {
            $fileId = (string)$file['_id'];
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'File not found']);
            exit;
        }
    } else if (!empty($fileId)) {
        // If fileId is provided directly, we should also check expiry
        $file = $fileHandler->getFileById($fileId);
        if ($file && $fileHandler->isFileExpired($file)) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'File not found']);
            exit;
        }
    }
    
    //delete file
    $result = $fileHandler->deleteFile($fileId, $security, $deletionPasscode);
    
    if ($result['success']) {
        http_response_code(200);
    } else {
        http_response_code(400);
    }
    
    echo json_encode($result);
    
} catch (\Exception $e) {
    error_log("Deletion error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while deleting the file.'
    ]);
}

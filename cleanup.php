<?php
/**
 * Cleanup Script - Moves expired files to deleted collection
  * was supposed to be a cron job but i decided to let the function to check files expiration be called directly when users reload or visit the site the function will check current time and determine what files are expired and move them accordingly
 */

if (php_sapi_name() !== 'cli') {
    header('Location: home');
    exit;
}

require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'SecurityHandler.php';
require_once 'FileHandler.php';

use MongoDB\Client;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

try {
   
    $config = require 'config.php';
    //mongodb connection
    $mongoUri = $_ENV['MONGODB_URI'] ?? getenv('MONGODB_URI');
    $database = $_ENV['MONGODB_DATABASE'] ?? getenv('MONGODB_DATABASE');
    $client = new Client($mongoUri);
    
    
    $security = new SecurityHandler($client, $database, $config);
    $fileHandler = new FileHandler($client, $database, $config);
    
    $logsCollection = $client->selectDatabase($database)->selectCollection('admin-cleanup-logs');

    $logsCollection->insertOne([
        'timestamp' => new \MongoDB\BSON\UTCDateTime(),
        'level' => 'info',
        'message' => 'Starting cleanup process',
        'details' => ['start_time' => date('Y-m-d H:i:s')]
    ]);
    
    //run cleanup
    $result = $fileHandler->cleanupExpiredFiles();
    
    if ($result['success']) {
        $logsCollection->insertOne([
            'timestamp' => new \MongoDB\BSON\UTCDateTime(),
            'level' => 'success',
            'message' => 'Cleanup successful',
            'details' => [
                'moved_count' => $result['moved_count'],
                'result_message' => $result['message']
            ]
        ]);
    } else {
        $logsCollection->insertOne([
            'timestamp' => new \MongoDB\BSON\UTCDateTime(),
            'level' => 'error',
            'message' => 'Cleanup failed',
            'details' => [
                'error_message' => $result['message']
            ]
        ]);
    }
    
    $logsCollection->insertOne([
        'timestamp' => new \MongoDB\BSON\UTCDateTime(),
        'level' => 'info',
        'message' => 'Cleanup completed',
        'details' => ['end_time' => date('Y-m-d H:i:s')]
    ]);
    
} catch (\Exception $e) {
    if (isset($client) && isset($database)) {
        try {
            $logsCollection = $client->selectDatabase($database)->selectCollection('admin-cleanup-logs');
            $logsCollection->insertOne([
                'timestamp' => new \MongoDB\BSON\UTCDateTime(),
                'level' => 'critical',
                'message' => 'Fatal error during cleanup',
                'details' => ['exception' => $e->getMessage()]
            ]);
        } catch (\Exception $logError) {
            error_log("Failed to log to MongoDB: " . $logError->getMessage());
        }
    }
    error_log("Cleanup error: " . $e->getMessage());
    exit(1);
}

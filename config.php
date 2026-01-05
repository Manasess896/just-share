<?php
/**
 * Configuration file for secure file upload system
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    header('Location: home');
    exit;
}

return [
 
    'base_url' => $_ENV['BASE_URL'] ?? 'http://localhost/publicfiles',
    'max_files_per_upload' => 10,
    'max_file_size' => 5 * 1024 * 1024, 
    'max_total_size_per_upload' => 50 * 1024 * 1024,
    
    // rate limiting
    'rate_limit_per_minute' => 10,
    'rate_limit_window' => 60, //in  seconds
    
    //file expiry options in  Seconds
    'expiry_options' => [
        '5 Minutes' => 300,
        '30 Minutes' => 1800,
        '1 Hour' => 3600,
        '2 Hours' => 7200,
        '5 Hours' => 18000,
        '1 Day' => 86400,
        '7 Days' => 604800,
        '30 Days' => 2592000,
        '90 Days' => 7776000,
        '180 Days' => 15552000,
        '365 Days' => 31536000,
    ],
    'auto_delete_days' => 365,
    
    //allowed file types and MIME types
    'allowed_documents' => [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'ppt' => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'txt' => ['text/plain'],
        'rtf' => ['application/rtf', 'text/rtf'],
        'odt' => ['application/vnd.oasis.opendocument.text'],
        'ods' => ['application/vnd.oasis.opendocument.spreadsheet'],
        'odp' => ['application/vnd.oasis.opendocument.presentation'],
    ],
    //alllowed image types and MIME types
    'allowed_images' => [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'bmp' => ['image/bmp'],
        'svg' => ['image/svg+xml'],
        'ico' => ['image/x-icon', 'image/vnd.microsoft.icon'],
    ],
    
    //security settings
    'passcode_length' => 12,
    
    // Session security
    'session' => [
        'cookie_lifetime' => 0, // Until browser closes
        'cookie_path' => '/',
        'cookie_domain' => '',
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ],
    
    //database collections
    'collections' => [
        'files' => 'files',
        'deleted_files' => 'deleted_files',
        'metadata_archive' => 'metadata_archive',
        'rate_limits' => 'rate_limits',
    ],
    
    // GridFS bucket names
    'gridfs_buckets' => [
        'active' => 'fs',
        'deleted' => 'deleted_fs',
    ],
];

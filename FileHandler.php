<?php

 // File Handler handles GridFS uploads, metadata stripping, and file management
 

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    header('Location: home');
    exit;
}

use MongoDB\Client;
use MongoDB\BSON\ObjectId;
use MongoDB\GridFS\Bucket;
use setasign\Fpdi\Fpdi;

class FileHandler {
    private $client;
    private $database;
    private $config;
    private $filesCollection;
    private $metadataCollection;
    private $gridFsBucket;
    private $deletedGridFsBucket;
    
    public function __construct(Client $client, string $database, array $config) {
        $this->client = $client;
        $this->database = $database;
        $this->config = $config;
        
        $db = $client->selectDatabase($database);
        $this->filesCollection = $db->selectCollection($config['collections']['files']);
        $this->metadataCollection = $db->selectCollection($config['collections']['metadata_archive']);
        
        // Initialize GridFS buckets
        $this->gridFsBucket = $db->selectGridFSBucket([
            'bucketName' => $config['gridfs_buckets']['active']
        ]);
        
        $this->deletedGridFsBucket = $db->selectGridFSBucket([
            'bucketName' => $config['gridfs_buckets']['deleted']
        ]);
    }
    

     //pload a file to GridFS with metadata stripping
   
    public function uploadFile(array $fileData, SecurityHandler $security): array {
        try {
            $tmpPath = $fileData['tmp_path'];
            $originalFilename = $fileData['original_filename'];
            $sanitizedFilename = $security->sanitizeFilename($originalFilename);
            
            //validate file type make sure the file is allowed
            $validation = $security->validateFileType($sanitizedFilename, $tmpPath);
            if (!$validation['valid']) {
                return ['success' => false, 'message' => $validation['message']];
            }
            //check file size not to pass 5mb limit
            $fileSize = filesize($tmpPath);
            if ($fileSize > $this->config['max_file_size']) {
                return [
                    'success' => false,
                    'message' => 'File size exceeds maximum allowed size of ' . 
                                ($this->config['max_file_size'] / 1024 / 1024) . ' MB'
                ];
            }
            











            //strip metadata based on file type
            $cleanedPath = $this->stripMetadata($tmpPath, $validation['file_category'], $validation['extension']);
            
            // Update file size after stripping metadata to ensure Content-Length matches actual stored file size
            clearstatcache(true, $cleanedPath);
            $fileSize = filesize($cleanedPath);

            $slug = $security->generateSlug($fileData['title'] ?? pathinfo($sanitizedFilename, PATHINFO_FILENAME));
             //generate passcodes
            $accessPasscode = $fileData['is_private'] ? $security->generatePasscode($this->config['passcode_length']) : null;
            $deletionPasscode = $security->generatePasscode($this->config['passcode_length']);
            //prepare file stream
            $stream = fopen($cleanedPath, 'rb');
            //upload to GridFS
            $gridFsId = $this->gridFsBucket->uploadFromStream($sanitizedFilename, $stream, [
                'metadata' => [
                    'original_filename' => $sanitizedFilename,
                    'file_category' => $validation['file_category'],
                    'mime_type' => $validation['mime_type'],
                    'extension' => $validation['extension'],
                ]
            ]);
            
            fclose($stream);
            





            //calculate expiry date
            $expirySeconds = $fileData['expiry_seconds'] ?? 31536000;//default is  365 days legal protection 
            $expiryDate = new \MongoDB\BSON\UTCDateTime((time() + $expirySeconds) * 1000);
            $uploadDate = new \MongoDB\BSON\UTCDateTime(time() * 1000);
            $fileMetadata = [
                'gridfs_id' => $gridFsId,
                'slug' => $slug,
                'title' => $security->sanitizeText($fileData['title'] ?? $sanitizedFilename),
                'original_filename' => $sanitizedFilename,
                'file_size' => $fileSize,
                'file_category' => $validation['file_category'],
                'mime_type' => $validation['mime_type'],
                'extension' => $validation['extension'],
                'is_private' => $fileData['is_private'],
                'access_passcode_hash' => $accessPasscode ? $security->hashPasscode($accessPasscode) : null,
                'deletion_passcode_hash' => $security->hashPasscode($deletionPasscode),
                'upload_date' => $uploadDate,
                'expiry_date' => $expiryDate,
                'expiry_seconds' => $expirySeconds,
                'upload_ip' => $this->getClientIp(),
                'download_count' => 0,
                'status' => 'active',
                'created_at' => $uploadDate,
                'updated_at' => $uploadDate
            ];
            
            $result = $this->filesCollection->insertOne($fileMetadata);
            if ($cleanedPath !== $tmpPath) {
                @unlink($cleanedPath);
            }
            
            return [
                'success' => true,
                'file_id' => (string)$result->getInsertedId(),
                'slug' => $slug,
                'access_passcode' => $accessPasscode,
                'deletion_passcode' => $deletionPasscode,
                'filename' => $sanitizedFilename,
                'extension' => $validation['extension'],
                'expiry_date' => date('Y-m-d H:i:s', time() + $expirySeconds)
            ];
            
        } catch (\Exception $e) {
            error_log("File upload error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred during file upload. Please try again.'
            ];
        }
    }
     //strip metadata from files
    private function stripMetadata(string $filePath, string $category, string $extension): string {
        if ($category === 'image') {
            return $this->stripImageMetadata($filePath, $extension);
        } elseif ($category === 'document') {
            return $this->stripDocumentMetadata($filePath, $extension);
        }
        return $filePath;
    }
    
    
     //strip metadata from images
    private function stripImageMetadata(string $filePath, string $extension): string {
        try {
            $cleanPath = $filePath . '.clean';
            //GD library to strip EXIF data
            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                    $image = imagecreatefromjpeg($filePath);
                    if ($image) {
                        imagejpeg($image, $cleanPath, 90);
                        return $cleanPath;
                    }
                    break;
                    
                case 'png':
                    $image = imagecreatefrompng($filePath);
                    if ($image) {
                        imagepng($image, $cleanPath, 9);
                        return $cleanPath;
                    }
                    break;
                    
                case 'gif':
                    $image = imagecreatefromgif($filePath);
                    if ($image) {
                        imagegif($image, $cleanPath);
                        return $cleanPath;
                    }
                    break;
                    
                case 'webp':
                    if (function_exists('imagecreatefromwebp')) {
                        $image = imagecreatefromwebp($filePath);
                        if ($image) {
                            imagewebp($image, $cleanPath, 90);
                            return $cleanPath;
                        }
                    }
                    break;
            }
        } catch (\Exception $e) {
            error_log("Metadata stripping failed: " . $e->getMessage());
        }
        
        return $filePath;
    }
     //trip metadata from documents
    
    private function stripDocumentMetadata(string $filePath, string $extension): string {
      
        try {
            $fileInfo = [
                'original_path' => $filePath,
                'file_size' => filesize($filePath),
                'modified_time' => filemtime($filePath),
                'archived_at' => new \MongoDB\BSON\UTCDateTime(time() * 1000)
            ];
            
            $this->metadataCollection->insertOne($fileInfo);
        } catch (\Exception $e) {
            error_log("Metadata archival failed: " . $e->getMessage());
        }
        try {
            if ($extension === 'pdf') {
                return $this->stripPdfMetadata($filePath);
            } elseif (in_array($extension, ['docx', 'xlsx', 'pptx'])) {
                return $this->stripOfficeMetadata($filePath, $extension);
            }
        } catch (\Exception $e) {
            error_log("Metadata stripping failed for $extension: " . $e->getMessage());
        }
        
        return $filePath;
    }
    private function stripPdfMetadata(string $filePath): string {
        $cleanPath = $filePath . '.clean.pdf';
        $pdf = new Fpdi();
        $pageCount = $pdf->setSourceFile($filePath);
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);
        }
        
        $pdf->Output('F', $cleanPath);
        return $cleanPath;
    }
     //strip metadata from Office documents using ZipArchive
     
    private function stripOfficeMetadata(string $filePath, string $extension): string {
        $cleanPath = $filePath . '.clean.' . $extension;
        if (!copy($filePath, $cleanPath)) {
            throw new \Exception("Failed to create copy for metadata stripping");
        }

        $zip = new \ZipArchive();
        if ($zip->open($cleanPath) === TRUE) {
            //list of metadata files to remove
            $metadataFiles = [
                'docProps/core.xml',
                'docProps/app.xml',
                'docProps/custom.xml'
            ];
            foreach ($metadataFiles as $xmlFile) {
                if ($zip->locateName($xmlFile) !== false) {
                    $zip->deleteName($xmlFile);
                }
            }
            //add empty core properties to prevent corruption warnings
            $emptyCore = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"></cp:coreProperties>';
            $zip->addFromString('docProps/core.xml', $emptyCore);

            $zip->close();
            return $cleanPath;
        }
        return $filePath;
    }
    public function getFileBySlug(string $slug): ?array {
        $file = $this->filesCollection->findOne([
            'slug' => (string)$slug,
            'status' => 'active'
        ]);
        
        return $file ? iterator_to_array($file) : null;
    }

    public function getFileById(string $fileId): ?array {
        try {
            $file = $this->filesCollection->findOne([
                '_id' => new ObjectId((string)$fileId),
                'status' => 'active'
            ]);
            
            return $file ? iterator_to_array($file) : null;
        } catch (\Exception $e) {
            return null;
        }
    }
    public function downloadFile(string $gridFsId, string $fileId): array {
        try {
            $stream = $this->gridFsBucket->openDownloadStream(new ObjectId((string)$gridFsId)); 
            //download count
            $this->filesCollection->updateOne(
                ['_id' => new ObjectId((string)$fileId)],
                ['$inc' => ['download_count' => 1]]
            );
            
            return [
                'success' => true,
                'stream' => $stream
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'File not found or cannot be accessed.'
            ];
        }
    }
     //Delete  a file
     
    public function deleteFile(string $fileId, SecurityHandler $security, string $deletionPasscode): array {
        try {
            $file = $this->getFileById($fileId);
            
            if (!$file) {
                return ['success' => false, 'message' => 'File not found.'];
            }
            
            //verify deletion passcode
            if (!$security->verifyPasscode($deletionPasscode, $file['deletion_passcode_hash'])) {
                return ['success' => false, 'message' => 'Invalid deletion passcode.'];
            }
            
            //get file from GridFS
            $stream = $this->gridFsBucket->openDownloadStream($file['gridfs_id']);
            $deletedGridFsId = $this->deletedGridFsBucket->uploadFromStream(
                $file['original_filename'],
                $stream,
                ['metadata' => $file]
            );
            
            // Delete
            $this->gridFsBucket->delete($file['gridfs_id']);
            $deletedCollection = $this->client->selectDatabase($this->database)
                ->selectCollection($this->config['collections']['deleted_files']);
            
            $file['deleted_gridfs_id'] = $deletedGridFsId;
            $file['deleted_at'] = new \MongoDB\BSON\UTCDateTime(time() * 1000);
            $file['status'] = 'deleted';
            
            $deletedCollection->insertOne($file);
            $this->filesCollection->deleteOne(['_id' => new ObjectId((string)$fileId)]);
            return ['success' => true, 'message' => 'File deleted successfully.'];
        } catch (\Exception $e) {
            error_log("File deletion error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred during file deletion.'];
        }
    }
    public function cleanupExpiredFiles(): array {
        try {
            $currentTime = new \MongoDB\BSON\UTCDateTime(time() * 1000);
            $expiredFiles = $this->filesCollection->find([
                'expiry_date' => ['$lt' => $currentTime],
                'status' => 'active'
            ]);
            
            $movedCount = 0;
            
            foreach ($expiredFiles as $file) {
                if ($this->processExpiredFile($file)) {
                    $movedCount++;
                }
            }
            return [
                'success' => true,
                'moved_count' => $movedCount,
                'message' => "Moved $movedCount expired files to deleted collection."
            ];
        } catch (\Exception $e) {
            error_log("Cleanup error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred during cleanup.'];
        }
    }

    public function isFileExpired($file): bool {
        if (empty($file)) return false;
        
        // Convert to array if it's an object to safely access keys
        $fileData = is_object($file) && $file instanceof \Traversable ? iterator_to_array($file) : (array)$file;

        if (!isset($fileData['expiry_date'])) return false;
        
        $expiryDate = $fileData['expiry_date'];
        $currentTime = new \MongoDB\BSON\UTCDateTime(time() * 1000);
        
        if ($expiryDate < $currentTime) {
            $this->processExpiredFile($fileData);
            return true;
        }
        return false;
    }

    private function processExpiredFile($file): bool {
        try {
            // Normalize to array
            $fileArray = is_object($file) && $file instanceof \Traversable ? iterator_to_array($file) : (array)$file;
            
            // Ensure we have necessary fields
            if (!isset($fileArray['gridfs_id']) || !isset($fileArray['_id'])) {
                return false;
            }

            $gridFsId = $fileArray['gridfs_id'];
            $fileId = $fileArray['_id'];
            $originalFilename = $fileArray['original_filename'] ?? 'unknown';

            // Check if file still exists in GridFS before trying to move
            // This prevents errors if it was already deleted/moved
            try {
                $stream = $this->gridFsBucket->openDownloadStream($gridFsId);
            } catch (\MongoDB\GridFS\Exception\FileNotFoundException $e) {
                // File missing from GridFS, just remove metadata
                $this->filesCollection->deleteOne(['_id' => $fileId]);
                return true;
            }

            $deletedGridFsId = $this->deletedGridFsBucket->uploadFromStream(
                $originalFilename,
                $stream,
                ['metadata' => $fileArray]
            );
            
            // Delete from active GridFS
            $this->gridFsBucket->delete($gridFsId);
            
            $deletedCollection = $this->client->selectDatabase($this->database)
                ->selectCollection($this->config['collections']['deleted_files']);
            
            $fileArray['deleted_gridfs_id'] = $deletedGridFsId;
            $fileArray['deleted_at'] = new \MongoDB\BSON\UTCDateTime(time() * 1000);
            $fileArray['status'] = 'expired';
            
            $deletedCollection->insertOne($fileArray);
            $this->filesCollection->deleteOne(['_id' => $fileId]);
            
            return true;
        } catch (\Exception $e) {
            error_log("Error moving expired file: " . $e->getMessage());
            return false;
        }
    }

    private function getClientIp(): string {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return (string)$ip;
                }
            }
        }
        return '0.0.0.0';
    }
    public function getRecentFiles(int $limit = 50): array {
        try {
            $cursor = $this->filesCollection->find(
                ['status' => 'active'],
                [
                    'limit' => $limit,
                    'sort' => ['created_at' => -1],
                    'projection' => [
                        'slug' => 1,
                        'original_filename' => 1,
                        'title' => 1,
                        'file_category' => 1,
                        'mime_type' => 1,
                        'extension' => 1,
                        'is_private' => 1,
                        'expiry_date' => 1,
                        'created_at' => 1
                    ]
                ]
            );
            
            return iterator_to_array($cursor);
        } catch (\Exception $e) {
            error_log("Error fetching recent files: " . $e->getMessage());
            return [];
        }
    }
}

<?php
session_start();
require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'FileHandler.php';
require_once 'SecurityHandler.php';

use MongoDB\Client;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$config = require 'config.php';

//MongoDB connection
$mongoUri = $_ENV['MONGODB_URI'] ?? getenv('MONGODB_URI');
$database = $_ENV['MONGODB_DATABASE'] ?? getenv('MONGODB_DATABASE');
$client = new Client($mongoUri);

$security = new SecurityHandler($client, $database, $config);
$fileHandler = new FileHandler($client, $database, $config);

//generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

//check for expired files for deletion when loading the page
$fileHandler->cleanupExpiredFiles();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Just Share - Anonymous File Sharing | Upload & Share Documents Instantly</title>
    <meta name="description" content="Share documents, images, and files anonymously without account creation. Upload files with optional password protection, set expiration dates up to 365 days. Perfect for temporary file sharing on social media and web.">
    <meta name="keywords" content="file sharing, temporary file sharing,file.io,share ,no accounts,no regestration,free upload files, share files online, instant file sharing, secure file transfer, no registration file sharing">
    <meta name="author" content="Just Share">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?php echo $config['base_url']; ?>/home">
    <link rel="icon" type="image/jpeg" href="<?php echo $config['base_url']; ?>/logo.jpeg">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo $config['base_url']; ?>/home">
    <meta property="og:title" content="Just Share - Simple Temporary File Sharing">
    <meta property="og:description" content="Upload and share your files instantly with temporary links. No registration required. Secure and easy to use.">
    <meta property="og:image" content="<?php echo $config['base_url']; ?>/og-image.png">
    <meta property="og:site_name" content="Just Share">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?php echo $config['base_url']; ?>/home">
    <meta name="twitter:title" content="Just Share - Simple Temporary File Sharing">
    <meta name="twitter:description" content="Upload and share your files instantly with temporary links. No registration required.">
    <meta name="twitter:image" content="<?php echo $config['base_url']; ?>/twitter-image.png">
    <meta name="theme-color" content="#00b894">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebApplication",
      "name": "Just Share",
      "description": "Simple temporary file sharing service for instant uploads and secure sharing",
      "url": "<?php echo $config['base_url']; ?>",
      "applicationCategory": "UtilityApplication",
      "operatingSystem": "Any",
      "offers": {
        "@type": "Offer",
        "price": "0",
        "priceCurrency": "USD"
      }
    }
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --bg-color: #121212;
            --card-bg: #1e1e1e;
            --text-main: #ffffff;
            --text-muted: #b0b0b0;
            --primary: #00b894;
            --primary-hover: #00a383;
            --border-color: #333333;
            --input-bg: #2d2d2d;
            --danger: #ff7675;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .navbar-custom {
            background-color: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 0;
        }
        
        .navbar-brand {
            color: var(--primary) !important;
            font-weight: 700;
            font-size: 1.5rem;
        }
        
        .nav-link {
            color: var(--text-muted) !important;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .nav-link:hover, .nav-link.active {
            color: var(--primary) !important;
        }

        .navbar-toggler {
            border-color: var(--border-color);
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(255, 255, 255, 0.55)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .container {
            max-width: 800px;
            width: 100%;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .header h1 {
            font-size: 36px;
            color: var(--text-main);
            margin-bottom: 10px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        
        .header p {
            color: var(--text-muted);
            font-size: 16px;
        }
        
        .upload-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 40px;
            border: 1px solid var(--border-color);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }
        
        .upload-zone {
            border: 2px dashed var(--border-color);
            border-radius: 12px;
            padding: 40px 20px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
            background: rgba(255, 255, 255, 0.02);
            margin-bottom: 30px;
        }
        
        .upload-zone:hover,
        .upload-zone.drag-over {
            border-color: var(--primary);
            background: rgba(0, 184, 148, 0.05);
        }
        
        .upload-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.8;
        }
        
        .upload-zone h3 {
            color: var(--text-main);
            margin-bottom: 8px;
            font-size: 18px;
        }
        
        .upload-zone p {
            color: var(--text-muted);
            font-size: 13px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            color: var(--text-muted);
            font-weight: 500;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .form-group input[type="text"],
        .form-group select {
            width: 100%;
            padding: 14px 16px;
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-main);
            font-size: 15px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .radio-group {
            display: flex;
            gap: 15px;
            background: var(--input-bg);
            padding: 5px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
        }
        
        .radio-option {
            flex: 1;
            position: relative;
        }
        
        .radio-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
            height: 0;
            width: 0;
        }
        
        .radio-option label {
            display: block;
            padding: 12px;
            text-align: center;
            cursor: pointer;
            border-radius: 8px;
            margin: 0;
            color: var(--text-muted);
            transition: all 0.3s;
            font-size: 14px;
            font-weight: 500;
        }
        
        .radio-option input[type="radio"]:checked + label {
            background: var(--primary);
            color: #121212;
            font-weight: 600;
        }
        
        .btn {
            width: 100%;
            padding: 16px;
            background: var(--primary);
            color: #121212;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.3s, transform 0.2s;
            margin-top: 10px;
        }
        
        .btn:hover:not(:disabled) {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .selected-files {
            margin-bottom: 20px;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 15px;
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .file-info {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--text-main);
            overflow: hidden;
        }
        
        .file-icon {
            font-size: 20px;
        }
        
        .file-details {
            overflow: hidden;
        }
        
        .file-name {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 14px;
        }
        
        .file-size {
            color: var(--text-muted);
            font-size: 12px;
        }
        
        .remove-file {
            background: transparent;
            color: var(--danger);
            border: 1px solid var(--danger);
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
            margin-left: 10px;
            flex-shrink: 0;
        }
        
        .remove-file:hover {
            background: var(--danger);
            color: white;
        }

        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .gallery-item {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            transition: transform 0.2s;
            position: relative;
        }

        .gallery-item:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
        }

        .gallery-preview {
            height: 150px;
            background: #2d2d2d;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .gallery-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .gallery-preview .doc-icon {
            font-size: 48px;
            color: var(--text-muted);
        }

        .gallery-info {
            padding: 15px;
        }

        .gallery-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: var(--text-main);
        }

        .gallery-meta {
            font-size: 11px;
            color: var(--text-muted);
            margin-bottom: 10px;
        }

        .gallery-actions {
            display: flex;
            gap: 10px;
        }

        .gallery-btn {
            flex: 1;
            padding: 8px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: background 0.2s;
        }

        .btn-download {
            background: var(--primary);
            color: #121212;
            font-weight: 600;
        }

        .btn-download:hover {
            background: var(--primary-hover);
        }

        .btn-copy {
            background: var(--input-bg);
            color: var(--text-main);
            border: 1px solid var(--border-color);
        }

        .btn-copy:hover {
            background: #333;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
        }
        
        .modal-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }
        
        .modal {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 30px;
            width: 90%;
            max-width: 600px;
            border: 1px solid var(--border-color);
            transform: translateY(20px);
            transition: transform 0.3s;
        }
        
        .modal-overlay.active .modal {
            transform: translateY(0);
        }
        
        .modal-header {
            margin-bottom: 20px;
            text-align: center;
        }
        
        .modal-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--primary);
        }
        
        .codes-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
            background: var(--input-bg);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .codes-table th, .codes-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .codes-table th {
            background: rgba(0,0,0,0.2);
            font-weight: 600;
            color: var(--text-muted);
            font-size: 13px;
        }
        
        .codes-table td {
            font-size: 14px;
        }
        h1{
            font-weight: 700;
            color: var(--primary);
            text-transform: uppercase  ;
            letter-spacing: -1px;
            font-family: monospace;
            text-align: left;
        }
        .code-cell {
            font-family: monospace;
            color: var(--primary);
            font-weight: 600;
            position: relative;
            padding-right: 30px;
        }
        
        .copy-icon {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            opacity: 0.7;
            font-size: 14px;
        }
        
        .copy-icon:hover { opacity: 1; }
        
        .modal-actions {
            text-align: center;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background: rgba(255, 118, 117, 0.1);
            color: var(--danger);
            border: 1px solid var(--danger);
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
            color: var(--text-muted);
        }
        
        .loading.active {
            display: block;
        }
        
        .progress-bar {
            width: 100%;
            height: 4px;
            background: var(--input-bg);
            border-radius: 2px;
            margin-top: 10px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--primary);
            width: 0%;
            transition: width 0.3s;
        }
        
        .spinner {
            border: 3px solid rgba(255,255,255,0.1);
            border-top: 3px solid var(--primary);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }
        
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        @media (max-width: 600px) {
            body { padding: 20px 15px; }
            .upload-card { padding: 25px; }
            .header h1 { font-size: 28px; }
            .gallery-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-custom mb-4">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="<?php echo $config['base_url']; ?>/home">
                <img src="<?php echo $config['base_url']; ?>/logo.jpeg" alt="Logo" width="30" height="30" class="d-inline-block align-text-top me-2 rounded-circle">
                Just Share
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $config['base_url']; ?>/home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $config['base_url']; ?>/files">Gallery</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    
    <div class="container">
        <div class="header">
      
            <p>Simple & Temporary File Sharing</p>
        </div>
        
        <div class="upload-card">
            <form id="uploadForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <div class="upload-zone" id="uploadZone">
                    <div class="upload-icon">☁️</div>
                    <h3>Drop files here or click to upload</h3>
                    <p>Max <?php echo $config['max_files_per_upload']; ?> files • <?php echo $config['max_file_size'] / 1024 / 1024; ?>MB limit</p>
                    <input 
                        type="file" 
                        id="fileInput" 
                        name="files[]" 
                        class="file-input" 
                        style="display: none;"
                        multiple 
                        accept=".<?php echo implode(',.',array_keys(array_merge($config['allowed_documents'], $config['allowed_images']))); ?>"
                    >
                </div>
                
                <div id="selectedFiles" class="selected-files"></div>
                
                <div class="form-group">
                    <label for="title">Title (Optional)</label>
                    <input type="text" id="title" name="title" placeholder="File description" maxlength="200">
                </div>
                
                <div class="form-group">
                    <label>Privacy</label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="public" name="is_private" value="0" checked>
                            <label for="public">Public Link</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="private" name="is_private" value="1">
                            <label for="private">Password Protected</label>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="expiry">Expires In</label>
                    <select id="expiry" name="expiry_days">
                        <?php foreach ($config['expiry_options'] as $label => $seconds): ?>
                            <option value="<?php echo $seconds; ?>" <?php echo $seconds === 31536000 ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn" id="uploadBtn" disabled>Upload Files</button>
            </form>
            
            <div class="loading" id="loading">
                <div class="spinner"></div>
                <p id="loadingText">Preparing upload...</p>
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill"></div>
                </div>
            </div>
        </div>
    </div>
    
    
    <script>
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('fileInput');
        const selectedFilesDiv = document.getElementById('selectedFiles');
        const uploadForm = document.getElementById('uploadForm');
        const uploadBtn = document.getElementById('uploadBtn');
        const loading = document.getElementById('loading');
        const loadingText = document.getElementById('loadingText');
        const progressFill = document.getElementById('progressFill');
        
        const maxFiles = <?php echo $config['max_files_per_upload']; ?>;
        const maxFileSize = <?php echo $config['max_file_size']; ?>;
        let selectedFiles = [];
        let uploadedFilesData = [];
        uploadZone.addEventListener('click', () => fileInput.click());
        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('drag-over');
        });
        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('drag-over');
        });
        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('drag-over');
            handleFiles(e.dataTransfer.files);
        });
        fileInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });
        function handleFiles(files) {
            if (files.length > maxFiles) {
                showAlert('error', `You can only upload up to ${maxFiles} files at a time.`);
                return;
            }
            selectedFiles = Array.from(files);
            let totalSize = 0;
            for (let file of selectedFiles) {
                if (file.size > maxFileSize) {
                    showAlert('error', `File "${file.name}" exceeds maximum size of ${maxFileSize / 1024 / 1024}MB`);
                    selectedFiles = [];
                    return;
                }
                totalSize += file.size;
            }
            
            displaySelectedFiles();
            uploadBtn.disabled = selectedFiles.length === 0;
        }
        //isplay selected files
        function displaySelectedFiles() {
            selectedFilesDiv.innerHTML = '';
            selectedFiles.forEach((file, index) => {
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                fileItem.innerHTML = `
                    <div class="file-info">
                        <div class="file-icon">${getFileIcon(file.name)}</div>
                        <div class="file-details">
                            <div class="file-name">${escapeHtml(file.name)}</div>
                            <div class="file-size">${formatFileSize(file.size)}</div>
                        </div>
                    </div>
                    <button type="button" class="remove-file" onclick="removeFile(${index})">Remove</button>
                `;
                selectedFilesDiv.appendChild(fileItem);
            });
        }
        window.removeFile = function(index) {
            selectedFiles.splice(index, 1);
            const dt = new DataTransfer();
            selectedFiles.forEach(file => dt.items.add(file));
            fileInput.files = dt.files;
            
            displaySelectedFiles();
            uploadBtn.disabled = selectedFiles.length === 0;
        };
        uploadForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            if (selectedFiles.length === 0) {
                showAlert('error', 'Please select at least one file to upload.');
                return;
            }
            
            uploadBtn.disabled = true;
            loading.classList.add('active');
            uploadForm.style.display = 'none';
            uploadedFilesData = [];
            
            const totalFiles = selectedFiles.length;
            let uploadedCount = 0;
            
            //process files sequentially by Lazy upload
            for (let i = 0; i < totalFiles; i++) {
                const file = selectedFiles[i];
                loadingText.textContent = `Uploading file ${i + 1} of ${totalFiles}...`;
                progressFill.style.width = `${((i) / totalFiles) * 100}%`;
                
                try {
                    //small delay 
                    if (i > 0) await new Promise(r => setTimeout(r, 1000));
                    
                    const result = await uploadSingleFile(file);
                    
                    if (result.success && result.files && result.files.length > 0) {
                        uploadedFilesData.push(result.files[0]);
                        uploadedCount++;
                    } else {
                        showAlert('error', `Failed to upload ${file.name}: ${result.message || 'Unknown error'}`);
                    }
                    
                } catch (error) {
                    console.error('Upload error:', error);
                    showAlert('error', `Error uploading ${file.name}`);
                }
                
                progressFill.style.width = `${((i + 1) / totalFiles) * 100}%`;
            }
            
            loading.classList.remove('active');
            uploadForm.style.display = 'block';
            
            //reset form if all successful
            if (uploadedCount === totalFiles) {
                uploadForm.reset();
                selectedFiles = [];
                displaySelectedFiles();
                showSuccessModal();
            }
            
            uploadBtn.disabled = false;
        });
        async function uploadSingleFile(file) {
            const formData = new FormData(uploadForm);
            formData.delete('files[]'); 
            formData.append('files[]', file); 
            
            const response = await fetch('upload.php', {
                method: 'POST',
                body: formData
            });
            
            return await response.json();
        }
        //show success modal with codes
        function showSuccessModal() {
            let tableHtml = `
                <p style="color: #b0b0b0; margin-bottom: 15px;">Please save these codes now. They will not be shown again.</p>
                <div style="overflow-x: auto;">
                    <table class="codes-table" style="width: 100%; text-align: left; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th style="padding: 10px; border-bottom: 1px solid #333;">File Name</th>
                                <th style="padding: 10px; border-bottom: 1px solid #333;">Access Code</th>
                                <th style="padding: 10px; border-bottom: 1px solid #333;">Deletion Code</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            uploadedFilesData.forEach(file => {
                tableHtml += `
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #333;">${escapeHtml(file.filename)}</td>
                        <td class="code-cell" style="padding: 10px; border-bottom: 1px solid #333; font-family: monospace; color: #00b894;">
                            ${file.access_passcode || '<span style="color:#b0b0b0">Public</span>'}
                            ${file.access_passcode ? `<span class="copy-icon" style="cursor: pointer; margin-left: 5px;" onclick="copyToClipboard('${file.access_passcode}', this)"><i class="bi bi-copy"></i></span>` : ''}
                        </td>
                        <td class="code-cell" style="padding: 10px; border-bottom: 1px solid #333; font-family: monospace; color: #00b894;">
                            ${file.deletion_passcode}
                            <span class="copy-icon" style="cursor: pointer; margin-left: 5px;" onclick="copyToClipboard('${file.deletion_passcode}', this)"><i class="bi bi-copy"></i></span>
                        </td>
                    </tr>
                `;
            });
            
            tableHtml += `</tbody></table></div>`;

            Swal.fire({
                title: 'Upload Successful!',
                html: tableHtml,
                icon: 'success',
                background: '#1e1e1e',
                color: '#ffffff',
                confirmButtonText: 'I have saved these codes',
                confirmButtonColor: '#00b894',
                width: '600px',
                allowOutsideClick: false
            });
        }
        
        //copy to clipboard 
        window.copyToClipboard = function(text, element) {
            navigator.clipboard.writeText(text).then(() => {
                //visual feedback without closing modal
                const originalHtml = element.innerHTML;
                element.innerHTML = '<i class="bi bi-check-lg"></i>';
                
                setTimeout(() => {
                    element.innerHTML = originalHtml;
                }, 2000);
            });
        };
        function showAlert(type, message) {
            Swal.fire({
                icon: type,
                title: type.charAt(0).toUpperCase() + type.slice(1),
                text: message,
                background: '#1e1e1e',
                color: '#ffffff',
                confirmButtonColor: '#00b894'
            });
        }
        function getFileIcon(filename) {
            const ext = filename.split('.').pop().toLowerCase();
            const icons = {
                pdf: '📄', doc: '📝', docx: '📝', xls: '📊', xlsx: '📊',
                ppt: '📊', pptx: '📊', txt: '📃', jpg: '🖼️', jpeg: '🖼️',
                png: '🖼️', gif: '🖼️', webp: '🖼️', svg: '🖼️'
            };
            return icons[ext] || '📎';
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
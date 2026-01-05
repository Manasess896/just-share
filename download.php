<?php
 //file Download/Access Endpoint

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
    //mongoDB connection
    $mongoUri = $_ENV['MONGODB_URI'] ?? getenv('MONGODB_URI');
    $database = $_ENV['MONGODB_DATABASE'] ?? getenv('MONGODB_DATABASE');
    $client = new Client($mongoUri);
    $security = new SecurityHandler($client, $database, $config);
    $fileHandler = new FileHandler($client, $database, $config);
    $slug = $_GET['slug'] ?? '';
    if (empty($slug)) {
        http_response_code(400);
        showError("Invalid file link");
    }
    //get file metadata
    $file = $fileHandler->getFileBySlug($slug);
    
    // Check if file is expired
    if ($file && $fileHandler->isFileExpired($file)) {
        $file = null;
    }

    if (!$file) {
        http_response_code(404);
        showError("File not found or has been deleted");
    }
    //check if file is private so that we validate user access
    if ($file['is_private']) {
        //check if passcode is provided
        $passcode = $_GET['passcode'] ?? $_POST['passcode'] ?? '';
        if (empty($passcode)) {
            // show passcode form
            showPasscodeForm($slug, $file['title']);
            exit;
        }   
        //verify passcode
        if (!$security->verifyPasscode($passcode, $file['access_passcode_hash'])) {
            http_response_code(403);
            showPasscodeForm($slug, $file['title'], 'Invalid passcode. Please try again.');
            exit;
        }
    }
    //download file
    $result = $fileHandler->downloadFile((string)$file['gridfs_id'], (string)$file['_id']);
    
    if (!$result['success']) {
        http_response_code(404);
        showError($result['message']);
    }

    // Get actual file size from the stream to avoid mismatch if metadata was stripped
    $streamStats = fstat($result['stream']);
    $actualFileSize = $streamStats['size'] ?? $file['file_size'];

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Content-Security-Policy: default-src \'none\'; style-src \'unsafe-inline\'; sandbox');
    $isPreview = isset($_GET['preview']) && $_GET['preview'] === '1';
    $safeImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    $disposition = 'attachment';
    if ($isPreview && in_array($file['mime_type'], $safeImageTypes)) {
        $disposition = 'inline';
    }
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Disposition: ' . $disposition . '; filename="' . $file['original_filename'] . '"');
    header('Content-Length: ' . $actualFileSize);
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    fpassthru($result['stream']);
    fclose($result['stream']);
    
} catch (\Exception $e) {
    error_log("Download error: " . $e->getMessage());
    http_response_code(500);
    showError("An error occurred while accessing the file.");
}


function showError($message) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error - SecureShare</title>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <style>
            body { background-color: #121212; color: #ffffff; font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        </style>
    </head>
    <body>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '<?php echo addslashes($message); ?>',
                background: '#1e1e1e',
                color: '#ffffff',
                confirmButtonColor: '#00b894',
                confirmButtonText: 'Go Home'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../home';
                }
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

//show passcode form for private files
function showPasscodeForm(string $slug, string $title, string $error = ''): void {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Enter Passcode - <?php echo htmlspecialchars($title); ?></title>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            
            .passcode-container {
                background: white;
                border-radius: 20px;
                padding: 40px;
                max-width: 450px;
                width: 100%;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            }
            
            .lock-icon {
                text-align: center;
                font-size: 60px;
                margin-bottom: 20px;
            }
            
            h1 {
                text-align: center;
                color: #333;
                font-size: 24px;
                margin-bottom: 10px;
            }
            
            .file-title {
                text-align: center;
                color: #666;
                font-size: 14px;
                margin-bottom: 30px;
            }
            
            .error-message {
                background: #fee;
                color: #c33;
                padding: 12px;
                border-radius: 8px;
                margin-bottom: 20px;
                text-align: center;
                font-size: 14px;
            }
            
            .form-group {
                margin-bottom: 20px;
            }
            
            label {
                display: block;
                color: #555;
                font-size: 14px;
                margin-bottom: 8px;
                font-weight: 500;
            }
            
            input[type="text"],
            input[type="password"] {
                width: 100%;
                padding: 14px;
                border: 2px solid #e0e0e0;
                border-radius: 10px;
                font-size: 16px;
                transition: all 0.3s;
            }
            
            input[type="text"]:focus,
            input[type="password"]:focus {
                outline: none;
                border-color: #667eea;
            }
            
            .btn {
                width: 100%;
                padding: 14px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 10px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: transform 0.2s;
            }
            
            .btn:hover {
                transform: translateY(-2px);
            }
            
            .btn:active {
                transform: translateY(0);
            }
        </style>
    </head>
    <body>
        <div class="passcode-container">
            <div class="lock-icon">🔒</div>
            <h1>Private File Access</h1>
            <div class="file-title"><?php echo htmlspecialchars($title); ?></div>
            
            <?php if ($error): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Access Denied',
                            text: '<?php echo addslashes($error); ?>',
                            confirmButtonColor: '#764ba2'
                        });
                    });
                </script>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="passcode">Enter Access Passcode:</label>
                    <input 
                        type="password" 
                        id="passcode" 
                        name="passcode" 
                        required 
                        autofocus 
                        placeholder="Enter your passcode"
                    >
                </div>
                <button type="submit" class="btn">Access File</button>
            </form>
        </div>
    </body>
    </html>
    <?php
}

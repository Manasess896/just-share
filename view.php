<?php
require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'SecurityHandler.php';
require_once 'FileHandler.php';

use MongoDB\Client;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$config = require 'config.php';

//mongoDB connection
$mongoUri = $_ENV['MONGODB_URI'] ?? getenv('MONGODB_URI');
$database = $_ENV['MONGODB_DATABASE'] ?? getenv('MONGODB_DATABASE');
$client = new Client($mongoUri);

$security = new SecurityHandler($client, $database, $config);
$fileHandler = new FileHandler($client, $database, $config);

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    http_response_code(404);
    showError("Invalid file link.");
}

$file = $fileHandler->getFileBySlug($slug);

// Check if file is expired
if ($file && $fileHandler->isFileExpired($file)) {
    $file = null;
}

if (!$file) {
    http_response_code(404);
    showError("File not found or has been deleted.");
}


$security->startSession();
$accessKey = 'access_' . $slug;
$isUnlocked = !$file['is_private'] || (isset($_SESSION[$accessKey]) && $_SESSION[$accessKey] === true);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['passcode'])) {
    // Validate CSRF token
    if (!$security->validateCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid CSRF token';
    } else {
        $rateLimit = $security->checkPasswordRateLimit();
        if (!$rateLimit['allowed']) {
            $error = $rateLimit['message'];
        } else {
            if ($security->verifyPasscode($_POST['passcode'], $file['access_passcode_hash'])) {
                $_SESSION[$accessKey] = true;
                $isUnlocked = true;
            } else {
                $security->recordPasswordFailure();
                $error = 'Invalid passcode';
            }
        }
    }
}

$isImage = in_array($file['extension'], ['jpg', 'jpeg', 'png', 'gif', 'webp']);
$downloadUrl = "../download/" . $file['slug'];
$previewUrl = ($isImage && $isUnlocked) ? $downloadUrl . "?preview=1" : "";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($file['original_filename']); ?> - Just Share | View Shared File</title>
    <meta name="description" content="View and download <?php echo htmlspecialchars($file['original_filename']); ?> shared anonymously via Just Share. No account required for accessing shared files with expiration dates.">
    <meta name="keywords" content="shared file, download file, view file, <?php echo htmlspecialchars($file['original_filename']); ?>">
    <meta name="author" content="Just Share">
    <meta name="robots" content="noindex, nofollow">
    <link rel="canonical" href="<?php echo $config['base_url']; ?>/view/<?php echo htmlspecialchars($slug); ?>">
    <link rel="icon" type="image/jpeg" href="<?php echo $config['base_url']; ?>/logo.jpeg">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo $config['base_url']; ?>/view/<?php echo htmlspecialchars($slug); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($file['original_filename']); ?> - Just Share">
    <meta property="og:description" content="View and download this file shared via Just Share">
    <?php if (in_array(strtolower(pathinfo($file['original_filename'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
        <meta property="og:image" content="<?php echo $config['base_url']; ?>/view/<?php echo htmlspecialchars($slug); ?>/preview">
    <?php else: ?>
        <meta property="og:image" content="<?php echo $config['base_url']; ?>/og-image.png">
    <?php endif; ?>
    <meta property="og:site_name" content="Just Share">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?php echo $config['base_url']; ?>/view/<?php echo htmlspecialchars($slug); ?>">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($file['original_filename']); ?> - Just Share">
    <meta name="twitter:description" content="View and download this file shared via Just Share">
    <?php if (in_array(strtolower(pathinfo($file['original_filename'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
        <meta name="twitter:image" content="<?php echo $config['base_url']; ?>/view/<?php echo htmlspecialchars($slug); ?>/preview">
    <?php else: ?>
        <meta name="twitter:image" content="<?php echo $config['base_url']; ?>/twitter-image.png">
    <?php endif; ?>

    <meta name="theme-color" content="#00b894">

    <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "MediaObject",
            "name": "<?php echo htmlspecialchars($file['original_filename']); ?>",
            "contentUrl": "<?php echo $config['base_url']; ?>/view/<?php echo htmlspecialchars($slug); ?>",
            "uploadDate": "<?php echo $file['created_at']->toDateTime()->format('c'); ?>",
            "fileSize": "<?php echo $file['file_size']; ?> bytes"
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

        /* Navbar Customization */
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

        .nav-link:hover,
        .nav-link.active {
            color: var(--primary) !important;
        }

        .navbar-toggler {
            border-color: var(--border-color);
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(255, 255, 255, 0.55)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
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
            margin-bottom: 30px;
        }

        .header h1 {
            margin-bottom: 10px;
        }

        .file-card {
            background: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .preview-area {
            background: #2d2d2d;
            min-height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .preview-area img {
            max-width: 100%;
            max-height: 500px;
            object-fit: contain;
        }

        .doc-icon {
            font-size: 80px;
            color: var(--text-muted);
        }

        .file-details {
            padding: 30px;
        }

        .file-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 15px;
            word-break: break-all;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .meta-item label {
            display: block;
            color: var(--text-muted);
            font-size: 12px;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .meta-item span {
            font-size: 15px;
            font-weight: 500;
        }

        .actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .btn {
            flex: 1;
            padding: 15px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-primary {
            background: var(--primary);
            color: #121212;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
        }

        .btn-secondary {
            background: var(--input-bg);
            color: var(--text-main);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: #333;
        }

        .btn-danger {
            background: rgba(255, 118, 117, 0.1);
            color: var(--danger);
            border: 1px solid var(--danger);
        }

        .btn-danger:hover {
            background: var(--danger);
            color: white;
        }

        /* Lock Screen */
        .lock-screen {
            padding: 50px 20px;
            text-align: center;
        }

        .lock-icon {
            font-size: 60px;
            margin-bottom: 20px;
        }

        .passcode-form {
            max-width: 400px;
            margin: 0 auto;
        }

        .passcode-input {
            width: 100%;
            padding: 15px;
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-main);
            font-size: 18px;
            margin-bottom: 15px;
            text-align: center;
            letter-spacing: 2px;
        }

        .error-msg {
            color: var(--danger);
            margin-bottom: 15px;
            background: rgba(255, 118, 117, 0.1);
            padding: 10px;
            border-radius: 6px;
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
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
            padding: 30px;
            border-radius: 16px;
            width: 90%;
            max-width: 400px;
            border: 1px solid var(--border-color);
        }

        .modal h3 {
            margin-bottom: 20px;
            color: var(--text-main);
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
    </style>
</head>

<body>


    <div class="container">
        <div class="file-card">
            <?php if (!$isUnlocked): ?>
                <div class="lock-screen">
                    <div class="lock-icon">🔒</div>
                    <h2>Private File</h2>
                    <p style="color: var(--text-muted); margin-bottom: 30px;">This file is password protected</p>

                    <form method="POST" class="passcode-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $security->generateCsrfToken(); ?>">
                        <?php if ($error): ?>
                            <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <input type="password" name="passcode" class="passcode-input" placeholder="Enter Passcode" required autofocus>
                        <button type="submit" class="btn btn-primary">Unlock File</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="preview-area">
                    <?php if ($isImage): ?>
                        <img src="<?php echo $previewUrl; ?>" alt="Preview">
                    <?php else: ?>
                        <div class="doc-icon"><?php echo getFileIcon($file['extension']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="file-details">
                    <div class="file-title"><?php echo htmlspecialchars($file['title'] ?: $file['original_filename']); ?></div>

                    <div class="meta-grid">
                        <div class="meta-item">
                            <label>File Size</label>
                            <span><?php echo formatFileSize($file['file_size']); ?></span>
                        </div>
                        <div class="meta-item">
                            <label>Type</label>
                            <span><?php echo strtoupper($file['extension']); ?></span>
                        </div>
                        <div class="meta-item">
                            <label>Uploaded</label>
                            <span><?php echo date('M j, Y H:i', $file['created_at']->toDateTime()->getTimestamp()); ?></span>
                        </div>
                        <div class="meta-item">
                            <label>Expires On</label>
                            <span style="color: var(--danger);"><?php echo date('M j, Y H:i', $file['expiry_date']->toDateTime()->getTimestamp()); ?></span>
                        </div>
                    </div>

                    <div class="actions">
                        <a href="<?php echo $downloadUrl; ?>" class="btn btn-primary">
                            Download
                        </a>
                        <button onclick="copyShareLink()" class="btn btn-secondary">
                            <span>🔗</span> Share Link
                        </button>
                        <button onclick="showDeleteModal()" class="btn btn-danger">
                            <span>🗑️</span> Delete
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('action') === 'delete') {
                showDeleteModal();
            }
        });

        function copyShareLink() {
            const url = window.location.href;
            navigator.clipboard.writeText(url).then(() => {
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    background: '#1e1e1e',
                    color: '#ffffff',
                    didOpen: (toast) => {
                        toast.addEventListener('mouseenter', Swal.stopTimer)
                        toast.addEventListener('mouseleave', Swal.resumeTimer)
                    }
                });

                Toast.fire({
                    icon: 'success',
                    title: 'Link copied to clipboard'
                });
            });
        }

        function showDeleteModal() {
            Swal.fire({
                title: 'Delete File',
                text: "Enter the deletion passcode provided during upload to permanently delete this file.",
                input: 'password',
                inputPlaceholder: 'Deletion Code',
                showCancelButton: true,
                confirmButtonText: 'Delete Permanently',
                confirmButtonColor: '#d33',
                background: '#1e1e1e',
                color: '#ffffff',
                inputAttributes: {
                    autocapitalize: 'off',
                    autocorrect: 'off'
                },
                preConfirm: (passcode) => {
                    if (!passcode) {
                        Swal.showValidationMessage('Please enter the deletion code');
                    }
                    return passcode;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    deleteFile(result.value);
                }
            });
        }

        async function deleteFile(passcode) {
            if (!passcode) return;

            try {
                const response = await fetch('../delete/<?php echo $slug; ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        deletion_passcode: passcode,
                        csrf_token: '<?php echo $security->generateCsrfToken(); ?>'
                    })
                });

                const result = await response.json();

                if (result.success) {
                    Swal.fire({
                        title: 'Deleted!',
                        text: 'File deleted successfully',
                        icon: 'success',
                        background: '#1e1e1e',
                        color: '#ffffff',
                        confirmButtonColor: '#00b894'
                    }).then(() => {
                        window.location.href = '../home';
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: result.message || 'Deletion failed',
                        icon: 'error',
                        background: '#1e1e1e',
                        color: '#ffffff',
                        confirmButtonColor: '#d33'
                    });
                }
            } catch (e) {
                Swal.fire({
                    title: 'Error!',
                    text: 'An error occurred',
                    icon: 'error',
                    background: '#1e1e1e',
                    color: '#ffffff',
                    confirmButtonColor: '#d33'
                });
            }
        }
    </script>
</body>

</html>
<?php
function getFileIcon($ext)
{
    $icons = [
        'pdf' => '📄',
        'doc' => '📝',
        'docx' => '📝',
        'xls' => '📊',
        'xlsx' => '📊',
        'ppt' => '📊',
        'pptx' => '📊',
        'txt' => '📃',
        'zip' => '📦',
        'rar' => '📦'
    ];
    return $icons[strtolower($ext)] ?? '📎';
}

function formatFileSize($bytes)
{
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

function showError($message)
{
    //show error if not found or deleted and redirect to files 
    global $config;
    $redirectUrl = isset($config['base_url']) ? $config['base_url'] . '/files' : 'files';
    header("Refresh: 2; url=" . $redirectUrl);
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error - SecureShare</title>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <style>
            body {
                background-color: #121212;
                color: #ffffff;
                font-family: sans-serif;
            }
        </style>
    </head>

    <body>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'File Not Found',
                    text: '<?php echo addslashes($message); ?>',
                    background: '#1e1e1e',
                    color: '#ffffff',
                    timer: 2000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    allowOutsideClick: false,
                    allowEscapeKey: false
                });
            });
        </script>
    </body>

    </html>
<?php
    exit;
}
?>
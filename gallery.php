<?php
require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'FileHandler.php';
require_once 'SecurityHandler.php';

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

// Always check for expired files when loading the gallery
$fileHandler->cleanupExpiredFiles();

$files = $fileHandler->getRecentFiles(50);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Primary SEO Meta Tags -->
    <title>Public Gallery - Just Share | Browse Anonymous Shared Files</title>
    <meta name="description" content="Browse anonymously shared files including documents, images, and other files. All uploads are visible in the public gallery with expiration dates. Download files instantly without account creation.">
    <meta name="keywords" content="public gallery, shared files, browse files, download files, file sharing gallery, recent uploads">
    <meta name="author" content="Just Share">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?php echo $config['base_url']; ?>/files">
    <link rel="icon" type="image/jpeg" href="<?php echo $config['base_url']; ?>/logo.jpeg">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo $config['base_url']; ?>/files">
    <meta property="og:title" content="Public Gallery - Just Share">
    <meta property="og:description" content="Browse the public gallery of recently shared files. Discover and download files from our community.">
    <meta property="og:image" content="<?php echo $config['base_url']; ?>/og-gallery.png">
    <meta property="og:site_name" content="Just Share">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?php echo $config['base_url']; ?>/files">
    <meta name="twitter:title" content="Public Gallery - Just Share">
    <meta name="twitter:description" content="Browse recently shared files on Just Share. Simple and secure file sharing.">
    <meta name="twitter:image" content="<?php echo $config['base_url']; ?>/twitter-gallery.png">
    
    <!-- Additional Meta Tags -->
    <meta name="theme-color" content="#00b894">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    
    <!-- Structured Data -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "CollectionPage",
      "name": "Public Gallery - Just Share",
      "description": "Browse recently shared files in the Just Share public gallery",
      "url": "<?php echo $config['base_url']; ?>/files",
      "isPartOf": {
        "@type": "WebSite",
        "name": "Just Share",
        "url": "<?php echo $config['base_url']; ?>"
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
            --danger: #ff7675;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
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
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .container {
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .header h1 { font-size: 28px; font-weight: 700; }
        
        .btn {
            padding: 10px 20px;
            background: var(--primary);
            color: #121212;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .btn:hover { background: var(--primary-hover); }
        
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 25px;
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
            height: 180px;
            background: #2d2d2d;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }
        
        .gallery-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .gallery-preview.private img {
            filter: blur(10px);
            opacity: 0.5;
        }
        
        .gallery-preview .doc-icon {
            font-size: 64px;
            color: var(--text-muted);
        }
        
        .lock-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: rgba(0,0,0,0.3);
            z-index: 2;
        }
        
        .lock-icon {
            font-size: 32px;
            margin-bottom: 5px;
        }
        
        .lock-text {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .gallery-info { padding: 15px; }
        
        .gallery-title {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .gallery-meta {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
        }
        
        .gallery-actions { display: flex; gap: 10px; }
        
        .gallery-btn {
            flex: 1;
            padding: 8px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
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
        
        .btn-download:hover { background: var(--primary-hover); }
        
        .btn-copy {
            background: var(--bg-color);
            color: var(--text-main);
            border: 1px solid var(--border-color);
        }
        
        .btn-copy:hover { background: #333; }

        .btn-delete {
            background: rgba(255, 118, 117, 0.1);
            color: var(--danger);
            border: 1px solid var(--danger);
        }
        
        .btn-delete:hover { background: var(--danger); color: white; }

        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 50px;
            color: var(--text-muted);
        }
    </style>
</head>
<body>

    
    <div class="container">
        <div class="header">
            <h1>Public Gallery</h1>
        </div>
        
        <div class="gallery-grid">
            <?php if (empty($files)): ?>
                <div class="empty-state">
                    <h2>No files uploaded yet</h2>
                    <p>Be the first to share something!</p>
                </div>
            <?php else: ?>
                <?php foreach ($files as $file): ?>
                    <?php 
                        $isImage = in_array($file['extension'], ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                        $isPrivate = $file['is_private'] ?? false;
                        $viewUrl = "file/" . $file['slug'];
                        $downloadUrl = "download/" . $file['slug'];
                        $previewUrl = ($isImage && !$isPrivate) ? $downloadUrl . "?preview=1" : "";
                    ?>
                    <div class="gallery-item">
                        <a href="<?php echo $viewUrl; ?>" style="text-decoration: none; color: inherit;">
                            <div class="gallery-preview <?php echo $isPrivate ? 'private' : ''; ?>">
                                <?php if ($isPrivate): ?>
                                    <?php if ($isImage): ?>
                                        <!-- blurred placeholder for private images -->
                                        <img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjMzMzIi8+PC9zdmc+" alt="Locked">
                                    <?php else: ?>
                                        <div class="doc-icon"><?php echo getFileIcon($file['extension']); ?></div>
                                    <?php endif; ?>
                                    <div class="lock-overlay">
                                        <div class="lock-icon"></div>
                                        <div class="lock-text">Private</div>
                                    </div>
                                <?php else: ?>
                                    <?php if ($isImage): ?>
                                        <img src="<?php echo $previewUrl; ?>" alt="<?php echo htmlspecialchars($file['original_filename']); ?>" loading="lazy">
                                    <?php else: ?>
                                        <div class="doc-icon"><?php echo getFileIcon($file['extension']); ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </a>
                        
                        <div class="gallery-info">
                            <div class="gallery-title" title="<?php echo htmlspecialchars($file['original_filename']); ?>">
                                <a href="<?php echo $viewUrl; ?>" style="color: inherit; text-decoration: none;">
                                    <?php echo htmlspecialchars($file['title'] ?: $file['original_filename']); ?>
                                </a>
                            </div>
                            <div class="gallery-meta">
                                <span><?php echo strtoupper($file['extension']); ?></span>
                                <span title="Deletion Date" style="color: var(--danger);">
                                    Exp: <?php echo date('M j', $file['expiry_date']->toDateTime()->getTimestamp()); ?>
                                </span>
                            </div>
                            <div class="gallery-actions">
                                <a href="<?php echo $downloadUrl; ?>" class="gallery-btn btn-download" target="_blank">Download</a>
                                <button class="gallery-btn btn-copy" onclick="copyLink('<?php echo $viewUrl; ?>')">Copy Link</button>
                                <a href="#" onclick="confirmDelete(event, '<?php echo $viewUrl; ?>?action=delete')" class="gallery-btn btn-delete">Delete</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function copyLink(url) {
            //here we Construct absolute URL for the file
            let baseUrl = window.location.href;
            if (baseUrl.endsWith('/')) baseUrl = baseUrl.slice(0, -1);
            //if we are at /files, remove it
            if (baseUrl.endsWith('/files')) {
                baseUrl = baseUrl.substring(0, baseUrl.length - 6);
            } else if (baseUrl.endsWith('gallery.php')) {
                baseUrl = baseUrl.substring(0, baseUrl.length - 11);
            }
            // trailing slash
            if (!baseUrl.endsWith('/')) baseUrl += '/';
            
            const fullUrl = baseUrl + url;
            
            navigator.clipboard.writeText(fullUrl).then(() => {
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

        function confirmDelete(event, url) {
            event.preventDefault();
            Swal.fire({
                title: 'Are you sure?',
                text: "You are about to go to the deletion page.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, proceed!',
                background: '#1e1e1e',
                color: '#ffffff'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            })
        }
    </script>
</body>
</html>

<?php
function getFileIcon($ext) {
    $icons = [
        'pdf' => '📄', 'doc' => '📝', 'docx' => '📝', 'xls' => '📊', 'xlsx' => '📊',
        'ppt' => '📊', 'pptx' => '📊', 'txt' => '📃', 'zip' => '📦', 'rar' => '📦'
    ];
    return $icons[strtolower($ext)] ?? '📎';
}
?>
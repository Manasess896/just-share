<?php
require_once 'config.php';
$config = require 'config.php';
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <link rel="icon" type="image/jpeg" href="<?php echo $config['base_url']; ?>/logo.jpeg">
    <style>
        :root {
            --bg-color: #121212;
            --card-bg: #1e1e1e;
            --text-main: #ffffff;
            --text-muted: #b0b0b0;
            --primary: #00b894;
            --primary-hover: #00a383;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        
        .container {
            padding: 20px;
            max-width: 600px;
        }
        
        h1 {
            font-size: 120px;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 0;
            line-height: 1;
            text-shadow: 0 0 20px rgba(0, 184, 148, 0.2);
        }
        
        h2 {
            font-size: 32px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        p {
            color: var(--text-muted);
            font-size: 18px;
            margin-bottom: 40px;
            line-height: 1.6;
        }
        
        .btn-group {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        .btn {
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 16px;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: #fff;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 184, 148, 0.3);
        }
        
        .btn-outline {
            border: 2px solid var(--primary);
            color: var(--primary);
            background: transparent;
        }
        
        .btn-outline:hover {
            background: rgba(0, 184, 148, 0.1);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>404</h1>
        <h2>Page Not Found</h2>
        <p>The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.</p>
        <div class="btn-group">
            <a href="<?php echo $config['base_url']; ?>/home" class="btn btn-primary">Go Home</a>
            <a href="<?php echo $config['base_url']; ?>/files" class="btn btn-outline">View Gallery</a>
        </div>
    </div>
</body>
</html>

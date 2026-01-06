<?php
 //security Handler - Handles rate limiting, input sanitization, and security checks instead of screenshotting my code and posting it on x or redit how about you just tell me where the issue is 

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    header('Location: home');
    exit;
}

use MongoDB\Client;

class SecurityHandler {
    private $collection;
    private $config;
    
    public function __construct(Client $client, string $database, array $config) {
        $this->config = $config;
        $this->collection = $client->selectCollection($database, $config['collections']['rate_limits']);
    }
    public function checkRateLimit(): array {
        $clientIp = $this->getClientIp();
        $currentTime = time();
        $windowStart = $currentTime - $this->config['rate_limit_window'];
        $this->collection->deleteMany([
            'timestamp' => ['$lt' => $windowStart]
        ]);
        


        $count = $this->collection->countDocuments([
            'timestamp' => ['$gte' => $windowStart],
            'ip' => $clientIp
        ]);
        
        if ($count >= $this->config['rate_limit_per_minute']) {
            return [
                'allowed' => false,
                'message' => 'Rate limit exceeded. Please wait before uploading more files.',
                'retry_after' => 60
            ];
        }
        
        return ['allowed' => true];
    }








     //check rate limit for password attempts
    public function checkPasswordRateLimit(): array {
        $clientIp = $this->getClientIp();
        $currentTime = time();
        $windowStart = $currentTime - 300; // 5 minutes window
        
        $count = $this->collection->countDocuments([
            'timestamp' => ['$gte' => $windowStart],
            'ip' => $clientIp,
            'type' => 'password_failure'
        ]);
        
        if ($count >= 5) { // 5 attempts per 5 minutes
            return [
                'allowed' => false,
                'message' => 'Too many failed attempts. Please try again in 5 minutes.',
                'retry_after' => 300
            ];
        }
        
        return ['allowed' => true];
    }

    //record a password failure
    public function recordPasswordFailure(): void {
        $this->collection->insertOne([
            'ip' => $this->getClientIp(),
            'timestamp' => time(),
            'type' => 'password_failure',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    }
    



     //record an upload attempt for rate limiting
    public function recordUpload(int $fileCount = 1): void {
        $clientIp = $this->getClientIp();
        
        for ($i = 0; $i < $fileCount; $i++) {
            $this->collection->insertOne([
                'ip' => $clientIp,
                'timestamp' => time(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        }
    }
    

     //sanitize filename to prevent XSS and directory traversal
    public function sanitizeFilename(string $filename): string {
   
        $filename = basename($filename);
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        $filename = preg_replace('/\.+/', '.', $filename);
        $filename = ltrim($filename, '.');
        if (strlen($filename) > 200) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $name = substr(pathinfo($filename, PATHINFO_FILENAME), 0, 190);
            $filename = $name . '.' . $ext;
        }
        
        return $filename;
    }
    
     //clean text input (titles, descriptions) to prevent XSS
  
    public function sanitizeText(string $text, int $maxLength = 500): string {
        //remove  html tags
        $text = strip_tags($text);
        $text = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
      //limit length
        $text = trim($text);
        if (strlen($text) > $maxLength) {
            $text = substr($text, 0, $maxLength);
        }
        
        return $text;
    }
    
   
     //generate a  passcode
    
    public function generatePasscode(int $length = 12): string {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*';
        $passcode = '';
        $max = strlen($characters) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $passcode .= $characters[random_int(0, $max)];
        }
        
        return $passcode;
    }
    
    // Hash a passcode for secure storage
    public function hashPasscode(string $passcode): string {
        return password_hash($passcode, PASSWORD_DEFAULT);
    }
    
    public function verifyPasscode(string $passcode, string $hash): bool {
        return password_verify($passcode, $hash);
    }

    // CSRF Protection
    public function generateCsrfToken(): string {
        $this->startSecureSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public function validateCsrfToken(?string $token): bool {
        $this->startSecureSession();
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public function startSession(): void {
        $this->startSecureSession();
    }

    private function startSecureSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            $sessionConfig = $this->config['session'] ?? [];
            if (!empty($sessionConfig)) {
                session_set_cookie_params([
                    'lifetime' => $sessionConfig['cookie_lifetime'] ?? 0,
                    'path' => $sessionConfig['cookie_path'] ?? '/',
                    'domain' => $sessionConfig['cookie_domain'] ?? '',
                    'secure' => $sessionConfig['cookie_secure'] ?? false,
                    'httponly' => $sessionConfig['cookie_httponly'] ?? true,
                    'samesite' => $sessionConfig['cookie_samesite'] ?? 'Lax'
                ]);
                ini_set('session.use_strict_mode', $sessionConfig['use_strict_mode'] ?? 1);
            }
            session_start();
        }
    }

    public function generateSlug(string $text, int $length = 50): string {
        $slug = strtolower($text);
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        if (strlen($slug) > $length) {
            $slug = substr($slug, 0, $length);
            $slug = rtrim($slug, '-');
        }
        $slug .= '-' . bin2hex(random_bytes(4));
        
        return $slug;
    }

     //validate file type and MIME type
    public function validateFileType(string $filename, string $tmpPath): array {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowedTypes = array_merge(
            $this->config['allowed_documents'],
            $this->config['allowed_images']
        );
        //check if file extension is allowed
        if (!isset($allowedTypes[$extension])) {
            return [
                'valid' => false,
                'message' => "File type '$extension' is not allowed."
            ];
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes[$extension])) {
            return [
                'valid' => false,
                'message' => "MIME type mismatch. Expected " . implode(' or ', $allowedTypes[$extension]) . ", got $mimeType"
            ];
        }
        
        return [
            'valid' => true,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'file_category' => isset($this->config['allowed_images'][$extension]) ? 'image' : 'document'
        ];
    }
    private function getClientIp(): string {
        // Production configuration: No proxy.
        // Only trust REMOTE_ADDR to prevent IP spoofing via headers.
        if (!empty($_SERVER['REMOTE_ADDR']) && filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP)) {
            return (string)$_SERVER['REMOTE_ADDR'];
        }
        
        return '0.0.0.0';
    }
    
    public function validateExpiry($expiry): ?int {
        $expiry = intval($expiry);
        if (in_array($expiry, $this->config['expiry_options'])) {
            return $expiry;
        }
        return null;
    }
}

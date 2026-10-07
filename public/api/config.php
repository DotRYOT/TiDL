<?php
/**
 * TidalDL - Configuration
 * 
 * This file handles session management and common utilities
 * for the Tidal music downloader backend.
 * 
 * Optimized for Arch Linux with auto-detection of binary paths.
 */

// Enable error reporting in development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/httpd/tidaldl_php.log');

// Set headers for JSON API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * Auto-detect binary paths for Arch Linux
 */
function detectBinary($name, $fallbacks = []) {
    // First check common Arch Linux paths
    $archPaths = [
        "/usr/bin/{$name}",
        "/usr/local/bin/{$name}",
        "/bin/{$name}",
    ];
    
    foreach ($archPaths as $path) {
        if (file_exists($path) && is_executable($path)) {
            return $path;
        }
    }
    
    // Try 'which' command
    $which = trim(shell_exec("which {$name} 2>/dev/null") ?? '');
    if (!empty($which) && file_exists($which)) {
        return $which;
    }
    
    // Try fallbacks
    foreach ($fallbacks as $fallback) {
        if (file_exists($fallback) && is_executable($fallback)) {
            return $fallback;
        }
    }
    
    // Return just the name (rely on PATH)
    return $name;
}

/**
 * Detect the operating system
 */
function detectOS() {
    if (file_exists('/etc/arch-release')) {
        return 'arch';
    } elseif (file_exists('/etc/os-release')) {
        $content = file_get_contents('/etc/os-release');
        if (strpos($content, 'Arch') !== false) return 'arch';
        if (strpos($content, 'Ubuntu') !== false) return 'ubuntu';
        if (strpos($content, 'Debian') !== false) return 'debian';
        if (strpos($content, 'Fedora') !== false) return 'fedora';
    }
    return 'unknown';
}

// Detect OS
$detectedOS = detectOS();

// Configuration constants with Arch Linux paths
define('OS_TYPE', $detectedOS);
define('DOWNLOAD_DIR', __DIR__ . '/../downloads/');
define('TEMP_DIR', __DIR__ . '/../temp/');
define('YTDLP_BIN', detectBinary('yt-dlp'));
define('FFMPEG_BIN', detectBinary('ffmpeg'));
define('TIDAL_API_BASE', 'https://api.tidal.com/v1');
define('TIDAL_AUTH_URL', 'https://auth.tidal.com/v1/oauth2');

// Arch Linux specific paths
if ($detectedOS === 'arch') {
    define('LOG_DIR', '/var/log/httpd/');
    define('PHP_INI', '/etc/php/php.ini');
} else {
    define('LOG_DIR', __DIR__ . '/../logs/');
    define('PHP_INI', '');
}

// Ensure directories exist
if (!is_dir(DOWNLOAD_DIR)) {
    mkdir(DOWNLOAD_DIR, 0775, true);
}
if (!is_dir(TEMP_DIR)) {
    mkdir(TEMP_DIR, 0775, true);
}
if ($detectedOS !== 'arch' && !is_dir(LOG_DIR)) {
    mkdir(LOG_DIR, 0775, true);
}

/**
 * Start PHP session for storing credentials
 */
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Configure session for security
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Lax');
        session_start();
    }
}

/**
 * Send JSON response
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit();
}

/**
 * Get JSON request body
 */
function getRequestBody() {
    $body = file_get_contents('php://input');
    if (empty($body)) {
        return [];
    }
    $data = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [];
    }
    return $data ?? [];
}

/**
 * Validate required fields in request
 */
function validateRequired($data, $fields) {
    $missing = [];
    foreach ($fields as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
            $missing[] = $field;
        }
    }
    if (!empty($missing)) {
        jsonResponse([
            'success' => false,
            'error' => 'Missing required fields: ' . implode(', ', $missing)
        ], 400);
    }
}

/**
 * Make HTTP request using cURL
 */
function httpRequest($url, $method = 'GET', $data = null, $headers = []) {
    $ch = curl_init();
    
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'TidalDL/1.0 (Arch Linux)',
    ];
    
    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        if ($data) {
            if (is_array($data)) {
                $options[CURLOPT_POSTFIELDS] = http_build_query($data);
            } else {
                $options[CURLOPT_POSTFIELDS] = $data;
            }
        }
    }
    
    if (!empty($headers)) {
        $options[CURLOPT_HTTPHEADER] = $headers;
    }
    
    curl_setopt_array($ch, $options);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $errno = curl_errno($ch);
    
    curl_close($ch);
    
    if ($errno) {
        return ['error' => $error, 'errno' => $errno, 'http_code' => $httpCode];
    }
    
    $decoded = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return $decoded;
    }
    
    return ['raw' => $response, 'http_code' => $httpCode];
}

/**
 * Sanitize filename - remove unsafe characters
 */
function sanitizeFilename($filename) {
    // Remove null bytes
    $filename = str_replace("\0", '', $filename);
    
    // Replace unsafe characters
    $filename = preg_replace('/[<>:"|?*\x00-\x1F]/', '', $filename);
    
    // Replace multiple spaces with single space
    $filename = preg_replace('/\s+/', ' ', $filename);
    
    // Trim whitespace and dots
    $filename = trim($filename, " \t\n\r\0\x0B.");
    
    // Limit length
    if (strlen($filename) > 200) {
        $filename = substr($filename, 0, 200);
    }
    
    // Fallback for empty filename
    if (empty($filename)) {
        $filename = 'untitled';
    }
    
    return $filename;
}

/**
 * Check if required binaries are available
 */
function checkDependencies() {
    $checks = [
        'yt-dlp' => YTDLP_BIN,
        'ffmpeg' => FFMPEG_BIN,
    ];
    
    $missing = [];
    foreach ($checks as $name => $path) {
        $testCmd = escapeshellarg($path) . ' --version 2>&1';
        $output = shell_exec($testCmd);
        if ($output === null || trim($output) === '') {
            $missing[] = $name;
        }
    }
    
    return [
        'all_ok' => empty($missing),
        'missing' => $missing,
        'paths' => $checks,
        'os' => OS_TYPE,
    ];
}

/**
 * Get disk space information
 */
function getDiskSpace() {
    $free = disk_free_space(DOWNLOAD_DIR);
    $total = disk_total_space(DOWNLOAD_DIR);
    
    return [
        'free_bytes' => $free,
        'total_bytes' => $total,
        'free_human' => formatBytes($free),
        'total_human' => formatBytes($total),
        'used_percent' => round((1 - $free / $total) * 100, 1),
    ];
}

/**
 * Format bytes to human readable
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

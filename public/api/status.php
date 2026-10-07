<?php
/**
 * TidalDL - System Status Endpoint
 * 
 * Returns system health information including
 * dependency status, disk space, and configuration.
 */

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

startSession();

// Gather system information
$systemInfo = [
    'success' => true,
    'system' => [
        'os' => OS_TYPE,
        'os_pretty' => getOSPrettyName(),
        'php_version' => PHP_VERSION,
        'php_sapi' => php_sapi_name(),
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
    ],
    'dependencies' => checkDependencies(),
    'disk_space' => getDiskSpace(),
    'directories' => [
        'download_dir' => [
            'path' => DOWNLOAD_DIR,
            'exists' => is_dir(DOWNLOAD_DIR),
            'writable' => is_writable(DOWNLOAD_DIR),
        ],
        'temp_dir' => [
            'path' => TEMP_DIR,
            'exists' => is_dir(TEMP_DIR),
            'writable' => is_writable(TEMP_DIR),
        ],
    ],
    'session' => [
        'authenticated' => !empty($_SESSION['tidal_access_token']),
        'client_id_set' => !empty($_SESSION['tidal_client_id']),
    ],
    'php_extensions' => [
        'curl' => extension_loaded('curl'),
        'json' => extension_loaded('json'),
        'session' => extension_loaded('session'),
        'gd' => extension_loaded('gd'),
        'mbstring' => extension_loaded('mbstring'),
    ],
    'php_settings' => [
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'max_execution_time' => ini_get('max_execution_time'),
        'memory_limit' => ini_get('memory_limit'),
    ],
];

/**
 * Get pretty OS name
 */
function getOSPrettyName() {
    if (file_exists('/etc/os-release')) {
        $content = file_get_contents('/etc/os-release');
        if (preg_match('/PRETTY_NAME="?([^"\n]+)"?/m', $content, $matches)) {
            return $matches[1];
        }
    }
    
    switch (OS_TYPE) {
        case 'arch': return 'Arch Linux';
        case 'ubuntu': return 'Ubuntu';
        case 'debian': return 'Debian';
        case 'fedora': return 'Fedora';
        default: return PHP_OS;
    }
}

jsonResponse($systemInfo);

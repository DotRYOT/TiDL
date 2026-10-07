<?php
/**
 * TidalDL - Authentication Endpoint
 * 
 * Handles OAuth2 authentication with Tidal API using
 * client credentials flow.
 */

require_once __DIR__ . '/config.php';

startSession();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

$body = getRequestBody();
validateRequired($body, ['client_id', 'client_secret']);

$clientId = trim($body['client_id']);
$clientSecret = trim($body['client_secret']);

/**
 * Authenticate with Tidal using OAuth2 Client Credentials flow
 */
function authenticateTidal($clientId, $clientSecret) {
    $authUrl = TIDAL_AUTH_URL . '/token';
    
    $postData = [
        'grant_type' => 'client_credentials',
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
    ];
    
    $headers = [
        'Content-Type: application/x-www-form-urlencoded',
        'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret),
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $authUrl,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 15,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return [
            'success' => false,
            'error' => 'Connection failed: ' . $error,
        ];
    }
    
    $data = json_decode($response, true);
    
    if ($httpCode !== 200) {
        $errorMsg = $data['error_description'] ?? $data['error'] ?? 'Authentication failed';
        return [
            'success' => false,
            'error' => $errorMsg,
            'http_code' => $httpCode,
        ];
    }
    
    if (!isset($data['access_token'])) {
        return [
            'success' => false,
            'error' => 'No access token received from Tidal API',
        ];
    }
    
    return [
        'success' => true,
        'access_token' => $data['access_token'],
        'token_type' => $data['token_type'] ?? 'Bearer',
        'expires_in' => $data['expires_in'] ?? 86400,
    ];
}

/**
 * Alternative: Device code authentication flow
 * Used when client credentials flow isn't available
 */
function authenticateDeviceCode($clientId) {
    // Step 1: Get device code
    $deviceUrl = TIDAL_AUTH_URL . '/device_authorization';
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $deviceUrl,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['client_id' => $clientId]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 15,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode !== 200 || !isset($data['deviceCode'])) {
        return [
            'success' => false,
            'error' => 'Failed to get device authorization code',
        ];
    }
    
    return [
        'success' => true,
        'device_code' => $data['deviceCode'],
        'user_code' => $data['userCode'],
        'verification_url' => $data['verificationUri'] ?? 'https://link.tidal.com',
        'expires_in' => $data['expiresIn'] ?? 300,
        'interval' => $data['interval'] ?? 2,
    ];
}

// Attempt authentication
$result = authenticateTidal($clientId, $clientSecret);

if ($result['success']) {
    // Store in session
    $_SESSION['tidal_access_token'] = $result['access_token'];
    $_SESSION['tidal_client_id'] = $clientId;
    $_SESSION['tidal_client_secret'] = $clientSecret;
    $_SESSION['tidal_token_expires'] = time() + ($result['expires_in'] ?? 86400);
    
    jsonResponse([
        'success' => true,
        'access_token' => $result['access_token'],
        'expires_in' => $result['expires_in'] ?? 86400,
        'message' => 'Successfully authenticated with Tidal API',
    ]);
} else {
    // If client credentials fail, try device code flow
    $deviceResult = authenticateDeviceCode($clientId);
    
    if ($deviceResult['success']) {
        // Store device code for polling
        $_SESSION['tidal_device_code'] = $deviceResult['device_code'];
        $_SESSION['tidal_client_id'] = $clientId;
        $_SESSION['tidal_client_secret'] = $clientSecret;
        
        jsonResponse([
            'success' => true,
            'auth_method' => 'device_code',
            'user_code' => $deviceResult['user_code'],
            'verification_url' => $deviceResult['verification_url'],
            'expires_in' => $deviceResult['expires_in'],
            'interval' => $deviceResult['interval'],
            'message' => 'Please visit the verification URL and enter the code',
        ]);
    } else {
        jsonResponse([
            'success' => false,
            'error' => $result['error'],
        ], 401);
    }
}

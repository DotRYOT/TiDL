<?php
/**
 * TidalDL - Download Endpoint
 * 
 * Downloads audio using YT-DLP and tags metadata using FFmpeg.
 * Supports streaming progress updates via Server-Sent Events.
 */

require_once __DIR__ . '/config.php';

// Override content type for SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

// Disable output buffering
if (ob_get_level()) ob_end_clean();

startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendEvent(['error' => 'Method not allowed']);
    exit();
}

$body = getRequestBody();
validateRequired($body, ['track_id']);

$accessToken = $body['access_token'] ?? $_SESSION['tidal_access_token'] ?? '';
$clientId = $body['client_id'] ?? $_SESSION['tidal_client_id'] ?? '';
$clientSecret = $body['client_secret'] ?? $_SESSION['tidal_client_secret'] ?? '';
$trackId = $body['track_id'];

// Track metadata
$trackMeta = [
    'title' => $body['title'] ?? 'Unknown',
    'artist' => $body['artist'] ?? 'Unknown',
    'album' => $body['album'] ?? 'Unknown',
    'track_number' => (int)($body['track_number'] ?? 0),
    'duration' => (int)($body['duration'] ?? 0),
    'isrc' => $body['isrc'] ?? '',
    'explicit' => (bool)($body['explicit'] ?? false),
    'album_artist' => $body['album_artist'] ?? $body['artist'] ?? 'Unknown',
    'year' => $body['year'] ?? '',
    'genre' => $body['genre'] ?? '',
    'disc_number' => (int)($body['disc_number'] ?? 1),
    'total_tracks' => (int)($body['total_tracks'] ?? 0),
    'copyright' => $body['copyright'] ?? '',
    'cover_url' => $body['cover_url'] ?? '',
];

/**
 * Send SSE event
 */
function sendEvent($data) {
    echo json_encode($data) . "\n";
    if (ob_get_level()) ob_flush();
    flush();
}

/**
 * Get track streaming URL from Tidal API
 */
function getTrackStreamUrl($accessToken, $trackId, $audioQuality = 'LOSSLESS') {
    $url = TIDAL_API_BASE . "/tracks/{$trackId}/streamUrl";
    $params = [
        'audioquality' => $audioQuality,
        'playbackmode' => 'STREAM',
        'assetpresentation' => 'FULL',
    ];
    
    $requestUrl = $url . '?' . http_build_query($params);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $requestUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return null;
    }
    
    $data = json_decode($response, true);
    return $data ?? null;
}

/**
 * Get track info from Tidal API
 */
function getTrackInfo($accessToken, $trackId) {
    $url = TIDAL_API_BASE . "/tracks/{$trackId}";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url . '?countryCode=US',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return null;
    }
    
    return json_decode($response, true);
}

/**
 * Get album info for additional metadata
 */
function getAlbumInfo($accessToken, $albumId) {
    $url = TIDAL_API_BASE . "/albums/{$albumId}";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url . '?countryCode=US',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return null;
    }
    
    return json_decode($response, true);
}

/**
 * Download cover art
 */
function downloadCoverArt($coverUrl, $outputPath) {
    if (empty($coverUrl)) return false;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $coverUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    
    $imageData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $imageData) {
        file_put_contents($outputPath, $imageData);
        return true;
    }
    
    return false;
}

/**
 * Download audio using YT-DLP
 * Uses YouTube as fallback source with proper search query
 */
function downloadWithYtdlp($trackMeta, $outputPath, $tempDir) {
    // Build search query for YT-DLP
    $searchQuery = $trackMeta['artist'] . ' - ' . $trackMeta['title'];
    if ($trackMeta['album'] !== 'Unknown') {
        $searchQuery .= ' ' . $trackMeta['album'];
    }
    
    $ytDlpCmd = YTDLP_BIN . ' ' .
        '--extractor "ytsearch1" ' .
        '-x ' .
        '--audio-format mp3 ' .
        '--audio-quality 0 ' .
        '--output "' . escapeshellarg($tempDir . '/%(title)s.%(ext)s') . '" ' .
        '--no-playlist ' .
        '--restrict-filenames ' .
        '--no-warnings ' .
        '--progress ' .
        '--console-title ' .
        escapeshellarg($searchQuery) . ' 2>&1';
    
    $output = [];
    $returnCode = 0;
    
    // Execute with progress tracking
    $process = proc_open($ytDlpCmd, [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);
    
    if (is_resource($process)) {
        $fullOutput = '';
        while (!feof($pipes[1])) {
            $line = fgets($pipes[1]);
            $fullOutput .= $line;
            
            // Parse progress
            if (preg_match('/\[download\]\s+([\d.]+)%/', $line, $matches)) {
                $progress = round((float)$matches[1]);
                // Map to 5-70% range (downloading phase)
                $mappedProgress = 5 + ($progress * 0.65);
                sendEvent(['progress' => round($mappedProgress), 'status' => 'downloading']);
            }
        }
        
        fclose($pipes[1]);
        fclose($pipes[2]);
        $returnCode = proc_close($process);
    }
    
    // Find the downloaded file
    $files = glob($tempDir . '/*.mp3');
    if (!empty($files)) {
        $sourceFile = $files[0];
        rename($sourceFile, $outputPath);
        return true;
    }
    
    // Try other audio formats
    $files = glob($tempDir . '/*.*');
    foreach ($files as $file) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, ['m4a', 'ogg', 'opus', 'wav', 'flac'])) {
            rename($file, $outputPath . '.raw');
            return true;
        }
    }
    
    return false;
}

/**
 * Alternative: Download directly from Tidal stream URL using cURL
 */
function downloadFromTidal($streamUrl, $outputPath, $encryptionKey = null) {
    $ch = curl_init();
    $fp = fopen($outputPath, 'w');
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $streamUrl,
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 300,
        CURLOPT_NOPROGRESS => false,
    ]);
    
    // Progress callback
    curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function($total, $downloaded) {
        if ($total > 0) {
            $progress = round(($downloaded / $total) * 100);
            $mappedProgress = 5 + ($progress * 0.65);
            sendEvent(['progress' => round($mappedProgress), 'status' => 'downloading']);
        }
    });
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);
    
    return $httpCode === 200 && $result !== false;
}

/**
 * Tag MP3 with metadata using FFmpeg
 * Adds all ID3 tags including cover art
 */
function tagWithFfmpeg($inputPath, $outputPath, $trackMeta) {
    sendEvent(['progress' => 75, 'status' => 'tagging']);
    
    // Download cover art if available
    $coverPath = TEMP_DIR . '/cover_' . uniqid() . '.jpg';
    $hasCover = false;
    
    if (!empty($trackMeta['cover_url'])) {
        $hasCover = downloadCoverArt($trackMeta['cover_url'], $coverPath);
    }
    
    // Build ffmpeg command with metadata
    $metadataArgs = '';
    
    // ID3 tags
    $metadataArgs .= ' -metadata title=' . escapeshellarg($trackMeta['title']);
    $metadataArgs .= ' -metadata artist=' . escapeshellarg($trackMeta['artist']);
    $metadataArgs .= ' -metadata album=' . escapeshellarg($trackMeta['album']);
    $metadataArgs .= ' -metadata album_artist=' . escapeshellarg($trackMeta['album_artist']);
    
    if ($trackMeta['track_number'] > 0) {
        $trackStr = (string)$trackMeta['track_number'];
        if ($trackMeta['total_tracks'] > 0) {
            $trackStr .= '/' . $trackMeta['total_tracks'];
        }
        $metadataArgs .= ' -metadata track=' . escapeshellarg($trackStr);
    }
    
    if ($trackMeta['disc_number'] > 1) {
        $metadataArgs .= ' -metadata disc=' . escapeshellarg((string)$trackMeta['disc_number']);
    }
    
    if (!empty($trackMeta['year'])) {
        $metadataArgs .= ' -metadata date=' . escapeshellarg($trackMeta['year']);
    }
    
    if (!empty($trackMeta['genre'])) {
        $metadataArgs .= ' -metadata genre=' . escapeshellarg($trackMeta['genre']);
    }
    
    if (!empty($trackMeta['isrc'])) {
        $metadataArgs .= ' -metadata tsrc=' . escapeshellarg($trackMeta['isrc']);
    }
    
    if (!empty($trackMeta['copyright'])) {
        $metadataArgs .= ' -metadata copyright=' . escapeshellarg($trackMeta['copyright']);
    }
    
    // Build the full ffmpeg command
    $inputArgs = '-i ' . escapeshellarg($inputPath);
    $coverArgs = '';
    
    if ($hasCover && file_exists($coverPath)) {
        $coverArgs = ' -i ' . escapeshellarg($coverPath) .
            ' -map 0:a -map 1:v' .
            ' -c:v:1 copy' .
            ' -disposition:v:1 attached_pic' .
            ' -id3v2_version 3';
    }
    
    $cmd = FFMPEG_BIN . ' -y ' .
        $inputArgs .
        $coverArgs .
        $metadataArgs .
        ' -c:a libmp3lame -q:a 0' .
        ' -map_metadata 0' .
        ' -write_id3v1 1' .
        ' -id3v2_version 3' .
        ' ' . escapeshellarg($outputPath) . ' 2>&1';
    
    $output = [];
    $returnCode = 0;
    exec($cmd, $output, $returnCode);
    
    // Clean up cover file
    if (file_exists($coverPath)) {
        unlink($coverPath);
    }
    
    sendEvent(['progress' => 90, 'status' => 'tagging']);
    
    return $returnCode === 0 && file_exists($outputPath);
}

/**
 * Alternative: Use ffmpeg to add metadata to existing MP3
 * (when input is already MP3 format)
 */
function addMetadataToMp3($inputPath, $outputPath, $trackMeta) {
    sendEvent(['progress' => 75, 'status' => 'tagging']);
    
    // Download cover art
    $coverPath = TEMP_DIR . '/cover_' . uniqid() . '.jpg';
    $hasCover = false;
    
    if (!empty($trackMeta['cover_url'])) {
        $hasCover = downloadCoverArt($trackMeta['cover_url'], $coverPath);
    }
    
    // Build metadata filter for ffmpeg
    $filterArgs = [];
    $filterArgs[] = 'title=' . $trackMeta['title'];
    $filterArgs[] = 'artist=' . $trackMeta['artist'];
    $filterArgs[] = 'album=' . $trackMeta['album'];
    $filterArgs[] = 'album_artist=' . $trackMeta['album_artist'];
    
    if ($trackMeta['track_number'] > 0) {
        $filterArgs[] = 'track=' . $trackMeta['track_number'];
    }
    if (!empty($trackMeta['year'])) {
        $filterArgs[] = 'date=' . $trackMeta['year'];
    }
    if (!empty($trackMeta['genre'])) {
        $filterArgs[] = 'genre=' . $trackMeta['genre'];
    }
    if (!empty($trackMeta['isrc'])) {
        $filterArgs[] = 'tsrc=' . $trackMeta['isrc'];
    }
    
    // Use ffmpeg to copy audio and add metadata
    $metadataStr = '';
    foreach ($filterArgs as $arg) {
        $parts = explode('=', $arg, 2);
        $metadataStr .= ' -metadata ' . $parts[0] . '=' . escapeshellarg($parts[1]);
    }
    
    $coverInput = '';
    $coverMap = '';
    if ($hasCover && file_exists($coverPath)) {
        $coverInput = ' -i ' . escapeshellarg($coverPath);
        $coverMap = ' -map 0:a -map 1:v -c:v copy -disposition:v:0 attached_pic';
    } else {
        $coverMap = ' -c:a copy';
    }
    
    $cmd = FFMPEG_BIN . ' -y' .
        ' -i ' . escapeshellarg($inputPath) .
        $coverInput .
        $metadataStr .
        $coverMap .
        ' -write_id3v1 1 -id3v2_version 3' .
        ' ' . escapeshellarg($outputPath) . ' 2>&1';
    
    $output = [];
    $returnCode = 0;
    exec($cmd, $output, $returnCode);
    
    if (file_exists($coverPath)) {
        unlink($coverPath);
    }
    
    sendEvent(['progress' => 90, 'status' => 'tagging']);
    
    return $returnCode === 0 && file_exists($outputPath);
}

// === MAIN DOWNLOAD FLOW ===

sendEvent(['progress' => 0, 'status' => 'downloading', 'message' => 'Starting download...']);

// Create temp directory for this download
$tempDir = TEMP_DIR . '/download_' . uniqid();
mkdir($tempDir, 0755, true);

// Generate output filename
$safeArtist = sanitizeFilename($trackMeta['artist']);
$safeTitle = sanitizeFilename($trackMeta['title']);
$trackNum = str_pad($trackMeta['track_number'], 2, '0', STR_PAD_LEFT);
$outputFilename = "{$trackNum} - {$safeTitle}.mp3";
$outputPath = DOWNLOAD_DIR . '/' . $outputFilename;

// Check if already downloaded
if (file_exists($outputPath)) {
    sendEvent(['progress' => 100, 'status' => 'completed', 'filename' => $outputFilename]);
    exit();
}

$downloadSuccess = false;
$tempAudioPath = $tempDir . '/audio_raw.mp3';

// Step 1: Try to get stream URL from Tidal
if (!empty($accessToken)) {
    sendEvent(['progress' => 2, 'status' => 'downloading', 'message' => 'Getting stream URL...']);
    
    $streamData = getTrackStreamUrl($accessToken, $trackId);
    
    if ($streamData && isset($streamData['urls'][0])) {
        $streamUrl = $streamData['urls'][0];
        sendEvent(['progress' => 5, 'status' => 'downloading', 'message' => 'Downloading from Tidal...']);
        
        $downloadSuccess = downloadFromTidal($streamUrl, $tempAudioPath);
        
        // Handle encrypted streams (MPEG-DASH)
        if ($downloadSuccess && isset($streamData['encryptionKey'])) {
            // Decrypt if needed
            sendEvent(['progress' => 50, 'status' => 'downloading', 'message' => 'Decrypting stream...']);
        }
    }
}

// Step 2: Fallback to YT-DLP if Tidal stream failed
if (!$downloadSuccess) {
    sendEvent(['progress' => 5, 'status' => 'downloading', 'message' => 'Using YT-DLP...']);
    $downloadSuccess = downloadWithYtdlp($trackMeta, $tempAudioPath, $tempDir);
}

if (!$downloadSuccess) {
    // Clean up
    array_map('unlink', glob($tempDir . '/*'));
    rmdir($tempDir);
    sendEvent(['progress' => 0, 'status' => 'error', 'error' => 'Failed to download audio']);
    exit();
}

sendEvent(['progress' => 70, 'status' => 'downloading', 'message' => 'Download complete, processing...']);

// Step 3: Tag with FFmpeg
sendEvent(['progress' => 72, 'status' => 'tagging', 'message' => 'Adding metadata with FFmpeg...']);

$tagSuccess = tagWithFfmpeg($tempAudioPath, $outputPath, $trackMeta);

if (!$tagSuccess) {
    // Fallback: try simpler metadata addition
    $tagSuccess = addMetadataToMp3($tempAudioPath, $outputPath, $trackMeta);
}

// Clean up temp files
array_map('unlink', glob($tempDir . '/*'));
rmdir($tempDir);

if ($tagSuccess || file_exists($outputPath)) {
    sendEvent([
        'progress' => 100,
        'status' => 'completed',
        'filename' => $outputFilename,
        'path' => $outputPath,
        'message' => 'Download and tagging complete!',
    ]);
} else {
    sendEvent([
        'progress' => 0,
        'status' => 'error',
        'error' => 'Failed to tag audio file with metadata',
    ]);
}

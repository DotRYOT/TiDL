<?php
/**
 * TidalDL - Search Endpoint
 * 
 * Searches Tidal API for tracks, albums, artists, and playlists.
 */

require_once __DIR__ . '/config.php';

startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

$body = getRequestBody();
validateRequired($body, ['query']);

$accessToken = $body['access_token'] ?? $_SESSION['tidal_access_token'] ?? '';
$query = trim($body['query']);
$type = $body['type'] ?? 'tracks';
$limit = min((int)($body['limit'] ?? 20), 50);
$offset = (int)($body['offset'] ?? 0);

if (empty($accessToken)) {
    jsonResponse(['success' => false, 'error' => 'Not authenticated'], 401);
}

/**
 * Search Tidal API
 */
function searchTidal($accessToken, $query, $type, $limit, $offset) {
    $baseUrl = TIDAL_API_BASE;
    $params = [
        'query' => $query,
        'limit' => $limit,
        'offset' => $offset,
        'countryCode' => 'US',
    ];
    
    $endpoint = '';
    switch ($type) {
        case 'tracks':
            $endpoint = '/search/tracks';
            break;
        case 'albums':
            $endpoint = '/search/albums';
            break;
        case 'artists':
            $endpoint = '/search/artists';
            break;
        case 'playlists':
            $endpoint = '/search/playlists';
            break;
        default:
            $endpoint = '/search';
    }
    
    $url = $baseUrl . $endpoint . '?' . http_build_query($params);
    
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Accept: application/json',
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 15,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => 'Request failed: ' . $error];
    }
    
    if ($httpCode === 401) {
        return ['success' => false, 'error' => 'Access token expired or invalid'];
    }
    
    if ($httpCode !== 200) {
        return ['success' => false, 'error' => 'API returned status ' . $httpCode];
    }
    
    $data = json_decode($response, true);
    
    if (!$data) {
        return ['success' => false, 'error' => 'Invalid API response'];
    }
    
    return ['success' => true, 'data' => $data];
}

/**
 * Format track data for frontend
 */
function formatTrack($track) {
    $artists = [];
    if (isset($track['artists'])) {
        foreach ($track['artists'] as $artist) {
            $artists[] = $artist['name'];
        }
    }
    
    $coverUrl = '';
    if (isset($track['album']['cover'])) {
        $coverUrl = 'https://resources.tidal.com/images/' . 
                    str_replace('-', '/', $track['album']['cover']) . '/320x320.jpg';
    }
    
    return [
        'id' => (string)($track['id'] ?? ''),
        'title' => $track['title'] ?? 'Unknown',
        'artist' => implode(', ', $artists) ?: ($track['artist']['name'] ?? 'Unknown'),
        'album' => $track['album']['title'] ?? 'Unknown',
        'duration' => (int)($track['duration'] ?? 0),
        'trackNumber' => (int)($track['trackNumber'] ?? 0),
        'volumeNumber' => (int)($track['volumeNumber'] ?? 1),
        'explicit' => (bool)($track['explicit'] ?? false),
        'audioQuality' => $track['audioQuality'] ?? 'LOW',
        'coverUrl' => $coverUrl,
        'isrc' => $track['isrc'] ?? '',
        'copyright' => $track['copyright'] ?? '',
    ];
}

/**
 * Format album data for frontend
 */
function formatAlbum($album) {
    $artists = [];
    if (isset($album['artists'])) {
        foreach ($album['artists'] as $artist) {
            $artists[] = $artist['name'];
        }
    }
    
    $coverUrl = '';
    if (isset($album['cover'])) {
        $coverUrl = 'https://resources.tidal.com/images/' . 
                    str_replace('-', '/', $album['cover']) . '/320x320.jpg';
    }
    
    return [
        'id' => (string)($album['id'] ?? ''),
        'title' => $album['title'] ?? 'Unknown',
        'artist' => implode(', ', $artists) ?: ($album['artist']['name'] ?? 'Unknown'),
        'numberOfTracks' => (int)($album['numberOfTracks'] ?? 0),
        'duration' => (int)($album['duration'] ?? 0),
        'coverUrl' => $coverUrl,
        'releaseDate' => $album['releaseDate'] ?? '',
        'audioQuality' => $album['audioQuality'] ?? 'LOW',
    ];
}

// Execute search
$result = searchTidal($accessToken, $query, $type, $limit, $offset);

if (!$result['success']) {
    jsonResponse($result, 500);
}

$data = $result['data'];
$response = [
    'success' => true,
    'query' => $query,
    'type' => $type,
    'total' => 0,
    'tracks' => [],
    'albums' => [],
];

// Format tracks
if (isset($data['items'])) {
    $response['total'] = $data['totalNumberOfItems'] ?? count($data['items']);
    
    if ($type === 'tracks' || $type === '') {
        foreach ($data['items'] as $track) {
            $response['tracks'][] = formatTrack($track);
        }
    } elseif ($type === 'albums') {
        foreach ($data['items'] as $album) {
            $response['albums'][] = formatAlbum($album);
        }
    }
}

// For general search, include both
if ($type === '') {
    if (isset($data['tracks']['items'])) {
        foreach ($data['tracks']['items'] as $track) {
            $response['tracks'][] = formatTrack($track);
        }
    }
    if (isset($data['albums']['items'])) {
        foreach ($data['albums']['items'] as $album) {
            $response['albums'][] = formatAlbum($album);
        }
    }
}

jsonResponse($response);

# TidalDL - Music Downloader

A modern, OLED-themed music downloader that uses the Tidal API with YT-DLP for audio downloading and FFmpeg for comprehensive metadata tagging.

## Features

- 🔐 **Tidal API Integration** - Authenticate with your own Client ID and Client Secret
- 🔍 **Smart Search** - Search for tracks, albums, artists across the Tidal catalog
- 📥 **High-Quality Downloads** - Support for Hi-Res Lossless, Lossless, and High quality
- 🏷️ **Full Metadata Tagging** - Automatically adds ID3 tags using FFmpeg including:
  - Title, Artist, Album, Album Artist
  - Track number, Disc number
  - Year, Genre, ISRC
  - Copyright information
  - Embedded cover art
- 🎨 **OLED-Perfect UI** - Pure black (#000000) background with high contrast accents
- 📊 **Real-time Progress** - Live download and tagging progress tracking
- 🔄 **Queue Management** - Batch download with queue management

## Requirements

### Server
- PHP 7.4+ with cURL extension
- Apache with mod_rewrite (or Nginx equivalent config)
- Write permissions for `downloads/` and `temp/` directories

### External Tools
- **YT-DLP** - For audio downloading from YouTube as fallback
  ```bash
  # Install via pip
  pip install yt-dlp
  
  # Or download binary
  # https://github.com/yt-dlp/yt-dlp/releases
  ```
  
- **FFmpeg** - For audio processing and metadata tagging
  ```bash
  # Ubuntu/Debian
  sudo apt install ffmpeg
  
  # macOS
  brew install ffmpeg
  
  # Windows - download from https://ffmpeg.org/download.html
  ```

### Tidal API Credentials
You'll need a Tidal API Client ID and Client Secret. These can be obtained from:
- Tidal Developer Portal: https://developer.tidal.com/
- Or by registering an application with Tidal

## Installation

1. **Clone/Extract** the project to your web server's document root

2. **Install dependencies**
   ```bash
   npm install
   ```

3. **Build the frontend**
   ```bash
   npm run build
   ```

4. **Set permissions**
   ```bash
   chmod -R 755 downloads/
   chmod -R 755 temp/
   ```

5. **Configure PHP** (optional)
   - Edit `public/api/config.php` to adjust paths if needed
   - Ensure `YTDLP_BIN` and `FFMPEG_BIN` point to correct binaries

## Usage

1. Open the web application in your browser
2. Enter your Tidal Client ID and Client Secret
3. Click "Connect to Tidal" to authenticate
4. Search for music using the search bar
5. Select tracks and add them to the download queue
6. Downloads will begin automatically with progress tracking
7. Completed files will be saved to the `downloads/` directory with full metadata

## Architecture

```
├── src/                    # React frontend source
│   ├── App.tsx            # Main application component
│   ├── components/        # UI components
│   │   ├── Header.tsx     # App header with connection status
│   │   ├── AuthSection.tsx # API credential input
│   │   ├── SearchSection.tsx # Search interface
│   │   └── DownloadQueue.tsx # Download queue display
│   └── types.ts           # TypeScript type definitions
├── public/
│   └── api/               # PHP backend
│       ├── config.php     # Configuration & utilities
│       ├── auth.php       # OAuth2 authentication
│       ├── search.php     # Track/album search
│       └── download.php   # Download & metadata tagging
├── downloads/             # Completed downloads
└── temp/                  # Temporary files during download
```

## API Endpoints

### POST /api/auth.php
Authenticate with Tidal API.
```json
{
  "client_id": "your-client-id",
  "client_secret": "your-client-secret"
}
```

### POST /api/search.php
Search for tracks or albums.
```json
{
  "access_token": "bearer-token",
  "query": "search term",
  "type": "tracks|albums",
  "limit": 20
}
```

### POST /api/download.php
Download a track with metadata tagging.
```json
{
  "access_token": "bearer-token",
  "track_id": "123456",
  "title": "Track Title",
  "artist": "Artist Name",
  "album": "Album Name",
  "track_number": 1,
  "isrc": "USRC12345678"
}
```

## Security Notes

- Credentials are stored in PHP session (server-side only)
- Never commit your API credentials to version control
- The PHP backend validates all inputs before processing
- File paths are sanitized to prevent directory traversal

## License

MIT License - Use at your own risk. Respect copyright and terms of service.

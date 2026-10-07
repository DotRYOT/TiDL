# TidalDL - Music Downloader for Arch Linux

A modern, OLED-themed music downloader that uses the Tidal API with YT-DLP for audio downloading and FFmpeg for comprehensive metadata tagging.

![TidalDL](https://img.shields.io/badge/Arch%20Linux-Compatible-blue?style=flat-square)
![PHP](https://img.shields.io/badge/PHP-8.0+-purple?style=flat-square)
![License](https://img.shields.io/badge/license-MIT-green?style=flat-square)

## 🎯 Features

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
- 🐧 **Arch Linux Optimized** - Native support for Arch Linux package ecosystem

## 📋 Table of Contents

- [Requirements](#requirements)
- [Installation on Arch Linux](#installation-on-arch-linux)
- [Configuration](#configuration)
- [Usage](#usage)
- [Troubleshooting](#troubleshooting)
- [Architecture](#architecture)
- [API Documentation](#api-documentation)
- [Security](#security)

## 🔧 Requirements

### System Requirements (Arch Linux)

```bash
# Base system
- Arch Linux (rolling release)
- PHP 8.0 or higher
- Web server (Nginx or Apache)
- FFmpeg
- yt-dlp
```

### Optional Dependencies

```bash
# For better performance
- php-opcache
- php-apcu

# For SSL/TLS support
- openssl
```

## 🚀 Installation on Arch Linux

### Method 1: Quick Install Script (Recommended)

```bash
# Download and run the install script
curl -O https://raw.githubusercontent.com/your-repo/tidaldl/main/install-arch.sh
chmod +x install-arch.sh
./install-arch.sh
```

### Method 2: Manual Installation

#### Step 1: Install Dependencies

```bash
# Update system
sudo pacman -Syu

# Install PHP and extensions
sudo pacman -S php php-fpm php-curl php-gd

# Install FFmpeg
sudo pacman -S ffmpeg

# Install yt-dlp (from official repos)
sudo pacman -S yt-dlp

# OR from AUR (if you prefer latest version)
# yay -S yt-dlp-git
```

#### Step 2: Install Web Server

**Option A: Nginx (Recommended for Arch)**

```bash
# Install Nginx
sudo pacman -S nginx

# Enable and start Nginx
sudo systemctl enable nginx
sudo systemctl start nginx
```

**Option B: Apache**

```bash
# Install Apache
sudo pacman -S apache

# Enable and start Apache
sudo systemctl enable httpd
sudo systemctl start httpd
```

#### Step 3: Clone and Setup TidalDL

```bash
# Choose your installation directory
# Option A: System-wide (recommended)
sudo mkdir -p /srv/http/tidaldl
cd /srv/http/tidaldl

# Option B: User directory
mkdir -p ~/web/tidaldl
cd ~/web/tidaldl

# Clone the repository (or copy files)
git clone https://github.com/your-repo/tidaldl.git .

# OR if you have the files locally:
# cp -r /path/to/tidaldl/* .

# Set proper ownership
sudo chown -R http:http /srv/http/tidaldl
# OR for user directory:
# chown -R $USER:$USER ~/web/tidaldl

# Set permissions
sudo chmod -R 755 /srv/http/tidaldl
sudo chmod -R 777 /srv/http/tidaldl/downloads
sudo chmod -R 777 /srv/http/tidaldl/temp

# Create required directories
mkdir -p downloads temp
```

#### Step 4: Install Node.js Dependencies

```bash
# Install Node.js and npm (if not already installed)
sudo pacman -S nodejs npm

# Install frontend dependencies
npm install

# Build the frontend
npm run build
```

#### Step 5: Configure Web Server

**For Nginx:**

```bash
# Create Nginx configuration
sudo nano /etc/nginx/conf.d/tidaldl.conf
```

Add the following configuration:

```nginx
server {
    listen 80;
    server_name localhost;  # Change to your domain if using one
    
    root /srv/http/tidaldl/dist;
    index index.html;
    
    # Frontend routes
    location / {
        try_files $uri $uri/ /index.html;
    }
    
    # PHP API routes
    location /api/ {
        root /srv/http/tidaldl/dist;
        
        location ~ \.php$ {
            fastcgi_pass unix:/run/php-fpm/php-fpm.sock;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            include fastcgi_params;
            
            # Increase timeout for downloads
            fastcgi_read_timeout 600;
            fastcgi_send_timeout 600;
        }
    }
    
    # Static assets
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
    
    # Security headers
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
}
```

```bash
# Test Nginx configuration
sudo nginx -t

# Reload Nginx
sudo systemctl reload nginx
```

**For Apache:**

```bash
# Enable required modules
sudo sed -i 's/#LoadModule rewrite_module/LoadModule rewrite_module/' /etc/httpd/conf/httpd.conf
sudo sed -i 's/#LoadModule proxy_fcgi_module/LoadModule proxy_fcgi_module/' /etc/httpd/conf/httpd.conf

# Create Apache configuration
sudo nano /etc/httpd/conf/extra/tidaldl.conf
```

Add the following configuration:

```apache
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /srv/http/tidaldl/dist
    
    <Directory /srv/http/tidaldl/dist>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # PHP-FPM configuration
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php-fpm/php-fpm.sock|fcgi://localhost"
    </FilesMatch>
    
    # Increase timeout for downloads
    ProxyTimeout 600
    
    ErrorLog /var/log/httpd/tidaldl_error.log
    CustomLog /var/log/httpd/tidaldl_access.log combined
</VirtualHost>
```

```bash
# Include the configuration
echo "Include conf/extra/tidaldl.conf" | sudo tee -a /etc/httpd/conf/httpd.conf

# Test Apache configuration
sudo httpd -t

# Restart Apache
sudo systemctl restart httpd
```

#### Step 6: Configure PHP-FPM

```bash
# Edit PHP-FPM configuration
sudo nano /etc/php/php-fpm.d/www.conf

# Make sure these settings are correct:
# listen = /run/php-fpm/php-fpm.sock
# listen.owner = http
# listen.group = http
# listen.mode = 0660

# Enable PHP-FPM
sudo systemctl enable php-fpm
sudo systemctl start php-fpm
```

#### Step 7: Configure PHP

```bash
# Edit PHP configuration
sudo nano /etc/php/php.ini

# Set these values:
# upload_max_filesize = 100M
# post_max_size = 100M
# max_execution_time = 600
# max_input_time = 600
# memory_limit = 256M

# Restart PHP-FPM to apply changes
sudo systemctl restart php-fpm
```

#### Step 8: Verify Installation

```bash
# Check PHP version
php -v

# Check FFmpeg
ffmpeg -version

# Check yt-dlp
yt-dlp --version

# Check PHP-FPM status
systemctl status php-fpm

# Check web server status
systemctl status nginx  # or httpd for Apache

# Test PHP processing
echo "<?php phpinfo(); ?>" | sudo tee /srv/http/tidaldl/dist/test.php
# Visit http://localhost/test.php in your browser
# Remove test file after verification
sudo rm /srv/http/tidaldl/dist/test.php
```

## ⚙️ Configuration

### TidalDL Configuration

Edit `public/api/config.php` to customize paths:

```php
// Arch Linux default paths
define('DOWNLOAD_DIR', __DIR__ . '/../downloads/');
define('TEMP_DIR', __DIR__ . '/../temp/');
define('YTDLP_BIN', '/usr/bin/yt-dlp');
define('FFMPEG_BIN', '/usr/bin/ffmpeg');
```

### Getting Tidal API Credentials

1. Visit [Tidal Developer Portal](https://developer.tidal.com/)
2. Register for a developer account
3. Create a new application
4. Note your **Client ID** and **Client Secret**
5. Use these credentials in the web interface

### Firewall Configuration (if needed)

```bash
# If using UFW
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# If using firewalld
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload
```

## 🎵 Usage

### Starting the Application

1. **Open your browser** and navigate to:
   - `http://localhost` (if using localhost)
   - `http://your-domain.com` (if using a custom domain)

2. **Authenticate with Tidal:**
   - Enter your Client ID
   - Enter your Client Secret
   - Click "Connect to Tidal"

3. **Search for Music:**
   - Use the search bar to find tracks, albums, or artists
   - Select search type (Tracks or Albums)
   - Press Enter or click the search button

4. **Download Music:**
   - Click the "+" button to add individual tracks to queue
   - Or select multiple tracks and click "Download Selected"
   - Monitor progress in the Downloads tab

5. **Access Downloaded Files:**
   - Files are saved to `/srv/http/tidaldl/downloads/`
   - All metadata is embedded in the MP3 files
   - Cover art is automatically downloaded and embedded

### Command Line Management

```bash
# View recent downloads
ls -lh /srv/http/tidaldl/downloads/

# Check download size
du -sh /srv/http/tidaldl/downloads/

# Clear temporary files
sudo rm -rf /srv/http/tidaldl/temp/*

# View logs (Nginx)
sudo tail -f /var/log/nginx/error.log

# View logs (Apache)
sudo tail -f /var/log/httpd/tidaldl_error.log
```

## 🔧 Troubleshooting

### Common Issues

#### 1. PHP-FPM Connection Error

**Error:** `502 Bad Gateway` or `Connection refused`

**Solution:**
```bash
# Check PHP-FPM status
sudo systemctl status php-fpm

# Restart PHP-FPM
sudo systemctl restart php-fpm

# Check socket permissions
ls -l /run/php-fpm/php-fpm.sock

# Fix permissions if needed
sudo chown http:http /run/php-fpm/php-fpm.sock
```

#### 2. Permission Denied for Downloads

**Error:** `Permission denied` when downloading

**Solution:**
```bash
# Fix ownership
sudo chown -R http:http /srv/http/tidaldl/downloads
sudo chown -R http:http /srv/http/tidaldl/temp

# Fix permissions
sudo chmod -R 775 /srv/http/tidaldl/downloads
sudo chmod -R 775 /srv/http/tidaldl/temp
```

#### 3. yt-dlp Not Found

**Error:** `yt-dlp: command not found`

**Solution:**
```bash
# Verify installation
which yt-dlp

# If not installed
sudo pacman -S yt-dlp

# Update config.php with correct path
# define('YTDLP_BIN', '/usr/bin/yt-dlp');
```

#### 4. FFmpeg Metadata Not Applied

**Error:** Downloaded files have no metadata

**Solution:**
```bash
# Verify FFmpeg installation
ffmpeg -version

# Check FFmpeg path in config.php
# define('FFMPEG_BIN', '/usr/bin/ffmpeg');

# Test FFmpeg manually
ffmpeg -version
```

#### 5. Authentication Failed

**Error:** `Invalid client credentials`

**Solution:**
- Verify your Client ID and Client Secret are correct
- Check that your Tidal developer account is active
- Ensure you're using the correct API endpoint
- Check PHP error logs: `sudo journalctl -u php-fpm`

#### 6. Download Timeout

**Error:** Downloads fail or timeout

**Solution:**
```bash
# Increase PHP timeout
sudo nano /etc/php/php.ini
# max_execution_time = 600
# max_input_time = 600

# Increase web server timeout
# For Nginx: fastcgi_read_timeout 600;
# For Apache: ProxyTimeout 600

# Restart services
sudo systemctl restart php-fpm
sudo systemctl restart nginx  # or httpd
```

### Debug Mode

Enable debug logging:

```bash
# Check PHP error logs
sudo journalctl -u php-fpm -f

# Check web server logs
sudo tail -f /var/log/nginx/error.log  # Nginx
sudo tail -f /var/log/httpd/error_log  # Apache

# Test API endpoint manually
curl -X POST http://localhost/api/auth.php \
  -H "Content-Type: application/json" \
  -d '{"client_id":"test","client_secret":"test"}'
```

## 🏗️ Architecture

```
tidaldl/
├── src/                          # React frontend source
│   ├── App.tsx                   # Main application component
│   ├── components/               # UI components
│   │   ├── Header.tsx           # App header with connection status
│   │   ├── AuthSection.tsx      # API credential input
│   │   ├── SearchSection.tsx    # Search interface
│   │   └── DownloadQueue.tsx    # Download queue display
│   ├── types.ts                 # TypeScript type definitions
│   ├── index.css                # Global styles (OLED theme)
│   └── main.tsx                 # React entry point
│
├── public/                       # Static assets & PHP backend
│   ├── api/                     # PHP API endpoints
│   │   ├── config.php          # Configuration & utilities
│   │   ├── auth.php            # OAuth2 authentication
│   │   ├── search.php          # Track/album search
│   │   └── download.php        # Download & metadata tagging
│   └── README.md               # This file
│
├── dist/                         # Built frontend (generated)
│   ├── index.html               # Main HTML file
│   ├── assets/                  # Compiled JS/CSS
│   └── api/                     # PHP files (copied from public/)
│
├── downloads/                    # Completed downloads
│   └── *.mp3                    # Downloaded tracks with metadata
│
├── temp/                         # Temporary files during download
│   └── download_*/              # Per-download temp directories
│
├── install-arch.sh              # Arch Linux install script
├── package.json                 # Node.js dependencies
├── vite.config.js               # Vite build configuration
└── README.md                    # This file
```

## 📡 API Documentation

### Authentication Endpoint

**POST** `/api/auth.php`

Authenticate with Tidal API using OAuth2.

**Request:**
```json
{
  "client_id": "your-client-id",
  "client_secret": "your-client-secret"
}
```

**Response (Success):**
```json
{
  "success": true,
  "access_token": "bearer-token-here",
  "expires_in": 86400,
  "message": "Successfully authenticated with Tidal API"
}
```

**Response (Device Code Flow):**
```json
{
  "success": true,
  "auth_method": "device_code",
  "user_code": "ABCD-1234",
  "verification_url": "https://link.tidal.com",
  "expires_in": 300,
  "interval": 2,
  "message": "Please visit the verification URL and enter the code"
}
```

### Search Endpoint

**POST** `/api/search.php`

Search for tracks or albums.

**Request:**
```json
{
  "access_token": "bearer-token",
  "query": "search term",
  "type": "tracks|albums",
  "limit": 20,
  "offset": 0
}
```

**Response:**
```json
{
  "success": true,
  "query": "search term",
  "type": "tracks",
  "total": 100,
  "tracks": [
    {
      "id": "123456",
      "title": "Track Title",
      "artist": "Artist Name",
      "album": "Album Name",
      "duration": 240,
      "trackNumber": 1,
      "volumeNumber": 1,
      "explicit": false,
      "audioQuality": "HI_RES_LOSSLESS",
      "coverUrl": "https://...",
      "isrc": "USRC12345678"
    }
  ]
}
```

### Download Endpoint

**POST** `/api/download.php`

Download a track with metadata tagging. Returns Server-Sent Events for progress.

**Request:**
```json
{
  "access_token": "bearer-token",
  "client_id": "your-client-id",
  "client_secret": "your-client-secret",
  "track_id": "123456",
  "title": "Track Title",
  "artist": "Artist Name",
  "album": "Album Name",
  "track_number": 1,
  "duration": 240,
  "isrc": "USRC12345678",
  "explicit": false,
  "cover_url": "https://..."
}
```

**Response (SSE Stream):**
```
{"progress": 0, "status": "downloading", "message": "Starting download..."}
{"progress": 25, "status": "downloading"}
{"progress": 50, "status": "downloading"}
{"progress": 75, "status": "tagging", "message": "Adding metadata..."}
{"progress": 100, "status": "completed", "filename": "01 - Track Title.mp3"}
```

## 🔒 Security

### Best Practices

1. **Never commit API credentials** to version control
2. **Use HTTPS** in production (Let's Encrypt recommended)
3. **Restrict access** to trusted networks
4. **Regular updates** - Keep Arch Linux packages updated:
   ```bash
   sudo pacman -Syu
   ```
5. **Monitor logs** for suspicious activity
6. **Firewall** - Only open necessary ports

### File Permissions

```bash
# Recommended permissions
sudo chown -R http:http /srv/http/tidaldl
sudo chmod -R 755 /srv/http/tidaldl
sudo chmod -R 775 /srv/http/tidaldl/downloads
sudo chmod -R 775 /srv/http/tidaldl/temp

# PHP files should not be executable
sudo find /srv/http/tidaldl -name "*.php" -exec chmod 644 {} \;
```

### SSL/TLS Configuration (Recommended)

```bash
# Install Certbot
sudo pacman -S certbot certbot-nginx

# Get SSL certificate
sudo certbot --nginx -d your-domain.com

# Auto-renewal is configured automatically
```

## 📝 License

MIT License - Use at your own risk. Respect copyright and terms of service.

## 🤝 Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Test on Arch Linux
4. Submit a pull request

## 🐛 Known Issues

- Large downloads may timeout on slow connections
- Some tracks may not be available for download due to regional restrictions
- Cover art download may fail for some albums

## 📞 Support

For issues and questions:
- Check the [Troubleshooting](#troubleshooting) section
- Review PHP and web server logs
- Ensure all dependencies are installed and up to date

---

**Disclaimer:** This tool is for educational purposes only. Respect copyright laws and Tidal's terms of service. Only download content you have the right to access.

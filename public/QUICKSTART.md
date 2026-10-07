# TidalDL - Quick Start Guide for Arch Linux

## 🚀 Fast Setup (5 Minutes)

### Prerequisites
- Arch Linux (up to date)
- sudo access
- Internet connection

### Step 1: Install Dependencies

```bash
# One-liner to install everything needed
sudo pacman -Syu --noconfirm php php-fpm php-curl php-gd ffmpeg yt-dlp nodejs npm nginx
```

### Step 2: Setup Directories

```bash
# Create application directory
sudo mkdir -p /srv/http/tidaldl/{downloads,temp}

# Copy project files
sudo cp -r . /srv/http/tidaldl/

# Set permissions
sudo chown -R http:http /srv/http/tidaldl
sudo chmod -R 775 /srv/http/tidaldl/downloads
sudo chmod -R 775 /srv/http/tidaldl/temp
```

### Step 3: Build Frontend

```bash
cd /srv/http/tidaldl
sudo -u http npm install
sudo -u http npm run build
```

### Step 4: Configure PHP

```bash
# Edit PHP config
sudo nano /etc/php/php.ini

# Set these values:
# upload_max_filesize = 100M
# post_max_size = 100M
# max_execution_time = 600
# memory_limit = 256M
```

### Step 5: Configure Nginx

```bash
# Copy nginx config
sudo cp /srv/http/tidaldl/nginx-tidaldl.conf /etc/nginx/conf.d/tidaldl.conf

# Test and reload
sudo nginx -t
sudo systemctl enable --now nginx php-fpm
```

### Step 6: Open Browser

Navigate to `http://localhost` and enter your Tidal API credentials.

---

## 📋 Getting Tidal API Credentials

1. Go to https://developer.tidal.com/
2. Sign up / Sign in
3. Create a new application
4. Copy your **Client ID** and **Client Secret**
5. Paste them into the web interface

---

## 🔧 Troubleshooting Quick Fixes

### 502 Bad Gateway
```bash
sudo systemctl restart php-fpm
sudo systemctl restart nginx
```

### Permission Denied
```bash
sudo chown -R http:http /srv/http/tidaldl
sudo chmod -R 775 /srv/http/tidaldl/downloads /srv/http/tidaldl/temp
```

### yt-dlp Not Found
```bash
# Check if installed
which yt-dlp

# Install if missing
sudo pacman -S yt-dlp
```

### FFmpeg Not Working
```bash
# Check if installed
ffmpeg -version

# Install if missing
sudo pacman -S ffmpeg
```

### PHP Not Processing
```bash
# Check PHP-FPM socket
ls -la /run/php-fpm/php-fpm.sock

# Restart PHP-FPM
sudo systemctl restart php-fpm

# Check PHP-FPM config
cat /etc/php/php-fpm.d/www.conf | grep listen
```

---

## 📁 File Locations (Arch Linux)

| Component | Path |
|-----------|------|
| Application | `/srv/http/tidaldl/` |
| Downloads | `/srv/http/tidaldl/downloads/` |
| Temp files | `/srv/http/tidaldl/temp/` |
| Nginx config | `/etc/nginx/conf.d/tidaldl.conf` |
| PHP config | `/etc/php/php.ini` |
| PHP-FPM config | `/etc/php/php-fpm.d/www.conf` |
| Nginx logs | `/var/log/nginx/tidaldl_*.log` |
| PHP logs | `/var/log/httpd/` |

---

## 🛠️ Useful Commands

```bash
# Check all services
systemctl status nginx php-fpm

# View Nginx errors
sudo tail -f /var/log/nginx/error.log

# View PHP-FPM errors
sudo journalctl -u php-fpm -f

# Check disk space
df -h /srv/http/tidaldl/

# List downloads
ls -lh /srv/http/tidaldl/downloads/

# Clear temp files
sudo rm -rf /srv/http/tidaldl/temp/*

# Rebuild frontend
cd /srv/http/tidaldl && npm run build

# Update system packages
sudo pacman -Syu

# Check API status
curl http://localhost/api/status.php | jq
```

---

## 🔒 Security Checklist

- [ ] Change `server_name` in nginx config to your actual domain
- [ ] Set up HTTPS with Let's Encrypt: `sudo certbot --nginx`
- [ ] Restrict access with firewall rules
- [ ] Never commit API credentials to git
- [ ] Keep system updated: `sudo pacman -Syu`
- [ ] Monitor logs regularly

---

## 📖 Full Documentation

See the main [README.md](./README.md) for complete documentation including:
- Detailed architecture overview
- API endpoint documentation
- Apache configuration
- Advanced troubleshooting
- Contributing guidelines

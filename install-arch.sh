#!/bin/bash
#
# TidalDL - Arch Linux Installation Script
# Automated setup for the Tidal music downloader
#
# Usage: chmod +x install-arch.sh && ./install-arch.sh
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Configuration
INSTALL_DIR="/srv/http/tidaldl"
WEB_SERVER=""
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Banner
print_banner() {
    echo -e "${CYAN}"
    echo "╔════════════════════════════════════════════════════════════╗"
    echo "║                                                            ║"
    echo "║   ████████╗██╗██████╗ ██████╗  █████╗ ██╗     ██╗         ║"
    echo "║   ╚══██╔══╝██║██╔══██╗██╔══██╗██╔══██╗██║     ██║         ║"
    echo "║      ██║   ██║██║  ██║██████╔╝███████║██║     ██║         ║"
    echo "║      ██║   ██║██║  ██║██╔══██╗██╔══██║██║     ██║         ║"
    echo "║      ██║   ██║██████╔╝██║  ██║██║  ██║███████╗███████╗    ║"
    echo "║      ╚═╝   ╚═╝╚═════╝ ╚═╝  ╚═╝╚═╝  ╚═╝╚══════╝╚══════╝   ║"
    echo "║                                                            ║"
    echo "║              Music Downloader for Arch Linux               ║"
    echo "║                                                            ║"
    echo "╚════════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
}

# Logging functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[✓]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[!]${NC} $1"
}

log_error() {
    echo -e "${RED}[✗]${NC} $1"
}

log_step() {
    echo -e "\n${CYAN}━━━ $1 ━━━${NC}"
}

# Check if running as root
check_root() {
    if [[ $EUID -ne 0 ]]; then
        log_error "This script must be run as root (use sudo)"
        exit 1
    fi
}

# Check if running on Arch Linux
check_arch() {
    if [ ! -f /etc/arch-release ]; then
        log_warn "This script is designed for Arch Linux."
        log_warn "It may not work correctly on other distributions."
        read -p "Continue anyway? (y/N) " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    else
        log_success "Arch Linux detected"
    fi
}

# Detect AUR helper
detect_aur_helper() {
    if command -v yay &> /dev/null; then
        AUR_HELPER="yay"
    elif command -v paru &> /dev/null; then
        AUR_HELPER="paru"
    elif command -v pamac &> /dev/null; then
        AUR_HELPER="pamac"
    else
        AUR_HELPER=""
    fi
}

# Install base dependencies
install_dependencies() {
    log_step "Installing Dependencies"
    
    # Update system
    log_info "Updating system packages..."
    pacman -Syu --noconfirm
    
    # Install PHP and extensions
    log_info "Installing PHP and extensions..."
    pacman -S --needed --noconfirm \
        php \
        php-fpm \
        php-curl \
        php-gd \
        php-json
    
    log_success "PHP installed: $(php -v | head -n 1)"
    
    # Install FFmpeg
    log_info "Installing FFmpeg..."
    pacman -S --needed --noconfirm ffmpeg
    log_success "FFmpeg installed: $(ffmpeg -version | head -n 1)"
    
    # Install yt-dlp
    log_info "Installing yt-dlp..."
    pacman -S --needed --noconfirm yt-dlp
    log_success "yt-dlp installed: $(yt-dlp --version)"
    
    # Install Node.js and npm (for building frontend)
    if ! command -v node &> /dev/null; then
        log_info "Installing Node.js and npm..."
        pacman -S --needed --noconfirm nodejs npm
        log_success "Node.js installed: $(node --version)"
    else
        log_success "Node.js already installed: $(node --version)"
    fi
    
    # Install optional dependencies
    log_info "Installing optional PHP extensions..."
    pacman -S --needed --noconfirm php-opcache 2>/dev/null || true
}

# Install web server
install_web_server() {
    log_step "Installing Web Server"
    
    echo -e "${YELLOW}Select a web server:${NC}"
    echo "  1) Nginx (recommended)"
    echo "  2) Apache"
    echo "  3) Skip (already installed)"
    read -p "Enter choice [1-3]: " web_choice
    
    case $web_choice in
        1)
            WEB_SERVER="nginx"
            pacman -S --needed --noconfirm nginx
            log_success "Nginx installed"
            ;;
        2)
            WEB_SERVER="apache"
            pacman -S --needed --noconfirm apache
            log_success "Apache installed"
            ;;
        3)
            # Detect existing web server
            if command -v nginx &> /dev/null; then
                WEB_SERVER="nginx"
                log_success "Nginx detected"
            elif command -v httpd &> /dev/null; then
                WEB_SERVER="apache"
                log_success "Apache detected"
            else
                log_error "No web server found. Please install Nginx or Apache first."
                exit 1
            fi
            ;;
        *)
            log_error "Invalid choice"
            exit 1
            ;;
    esac
}

# Setup application directory
setup_directories() {
    log_step "Setting Up Directories"
    
    # Ask for install location
    echo -e "${YELLOW}Installation directory:${NC}"
    echo "  1) /srv/http/tidaldl (system-wide, recommended)"
    echo "  2) Custom location"
    read -p "Enter choice [1-2]: " dir_choice
    
    case $dir_choice in
        1)
            INSTALL_DIR="/srv/http/tidaldl"
            ;;
        2)
            read -p "Enter custom path: " INSTALL_DIR
            ;;
        *)
            INSTALL_DIR="/srv/http/tidaldl"
            ;;
    esac
    
    # Create directories
    log_info "Creating installation directory: $INSTALL_DIR"
    mkdir -p "$INSTALL_DIR"
    mkdir -p "$INSTALL_DIR/downloads"
    mkdir -p "$INSTALL_DIR/temp"
    
    # Copy application files
    log_info "Copying application files..."
    cp -r "$SCRIPT_DIR"/* "$INSTALL_DIR/" 2>/dev/null || true
    
    # Set permissions
    log_info "Setting permissions..."
    chown -R http:http "$INSTALL_DIR"
    chmod -R 755 "$INSTALL_DIR"
    chmod -R 775 "$INSTALL_DIR/downloads"
    chmod -R 775 "$INSTALL_DIR/temp"
    
    log_success "Directories created and permissions set"
}

# Build frontend
build_frontend() {
    log_step "Building Frontend"
    
    cd "$INSTALL_DIR"
    
    if [ -f "package.json" ]; then
        log_info "Installing Node.js dependencies..."
        npm install
        
        log_info "Building frontend..."
        npm run build
        
        log_success "Frontend built successfully"
    else
        log_warn "package.json not found, skipping frontend build"
    fi
}

# Configure PHP-FPM
configure_php_fpm() {
    log_step "Configuring PHP-FPM"
    
    # Backup original config
    if [ -f /etc/php/php-fpm.d/www.conf ]; then
        cp /etc/php/php-fpm.d/www.conf /etc/php/php-fpm.d/www.conf.bak
    fi
    
    # Configure PHP settings
    log_info "Configuring PHP settings..."
    
    PHP_INI="/etc/php/php.ini"
    if [ -f "$PHP_INI" ]; then
        # Update PHP settings
        sed -i 's/^upload_max_filesize.*/upload_max_filesize = 100M/' "$PHP_INI"
        sed -i 's/^post_max_size.*/post_max_size = 100M/' "$PHP_INI"
        sed -i 's/^max_execution_time.*/max_execution_time = 600/' "$PHP_INI"
        sed -i 's/^max_input_time.*/max_input_time = 600/' "$PHP_INI"
        sed -i 's/^memory_limit.*/memory_limit = 256M/' "$PHP_INI"
        
        log_success "PHP configuration updated"
    fi
    
    # Enable and start PHP-FPM
    systemctl enable php-fpm
    systemctl start php-fpm
    log_success "PHP-FPM enabled and started"
}

# Configure Nginx
configure_nginx() {
    log_step "Configuring Nginx"
    
    # Create Nginx config
    cat > /etc/nginx/conf.d/tidaldl.conf << 'NGINX_CONF'
server {
    listen 80;
    server_name localhost;
    
    root /srv/http/tidaldl/dist;
    index index.html;
    
    # Frontend routes (SPA)
    location / {
        try_files $uri $uri/ /index.html;
    }
    
    # PHP API routes
    location ~ ^/api/.*\.php$ {
        root /srv/http/tidaldl/dist;
        
        fastcgi_pass unix:/run/php-fpm/php-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        
        # Increase timeout for downloads
        fastcgi_read_timeout 600;
        fastcgi_send_timeout 600;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }
    
    # Static assets caching
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
    
    # Security headers
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    
    # Disable access to hidden files
    location ~ /\. {
        deny all;
    }
}
NGINX_CONF
    
    # Update root path if custom directory
    if [ "$INSTALL_DIR" != "/srv/http/tidaldl" ]; then
        sed -i "s|/srv/http/tidaldl|$INSTALL_DIR|g" /etc/nginx/conf.d/tidaldl.conf
    fi
    
    # Test configuration
    nginx -t
    if [ $? -eq 0 ]; then
        log_success "Nginx configuration valid"
        
        # Enable and start Nginx
        systemctl enable nginx
        systemctl restart nginx
        log_success "Nginx enabled and started"
    else
        log_error "Nginx configuration test failed"
        exit 1
    fi
}

# Configure Apache
configure_apache() {
    log_step "Configuring Apache"
    
    # Enable required modules
    sed -i 's/#LoadModule rewrite_module/LoadModule rewrite_module/' /etc/httpd/conf/httpd.conf
    sed -i 's/#LoadModule proxy_fcgi_module/LoadModule proxy_fcgi_module/' /etc/httpd/conf/httpd.conf
    sed -i 's/#LoadModule proxy_module/LoadModule proxy_module/' /etc/httpd/conf/httpd.conf
    
    # Create Apache config
    cat > /etc/httpd/conf/extra/tidaldl.conf << 'APACHE_CONF'
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /srv/http/tidaldl/dist
    
    <Directory /srv/http/tidaldl/dist>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        
        # SPA fallback
        RewriteEngine On
        RewriteBase /
        RewriteRule ^index\.html$ - [L]
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule . /index.html [L]
    </Directory>
    
    # PHP-FPM via FastCGI
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php-fpm/php-fpm.sock|fcgi://localhost"
    </FilesMatch>
    
    # Increase timeout for downloads
    ProxyTimeout 600
    
    # Security headers
    Header always set X-Frame-Options "DENY"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    
    ErrorLog /var/log/httpd/tidaldl_error.log
    CustomLog /var/log/httpd/tidaldl_access.log combined
</VirtualHost>
APACHE_CONF
    
    # Update root path if custom directory
    if [ "$INSTALL_DIR" != "/srv/http/tidaldl" ]; then
        sed -i "s|/srv/http/tidaldl|$INSTALL_DIR|g" /etc/httpd/conf/extra/tidaldl.conf
    fi
    
    # Include the configuration
    if ! grep -q "tidaldl.conf" /etc/httpd/conf/httpd.conf; then
        echo "Include conf/extra/tidaldl.conf" >> /etc/httpd/conf/httpd.conf
    fi
    
    # Test configuration
    httpd -t
    if [ $? -eq 0 ]; then
        log_success "Apache configuration valid"
        
        # Enable and start Apache
        systemctl enable httpd
        systemctl restart httpd
        log_success "Apache enabled and started"
    else
        log_error "Apache configuration test failed"
        exit 1
    fi
}

# Configure firewall
configure_firewall() {
    log_step "Configuring Firewall (Optional)"
    
    read -p "Configure firewall rules? (y/N): " fw_choice
    
    if [[ $fw_choice =~ ^[Yy]$ ]]; then
        # Check for UFW
        if command -v ufw &> /dev/null; then
            ufw allow 80/tcp
            ufw allow 443/tcp
            log_success "UFW rules added"
        # Check for firewalld
        elif command -v firewall-cmd &> /dev/null; then
            firewall-cmd --permanent --add-service=http
            firewall-cmd --permanent --add-service=https
            firewall-cmd --reload
            log_success "Firewalld rules added"
        # Check for iptables
        elif command -v iptables &> /dev/null; then
            iptables -A INPUT -p tcp --dport 80 -j ACCEPT
            iptables -A INPUT -p tcp --dport 443 -j ACCEPT
            log_success "iptables rules added"
        else
            log_warn "No firewall detected, skipping"
        fi
    else
        log_info "Skipping firewall configuration"
    fi
}

# Verify installation
verify_installation() {
    log_step "Verifying Installation"
    
    local all_good=true
    
    # Check PHP
    if command -v php &> /dev/null; then
        log_success "PHP: $(php -v | head -n 1 | cut -d' ' -f1-2)"
    else
        log_error "PHP not found"
        all_good=false
    fi
    
    # Check PHP-FPM
    if systemctl is-active --quiet php-fpm; then
        log_success "PHP-FPM: Running"
    else
        log_error "PHP-FPM: Not running"
        all_good=false
    fi
    
    # Check FFmpeg
    if command -v ffmpeg &> /dev/null; then
        log_success "FFmpeg: $(ffmpeg -version 2>&1 | head -n 1 | cut -d' ' -f1-3)"
    else
        log_error "FFmpeg not found"
        all_good=false
    fi
    
    # Check yt-dlp
    if command -v yt-dlp &> /dev/null; then
        log_success "yt-dlp: $(yt-dlp --version)"
    else
        log_error "yt-dlp not found"
        all_good=false
    fi
    
    # Check web server
    if [ "$WEB_SERVER" = "nginx" ]; then
        if systemctl is-active --quiet nginx; then
            log_success "Nginx: Running"
        else
            log_error "Nginx: Not running"
            all_good=false
        fi
    elif [ "$WEB_SERVER" = "apache" ]; then
        if systemctl is-active --quiet httpd; then
            log_success "Apache: Running"
        else
            log_error "Apache: Not running"
            all_good=false
        fi
    fi
    
    # Check directories
    if [ -d "$INSTALL_DIR/downloads" ] && [ -w "$INSTALL_DIR/downloads" ]; then
        log_success "Downloads directory: Writable"
    else
        log_error "Downloads directory: Not writable"
        all_good=false
    fi
    
    if [ -d "$INSTALL_DIR/temp" ] && [ -w "$INSTALL_DIR/temp" ]; then
        log_success "Temp directory: Writable"
    else
        log_error "Temp directory: Not writable"
        all_good=false
    fi
    
    # Check frontend build
    if [ -f "$INSTALL_DIR/dist/index.html" ]; then
        log_success "Frontend: Built successfully"
    else
        log_warn "Frontend: Not built (run 'npm run build' in $INSTALL_DIR)"
    fi
    
    if [ "$all_good" = true ]; then
        echo ""
        log_success "All checks passed! Installation complete."
    else
        echo ""
        log_warn "Some checks failed. Please review the errors above."
    fi
}

# Print final instructions
print_instructions() {
    log_step "Installation Complete!"
    
    echo ""
    echo -e "${GREEN}╔════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║                  Installation Complete!                    ║${NC}"
    echo -e "${GREEN}╚════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${CYAN}Access the application at:${NC}"
    echo -e "  ${GREEN}http://localhost${NC}"
    echo ""
    echo -e "${CYAN}Next steps:${NC}"
    echo -e "  1. Get your Tidal API credentials from ${YELLOW}https://developer.tidal.com/${NC}"
    echo -e "  2. Open the web interface and enter your credentials"
    echo -e "  3. Start searching and downloading music!"
    echo ""
    echo -e "${CYAN}Useful commands:${NC}"
    echo -e "  ${YELLOW}# View downloads${NC}"
    echo -e "  ls -lh $INSTALL_DIR/downloads/"
    echo ""
    echo -e "  ${YELLOW}# Check service status${NC}"
    echo -e "  systemctl status php-fpm"
    echo -e "  systemctl status $WEB_SERVER"
    echo ""
    echo -e "  ${YELLOW}# View logs${NC}"
    if [ "$WEB_SERVER" = "nginx" ]; then
        echo -e "  tail -f /var/log/nginx/error.log"
    else
        echo -e "  tail -f /var/log/httpd/tidaldl_error.log"
    fi
    echo ""
    echo -e "  ${YELLOW}# Clear temporary files${NC}"
    echo -e "  rm -rf $INSTALL_DIR/temp/*"
    echo ""
    echo -e "${CYAN}Documentation:${NC}"
    echo -e "  Full setup guide: ${YELLOW}$INSTALL_DIR/README.md${NC}"
    echo ""
}

# Main installation flow
main() {
    print_banner
    
    log_info "Starting TidalDL installation for Arch Linux..."
    echo ""
    
    check_root
    check_arch
    detect_aur_helper
    
    install_dependencies
    install_web_server
    setup_directories
    build_frontend
    configure_php_fpm
    
    if [ "$WEB_SERVER" = "nginx" ]; then
        configure_nginx
    elif [ "$WEB_SERVER" = "apache" ]; then
        configure_apache
    fi
    
    configure_firewall
    verify_installation
    print_instructions
}

# Run main function
main "$@"

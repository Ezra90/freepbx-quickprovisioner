# FreePBX Quick Provisioner - Installation Guide

## Table of Contents
1. [Windows Preparation](#windows-preparation)
2. [Raspberry Pi RasPBX Setup](#raspberry-pi-raspbx-setup)
3. [GitHub SSH Key Configuration](#github-ssh-key-configuration)
4. [Module Installation](#module-installation)
5. [Permission Management](#permission-management)

---

## Windows Preparation

### Prerequisites
- Windows 10/11 with WSL 2 enabled (optional but recommended)
- Git for Windows
- Text editor (VS Code recommended)
- Administrative access

### Step 1: Install Required Software
1. **Git for Windows**
   - Download from https://git-scm.com/download/win
   - Use default installation options
   - Select "Use Git from the command line and also from 3rd-party software"

2. **Windows Terminal** (Recommended)
   - Install from Microsoft Store
   - Provides better command-line experience

3. **PuTTY or OpenSSH** (for SSH connections)
   - Download PuTTY from https://www.putty.org/
   - Or use Windows OpenSSH (built-in on Windows 10+)

### Step 2: Generate SSH Keys on Windows
```powershell
# Open PowerShell as Administrator
ssh-keygen -t ed25519 -C "your.email@example.com"

# When prompted:
# Enter file in which to save the key: C:\Users\YourUsername\.ssh\id_ed25519
# Enter passphrase (recommended but optional)
# Confirm passphrase

# View your public key
cat C:\Users\YourUsername\.ssh\id_ed25519.pub
```

### Step 3: Install WinSCP (Optional)
- Download from https://winscp.net/
- Useful for GUI-based file transfers to Raspberry Pi

---

## Raspberry Pi RasPBX Setup

### Hardware Requirements
- Raspberry Pi 4B (8GB RAM recommended) or Raspberry Pi 5
- MicroSD Card (64GB minimum, Class 10 or higher)
- Power Supply (5V/3A minimum for Pi 4, 5V/5A for Pi 5)
- Ethernet Connection (strongly recommended over WiFi)
- Optional: Case with cooling and HDMI cable

### Initial Setup

#### Step 1: Flash RasPBX Image
1. Download RasPBX image from https://raspi.pbx.org/
2. Use Balena Etcher or similar tool to flash to MicroSD card
3. Insert card into Raspberry Pi and power on
4. Wait 3-5 minutes for first boot

#### Step 2: Initial Boot Configuration
```bash
# Connect via SSH (default password: raspberry)
ssh root@raspbx.local
# or use IP address: ssh root@YOUR_PI_IP

# Change default password immediately
passwd

# Update system
apt-get update
apt-get upgrade -y

# Set timezone
raspi-config
# Navigate to: Localization Options > Timezone
```

#### Step 3: Network Configuration
```bash
# View current network status
ip addr show

# For static IP (recommended for PBX)
sudo nano /etc/dhcpcd.conf

# Add at end of file:
# interface eth0
# static ip_address=192.168.1.100/24
# static routers=192.168.1.1
# static domain_name_servers=8.8.8.8 8.8.4.4

# Restart network
sudo systemctl restart dhcpcd
```

---

## GitHub SSH Key Configuration

### On Raspberry Pi (RasPBX)

#### Step 1: Generate SSH Key Pair
```bash
# Log in as root or appropriate user
ssh-keygen -t ed25519 -C "raspbx-$(hostname)@example.com"

# When prompted:
# Enter file: /root/.ssh/id_ed25519 (press Enter for default)
# Passphrase: (optional, press Enter if you don't want one)

# View your public key
cat /root/.ssh/id_ed25519.pub
```

#### Step 2: Add Public Key to GitHub
1. Go to https://github.com/settings/keys
2. Click "New SSH key"
3. Title: "RasPBX Server" or similar
4. Key type: Authentication Key
5. Paste your public key (from `cat /root/.ssh/id_ed25519.pub`)
6. Click "Add SSH key"

#### Step 3: Configure SSH Client
```bash
# Create SSH config for convenience (optional)
cat >> /root/.ssh/config << 'EOF'
Host github.com
    HostName github.com
    User git
    IdentityFile /root/.ssh/id_ed25519
    AddKeysToAgent yes
EOF

# Set proper permissions
chmod 600 /root/.ssh/config
chmod 700 /root/.ssh
chmod 600 /root/.ssh/id_ed25519
chmod 644 /root/.ssh/id_ed25519.pub

# Test SSH connection
ssh -T git@github.com
# Should see: "Hi username! You've successfully authenticated..."
```

#### Step 4: Configure Git User
```bash
# Set git configuration
git config --global user.name "Your Name"
git config --global user.email "your.email@example.com"

# Verify configuration
git config --list
```

---

## Module Installation

### Current Module Versions
- **FreePBX Core**: 16.x/17.x (compatible with RasPBX)
- **Quick Provisioner Module**: 1.0.0+
- **Required Dependencies**:
  - PHP 7.4+ (RasPBX default: 8.0+)
  - MySQL/MariaDB 5.7+
  - Apache 2.4+

### Installation Steps

#### Step 1: Clone the Repository
```bash
# Navigate to FreePBX module directory
cd /var/www/html/admin/modules

# Clone repository via SSH
git clone git@github.com:Ezra90/freepbx-quickprovisioner.git quickprovisioner

# Or via HTTPS if SSH not configured
git clone https://github.com/Ezra90/freepbx-quickprovisioner.git quickprovisioner

# Navigate to module
cd quickprovisioner
```

#### Step 2: Install Module Dependencies
```bash
# Check if composer is available
which composer

# If not installed, install composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# Install PHP dependencies
composer install

# Or if using PHP-specific composer
php composer.phar install
```

#### Step 3: Enable Module in FreePBX
1. Log into FreePBX Admin GUI (https://your-pi-ip/admin)
2. Navigate to Admin → Module Admin
3. Search for "quickprovisioner" or "Quick Provisioner"
4. Click the download icon to install
5. Wait for installation to complete
6. Click "Enable" to activate the module
7. Module should now appear in the main navigation menu

#### Step 4: Verify Installation
```bash
# Check module directory permissions
ls -la /var/www/html/admin/modules/quickprovisioner/

# Check FreePBX logs for any errors
tail -f /var/log/asterisk/full

# Or check FreePBX system logs
tail -f /var/log/freeswitch/freeswitch.log
```

---

## Permission Management

### Directory Structure and Ownership

#### Step 1: Verify Ownership
```bash
# Check current ownership of module directory
ls -la /var/www/html/admin/modules/quickprovisioner/

# Typical output should show:
# drwxr-xr-x  asterisk asterisk
```

#### Step 2: Set Correct Permissions
```bash
# Navigate to module directory
cd /var/www/html/admin/modules/quickprovisioner

# Set ownership to asterisk user
sudo chown -R asterisk:asterisk .

# Set directory permissions (755 = rwxr-xr-x)
find . -type d -exec chmod 755 {} \;

# Set file permissions (644 = rw-r--r--)
find . -type f -exec chmod 644 {} \;

# Allow execution of shell scripts (if any)
find . -name "*.sh" -exec chmod 755 {} \;
```

#### Step 3: Critical Directories and Files
```bash
# Ensure FreePBX root has correct permissions
sudo chown -R asterisk:asterisk /var/www/html/admin/

# Ensure write permissions for necessary directories
sudo chmod 775 /var/www/html/admin/modules/quickprovisioner/

# Enable execution of critical files
chmod +x /var/www/html/admin/modules/quickprovisioner/quickprovisioner.php

# Check PHP files have execute permission (755)
chmod 755 /var/www/html/admin/modules/quickprovisioner/*.php
```

#### Step 4: Web Server Permissions
```bash
# Ensure Apache/web server can read module files
ls -la /var/www/html/admin/modules/quickprovisioner/ | head -20

# Typical permission matrix:
# drwxr-xr-x = 755  (directories)
# -rw-r--r-- = 644  (regular files)
# -rwxr-xr-x = 755  (executable files/scripts)

# All owned by: asterisk:asterisk
```

#### Step 5: Database Permissions
```bash
# If module uses database tables, ensure proper access
mysql -u root -p

# Inside MySQL:
# GRANT ALL PRIVILEGES ON freepbx.* TO 'asterisk'@'localhost';
# FLUSH PRIVILEGES;
```

### Troubleshooting Permission Issues

#### Module Not Loading?
```bash
# Check ownership
stat /var/www/html/admin/modules/quickprovisioner/

# Check if module files are readable
test -r /var/www/html/admin/modules/quickprovisioner/quickprovisioner.php && echo "Readable" || echo "Not readable"

# Check FreePBX error logs
grep -i "permission\|denied" /var/log/freeswitch/*.log
```

#### Cannot Write to Module Directory?
```bash
# Check current user
whoami

# View directory permissions
stat -c "%A %U:%G" /var/www/html/admin/modules/quickprovisioner/

# Fix: Change to correct ownership
sudo chown -R asterisk:asterisk /var/www/html/admin/modules/quickprovisioner/
sudo chmod -R 755 /var/www/html/admin/modules/quickprovisioner/
```

#### Web Server Access Issues?
```bash
# Check Apache/web server user
ps aux | grep apache
ps aux | grep httpd

# Ensure asterisk user is in web server group
usermod -a -G www-data asterisk
usermod -a -G asterisk www-data

# Restart web server
systemctl restart apache2
# or
systemctl restart httpd
```

---

## Post-Installation Verification

### Step 1: Verify Module Loading
```bash
# Check FreePBX module table
mysql -u asterisk -p asterisk -e "SELECT * FROM modules WHERE module='quickprovisioner';"

# Should show module status and version information
```

### Step 2: Check System Logs
```bash
# Review recent logs for errors
tail -50 /var/log/freeswitch/freeswitch.log | grep -i "quickprovisioner\|error"

# Check asterisk logs
tail -50 /var/log/asterisk/full | grep -i "error\|warning"
```

### Step 3: Web Interface Test
1. Navigate to https://your-pi-ip/admin
2. Login with FreePBX credentials
3. Verify "Quick Provisioner" appears in menu
4. Check module settings/configuration page loads without errors

### Step 4: Test Basic Functionality
```bash
# Test SSH connection to GitHub (if configured for updates)
ssh -T git@github.com

# Check module status via CLI
ssh root@your-pi-ip
asterisk -rn
# Then: core show modules like quickprovisioner
```

---

## Maintenance and Updates

### Regular Updates
```bash
# Update system packages
apt-get update
apt-get upgrade -y

# Update FreePBX modules via GUI or CLI
fwconsole ma upgradeall

# Update quickprovisioner module specifically
cd /var/www/html/admin/modules/quickprovisioner
git pull origin main  # Requires SSH key setup
```

### Backup Before Updates
```bash
# Backup module
cp -r /var/www/html/admin/modules/quickprovisioner /var/backups/quickprovisioner.backup

# Backup database
mysqldump -u asterisk -p freepbx > /var/backups/freepbx.backup.sql
```

---

## Common Issues and Solutions

| Issue | Solution |
|-------|----------|
| SSH key not found | Verify SSH key location: `ls -la /root/.ssh/id_ed25519` |
| Permission denied | Check ownership: `chown -R asterisk:asterisk /path/to/module` |
| Module not loading | Check logs: `tail -f /var/log/freeswitch/freeswitch.log` |
| Git clone fails | Ensure SSH key is added to GitHub account and permissions are 600 |
| PHP execution error | Verify PHP is installed: `php -v` and check error logs |

---

## Support and Troubleshooting

- **GitHub Issues**: https://github.com/Ezra90/freepbx-quickprovisioner/issues
- **FreePBX Forums**: https://community.freepbx.org/
- **RasPBX Documentation**: https://raspi.pbx.org/
- **SSH Troubleshooting**: `ssh -vvv git@github.com` for detailed connection info

---

## Security Recommendations

1. **Change default passwords** immediately after initial setup
2. **Use SSH keys** instead of passwords for GitHub authentication
3. **Enable firewall** on Raspberry Pi: `ufw enable`
4. **Restrict SSH access** to specific IPs if possible
5. **Use strong passphrase** for SSH keys
6. **Keep system updated** regularly
7. **Monitor logs** for suspicious activity

---

Last Updated: 2026-01-02
Version: 1.0 - Comprehensive Installation Guide for RasPBX

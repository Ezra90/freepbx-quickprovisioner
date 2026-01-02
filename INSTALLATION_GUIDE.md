# RasPBX Installation Guide with FreePBX Quick Provisioner

## Table of Contents

1. [Introduction](#introduction)
2. [RasPBX Installation](#1-raspbx-installation)
3. [System Preparation](#2-system-preparation)
4. [SSH Key Generation](#3-ssh-key-generation)
5. [Add Public Key to GitHub](#4-add-public-key-to-github)
6. [Clone Private Repository](#5-clone-private-repository)
7. [Module Installation](#6-module-installation)
8. [Asterisk Init Script Permission Fix](#7-asterisk-init-script-permission-fix)
9. [Enable Module in FreePBX](#8-enable-module-in-freepbx)
10. [Reboot and Verification](#9-reboot-and-verification)
11. [Troubleshooting](#10-troubleshooting)

---

## Introduction

This guide provides a complete, step-by-step process for installing RasPBX from scratch and configuring the FreePBX Quick Provisioner module. This is designed as a comprehensive reference for setting up a new RasPBX system with secure GitHub authentication and proper module installation.

**Target Audience**: System administrators and developers who need to deploy RasPBX with the Quick Provisioner module.

**Prerequisites**:
- Raspberry Pi (Model 3B+ or newer recommended)
- MicroSD card (16GB minimum, 32GB recommended)
- Computer with SD card reader
- Network connection (Ethernet recommended for initial setup)
- GitHub account with access to the freepbx-quickprovisioner repository

**Estimated Time**: 2-3 hours for complete installation and setup

---

## 1. RasPBX Installation

### 1.1 Download RasPBX

1. Visit the official RasPBX website: [http://www.raspbx.org/](http://www.raspbx.org/)
2. Download the latest RasPBX image (e.g., `raspbx-latest.img.zip`)
3. Verify the download integrity using the provided checksums if available

### 1.2 Write Image to SD Card

#### Using Raspberry Pi Imager (Recommended)

1. Download and install [Raspberry Pi Imager](https://www.raspberrypi.com/software/)
2. Insert your microSD card into your computer
3. Open Raspberry Pi Imager
4. Click **"Choose OS"** → **"Use custom"** and select the downloaded RasPBX image
5. Click **"Choose Storage"** and select your SD card
6. Click **"Write"** and wait for the process to complete
7. Safely eject the SD card

#### Using Command Line (Linux/macOS)

```bash
# Extract the image
unzip raspbx-latest.img.zip

# Identify your SD card device (be careful to select the correct device!)
lsblk

# Write the image to SD card (replace /dev/sdX with your SD card device)
sudo dd if=raspbx-latest.img of=/dev/sdX bs=4M status=progress
sudo sync

# Eject the SD card
sudo eject /dev/sdX
```

#### Using Win32 Disk Imager (Windows)

1. Download and install [Win32 Disk Imager](https://sourceforge.net/projects/win32diskimager/)
2. Extract the RasPBX image from the ZIP file
3. Insert your SD card
4. Open Win32 Disk Imager
5. Select the extracted `.img` file
6. Select your SD card drive letter
7. Click **"Write"** and confirm
8. Wait for completion and eject the SD card

### 1.3 Initial Boot and Setup

1. Insert the SD card into your Raspberry Pi
2. Connect an Ethernet cable (WiFi configuration can be done later)
3. Connect power supply to boot the Raspberry Pi
4. Wait 2-3 minutes for first boot initialization

**Note**: On first boot, the system will automatically expand the filesystem and reboot.

### 1.4 Find RasPBX IP Address

You can find the IP address using one of these methods:

```bash
# Method 1: Check your router's DHCP client list
# Look for a device named "raspbx"

# Method 2: Use network scanner (from another computer)
nmap -sn 192.168.1.0/24

# Method 3: Use arp-scan (Linux)
sudo arp-scan --localnet | grep -i raspberry

# Method 4: Connect monitor and keyboard to see IP on console
```

### 1.5 Initial Login

**SSH Access**:
```bash
ssh root@<raspbx-ip-address>
```

**Default Credentials**:
- Username: `root`
- Password: `raspberry`

**Web Interface**:
- URL: `http://<raspbx-ip-address>`
- Username: `admin`
- Password: `admin`

**Change Default Passwords Immediately**:
```bash
# SSH/Root password
passwd root

# FreePBX admin password - do this via web interface:
# Admin → Admin → admin → Change Password
```

---

## 2. System Preparation

### 2.1 Update System Packages

```bash
# Update package lists
apt-get update

# Upgrade installed packages (this may take 15-30 minutes)
apt-get upgrade -y

# Clean up
apt-get autoremove -y
apt-get autoclean
```

### 2.2 Install Required Dependencies

```bash
# Install Git
apt-get install -y git

# Install additional tools
apt-get install -y nano vim curl wget

# Install development tools (if needed for building)
apt-get install -y build-essential

# Verify installations
git --version
```

### 2.3 Configure System Settings

#### Set Timezone

```bash
# Configure timezone
dpkg-reconfigure tzdata

# Verify timezone
timedatectl
```

#### Configure Network (Optional)

For static IP configuration, edit network interfaces:

```bash
# Edit network configuration
nano /etc/network/interfaces

# Example static IP configuration:
# auto eth0
# iface eth0 inet static
#     address 192.168.1.100
#     netmask 255.255.255.0
#     gateway 192.168.1.1
#     dns-nameservers 8.8.8.8 8.8.4.4

# Restart networking
systemctl restart networking
```

### 2.4 Verify FreePBX Installation

```bash
# Check Asterisk status
asterisk -rx "core show version"

# Check FreePBX status
fwconsole version

# Verify Apache/web server
systemctl status apache2
```

---

## 3. SSH Key Generation

Using SSH keys provides secure, password-less authentication with GitHub.

### 3.1 Generate SSH Key Pair

```bash
# Generate SSH key (use your email address)
ssh-keygen -t ed25519 -C "your_email@example.com"

# When prompted:
# - File location: Press Enter to accept default (~/.ssh/id_ed25519)
# - Passphrase: Enter a strong passphrase or press Enter for no passphrase
```

**Alternative**: If your system doesn't support Ed25519, use RSA:

```bash
ssh-keygen -t rsa -b 4096 -C "your_email@example.com"
```

### 3.2 Start SSH Agent

```bash
# Start the SSH agent
eval "$(ssh-agent -s)"

# Add your SSH private key to the agent
ssh-add ~/.ssh/id_ed25519
# Or for RSA:
# ssh-add ~/.ssh/id_rsa
```

### 3.3 Display Public Key

```bash
# Display your public key
cat ~/.ssh/id_ed25519.pub
# Or for RSA:
# cat ~/.ssh/id_rsa.pub
```

Copy the entire output. It should look like:

```
ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIGJxXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX your_email@example.com
```

---

## 4. Add Public Key to GitHub

### 4.1 Add SSH Key to GitHub Account

1. **Log in to GitHub**: Go to [https://github.com](https://github.com) and log in
2. **Navigate to Settings**:
   - Click your profile photo (top right)
   - Click **Settings**
3. **Access SSH Keys Section**:
   - In the left sidebar, click **SSH and GPG keys**
4. **Add New SSH Key**:
   - Click **New SSH key** button
   - **Title**: Give it a descriptive name (e.g., "RasPBX - Raspberry Pi")
   - **Key**: Paste the public key you copied earlier
   - Click **Add SSH key**
5. **Confirm**: You may be prompted to enter your GitHub password

### 4.2 Test SSH Connection

```bash
# Test GitHub SSH connection
ssh -T git@github.com

# Expected output:
# Hi username! You've successfully authenticated, but GitHub does not provide shell access.
```

If you see the success message, your SSH key is properly configured!

**Troubleshooting Connection Issues**:

```bash
# Debug SSH connection
ssh -vT git@github.com

# Check SSH config
cat ~/.ssh/config

# Verify key permissions
ls -la ~/.ssh/
# Private key should be 600: -rw-------
# Public key should be 644: -rw-r--r--

# Fix permissions if needed
chmod 600 ~/.ssh/id_ed25519
chmod 644 ~/.ssh/id_ed25519.pub
```

---

## 5. Clone Private Repository

### 5.1 Navigate to Modules Directory

```bash
# Change to FreePBX modules directory
cd /var/www/html/admin/modules

# Verify you're in the correct directory
pwd
# Should output: /var/www/html/admin/modules
```

### 5.2 Clone the Repository Using SSH

```bash
# Clone the freepbx-quickprovisioner repository
git clone git@github.com:Ezra90/freepbx-quickprovisioner.git quickprovisioner

# Verify the clone
ls -la quickprovisioner/
```

**Expected Output**:
```
drwxr-xr-x 4 root     root     4096 Jan  2 10:00 .
drwxr-xr-x 8 asterisk asterisk 4096 Jan  2 10:00 ..
drwxr-xr-x 8 root     root     4096 Jan  2 10:00 .git
-rw-r--r-- 1 root     root      123 Jan  2 10:00 .gitignore
-rw-r--r-- 1 root     root      456 Jan  2 10:00 LICENSE
-rw-r--r-- 1 root     root     1234 Jan  2 10:00 README.md
... (other files)
```

### 5.3 Verify Repository Contents

```bash
# List repository contents
cd quickprovisioner
ls -la

# Check module.xml exists
cat module.xml
```

---

## 6. Module Installation

### 6.1 Set Proper Ownership

The module files must be owned by the `asterisk` user for FreePBX to work correctly.

```bash
# Change ownership to asterisk user and group
chown -R asterisk:asterisk /var/www/html/admin/modules/quickprovisioner

# Verify ownership
ls -la /var/www/html/admin/modules/ | grep quickprovisioner
```

**Expected Output**:
```
drwxr-xr-x  4 asterisk asterisk  4096 Jan  2 10:00 quickprovisioner
```

### 6.2 Set Proper Permissions

```bash
# Set directory permissions (755)
find /var/www/html/admin/modules/quickprovisioner -type d -exec chmod 755 {} \;

# Set file permissions (644)
find /var/www/html/admin/modules/quickprovisioner -type f -exec chmod 644 {} \;

# Verify permissions
ls -la /var/www/html/admin/modules/quickprovisioner/
```

### 6.3 Create Required Directories

```bash
# Navigate to module directory
cd /var/www/html/admin/modules/quickprovisioner

# Create assets directory if it doesn't exist
mkdir -p assets/{js,css,images,wallpapers}

# Set ownership and permissions
chown -R asterisk:asterisk assets/
chmod -R 755 assets/
```

### 6.4 Verify Module Structure

```bash
# Check the module structure
tree -L 2 /var/www/html/admin/modules/quickprovisioner

# Or without tree:
find /var/www/html/admin/modules/quickprovisioner -maxdepth 2 -type d
```

---

## 7. Asterisk Init Script Permission Fix

To ensure proper permissions are maintained after system reboots, we need to modify the Asterisk init script.

### 7.1 Understanding the Issue

FreePBX modules require the `asterisk` user to have proper ownership of files. After reboot, permissions may reset or be incorrect, causing the module to fail.

### 7.2 Backup the Init Script

```bash
# Create a backup of the init script
cp /etc/init.d/asterisk /etc/init.d/asterisk.backup

# Verify backup
ls -la /etc/init.d/asterisk*
```

### 7.3 Edit the Init Script

```bash
# Open the init script for editing
nano /etc/init.d/asterisk
```

Find the `start()` function (typically around line 20-50), and add the permission fix commands **before** Asterisk starts:

```bash
start() {
    # Add these lines to fix permissions on startup
    echo "Fixing FreePBX module permissions..."
    chown -R asterisk:asterisk /var/www/html/admin/modules/quickprovisioner
    find /var/www/html/admin/modules/quickprovisioner -type d -exec chmod 755 {} \;
    find /var/www/html/admin/modules/quickprovisioner -type f -exec chmod 644 {} \;
    
    # Existing start commands below
    echo "Starting Asterisk..."
    # ... rest of the original start function
}
```

**Complete Example**:

```bash
start() {
    # Fix permissions for QuickProvisioner module
    if [ -d /var/www/html/admin/modules/quickprovisioner ]; then
        echo "Fixing QuickProvisioner permissions..."
        chown -R asterisk:asterisk /var/www/html/admin/modules/quickprovisioner
        find /var/www/html/admin/modules/quickprovisioner -type d -exec chmod 755 {} \; 2>/dev/null
        find /var/www/html/admin/modules/quickprovisioner -type f -exec chmod 644 {} \; 2>/dev/null
    fi
    
    # Start Asterisk (existing code)
    if [ -f $ASTSAFE ]; then
        if [ -f $ASTPID ]; then
            echo "Asterisk is already running"
            exit 1
        fi
        cd /tmp
        $ASTSAFE > /dev/null 2>&1 &
        echo $! > $ASTPID
        echo "Started Asterisk"
    else
        echo "Asterisk safe_asterisk not found"
        exit 1
    fi
}
```

Save the file:
- Press `Ctrl + X`
- Press `Y` to confirm
- Press `Enter` to save

### 7.4 Test the Init Script

```bash
# Test the init script syntax
bash -n /etc/init.d/asterisk

# If no output, syntax is correct

# Restart Asterisk to test
systemctl restart asterisk

# Check Asterisk status
systemctl status asterisk

# Verify Asterisk is running
asterisk -rx "core show version"
```

### 7.5 Alternative: Create Systemd Service Override

If you prefer using systemd, create a service override:

```bash
# Create systemd override directory
mkdir -p /etc/systemd/system/asterisk.service.d/

# Create override configuration
cat > /etc/systemd/system/asterisk.service.d/permissions.conf << 'EOF'
[Service]
ExecStartPre=/bin/bash -c 'if [ -d /var/www/html/admin/modules/quickprovisioner ]; then chown -R asterisk:asterisk /var/www/html/admin/modules/quickprovisioner; find /var/www/html/admin/modules/quickprovisioner -type d -exec chmod 755 {} \\; 2>/dev/null; find /var/www/html/admin/modules/quickprovisioner -type f -exec chmod 644 {} \\; 2>/dev/null; fi'
EOF

# Reload systemd
systemctl daemon-reload

# Restart Asterisk
systemctl restart asterisk
```

---

## 8. Enable Module in FreePBX

### 8.1 Refresh Module List

```bash
# Clear FreePBX cache
fwconsole cache --flush

# Refresh module signatures
fwconsole ma refreshsignatures

# List available modules
fwconsole ma list | grep -i quickprovisioner
```

### 8.2 Enable Module via Command Line

```bash
# Install and enable the module
fwconsole ma install quickprovisioner

# Enable the module
fwconsole ma enable quickprovisioner

# Reload FreePBX
fwconsole reload
```

### 8.3 Enable Module via Web Interface

1. **Log in to FreePBX**:
   - Navigate to `http://<raspbx-ip-address>`
   - Log in with admin credentials

2. **Navigate to Module Admin**:
   - Click **Admin** in top menu
   - Click **Module Admin**

3. **Find Quick Provisioner**:
   - In the module list, search for "Quick Provisioner" or "HH Quick Provisioner"
   - You may need to scroll through the list or use the search box

4. **Install the Module**:
   - If the module shows "Not Installed", click the **Install** button
   - Wait for installation to complete

5. **Enable the Module**:
   - Click the **Enable** button next to the module
   - The status should change to "Enabled"

6. **Apply Configuration**:
   - Look for the orange/red banner at the top that says "Apply Config"
   - Click **Apply Config** button
   - Wait for the configuration to be applied

### 8.4 Verify Module Installation

```bash
# Check module status
fwconsole ma list | grep quickprovisioner

# Expected output:
# quickprovisioner 2.1.0  Enabled
```

### 8.5 Access the Module

1. In FreePBX web interface, look for:
   - **Applications** menu → **HH Quick Provisioner**
   - Or click directly on **HH Quick Provisioner** in the left menu

2. You should see the Quick Provisioner interface with tabs:
   - Device List
   - Add/Edit Device
   - Contacts
   - Asset Manager
   - Handset Model Templates

---

## 9. Reboot and Verification

### 9.1 Perform System Reboot

```bash
# Reboot the system
reboot

# Wait 2-3 minutes for system to come back online
```

### 9.2 Verify System Services

```bash
# SSH back into the system
ssh root@<raspbx-ip-address>

# Check Asterisk is running
systemctl status asterisk
asterisk -rx "core show version"

# Check Apache is running
systemctl status apache2

# Check FreePBX is operational
fwconsole version
```

### 9.3 Verify Module Permissions

```bash
# Check ownership
ls -la /var/www/html/admin/modules/quickprovisioner/

# Expected: Files owned by asterisk:asterisk

# Check specific file permissions
stat /var/www/html/admin/modules/quickprovisioner/module.xml

# Directory permissions should be 755
# File permissions should be 644
```

### 9.4 Test Module Functionality

1. **Access FreePBX Web Interface**:
   ```
   http://<raspbx-ip-address>
   ```

2. **Navigate to Quick Provisioner**:
   - **Applications** → **HH Quick Provisioner**

3. **Test Basic Functionality**:
   - Click on different tabs (Device List, Add/Edit Device, etc.)
   - Verify no permission errors appear
   - Check browser console for JavaScript errors (F12)

4. **Check Logs for Errors**:
   ```bash
   # Check Apache error log
   tail -f /var/log/apache2/error.log
   
   # Check Asterisk log
   tail -f /var/log/asterisk/full
   
   # Check FreePBX log
   tail -f /var/log/asterisk/freepbx.log
   ```

---

## 10. Troubleshooting

### 10.1 Module Not Appearing in FreePBX

**Problem**: Module is in the correct directory but doesn't appear in Module Admin.

**Solutions**:

1. **Check module.xml**:
   ```bash
   # Verify module.xml exists and is valid
   xmllint --noout /var/www/html/admin/modules/quickprovisioner/module.xml
   
   # If errors appear, the XML is malformed
   cat /var/www/html/admin/modules/quickprovisioner/module.xml
   ```

2. **Refresh signatures and cache**:
   ```bash
   fwconsole cache --flush
   fwconsole ma refreshsignatures
   fwconsole reload
   ```

3. **Check ownership and permissions**:
   ```bash
   chown -R asterisk:asterisk /var/www/html/admin/modules/quickprovisioner
   find /var/www/html/admin/modules/quickprovisioner -type d -exec chmod 755 {} \;
   find /var/www/html/admin/modules/quickprovisioner -type f -exec chmod 644 {} \;
   ```

### 10.2 Permission Denied Errors

**Problem**: "Permission denied" errors when accessing module features.

**Solutions**:

1. **Fix ownership**:
   ```bash
   chown -R asterisk:asterisk /var/www/html/admin/modules/quickprovisioner
   ```

2. **Fix permissions**:
   ```bash
   find /var/www/html/admin/modules/quickprovisioner -type d -exec chmod 755 {} \;
   find /var/www/html/admin/modules/quickprovisioner -type f -exec chmod 644 {} \;
   ```

3. **Check SELinux** (if enabled):
   ```bash
   # Check SELinux status
   getenforce
   
   # If Enforcing, try setting to Permissive temporarily
   setenforce 0
   
   # Test if module works now
   # If yes, add proper SELinux context
   ```

4. **Verify Apache user**:
   ```bash
   # Check what user Apache runs as
   ps aux | grep apache2
   
   # Should show www-data or asterisk
   ```

### 10.3 SSH Key Authentication Fails

**Problem**: Cannot clone repository with SSH.

**Solutions**:

1. **Verify SSH key is added to GitHub**:
   - Log in to GitHub
   - Settings → SSH and GPG keys
   - Ensure your key is listed

2. **Test SSH connection**:
   ```bash
   ssh -T git@github.com
   # Should show: "Hi username! You've successfully authenticated..."
   ```

3. **Check SSH agent**:
   ```bash
   # List added keys
   ssh-add -l
   
   # If empty, add key again
   ssh-add ~/.ssh/id_ed25519
   ```

4. **Use verbose mode to debug**:
   ```bash
   ssh -vT git@github.com
   # Review output for clues
   ```

5. **Check key permissions**:
   ```bash
   chmod 600 ~/.ssh/id_ed25519
   chmod 644 ~/.ssh/id_ed25519.pub
   ```

### 10.4 Module Shows Blank Page

**Problem**: Module page loads but shows blank/white screen.

**Solutions**:

1. **Check PHP errors**:
   ```bash
   # Check Apache error log
   tail -f /var/log/apache2/error.log
   
   # Enable PHP error reporting temporarily
   nano /var/www/html/admin/modules/quickprovisioner/page.quickprovisioner.php
   # Add at the top:
   # ini_set('display_errors', 1);
   # error_reporting(E_ALL);
   ```

2. **Check browser console**:
   - Open browser developer tools (F12)
   - Check Console tab for JavaScript errors
   - Check Network tab for failed requests

3. **Verify database**:
   ```bash
   # Check if module tables exist
   mysql -u root -p freepbx -e "SHOW TABLES LIKE '%quickprovisioner%';"
   ```

4. **Check file syntax**:
   ```bash
   php -l /var/www/html/admin/modules/quickprovisioner/page.quickprovisioner.php
   ```

### 10.5 Assets Not Loading (404 Errors)

**Problem**: CSS/JS/Images return 404 errors.

**Solutions**:

1. **Verify assets directory exists**:
   ```bash
   ls -la /var/www/html/admin/modules/quickprovisioner/assets/
   ```

2. **Check permissions**:
   ```bash
   chmod -R 755 /var/www/html/admin/modules/quickprovisioner/assets/
   chown -R asterisk:asterisk /var/www/html/admin/modules/quickprovisioner/assets/
   ```

3. **Verify file paths in code**:
   ```bash
   # Check for correct asset references
   grep -r "assets/" /var/www/html/admin/modules/quickprovisioner/*.php
   ```

4. **Check Apache configuration**:
   ```bash
   # Verify DocumentRoot
   grep -i documentroot /etc/apache2/sites-enabled/*
   
   # Test Apache config
   apache2ctl configtest
   ```

### 10.6 Git Clone Fails

**Problem**: Cannot clone the repository.

**Solutions**:

1. **Check network connectivity**:
   ```bash
   ping -c 4 github.com
   ```

2. **Try HTTPS instead of SSH** (if SSH issues persist):
   ```bash
   cd /var/www/html/admin/modules
   git clone https://github.com/Ezra90/freepbx-quickprovisioner.git quickprovisioner
   ```
   
   Note: You'll need to enter GitHub credentials or use a personal access token.

3. **Check disk space**:
   ```bash
   df -h
   ```

4. **Check Git installation**:
   ```bash
   git --version
   
   # Reinstall if needed
   apt-get install --reinstall git
   ```

### 10.7 Permissions Reset After Reboot

**Problem**: Module stops working after reboot due to permission changes.

**Solutions**:

1. **Verify init script modifications** (from Section 7):
   ```bash
   # Check if init script has permission fix
   grep -A 5 "Fix.*permissions" /etc/init.d/asterisk
   ```

2. **Manually fix permissions**:
   ```bash
   chown -R asterisk:asterisk /var/www/html/admin/modules/quickprovisioner
   find /var/www/html/admin/modules/quickprovisioner -type d -exec chmod 755 {} \;
   find /var/www/html/admin/modules/quickprovisioner -type f -exec chmod 644 {} \;
   ```

3. **Create a cron job** (alternative solution):
   ```bash
   # Add to root's crontab
   crontab -e
   
   # Add this line:
   @reboot sleep 60 && chown -R asterisk:asterisk /var/www/html/admin/modules/quickprovisioner && find /var/www/html/admin/modules/quickprovisioner -type d -exec chmod 755 {} \; && find /var/www/html/admin/modules/quickprovisioner -type f -exec chmod 644 {} \;
   ```

### 10.8 FreePBX Web Interface Not Loading

**Problem**: Cannot access FreePBX web interface.

**Solutions**:

1. **Check Apache status**:
   ```bash
   systemctl status apache2
   
   # Restart if needed
   systemctl restart apache2
   ```

2. **Check firewall**:
   ```bash
   # Check if firewall is blocking
   iptables -L -n -v
   
   # Allow HTTP if needed
   iptables -A INPUT -p tcp --dport 80 -j ACCEPT
   iptables -A INPUT -p tcp --dport 443 -j ACCEPT
   ```

3. **Check Apache error logs**:
   ```bash
   tail -50 /var/log/apache2/error.log
   ```

4. **Verify Apache configuration**:
   ```bash
   apache2ctl configtest
   ```

### 10.9 Database Connection Errors

**Problem**: Module reports database errors.

**Solutions**:

1. **Check MySQL/MariaDB status**:
   ```bash
   systemctl status mysql
   # or
   systemctl status mariadb
   ```

2. **Test database connection**:
   ```bash
   mysql -u root -p freepbx -e "SELECT 1;"
   ```

3. **Verify module tables exist**:
   ```bash
   mysql -u root -p freepbx -e "SHOW TABLES LIKE '%quickprovisioner%';"
   ```

4. **Recreate tables if needed**:
   ```bash
   # Check if install.sql exists first
   if [ -f /var/www/html/admin/modules/quickprovisioner/install.sql ]; then
       mysql -u root -p freepbx < /var/www/html/admin/modules/quickprovisioner/install.sql
   else
       echo "install.sql not found - module may not require database tables"
   fi
   ```

### 10.10 Getting Help

If you continue to experience issues:

1. **Check Logs**:
   ```bash
   # FreePBX log
   tail -100 /var/log/asterisk/freepbx.log
   
   # Asterisk log
   tail -100 /var/log/asterisk/full
   
   # Apache error log
   tail -100 /var/log/apache2/error.log
   ```

2. **Enable Debug Mode**:
   ```bash
   # Enable FreePBX debug
   fwconsole setting FREEPBX_DEBUG 1
   
   # Check logs again after reproducing the issue
   ```

3. **Community Resources**:
   - FreePBX Community Forums: [https://community.freepbx.org/](https://community.freepbx.org/)
   - RasPBX Forums: [http://www.raspberry-asterisk.org/forum/](http://www.raspberry-asterisk.org/forum/)
   - GitHub Issues: [https://github.com/Ezra90/freepbx-quickprovisioner/issues](https://github.com/Ezra90/freepbx-quickprovisioner/issues)

---

## Appendix A: Quick Reference Commands

### System Management
```bash
# Reboot system
reboot

# Check system status
systemctl status asterisk
systemctl status apache2
systemctl status mysql

# Update system
apt-get update && apt-get upgrade -y
```

### Module Management
```bash
# Refresh module cache
fwconsole cache --flush
fwconsole ma refreshsignatures

# List modules
fwconsole ma list | grep quickprovisioner

# Enable module
fwconsole ma enable quickprovisioner

# Reload FreePBX
fwconsole reload
```

### Permission Fix
```bash
# Fix ownership
chown -R asterisk:asterisk /var/www/html/admin/modules/quickprovisioner

# Fix permissions
find /var/www/html/admin/modules/quickprovisioner -type d -exec chmod 755 {} \;
find /var/www/html/admin/modules/quickprovisioner -type f -exec chmod 644 {} \;
```

### Git Commands
```bash
# Clone repository
git clone git@github.com:Ezra90/freepbx-quickprovisioner.git quickprovisioner

# Update repository
cd /var/www/html/admin/modules/quickprovisioner
git pull

# Check repository status
git status
```

---

## Appendix B: File Locations Reference

| Component | Location |
|-----------|----------|
| Module Directory | `/var/www/html/admin/modules/quickprovisioner` |
| Module XML | `/var/www/html/admin/modules/quickprovisioner/module.xml` |
| Assets | `/var/www/html/admin/modules/quickprovisioner/assets/` |
| Asterisk Init Script | `/etc/init.d/asterisk` |
| Apache Config | `/etc/apache2/` |
| FreePBX Log | `/var/log/asterisk/freepbx.log` |
| Asterisk Log | `/var/log/asterisk/full` |
| Apache Error Log | `/var/log/apache2/error.log` |
| SSH Keys | `~/.ssh/id_ed25519` or `~/.ssh/id_rsa` |

---

## Appendix C: Default Credentials

| Service | Username | Default Password | Notes |
|---------|----------|------------------|-------|
| SSH/Root | `root` | `raspberry` | **Change immediately!** |
| FreePBX Web | `admin` | `admin` | **Change immediately!** |
| MySQL/MariaDB | `root` | Usually set during first boot | Check RasPBX docs |

---

## Document Information

- **Version**: 1.0.0
- **Last Updated**: January 2024
- **Author**: Repository Documentation
- **Tested On**: RasPBX (latest), FreePBX 13.0+
- **Module Version**: Quick Provisioner v2.1.0 (check module.xml for current version)

---

## License

This installation guide is provided as-is for use with the FreePBX Quick Provisioner module. The module itself is licensed under GPLv3.

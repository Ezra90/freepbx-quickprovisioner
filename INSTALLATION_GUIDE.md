# RasPBX QuickProvisioner Installation Guide

This guide provides comprehensive instructions for installing and configuring RasPBX with the QuickProvisioner module on a Raspberry Pi.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Windows USB Preparation](#windows-usb-preparation)
3. [Raspberry Pi OS Setup](#raspberry-pi-os-setup)
4. [GitHub SSH Key Configuration](#github-ssh-key-configuration)
5. [FreePBX and Module Installation](#freepbx-and-module-installation)
6. [QuickProvisioner Module Installation](#quickprovisioner-module-installation)
7. [Post-Installation Configuration](#post-installation-configuration)
8. [Troubleshooting](#troubleshooting)

---

## Prerequisites

Before you begin, ensure you have the following:

- **Hardware:**
  - Raspberry Pi 4 or later (recommended: 4GB RAM or higher)
  - MicroSD card (32GB or larger recommended)
  - USB card reader
  - Ethernet cable or WiFi connectivity
  - Power supply (5V/3A or higher)

- **Software:**
  - Raspberry Pi Imager (download from [raspberrypi.com](https://www.raspberrypi.com/software/))
  - SSH client (built-in on macOS/Linux, PuTTY on Windows)
  - Text editor for configuration files

- **Access:**
  - Internet connection
  - GitHub account with SSH key capability
  - Familiarity with Linux command line

---

## Windows USB Preparation

### Step 1: Download Raspberry Pi Imager

1. Visit [raspberrypi.com/software](https://www.raspberrypi.com/software/)
2. Download the Windows version of Raspberry Pi Imager
3. Run the installer and follow the on-screen instructions

### Step 2: Prepare the MicroSD Card

1. Insert the MicroSD card into your USB card reader
2. Connect the card reader to your Windows PC
3. Open Raspberry Pi Imager

### Step 3: Select OS and Configure

1. Click **"CHOOSE OS"**
2. Select **Raspberry Pi OS (64-bit)** - recommended for RasPBX
3. Click **"CHOOSE STORAGE"**
4. Select your MicroSD card (verify the size to ensure you select the correct device)

### Step 4: Configure Advanced Options

1. Click the **gear icon** to access advanced options
2. Enable the following:
   - **Set hostname:** `raspbx` (or your preferred name)
   - **Enable SSH:** Check this box and select "Use password authentication"
   - **Set username and password:** Use `root` as username with a secure password
   - **Configure wireless LAN:** (optional) Enter your WiFi SSID and password
   - **Set locale settings:** Set your timezone and keyboard layout
3. Click **"SAVE"**

### Step 5: Write to SD Card

1. Click **"WRITE"** in the main Imager window
2. Confirm the warning dialog
3. Wait for the process to complete (5-15 minutes depending on card speed)
4. Click **"CONTINUE"** when finished
5. Eject the card and remove it from your PC

---

## Raspberry Pi OS Setup

### Step 1: Initial Boot

1. Insert the prepared MicroSD card into your Raspberry Pi
2. Connect the Ethernet cable (if not using WiFi)
3. Power on the Raspberry Pi
4. Wait 2-3 minutes for initial setup to complete

### Step 2: Connect via SSH

**From macOS/Linux:**
```bash
ssh root@raspbx.local
# Or use the IP address if .local doesn't resolve:
# ssh root@<raspberry-pi-ip>
```

**From Windows (using PuTTY):**
1. Open PuTTY
2. Enter `raspbx.local` (or IP address) in the Host Name field
3. Set port to 22
4. Click "Open"
5. Login with username `root` and the password you configured

### Step 3: Update System

```bash
apt-get update
apt-get upgrade -y
apt-get install -y curl wget git openssh-server sudo
```

### Step 4: Disable WiFi and Bluetooth (if required)

**Disable WiFi:**
```bash
# Edit the network configuration
nano /boot/firmware/cmdline.txt
```

Add the following to the end of the line (without creating a new line):
```
dtoverlay=disable-wifi
```

Save with Ctrl+X, then Y, then Enter.

**Disable Bluetooth:**
```bash
# Edit the configuration file
nano /boot/firmware/config.txt
```

Add the following lines at the end:
```
dtoverlay=disable-bt
```

Save with Ctrl+X, then Y, then Enter.

**Reboot to apply changes:**
```bash
reboot
```

### Step 5: Configure Static IP (Optional)

For a more stable installation, configure a static IP address:

```bash
nano /etc/dhcpcd.conf
```

Add the following lines at the end:
```
# For Ethernet (eth0)
interface eth0
static ip_address=192.168.1.100/24
static routers=192.168.1.1
static domain_name_servers=8.8.8.8 8.8.4.4

# For WiFi (wlan0) - if applicable
# interface wlan0
# static ip_address=192.168.1.101/24
# static routers=192.168.1.1
# static domain_name_servers=8.8.8.8 8.8.4.4
```

Save and reboot:
```bash
reboot
```

---

## GitHub SSH Key Configuration

### Step 1: Generate SSH Key Pair

```bash
ssh-keygen -t rsa -b 4096 -f ~/.ssh/id_rsa -N ""
```

This creates two files:
- `~/.ssh/id_rsa` (private key - keep secure)
- `~/.ssh/id_rsa.pub` (public key - share with GitHub)

### Step 2: Display Your Public Key

```bash
cat ~/.ssh/id_rsa.pub
```

Copy the entire output (it will look like `ssh-rsa AAAA...`).

### Step 3: Add SSH Key to GitHub

1. Log in to your GitHub account
2. Navigate to **Settings** > **SSH and GPG keys**
3. Click **New SSH key**
4. Give it a title like "RasPBX Installation"
5. Paste the public key content into the key field
6. Click **Add SSH key**

### Step 4: Test SSH Connection

```bash
ssh -T git@github.com
```

You should see a message like:
```
Hi Ezra90! You've successfully authenticated, but GitHub does not provide shell access.
```

---

## FreePBX and Module Installation

### Step 1: Install Required Dependencies

```bash
apt-get install -y \
    build-essential \
    curl \
    wget \
    git \
    php \
    php-cli \
    php-mysql \
    php-mbstring \
    php-xml \
    php-json \
    php-curl \
    php-gd \
    php-intl \
    mariadb-server \
    mariadb-client \
    apache2 \
    libapache2-mod-php \
    npm \
    nodejs
```

### Step 2: Start Required Services

```bash
# Enable and start MariaDB
systemctl enable mariadb
systemctl start mariadb

# Enable and start Apache
systemctl enable apache2
systemctl start apache2
```

### Step 3: Install Asterisk (if not already installed)

RasPBX typically comes with Asterisk, but verify:

```bash
asterisk -v
```

If not installed, follow the [Asterisk Installation Guide](https://wiki.asterisk.org/wiki/display/AST/Building+and+Installing+Asterisk+from+Source).

### Step 4: Prepare FreePBX Installation Directory

```bash
cd /opt
mkdir -p freepbx
cd freepbx
```

### Step 5: Install FreePBX Core

```bash
# Download FreePBX
wget http://mirror.freepbx.org/modules/packages/freepbx/freepbx-16.0-latest.tgz

# Extract the archive
tar -xzf freepbx-16.0-latest.tgz
cd freepbx

# Run the installation
./install -n
```

### Step 6: Install FreePBX Modules

FreePBX modules extend the core functionality. Install all 16 recommended modules:

```bash
cd /opt/freepbx

# 1. ARI Manager - Asterisk REST Interface management
fwconsole ma install arimanager

# 2. Backup - System backup and restore functionality
fwconsole ma install backup

# 3. Built-in - Core built-in features
fwconsole ma install builtin

# 4. Bulk Handler - Bulk operations management
fwconsole ma install bulkhandler

# 5. Call Recording - Record and manage call recordings
fwconsole ma install callrecording

# 6. Core - Core FreePBX functionality
fwconsole ma install core

# 7. Dashboard - Admin dashboard interface
fwconsole ma install dashboard

# 8. File Store - File storage management
fwconsole ma install filestore

# 9. Framework - Core framework and utilities
fwconsole ma install framework

# 10. Manager - Advanced module manager
fwconsole ma install manager

# 11. Miscellaneous Applications - Additional utilities
fwconsole ma install miscapps

# 12. PM2 - Process manager for Node.js applications
fwconsole ma install pm2

# 13. Recordings - Voicemail and call recording management
fwconsole ma install recordings

# 14. SIP Settings - SIP configuration and management
fwconsole ma install sipsettings

# 15. Sound Languages - Multi-language sound support
fwconsole ma install soundlang

# 16. Voicemail - Voicemail system management
fwconsole ma install voicemail

# Enable all modules
fwconsole ma enable arimanager backup builtin bulkhandler callrecording core dashboard filestore framework manager miscapps pm2 recordings sipsettings soundlang voicemail

# Reload the FreePBX system
fwconsole reload
```

### Step 7: Verify Module Installation

```bash
# List all installed modules
fwconsole ma list

# You should see all 16 modules listed with their status
```

---

## Permission Management for Asterisk User

### Step 1: Create or Verify Asterisk User

```bash
# Check if asterisk user exists
id asterisk

# If not found, create it
useradd -m -s /bin/bash asterisk

# Add asterisk to necessary groups
usermod -aG audio,dialout asterisk
```

### Step 2: Set Directory Permissions

```bash
# Set permissions for FreePBX directory
chown -R asterisk:asterisk /opt/freepbx
chmod -R 755 /opt/freepbx

# Set permissions for Asterisk configuration
chown -R asterisk:asterisk /etc/asterisk
chmod -R 755 /etc/asterisk

# Set permissions for Asterisk sounds and media
chown -R asterisk:asterisk /var/lib/asterisk
chmod -R 755 /var/lib/asterisk

# Set permissions for Asterisk logs
chown -R asterisk:asterisk /var/log/asterisk
chmod -R 755 /var/log/asterisk

# Set permissions for Asterisk spool (recordings, voicemail)
chown -R asterisk:asterisk /var/spool/asterisk
chmod -R 755 /var/spool/asterisk
```

### Step 3: Configure Asterisk Service to Run as Asterisk User

```bash
nano /etc/default/asterisk
```

Ensure the following lines are present:
```
AST_USER="asterisk"
AST_GROUP="asterisk"
```

### Step 4: Restart Asterisk

```bash
systemctl restart asterisk

# Verify it's running as asterisk user
ps aux | grep asterisk
```

---

## QuickProvisioner Module Installation

### Step 1: Clone the Repository

Navigate to the FreePBX modules directory:

```bash
cd /opt/freepbx/var/www/html/admin/modules
```

Clone the QuickProvisioner module using SSH:

```bash
git clone git@github.com:Ezra90/freepbx-quickprovisioner.git quickprovisioner
```

Or using HTTPS (if SSH is not configured):

```bash
git clone https://github.com/Ezra90/freepbx-quickprovisioner.git quickprovisioner
```

### Step 2: Set Directory Permissions

```bash
# Set ownership to asterisk user
chown -R asterisk:asterisk /opt/freepbx/var/www/html/admin/modules/quickprovisioner

# Set appropriate permissions
chmod -R 755 /opt/freepbx/var/www/html/admin/modules/quickprovisioner
chmod -R 644 /opt/freepbx/var/www/html/admin/modules/quickprovisioner/*.php
chmod -R 755 /opt/freepbx/var/www/html/admin/modules/quickprovisioner/bin
```

### Step 3: Enable the QuickProvisioner Module

```bash
cd /opt/freepbx
fwconsole ma enable quickprovisioner
fwconsole reload
```

### Step 4: Verify Installation

```bash
# Check if module is listed and enabled
fwconsole ma list | grep quickprovisioner

# Access the FreePBX web interface
# Navigate to http://<your-raspbx-ip>
# Admin > Modules > Scroll to QuickProvisioner
# Should see it listed and enabled
```

### Step 5: Configure QuickProvisioner (Optional)

Access the configuration through the FreePBX web interface:

1. Open your browser and navigate to `http://<your-raspbx-ip>`
2. Log in with your FreePBX admin credentials
3. Navigate to **Admin** > **Modules** > **QuickProvisioner**
4. Configure settings as needed for your deployment

---

## Post-Installation Configuration

### Step 1: Secure MariaDB

```bash
mysql_secure_installation
```

Follow the prompts to:
- Set root password
- Remove anonymous users
- Disable remote root login
- Remove test databases

### Step 2: Configure Apache for HTTPS (Recommended)

```bash
# Enable SSL module
a2enmod ssl

# Create self-signed certificate
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/ssl/private/raspbx.key \
    -out /etc/ssl/certs/raspbx.crt

# Restart Apache
systemctl restart apache2
```

### Step 3: Configure Firewall

```bash
# Install UFW if not already installed
apt-get install -y ufw

# Enable UFW
ufw enable

# Allow SSH
ufw allow 22/tcp

# Allow HTTP and HTTPS
ufw allow 80/tcp
ufw allow 443/tcp

# Allow SIP (UDP)
ufw allow 5060/udp
ufw allow 5160/udp

# Allow RTP (audio/video)
ufw allow 10000:20000/udp

# Allow IAX2
ufw allow 4569/udp

# Verify rules
ufw status
```

### Step 4: Set Up Automated Backups

```bash
# Create a backup directory
mkdir -p /var/backups/freepbx

# Use FreePBX backup module via cron
# Edit crontab
crontab -e

# Add daily backup at 2 AM
0 2 * * * /opt/freepbx/bin/fwconsole backup --backup 2>&1 | logger
```

### Step 5: Enable Updates

```bash
# Configure automatic security updates
apt-get install -y unattended-upgrades
dpkg-reconfigure unattended-upgrades

# Configure automatic FreePBX module updates
# In FreePBX web interface: Admin > System Admin > Module Admin
# Enable "Auto-enable Upgrades" if desired
```

---

## Troubleshooting

### SSH Connection Issues

**Problem:** Cannot connect via SSH
```bash
# Check SSH service status
systemctl status ssh

# Restart SSH service
systemctl restart ssh

# Verify SSH is listening on port 22
netstat -ln | grep :22
```

**Solution:** Ensure the Raspberry Pi has network connectivity and SSH is enabled.

### GitHub SSH Key Issues

**Problem:** "Permission denied (publickey)" when cloning
```bash
# Verify SSH key exists
ls -la ~/.ssh/

# Test SSH connection
ssh -vvv git@github.com

# Add SSH key to agent if needed
eval "$(ssh-agent -s)"
ssh-add ~/.ssh/id_rsa
```

**Solution:** Ensure your public key is added to GitHub and the private key has correct permissions (600).

### Module Installation Issues

**Problem:** Module fails to install
```bash
# Check FreePBX logs
tail -f /var/log/asterisk/freepbx_engine.log

# Verify module directory permissions
ls -la /opt/freepbx/var/www/html/admin/modules/quickprovisioner

# Reload FreePBX
fwconsole reload

# Check for syntax errors
php -l /opt/freepbx/var/www/html/admin/modules/quickprovisioner/quickprovisioner.php
```

**Solution:** Ensure proper directory permissions and ownership by asterisk user.

### Asterisk Service Issues

**Problem:** Asterisk service fails to start
```bash
# Check service status
systemctl status asterisk

# View detailed error log
journalctl -u asterisk -n 50

# Check Asterisk configuration
asterisk -nvc

# Verify ownership and permissions
ls -la /etc/asterisk
ls -la /var/lib/asterisk
```

**Solution:** Ensure all configuration files and directories have correct ownership and permissions.

### Database Connection Issues

**Problem:** FreePBX cannot connect to database
```bash
# Check MariaDB status
systemctl status mariadb

# Test database connection
mysql -u root -p -e "show databases;"

# Verify FreePBX database
mysql -u root -p -e "use asterisk; show tables;"
```

**Solution:** Ensure MariaDB is running and the asterisk database exists and is properly configured.

### Web Interface Access Issues

**Problem:** Cannot access FreePBX web interface
```bash
# Check Apache status
systemctl status apache2

# Verify Apache is listening
netstat -ln | grep :80

# Check Apache error logs
tail -f /var/log/apache2/error.log

# Verify PHP is working
php -v
```

**Solution:** Ensure Apache is running, PHP is installed, and firewall allows HTTP/HTTPS traffic.

---

## Additional Resources

- [Raspberry Pi Documentation](https://www.raspberrypi.com/documentation/)
- [RasPBX Project](http://www.raspbx.org/)
- [FreePBX Documentation](https://docs.freepbx.org/)
- [Asterisk Documentation](https://wiki.asterisk.org/)
- [GitHub SSH Documentation](https://docs.github.com/en/authentication/connecting-to-github-with-ssh)

---

## Support and Issues

For issues specific to QuickProvisioner, please visit:
- [QuickProvisioner GitHub Repository](https://github.com/Ezra90/freepbx-quickprovisioner)
- Create an issue with detailed logs and error messages

For general RasPBX support:
- [RasPBX Forums](http://www.raspbx.org/)
- [Asterisk Community](https://community.asterisk.org/)

---

**Last Updated:** 2026-01-02
**Document Version:** 1.0

# FreePBX QuickProvisioner - Comprehensive Installation Guide

**Last Updated:** 2026-01-02
**Compatible with:** Asterisk 22, PHP 8.2, MariaDB, Apache2, Raspberry Pi OS

---

## Table of Contents

1. [Windows Preparation (USB Flashing Only)](#windows-preparation-usb-flashing-only)
2. [Raspberry Pi RasPBX Setup](#raspberry-pi-raspbx-setup)
3. [GitHub SSH Key Configuration](#github-ssh-key-configuration)
4. [Module Installation](#module-installation)
5. [Permission Management](#permission-management)
6. [Post-Installation Verification](#post-installation-verification)
7. [Maintenance and Updates](#maintenance-and-updates)
8. [Common Issues and Solutions](#common-issues-and-solutions)
9. [Support and Troubleshooting](#support-and-troubleshooting)
10. [Security Recommendations](#security-recommendations)

---

## Windows Preparation (USB Flashing Only)

> **Note:** This section is exclusively for creating a bootable USB drive to install RasPBX on Raspberry Pi. Windows is NOT used to run FreePBX or any FreePBX services.

### Prerequisites

- **USB Drive:** 8GB or larger (will be erased)
- **Windows PC:** Windows 7 or later
- **RasPBX ISO File:** Download from [RasPBX Downloads](https://www.raspbx.org/downloads/)

### Step 1: Download Required Tools

Download one of the following USB flashing tools:

- **Balena Etcher (Recommended):** [https://www.balena.io/etcher/](https://www.balena.io/etcher/)
- **Rufus:** [https://rufus.ie/](https://rufus.ie/)
- **Win32 Disk Imager:** [https://sourceforge.net/projects/win32diskimager/](https://sourceforge.net/projects/win32diskimager/)

### Step 2: Prepare USB Drive

1. Insert USB drive into Windows PC
2. **WARNING:** This will erase all data on the USB drive
3. Open Balena Etcher (or chosen flashing tool)
4. Click "Flash from file" and select the RasPBX `.iso` file
5. Click "Select target" and choose your USB drive
6. Click "Flash" and wait for completion (typically 5-10 minutes)
7. Eject the USB drive safely when complete

### Step 3: Boot Raspberry Pi from USB

1. Insert the flashed USB drive into Raspberry Pi
2. Power on the Raspberry Pi
3. System should boot from USB and begin installation
4. Follow Raspberry Pi RasPBX setup instructions in the next section

---

## Raspberry Pi RasPBX Setup

### Hardware Requirements

- **Raspberry Pi:** Model 3B+, 4B, or 5 (recommended: 4B+ with 2GB+ RAM)
- **Power Supply:** 5V/3A (minimum for Pi 4B)
- **Storage:** microSD card 16GB or larger, or USB 3.0 drive for Pi 5
- **Network:** Ethernet connection (recommended) or WiFi adapter
- **USB Drive:** 8GB+ for initial installation media (if not using pre-flashed microSD)

### Installation Steps

#### Option A: Using Pre-Flashed microSD Card

1. Insert flashed microSD card into Raspberry Pi
2. Connect Ethernet cable (optional but recommended)
3. Power on Raspberry Pi
4. Wait 3-5 minutes for initial boot and setup
5. Access web interface at `http://<raspberry-pi-ip>` or `http://raspbx.local`
6. Default credentials: `admin` / `admin` (change immediately)

#### Option B: Using USB Installation Media

1. Flash RasPBX to USB drive using Windows Preparation steps
2. Insert USB drive into Raspberry Pi
3. Power on Raspberry Pi
4. Follow on-screen installation prompts
5. Select microSD card as installation target
6. Complete installation and reboot

### Initial RasPBX Configuration

After accessing the web interface:

1. **Change Default Credentials**
   ```
   Username: admin
   New Password: [Strong password with letters, numbers, symbols]
   ```

2. **Configure Network Settings**
   - Set static IP address (recommended)
   - Configure DNS servers
   - Enable/disable WiFi as needed

3. **System Locale and Timezone**
   - Select appropriate timezone
   - Configure language preferences
   - Set system time (NTP recommended)

4. **Enable Essential Services**
   - Verify Asterisk is running
   - Verify MariaDB is running
   - Verify Apache2 is running

---

## GitHub SSH Key Configuration

This section enables secure cloning and pushing to GitHub repositories without entering credentials repeatedly.

### Generate SSH Key on Raspberry Pi

```bash
# SSH into your Raspberry Pi
ssh pi@<raspberry-pi-ip>

# Generate SSH key pair
ssh-keygen -t ed25519 -C "your-email@example.com"

# When prompted:
# - File location: Press Enter (default: ~/.ssh/id_ed25519)
# - Passphrase: [Enter strong passphrase]
# - Confirm passphrase: [Repeat passphrase]
```

### Display and Copy Public Key

```bash
# Display your public key
cat ~/.ssh/id_ed25519.pub

# Copy the entire output starting with "ssh-ed25519"
```

### Add Public Key to GitHub

1. Log in to [GitHub](https://github.com)
2. Navigate to **Settings → SSH and GPG keys**
3. Click **New SSH key**
4. **Title:** FreePBX QuickProvisioner (or descriptive name)
5. **Key type:** Authentication Key
6. **Key:** Paste your public key from above
7. Click **Add SSH key**

### Test SSH Connection

```bash
# Test connection to GitHub
ssh -T git@github.com

# Expected output: "Hi [username]! You've successfully authenticated..."
```

### Configure Git (Optional but Recommended)

```bash
# Set global Git configuration
git config --global user.name "Your Name"
git config --global user.email "your-email@example.com"

# Set default branch name
git config --global init.defaultBranch main
```

### Clone Repository Using SSH

```bash
# Clone using SSH (requires SSH key configured)
git clone git@github.com:Ezra90/freepbx-quickprovisioner.git

# Navigate to repository
cd freepbx-quickprovisioner
```

---

## Module Installation

FreePBX requires the installation and configuration of essential modules for full functionality. All listed modules must be installed and enabled.

### Required FreePBX Modules

| Module | Purpose | Version |
|--------|---------|---------|
| **arimanager** | ARI (Asterisk REST Interface) management | Latest |
| **backup** | System backup and restore functionality | Latest |
| **bulkhandler** | Bulk operations and batch processing | Latest |
| **callrecording** | Call recording and playback management | Latest |
| **core** | Core FreePBX framework and features | Latest |
| **dashboard** | Admin dashboard and statistics | Latest |
| **filestore** | File storage management | Latest |
| **framework** | FreePBX framework and libraries | Latest |
| **manager** | Manager interface for system control | Latest |
| **miscapps** | Miscellaneous applications | Latest |
| **pm2** | Process manager integration | Latest |
| **recordings** | Recording management and playback | Latest |
| **sipsettings** | SIP configuration and settings | Latest |
| **soundlang** | Sound files and language management | Latest |
| **voicemail** | Voicemail system and management | Latest |

### Installation Methods

#### Method 1: Web Interface Installation

1. Log in to FreePBX web interface (http://<raspberry-pi-ip>)
2. Navigate to **Admin → Modules → Manage**
3. Search for each module listed above
4. Click **Download** next to each module
5. After download completes, click **Install**
6. Repeat for all listed modules
7. Navigate to **Admin → Modules → Admin** to enable each module
8. Apply changes at the top of the page

#### Method 2: Command Line Installation

```bash
# SSH into Raspberry Pi
ssh pi@<raspberry-pi-ip>

# Change to FreePBX directory
cd /var/www/html

# Download a module (example: arimanager)
fwconsole ma download arimanager

# Install the module
fwconsole ma install arimanager

# Enable the module
fwconsole ma enable arimanager

# Repeat for all required modules
fwconsole ma download backup && fwconsole ma install backup && fwconsole ma enable backup
fwconsole ma download bulkhandler && fwconsole ma install bulkhandler && fwconsole ma enable bulkhandler
fwconsole ma download callrecording && fwconsole ma install callrecording && fwconsole ma enable callrecording
fwconsole ma download core && fwconsole ma install core && fwconsole ma enable core
fwconsole ma download dashboard && fwconsole ma install dashboard && fwconsole ma enable dashboard
fwconsole ma download filestore && fwconsole ma install filestore && fwconsole ma enable filestore
fwconsole ma download framework && fwconsole ma install framework && fwconsole ma enable framework
fwconsole ma download manager && fwconsole ma install manager && fwconsole ma enable manager
fwconsole ma download miscapps && fwconsole ma install miscapps && fwconsole ma enable miscapps
fwconsole ma download pm2 && fwconsole ma install pm2 && fwconsole ma enable pm2
fwconsole ma download recordings && fwconsole ma install recordings && fwconsole ma enable recordings
fwconsole ma download sipsettings && fwconsole ma install sipsettings && fwconsole ma enable sipsettings
fwconsole ma download soundlang && fwconsole ma install soundlang && fwconsole ma enable soundlang
fwconsole ma download voicemail && fwconsole ma install voicemail && fwconsole ma enable voicemail
```

### Verify Module Installation

```bash
# List all installed modules
fwconsole ma list

# Check status of specific module
fwconsole ma status arimanager

# Reload FreePBX to apply changes
fwconsole reload
```

### Troubleshoot Module Installation

```bash
# If modules fail to download, check internet connection
ping 8.8.8.8

# Check FreePBX logs
tail -f /var/log/asterisk/freepbx_engine.log

# Restart Apache and Asterisk if modules not loading
sudo systemctl restart apache2
sudo systemctl restart asterisk
```

---

## Permission Management

### Verify File and Directory Permissions

Proper permissions are critical for FreePBX security and functionality.

#### Check Current Permissions

```bash
# Check FreePBX directory permissions
ls -la /var/www/html/admin/

# Check Asterisk directory permissions
ls -la /etc/asterisk/

# Check logs directory permissions
ls -la /var/log/asterisk/
```

#### Standard Permission Configuration

```bash
# Set FreePBX directory ownership
sudo chown -R asterisk:asterisk /var/www/html

# Set proper directory permissions
sudo chmod -R 755 /var/www/html

# Set proper file permissions
sudo find /var/www/html -type f -exec chmod 644 {} \;

# Set Asterisk config permissions
sudo chown -R asterisk:asterisk /etc/asterisk
sudo chmod -R 755 /etc/asterisk

# Set voicemail permissions
sudo chmod -R 755 /var/spool/asterisk/voicemail

# Set recording permissions
sudo chmod -R 755 /var/spool/asterisk/monitor
```

#### User Groups and Access Control

```bash
# Add your user to asterisk group
sudo usermod -aG asterisk $USER

# Verify group membership
groups $USER

# Log out and log back in for changes to take effect
```

### FreePBX Web Interface Permissions

Navigate to **Admin → System Admin → Backup & Restore** to configure:
- Backup directories
- File access permissions
- User account restrictions

---

## Post-Installation Verification

### System Health Check

```bash
# Check system resources
free -h
df -h

# Check Asterisk status
sudo systemctl status asterisk

# Check MariaDB status
sudo systemctl status mariadb

# Check Apache2 status
sudo systemctl status apache2

# Check Asterisk uptime
asterisk -rx "core show uptime"
```

### Service Verification

```bash
# Verify Asterisk is listening on SIP port
sudo netstat -tlnp | grep asterisk

# Verify MariaDB is listening
sudo netstat -tlnp | grep mysqld

# Verify Apache is listening
sudo netstat -tlnp | grep apache
```

### Web Interface Access

1. Open web browser
2. Navigate to `http://<raspberry-pi-ip>`
3. Log in with admin credentials
4. Verify all modules are listed as "Enabled"
5. Check **Admin → System Admin → System Status** for warnings

### Asterisk Console Access

```bash
# Connect to Asterisk console
sudo asterisk -rvvv

# Common diagnostic commands:
core show uptime
core show channels
sip show peers
sip show channels
voicemail show users
```

### Test Dial Plan

```bash
# Create test extension 100 via web interface or CLI
asterisk -rx "sip show peers"

# Test voicemail
asterisk -rx "voicemail show users"

# Check call logs
sudo tail -f /var/log/asterisk/messages
```

---

## Maintenance and Updates

### Regular Maintenance Schedule

| Task | Frequency | Command |
|------|-----------|---------|
| Check disk space | Daily | `df -h` |
| Review logs | Daily | `tail -f /var/log/asterisk/messages` |
| Backup database | Weekly | See backup section |
| Update system | Monthly | See updates section |
| Test restore | Monthly | See backup section |

### System Backup

#### Automated Backup via Web Interface

1. Log in to FreePBX
2. Navigate to **Admin → System Admin → Backup & Restore**
3. Click **Add Backup Schedule**
4. Configure:
   - Frequency (Daily recommended)
   - Time (Off-peak hours)
   - Backup location
5. Click **Save**

#### Manual Backup

```bash
# Create backup directory
sudo mkdir -p /backups/freepbx

# Backup FreePBX database
sudo mysqldump -u freepbxuser -p freepbx > /backups/freepbx/freepbx_$(date +%Y%m%d_%H%M%S).sql

# Backup configuration files
sudo tar -czf /backups/freepbx/asterisk_config_$(date +%Y%m%d_%H%M%S).tar.gz /etc/asterisk/

# Backup voicemail and recordings
sudo tar -czf /backups/freepbx/voicemail_$(date +%Y%m%d_%H%M%S).tar.gz /var/spool/asterisk/voicemail/
```

### System Updates

#### Update Raspberry Pi OS

```bash
# Update package lists
sudo apt update

# Upgrade packages
sudo apt upgrade -y

# Clean cache
sudo apt autoclean
sudo apt autoremove
```

#### Update FreePBX

```bash
# Check for FreePBX updates
fwconsole ma list | grep -i update

# Update framework first
fwconsole ma download framework
fwconsole ma install framework

# Update all other modules
fwconsole ma downloadall
fwconsole ma installall

# Apply changes and reload
fwconsole reload
```

#### Update Asterisk

```bash
# Check current Asterisk version
asterisk -v

# Update Asterisk (if available)
sudo apt update
sudo apt upgrade asterisk

# Restart Asterisk after update
sudo systemctl restart asterisk
```

### Service Restart Procedures

```bash
# Restart all services (safe method)
sudo systemctl restart asterisk
sudo systemctl restart mariadb
sudo systemctl restart apache2

# Restart single service
sudo systemctl restart asterisk  # or mariadb, apache2

# Check service status
sudo systemctl status asterisk   # or mariadb, apache2

# Enable services on boot
sudo systemctl enable asterisk mariadb apache2
```

---

## Common Issues and Solutions

### Issue 1: Web Interface Not Accessible

**Symptoms:** Cannot reach `http://<raspberry-pi-ip>`

**Solutions:**

```bash
# Check Apache is running
sudo systemctl status apache2

# Start Apache if stopped
sudo systemctl start apache2

# Check Apache error log
sudo tail -f /var/log/apache2/error.log

# Verify Apache is listening on port 80
sudo netstat -tlnp | grep apache

# Restart Apache
sudo systemctl restart apache2
```

### Issue 2: Asterisk Not Starting

**Symptoms:** Asterisk service fails to start or crashes

**Solutions:**

```bash
# Check Asterisk status
sudo systemctl status asterisk

# View Asterisk logs
sudo tail -f /var/log/asterisk/messages

# Validate Asterisk configuration
sudo asterisk -nvc

# Check for configuration errors
sudo asterisk -g

# Restart Asterisk
sudo systemctl restart asterisk

# Check system resources
free -h
df -h
```

### Issue 3: No Audio in Calls

**Symptoms:** Calls connect but no audio

**Solutions:**

```bash
# Check RTP ports are open
sudo ufw allow 10000:20000/udp

# Verify SIP configuration
asterisk -rx "sip show peers"

# Check for NAT/firewall issues
asterisk -rx "core show settings"

# Restart Asterisk
sudo systemctl restart asterisk

# Check audio codecs
asterisk -rx "core show codecs"
```

### Issue 4: Database Connection Errors

**Symptoms:** FreePBX shows database error messages

**Solutions:**

```bash
# Check MariaDB is running
sudo systemctl status mariadb

# Start MariaDB if stopped
sudo systemctl start mariadb

# Check database connectivity
mysql -u freepbxuser -p -e "SELECT 1;"

# Check MariaDB logs
sudo tail -f /var/log/mysql/error.log

# Restart MariaDB
sudo systemctl restart mariadb
```

### Issue 5: High CPU Usage

**Symptoms:** Raspberry Pi running slowly, high CPU usage

**Solutions:**

```bash
# Check CPU usage
top -b -n 1

# Check process consuming CPU
ps aux | sort -nrk 3,3 | head -n 10

# Check memory usage
free -h

# Stop unnecessary services
sudo systemctl stop bluetooth

# Check running processes
ps aux | grep asterisk
```

### Issue 6: Permission Denied Errors

**Symptoms:** Errors accessing files or directories

**Solutions:**

```bash
# Check file permissions
ls -la /var/www/html/
ls -la /etc/asterisk/

# Fix FreePBX permissions
sudo chown -R asterisk:asterisk /var/www/html
sudo chmod -R 755 /var/www/html

# Fix Asterisk permissions
sudo chown -R asterisk:asterisk /etc/asterisk
sudo chmod -R 755 /etc/asterisk

# Restart services
sudo systemctl restart asterisk apache2
```

### Issue 7: Modules Not Showing as Enabled

**Symptoms:** Modules installed but not active

**Solutions:**

```bash
# List module status
fwconsole ma list

# Enable specific module
fwconsole ma enable arimanager

# Enable all modules
fwconsole ma enableall

# Reload FreePBX
fwconsole reload

# Restart services
sudo systemctl restart asterisk apache2
```

---

## Support and Troubleshooting

### Log Files Location

| Service | Log File | Command |
|---------|----------|---------|
| Asterisk | `/var/log/asterisk/messages` | `tail -f /var/log/asterisk/messages` |
| Apache2 | `/var/log/apache2/error.log` | `tail -f /var/log/apache2/error.log` |
| MariaDB | `/var/log/mysql/error.log` | `tail -f /var/log/mysql/error.log` |
| System | `/var/log/syslog` | `tail -f /var/log/syslog` |
| FreePBX | `/var/log/asterisk/freepbx_engine.log` | `tail -f /var/log/asterisk/freepbx_engine.log` |

### Useful Diagnostic Commands

```bash
# System information
uname -a
cat /proc/meminfo
df -h

# Network information
ip addr show
netstat -tulpn

# Asterisk diagnostics
asterisk -rx "core show version"
asterisk -rx "core show uptime"
asterisk -rx "sip show peers"
asterisk -rx "sip show channels"

# FreePBX diagnostics
fwconsole dbug on
fwconsole dbug off
fwconsole reload
```

### Getting Help

1. **Check Logs First:** Always review relevant log files for error messages
2. **Verify Services:** Ensure all services are running (`systemctl status asterisk`)
3. **Check Permissions:** Verify file and directory permissions are correct
4. **Test Connectivity:** Confirm network connectivity and firewall rules
5. **Review Configuration:** Check Asterisk and FreePBX configurations for syntax errors

### Issue Reporting

When reporting issues, include:
- Asterisk version: `asterisk -v`
- FreePBX version: Web interface bottom right
- Raspberry Pi model and OS version: `uname -a`
- Relevant log excerpts (last 50 lines)
- Steps to reproduce the issue
- Expected vs. actual behavior

---

## Security Recommendations

### Strong Access Control

#### Change Default Credentials

```bash
# Change admin password immediately after installation
# Via web interface: Admin → System Admin → Credentials

# Change MySQL password
mysql -u freepbxuser -p
# At MySQL prompt:
# ALTER USER 'freepbxuser'@'localhost' IDENTIFIED BY 'new_strong_password';
# FLUSH PRIVILEGES;
# EXIT;
```

#### Set Up Additional Admin Accounts

1. Log in as admin
2. Navigate to **Admin → System Admin → Users**
3. Click **Add New User**
4. Configure user with appropriate permissions
5. Assign strong password

### Firewall Configuration

```bash
# Enable UFW firewall
sudo ufw enable

# Allow essential ports only
sudo ufw allow ssh
sudo ufw allow 80/tcp      # HTTP
sudo ufw allow 443/tcp     # HTTPS
sudo ufw allow 5060/tcp    # SIP TCP
sudo ufw allow 5060/udp    # SIP UDP
sudo ufw allow 10000:20000/udp # RTP

# Check firewall status
sudo ufw status

# Disable access to dangerous ports
sudo ufw deny 22          # SSH - if not needed
sudo ufw deny 3306        # MySQL - allow only local
```

### Network Segmentation

1. **Internal Network:** Keep Raspberry Pi on internal network if possible
2. **VPN Access:** Use VPN for remote administration
3. **SSH Keys:** Use SSH key authentication instead of passwords
4. **Restrict Access:** Limit FreePBX access to trusted IP addresses

### Regular Security Updates

```bash
# Enable automatic security updates
sudo apt install -y unattended-upgrades

# Configure automatic updates
sudo dpkg-reconfigure -plow unattended-upgrades

# Check for available updates
sudo apt update
apt list --upgradable
```

### Database Security

```bash
# Limit MySQL to localhost only
sudo nano /etc/mysql/mariadb.conf.d/50-server.cnf
# Find: bind-address = 127.0.0.1
# Ensure it's set to localhost only

# Remove MySQL test database
mysql -u root -p
# DROP DATABASE test;
# DELETE FROM mysql.user WHERE user='test';
# FLUSH PRIVILEGES;
```

### Asterisk SIP Security

1. Log in to FreePBX web interface
2. Navigate to **Settings → SIP Settings**
3. Configure:
   - **Require REGISTER:** Yes
   - **Require Certificate Validation:** Yes
   - **SIP TLS:** Enable if supported by devices
   - **SRTP:** Enable for sensitive calls
4. Apply changes

### Regular Backups

```bash
# Create weekly backup
0 2 * * 0 /usr/bin/mysqldump -u freepbxuser -p'password' freepbx > /backups/freepbx_$(date +\%Y\%m\%d).sql

# Backup to external storage
rsync -av /backups/ /mnt/external_backup/

# Verify backup integrity
gzip -t /backups/freepbx_*.sql.gz
```

### Monitoring and Alerts

1. Set up log rotation for large log files
2. Monitor disk space regularly
3. Monitor system resources (CPU, memory)
4. Review Asterisk logs daily for errors
5. Test disaster recovery procedures monthly

### Disable Unnecessary Services

```bash
# Disable Bluetooth if not needed
sudo systemctl disable bluetooth
sudo systemctl stop bluetooth

# Disable SSH if only local access needed (advanced users only)
# sudo systemctl disable ssh
# sudo systemctl stop ssh

# Check running services
sudo systemctl list-units --type=service --state=running
```

### Documentation

Maintain documentation of:
- All user accounts and their purposes
- All extensions and their configurations
- Backup procedures and recovery steps
- Security policies and access restrictions
- Change log for all modifications
- Contact information for support

---

## System Requirements Summary

| Component | Specification |
|-----------|---------------|
| **Asterisk Version** | 22.x (latest) |
| **PHP Version** | 8.2+ |
| **MariaDB Version** | 10.5+ |
| **Apache2 Version** | 2.4+ |
| **Raspberry Pi Model** | 3B+, 4B, 5 recommended |
| **RAM Minimum** | 1GB (2GB recommended) |
| **Storage Minimum** | 16GB microSD (32GB recommended) |
| **Network** | Ethernet or WiFi |
| **Power Supply** | 5V/3A minimum |

## Running Services

After successful installation, these services should be running:

```bash
# Check all services
sudo systemctl status asterisk mariadb apache2

# Expected output:
# ● asterisk.service - Asterisk PBX
# ● mariadb.service - MariaDB database server
# ● apache2.service - Apache HTTP Server
```

## Additional Resources

- [FreePBX Official Documentation](https://wiki.freepbx.org/)
- [Asterisk Project](https://www.asterisk.org/)
- [RasPBX Project](https://www.raspbx.org/)
- [Raspberry Pi Documentation](https://www.raspberrypi.org/documentation/)
- [GitHub Repository](https://github.com/Ezra90/freepbx-quickprovisioner)

---

**Document Version:** 1.0  
**Last Updated:** 2026-01-02 04:11:06 UTC  
**Author:** FreePBX QuickProvisioner Team  
**License:** GNU General Public License v3.0

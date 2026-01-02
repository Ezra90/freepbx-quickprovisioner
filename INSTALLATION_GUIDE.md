# FreePBX QuickProvisioner Installation Guide

## Table of Contents
1. [System Requirements](#system-requirements)
2. [Installation Steps](#installation-steps)
   - [2.1 Prepare Your System](#21-prepare-your-system)
   - [2.2 Clone the Repository](#22-clone-the-repository)
   - [2.3 Install Dependencies](#23-install-dependencies)
   - [2.4 Configure FreePBX](#24-configure-freepbx)
   - [2.5 Disable WiFi and Bluetooth](#25-disable-wifi-and-bluetooth)
3. [Verification](#verification)
4. [Troubleshooting](#troubleshooting)

## System Requirements

- **Operating System**: CentOS 7+ or compatible Linux distribution
- **FreePBX**: Version 13 or higher
- **RAM**: Minimum 2GB (4GB recommended)
- **Disk Space**: Minimum 10GB free space
- **Network**: Active internet connection during installation
- **PHP**: Version 5.6 or higher
- **Asterisk**: Version 11 or higher

## Installation Steps

### 2.1 Prepare Your System

Before beginning installation, ensure your system is up to date:

```bash
sudo yum update -y
sudo yum upgrade -y
```

### 2.2 Clone the Repository

Clone the FreePBX QuickProvisioner repository:

```bash
cd /opt
sudo git clone https://github.com/Ezra90/freepbx-quickprovisioner.git
cd freepbx-quickprovisioner
```

### 2.3 Install Dependencies

Install all required dependencies:

```bash
sudo yum install -y \
    asterisk \
    asterisk-config \
    asterisk-core-sounds-en-ulaw \
    asterisk-extra-sounds-en-ulaw \
    asterisk-voicemail \
    asterisk-voicemail-imap \
    dahdi-linux \
    dahdi-tools \
    libpri \
    php \
    php-cli \
    php-pdo \
    php-mysql \
    mariadb-server \
    mariadb
```

Start the services:

```bash
sudo systemctl start mariadb
sudo systemctl start asterisk
sudo systemctl enable mariadb
sudo systemctl enable asterisk
```

### 2.4 Configure FreePBX

Run the FreePBX installation script:

```bash
sudo ./install.sh
```

Follow the on-screen prompts to complete the FreePBX configuration.

### 2.5 Disable WiFi and Bluetooth

For production environments, it is recommended to disable WiFi and Bluetooth to ensure stable network connectivity through Ethernet and reduce potential security vulnerabilities.

#### Disable WiFi

To disable WiFi on your system:

```bash
# Check available WiFi interfaces
nmcli dev show | grep GENERAL.DEVICE

# Disable WiFi adapter
sudo nmcli radio wifi off

# Verify WiFi is disabled
nmcli radio wifi
```

**Permanent Disable via Kernel Module:**

If you want to permanently disable WiFi, blacklist the WiFi driver:

```bash
echo "blacklist [wifi_driver_name]" | sudo tee -a /etc/modprobe.d/blacklist.conf
sudo dracut -f
```

#### Disable Bluetooth

To disable Bluetooth on your system:

```bash
# Check if Bluetooth service is running
sudo systemctl status bluetooth

# Disable Bluetooth service
sudo systemctl disable bluetooth
sudo systemctl stop bluetooth

# Verify Bluetooth is disabled
sudo systemctl status bluetooth
```

**Disable Bluetooth at Boot Level:**

Edit the BIOS/UEFI settings to disable Bluetooth, or blacklist the Bluetooth module:

```bash
echo "blacklist btusb" | sudo tee -a /etc/modprobe.d/blacklist.conf
sudo dracut -f
```

## Verification

After completing the installation, verify everything is working correctly:

1. **Check Asterisk Status**:
```bash
sudo systemctl status asterisk
```

2. **Check FreePBX Access**:
Open your browser and navigate to `http://[server-ip]/admin`

3. **Verify Network Configuration**:
```bash
ip addr show
nmcli conn show
```

4. **Verify Disabled Services**:
```bash
nmcli radio wifi
sudo systemctl status bluetooth
```

## Troubleshooting

### Issue: FreePBX not accessible

**Solution**: Check firewall rules and ensure port 80/443 are open:
```bash
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload
```

### Issue: Asterisk service fails to start

**Solution**: Check logs for errors:
```bash
sudo tail -f /var/log/asterisk/messages
```

### Issue: Database connection errors

**Solution**: Verify MariaDB is running:
```bash
sudo systemctl status mariadb
sudo systemctl restart mariadb
```

### Issue: Permission denied errors

**Solution**: Ensure proper permissions:
```bash
sudo chown -R asterisk:asterisk /var/lib/asterisk
sudo chown -R asterisk:asterisk /etc/asterisk
```

For additional support, please refer to the [FreePBX Documentation](https://documentation.freepbx.org) or create an issue on the GitHub repository.

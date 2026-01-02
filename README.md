# FreePBX Quick Provisioner

A quick provisioning tool for FreePBX systems, optimized for RaspBX installations.

## Installation

### Step 1: Clone the Repository

First, clone the module from GitHub directly into the FreePBX modules directory:

```bash
cd /var/www/html/admin/modules

# Clone fresh from git
git clone git@github.com:Ezra90/freepbx-quickprovisioner.git quickprovisioner
```

### Step 2: Set Correct Ownership and Permissions

```bash
# Fix ownership
# WARNING: Check for extra spaces - should be "asterisk:asterisk" with NO SPACE after colon
sudo chown -R asterisk:asterisk quickprovisioner

# Fix permissions
sudo chmod -R 755 quickprovisioner
sudo chmod -R 775 quickprovisioner/assets quickprovisioner/templates

# Create uploads directory
sudo mkdir -p quickprovisioner/assets/uploads

# Add . htaccess security files
# WARNING: Check for spaces - should be ".htaccess" with NO SPACE before "htaccess"
echo "Deny from all" | sudo tee quickprovisioner/assets/uploads/.htaccess > /dev/null
echo "Deny from all" | sudo tee quickprovisioner/templates/.htaccess > /dev/null
```

### Step 3: Restart Asterisk

This critical step prevents reload errors by recreating the Asterisk control socket: 

```bash
sudo systemctl restart asterisk

# Wait for Asterisk to fully start
sleep 15

# Verify the module is available locally
sudo fwconsole ma list | grep quickprovisioner
```

### Step 4: Install via FreePBX GUI

**Important**: Since the module is unsigned, you must install it through the FreePBX web interface:

1. Open FreePBX Admin in your browser: `https://your-raspbx-ip/admin`
2. Navigate to **Admin → Module Updates**
3. Look for **HH Quick Provisioner** in the list (it should show as "Not Installed (Locally available)")
4. Click **Install**
5. Click **Apply Config** at the top right

### Step 5: Verify Installation

After applying config: 

```bash
sudo fwconsole ma list | grep quickprovisioner
```

You should see:
```
| quickprovisioner | 2.1.0 | Enabled | GPLv3 | Unsigned |
```

The module will now appear in the FreePBX Admin interface under **Applications → HH Quick Provisioner**. 

## Why This Process? 

- **Clone from Git**: Ensures you have the latest code
- **Ownership & Permissions**: Asterisk must own the files to read them
- **Restart Asterisk**: Creates a fresh control socket for proper communication
- **GUI Installation**: Required for unsigned modules to be properly registered in FreePBX database

## Important Notes

- The module must be cloned as `quickprovisioner` (lowercase)
- Clone location: `/var/www/html/admin/modules/quickprovisioner`
- Ownership MUST be `asterisk:asterisk` with NO SPACES
- Directory permissions must be `755` for proper operation
- The `.htaccess` files protect sensitive directories from direct web access

## Troubleshooting

### "Unable to connect to remote asterisk" Error

If you see this error when applying config:
```
Unable to connect to remote asterisk (does /var/run/asterisk/asterisk.ctl exist?)
```

**Solution:**
```bash
sudo systemctl restart asterisk
sleep 15
sudo fwconsole reload
```

### Module shows "Not Installed (Locally available)"

This is normal for unsigned modules. Simply click **Install** in the Module Updates page.

### Installation fails in GUI

Verify permissions are correct: 
```bash
ls -la /var/www/html/admin/modules/quickprovisioner/
sudo fwconsole ma list | grep quickprovisioner
```

All files should be owned by `asterisk:asterisk` with at least `755` permissions.

### "Reload Complete" but still errors in GUI

Usually an Asterisk connectivity issue:
```bash
sudo systemctl status asterisk
sudo systemctl restart asterisk
sleep 15
sudo fwconsole reload --verbose
```

## Features

- Device provisioning automation
- Support for multiple phone manufacturers
- Template-based configuration
- Batch provisioning capabilities
- REST API for integration

## Usage

After successful installation, the module appears in FreePBX Admin: 

1. Go to **Applications → HH Quick Provisioner**
2. Configure your device settings
3. Deploy to target devices

## Configuration

Before running in production, update security-sensitive configuration:
- Database credentials
- Server hostnames and IP addresses
- API keys and authentication tokens
- SSL/TLS certificate paths

## Support

For issues, questions, or contributions: 
- [GitHub Repository](https://github.com/Ezra90/freepbx-quickprovisioner)
- [Issue Tracker](https://github.com/Ezra90/freepbx-quickprovisioner/issues)

## License

GPLv3 - See LICENSE file for details

## Version

Current Version: 2.1.0
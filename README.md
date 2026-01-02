# FreePBX Quick Provisioner for RaspBX

Quick provisioner module for FreePBX on RaspBX systems.

## Installation Instructions

### Clone the Repository
```bash
git clone https://github.com/Ezra90/freepbx-quickprovisioner.git /opt/freepbx/var/www/html/admin/modules/freepbx-quickprovisioner
```

### Set Permissions
```bash
chmod -R 755 /opt/freepbx/var/www/html/admin/modules/freepbx-quickprovisioner
chown -R asterisk:asterisk /opt/freepbx/var/www/html/admin/modules/freepbx-quickprovisioner
```

### Restart Asterisk
```bash
sudo systemctl restart asterisk
```

## Features

- Quick provisioning for FreePBX devices
- Optimized for RaspBX installations
- Streamlined configuration management

## Usage

After installation, the module will be available in the FreePBX Admin interface under Modules.

## Support

For issues and questions, please refer to the repository's issue tracker.

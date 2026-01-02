# FreePBX Quick Provisioner Module

A FreePBX module for rapid device provisioning and configuration management.

## Installation

1. Clone this repository into your FreePBX modules directory:
   ```bash
   cd /var/www/html/admin/modules
   git clone https://github.com/Ezra90/freepbx-quickprovisioner.git quickprovisioner
   ```

2. Navigate to the module directory:
   ```bash
   cd quickprovisioner
   ```

3. Install dependencies:
   ```bash
   composer install
   ```

4. Enable the module in FreePBX admin interface or via command line:
   ```bash
   fwconsole modules enable quickprovisioner
   ```

5. Reload FreePBX to activate the module:
   ```bash
   fwconsole reload
   ```

## Testing - Fresh Install and Uninstall Cycle

To thoroughly test the module installation and uninstall process:

### Fresh Install Testing
1. Disable the module (if enabled):
   ```bash
   fwconsole modules disable quickprovisioner
   ```

2. Remove the module directory:
   ```bash
   rm -rf /var/www/html/admin/modules/quickprovisioner
   ```

3. Clone a fresh copy:
   ```bash
   cd /var/www/html/admin/modules
   git clone https://github.com/Ezra90/freepbx-quickprovisioner.git quickprovisioner
   ```

4. Enable the module:
   ```bash
   fwconsole modules enable quickprovisioner
   ```

5. Verify module is active in FreePBX admin interface

### Uninstall Testing
1. Disable the module:
   ```bash
   fwconsole modules disable quickprovisioner
   ```

2. Remove the module directory:
   ```bash
   rm -rf /var/www/html/admin/modules/quickprovisioner
   ```

3. Verify module is no longer visible in FreePBX admin interface

4. Check system logs for any errors:
   ```bash
   tail -f /var/log/asterisk/full
   tail -f /var/log/fwconsole.log
   ```

## Usage

[Add usage instructions here]

## Configuration

[Add configuration instructions here]

## Features

- Quick device provisioning
- [Add more features as developed]

## Support

For issues or questions, please open an issue on GitHub.

## License

[Add license information]

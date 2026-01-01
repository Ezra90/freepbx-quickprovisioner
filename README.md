# FreePBX QuickProvisioner

A powerful and efficient provisioning tool for FreePBX systems, designed to streamline the deployment and configuration of VoIP devices.

## Overview

FreePBX QuickProvisioner is an automation-focused solution that simplifies the provisioning process for VoIP phones and devices within a FreePBX environment. It provides a quick, reliable way to deploy configurations across multiple devices with minimal manual intervention.

## Features

- **Rapid Device Provisioning**: Quickly deploy configurations to multiple VoIP devices simultaneously
- **Flexible Configuration Management**: Support for various device types and manufacturers
- **Automated Workflow**: Reduce manual configuration steps with intelligent automation
- **Easy Integration**: Seamlessly integrates with existing FreePBX installations
- **Configuration Templates**: Pre-built templates for common device setups
- **Error Handling**: Robust error detection and recovery mechanisms
- **Logging and Monitoring**: Detailed logs for troubleshooting and auditing

## Requirements

- FreePBX 13.0 or later
- PHP 5.6 or higher
- Web server with SSL support (recommended)
- Network connectivity to target devices
- Administrative access to FreePBX system

## Installation

1. **Download the module**:
   ```bash
   cd /usr/src/freepbx
   git clone https://github.com/Ezra90/freepbx-quickprovisioner.git
   cd freepbx-quickprovisioner
   ```

2. **Install the module**:
   ```bash
   fwconsole ma install freepbx-quickprovisioner
   ```

3. **Enable the module**:
   ```bash
   fwconsole ma enable freepbx-quickprovisioner
   ```

## Usage

### Basic Provisioning

1. Navigate to **Admin** → **QuickProvisioner** in the FreePBX web interface
2. Select your device type from the available options
3. Configure the desired settings and parameters
4. Choose target devices or device groups
5. Click **Provision** to deploy the configuration

### Configuration Templates

The tool includes pre-configured templates for popular device manufacturers:
- Cisco/Linksys
- Yealink
- Grandstream
- Polycom
- Snom

### API Usage

For programmatic access:

```php
// Example API call
$provisioner = new QuickProvisioner();
$result = $provisioner->provisionDevice([
    'device_id' => 'SIP123456',
    'template' => 'yealink_t46u',
    'options' => [
        'server' => 'pbx.example.com',
        'extension' => '100'
    ]
]);
```

## Configuration

Configuration options can be customized through:

1. **Web Interface**: Admin → QuickProvisioner → Settings
2. **Configuration File**: `/etc/asterisk/freepbx-quickprovisioner.conf`
3. **Database**: Stored in FreePBX database with `gc_` prefix

## Supported Devices

### IP Phones
- Cisco SPA5xx / 7xx / 9xx series
- Yealink T4x / T5x series
- Grandstream GXP16xx / GXP18xx series
- Polycom VVX series
- Snom D7xx / D9xx series

### Additional Devices
- Wireless phones
- Conference phones
- Mobile client integrations

## Troubleshooting

### Common Issues

**Device Not Responding**
- Verify network connectivity to the device
- Check firewall rules and port accessibility
- Ensure device IP is correctly configured

**Configuration Not Applied**
- Check device compatibility with selected template
- Verify device credentials are correct
- Review logs for detailed error messages

**Access Denied Errors**
- Confirm administrative privileges in FreePBX
- Check module permissions
- Verify FreePBX user account settings

### Logs

Access detailed logs at:
```
/var/log/asterisk/freepbx-quickprovisioner.log
```

## Advanced Features

### Batch Provisioning

Provision multiple devices simultaneously:
```bash
fwconsole ma --batch provision device_list.csv
```

### Custom Templates

Create custom provisioning templates:
1. Navigate to **Admin** → **QuickProvisioner** → **Templates**
2. Click **Add Template**
3. Configure parameters and save

### Scheduling

Schedule provisioning tasks:
1. Go to **Admin** → **QuickProvisioner** → **Scheduling**
2. Create new scheduled task with desired frequency
3. Monitor execution history

## Development

### Contributing

Contributions are welcome! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Local Development Setup

```bash
git clone https://github.com/Ezra90/freepbx-quickprovisioner.git
cd freepbx-quickprovisioner
composer install
./vendor/bin/phpunit
```

## API Reference

### Endpoints

#### GET /api/provisioner/devices
List all provisioning-capable devices

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": "SIP123456",
      "name": "Phone - John Doe",
      "type": "yealink_t46u",
      "ip_address": "192.168.1.100"
    }
  ]
}
```

#### POST /api/provisioner/provision
Provision a device

**Request:**
```json
{
  "device_id": "SIP123456",
  "template": "yealink_t46u",
  "options": {}
}
```

## Security Considerations

- Always use HTTPS for provisioning operations
- Restrict module access to authorized administrators only
- Regularly update device firmware
- Enable device authentication mechanisms
- Monitor provisioning logs for suspicious activity
- Secure backup configurations regularly

## Performance Optimization

- Provision devices during off-peak hours when possible
- Use batch provisioning for multiple devices
- Configure appropriate timeout values for your network
- Monitor system resources during large deployments

## Support

For issues, questions, or suggestions:

1. Check the [Issues](https://github.com/Ezra90/freepbx-quickprovisioner/issues) page
2. Review existing documentation
3. Contact the maintainers
4. Submit detailed bug reports with logs

## License

This project is licensed under the AGPL-3.0 License - see the LICENSE file for details.

## Changelog

### Version 1.0.0 (2026-01-01)
- Initial release
- Core provisioning functionality
- Device template support
- Web interface integration
- API endpoints

## Acknowledgments

- FreePBX Community
- Contributors and testers
- Device manufacturer support teams

## Contact

- **Maintainer**: Ezra90
- **GitHub**: https://github.com/Ezra90/freepbx-quickprovisioner
- **Issues**: https://github.com/Ezra90/freepbx-quickprovisioner/issues

---

**Last Updated**: 2026-01-01

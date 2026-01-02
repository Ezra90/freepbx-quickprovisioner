# FreePBX Quick Provisioner

A quick provisioning tool for FreePBX systems.

## Installation

### RaspBX Installation

To install FreePBX Quick Provisioner on RaspBX:

1. **Prerequisites**
   - RaspBX system with SSH access
   - Administrator privileges
   - Internet connectivity

2. **Installation Steps**
   ```bash
   # Clone the repository
   git clone https://github.com/Ezra90/freepbx-quickprovisioner.git
   cd freepbx-quickprovisioner
   
   # Run the installation script
   sudo ./install.sh
   
   # Verify installation
   freepbx-quickprovisioner --version
   ```

3. **Configuration**
   - Edit the configuration file: `/etc/freepbx-quickprovisioner/config.conf`
   - Update your FreePBX settings and device provisioning options
   - Restart the service: `sudo systemctl restart freepbx-quickprovisioner`

### Local Development Setup

To set up the project for local development:

1. **Prerequisites**
   - Git installed
   - Node.js 16+ (if applicable)
   - Python 3.8+ (if applicable)
   - Your preferred code editor

2. **Clone the Repository**
   ```bash
   git clone https://github.com/Ezra90/freepbx-quickprovisioner.git
   cd freepbx-quickprovisioner
   ```

3. **Install Dependencies**
   ```bash
   # For Node.js projects
   npm install
   
   # For Python projects
   pip install -r requirements.txt
   ```

4. **Configure Local Environment**
   ```bash
   # Copy the example configuration
   cp config.example.conf config.local.conf
   
   # Edit with your local FreePBX instance details
   nano config.local.conf
   ```

5. **Run Development Server**
   ```bash
   # For Node.js
   npm run dev
   
   # For Python
   python app.py
   ```

6. **Run Tests**
   ```bash
   npm test
   # or
   python -m pytest
   ```

## Usage

[Add usage instructions here]

## Contributing

Contributions are welcome! Please follow the coding standards and submit pull requests for review.

## License

[Specify your license here]

## Support

For issues and questions, please open an issue on the GitHub repository.

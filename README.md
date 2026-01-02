# FreePBX Quick Provisioner

A quick provisioning solution for FreePBX systems.

## Prerequisites

- FreePBX installation
- Bash shell
- Necessary permissions to execute provisioning scripts

## Installation & Setup

### Step 1: Clone the Repository

```bash
git clone https://github.com/Ezra90/freepbx-quickprovisioner.git
cd freepbx-quickprovisioner
```

### Step 2: Create Required Assets Directory

The provisioner requires an `assets` directory to store configuration files and templates.

**⚠️ WARNING: Ensure the path contains NO SPACES**

Create the assets directory:

```bash
mkdir -p assets
```

Verify the directory was created:

```bash
ls -la | grep assets
```

### Step 3: Configure Your Environment

Before running the provisioning scripts, ensure you have:

1. **Asset Files Properly Placed**: All configuration files should be in the `assets/` directory with no spaces in filenames or paths
2. **Proper Permissions**: Execute permissions on scripts:

```bash
chmod +x *.sh
```

### Step 4: Run the Provisioner

**⚠️ IMPORTANT WARNINGS ABOUT COMMAND EXECUTION:**

- **No Spaces in Paths**: Do not use paths with spaces. Example of incorrect command:
  ```bash
  # ❌ WRONG - Contains spaces in path
  /path with spaces/freepbx-quickprovisioner/provisioner.sh
  ```

- **Use Full Paths or Change Directory**: Either change to the script directory first:
  ```bash
  # ✅ CORRECT
  cd /path/to/freepbx-quickprovisioner
  ./provisioner.sh
  ```

  Or use a full path without spaces:
  ```bash
  # ✅ CORRECT
  /path/to/freepbx-quickprovisioner/provisioner.sh
  ```

- **Quote Paths with Variables**: When using variables in commands, always quote them:
  ```bash
  # ✅ CORRECT
  "$SCRIPT_DIR/provisioner.sh"
  
  # ❌ WRONG
  $SCRIPT_DIR/provisioner.sh  # May fail if path contains spaces
  ```

### Complete Setup Example

Here's a step-by-step walkthrough of the complete setup:

```bash
# 1. Navigate to your installation directory
cd /opt/freepbx-quickprovisioner

# 2. Ensure assets directory exists
mkdir -p assets

# 3. Place your configuration files in assets/
cp /path/to/config/files/* assets/

# 4. Make scripts executable
chmod +x *.sh

# 5. Run the provisioner
./provisioner.sh
```

## File Structure

```
freepbx-quickprovisioner/
├── README.md
├── provisioner.sh
├── assets/                 # Required directory for configurations
│   ├── config.conf
│   ├── templates/
│   └── other-configs/
└── other-scripts/
```

## Configuration

Place all configuration files and templates in the `assets/` directory. The provisioner will reference these files during the setup process.

### Asset File Guidelines

- Use descriptive filenames without spaces
- Keep related files in subdirectories (e.g., `assets/templates/`, `assets/configs/`)
- Ensure proper file permissions (readable by provisioner user)
- Validate syntax of configuration files before running provisioner

## Troubleshooting

### Issue: "Command not found" or script fails to run

**Solution**: Verify:
1. You're in the correct directory: `pwd` should show the provisioner path
2. Scripts have execute permissions: `ls -la` should show `x` in permissions
3. No spaces in the full path to the directory
4. You're using `./provisioner.sh` if in the directory, or full path otherwise

### Issue: Asset files not found

**Solution**:
1. Verify `assets/` directory exists: `ls -la | grep assets`
2. Verify files are in the assets directory: `ls -la assets/`
3. Check file permissions: `ls -la assets/`
4. Verify no spaces in filenames or paths

### Issue: Permission denied

**Solution**:
```bash
# Make scripts executable
chmod +x *.sh

# Ensure proper directory permissions
chmod 755 assets/
```

## Security Considerations

- Keep the assets directory secure and restricted to authorized users only
- Review all configuration files before deployment
- Use version control to track changes
- Test scripts in a non-production environment first
- Ensure proper file permissions throughout

## Contributing

For bug reports, feature requests, or contributions, please open an issue or submit a pull request.

## License

Please refer to the LICENSE file for licensing information.

## Support

For issues or questions, please open an issue on the GitHub repository.

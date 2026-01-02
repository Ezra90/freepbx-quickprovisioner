# FreePBX Quick Provisioner - Setup Guide

## Overview

This guide provides comprehensive instructions for setting up and configuring the FreePBX Quick Provisioner module. Please follow each step carefully, paying special attention to file spacing and directory structure requirements.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Installation Steps](#installation-steps)
3. [Directory Structure](#directory-structure)
4. [Assets Directory Creation](#assets-directory-creation)
5. [Configuration](#configuration)
6. [Spacing and Formatting Warnings](#spacing-and-formatting-warnings)
7. [Troubleshooting](#troubleshooting)

## Prerequisites

Before beginning the setup process, ensure you have the following:

- FreePBX 14.0 or higher installed and running
- SSH access to your FreePBX server
- Root or sudo privileges
- A text editor (nano, vi, or your preferred editor)
- Basic understanding of Linux command line
- Database access credentials (if required)

## Installation Steps

### Step 1: Clone or Download the Module

```bash
# Navigate to FreePBX modules directory
cd /var/www/html/admin/modules

# Clone the repository (replace with your repository URL)
git clone https://github.com/Ezra90/freepbx-quickprovisioner.git quickprovisioner

# Set proper permissions
chown -R asterisk:asterisk quickprovisioner
chmod -R 755 quickprovisioner
```

### Step 2: Verify File Permissions

Ensure the module directory has proper permissions:

```bash
# Check ownership
ls -ld /var/www/html/admin/modules/quickprovisioner

# Output should show:
# drwxr-xr-x asterisk asterisk /var/www/html/admin/modules/quickprovisioner
```

### Step 3: Enable the Module in FreePBX

1. Log in to your FreePBX web interface
2. Navigate to **Admin** → **Module Admin**
3. Search for "Quick Provisioner"
4. Click the **Install** button if not already installed
5. Click the **Enable** button to activate the module

### Step 4: Refresh Module Cache

```bash
# Clear the module cache
fwconsole ma refreshsignatures

# Check module status
fwconsole ma list | grep -i quickprovisioner
```

## Directory Structure

The module should have the following directory structure:

```
freepbx-quickprovisioner/
├── agi-bin/
│   └── [AGI scripts]
├── assets/
│   ├── js/
│   │   └── [JavaScript files]
│   ├── css/
│   │   └── [CSS files]
│   └── images/
│       └── [Image files]
├── views/
│   └── [View templates]
├── controllers/
│   └── [Controller files]
├── library/
│   └── [Library files]
├── i18n/
│   └── [Language files]
├── install.sql
├── uninstall.sql
├── module.xml
└── quickprovisioner.class.php
```

## Assets Directory Creation

### Complete Assets Directory Setup

Follow these steps to create and configure the assets directory structure:

#### Step 1: Create the Assets Directory

```bash
# Navigate to the module directory
cd /var/www/html/admin/modules/quickprovisioner

# Create the main assets directory
mkdir -p assets

# Set permissions
chmod 755 assets
chown asterisk:asterisk assets
```

#### Step 2: Create Subdirectories

```bash
# Create JavaScript directory
mkdir -p assets/js
chmod 755 assets/js
chown asterisk:asterisk assets/js

# Create CSS directory
mkdir -p assets/css
chmod 755 assets/css
chown asterisk:asterisk assets/css

# Create Images directory
mkdir -p assets/images
chmod 755 assets/images
chown asterisk:asterisk assets/images

# Create Additional directories (if needed)
mkdir -p assets/fonts
chmod 755 assets/fonts
chown asterisk:asterisk assets/fonts

mkdir -p assets/videos
chmod 755 assets/videos
chown asterisk:asterisk assets/videos
```

#### Step 3: Verify Directory Structure

```bash
# Display the complete assets structure
tree -L 3 /var/www/html/admin/modules/quickprovisioner/assets

# Alternative if 'tree' is not installed:
find /var/www/html/admin/modules/quickprovisioner/assets -type d | sort
```

#### Step 4: Copy Assets Files

Place your asset files in the appropriate directories:

```bash
# Copy JavaScript files (example)
cp /path/to/your/js/file.js assets/js/

# Copy CSS files (example)
cp /path/to/your/css/file.css assets/css/

# Copy image files (example)
cp /path/to/your/images/image.png assets/images/

# Set proper permissions on files
chmod 644 assets/js/*
chmod 644 assets/css/*
chmod 644 assets/images/*
```

#### Step 5: Verify Permissions

```bash
# Check all asset directories and files
ls -laR /var/www/html/admin/modules/quickprovisioner/assets/

# Expected output format:
# -rw-r--r-- asterisk asterisk assets/js/file.js
# -rw-r--r-- asterisk asterisk assets/css/file.css
# -rw-r--r-- asterisk asterisk assets/images/file.png
```

## Configuration

### Step 1: Module Configuration File

Create or edit the module configuration file:

```bash
# Navigate to module directory
cd /var/www/html/admin/modules/quickprovisioner

# Create config.php (if it doesn't exist)
touch config.php
chmod 644 config.php
chown asterisk:asterisk config.php
```

### Step 2: Database Initialization

If your module requires database tables:

```bash
# Import the database schema
mysql -u root -p freepbx < install.sql

# Verify table creation
mysql -u root -p freepbx -e "SHOW TABLES LIKE '%quickprovisioner%';"
```

### Step 3: Configure Module Settings

1. Go to **Admin** → **Quick Provisioner**
2. Configure any required settings (API keys, endpoints, etc.)
3. Save your configuration
4. Test the module functionality

## Spacing and Formatting Warnings

### ⚠️ Critical Spacing Issues

**WARNING: Proper spacing is essential for module functionality. Failure to follow these guidelines may result in module failures.**

#### File Spacing Requirements

1. **Line Endings**: All files must use Unix-style line endings (LF, not CRLF)
   ```bash
   # Check and fix line endings
   dos2unix /var/www/html/admin/modules/quickprovisioner/**/*
   
   # Or using sed:
   find /var/www/html/admin/modules/quickprovisioner -type f -exec sed -i 's/\r$//' {} \;
   ```

2. **Indentation**: Use consistent indentation (4 spaces recommended, NOT tabs)
   - PHP files: 4 spaces
   - JavaScript files: 2-4 spaces (be consistent)
   - CSS files: 2-4 spaces (be consistent)

3. **Blank Lines**: 
   - No blank lines at the end of files
   - No trailing whitespace on any line
   - Single blank line between functions/methods

4. **File Headers**: Ensure proper spacing in file headers
   ```php
   <?php
   // File header here
   // blank line below
   
   namespace modules\quickprovisioner;
   ```

#### XML Spacing Warning

The `module.xml` file is particularly sensitive to spacing issues:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!-- NO BLANK LINES BEFORE XML DECLARATION -->
<module>
  <!-- Proper indentation required -->
  <rawname>quickprovisioner</rawname>
  <name>Quick Provisioner</name>
  <!-- Ensure consistent spacing -->
</module>
```

**IMPORTANT**: The XML declaration MUST be the first line with no preceding blank lines or spaces.

#### Directory Path Spacing

When specifying directory paths in configuration:

```php
// CORRECT - No trailing slashes unless necessary
$basePath = '/var/www/html/admin/modules/quickprovisioner';

// INCORRECT - Inconsistent spacing or formatting
$basePath = '/var/www/html/admin/modules/quickprovisioner/';
```

### ⚠️ Database Query Spacing

If your module uses custom SQL queries, maintain consistent formatting:

```sql
-- CORRECT spacing
SELECT * 
FROM quickprovisioner_devices 
WHERE status = 'active' 
  AND created_date > DATE_SUB(NOW(), INTERVAL 7 DAY);

-- INCORRECT - No spacing
SELECT * FROM quickprovisioner_devices WHERE status = 'active' AND created_date > DATE_SUB(NOW(), INTERVAL 7 DAY);
```

### ⚠️ Asset File References

When referencing assets, use exact spacing and paths:

```html
<!-- CORRECT -->
<script src="/admin/modules/quickprovisioner/assets/js/module.js"></script>
<link rel="stylesheet" href="/admin/modules/quickprovisioner/assets/css/style.css">

<!-- INCORRECT - Extra spaces or incorrect paths -->
<script src="/admin/modules/quickprovisioner/assets/js/module.js "></script>
<link rel="stylesheet" href="/admin/modules/quickprovisioner/assets/css/ style.css">
```

### Validation Commands

Use these commands to verify proper spacing:

```bash
# Check for trailing whitespace
grep -r ' $' /var/www/html/admin/modules/quickprovisioner

# Check for CRLF line endings
file /var/www/html/admin/modules/quickprovisioner/module.xml

# Count lines to ensure no extra blank lines at end
tail -c 5 /var/www/html/admin/modules/quickprovisioner/module.xml | od -c
```

## Troubleshooting

### Module Not Appearing in Module Admin

**Problem**: Module shows in filesystem but not in FreePBX interface

**Solution**:
```bash
# Clear FreePBX cache
fwconsole cache --flush

# Reload module signatures
fwconsole ma refreshsignatures

# Check module.xml for spacing/syntax errors
xmllint --noout /var/www/html/admin/modules/quickprovisioner/module.xml
```

### Permission Denied Errors

**Problem**: "Permission denied" when accessing module features

**Solution**:
```bash
# Ensure proper ownership
chown -R asterisk:asterisk /var/www/html/admin/modules/quickprovisioner

# Ensure proper permissions
find /var/www/html/admin/modules/quickprovisioner -type f -exec chmod 644 {} \;
find /var/www/html/admin/modules/quickprovisioner -type d -exec chmod 755 {} \;
```

### Assets Not Loading

**Problem**: JavaScript/CSS files return 404 errors

**Solution**:
1. Verify assets directory exists:
   ```bash
   ls -la /var/www/html/admin/modules/quickprovisioner/assets/
   ```

2. Check file permissions:
   ```bash
   ls -la /var/www/html/admin/modules/quickprovisioner/assets/js/
   ```

3. Verify correct paths in HTML/PHP:
   ```bash
   grep -r "assets/" /var/www/html/admin/modules/quickprovisioner/views/
   ```

4. Check web server error logs:
   ```bash
   tail -f /var/log/httpd/error_log
   # or for nginx
   tail -f /var/log/nginx/error.log
   ```

### Database Connection Issues

**Problem**: Module reports database errors

**Solution**:
1. Verify database exists:
   ```bash
   mysql -u root -p freepbx -e "SELECT 1;"
   ```

2. Check database credentials in config:
   ```bash
   cat /var/www/html/admin/modules/quickprovisioner/config.php | grep -i database
   ```

3. Verify database tables exist:
   ```bash
   mysql -u root -p freepbx -e "SHOW TABLES LIKE '%quickprovisioner%';"
   ```

### Blank Page or White Screen

**Problem**: Module page loads but shows blank content

**Solution**:
1. Check PHP errors:
   ```bash
   tail -f /var/log/php-fpm/www-error.log
   # or
   tail -f /var/log/apache2/error.log
   ```

2. Enable debug mode in module config
3. Check for syntax errors:
   ```bash
   php -l /var/www/html/admin/modules/quickprovisioner/quickprovisioner.class.php
   ```

## Support and Additional Resources

- FreePBX Documentation: https://wiki.freepbx.org/
- Module Development Guide: https://wiki.freepbx.org/display/SUP/Module+Development
- Community Forums: https://community.freepbx.org/

## Version History

- **1.0.0** - Initial setup guide creation
  - Created: 2026-01-02
  - Comprehensive setup instructions
  - Complete assets directory creation process
  - Detailed spacing and formatting warnings
  - Troubleshooting section

---

**Last Updated**: 2026-01-02  
**Document Version**: 1.0.0  
**Status**: Active

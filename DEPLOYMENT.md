# FreePBX QuickProvisioner - Deployment Guide

This guide covers the deployment and maintenance of the QuickProvisioner module on a FreePBX system, particularly for Raspberry Pi deployments.

## Table of Contents

- [Initial Setup](#initial-setup)
- [Directory Permissions](#directory-permissions)
- [Database Initialization](#database-initialization)
- [Updating the Module](#updating-the-module)
- [Checking for Changes](#checking-for-changes)
- [Troubleshooting](#troubleshooting)

## Initial Setup

The QuickProvisioner module is deployed directly via git to the FreePBX modules directory. This method allows for easy updates and version control.

### Prerequisites

- FreePBX 13.0 or later installed
- SSH or terminal access to your FreePBX server
- Git installed on the system
- Administrative privileges (sudo access)

### Installation Steps

1. **Navigate to the FreePBX modules directory**:
   ```bash
   cd /var/www/html/admin/modules
   ```

2. **Clone the QuickProvisioner repository**:
   ```bash
   sudo git clone https://github.com/Ezra90/freepbx-quickprovisioner.git quickprovisioner
   ```

3. **Set proper ownership and permissions** (see [Directory Permissions](#directory-permissions) section below):
   ```bash
   sudo chown -R asterisk:asterisk /var/www/html/admin/modules/quickprovisioner
   sudo find /var/www/html/admin/modules/quickprovisioner -type d -exec chmod 775 {} \;
   sudo find /var/www/html/admin/modules/quickprovisioner -type f -exec chmod 664 {} \;
   ```

4. **Clear FreePBX cache** (optional but recommended):
   ```bash
   sudo fwconsole reload
   ```

5. **Access the module** through the FreePBX web interface:
   - Navigate to **Admin** → **Modules** → **Check Online**
   - The QuickProvisioner module should appear in your module list
   - Access it via **Admin** → **HH Quick Provisioner**

## Directory Permissions

The QuickProvisioner module requires specific permissions to function correctly. The web server (running as the `asterisk` user in FreePBX) needs to be able to read and write to certain directories.

### Required Permissions

| Path | Owner:Group | Directory Permissions | File Permissions | Purpose |
|------|-------------|----------------------|------------------|---------|
| `/var/www/html/admin/modules/quickprovisioner` | asterisk:asterisk | 775 | 664 | Module root |
| `/var/www/html/admin/modules/quickprovisioner/assets` | asterisk:asterisk | 775 | 664 | Asset storage |
| `/var/www/html/admin/modules/quickprovisioner/assets/uploads` | asterisk:asterisk | 775 | 664 | User uploaded wallpapers/photos |
| `/var/www/html/admin/modules/quickprovisioner/templates` | asterisk:asterisk | 775 | 664 | Device templates |

### Setting Permissions Manually

If you need to reset permissions after updates or changes:

```bash
# Set ownership to asterisk user
sudo chown -R asterisk:asterisk /var/www/html/admin/modules/quickprovisioner

# Set directory permissions (775 = rwxrwxr-x)
sudo find /var/www/html/admin/modules/quickprovisioner -type d -exec chmod 775 {} \;

# Set file permissions (664 = rw-rw-r--)
sudo find /var/www/html/admin/modules/quickprovisioner -type f -exec chmod 664 {} \;
```

### Permission Explanation

- **775 for directories**: Owner and group can read, write, and execute; others can read and execute
- **664 for files**: Owner and group can read and write; others can read only
- **asterisk:asterisk**: FreePBX runs as the asterisk user, which needs full access

## Database Initialization

The QuickProvisioner module automatically creates required database tables on first access. No manual database setup is needed.

### Database Tables

The module creates the following table:

- `quickprovisioner_devices`: Stores device configurations, MAC addresses, extensions, wallpapers, security PINs, programmable keys, contacts, and custom options

### Automatic Initialization

When you first access the QuickProvisioner module through the FreePBX web interface:

1. The `install.php` script runs automatically
2. Database tables are created if they don't exist
3. Required directories (`assets`, `assets/uploads`, `templates`) are created
4. Default device templates are installed if none exist
5. Security `.htaccess` files are created to protect sensitive directories

### Manual Database Reset

If you need to reset the database (this will delete all device configurations):

```bash
# Connect to MySQL as root
mysql -u root -p

# Select the FreePBX database
USE asterisk;

# Drop the table
DROP TABLE IF EXISTS quickprovisioner_devices;

# Exit MySQL
EXIT;
```

Then access the QuickProvisioner module through the web interface to reinitialize.

## Updating the Module

When updates are available from the GitHub repository, you can easily pull them using git.

### Update Steps

1. **Check current status before updating**:
   ```bash
   cd /var/www/html/admin/modules/quickprovisioner
   sudo git status
   sudo git diff
   ```

2. **Stash any local changes** (if you've made customizations):
   ```bash
   sudo git stash
   ```

3. **Pull the latest updates**:
   ```bash
   sudo git pull origin main
   ```

4. **Restore your local changes** (if you stashed them):
   ```bash
   sudo git stash pop
   ```

5. **Fix permissions** (if needed):
   ```bash
   sudo chown -R asterisk:asterisk /var/www/html/admin/modules/quickprovisioner
   sudo find /var/www/html/admin/modules/quickprovisioner -type d -exec chmod 775 {} \;
   sudo find /var/www/html/admin/modules/quickprovisioner -type f -exec chmod 664 {} \;
   ```

6. **Clear FreePBX cache**:
   ```bash
   sudo fwconsole reload
   ```

### Update Frequency

- Check for updates regularly (monthly recommended)
- Subscribe to repository notifications on GitHub for important updates
- Test updates on a development system before applying to production

## Checking for Changes

Before updating or to see what has changed, use these git commands:

### Check Status

See which files have been modified locally:

```bash
cd /var/www/html/admin/modules/quickprovisioner
sudo git status
```

### View Differences

See detailed changes in tracked files:

```bash
# View all changes
sudo git diff

# View changes in a specific file
sudo git diff path/to/file.php

# View changes compared to remote
sudo git fetch origin
sudo git diff origin/main
```

### View Commit History

See recent changes from the repository:

```bash
# View last 10 commits
sudo git log --oneline -n 10

# View detailed changes in a specific commit
sudo git show <commit-hash>
```

### Check Remote Updates

See if updates are available without pulling them:

```bash
# Fetch remote changes (doesn't modify local files)
sudo git fetch origin

# See commits that are available to pull
sudo git log HEAD..origin/main --oneline

# See summary of changes
sudo git diff --stat HEAD origin/main
```

## Troubleshooting

### Common Issues and Solutions

#### Module Not Appearing in FreePBX

**Symptoms**: QuickProvisioner doesn't show in the Admin menu

**Solutions**:
1. Verify the module is in the correct location:
   ```bash
   ls -la /var/www/html/admin/modules/quickprovisioner
   ```

2. Check that `module.xml` exists:
   ```bash
   cat /var/www/html/admin/modules/quickprovisioner/module.xml
   ```

3. Clear FreePBX cache:
   ```bash
   sudo fwconsole reload
   sudo fwconsole ma refreshsignatures
   ```

#### Permission Denied Errors

**Symptoms**: "Permission denied" errors when uploading wallpapers or saving configurations

**Solutions**:
1. Check directory ownership:
   ```bash
   ls -la /var/www/html/admin/modules/quickprovisioner/assets/uploads
   ```

2. Reset permissions:
   ```bash
   sudo chown -R asterisk:asterisk /var/www/html/admin/modules/quickprovisioner
   sudo chmod -R 775 /var/www/html/admin/modules/quickprovisioner/assets/uploads
   ```

3. Verify the asterisk user exists:
   ```bash
   id asterisk
   ```

#### Database Errors

**Symptoms**: "Table doesn't exist" or database connection errors

**Solutions**:
1. Verify MySQL is running:
   ```bash
   sudo systemctl status mysql
   # or
   sudo systemctl status mariadb
   ```

2. Check FreePBX database connection:
   ```bash
   mysql -u freepbxuser -p asterisk
   ```

3. Manually trigger installation by accessing the module through the web interface

#### Git Update Conflicts

**Symptoms**: "error: Your local changes to the following files would be overwritten by merge"

**Solutions**:
1. View what changed:
   ```bash
   sudo git diff
   ```

2. Save your changes:
   ```bash
   sudo git stash
   ```

3. Pull updates:
   ```bash
   sudo git pull
   ```

4. Restore your changes:
   ```bash
   sudo git stash pop
   ```

5. If conflicts occur, resolve them manually and run:
   ```bash
   sudo git add .
   sudo git commit -m "Resolved merge conflicts"
   ```

#### Module Not Working After Update

**Symptoms**: Module stops working after git pull

**Solutions**:
1. Check error logs:
   ```bash
   sudo tail -f /var/log/asterisk/full
   sudo tail -f /var/log/apache2/error.log
   # or
   sudo tail -f /var/log/httpd/error_log
   ```

2. Verify PHP syntax:
   ```bash
   php -l /var/www/html/admin/modules/quickprovisioner/install.php
   ```

3. Reset to previous version:
   ```bash
   sudo git log --oneline -n 5
   sudo git reset --hard <previous-commit-hash>
   ```

### Getting Help

If you encounter issues not covered here:

1. Check the [GitHub Issues](https://github.com/Ezra90/freepbx-quickprovisioner/issues) page
2. Search FreePBX forums for similar problems
3. Create a new issue on GitHub with:
   - FreePBX version
   - PHP version
   - Error messages from logs
   - Steps to reproduce the issue

## Best Practices

### Backup Before Updates

Always backup your configuration before updating:

```bash
# Backup the entire module directory
sudo tar -czf quickprovisioner-backup-$(date +%Y%m%d).tar.gz /var/www/html/admin/modules/quickprovisioner

# Backup just the database
mysqldump -u root -p asterisk quickprovisioner_devices > quickprovisioner-db-backup-$(date +%Y%m%d).sql
```

### Regular Maintenance

- Check for updates monthly
- Review permissions quarterly
- Backup configurations before major updates
- Test updates in development before production
- Document any custom modifications

### Security Considerations

- Keep the module updated to receive security fixes
- Ensure `.htaccess` files are present in `assets/uploads` and `templates` directories
- Use strong security PINs for device lock features
- Restrict SSH access to trusted administrators only
- Monitor logs for unusual activity

## Additional Resources

- **Main README**: [README.md](README.md)
- **GitHub Repository**: https://github.com/Ezra90/freepbx-quickprovisioner
- **FreePBX Documentation**: https://wiki.freepbx.org/
- **License**: AGPL-3.0 (see [LICENSE](LICENSE))

---

**Last Updated**: 2026-01-02

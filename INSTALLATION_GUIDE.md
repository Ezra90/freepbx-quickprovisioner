# INSTALLATION GUIDE

This document details the installation of the FreePBX Quick Provisioner.

## Step 1: Download

Download the latest release from the [official GitHub Releases page](https://github.com/Ezra90/freepbx-quickprovisioner/releases).

## Step 2: Prepare your environment

1. Ensure your device is up-to-date using:
   ```
sudo apt-get update
sudo apt-get upgrade
   ```
2. Install necessary dependencies:
   ```
sudo apt-get install -y git curl
   ```

## Step 3: Clone the Repository

Clone the repository using:
```
git clone https://github.com/Ezra90/freepbx-quickprovisioner.git
```

## Step 4: Configure device settings

Disable the onboard WiFi and Bluetooth by modifying the `/boot/firmware/config.txt` file. Add the following lines:
```
dtoverlay=disable-wifi
dtoverlay=disable-bt
```

## Step 5: Run the Provisioner

1. Navigate to the project folder:
   ```
cd freepbx-quickprovisioner
   ```
2. Run the installer script:
   ```
./install.sh
   ```

For more information, refer to the [official documentation](https://github.com/Ezra90/freepbx-quickprovisioner/wiki).

---

For issues, please use the [GitHub Issues page](https://github.com/Ezra90/freepbx-quickprovisioner/issues).
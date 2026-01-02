# FreePBX QuickProvisioner Installation Guide

## Overview
This guide provides step-by-step instructions for installing and configuring FreePBX QuickProvisioner on RasPBX systems.

## Windows Preparation

> **Note:** This section is only needed for initial RasPBX USB/SD card flashing on Windows. If you are setting up FreePBX QuickProvisioner on an already-running RasPBX system, you can skip to the [RasPBX Setup](#raspbx-setup) section.

### Requirements
- Windows 7 or later
- Administrator access
- USB flash drive (minimum 8GB) or SD card
- RasPBX image file

### Steps

1. **Download Balena Etcher**
   - Visit [balena.io/etcher](https://www.balena.io/etcher/)
   - Download and install the Windows version

2. **Obtain RasPBX Image**
   - Download the latest RasPBX image from the official repository
   - Extract the .zip file to get the .img file

3. **Flash the Image**
   - Insert your USB drive or SD card into your computer
   - Open Balena Etcher
   - Click "Select Image" and choose the RasPBX .img file
   - Click "Select Target" and choose your USB drive or SD card
   - Click "Flash" and wait for completion
   - Eject the drive safely

4. **Insert into Raspberry Pi**
   - Power off your Raspberry Pi
   - Insert the flashed USB drive or SD card
   - Power on the device

## RasPBX Setup

### Initial Configuration
1. Connect to your Raspberry Pi via SSH
2. Run the initial setup script
3. Follow the on-screen prompts

### Installing FreePBX QuickProvisioner
1. SSH into your RasPBX system
2. Clone the repository:
   ```bash
   git clone https://github.com/Ezra90/freepbx-quickprovisioner.git
   ```
3. Navigate to the directory:
   ```bash
   cd freepbx-quickprovisioner
   ```
4. Run the installation script:
   ```bash
   sudo ./install.sh
   ```

## Configuration

Follow the configuration prompts to set up your provisioning parameters.

## Troubleshooting

For common issues, refer to the README.md file or open an issue on GitHub.

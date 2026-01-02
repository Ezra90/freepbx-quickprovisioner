# INSTALLATION GUIDE

## Step 1: Preparing the Raspberry Pi
...existing content...

## Step 4: Configuring firmware settings
To disable both Bluetooth and WiFi on your Raspberry Pi, edit the `/boot/firmware/config.txt` file.
Add the following lines to the end of the file:
```
dtoverlay=disable-wifi
dtoverlay=disable-bt
```
This will disable both wireless functionalities when the system boots next time.
...remaining content...

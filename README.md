Cloud VMU
=========
Cloud VMU is a self hosted VMU file upload/download PHP web app designed specifically for use by the legacy Sega Dreamcast Web Browser. You can host it locally or on a remote PHP server (like Hostgator). You can also host it locally on a DreamPi and access it via http://vmu.local.

# DreamPi Setup (manual)

## 1. SSH into Your DreamPi
Make sure DreamPi is powered on and connected to your network. Then, SSH into it:

```bash
ssh pi@dreampi.local
(Default password: raspberry unless changed)
```

If that doesn't work, find the IP address of DreamPi (ifconfig on another Pi or check your router) and connect via:

```bash
ssh pi@<DreamPi-IP>
```

### 2. Install Apache and PHP
Since DreamPi is based on Raspberry Pi OS (Debian-based), install a web server:


```bash
sudo nano /etc/apt/sources.list
```

Replace the existing line with the following:

```bash
deb http://legacy.raspbian.org/raspbian stretch main contrib non-free rpi
```

Then, update your package list and install the required packages:

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y apache2 php libapache2-mod-php imagemagick php-gd

```

Once installed, enable and start the web server:

```bash
sudo systemctl enable apache2
sudo systemctl start apache2
```

## 3. Deploy the Cloud VMU Web App
Now, move the Cloud VMU PHP web app to the right directory:

```bash
sudo mkdir -p /var/www/vmu
cd /var/www/vmu
```

#### If You Have a Git Repo for Your App
Clone it directly:

```bash
git clone https://github.com/robertdalesmith/dreamcast-cloud-vmu.git /var/www/vmu
```

#### If You Need to Manually Copy Files
If your PHP app is on your computer, you can use SCP to copy it over:

```bash
scp -r /path/to/your-vmu-app pi@dreampi.local:/var/www/vmu
```

Then, set proper permissions:
```bash
sudo chown -R www-data:www-data /var/www/vmu
sudo chmod -R 755 /var/www/vmu
```

## 4. Configure Apache to Serve `vmu.local`
Create a new Apache config file:

```bash
sudo nano /etc/apache2/sites-available/vmu.conf
```

Paste the following:

```bash
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/vmu
    ServerName vmu.local

    <Directory /var/www/vmu>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
```

Save and exit (`CTRL + X`, then `Y`, then `Enter`).

### Enable the Site and Restart Apache

```bash
sudo a2ensite vmu.conf
sudo systemctl restart apache2
```

## 5. Set Up `dnsmasq` for `vmu.local`

DreamPi already uses dnsmasq, so we just need to add a local entry.

### Edit `dnsmasq.conf`

```bash
sudo nano /etc/dnsmasq.conf
```

Add this line at the bottom with your DreamPi's actual IP address:

```bash
address=/vmu.local/<DreamPi-IP>
```

### Restart dnsmasq

```bash
sudo systemctl restart dnsmasq
```

### Add `vmu.local` to `/etc/hosts`

```bash
sudo nano /etc/hosts
```

Add this line to the end of the file:

```bash
127.0.0.1 vmu.local
```

Save and exit (`CTRL + X`, then `Y`, then `Enter`).

## 6. Test on Dreamcast
Now, boot up your Dreamcast, open the Dreamcast Web Browser, and try to access:

```bash
http://vmu.local
```

You should see the Cloud VMU web app.

or

```bash
http://<DreamPi-IP>
```

## 7. (Optional) Auto update DreamPi IP in dnsmasq
If you want to auto update the DreamPi IP in dnsmasq, you can add the following script to `rc.local`:

```bash
sudo nano /usr/local/bin/update-dnsmasq.sh
```

Add the following:

```bash
#!/bin/bash

# Get the current DreamPi IP
DREAMPi_IP=$(hostname -I | awk '{print $1}')

# Check if the line already exists
if grep -q "address=/vmu.local/" /etc/dnsmasq.conf; then
    # Replace the old IP with the new one
    sudo sed -i "s|address=/vmu.local/.*|address=/vmu.local/$DREAMPi_IP|" /etc/dnsmasq.conf
else
    # If no existing entry, add it
    echo "address=/vmu.local/$DREAMPi_IP" | sudo tee -a /etc/dnsmasq.conf
fi

# Restart dnsmasq to apply changes
sudo systemctl restart dnsmasq
```

### Add the script to `rc.local`

```bash
sudo nano /etc/rc.local
```

Add this line to the end of the file right before `exit 0`:

```bash
/usr/local/bin/update-dnsmasq.sh
```

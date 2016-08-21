#!/usr/bin/env bash
#Title : NoTrack Installer
#Description : This script will install NoTrack and then configure dnsmasq and lighttpd
#Authors : QuidsUp, floturcocantsee, rchard2scout, fernfrost
#Usage : bash install.sh


#######################################
# Importing utilities
#######################################
. core.sh


#Optional user customisable settings---------------------------------
NETWORK_DEVICE=""
IP_VERSION=""
InstallLoc=""

#Program Settings----------------------------------------------------
Version="0.7.14"
DNS_SERVER_1=""
DNS_SERVER_2=""
SudoRequired=0                                   #1 If installing to /opt

#Welcome Dialog------------------------------------------------------
Show_Welcome() {
  echo "Welcome to NoTrack v$Version"
  echo
  echo "This installer will transform your system into a network-wide Tracker Blocker"
  echo "Install Guide: https://youtu.be/MHsrdGT5DzE"
  echo
  echo "Press any key to contine..."
  read -rn1
  
  
  menu "Initating Network Interface\nNoTrack is a SERVER, therefore it needs a STATIC IP ADDRESS to function properly.\n\nHow to set a Static IP on Linux Server: https://youtu.be/vIgTmFu-puo" "Ok" "Cancel" 
  if [ $? == 2 ]; then                           #Abort install if user selected no
    error_exit "Aborting Install" 1
  fi
}

#Finish Dialog-------------------------------------------------------
Show_Finish() {
  echo "NoTrack Install Complete"
  echo "Access the admin console at http://$(hostname)/admin"
  echo
}

#Ask Install Location------------------------------------------------
Ask_InstallLoc() {
  local HomeLoc="${HOME}"
  
  if [[ $HomeLoc == "/root" ]]; then      #Change root folder to users folder
    HomeLoc="$(getent passwd $SUDO_USER | grep /home | grep -v syslog | cut -d: -f6)"    
    if [ $(wc -w <<< "$HomeLoc") -gt 1 ]; then   #Too many sudo users
      echo "Unable to estabilish which Home folder to install to"
      echo "Either run this installer without using sudo / root, or manually set the \$InstallLoc variable"
      echo "\$InstallLoc=\"/home/you/NoTrack\""
      exit 15
    fi    
  fi
  
  menu "Select Install Folder" "Home $HomeLoc" "Opt /opt" "Cancel"
  
  case $? in
    1) 
      InstallLoc="$HomeLoc/notrack" 
    ;;
    2) 
      InstallLoc="/opt/notrack"
      SudoRequired=1
    ;;
    3)
      error_exit "Aborting Install" 1
    ;;  
  esac
  
  if [[ $InstallLoc == "" ]]; then
    error_exit "Install folder not set" 15
  fi  
}

#Install Packages----------------------------------------------------
Install_Deb() {
  local PHPVersion="php5"
  local PHPMemcache="php5-memcache"
  
  echo "Checking to see if PHP 7.0 is available"
  apt-cache show php7.0 &> /dev/null             #Search apt-cache and dump output to /dev/null
  if [ $? == 0 ]; then                           #Closure code of zero indicates package is available
    echo "Installing PHP 7.0"
    PHPVersion="php7.0"
    PHPMemcache="php-memcache"                   #Believe version number has now been dropped as of PHP v7
  else
    echo "Installing PHP 5"
  fi
  
  echo
  echo "Preparing to Install Deb Packages..."
  sleep 2s
  #sudo apt-get update
  #echo
  echo "Installing dependencies"
  sleep 2s
  sudo apt-get -y install unzip
  echo
  echo "Installing Dnsmasq"
  sleep 2s
  sudo apt-get -y install dnsmasq
  echo
  echo "Installing Lighttpd and PHP"
  sleep 2s
  sudo apt-get -y install lighttpd memcached "$PHPMemcache" "$PHPVersion-cgi" "$PHPVersion-curl"
  echo
  echo "Restarting Lighttpd"
  sudo service lighttpd restart
}
#--------------------------------------------------------------------
Install_Dnf() {
  echo "Preparing to Install RPM packages using Dnf..."
  sleep 2s
  sudo dnf update
  echo
  echo "Installing dependencies"
  sleep 2s
  sudo dnf -y install unzip
  echo
  echo "Installing Dnsmasq"
  sleep 2s
  sudo dnf -y install dnsmasq
  echo
  echo "Installing Lighttpd and PHP"
  sleep 2s
  sudo dnf -y install lighttpd memcached php-pecl-memcached php
  echo
}
#--------------------------------------------------------------------
Install_Pacman() {
  echo "Preparing to Install Arch Packages..."
  sleep 2s
  echo
  echo "Installing dependencies"
  sleep 2s
  sudo pacman -S --noconfirm unzip
  echo
  echo "Installing Dnsmasq"
  sleep 2s
  sudo pacman -S --noconfirm dnsmasq
  echo
  echo "Installing Lighttpd and PHP"
  sleep 2s
  sudo pacman -S --noconfirm lighttpd php memcached php-memcache php-cgi 
  #Curl is also needed, but I have written the PHP code to only use Curl if its installed
  echo  
}
#--------------------------------------------------------------------
Install_Yum() {
  echo "Preparing to Install RPM packages using Yum..."
  sleep 2s
  sudo yum update
  echo
  echo "Installing dependencies"
  sleep 2s
  sudo yum -y install unzip
  echo
  echo "Installing Dnsmasq"
  sleep 2s
  sudo yum -y install dnsmasq
  echo
  echo "Installing Lighttpd and PHP"
  sleep 2s
  sudo yum -y install lighttpd php memcached php-pecl-memcached
  echo
}
#--------------------------------------------------------------------
Install_Apk() {
  echo "Preparing to install packages using Apk..."
  sleep 2s
  sudo apk update
  echo
  echo "Installing dependencies"
  sleep 2s
  sudo apk add unzip
  echo
  echo "Installing Dnsmasq"
  sleep 2s
  sudo apk add dnsmasq
  echo
  echo "Installing Lighttpd and PHP"
  sudo apk add lighttpd php5 memcached                  #Having issues here
  echo
}
#--------------------------------------------------------------------
Install_Packages() {
  if [ "$(command -v apt-get)" ]; then Install_Deb
  elif [ "$(command -v dnf)" ]; then Install_Dnf
  elif [ "$(command -v yum)" ]; then Install_Yum  
  elif [ "$(command -v pacman)" ]; then Install_Pacman
  elif [ "$(command -v apk)"]; then Install_Apk
  else 
    echo "Unable to work out which package manage is being used."
    echo "Ensure you have the following packages installed:"
    echo -e "\tdnsmasq"
    echo -e "\tlighttpd"
    echo -e "\tphp-cgi"
    echo -e "\tphp-curl"
    echo -e "\tmemcached"
    echo -e "\tphp-memcache"
    echo -e "\tunzip"
    echo
    echo -en "Press any key to continue... "
    read -rn1
    echo
  fi
}
#Backup Configs------------------------------------------------------
Backup_Conf() {
  echo "Backing up old config files"
  
  echo "Copying /etc/dnsmasq.conf to /etc/dnsmasq.conf.old"
  check_file_exists "/etc/dnsmasq.conf" 24
  sudo cp /etc/dnsmasq.conf /etc/dnsmasq.conf.old
  
  echo "Copying /etc/lighttpd/lighttpd.conf to /etc/lighttpd/lighttpd.conf.old"
  
  check_file_exists "/etc/lighttpd/lighttpd.conf" 24
  sudo cp /etc/lighttpd/lighttpd.conf /etc/lighttpd/lighttpd.conf.old
  echo
}
#Download With Git---------------------------------------------------
Download_WithGit() {
  #Download with Git if the user has it installed on their system
  echo "Downloading NoTrack using Git"
  if [ $SudoRequired == 0 ]; then
    git clone --depth=1 https://github.com/quidsup/notrack.git "$InstallLoc"
  else
    sudo git clone --depth=1 https://github.com/quidsup/notrack.git "$InstallLoc"
  fi
  echo
}

#Download WithWget---------------------------------------------------
Download_WithWget() {
  #Alternative download with wget 
  if [ -d $InstallLoc ]; then                    #Check if NoTrack folder exists
    echo "NoTrack folder exists. Skipping download"
  else
    echo "Downloading latest version of NoTrack from github"
    wget https://github.com/quidsup/notrack/archive/master.zip -O /tmp/notrack-master.zip
    if [ ! -e /tmp/notrack-master.zip ]; then    #Check again to see if download was successful
      echo "Error Download from github has failed"
      exit 23                                    #Abort we can't go any further without any code from git
    fi  

    unzip -oq /tmp/notrack-master.zip -d /tmp
    if [ $SudoRequired == 0 ]; then
      mv /tmp/notrack-master "$InstallLoc"
    else
      sudo mv /tmp/notrack-master "$InstallLoc"
    fi
    rm /tmp/notrack-master.zip                  #Cleanup
  fi
  
  sudo chown "$(whoami)":"$(whoami)" -hR "$InstallLoc"
}
#Setup Dnsmasq-------------------------------------------------------
Setup_Dnsmasq() {  
  local HostName=""
  if [ -e /etc/sysconfig/network ]; then
    HostName=$(cat /etc/sysconfig/network | grep "HOSTNAME" | cut -d "=" -f 2 | tr -d [[:space:]])
  elif [ -e /etc/hostname ]; then
    HostName=$(cat /etc/hostname)
  else
    echo "Warning. Unable to find hostname"
  fi
  
  #Copy config files modified for NoTrack
  echo "Copying config files from $InstallLoc to /etc/"
  check_file_exists "$InstallLoc/conf/dnsmasq.conf" 24
  sudo cp "$InstallLoc/conf/dnsmasq.conf" /etc/dnsmasq.conf
  
  check_file_exists "$InstallLoc/conf/lighttpd.conf" 24
  sudo cp "$InstallLoc/conf/lighttpd.conf" /etc/lighttpd/lighttpd.conf
  
  #Finish configuration of dnsmasq config
  echo "Setting DNS Servers in /etc/dnsmasq.conf"
  sudo sed -i "s/server=changeme1/server=$DNS_SERVER_1/" /etc/dnsmasq.conf
  sudo sed -i "s/server=changeme2/server=$DNS_SERVER_2/" /etc/dnsmasq.conf
  sudo sed -i "s/interface=eth0/interface=$NETWORK_DEVICE/" /etc/dnsmasq.conf
  echo "Creating file /etc/localhosts.list for Local Hosts"
  sudo touch /etc/localhosts.list                #File for user to add DNS entries for their network
  if [[ $HostName != "" ]]; then
    echo "Writing first entry for this system: $IP_ADDRESS - $HostName"
    echo -e "$IP_ADDRESS\t$HostName" | sudo tee -a /etc/localhosts.list #First entry is this system
  fi
    
  #Setup Log rotation for dnsmasq
  echo "Copying log rotation script for Dnsmasq"
  check_file_exists "$InstallLoc/conf/logrotate.txt" 24
  sudo cp "$InstallLoc/conf/logrotate.txt" /etc/logrotate.d/logrotate.txt
  sudo mv /etc/logrotate.d/logrotate.txt /etc/logrotate.d/notrack
  
  if [ ! -d "/var/log/notrack/" ]; then          #Check /var/log/notrack/ folder
    echo "Creating folder: /var/log/notrack/"
    sudo mkdir /var/log/notrack/
  fi
  sudo touch /var/log/notrack.log                #Create log file for Dnsmasq
  sudo chmod 664 /var/log/notrack.log            #Dnsmasq sometimes defaults to permissions 774
  echo "Setup of Dnsmasq complete"
  echo
}

#Setup Lighttpd------------------------------------------------------
Setup_Lighttpd() {
  local SudoCheck=""

  echo "Configuring Lighttpd"
  sudo usermod -a -G www-data "$(whoami)"        #Add www-data group rights to current user
  sudo lighty-enable-mod fastcgi fastcgi-php
  
    
  if [ ! -d /var/www/html ]; then                #www/html folder will get created by Lighttpd install
    echo "Creating Web folder: /var/www/html"
    sudo mkdir -p /var/www/html                  #Create the folder for now incase installer failed
  fi
  
  if [ -d /var/www/html/sink ]; then             #Remove Sink folder
    echo "Removing old folder: /var/www/html/sink"
    sudo rm -r /var/www/html/sink
  fi
  if [ -e /var/www/html/admin ]; then            #Remove old symlinks
    echo "Removing old file: /var/www/html/admin"
    sudo rm /var/www/html/admin
  fi
  echo "Creating Sink Folder"
  sudo mkdir /var/www/html/sink
  echo '<img src="data:image/gif;base64,R0lGODlhAQABAAAAACwAAAAAAQABAAA=" alt="" />' | sudo tee /var/www/html/sink/index.html &> /dev/null
  echo "Changing ownership of sink folder to www-data"
  sudo chown -hR www-data:www-data /var/www/html/sink
  sudo chmod -R 775 /var/www/html/sink
  echo 'Setting Block message to 1x1 pixel'
    
  echo "Creating symlink from $InstallLoc/admin to /var/www/html/admin"
  sudo ln -s "$InstallLoc/admin" /var/www/html/admin
  sudo chmod -R 775 /var/www/html                #Give read/write/execute privilages to Web folder
  
  SudoCheck=$(sudo cat /etc/sudoers | grep www-data)
  if [[ $SudoCheck == "" ]]; then
    echo "Adding NoPassword permissions for www-data to execute script /usr/local/sbin/ntrk-exec as root"
    echo -e "www-data\tALL=(ALL:ALL) NOPASSWD: /usr/local/sbin/ntrk-exec" | sudo tee -a /etc/sudoers
    echo
  fi  
  
  echo "Setup of Lighttpd complete"
  echo
}

#Setup Notrack-------------------------------------------------------
Setup_NoTrack() {
  #Setup Tracker list downloader
  echo "Setting up NoTrack block list downloader"
  
  check_file_exists "$InstallLoc/notrack.sh" 25
  sudo cp "$InstallLoc/notrack.sh" /usr/local/sbin/notrack.sh
  sudo mv /usr/local/sbin/notrack.sh /usr/local/sbin/notrack #Cron jobs will only execute on files Without extensions
  sudo chmod +x /usr/local/sbin/notrack          #Make NoTrack Script executable
  
  check_file_exists "$InstallLoc/dns-log-archive.sh" 24
  sudo cp "$InstallLoc/dns-log-archive.sh" /usr/local/sbin/dns-log-archive.sh
  sudo mv /usr/local/sbin/dns-log-archive.sh /usr/local/sbin/dns-log-archive
  sudo chmod +x /usr/local/sbin/dns-log-archive
  
  echo "Creating daily cron job in /etc/cron.daily/"
  if [ -e /etc/cron.daily/notrack ]; then        #Remove old symlink
    echo "Removing old file: /etc/cron.daily/notrack"
    sudo rm /etc/cron.daily/notrack
  fi
  #Create cron daily job with a symlink to notrack script
  sudo ln -s /usr/local/sbin/notrack /etc/cron.daily/notrack
    
  if [ ! -d "/etc/notrack" ]; then               #Check /etc/notrack folder exists
    echo "Creating folder: /etc/notrack"
    sudo mkdir "/etc/notrack"
  fi
  
  if [ -e /etc/notrack/notrack.conf ]; then      #Remove old config file
    echo "Removing old file: /etc/notrack/notrack.conf"
    sudo rm /etc/notrack/notrack.conf
  fi
  echo "Creating NoTrack config file: /etc/notrack/notrack.conf"
  sudo touch /etc/notrack/notrack.conf          #Create Config file
  echo "Writing initial config"
  echo "IPVersion = $IP_VERSION" | sudo tee /etc/notrack/notrack.conf
  echo "NetDev = $NETWORK_DEVICE" | sudo tee -a /etc/notrack/notrack.conf
  echo
}
#Ntrk Scripts--------------------------------------------------------
Setup_NtrkScripts() {
  check_file_exists "$InstallLoc/ntrk-exec.sh" 26
  echo "Copying ntrk-exec.sh"
  sudo cp "$InstallLoc/ntrk-exec.sh" /usr/local/sbin/
  sudo mv /usr/local/sbin/ntrk-exec.sh /usr/local/sbin/ntrk-exec
  sudo chmod 755 /usr/local/sbin/ntrk-exec
  
  check_file_exists "$InstallLoc/ntrk-pause.sh" 27
  echo "Copying ntrk-pause.sh"
  sudo cp "$InstallLoc/ntrk-pause.sh" /usr/local/sbin/
  sudo mv /usr/local/sbin/ntrk-pause.sh /usr/local/sbin/ntrk-pause
  sudo chmod 755 /usr/local/sbin/ntrk-pause
  
  check_file_exists "$InstallLoc/ntrk-upgrade.sh" 28
  echo "Copying ntrk-upgrade.sh"
  sudo cp "$InstallLoc/ntrk-upgrade.sh" /usr/local/sbin/
  sudo mv /usr/local/sbin/ntrk-upgrade.sh /usr/local/sbin/ntrk-upgrade
  sudo chmod 755 /usr/local/sbin/ntrk-upgrade
}

#FirewallD-----------------------------------------------------------
Setup_FirewallD() {
  #Configure FirewallD to Work With Dnsmasq
  echo "Creating Firewall Rules Using FirewallD"
  
  if [[ $(sudo firewall-cmd --query-service=dns) == "yes" ]]; then
    echo "Firewall rule DNS already exists! Skipping..."
  else
    echo "Firewall rule DNS has been added"
    sudo firewall-cmd --permanent --add-service=dns    #Add firewall rule for dns connections
  fi
    
  #Configure FirewallD to Work With Lighttpd
  if [[ $(sudo firewall-cmd --query-service=http) == "yes" ]]; then
    echo "Firewall rule HTTP already exists! Skipping..."
  else
    echo "Firewall rule HTTP has been added"
    sudo firewall-cmd --permanent --add-service=http    #Add firewall rule for http connections
  fi

  if [[ $(sudo firewall-cmd --query-service=https) == "yes" ]]; then
    echo "Firewall rule HTTPS already exists! Skipping..."
  else
    echo "Firewall rule HTTPS has been added"
    sudo firewall-cmd --permanent --add-service=https   #Add firewall rule for https connections
  fi
  
  echo "Reloading FirewallD..."
  sudo firewall-cmd --reload
}
#Main----------------------------------------------------------------
if [[ $(command -v sudo) == "" ]]; then
  error_exit "NoTrack requires Sudo to be installed for Admin functionality" 10  
fi

Show_Welcome

if [[ $InstallLoc == "" ]]; then
  Ask_InstallLoc
fi

if [[ $NETWORK_DEVICE == "" ]]; then
  prompt_network_device
fi

if [[ $IP_VERSION == "" ]]; then
  prompt_ip_version
fi

get_ip_address $IP_VERSION $NETWORK_DEVICE
echo "System IP Address $IP_ADDRESS"
sleep 2s

prompt_dns_server $IP_VERSION

clear
echo "Installing to: $InstallLoc"                #Final report before Installing
echo "Network Device set to: $NETWORK_DEVICE"
echo "IPVersion set to: $IP_VERSION"
echo "System IP Address $IP_ADDRESS"
echo "Primary DNS Server set to: $DNS_SERVER_1"
echo "Secondary DNS Server set to: $DNS_SERVER_2"
echo 
sleep 8s

Install_Packages                                 #Install Apps with the appropriate package manager

Backup_Conf                                      #Backup old config files

if [ "$(command -v git)" ]; then                 #Utilise Git if its installed
  Download_WithGit
else
  Download_WithWget                              #Git not installed, fallback to wget
fi

Setup_Dnsmasq
Setup_Lighttpd
Setup_NoTrack
Setup_NtrkScripts

if [ "$(command -v firewall-cmd)" ]; then        #Check FirewallD exists
  Setup_FirewallD
fi

echo "Starting Services"
sudo service lighttpd restart

echo "Downloading List of Trackers"
sudo /usr/local/sbin/notrack -f

Show_Finish

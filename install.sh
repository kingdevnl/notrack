#!/usr/bin/env bash
#Title : NoTrack Installer
#Description : This script will install NoTrack and then configure dnsmasq and lighttpd
#Author : QuidsUp
#Usage : bash install.sh

#Program Settings----------------------------------------------------
Version="0.5"
NetDev=$(ip -o link show | awk '{print $2,$9}' | grep ": UP" | cut -d ":" -f 1)
CountNetDev=$(wc -w <<< "$NetDev")
Height=$(tput lines)
Width=$(tput cols)
Height=$((Height / 2))
Width=$(((Width * 2) / 3))
IPVersion=""
DNSChoice1=""
DNSChoice2=""
InstallLoc="${HOME}/NoTrack"

#Welcome Dialog------------------------------------------------------
Show_Welcome() {
  whiptail --msgbox --title "Welcome to NoTrack v$Version" "This installer will transform your system into a network-wide Tracker Blocker!\n\nInstall Guide: https://youtu.be/MHsrdGT5DzE" 20 $Width

  whiptail --title "Initating Network Interface" --yesno "NoTrack is a SERVER, therefore it needs a STATIC IP ADDRESS to function properly.\n\nHow to set a Static IP on Linux Server: https://youtu.be/vIgTmFu-puo" --yes-button "Ok" --no-button "Abort" 20 $Width
  if (( $? == 1)) ; then                           #Abort install if user selected no
    echo "Aborting Install"
    exit 1
  fi
}

#Finish Dialog-------------------------------------------------------
Show_Finish() {
  whiptail --msgbox --title "Install Complete" "NoTrack has been installed\nAccess the admin console at http://$(hostname)/admin" 15 $Width
}

#Ask User Which Network device to use for DNS lookups----------------
#Needed if user has more than one network device active on their system
#Whiptail method here is a bit crude, perhaps it could be improved?
Ask_NetDev() {
  if [[ $CountNetDev == 2 ]]; then               #Whiptail dialog for 2 choices
    ListDev=($NetDev)
    Fun=$(whiptail --title "Network Device" --radiolist "Select Network Device to use for DNS Queries" $Height $Width 2 --ok-button Select \
    "1" ${ListDev[0]} on \
    "2" ${ListDev[1]} off \
     3>&1 1>&2 2>&3) 
    Ret=$?  
    if [[ $Ret == 1 ]]; then
      echo "Aborting Install"
      exit 1
    elif [[ $Ret == 0 ]]; then
      NetDev=${ListDev[$Fun-1]}    
    fi 
  elif [[ $CountNetDev == 3 ]]; then             #Whiptail dialog for 3 devices
    ListDev=($NetDev)
    Fun=$(whiptail --title "Network Device" --radiolist "Select Network Device for DNS Queries" $Height $Width 3 --ok-button Select \
    "1" ${ListDev[0]} on \
    "2" ${ListDev[1]} off \
    "3" ${ListDev[2]} off \
     3>&1 1>&2 2>&3) 
    Ret=$?  
    if [[ $Ret == 1 ]]; then
    echo "Aborting Install"
    exit 1
    elif [[ $Ret == 0 ]]; then
      NetDev=${ListDev[$Fun-1]}    
    fi
  elif [[ $CountNetDev > 3 ]]; then              #4 or more use bash prompt
    echo
    echo "Network Devices detected:"
    echo "$NetDev" | tr -s " " "\012"
    echo -n "Type Network Device to use for DNS queries: "
    read Choice
    NetDev=$Choice
    echo
  fi
}

#Ask user which IP Version they are using on their network-----------
Ask_IPVersion() {
  Fun=$(whiptail --title "IP Version" --radiolist "Select IP Version being used" $Height $Width 2 --ok-button Select \
   IPv4 "IP Version 4 (default)" on \
   IPv6 "IP Version 6" off \
   3>&1 1>&2 2>&3) 
  Ret=$?
    
  if [ $Ret -eq 1 ]; then
    echo "Aborting Install"
    exit 1
  elif [ $Ret -eq 0 ]; then
    case "$Fun" in
      "IPv4") IPVersion="IPv4" ;;
      "IPv6") IPVersion="IPv6" ;;
      *) whiptail --msgbox "Programmer error: unrecognized option" 10 $Width 1 ;;
    esac 
  fi
}

#Ask user for preffered DNS server-----------------------------------
Ask_DNSServer() {
  Fun=$(whiptail --title "DNS Server" --radiolist "The job of a DNS server is to translate human readable domain names (e.g. google.com) into an  IP address which your computer will understand (e.g. 109.144.113.88) \nBy default your router forwards DNS queries to your Internet Service Provider (ISP), however ISP DNS servers are not the best.\nChoose a better DNS server from the list below:" $Height $Width 7 --ok-button Select \
   OpenDNS "OpenDNS" on \
   Google "Google Public DNS" off \
   DNSWatch "DNS.Watch" off \
   Verisign "Verisign" off \
   Comodo "Comodo" off \
   FreeDNS "FreeDNS" off \
   Yandex "Yandex DNS" off \
   3>&1 1>&2 2>&3) 
  Ret=$?
    
  if [ $Ret -eq 1 ]; then
    echo "Aborting Install"
    exit 1
  elif [ $Ret -eq 0 ]; then
    case "$Fun" in
      "OpenDNS") 
        DNSChoice1="208.67.222.222" 
        DNSChoice2="208.67.220.220"
      ;;
      "Google") 
        DNSChoice1="8.8.8.8"
        DNSChoice2="8.8.4.4"
      ;;
      "DNSWatch") 
        if [[ $IPVersion == "IPv6" ]]; then
          DNSChoice1="2001:1608:10:25::1c04:b12f"
          DNSChoice2="2001:1608:10:25::9249:d69b"
        else
          DNSChoice1="84.200.69.80"
          DNSChoice2="84.200.70.40"
        fi
      ;;
      "Verisign")
        DNSChoice1="64.6.64.6"
        DNSChoice2="64.6.65.6"
      ;;
      "Comodo")
        DNSChoice1="8.26.56.26"
        DNSChoice2="8.20.247.20"
      ;;
      "FreeDNS")
        DNSChoice1="37.235.1.174"
        DNSChoice2="37.235.1.177"
      ;;
      "Yandex")
        if [[ $IPVersion == "IPv6" ]]; then
          DNSChoice1="2a02:6b8::feed:bad"
          DNSChoice2="2a02:6b8:0:1::feed:bad"
        else
          DNSChoice1="77.88.8.88"
          DNSChoice2="77.88.8.2"
        fi
      ;;
      *) whiptail --msgbox "Programmer error: unrecognized option" 10 $Width 1 ;;
    esac 
  fi
}

#Check File Exists---------------------------------------------------
Check_File_Exists() {
  if [ ! -e "$1" ]; then
    echo "Error file $1 is missing.  Aborting."
    exit 2
  fi
}

#Install Packages----------------------------------------------------
Install_Deb() {
  echo "Preparing to Install Deb Packages..."
  sleep 5s
  sudo apt-get update
  echo
  echo "Installing dependencies"
  sleep 2s
  sudo apt-get -y install unzip
  echo
  echo "Installing Dnsmasq"
  sleep 2s
  sudo apt-get -y install dnsmasq
  echo
  echo "Installing Lighttpd and PHP5"
  sleep 2s
  sudo apt-get -y install lighttpd memcached php5-memcache php5-cgi php5-curl 
  echo
  echo "Restarting Lighttpd"
  sudo service lighttpd restart
}
#--------------------------------------------------------------------
Install_Dnf() {
  echo "Preparing to Install RPM packages using Dnf..."
  sleep 5s
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
  sleep 3s
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
  #Possible Bugfix - Need CURL package
  echo  
}
#--------------------------------------------------------------------
Install_Yum() {
  echo "Preparing to Install RPM packages using Yum..."
  sleep 5s
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
Install_Zypper() {
  echo "Zypper package install not implemented yet.  Aborting."
  exit 2
}
#--------------------------------------------------------------------
Install_Packages() {
  if [ $(command -v apt-get) ]; then Install_Deb
  elif [ $(command -v dnf) ]; then Install_Dnf
  elif [ $(command -v yum) ]; then Install_Yum
  elif [ $(command -v zypper) ]; then Install_Zypper
  elif [ $(command -v pacman) ]; then Install_Pacman
  else 
    echo "Unable to work out which package manage is being used."
    echo "Ensure you have the following packages installed:"
    echo -e "\tdnsmasq"
    echo -e "\tlighttpd"
    echo -e "\tphp-cgi"
    echo -e "\tphp-curl"
    echo -e "\tphp-xcache"
    echo -e "\tunzip"
    echo
    sleep 10s
  fi
}
#Backup Configs------------------------------------------------------
Backup_Conf() {
  echo "Backing up old config files"
  echo "Copying /etc/dnsmasq.conf to /etc/dnsmasq.conf.old"
  sudo cp /etc/dnsmasq.conf /etc/dnsmasq.conf.old
  echo "Copying /etc/lighttpd/lighttpd.conf to /etc/lighttpd/lighttpd.conf.old"
  sudo cp /etc/lighttpd/lighttpd.conf /etc/lighttpd/lighttpd.conf.old
  echo
}

#Download With Git---------------------------------------------------
Download_WithGit() {
  #Download with Git if the user has it installed on their system
  echo "Downloading NoTrack using Git"
  git clone --depth=1 https://github.com/quidsup/notrack.git "$InstallLoc"
  echo
}

#Download WithWget---------------------------------------------------
Download_WithWget() {
  #Alternative download with wget 
  if [ -d $InstallLoc ]; then                      #Check if NoTrack folder exists
    echo "NoTrack folder exists. Skipping download"
  else
    echo "Downloading latest version of NoTrack from github"
    wget https://github.com/quidsup/notrack/archive/master.zip -O /tmp/notrack-master.zip
    if [ ! -e /tmp/notrack-master.zip ]; then    #Check again to see if download was successful
      echo "Error Download from github has failed"
      exit 2                                     #Abort we can't go any further without any code from git
    fi  

    unzip -oq /tmp/notrack-master.zip -d /tmp
    mv /tmp/notrack-master "$InstallLoc"
    rm /tmp/notrack-master.zip                  #Cleanup
  fi
  
  sudo chown "$(whoami)":"$(whoami)" -hR "$InstallLoc"
}
#Setup Dnsmasq-------------------------------------------------------
Setup_Dnsmasq() {
  #Copy config files modified for NoTrack
  echo "Copying config files from $InstallLoc to /etc/"
  Check_File_Exists "$InstallLoc/conf/dnsmasq.conf"
  sudo cp "$InstallLoc/conf/dnsmasq.conf" /etc/dnsmasq.conf
  
  Check_File_Exists "$InstallLoc/conf/lighttpd.conf"
  sudo cp "$InstallLoc/conf/lighttpd.conf" /etc/lighttpd/lighttpd.conf
  
  #Configure FirewallD to Work With Dnsmasq
  if [ $(command -v firewall-cmd) ]; then
    echo "Creating Firewall Rules Using FirewallD"
    if [ $(sudo firewall-cmd --query-service=dns) == "yes" ]; then
      echo "Firewall rule DNS already exists! Skipping..."
    else
      echo "Firewall rule DNS has been added"
      sudo firewall-cmd --permanent --add-service=dns    #Add firewall rule for dns connections
    fi
  fi

  #Finish configuration of dnsmasq config
  echo "Setting DNS Servers in /etc/dnsmasq.conf"
  sudo sed -i "s/server=changeme1/server=$DNSChoice1/" /etc/dnsmasq.conf
  sudo sed -i "s/server=changeme2/server=$DNSChoice2/" /etc/dnsmasq.conf
  sudo sed -i "s/interface=eth0/interface=$NetDev/" /etc/dnsmasq.conf
  echo "Creating file /etc/localhosts.list for Local Hosts"
  echo "Start filling it out, and then enable by uncommenting"
  echo "#addn-hosts=/etc/localhosts.list in /etc/dnsmasq.conf"
  sudo touch /etc/localhosts.list               #File for user to add DNS entries for their network
    
  #Setup Log rotation for dnsmasq
  echo "Copying log rotation script for Dnsmasq"
  Check_File_Exists "$InstallLoc/conf/logrotate.txt"
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
  echo "Configuring Lighttpd"
  sudo usermod -a -G www-data "$(whoami)"        #Add www-data group rights to current user
  sudo lighty-enable-mod fastcgi fastcgi-php
  
  #Configure FirewallD to Work With Lighttpd
  if [ $(command -v firewall-cmd) ]; then
    echo "Creating Firewall Rules Using FirewallD"
    if [ $(sudo firewall-cmd --query-service=http) == "yes" ]; then
      echo "Firewall rule HTTP already exists! Skipping..."
    else
      echo "Firewall rule HTTP has been added"
      sudo firewall-cmd --permanent --add-service=http    #Add firewall rule for http connections
    fi

    if [ $(sudo firewall-cmd --query-service=https) == "yes" ]; then
      echo "Firewall rule HTTPS already exists! Skipping..."
    else
      echo "Firewall rule HTTPS has been added"
      sudo firewall-cmd --permanent --add-service=https   #Add firewall rule for https connections
    fi
  fi
  
  if [ ! -d /var/www/html ]; then                #www/html folder will get created by Lighttpd install
    echo "Creating Web folder: /var/www/html"
    sudo mkdir -p /var/www/html                  #Create the folder for now incase installer failed
  fi
  
  if [ -e /var/www/html/sink ]; then             #Remove old symlinks
    echo "Removing old file: /var/www/html/sink"
    sudo rm /var/www/html/sink
  fi
  if [ -e /var/www/html/admin ]; then
    echo "Removing old file: /var/www/html/admin"
    sudo rm /var/www/html/admin
  fi
  echo "Creating symlink from $InstallLoc/sink to /var/www/html/sink"
  sudo ln -s "$InstallLoc/sink" /var/www/html/sink #Setup symlinks for Web folders
  echo "Creating symlink from $InstallLoc/admin to /var/www/html/admin"
  sudo ln -s "$InstallLoc/admin" /var/www/html/admin
  sudo chmod 775 /var/www/html                   #Give read/write/execute privilages to Web folder
  echo "Restarting Lighttpd"
  sudo service lighttpd restart
  echo "Setup of Lighttpd complete"
  echo
}

#Setup Notrack-------------------------------------------------------
Setup_NoTrack() {
  #Setup Tracker list downloader
  echo "Setting up Tracker list downloader"
  
  Check_File_Exists "$InstallLoc/notrack.sh"
  sudo cp "$InstallLoc/notrack.sh" /usr/local/sbin/notrack.sh
  sudo mv /usr/local/sbin/notrack.sh /usr/local/sbin/notrack #Cron jobs will only execute on files Without extensions
  sudo chmod +x /usr/local/sbin/notrack          #Make NoTrack Script executable
  
  Check_File_Exists "$InstallLoc/dns-log-archive.sh"
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
  echo
  
  if [ ! -d "/etc/notrack" ]; then               #Check /etc/notrack folder exists
    echo "Creating folder: /etc/notrack"
    echo
    sudo mkdir "/etc/notrack"
  fi
  
  if [ -e /etc/notrack/notrack.conf ]; then      #Remove old config file
    echo "Removing old file: /etc/notrack/notrack.conf"
    sudo rm /etc/notrack/notrack.conf
  fi
  echo "Creating NoTrack config file: /etc/notrack/notrack.conf"
  sudo touch /etc/notrack/notrack.conf          #Create Config file
  echo "IPVersion = $IPVersion" | sudo tee /etc/notrack/notrack.conf
  echo "NetDev = $NetDev" | sudo tee /etc/notrack/notrack.conf
  
  echo
  echo "Setup of NoTrack complete"
  echo
  echo
}
#NoTrack Exec--------------------------------------------------------
Setup_NtrkExec() {
  Check_File_Exists "$InstallLoc/ntrk-exec.sh"
  sudo cp "$InstallLoc/ntrk-exec.sh" /usr/local/sbin/
  sudo mv /usr/local/sbin/ntrk-exec.sh /usr/local/sbin/ntrk-exec
  sudo chmod 755 /usr/local/sbin/ntrk-exec
  
  SudoCheck=$(sudo cat /etc/sudoers | grep www-data)
  if [[ $SudoCheck == "" ]]; then
    echo "Adding NoPassword permissions for www-data to execute script /usr/local/sbin/ntrk-exec as root"
    echo -e "www-data\tALL=(ALL:ALL) NOPASSWD: /usr/local/sbin/ntrk-exec" | sudo tee -a /etc/sudoers
    echo
  fi  
}
#Main----------------------------------------------------------------
if [ $InstallLoc == "/root/NoTrack" ]; then      #Change root folder to users folder
  InstallLoc="$(getent passwd $SUDO_USER | cut -d: -f6)/NoTrack"
fi

echo "NoTrack Install version: v$Version"
echo

Show_Welcome

Ask_NetDev
echo "Network Device set to: $NetDev"
echo

Ask_IPVersion
echo "IPVersion set to: $IPVersion"
echo

Ask_DNSServer
echo "Primary DNS Server set to: $DNSChoice1"
echo "Secondary DNS Server set to: $DNSChoice2"
echo 

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
Setup_NtrkExec

# Reload FirewallD to ensure new rules are functional
if [ $(command -v firewall-cmd) ]; then
  echo "Reloading FirewallD..."
  sudo firewall-cmd --reload
fi

echo "Downloading List of Trackers"
sudo /usr/local/sbin/notrack

Show_Finish

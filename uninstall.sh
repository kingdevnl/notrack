#!/usr/bin/env bash
#Title : NoTrack Uninstaller
#Description : This script remove the files NoTrack created, and then return dnsmasq and lighttpd to their default configuration
#Author : QuidsUp
#Usage : bash uninstall.sh

#User Configerable variables-----------------------------------------
SBinFolder="/usr/local/sbin"
EtcFolder="/etc"

#Program Settings----------------------------------------------------
Width=$(tput cols)
Width=$(((Width * 2) / 3))
InstallLoc="${HOME}/NoTrack"

#Welcome Dialog------------------------------------------------------
Show_Welcome() {
  whiptail --title "Farewell to NoTrack" --yesno "This script will remove the files created by NoTrack, and then return dnsmasq and lighttpd to their default configuration" --yes-button "Ok" --no-button "Abort" 20 $Width
  if (( $? == 1)) ; then                           #Abort install if user selected no
    echo "Aborting Uninstall"
    exit 1
  fi
}
#Error_Exit----------------------------------------------------------
Error_Exit() {
  echo "$1"
  echo "Aborting"
  exit 2
}
#Copy File-----------------------------------------------------------
CopyFile()
  #$1 Source
  #$2 Target
  if [ -e "$1" ]; then
    echo "Copying $1 to $2"
    cp "$1" "$2"
  else
    echo "File $1 not found"
  fi
}
#Delete old file if it Exists----------------------------------------
DeleteFile() {
  if [ -e "$1" ]; then
    echo "Deleting file $1"
    rm "$1"    
  fi
}
#Delete old file if it Exists----------------------------------------
DeleteFolder() {
  if [ -d "$1" ]; then
    echo "Deleting folder $1"
    rm -rf "$1"    
  fi
}
Install_Packages() {
  if [ $(command -v apt-get) ]; then Install_Deb
  elif [ $(command -v dnf) ]; then Install_Dnf
  elif [ $(command -v yum) ]; then Install_Yum  
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


#NoTrack Exec--------------------------------------------------------
Setup_NtrkExec() {
   
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

if [ ! -e "$InstallLoc" ]; then
  Error_Exit "Can't find NoTrack folder"  
fi

Show_Welcome

if [[ "$(id -u)" != "0" ]]; then
  echo "Root access is required to carry out uninstall of NoTrack"
  sudo su
fi

if [ "$(id -u)" != "0" ]; then
  Error_Exit "Root access hasn't been granted"
fi

echo "Stopping Dnsmasq"
service dnsmasq stop
echo "Stopping Lighttpd"
service lighttpd stop
echo

echo "Deleting Symlinks for Web Folders"
echo "Deleting Sink symlink"
DeleteFile "/var/www/html/sink"
echo "Deleting Admin symlink"
DeleteFile "/var/www/html/admin"
echo

echo "Restoring Configuration files"
echo "Restoring Dnsmasq config"
CopyFile "/etc/dnsmasq.conf.old" "/etc/dnsmasq.conf"
echo "Restoring Lighttpd config"
CopyFile "/etc/lighttpd/lighttpd.conf.old" "/etc/lighttpd/lighttpd.conf"
echo

echo "Removing Log file rotator"
DeleteFile "/etc/logrotate.d/notrack"
echo

echo "Removing Cron job"
DeleteFile "/etc/cron.daily/notrack"
echo

echo "Deleting NoTrack scripts"
echo "Deleting dns-log-archive"
DeleteFile "$SBinFolder/dns-log-archive"
echo "Deleting notrack"
DeleteFile "$SBinFolder/notrack"
echo "Deleting ntrk-exec"
DeleteFile "$SBinFolder/ntrk-exec"
echo "Deleting ntrk-pause"
DeleteFile "$SBinFolder/ntrk-pause"
echo

echo "Removing root permissions for www-data to launch ntrk-exec"
sed -i '/www-data/d' /etc/sudoers
"www-data ALL=(ALL:ALL) NOPASSWD: /usr/local/sbin/ntrk-exec" | sudo tee -a /etc/sudoers


echo "Deleting /etc/notrack Folder"
DeleteFolder "$EtcFolder/notrack"
echo 

echo "Deleting Install Folder"
DeleteFolder "$InstallLoc"



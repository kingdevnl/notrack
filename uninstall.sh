#!/usr/bin/bash
#Title : NoTrack Uninstaller
#Description : This script remove the files NoTrack created, and then return dnsmasq and lighttpd to their default configuration
#Author : QuidsUp
#Usage : sudo bash uninstall.sh

#User Configerable variables-----------------------------------------
SBinFolder="/usr/local/sbin"
EtcFolder="/etc"

#Program Settings----------------------------------------------------
InstallLoc="${HOME}/NoTrack"


#Copy File-----------------------------------------------------------
CopyFile() {
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
#Find NoTrack--------------------------------------------------------
Find_NoTrack() {
  #This function finds where NoTrack is installed
  #1. Check current folder
  #2. Check users home folders
  #3. Check /opt/notrack
  #4. If not found then abort
  
  if [ -e "$(pwd)/notrack.sh" ]; then
    InstallLoc="$(pwd)"  
    return 1
  fi
  
  for HomeDir in /home/*; do
    if [ -d "$HomeDir/NoTrack" ]; then 
      InstallLoc="$HomeDir/NoTrack"
      break
    elif [ -d "$HomeDir/notrack" ]; then 
      InstallLoc="$HomeDir/notrack"
      break
    fi
  done

  if [[ $InstallLoc == "" ]]; then
    if [ -d "/opt/notrack" ]; then
      InstallLoc="/opt/notrack"
    else
      echo "Error Unable to find NoTrack folder"
      echo "Aborting"
      exit 22
    fi
  fi
  
  return 1
}

#Main----------------------------------------------------------------

Find_NoTrack                                     #Where is NoTrack located?

if [[ "$(id -u)" != "0" ]]; then
  echo "Root access is required to carry out uninstall of NoTrack"
  echo "sudo bash uninstall.sh"
  exit 5
  #su -c "$0" "$@" - This could be an alternative for systems without sudo
fi

echo "This script will remove the files created by NoTrack, and then returns dnsmasq and lighttpd to their default configuration"
echo "NoTrack Installation Folder: $InstallLoc"
echo
read -p "Continue (Y/n)? " -n1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
  echo "Aborting"
  exit 1
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
echo "Removing Local Hosts file"
DeleteFile "/etc/localhosts.list"
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

echo "Deleting /etc/notrack Folder"
DeleteFolder "$EtcFolder/notrack"
echo 

echo "Deleting Install Folder"
DeleteFolder "$InstallLoc"
echo

echo "Finished deleting all files"
echo

echo "The following packages will also need removing:"
echo -e "\tdnsmasq"
echo -e "\tlighttpd"
echo -e "\tphp"
echo -e "\tphp-cgi"
echo -e "\tphp-curl"
echo -e "\tmemcached"
echo -e "\tphp-memcache"
echo

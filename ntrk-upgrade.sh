#!/bin/bash
#Title : NoTrack Upgrader
#Description : 
#Author : QuidsUp
#Date : 2016-03-22
#Usage : ntrk-upgrade
#Last updated with NoTrack v0.7.10

#Error Codes:
#21 Root access required
#22 Unable to find NoTrack
#23 Download of NoTrack has failed
#24 File missing

#Settings (Leave these alone)----------------------------------------
ConfigFile="/etc/notrack/notrack.conf"

#Variables-----------------------------------------------------------
INSTALL_LOCATION=""
UserName=""

#--------------------------------------------------------------------
check_file_exists() {
  if [ ! -e "$1" ]; then
    echo "Error file $1 is missing.  Aborting."
    exit 24
  fi
}
#--------------------------------------------------------------------
if [[ "$(id -u)" != "0" ]]; then
  echo "Root access is required to carry out upgrade of NoTrack"
  exit 21
fi

for HomeDir in /home/*; do
  if [ -d "$HomeDir/NoTrack" ]; then 
    INSTALL_LOCATION="$HomeDir/NoTrack"
    break
  elif [ -d "$HomeDir/notrack" ]; then 
    INSTALL_LOCATION="$HomeDir/notrack"
    break
  fi
done

if [[ $INSTALL_LOCATION == "" ]]; then
  if [ -d "/opt/notrack" ]; then
    INSTALL_LOCATION="/opt/notrack"
    UserName="root"
  elif [ -d "/root/notrack" ]; then
    INSTALL_LOCATION="/root/notrack"
    UserName="root"
  elif [ -d "/notrack" ]; then
    INSTALL_LOCATION="/notrack"
    UserName="root"
  else
    echo "Error Unable to find NoTrack folder"
    echo "Aborting"
    exit 22
  fi
else 
  UserName=$(grep "$HomeDir" /etc/passwd | cut -d : -f1)
fi

echo "Install Location $INSTALL_LOCATION"
echo "Username: $UserName"

#Alt command for sudoless systems
#su -c "cd /home/$USERNAME/$PROJECT ; svn update" -m "$USERNAME" 

sudo -u $UserName bash << ROOTLESS
if [ "$(command -v git)" ]; then                 #Utilise Git if its installed
  echo "Pulling latest updates of NoTrack using Git"
  cd "$INSTALL_LOCATION"
  git pull
  if [ $? != "0" ]; then                         #Git repository not found
    echo "Git repository not found"
    if [ -d "$INSTALL_LOCATION-old" ]; then      #Delete NoTrack-old folder if it exists
      echo "Removing old NoTrack folder"
      rm -rf "$INSTALL_LOCATION-old"
    fi
    echo "Moving $INSTALL_LOCATION folder to $INSTALL_LOCATION-old"
    mv "$INSTALL_LOCATION" "$INSTALL_LOCATION-old"
    echo "Cloning NoTrack to $INSTALL_LOCATION with Git"
    git clone --depth=1 https://github.com/quidsup/notrack.git "$INSTALL_LOCATION"
  fi
else                                             #Git not installed, fallback to wget
  echo "Downloading latest version of NoTrack from https://github.com/quidsup/notrack/archive/master.zip"
  wget -O /tmp/notrack-master.zip https://github.com/quidsup/notrack/archive/master.zip
  if [ ! -e /tmp/notrack-master.zip ]; then    #Check to see if download was successful
    #Abort we can't go any further without any code from git
    echo "Error Download from github has failed"
    exit 23
  fi
  
  if [ -d "$INSTALL_LOCATION" ]; then            #Check if NoTrack folder exists  
    if [ -d "$INSTALL_LOCATION-old" ]; then      #Delete NoTrack-old folder if it exists
      echo "Removing old NoTrack folder"
      rm -rf "$INSTALL_LOCATION-old"
    fi
    echo "Moving $INSTALL_LOCATION folder to $INSTALL_LOCATION-old"
    mv "$INSTALL_LOCATION" "$INSTALL_LOCATION-old"
  fi
 
  echo "Unzipping notrack-master.zip"
  unzip -oq /tmp/notrack-master.zip -d /tmp
  echo "Copying folder across to $INSTALL_LOCATION"
  mv /tmp/notrack-master "$INSTALL_LOCATION"
  echo "Removing temporary files"
  rm /tmp/notrack-master.zip                     #Cleanup
fi

ROOTLESS

if [ $? == 23 ]; then                            #Code hasn't downloaded
  exit 23
fi

check_file_exists "$INSTALL_LOCATION/notrack.sh"       #NoTrack.sh
echo "Updating notrack.sh"
cp "$INSTALL_LOCATION/notrack.sh" /usr/local/sbin/
mv /usr/local/sbin/notrack.sh /usr/local/sbin/notrack
chmod 755 /usr/local/sbin/notrack
  
check_file_exists "$INSTALL_LOCATION/ntrk-exec.sh"     #ntrk-exec.sh
echo "Updating ntrk-exec.sh"
cp "$INSTALL_LOCATION/ntrk-exec.sh" /usr/local/sbin/
mv /usr/local/sbin/ntrk-exec.sh /usr/local/sbin/ntrk-exec
chmod 755 /usr/local/sbin/ntrk-exec
  
check_file_exists "$INSTALL_LOCATION/ntrk-pause.sh"    #ntrk-pause.sh
echo "Updating ntrk-pause.sh"
cp "$INSTALL_LOCATION/ntrk-pause.sh" /usr/local/sbin/
mv /usr/local/sbin/ntrk-pause.sh /usr/local/sbin/ntrk-pause
chmod 755 /usr/local/sbin/ntrk-pause

check_file_exists "$INSTALL_LOCATION/ntrk-upgrade.sh"  #ntrk-upgrade.sh
echo "Updating ntrk-upgrade.sh"
cp "$INSTALL_LOCATION/ntrk-upgrade.sh" /usr/local/sbin/
mv /usr/local/sbin/ntrk-upgrade.sh /usr/local/sbin/ntrk-upgrade
chmod 755 /usr/local/sbin/ntrk-upgrade

check_file_exists "$INSTALL_LOCATION/scripts/ntrk-parse.sh"      #ntrk-parse.sh
echo "Updating ntrk-parse.sh"
cp "$INSTALL_LOCATION/scripts/ntrk-parse.sh" /usr/local/sbin/
mv /usr/local/sbin/ntrk-parse.sh /usr/local/sbin/ntrk-parse
chmod 755 /usr/local/sbin/ntrk-parse
  
SudoCheck=$(cat /etc/sudoers | grep www-data)
if [[ $SudoCheck == "" ]]; then
  echo "Adding NoPassword permissions for www-data to execute script /usr/local/sbin/ntrk-exec as root"
  echo -e "www-data\tALL=(ALL:ALL) NOPASSWD: /usr/local/sbin/ntrk-exec" | tee -a /etc/sudoers
fi

#v0.8.1 - Add user_agent table to sql db
mysql --user=ntrk --password=ntrkpass -D ntrkdb -e "ALTER TABLE lightyaccess ADD COLUMN IF NOT EXISTS referrer TEXT AFTER uri_path;"
mysql --user=ntrk --password=ntrkpass -D ntrkdb -e "ALTER TABLE lightyaccess ADD COLUMN IF NOT EXISTS user_agent TEXT AFTER referrer;"

#v0.8.1 - Add user_agent collection to lighttpd.conf
if [[ $(grep '"%{%s}t|%V|%r|%s|%b"' /etc/lighttpd/lighttpd.conf) != "" ]]; then
  sed -i 's/"%{%s}t|%V|%r|%s|%b"/"%{%s}t|%V|%r|%s|%b|%{Referer}i|%{User-Agent}i"/' /etc/lighttpd/lighttpd.conf
  echo "lighttpd needs restarting: sudo systemctl restart lighttpd"
fi


if [ -e "$ConfigFile" ]; then                  #Remove Latestversion number from Config file
  echo "Removing version number from Config file"
  grep -v "LatestVersion" "$ConfigFile" > /tmp/notrack.conf
  mv /tmp/notrack.conf "$ConfigFile"
fi
  
echo "NoTrack updated"

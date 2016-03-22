#!/bin/bash
#Title : NoTrack Upgrader
#Description : 
#Author : QuidsUp
#Date : 2016-03-22
#Usage : ntrk-upgrade

#Settings (Leave these alone)----------------------------------------
ConfigFile="/etc/notrack/notrack.conf"
OldConfig="/etc/notrack/old.conf"
ConfigFile="/etc/notrack/notrack.conf"

#Variables-----------------------------------------------------------
InstallLoc=""
UserName=""

#--------------------------------------------------------------------
Check_File_Exists() {
  if [ ! -e "$1" ]; then
    echo "Error file $1 is missing.  Aborting."
    exit 5
  fi
}
#--------------------------------------------------------------------
if [[ "$(id -u)" != "0" ]]; then
  echo "Root access is required to carry out upgrade of NoTrack"
  exit 1
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

if [[ InstallLoc == "" ]]; then
  if [ -d "/opt/notrack" ]; then
    InstallLoc="/opt/notrack"
    UserName="root"
  else
    echo "Error Unable to find NoTrack folder"
    echo "Aborting"
    exit 4
  fi
else 
  UserName=$(grep "$HomeDir" /etc/passwd | cut -d : -f1)
fi

echo "Install Location $InstallLoc"
echo "Username: $UserName"

#su -c "cd /home/$USERNAME/$PROJECT ; svn update" -m "$USERNAME" 

sudo -u $UserName bash << ROOTLESS
if [ "$(command -v git)" ]; then               #Utilise Git if its installed
  echo "Pulling latest updates of NoTrack using Git"
  cd "$InstallLoc"
  git pull
  if [ $? != "0" ]; then                       #Git repository not found
    echo "Git repository not found"
    if [ -d "$InstallLoc-old" ]; then          #Delete NoTrack-old folder if it exists
      echo "Removing old NoTrack folder"
      rm -rf "$InstallLoc-old"
    fi
    echo "Moving $InstallLoc folder to $InstallLoc-old"
    mv "$InstallLoc" "$InstallLoc-old"
    echo "Cloning NoTrack to $InstallLoc with Git"
    git clone --depth=1 https://github.com/quidsup/notrack.git "$InstallLoc"
  fi
else                                           #Git not installed, fallback to wget
  echo "Downloading latest version of NoTrack from https://github.com/quidsup/notrack/archive/master.zip"
  wget -O /tmp/notrack-master.zip https://github.com/quidsup/notrack/archive/master.zip
  if [ ! -e /tmp/notrack-master.zip ]; then    #Check to see if download was successful
    #Abort we can't go any further without any code from git
    Error_Exit "Error Download from github has failed"      
  fi
  
  if [ -d "$InstallLoc" ]; then                #Check if NoTrack folder exists  
    if [ -d "$InstallLoc-old" ]; then          #Delete NoTrack-old folder if it exists
      echo "Removing old NoTrack folder"
      rm -rf "$InstallLoc-old"
    fi
    echo "Moving $InstallLoc folder to $InstallLoc-old"
    mv "$InstallLoc" "$InstallLoc-old"
  fi
 
  echo "Unzipping notrack-master.zip"
  unzip -oq /tmp/notrack-master.zip -d /tmp
  echo "Copying folder across to $InstallLoc"
  mv /tmp/notrack-master "$InstallLoc"
  echo "Removing temporary files"
  rm /tmp/notrack-master.zip                  #Cleanup
fi


ROOTLESS

Check_File_Exists "$InstallLoc/notrack.sh"
echo "Updating notrack.sh"
cp "$InstallLoc/notrack.sh" /usr/local/sbin/
mv /usr/local/sbin/notrack.sh /usr/local/sbin/notrack
chmod 755 /usr/local/sbin/notrack
  
Check_File_Exists "$InstallLoc/ntrk-exec.sh"
echo "Updating ntck-exec.sh"
cp "$InstallLoc/ntrk-exec.sh" /usr/local/sbin/
mv /usr/local/sbin/ntrk-exec.sh /usr/local/sbin/ntrk-exec
chmod 755 /usr/local/sbin/ntrk-exec
  
Check_File_Exists "$InstallLoc/ntrk-pause.sh"
echo "Updating ntck-pause.sh"
cp "$InstallLoc/ntrk-pause.sh" /usr/local/sbin/
mv /usr/local/sbin/ntrk-pause.sh /usr/local/sbin/ntrk-pause
chmod 755 /usr/local/sbin/ntrk-pause

Check_File_Exists "$InstallLoc/ntrk-upgrade.sh"
echo "Updating ntck-upgrade.sh"
cp "$InstallLoc/ntrk-upgrade.sh" /usr/local/sbin/
mv /usr/local/sbin/ntrk-upgrade.sh /usr/local/sbin/ntrk-upgrade
chmod 755 /usr/local/sbin/ntrk-upgrade
  
SudoCheck=$(cat /etc/sudoers | grep www-data)
if [[ $SudoCheck == "" ]]; then
  echo "Adding NoPassword permissions for www-data to execute script /usr/local/sbin/ntrk-exec as root"
  echo -e "www-data\tALL=(ALL:ALL) NOPASSWD: /usr/local/sbin/ntrk-exec" | tee -a /etc/sudoers
fi
  
if [ -e "$ConfigFile" ]; then                  #Remove Latestversion number from Config file
  echo "Removing version number from Config file"
  grep -v "LatestVersion" "$ConfigFile" > /tmp/notrack.conf
  mv /tmp/notrack.conf "$ConfigFile"
fi
  
echo "NoTrack updated"

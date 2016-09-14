#!/bin/bash
#Title : NoTrack Exec
#Description : NoTrack Exec takes jobs that have been written to from
# A low privilege user, e.g. www-data, and then carries out the job
# at root level.
#Author : QuidsUp
#Date : 2015-02-02
#Usage : Write jobs to /tmp/ntrk-exec.txt, then launch ntrk-exec


#######################################
# Constants
#######################################
readonly PASSWORD_FILE="/etc/notrack/.password"


#Settings------------------------------------------------------------
ConfigFile="/etc/notrack/notrack.conf"
ExecFile="/tmp/ntrk-exec.txt"

#Check File Exists---------------------------------------------------
Check_File_Exists() {
  if [ ! -e "$1" ]; then
    echo "Error file $1 is missing.  Aborting."
    exit 24
  fi
}
#Copy Black List-----------------------------------------------------
Copy_BlackList() {
  if [ -e "/tmp/blacklist.txt" ]; then
    chown root:root /tmp/blacklist.txt
    chmod 644 /tmp/blacklist.txt
    echo "Copying /tmp/blacklist.txt to /etc/notrack/blacklist.txt"
    mv /tmp/blacklist.txt /etc/notrack/blacklist.txt
    echo
  fi
}
#Copy White List-----------------------------------------------------
Copy_WhiteList() {
  if [ -e "/tmp/whitelist.txt" ]; then
    chown root:root /tmp/whitelist.txt
    chmod 644 /tmp/whitelist.txt
    echo "Copying /tmp/whitelist.txt to /etc/notrack/whitelist.txt"
    mv /tmp/whitelist.txt /etc/notrack/whitelist.txt    
  fi
}
#Copy TLD Black List-------------------------------------------------
Copy_TLDBlackList() {
  if [ -e "/tmp/domain-blacklist.txt" ]; then
    chown root:root /tmp/domain.txt
    chmod 644 /tmp/domain-blacklist.txt
    echo "Copying /tmp/domain-blacklist.txt to /etc/notrack/domain-blacklist.txt"
    mv /tmp/domain-blacklist.txt /etc/notrack/domain-blacklist.txt
    echo
  fi
}
#Copy TLD White List-------------------------------------------------
Copy_TLDWhiteList() {
  if [ -e "/tmp/domain-whitelist.txt" ]; then
    chown root:root /tmp/domain-whitelist.txt
    chmod 644 /tmp/domain-whitelist.txt
    echo "Copying /tmp/domain-whitelist.txt to /etc/notrack/domain-whitelist.txt"
    mv /tmp/domain-whitelist.txt /etc/notrack/domain-whitelist.txt    
  fi
}
#Create Access Log---------------------------------------------------
Create_AccessLog() {
  if [ ! -e "/var/log/ntrk-admin.log" ]; then
    echo "Creating /var/log/ntrk-admin.log"
    touch /var/log/ntrk-admin.log
    chmod 666 /var/log/ntrk-admin.log
  fi
}
#Delete History------------------------------------------------------
Delete_History() {
  echo "Deleting Log Files in /var/log/notrack"
  rm /var/log/notrack/*                          #Delete all files in notrack log folder
  touch /var/log/notrack.log
  chown root:root /var/log/lighttpd/notrack.log
  chmod 644 /var/log/lighttpd/notrack.log
  echo "Deleting Log Files in /var/log/lighttpd"
  rm /var/log/lighttpd/*                         #Delete all files in lighttpd log folder
  touch /var/log/lighttpd/access.log             #Create new access log and set privileges
  chown www-data:root /var/log/lighttpd/access.log
  chmod 644 /var/log/lighttpd/access.log
  touch /var/log/lighttpd/error.log              #Create new error log and set privileges
  chown www-data:root /var/log/lighttpd/error.log
  chmod 644 /var/log/lighttpd/error.log
}
#Update Config-------------------------------------------------------
Update_Config() {
  if [ -e "/tmp/notrack.conf" ]; then
    chown root:root /tmp/notrack.conf
    chmod 644 /tmp/notrack.conf      
    echo "Copying /tmp/notrack.conf to /etc/notrack/notrack.conf"
    mv /tmp/notrack.conf /etc/notrack/notrack.conf
    echo
  fi
}
#Upgrade NoTrack
Upgrade-NoTrack() {
  if [ -e /usr/local/sbin/ntrk-upgrade ]; then
    echo "Running NoTrack Upgrade"
    /usr/local/sbin/ntrk-upgrade
  else
    echo "NoTrack Upgrade is missing, using fallback notrack.sh"
    /usr/local/sbin/notrack -u
  fi
}


#######################################
# Enables password protection and sets hashed password
# Globals:
#   $PASSWORD_FILE
# Arguments:
#   $1 Hashed password
# Returns:
#   None
#######################################
enable_password_protection(){
  if [ -e $PASSWORD_FILE ]; then
    sudo rm $PASSWORD_FILE
  fi

  sudo echo $1 >> $PASSWORD_FILE
}


#######################################
# Disables password protection
# Globals:
#   $PASSWORD_FILE
# Arguments:
#   None
# Returns:
#   None
#######################################
disable_password_protection(){
  sudo rm $PASSWORD_FILE
}


#Main----------------------------------------------------------------

if [[ $(whoami) == "www-data" ]]; then           #Check if launced from web server without root user
  echo "Error. ntrk-exec needs to be run as root"
  echo "run 'notrack --upgrade' to set permissions in sudoers file"
  exit 2
fi

if [[ "$(id -u)" != "0" ]]; then                 #Check if running as root
  echo "Error this script must be run as root"
  exit 2
fi


if [ "$1" ]; then                         #Have any arguments been given
  if ! Options=$(getopt -u -o h -l enable-password:,disable-password -- "$@"); then
    # something went wrong, getopt will put out an error message for us
    exit 1
  fi

  set -- $Options

  while [ $# -gt 0 ]
  do
    case $1 in
      -h)
        echo "Help"
        ;;
      --enable-password) 
        enable_password_protection $2
	      shift
        ;;
      --disable-password) 
        disable_password_protection
        ;;
      (--) shift; break;;
      (-*) echo "$0: error - unrecognized option $1" 1>&2; exit 6;;
      (*) break;;
    esac
    shift
  done
else 
  Check_File_Exists "$ExecFile"

  while read -r Line; do  
    case "$Line" in
      copy-blacklist)
        Copy_BlackList
      ;;
      copy-whitelist) 
        Copy_WhiteList
      ;;
      copy-tldblacklist)
        Copy_TLDBlackList
      ;;
      copy-tldwhitelist) 
        Copy_TLDWhiteList
      ;;
      create-accesslog)
        Create_AccessLog
      ;;
      delete-history)
        Delete_History
      ;;
      force-notrack)
        /usr/local/sbin/notrack --force
      ;;
      pause5)
        /usr/local/sbin/ntrk-pause --pause 5
      ;;
      pause15)
        /usr/local/sbin/ntrk-pause --pause 15
      ;;
      pause30)
        /usr/local/sbin/ntrk-pause --pause 30
      ;;
      pause60)
        /usr/local/sbin/ntrk-pause --pause 60
      ;;
      restart)
        reboot
      ;;
      start)
        /usr/local/sbin/ntrk-pause --start
      ;;
      stop)
        /usr/local/sbin/ntrk-pause --stop
      ;;
      shutdown)
        shutdown now
      ;;
      update-config)
        Update_Config
      ;;
      upgrade-notrack)
        Upgrade-NoTrack
      ;;  
      blockmsg-message)
        if [ -L /var/www/html/sink ]; then         #Remove at RC
          echo "Removing old symbolic link folder"
          rm /var/www/html/sink
        fi
        if [ ! -d  /var/www/html/sink ]; then
          echo "Creating Sink Folder"
          mkdir /var/www/html/sink
        fi
        echo 'Setting Block message Blocked by NoTrack'
        echo '<p>Blocked by NoTrack</p>' | tee /var/www/html/sink/index.html &> /dev/null
        sudo chown -hR www-data:www-data /var/www/html/sink
        sudo chmod -R 775 /var/www/html/sink
      ;;
      blockmsg-pixel)
        if [ -L /var/www/html/sink ]; then         #Remove at RC
          echo "Removing old symbolic link folder"
          rm /var/www/html/sink        
        fi
        if [ ! -d  /var/www/html/sink ]; then
          echo "Creating Sink Folder"
          mkdir /var/www/html/sink        
        fi
        echo '<img src="data:image/gif;base64,R0lGODlhAQABAAAAACwAAAAAAQABAAA=" alt="" />' | tee /var/www/html/sink/index.html &> /dev/null
        sudo chown -hR www-data:www-data /var/www/html/sink
        sudo chmod -R 775 /var/www/html/sink
        echo 'Setting Block message to 1x1 pixel'
      ;;
      run-notrack)
        /usr/local/sbin/notrack
      ;;
      *)
        echo "Invalid action $Line"
    esac
  done < "$ExecFile"

  if [ -e /tmp/ntrk-exec.txt ]; then
    echo "Deleting $ExecFile"
    rm "$ExecFile"
  fi
fi
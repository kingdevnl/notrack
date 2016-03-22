#!/bin/bash
#Title : NoTrack Exec
#Description : NoTrack Exec takes jobs that have been written to from
# A low privilege user, e.g. www-data, and then carries out the job
# at root level.
#Author : QuidsUp
#Date : 2015-02-02
#Usage : Write jobs to /tmp/ntrk-exec.txt, then launch ntrk-exec

#Settings------------------------------------------------------------
ConfigFile="/etc/notrack/notrack.conf"

#Check File Exists---------------------------------------------------
Check_File_Exists() {
  if [ ! -e "$1" ]; then
    echo "Error file $1 is missing.  Aborting."
    exit 2
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
  if [ -e "/tmp/tldblacklist.txt" ]; then
    chown root:root /tmp/tldblacklist.txt
    chmod 644 /tmp/tldblacklist.txt
    echo "Copying /tmp/tldblacklist.txt to /etc/notrack/domain-blacklist.txt"
    mv /tmp/tldblacklist.txt /etc/notrack/domain-blacklist.txt
    echo
  fi
}
#Copy TLD White List-------------------------------------------------
Copy_TLDWhiteList() {
  if [ -e "/tmp/tldwhitelist.txt" ]; then
    chown root:root /tmp/tldwhitelist.txt
    chmod 644 /tmp/tldwhitelist.txt
    echo "Copying /tmp/tldwhitelist.txt to /etc/notrack/domain-whitelist.txt"
    mv /tmp/tldwhitelist.txt /etc/notrack/domain-whitelist.txt    
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
  cat /dev/null > /var/log/notrack.log           #Zero out live log
}
#Pause---------------------------------------------------------------
Pause() {
  #$1 = Time in minutes
  
  #1. Check if Config file exists
  #2. If it does then:
  #  2a. Copy Config to a temp file
  #  2b. Zero out Config file
  #  2c. Read temp file, and copy each line to Config if it doesn't start with "BlockList" (This way we can retain the users old config)
  #3. If Config doesn't exist, then Create a new file
  #4. Write lines into config disabling NoTrack & TLD BlockLists (These are the only two enabled by default in NoTrack)
  #5. Run NoTrack
  #6. Sleep
  #7. Move old Config back if it existed, or delete Config file
  #8. Run NoTrack again
  
  echo "Pausing NoTrack for $1 minutes"
  
  local ConfigExists=0
    
  if [ -e "$ConfigFile" ]; then
    ConfigExists=1
    echo "Copying $ConfigFile to /tmp/oldnotrack.conf"
    cp "$ConfigFile" /tmp/oldnotrack.conf
    
    cat /dev/null > $ConfigFile                  #Empty config file
    
    echo "Reading temporary Config file"
    while IFS=$'\n' read -r Line _
    do
      if [[ ${Line:0:9} != "BlockList" ]]; then  #Exclude Blocklist lines
        echo "$Line" >> $ConfigFile              #Copy old line to Config
      fi
    done < "/tmp/oldnotrack.conf"
  else                                           #No file found
    echo "No Config file found"
    echo "Creating Config file"
    touch "$ConfigFile"                          #Create new Config
  fi
  
  echo "Writing config file"
  echo "BlockList_NoTrack = 0" >> $ConfigFile
  echo "BlockList_TLD = 0" >> $ConfigFile
  
  echo "Running NoTrack"
  echo
  notrack
  
  echo
  echo "Sleeping for $1 minutes"  
  sleep "$1m"
    
  if [ $ConfigExists == 1 ]; then
    echo "Copying /tmp/oldnotrack.conf to $ConfigFile"
    mv /tmp/oldnotrack.conf "$ConfigFile"
  else
    echo "Deleting Config file and resuming default values"
    rm "$ConfigFile"
  fi
  
  echo "Running NoTrack again"
  echo
  notrack
  echo
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
    ntrk-upgrade
  else
    echo "NoTrack Upgrade is missing, using fallback notrack.sh"
    notrack -u
  fi
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

Check_File_Exists "/tmp/ntrk-exec.txt"

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
      notrack --force
    ;;
    pause5)
      ntrk-pause --pause 5
    ;;
    pause15)
      ntrk-pause --pause 15
    ;;
    pause30)
      ntrk-pause --pause 30
    ;;
    pause60)
      ntrk-pause --pause 60
    ;;
    restart)
      reboot
    ;;
    start)
      ntrk-pause --start
    ;;
    stop)
      ntrk-pause --stop
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
      echo 'Setting Block message Blocked by NoTrack';
      echo '<p>Blocked by NoTrack</p>' > /var/www/html/sink/index.html
    ;;
    blockmsg-pixel)
      echo '<img src="data:image/gif;base64,R0lGODlhAQABAAAAACwAAAAAAQABAAA=" alt="" />' > /var/www/html/sink/index.html
      echo 'Setting Block message to 1x1 pixel';
    ;;
    run-notrack)
      notrack
    ;;
    *)
      echo "Invalid action $Line"
  esac
done < /tmp/ntrk-exec.txt

if [ -e /tmp/ntrk-exec.txt ]; then
  echo "Deleting /tmp/ntrk-exec.txt" 
  rm /tmp/ntrk-exec.txt
fi

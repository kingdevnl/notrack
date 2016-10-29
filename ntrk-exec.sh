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
readonly FILE_CONFIG="/etc/notrack/notrack.conf"
readonly FILE_EXEC="/tmp/ntrk-exec.txt"
readonly TEMP_CONFIG="/tmp/notrack.conf"

readonly USER="ntrk"
readonly PASSWORD="ntrkpass"
readonly DBNAME="ntrkdb"

#--------------------------------------------------------------------
# Block Message
#   Sets Block message for sink page
#
# Globals:
#   None
# Arguments:
#   $1 Message
# Returns:
#   None
#--------------------------------------------------------------------
function block_message() {
  if [[ $1 == "message" ]]; then
    echo 'Setting Block message Blocked by NoTrack'
    echo '<p>Blocked by NoTrack</p>' | tee /var/www/html/sink/index.html &> /dev/null
  elif [[ $1 == "pixel" ]]; then
    echo 'Setting Block message to pixel'
    echo '<img src="data:image/gif;base64,R0lGODlhAQABAAAAACwAAAAAAQABAAA=" alt="" />' | tee /var/www/html/sink/index.html &> /dev/null
  fi
  
  if getent passwd www-data > /dev/null 2>&1; then  #default group is www-data
    sudo chown -hR www-data:www-data /var/www/html/sink    
  elif getent passwd http > /dev/null 2>&1; then    #Arch uses group http
    sudo chown -hR http:http /var/www/html/sink    
  fi
  
  sudo chmod -R 775 /var/www/html/sink
}

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

#--------------------------------------------------------------------
# Copy TLD Lists
#
# Globals:
#   None
# Arguments:
#   None
# Returns:
#   None
#--------------------------------------------------------------------
copy_tldlists() {
  if [ -e "/tmp/domain-blacklist.txt" ]; then
    chown root:root /tmp/domain.txt
    chmod 644 /tmp/domain-blacklist.txt
    echo "Copying /tmp/domain-blacklist.txt to /etc/notrack/domain-blacklist.txt"
    mv /tmp/domain-blacklist.txt /etc/notrack/domain-blacklist.txt
    echo
  fi

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

#--------------------------------------------------------------------
# Delete History
#
# Globals:
#   None
# Arguments:
#   None
# Returns:
#   None
#--------------------------------------------------------------------
delete_history() {
  echo "Deleting contents of Historic table"
  echo "DELETE LOW_PRIORITY FROM historic;" | mysql --user="$USER" --password="$PASSWORD" -D "$DBNAME"
  echo "ALTER TABLE historic AUTO_INCREMENT = 1;" | mysql --user="$USER" --password="$PASSWORD" -D "$DBNAME"
  
  echo "Deleting Log Files in /var/log/lighttpd"
  rm /var/log/lighttpd/*                         #Delete all files in lighttpd log folder
  touch /var/log/lighttpd/access.log             #Create new access log and set privileges
  chown www-data:root /var/log/lighttpd/access.log
  chmod 644 /var/log/lighttpd/access.log
  touch /var/log/lighttpd/error.log              #Create new error log and set privileges
  chown www-data:root /var/log/lighttpd/error.log
  chmod 644 /var/log/lighttpd/error.log
}


#--------------------------------------------------------------------
# Update Config
#
# Globals:
#   FILE_CONFIG, TEMP_CONFIG
# Arguments:
#   None
# Returns:
#   None
#--------------------------------------------------------------------
function update_config() {
  if [ -e "/tmp/notrack.conf" ]; then
    chown root:root "$TEMP_CONFIG"
    chmod 644 /tmp/notrack.conf      
    echo "Copying $TEMP_CONFIG to $FILE_CONFIG"
    mv "$TEMP_CONFIG" "$FILE_CONFIG"
    echo
  fi
}


#--------------------------------------------------------------------
# Upgrade NoTrack
#
# Globals:
#   None
# Arguments:
#   None
# Returns:
#   None
#--------------------------------------------------------------------
Upgrade-NoTrack() {
  if [ -e /usr/local/sbin/ntrk-upgrade ]; then
    echo "Running NoTrack Upgrade"
    /usr/local/sbin/ntrk-upgrade
  else
    echo "NoTrack Upgrade is missing, using fallback notrack.sh"
    /usr/local/sbin/notrack -u
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


if [ "$1" ]; then                         #Have any arguments been given
  if ! Options=$(getopt -o hps -l bm-msg,bm-pxl,copy-tld,delete-history,force,run-notrack,restart,save-conf,shutdown,pause: -- "$@"); then
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
      --bm-msg)        
        block_message "message"
      ;;
      --bm-pxl)
        block_message "pixel"
      ;;
      --copy-tld)
        copy_tldlists
      ;;
      --delete-history)
        delete_history
      ;;
      --force)
        /usr/local/sbin/notrack --force > /dev/null &
      ;;
      -p)                                        #Play
        /usr/local/sbin/ntrk-pause --start  > /dev/null &
      ;;
      --pause)
        pausetime=$(sed "s/'//g" <<< "$2")       #Remove single quotes
        echo "$pausetime"        
        /usr/local/sbin/ntrk-pause --pause "$pausetime"  > /dev/null &
      ;;
      --restart)
        reboot > /dev/null &
      ;;
      -s)                                        #Stop
        /usr/local/sbin/ntrk-pause --stop  > /dev/null &
      ;;
      --shutdown)
        shutdown now  > /dev/null &
      ;;      
      --run-notrack)        
        /usr/local/sbin/notrack > /dev/null &
      ;;
      --save-conf)
        update_config
      ;;
      (--) shift; break;;
      (-*) echo "$0: error - unrecognized option $1" 1>&2; exit 6;;
      (*) break;;
    esac
    shift
  done
else 
  Check_File_Exists "$FILE_EXEC"

  while read -r Line; do  
    case "$Line" in
      copy-blacklist)
        Copy_BlackList
      ;;
      copy-whitelist) 
        Copy_WhiteList
      ;;      
      create-accesslog)
        Create_AccessLog
      ;;      
      upgrade-notrack)
        Upgrade-NoTrack
      ;;            
      *)
        echo "Invalid action $Line"
    esac
  done < "$FILE_EXEC"

  if [ -e /tmp/ntrk-exec.txt ]; then
    echo "Deleting $FILE_EXEC"
    rm "$FILE_EXEC"
  fi
fi

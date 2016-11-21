#!/bin/bash
#Title : NoTrack Pause
#Description : NoTrack Pause can pause/stop/start blocking in NoTrack by moving blocklists away from /etc/dnsmasq.d
#Author : QuidsUp
#Date : 2016-02-23
#Last updated with notrack 0.8.2
#Usage : ntrk-pause [--pause | --stop | --start | --status]

#Move file to /scripts at 0.8.5 TODO

#######################################
# Constants
#######################################
readonly FILE_CONFIG="/etc/notrack/notrack.conf"
readonly NOTRACK_LIST="/etc/dnsmasq.d/notrack.list"
readonly NOTRACK_TEMP="/tmp/ntrkpause/notrack.list"


#######################################
# Global Variables
#######################################
pause_time=0


#--------------------------------------------------------------------
# Backup Lists
#   Backup all NoTrack blocklists from /etc/dnsmasq.d to /tmp/ntrkpause
#
# Globals:
#   NOTRACK_LIST, NOTRACK_TEMP
# Arguments:
#   None
# Returns:
#   None
#--------------------------------------------------------------------
function backup_lists() {
  create_folder "/tmp/ntrkpause"
  move_file "$NOTRACK_LIST" "$NOTRACK_TEMP"
}


#--------------------------------------------------------------------
# Check Root
#   1: Checks if running as root
#   2: Checks if script is already running, and then closes older running script
#
# Globals:
#   None
# Arguments:
#   $1 - Folder to create
# Returns:
#   None
#--------------------------------------------------------------------
function check_root() {
  local pid=""
  pid=$(pgrep ntrk-pause | head -n 1)            #Get PID of first ntrk-pause process

  if [[ "$(id -u)" != "0" ]]; then
    echo "Error this script must be run as root"
    exit 5
  fi
  
  #Check if another copy of ntrk-pause is running, and terminate it 
  if [[ $pid != "$$" ]] && [[ -n $pid ]] ; then  #$$ = This PID    
    echo "Ending ntrk-pause process $pid"
    kill -9 "$pid"
  fi
}


#--------------------------------------------------------------------
# Create Folder
#   Creates a folder if it doesn't exist
#
# Globals:
#   None
# Arguments:
#   $1 - Folder to create
# Returns:
#   None
#--------------------------------------------------------------------
function create_folder {
  if [ ! -d "$1" ]; then                         #Does folder exist?
    echo "Creating folder: $1"                   #Tell user folder being created
    mkdir "$1"                                   #Create folder
  fi
}


#--------------------------------------------------------------------
# Delete Folder
#   Deletes a folder if it exists
#
# Globals:
#   None
# Arguments:
#   $1 - Folder to delete
# Returns:
#   None
#--------------------------------------------------------------------
function delete_folder {
  if [ -d "$1" ]; then                           #Does folder exist?
    echo "Deleting folder: $1"                   #Tell user folder being deleted
    rm -r "$1"                                   #Delete folder
  fi
}


#--------------------------------------------------------------------
# Get Status
#   Checks status of config and if blocklists exist
#
# Globals:
#   FILE_CONFIG, NOTRACK_LIST, NOTRACK_TEMP
# Arguments:
#   None
# Returns:
#   Status of blocking
#   ? 0 = NoTrack Running with Blocking Enabled
#   ? 100 = Blocking Paused
#   ? 101 = Blocking Disabled
#   ? 102 = Error Unknown Status
#   ? 103 = NoTrack Running
#--------------------------------------------------------------------
function get_status() {
  if [[ $(pgrep notrack) != "" ]]; then          #Is NoTrack running?
    return 103
  fi
  
  if [ -e "$FILE_CONFIG" ]; then                 #Does config exist?
    if [[ $(grep "Status = Paused" "$FILE_CONFIG") != "" ]]; then
      return 100
    elif [[ $(grep "Status = Stop" "$FILE_CONFIG") != "" ]]; then
      return 101
    else                                         #Status unknown - Check blocklist exists
      if [ -e "$NOTRACK_LIST" ]; then
        return 0
      elif [ -e "$NOTRACK_TEMP" ]; then
        return 101
      else
        return 102                               #No idea - no blocking set
      fi
    fi
  else                                           #No config file, check blocklist exists
    if [ -e "$NOTRACK_LIST" ]; then
      return 0
    elif [ -e "$NOTRACK_TEMP" ]; then
      return 101
    else                                         #No idea - no blocking set, no config
      return 102
    fi
  fi
  
  return 0                                       #Shouldn't get to this point
}


#--------------------------------------------------------------------
# Move File
#   Checks if Source file exists, then copies it to Destination
#
# Globals:
#   None
# Arguments:
#   $1 = Source
#   $2 = Destination
# Returns:
#   0 on success, 1 when file not found
#--------------------------------------------------------------------
function move_file() {
  if [ -e "$1" ]; then
    mv "$1" "$2"
    echo "Moving $1 to $2"
    return 0
  else
    echo "WARNING: Unable to find file $1"
    return 1
  fi 
}


#--------------------------------------------------------------------
# Restart service
#   Restarts dnsmasq with either systemd or sysvinit
#
# Globals:
#   None
# Arguments:
#   None
# Returns:
#   None
#--------------------------------------------------------------------
function restart_dnsmasq() {
  echo "Restarting Dnsmasq"
  if [ "$(command -v systemctl)" ]; then         #Using systemd or sysvinit?
    systemctl restart dnsmasq
  else
    service dnsmasq restart
  fi
}


#--------------------------------------------------------------------
# Restore Lists
#   1: Restore all NoTrack blocklists from /tmp/ntrkpause to /etc/dnsmasq.d
#   2: If list doesn't exist, then run NoTrack
#   3: Remove status from config
#
# Globals:
#   NOTRACK_LIST, NOTRACK_TEMP, FILE_CONFIG
# Arguments:
#   None
# Returns:
#   None
#--------------------------------------------------------------------
function restore_lists() {
  if [ -e "$NOTRACK_TEMP" ]; then
    move_file "$NOTRACK_TEMP" "$NOTRACK_LIST"
    delete_folder "/tmp/ntrkpause"
  else
    echo "Unable to find old blocklists, running NoTrack"
    /usr/sbin/notrack -f
  fi
  
  if [ -e "$FILE_CONFIG" ]; then                 #Remove status from Config file
    echo "Removing status from Config file"
    grep -v "Status" "$FILE_CONFIG" > /tmp/notrack.conf
    move_file "/tmp/notrack.conf" "$FILE_CONFIG"
  fi
}


#--------------------------------------------------------------------
# Show Help
#   Display help, then exit
#
# Globals:
#   None
# Arguments:
#   None
# Returns:
#   None
#--------------------------------------------------------------------
show_help() {
  echo "Usage: ntrk-pause [Option]"
  echo "ntrk-pause Starts and Stops blocking with NoTrack"
  echo
  echo "The following options can be specified:"
  echo -e "  -d, --stop\t\tStop NoTrack"
  echo -e "  -h, --help\t\tDisplay this help and exit"
  echo -e "  -p, --pause [Number]\tPause NoTrack for [Number] of Minutes"
  echo -e "  -s, --start\t\tStart NoTrack from Either Paused of Stopped state"
  echo -e "  --status\t\tDisplay current status of ntrk-pause"
  echo
  exit 0
}


#--------------------------------------------------------------------
# Show Status
#   Displays the result of get_status, then exit
#
# Globals:
#   None
# Arguments:
#   None
# Returns:
#   None
#--------------------------------------------------------------------
show_status() {
  get_status
  
  case $? in
    0)
      echo "Status 0: Blocking Enabled"
      ;;    
    100)
      echo "Status 100: Blocking Paused"
      ;;
    101)
      echo "Status 101: Blocking Disabled"
      ;;
    102)
      echo "Status 102: Old config exists, but status unknown"      
      ;;
    103)
      echo "Status 103: NoTrack already running"      
      ;;
  esac
  exit 0
}  


#--------------------------------------------------------------------
# Disable Blocking - Stop
#   1. Check if running as Root user
#   2. Get Status of ntrk-pause
#   3. Following action depends on the result of get_status
# Globals:
#   None
# Arguments:
#   None
# Returns:
#   None
#--------------------------------------------------------------------
disable_blocking() {
  check_root

  get_status
  case "$?" in
    0)                                           #Enable > Disabled
      backup_lists
      echo "Status = Stop" >> $FILE_CONFIG
      restart_dnsmasq
      ;;
    100)                                         #Paused > Disabled
      echo "Switching from Paused to Disabled"
      sed -i "s/^\(Status *= *\).*/\1Stop/" $FILE_CONFIG
      ;;
    101)
      echo "NoTrack blocking already Disabled"
      return 101
      ;;
    102)
      echo "Unknown Status"
      exit 102
      ;;
    103)
      echo "NoTrack already running"
      exit 103
      ;;
  esac  
}


#--------------------------------------------------------------------
# Enable Blocking - Start
#   1. Check if running as Root user
#   2. Get Status of ntrk-pause
#   3. Following action depends on the result of get_status
# Globals:
#   None
# Arguments:
#   None
# Returns:
#   None
#--------------------------------------------------------------------
enable_blocking() {
  check_root

  get_status
  case "$?" in
    0)                                           #Enable > Enabled
      echo "NoTrack blocking already enabled"
      ;;
    100 | 101)                                   #Paused | Stop > Enable
      restore_lists
      restart_dnsmasq      
      ;;    
    102)
      echo "Unknown Status, running NoTrack to enable blocking"
      /usr/local/sbin/notrack
      ;;
    103)
      echo "NoTrack already running"
      exit 103
      ;;
  esac  
}


#--------------------------------------------------------------------
# Pause Blocking
#   1. Check if running as Root user
#   2. Get Status of ntrk-pause
#   3. Following action depends on the result of get_status
#   4. Sleep for ntrk-pause $2 minutes
#   5. Restore blocklists
# Globals:
#   None
# Arguments:
#   None
# Returns:
#   None
#--------------------------------------------------------------------
function pause_blocking() {
  local unpause_time=0
  
  #Calculate unpause time, based on (Current Epoch time + (ntrk-pause $2 * 60))
  unpause_time=$(date +%s)                       #Epoch time now
  let unpause_time+="($pause_time * 60)"
  
  check_root
  
  get_status  
  case $? in
    0)                                           #Enabled > Paused
      backup_lists
      restart_dnsmasq
      echo "Status = Paused$unpause_time" >> $FILE_CONFIG
      ;;    
    100)                                         #Paused > Different Pause Time
      echo "Changing Pause time"
      sed -i "s/^\(Status *= *\).*/\1Paused$unpause_time/" $FILE_CONFIG
      ;;
    101)                                         #Disabled > Paused
      echo "Switching from Disabled to Paused"
      sed -i "s/^\(Status *= *\).*/\1Paused$unpause_time/" $FILE_CONFIG
      ;;
    102)                                         #Unknown
      echo "Old config exists, but status unknown"
      exit 102
      ;;
    103)
      echo "NoTrack already running"
      exit 103
      ;;
  esac
  
  echo
  echo "Sleeping for $pause_time minutes"  
  sleep "${pause_time}m"
  
  restore_lists
  restart_dnsmasq   
}



#Main----------------------------------------------------------------

if [ "$1" ]; then                         #Have any arguments been given
  if ! options=$(getopt -o hdsp: -l help,stop,start,status,pause: -- "$@"); then
    # something went wrong, getopt will put out an error message for us
    exit 1
  fi

  set -- $options

  while [ $# -gt 0 ]
  do
    case $1 in
      -h|--help) 
        show_help 
        ;;
      -d|--stop)
        disable_blocking
        ;;
      -s|--start) 
        enable_blocking
        ;;      
      -p|--pause)
        pause_time=$(sed "s/'//g" <<< "$2")      #Remove single quotes from $2
        pause_blocking
        shift
        ;;
      --status)
        show_status
        ;;
      (--) shift; break;;
      (-*) echo "$0: error - unrecognized option $1" 1>&2; exit 6;;
      (*) break;;
    esac
    shift
  done
else                                             #No commands passed
  echo "Checking status of NoTrack"
  #No instructions given by user, the following will happen based on the result of get_status
  #a. Status Nothing - Pause for 15 Minutes
  #b. Status Unknown - Run NoTrack
  #c. Status Paused - Unpause
  #d. Status Stopped - Start
  
  get_status
  case $? in
    0)
      echo "Pausing NoTrack for 15 minutes"
      pause_time=15
      pause_blocking
      ;;    
    100)
      echo "Unpausing NoTrack"
      enable_blocking
      ;;
    101)
      echo "Enabling NoTrack"
      enable_blocking
      ;;
    102)
      echo "Pause status unknown. Running NoTrack"
      /usr/sbin/notrack -f
      ;;
  esac  
fi


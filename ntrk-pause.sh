#!/bin/bash
#Title : NoTrack Pause
#Description : NoTrack Pause can pause/stop/start blocking in NoTrack. It alters the Blocking settings in /etc/notrack/notrack.conf 
#Author : QuidsUp
#Date : 2016-02-23
#Usage : ntrk-pause [--pause | --stop | --start | --status]

#Settings (Leave these alone)----------------------------------------
ConfigFile="/etc/notrack/notrack.conf"
OldConfig="/etc/notrack/old.conf"
BlockingListFile="/etc/dnsmasq.d/notrack.list"

#Global Variables----------------------------------------------------
PauseTime=0

#Delete old file if it Exists----------------------------------------
function DeleteOldFile() {
  #$1 File to delete
  if [ -e "$1" ]; then                           #Does file exist?
    echo "Deleting file $1"
    rm "$1"                                      #If yes then delete it
  fi
}
#Backup Config-------------------------------------------------------
function BackupConfig() {
  #1. Check if Config file exists
  #2. If it does then:
  #  2a. Copy Config to a temp file
  #  2b. Zero out Config file
  #  2c. Read temp file, and copy each line to Config if it doesn't start with "BlockList" (This way we can retain the users old config)
  #3. If config doesn't exist then Create a new file
   
  if [ -e "$ConfigFile" ]; then    
    echo "Copying $ConfigFile to $OldConfig"
    cp "$ConfigFile" "$OldConfig"
    
    cat /dev/null > $ConfigFile                  #Empty config file
    
    echo "Reading temporary Config file"
    while IFS=$'\n' read -r Line _
    do
      if [[ ! $Line =~ ^(BlockList_|BL_) ]]; then  #Exclude Blocklist lines
        echo "$Line" >> $ConfigFile              #Copy old line to Config
      fi
    done < "$OldConfig"
  else                                           #No file found
    echo "No Config file found"
    echo "Creating Config file"
    touch "$ConfigFile"                          #Create new Config
  fi
}
#Check if running as Root--------------------------------------------
function CheckRoot() {
  local Pid=""
  Pid=$(pgrep ntrk-pause | head -n 1)            #Get PID of first ntrk-pause process

  if [[ "$(id -u)" != "0" ]]; then
    echo "Error this script must be run as root"
    exit 5
  fi
  
  #Check if another copy of ntrk-pause is running, and terminate it 
  if [[ $Pid != "$$" ]] && [[ -n $Pid ]] ; then  #$$ = This PID    
    echo "Ending ntrk-pause process $Pid"
    kill -9 "$Pid"
  fi
}
#GetStatus-----------------------------------------------------------
function GetStatus() {
#? 0 = NoTrack Running with Blocking Enabled
#? 100 = Blocking Paused
#? 101 = Blocking Disabled
#? 102 = Error Unknown Status
#? 103 = NoTrack Running
  if [[ $(pgrep notrack) != "" ]]; then
    return 103
  fi
  
  if [ -e "$OldConfig" ]; then
    if [ -e "$ConfigFile" ]; then
      if [[ $(grep "Status = Paused" "$ConfigFile") != "" ]]; then
        return 100
      elif [[ $(grep "Status = Stop" "$ConfigFile") != "" ]]; then
        return 101
      fi
    else
      return 102
    fi
  else 
    return 0
  fi
}
#Pause---------------------------------------------------------------
function Pause() {
  #1. Calculate unpause time, based on (Current Epoch time + (ntrk-pause $1 * 60))
  #2. Check if running as Root user
  #3. Get Status of ntrk-pause
  #  3a Status Nothing:
  #  3a i. Backup Config
  #  3a ii. Disable blocking in Config, and write Unpause time
  #  3b. Status Unknown, leave this function
  #  3c. Status Paused, change unpause time in Config file
  #  3d. Status Stopped, change status and add unpause time in Config file
  # NoTrack & TLD BlockLists are the only two BlockLists enabled by default in NoTrack
  #4. Run NoTrack
  #5. Sleep for ntrk-pause $1 minutes
  #7. Move old Config back if it existed, or delete Config file
  #8. Run NoTrack again
  
  local UnPauseTime=0
  UnPauseTime=$(date +%s)
  let UnPauseTime+="($PauseTime * 60)"
  
  CheckRoot
  
  GetStatus
  
  case $? in
    0)
      BackupConfig
      echo "BlockList_NoTrack = 0" >> $ConfigFile
      echo "BlockList_TLD = 0" >> $ConfigFile
      echo "Status = Paused$UnPauseTime" >> $ConfigFile
      ;;    
    100)
      echo "Changing Pause time"
      sed -i "s/^\(Status *= *\).*/\1Paused$UnPauseTime/" $ConfigFile
      ;;
    101)
      echo "Switching from Disabled to Paused"
      sed -i "s/^\(Status *= *\).*/\1Paused$UnPauseTime/" $ConfigFile
      ;;
    102)
      echo "Old config exists, but status unknown"
      exit 102
      ;;
    103)
      echo "NoTrack already running"
      exit 103
      ;;
  esac
  
  #echo "Running NoTrack to disable blocking"
  #echo
  #/usr/local/sbin/notrack
  
  echo "Deleting NoTrack block list"
  DeleteOldFile "$BlockingListFile" 
  echo "Restarting Dnsnmasq"
  service dnsmasq restart                          #Restart dnsmasq
  
  echo
  echo "Sleeping for $PauseTime minutes"  
  sleep "${PauseTime}m"
  
  if [ -e "$OldConfig" ]; then
    echo "Moving $OldConfig to $ConfigFile"
    mv "$OldConfig" "$ConfigFile"
  else
    echo "Deleting Config file and resuming default values"
    rm "$ConfigFile"
  fi

  echo "Running NoTrack to enable blocking"
  echo
  /usr/local/sbin/notrack
  echo
}
#Help----------------------------------------------------------------
ShowHelp() {
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
#Show Status---------------------------------------------------------
ShowStatus() {
  GetStatus
  
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
#Start---------------------------------------------------------------
function Start() {
  #1. Get Status of ntrk-pause
  #2. Exit if Status = 103 (NoTrack already running)
  #3. Check if running as Root user
  #4a. Move old Config file back if it existed
  #4b. Or Check if there is a Status line in Config file.
  #  4b i. User never had a Config file, Delete it to force back default values
  #4c. Blocking is enabled, don't change anything
  #5. Run NoTrack
  GetStatus
  if [ $? == 103 ]; then
    echo "NoTrack already running"
    exit 103
  fi
  
  CheckRoot
    
  if [ -e "$OldConfig" ]; then
    echo "Copying $OldConfig to $ConfigFile"
    mv "$OldConfig" "$ConfigFile"
  elif [[ $(grep "Status" "$ConfigFile") != "" ]]; then
    echo "Deleting Config file and resuming default values"
    rm "$ConfigFile"    
  else
    echo "NoTrack blocking already enabled"
  fi
  
  echo "Running NoTrack to enable blocking"
  echo
  /usr/local/sbin/notrack
  echo
}
#Stop----------------------------------------------------------------
Stop() {
  #1. Check if running as Root user
  #2. Get Status of ntrk-pause
  #  2a Status Nothing:
  #  2a i. Backup Config
  #  2a ii. Disable blocking in Config, add Status Stopped
  #  2b. Status Unknown, leave this function
  #  2c. Status Paused, change status to Stopped
  #  2d. Status Stopped, leave this function
  # NoTrack & TLD BlockLists are the only two BlockLists enabled by default in NoTrack
  #3. Run NoTrack
  CheckRoot

  GetStatus
  
  case $? in
    0)
      BackupConfig    
      echo "BlockList_NoTrack = 0" >> $ConfigFile
      echo "BlockList_TLD = 0" >> $ConfigFile
      echo "Status = Stop" >> $ConfigFile
      ;;
    100)
      echo "Switching from Paused to Stop"
      sed -i "s/^\(Status *= *\).*/\1Stop/" $ConfigFile
      ;;
    101)
      echo "NoTrack blocking already Disabled"
      return 101
      ;;
    102)
      echo "Old config exists, but status unknown"
      exit 102
      ;;
    103)
      echo "NoTrack already running"
      exit 103
      ;;
  esac
  
  #echo "Running NoTrack to disable Blocking"
  #echo
  #/usr/local/sbin/notrack
  echo "Deleting NoTrack block list"
  DeleteOldFile "$BlockingListFile"
  echo "Restarting Dnsnmasq"
  service dnsmasq restart                          #Restart dnsmasq
}
#Main----------------------------------------------------------------

if [ "$1" ]; then                         #Have any arguments been given
  if ! Options=$(getopt -o hdsp: -l help,stop,start,status,pause: -- "$@"); then
    # something went wrong, getopt will put out an error message for us
    exit 1
  fi

  set -- $Options

  while [ $# -gt 0 ]
  do
    case $1 in
      -h| --help) 
        ShowHelp 
        ;;
      -d|--stop)
        Stop
        ;;
      -s|--start) 
        Start
        ;;      
      -p|--pause)
        PauseTime=$(sed "s/'//g" <<< "$2")       #Remove single quotes
        Pause
        shift
        ;;
      --status)
        ShowStatus
        ;;
      (--) shift; break;;
      (-*) echo "$0: error - unrecognized option $1" 1>&2; exit 6;;
      (*) break;;
    esac
    shift
  done
else                                             #No commands passed
  echo "Checking status of NoTrack"
  #No instructions given by user, the following will happen based on the result of GetStatus
  #a. Status Nothing - Pause for 15 Minutes
  #b. Status Unknown - Run Start, in order to push Config file back in
  #c. Status Paused - Unpause
  #d. Status Stopped - Start
  
  GetStatus
  case $? in
    0)
      PauseTime=15
      Pause
      ;;    
    100)
      echo "Unpausing NoTrack"
      Start
      ;;
    101)
      echo "Enabling NoTrack"
      Start
      ;;
    102)
      echo "Status unknown. Forcing old Config back into use"
      Start
      ;;
  esac  
fi


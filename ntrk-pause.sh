#!/bin/bash
#Title : NoTrack Pause
#Description : 
#Author : QuidsUp
#Date : 2015-02-23
#Usage : 

#Settings (Leave these alone)----------------------------------------
ConfigFile="/etc/notrack/notrack.conf"
OldConfig="/etc/notrack/old.conf"

#Global Variables----------------------------------------------------
PauseTime=0

#Backup Config-------------------------------------------------------
BackupConfig() {
  #1. Check if Config file exists
  #2. If it does then:
  #  2a. Copy Config to a temp file
  #  2b. Zero out Config file
  #  2c. Read temp file, and copy each line to Config if it doesn't start with "BlockList" (This way we can retain the users old config)
  
   
  if [ -e "$ConfigFile" ]; then    
    echo "Copying $ConfigFile to $OldConfig"
    cp "$ConfigFile" "$OldConfig"
    
    cat /dev/null > $ConfigFile                  #Empty config file
    
    echo "Reading temporary Config file"
    while IFS=$'\n' read -r Line _
    do
      if [[ ${Line:0:9} != "BlockList" ]]; then  #Exclude Blocklist lines
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
CheckRoot() {
  if [[ "$(id -u)" != "0" ]]; then
    echo "Error this script must be run as root"
    exit 2
  fi
  
  if [[ $(pgrep ntrk-pause | head -n 1) != "$$" ]]; then
    echo "Ending ntrk-pause process $(pgrep ntrk-pause | head -n 1)"
    kill -9 "$(pgrep ntrk-pause | head -n 1)"
  fi
}
#GetStatus-----------------------------------------------------------
GetStatus() {
#? 0 = NoTrack Running with Blocking Enabled
#? 2 = Error Unknown Status
#? 3 = NoTrack Running
#? 100 = Blocking Paused
#? 101 = Blocking Disabled
  if [ -e "$OldConfig" ]; then
    if [ -e "$ConfigFile" ]; then
      if [[ $(cat "$ConfigFile" | grep "Status = Paused") != "" ]]; then
        return 100
      elif [[ $(cat "$ConfigFile" | grep "Status = Stop") != "" ]]; then
        return 101
      fi
    else
      return 2
    fi
  else 
    return 0
  fi
}
#Pause---------------------------------------------------------------
Pause() {
  
  
  #3. If Config doesn't exist, then Create a new file
  #4. Write lines into config disabling NoTrack & TLD BlockLists (These are the only two enabled by default in NoTrack)
  #5. Run NoTrack
  #6. Sleep
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
    2)
      echo "Old config exists, but status unknown"
      exit 2
      ;;
    100)
      echo "Changing Pause time"
      sed -i "s/^\(Status *= *\).*/\1Paused$UnPauseTime/" $ConfigFile
      ;;
    101)
      echo "Switching from Disabled to Paused"
      sed -i "s/^\(Status *= *\).*/\1Paused$UnPauseTime/" $ConfigFile
      ;;
  esac
  
  echo "Running NoTrack to disable blocking"
  echo
  notrack
  
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
  notrack
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
  echo
  exit 0
}
#Start---------------------------------------------------------------
Start() {
  CheckRoot
    
  if [ -e "$OldConfig" ]; then
    echo "Copying $OldConfig to $ConfigFile"
    mv "$OldConfig" "$ConfigFile"
  elif [[ $(cat "$ConfigFile" | grep Status) != "" ]]; then
    echo "Deleting Config file and resuming default values"
    rm "$ConfigFile"    
  else
    echo "NoTrack blocking already enabled"
  fi
  
  echo "Running NoTrack to enable blocking"
  echo
  notrack
  echo
}
#Stop----------------------------------------------------------------
Stop() {
  CheckRoot

  GetStatus
  
  case $? in
    0)
      BackupConfig    
      echo "BlockList_NoTrack = 0" >> $ConfigFile
      echo "BlockList_TLD = 0" >> $ConfigFile
      echo "Status = Stop" >> $ConfigFile
      ;;
    2)
      echo "Old config exists, but status unknown"
      exit 2
      ;;
    100)
      echo "Switching from Paused to Stop"
      sed -i "s/^\(Status *= *\).*/\1Stop/" $ConfigFile
      ;;
    101)
      echo "NoTrack blocking already Disabled"
      return 1
      ;;
  esac
  
  echo "Running NoTrack to disable Blocking"
  echo
  notrack
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
        PauseTime=$(sed "s/'//g" <<< "$2")
        Pause        
        shift
        ;;
      --status)
        GetStatus
        echo "Status $?"
        ;;
      (--) shift; break;;
      (-*) echo "$0: error - unrecognized option $1" 1>&2; exit 1;;
      (*) break;;
    esac
    shift
  done
else                                             #No commands passed
  echo "Checking status of NoTrack"
  GetStatus
  case $? in
    0)
      PauseTime=15
      Pause
      ;;
    2)
      echo "Status unknown. Forcing old Config back into use"
      Start
      ;;
    100)
      echo "Unpausing NoTrack"
      Start
      ;;
    101)
      echo "Enabling NoTrack"
      Start
      ;;
  esac  
fi


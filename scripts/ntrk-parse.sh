#!/bin/bash 
#Title : NoTrack Live DNS Log Archiver
#Description : Loads contents of /var/log/notrack.log into "live" SQL DB
#Author : QuidsUp
#Date Created : 03 October 2016
#Usage : sudo ntrk-parse

#process_todaylog can take a long time to run. In order to prevent loss of DNS queries
#the log file is loaded into an array, and then immediately zeroed out.
#Processing is done on the array from memory
#Between 04:00 to 04:20 Live table is copied to Historic table
#For systems not running 24/7 Live table is copied after data is over 1 day old

#######################################
# Constants
#######################################
readonly FILE_DNSLOG="/var/log/notrack.log"
readonly FILE_CONFIG="/etc/notrack/notrack.conf"
readonly MAXAGE=88000                            #Just over 1 day in seconds
readonly MINLINES=50
readonly VERSION="0.8"

readonly USER="ntrk"
readonly PASSWORD="ntrkpass"
readonly DBNAME="ntrkdb"

#######################################
# Global Variables
#######################################
declare -a logarray
simpleurl=""
datestr="$(date +"%Y-%m-%d")"

declare -A commonsites
commonsites["cloudfront.net"]=true
commonsites["googleusercontent.com"]=true
commonsites["googlevideo.com"]=true
commonsites["cedexis-radar.net"]=true
commonsites["gvt1.com"]=true
commonsites["deviantart.net"]=true
commonsites["deviantart.com"]=true
commonsites["tumblr.com"]=true


#--------------------------------------------------------------------
# Check If Running as Root and if Script is already running
#
# Globals:
#   None
# Arguments:
#   None
# Returns:
#   None
#--------------------------------------------------------------------
function check_root() {
  local Pid=""
  Pid=$(pgrep ntrk-parse | head -n 1)            #Get PID of first notrack process

  if [[ "$(id -u)" != "0" ]]; then
    echo "This script must be run as root"
    exit 5
  fi
  
  #Check if another copy of notrack is running
  if [[ $Pid != "$$" ]] && [[ -n $Pid ]] ; then  #$$ = This PID    
    echo "ntrk-parse already running under Pid $Pid"
    exit 111
  fi
}

#--------------------------------------------------------------------
# Copy Live Table to Historic
#
# Globals:
#   USER, PASSWORD, DBNAME
# Arguments:
#   None
# Returns:
#   None
#--------------------------------------------------------------------
function copy_table() {
  mysql -sN --user="$USER" --password="$PASSWORD" -D "$DBNAME" -e "INSERT INTO historic SELECT NULL,log_time,sys,dns_request,dns_result FROM live ORDER BY log_time;"
  
  if [ $? == 0 ]; then
    echo "Successfully copied Live table to Historic"
  else
    echo "Error $? failed to copy Live table"
  fi
}

#--------------------------------------------------------------------
# Check Log Age
#   Query timestamp of first value in Live table
#
# Globals:
#   USER, PASSWORD, DBNAME
# Arguments:
#   None
# Returns:
#   0 - In date
#   >0 - Number of days old
#--------------------------------------------------------------------
function check_logage() {
  local log_time=""
  local log_epoch=0
  local unixtime=0
  
  log_time=$(mysql -sN --user="$USER" --password="$PASSWORD" -D "$DBNAME" -e "SELECT log_time FROM live ORDER BY log_time LIMIT 1;")
  #echo "Log Time:$log_time"
  
  if [[ $log_time == "" ]]; then                 #Anything returned? CHECK THIS VALUE
    echo "No log time found"
    return 0                                     #Error, but treat as 0 - ok
  fi
  
  log_epoch=$(date +"%s" -d "$log_time")         #Convert YYYY-MM-DD hh:mm:ss to epoch
  unixtime=$(date +"%s")                         #Get current epoch time
    
  if [ $((unixtime-log_epoch)) -gt $MAXAGE ]; then         #Check age
    if [ "$(((unixtime-log_epoch)/86400))" -gt 254 ]; then #Avoid error values > 254
      return 254
    fi
    return "$(((unixtime-log_epoch)/86400))"     #Return value is days
  fi
  
  return 0                                       #Otherwise return 0 - ok
}


#--------------------------------------------------------------------
# Delete Live DB
#   1. Delete all rows in the Live Table
#   2. Reset Counter
#
# Globals:
#   USER, PASSWORD, DBNAME
# Arguments:
#   None
# Returns:
#   None
#--------------------------------------------------------------------
function delete_live() {
  echo "DELETE LOW_PRIORITY FROM live;" | mysql --user="$USER" --password="$PASSWORD" -D "$DBNAME"
  echo "ALTER TABLE live AUTO_INCREMENT = 1;" | mysql --user="$USER" --password="$PASSWORD" -D "$DBNAME"
}

#--------------------------------------------------------------------
# Load Config File
#   1. Read SQL password (future) and Suppress from Config
#   2. Explode values of Suppress into a Temp array, then add to commonsites
#
# Globals:
#   commonsites
# Arguments:
#   None
# Returns:
#   None
#--------------------------------------------------------------------
function load_config() {
  local suppress_str=""
  local url=""
  local -a temp

  if [ ! -e "$FILE_CONFIG" ]; then
    echo "Config $FILE_CONFIG missing"
    return
  fi
  
  echo "Reading Config File"
  while IFS='= ' read -r key value               #Seperator '= '
  do
    if [[ ! $key =~ ^\ *# && -n $key ]]; then
      value="${value%%\#*}"    # Del in line right comments
      value="${value%%*( )}"   # Del trailing spaces
      value="${value%\"*}"     # Del opening string quotes 
      value="${value#\"*}"     # Del closing string quotes 
        
      case "$key" in
        Suppress) suppress_str="$value";;        
      esac            
    fi
  done < $FILE_CONFIG  
  
  unset IFS
    
  IFS=',' read -ra temp <<< "${suppress_str}"    #Explode string into temp array
  unset IFS
  for url in "${temp[@]}"; do                    #Read each item of temp array
    commonsites[$url]=true                       #Add users Config[Suppress] to commonsites
  done  
}


#--------------------------------------------------------------------
# Load Log file into array
#   This function is used to ensure that losses are minimised while we process notrack.log
#   1. Read notrack.log and add values into logarray
#   2. Empty log file

# Globals:
#   logarray
# Arguments:
#   None
# Returns:
#   None
#--------------------------------------------------------------------
function load_todaylog() {
  echo "Reading log file into array"
  while IFS=$'\n' read -r line
  do
    logarray+=("$line")
  done < "$FILE_DNSLOG"
    
  cat /dev/null > "$FILE_DNSLOG"                 #Empty log file
}


#--------------------------------------------------------------------
# Process Today Log
#   1. Read each line of logarray and pattern match with regex 
#   2. Add queries to querylist and systemlist arrays
#   3. Find what happened to each query
#   4. Build string for SQL entry
#   5. Echo result into SQL
# Globals:
#   logarray, simpleurl, USER, PASSWORD, DBNAME
# Arguments:
#   None
# Returns:
#   None
#--------------------------------------------------------------------

#Dnsmasq log line consists of:
#0 - Month (3 characters)
#1 - Day (d or dd)
#2 - Time (dd:dd:dd) - Group 1
#3 - dnsmasq[d{1-6}]
#4 - Function (query, forwarded, reply, cached, config) - Group 2
#5 - Query Type [A/AAA] - Group 3
#5 - Website Requested - Group 4
#6 - Action (is|to|from) - Group 5
#7 - Return value - Group 6

function process_todaylog() {
  local dedup_answer=""
  local line=""
  local -A querylist
  local -A systemlist
  local url=""
  local dns_result=""  

  echo "Processing log file"
    
  for line in "${logarray[@]}"; do
    if [[ $line =~ ^[A-Z][a-z][a-z][[:space:]][[:space:]]?[0-9]{1,2}[[:space:]]([0-9]{2}\:[0-9]{2}\:[0-9]{2})[[:space:]]dnsmasq\[[0-9]{1,6}\]\:[[:space:]](query|reply|config|\/etc\/localhosts\.list)(\[[A]{1,4}\])?[[:space:]]([A-Za-z0-9\.\-]+)[[:space:]](is|to|from)[[:space:]](.*)$ ]]; then
      url="${BASH_REMATCH[4]}"
      
      if [[ ${BASH_REMATCH[2]} == "query" ]]; then
        if [[ ${BASH_REMATCH[3]} == "[A]" ]]; then         #Only IPv4 (prevent double)
          querylist[$url]="${BASH_REMATCH[1]}"             #Add time to query array
          systemlist[$url]="${BASH_REMATCH[6]}"            #Add IP to system array
        fi      
      elif [[ $url != "$dedup_answer" ]]; then   #Simplify processing of multiple IP addresses returned
        dedup_answer="$url"                      #Deduplicate answer
        if [ "${querylist[$url]}" ]; then        #Does answer match a query?
          if [[ ${BASH_REMATCH[2]} == "reply" ]]; then dns_result="A"    #Allowed
          elif [[ ${BASH_REMATCH[2]} == "config" ]]; then dns_result="B" #Blocked
          elif [[ ${BASH_REMATCH[2]} == "/etc/localhosts.list" ]]; then dns_result="L"
          fi
          
          simplify_url "$url"                    #Simplify with commonsites
          
          if [[ $simpleurl != "" ]]; then        #Add row into SQL Table
            echo "INSERT INTO live (id,log_time,sys,dns_request,dns_result) VALUES ('null','$datestr ${querylist[$url]}', '${systemlist[$url]}', '$simpleurl', '$dns_result')" | mysql --user="$USER" --password="$PASSWORD" -D "$DBNAME"
          fi
                    
          unset querylist[$url]                  #Delete value from querylist
          unset systemlist[$url]                 #Delete value from system list
        fi      
      fi
    fi
  done
  unset IFS  
}

#--------------------------------------------------------------------
# Show Log Age
#   Echos result of check_logage
#
# Globals:
#   None
# Arguments:
#   $1 - Age of earliest entry in Live
# Returns:
#   None
#--------------------------------------------------------------------
function show_logage() {
  if [ "$1" == 0 ]; then echo "In date"
  else
    echo "Out of date: $1 Days old"
  fi
}

#--------------------------------------------------------------------
#Show Help
function show_help() {
  echo "NoTrack DNS Log Parser"
  echo "Usage: sudo ntrk-parse"
  echo
  echo "The following options can be specified:"
  echo -e "  -a, --age\tCheck Age of Live table"
  echo -e "  -c, --copy\tCopy Live table to Historic table"
  echo -e "  -d, --delete\tDelete contents of Live table"
  echo -e "  -h, --help\tThis Help"
  echo -e "  -v, --version\tDisplay version number"
}
#--------------------------------------------------------------------
#Show Version
function show_version() {
  echo "NoTrack live DNS Archiver v$VERSION"
  echo
}

#--------------------------------------------------------------------
# Simplify URL
#   1: Drop www (its unnecessary and not all websites use it now)
#   2. Extract domain.tld, including double-barrelled domains
#   3. Check if site is to be suppressed (present in commonsites)
# Globals:
#   simpleurl
#   commonsites
# Arguments:
#   $1 - URL To Simplify
# Returns:
#   via simpleurl global variable
#-------------------------------------------------------------------- 
function simplify_url() {
  local baseurl=""
  simpleurl=""
  
  baseurl="$1"
    
  if [[ ${baseurl:0:4} == "www." ]]; then
    baseurl="${baseurl:4}"
  fi
  
  if [[ $baseurl =~ [A-Za-z0-9\-]{2,63}\.(gov\.|org\.|co\.|com\.)?[A-Za-z0-9\-]{2,63}$ ]]; then
    if [ ${commonsites[${BASH_REMATCH[0]}]} ]; then
      simpleurl="*.${BASH_REMATCH[0]}"
    else
      simpleurl="$baseurl"
    fi
  fi 
}

#--------------------------------------------------------------------
#Main
if [ "$1" ]; then                                #Have any arguments been given
  if ! options="$(getopt -o acdhv -l age,copy,delete,help,version -- "$@")"; then
    # something went wrong, getopt will put out an error message for us
    exit 6
  fi

  set -- $options

  while [ $# -gt 0 ]
  do
    case $1 in
      -a|--age)
        check_logage
        show_logage $?
        exit 0
      ;;
      -c|--copy)
        copy_table
        exit 0
      ;;      
      -d|--delete)
        delete_live
        exit 0
      ;;
      -h|--help)
        show_help
        exit 0
      ;;
      -v|--version) 
        show_version
        exit 0
      ;;
      (--) 
        shift
        break
      ;;
      (-*)         
        echo "$0: error - unrecognized option $1"
        exit 6
      ;;
      (*) 
        break
      ;;
    esac
    shift
  done
fi

#Between 04:00 - 04:20 Its time to copy Live to Historic
if [ "$(date +'%H')" == 4 ]; then    
  if [ "$(date +'%M')" -lt 20 ]; then
    copy_table                                   #Copy Live to Historic
    delete_live
    exit 112                                     #No rush to parse log right now
  fi
fi

#Alternate option to anyone not running their system 24/7
check_logage                                     #Is Live older than MAXAGE?
if [ $? -gt 0 ]; then                            #More than 0 is age in days
  copy_table                                     #Copy Live to Historic
  delete_live
  sleep 2s
fi

if [ "$(wc -l "$FILE_DNSLOG" | cut -d " " -f 1)" -lt $MINLINES ]; then
  echo "Not much in $FILE_DNSLOG, exiting"
  exit 110
fi

check_root                                       #Are we running as root?
load_config                                      #Load users config
load_todaylog                                    #Load log file into array
process_todaylog                                 #Process and add log to SQL table


#!/bin/bash 
#Title : NoTrack Live DNS Log Archiver
#Description : Loads contents of /var/log/notrack.log into "live" SQL DB
#Author : QuidsUp
#Date Created : 03 October 2016
#Usage : live.sh

#process_todaylog can take a long time to run. In order to prevent loss of DNS queries
#the log file is loaded into an array, and then immediately zeroed out.
#Processing is done on the array from memory

#######################################
# Constants
#######################################
readonly FILE_DNSLOG="/var/log/notrack.log"
readonly FILE_CONFIG="/etc/notrack/notrack.conf"
readonly VERSION="0.8"

readonly USER="ntrk"
readonly PASSWORD="ntrkpass"
readonly DBNAME="ntrkdb"

#######################################
# Global Variables
#######################################
declare -a logarray
declare -a processedlog
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
# Delete Live DB
#   1. Delete all rows in the Live Table
#   2. Reset Counter
#
# Globals:
#   None
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
    if [[ ! key =~ ^\ *# && -n $key ]]; then
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
}


#--------------------------------------------------------------------
# Process Today Log
#   1. Read each line of logarray and pattern match with regex 
#   2. Add queries to querylist and systemlist arrays
#   3. Find what happened to each query
#   4. Build string for SQL entry
#   5. Echo result into SQL
# Globals:
#   SiteList
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
  local result=""  

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
          if [[ ${BASH_REMATCH[2]} == "reply" ]]; then result="A"    #Allowed
          elif [[ ${BASH_REMATCH[2]} == "config" ]]; then result="B" #Blocked
          elif [[ ${BASH_REMATCH[2]} == "/etc/localhosts.list" ]]; then result="L"
          fi
          
          simplify_url "$url"                    #Simplify with commonsites
          
          if [[ $simpleurl != "" ]]; then        #Add row into SQL Table
            echo "INSERT INTO live (id,log_time,system,dns_request,result) VALUES ('null','$datestr ${querylist[$url]}', '${systemlist[$url]}', '$simpleurl', '$result')" | mysql --user="$USER" --password="$PASSWORD" -D "$DBNAME"
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
#   $1 URL To Simplify
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
if [ "$1" ]; then                                #Have any arguments been given
  if ! options="$(getopt -o dv -l delete,version -- "$@")"; then
    # something went wrong, getopt will put out an error message for us
    exit 6
  fi

  set -- $options

  while [ $# -gt 0 ]
  do
    case $1 in      
      -v|--version) 
        show_version
        exit 0
      ;;
      -d|--delete)
        delete_live
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

load_config                                      #Load users config
load_todaylog                                    #Load log file into array
process_todaylog                                 #Process and add log to SQL table



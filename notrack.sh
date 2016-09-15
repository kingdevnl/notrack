#!/bin/bash
#Title : NoTrack
#Description : This script will download latest Adblock Domain block files from quidsup.net, then parse them into Dnsmasq.
#Script will also create quick.lists for use by stats.php web page
#Author : QuidsUp
#Date : 2015-01-14
#Usage : sudo bash notrack.sh

#User Configerable Settings (in case config file is missing)---------
#Set NetDev to the name of network device e.g. "eth0" IF you have multiple network cards
NetDev=$(ip -o link show | awk '{print $2,$9}' | grep ": UP" | cut -d ":" -f 1)

#If NetDev fails to recognise a Local Area Network IP Address, then you can use IPVersion to assign a custom IP Address in /etc/notrack/notrack.conf
#e.g. IPVersion = 192.168.1.2
IPVersion="IPv4"

declare -A Config                                #Config array for Block Lists
Config[bl_custom]=""
Config[bl_notrack]=1
Config[bl_tld]=1
Config[bl_qmalware]=1
Config[bl_hexxium]=1
Config[bl_disconnectmalvertising]=0
Config[bl_easylist]=0
Config[bl_easyprivacy]=0
Config[bl_fbannoyance]=0
Config[bl_fbenhanced]=0
Config[bl_fbsocial]=0
Config[bl_hphosts]=0
Config[bl_malwaredomainlist]=0
Config[bl_malwaredomains]=0
Config[bl_pglyoyo]=0
Config[bl_someonewhocares]=0
Config[bl_spam404]=0
Config[bl_swissransom]=0
Config[bl_swisszeus]=0
Config[bl_winhelp2002]=0
Config[bl_areasy]=0                              #Arab
Config[bl_chneasy]=0                             #China
Config[bl_deueasy]=0                             #Germany
Config[bl_dnkeasy]=0                             #Denmark
Config[bl_ruseasy]=0                             #Russia
Config[bl_fblatin]=0                             #Portugal/Spain (Latin Countries)

#######################################
# Constants
#######################################
Version="0.7.17"
readonly CSV_BLOCKING="/etc/notrack/blocking.csv"
readonly LISTFILE_BLOCKING="/etc/dnsmasq.d/notrack.list"
readonly FILE_BLACKLIST="/etc/notrack/blacklist.txt"
readonly FILE_WHITELIST="/etc/notrack/whitelist.txt"
readonly FILE_DOMAINBLACK="/etc/notrack/domain-blacklist.txt"
readonly FILE_DOMAINWHITE="/etc/notrack/domain-whitelist.txt"
readonly FILE_QUICKLIST="/etc/notrack/domain-quick.list"
readonly CSV_DOMAIN="/var/www/html/admin/include/tld.csv"
readonly FILE_CONFIG="/etc/notrack/notrack.conf"
readonly CHECKTIME=343800                        #Time in Seconds between downloading lists (4 days - 30mins)

declare -A URLList                               #Array of URL's
URLList[notrack]="https://raw.githubusercontent.com/quidsup/notrack/master/trackers.txt"
URLList[qmalware]="https://raw.githubusercontent.com/quidsup/notrack/master/malicious-sites.txt"
URLList[hexxium]="https://hexxiumcreations.github.io/threat-list/hexxiumthreatlist.txt"
URLList[disconnectmalvertising]="https://s3.amazonaws.com/lists.disconnect.me/simple_malvertising.txt"
URLList[easylist]="https://easylist-downloads.adblockplus.org/easylist_noelemhide.txt"
URLList[easyprivacy]="https://easylist-downloads.adblockplus.org/easyprivacy.txt"
URLList[fbannoyance]="https://easylist-downloads.adblockplus.org/fanboy-annoyance.txt"
URLList[fbenhanced]="https://www.fanboy.co.nz/enhancedstats.txt"
URLList[fbsocial]="https://secure.fanboy.co.nz/fanboy-social.txt"
URLList[hphosts]="http://hosts-file.net/ad_servers.txt"
URLList[malwaredomainlist]="http://www.malwaredomainlist.com/hostslist/hosts.txt"
URLList[malwaredomains]="http://mirror1.malwaredomains.com/files/justdomains"
#URLList[securemecca]="http://securemecca.com/Downloads/hosts.txt"
URLList[spam404]="https://raw.githubusercontent.com/Dawsey21/Lists/master/adblock-list.txt"
URLList[swissransom]="https://ransomwaretracker.abuse.ch/downloads/RW_DOMBL.txt"
URLList[swisszeus]="https://zeustracker.abuse.ch/blocklist.php?download=domainblocklist"
URLList[pglyoyo]="http://pgl.yoyo.org/adservers/serverlist.php?hostformat=;mimetype=plaintext"
URLList[someonewhocares]="http://someonewhocares.org/hosts/hosts"
URLList[winhelp2002]="http://winhelp2002.mvps.org/hosts.txt"
URLList[areasy]="https://easylist-downloads.adblockplus.org/Liste_AR.txt"
URLList[chneasy]="https://easylist-downloads.adblockplus.org/easylistchina.txt"
URLList[deueasy]="https://easylist-downloads.adblockplus.org/easylistgermany.txt"
URLList[dnkeasy]="https://adblock.dk/block.csv"
URLList[ruseasy]="https://easylist-downloads.adblockplus.org/ruadlist+easylist.txt"
URLList[fblatin]="https://www.fanboy.co.nz/fanboy-espanol.txt"

#######################################
# Global Variables
#######################################
FileTime=0                                       #Return value from Get_FileTime
Force=0                                          #Force update block list
OldLatestVersion="$Version"
UnixTime=$(date +%s)                             #Unix time now
JumpPoint=0                                      #Percentage increment
PercentPoint=0                                   #Number of lines to loop through before a percentage increment is hit
declare -A WhiteList                             #associative array for referencing sites in White List
declare -a CSVList                               #Array to store each list in CSV form
declare -A DomainList                            #Array to check if TLD blocked
declare -A SiteList                              #Array to store sites being blocked
declare -i Dedup=0                               #Count of Deduplication

#--------------------------------------------------------------------
# Error Exit
#
# Globals:
#   None
# Arguments:
#  $1 = Error Message
#  $2 = Exit Code
# Returns:
#   None
#--------------------------------------------------------------------
function Error_Exit() {  
  echo "Error. $1"
  echo "Aborting"
  exit "$2"
}

#--------------------------------------------------------------------
# Create File
# Checks if a file exists and creates it
#
# Globals:
#   None
# Arguments:
#   #$1 File to create
# Returns:
#   None
#--------------------------------------------------------------------
function CreateFile() {  
  if [ ! -e "$1" ]; then                         #Does file already exist?
    echo "Creating file: $1"
    touch "$1"                                   #If not then create it
  fi
}

#--------------------------------------------------------------------
# Delete Old File
# Checks if a file exists and then deletes it
#
# Globals:
#   None
# Arguments:
#   #$1 File to delete
# Returns:
#   None
#--------------------------------------------------------------------
function DeleteOldFile() {  
  if [ -e "$1" ]; then                           #Does file exist?
    echo "Deleting file $1"
    rm "$1"                                      #If yes then delete it
  fi
}

#--------------------------------------------------------------------
# Add Site to List
# Checks whether a Site is in the Users whitelist or has previously been added
#
# Globals:
#   DomainList
#   WhiteList
#   Dedup
# Arguments:
#   $1 Site to Add
#   $2 Comment
# Returns:
#   None
#--------------------------------------------------------------------
function AddSite() {
  local Site="$1"
  
  if [[ $Site =~ ^www\. ]]; then                 #Drop www.
    Site="${Site:4}"
  fi
  
  #Ignore Sub domain
  #Group 1 Domain: A-Z,a-z,0-9,-  one or more
  # .
  #Group 2 (Double-barrelled TLD's) : org. | co. | com.  optional
  #Group 3 TLD: A-Z,a-z,0-9,-  one or more
  
  if [[ $Site =~ ([A-Za-z0-9\-]+)\.(org\.|co\.|com\.)?([A-Za-z0-9\-]+)$ ]]; then
    if [ "${DomainList[.${BASH_REMATCH[3]}]}" ]; then  #Drop if .domain is in TLD
      #echo "Dedup TLD $Site"                    #Uncomment for debugging
      ((Dedup++))
      return 0
    fi
    
    if [ "${SiteList[${BASH_REMATCH[1]}.${BASH_REMATCH[2]}${BASH_REMATCH[3]}]}" ]; then  #Drop if sub.site.domain has been added
      #echo "Dedup Domain $Site"                 #Uncomment for debugging
      ((Dedup++))
      return 0
    fi
    
    if [ "${SiteList[$Site]}" ]; then            #Drop if sub.site.domain has been added
      #echo "Dedup Duplicate Sub $Site"          #Uncomment for debugging
      ((Dedup++))
      return 0
    fi
  
    if [ "${WhiteList[$Site]}" ] || [ "${WhiteList[${BASH_REMATCH[1]}.${BASH_REMATCH[2]}${BASH_REMATCH[3]}]}" ]; then                 #Is sub.site.domain or site.domain in whitelist?    
      CSVList+=("$Site,Disabled,$2")             #Add to CSV as Disabled      
    else                                         #No match in whitelist
      CSVList+=("$Site,Active,$2")               #Add to CSV as Active
      SiteList[$Site]=true                       #Add site into SiteList array
    fi
  #else
    #echo "Invalid site $Site"
  fi  
}

#--------------------------------------------------------------------
# Calculate Percent Point in list files
#   1. Count number of lines in file with "wc"
#   2. Calculate Percentage Point (number of for loop passes for 1%)
#   3. Calculate Jump Point (increment of 1 percent point on for loop)
#   E.g.1 20 lines = 1 for loop pass to increment percentage by 5%
#   E.g.2 200 lines = 2 for loop passes to increment percentage by 1%
#
# Globals:
#   PercentPoint
#   JumpPoint
# Arguments:
#   $1 = File to Calculate
# Returns:
#   None
#--------------------------------------------------------------------
function CalculatePercentPoint() {  
  local NumLines=0
  
  NumLines=$(wc -l "$1" | cut -d " " -f 1)       #Count number of lines
  if [ "$NumLines" -ge 100 ]; then
    PercentPoint=$((NumLines/100))
    JumpPoint=1
  else
    PercentPoint=1
    JumpPoint=$((100/NumLines))
  fi
}

#--------------------------------------------------------------------
# Check Version of Dnsmasq
#
# Globals:
#   None
# Arguments:
#   None
# Returns:
#   50. Dnsmasq Missing
#   51. Dnsmasq Version Unknown
#   52. Dnsmasq doesn't support whitelisting (below 2.75)
#   53. Dnsmasq supports whitelisting (2.75 and above)#   
#--------------------------------------------------------------------
function CheckDnsmasqVer() {
  if [ -z "$(command -v dnsmasq)" ]; then
    return 50
  fi
  
  local VerStr=""
  VerStr="$(dnsmasq --version)"                  #Get version from dnsmasq
  
  #The return is very wordy, so we need to extract the relevent info
  [[ $VerStr =~ ^Dnsmasq[[:space:]]version[[:space:]]([0-9]\.[0-9]{1,2}) ]]
  
  local VerNo="${BASH_REMATCH[1]}"               #Extract version number from string
  if [[ -z $VerNo ]]; then                       #Was anything extracted?
    return 51
  else
    [[ $VerNo =~ ([0-9])\.([0-9]{1,2}) ]]
    if [ "${BASH_REMATCH[1]}" -eq 2 ] && [ "${BASH_REMATCH[2]}" -ge 75 ]; then  #Version 2.75 onwards
      return 53
    elif [ "${BASH_REMATCH[1]}" -ge 3 ]; then    #Version 3 onwards
      return 53
    else                                         #2.74 or below
      return 52
    fi
  fi
}

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
  Pid=$(pgrep notrack | head -n 1)               #Get PID of first notrack process

  if [[ "$(id -u)" != "0" ]]; then
    Error_Exit "This script must be run as root" "5"    
  fi
  
  #Check if another copy of notrack is running
  if [[ $Pid != "$$" ]] && [[ -n $Pid ]] ; then  #$$ = This PID    
    Error_Exit "NoTrack already running under Pid $Pid" "8"
  fi
}

#--------------------------------------------------------------------
# Count number of lines in /etc/dnsmasq.d block lists
#
# Globals:
#   None
# Arguments:
#   None
# Returns:
#   None
#--------------------------------------------------------------------
function CountLines() {
  local ListFile=""
  local LineCount=0
  
  for ListFile in /etc/dnsmasq.d/*.list; do
    let "LineCount += $(wc -l "$ListFile" | cut -d\  -f 1)"
  done
  
  echo "$LineCount"
}

#Generate BlackList--------------------------------------------------
function Generate_BlackList() {
  local -a Tmp                                   #Local array to build contents of file
  
  echo "Creating blacklist"
  touch "$FILE_BLACKLIST"
  Tmp+=("#Use this file to create your own custom block list")
  Tmp+=("#Run notrack script (sudo notrack) after you make any changes to this file")
  Tmp+=("#doubleclick.net")
  Tmp+=("#googletagmanager.com")
  Tmp+=("#googletagservices.com")
  printf "%s\n" "${Tmp[@]}" > $FILE_BLACKLIST     #Write Array to file with line seperator
}
#Generate WhiteList--------------------------------------------------
function Generate_WhiteList() {
  local -a Tmp                                   #Local array to build contents of file
  
  echo "Creating whitelist"
  touch "$FILE_WHITELIST"
  Tmp+=("#Use this file to remove sites from block list")
  Tmp+=("#Run notrack script (sudo notrack) after you make any changes to this file")
  Tmp+=("#doubleclick.net")
  Tmp+=("#google-analytics.com")
  printf "%s\n" "${Tmp[@]}" > $FILE_WHITELIST     #Write Array to file with line seperator
}
#Get IP Address of System--------------------------------------------
function Get_IPAddress() {
  #A manual IP address can be assigned using IPVersion
  if [ "$IPVersion" == "IPv4" ]; then
    echo "Internet Protocol Version 4 (IPv4)"
    echo "Reading IPv4 Address from $NetDev"
    IPAddr=$(ip addr list "$NetDev" | grep inet | head -n 1 | cut -d ' ' -f6 | cut -d/ -f1)
    
  elif [ "$IPVersion" == "IPv6" ]; then
    echo "Internet Protocol Version 6 (IPv6)"
    echo "Reading IPv6 Address"
    IPAddr=$(ip addr list "$NetDev" | grep inet6 | head -n 1 | cut -d ' ' -f6 | cut -d/ -f1)
  else
    echo "Custom IP Address used"
    IPAddr="$IPVersion";                         #Use IPVersion to assign a manual IP Address
  fi
  echo "System IP Address: $IPAddr"
  echo
}
#Get File Time-------------------------------------------------------
function Get_FileTime() {
  #$1 = File to be checked
  if [ -e "$1" ]; then                           #Does file exist?
    FileTime=$(stat -c %Z "$1")                  #Return time of last status change, seconds since Epoch
  else
    FileTime=0                                   #Otherwise retrun 0
  fi
}

#Custom BlackList----------------------------------------------------
function GetList_BlackList() {
  echo "Processing Custom Black List"
  CSVList=()
  Process_PlainList "$FILE_BLACKLIST"
    
  if [ ${#CSVList[@]} -gt 0 ]; then              #Are there any URL's in the block list?
    printf "%s\n" "${CSVList[@]}" > "/etc/notrack/custom.csv"
    cat /etc/notrack/custom.csv >> "$CSV_BLOCKING"
  else
    DeleteOldFile "/etc/notrack/custom.csv"
  fi
  echo "Finished processing Custom Black List"
  echo  
}
#Get Custom List-----------------------------------------------------
function Get_Custom() {
  local -A CustomListArray
  local CSVFile=""
  local DLFile=""
  local DLFileTime=0                             #Downloaded File Time
  local CustomCount=1                            #For displaying count of custom list
    

  if [[ ${Config[bl_custom]} == "" ]]; then      #Are there any custom block lists?
    echo "No Custom Block Lists in use"
    echo
    for FileName in /etc/notrack/custom_*; do    #Clean up old custom lists
      FileName=${FileName##*/}                   #Get filename from path
      FileName=${FileName%.*}                    #Remove file extension
      DeleteOldFile "/etc/dnsmasq.d/$FileName.list"
      DeleteOldFile "/etc/notrack/$FileName.csv"
      DeleteOldFile "/tmp/$FileName.txt"
    done
    return
  fi
  
  echo "Processing Custom Block Lists"
  #Split comma seperated list into individual URL's
  IFS=',' read -ra CustomList <<< "${Config[bl_custom]}"
  for ListUrl in "${CustomList[@]}"; do
    echo "$CustomCount: $ListUrl"
    FileName=${ListUrl##*/}                      #Get filename from URL
    FileName=${FileName%.*}                      #Remove file extension
    DLFile="/tmp/custom_$FileName.txt"
    CSVFile="/etc/notrack/custom_$FileName.csv"    
    CustomListArray[$FileName]="$FileName"       #Used later to find old custom lists
    
    Get_FileTime "$DLFile"                       #When was file last downloaded / copied?
    DLFileTime="$FileTime"
    
    #Detrmine whether we are dealing with a download or local file
    if [[ $ListUrl =~ ^(https?|ftp):// ]]; then  #Is URL a http(s) or ftp?
      if [ $DLFileTime -lt $((UnixTime-CHECKTIME)) ]; then #Is list older than 4 days
        echo "Downloading $FileName"      
        wget -qO "$DLFile" "$ListUrl"            #Yes, download it
      else
        echo "File in date, not downloading"
      fi
    elif [ -e "$ListUrl" ]; then                 #Is it a file on the server?        
      echo "$ListUrl File Found on system"
      Get_FileTime "$ListUrl"                    #Get date of file
      
      if [ $FileTime -gt $DLFileTime ]; then     #Is the original file newer than file in /tmp?
        echo "Copying to $DLFile"                #Yes, copy file
        cp "$ListUrl" "$DLFile"
      else
        echo "File in date, not copying"
      fi
    else                                         #Don't know what to do, skip to next file
      echo "Unable to identify what $ListUrl is"
      echo
      continue
    fi      
      
    if [ -s "$DLFile" ]; then                    #Only process if filesize > 0
      CSVList=()                                 #Zero Array
              
      #Adblock EasyList can be identified by first line of file
      Line=$(head -n1 "$DLFile")                 #What is on the first line?
      if [[ ${Line:0:13} == "[Adblock Plus" ]]; then #First line identified as EasyList
        echo "Block list identified as Adblock Plus EasyList"
        Process_EasyList "$DLFile"
      else                                       #Other, lets grab URL from each line
        echo "Processing as Custom List"
        Process_CustomList "$DLFile"
      fi
      
      if [ ${#CSVList[@]} -gt 0 ]; then          #Are there any URL's in the block list?
        CreateFile "$CSVFile"                    #Create CSV File
        printf "%s\n" "${CSVList[@]}" > "$CSVFile"  #Output array to file
        cat "$CSVFile" >> "$CSV_BLOCKING"
        echo "Finished processing $FileName"        
      else                                       #No URL's in block list
        DeleteOldFile "$CSVFile"                 #Delete CSV File        
        echo "No URL's extracted from Block list"
      fi
    else                                         #File not downloaded
      echo "Error $DLFile not found"
    fi
    
    echo
    ((CustomCount++))                            #Increase count of custom lists
  done
  
  
  for FileName in /etc/dnsmasq.d/custom_*; do    #Clean up old custom lists
    FileName=${FileName##*/}                     #Get filename from path
    FileName=${FileName%.*}                      #Remove file extension
    FileName=${FileName:7}                       #Remove custom_    
    if [ ! "${CustomListArray[$FileName]}" ]; then
      DeleteOldFile "/etc/dnsmasq.d/custom_$FileName.list"
      DeleteOldFile "/etc/notrack/custom_$FileName.csv"
    fi
  done
  
  unset IFS
}
#GetList-------------------------------------------------------------
function GetList() {
  #$1 = List to be Processed
  #$2 = Process Method
  local Lst="$1"
  local CSVFile="/etc/notrack/$1.csv"
  local DLFile="/tmp/$1.txt"
  
  #Should we process this list according to the Config settings?
  if [ "${Config[bl_$Lst]}" == 0 ]; then 
    DeleteOldFile "$CSVFile"     #If not delete the old file, then leave the function
    DeleteOldFile "$DLFile"
    return 0
  fi
  
  Get_FileTime "$DLFile"
   
  if [ $FileTime -gt $((UnixTime-CHECKTIME)) ]; then  
    echo "$Lst in date. Not downloading"    
  else  
    echo "Downloading $Lst"
    wget -qO "$DLFile" "${URLList[$Lst]}"
  fi
  
  if [ ! -s "$DLFile" ]; then                    #Check if list has been downloaded
    echo "File not downloaded"
    DeleteOldFile "$CSVFile"
    return 1
  fi
  
  CSVList=()                                     #Zero Arrays      
  echo "Processing list $Lst"                    #Inform user
  
  case $2 in                                     #What type of processing is required?
    "easylist") Process_EasyList "$DLFile" ;;
    "plain") Process_PlainList "$DLFile" ;;
    "notrack") Process_NoTrackList "$DLFile" ;;
    "tldlist") Process_TLDList ;;
    "unix") Process_UnixList "$DLFile" ;;    
    *) Error_Exit "Unknown option $2" "7"
  esac  
  
  if [ ${#CSVList[@]} -gt 0 ]; then              #Are there any URL's in the block list?
    CreateFile "$CSVFile"                        #Create CSV File    
    printf "%s\n" "${CSVList[@]}" > "$CSVFile"   #Output arrays to file    
    cat "/etc/notrack/$Lst.csv" >> "$CSV_BLOCKING"  
    echo "Finished processing $Lst"    
  else                                           #No URL's in block list
    echo "No URL's extracted from Block list"
    DeleteOldFile "$CSVFile"                     #Delete CSV File    
  fi
  
  echo
}

#--------------------------------------------------------------------
# Load Config File
#   Default values are set at top of this script
#   Config File contains Key & Value on each line for some/none/or all items
#   If the Key is found in the case, then we write the value to the Variable
#
# Globals:
#   Config
# Arguments:
#   None
# Returns:
#   None
#--------------------------------------------------------------------
function load_config() {  
  if [ ! -e "$FILE_CONFIG" ]; then
    echo "Config $FILE_CONFIG missing"
    return
  fi
  
  echo "Reading Config File"
  while IFS='= ' read -r Key Value             #Seperator '= '
  do
    if [[ ! $Key =~ ^\ *# && -n $Key ]]; then
      Value="${Value%%\#*}"    # Del in line right comments
      Value="${Value%%*( )}"   # Del trailing spaces
      Value="${Value%\"*}"     # Del opening string quotes 
      Value="${Value#\"*}"     # Del closing string quotes 
        
      case "$Key" in
        IPVersion) IPVersion="$Value";;
        NetDev) NetDev="$Value";;
        LatestVersion) OldLatestVersion="$Value";;
        bl_custom) Config[bl_custom]="$Value";;
        bl_notrack) Config[bl_notrack]="$Value";;
        bl_tld) Config[bl_tld]="$Value";;
        bl_qmalware) Config[bl_qmalware]="$Value";;
        bl_hexxium) Config[bl_hexxium]="$Value";;
        bl_disconnectmalvertising) Config[bl_disconnectmalvertising]="$Value";;          
        bl_easylist) Config[bl_easylist]="$Value";;
        bl_easyprivacy) Config[bl_easyprivacy]="$Value";;
        bl_fbannoyance) Config[bl_fbannoyance]="$Value";;
        bl_fbenhanced) Config[bl_fbenhanced]="$Value";;
        bl_fbsocial) Config[bl_fbsocial]="$Value";;
        bl_hphosts) Config[bl_hphosts]="$Value";;
        bl_malwaredomainlist) Config[bl_malwaredomainlist]="$Value";;
        bl_malwaredomains) Config[bl_malwaredomains]="$Value";;          
        bl_pglyoyo) Config[bl_pglyoyo]="$Value";;
        #bl_securemecca) Config[bl_securemecca]="$Value";;
        bl_someonewhocares) Config[bl_someonewhocares]="$Value";;
        bl_spam404) Config[bl_spam404]="$Value";;
        bl_swissransom) Config[bl_swissransom]="$Value";;
        bl_swisszeus) Config[bl_swisszeus]="$Value";;
        bl_winhelp2002) Config[bl_winhelp2002]="$Value";;
        bl_areasy) Config[bl_areasy]="$Value";;
        bl_chneasy) Config[bl_chneasy]="$Value";;
        bl_deueasy) Config[bl_deueasy]="$Value";;
        bl_dnkeasy) Config[bl_dnkeasy]="$Value";;
        bl_ruseasy) Config[bl_ruseasy]="$Value";;   
        bl_fblatin) Config[bl_fblatin]="$Value";;         
      esac            
    fi
  done < $FILE_CONFIG  
  
  unset IFS
}

#--------------------------------------------------------------------
# Load White List
# 
# Globals:
#   FILE_WHITELIST, WhiteList
# Arguments:
#   None
# Returns:
#   None
#--------------------------------------------------------------------
function load_whitelist() {
  while IFS='\n' read -r Line
  do
    if [[ $Line =~ ^([A-Za-z0-9\-]+)\.([A-Za-z0-9\.\-]+)[[:space:]]?#?(.*)$ ]]; then
      WhiteList["${BASH_REMATCH[1]}.${BASH_REMATCH[2]}"]=true   #Add site to associative array      
    fi    
  done < $FILE_WHITELIST
  
  unset IFS
}

#--------------------------------------------------------------------
# Process Custom List
# 
# Globals:
#   JumpPoint
#   PercentPoint
# Arguments:
#   #$1 List file to process
# Returns:
#   None
#--------------------------------------------------------------------
function Process_CustomList() {
  local i=0
  local j=0

  CalculatePercentPoint "$1"
  i=1                                            #Progress counter
  j=$JumpPoint                                   #Jump in percent
      
  while IFS=$'#\n\r' read -r Line Comment _
  do
    if [[ ! $Line =~ ^\ *# ]] && [[ -n $Line ]]; then
      Line="${Line%%\#*}"                        #Delete comments
      Line="${Line%%*( )}"                       #Delete trailing spaces      
      if [[ $Line =~ ([A-Za-z0-9\-]*\.)?([A-Za-z0-9\-]*\.)?[A-Za-z0-9\-]*\.[A-Za-z0-9\-]*$ ]]; then
        AddSite "${BASH_REMATCH[0]}" "$Comment"
      fi
    fi
    
    if [ $i -ge $PercentPoint ]; then            #Display progress
      echo -ne " $j%  \r"                        #Echo without return
      j=$((j + JumpPoint))
      i=0
    fi
    ((i++))
  done < "$1"
  echo " 100%"
  
  unset IFS
}

#--------------------------------------------------------------------
# Process Easy List
#   EasyLists contain a mixture of Element hiding rules and third party sites to block.
#   DNS is only capable of blocking sites, therefore NoTrack can only use the lines with $third party or popup in
# Globals:
#   JumpPoint
#   PercentPoint
# Arguments:
#   $1 List file to process
# Returns:
#   None
# Regex:
#   ||
#   Group 1: IPv4 address  optional
#   Group 2: Site A-Z, a-z, 0-9, -, .  one or more
#   Group 3: ^ | / | $  once
#   Group 4: $third-party | $popup | $popup,third-party
#--------------------------------------------------------------------
function Process_EasyList() {
  local i=0
  local j=0
  
  CalculatePercentPoint "$1"
  i=1                                            #Progress counter
  j=$JumpPoint                                   #Jump in percent  
    
  while IFS=$'\n' read -r Line
  do    
    if [[ $Line =~ ^\|\|([[:digit:]]{1,3}\.[[:digit:]]{1,3}\.[[:digit:]]{1,3}\.[[:digit:]]{1,3})?([A-Za-z0-9\.\-]+)(\^|\/|$)(\$third-party|\$popup|\$popup\,third\-party)?$ ]]; then
      AddSite "${BASH_REMATCH[2]}" ""      
    fi    
    if [ $i -ge $PercentPoint ]; then            #Display progress
      echo -ne " $j%  \r"                        #Echo without return
      j=$((j + JumpPoint))
      i=0
    fi
    ((i++))
  done < "$1"
  echo " 100%"
  
  unset IFS
}

#--------------------------------------------------------------------
# Process NoTrack List
#   NoTrack list is just like PlainList, but contains latest version number
#   which is used by the Admin page to inform the user an upgrade is available
# Globals:
#   JumpPoint
#   PercentPoint
#   Version
# Arguments:
#   $1 List file to process
# Returns:
#   None
# Regex:
#   Group 1: Subdomain or Domain
#   .
#   Group 2: Domain or TLD
#   space  optional
#   #  optional
#   Group 3: Comment  any character zero or more times
#--------------------------------------------------------------------
function Process_NoTrackList() {
  local i=0
  local j=0
  local LatestVersion=""
  
  CalculatePercentPoint "$1"
  i=1                                            #Progress counter
  j=$JumpPoint                                   #Jump in percent
  
  while IFS=$'\n' read -r Line
  do  
    if [[ $Line =~ ^([A-Za-z0-9\-]+)\.([A-Za-z0-9\.\-]+)[[:space:]]?#?(.*)$ ]]; then
      AddSite "${BASH_REMATCH[1]}.${BASH_REMATCH[2]}" "${BASH_REMATCH[3]}"
    elif [[ $Line =~ ^#LatestVersion[[:space:]]([0-9\.]+)$ ]]; then #Is it version number
      LatestVersion="${BASH_REMATCH[1]}"         #Extract Version number      
      if [[ $OldLatestVersion != "$LatestVersion" ]]; then 
        echo "New version of NoTrack available v$LatestVersion"
        #Check if config line LatestVersion exists
        #If not add it in with tee
        #If it does then use sed to update it
        if [[ $(grep "LatestVersion" "$FILE_CONFIG") == "" ]]; then
          echo "LatestVersion = $LatestVersion" | sudo tee -a "$FILE_CONFIG"
        else
          sed -i "s/^\(LatestVersion *= *\).*/\1$LatestVersion/" $FILE_CONFIG
        fi
      fi      
    fi
    
    if [ $i -ge $PercentPoint ]; then            #Display progress
      echo -ne " $j%  \r"                        #Echo without return
      j=$((j + JumpPoint))
      i=0
    fi
    ((i++))
  done < "$1"
  echo " 100%"
  
  unset IFS
}

#--------------------------------------------------------------------
# Process Plain List
#
# Globals:
#   JumpPoint
#   PercentPoint
# Arguments:
#   $1 List file to process
# Returns:
#   None
# Regex:
#   Group 1: Subdomain or Domain
#   .
#   Group 2: Domain or TLD
#   space  optional
#   #  optional
#   Group 3: Comment  any character zero or more times
#--------------------------------------------------------------------
function Process_PlainList() {
  local i=0
  local j=0
  
  CalculatePercentPoint "$1"
  i=1                                            #Progress counter
  j=$JumpPoint                                   #Jump in percent
    
  while IFS=$'\n' read -r Line
  do 
    if [[ $Line =~ ^([A-Za-z0-9\-]+)\.([A-Za-z0-9\.\-]+)[[:space:]]?#?(.*)$ ]]; then
      AddSite "${BASH_REMATCH[1]}.${BASH_REMATCH[2]}" "${BASH_REMATCH[3]}"    
    fi
    
    if [ $i -ge $PercentPoint ]; then            #Display progress
      echo -ne " $j%  \r"                        #Echo without return
      j=$((j + JumpPoint))
      i=0
    fi
    ((i++))
  done < "$1"
  echo " 100%"
  
  unset IFS
}

#--------------------------------------------------------------------
# Process TLD List
#   1. Load Domain whitelist into associative array
#   2. Read downloaded TLD list, and compare with Domain WhiteList
#   3. Read users custom TLD list, and compare with Domain WhiteList
#   4. Results are stored in CSVList, and SiteList These arrays are sent back to GetList() for writing to file.
#   The Downloaded & Custom lists are handled seperately to reduce number of disk writes in say cat'ting the files together
#   FILE_QUICKLIST is used to speed up processing in stats.php
# Globals:
#   FILE_DOMAINBLACK, FILE_DOMAINWHITE
#   CSV_DOMAIN
# Arguments:
#   $1 List file to process
# Returns:
#   None
# Regex:
#   Group 1: Subdomain or Domain
#   .
#   Group 2: Domain or TLD
#   space  optional
#   #  optional
#   Group 3: Comment  any character zero or more times
#--------------------------------------------------------------------
function Process_TLDList() { 
  local -A DomainBlackList
  local -A DomainWhiteList
  
  Get_FileTime "$FILE_DOMAINWHITE"
  local DomainWhiteFileTime=$FileTime
  Get_FileTime "$CSV_DOMAIN"
  local filetime_csvdomain=$FileTime
  Get_FileTime "/etc/dnsmasq.d/tld.list"
  local TLDListFileTime=$FileTime
  
  if [ "${Config[bl_tld]}" == 0 ]; then          #Should we process this list according to the Config settings?
    DeleteOldFile "/etc/dnsmasq.d/tld.list"      #If not delete the old file, then leave the function
    DeleteOldFile "/etc/notrack/tld.csv"
    DeleteOldFile "$FILE_QUICKLIST"
    echo
    return 0
  fi
  
  CSVList=()                                     #Zero Arrays
      
  echo "Processing Top Level Domain List"
  
  CreateFile "$FILE_QUICKLIST"                  #Quick lookup file for stats.php
  cat /dev/null > "$FILE_QUICKLIST"             #Empty file
  
  while IFS=$'\n' read -r Line
  do
    if [[ $Line =~ ^\.([A-Za-z0-9\-]+)[[:space:]]?#?(.*)$ ]]; then
      DomainWhiteList[".${BASH_REMATCH[1]}"]=true #Add domain to associative array      
    fi
  done < "$FILE_DOMAINWHITE"
  
  while IFS=$'\n' read -r Line _
  do
    if [[ $Line =~ ^\.([A-Za-z0-9\-]+)[[:space:]]?#?(.*)$ ]]; then
      DomainBlackList[".${BASH_REMATCH[1]}"]=true #Add domain to associative array      
    fi
    
  done < "$FILE_DOMAINBLACK"
  
  while IFS=$',\n' read -r TLD Name Risk _; do    
    if [[ $Risk == 1 ]]; then      
      if [ ! "${DomainWhiteList[$TLD]}" ]; then  #Is site not in WhiteList
        SiteList[$TLD]=true
        CSVList+=("$TLD,Active,$Name")
        DomainList[$TLD]=true
      fi    
    else      
      if [ "${DomainBlackList[$TLD]}" ]; then      
        SiteList[$TLD]=true
        CSVList+=("$TLD,Active,$Name")
        DomainList[$TLD]=true
      fi
    fi
  done < "$CSV_DOMAIN"
  
  #Are the Whitelist and CSV younger than processed list in dnsmasq.d?
  if [ $DomainWhiteFileTime -lt $TLDListFileTime ] && [ $filetime_csvdomain -lt $TLDListFileTime ] && [ $Force == 0 ]; then
    cat "/etc/notrack/tld.csv" >> "$CSV_BLOCKING"
    echo "Top Level Domain List is in date, not saving"
    echo
    return 0    
  fi
  
  printf "%s\n" "${!DomainList[@]}" > $FILE_QUICKLIST
  printf "%s\n" "${CSVList[@]}" > "/etc/notrack/tld.csv"  
  
  echo "Finished processing Top Level Domain List"
  echo
  
  unset IFS  
}

#--------------------------------------------------------------------
# Process Unix List
#
# Globals:
#   JumpPoint
#   PercentPoint
# Arguments:
#   $1 List file to process
# Returns:
#   None
# Regex:
#   Group 1: 127.0.0.1 | 0.0.0.0
#   Space  one or more (include tab)
#   Group 2: Subdomain or Domain
#   .
#   Group 3: Domain or TLD
#   Group 4: space  one or more  optional
#   # Optional
#   Group 6: Comment  any character zero or more times
#--------------------------------------------------------------------
function Process_UnixList() {
  #All Unix lists that I've come across are Windows formatted, therefore we use the carriage return IFS \r
  
  local i=0
  local j=0
  
  CalculatePercentPoint "$1"
  i=1                                            #Progress counter
  j=$JumpPoint                                   #Jump in percent
  
  while IFS=$'\n\r' read -r Line                 #Include carriage return for Windows
  do 
    
    
    if [[ $Line =~ ^(127\.0\.0\.1|0\.0\.0\.0)[[:space:]]+([A-Za-z0-9\-]+)\.([A-Za-z0-9\.\-]+)([[:space:]]+)?#?(.*)$ ]]; then
      AddSite "${BASH_REMATCH[2]}.${BASH_REMATCH[3]}" "${BASH_REMATCH[5]}"    
    fi
       
    if [ $i -ge $PercentPoint ]; then            #Display progress
      echo -ne " $j%  \r"                        #Echo without return
      j=$((j + JumpPoint))
      i=0
    fi
    ((i++))
  done < "$1"
  echo " 100%"
  
  unset IFS
}

#--------------------------------------------------------------------
# Process White Listed sites from Blocked TLD List
#
# Globals:
#   WhiteList
#   DomainList
# Arguments:
#   None
# Returns:
#   0: Success
#   55: Failed
#--------------------------------------------------------------------
function Process_WhiteList() {  
  local Method=0                                 #1: White list from Dnsmasq, 2: Dig
  local -a DNSList
  DNSList=()                                     #Zero Array
  
  CheckDnsmasqVer                                #What version is Dnsmasq?
  if [ $? == 53 ]; then                          #v2.75 or above is required
    Method=1
    echo "White listing from blocked Top Level Domains with Dnsmasq"
  elif [ -n "$(command -v dig)" ]; then          #Is dig available?
    Method=2
    echo "White listing using resolved IP's from Dig"
  else
    echo "Unable to White list from blocked Top Level Domains"
    echo
    return 55
  fi
  
  for Site in "${!WhiteList[@]}"; do             #Read entire White List associative array
    if [[ $Site =~ \.[A-Za-z0-9\-]+$ ]]; then    #Extract the TLD
      if [ "${DomainList[${BASH_REMATCH[0]}]}" ]; then   #Is TLD present in Domain List?
        if [ "$Method" == 1 ]; then              #What method to unblock site? 
          DNSList+=("server=/$Site/#")           #Add unblocked site to DNS List Array
        elif [ "$Method" == 2 ]; then            #Or use Dig
          while IFS=$'\n' read -r Line           #Read each line of Dig output
          do
            if [[ $Line =~ (A|AAAA)[[:space:]]+([a-f0-9\.\:]+)$ ]]; then  #Match A or AAAA IPv4/IPv6
              DNSList+=("host-record=$Site,${BASH_REMATCH[2]}") 
            fi
            if [[ $Line =~ TXT[[:space:]]+(.+)$ ]]; then    #Match TXT "comment"
              DNSList+=("txt-record=$Site,${BASH_REMATCH[1]}")
            fi
          done <<< "$(dig "$Site" @8.8.8.8 ANY +noall +answer)"
        fi
      fi
    fi
  done
  
  unset IFS                                      #Reset IFS
  
  if [ "${#DNSList[@]}" -gt 0 ]; then            #How many items in DNS List array?
    echo "Finished processing white listed sites from blocked TLD's"
    echo "${#DNSList[@]} sites white listed"
    echo "Writing white list to /etc/dnsmasq.d/whitelist.list"
    printf "%s\n" "${DNSList[@]}" > "/etc/dnsmasq.d/whitelist.list"   #Output array to file    
  else                                           #No sites, delete old list file
    echo "No sites to white list from blocked TLD's"
    DeleteOldFile "/etc/dnsmasq.d/whitelist.list"
  fi
  echo  
}

#--------------------------------------------------------------------
# Sort List then save to file
#   1. Sort SiteList array into new array SortedList
#   2. Go through SortedList and check subdomains again
#   3. Copy SortedList to DNSList, removing any blocked subdomains
#   4. Write list to dnsmasq folder
# Globals:
#   SiteList
# Arguments:
#   None
# Returns:
#   None
#--------------------------------------------------------------------
function SortList() {
  local ListSize=0
  local i=0
  local j=0
  local -a SortedList                            #Sorted array of SiteList
  local -a DNSList                               #Dnsmasq list  
  Dedup=0                                        #Reset Deduplication
  
  ListSize=${#SiteList[@]}                       #Get number of items in Array
  if [ "$ListSize" == 0 ]; then                  #Fatal error
    Error_Exit "No items in Block List" "8"
  fi  
  if [ "$ListSize" -ge 100 ]; then               #Calculate Percentage Point
    PercentPoint=$((ListSize/100))
    JumpPoint=1
  else
    PercentPoint=1
    JumpPoint=$((100/ListSize))
  fi
  
  echo "Sorting List"
  IFS=$'\n' SortedList=($(sort <<< "${!SiteList[*]}"))
  unset IFS
    
  echo "Final Deduplication"
  DNSList+=("#Tracker Block list last updated $(date)")
  DNSList+=("#Don't make any changes to this file, use $FILE_BLACKLIST and $FILE_WHITELIST instead")
  
  for Site in "${SortedList[@]}"; do
    # ^ Subdomain
    #Group 1: Domain
    #Group 2: org. | co. | com.  optional
    #Group 3: TLD
    
    #Is there a subdomain?
    if [[ $Site =~ ^[A-Za-z0-9\-]+\.([A-Za-z0-9\-]+)\.(org\.|co\.|com\.)?([A-Za-z0-9\-]+)$ ]]; then
      #Is site.domain already in list?
      if [ ${SiteList[${BASH_REMATCH[1]}.${BASH_REMATCH[2]}${BASH_REMATCH[3]}]} ]; then        
        ((Dedup++))                              #Yes, add to total of dedup
      else
        DNSList+=("address=/$Site/$IPAddr")      #No, add to Array
      fi
    else                                         #No subdomain, add to Array
      DNSList+=("address=/$Site/$IPAddr")
    fi
    
    if [ $i -ge $PercentPoint ]; then            #Display progress
      echo -ne " $j%  \r"                        #Echo without return
      j=$((j + JumpPoint))
      i=0
    fi
    ((i++))
    
  done
  
  echo " 100%"
  echo
  #printf "%s\n" "${SortedList[@]}"              #Uncomment to debug
  echo "Further Deduplicated $Dedup Domains"
  echo "Number of Domains in Block List: ${#DNSList[@]}"
  echo "Writing block list to $LISTFILE_BLOCKING"
  printf "%s\n" "${DNSList[@]}" > "$LISTFILE_BLOCKING"
  
  echo
}

#--------------------------------------------------------------------
#Show on screen help
function Show_Help() {
  echo "Usage: notrack"
  echo "Downloads and Installs updated tracker lists"
  echo
  echo "The following options can be specified:"
  echo -e "  -f, --force\tForce update of Block list"
  echo -e "  -h, --help\tDisplay this help and exit"
  echo -e "  -t, --test\tConfig Test"
  echo -e "  -v, --version\tDisplay version information and exit"
  echo -e "  -u, --upgrade\tRun a full upgrade"
  echo -e "  --count\tCount number of sites in active Block lists"
}

#--------------------------------------------------------------------
#Show Version
function Show_Version() {
  echo "NoTrack Version $Version"
  echo
}

#--------------------------------------------------------------------
# Test
#   Display Config and version number
# Globals:
#   Config
# Arguments:
#   None
# Returns:
#   None
#--------------------------------------------------------------------
function Test() {
  local DnsmasqVersion=""
  local key=""
  local value=""

  echo "NoTrack Config Test"
  echo
  echo "NoTrack version $Version"
  
  DnsmasqVersion=$(dnsmasq --version)
  [[ $DnsmasqVersion =~ ^Dnsmasq[[:space:]]version[[:space:]]([0-9]\.[0-9]{1,2}) ]]
  local VerNo="${BASH_REMATCH[1]}"               #Extract version number from string
  if [[ -z $VerNo ]]; then                       #Was anything extracted?
    echo "Dnsmasq version Unknown"
  else
    echo "Dnsmasq version $VerNo"
    CheckDnsmasqVer
    if [ $? == 53 ]; then                        #Is white listing supported?
      echo "Dnsmasq Supports White listing"
    else                                         #No, version too low
      echo "Dnsmasq Doesn't support White listing (v2.75 or above is required)"
      if [ -n "$(command -v dig)" ]; then        #Is dig available?
        echo "Fallback option using Dig is available"
      else
        echo "Dig isn't installed. Unable to White list from blocked TLD's"
      fi
    fi
  fi  
  echo
  
  load_config                                    #Load saved variables
  Get_IPAddress                                  #Read IP Address of NetDev
  
  echo "Block Lists Utilised:"
  for key in "${!Config[@]}"; do                 #Read keys from Config array
    if [[ "${Config[$key]}" == 1 ]]; then        #Is block list enabled?
      echo "$key"                                #Yes, display it
    fi
  done
  echo
  
  if [[ ${Config[bl_custom]} != "" ]]; then      #Any custom block lists?
    echo "Additional Custom Block Lists Utilised:"
    echo "${Config[bl_custom]}"
  fi
}

#--------------------------------------------------------------------
# Check if an update is required
#   Triggers for Update being required:
#   1. -f or --forced
#   2 Block list older than 4 days
#   3 White list recently modified
#   4 Black list recently modified
#   5 Config recently modified
#   6 Domain White list recently modified
#   7 Domain Black list recently modified
#   8 Domain CSV recently modified
# Globals:
#   Force
#   FILE_BLACKLIST, FILE_WHITELIST, FILE_CONFIG, FILE_DOMAINBLACK, FILE_DOMAINWHITE
#   CSV_DOMAIN
# Arguments:
#   None
# Returns:
#   None
#--------------------------------------------------------------------
function UpdateRequired() {
  Get_FileTime "$LISTFILE_BLOCKING"
  local ListFileTime="$FileTime"
  
  if [ $Force == 1 ]; then
    echo "Forced Update"
    return 0
  fi
  if [ $ListFileTime -lt $((UnixTime-CHECKTIME)) ]; then
    echo "Block List out of date"
    return 0
  fi
  Get_FileTime "$FILE_WHITELIST"
  if [ $FileTime -gt $ListFileTime ]; then
    echo "White List recently modified"
    return 0
  fi
  Get_FileTime "$FILE_BLACKLIST"
  if [ $FileTime -gt $ListFileTime ]; then
    echo "Black List recently modified"
    return 0
  fi
  Get_FileTime "$FILE_CONFIG"
  if [ $FileTime -gt $ListFileTime ]; then
    echo "Config recently modified"
    return 0
  fi
  Get_FileTime "$FILE_DOMAINWHITE"
  if [ $FileTime -gt $ListFileTime ]; then
    echo "Domain White List recently modified"
    return 0
  fi
  Get_FileTime "$FILE_DOMAINBLACK"
  if [ $FileTime -gt $ListFileTime ]; then
    echo "Domain White List recently modified"
    return 0
  fi
  Get_FileTime "$CSV_DOMAIN"
  if [ $FileTime -gt $ListFileTime ]; then
    echo "Domain Master List recently modified"
    return 0
  fi
  
  echo "No update required"
  exit 0
}

#--------------------------------------------------------------------
# Upgrade NoTrack
# Globals:
#   Config
# Arguments:
#   None
# Returns:
#   None
#--------------------------------------------------------------------
function Upgrade() {
  #As of v0.7.9 Upgrading is now handled by ntrk-upgrade.sh
  #This function attempts to run it from /usr/local/sbin
  #If that fails, then it looks in the users home folder
  if [ -e /usr/local/sbin/ntrk-upgrade ]; then
    echo "Running ntrk-upgrade"
    /usr/local/sbin/ntrk-upgrade
    exit 0
  fi

  echo "Warning. ntrk-upgrade missing from /usr/local/sbin/"
  echo "Attempting to find alternate copy..."  

  for HomeDir in /home/*; do
    if [ -d "$HomeDir/NoTrack" ]; then 
      InstallLoc="$HomeDir/NoTrack"
      break
    elif [ -d "$HomeDir/notrack" ]; then 
      InstallLoc="$HomeDir/notrack"
      break
    fi
  done

  if [[ $InstallLoc == "" ]]; then
    if [ -d "/opt/notrack" ]; then
      InstallLoc="/opt/notrack"      
    else
      Error_Exit "Unable to find NoTrack folder" "22"
    fi
  else    
    if [ -e "$InstallLoc/ntrk-upgrade.sh" ]; then
      echo "Found alternate copy in $InstallLoc"
      sudo bash "$InstallLoc/ntrk-upgrade.sh"    
    else
      Error_Exit "Unable to find ntrk-upgrade.sh" "20"
    fi
  fi
}

#Main----------------------------------------------------------------
if [ "$1" ]; then                                #Have any arguments been given
  if ! options="$(getopt -o fhvtu -l count,help,force,version,upgrade,test -- "$@")"; then
    # something went wrong, getopt will put out an error message for us
    exit 6
  fi

  set -- $options

  while [ $# -gt 0 ]
  do
    case $1 in
      --count)
        CountLines
        exit 0
      ;;
      -f|--force)
        Force=1        
      ;;
      -h|--help) 
        Show_Help
        exit 0
      ;;
      -t|--test)
        Test
        exit 0
      ;;
      -v|--version) 
        Show_Version
        exit 0
      ;;
      -u|--upgrade)
        Upgrade
        exit 0
      ;;
      (--) 
        shift
        break
      ;;
      (-*)         
        Error_Exit "$0: error - unrecognized option $1" "6"
      ;;
      (*) 
        break
      ;;
    esac
    shift
  done
fi
  
#--------------------------------------------------------------------
#At this point the functionality of notrack.sh is to update Block Lists
#1. Check if user is running as root
#2. Create folder /etc/notrack
#3. Load config file (or use default values)
#4. Get IP address of system, e.g. 192.168.1.2
#5. Generate WhiteList if it doesn't exist
#6. Check if Update is required 
#7. Load WhiteList file into WhiteList associative array
#8. Create csv file of blocked sites, or empty it
#9. Process Users Custom BlackList
#10. Process Other block lists according to Config
#11. Process Custom block lists
#12. Sort list and do final deduplication

check_root                                       #Check if Script run as Root
  
if [ ! -d "/etc/notrack" ]; then                 #Check /etc/notrack folder exists
  echo "Creating notrack folder under /etc"
  echo
  mkdir "/etc/notrack"
  if [ ! -d "/etc/notrack" ]; then               #Check again
    Error_Exit "Unable to create folder /etc/notrack" "2"
  fi
fi
  
load_config                                      #Load saved variables
Get_IPAddress                                    #Read IP Address of NetDev
  
if [ ! -e $FILE_WHITELIST ]; then Generate_WhiteList
fi
  
load_whitelist                                   #Load Whitelist into array
CreateFile "$CSV_BLOCKING"                       #Create Block list csv
  
if [ ! -e "$FILE_BLACKLIST" ]; then Generate_BlackList
fi

CreateFile "$FILE_DOMAINWHITE"                   #Create Black & White lists
CreateFile "$FILE_DOMAINBLACK"

#Legacy files as of v0.7.15 since block list was consolidated
DeleteOldFile /etc/dnsmasq.d/adblockmanager.list
DeleteOldFile /etc/dnsmasq.d/hphosts.list
DeleteOldFile /etc/dnsmasq.d/someonewhocares.list
DeleteOldFile /etc/dnsmasq.d/custom.list
DeleteOldFile /etc/dnsmasq.d/malwaredomainlist.list
DeleteOldFile /etc/dnsmasq.d/spam404.list
DeleteOldFile /etc/dnsmasq.d/disconnectmalvertising.list
DeleteOldFile /etc/dnsmasq.d/malwaredomains.list
DeleteOldFile /etc/dnsmasq.d/swissransom.list
DeleteOldFile /etc/dnsmasq.d/easylist.list
DeleteOldFile /etc/dnsmasq.d/swisszeus.list
DeleteOldFile /etc/dnsmasq.d/easyprivacy.list
DeleteOldFile /etc/dnsmasq.d/pglyoyo.list
DeleteOldFile /etc/dnsmasq.d/tld.list
DeleteOldFile /etc/dnsmasq.d/fbannoyance.list
DeleteOldFile /etc/dnsmasq.d/qmalware.list
DeleteOldFile /etc/dnsmasq.d/winhelp2002.list
DeleteOldFile /etc/dnsmasq.d/fbenhanced.list
DeleteOldFile /etc/dnsmasq.d/fbsocial.list
DeleteOldFile /etc/dnsmasq.d/chneasy.list
DeleteOldFile /etc/dnsmasq.d/ruseasy.list


UpdateRequired                                   #Check if NoTrack needs to run

CreateFile "$LISTFILE_BLOCKING"
cat /dev/null > "$CSV_BLOCKING"                  #Empty file

Process_TLDList                                  #Load and Process TLD List
Process_WhiteList                                #Process White List

GetList_BlackList                                #Process Users Blacklist
  
GetList "notrack" "notrack"
GetList "qmalware" "plain"
GetList "hexxium" "easylist"
GetList "disconnectmalvertising" "plain"
GetList "easylist" "easylist"
GetList "easyprivacy" "easylist"
GetList "fbannoyance" "easylist"
GetList "fbenhanced" "easylist"
GetList "fbsocial" "easylist"
GetList "hphosts" "unix"
GetList "malwaredomainlist" "unix"
GetList "malwaredomains" "plain"
GetList "pglyoyo" "plain"
GetList "someonewhocares" "unix"
GetList "spam404" "easylist"
GetList "swissransom" "plain"
GetList "swisszeus" "plain"
GetList "winhelp2002" "unix"
GetList "areasy" "easylist"
GetList "chneasy" "easylist"
GetList "deueasy" "easylist"
GetList "dnkeasy" "easylist" 
GetList "ruseasy" "easylist"
GetList "fblatin" "easylist"

Get_Custom                                       #Process Custom Block lists

echo "Deduplicated $Dedup Domains"
SortList                                         #Sort, Dedup 2nd round, Save list

if [ "${Config[bl_tld]}" == 0 ]; then
  DeleteOldFile "$FILE_QUICKLIST"
fi
  
echo "Restarting Dnsmasq"
service dnsmasq restart                          #Restart dnsmasq
echo "NoTrack complete"
echo

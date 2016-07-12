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

declare -A Config                                #Config array for Blocklists
Config[blocklist_notrack]=1
Config[blocklist_tld]=1
Config[blocklist_qmalware]=1
Config[blocklist_adblockmanager]=0
Config[blocklist_disconnectmalvertising]=0
Config[blocklist_easylist]=0
Config[blocklist_easyprivacy]=0
Config[blocklist_fbannoyance]=0
Config[blocklist_fbenhanced]=0
Config[blocklist_fbsocial]=0
Config[blocklist_hphosts]=0
Config[blocklist_malwaredomainlist]=0
Config[blocklist_malwaredomains]=0
Config[blocklist_pglyoyo]=0
Config[blocklist_someonewhocares]=0
Config[blocklist_spam404]=0
Config[blocklist_swissransom]=0
Config[blocklist_swisszeus]=0
Config[blocklist_winhelp2002]=0
Config[blocklist_chneasy]=0                      #China
Config[blocklist_ruseasy]=0                      #Russia

#Leave these Settings alone------------------------------------------
Version="0.7.14"
BlockingCSV="/etc/notrack/blocking.csv"
BlackListFile="/etc/notrack/blacklist.txt"
WhiteListFile="/etc/notrack/whitelist.txt"
DomainBlackListFile="/etc/notrack/domain-blacklist.txt"
DomainWhiteListFile="/etc/notrack/domain-whitelist.txt"
DomainQuickList="/etc/notrack/domain-quick.list"
DomainCSV="/var/www/html/admin/include/tld.csv"
ConfigFile="/etc/notrack/notrack.conf"

declare -A URLList                               #Array of URL's
#URLList[notrack]="http://quidsup.net/trackers.txt" - Deprecated
URLList[notrack]="https://raw.githubusercontent.com/quidsup/notrack/master/trackers.txt"
URLList[qmalware]="https://raw.githubusercontent.com/quidsup/notrack/master/malicious-sites.txt"
URLList[adblockmanager]="http://adblock.gjtech.net/?format=unix-hosts"
URLList[disconnectmalvertising]="https://s3.amazonaws.com/lists.disconnect.me/simple_malvertising.txt"
URLList[easylist]="https://easylist-downloads.adblockplus.org/easylist_noelemhide.txt"
URLList[easyprivacy]="https://easylist-downloads.adblockplus.org/easyprivacy.txt"
URLList[fbannoyance]="https://easylist-downloads.adblockplus.org/fanboy-annoyance.txt"
URLList[fbenhanced]="https://www.fanboy.co.nz/enhancedstats.txt"
URLList[fbsocial]="https://secure.fanboy.co.nz/fanboy-social.txt"
URLList[hphosts]="http://hosts-file.net/ad_servers.txt"
URLList[malwaredomainlist]="http://www.malwaredomainlist.com/hostslist/hosts.txt"
URLList[malwaredomains]="http://mirror1.malwaredomains.com/files/justdomains"
URLList[spam404]="https://raw.githubusercontent.com/Dawsey21/Lists/master/adblock-list.txt"
URLList[swissransom]="https://ransomwaretracker.abuse.ch/downloads/RW_DOMBL.txt"
URLList[swisszeus]="https://zeustracker.abuse.ch/blocklist.php?download=domainblocklist"
URLList[pglyoyo]="http://pgl.yoyo.org/adservers/serverlist.php?hostformat=;mimetype=plaintext"
URLList[someonewhocares]="http://someonewhocares.org/hosts/hosts"
URLList[winhelp2002]="http://winhelp2002.mvps.org/hosts.txt"
URLList[chneasy]="https://easylist-downloads.adblockplus.org/easylistchina.txt"
URLList[ruseasy]="https://easylist-downloads.adblockplus.org/ruadlist+easylist.txt"

#Global Variables----------------------------------------------------
ChangesMade=0                                    #Number of Lists processed. If left at zero then Dnsmasq won't be restarted
FileTime=0                                       #Return value from Get_FileTime
OldLatestVersion="$Version"
UnixTime=$(date +%s)                             #Unix time now
declare -A WhiteList                             #associative array
WhiteListFileTime=0
declare -a CSVList                               #Array to store each list
declare -a DNSList
JumpPoint=0                                      #Percentage increment
PercentPoint=0                                   #Number of lines to loop through before a percentage increment is hit

#Error_Exit----------------------------------------------------------
Error_Exit() {
  echo "$1"
  echo "Aborting"
  exit 2
}
#Check File Exists and Abort if it doesn't exist---------------------
Check_File_Exists() {
  if [ ! -e "$1" ]; then
    echo "Error file $1 is missing.  Aborting."
    exit 2
  fi
}
#Create File---------------------------------------------------------
CreateFile() {
  if [ ! -e "$1" ]; then
    echo "Creating file: $1"
    touch "$1"
  fi
}
#Delete old file if it Exists----------------------------------------
DeleteOldFile() {
  if [ -e "$1" ]; then
    echo "Deleting file $1"
    rm "$1"
    ((ChangesMade++))                            #Deleting a file counts as a change, and may require Dnsmasq to be restarted
  fi
}
#Add Site to List-----------------------------------------------------
AddSite() {
  #$1 = Site to Add
  #$2 = Comment
  #Add Site checks whether a Site is in the Users whitelist
  #1. Desregard zero length strings
  #2. Check If site name ($1) is listed in the WhiteList associative array
  #3a. If it is then add a line to CSVList Array saying Site is Disabled
  #3b. Otherwise add line to CSVList Array saying Site is Enabled, and add line to DNSList in the form of "address=/site.com/192.168.0.0"
  
  if [ ${#1} == 0 ]; then return 0; fi           #Ignore zero length str
  
  if [ "${WhiteList[$1]}" ]; then                #Is site in WhiteList Array?    
    CSVList+=("$1,Disabled,$2")
  else                                           #No match in whitelist    
    DNSList+=("address=/$1/$IPAddr")
    CSVList+=("$1,Active,$2")
  fi
}
#Calculate Percent Point in list files-------------------------------
CalculatePercentPoint() {
  #$1 = File to Calculate
  #1. Count number of lines in file with "wc"
  #2. Calculate Percentage Point (number of for loop passes for 1%)
  #3. Calculate Jump Point (increment of 1 percent point on for loop)
  #E.g.1 20 lines = 1 for loop pass to increment percentage by 5%
  #E.g.2 200 lines = 2 for loop passes to increment percentage by 1%
  local NumLines=0
  
  NumLines=$(wc -l "$1" | cut -d " " -f 1)       #Count number of lines
  if [ $NumLines -ge 100 ]; then
    PercentPoint=$((NumLines/100))
    JumpPoint=1
  else
    PercentPoint=1
    JumpPoint=$((100/NumLines))
  fi
}
#Read Config File----------------------------------------------------
#Default values are set at top of this script
#Config File contains Key & Value on each line for some/none/or all items
#If the Key is found in the case, then we write the value to the Variable

Read_Config_File() {  
  if [ -e "$ConfigFile" ]; then
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
          BlockList_NoTrack) Config[blocklist_notrack]="$Value";;
          BlockList_TLD) Config[blocklist_tld]="$Value";;
          BlockList_QMalware) Config[blocklist_qmalware]="$Value";;
          BlockList_DisconnectMalvertising) Config[blocklist_disconnectmalvertising]="$Value";;
          BlockList_AdBlockManager) Config[blocklist_adblockmanager]="$Value";;
          BlockList_EasyList) Config[blocklist_easylist]="$Value";;
          BlockList_EasyPrivacy) Config[blocklist_easyprivacy]="$Value";;
          BlockList_FBAnnoyance) Config[blocklist_fbannoyance]="$Value";;
          BlockList_FBEnhanced) Config[blocklist_fbenhanced]="$Value";;
          BlockList_FBSocial) Config[blocklist_fbsocial]="$Value";;
          BlockList_hpHosts) Config[blocklist_hphosts]="$Value";;
          BlockList_MalwareDomainList) Config[blocklist_malwaredomainlist]="$Value";;
          BlockList_MalwareDomains) Config[blocklist_malwaredomains]="$Value";;          
          BlockList_PglYoyo) Config[blocklist_pglyoyo]="$Value";;
          BlockList_SomeoneWhoCares) Config[blocklist_someonewhocares]="$Value";;
          BlockList_Spam404) Config[blocklist_spam404]="$Value";;
          BlockList_SwissRansom) Config[blocklist_swissransom]="$Value";;
          BlockList_SwissZeus) Config[blocklist_swisszeus]="$Value";;
          BlockList_Winhelp2002) Config[blocklist_winhelp2002]="$Value";;
          BlockList_CHNEasy) Config[blocklist_chneasy]="$Value";;
          BlockList_RUSEasy) Config[blocklist_ruseasy]="$Value";;          
        esac            
      fi
    done < $ConfigFile
  fi 
}

#Read White List-----------------------------------------------------
Read_WhiteList() {
  while IFS='# ' read -r Line _
  do
    if [[ ! $Line =~ ^\ *# && -n $Line ]]; then
      Line="${Line%%\#*}"                        #Delete comments
      Line="${Line%%*( )}"                       #Delete trailing spaces
      WhiteList[$Line]="$Line"
    fi
  done < $WhiteListFile
}
#Generate BlackList--------------------------------------------------
Generate_BlackList() {
  local -a Tmp                                   #Local array to build contents of file
  
  echo "Creating blacklist"
  touch "$BlackListFile"
  Tmp+=("#Use this file to create your own custom block list")
  Tmp+=("#Run notrack script (sudo notrack) after you make any changes to this file")
  Tmp+=("#doubleclick.net")
  Tmp+=("#googletagmanager.com")
  Tmp+=("#googletagservices.com")
  printf "%s\n" "${Tmp[@]}" > $BlackListFile     #Write Array to file with line seperator
}
#Generate WhiteList--------------------------------------------------
Generate_WhiteList() {
  local -a Tmp                                   #Local array to build contents of file
  
  echo "Creating whitelist"
  touch "$WhiteListFile"
  Tmp+=("#Use this file to remove sites from block list")
  Tmp+=("#Run notrack script (sudo notrack) after you make any changes to this file")
  Tmp+=("#doubleclick.net")
  Tmp+=("#google-analytics.com")
  printf "%s\n" "${Tmp[@]}" > $WhiteListFile     #Write Array to file with line seperator
}
#Get IP Address of System--------------------------------------------
Get_IPAddress() {    
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
Get_FileTime() {
  #$1 = File to be checked
  if [ -e "$1" ]; then                           #Does file exist?
    FileTime=$(stat -c %Z "$1")                  #Return time of last status change, seconds since Epoch
  else
    FileTime=0                                   #Otherwise retrun 0
  fi
}

#Custom BlackList----------------------------------------------------
GetList_BlackList() {
  local BlFileTime=0                             #Blacklist File Time
  local ListFileTime=0                           #Processed List File Time
  
  Get_FileTime "/etc/dnsmasq.d/custom.list"
  ListFileTime=$FileTime
  Get_FileTime "$BlackListFile"
  BlFileTime=$FileTime
  
  #Are the Whitelist & Blacklist older than 36 Hours, and the Processed List of any age?
  if [ $WhiteListFileTime -lt $((UnixTime-187200)) ] && [ $BlFileTime -lt $((UnixTime-187200)) ] && [ $ListFileTime -gt 0 ]; then
    if [ $(wc -l /etc/notrack/custom.csv | cut -d " " -f 1) -gt 1 ]; then
      cat /etc/notrack/custom.csv >> "$BlockingCSV"
    fi
    echo "Custom Black List is in date, no need for processing"
    echo
    return 0
  fi

  echo "Processing Custom Black List"
  
  Process_PlainList "$BlackListFile"
  
  printf "%s\n" "${CSVList[@]}" > "/etc/notrack/custom.csv"
  printf "%s\n" "${DNSList[@]}" > "/etc/dnsmasq.d/custom.list"
  if [ $(wc -l /etc/notrack/custom.csv | cut -d " " -f 1) -gt 1 ]; then
    cat /etc/notrack/custom.csv >> "$BlockingCSV"
  fi
  echo "Finished processing Custom Black List"
  echo
  ((ChangesMade++))
}
#GetList-------------------------------------------------------------
GetList() {
  #$1 = List to be Processed
  #$2 = Process Method
  #$3 = Time (in seconds) between needing to process a new list
  local Lst="$1"
  local CSVFile="/etc/notrack/$1.csv"
  local DLFile="/tmp/$1.txt"
  local ListFile="/etc/dnsmasq.d/$1.list"
  local DLFileTime=0                             #Downloaded File Time
  local ListFileTime=0                           #Processed List File Time
  
  if [ ${Config[blocklist_$Lst]} == 0 ]; then    #Should we process this list according to the Config settings?
    DeleteOldFile "$ListFile"                    #If not delete the old file, then leave the function
    DeleteOldFile "$CSVFile"
    DeleteOldFile "$DLFile"
    return 0
  fi
  
  Get_FileTime "$ListFile"
  ListFileTime=$FileTime
  Get_FileTime "$DLFile"
  DLFileTime=$FileTime
  
  #Is the Whitelist older than 36 Hours, and the Processed List younger than $3. If so leave the function without processing
  if [ $WhiteListFileTime -lt $((UnixTime-187200)) ] && [ $ListFileTime -gt $((UnixTime-$3)) ]; then
    cat "$CSVFile" >> "$BlockingCSV"
    echo "$Lst is in date, no need for processing"
    echo
    return 0
  fi
  
  #If the Downloaded List is older than $3 then don't download it again
  if [ $DLFileTime -gt $((UnixTime-$3)) ]; then  
    echo "$Lst in date. Not downloading"    
  else  
    echo "Downloading $Lst"
    wget -qO "$DLFile" "${URLList[$Lst]}"
  fi
  
  if [ ! -e "$DLFile" ]; then                    #Check if list has been downloaded
    echo "File not downloaded"
    return 1
  fi
  
  CSVList=()                                     #Zero Arrays
  DNSList=()  
  CreateFile "$CSVFile"                          #Create CSV File
  CreateFile "$ListFile"                         #Create List File
  
  echo "Processing list $Lst"                    #Inform user
  
  case $2 in                                     #What type of processing is required?
    "easylist") Process_EasyList "$DLFile" ;;
    "plain") Process_PlainList "$DLFile" ;;
    "notrack") Process_NoTrackList "$DLFile" ;;
    "tldlist") Process_TLDList ;;
    "unix127") Process_UnixList127 "$DLFile" ;;
    "unix0") Process_UnixList0 "$DLFile" ;;
    *) Error_Exit "Unknown option $2"
  esac
  
  #Write arrays to file
  printf "%s\n" "${CSVList[@]}" > "/etc/notrack/$Lst.csv"
  printf "%s\n" "${DNSList[@]}" > "/etc/dnsmasq.d/$Lst.list"
  cat "/etc/notrack/$Lst.csv" >> "$BlockingCSV"
  echo "Finished processing $Lst"
  echo
  ((ChangesMade++))
}
#Process EasyList----------------------------------------------------
Process_EasyList() {
  #EasyLists contain a mixture of Element hiding rules and third party sites to block.
  #DNS is only capable of blocking sites, therefore NoTrack can only use the lines with $third party in
  
  #$1 = SourceFile
  
  CalculatePercentPoint "$1"
  i=1                                            #Progress counter
  j=$JumpPoint                                   #Jump in percent
    
  while IFS=$' \n' read -r Line
  do
    #||somesite.com^
    if [[ $Line =~ ^\|\|[a-z0-9\.-]*\^$ ]]; then
      AddSite "${Line:2:-1}" ""
    ##[href^="http://somesite.com/"]
    elif [[ $Line =~ ^##\[href\^=\"http:\/\/[a-z0-9\.-]*\/\"\]$ ]]; then
      #As above, but remove www.
      if [[ $Line =~ ^##\[href\^=\"http:\/\/www\.[a-z0-9\.-]*\/\"\]$ ]]; then
        AddSite "${Line:21:-3}" ""
      else
        AddSite "${Line:17:-3}" ""
      fi
    #||somesite.com^$third-party
    elif [[ $Line =~ ^\|\|[a-z0-9\.-]*\^\$third-party$ ]]; then
      #Basic method of ignoring IP addresses (\d doesn't work)
      if  [[ ! $Line =~ ^\|\|[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\^\$third-party$ ]]; then
        AddSite "${Line:2:-13}" ""
      fi
    #||somesite.com^$popup,third-party
    elif [[ $Line =~ ^\|\|[a-z0-9\.-]*\^\$popup\,third-party$ ]]; then
      AddSite "${Line:2:-19}" ""
    elif [[ $Line =~ ^\|\|[a-z0-9\.-]*\^\$third-party\,domain=~ ]]; then
      #^$third-party,domain= apepars mid line, we need to replace it with a | pipe seperator like the rest of the line has
      Line=$(sed "s/\^$third-party,domain=~/\|/g" <<< "$Line")
      IFS='|~', read -r -a ArrayOfLine <<< "$Line" #Explode into array using seperator | or ~
      for Line in "${ArrayOfLine[@]}"            #Loop through array
      do
        if [[ $Line =~ ^\|\|[a-z0-9\.-]*$ ]]; then #Check Array line is a URL
          AddSite "$Line" ""
        fi
      done  
    fi
    
    if [ $i -ge $PercentPoint ]; then            #Display progress
      echo -ne " $j%  \r"                        #Echo without return
      j=$((j + $JumpPoint))
      i=0
    fi
    ((i++))
  done < "$1"
  echo " 100%"
}
#Process NoTrack List------------------------------------------------
Process_NoTrackList() {
  #NoTrack list is just like PlainList, but contains latest version number
  #which is used by the Admin page to inform the user an upgrade is available
  
  #$1 = SourceFile
  
  DNSList+=("#Tracker Blocklist last updated $(date)")
  DNSList+=("#Don't make any changes to this file, use $BlackListFile and $WhiteListFile instead")
    
  CalculatePercentPoint "$1"
  i=1                                            #Progress counter
  j=$JumpPoint                                   #Jump in percent
  
  while IFS='# ' read -r Line Comment
  do
    if [[ ! $Line =~ ^\ *# && -n $Line ]]; then
      Line="${Line%%\#*}"                        #Delete comments
      Line="${Line%%*( )}"                       #Delete trailing spaces      
      AddSite "$Line" "$Comment"      
    elif [[ "${Comment:0:13}" == "LatestVersion" ]]; then
      LatestVersion="${Comment:14}"              #Substr version number only
      if [[ $OldLatestVersion != "$LatestVersion" ]]; then 
        echo "New version of NoTrack available v$LatestVersion"
        #Check if config line LatestVersion exists
        #If not add it in with tee
        #If it does then use sed to update it
        if [[ $(cat "$ConfigFile" | grep LatestVersion) == "" ]]; then
          echo "LatestVersion = $LatestVersion" | sudo tee -a "$ConfigFile"
        else
          sed -i "s/^\(LatestVersion *= *\).*/\1$LatestVersion/" $ConfigFile
        fi
      fi      
    fi
    
    if [ $i -ge $PercentPoint ]; then            #Display progress
      echo -ne " $j%  \r"                        #Echo without return
      j=$((j + $JumpPoint))
      i=0
    fi
    ((i++))
  done < "$1"
  echo " 100%"
}
#Process PlainList---------------------------------------------------
#Plain Lists are styled like:
# #Comment
# Site
# Site #Comment
Process_PlainList() {
  #$1 = SourceFile
  CalculatePercentPoint "$1"
  i=1                                            #Progress counter
  j=$JumpPoint                                   #Jump in percent
    
  while IFS=$'# \n' read -r Line Comment _
  do
    if [[ ! $Line =~ ^\ *# && -n $Line ]]; then
      Line="${Line%%\#*}"                        #Delete comments
      Line="${Line%%*( )}"                       #Delete trailing spaces      
      #echo "$Line $2 $Comment"
      AddSite "$Line" "$Comment"
    fi
    
    if [ $i -ge $PercentPoint ]; then            #Display progress
      echo -ne " $j%  \r"                        #Echo without return
      j=$((j + $JumpPoint))
      i=0
    fi
    ((i++))
  done < "$1"
  echo " 100%"
}
#Process TLD List----------------------------------------------------
Process_TLDList() {
  #1. Load Domain whitelist into associative array
  #2. Read downloaded TLD list, and compare with Domain WhiteList
  #3. Read users custom TLD list, and compare with Domain WhiteList
  #4. Results are stored in CSVList, and DNSList. These arrays are sent back to GetList() for writing to file.
  #The Downloaded & Custom lists are handled seperately to reduce number of disk writes in say cat'ting the files together
  #DomainQuickList is used to speed up processing in stats.php
  
  local -A DomainWhiteList
  local -A DomainBlackList
  
  Get_FileTime "$DomainWhiteListFile"
  local DomainWhiteFileTime=$FileTime
  Get_FileTime "$DomainCSV"
  local DomainCSVFileTime=$FileTime
  Get_FileTime "/etc/dnsmasq.d/tld.list"
  local TLDListFileTime=$FileTime
  
  if [ ${Config[blocklist_tld]} == 0 ]; then     #Should we process this list according to the Config settings?
    DeleteOldFile "/etc/dnsmasq.d/tld.list"      #If not delete the old file, then leave the function
    DeleteOldFile "/etc/notrack/tld.csv"
    DeleteOldFile "$DomainQuickList"
    echo
    return 0
  fi
  
  CSVList=()                                     #Zero Arrays
  DNSList=()
  
  #Are the Whitelist and CSV younger than processed list in dnsmasq.d?
  if [ $DomainWhiteFileTime -lt $TLDListFileTime ] && [ $DomainCSVFileTime -lt $TLDListFileTime ]; then
    cat "/etc/notrack/tld.csv" >> "$BlockingCSV"
    echo "Top Level Domain List is in date, no need for processing"
    echo
    return 0    
  fi
  
  echo "Processing Top Level Domain List"
  
  CreateFile "$DomainQuickList"                  #Quick lookup file for stats.php
  cat /dev/null > "$DomainQuickList"             #Empty file
  
  while IFS=$'#\n' read -r Line _
  do
    if [[ ! $Line =~ ^\ *# ]] && [[ -n $Line ]]; then
      Line="${Line%%\#*}"                        #Delete comments
      Line="${Line%%*( )}"                       #Delete trailing spaces
      DomainWhiteList[$Line]="$Line"             #Add domain to associative array      
    fi
  done < "$DomainWhiteListFile"
  
  while IFS=$'#\n' read -r Line _
  do
    if [[ ! $Line =~ ^\ *# ]] && [[ -n $Line ]]; then
      Line="${Line%%\#*}"                        #Delete comments
      Line="${Line%%*( )}"                       #Delete trailing spaces
      DomainBlackList[$Line]="$Line"             #Add domain to associative array
    fi
  done < "$DomainBlackListFile"
  
  while IFS=$',\n' read -r TLD Name Risk _; do
    if [[ $Risk == 1 ]]; then
      if [ ! "${DomainWhiteList[$TLD]}" ]; then  #Is site not in WhiteList
        DNSList+=("address=/$TLD/$IPAddr")
        CSVList+=("$TLD,Active,$Name")
        echo "$TLD" >> $DomainQuickList
      fi    
    else
      if [ "${DomainBlackList[$TLD]}" ]; then
        DNSList+=("address=/$TLD/$IPAddr")
        CSVList+=("$TLD,Active,$Name")
        echo "$TLD" >> $DomainQuickList
      fi
    fi
  done < "$DomainCSV"
  
  printf "%s\n" "${CSVList[@]}" > "/etc/notrack/tld.csv"
  printf "%s\n" "${DNSList[@]}" > "/etc/dnsmasq.d/tld.list"
  
  echo "Finished processing Top Level Domain List"
  echo
  ((ChangesMade++))
}
#Process UnixList 0--------------------------------------------------
Process_UnixList0() {
  #Unix hosts file with 0 localhost have: 0.0.0.0 site.com
  #$1 = SourceFile
  CalculatePercentPoint "$1"
  i=1                                            #Progress counter
  j=$JumpPoint                                   #Jump in percent
  
  while IFS=$'\r' read -r Line _
  do
    if [[ ${Line:0:3} == "0.0" ]]; then          #Does line start with 0.0
      Line=${Line:8}                             #Trim out 0.0.0.0
           
      if [[ ! $Line =~ ^(#|localhost|www\.|EOF|\[) ]]; then
        Line="${Line%%\#*}"                      #Delete comments
        Line="${Line%%*( )}"                     #Delete trailing spaces
        AddSite "$Line" ""
      fi
    fi
    
    if [ $i -ge $PercentPoint ]; then            #Display progress
      echo -ne " $j%  \r"                        #Echo without return
      j=$((j + $JumpPoint))
      i=0
    fi
    ((i++))
  done < "$1"
  echo " 100%"
 }
#Process UnixList 127------------------------------------------------
Process_UnixList127() {
  #All Unix lists that I've come across are Windows formatted, therefore we use the carriage return IFS \r
  #Some files are double spaced, e.g.
  # 127.0.0.1  somesite.com
  # 127.0.0.1 somesite.com
  #1. Calculate Percentage and Jump points
  #2. Read line from file
  #3. Is it a double spaced line?
  #3a. Trim 127.0.0.1__
  #3b. Delete trailing comments
  #3c. Delete trailing spaces
  #3d. Parse Line to AddSite function
  #4. Is line single spaced?
  #4a. Trim 127.0.0.1_
  #4b,c,d as above
  #5. Display progress
  #6. loop back to 2.
  #$1 = SourceFile
  CalculatePercentPoint "$1"
  i=1                                            #Progress counter
  j=$JumpPoint                                   #Jump in percent
  
  while IFS=$'\r' read -r Line _
  do
    if [[ ${Line:0:11} == "127.0.0.1  " ]]; then #Some files have a double space
      Line=${Line:11}                            #Crop 127.0.0.1
      if [[ ! $Line =~ ^(#|localhost|www|EOF|\[).*$ ]]; then
        Line="${Line%%\#*}"                      #Delete comments
        Line="${Line%%*( )}"                     #Delete trailing spaces
        AddSite "$Line" ""
      fi
    elif [[ ${Line:0:10} == "127.0.0.1 " ]]; then
      Line=${Line:10}                            #Crop 127.0.0.1
      if [[ ! $Line =~ ^(#|localhost|www|EOF|\[).*$ ]]; then
        Line="${Line%%\#*}"                      #Delete comments
        Line="${Line%%*( )}"                     #Delete trailing spaces
        AddSite "$Line" ""
      fi
    fi
    
    if [ $i -ge $PercentPoint ]; then            #Display progress
      echo -ne " $j%  \r"                        #Echo without return
      j=$((j + $JumpPoint))
      i=0
    fi
    ((i++))
  done < "$1"
  echo " 100%"
 }
#Help----------------------------------------------------------------
Show_Help() {
  echo "Usage: notrack"
  echo "Downloads and Installs updated tracker lists"
  echo
  echo "The following options can be specified:"
  echo -e "  -h, --help\tDisplay this help and exit"
  echo -e "  -t, --test\tConfig Test"
  echo -e "  -v, --version\tDisplay version information and exit"
  echo -e "  -u, --upgrade\tRun a full upgrade"
}
#Show Version--------------------------------------------------------
Show_Version() {
  echo "NoTrack Version v$Version"
  echo
}
#Test----------------------------------------------------------------
Test() {
  echo "NoTrack Config Test"
  echo
  echo "NoTrack version v$Version"
  if [ -e "$ConfigFile" ]; then
    echo "Config file $ConfigFile"
  else
    echo "No Config file available"
  fi
  Read_Config_File                               #Load saved variables
  Get_IPAddress                                  #Read IP Address of NetDev
  
  echo "BlockLists Utilised:"
  echo "BlockList_NoTrack ${Config[blocklist_notrack]}"
  echo "BlockList_TLD ${Config[blocklist_tld]}"
  echo "BlockList_QMalware ${Config[blocklist_qmalware]}"
  echo "BlockList_AdBlockManager ${Config[blocklist_adblockmanager]}"
  echo "BlockList_DisconnectMalvertising ${Config[blocklist_disconnectmalvertising]}"
  echo "BlockList_EasyList ${Config[blocklist_easylist]}"
  echo "BlockList_EasyPrivacy ${Config[blocklist_easyprivacy]}"
  echo "BlockList_FBAnnoyance ${Config[blocklist_fbannoyance]}"
  echo "BlockList_FBEnhanced ${Config[blocklist_fbenhanced]}"
  echo "BlockList_FBSocial ${Config[blocklist_fbsocial]}"
  echo "BlockList_hpHosts ${Config[blocklist_hphosts]}"
  echo "BlockList_MalwareDomainList ${Config[blocklist_malwaredomainlist]}"
  echo "BlockList_MalwareDomains ${Config[blocklist_malwaredomains]}"
  echo "BlockList_PglYoyo ${Config[blocklist_pglyoyo]}"
  echo "BlockList_SomeoneWhoCares ${Config[blocklist_someonewhocares]}"
  echo "BlockList_Spam404 ${Config[blocklist_spam404]}"
  echo "BlockList_SwissRansom ${Config[blocklist_swissransom]}"
  echo "BlockList_SwissZeus ${Config[blocklist_swisszeus]}"
  echo "BlockList_Winhelp2002 ${Config[blocklist_winhelp2002]}"
  echo "BlockList_CHNEasy ${Config[blocklist_chneasy]}"
  echo "BlockList_RUSEasy ${Config[blocklist_ruseasy]}"
}
#Upgrade-------------------------------------------------------------
Upgrade() {
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
      echo "Error Unable to find NoTrack folder"
      echo "Aborting"
      exit 22
    fi
  else    
    Check_File_Exists "$InstallLoc/ntrk-upgrade.sh"
    echo "Found alternate copy in $InstallLoc"
    sudo bash "$InstallLoc/ntrk-upgrade.sh"
  fi
}
#Main----------------------------------------------------------------
if [ "$1" ]; then                                #Have any arguments been given
  if ! options=$(getopt -o fhvtu -l help,force,version,upgrade,test -- "$@"); then
    # something went wrong, getopt will put out an error message for us
    exit 1
  fi

  set -- $options

  while [ $# -gt 0 ]
  do
    case $1 in      
      -f|--force)
        UnixTime=2524608000     #Change time forward to Jan 2050, which will force all lists to update
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
        Error_Exit "$0: error - unrecognized option $1"
      ;;
      (*) 
        break
      ;;
    esac
    shift
  done
fi
  
#--------------------------------------------------------------------
#At this point the functionality of notrack.sh is to update blocklists
#1. Check if user is running as root
#2. Create folder /etc/notrack
#3. Load config file (or use default values)
#4. Get IP address of system, e.g. 192.168.1.2
#5. Get last time (in Epoch) of when WhiteList was changed (If its more than 36 hours then we don't process BlackLists unless they have changed)
#6. Generate WhiteList if it doens't exist
#7. Load WhiteList file into WhiteList associative array
#8. Create csv file of blocked sites, or empty it if it exists
#9. Create BlackList, TLD BlackList, and TLD WhiteList if they don't exist
#10. Process Users Custom BlackList
#11. Process Other blocklists according to Config
#12. Delete TLD Blocklist file if Config says its disabled
#13. Tell user how many sites are blocked by counting number of lines with "Active" in
#14. If the number if changes is 1 or more then restart Dnsmasq
if [ "$(id -u)" != 0 ]; then                     #Check if running as root
  Error_Exit "Error this script must be run as root"
fi
  
if [ ! -d "/etc/notrack" ]; then                 #Check /etc/notrack folder exists
  echo "Creating notrack folder under /etc"
  echo
  mkdir "/etc/notrack"
  if [ ! -d "/etc/notrack" ]; then               #Check again
    Error_Exit "Error Unable to create folder /etc/notrack"      
  fi
fi
  
Read_Config_File                                 #Load saved variables
Get_IPAddress                                    #Read IP Address of NetDev
  
Get_FileTime "$WhiteListFile"
WhiteListFileTime=$FileTime
  
if [ ! -e $WhiteListFile ]; then Generate_WhiteList
fi
  
Read_WhiteList                                 #Load Whitelist into array
CreateFile "$BlockingCSV"
cat /dev/null > $BlockingCSV                   #Empty csv file
  
if [ ! -e "$BlackListFile" ]; then Generate_BlackList
fi

#Legacy files as of v0.7.14
DeleteOldFile /etc/notrack/domains.txt
DeleteOldFile /tmp/tld.txt

Process_TLDList

GetList_BlackList                                #Process Users Blacklist
  
GetList "notrack" "notrack" 172800               #2 Days
GetList "qmalware" "plain" 345600                #4 Days
GetList "adblockmanager" "unix127" 604800        #7 Days
GetList "disconnectmalvertising" "plain" 345600  #4 Days
GetList "easylist" "easylist" 345600             #4 Days
GetList "easyprivacy" "easylist" 345600          #4 Days
GetList "fbannoyance" "easylist" 172800          #2 Days
GetList "fbenhanced" "easylist" 172800           #2 Days
GetList "fbsocial" "easylist" 345600             #4 Days
GetList "hphosts" "unix127" 345600               #4 Days
GetList "malwaredomainlist" "unix127" 345600     #4 Days
GetList "malwaredomains" "plain" 345600          #4 Days
GetList "pglyoyo" "plain" 345600                 #4 Days
GetList "someonewhocares" "unix127" 345600       #4 Days
GetList "spam404" "easylist" 172800              #2 Days
GetList "swissransom" "plain" 86400              #1 Day
GetList "swisszeus" "plain" 86400                #1 Day
GetList "winhelp2002" "unix0" 604800             #7 Days
GetList "chneasy" "easylist" 345600              #China
GetList "ruseasy" "easylist" 345600              #Russia
  
if [ ${Config[blocklist_tld]} == 0 ]; then
  DeleteOldFile "$DomainQuickList"
fi
  
echo "Imported $(cat "$BlockingCSV" | grep -c Active) Domains into Block List"
  
if [ $ChangesMade -gt 0 ]; then                  #Have any lists been processed?
  echo "Restarting Dnsnmasq"
  service dnsmasq restart                        #Restart dnsmasq  
fi

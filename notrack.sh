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
IPVersion="IPv4"

declare -A Config
Config[blocklist_notrack]=1
Config[blocklist_tld]=1
Config[blocklist_adblockmanager]=0
Config[blocklist_easylist]=0
Config[blocklist_easyprivacy]=0
Config[blocklist_hphosts]=0
Config[blocklist_pglyoyo]=0
Config[blocklist_someonewhocares]=0
Config[blocklist_malwaredomains]=0
Config[blocklist_winhelp2002]=0

#Leave these Settings alone------------------------------------------
Version="0.7.1"
BlockingCSV="/etc/notrack/blocking.csv"
BlackListFile="/etc/notrack/blacklist.txt"
WhiteListFile="/etc/notrack/whitelist.txt"
DomainBlackListFile="/etc/notrack/domain-blacklist.txt"
DomainWhiteListFile="/etc/notrack/domain-whitelist.txt"
DomainQuickList="/etc/notrack/domain-quick.list"
ConfigFile="/etc/notrack/notrack.conf"

declare -A URLList                               #Array of URL's
URLList[notrack]="http://quidsup.net/trackers.txt"
URLList[tld]="http://quidsup.net/malicious-domains.txt"
URLList[adblockmanager]="http://adblock.gjtech.net/?format=unix-hosts"
URLList[easylist]="https://easylist-downloads.adblockplus.org/easylist_noelemhide.txt"
URLList[easyprivacy]="https://easylist-downloads.adblockplus.org/easyprivacy.txt"
URLList[hphosts]="http://hosts-file.net/ad_servers.txt"
URLList[malwaredomains]="http://mirror1.malwaredomains.com/files/justdomains"
URLList[pglyoyo]="http://pgl.yoyo.org/adservers/serverlist.php?hostformat=;mimetype=plaintext"
URLList[someonewhocares]="http://someonewhocares.org/hosts/hosts"
URLList[winhelp2002]="http://winhelp2002.mvps.org/hosts.txt"

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
          BlockList_AdBlockManager) Config[blocklist_adblockmanager]="$Value";;
          BlockList_EasyList) Config[blocklist_easylist]="$Value";;
          BlockList_EasyPrivacy) Config[blocklist_easyprivacy]="$Value";;
          BlockList_hpHosts) Config[blocklist_hphosts]="$Value";;
          BlockList_MalwareDomains) Config[blocklist_malwaredomains]="$Value";;
          BlockList_PglYoyo) Config[blocklist_pglyoyo]="$Value";;
          BlockList_SomeoneWhoCares) Config[blocklist_someonewhocares]="$Value";;
          BlockList_Winhelp2002) Config[blocklist_winhelp2002]="$Value";;
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
#Generate Domain BlackList-------------------------------------------
Generate_DomainBlackList() {
  local -a Tmp                                   #Local array to build contents of file
  
  echo "Creating domain blacklist"
  touch "$DomainBlackListFile"
  Tmp+=("#Use this file to add additional domains to the blocklist.")
  Tmp+=("#Run notrack script (sudo notrack) after you make any changes to this file")
  Tmp+=("# I have divided the list info three different classifications:")
  Tmp+=("# 1: Very high risk - Cheap/Free domains which attract a high number of scammers. This list gets downloaded from: ${URLList[tld]}")
  Tmp+=("# 2: Risky - More of a mixture of legitimate to malicious domains. Consider enabling blocking of these domains, unless you live in one of the countries listed.")
  Tmp+=("# 3: Low risk - Malicious sites do appear in these domains, but they are well in the minority.")
  Tmp+=("# Risky domains----------------------------------------")
  Tmp+=("#.asia #Asia-Pacific")
  Tmp+=("#.biz #Business")
  Tmp+=("#.cc #Cocos Islands")
  Tmp+=("#.co #Columbia")
  Tmp+=("#.cn #China")
  Tmp+=("#.eu #European Union")
  Tmp+=("#.ga # Gabonese Republic")
  Tmp+=("#.in #India")
  Tmp+=("#.info #Information")
  Tmp+=("#.mobi #Mobile Devices")
  Tmp+=("#.org #Organisations")
  Tmp+=("#.pl #Poland")
  Tmp+=("#.ru #Russia")
  Tmp+=("#.us #USA")
  Tmp+=("# Low Risk domains--------------------------------------")
  Tmp+=("#.am #Armenia")
  Tmp+=("#.hr #Croatia")
  Tmp+=("#.hu #Hungary")
  Tmp+=("#.pe #Peru")
  Tmp+=("#.rs #Serbia")
  Tmp+=("#.st #São Tomé and Príncipe")
  Tmp+=("#.tc #Turks and Caicos Islands")
  Tmp+=("#.th #Thailand")
  Tmp+=("#.tk #Tokelau")
  Tmp+=("#.tl #East Timor")
  Tmp+=("#.tt #Trinidad and Tobago")
  Tmp+=("#.tv #Tuvalu")
  Tmp+=("#.vn #Vietnam")
  Tmp+=("#.ws #Western Samoa")
  printf "%s\n" "${Tmp[@]}" > $DomainBlackListFile   #Write Array to file with line seperator
}
#Generate Domain WhiteList-------------------------------------------
Generate_DomainWhiteList() {
  local -a Tmp                                   #Local array to build contents of file
  
  echo "Creating Domain whitelist"
  touch "$DomainWhiteListFile"
  Tmp+=("#Use this file to remove files malicious domains from block list")
  Tmp+=("#Run notrack script (sudo notrack) after you make any changes to this file")
  Tmp+=("#.cf #Central African Republic")
  Tmp+=("#.cricket")
  Tmp+=("#.country")
  Tmp+=("#.gq #Equatorial Guinea")
  Tmp+=("#.kim")
  Tmp+=("#.link")
  Tmp+=("#.party")
  Tmp+=("#.pink")
  Tmp+=("#.review")
  Tmp+=("#.science")
  Tmp+=("#.work")
  Tmp+=("#.xyz")
  printf "%s\n" "${Tmp[@]}" > $DomainWhiteListFile #Write Array to file with line seperator
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
  echo "IP Version: $IPVersion"
  
  if [ "$IPVersion" == "IPv4" ]; then
    echo "Reading IPv4 Address from $NetDev"
    IPAddr=$(ip addr list "$NetDev" |grep "inet " |cut -d' ' -f6|cut -d/ -f1)
    
  elif [ "$IPVersion" == "IPv6" ]; then
    echo "Reading IPv6 Address"
    IPAddr=$(ip addr list "$NetDev" |grep "inet6 " |cut -d' ' -f6|cut -d/ -f1)    
  else
    Error_Exit "Unknown IP Version"    
  fi
  echo "System IP Address $IPAddr"
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
  #local Method="$2"
  
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
#||ozone.ru^$third-party,domain=~ozon.ru|~ozonru.co.il|~ozonru.com|~ozonru.eu|~ozonru.kz
#||promotools.biz^$third-party
#||surveysforgifts.org^$popup,third-party
#||dt00.net^$third-party,domain=~marketgid.com|~marketgid.ru|~marketgid.ua|~mgid.com|~thechive.com
#||pubdirecte.com^$third-party,domain=~debrideurstream.fr
  #$1 = SourceFile
  CalculatePercentPoint "$1"
  i=1                                            #Progress counter
  j=$JumpPoint                                   #Jump in percent
    
  while IFS=$' \n' read -r Line
  do
    if [[ $Line =~ ^\|\|[a-z0-9\.-]*\^\$third-party$ ]]; then
      AddSite "${Line:2:-13}" ""
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
  
  declare -A DomainWhiteList
  
  CreateFile "$DomainQuickList"                  #Quick lookup file for stats.php
  cat /dev/null > "$DomainQuickList"             #Empty file
  
  while IFS=$'#\n' read -r Line _
  do
    if [[ ! $Line =~ ^\ *# && -n $Line ]]; then
      Line="${Line%%\#*}"                        #Delete comments
      Line="${Line%%*( )}"                       #Delete trailing spaces
      DomainWhiteList[$Line]="$Line"             #Add domain to associative array      
    fi
  done < "$DomainWhiteListFile"
  
  while IFS=$'#\n' read -r Line Comment _         
  do
    if [[ ! $Line =~ ^\ *# && -n $Line ]]; then
      Line="${Line%%\#*}"                        #Delete comments
      Line="${Line%%*( )}"                       #Delete trailing spaces            
      if [ "${DomainWhiteList[$Line]}" ]; then
        CSVList+=("$Line,Disabled,$Comment")
      else
        DNSList+=("address=/$Line/$IPAddr")
        CSVList+=("$Line,Active,$Comment")
        echo "$Line" >> "$DomainQuickList"
      fi
    fi
  done < /tmp/tld.txt
  
  while IFS=$'#\n' read -r Line Comment _
  do
    if [[ ! $Line =~ ^\ *# && -n $Line ]]; then
      Line="${Line%%\#*}"                        #Delete comments
      Line="${Line%%*( )}"                       #Delete trailing spaces            
      if [ "${DomainWhiteList[$Line]}" ]; then
        CSVList+=("$Line,Disabled,$Comment")
      else
        DNSList+=("address=/$Line/$IPAddr")
        CSVList+=("$Line,Active,$Comment")
        echo "$Line" >> "$DomainQuickList"
      fi
    fi
  done < "$DomainBlackListFile" 
  
}
#Process UnixList 0--------------------------------------------------
#Unix hosts file starting 0.0.0.0 site.com
Process_UnixList0() {
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
#Unix hosts file starting 127.0.0.1 site.com
Process_UnixList127() {
  #$1 = SourceFile
  CalculatePercentPoint "$1"
  i=1                                            #Progress counter
  j=$JumpPoint                                   #Jump in percent
  
  while IFS=$'\r' read -r Line _
  do
    if [[ ${Line:0:3} == "127" ]]; then          #Does line start with 127
      Line=${Line:10}                            #Trim 127.0.0.1
      Line="${Line%%\#*}"                        #Delete comments
      if [[ ! $Line =~ ^(#|localhost|www|EOF|\[) ]]; then        
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
#Upgrade-------------------------------------------------------------
Web_Upgrade() {
  if [ "$(id -u)" == "0" ]; then                 #Check if running as root
     echo "Error do not run the upgrader as root"
     Error_Exit "Execute with: bash notrack -b / notrack -u"     
  fi
  
  Check_File_Exists "/var/www/html/admin"
  InstallLoc=$(readlink -f /var/www/html/admin/)
  InstallLoc=${InstallLoc/%\/admin/}             #Trim "/admin" from string
    
  if [ "$(command -v git)" ]; then               #Utilise Git if its installed
    echo "Pulling latest updates of NoTrack using Git"
    cd "$InstallLoc" || Error_Exit "Unable to cd to $InstallLoc"
    git pull
    if [ $? != "0" ]; then                       #Git repository not found
      if [ -d "$InstallLoc-old" ]; then          #Delete NoTrack-old folder if it exists
        echo "Removing old NoTrack folder"
        rm -rf "$InstallLoc-old"
      fi
      echo "Moving $InstallLoc folder to $InstallLoc-old"
      mv "$InstallLoc" "$InstallLoc-old"
      echo "Cloning NoTrack to $InstallLoc with Git"
      git clone --depth=1 https://github.com/quidsup/notrack.git "$InstallLoc"
    fi
  else                                           #Git not installed, fallback to wget
    if [ -d "$InstallLoc" ]; then                #Check if NoTrack folder exists  
      if [ -d "$InstallLoc-old" ]; then          #Delete NoTrack-old folder if it exists
        echo "Removing old NoTrack folder"
        rm -rf "$InstallLoc-old"
      fi
      echo "Moving $InstallLoc folder to $InstallLoc-old"
      mv "$InstallLoc" "$InstallLoc-old"
    fi

    echo "Downloading latest version of NoTrack from https://github.com/quidsup/notrack/archive/master.zip"
    wget -O /tmp/notrack-master.zip https://github.com/quidsup/notrack/archive/master.zip
    if [ ! -e /tmp/notrack-master.zip ]; then    #Check to see if download was successful
      #Abort we can't go any further without any code from git
      Error_Exit "Error Download from github has failed"      
    fi
  
    echo "Unzipping notrack-master.zip"
    unzip -oq /tmp/notrack-master.zip -d /tmp
    echo "Copying folder across to $InstallLoc"
    mv /tmp/notrack-master "$InstallLoc"
    echo "Removing temporary files"
    rm /tmp/notrack-master.zip                  #Cleanup
  fi
  echo "Upgrade complete"
}

#Full Upgrade--------------------------------------------------------
Full_Upgrade() {
  #This function is run after Web_Upgrade
  #All we need to do is copy notrack.sh script to /usr/local/sbin
  
  InstallLoc=$(readlink -f /var/www/html/admin/)
  InstallLoc=${InstallLoc/%\/admin/}             #Trim "/admin" from string
  
  Check_File_Exists "$InstallLoc/notrack.sh"
  sudo cp "$InstallLoc/notrack.sh" /usr/local/sbin/
  sudo mv /usr/local/sbin/notrack.sh /usr/local/sbin/notrack
  sudo chmod +x /usr/local/sbin/notrack
  
  Check_File_Exists "$InstallLoc/ntrk-exec.sh"
  sudo cp "$InstallLoc/ntrk-exec.sh" /usr/local/sbin/
  sudo mv /usr/local/sbin/ntrk-exec.sh /usr/local/sbin/ntrk-exec
  sudo chmod 755 /usr/local/sbin/ntrk-exec
  
  Check_File_Exists "$InstallLoc/ntrk-pause.sh"
  sudo cp "$InstallLoc/ntrk-pause.sh" /usr/local/sbin/
  sudo mv /usr/local/sbin/ntrk-pause.sh /usr/local/sbin/ntrk-pause
  sudo chmod 755 /usr/local/sbin/ntrk-pause
  
  SudoCheck=$(sudo cat /etc/sudoers | grep www-data)
  if [[ $SudoCheck == "" ]]; then
    echo "Adding NoPassword permissions for www-data to execute script /usr/local/sbin/ntrk-exec as root"
    echo -e "www-data\tALL=(ALL:ALL) NOPASSWD: /usr/local/sbin/ntrk-exec" | sudo tee -a /etc/sudoers
  fi
  
  if [ -e "$ConfigFile" ]; then                  #Remove Latestversion number from Config file
     echo "Removing version number from Config file"
     sudo grep -v "LatestVersion" "$ConfigFile" > /tmp/notrack.conf
     sudo mv /tmp/notrack.conf "$ConfigFile"
  fi
  
  
  echo "NoTrack Script updated"
}
#Help----------------------------------------------------------------
Show_Help() {
  echo "Usage: notrack"
  echo "Downloads and Installs updated tracker lists"
  echo
  echo "The following options can be specified:"
  echo -e "  -b\t\tUpgrade web pages only"
  echo -e "  -h, --help\tDisplay this help and exit"
  echo -e "  -v, --version\tDisplay version information and exit"
  echo -e "  -u, --upgrade\tRun a full upgrade"
}

#Show Version--------------------------------------------------------
Show_Version() {
  echo "NoTrack Version v$Version"  
  echo
}

#Main----------------------------------------------------------------
if [ "$1" ]; then                                #Have any arguments been given
  if ! options=$(getopt -o bfhvu -l help,force,version,upgrade -- "$@"); then
    # something went wrong, getopt will put out an error message for us
    exit 1
  fi

  set -- $options

  while [ $# -gt 0 ]
  do
    case $1 in
      -b) 
        Web_Upgrade
        exit 0
      ;;
      -f|--force)
        UnixTime=2678400     #Change time back to Feb 1970, which will force all lists to update
      ;;
      -h|--help) 
        Show_Help
        exit 0
      ;;
      -v|--version) 
        Show_Version
        exit 0
      ;;
      -u|--upgrade)
        Web_Upgrade
        Full_Upgrade
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
  
#Legacy files to delete, remove at Beta release
DeleteOldFile "/etc/dnsmasq.d/adsites.list"    
DeleteOldFile "/etc/dnsmasq.d/malicious-domains.list"
DeleteOldFile "/etc/notrack/trackers.txt"
DeleteOldFile "/etc/notrack/tracker-quick.list" 

  
if [ ! -e "$BlackListFile" ]; then Generate_BlackList
fi
  
if [ ! -e "$DomainBlackListFile" ]; then Generate_DomainBlackList
fi
  
if [ ! -e "$DomainWhiteListFile" ]; then Generate_DomainWhiteList
fi
  
GetList_BlackList                                #Process Users Blacklist
  
GetList "tld" "tldlist" 604800                   #7 Days
GetList "notrack" "notrack" 172800               #2 Days  
GetList "adblockmanager" "unix127" 604800        #7 Days
GetList "easylist" "easylist" 345600             #4 Days
GetList "easyprivacy" "easylist" 345600          #4 Days
GetList "hphosts" "unix127" 345600               #4 Days
GetList "malwaredomains" "plain" 345600          #4 Days
GetList "pglyoyo" "plain" 345600                 #4 Days
GetList "someonewhocares" "unix127" 345600       #4 Days
GetList "winhelp2002" "unix0" 604800             #7 Days
  
if [ ${Config[blocklist_tld]} == 0 ]; then
  DeleteOldFile "$DomainQuickList"
fi
  
echo "Imported $(cat "$BlockingCSV" | grep -c Active) Domains into Block List"
  
if [ $ChangesMade -gt 0 ]; then                  #Have any lists been processed?
  echo "Restarting Dnsnmasq"
  service dnsmasq restart                        #Restart dnsmasq  
fi

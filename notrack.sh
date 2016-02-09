#!/bin/bash
#Title : NoTrack
#Description : This script will download latest Adblock Domain block files from quidsup.net, then parse them into Dnsmasq.
#Script will also create quick.lists for use by stats.php web page
#Author : QuidsUp
#Date : 2015-01-14
#Usage : sudo bash notrack.sh

#User Configerable Variables in case config file is missing----------
#Set NetDev to the name of network device e.g. "eth0" IF you have multiple network cards
NetDev=$(ip -o link show | awk '{print $2,$9}' | grep ": UP" | cut -d ":" -f 1)
IPVersion="IPv4"

#Blocklist Sources and their file format-----------------------------
#NoTrack - PlainList + Special http://quidsup.net/trackers.txt
#TLD - PlainList
#AdBlockManager - UnixList127 - http://adblock.gjtech.net/?format=unix-hosts
#https://easylist.adblockplus.org
#EasyList - EasyList - https://easylist-downloads.adblockplus.org/easylist_noelemhide.txt
#EasyPrivacy - EasyList - https://easylist-downloads.adblockplus.org/easyprivacy.txt
#hpHosts - UnixList127 - http://hosts-file.net
#PglYoyo - PlainList - http://pgl.yoyo.org/adservers
#SomeoneWhoCares - UnixList127 - http://someonewhocares.org/hosts/
#MalwareDomains - PlainList - mirror1.malwaredomains.com/files/justdomains
#Winhelp2002 - UnixList0 - http://winhelp2002.mvps.org/hosts.txt

#System Variables----------------------------------------------------
Version="0.6.2"
TrackerSource="http://quidsup.net/trackers.txt" 
TrackerListFile="/etc/dnsmasq.d/trackers.list" 
TrackerQuickList="/etc/notrack/tracker-quick.list"
BlackListFile="/etc/notrack/blacklist.txt"
WhiteListFile="/etc/notrack/whitelist.txt"
WhiteListCount=0
DomainSource="http://quidsup.net/malicious-domains.txt"
DomainListFile="/etc/dnsmasq.d/malicious-domains.list"
DomainBlackList="/etc/notrack/domain-blacklist.txt"
DomainWhiteList="/etc/notrack/domain-whitelist.txt"
DomainQuickList="/etc/notrack/domain-quick.list"
ConfigFile="/etc/notrack/notrack.conf"
OldLatestVersion="$Version"

BlockList_NoTrack=1
BlockList_TLD=1
BlockList_AdBlockManager=0
BlockList_EasyList=0
BlockList_EasyPrivacy=0
BlockList_hpHosts=0
BlockList_PglYoyo=0
BlockList_SomeoneWhoCares=0
BlockList_MalwareDomains=0
BlockList_Winhelp2002=0

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
    echo "Deleting old file $1"
    rm "$1"
    echo
  fi
}
#Add Site to List-----------------------------------------------------
AddSite() {
  #$1 = Site to Add
  #$2 = File
  #$3 = Comment
  
  if [ ${#1} == 0 ]; then return; fi             #Ignore zero length str
  
  if [[ $WhiteListCount == 0 ]]; then            #No Items in blocklist?
    echo "address=/$1/$IPAddr" >> "$2"           #Just add site to tracker list
    echo "$1,Active,$3" >> $TrackerQuickList
  else
    for WLSite in "${WhiteList[@]}"              #Loop through White List
    do      
      if [[ $1 == "$WLSite" ]]; then             #Matched, site disabled
        echo "$1,Disabled,$3" >> $TrackerQuickList
        return 0                                 #Leave function
      fi
    done
    
    echo "address=/$1/$IPAddr" >> "$2"           #No match in whitelist    echo "$1,Active,$3" >> $TrackerQuickList     
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
          BlockList_NoTrack) BlockList_NoTrack="$Value";;
          BlockList_TLD) BlockList_TLD="$Value";;
          BlockList_AdBlockManager) BlockList_AdBlockManager="$Value";;
          BlockList_EasyList) BlockList_EasyList="$Value";;
          BlockList_EasyPrivacy) BlockList_EasyPrivacy="$Value";;
          BlockList_hpHosts) BlockList_hpHosts="$Value";;
          BlockList_MalwareDomains) BlockList_MalwareDomains="$Value";;
          BlockList_PglYoyo) BlockList_PglYoyo="$Value";;
          BlockList_SomeoneWhoCares) BlockList_SomeoneWhoCares="$Value";;
          BlockList_Winhelp2002) BlockList_Winhelp2002="$Value";;
        esac            
      fi
    done < $ConfigFile
  fi 
}

#Read White List-----------------------------------------------------
Read_WhiteList() {
  while IFS=' ' read -r Line
  do
    if [[ ! $Line =~ ^\ *# && -n $Line ]]; then
      Line="${Line%%\#*}"                        #Delete comments
      Line="${Line%%*( )}"                       #Delete trailing spaces
      WhiteList[$WhiteListCount]="$Line"      
      ((WhiteListCount++))
    fi
  done < $WhiteListFile  
}
#Check Lists---------------------------------------------------------
Check_Lists() {  
  if [ ! -e $BlackListFile ]; then               #Check if Blacklist exists
    echo "Creating blacklist"
    touch $BlackListFile
    echo "#Use this file to add additional websites to be blocked" >> $BlackListFile
    echo "#Run notrack script (sudo notrack) after you make any changes to this file" >> $BlackListFile
    echo "#doubleclick.net" >> $BlackListFile
    echo "#googletagmanager.com" >> $BlackListFile
    echo "#googletagservices.com" >> $BlackListFile
  fi

  if [ ! -e $WhiteListFile ]; then               #Check if Whitelist exists
    echo "Creating whitelist"
    touch $WhiteListFile
    echo "# Use this file to remove files from blocklist" >> $WhiteListFile
    echo "# Run notrack script (sudo notrack) after you make any changes to this file" >> $WhiteListFile
    echo "#doubleclick.net" >> $WhiteListFile
    echo "#google-analytics.com" >> $WhiteListFile
  fi
  
  if [ ! -e $DomainBlackList ]; then             #Check if DomainBlacklist exists
    echo "Creating domain blacklist"
    touch $DomainBlackList
    echo "#Use this file to add additional domains to the blocklist." >> $DomainBlackList
    echo "#Run notrack script (sudo notrack) after you make any changes to this file" >> $DomainBlackList
    echo "# I have divided the list info three different classifications:" >> $DomainBlackList
    echo "# 1: Very high risk - Cheap/Free domains which attract a high number of scammers. This list gets downloaded from: $DomainSource" >> $DomainBlackList
    echo "# 2: Risky - More of a mixture of legitimate to malicious domains. Consider enabling blocking of these domains, unless you live in one of the countries listed." >> $DomainBlackList
    echo "# 3: Low risk - Malicious sites do appear in these domains, but they are well in the minority." >> $DomainBlackList

    echo "# Risky domains----------------------------------------" >> $DomainBlackList
    echo "#.asia #Asia-Pacific" >> $DomainBlackList
    echo "#.biz #Business" >> $DomainBlackList
    echo "#.cc #Cocos Islands" >> $DomainBlackList
    echo "#.co #Columbia" >> $DomainBlackList
    echo "#.cn #China" >> $DomainBlackList
    echo "#.eu #European Union" >> $DomainBlackList
    echo "#.ga # Gabonese Republic" >> $DomainBlackList
    echo "#.in #India" >> $DomainBlackList
    echo "#.info #Information" >> $DomainBlackList
    echo "#.mobi #Mobile Devices" >> $DomainBlackList
    echo "#.org #Organisations" >> $DomainBlackList
    echo "#.pl #Poland" >> $DomainBlackList
    echo "#.ru #Russia" >> $DomainBlackList
    echo "#.us #USA" >> $DomainBlackList

    echo "# Low Risk domains--------------------------------------" >> $DomainBlackList
    echo "#.am #Armenia" >> $DomainBlackList
    echo "#.hr #Croatia" >> $DomainBlackList
    echo "#.hu #Hungary" >> $DomainBlackList
    echo "#.pe #Peru" >> $DomainBlackList
    echo "#.rs #Serbia" >> $DomainBlackList
    echo "#.st #São Tomé and Príncipe" >> $DomainBlackList
    echo "#.tc #Turks and Caicos Islands" >> $DomainBlackList
    echo "#.th #Thailand" >> $DomainBlackList
    echo "#.tk #Tokelau" >> $DomainBlackList
    echo "#.tl #East Timor" >> $DomainBlackList
    echo "#.tt #Trinidad and Tobago" >> $DomainBlackList
    echo "#.tv #Tuvalu" >> $DomainBlackList
    echo "#.vn #Vietnam" >> $DomainBlackList
    echo "#.ws #Western Samoa" >> $DomainBlackList  
  fi

  if [ ! -e $DomainWhiteList ]; then             #Check if Domain Whitelist exists
    echo "Creating Domain whitelist"
    echo
    touch $DomainWhiteList
    echo "#Use this file to remove files malicious domains from blocklist" >> $DomainWhiteList
    echo "#Run notrack script (sudo notrack) after you make any changes to this file" >> $DomainWhiteList    
    echo "#.cf #Central African Republic" >> $DomainWhiteList
    echo "#.cricket" >> $DomainWhiteList
    echo "#.country" >> $DomainWhiteList
    echo "#.gq #Equatorial Guinea" >> $DomainWhiteList
    echo "#.kim" >> $DomainWhiteList
    echo "#.link" >> $DomainWhiteList
    echo "#.party" >> $DomainWhiteList
    echo "#.pink" >> $DomainWhiteList
    echo "#.review" >> $DomainWhiteList
    echo "#.science" >> $DomainWhiteList
    echo "#.work" >> $DomainWhiteList
    echo "#.xyz" >> $DomainWhiteList
  fi
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

#NoTrack BlockList---------------------------------------------------
GetList_NoTrack() {
  CreateFile "$TrackerListFile"  
  
  echo "Downloading Tracker Site List from: $TrackerSource"
  echo
  wget -O /etc/notrack/trackers.txt $TrackerSource
  echo
  Check_File_Exists "/etc/notrack/trackers.txt"
  
  echo "Processing NoTrack BlockList"
  echo "#Tracker Blocklist last updated $(date)" > $TrackerListFile
  echo "#Don't make any changes to this file, use $BlackListFile and $WhiteListFile instead" >> $TrackerListFile
    
  i=0                                            #Progress counter
  j=1                                            #Percent point
  c=$(wc -l "/etc/notrack/trackers.txt" | cut -d " " -f 1)              #Count number of lines
  c=$((c/100))                                   #Calculate 1%
  
  while IFS='# ' read -r Line Comment
  do
    if [[ ! $Line =~ ^\ *# && -n $Line ]]; then
      Line="${Line%%\#*}"                        #Delete comments
      Line="${Line%%*( )}"                       #Delete trailing spaces      
      AddSite "$Line" "$TrackerListFile" "$Comment"      
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
    
    if [ $i -ge $c ]; then                       #Display progress
      echo -ne " $j%  \r"      
      ((j++))
      i=0      
    fi
    ((i++))
  done < /etc/notrack/trackers.txt
  
  echo 
  echo "Finished processing NoTrack Blocklist"
  echo
}

#TLD BlockList-------------------------------------------------------
GetList_TLD() {
  CreateFile $DomainListFile
  CreateFile $DomainQuickList
  
  echo "Downloading Malcious Domain List from: $DomainSource"
  echo
  wget -O /etc/notrack/domains.txt $DomainSource
  
  Check_File_Exists "/etc/notrack/domains.txt"
  
  #Merge Domainlist with users Blacklist
  cat /etc/notrack/domains.txt $DomainBlackList > /tmp/combined.txt

  #Merge Whitelist with above two lists to remove duplicates
  echo "#Domain Blocklist last updated $(date)" > $DomainListFile
  echo "#Don't make any changes to this file, use $DomainBlackList and $DomainWhiteList instead" >> $DomainListFile
  cat /dev/null > $DomainQuickList

  awk 'NR==FNR{A[$1]; next}!($1 in A)' $DomainWhiteList /tmp/combined.txt | while read -r Line; do
    if [[ ! $Line =~ ^\ *# && -n $Line ]]; then 
      Line="${Line%%\#*}"  # Del in line right comments
      Line="${Line%%*( )}" # Del trailing spaces 
      echo "address=/$Line/$IPAddr" >> $DomainListFile
      echo "$Line" >> $DomainQuickList
    fi
  done

  echo "Imported $(wc -l $DomainQuickList | cut -d' ' -f1) Malicious Domains into TLD block list"
  
  rm /tmp/combined.txt                           #Clear up
  echo
}

#NoTrack BlockList---------------------------------------------------
GetList_BlackList() {
  echo "Processing Custom Black List"
  Process_PlainList "$BlackListFile" "$TrackerListFile"
  echo "Finished processing Custom Black List"
  echo
}
#GetList AdBlockManager----------------------------------------------
GetList_AdBlockManager() {
  echo "Downloading AdBlock Manager List"
  wget -O /tmp/adblockmanager.txt "http://adblock.gjtech.net/?format=unix-hosts"
  
  if [ ! -e /tmp/adblockmanager.txt ]; then      #Check if list has been downloaded
    echo "File not downloaded"                   #Warn user
    return 1
  fi
  
  CreateFile "/etc/dnsmasq.d/adblockmanager.list"
  echo "Processing AdBlock Manager List"
  Process_UnixList127 "/tmp/adblockmanager.txt" "/etc/dnsmasq.d/adblockmanager.list"
  echo "Finished processing AdBlock Manager List"
  echo
}
#GetList EasyList----------------------------------------------------
GetList_EasyList() {
  echo "Downloading EasyList"
  wget -O /tmp/easylist.txt "https://easylist-downloads.adblockplus.org/easylist_noelemhide.txt"

  if [ ! -e /tmp/easylist.txt ]; then            #Check if list has been downloaded
    echo "File not downloaded"                   #Warn user
    return 1
  fi
  
  CreateFile "/etc/dnsmasq.d/easylist.list"
  echo "Processing EasyList"
  Process_EasyList "/tmp/easylist.txt" "/etc/dnsmasq.d/easylist.list"
  echo "Finished processing EasyList"
  rm /tmp/easylist.txt
  echo
}
#GetList EasyPrivacy-------------------------------------------------
GetList_EasyPrivacy() {
  echo "Downloading EasyPrivacy"
  #wget -O /tmp/easyprivacy.txt "https://easylist-downloads.adblockplus.org/easyprivacy.txt"

  if [ ! -e /tmp/easyprivacy.txt ]; then         #Check if list has been downloaded
    echo "File not downloaded"                   #Warn user
    return 1
  fi
  
  CreateFile "/etc/dnsmasq.d/easyprivacy.list"
  echo "Processing EasyPrivacy"
  Process_EasyList "/tmp/easyprivacy.txt" "/etc/dnsmasq.d/easyprivacy.list"
  echo "Finished processing EasyPrivacy"
  #rm /tmp/easylist.txt
  echo
}
#GetList hpHosts-----------------------------------------------------
GetList_hpHosts() {
  echo "Downloading hpHosts Block List"
  wget -O /tmp/hphosts.txt "http://hosts-file.net/ad_servers.txt"
  
  if [ ! -e /tmp/hphosts.txt ]; then             #Check if list has been downloaded
    echo "File not downloaded"                   #Warn user
    return 1
  fi
  
  CreateFile "/etc/dnsmasq.d/hphosts.list"
  echo "Processing hpHosts Block List"
  Process_UnixList127 "/tmp/hphosts.txt" "/etc/dnsmasq.d/hphosts.list"
  echo "Finished processing hpHosts Block List"
  echo
  rm /tmp/hphosts.txt
}
#GetList Malware Domains---------------------------------------------
GetList_MalwareDomains() {
  echo "Downloading Malware Domains Block List"
  wget -O /tmp/malwaredomains.txt "http://mirror1.malwaredomains.com/files/justdomains"
  #Alt https://mirror.cedia.org.ec/malwaredomains/justdomains
  
  if [ ! -e /tmp/malwaredomains.txt ]; then      #Check if list has been downloaded
    echo "File not downloaded"                   #Warn user
    return 1
  fi
  
  CreateFile "/etc/dnsmasq.d/malwaredomains.list"
  echo "Processing Malware Domains Block List"
  Process_PlainList "/tmp/malwaredomains.txt" "/etc/dnsmasq.d/malwaredomains.list"
  echo "Finished processing Malware Domains Block List"
  echo
  rm /tmp/malwaredomains.txt
}
#PGL Yoyo BlockList--------------------------------------------------
GetList_PglYoyo() {  
  echo "Downloading PglYoyo BlockList"
  wget -O /tmp/pglyoyo.txt "http://pgl.yoyo.org/adservers/serverlist.php?hostformat=;mimetype=plaintext"
  
  if  [ ! -e /tmp/pglyoyo.txt ]; then            #Check list has been downloaded
    echo "File not downloaded"                   #Warn user
    return 1                                     
  fi
  
  CreateFile "/etc/dnsmasq.d/pglyoyo.list"
  echo "Processing PglYoyo Blocklist"
  Process_PlainList "/tmp/pglyoyo.txt" "/etc/dnsmasq.d/pglyoyo.list"
  echo "Finished processing PglYoyo Blocklist"
  rm /tmp/pglyoyo.txt                            #Clean up
  echo
}
#Get List SomeoneWhoCares--------------------------------------------
GetList_SomeoneWhoCares() {
  echo "Downloading SomeoneWhoCares Block List"
  wget -O /tmp/someonewhocares.txt "http://someonewhocares.org/hosts/hosts"
  
  if [ ! -e /tmp/someonewhocares.txt ]; then     #Check list has been downloaded
    echo "File not downloaded"                   #Warn user
    return 1
  fi
  
  CreateFile "/etc/dnsmasq.d/someonewhocares.list"
  echo "Processing SomeoneWhoCares Block List"
  Process_UnixList127 "/tmp/someonewhocares.txt" "/etc/dnsmasq.d/someonewhocares.list"
  echo "Finished processing SomeoneWhoCares Block List"
  echo
  rm /tmp/someonewhocares.txt
}
#Get List Winhelp2002------------------------------------------------
GetList_Winhelp2002() {
  echo "Downloading Winhelp2002 Block List"
  wget -O /tmp/winhelp2002.txt "http://winhelp2002.mvps.org/hosts.txt"
  
  if [ ! -e /tmp/winhelp2002.txt ]; then         #Check list has been downloaded
    echo "File not downloaded"                   #Warn user
    return 1
  fi
  
  CreateFile "/etc/dnsmasq.d/winhelp2002.list"
  echo "Processing Winhelp2002 Block List"
  Process_UnixList0 "/tmp/winhelp2002.txt" "/etc/dnsmasq.d/winhelp2002.list"
  echo "Finished processing Winhelp2002 Block List"
  echo
  rm /tmp/winhelp2002.txt
}
#Process EasyList----------------------------------------------------
Process_EasyList() {
#||ozone.ru^$third-party,domain=~ozon.ru|~ozonru.co.il|~ozonru.com|~ozonru.eu|~ozonru.kz
#||promotools.biz^$third-party
#||surveysforgifts.org^$popup,third-party
#||dt00.net^$third-party,domain=~marketgid.com|~marketgid.ru|~marketgid.ua|~mgid.com|~thechive.com
#||pubdirecte.com^$third-party,domain=~debrideurstream.fr
#$1 = SourceFile
#$2 = DestFile
  i=0                                            #Progress counter
  j=1                                            #Percent point
  c=$(wc -l "$1" | cut -d " " -f 1)              #Count number of lines
  c=$((c/100))                                   #Calculate 1%
    
  while IFS=' ' read -r Line
  do
    if [[ $Line =~ ^\|\|[a-z0-9\.-]*\^\$third-party$ ]]; then
      AddSite "${Line:2:-13}" "$2" ""
    elif [[ $Line =~ ^\|\|[a-z0-9\.-]*\^\$popup\,third-party$ ]]; then
      AddSite "${Line:2:-19}" "$2" ""
    elif [[ $Line =~ ^\|\|[a-z0-9\.-]*\^\$third-party\,domain=~ ]]; then
      #^$third-party,domain= apepars mid line, we need to replace it with a | pipe seperator like the rest of the line has
      Line=$(sed 's/\^$third-party,domain=~/\|/g' <<< "$Line")
      IFS='|~', read -r -a ArrayOfLine <<< "$Line" #Explode into array using seperator | or ~
      for Line in "${ArrayOfLine[@]}"            #Loop through array
      do
        AddSite "$Line" "$2" ""
      done  
    fi
    
    if [ $i -ge $c ]; then                       #Display progress
      echo -ne " $j%  \r"      
      ((j++))
      i=0      
    fi
    ((i++))
  done < "$1"
  echo 
}

#Process PlainList---------------------------------------------------
#Plain Lists are styled like:
# #Comment
# Site
# Site #Comment
Process_PlainList() {
#$1 = SourceFile
#$2 = DestFile
  i=0                                            #Progress counter
  j=1                                            #Percent point
  c=$(wc -l "$1" | cut -d " " -f 1)              #Count number of lines
  c=$((c/100))                                   #Calculate 1%
  
  while IFS='# ' read -r Line Comment
  do
    if [[ ! $Line =~ ^\ *# && -n $Line ]]; then
      Line="${Line%%\#*}"                        #Delete comments
      Line="${Line%%*( )}"                       #Delete trailing spaces
      Line="${Line:-1}"                          #Delete return
      AddSite "$Line" "$2" "$Comment"
    fi
    
    if [ $i -ge $c ]; then                       #Display progress
      echo -ne " $j%  \r"      
      ((j++))
      i=0      
    fi
    ((i++))
  done < "$1"
  echo
}
#Process UnixList 0--------------------------------------------------
#Unix hosts file starting 0.0.0.0 site.com
Process_UnixList0() {
#$1 = SourceFile
#$2 = DestFile
  i=0                                            #Progress counter
  j=1                                            #Percent point
  c=$(wc -l "$1" | cut -d " " -f 1)              #Count number of lines
  c=$((c/100))                                   #Calculate 1%
  
  while IFS='' read -r Line 
  do
    if [[ ${Line:0:3} == "0.0" ]]; then
      Line=${Line:8}
           
      if [[ ! $Line =~ ^(#|localhost|www\.|EOF|\[) ]]; then
        Line="${Line%%\#*}"                      #Delete comments
        #Line -1 doesn't work here, resort to tr instead
        Line=$(tr -d '\r' <<< "$Line")           #F*in slow
        AddSite "$Line" "$2" ""        
      fi
    fi
    
    if [ $i -ge $c ]; then                       #Display progress
      echo -ne " $j%  \r"      
      ((j++))
      i=0      
    fi
    ((i++))
  done < "$1"
  echo
 }
#Process UnixList 127------------------------------------------------
#Unix hosts file starting 127.0.0.1 site.com
Process_UnixList127() {
#$1 = SourceFile
#$2 = DestFile
  i=0                                            #Progress counter
  j=1                                            #Percent point
  c=$(wc -l "$1" | cut -d " " -f 1)              #Count number of lines
  c=$((c/100))                                   #Calculate 1%
  
  while IFS='' read -r Line
  do
    if [[ ${Line:0:3} == "127" ]]; then      
      Line=${Line:10}
      Line="${Line%%\#*}"                        #Delete comments
      if [[ ! $Line =~ ^(#|localhost|www|EOF|\[) ]]; then
        Line=${Line:-1}                          #Strip carrige return
        #echo "$Line $2"
        AddSite "$Line" "$2" ""
      fi
    fi
    
   if [ $i -ge $c ]; then                       #Display progress
      echo -ne " $j%  \r"      
      ((j++))
      i=0      
    fi
    ((i++))
  done < "$1"
  echo
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
    wget https://github.com/quidsup/notrack/archive/master.zip -O /tmp/notrack-master.zip
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
  
  SudoCheck=$(sudo cat /etc/sudoers | grep www-data)
  if [[ $SudoCheck == "" ]]; then
    echo "Adding NoPassword permissions for www-data to execute script /usr/local/sbin/ntrk-exec as root"
    echo -e "www-data\tALL=(ALL:ALL) NOPASSWD: /usr/local/sbin/ntrk-exec" | sudo tee -a /etc/sudoers
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
  if ! options=$(getopt -o bhvu -l help,version,upgrade -- "$@"); then
    # something went wrong, getopt will put out an error message for us
    exit 1
  fi

  set -- $options

  while [ $# -gt 0 ]
  do
    case $1 in
      -b) 
        Web_Upgrade
      ;;
      -h|--help) 
        Show_Help
      ;;
      -v|--version) 
        Show_Version
      ;;
      -u|--upgrade)
        Web_Upgrade
        Full_Upgrade
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
else                                             #No arguments means update trackers
#--------------------------------------------------------------------
  if [ "$(id -u)" != "0" ]; then                 #Check if running as root
    Error_Exit "Error this script must be run as root"
  fi
  
  if [ ! -d "/etc/notrack" ]; then               #Check /etc/notrack folder exists
    echo "Creating notrack folder under /etc"
    echo
    mkdir "/etc/notrack"
    if [ ! -d "/etc/notrack" ]; then             #Check again
      Error_Exit "Error Unable to create folder /etc/notrack"      
    fi
  fi
  
  Read_Config_File                               #Load saved variables
  Check_Lists                                    #Generate Black & White lists if necessary
  Get_IPAddress                                  #Read IP Address of NetDev
  
  Read_WhiteList                                 #Load Whitelist into array
  
  CreateFile "$TrackerQuickList"
  cat /dev/null > $TrackerQuickList              #Empty csv file
  
  DeleteOldFile "/etc/dnsmasq.d/adsites.list"    #Legacy NoTrack list
  
  #Check if we need to process each blocklist
  #If not then Delete old file to prevent Dnsmasq from reading it
  
  if [ $BlockList_NoTrack == 1 ]; then
    GetList_NoTrack                              #Process Quids Block list
  else
    DeleteOldFile "$TrackerListFile"
  fi
  
  GetList_BlackList                              #Process Users Blacklist
    
  #TLD Blocklist?
  if [ "$BlockList_TLD" == 1 ]; then GetList_TLD                                  
  else DeleteOldFile "$DomainListFile"           
  fi
  
  #AdBlock Manager
  if [ "$BlockList_AdBlockManager" == 1 ]; then GetList_AdBlockManager
  else DeleteOldFile "/etc/dnsmasq.d/adblockmanager.list"
  fi
  
  #EasyList
  if [ "$BlockList_EasyList" == 1 ]; then GetList_EasyList
  else DeleteOldFile "/etc/dnsmasq.d/easylist.list"
  fi
  
  #EasyPrivacy
  if [ "$BlockList_EasyPrivacy" == 1 ]; then GetList_EasyPrivacy
  else DeleteOldFile "/etc/dnsmasq.d/easyprivacy.list"
  fi
  
  #hpHosts
  if [ "$BlockList_hpHosts" == 1 ]; then GetList_hpHosts
  else DeleteOldFile "/etc/dnsmasq.d/hphosts.list"
  fi
  
  #Malware Domains
  if [ "$BlockList_MalwareDomains" == 1 ]; then GetList_MalwareDomains
  else DeleteOldFile "/etc/dnsmasq.d/malwaredomains.list"
  fi
  
  #PglYoyo
  if [ "$BlockList_PglYoyo" == 1 ]; then GetList_PglYoyo
  else DeleteOldFile "/etc/dnsmasq.d/pglyoyo.list"
  fi
  
  #SomeoneWhoCares
  if [ "$BlockList_SomeoneWhoCares" == 1 ]; then GetList_SomeoneWhoCares
  else DeleteOldFile "/etc/dnsmasq.d/someonewhocares.list"
  fi
  
  if [ "$BlockList_Winhelp2002" == 1 ]; then GetList_Winhelp2002
  else DeleteOldFile "/etc/dnsmasq.d/winhelp2002.list"
  fi
  
  echo "Imported $(cat /etc/notrack/tracker-quick.list | grep -c Active) Domains into Block List"
  
  echo "Restarting Dnsnmasq"
  service dnsmasq restart                        #Restart dnsmasq
fi 

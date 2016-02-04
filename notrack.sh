#!/bin/bash
#Title : NoTrack
#Description : This script will download latest Adblock Domain block files from quidsup.net, then parse them into Dnsmasq.
#Script will also create quick.lists for use by stats.php web page
#Author : QuidsUp
#Date : 2015-01-14
#Usage : sudo bash notrack.sh

#User Configerable Variables in case config file is missing
NetDev=$(ip -o link show | awk '{print $2,$9}' | grep ": UP" | cut -d ":" -f 1) #Set this to the name of network device e.g. "eth0" if you have multiple network cards
IPVersion="IPv4"
BlockList_TLD="1"

#System Variables----------------------------------------------------
Version="0.6"
TrackerSource="http://quidsup.net/trackers.txt" 
TrackerListFile="/etc/dnsmasq.d/trackers.list" 
TrackerQuickList="/etc/notrack/tracker-quick.list"
TrackerBlackList="/etc/notrack/blacklist.txt"
TrackerWhiteList="/etc/notrack/whitelist.txt"
DomainSource="http://quidsup.net/malicious-domains.txt"
DomainListFile="/etc/dnsmasq.d/malicious-domains.list"
DomainBlackList="/etc/notrack/domain-blacklist.txt"
DomainWhiteList="/etc/notrack/domain-whitelist.txt"
DomainQuickList="/etc/notrack/domain-quick.list"
ConfigFile="/etc/notrack/notrack.conf"

OldLatestVersion="$Version"

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
  fi
}

#Read Config File----------------------------------------------------
Read_Config_File() {  
  if [ -e "$ConfigFile" ]; then
    echo "Reading Config File"
    while IFS='= ' read Key Value
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
          BlockList_TLD) BlockList_TLD="$Value";;
        esac            
      fi
    done < $ConfigFile
  fi 
}

#Check Lists---------------------------------------------------------
Check_Lists() {  
  #Check if Blacklist exists-----------------------------------------
  if [ ! -e $TrackerBlackList ]; then
    echo "Creating blacklist"
    touch $TrackerBlackList
    echo "#Use this file to add additional websites to be blocked" >> $TrackerBlackList
    echo "#Run notrack script (sudo notrack) after you make any changes to this file" >> $TrackerBlackList
    echo "#doubleclick.net" >> $TrackerBlackList
    echo "#google-analytics.com" >> $TrackerBlackList
    echo "#googletagmanager.com" >> $TrackerBlackList
    echo "#googletagservices.com" >> $TrackerBlackList
  fi

  #Check if Whitelist exists-----------------------------------------
  if [ ! -e $TrackerWhiteList ]; then
    echo "Creating whitelist"
    touch $TrackerWhiteList
    echo "# Use this file to remove files from blocklist" >> $TrackerWhiteList
    echo "# Run notrack script (sudo notrack) after you make any changes to this file" >> $TrackerWhiteList
    echo "#doubleclick.net" >> $TrackerWhiteList
    echo "#google-analytics.com" >> $TrackerWhiteList
  fi


  #Check if DomainBlacklist exists-----------------------------------
  if [ ! -e $DomainBlackList ]; then
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

  #Check if Domain Whitelist exists
  if [ ! -e $DomainWhiteList ]; then
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
    echo "Reading IPv4 Address from $NetDev."
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
  CreateFile "$TrackerQuickList"
  
  echo "Downloading Tracker Site List from: $TrackerSource"
  echo
  wget -O /etc/notrack/trackers.txt $TrackerSource
  echo
  Check_File_Exists "/etc/notrack/trackers.txt"
  
  #Merge Downloaded List with users Blacklist
  cat /etc/notrack/trackers.txt $TrackerBlackList > /tmp/combined.txt

  #Merge Whitelist with above two lists to remove duplicates
  echo "Processing Tracker List"
  echo "#Tracker Blocklist last updated $(date)" > $TrackerListFile
  echo "#Don't make any changes to this file, use $TrackerBlackList and $TrackerWhiteList instead" >> $TrackerListFile
  cat /dev/null > $TrackerQuickList              #Empty old List
  
  i=0                                            #Progress dot counter
  awk 'NR==FNR{A[$1]; next}!($1 in A)' $TrackerWhiteList /tmp/combined.txt | while read -r Line; do
    if [ $i == 100 ]; then                       #Display some progress ..
      echo -n .
      i=0
    fi
    if [[ ! $Line =~ ^\ *# && -n $Line ]]; then
      Line="${Line%%\#*}"                        #Delete comments
      Line="${Line%%*( )}"                       #Delete trailing spaces
      echo "address=/$Line/$IPAddr" >> $TrackerListFile
      echo "$Line" >> $TrackerQuickList    
    elif [[ "${Line:0:14}" == "#LatestVersion" ]]; then
      LatestVersion="${Line:15}"                 #Substr version number only
      if [[ $OldLatestVersion != "$LatestVersion" ]]; then
        echo "New version of NoTrack available v$LatestVersion"
        sed -i "s/^\(LatestVersion *= *\).*/\1$LatestVersion/" $ConfigFile      
      fi
    fi
    ((i++))
  done

  echo .                                         #Final dot and carriage return
  echo "Imported $(wc -l $TrackerQuickList | cut -d' ' -f1) Advert Domains into block list"
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
  Check_Lists
  Get_IPAddress
  
  DeleteOldFile "/etc/dnsmasq.d/adsites.list"    #Legacy NoTrack list
  GetList_NoTrack
  
  if [[ $BlockList_TLD == "1" ]]; then           #Process TLD Blocklist?
    GetList_TLD
  else 
    DeleteOldFile "$DomainListFile"
  fi
  
  echo "Removing temporary files"
  rm /tmp/combined.txt                           #Clear up

  echo "Restarting Dnsnmasq"
  service dnsmasq restart                        #Restart dnsmasq
fi 

#!/usr/bin/env python
#Title : NoTrack Back-end script
#Description : This script will download latest block list files, then parse them into Dnsmasq.
#Script will also create quick.lists for use by stats.php web page
#Author : QuidsUp
#Date : 2015-09-02
#Usage : sudo pyton notrack.py

#Re-write of notrack.sh into python
from __future__ import print_function
import os
import re
import string
import subprocess
import sys
import time



#User configerable Settings (in case config file is missing)---------
NetDev = subprocess.check_output("ip -o link show | awk '{print $2,$9}' | grep UP | cut -d \: -f 1", shell=True).splitlines()[0]
#If NetDev fails to recognise a Local Area Network IP Address, then you can use IPVersion to assign a custom IP Address in /etc/notrack/notrack.conf
#e.g. IPVersion = 192.168.1.2

IPVersion = "IPv4"

config = {                                       #config array for Block Lists
  "bl_custom": "",
  "bl_notrack": True,
  "bl_tld": True,
  "bl_qmalware": True,
  "bl_hexxium": True,
  "bl_disconnectmalvertising": False,
  "bl_easylist": False,
  "bl_easyprivacy": False,
  "bl_fbannoyance": False,
  "bl_fbenhanced": False,
  "bl_fbsocial": False,
  "bl_hphosts": False,
  "bl_malwaredomainlist": False,
  "bl_malwaredomains": False,
  "bl_pglyoyo": False,
  "bl_someonewhocares": False,
  "bl_spam4False4": False,
  "bl_swissransom": False,
  "bl_swisszeus": False,
  "bl_winhelp2002": False,
  "bl_areasy": False,                            #Arab
  "bl_chneasy": False,                           #China
  "bl_deueasy": False,                           #Germany
  "bl_dnkeasy": False,                           #Denmark
  "bl_ruseasy": False,                           #Russia
  "bl_fblatin": False,                           #Portugal/Spain (Latin Countries)
}

#Constants-----------------------------------------------------------
VERSION = "0.7.16"
CHECKTIME = 343800                               #Time in Seconds between downloading lists (4 days - 30mins)
CSV_BLOCKING = "/etc/notrack/blocking.csv"
CSV_TLD = "/var/www/html/admin/include/tld.csv"
FILE_BLACKLIST = "/etc/notrack/blacklist.txt"
FILE_WHITELIST = "/etc/notrack/whitelist.txt"
FILE_TLDBLACK = "/etc/notrack/domain-blacklist.txt"
FILE_TLDWHITE = "/etc/notrack/domain-whitelist.txt"
FILE_TLDQUICK = "/etc/notrack/domain-quick.list"
LIST_NOTRACK = "/etc/dnsmasq.d/notrack.list"
DIR_NOTRACK = '/etc/notrack/'

configFile = "/etc/notrack/notrack.conf"


#Block list URL's----------------------------------------------------
URLList = {                               #Array of URL's
"notrack": "https://raw.githubusercontent.com/quidsup/notrack/master/trackers.txt",
"qmalware": "https://raw.githubusercontent.com/quidsup/notrack/master/malicious-sites.txt",
"hexxium": "https://hexxiumcreations.github.io/threat-list/hexxiumthreatlist.txt",
"disconnectmalvertising": "https://s3.amazonaws.com/lists.disconnect.me/simple_malvertising.txt",
"easylist": "https://easylist-downloads.adblockplus.org/easylist_noelemhide.txt",
"easyprivacy": "https://easylist-downloads.adblockplus.org/easyprivacy.txt",
"fbannoyance": "https://easylist-downloads.adblockplus.org/fanboy-annoyance.txt",
"fbenhanced": "https://www.fanboy.co.nz/enhancedstats.txt",
"fbsocial": "https://secure.fanboy.co.nz/fanboy-social.txt",
"hphosts": "http://hosts-file.net/ad_servers.txt",
"malwaredomainlist": "http://www.malwaredomainlist.com/hostslist/hosts.txt",
"malwaredomains": "http://mirror1.malwaredomains.com/files/justdomains",
"spam404": "https://raw.githubusercontent.com/Dawsey21/Lists/master/adblock-list.txt",
"swissransom": "https://ransomwaretracker.abuse.ch/downloads/RW_DOMBL.txt",
"swisszeus": "https://zeustracker.abuse.ch/blocklist.php?download=domainblocklist",
"pglyoyo": "http://pgl.yoyo.org/adservers/serverlist.php?hostformat=;mimetype=plaintext",
"someonewhocares": "http://someonewhocares.org/hosts/hosts",
"winhelp2002": "http://winhelp2002.mvps.org/hosts.txt",
"areasy": "https://easylist-downloads.adblockplus.org/Liste_AR.txt",
"chneasy": "https://easylist-downloads.adblockplus.org/easylistchina.txt",
"deueasy": "https://easylist-downloads.adblockplus.org/easylistgermany.txt",
"dnkeasy": "https://adblock.dk/block.csv",
"ruseasy": "https://easylist-downloads.adblockplus.org/ruadlist+easylist.txt",
"fblatin": "https://www.fanboy.co.nz/fanboy-espanol.txt",
}
#Global Variables----------------------------------------------------

force = False                                    #Force update block list
#OldLatestVersion = Version
#UnixTime = time.time()                           #Unix time now
#JumpPoint=0                                      #Percentage increment
#PercentPoint=0                                   #Number of lines to loop through before a percentage increment is hit
CSVList = []
WhiteList = {}                                   #associative array for referencing sites in White List
DomainList = {}                                  #Array to check if TLD blocked
SiteList = {}                                    #Array to store sites being blocked
dedup = 0                                        #Count of deduplication
CSVFiles = []

#Error Exit 2nd generation--------------------------------------------
def error_exit(Msg, ExitCode): 
  print("Error. %s" % Msg)
  print("Aborting")
  sys.exit(ExitCode)



"""
#Create File---------------------------------------------------------
function CreateFile() {
  #$1 = File to create
  if [ ! -e "$1" ]; then                         #Does file already exist?
    echo "Creating file: $1"
    touch "$1"                                   #If not then create it
  fi
}
"""
#Delete old file if it Exists----------------------------------------
def DeleteOldFile(File):
  if os.path.isfile(File):
    print("Deleting file %s" % File)
    os.remove(File)
    return True
  else:
    return False

#--------------------------------------------------------------------
# Add Site to List
# Checks whether a Site is in the Users whitelist or has previously been added
#
# Arguments:
#   $1 Site to Add
#   $2 Comment
# Returns:
#   None
#--------------------------------------------------------------------
def addsite(site, comment):
  global SiteList, DomainList, WhiteList, dedup
  """    
  if [[ $Site =~ ^www\. ]]; then                 #Drop www.
    Site="${Site:4}"
  fi
   """  
  #Ignore Sub domain
  #Group 1 Domain: A-Z,a-z,0-9,-  one or more
  # .
  #Group 2 (Double-barrelled TLD's) : org. | co. | com.  optional
  #Group 3 TLD: A-Z,a-z,0-9,-  one or more
  
  """
  DomainList[Match.group(1)] = True
          SiteList[Match.group(1)] = True
          CSVList.append(Match.group(1)+',Active,'+Match.group(2))
        elif Match.group(1) in DomainBlackList:
  """
  Match = re.match('([A-Za-z0-9\-_]+)\.(org\.|co\.|com\.)?([A-Za-z0-9\-_]+)$', site)
  if Match:
    if Match.group(3) in DomainList:             #Drop if .domain is in TLD
      print('Dedup TLD %s' % site)
      dedup += 1
      return
    
    if Match.group(1)+Match.group(2)+Match.group(3) in SiteList: #Drop if site.domain has been added
      print('Dedup Domain %s' % site)            #Uncomment for debugging
      dedup += 1
      return
    
    if site in SiteList:                         #Drop if sub.site.domain has been added
      print('Dedup Duplicate Sub %s' % site)     #Uncomment for debugging
      dedup += 1
      return
      
    if site in WhiteList or Match.group(1)+Match.group(2)+Match.group(3) in WhiteList: #Is sub.site.domain or site.domain in whitelist?    
      CSVList.append(site+',Disabled,'+comment)  #Add to CSV as Disabled      
    else:                                        #No match in whitelist
      CSVList.append(site+',Active,'+comment)    #Add to CSV as Active
      SiteList[site] = True                      #Add site into SiteList array
    
  else:
    print('Invalid site %s' % site)
"""
#Calculate Percent Point in list files-------------------------------
function CalculatePercentPoint() {
  #$1 = File to Calculate
  #1. Count number of lines in file with "wc"
  #2. Calculate Percentage Point (number of for loop passes for 1%)
  #3. Calculate Jump Point (increment of 1 percent point on for loop)
  #E.g.1 20 lines = 1 for loop pass to increment percentage by 5%
  #E.g.2 200 lines = 2 for loop passes to increment percentage by 1%
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
"""
#--------------------------------------------------------------------
def cmd_exists(cmd):
  """Checks if a Command exists
  
  Args:
    Command to check
  
  Returns:
    True if Command exists
    Fail if Command  doesn't exist
  """
  if subprocess.call("command -v %s >/dev/null 2>&1" % cmd , shell=True) == 127:
    return False
  else:
    return True
  

#Count Lines---------------------------------------------------------
def count_lines():
  count = 0
  for f in os.listdir('/etc/dnsmasq.d'):
    if f.endswith('.list'):    
      count += int(subprocess.check_output('wc -l /etc/dnsmasq.d/%s | cut -d\  -f 1' % f, shell=True).splitlines()[0])
  
  print(count)
#--------------------------------------------------------------------
def filetime(filename):
  if os.path.isfile(filename):
    return os.path.getmtime(filename)
  return 0
#--------------------------------------------------------------------
def filter_bool(value, default):
  """Checks if a variable is a bool
  
  Args:
    value to check
    default value to use
  
  Returns:
    True if Value is 1
    False if Value is 0
    Default for Other
  """
  if value == 0: return False
  elif value == 1: return True
  return default

#--------------------------------------------------------------------
#Default values are set at top of this script
#config File contains Key & Value on each line for some/none/or all items
#If the Key is found in the case, then we write the value to the Variable
def load_config():
  global config
  if not os.path.isfile(configFile):
    print("config file %s missing, using default values" % configFile)
    return False
    
  print("Reading config File %s" % configFile)
    
  with open(configFile, 'r') as fp:
    for line in fp:
      line = line.split('=')
      key = line[0].strip()
      value = line[1].strip()
      
      if key == 'IPVersion': IPVersion = value
      elif key == 'NetDev': NetDev = value
      elif key == 'LatestVersion': OldLatestVersion = value
      elif key == 'bl_custom': config['bl_custom'] = value
      elif key == 'bl_notrack': config['bl_notrack'] = filter_bool(value, True)
      elif key == 'bl_tld': config['bl_tld'] = filter_bool(value, True)
      elif key == 'bl_qmalware': config['bl_qmalware'] = filter_bool(value, True)
      elif key == 'bl_hexxium': config['bl_hexxium'] = filter_bool(value, True)
      elif key == 'bl_disconnectmalvertising': config['bl_disconnectmalvertising'] = filter_bool(value, False)
      elif key == 'bl_easylist': config['bl_easylist'] = filter_bool(value, False)
      elif key == 'bl_easyprivacy': config['bl_easyprivacy'] = filter_bool(value, False)
      elif key == 'bl_fbannoyance': config['bl_fbannoyance'] = filter_bool(value, False)
      elif key == 'bl_fbenhanced': config['bl_fbenhanced'] = filter_bool(value, False)
      elif key == 'bl_fbsocial': config['bl_fbsocial'] = filter_bool(value, False)
      elif key == 'bl_hphosts': config['bl_hphosts'] = filter_bool(value, False)
      elif key == 'bl_malwaredomainlist': config['bl_malwaredomainlist'] = filter_bool(value, False)
      elif key == 'bl_malwaredomains': config['bl_malwaredomains'] = filter_bool(value, False)
      elif key == 'bl_pglyoyo': config['bl_pglyoyo'] = filter_bool(value, False)
      elif key == 'bl_someonewhocares': config['bl_someonewhocares'] = filter_bool(value, False)
      elif key == 'bl_spam404': config['bl_spam404'] = filter_bool(value, False)
      elif key == 'bl_swissransom': config['bl_swissransom'] = filter_bool(value, False)
      elif key == 'bl_swisszeus': config['bl_swisszeus'] = filter_bool(value, False)
      elif key == 'bl_winhelp2002': config['bl_winhelp2002'] = filter_bool(value, False)
      elif key == 'bl_areasy': config['bl_areasy'] = filter_bool(value, False)
      elif key == 'bl_chneasy': config['bl_chneasy'] = filter_bool(value, False)
      elif key == 'bl_deueasy': config['bl_deueasy'] = filter_bool(value, False)
      elif key == 'bl_dnkeasy': config['bl_dnkeasy'] = filter_bool(value, False)
      elif key == 'bl_ruseasy': config['bl_ruseasy'] = filter_bool(value, False)
      elif key == 'bl_fblatin': config['bl_fblatin'] = filter_bool(value, False)
    fp.close()

#--------------------------------------------------------------------
def load_sitelist(filename):
  sitelist = {}
  
  with open(filename, 'r') as fp:
    for line in fp:
      Match = re.match("^([A-Za-z0-9\-_\.]+)\s?#?", line)      
      if Match:
        sitelist[Match.group(1)] = True
    fp.close()
    
    return sitelist

#--------------------------------------------------------------------
def load_tldlist():
  #1. Load Domain whitelist into associative array
  #2. Read downloaded TLD list, and compare with Domain WhiteList
  #3. Read users custom TLD list, and compare with Domain WhiteList
  #4. Results are stored in CSVList, and SiteList These arrays are sent back to GetList() for writing to file.
  #The Downloaded & Custom lists are handled seperately to reduce number of disk writes in say cat'ting the files together
  #DomainQuickList is used to speed up processing in stats.php
  
  global config, CSV_TLD, DIR_NOTRACK, FILE_TLDBLACK, FILE_TLDWHITE, FILE_TLDQUICK, SiteList
  
  del CSVList[:]
  DomainBlackList = {}
  DomainWhiteList = {}
  
  list_time = filetime(DIR_NOTRACK+'tld.csv')
  
  if not config['bl_tld']:                       #Should we process this list according to the config settings?
    DeleteOldFile(FILE_TLDQUICK)
    DeleteOldFile(DIR_NOTRACK+'tld.csv')
    return
        
  print("Processing Top Level Domain List")
    
  DomainBlackList = load_sitelist(FILE_TLDBLACK) #Load TLD Black list into Array
  DomainWhiteList = load_sitelist(FILE_TLDWHITE) #Load TLD White list into Array
  
  with open(CSV_TLD, 'r') as fp:                 #Open CSV from web folder
    for line in fp:
      #Group 1: TLD
      #Group 2: Country
      #Group 3: Risk
      Match = re.match("^([A-Za-z0-9\-_\.]+),(\w+),(\d)", line)
      if Match:
        if Match.group(3) == 1 and not Match.group(1) in DomainWhiteList:
          DomainList[Match.group(1)] = True
          SiteList[Match.group(1)] = True
          CSVList.append(Match.group(1)+',Active,'+Match.group(2))
        elif Match.group(1) in DomainBlackList:
          DomainList[Match.group(1)] = True
          SiteList[Match.group(1)] = True
          CSVList.append(Match.group(1)+',Active,'+Match.group(2))
    fp.close()
  
  if filetime(FILE_TLDBLACK) < list_time and filetime(FILE_TLDWHITE) < list_time and  filetime(CSV_TLD) < list_time and not force:
    print("Top Level Domain List is in date, not saving\n")
    CSVFiles.append('tld.csv')
    return 0
    
  with open(DIR_NOTRACK+'tld.csv', 'w') as fp:
    fp.write('\n'.join(CSVList)+'\n')
    fp.close
  
  with open(FILE_TLDQUICK, 'w') as fp:
    fp.write('\n'.join(DomainList))
    fp.close()
  
  CSVFiles.append('tld.csv')
  
  print("Finished processing Top Level Domain List\n")

#--------------------------------------------------------------------
def load_whitelist():
  global WhiteList, FILE_WHITELIST
  
  WhiteList = load_sitelist(FILE_WHITELIST)  

#--------------------------------------------------------------------
"""
#Generate BlackList--------------------------------------------------
function Generate_BlackList() {
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
function Generate_WhiteList() {
  local -a Tmp                                   #Local array to build contents of file
  
  echo "Creating whitelist"
  touch "$WhiteListFile"
  Tmp+=("#Use this file to remove sites from block list")
  Tmp+=("#Run notrack script (sudo notrack) after you make any changes to this file")
  Tmp+=("#doubleclick.net")
  Tmp+=("#google-analytics.com")
  printf "%s\n" "${Tmp[@]}" > $WhiteListFile     #Write Array to file with line seperator
}
"""
#--------------------------------------------------------------------
#Get IP Address of System
def get_ipaddress():
  global IPVersion, IPAddr, NetDev
  
  #A manual IP address can be assigned using IPVersion
  if IPVersion == 'IPv4':
    print("Internet Protocol Version 4 (IPv4)")
    print("Reading IPv4 Address from %s" % NetDev)
    IPAddr = subprocess.check_output('ip addr list '+NetDev+' | grep inet | head -n 1 | cut -d\  -f6 | cut -d/ -f1', shell=True).splitlines()[0]
  elif IPVersion == 'IPv6':
    print("Internet Protocol Version 6 (IPv6)")
    print("Reading IPv6 Address")
    IPAddr = subprocess.check_output('ip addr list '+NetDev+' | grep inet6 | head -n 1 | cut -d\  -f6 | cut -d/ -f1', shell=True).splitlines()[0]
  else:
    print("Custom IP Address used")
    IPAddr = IPVersion                           #Use IPVersion to assign a manual IP Address  
  
  print("System IP Address: %s\n" % IPAddr)





#Custom BlackList----------------------------------------------------
def getlist_blacklist():
  print("Processing Custom Black List")
  del CSVList[:]
  process_plainlist(DIR_NOTRACK+'blacklist.txt')
    
  if len(CSVList) > 0:                           #Are there any URL's in the block list?
    with open(DIR_NOTRACK+'custom.csv', 'w') as fp:
      fp.write('\n'.join(CSVList)+'\n')
      fp.close
    CSVFiles.append('custom.csv')
  else:
    DeleteOldFile('/etc/notrack/custom.csv')
  
  print("Finished processing Custom Black List\n")

"""

#Get Custom List-----------------------------------------------------
function Get_Custom() {
  local -A CustomListArray
  local CSVFile=""
  local DLFile=""
  local DLFileTime=0                             #Downloaded File Time
  local CustomCount=1                            #For displaying count of custom list
    

  if [[ ${config[bl_custom]} == "" ]]; then      #Are there any custom block lists?
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
  IFS=',' read -ra CustomList <<< "${config[bl_custom]}"
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
      if [ $DLFileTime -lt $((UnixTime-CheckTime)) ]; then #Is list older than 4 days
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
        cat "$CSVFile" >> "$BlockingCSV"
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
  
  #Should we process this list according to the config settings?
  if [ "${config[bl_$Lst]}" == 0 ]; then 
    DeleteOldFile "$CSVFile"     #If not delete the old file, then leave the function
    DeleteOldFile "$DLFile"
    return 0
  fi
  
  Get_FileTime "$DLFile"
   
  if [ $FileTime -gt $((UnixTime-CheckTime)) ]; then  
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
    *) error_exit "Unknown option $2" "7"
  esac  
  
  if [ ${#CSVList[@]} -gt 0 ]; then              #Are there any URL's in the block list?
    CreateFile "$CSVFile"                        #Create CSV File    
    printf "%s\n" "${CSVList[@]}" > "$CSVFile"   #Output arrays to file    
    cat "/etc/notrack/$Lst.csv" >> "$BlockingCSV"  
    echo "Finished processing $Lst"    
  else                                           #No URL's in block list
    echo "No URL's extracted from Block list"
    DeleteOldFile "$CSVFile"                     #Delete CSV File    
  fi
  
  echo
}
#--------------------------------------------------------------------
function Process_CustomList() {
  #$1 = SourceFile
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
#Process EasyList----------------------------------------------------
function Process_EasyList() {
  #EasyLists contain a mixture of Element hiding rules and third party sites to block.
  #DNS is only capable of blocking sites, therefore NoTrack can only use the lines with $third party or popup in
  
  #$1 = SourceFile
  
  CalculatePercentPoint "$1"
  i=1                                            #Progress counter
  j=$JumpPoint                                   #Jump in percent  
    
  while IFS=$'\n' read -r Line
  do    
    
    #||
    #Group 1: IPv4 address   optional
    #Group 2: Site A-Z, a-z, 0-9, -, .  one or more
    #Group 3: ^ | / | $  once
    #Group 4: $third-party | $popup | $popup,third-party
    
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
#Process NoTrack List------------------------------------------------
function Process_NoTrackList() {
  #NoTrack list is just like PlainList, but contains latest version number
  #which is used by the Admin page to inform the user an upgrade is available
  
  #$1 = SourceFile
  local LatestVersion=""
  
  CalculatePercentPoint "$1"
  i=1                                            #Progress counter
  j=$JumpPoint                                   #Jump in percent
  
  while IFS=$'\n' read -r Line
  do
    #Group 1: Subdomain or Somain
    # .
    #Group 2: Domain or TLD
    # space Optional
    # # Optional
    #Group 3: Comment  any character zero or more times
  
    if [[ $Line =~ ^([A-Za-z0-9\-]+)\.([A-Za-z0-9\.\-]+)[[:space:]]?#?(.*)$ ]]; then
      AddSite "${BASH_REMATCH[1]}.${BASH_REMATCH[2]}" "${BASH_REMATCH[3]}"
    elif [[ $Line =~ ^#LatestVersion[[:space:]]([0-9\.]+)$ ]]; then #Is it version number
      LatestVersion="${BASH_REMATCH[1]}"         #Extract Version number      
      if [[ $OldLatestVersion != "$LatestVersion" ]]; then 
        echo "New version of NoTrack available v$LatestVersion"
        #Check if config line LatestVersion exists
        #If not add it in with tee
        #If it does then use sed to update it
        if [[ $(grep "LatestVersion" "$configFile") == "" ]]; then
          echo "LatestVersion = $LatestVersion" | sudo tee -a "$configFile"
        else
          sed -i "s/^\(LatestVersion *= *\).*/\1$LatestVersion/" $configFile
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
"""
#--------------------------------------------------------------------
#Process PlainList---------------------------------------------------
def process_plainlist(filename):
  #$1 = SourceFile
  #CalculatePercentPoint "$1"
  #i=1                                            #Progress counter
  #j=$JumpPoint                                   #Jump in percent
  
  
  with open(filename, 'r') as fp:
    for line in fp:
      Match = re.match("^([A-Za-z0-9\-_]+)\.([A-Za-z0-9\-_\.]+)\s#?(.*)\n$", line)
      if Match:
        addsite(Match.group(1)+'.'+Match.group(2), Match.group(3))
    fp.close()
    
    """
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
#Process TLD List----------------------------------------------------
#Process UnixList----------------------------------------------------
function Process_UnixList() {
  #All Unix lists that I've come across are Windows formatted, therefore we use the carriage return IFS \r
    
  #$1 = SourceFile
  CalculatePercentPoint "$1"
  i=1                                            #Progress counter
  j=$JumpPoint                                   #Jump in percent
  
  while IFS=$'\n\r' read -r Line                 #Include carriage return for Windows
  do 
    #Group 1: 127.0.0.1 | 0.0.0.0
    #Space  one or more (include tab)
    #Group 2: Subdomain or Domain
    # .
    #Group 3: Domain or TLD
    #Group 4: space  one or more  optional
    # # Optional
    #Group 6: Comment  any character zero or more times
    
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
#  WhiteList
#  DomainList
#
# Arguments:
#  None
#
# Returns:
#  0: Success
#  55: Failed
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

#Sort List-----------------------------------------------------------
function SortList() {
  #1. Sort SiteList array into new array SortedList
  #2. Go through SortedList and check subdomains again
  #3. Copy SortedList to DNSList, removing any blocked subdomains
  #4. Write list to dnsmasq folder

  local -a SortedList                            #Sorted array of SiteList
  local -a DNSList                               #Dnsmasq list  
  dedup=0                                        #Reset deduplication
  
  echo "Sorting List"
  IFS=$'\n' SortedList=($(sort <<< "${!SiteList[*]}"))
  unset IFS
    
  echo "Final deduplication"
  DNSList+=("#Tracker Block list last updated $(date)")
  DNSList+=("#Don't make any changes to this file, use $BlackListFile and $WhiteListFile instead")
  
  for Site in "${SortedList[@]}"; do
    # ^ Subdomain
    #Group 1: Domain
    #Group 2: org. | co. | com.  optional
    #Group 3: TLD
    
    #Is there a subdomain?
    if [[ $Site =~ ^[A-Za-z0-9\-]+\.([A-Za-z0-9\-]+)\.(org\.|co\.|com\.)?([A-Za-z0-9\-]+)$ ]]; then
      #Is site.domain already in list?
      if [ ${SiteList[${BASH_REMATCH[1]}.${BASH_REMATCH[2]}${BASH_REMATCH[3]}]} ]; then        
        ((dedup++))                              #Yes, add to total of dedup
      else
        DNSList+=("address=/$Site/$IPAddr")      #No, add to Array
      fi
    else                                         #No subdomain, add to Array
      DNSList+=("address=/$Site/$IPAddr")
    fi
  done
  #printf "%s\n" "${SortedList[@]}"
  echo "Further deduplicated $dedup Domains"
  echo "Number of Domains in Block List: ${#DNSList[@]}"
  echo "Writing block list to $BlockingListFile"
  printf "%s\n" "${DNSList[@]}" > "$BlockingListFile"
  
  echo
}
"""
#Help----------------------------------------------------------------
def Show_Help():
  print("Usage: notrack")
  print("Downloads and processes tracker lists into Dnsmasq")
  print("")
  print("The following options can be specified:")
  print("  -f, --force\tForce update of Block list")
  print("  -h, --help\tDisplay this help and exit")
  print("  -t, --test\tconfig Test")
  print("  -v, --version\tDisplay version information and exit")
  print("  -u, --upgrade\tRun a full upgrade")
  print("  --count\tCount number of sites in active Block lists")
  print("")
  
#Show Version--------------------------------------------------------
def Show_Version():
  print("NoTrack Version %s" % Version)
  print("")
#--------------------------------------------------------------------
# Get Dnsmasq Version
#
# Globals:
#   None
# Arguments:
#   None
# Returns:
#   Version number on success or Zero on fail
#--------------------------------------------------------------------
      
def get_dnsmasq_version():
  """Get Dnsmasq Version
  Checks if Dnsmasq exists
  Extract version number from "dnsmasq -v"
  
  Arguments:
    None
  
  Returns:
    Version number on success
    0 on Fail
  """
  if cmd_exists("dnsmasq"):    
    DnsmasqStr = subprocess.check_output("dnsmasq --version", shell=True).splitlines()[0]
    Match = re.match("^Dnsmasq\sversion\s(\d\.\d{1,2})", DnsmasqStr)
    if Match:
      return float(Match.group(1))
  
  return 0
    
#Test----------------------------------------------------------------
def Test():
  dnsmasq_version = 0
    
  print("NoTrack config Test\n")
  
  print("NoTrack Version: %s" % VERSION)
  
  dnsmasq_version = get_dnsmasq_version()
  
  if dnsmasq_version == 0:
    print("Unable to find Dnsmasq")
  elif dnsmasq_version >= 2.75:
    print("Dnsmasq Version: %.2f" % dnsmasq_version)
    print("Dnsmasq Supports White listing")
  else:
    print("Dnsmasq Version: %.2f" % dnsmasq_version)
    print("Dnsmasq Doesn't support White listing (v2.75 or above is required)")
    if cmd_exists("dig"):
      print("Fallback option using Dig is available")
    else:
      print("Dig isn't installed. Unable to White list from blocked TLD's")

  print("")
  
  if os.path.isfile(configFile):                 #Does config exist?
    load_config()                                #Yes, Load config file
  else:
    print("No config file available")            #No, inform user
    
  print("Block Lists Utilised:")                 #Show block lists in use
  for key, value in config.iteritems():          #Read items in config
    if key != "bl_custom" and value:             #Ignore bl_custom and False values
      print(key)
  
  if config["bl_custom"] != "":                  #Anything in custom block list?
    print("bl_custom: %s" % config["bl_custom"])
  else:
    print("bl_custom: None")
  """
  
  
  
    echo "No config file available"
  fi
  
  Get_IPAddress                                  #Read IP Address of NetDev
  
  }
#Check Update Required----------------------------------------------
function UpdateRequired() {
  #Triggers for Update being required:
  #1. -f or --forced
  #2 Block list older than 4 days
  #3 White list recently modified
  #4 Black list recently modified
  #5 config recently modified
  #6 Domain White list recently modified
  #7 Domain Black list recently modified
  #8 Domain CSV recently modified

  Get_FileTime "$BlockingListFile"
  local ListFileTime="$FileTime"
  
  if [ $Force == 1 ]; then
    echo "Forced Update"
    return 0
  fi
  if [ $ListFileTime -lt $((UnixTime-CheckTime)) ]; then
    echo "Block List out of date"
    return 0
  fi
  Get_FileTime "$WhiteListFile"
  if [ $FileTime -gt $ListFileTime ]; then
    echo "White List recently modified"
    return 0
  fi
  Get_FileTime "$BlackListFile"
  if [ $FileTime -gt $ListFileTime ]; then
    echo "Black List recently modified"
    return 0
  fi
  Get_FileTime "$configFile"
  if [ $FileTime -gt $ListFileTime ]; then
    echo "config recently modified"
    return 0
  fi
  Get_FileTime "$DomainWhiteListFile"
  if [ $FileTime -gt $ListFileTime ]; then
    echo "Domain White List recently modified"
    return 0
  fi
  Get_FileTime "$DomainBlackListFile"
  if [ $FileTime -gt $ListFileTime ]; then
    echo "Domain White List recently modified"
    return 0
  fi
  Get_FileTime "$DomainCSV"
  if [ $FileTime -gt $ListFileTime ]; then
    echo "Domain Master List recently modified"
    return 0
  fi
  
  echo "No update required"
  exit 0
}
#Upgrade-------------------------------------------------------------
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
      error_exit "Unable to find NoTrack folder" "22"
    fi
  else    
    if [ -e "$InstallLoc/ntrk-upgrade.sh" ]; then
      echo "Found alternate copy in $InstallLoc"
      sudo bash "$InstallLoc/ntrk-upgrade.sh"    
    else
      error_exit "Unable to find ntrk-upgrade.sh" "20"
    fi
  fi
}
"""
#Main----------------------------------------------------------------




if len(sys.argv) > 1:
  for arg in sys.argv[1:]:
    if arg == "-c" or arg == "--count":
      count_lines()
      sys.exit(0)
    elif arg == "-f" or arg == "--force":
      force = True
    elif arg == "-t" or arg == "--test":
      Test()
      sys.exit(0)
    elif arg == "-h" or arg == "--help":
      Show_Help()
      sys.exit(0)
    elif arg == "-v" or arg == "--version":
      Show_Version()
      sys.exit(0)
    else:
      error_exit("Unknown command", 6)
"""
            
      -u|--upgrade)
        Upgrade
        exit 0
      ;;
   
  done
fi
"""  
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
#10. Process Other block lists according to config
#11. Process Custom block lists
#12. Sort list and do final deduplication

if os.geteuid() != 0:
 error_exit("This script must be run as root", 5)

"""  
if [ ! -d "/etc/notrack" ]; then                 #Check /etc/notrack folder exists
  echo "Creating notrack folder under /etc"
  echo
  mkdir "/etc/notrack"
  if [ ! -d "/etc/notrack" ]; then               #Check again
    error_exit "Unable to create folder /etc/notrack" "2"
  fi
fi
"""  
load_config()                                    #Load saved variables
get_ipaddress()                                  #Read IP Address of NetDev

"""  
if [ ! -e $WhiteListFile ]; then Generate_WhiteList
fi
"""  
load_whitelist()                                 #Load Whitelist into array
"""
CreateFile "$BlockingCSV"                        #Create Block list csv
  
if [ ! -e "$BlackListFile" ]; then Generate_BlackList
fi

CreateFile "$DomainWhiteListFile"                #Create Black & White lists
CreateFile "$DomainBlackListFile"

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
"""

"""
CreateFile "$BlockingListFile"
cat /dev/null > "$BlockingCSV"                   #Empty file
"""

load_tldlist()                                   #Load and Process TLD List
"""

Process_WhiteList                                #Process White List
"""

getlist_blacklist()                              #Process Users Blacklist
"""  
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
#GetList "securemecca" "unix"
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

echo "deduplicated $dedup Domains"
SortList                                         #Sort, dedup 2nd round, Save list

if [ "${config[bl_tld]}" == 0 ]; then
  DeleteOldFile "$DomainQuickList"
fi
  
echo "Restarting Dnsmasq"
service dnsmasq restart                          #Restart dnsmasq
echo "NoTrack complete"
echo
"""

"""
write Blocking List
destination = open(outfile,'wb')
shutil.copyfileobj(open(file1,'rb'), destination)
shutil.copyfileobj(open(file2,'rb'), destination)
destination.close()
"""

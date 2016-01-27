#!/bin/bash 
#Title : NoTrack DNS Log Archiver
#Description : This script will strip out surplus log information from dnsmasq log files, and store the remainder as a file named dns-"today".log in /var/log/notrack
#Author : QuidsUp
#Usage : dns-log-archive "file.log"

FileIn=$1
FileOut="/var/log/notrack/dns-$(date +%F).log"

if [[ $FileIn == "" ]]; then                     #Check Input file has been provided
  echo "Error input file required"
  exit 2
fi

if [[ $2 == "-k" ]]; then                        #Replace old file if user has added -k
  mv "$FileIn" /tmp/temp.log
  FileOut="$FileIn"
  FileIn="/tmp/temp.log"
fi

if [ -e "$FileOut" ]; then                       #Delete new file if exists
  echo "Removing old file $FileOut"
  rm "$FileOut"
fi

touch "$FileOut"
chmod 644 "$FileOut"

#Dnsmasq log line consists of:
#0 - Month
#1 - Day
#2 - Time
#3 - dnsmasq[pid]
#4 - Function (query, forwarded, reply, cached, config)
#5 - Website Requested
#6 - "is"
#7 - IP Returned

# + = Allowed
# - = Blocked
# 1 = Local

echo "Processing $FileIn"
Dedup=""
while IFS='' read -r Line || [[ -n "$Line" ]]; do
  Seg=($Line)
  
  if [[ ${Seg[5]:0:4} == "www." ]]; then         #Strip www.
    Seg[5]=${Seg[5]:4}
  fi
  
  if [[ ${Seg[4]} == "reply" && ${Seg[5]} != "$Dedup" ]]; then
    echo "${Seg[5]}+" >> "$FileOut"
    Dedup="${Seg[5]}"
  elif [[ ${Seg[4]} == "config" && ${Seg[5]} != "$Dedup" ]]; then
    echo "${Seg[5]}-" >> "$FileOut"
    Dedup="${Seg[5]}"
  elif  [[ ${Seg[4]} == "/etc/localhosts.list" && ${Seg[5]:0:1} != "1" ]]; then
    echo "${Seg[5]}1" >> "$FileOut"
    #Dedup="${Seg[5]}"
  fi
done < "$FileIn"

rm "$FileIn"                                     #Delete old input file
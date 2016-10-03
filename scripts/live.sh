#!/bin/bash 
#Title : NoTrack Live DNS Log Archiver
#Description : 
#Author : QuidsUp
#Date Created : 03 October 2016
#Usage : dns-log-archive "file.log"

#Dnsmasq log line consists of:
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

readonly FILE_DNSLOG="/var/log/notrack.log"



function load_todaylog() {
  dedup_answer=''
  url=''
  local -A querylist

  while IFS='\n' read -r line
  do
    if [[ $line =~ ^[A-Z][a-z][a-z][[:space:]][[:space:]]?[0-9]{1,2}[[:space:]]([0-9]{2}\:[0-9]{2}\:[0-9]{2})[[:space:]]dnsmasq\[[0-9]{1,6}\]\:[[:space:]](query|reply|config|\/etc\/localhosts\.list)(\[[A]{1,4}\])?[[:space:]]([A-Za-z0-9\.\-]+)[[:space:]](is|to|from)(.*)$ ]]; then
      
      if [[ ${BASH_REMATCH[2]} == "query" ]]; then
        if [[ ${BASH_REMATCH[3]} == "[A]" ]]; then            #Only IPv4 (prevent double)
          #$querylist[$matches[4]] = true                #Add to query to $querylist
          echo  ${BASH_REMATCH[4]}         
        fi
      fi
    fi
  done < "$FILE_DNSLOG"
        #elseif ($matches[4] != $dedup_answer) {            #Simplify processing of multiple IP addresses returned
        #  if (array_key_exists($matches[4], $querylist)) { #Does answer match a query?
            #if ($matches[2] == 'reply') $url = simplify_url($matches[4]).'+'
            #elseif ($matches[2] == 'config') $url = simplify_url($matches[4]).'-'
            #elseif ($matches[2] == '/etc/localhosts.list') $url = #simplify_url($matches[4]).'1'
            
#            if (array_key_exists($url, $unsortedlog)) $unsortedlog[$url]++ #Tally
 #           else $unsortedlog[$url] = 1
                    
  #          unset($querylist[$matches[4]])                #Delete query from $querylist
          #}
          #$dedup_answer = $matches[4]                     #Deduplicate answer
        #}
      #}
    #}
  unset IFS
}

load_todaylog

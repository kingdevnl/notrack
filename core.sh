#!/usr/bin/env bash
#
#Title : Core
#Description : Contains core utility functions
#Authors : fernfrost
#Usage : . core.sh


#######################################
# Constants
#######################################
readonly IP_V4="IPv4"
readonly IP_V6="IPv6"

readonly DHCPCD_CONF_PATH="/etc/dhcpcd.conf"
readonly DHCPCD_CONF_OLD_PATH="/etc/dhcpcd.conf.old"
readonly NETWORDK_INTERFACES_PATH="/etc/network/interfaces"
readonly NETWORDK_INTERFACES_OLD_PATH="/etc/network/interfaces.old"


#######################################
# Environment variables
#######################################
GATEWAY_ADDRESS=""
IP_ADDRESS=""
NETWORK_DEVICE=""
IP_VERSION=""
DNS_SERVER_1=""
DNS_SERVER_2=""
BROADCAST_ADDRESS=""
NETMASK_ADDRESS=""
NETWORK_START_ADDRESS=""


#######################################
# Exit script with exit code
# Globals:
#   None
# Arguments:
#   $1 Error Message
#   $2 Exit Code
# Returns:
#   Exit Code
#######################################
error_exit() {
  echo "Error. $1"
  echo "Aborting"
  exit "$2"
}


#######################################
# Check if file exists
# Globals:
#   None
# Arguments:
#   $1 File Path
#   $2 Exit Code
# Returns:
#   Exit Code
#######################################
check_file_exists() {
  if [ ! -e "$1" ]; then
    echo "Error. File $1 is missing.  Aborting."
    exit "$2" 
  fi
}


#######################################
# Draw prompt menu
# Globals:
#   None
# Arguments:
#   $1 = Title, $2, $3... Option 1, 2...
#   $? = Choice user made
#   1. Clear Screen
#   2. Draw menu
#   3. Read single character of user input
#   4. Evaluate user input
#   4a. Check if value is between 0-9
#   4b. Check if value is between 1 and menu size. Return out of function if sucessful
#   4c. Check if user pressed the up key (ending A), Move highlighted point
#   4d. Check if user pressed the up key (ending B), Move highlighted point
#   4e. Check if user pressed Enter key, Return out of function
#   4f. Check if user pressed Q or q, Exit out with error code 1
#   5. User failed to input valid selection. Loop back to #2
# Returns:
#   None
#######################################
menu() {
  local choice
  local highlight
  local menu_size  
  
  highlight=1
  menu_size=0
  clear
  while true; do    
    for i in "$@"; do
      if [ $menu_size == 0 ]; then                #$1 Is Title
        echo -e "$1"
        echo
      else        
        if [ $highlight == $menu_size ]; then
          echo " * $menu_size: $i"
        else
          echo "   $menu_size: $i"
        fi
      fi
      ((menu_size++))
    done
        
    read -r -sn1 choice;
    echo "$choice"
    if [[ $choice =~ ^[0-9]+$ ]]; then           #Has the user chosen 0-9
      if [[ $choice -ge 1 ]] && [[ $choice -lt $menu_size ]]; then
        return $choice
        #break;
      fi
    elif [[ $choice ==  "A" ]]; then             #Up
      if [ $highlight -le 1 ]; then              #Loop around list
        highlight=$((menu_size-1))
        echo
      else
        ((highlight--))
      fi
    elif [[ $choice ==  "B" ]]; then             #Down
      if [ $highlight -ge $((menu_size-1)) ]; then #Loop around list
        highlight=1
        echo
      else
        ((highlight++))
      fi
    elif [[ $choice == "" ]]; then               #Enter
      return $highlight                          #Return Highlighted value
    elif [[ $choice == "q" ]] || [[ $choice == "Q" ]]; then
      exit 1
    fi
    #C Right, D Left
    
    menu_size=0
    clear   
  done
}


#######################################
# Prompt for network device
# Globals:
#   NETWORK_DEVICE
# Arguments:
#   None
# Returns:
#   None
#######################################
prompt_network_device() {
  local count_net_dev=0
  local device=""
  local -a device_list
  local menu_choice

  if [ ! -d /sys/class/net ]; then               #Check net devices folder exists
    echo "Error. Unable to find list of Network Devices"
    echo "Edit user customisable setting \$NetDev with the name of your Network Device"
    echo "e.g. \$NetDev=\"eth0\""
    exit 11
  fi

  for device in /sys/class/net/*; do             #Read list of net devices
    device="${device:15}"                        #Trim path off
    if [[ $device != "lo" ]]; then               #Exclude loopback
      device_list[$count_net_dev]="$device"
      ((count_net_dev++))
    fi
  done
   
  if [ $count_net_dev == 0 ]; then                 #None found
    echo "Error. No Network Devices found"
    echo "Edit user customisable setting \$NetDev with the name of your Network Device"
    echo "e.g. \$NetDev=\"eth0\""
    exit 11
    
  elif [ $count_net_dev == 1 ]; then               #1 Device
    NETWORK_DEVICE=${device_list[0]}                         #Simple, just set it
  elif [ $count_net_dev -gt 0 ]; then
    menu "Select Network Device" ${device_list[*]}
    menu_choice=$?
    NETWORK_DEVICE=${device_list[$((menu_choice-1))]}
  elif [ $count_net_dev -gt 9 ]; then              #9 or more use bash prompt
    clear
    echo "Network Devices detected: ${device_list[*]}"
    echo -n "Select Network Device to use for DNS queries: "
    read -r choice
    NETWORK_DEVICE=$choice
    echo    
  fi
  
  if [[ $NETWORK_DEVICE == "" ]]; then
    error_exit "Network Device not entered" 11
  fi  
}


#######################################
# Prompt for ip version
# Globals:
#   IP_VERSION
# Arguments:
#   None
# Returns:
#   None
#######################################
prompt_ip_version() {
  menu "Select IP Version being used" "IP Version 4 (default)" "IP Version 6" 
  case "$?" in
    1) IP_VERSION=$IP_V4 ;;
    2) IP_VERSION=$IP_V6 ;;
    3) Error_Exit "Aborting Install" 1
  esac   
}


#######################################
# Prompt for DNS server
# Globals:
#   DNS_SERVER_1
#   DNS_SERVER_2
# Arguments:
#   $1 IP version
# Returns:
#   None
#######################################
prompt_dns_server() {
  menu "Choose DNS Server\nThe job of a DNS server is to translate human readable domain names (e.g. google.com) into an  IP address which your computer will understand (e.g. 109.144.113.88) \nBy default your router forwards DNS queries to your Internet Service Provider (ISP), however ISP DNS servers are not the best." "OpenDNS" "Google Public DNS" "DNS.Watch" "Verisign" "Comodo" "FreeDNS" "Yandex DNS" "Other" 
  
  case "$?" in
    1)                                           #OpenDNS
      if [[ $1 == $IP_V6 ]]; then
        DNS_SERVER_1="2620:0:ccc::2"
        DNS_SERVER_2="2620:0:ccd::2"
      else
        DNS_SERVER_1="208.67.222.222" 
        DNS_SERVER_2="208.67.220.220"
      fi
    ;;
    2)                                           #Google
      if [[ $1 == $IP_V6 ]]; then
        DNS_SERVER_1="2001:4860:4860::8888"
        DNS_SERVER_2="2001:4860:4860::8844"
      else
        DNS_SERVER_1="8.8.8.8"
        DNS_SERVER_2="8.8.4.4"
      fi
    ;;
    3)                                           #DNSWatch
      if [[ $1 == $IP_V6 ]]; then
        DNS_SERVER_1="2001:1608:10:25::1c04:b12f"
        DNS_SERVER_2="2001:1608:10:25::9249:d69b"
      else
        DNS_SERVER_1="84.200.69.80"
        DNS_SERVER_2="84.200.70.40"
      fi
    ;;
    4)                                           #Verisign
      if [[ $1 == $IP_V6 ]]; then
        DNS_SERVER_1="2620:74:1b::1:1"
        DNS_SERVER_2="2620:74:1c::2:2"
      else
        DNS_SERVER_1="64.6.64.6"
        DNS_SERVER_2="64.6.65.6"
      fi
    ;;
    5)                                           #Comodo
      DNS_SERVER_1="8.26.56.26"
      DNS_SERVER_2="8.20.247.20"
    ;;
    6)                                           #FreeDNS
      DNS_SERVER_1="37.235.1.174"
      DNS_SERVER_2="37.235.1.177"
    ;;
    7)                                           #Yandex
      if [[ $1 == $IP_V6 ]]; then
        DNS_SERVER_1="2a02:6b8::feed:bad"
        DNS_SERVER_2="2a02:6b8:0:1::feed:bad"
      else
        DNS_SERVER_1="77.88.8.88"
        DNS_SERVER_2="77.88.8.2"
      fi
    ;;
    8)                                           #Other
      echo -en "DNS Server 1: "
      read -r DNS_SERVER_1
      echo -en "DNS Server 2: "
      read -r DNS_SERVER_2
    ;;
  esac   
}


#######################################
# Get default internet gateway address
# Globals:
#   GATEWAY_ADDRESS
# Arguments:
#   None
# Returns:
#   None
#######################################
get_gateway_address() {
  GATEWAY_ADDRESS=$(ip route | grep default | awk '{print $3}')
}


#######################################
# Get current ip address
# Globals:
#   IP_ADDRESS
# Arguments:
#   $1 Ip version, IPv4 / IPv6
#   $2 Network device
# Returns:
#   None
#######################################
get_ip_address() {
  if [[ $1 == $IP_V4 ]]; then
    echo "Reading IPv4 Address from $2"
    IP_ADDRESS=$(ip addr list "$2" |grep "inet " |cut -d' ' -f6|cut -d/ -f1)
    
  elif [[ $1 == $IP_V6 ]]; then
    echo "Reading IPv6 Address from $2"
    IP_ADDRESS=$(ip addr list "$2" |grep "inet6 " |cut -d' ' -f6|cut -d/ -f1)    
  else
    error_exit "Unknown IP Version" 12
  fi
  
  if [[ $IP_ADDRESS == "" ]]; then
    error_exit "Unable to detect IP Address" 13
  fi
}


#######################################
# Get netmask address
# Globals:
#   NETMASK_ADDRESS
# Arguments:
#   $1 Network device
# Returns:
#   None
#######################################
get_netmask_address(){
  NETMASK_ADDRESS=$(ifconfig "$1" | sed -rn '2s/ .*:(.*)$/\1/p')
}


#######################################
# Get broadcast address
# Globals:
#   BROADCAST_ADDRESS
# Arguments:
#   $1 Network device
# Returns:
#   None
#######################################
get_broadcast_address(){
  BROADCAST_ADDRESS=$(ip addr list "$1" | grep "inet" | grep "brd" | cut -d " " -f8)
}


#######################################
# Get netmask address
# Globals:
#   NETWORK_START_ADDRESS
# Arguments:
#   $1 Ip address
#   $2 Netmask address
# Returns:
#   None
#######################################
get_network_start_address(){
  IFS=. read -r i1 i2 i3 i4 <<< "$1"
  IFS=. read -r m1 m2 m3 m4 <<< "$2"
  NETWORK_START_ADDRESS="$((i1 & m1)).$((i2 & m2)).$((i3 & m3)).$((i4 & m4))"
}


#######################################
# Restore dhcpcd config files
# Globals:
#   None
# Arguments:
#   None
# Returns:
#   None
#######################################
restore_dhcpcd_config() {
  if [ -e "$DHCPCD_CONF_OLD_PATH" ]; then
    echo "Restoring dhcpcd config files"
  
    echo "Copying $DHCPCD_CONF_OLD_PATH to $DHCPCD_CONF_PATH"
    sudo cp $DHCPCD_CONF_OLD_PATH $DHCPCD_CONF_PATH
  fi
  echo
}


#######################################
# Backup dhcpcd config files
# Globals:
#   None
# Arguments:
#   None
# Returns:
#   None
#######################################
backup_dhcpcd_config() {
  echo "Backing up dhcpcd config files"
  
  echo "Copying $DHCPCD_CONF_PATH to $DHCPCD_CONF_OLD_PATH"
  if [ -e "$DHCPCD_CONF_PATH" ]; then
    sudo cp $DHCPCD_CONF_PATH $DHCPCD_CONF_OLD_PATH
  fi
  echo
}

#######################################
# Restore network interfaces config files
# Globals:
#   None
# Arguments:
#   None
# Returns:
#   None
#######################################
restore_network_interfaces_config() {
  if [ -e "$NETWORDK_INTERFACES_OLD_PATH" ]; then
    echo "Restoring network interfaces config files"
  
    echo "Copying $NETWORDK_INTERFACES_OLD_PATH to $NETWORDK_INTERFACES_PATH"
    sudo cp $NETWORDK_INTERFACES_OLD_PATH $NETWORDK_INTERFACES_PATH
  fi
  echo
}


#######################################
# Backup network interfaces config files
# Globals:
#   None
# Arguments:
#   None
# Returns:
#   None
#######################################
backup_network_interfaces_config() {
  echo "Backing up network interfaces config files"
  
  echo "Copying $NETWORDK_INTERFACES_PATH to $NETWORDK_INTERFACES_OLD_PATH"
  if [ -e "$NETWORDK_INTERFACES_PATH" ]; then
    sudo cp $NETWORDK_INTERFACES_PATH $NETWORDK_INTERFACES_OLD_PATH
  fi
  echo
}
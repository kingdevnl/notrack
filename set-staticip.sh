#!/usr/bin/env bash
#
#Title : Static Ip
#Description : This script will set a static ip address
#Authors : fernfrost
#Usage : bash set-staticip.sh

#######################################
# Variables from NoTrack
# Kept original to facilitate merge
#######################################
IPAddr=""
readonly IPVersion="IPv4"              # Defaults to IPv4
NetDev=""
readonly DNSChoice1="208.67.222.222"   # Defaults Open Dns


#######################################
# Constants
#######################################
readonly DHCPCD_CONF_PATH="/etc/dhcpcd.conf"
readonly DHCPCD_CONF_OLD_PATH="/etc/dhcpcd.conf.old"


#######################################
# Environment variables
#######################################
GATEWAY_ADDRESS=""


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
#   IPVersion
#   IPAddr
#   NetDev
# Arguments:
#   None
# Returns:
#   None
#######################################
get_ip_address() {
  echo "IP Version: $IPVersion"
  
  if [[ $IPVersion == "IPv4" ]]; then
    echo "Reading IPv4 Address from $NetDev"
    IPAddr=$(ip addr list "$NetDev" |grep "inet " |cut -d' ' -f6|cut -d/ -f1)
    
  elif [[ $IPVersion == "IPv6" ]]; then
    echo "Reading IPv6 Address from $NetDev"
    IPAddr=$(ip addr list "$NetDev" |grep "inet6 " |cut -d' ' -f6|cut -d/ -f1)    
  else
    error_exit "Unknown IP Version" 12
  fi
  
  if [[ $IPAddr == "" ]]; then
    error_exit "Unable to detect IP Address" 13
  fi
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
  check_file_exists "$DHCPCD_CONF_PATH" 24
  sudo cp $DHCPCD_CONF_PATH $DHCPCD_CONF_OLD_PATH
  echo
}


#######################################
# Set static ip
# Globals:
#   IPAddr
#   GATEWAY_ADDRESS
# Arguments:
#   None
# Returns:
#   None
#######################################
set_static_ip(){
  
  echo "Your current ip address is [$IPAddr]"
  echo
  read -p "Enter ip address: " -i $IPAddr -e IPAddr
  echo
  echo "Using $IPAddr as static ip address"

  echo
  echo

  echo "Your current internet gateway address is [$GATEWAY_ADDRESS]"
  echo "This is usually the address of your router"
  echo
  read -p "Enter internet gateway address: " -i $GATEWAY_ADDRESS -e GATEWAY_ADDRESS
  echo
  echo "Using $GATEWAY_ADDRESS as your internet gateway"

  if [ -e $DHCPCD_CONF_PATH ]; then
    set_static_ip_dhcpcd
  else
    # TODO: Add support for /etc/network/interfaces
    # TODO: Check for desktop
    # desktop=$(dpkg -l | egrep -i "(kde|gnome|lxde|xfce|mint|unity|fluxbox|openbox)" | grep -v library)
    error_exit "Currently only Raspbian Jessie supported" 13
  fi
}


#######################################
# Set static ip using dhcpcd.conf
# Globals:
#   NetDev
#   IPAddr
#   GATEWAY_ADDRESS
#   DNSChoice1
# Arguments:
#   None
# Returns:
#   None
#######################################
set_static_ip_dhcpcd(){
  sudo sed -i -e "\$a\ " $DHCPCD_CONF_PATH
  sudo sed -i -e "\$a#Static Ip Address" $DHCPCD_CONF_PATH
  sudo sed -i -e "\$ainterface $NetDev" $DHCPCD_CONF_PATH
  sudo sed -i -e "\$astatic ip_address=$IPAddr/24" $DHCPCD_CONF_PATH
  sudo sed -i -e "\$astatic routers="$GATEWAY_ADDRESS $DHCPCD_CONF_PATH
  sudo sed -i -e "\$astatic domain_name_servers="$DNSChoice1 $DHCPCD_CONF_PATH
}








#######################################
# Functions from NoTrack
# Kept original to facilitate merge
#######################################

Ask_NetDev() {
  local CountNetDev=0
  local Device=""
  local -a ListDev
  local MenuChoice

  if [ ! -d /sys/class/net ]; then               #Check net devices folder exists
    echo "Error. Unable to find list of Network Devices"
    echo "Edit user customisable setting \$NetDev with the name of your Network Device"
    echo "e.g. \$NetDev=\"eth0\""
    exit 11
  fi

  for Device in /sys/class/net/*; do             #Read list of net devices
    Device="${Device:15}"                        #Trim path off
    if [[ $Device != "lo" ]]; then               #Exclude loopback
      ListDev[$CountNetDev]="$Device"
      ((CountNetDev++))
    fi
  done
   
  if [ $CountNetDev == 0 ]; then                 #None found
    echo "Error. No Network Devices found"
    echo "Edit user customisable setting \$NetDev with the name of your Network Device"
    echo "e.g. \$NetDev=\"eth0\""
    exit 11
    
  elif [ $CountNetDev == 1 ]; then               #1 Device
    NetDev=${ListDev[0]}                         #Simple, just set it
  elif [ $CountNetDev -gt 0 ]; then
    Menu "Select Menu Device" ${ListDev[*]}
    MenuChoice=$?
    NetDev=${ListDev[$((MenuChoice-1))]}
  elif [ $CountNetDev -gt 9 ]; then              #9 or more use bash prompt
    clear
    echo "Network Devices detected: ${ListDev[*]}"
    echo -n "Select Network Device to use for DNS queries: "
    read -r Choice
    NetDev=$Choice
    echo    
  fi
  
  if [[ $NetDev == "" ]]; then
    error_exit "Network Device not entered" 11
  fi  
}












#######################################
# Main
#######################################
get_gateway_address

Ask_NetDev

get_ip_address

restore_dhcpcd_config

backup_dhcpcd_config

set_static_ip

echo
echo
echo "Your static ip address has been set to $IPAddr"
echo
echo "Reboot required for changes to take effect"
echo "Run sudo reboot"
echo
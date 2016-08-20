#!/usr/bin/env bash
#
#Title : Static Ip
#Description : This script will set a static ip address
#Authors : fernfrost
#Usage : bash set-staticip.sh


#######################################
# Importing utilities
#######################################
. core.sh


#######################################
# Constants
#######################################
readonly DHCPCD_CONF_PATH="/etc/dhcpcd.conf"
readonly DHCPCD_CONF_OLD_PATH="/etc/dhcpcd.conf.old"
readonly NETWORDK_INTERFACES_PATH="/etc/network/interfaces"
readonly NETWORDK_INTERFACES_OLD_PATH="/etc/network/interfaces.old"


#######################################
# Environment variables
#######################################
BROADCAST_ADDRESS=""
NETMASK_ADDRESS=""
NETWORK_START_ADDRESS=""


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


#######################################
# Set static ip
# Globals:
#   IP_ADDRESS
#   GATEWAY_ADDRESS
# Arguments:
#   None
# Returns:
#   None
#######################################
set_static_ip(){
  clear
  echo "Your current ip address is [$IP_ADDRESS]"
  echo
  read -p "Enter ip address: " -i $IP_ADDRESS -e IP_ADDRESS

  echo
  echo
  echo
  
  echo "Your current internet gateway address is [$GATEWAY_ADDRESS]"
  echo "This is usually the address of your router"
  echo
  read -p "Enter internet gateway address: " -i $GATEWAY_ADDRESS -e GATEWAY_ADDRESS
  echo

  if [[ ! -z $(which dhcpcd) ]]; then
    set_static_ip_dhcpcd
  else
    # Check GUI desktop is installed
    if [[ ! -z $(dpkg -l | egrep -i "(kde|gnome|lxde|xfce|mint|unity|fluxbox|openbox)" | grep -v library) ]]; then
      # GUI Desktop installed
      echo "GUI desktop detected, use connection editor to set static ip address"
      echo
      exit
    else
      # No GUI desktop installed
      set_static_ip_network_interfaces
    fi
  fi
}


#######################################
# Set static ip using dhcpcd.conf
# Globals:
#   NETWORK_DEVICE
#   IP_ADDRESS
#   GATEWAY_ADDRESS
#   DNS_SERVER_1
# Arguments:
#   None
# Returns:
#   None
#######################################
set_static_ip_dhcpcd(){
  sudo sed -i -e "\$a\ " $DHCPCD_CONF_PATH
  sudo sed -i -e "\$a#Static Ip Address" $DHCPCD_CONF_PATH
  sudo sed -i -e "\$ainterface $NETWORK_DEVICE" $DHCPCD_CONF_PATH
  if [[ $IP_VERSION = $IP_V4 ]]; then
    sudo sed -i -e "\$astatic ip_address=$IP_ADDRESS/24" $DHCPCD_CONF_PATH
  else
    sudo sed -i -e "\$astatic ip_address=$IP_ADDRESS/64" $DHCPCD_CONF_PATH
  fi
  sudo sed -i -e "\$astatic routers="$GATEWAY_ADDRESS $DHCPCD_CONF_PATH
  sudo sed -i -e "\$astatic domain_name_servers=$DNS_SERVER_1 $DNS_SERVER_2" $DHCPCD_CONF_PATH
}


#######################################
# Set static ip using /etc/network/interfaces
# Globals:
#   NETWORK_DEVICE
#   IP_ADDRESS
#   GATEWAY_ADDRESS
#   NETMASK_ADDRESS
#   NETWORK_START_ADDRESS
#   BROADCAST_ADDRESS
#   DNS_SERVER_1
#   DNS_SERVER_2
# Arguments:
#   None
# Returns:
#   None
#######################################
set_static_ip_network_interfaces(){
  sudo sed -i "s/iface $NETWORK_DEVICE inet dhcp/iface $NETWORK_DEVICE inet static/" $NETWORDK_INTERFACES_PATH
  sudo sed -i -e '/iface '"$NETWORK_DEVICE"' inet static/a \\tdns-nameservers '"$DNS_SERVER_1 $DNS_SERVER_2" $NETWORDK_INTERFACES_PATH
  sudo sed -i -e '/iface '"$NETWORK_DEVICE"' inet static/a \\tgateway '"$GATEWAY_ADDRESS" $NETWORDK_INTERFACES_PATH
  sudo sed -i -e '/iface '"$NETWORK_DEVICE"' inet static/a \\tbroadcast '"$BROADCAST_ADDRESS" $NETWORDK_INTERFACES_PATH
  sudo sed -i -e '/iface '"$NETWORK_DEVICE"' inet static/a \\tnetmask '"$NETMASK_ADDRESS" $NETWORDK_INTERFACES_PATH
  sudo sed -i -e '/iface '"$NETWORK_DEVICE"' inet static/a \\tnetwork '"$NETWORK_START_ADDRESS" $NETWORDK_INTERFACES_PATH
  sudo sed -i -e '/iface '"$NETWORK_DEVICE"' inet static/a \\taddress '"$IP_ADDRESS" $NETWORDK_INTERFACES_PATH
}


#######################################
# Show welcome message
# Globals:
#   None
# Arguments:
#   None
# Returns:
#   None
#######################################
show_welcome() {
  echo "Set Static Ip Address"
  echo
  echo "This script will guide you through setting a static ip address on your system"
  echo 
  echo "Press any key to contine..."
  read -rn1
}


#######################################
# Show end message
# Globals:
#   None
# Arguments:
#   None
# Returns:
#   None
#######################################
show_end() {
  clear
  echo "Your settings have successfully been applied"
  echo
  echo "Static IP Address:  $IP_ADDRESS"
  echo "Internet Gateway:   $GATEWAY_ADDRESS"
  echo "DNS Server:         $DNS_SERVER_1 $DNS_SERVER_2"
  echo
  echo "Reboot required for changes to take effect"
  echo "Run sudo reboot"
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
  BROADCAST_ADDRESS=$(ip addr list "$NETWORK_DEVICE" | grep "inet" | grep "brd" | cut -d " " -f8)
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
# Main
#######################################
show_welcome

if [[ ! -z $(dpkg -l | egrep -i "(kde|gnome|lxde|xfce|mint|unity|fluxbox|openbox)" | grep -v library) ]]; then
  # GUI Desktop installed
  echo "GUI desktop detected, use connection editor to set static ip address"
  echo
  exit
fi

prompt_ip_version

prompt_network_device

prompt_dns_server $IP_VERSION

get_ip_address $IP_VERSION $NETWORK_DEVICE

get_broadcast_address $NETWORK_DEVICE

get_netmask_address $NETWORK_DEVICE

get_network_start_address $IP_ADDRESS $NETMASK_ADDRESS

get_gateway_address

restore_dhcpcd_config
restore_network_interfaces_config

backup_dhcpcd_config
backup_network_interfaces_config

set_static_ip

show_end
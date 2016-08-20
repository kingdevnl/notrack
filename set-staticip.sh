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


#######################################
# Environment variables
#######################################


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
  echo "Set Static Ip"
  echo
  echo "This script will set a static ip address on your system"
  echo 
  echo "Press any key to contine..."
  read -rn1
}


#######################################
# Show end message
# Globals:
#   IP_ADDRESS
# Arguments:
#   None
# Returns:
#   None
#######################################
show_end() {
  menu "The static ip address has been set to: $IP_ADDRESS\n\nThe system must be rebooted for changes to take effect" "Reboot Now" "Reboot Later"

  if [[ $? == 1 ]]; then
    sudo reboot
  else
    echo "To reboot, run command: sudo reboot"
  fi
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

  clear
  echo "Your current internet gateway address is [$GATEWAY_ADDRESS]"
  echo "This is usually the address of your router"
  echo
  read -p "Enter internet gateway address: " -i $GATEWAY_ADDRESS -e GATEWAY_ADDRESS
  echo

  if [[ ! -z $(which dhcpcd) ]]; then
    set_static_ip_dhcpcd
  else
    set_static_ip_network_interfaces
  fi
}


#######################################
# Main
#######################################
show_welcome

if [[ -z $(which dhcpcd) ]]; then
  if [[ ! -z $(dpkg -l | egrep -i "(kde|gnome|lxde|xfce|mint|unity|fluxbox|openbox)" | grep -v library) ]]; then
    # GUI Desktop installed
    echo "GUI desktop detected, use connection editor to set static ip address"
    echo
    exit
  fi
fi

prompt_ip_version

prompt_network_device

prompt_dns_server $IP_VERSION

get_ip_address $IP_VERSION $NETWORK_DEVICE

get_broadcast_address $NETWORK_DEVICE

get_netmask_address $NETWORK_DEVICE

get_network_start_address $IP_ADDRESS $NETMASK_ADDRESS

get_gateway_address

if [[ ! -z $(which dhcpcd) ]]; then
  restore_dhcpcd_config
  backup_dhcpcd_config
else
  restore_network_interfaces_config
  backup_network_interfaces_config
fi

set_static_ip

show_end
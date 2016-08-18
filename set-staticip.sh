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
  sudo sed -i -e "\$astatic ip_address=$IP_ADDRESS/24" $DHCPCD_CONF_PATH
  sudo sed -i -e "\$astatic routers="$GATEWAY_ADDRESS $DHCPCD_CONF_PATH
  sudo sed -i -e "\$astatic domain_name_servers=$DNS_SERVER_1" $DHCPCD_CONF_PATH
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
# Show welcome message
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
  echo "DNS Server:         $DNS_SERVER_1"
  echo
  echo "Reboot required for changes to take effect"
  echo "Run sudo reboot"
}


#######################################
# Main
#######################################
show_welcome

prompt_ip_version

if [[ $IP_VERSION != $IP_V4 ]]; then
  # TODO: Test and verify IPv6 functionality
  error_exit "Only IPv4 supported for now" 1
fi

prompt_network_device

prompt_dns_server $IP_VERSION

get_ip_address $IP_VERSION $NETWORK_DEVICE

get_gateway_address

restore_dhcpcd_config

backup_dhcpcd_config

set_static_ip

show_end
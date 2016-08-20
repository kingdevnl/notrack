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
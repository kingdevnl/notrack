#!/usr/bin/env bash
#Title : NoTrack DHCP Server Setup
#Description : This script will setup DHCP server for NoTrack
#Authors : fernfrost
#Usage : bash setup-dhcp.sh


#Check File Exists---------------------------------------------------
Check_File_Exists() {
  #$1 File to Check
  #$2 Exit Code
  if [ ! -e "$1" ]; then
    echo "Error. File $1 is missing.  Aborting."
    exit "$2" 
  fi
}

#Program variables
DnsmasqConfNoTrackOldFile="/etc/dnsmasq.conf.notrack.old"

#Welcome Dialog------------------------------------------------------
Show_Welcome() {
  echo "This will setup DHCP Server for NoTrack"
  echo "Setup Guide: https://youtu.be/a5dUJ0SlGP0"
  echo
  echo "Press any key to contine..."
  read -rn1
}

#Finish Dialog-------------------------------------------------------
Show_Finish() {
  echo "DHCP Server Setup Complete"
  echo "Access the admin console at http://$(hostname)/admin"
  echo
}

#Backup Configs------------------------------------------------------
Backup_Conf() {
  echo "Backing up old config files"
  
  echo "Copying /etc/dnsmasq.conf to $DnsmasqConfNoTrackOldFile"
  Check_File_Exists "/etc/dnsmasq.conf" 24
  sudo cp /etc/dnsmasq.conf $DnsmasqConfNoTrackOldFile
  echo
}

#Restore Configs-----------------------------------------------------
Restore_Conf() {
  if [ -e "$DnsmasqConfNoTrackOldFile" ]; then
    clear
    echo "DHCP Server for NoTrack has already been configured"
    echo
    echo "Continuing with this setup will replace current configuration"
    echo
    echo "Press any key to contine..."
    read -rn1

    echo
    echo "Restoring old config files"
  
    echo "Copying $DnsmasqConfNoTrackOldFile to /etc/dnsmasq.conf"
    sudo cp $DnsmasqConfNoTrackOldFile /etc/dnsmasq.conf
  fi
  echo
}

#Config DHCP Range---------------------------------------------------
Config_DhcpRange(){
  clear
  echo "Configure DHCP Range"
  echo
  echo "You need to supply the range of addresses available for lease and a lease time"
  echo
  echo "Example:"
  echo "Range start:  192.168.62.100"
  echo "Range end:    192.168.62.254"
  echo "Lease time:   24h"
  echo

  echo "Enter range start:"
  read dhcp_range_start

  echo "Enter range end:"
  read dhcp_range_end

  echo "Enter lease time:"
  read dhcp_lease_time

  sudo sed -i "s/#dhcp-range-replace-token-ipv4/dhcp-range=$dhcp_range_start,$dhcp_range_end,$dhcp_lease_time/" /etc/dnsmasq.conf
}

#Config DHCP Option--------------------------------------------------
Config_DhcpOption(){
  clear
  echo "Configure Internet Gateway"
  echo
  echo "You need to supply the address of your internet gateway. This is usually the address of your router"
  echo
  echo "Example:"
  echo "Internet gateway:  192.168.62.1"
  echo

  echo "Enter internet gateway:"
  read dhcp_internet_gateway

  sudo sed -i "s/#dhcp-option-replace-token-ipv4/dhcp-option=3,$dhcp_internet_gateway/" /etc/dnsmasq.conf
}

#Config DHCP Logging-------------------------------------------------
Config_DhcpLogging(){
  #Logging is currently enabled by default
  echo "Configuring logging"
  echo
}

#Config DHCP Authoritative Mode--------------------------------------
Config_AuthoritativeMode(){
  echo "Configuring authoritative mode"
  sudo sed -i "s/#dhcp-authoritative/dhcp-authoritative/" /etc/dnsmasq.conf
  echo
}

#Main----------------------------------------------------------------

Show_Welcome

Restore_Conf

Backup_Conf

Config_DhcpRange

Config_DhcpOption

Config_DhcpLogging

Config_AuthoritativeMode

echo "Starting Services"
sudo service dnsmasq restart
echo

Show_Finish

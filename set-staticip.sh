#!/usr/bin/env bash
#Title : Static Ip
#Description : This script will set a static ip address
#Authors : fernfrost
#Usage : bash set-staticip.sh


IPAddr=""
RouterIPAddr=""
DNSChoice1="208.67.222.222"   #Using Open Dns

#Error Exit 2nd generation--------------------------------------------
Error_Exit() {
  #$1 Error Message
  #$2 Exit Code
  echo "Error. $1"
  echo "Aborting"
  exit "$2"
}

#Check File Exists---------------------------------------------------
Check_File_Exists() {
  #$1 File to Check
  #$2 Exit Code
  if [ ! -e "$1" ]; then
    echo "Error. File $1 is missing.  Aborting."
    exit "$2" 
  fi
}

#Get Operating System-------------------------------------------------
Get_Os(){
  Os_Id=$(lsb_release -i | cut -d ":" -f 2 | sed -e "s/^[[:space:]]*//")
  Os_Version=$(lsb_release -r | cut -d ":" -f 2 | sed -e "s/^[[:space:]]*//")
  Os_Description=$(lsb_release -d | cut -d ":" -f 2 | sed -e "s/^[[:space:]]*//")

  echo "Running on $Os_Description"
  echo

  if [ $Os_Id != "Raspbian" ]; then
    Error_Exit "Only Raspbian supported" 1
  fi
}


#Backup Configs------------------------------------------------------
Backup_Conf() {
  echo "Backing up config files"
  
  echo "Copying /etc/dhcpcd.conf to /etc/dhcpcd.conf.old"
  Check_File_Exists "/etc/dhcpcd.conf" 24
  sudo cp /etc/dhcpcd.conf /etc/dhcpcd.conf.old
  echo
}

#Restore Configs-----------------------------------------------------
Restore_Conf() {
  if [ -e "/etc/dhcpcd.conf.old" ]; then
    echo "Restoring old config files"
  
    echo "Copying /etc/dhcpcd.conf.old to /etc/dhcpcd.conf"
    sudo cp /etc/dhcpcd.conf.old /etc/dhcpcd.conf
  fi
  echo
}

#Set Static Ip Address-----------------------------------------------
Set_StaticIp(){
  echo "Configure Static Ip Address"
  echo
  echo "You need to supply an ip addresse and the address of your internet gateway (usually the ip address of your router)"
  echo
  echo "Example:"
  echo "Ip address:       192.168.62.2"
  echo "Internet gateway: 192.168.62.1"
  echo

  echo "Enter ip address:"
  read IPAddr

  echo

  echo "Enter internet gateway address:"
  read RouterIPAddr

  if [ $Os_Id = "Raspbian" ]; then
    Set_StaticIp_Raspbian_Jessie
  fi
}

Set_StaticIp_Raspbian_Jessie(){
  sudo sed -i -e "\$a\ " /etc/dhcpcd.conf
  sudo sed -i -e "\$a#Static Ip Address" /etc/dhcpcd.conf
  sudo sed -i -e "\$ainterface eth0" /etc/dhcpcd.conf
  sudo sed -i -e "\$astatic ip_address=$IPAddr/24" /etc/dhcpcd.conf
  sudo sed -i -e "\$astatic routers="$RouterIPAddr /etc/dhcpcd.conf
  sudo sed -i -e "\$astatic domain_name_servers="$DNSChoice1 /etc/dhcpcd.conf
}

#Main---------------------------------------------------------------
Get_Os

Restore_Conf

Backup_Conf

Set_StaticIp

echo
echo "Static ip successfully set to $IPAddr"
echo
echo "Reboot required"
echo "Run sudo reboot"
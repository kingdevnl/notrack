#!/usr/bin/env bash

Ask_Os(){
    local Os_Id=""
    local Os_Version=""
    local Os_Description=""
    
    if [ -e /etc/lsb-release ]; then
        Os_Id=$(cat /etc/lsb-release | grep "DISTRIB_ID" | cut -d "=" -f 2)
        Os_Version=$(cat /etc/lsb-release | grep "DISTRIB_RELEASE" | cut -d "=" -f 2)
        Os_Description=$(cat /etc/lsb-release | grep "DISTRIB_DESCRIPTION" | cut -d "=" -f 2 | cut -d "\"" -f 2)
    elif [ -e /etc/os-release ]; then
        #Raspbian Jessie
        Os_Id=$(cat /etc/lsb-release | grep "^ID=" | cut -d "=" -f 2)
        Os_Version=$(cat /etc/lsb-release | grep "VERSION_ID" | cut -d "=" -f 2 | cut -d "\"" -f 2)
        Os_Description=$(cat /etc/lsb-release | grep "PRETTY_NAME" | cut -d "=" -f 2 | cut -d "\"" -f 2)
    else
        echo "Warning: Unable to determine OS"
    fi


        echo $Os_Id
        echo $Os_Version
        echo $Os_Description
}


Ask_Os
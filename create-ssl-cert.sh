#!/usr/bin/env bash
#Title : NoTrack SSL Certificate Creator
#Description : This script will assist with creating and installing an SSL certificate on Lighttpd web server
#Author : QuidsUp
#Date 	: 2016-01-10
#Version: v0.7.14
#Usage 	: bash create-ssl-cert.sh

#Program Settings----------------------------------------------------
HostName=$(hostname -f)


#Check if required applications are installed------------------------
Check_AppsInstalled() {
  if [[ $(command -v lighttpd) == "" ]]; then
    echo "Lighttpd is not installed.  Aborting."
    exit 41
  fi
  if [[ $(command -v openssl) == "" ]]; then
    echo "OpenSSL is not installed.  Aborting."
    exit 42
  fi
}

#Main----------------------------------------------------------------
if [ "$(id -u)" == "0" ]; then                   #Check if running as root
  echo "Error: Don't run this script as Root"
  echo "Execute with: bash create-ssl-cert.sh"
  exit 4  
fi

Check_AppsInstalled                              #Check if required apps are installed

clear
echo "This installer will generate a self-signed SSL Certificate on your NoTrack Webserver - Lighttpd."
echo
echo "Internet Browsers will throw display Connection Untrusted against this self-signed certificate."
echo "Even if you purchase a legitimate SSL certificate it will still be invalid on NoTrack, because of the way it sink-holes multiple domains." 
echo

echo "Example details required to create an SSL Certificate:"
echo "Country Name (2 letter code) [AU]: GB"
echo "State or Province Name (full name) [Some-State]: ."
echo "Locality Name (eg, city) []: Cardiff"
echo "Organization Name (eg, company) [Internet Widgits Pty Ltd]: Quidsup"
echo "Organizational Unit Name (eg, section) []: IT"
echo "Common Name (e.g. server FQDN or YOUR name) []: $HostName"
echo "Email Address []: certs@quidsup.net"
echo
echo "Two letter Country Codes: https://www.digicert.com/ssl-certificate-country-codes.htm"
echo
read -p "Continue (Y/n)? " -n1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
  echo "Aborting"
  exit 1
fi

#Start creating the certificate
echo "Enabling SSL Module on Lighttpd"
sudo lighty-enable-mod ssl
echo

openssl req -new -newkey rsa:2048 -nodes -sha256 -x509 -days 365 -keyout ~/server.key -out ~/server.crt
if [ ! -e ~/server.key ] || [ ! -e ~/server.crt ]; then
  echo "Error creation of SSL certificate has failed.  Aborting"
  exit 43
fi

echo "Merging Crt file and Key file to form Pem"
cat ~/server.key ~/server.crt > ~/server.pem
echo

#pkcs12 method doesn't work in Lighttpd 
#openssl req -sha256 -x509 -newkey rsa:2048 -keyout ~/key.pem -out ~/server.pem -days 365
#echo "Generating pkcs12 certificate"
#echo "The pass phrase is what you just typed in earlier"
#openssl pkcs12 -export -in ~/server.pem -inkey ~/key.pem -name "$HostName" -out "$HostName-cert.p12"

echo "Copying Certificate to /etc/lighttpd/"
sudo cp ~/server.pem /etc/lighttpd/server.pem
echo

echo "Restarting Lighttpd"
sudo service lighttpd force-reload
echo

if [ -z "$(pgrep lighttpd)" ]; then                #Check if lighttpd restart has been successful
  echo "Lighttpd restart has failed."
  echo "Something is wrong in the Lighttpdconfig"
  echo
  
  sleep 5s
  echo "Disabling Lighttpd SSL Module"
  sudo lighty-disable-mod ssl                      #Disable SSL Module
  echo "Restarting Lighttpd"
  sudo service lighttpd force-reload
  echo
  
  if [ -z "$(pgrep lighttpd)" ]; then              #Check if lighttpd restart has now been successful
    echo "Lighttpd restart failed"
    exit 44
  else
    echo "Lighttpd restart successful"
    exit 45
  fi
fi

echo "SSL Certificate has been installed and Lighttpd has sucessfully restarted"
#echo "Install the $HostName-cert.p12 certificate into your web browser"
#!/bin/bash
#Title : NoTrack Exec
#Description :  page
#Author : QuidsUp
#Date : 2015-02-02
#Usage : ntrk-exec


#Check File Exists---------------------------------------------------
Check_File_Exists() {
  if [ ! -e "$1" ]; then
    echo "Error file $1 is missing.  Aborting."
    exit 2
  fi
}
#Delete History------------------------------------------------------
Delete-History() {
  echo "Deleting Log Files in /var/log/notrack"
  rm /var/log/notrack/*
}

#Main----------------------------------------------------------------

Check_File_Exists "/tmp/ntrk-exec.txt"

while read Line; do
  #echo "$Line"
  case "$Line" in
    delete-history)
      Delete-History
    ;;
    update-config)
      Check_File_Exists "/tmp/notrack.conf"
      chown root:root /tmp/notrack.conf
      chmod 644 /tmp/notrack.conf      
      echo "Copying /tmp/notrack.conf to /etc/notrack.conf"
      mv /tmp/notrack.conf /etc/notrack/notrack.conf
      echo
    ;;
    blockmsg-message)
      echo 'Setting Block message Blocked by NoTrack';
      echo '<p>Blocked by NoTrack</p>' > /var/www/html/sink/index.html
    ;;
    blockmsg-pixel)
      echo '<img src="data:image/gif;base64,R0lGODlhAQABAAAAACwAAAAAAQABAAA=" alt="" />' > /var/www/html/sink/index.html
      echo 'Setting Block message to 1x1 pixel';
    ;;
    run-notrack)
      notrack
    ;;
  esac
done < /tmp/ntrk-exec.txt

rm /tmp/ntrk-exec.txt
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <link href="./css/master.css" rel="stylesheet" type="text/css" />
    <link rel="icon" type="image/png" href="./favicon.png" />
    <title>DHCP Leases</title>
</head>

<body>
<div id="main">
<?php
$CurTopMenu = 'dhcp';
include('./include/topmenu.html');
echo "<h1>DHCP Leases</h1>\n";

if (file_exists('/var/lib/misc/dnsmasq.leases')) {
  $FileHandle= fopen('/var/lib/misc/dnsmasq.leases', 'r') or die("Error unable to open /var/lib/misc/dnsmasq.leases");
  echo '<div class="row"><br />'."\n";
  echo '<table class="dhcp-table"><tr>';
  echo '<th>Date of Request</th><th>Device Name</th><th>MAC Address</th><th>IP Allocated</th>'."\n";
  while (!feof($FileHandle)) {
    $Line = trim(fgets($FileHandle));            //Read Line of LogFile
    if ($Line != '') {                           //Sometimes a blank line appears in log file
      $Seg = explode(' ', $Line);
      //0 - Time Requested in Unix Time
      //1 - MAC Address
      //2 - IP Allocated
      //3 - Device Name
      //4 - '*' or MAC address
      echo '<tr><td>'.date("d M Y \- H:i:s", $Seg[0]).'</td><td>'.$Seg[3].'</td><td>'.$Seg[1].'</td><td>'.$Seg[2].'</td>';
      echo "</tr>\n";
    }    
  }
  echo "</table>\n";
}
else {
  echo "<p>DHCP Functionality not in use</p>\n";
}

?>
</div>
</div>
</body>
</html>

<?php
require('./include/global-vars.php');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <link href="./css/master.css" rel="stylesheet" type="text/css" />
    <link rel="icon" type="image/png" href="./favicon.png" />
    <title>NoTrack Admin</title>
</head>

<body>
<div id="main">
<?php
include('topmenu.html');
echo "<h1>NoTrack Admin</h1>\n"; 

$Config = array();

function Load_Config_File() {
  global $Config;
  if (!file_exists('/etc/notrack/notrack.conf')) {
    die("Error file /etc/notrack/notrack.conf doesn't exist");
  }
  $Config = parse_ini_file('/etc/notrack/notrack.conf');
  
  return null;
}

//Main---------------------------------------------------------------
echo '<div class="row"><br /></div>'."\n";
echo '<div class="row">';
echo '<a href="./blocklist.php"><div class="home-nav-r"><h2>Tracker Blocklist</h2><div class="home-nav-left"><h3 class="home-nav">'.number_format(floatval(exec('wc -l /etc/notrack/tracker-quick.list | cut -d\  -f 1'))).'</h3><h4 class="home-nav">Domains</h4></div><div class="home-nav-right"><img class="full" src="./images/magnifying_glass.png" alt=""></div></div></a>'."\n";

echo '<a href="./tldblocklist.php"><div class="home-nav-b"><h2>TLD Blocklist</h2><div class="home-nav-left"><h3 class="home-nav">'.number_format(floatval(exec('wc -l /etc/notrack/domain-quick.list | cut -d\  -f 1'))).'</h3><h4 class="home-nav">Domains</h4></div><div class="home-nav-right"><img class="full" src="./images/globe.png" alt=""></div></div></a>'."\n";

echo '<a href="./stats.php"><div class="home-nav-g"><h2>DNS Queries</h2><div class="home-nav-left"><h3 class="home-nav">'.number_format(floatval(exec('cat /var/log/notrack.log | grep -F query[A] | wc -l'))).'</h3><h4 class="home-nav">Today</h4></div><div class="home-nav-right"><img class="full" src="./images/server.png" alt=""></div></div></a>'."\n";

if (file_exists('/var/lib/misc/dnsmasq.leases')) {
  echo '<a href="./dhcpleases.php"><div class="home-nav-y"><h2>DHCP</h2><div class="home-nav-left"><h3 class="home-nav">'.number_format(floatval(exec('wc /var/lib/misc/dnsmasq.leases | cut -d\  -f 3'))).'</h3><h4 class="home-nav">Systems</h4></div><div class="home-nav-right"><img class="full" src="./images/computer.png" alt=""></div></div></a>'."\n";
}
else {
  echo '<a href="./dhcpleases.php"><div class="home-nav-y"><h2>DHCP</h2><div class="home-nav-left"><h3 class="home-nav">N/A</h3></div><div class="home-nav-right"><img class="full" src="./images/computer.png" alt=""></div></div></a>'."\n";
}
echo '</div>';


Load_Config_File();  
  
if (array_key_exists('LatestVersion', $Config)) {
  if ($Version != $Config[LatestVersion]) {      //See if upgrade Needed
    echo '<div class="splitrow"><br /><br /></div>'."\n";
    echo '<div class="row"><br />'."\n";
    echo '<p>New version available: v'.$Config[LatestVersion].'</p><br /></div>'."\n";   
  }
}
else {                                           //Config line missing
  echo '<div class="splitrow"><br /><br /></div>'."\n";
  echo '<div class="row"><br />'."\n";
  echo '<p>Unable to identify latest version available. </p>';
  echo "<br /></div>\n";    
}

//Temp warning about changes
echo '<div class="row">'."\n";
echo '<h3>Important Note: </h3>';
echo '<p>As of NoTrack v0.5 (released 27 Jan 2016) a significant number of changes have been made to the underlying functionality of NoTrack, therefore you will need to <b>re-install NoTrack</b>.</p>'."\n";
echo '<p>Remember to take a copy of Dnsmaq log file <b>/etc/dnsmasq.conf</b> if you have made any changes.<br />There is no need to do a full re-install of the OS.</p>'."\n";
echo '<p>Instructions:</p>'."\n";
echo '<pre>cd ~/NoTrack<br />bash install.sh</pre>'."\n";
echo '</div>';


?>
</div>
</body>
</html>

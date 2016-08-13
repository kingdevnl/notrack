<?php
require('./include/global-vars.php');
require('./include/global-functions.php');
require('./include/topmenu.php');

LoadConfigFile();
if ($Config['Password'] != '') {  
  session_start();  
  if (! Check_SessionID()) {
    header("Location: ./login.php");
    exit;
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <link href="./css/master.css" rel="stylesheet" type="text/css" />
  <link rel="icon" type="image/png" href="./favicon.png" />
  <script src="./include/menu.js"></script>
  <title>NoTrack Admin</title>
</head>

<body>
<div id="main">
<?php

ActionTopMenu();
DrawTopMenu();
//Main---------------------------------------------------------------

$BlockListDate = 0;
$StatusStr = '';
$DateStr = '';
$DateSubStr = '<h2>Block list is in date</h2>';
$CurrentTime = time();

if (substr($Config['Status'], 0, 6) == 'Paused') {
  $StatusStr = '<h4>Paused</h4>';
  $DateStr = '<h2>---</h2>';
  $DateSubStr = '';
}
elseif ($Config['Status'] == 'Stop') {
  $StatusStr = '<h6>Disabled</h6>';
  $DateStr = '<h2>---</h2>';
  $DateSubStr = '';
}
else {
  $StatusStr = '<h3>Active</h3>';
}

if (file_exists($FileBlockList)) {
  $BlockListDate = filemtime($FileBlockList);
  if ($BlockListDate > $CurrentTime - 86400) $DateStr = '<h3>Today</h3>';
  elseif ($BlockListDate > $CurrentTime - 172800) $DateStr = '<h3>2 Days ago</h3>';
  elseif ($BlockListDate > $CurrentTime - 259200) $DateStr = '<h3>3 Days ago</h3>';
  elseif ($BlockListDate > $CurrentTime - 345600) $DateStr = '<h4>4 Days ago</h4>';
  else {
    $DateStr = '<h6>'.date('d M', $BlockListDate).'</h6>';
    $DateSubStr = '<h6>Out of date</h6>';
  }
}  
else {
  if ($StatusStr == 'Active') {
    $StatusStr = 'Block List Missing';
    $DateStr = 'Unknown';
  }
}
echo '<div id="top-padding"></div>';
echo '<div class="home-nav-container">';

echo '<a href="#"><div class="home-nav"><h2>Status</h2><hr /><br />'.$StatusStr.'</div></a>'.PHP_EOL;
echo '<a href="#"><div class="home-nav"><h2>Last Updated</h2><hr /><br />'.$DateStr.$DateSubStr.'</div></a>'.PHP_EOL;

if (file_exists($CSVBlocking)) {                 //Block List
  if(filemtime($CSVBlocking) + 20 > time()) {    //Is notrack writing to CSV File?
    echo '<a href="./config.php?v=sites"><div class="home-nav"><h2>Block List</h2><span>Processing</span><div class="icon-box"><img src="./svg/home_trackers.svg" alt=""></div></div></a>'.PHP_EOL;
  } 
  else {                                         //NoTrack not writing to CSV File
    echo '<a href="./config.php?v=sites"><div class="home-nav"><h2>Block List</h2><hr /><span>'.number_format(floatval(exec('notrack --count'))).'<br />Domains</span><div class="icon-box"><img src="./svg/home_trackers.svg" alt=""></div></div></a>'.PHP_EOL;
  }
}
else {                                           //Block List missing
  echo '<a href="./config.php?v=sites"><div class="home-nav"><h2>Block List</h2><hr /><h6>File Not Found</h6><div class="icon-box"><img src="./svg/home_trackers.svg" alt=""></div></div></a>'.PHP_EOL;
}

//Sites Blocked
echo '<a href="./blocked.php"><div class="home-nav"><h2>Sites Blocked</h2><hr /><span>'.number_format(floatval(exec('grep -v admin /var/log/lighttpd/access.log | wc -l'))).'<br />This Week</span><div class="icon-box"><img src="./svg/home_blocked.svg" alt=""></div></div></a>'.PHP_EOL;

//DNS Queries
echo '<a href="./stats.php"><div class="home-nav"><h2>DNS Queries</h2><hr /><span>'.number_format(floatval(exec('grep -F query[A] /var/log/notrack.log | wc -l'))).'<br />Today</span><div class="icon-box"><img src="./svg/home_server.svg" srcset="./svg/home_server.svg"  alt=""></div></div></a>'.PHP_EOL;

if (file_exists('/var/lib/misc/dnsmasq.leases')) { //DHCP Active
  echo '<a href="./dhcpleases.php"><div class="home-nav"><h2>DHCP</h2><hr /><span>'.number_format(floatval(exec('wc -l /var/lib/misc/dnsmasq.leases | cut -d\  -f 1'))).'<br />Systems</span><div class="icon-box"><img src="./svg/home_dhcp.svg" alt=""></div></div></a>'.PHP_EOL;
}
else {                                           //DHCP Disabled
  echo '<a href="./dhcpleases.php"><div class="home-nav"><h2>DHCP</h2><hr /><span>N/A</span><div class="icon-box"><img class="full" src="./svg/home_dhcp.svg" alt=""></div></div></a>'.PHP_EOL;
}


/*echo '<div class="row-mobile">';                 //Row visible for Mobiles
echo '<a href="./config.php"><div class="home-nav-p"><h2>Config</h2><div class="home-nav-left">&nbsp;</div><div class="home-nav-right"><img class="full" src="./svg/home_config.svg" alt=""></div></div></a>'.PHP_EOL;
*/
echo '</div>'.PHP_EOL;
echo '<div class="row"><br /></div>'.PHP_EOL;

if (($Version != $Config['LatestVersion']) && Check_Version($Config['LatestVersion'])) {      //See if upgrade Needed
  DrawSysTable('Upgrade');
  echo '<p>New version available: v'.$Config['LatestVersion'].'&nbsp;&nbsp;<a class="button-grey" href="./upgrade.php">Upgrade</a></p>';        
  echo '</table></div></div>'.PHP_EOL;
}

?>
</div>
</body>
</html>

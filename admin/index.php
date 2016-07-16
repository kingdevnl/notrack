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
echo '<div id="top-padding"></div>';
echo '<div class="row">';

//Tracker Blocklist
echo '<a href="./config.php?v=sites"><div class="home-nav-r"><h2>Tracker Blocklist</h2><div class="home-nav-left"><h3>'.number_format(floatval(exec('grep -c Active '. $CSVBlocking))).'</h3><h4>Domains</h4></div><div class="home-nav-right"><img class="full" src="./svg/home_trackers.svg" alt=""></div></div></a>'.PHP_EOL;

//Sites Blocked
echo '<a href="./blocked.php"><div class="home-nav-b"><h2>Sites Blocked</h2><div class="home-nav-left"><h3>'.number_format(floatval(exec('grep -v admin /var/log/lighttpd/access.log | wc -l'))).'</h3><h4>This Week</h4></div><div class="home-nav-right"><img class="full" src="./svg/home_blocked.svg" alt=""></div></div></a>'.PHP_EOL;

//DNS Queries
echo '<a href="./stats.php"><div class="home-nav-g"><h2>DNS Queries</h2><div class="home-nav-left"><h3>'.number_format(floatval(exec('grep -F query[A] /var/log/notrack.log | wc -l'))).'</h3><h4>Today</h4></div><div class="home-nav-right"><img class="full" src="./svg/home_server.svg" srcset="./svg/home_server.svg"  alt=""></div></div></a>'.PHP_EOL;

//DHCP Systems
if (file_exists('/var/lib/misc/dnsmasq.leases')) {
  echo '<a href="./dhcpleases.php"><div class="home-nav-y"><h2>DHCP</h2><div class="home-nav-left"><h3>'.number_format(floatval(exec('wc -l /var/lib/misc/dnsmasq.leases | cut -d\  -f 1'))).'</h3><h4>Systems</h4></div><div class="home-nav-right"><img class="full" src="./svg/home_dhcp.svg" alt=""></div></div></a>'.PHP_EOL;
}
else {
  echo '<a href="./dhcpleases.php"><div class="home-nav-y"><h2>DHCP</h2><div class="home-nav-left"><h3>N/A</h3></div><div class="home-nav-right"><img class="full" src="./svg/home_dhcp.svg" alt=""></div></div></a>'.PHP_EOL;
}
echo '</div>'.PHP_EOL;

echo '<div class="row-mobile">';
echo '<a href="./config.php"><div class="home-nav-p"><h2>Config</h2><div class="home-nav-left">&nbsp;</div><div class="home-nav-right"><img class="full" src="./svg/home_config.svg" alt=""></div></div></a>'.PHP_EOL;

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

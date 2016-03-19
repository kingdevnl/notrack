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
echo '<div class="row"><br />';

//Tracker Blocklist
echo '<a href="./config.php?v=sites"><div class="home-nav-r"><h2>Tracker Blocklist</h2><div class="home-nav-left"><h3>'.number_format(floatval(exec('cat '.$FileBlockingCSV.' | grep -c Active'))).'</h3><h4>Domains</h4></div><div class="home-nav-right"><img class="full" src="./images/magnifying_glass.png" alt=""></div></div></a>'.PHP_EOL;

//TLD Blocklist
if ($Config['BlockList_TLD'] == 1) {
  echo '<a href="./config.php?v=tldblack"><div class="home-nav-b"><h2>TLD Blocklist</h2><div class="home-nav-left"><h3>'.number_format(floatval(exec('wc -l /etc/notrack/domain-quick.list | cut -d\  -f 1'))).'</h3><h4>Domains</h4></div><div class="home-nav-right"><img class="full" src="./images/globe.png" alt=""></div></div></a>'.PHP_EOL;
}
else {
  echo '<a href="./config.php"><div class="home-nav-b"><h2>TLD Blocklist</h2><div class="home-nav-left"><br /><h4>Disabled</h4></div><div class="home-nav-right"><img class="full" src="./images/globe.png" alt=""></div></div></a>'.PHP_EOL;
}

//DNS Queries
echo '<a href="./stats.php"><div class="home-nav-g"><h2>DNS Queries</h2><div class="home-nav-left"><h3>'.number_format(floatval(exec('cat /var/log/notrack.log | grep -F query[A] | wc -l'))).'</h3><h4>Today</h4></div><div class="home-nav-right"><img class="full" src="./images/home_server.png" srcset="./svg/home_server.svg"  alt=""></div></div></a>'.PHP_EOL;

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

if ($Version != $Config['LatestVersion']) {      //See if upgrade Needed
  DrawSysTable('Upgrade');
  echo '<p>New version available: v'.$Config['LatestVersion'].'&nbsp;&nbsp;<a class="button-grey" href="./upgrade.php">Upgrade</a></p>';        
  echo '</table></div></div>'.PHP_EOL;
}

?>
</div>
</body>
</html>

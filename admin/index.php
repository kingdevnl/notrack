<?php
require('./include/global-vars.php');
require('./include/global-functions.php');
require('./include/menu.php');

load_config();
ensure_active_session();
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
<?php

action_topmenu();
draw_topmenu();
draw_sidemenu();

/************************************************
*Constants                                      *
************************************************/
define('QRY_BLOCKLIST', 'SELECT COUNT(*) FROM blocklist');
define('QRY_DNSQUERIES', 'SELECT COUNT(*) FROM live');
define('QRY_LIGHTY', 'SELECT COUNT(*) FROM lightyaccess WHERE log_time BETWEEN (CURDATE() - INTERVAL 7 DAY) AND CURDATE()');

/************************************************
*Global Variables                               *
************************************************/
$db = new mysqli(SERVERNAME, USERNAME, PASSWORD, DBNAME);


/********************************************************************
 *  Block List Box
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_blocklistbox() {
  $rows = 0;
  
  exec('pgrep notrack', $pids);
  if(empty($pids)) {
    $rows = count_rows(QRY_BLOCKLIST); 
    echo '<a href="./config.php?v=full"><div class="home-nav"><h2>Block List</h2><hr /><span>'.number_format(floatval($rows)).'<br />Domains</span><div class="icon-box"><img src="./svg/home_trackers.svg" alt=""></div></div></a>'.PHP_EOL;
  }
  else {    
    echo '<a href="./config.php?v=full"><div class="home-nav"><h2>Block List</h2><hr /><span>Processing</span><div class="icon-box"><img src="./svg/home_trackers.svg" alt=""></div></div></a>'.PHP_EOL;
  }  
}


/********************************************************************
 *  DNS Queries Box
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_dhcpbox() {
  if (file_exists('/var/lib/misc/dnsmasq.leases')) { //DHCP Active
    echo '<a href="./dhcpleases.php"><div class="home-nav"><h2>Network</h2><hr /><span>'.number_format(floatval(exec('wc -l /var/lib/misc/dnsmasq.leases | cut -d\  -f 1'))).'<br />Systems</span><div class="icon-box"><img src="./svg/home_dhcp.svg" alt=""></div></div></a>'.PHP_EOL;
  }
  else {                                           //DHCP Disabled
    echo '<a href="./dhcpleases.php"><div class="home-nav"><h2>Network</h2><hr /><span>DHCP Disabled</span><div class="icon-box"><img class="full" src="./svg/home_dhcp.svg" alt=""></div></div></a>'.PHP_EOL;
  }  
}


/********************************************************************
 *  DNS Queries Box
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_queriesbox() {
  $rows = 0;
  
  $rows = count_rows(QRY_DNSQUERIES);
  
  echo '<a href="./queries.php"><div class="home-nav"><h2>DNS Queries</h2><hr /><span>'.number_format(floatval($rows)).'<br />Today</span><div class="icon-box"><img src="./svg/home_queries.svg" srcset="./svg/home_queries.svg" alt=""></div></div></a>'.PHP_EOL;
}


/********************************************************************
 *  Status Box
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_statusbox() {
  global $Config;

  $currenttime = time();
  $date_msg = '';
  $date_submsg = '<h2>Block list is in date</h2>';
  $filemtime = 0;
  $status_msg = '';
  
  if (substr($Config['Status'], 0, 6) == 'Paused') {
    $status_msg = '<h4>Paused</h4>';
    $date_msg = '<h2>---</h2>';
    $date_submsg = '';
  }
  elseif ($Config['Status'] == 'Stop') {
    $status_msg = '<h6>Disabled</h6>';
    $date_msg = '<h2>---</h2>';
    $date_submsg = '';
  }
  else {
    $status_msg = '<h3>Active</h3>';
  }
  
  if (file_exists(BL_NOTRACK)) {                 //Does the notrack.list file exist?
    $filemtime = filemtime(BL_NOTRACK);      //Get last modified time
    if ($filemtime > $currenttime - 86400) $date_msg = '<h3>Today</h3>';
    elseif ($filemtime > $currenttime - 172800) $date_msg = '<h3>Yesterday</h3>';
    elseif ($filemtime > $currenttime - 259200) $date_msg = '<h3>3 Days ago</h3>';
    elseif ($filemtime > $currenttime - 345600) $date_msg = '<h3>4 Days ago</h3>';
    elseif ($filemtime > $currenttime - 432000) {  //5 days onwards is getting stale
      $date_msg = '<h4>5 Days ago</h4>';
      $date_submsg = '<h2>Block list is old</h2>';
    }
    elseif ($filemtime > $currenttime - 518400) {
      $date_msg = '<h4>6 Days ago</h4>';
      $date_submsg = '<h2>Block list is old</h2>';
    }
    elseif ($filemtime > $currenttime - 1209600) {
      $date_msg = '<h4>Last Week</h4>';
      $date_submsg = '<h2>Block list is old</h2>';
    }
    else {                                       //Beyond 2 weeks is too old
      $date_msg = '<h6>'.date('d M', $filemtime).'</h6>';
      $date_submsg = '<h6>Out of date</h6>';
    }
  }  
  else {
    if ($status_msg == '<h3>Active</h3>') {
      $status_msg = '<h6>Block List Missing</h6>';
      $date_msg = '<h6>Unknown</h6>';
    }
  }

  echo '<a href="#"><div class="home-nav"><h2>Status</h2><hr /><br />'.$status_msg.'</div></a>'.PHP_EOL;
  echo '<a href="#"><div class="home-nav"><h2>Last Updated</h2><hr /><br />'.$date_msg.$date_submsg.'</div></a>'.PHP_EOL;
}


/********************************************************************
 *  Sites Blocked Box
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_sitesblockedbox() {
  $rows = 0;
  
  $rows = count_rows(QRY_LIGHTY);

  echo '<a href="./blocked.php"><div class="home-nav"><h2>Sites Blocked</h2><hr /><span>'.number_format(floatval($rows)).'<br />This Week</span><div class="icon-box"><img src="./svg/home_blocked.svg" alt=""></div></div></a>'.PHP_EOL;
}


//Main---------------------------------------------------------------
echo '<div id="main">';
echo '<div class="home-nav-container">';

draw_statusbox();
draw_blocklistbox();
draw_sitesblockedbox();
draw_queriesbox();
draw_dhcpbox();

echo '</div>'.PHP_EOL;
echo '<div class="sys-group"><p>Welcome to the new version of NoTrack v0.8 with a SQL back-end.</p><p>Performance will now be significantly improved reviewing DNS Queries made, and searching Blocked sites list</p>'.PHP_EOL;
echo '<div class="row"><p>At this point in time the front-end web interface replicates what you have seen in previous versions, but the new back-end will allow for some fancy effects like graphs showing the most persistant trackers your systems have encountered, and the ability to highlight if one of your systems attempted to visit a malicious site</p>'.PHP_EOL;
echo '<p>Historic DNS logs are no longer available because I stripped Log Time, and System Request out of the files to save on processing power. That information is now stored in the Historic database, unfortunately with the data gone it would mean either spoofing missing data or leaving it alone. You can still access the original files at <code>/var/logs/notrack</code></p></div>'.PHP_EOL;
echo '<div class="row"><br /></div>'.PHP_EOL;

//Is an upgrade Needed?
if ((VERSION != $Config['LatestVersion']) && check_version($Config['LatestVersion'])) {      
  draw_systable('Upgrade');
  echo '<p>New version available: v'.$Config['LatestVersion'].'&nbsp;&nbsp;<a class="button-grey" href="./upgrade.php">Upgrade</a></p>';        
  echo '</table></div></div>'.PHP_EOL;
}

$db->close();
?>
</div>
</body>
</html>

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
  <link href="./css/chart.css" rel="stylesheet" type="text/css" />
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
define('QRY_LIGHTY', 'SELECT COUNT(*) FROM lightyaccess WHERE log_time BETWEEN (CURDATE() - INTERVAL 7 DAY) AND NOW()');

$CHARTCOLOURS = array('#008CD1', '#B1244A', '#00AA00');

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
  global $CHARTCOLOURS;

  $total = 0;
  $allowed = 0;
  $blocked = 0;
  $local = 0;
  $chartdata = array();
  
  $total = count_rows(QRY_DNSQUERIES);
  $local = count_rows('SELECT COUNT(*) FROM live WHERE dns_result = \'l\'');
  $blocked = count_rows('SELECT COUNT(*) FROM live WHERE dns_result = \'b\'');
  $allowed = $total - $blocked - $local;
  
  if ($local == 0) {
    $chartdata = array($allowed, $blocked);
  }
  else {
    $chartdata = array($allowed, $blocked, $local);
  }
  
  echo '<a href="./queries.php"><div class="home-nav"><h2>DNS Queries</h2><hr /><span>'.number_format(floatval($total)).'<br />Today'.PHP_EOL;
  echo '<svg width="20em" height="3em" overflow="visible">'.PHP_EOL;
  echo '<text x="0" y="2em" style="font-family: Arial; font-size: 0.58em; fill:'.$CHARTCOLOURS[0].'">'.number_format(floatval(($allowed/$total)*100)).'% Allowed</text>'.PHP_EOL;
  echo '<text x="6.4em" y="2em" style="font-family: Arial; font-size: 0.58em; fill:'.$CHARTCOLOURS[1].'">'.number_format(floatval(($blocked/$total)*100)).'% Blocked</text>'.PHP_EOL;
  if ($local > 0) {
    echo '<text x="0" y="3.3em" style="font-family: Arial; font-size: 0.58em; fill:'.$CHARTCOLOURS[2].'">'.number_format(floatval(($local/$total)*100)).'% Local</text>'.PHP_EOL;
  }
  echo '</svg></span>';
  
  echo '<div class="chart-box">'.PHP_EOL;
  echo '<svg width="100%" height="90%" viewbox="0 0 200 200">'.PHP_EOL;
  echo piechart($chartdata, 100, 100, 98, $CHARTCOLOURS);
  echo '<circle cx="100" cy="100" r="30" stroke="#00000A" stroke-width="2" fill="#f7f7f7" />'.PHP_EOL;
  echo '</svg>'.PHP_EOL;
  //<img src="./svg/home_queries.svg" srcset="./svg/home_queries.svg" alt="">
  echo '</div></div></a>'.PHP_EOL;
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
    $filemtime = filemtime(BL_NOTRACK);          //Get last modified time
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


/********************************************************************
 *  Traffic Graph
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_trafficgraph() {
  global $db;
  $allowed_values = array();
  $blocked_values = array();
  $xlabels = array();
  $max_value = 0;
  $ymax = 0;
  $xstep = 0;
  $pathout = '';
  $numvalues = 0;
  $x = 0;
  $y = 850;
  
  //Get allowed values
  $query = 'SELECT HOUR(log_time) AS hour, COUNT(*) AS num_rows FROM live WHERE dns_result = \'a\' GROUP BY HOUR(log_time) ';
  
  if(!$result = $db->query($query)){
    die('There was an error running the query'.$db->error);
  }
  while($row = $result->fetch_assoc()) {         //Read each row of results  
    $allowed_values[] = $row['num_rows'];
    $xlabels[] = $row['hour'];
  }
  
  $result->free();
  
  //Get blocked values
  $query = 'SELECT HOUR(log_time) AS hour, COUNT(*) AS num_rows FROM live WHERE dns_result = \'b\' GROUP BY HOUR(log_time) ';
  
  if(!$result = $db->query($query)){
    die('There was an error running the query'.$db->error);
  }
  while($row = $result->fetch_assoc()) {         //Read each row of results  
    $blocked_values[] = $row['num_rows'];    
  }
  
  $result->free();
  
  //Prepare chart
  $max_value = max(array(max($allowed_values), max($blocked_values)));
  $numvalues = count($allowed_values);
  $allowed_values[] = 0;                         //Ensure line returns to 0
  $blocked_values[] = 0;                         //Ensure line returns to 0
  $xlabels[] = $xlabels[$numvalues-1] + 1;       //Increment xlables
  
  $xstep = 1900 / 24;                            //Calculate x axis increment
  if ($max_value < 200) {                        //Calculate y axis maximum
    $ymax = (ceil($max_value / 10) * 10) + 10;
  }
  elseif ($max_value < 10000) {
    $ymax = ceil($max_value / 100) * 100;
  }
  else {
    $ymax = ceil($max_value / 1000) * 1000;
  }
    
  echo '<svg width="100%" height="90%" viewbox="0 0 2000 910" class="shadow">'.PHP_EOL;
  echo '<rect x="1" y="1" width="1998" height="908" rx="5" ry="5" fill="#f7f7f7" stroke="#B3B3B3" stroke-width="2px" opacity="1" />'.PHP_EOL;
  echo '<path class="axisline" d="M100,0 V850 H2000 " />';
  
  for ($i = 0.25; $i < 1; $i+=0.25) {            //Y Axis lines and labels
    echo '<path class="gridline" d="M100,'.($i*850).' H2000" />'.PHP_EOL;
    echo '<text class="axistext" x="8" y="'.(18+($i*850)).'">'.formatnumber((1-$i)*$ymax).'</text>'.PHP_EOL;
  }
  echo '<text x="8" y="855" class="axistext">0</text>';
  echo '<text x="8" y="38" class="axistext">'.formatnumber($ymax).'</text>';
  
  
  for ($i = 0; $i < $numvalues; $i+=2) {         //X Axis labels
    echo '<text x="'.(55+($i * $xstep)).'" y="898" class="axistext">'.$xlabels[$i].':00</text>'.PHP_EOL;
  }  
  
  for ($i = 2; $i < 24; $i+=2) {                 //X Grid lines
    echo '<path class="gridline" d="M'.(100+($i*$xstep)).',2 V850" />'.PHP_EOL;
  }
  
  $pathout = "<path d=\"M 100,850 ";             //Blue line for allowed
  for ($i = 1; $i < $numvalues; $i++) {
    $pathout .= calc_curve($allowed_values[$i-1], $allowed_values[$i], $allowed_values[$i+1], 100+(($i) * $xstep), $xstep, $ymax, '#008CD1');    
  }
  $pathout .= 'V850 " stroke="#008CD1" stroke-width="3px" fill="#00AEFF" fill-opacity="0.15" />'.PHP_EOL;
  echo $pathout;
  
  $pathout = "<path d=\"M 100,850 ";             //Red line for blocked
  for ($i = 1; $i < $numvalues; $i++) {
    if (! isset($blocked_values[$i+1])) {        //Check for zero blocked sites in next array value
      $blocked_values[] = 0;                     //Add zero to prevent warning
    }
    $pathout .= calc_curve($blocked_values[$i-1], $blocked_values[$i], $blocked_values[$i+1], 100+(($i) * $xstep), $xstep, $ymax, '#B1244A');    
  }
  $pathout .= 'V850 " stroke="#B1244A" stroke-width="3px" fill="#FF346D" fill-opacity="0.15" />'.PHP_EOL;
  echo $pathout;
  
  echo '</svg>'.PHP_EOL;                         //End SVG  

}

/********************************************************************
 *  Calculate Curve
 *    Calculates smooth bezier curve with 4 node points at -42%, -15%, +15%, +42%
 *
 *  Params:
 *    [$i-1][$i][$i+1], column position, column width, column height, colour
 *  Return:
 *    svg path nodes with bezier curve points
 */

function calc_curve($old, $cur, $new, $xpos, $xstep, $ymax, $colour) {
  $pathout = '';
  $x = 0;                                        //Node X
  $y = 0;                                        //Node Y
  $dx1 = 0;                                      //Bezier curve left X
  $dx2 = 0;                                      //Bezier curve left Y
  $dy1 = 0;                                      //Bezier curve right X
  $dy2 = 0;                                      //Bezier curve right Y    
  $diff = 0;                                     //Difference between values
  
  $diff = $cur - $old;                           //Left-hand slope
  
  if ($cur > 0) {
    echo '<circle cx="'.$xpos.'" cy="'.(850-($cur/$ymax)*850).'" r="6" fill="'.$colour.'" fill-opacity="0.8" />'.PHP_EOL;
  }
  
  $dx1 = $xpos - ($xstep * 0.5);
  $dy1 = 850-((($cur - ($diff * 0.49))/$ymax)*850);
  $x = $xpos - ($xstep * 0.45);
  $y = 850-((($cur - ($diff * 0.42))/$ymax)*850);
  $dx2 = $xpos - ($xstep * 0.35);
  $dy2 = 850-((($cur - ($diff * 0.2))/$ymax)*850);
  $pathout .= "C $x $y, $dx1 $dy1, $dx2 $dy2 ";
    
  $dx1 = $xpos - ($xstep * 0.25);
  $dy1 = 850-((($cur - ($diff * 0.1))/$ymax)*850);
  $x = $xpos - ($xstep * 0.15);
  $y = 850-((($cur - ($diff * 0.005))/$ymax)*850);
  $dx2 = $xpos - ($xstep * 0.1);
  $dy2 = 850-((($cur - ($diff * 0.001))/$ymax)*850);
  $pathout .= "C $x $y, $dx1 $dy1, $dx2 $dy2 ";
    
  $diff = $new - $cur;                           //Right-hand slope
    
  $dx1 = $xpos + ($xstep * 0.1);
  $dy1 = 850-((($cur + ($diff * 0.001))/$ymax)*850);
  $x = $xpos + ($xstep * 0.15);
  $y = 850-((($cur + ($diff * 0.005))/$ymax)*850);    
  $dx2 = $xpos + ($xstep * 0.25);
  $dy2 = 850-((($cur + ($diff * 0.1))/$ymax)*850);
  $pathout .= "C $x $y, $dx1 $dy1, $dx2 $dy2 ";
    
  $dx1 = $xpos + ($xstep * 0.35);
  $dy1 = 850-((($cur + ($diff * 0.2))/$ymax)*850);
  $x = $xpos + ($xstep * 0.44);
  $y = 850-((($cur + ($diff * 0.42))/$ymax)*850);
  $dx2 = $xpos + ($xstep * 0.5);
  $dy2 = 850-((($cur + ($diff * 0.49))/$ymax)*850);
  $pathout .= "C $x $y, $dx1 $dy1, $dx2 $dy2 ";
    
  return $pathout;
}

//Main---------------------------------------------------------------
echo '<div id="main">';
echo '<div class="home-nav-container">';

draw_statusbox();
draw_blocklistbox();
draw_sitesblockedbox();
draw_queriesbox();
draw_dhcpbox();

draw_trafficgraph();

echo '</div>'.PHP_EOL;
/*echo '<div class="sys-group"><p>Welcome to the new version of NoTrack v0.8 with a SQL back-end.</p><p>Performance will now be significantly improved reviewing DNS Queries made, and searching Blocked sites list</p>'.PHP_EOL;
echo '<p>At this point in time the front-end web interface replicates what you have seen in previous versions, but the new back-end will allow for some fancy effects like graphs showing the most persistant trackers your systems have encountered, and the ability to highlight if one of your systems attempted to visit a malicious site</p>'.PHP_EOL;
echo '<p>Historic DNS logs are no longer available because I stripped Log Time, and System Request out of the files to save on processing power. That information is now stored in the Historic database, unfortunately with the data gone it would mean either spoofing missing data or leaving it alone. You can still access the original files at <code>/var/logs/notrack</code></p></div>'.PHP_EOL;
//echo '<div class="row"><br /></div>'.PHP_EOL;*/

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

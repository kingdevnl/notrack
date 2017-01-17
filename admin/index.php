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
  <meta charset="UTF-8">
  <link href="./css/master.css" rel="stylesheet" type="text/css">
  <link href="./css/chart.css" rel="stylesheet" type="text/css">
  <link rel="icon" type="image/png" href="./favicon.png">
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
    echo '<a href="./config.php?v=full"><div class="home-nav"><h2>Block List</h2><hr><span>'.number_format(floatval($rows)).'<br>Domains</span><div class="icon-box"><img src="./svg/home_trackers.svg" alt=""></div></div></a>'.PHP_EOL;
  }
  else {    
    echo '<a href="./config.php?v=full"><div class="home-nav"><h2>Block List</h2><hr><span>Processing</span><div class="icon-box"><img src="./svg/home_trackers.svg" alt=""></div></div></a>'.PHP_EOL;
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
    echo '<a href="./dhcpleases.php"><div class="home-nav"><h2>Network</h2><hr><span>'.number_format(floatval(exec('wc -l /var/lib/misc/dnsmasq.leases | cut -d\  -f 1'))).'<br>Systems</span><div class="icon-box"><img src="./svg/home_dhcp.svg" alt=""></div></div></a>'.PHP_EOL;
  }
  else {                                           //DHCP Disabled
    echo '<a href="./dhcpleases.php"><div class="home-nav"><h2>Network</h2><hr><span>DHCP Disabled</span><div class="icon-box"><img class="full" src="./svg/home_dhcp.svg" alt=""></div></div></a>'.PHP_EOL;
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
  
  echo '<a href="./queries.php"><div class="home-nav"><h2>DNS Queries</h2><hr><span>'.number_format(floatval($total)).'<br>Today'.PHP_EOL;
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

  echo '<a href="#"><div class="home-nav"><h2>Status</h2><hr><br>'.$status_msg.'</div></a>'.PHP_EOL;
  echo '<a href="#"><div class="home-nav"><h2>Last Updated</h2><hr><br>'.$date_msg.$date_submsg.'</div></a>'.PHP_EOL;
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

  echo '<a href="./blocked.php"><div class="home-nav"><h2>Sites Blocked</h2><hr><span>'.number_format(floatval($rows)).'<br>This Week</span><div class="icon-box"><img src="./svg/home_blocked.svg" alt=""></div></div></a>'.PHP_EOL;
}


/********************************************************************
 *  Traffic Graph
 *    1. Load data from live table for values per hour
 *    2. Calulate maximum values of SQL data for $ymax
 *    3. Draw grid lines
 *    4. Draw axis labels
 *    5. Draw coloured graph lines
 *    6. Draw coloured circles to reduce sharpness of graph line
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function trafficgraph() {
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
  $y = 0;
  
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
  
  draw_graphline($allowed_values, $xstep, $ymax, '#008CD1');
  draw_graphline($blocked_values, $xstep, $ymax, '#B1244A');
  draw_circles($allowed_values, $xstep, $ymax, '#008CD1');
  draw_circles($blocked_values, $xstep, $ymax, '#B1244A');

  echo '<path class="axisline" d="M100,0 V850 H2000 " />';  //X and Y Axis line
  echo '</svg>'.PHP_EOL;                         //End SVG  

}

/********************************************************************
 *  Draw Graph Line
 *    Calulates and draws the graph line using straight point-to-point notes
 *
 *  Params:
 *    $values array, x step, y maximum value, line colour
 *  Return:
 *    svg path nodes with bezier curve points
 */

function draw_graphline($values, $xstep, $ymax, $colour) {
  $path = '';
  $x = 0;                                        //Node X
  $y = 0;                                        //Node Y
  $numvalues = count($values);
  
  $path = "<path d=\"M 100,850 ";
  for ($i = 1; $i < $numvalues; $i++) {
    $x = 100 + (($i) * $xstep);
    $y = 850 - (($values[$i] / $ymax) * 850);
    $path .= "L $x $y";
  }
  $path .= 'V850 " stroke="'.$colour.'" stroke-width="5px" fill="'.$colour.'" fill-opacity="0.15" />'.PHP_EOL;
  echo $path;  
}

/********************************************************************
 *  Draw Circle Points
 *    Draws circle shapes where line node points are, in order to reduce sharpness of graph line
 *
 *  Params:
 *    $values array, x step, y maximum value, line colour
 *  Return:
 *    svg path nodes with bezier curve points
 */
function draw_circles($values, $xstep, $ymax, $colour) {
  $path = '';
  $x = 0;                                        //Node X
  $y = 0;                                        //Node Y
  $numvalues = count($values);
    
  for ($i = 1; $i < $numvalues; $i++) {
    if ($values[$i] > 0) {
      $x = 100 + (($i) * $xstep);
      $y = 850 - (($values[$i] / $ymax) * 850);    
    
      echo '<circle cx="'.$x.'" cy="'.(850-($values[$i]/$ymax)*850).'" r="10px" fill="'.$colour.'" fill-opacity="1" stroke="#F7F7F7" stroke-width="5px" />'.PHP_EOL;
    }    
  }  
}


//Main---------------------------------------------------------------
echo '<div id="main">';
echo '<div class="home-nav-container">';

draw_statusbox();
draw_blocklistbox();
draw_sitesblockedbox();
draw_queriesbox();
draw_dhcpbox();

trafficgraph();

echo '</div>'.PHP_EOL;

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

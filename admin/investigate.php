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
  <link rel="icon" type="image/png" href="./favicon.png">
  <script src="./include/menu.js"></script>
  <script src="./include/queries.js"></script>
  <title>NoTrack - Investigate</title>
</head>

<body>
<?php
action_topmenu();
draw_topmenu();
draw_sidemenu();
echo '<div id="main">'.PHP_EOL;

/************************************************
*Constants                                      *
************************************************/
DEFINE('DEF_FILTER', 'all');
DEFINE('DEF_SYSTEM', 'all');
DEFINE('DEF_SDATE', date("Y-m-d", time() - 172800));  //Start Date of Historic -2d
DEFINE('DEF_EDATE', date("Y-m-d", time() - 86400));   //End Date of Historic   -1d

$FILTERLIST = array('all' => 'All Requests',
                    'allowed' => 'Allowed Only',
                    'blocked' => 'Blocked Only',
                    'local' => 'Local Only');

$VIEWLIST = array('livegroup', 'livetime', 'historicgroup', 'historictime');

$COMMONSITESLIST = array('cloudfront.net',
                         'googleusercontent.com',
                         'googlevideo.com',
                         'cedexis-radar.net',
                         'gvt1.com',
                         'deviantart.net',
                         'deviantart.com',
                         'ampproject.net',
                         'tumblr.com');
//CommonSites referres to websites that have a lot of subdomains which aren't necessarily relivent. In order to improve user experience we'll replace the subdomain of these sites with "*"

/************************************************
*Global Variables                               *
************************************************/
$datetime = '';
$site = 'quidsup.net';

/************************************************
*Arrays                                         *
************************************************/
$syslist = array();
$TLDBlockList = array();
$CommonSites = array();                          //Merge Common sites list with Users Suppress list


/********************************************************************
 *  Add Date Vars to SQL Search
 *
 *  Params:
 *    None
 *  Return:
 *    SQL Query string
 */
function add_datestr() {
  global $sqltable, $filter, $sys, $datestart, $dateend;
  
  if ($sqltable == 'live') return '';
  
  $searchstr = ' WHERE ';
  if (($filter != DEF_FILTER) || ($sys != DEF_SYSTEM)) $searchstr = ' AND ';
  
  $searchstr .= 'log_time BETWEEN \''.$datestart.'\' AND \''.$dateend.' 23:59\'';
  
  return $searchstr;
}


/********************************************************************
 *  Add Filter Vars to SQL Search
 *
 *  Params:
 *    None
 *  Return:
 *    SQL Query string
 */
function add_filterstr() {
  global $filter, $sys;
  
  $searchstr = " WHERE ";
  
  if (($filter == DEF_FILTER) && ($sys == DEF_SYSTEM)) {   //Nothing to add
    return '';
  }
  
  if ($sys != DEF_SYSTEM) {
    $searchstr .= "sys = '$sys'";
  }
  if ($filter != DEF_FILTER) {
    if ($sys != DEF_SYSTEM) {
      $searchstr .= " AND dns_result=";
    }    
    else {
      $searchstr .= " dns_result=";
    }    
    
    switch($filter) {
      case 'allowed':
        $searchstr .= "'a'";
        break;
      case 'blocked':
        $searchstr .= "'b'";
        break;
      case 'local':
        $searchstr .= "'l'";
        break;
    }
  }
  return $searchstr;        
}


/********************************************************************
 *  Count rows in table and save result to memcache
 *  
 *  1. Attempt to load value from Memcache
 *  2. Check if same query is being run
 *  3. If that fails then run query
 *
 *  Params:
 *    Query String
 *  Return:
 *    Number of Rows
 */
function count_rows_save($query) {
  global $db, $mem;
  
  $rows = 0;
  
  if ($mem->get('rows')) {                       //Does rows exist in memcache?
    if ($query == $mem->get('oldquery')) {       //Is this query same as old query?
      $rows = $mem->get('rows');                 //Use stored value      
      return $rows;
    }
  }
  
  if(!$result = $db->query($query)){
    die('There was an error running the query '.$db->error);
  }
  
  $rows = $result->fetch_row()[0];               //Extract value from array
  $result->free();    
  $mem->set('oldquery', $query, 0, 600);         //Save for 10 Mins
      
  return $rows;
}


/********************************************************************
 *  Draw Filter Box
 *  
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_filterbox() {
  global $FILTERLIST, $syslist, $filter, $page, $sqltable, $sort, $sys, $view;
  global $datestart, $dateend;
  
  $hidden_date_vars = '';
  $line = '';
  
  if ($sqltable == 'historic') {
    $hidden_date_vars = '<input type="hidden" name="datestart" value="'.$datestart.'"><input type="hidden" name="dateend" value="'.$dateend.'">'.PHP_EOL;
  }
  
  echo '<div class="sys-group">'.PHP_EOL;
  echo '<h5>DNS Queries</h5>'.PHP_EOL;
  echo '<div class="row"><div class="col-half">'.PHP_EOL;
  echo '<form method="get">'.PHP_EOL;
  echo '<input type="hidden" name="page" value="'.$page.'">'.PHP_EOL;
  echo '<input type="hidden" name="view" value="'.$view.'">'.PHP_EOL;
  echo '<input type="hidden" name="filter" value="'.$filter.'">'.PHP_EOL;
  echo '<input type="hidden" name="sort" value="'.strtolower($sort).'">'.PHP_EOL;
  echo $hidden_date_vars;
  echo '<span class="filter">System:</span><select name="sys" onchange="submit()">';
    
  if ($sys == DEF_SYSTEM) {
    echo '<option value="all">All</option>'.PHP_EOL;
  }
  else {
    echo '<option value="1">'.$sys.'</option>'.PHP_EOL;
    echo '<option value="all">All</option>'.PHP_EOL;
  }
  foreach ($syslist as $line) {
    if ($line != $sys) echo '<option value="'.$line.'">'.$line.'</option>'.PHP_EOL;
  }
  echo '</select></form>'.PHP_EOL;
  echo '</div>'.PHP_EOL;
  
  echo '<div class="col-half">'.PHP_EOL;
  echo '<form method="get">'.PHP_EOL;
  echo '<input type="hidden" name="page" value="'.$page.'">'.PHP_EOL;
  echo '<input type="hidden" name="view" value="'.$view.'">'.PHP_EOL;
  echo '<input type="hidden" name="sort" value="'.strtolower($sort).'">'.PHP_EOL;
  echo '<input type="hidden" name="sys" value="'.$sys.'">'.PHP_EOL;
  echo $hidden_date_vars;
  echo '<span class="filter">Filter:</span><select name="filter" onchange="submit()">';
  echo '<option value="'.$filter.'">'.$FILTERLIST[$filter].'</option>'.PHP_EOL;
  foreach ($FILTERLIST as $key => $line) {
    if ($key != $filter) echo '<option value="'.$key.'">'.$line.'</option>'.PHP_EOL;
  }
  echo '</select></form>'.PHP_EOL;
  echo '</div></div>'.PHP_EOL;
  
  if ($sqltable == 'historic') {
    echo '<div class="row">'.PHP_EOL;
    echo '<form method="get">'.PHP_EOL;
    echo '<input type="hidden" name="page" value="'.$page.'">'.PHP_EOL;
    echo '<input type="hidden" name="view" value="'.$view.'">'.PHP_EOL;
    echo '<input type="hidden" name="sort" value="'.strtolower($sort).'">'.PHP_EOL;
    echo '<input type="hidden" name="filter" value="'.$filter.'">'.PHP_EOL;
    echo '<input type="hidden" name="sys" value="'.$sys.'">'.PHP_EOL;
    echo '<div class="col-half">'.PHP_EOL;
    echo '<span class="filter">Start Date: </span><input name="datestart" type="date" value="'.$datestart.'" onchange="submit()"/>'.PHP_EOL;
    echo '</div>'.PHP_EOL;
    echo '<div class="col-half">'.PHP_EOL;
    echo '<span class="filter">End Date: </span><input name="dateend" type="date" value="'.$dateend.'" onchange="submit()"/>'.PHP_EOL;
    echo '</div>'.PHP_EOL;
    echo '</form>'.PHP_EOL;
    echo '</div>'.PHP_EOL;
  }
  
  echo '</div>'.PHP_EOL;
}



/********************************************************************
 *  Get Block List Name
 *    Returns the name of block list if it exists in the names array
 *  Params:
 *    $bl - bl_name
 *  Return:
 *    Full block list name
 */

function get_blocklistname($bl) {
  global $BLOCKLISTNAMES;
  
  if (array_key_exists($bl, $BLOCKLISTNAMES)) {
    return $BLOCKLISTNAMES[$bl];
  }
  
  return $bl;
}
/********************************************************************
 *  Search Block Reason
 *    1. Search $site in bl_source for Blocklist name
 *    2. Use regex match to extract (site).(tld)
 *    3. Search site.tld in bl_source
 *    4. On fail search for .tld in bl_source
 *    5. On fail return ''
 *
 *  Params:
 *    $site - Site to search
 *  Return:
 *    blocklist name
 */
function search_blockreason($site) {
  global $db;
  
  $result = $db->query('SELECT bl_source site FROM blocklist WHERE site = \''.$site.'\'');
  if ($result->num_rows > 0) {
    return $result->fetch_row()[0];
  }
  
    
  //Try to find LIKE site ending with site.tld
  if (preg_match('/([\w\d\-\_]+)\.([\w\d\-\_]+)$/', $site,  $matches) > 0) {
    $result = $db->query('SELECT bl_source site FROM blocklist WHERE site LIKE \'%'.$matches[1].'.'.$matches[2].'\'');

    if ($result->num_rows > 0) {
      return $result->fetch_row()[0];
    }    
    else {                                      //On fail try for site = .tld
      $result = $db->query('SELECT bl_source site FROM blocklist WHERE site = \'.'.$matches[2].'\'');
      if ($result->num_rows > 0) {
        return $result->fetch_row()[0];
      }
    }
  }
  
  return '';                                     //Don't know at this point    
}

//Need to ammend for historic view TODO
/********************************************************************
 *  Search Systems
 *  
 *  1. Find unique sys values in table
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function search_systems() {
  global $db, $mem, $syslist;
  
  $syslist = $mem->get('syslist');
  
  if (empty($syslist)) {
    if (! $result = $db->query("SELECT DISTINCT sys FROM live ORDER BY sys")) {
      die('There was an error running the query'.$db->error);
    }
    while($row = $result->fetch_assoc()) {       //Read each row of results
      $syslist[] = $row['sys'];                  //Add row value to $syslist
    }
    $result->free();
    $mem->set('syslist', $syslist, 0, 600);      //Save for 10 Mins
  }    
}


/********************************************************************
 *  Show Time View
 *    Show results in Time order
 *
 *  Params:
 *    None
 *  Return:
 *    false when nothing found, true on success
 */
function show_time_view() {
  global $db, $datetime, $site, $sys, $Config, $TLDBlockList;
    
  $rows = 0;
  $row_class = '';
  $query = '';
  $action = '';
  $blockreason = '';
  
  $query = "SELECT *, DATE_FORMAT(log_time, '%H:%i:%s') AS formatted_time FROM live WHERE sys = '$sys' AND log_time > SUBTIME('$datetime', '00:00:05') AND log_time < ADDTIME('$datetime', '00:00:03') ORDER BY UNIX_TIMESTAMP(log_time)";
  
    
  if(!$result = $db->query($query)){
    die('There was an error running the query'.$db->error);
  }
  
  if ($result->num_rows == 0) {                  //Leave if nothing found
    $result->free();
    echo "Nothing found for the selected dates";
    return false;
  }
  
  echo '<div class="sys-group">'.PHP_EOL;
  //draw_viewbuttons();
  
  echo '<table id="query-time-table">'.PHP_EOL;
  echo '<tr><th>Time</th><th>System</th><th>Site</th><th>Action</th></tr>'.PHP_EOL;  
  
  while($row = $result->fetch_assoc()) {         //Read each row of results
    $action = '<a target="_blank" href="'.$Config['SearchUrl'].$row['dns_request'].'"><img class="icon" src="./images/search_icon.png" alt="G" title="Search"></a>&nbsp;<a target="_blank" href="'.$Config['WhoIsUrl'].$row['dns_request'].'"><img class="icon" src="./images/whois_icon.png" alt="W" title="Whois"></a>&nbsp;';
    if ($row['dns_result'] == 'A') {             //Allowed
      $row_class='';
      $action .= '<span class="pointer"><img src="./images/report_icon.png" alt="Rep" title="Report Site" onclick="reportSite(\''.$row['dns_request'].'\', false, true)"></span>';
    }
    elseif ($row['dns_result'] == 'B') {         //Blocked
      $row_class = ' class="blocked"';      
      $blockreason = search_blockreason($row['dns_request']);      
      if ($blockreason == 'bl_notrack') {        //Show Report icon on NoTrack list
        $action .= '<span class="pointer"><img src="./images/report_icon.png" alt="Rep" title="Report Site" onclick="reportSite(\''.$row['dns_request'].'\', true, true)"></span>';
        $blockreason = '<p class="small">Blocked by NoTrack list</p>';
      }
      elseif ($blockreason == 'custom') {        //Users blacklist, show report icon
        $action .= '<span class="pointer"><img src="./images/report_icon.png" alt="Rep" title="Report Site" onclick="reportSite(\''.$row['dns_request'].'\', true, true)"></span>';
        $blockreason = '<p class="small">Blocked by Black list</p>';
      }
      elseif ($blockreason == '') {              //No reason is probably IP or Search request
        $row_class = ' class="invalid"';
        $blockreason = '<p class="small">Invalid request</p>';
      }
      else {
        $blockreason = '<p class="small">Blocked by '.get_blocklistname($blockreason).'</p>';
        $action .= '<span class="pointer"><img src="./images/report_icon.png" alt="Rep" title="Report Site" onclick="reportSite(\''.$row['dns_request'].'\', true, false)"></span>';
      }    
    }
    elseif ($row['dns_result'] == 'L') {         //Local
      $row_class = ' class="local"';
      $action = '&nbsp;';
    }
    
    if ($site == $row['dns_request']) {
      $row_class = ' class="cyan"';
    }
    
    echo '<tr'.$row_class.'><td>'.$row['formatted_time'].'</td><td>'.$row['sys'].'</td><td>'.$row['dns_request'].$blockreason.'</td><td>'.$action.'</td></tr>'.PHP_EOL;
    $blockreason = '';
  }
  
  echo '</table>'.PHP_EOL;
  echo '<br>'.PHP_EOL;
  echo '</div>'.PHP_EOL;
  
  $result->free();
  return true;
}

/********************************************************************
 *  Show Who Is Data
 *
 *  Params:
 *    URL to Query, Users API Key to jsonwhois
 *  Return:
 *    False on Fail, Array of WhoIs data on success
 */
function get_whoisdata($query, $apikey) {
  global $mem;

  $headers[] = 'Accept: application/json';
  $headers[] = 'Content-Type: application/json';
  $headers[] = 'Authorization: Token token='.$apikey;
  $url = 'https://jsonwhois.com/api/v1/whois/?domain='.$query;

  if ($mem->get('whois-'.$query)) {                        //Does Whois exist in memcache?
    $response = $mem->get('whois-'.$query);                //Use stored value    
    return $response;
  }
  
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $json_response = curl_exec($ch);

  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

  
  if ($status == 400) {                                    //Bad request domain doesn't exist
    echo '<div class="sys-group"><div class="sys-title">'.PHP_EOL;
    echo '<h5>Domain Information</h5></div>'.PHP_EOL;
    echo '<div class="sys-items">'.PHP_EOL;
    echo $query.' does not exist'.PHP_EOL;
    echo '</div></div>'.PHP_EOL;
    curl_close($ch);
    return false;
  }
  
  if ($status >= 300) {
    echo "Error: call to URL $url failed with status $status, response $json_response";
    curl_close($ch);
    return false;
  }
  
  curl_close($ch);

  $response = json_decode($json_response, true);           //Load json response into PHP array  
  $mem->set('whois-'.$query, $response, 0, 3600);          //Save Whois result for 1 hour
  
  return $response;
}

/********************************************************************
 *  Show Who Is Data
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function show_whoisdata() {
  global $whoisdata;
  
  if ($whoisdata === false) return;                        //No whois data available    
  
  if (isset($whoisdata['error'])) {
    echo '<div class="sys-group"><div class="sys-title">'.PHP_EOL;
    echo '<h5>Domain Information</h5></div>'.PHP_EOL;
    echo '<div class="sys-items">'.PHP_EOL;
    echo $whoisdata['error'].PHP_EOL;
    echo '</div></div>'.PHP_EOL;
    return;
  }
  
  draw_systable('Domain Information');
  draw_sysrow('Domain Name', $whoisdata['domain']);
  draw_sysrow('Name', $whoisdata['registrar']['name']);
  draw_sysrow('Status', ucfirst($whoisdata['status']));
  draw_sysrow('Created On', substr($whoisdata['created_on'], 0, 10));
  draw_sysrow('Updated On', substr($whoisdata['updated_on'], 0, 10));
  draw_sysrow('Expires On', substr($whoisdata['expires_on'], 0, 10));
  if (isset($whoisdata['nameservers'][0])) draw_sysrow('Name Servers', $whoisdata['nameservers']['0']['name']);
  if (isset($whoisdata['nameservers'][1])) draw_sysrow('', $whoisdata['nameservers']['1']['name']);
  if (isset($whoisdata['nameservers'][2])) draw_sysrow('', $whoisdata['nameservers']['2']['name']);
  if (isset($whoisdata['nameservers'][3])) draw_sysrow('', $whoisdata['nameservers']['3']['name']);
  echo '</table></div></div>'.PHP_EOL;
  
  if (isset($whoisdata['registrant_contacts'][0])) {
    draw_systable('Registrant Contact');
    draw_sysrow('Name', $whoisdata['registrant_contacts']['0']['name']);
    draw_sysrow('Organisation', $whoisdata['registrant_contacts']['0']['organization']);
    draw_sysrow('Address', $whoisdata['registrant_contacts']['0']['address']);
    draw_sysrow('City', $whoisdata['registrant_contacts']['0']['city']);
    draw_sysrow('Postcode', $whoisdata['registrant_contacts']['0']['zip']);
    if (isset($whoisdata['registrant_contacts'][0]['state'])) draw_sysrow('State', $whoisdata['registrant_contacts']['0']['state']);
    draw_sysrow('Country', $whoisdata['registrant_contacts']['0']['country']);
    if (isset($whoisdata['registrant_contacts'][0]['phone'])) draw_sysrow('Phone', $whoisdata['registrant_contacts']['0']['phone']);
    if (isset($whoisdata['registrant_contacts'][0]['fax'])) draw_sysrow('Fax', $whoisdata['registrant_contacts']['0']['fax']);
    if (isset($whoisdata['registrant_contacts'][0]['email'])) draw_sysrow('Email', strtolower($whoisdata['registrant_contacts']['0']['email']));
    echo '</table></div></div>'.PHP_EOL;
  }
  
  //print_r($whoisdata);
}

/********************************************************************
 *  Show Who Is Error when no API is set
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function show_whoiserror() {
  echo '<div class="sys-group"><div class="sys-title">'.PHP_EOL;
  echo '<h5>Domain Information</h5></div>'.PHP_EOL;
  echo '<div class="sys-items">'.PHP_EOL;
  echo '<p>Error: No WhoIs API key set. In order to use this feature you will need to add a valid JsonWhois API key to NoTrack config</p>'.PHP_EOL;
  echo '<p>Instructions:</p>'.PHP_EOL;
  echo '<ol>'.PHP_EOL;
  echo '<li>Sign up to <a href="https://jsonwhois.com/">JsonWhois</a></li>'.PHP_EOL;
  echo '<li> Add your API key to NoTrack <a href="./config.php?v=general">config</a></li>'.PHP_EOL;
  echo '</ol>'.PHP_EOL;
  echo '</div></div>'.PHP_EOL;
}
//Main---------------------------------------------------------------

$db = new mysqli(SERVERNAME, USERNAME, PASSWORD, DBNAME);

search_systems();                                //Need to find out systems on live table

if (isset($_GET['sys'])) {
  if (in_array($_GET['sys'], $syslist)) $sys = $_GET['sys'];
}

if (isset($_GET['datetime'])) {                 //Filter for hh:mm:ss
  if (preg_match(REGEX_TIME, $_GET['datetime']) > 0) {
    $datetime = date('Y-m-d ').$_GET['datetime'];
  }
}

if (isset($_GET['site'])) {
  if (filter_url($_GET['site'])) {
    $site = $_GET['site'];
  }
}

//echo "$sys - $datetime - $site";

show_time_view();

//TODO Whois needs TLD so remove sub domains from query
if ($Config['whoisapi'] == '') {
  show_whoiserror();
}
else {
  $whoisdata = get_whoisdata($site, $Config['whoisapi']);
  show_whoisdata();
}


$db->close();

?>

</div>

<div id="scrollup" class="button-scroll" onclick="ScrollToTop()"><img src="./svg/arrow-up.svg" alt="up"></div>
<div id="scrolldown" class="button-scroll" onclick="ScrollToBottom()"><img src="./svg/arrow-down.svg" alt="down"></div>

<div id="stats-box">
<div class="dialog-bar">Report</div>
<span id="sitename">site</span>
<span id="statsmsg">something</span>
<span id="statsblock1"><a class="button-blue" href="#">Block Whole</a> Block whole domain</span>
<span id="statsblock2"><a class="button-blue" href="#">Block Sub</a> Block just the subdomain</span>
<form name="reportform" action="https://quidsup.net/notrack/report.php" method="post" target="_blank">
<input type="hidden" name="site" id="siterep" value="none">
<span id="statsreport"><input type="submit" class="button-blue" value="Report">&nbsp;<input type="text" name="comment" class="textbox-small" placeholder="Optional comment"></span>
</form>

<br>
<div class="centered"><h6 class="button-grey" onclick="HideStatsBox()">Cancel</h6></div>
<div class="close-button" onclick="HideStatsBox()"><img src="./svg/button_close.svg" onmouseover="this.src='./svg/button_close_over.svg'" onmouseout="this.src='./svg/button_close.svg'" alt="close"></div>
</div>

</body>
</html>

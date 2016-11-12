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
  <link href="./css/help.css" rel="stylesheet" type="text/css" />
  <link rel="icon" type="image/png" href="./favicon.png" />
  <script src="./include/menu.js"></script>
  <script src="./include/queries.js"></script>
  <title>NoTrack - Sites Blocked</title>  
</head>

<body>
<?php
action_topmenu();
draw_topmenu();
draw_sidemenu();

/************************************************
*Constants                                      *
************************************************/


/************************************************
*Global Variables                               *
************************************************/
$page = 1;
$view = 'group';
$sort = 'DESC';

$db = new mysqli(SERVERNAME, USERNAME, PASSWORD, DBNAME);


/************************************************
*Arrays                                         *
************************************************/



/********************************************************************
 *  Add Date Vars to SQL Search
 *    Draw Sub Navigation menu
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_subnav() {
  global $view;
  
  echo '<div class="sys-group">'.PHP_EOL;
  echo '<h5>Sites Blocked</h5>'.PHP_EOL;
  echo '<nav><div class="sub-nav">'.PHP_EOL;
  echo '<ul>'.PHP_EOL;
  echo '<li><a'.is_active_class($view, 'group').' href="?view=group">Group</a></li>'.PHP_EOL;
  echo '<li><a'.is_active_class($view, 'time').' href="?view=time">Time</a></li>'.PHP_EOL;
  //echo '<li><a'.is_active_class($view, 'ref').' href="?view=ref">Referrer</a></li>'.PHP_EOL;
  //echo '<li><a'.is_active_class($view, 'vis').' href="?view=vis">Visualisation</a></li>'.PHP_EOL;
  echo '</ul>'.PHP_EOL;
  echo '</div></nav>'.PHP_EOL;
  echo '</div>'.PHP_EOL;
}

/********************************************************************
 *  Get User Agent 
 *    Identifies OS and Browser
 *  Params:
 *    UserAgent String
 *  Return:
 *    OS and Browser
 
1st Capturing Group (Mozilla|Dalvik|Opera)
2nd Capturing Group (Linux|X11|Android|Windows|compatible|iPad|iPhone|Macintosh|IE 11\.0)
3rd Capturing Group (MSIE|Android|Windows)?
Non-capturing group (?:KHTML|Gecko|AppleWebKit)?
4th Capturing Group (Firefox|Iceweasel|PaleMoon|SeaMonkey|\(KHTML\,\slike\sGecko\)\s)?
5th Capturing Group (Chrome|Version|min|brave)?
6th Capturing Group (Mobile|Safari)?
7th Capturing Group (Edge|OPR|Vivaldi)?
 */
function get_useragent($user_agent) {
  $matches = array();
  $ua = array('unknown', 'unknown');
  $pattern = '/(Mozilla|Dalvik|Opera)\/\d\.\d\.?\d?\s\((Linux|X11|Android|Windows|compatible|iPad|iPhone|Macintosh|IE 11\.0).\s?(MSIE|Android|Windows)?[^\)]+\)\s?(?:KHTML|Gecko|AppleWebKit)?[\/\d\.\+]*\s?(Firefox|Iceweasel|PaleMoon|SeaMonkey|\(KHTML\,\slike\sGecko\)\s)?(Chrome|Version|min|brave)?[\/\d\.\s]*(Mobile|Safari)?[\/\d\.\s]*(Edge|OPR|Vivaldi)?/';
  
  if (preg_match($pattern, $user_agent, $matches) > 0) {
    switch($matches[1]) {                        //Usually Mozilla
      case 'Dalvik': $ua[1] = 'android'; break;  //Android apps
      case 'Opera': $ua[1] = 'opera'; break;     //Opera prior to Blink
    }    
  
    switch($matches[2]) {                        //Most OS's or IE 11
      case 'Linux':
      case 'X11':
        $ua[0] = 'linux';
        break;
      case 'Android':
        $ua[0] = 'android';
        break;
      case 'Windows':
      case 'compatible':
        $ua[0] = 'windows';
        break;
      case 'iPad':
      case 'iPhone':
      case 'Macintosh':
        $ua[0] = 'apple';
        break;
      case 'IE 11.0':
        $ua[0] = 'windows';
        $ua[1] = 'internet-explorer';
        break;
    }
   
    if (isset($matches[3])) {                    //Android or IE
      switch($matches[3]) {
        case 'MSIE': $ua[1] = 'internet-explorer'; break;
        case 'Android': $ua[0] = 'android'; break;
        case 'Windows': $ua[0] = 'windows'; break;
      }      
    }
    
    if (isset($matches[4])) {                    //Gecko rendered Mozilla browsers
      switch($matches[4]) {
        case 'Firefox': $ua[1] = 'firefox'; break;
        case '(KHTML, like Gecko):': $ua[1] = 'chrome'; break;
        case 'Iceweasel': $ua[1] = 'iceweasel'; break;
        case 'PaleMoon': $ua[1] = 'palemoon'; break;
        case 'SeaMonkey': $ua[1] = 'seamonkey'; break;
      }
    }
    
    if (isset($matches[5])) {
      switch($matches[5]) {
        case 'Chrome': $ua[1] = 'chrome'; break;
        case 'min': $ua[1] = 'min'; break;
        case 'brave': $ua[1] = 'brave'; break;
      }
    }
    
    if (isset($matches[6])) {                    //Safari or Safari compliant
      if ($matches[5] == 'Version') {            //Backtrack to Group5 to check if actually Safari
        $ua[1] = 'safari';
      }
    }
    
    if (isset($matches[7])) {
      switch($matches[7]) {
        case 'Edge': $ua[1] = 'edge'; break;
        case 'OPR': $ua[1] = 'opera'; break;
        case 'Vivaldi': $ua[1] = 'vivaldi'; break;
      }
    }    
  }
    
  
  return $ua;
}

/********************************************************************
 *  Hightlight URL
 *    Highlight site, similar to browser behaviour
 *    Full Group 1: http / https / ftp
 *    Non-capture group to remove www.
 *    Full Group 2: Domain
 *    Full Group 3: URI Path
 *    Domain Group 1: Site
 *    Domain Group 2: Optional .gov, .org, .co, .com
 *    Domain Group 3: Top Level Domain
 *
 *    Merge final string together with Full Group 1, Full Group 2 - Length Domain, Domain (highlighted black), Full Group 3
 *  Params:
 *    URL
 *  Return:
 *    html formatted string 
 */
function highlight_url($url) {
  $highlighted =  $url;
  $full = array();
  $domain = array();
    
  if (preg_match('/^(https?:\/\/|ftp:\/\/)?(?:www\.)?([^\/]+)?(.*)$/', $url, $full) > 0) {    
    if (preg_match('/([\w\d\-\_]+)\.(co\.|com\.|gov\.|org\.)?([\w\d\-\_]+)$/', $full[2], $domain) > 0) {      
      $highlighted = '<span class="gray">'.$full[1].substr($full[2], 0, 0 -strlen($domain[0])).'</span>'.$domain[0].'<span class="gray">'.$full[3].'</span>';
    }
  }  
  return $highlighted;
}

/********************************************************************
 *  Show Access Table
 *    
 *  Params:
 *    None
 *  Return:
 *    True on results found
 */
function show_accesstable() {
  global $db, $page, $sort, $view;
  
  $rows = 0;
  $http_method = '';
  $referrer = '';
  $query = '';
  $remote_host = '';
  $table_row = '';
  $user_agent = '';
  $user_agent_array = array();
    
  echo '<div class="sys-group">'.PHP_EOL;
  if ($view == 'group') {
    echo '<h6>Sorted by Unique Site</h6>'.PHP_EOL;
    $rows = count_rows('SELECT COUNT(DISTINCT `site`) FROM lightyaccess');
    $query = 'SELECT * FROM lightyaccess GROUP BY site ORDER BY UNIX_TIMESTAMP(log_time) '.$sort.' LIMIT '.ROWSPERPAGE.' OFFSET '.(($page-1) * ROWSPERPAGE);
  }
  elseif ($view == 'time') {
    echo '<h6>Sorted by Time last seen</h6>'.PHP_EOL;
    $rows = count_rows('SELECT COUNT(*) FROM lightyaccess');
    $query = 'SELECT * FROM lightyaccess ORDER BY UNIX_TIMESTAMP(log_time) '.$sort.' LIMIT '.ROWSPERPAGE.' OFFSET '.(($page-1) * ROWSPERPAGE);
  }  
  
  if ((($page-1) * ROWSPERPAGE) > $rows) $page = 1;
    
  if(!$result = $db->query($query)){
    die('There was an error running the query'.$db->error);
  }
  
    
  if ($result->num_rows == 0) {                  //Leave if nothing found
    $result->free();
    echo 'No sites found in Access List'.PHP_EOL;
    echo '</div>';
    return false;
  }
  
  pagination($rows, 'view='.$view);              //Draw pagination buttons
  
  echo '<table id="access-table">'.PHP_EOL;      //Start table
  echo '<tr><th>Date Time</th><th>Method</th><th>User Agent</th><th>Site</th></tr>'.PHP_EOL;
  
  while($row = $result->fetch_assoc()) {         //Read each row of results
    if ($row['http_method'] == 'GET') {          //Colour HTTP Method
      $http_method = '<span class="green">GET</span>';
    }
    else {
      $http_method = '<span class="violet">POST</span>';
    }
    
        
    //Temporary situation until v0.8.3
    if (array_key_exists('referrer', $row)) {
      $referrer = $row['referrer'];
    }
    else {
      $referrer = '';
    }
    
    if (array_key_exists('user_agent', $row)) {
      $user_agent = $row['user_agent'];
    }
    else {
      $user_agent = '';
    }
    
    if (array_key_exists('remote_host', $row)) {
      $remote_host = $row['remote_host'];
    }
    else {
      $remote_host = '';
    }
        
    $user_agent_array = get_useragent($user_agent);  //Get OS and Browser from UserAgent
    
    //Build up the table row
    $table_row = '<tr><td>'.$row['log_time'].'</td><td>'.$http_method.'</td>';
    
    $table_row .='<td title="'.$user_agent.'"><div class="centered"><img src="./images/useragent/'.$user_agent_array[0].'.png" alt=""><img src="./images/useragent/'.$user_agent_array[1].'.png" alt=""></div></td>';
    
    $table_row .= '<td>'.highlight_url(htmlentities($row['site'].$row['uri_path'])).'<br />Referrer: '.highlight_url(htmlentities($referrer)).'<br />Requested By: '.$remote_host.'</td></tr>';
    
    echo $table_row.PHP_EOL;                     //Echo the table row
  }
  
  echo '</table><br />'.PHP_EOL;                 //End of table
  pagination($rows, 'view='.$view);              //Draw pagination buttons
  echo '</div>'.PHP_EOL;                         //End Sys-group div
  
  $result->free();

  return true;
}


//Main---------------------------------------------------------------

/************************************************
*GET REQUESTS                                   *
************************************************/
if (isset($_GET['view'])) {
  switch($_GET['view']) {
    case 'group': $view = 'group'; break;
    case 'time': $view = 'time'; break;    
  }
}

if (isset($_GET['page'])) {
  $page = filter_integer($_GET['page'], 1, PHP_INT_MAX, 1);
}

echo '<div id="main">';

draw_subnav();

if (($view == 'group') || ($view == 'time'))  {
  show_accesstable();
}

?>
</div>
<div id="scrollup" class="button-scroll" onclick="ScrollToTop()"><img src="./svg/arrow-up.svg" alt="up"></div>
<div id="scrolldown" class="button-scroll" onclick="ScrollToBottom()"><img src="./svg/arrow-down.svg" alt="down"></div>
</body>
</html>

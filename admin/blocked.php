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
$view = 'time';
$sort = 'DESC';

$db = new mysqli(SERVERNAME, USERNAME, PASSWORD, DBNAME);
#$searchbox = '';


/************************************************
*Arrays                                         *
************************************************/

echo '<div id="main">';

function draw_subnav() {
  global $view;
  
  echo '<div class="sys-group">'.PHP_EOL;
  echo '<h5>Sites Blocked</h5>'.PHP_EOL;
  echo '<div class="sub-nav">'.PHP_EOL;
  echo '<ul>'.PHP_EOL;
  echo '<li><a'.is_active_class($view, 'time').' href="?v=time">Time</a></li>'.PHP_EOL;
  echo '<li><a'.is_active_class($view, 'group').' href="?v=time">Group</a></li>'.PHP_EOL;
  
  echo '</ul>'.PHP_EOL;
  echo '</div></div>'.PHP_EOL;
}

function show_lightyaccess() {
  global $db, $page;
  
  $i = 1;
  $rows = 0;
  $http_method = '';
  $site_full = '';
  $site_msg = '';
  $referrer = '';
  $user_agent = '';
  $remote_host = '';
    
  echo '<div class="sys-group">'.PHP_EOL;
  echo '<h6>Sorted by Time</h6>'.PHP_EOL;
    
  $rows = count_rows('SELECT COUNT(DISTINCT `site`) FROM lightyaccess');
    
  if ((($page-1) * ROWSPERPAGE) > $rows) $page = 1;
    
  $query = 'SELECT * FROM lightyaccess GROUP BY site ORDER BY log_time DESC LIMIT '.ROWSPERPAGE.' OFFSET '.(($page-1) * ROWSPERPAGE);
    
  if(!$result = $db->query($query)){
    die('There was an error running the query'.$db->error);
  }
  
  /*echo '<form method="GET">'.PHP_EOL;            //Form for Text Search
  echo '<input type="hidden" name="page" value="'.$page.'" />'.PHP_EOL;
  if ($searchbox == '') {                        //Anything in search box?
    echo '<input type="text" name="s" id="search" placeholder="Search">'.PHP_EOL;
  }
  else {                                         //Yes - Add it as current value
    echo '<input type="text" name="s" id="search" value="'.$searchbox.'">';
    $linkstr = '&amp;s='.$searchbox;             //Also add it to $linkstr
  }
  echo '</form></div>'.PHP_EOL;                  //End form*/
  
  
  if ($result->num_rows == 0) {                  //Leave if nothing found
    $result->free();
    echo 'No sites found in Access List'.PHP_EOL;
    echo '</div>';
    return false;
  }
  
  pagination($rows, '');
  echo '<table id="access-table">'.PHP_EOL;
  echo '<tr><th>Date Time</th><th>Requester</th><th>Method</th><th>User Agent</th><th>Referrer</th><th>Site</th></tr>'.PHP_EOL;
  
  while($row = $result->fetch_assoc()) {         //Read each row of results
    if ($row['http_method'] == 'GET') {
      $http_method = '<span class="green">GET</span>';
    }
    else {
      $http_method = '<span class="violet">POST</span>';
    }
    
    $site_full = $row['site'].$row['uri_path'];
    
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
    //If string length too long, then attempt to cut out segment of known file name of URI and join to shortened URL
    //For unknown file just show the first 45 characters and display+ button
    if (strlen($site_full) > 48) {
      if (preg_match('/[A-Za-z0-9\-_\%\&\?\.#]{1,18}\.(php|html|js|json|jpg|gif|png)$/', $row['uri_path'], $matches) > 0) {
        $site_msg = substr($site_full, 0, (45 - strlen($matches[0]))).'.../'.$matches[0].' <span id="b'.$i.'" class="button-small pointer" onclick="ShowFull(\''.$i.'\')">+</span>'.'<p id="r'.$i.'" class="smallhidden">'.$site_full.'</p>';
      }
      else {
        $site_msg = substr($site_full, 0, 45).'... <span id="b'.$i.'" class="button-small pointer" onclick="ShowFull(\''.$i.'\')">+</span>'.'<p id="r'.$i.'" class="smallhidden">'.$site_full.'</p>';
      }      
    }
    else {
      $site_msg = $site_full;
    }
    echo '<tr><td>'.$row['log_time'].'</td><td>'.$remote_host.'</td><td>'.$http_method.'</td><td><img src="https://cloud.githubusercontent.com/assets/10121067/20120252/0447c3d6-a604-11e6-92c9-ba02d983bb01.jpg" title="'.$user_agent.'"<img></td><td>'.$referrer.'</td><td>'.$site_msg.'</td></tr>'.PHP_EOL;
    
    $i++;
  }
  echo '</table>'.PHP_EOL;
  echo '</div>'.PHP_EOL;
  
  $result->free();

  return true;
}


//Main---------------------------------------------------------------

/************************************************
*GET REQUESTS                                   *
************************************************/
/*if (isset($_GET['s'])) {                         //Search box
  //Allow only characters a-z A-Z 0-9 ( ) . _ - and \whitespace
  $searchbox = preg_replace('/[^a-zA-Z0-9\(\)\.\s_-]/', '', $_GET['s']);
  $searchbox = strtolower($searchbox);  
}*/

if (isset($_GET['page'])) {
  $page = filter_integer($_GET['page'], 1, PHP_INT_MAX, 1);
}

draw_subnav();
show_lightyaccess();

?>
</div>
<div id="scrollup" class="button-scroll" onclick="ScrollToTop()"><img src="./svg/arrow-up.svg" alt="up"></div>
<div id="scrolldown" class="button-scroll" onclick="ScrollToBottom()"><img src="./svg/arrow-down.svg" alt="down"></div>
</body>
</html>

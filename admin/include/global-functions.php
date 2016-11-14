<?php
//Global Functions used in NoTrack Admin

/********************************************************************
 *  Draw Sys Table
 *    Start off a sys-group table
 *  Params:
 *    Title
 *  Return:
 *    None
 */ 
function draw_systable($title) {
  echo '<div class="sys-group"><div class="sys-title">'.PHP_EOL;
  echo '<h5>'.$title.'</h5></div>'.PHP_EOL;
  echo '<div class="sys-items"><table class="sys-table">'.PHP_EOL;
  
  return null;
}


/********************************************************************
 *  Draw Sys Table
 *    Start off a sys-group table
 *  Params:
 *    Description, Value
 *  Return:
 *    None
 */
function draw_sysrow($description, $value) {
  echo '<tr><td>'.$description.': </td><td>'.$value.'</td></tr>'.PHP_EOL;
  
  return null;
}


/********************************************************************
 *  Activate Session
 *    Create login session
 *  Params:
 *    None
 *  Return:
 *    None
 */
function activate_session() {  
  $_SESSION['session_expired'] = false;
  $_SESSION['session_start'] = time();  
}

function ensure_active_session() {
  if (is_password_protection_enabled()) {
    session_start();
    if (isset($_SESSION['session_start'])) {
      if (!is_active_session()) {
        $_SESSION['session_expired'] = true;
        header('Location: ./login.php');
        exit;
      }
    }
    else {
      header('Location: ./login.php');
      exit;
    }
  }
}


function is_active_session() {
  $session_duration = 1800;    
  if (isset($_SESSION['session_start'])) {    
    if ((time() - $_SESSION['session_start']) < $session_duration) {
      return true;
    }
  }
  return false;
}

function is_password_protection_enabled() {
  global $Config;
  
  if ($Config['Password'] != '') return true;
  return false;
}


/********************************************************************
 *  Check Version
 *    1. Split strings by '.'
 *    2. Combine back together and multiply with Units array
 *    e.g 1.0 - 1x10000 + 0x100 = 10,000
 *    e.g 0.8.0 - 0x10000 + 8x100 + 0x1 = 800
 *    e.g 0.7.10 - 0x10000 + 7x100 + 10x1 = 710
 *  Params:
 *    Version
 *  Return:
 *    true if latestversion >= currentversion, or false if latestversion < currentversion
 */
function check_version($latestversion) {
  //If LatestVersion is less than Current Version then function returns false
  
  $numversion = 0;
  $numlatest = 0;
  $units = array(10000,100,1);
  
  $splitversion = explode('.', VERSION);
  $splitlatest = explode('.', $latestversion);
  
  for ($i = 0; $i < count($splitversion); $i++) {
    $numversion += ($units[$i] * intval($splitversion[$i]));
  }
  for ($i = 0; $i < count($splitlatest); $i++) {
    $numlatest += ($units[$i] * intval($splitlatest[$i]));
  }
  
  if ($numlatest < $numversion) return false;
  
  return true;
}

/********************************************************************
 *  Count rows in table
 *
 *  Params:
 *    SQL Query
 *  Return:
 *    Number of Rows
 */
function count_rows($query) {
  global $db, $mem;
  
  $rows = 0;
  
  if(!$result = $db->query($query)){
    die('There was an error running the query '.$db->error);
  }
  
  $rows = $result->fetch_row()[0];               //Extract value from array
  $result->free();    
        
  return $rows;
}


/********************************************************************
 *  Filter Integer Value
 *    Checks if Integer value given is between min and max
 *  Params:
 *    Value to Check, Minimum, Maximum, Default Value
 *  Return:
 *    value on success, default value on fail
 */
function filter_integer($value, $min, $max, $defaultvalue=0) {
  if (is_numeric($value)) {
    if (($value >= $min) && ($value <= $max)) {
      return intval($value);
    }
  }
  
  return $defaultvalue;
}

/********************************************************************
 *  Is Active Class
 *    Used to allocate class="active" against li
 *  Params:
 *    Current View, Item
 *  Return:
 *    class='active' or '' when inactive
 */
function is_active_class($currentview, $item) {
  if ($currentview == $item) {
    return ' class="active"';
  }
  else {
    return '';
  }
}

/********************************************************************
 *  Pagination
 *  
 *  Draw up to 7 buttons
 *  Main [<] [1] [x] [x+1] [L] [>]
 *  Or   [ ] [1] [2] [>]
 *
 *  Params:
 *    rows
 *    $linktext = text for a href
 *  Return:
 *    None
 */
function pagination($totalrows, $linktext) {
  global $page;

  $numpages = 0;
  $currentpage = 0;
  $startloop = 0;
  $endloop = 0;
  
  if ($totalrows > ROWSPERPAGE) {                     //Is Pagination needed?
    $numpages = ceil($totalrows / ROWSPERPAGE);       //Calculate List Size
    
    //<div class="sys-group">
    echo '<div class="float-left pag-nav"><ul>'.PHP_EOL;
  
    if ($page == 1) {                            // [ ] [1]
      echo '<li><span>&nbsp;&nbsp;</span></li>'.PHP_EOL;
      echo '<li class="active"><a href="?page=1&amp;'.$linktext.'">1</a></li>'.PHP_EOL;
      $startloop = 2;
      if ($numpages > 4)  $endloop = $page + 4;
      else $endloop = $numpages;
    }
    else {                                       // [<] [1]
      echo '<li><a href="?page='.($page-1).'&amp;'.$linktext.'">&#x00AB;</a></li>'.PHP_EOL;
      echo '<li><a href="?page=1&amp;'.$linktext.'">1</a></li>'.PHP_EOL;
      
      if ($numpages < 5) {
        $startloop = 2;                          // [1] [2] [3] [4] [L]
      }
      elseif (($page > 2) && ($page > $numpages -4)) {
        $startloop = ($numpages - 3);            //[1]  [x-1] [x] [L]
      }
      else {
        $startloop = $page;                      // [1] [x] [x+1] [L]
      }
      
      if (($numpages > 3) && ($page < $numpages - 2)) {
        $endloop = $page + 3;                    // [y] [y+1] [y+2] [y+3]
      }
      else {
        $endloop = $numpages;                    // [1] [x-2] [x-1] [y] [L]
      }      
    }    
    
    for ($i = $startloop; $i < $endloop; $i++) { //Loop to draw 3 buttons
      if ($i == $page) {
        echo '<li class="active"><a href="?page='.$i.'&amp;'.$linktext.'">'.$i.'</a></li>'.PHP_EOL;
      }
      else {
        echo '<li><a href="?page='.$i.'&amp;'.$linktext.'">'.$i.'</a></li>'.PHP_EOL;
      }
    }
    
    if ($page == $numpages) {                    // [Final] [ ]
      echo '<li class="active"><a href="?page='.$numpages.'&amp;'.$linktext.'">'.$numpages.'</a></li>'.PHP_EOL;
      echo '<li><span>&nbsp;&nbsp;</span></li>'.PHP_EOL;
    }    
    else {                                       // [Final] [>]
      echo '<li><a href="?page='.$numpages.'&amp;'.$linktext.'">'.$numpages.'</a></li>'.PHP_EOL;
      echo '<li><a href="?page='.($page+1).'&amp;'.$linktext.'">&#x00BB;</a></li>'.PHP_EOL;
    }	
    
  echo '</ul></div>'.PHP_EOL;
  //</div>
  }
}


/********************************************************************
 *  Save Config
 *    1. Check if Latest Version is less than Current Version
 *    2. Open Temp Config file for writing
 *    3. Loop through Config Array
 *    4. Write all values, except for "Status = Enabled"
 *    5. Close Config File
 *    6. Delete Config Array out of Memcache, in order to force reload
 *    7. Onward process is to Display appropriate config view
 *  Params:
 *    None
 *  Return:
 *    SQL Query string
 */
function save_config() {
  global $Config, $mem;
  
  $key = '';
  $value = '';
  
  //Prevent wrong version being written to config file if user has just upgraded and old LatestVersion is still stored in Memcache
  if (check_version($Config['LatestVersion'])) {
    $Config['LatestVersion'] = VERSION;
  }
  
  $fh = fopen(CONFIGTEMP, 'w');                  //Open temp config for writing
  
  foreach ($Config as $key => $value) {          //Loop through Config array
    if ($key == 'Status') {
      if ($value != 'Enabled') {
        fwrite($fh, $key.' = '.$value.PHP_EOL);  //Write Key & Value
      }
    }
    else {
      fwrite($fh, $key.' = '.$value.PHP_EOL);    //Write Key & Value
    }
  }
  fclose($fh);                                   //Close file
  
  $mem->delete('Config');                        //Delete config from Memcache
  
  exec(NTRK_EXEC.'--save-conf');
}





//Execute Action-----------------------------------------------------
function ExecAction($Action, $ExecNow, $Fork=false) {
  //Execute Action writes a command into /tmp/ntrk-exec.txt
  //It can then either wait inline for ntrk-exec to process command, or fork ntrk-exec
  
  //Options:
  //ExecNow - false: Write Action to file and then return
  //ExecNow - true: Run ntrk-exec and wait until its finished
  //Fork - true: Run ntrk-exec and fork to new process

  global $FileTmpAction;
  if (file_put_contents($FileTmpAction, $Action.PHP_EOL, FILE_APPEND) === false) {
    die('Unable to write to file '.$FileTmpAction);
  }
  
  if (($ExecNow) && (! $Fork)) {
    exec('sudo ntrk-exec 2>&1');
    //echo "<pre>\n";
    //$Msg = shell_exec('sudo ntrk-exec 2>&1');
    //echo $Msg;
    //echo "</pre>\n";
  }
  elseif (($ExecNow) && ($Fork)) {
    exec("sudo ntrk-exec > /dev/null &");
  }
  
  return null;    
}
//Filter Bool from GET-----------------------------------------------
function Filter_Bool($Str) {
  //1. Check Variable Exists
  //2. Check if Variable is 'true', then return boolean true
  //3. Otherwise return boolean false
  
  if (isset($_GET[$Str])) {
    if ($_GET[$Str] == 'true') return true;
  }
  return false;
}
//Filter Int from GET------------------------------------------------
function Filter_Int($Str, $Min, $Max, $DefaltValue=false) {
  //1. Check Variable Exists
  //2. Check Value is between $Min and $Max
  //3. Return Value on success, and $DefaultValue on fail
  
  if (isset($_GET[$Str])) {
    if (is_numeric($_GET[$Str])) {
      if (($_GET[$Str] >= $Min) && ($_GET[$Str] < $Max)) {
        return intval($_GET[$Str]);
      }
    }
  }
  return $DefaltValue;
}
//Filter Int from POST-----------------------------------------------
function Filter_Int_Post($Str, $Min, $Max, $DefaltValue=false) {
  //1. Check Variable Exists
  //2. Check Value is between $Min and $Max
  //3. Return Value on success, and $DefaultValue on fail
  
  if (isset($_POST[$Str])) {
    if (is_numeric($_POST[$Str])) {
      if (($_POST[$Str] >= $Min) && ($_POST[$Str] < $Max)) {
        return intval($_POST[$Str]);
      }
    }
  }
  return $DefaltValue;
}

//Filter String from GET---------------------------------------------
function Filter_Str($Str) {
  //1. Check Variable Exists
  //2. Check String doesn't contain !"£$%^*()[]<>|/\
  //Return True on success, and False on fail

  if (isset($_GET[$Str])) {
    if (preg_match('/[!\"£\$%\^\*\(\)\[\]<>\|\/\\\\]/', $_GET[$Str]) == 0) return true;    
  }
  return false;
}
//Filter String Value------------------------------------------------
function Filter_Str_Value($Str, $DefaltValue='') {
  //1. Check String Length is > 0 AND String doesn't contain !"<>,|/\
  //2. Return Str on success, and Default on fail
  
  if (preg_match('/[!\"<>\|]/', $Str) == 0) {
    return $Str;
  }  
  return $DefaltValue;
}
//Filter URL GET-----------------------------------------------------
function Filter_URL($Str) {
  //1. Check Variable Exists
  //2. Check String Length is > 0 AND String doesn't contain !"£$%^&()+=<>,|/\
  //3. Check String matches the form of a URL "any.co"
  //Return True on success, and False on fail
  
  if (isset($_GET[$Str])) {
    if (((strlen($_GET[$Str]) > 0) && (preg_match('/[!\"£\$%\^&\(\)+=<>\,\|\/\\\\]/', $_GET[$Str]) == 0))) {
      if (preg_match('/.*\..{2,}/', $_GET[$Str]) == 1) return true;
    }
  }
  return false;
}
//Filter URL Str-----------------------------------------------------
function Filter_URL_Str($Str) {
  //1. Check String Length is > 0 AND String doesn't contain !"£$^()<>,|
  //2. Check String matches the form of a URL "any.co"
  //Return True on success, and False on fail
  if (preg_match('/[!\"£\$\^\(\)<>\,\|]/', $Str) == 0) {
    if (preg_match('/.*\..{2,}$/', $Str) == 1) return true;    
  }
  return false;
}


/********************************************************************
 *  Load Config File
 *    1. Attempt to load Config from Memcache
 *    2. Write DefaultConfig to Config, incase any variables are missing
 *    3. Read Config File
 *    4. Split Line between: (Var = Value)
 *    5. Certain values need filtering to prevent XSS
 *    6. For other values, check if key exists, then replace with new value
 *    7. Setup SearchUrl
 *    8. Write Config to Memcache
 *  Params:
 *    Description, Value
 *  Return:
 *    None
 */
function load_config() {
  global $Config, $mem, $DEFAULTCONFIG, $SEARCHENGINELIST, $WHOISLIST;
  $line = '';
  $splitline = array();
  
  $Config=$mem->get('Config');                   //Load Config array from Memcache
  if (! empty($Config)) {
    return null;                                 //Did it load from memory?
  }
  
  $Config = $DEFAULTCONFIG;                      //Firstly Set Default Config
  
  if (file_exists(CONFIGFILE)) {                 //Check file exists
    $fh= fopen(CONFIGFILE, 'r');
    while (!feof($fh)) {
      $line = trim(fgets($fh));                  //Read Line of LogFile
      $splitline = explode('=', $line);
      if (count($splitline) == 2) {
        $splitline[0] = trim($splitline[0]);
        $splitline[1] = trim($splitline[1]);
        switch (trim($splitline[0])) {
          case 'LatestVersion':
            $Config['LatestVersion'] = Filter_Str_Value($splitline[1], VERSION);
            break;
          case 'Status':
            $Config['Status'] = Filter_Str_Value($splitline[1], 'Enabled');
            break;
          case 'BlockMessage':
            $Config['BlockMessage'] = Filter_Str_Value($splitline[1], 'pixel');
            break;
          case 'Search':
            $Config['Search'] = Filter_Str_Value($splitline[1], 'DuckDuckGo');
            break;
          case 'WhoIs':
            $Config['WhoIs'] = Filter_Str_Value($splitline[1], 'Who.is');              
            break;
          case 'Delay':
            $Config['Delay'] = filter_integer($splitline[1], 0, 3600, 30);
            break;
          case 'Suppress':
            $Config['Suppress'] = Filter_Str_Value($splitline[1], '');
            break;            
          default:
            if (array_key_exists($splitline[0], $Config)) {
              $Config[$splitline[0]] = $splitline[1];
            }
            break;
        }
      }
    }
    
    fclose($fh);
  }
  
  //Set SearchUrl if User hasn't configured a custom string via notrack.conf
  if ($Config['SearchUrl'] == '') {      
    if (array_key_exists($Config['Search'], $SEARCHENGINELIST)) {
      $Config['SearchUrl'] = $SEARCHENGINELIST[$Config['Search']];
    } 
    else {
      $Config['SearchUrl'] = $SEARCHENGINELIST['DuckDuckGo'];       
    }
  }
   
  //Set WhoIsUrl if User hasn't configured a custom string via notrack.conf
  if ($Config['WhoIsUrl'] == '') {      
    if (array_key_exists($Config['WhoIs'], $WHOISLIST)) {
      $Config['WhoIsUrl'] = $WHOISLIST[$Config['WhoIs']];
    } 
    else {
      $Config['WhoIsUrl'] = $WHOISLIST['Who.is'];       
    }
  }
  
  $mem->set('Config', $Config, 0, 1200);
  
  return null;
}


/********************************************************************
 *  Draw Pie Chart
 *    Credit to Branko: http://www.tekstadventure.nl/branko/blog/2008/04/php-generator-for-svg-pie-charts
 *  Params:
 *    aray of values, the centre coordinates x and y, radius of the piechart, colours
 *  Return:
 *    svg data for path nodes
 */
function piechart($data, $cx, $cy, $radius, $colours) {
  $chartelem = "";
  $sum = 0;

  $max = count($data);  

  foreach ($data as $key=>$val) {
    $sum += $val;
  }
  $deg = $sum/360;                               // one degree
  $jung = $sum/2;                                // necessary to test for arc type

  //Data for grid, circle, and slices
  $dx = $radius;                                 // Starting point:  
  $dy = 0;                                       // first slice starts in the East
  $oldangle = 0;

  for ($i = 0; $i<$max; $i++) {                  // Loop through the slices
    $angle = $oldangle + $data[$i]/$deg;         // cumulative angle
    $x = cos(deg2rad($angle)) * $radius;         // x of arc's end point
    $y = sin(deg2rad($angle)) * $radius;         // y of arc's end point

    $colour = $colours[$i];

    if ($data[$i] > $jung) {                     // arc spans more than 180 degrees
      $laf = 1;
    }
    else {
      $laf = 0;
    }

    $ax = $cx + $x;                              // absolute $x
    $ay = $cy + $y;                              // absolute $y
    $adx = $cx + $dx;                            // absolute $dx
    $ady = $cy + $dy;                            // absolute $dy
    $chartelem .= "<path d=\"M$cx,$cy ";         // move cursor to center
    $chartelem .= " L$adx,$ady ";                // draw line away away from cursor
    $chartelem .= " A$radius,$radius 0 $laf,1 $ax,$ay "; // draw arc
    $chartelem .= " z\" ";                       // z = close path
    $chartelem .= " fill=\"$colour\" stroke=\"#00000A\" stroke-width=\"2\" ";
    $chartelem .= " fill-opacity=\"0.95\" stroke-linejoin=\"round\" />";
    $chartelem .= PHP_EOL;
    $dx = $x;      // old end points become new starting point
    $dy = $y;      // id.
    $oldangle = $angle;
  }
  
  return $chartelem; 
}
?>

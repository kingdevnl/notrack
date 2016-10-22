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
  for ($i = 0; $i < count($splitversion); $i++) {
    $numlatest += ($units[$i] * intval($splitlatest[$i]));
  }
  
  if ($numlatest < $numversion) return false;
  
  return true;
}

/********************************************************************
 *  Count rows in table
 *
 *  Params:
 *    Query String
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
 *  Pagination
 *  
 *  Draw up to 6 buttons
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
      if (($numpages > 3) && ($page < $numpages - 3)) $endloop = $page + 3;
      else $endloop = $numpages;
    }
    else {                                       // [<] [1]
      echo '<li><a href="?page='.($page-1).'&amp;'.$linktext.'">&#x00AB;</a></li>'.PHP_EOL;
      echo '<li><a href="?page=1&amp;'.$linktext.'">1</a></li>'.PHP_EOL;
      
      if ($numpages < 4) $startloop = 2;         // [1] [2] [3] [L]
      elseif (($page > 2) && ($page > $numpages -3)) $startloop = ($numpages - 2); //[1]  [x-1] [x] [L]
      else $startloop = $page;                   // [1] [x] [x+1] [L]
      
      if (($numpages > 3) && ($page < $numpages - 2)) $endloop = $page + 2; // [y] [y+1] [y+2]
      else $endloop = $numpages;                 // [1] [x-1] [y] [L]
    }    
    
    for ($i = $startloop; $i < $endloop; $i++) { //Loop to draw 2 buttons
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
 *
 *  Params:
 *    None
 *  Return:
 *    SQL Query string
 */
function save_config() {
  //1. Check if Latest Version is less than Current Version
  //2. Open Temp Config file for writing
  //3. Loop through Config Array
  //4. Write all values, except for "Status = Enabled"
  //5. Close Config File
  //6. Delete Config Array out of Memcache, in order to force reload
  //7. Onward process is to Display appropriate config view
  
  global $Config, $FileTmpConfig, $mem;  
  
  //Prevent wrong version being written to config file if user has just upgraded and old LatestVersion is still stored in Memcache
  if (check_version($Config['LatestVersion'])) {
    $Config['LatestVersion'] = VERSION;
  }
  
  $fh = fopen($FileTmpConfig, 'w');      //Open temp config for writing
  
  foreach ($Config as $Key => $Value) {          //Loop through Config array
    if ($Key == 'Status') {
      if ($Value != 'Enabled') {
        fwrite($fh, $Key.' = '.$Value.PHP_EOL);  //Write Key & Value
      }
    }
    else {
      fwrite($fh, $Key.' = '.$Value.PHP_EOL);    //Write Key & Value
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
//Load Config File---------------------------------------------------
function load_config() {
  //1. Attempt to load Config from Memcache
  //2. Write DefaultConfig to Config, incase any variables are missing
  //3. Read Config File
  //4. Split Line between = (Var = Value)
  //5. Filter each value
  //6. Setup SearchUrl
  //7. Write Config to Memcache
  //As of v0.7.16 blocklists were renamed to bl_

  global $FileConfig, $Config, $DefaultConfig, $mem;
  
  $Config=$mem->get('Config');                   //Load array from Memcache
  
  if (! empty($Config)) return;                  //Did it load from memory?
  
  $Config = $DefaultConfig;                      //Firstly Set Default Config
  if (file_exists($FileConfig)) {                //Check file exists      
    $fh= fopen($FileConfig, 'r');
    while (!feof($fh)) {
      $Line = trim(fgets($fh));          //Read Line of LogFile
      if ($Line != '') {
        $SplitLine = explode('=', $Line);
        if (count($SplitLine == 2)) {          
          $SplitLine[1] = trim($SplitLine[1]);
          switch (trim($SplitLine[0])) {
            case 'LatestVersion':
              $Config['LatestVersion'] = Filter_Str_Value($SplitLine[1], VERSION);
              break;
            case 'NetDev':
              $Config['NetDev'] = $SplitLine[1];
              break;
            case 'IPVersion':
              $Config['IPVersion'] = $SplitLine[1];
              break;
            case 'Status':
              $Config['Status'] = Filter_Str_Value($SplitLine[1], 'Enabled');
              break;
            case 'BlockMessage':
              $Config['BlockMessage'] = Filter_Str_Value($SplitLine[1], 'pixel');
              break;
            case 'Search':
              $Config['Search'] = Filter_Str_Value($SplitLine[1], 'DuckDuckGo');
              break;
            case 'WhoIs':
              $Config['WhoIs'] = Filter_Str_Value($SplitLine[1], 'Who.is');              
              break;
            case 'Username':
              $Config['Username'] = $SplitLine[1];
              break;
            case 'Password':
              $Config['Password'] = $SplitLine[1];
              break;
            case 'Delay':
              $Config['Delay'] = filter_integer($SplitLine[1], 0, 3600, 30);
              break;
            case 'Suppress':
              $Config['Suppress'] = Filter_Str_Value($SplitLine[1], '');
              break;            
            case 'bl_custom':
              $Config['bl_custom'] = Filter_Str_Value($SplitLine[1], '');
              break;            
            case 'bl_notrack':
              $Config['bl_notrack'] = filter_integer($SplitLine[1], 0, 1, 1);
              break;            
            case 'bl_tld':
              $Config['bl_tld'] = filter_integer($SplitLine[1], 0, 1, 1);
              break;            
            case 'bl_qmalware':
              $Config['bl_qmalware'] = filter_integer($SplitLine[1], 0, 1, 1);
              break;            
            case 'bl_hexxium':
              $Config['bl_hexxium'] = filter_integer($SplitLine[1], 0, 1, 1);
              break;            
            case 'bl_disconnectmalvertising':
              $Config['bl_disconnectmalvertising'] = filter_integer($SplitLine[1], 0, 1, 0);
              break;            
            case 'bl_easylist':
              $Config['bl_easylist'] = filter_integer($SplitLine[1], 0, 1, 0);
              break;            
            case 'bl_easyprivacy':
              $Config['bl_easyprivacy'] = filter_integer($SplitLine[1], 0, 1, 0);
              break;            
            case 'bl_fbannoyance':
              $Config['bl_fbannoyance'] = filter_integer($SplitLine[1], 0, 1, 0);
              break;            
            case 'bl_fbenhanced':
              $Config['bl_fbenhanced'] = filter_integer($SplitLine[1], 0, 1, 0);
              break;            
            case 'bl_fbsocial':
              $Config['bl_fbsocial'] = filter_integer($SplitLine[1], 0, 1, 0);
              break;
            case 'bl_hphosts':
              $Config['bl_hphosts'] = filter_integer($SplitLine[1], 0, 1, 0);
              break;            
            case 'bl_malwaredomainlist':
              $Config['bl_malwaredomainlist'] = filter_integer($SplitLine[1], 0, 1, 0);
              break;            
            case 'bl_malwaredomains':
              $Config['bl_malwaredomains'] = filter_integer($SplitLine[1], 0, 1, 0);
              break;            
            case 'bl_pglyoyo':
              $Config['bl_pglyoyo'] = filter_integer($SplitLine[1], 0, 1, 0);
              break;            
            case 'bl_someonewhocares':
              $Config['bl_someonewhocares'] = filter_integer($SplitLine[1], 0, 1, 0);
              break;            
            case 'bl_spam404':
              $Config['bl_spam404'] = filter_integer($SplitLine[1], 0, 1, 0);
              break;
            case 'bl_swissransom':
              $Config['bl_swissransom'] = filter_integer($SplitLine[1], 0, 1, 0);
              break;
            case 'bl_swisszeus':
              $Config['bl_swisszeus'] = filter_integer($SplitLine[1], 0, 1, 0);
              break;            
            case 'bl_winhelp2002':
              $Config['bl_winhelp2002'] = filter_integer($SplitLine[1], 0, 1, 0);
              break;
            /*case 'bl_':
              $Config['bl_'] = filter_integer($SplitLine[1], 0, 1, 0);
              break;*/
            //Region Specific
            case 'bl_areasy':
              $Config['bl_areasy'] = filter_integer($SplitLine[1], 0, 1, 0);
              break;            
            case 'bl_chneasy':
              $Config['bl_chneasy'] = filter_integer($SplitLine[1], 0, 1, 0);
              break;
            case 'bl_deueasy':
              $Config['bl_deueasy'] = filter_integer($SplitLine[1], 0, 1, 0);
              break;
            case 'bl_dnkeasy':
              $Config['bl_dnkeasy'] = filter_integer($SplitLine[1], 0, 1, 0);
              break;            
            case 'bl_ruseasy':
              $Config['bl_ruseasy'] = filter_integer($SplitLine[1], 0, 1, 0);
              break;            
            case 'bl_fblatin':
              $Config['bl_fblatin'] = filter_integer($SplitLine[1], 0, 1, 0);
              break;
          }
        }
      }
    }
    
    //Set SearchUrl if User hasn't configured a custom string via notrack.conf
    if ($Config['SearchUrl'] == '') {      
      switch($Config['Search']) {
        case 'Baidu':
          $Config['SearchUrl'] = 'https://www.baidu.com/s?wd=';
          break;
        case 'Bing':
          $Config['SearchUrl'] = 'https://www.bing.com/search?q=';
          break;
        case 'DuckDuckGo':
          $Config['SearchUrl'] = 'https://duckduckgo.com/?q=';
          break;
        case 'Exalead':
          $Config['SearchUrl'] = 'https://www.exalead.com/search/web/results/?q=';
          break;
        case 'Gigablast':
          $Config['SearchUrl'] = 'https://www.gigablast.com/search?q=';
          break;
        case 'Google':
          $Config['SearchUrl'] = 'https://www.google.com/search?q=';
          break;
        case 'Ixquick':
          $Config['SearchUrl'] = 'https://ixquick.eu/do/search?q=';
          break;
        case 'Qwant':
          $Config['SearchUrl'] = 'https://www.qwant.com/?q=';
          break;
        case 'StartPage':
          $Config['SearchUrl'] = 'https://startpage.com/do/search?q=';
          break;
        case 'Yahoo':
          $Config['SearchUrl'] = 'https://search.yahoo.com/search?p=';
          break;
        case 'Yandex':
          $Config['SearchUrl'] = 'https://www.yandex.com/search/?text=';
          break;
        default:
          $Config['SearchUrl'] = 'https://duckduckgo.com/?q=';          
      }
    }
    
    //Set WhoIsUrl if User hasn't configured a custom string via notrack.conf
    if ($Config['WhoIsUrl'] == '') {      
      switch($Config['WhoIs']) {
        case 'DomainTools':
          $Config['WhoIsUrl'] = 'http://whois.domaintools.com/';
          break;
        case 'Icann':
          $Config['WhoIsUrl'] = 'https://whois.icann.org/lookup?name=';
          break;          
        case 'Who.is':
          $Config['WhoIsUrl'] = 'https://who.is/whois/';
          break;
        default:
          $Config['WhoIsUrl'] = 'https://who.is/whois/';
      }
    }
    
    fclose($fh);
    $mem->set('Config', $Config, 0, 1200);
  }
  
  return null;
}
?>

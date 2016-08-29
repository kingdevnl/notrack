<?php
//Global Functions used in NoTrack Admin
//Check Version------------------------------------------------------
function Check_Version($LatestVersion) {
  //If LatestVersion is less than Current Version then function returns false
  
  //1. Split strings by '.'
  //2. Combine back together and multiply with Units array
  //e.g 1.0 - 1x10000 + 0x100 = 10,000
  //e.g 0.8.0 - 0x10000 + 8x100 + 0x1 = 800
  //e.g 0.7.10 - 0x10000 + 7x100 + 10x1 = 710
  //3. If Latest < Current Version return Current Version
  //4. Otherwise return Latest
  
  global $Version;
  
  $NumVersion = 0;
  $NumLatest = 0;
  $Units = array(10000,100,1);
  
  $SplitVersion = explode('.', $Version);
  $SplitLatest = explode('.', $LatestVersion);
  
  for ($i = 0; $i < count($SplitVersion); $i++) {
    $NumVersion += ($Units[$i] * intval($SplitVersion[$i]));
  }
  for ($i = 0; $i < count($SplitVersion); $i++) {
    $NumLatest += ($Units[$i] * intval($SplitLatest[$i]));
  }
  
  if ($NumLatest < $NumVersion) return false;
  
  return true;
}
//Check User Session-------------------------------------------------
function Check_SessionID() {
  if (isset($_SESSION['sid'])) {
    if ($_SESSION['sid'] == 1) return true;
  }
  
  return false;
}
//Draw Sys Table-----------------------------------------------------
function DrawSysTable($Title) {
  echo '<div class="sys-group"><div class="sys-title">'.PHP_EOL;
  echo '<h5>'.$Title.'</h5></div>'.PHP_EOL;
  echo '<div class="sys-items"><table class="sys-table">'.PHP_EOL;
  
  return null;
}
//Draw Sys Table with Help Button------------------------------------
function DrawSysTableHelp($Title, $HelpPage) {
  echo '<div class="sys-group"><div class="sys-title">'.PHP_EOL;
  echo '<h5>'.$Title.'&nbsp;<a href="./help.php?p='.$HelpPage.'"><img class="btn" src="./svg/button_help.svg" alt="help"></a></h5></div>'.PHP_EOL;
  echo '<div class="sys-items"><table class="sys-table">'.PHP_EOL;
  return null;
}
//Draw Sys Row-------------------------------------------------------
function DrawSysRow($Description, $Value) {
  echo '<tr><td>'.$Description.': </td><td>'.$Value.'</td></tr>'.PHP_EOL;
  
  return null;
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
//Filter Int Value---------------------------------------------------
function Filter_Int_Value($Val, $Min, $Max, $DefaultValue=0) {
  if (is_numeric($Val)) {
    if (($Val >= $Min) && ($Val <= $Max)) {
      return intval($Val);
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
function LoadConfigFile() {
  //1. Attempt to load Config from Memcache
  //2. Write DefaultConfig to Config, incase any variables are missing
  //3. Read Config File
  //4. Split Line between = (Var = Value)
  //5. Filter each value
  //6. Setup SearchUrl
  //7. Write Config to Memcache
  //As of v0.7.16 blocklists were renamed to bl_

  global $FileConfig, $Config, $DefaultConfig, $Mem, $Version;
  
  $Config=$Mem->get('Config');                   //Load array from Memcache
  
  if (! empty($Config)) return;                  //Did it load from memory?
  
  $Config = $DefaultConfig;                      //Firstly Set Default Config
  if (file_exists($FileConfig)) {                //Check file exists      
    $FileHandle= fopen($FileConfig, 'r');
    while (!feof($FileHandle)) {
      $Line = trim(fgets($FileHandle));          //Read Line of LogFile
      if ($Line != '') {
        $SplitLine = explode('=', $Line);
        if (count($SplitLine == 2)) {          
          $SplitLine[1] = trim($SplitLine[1]);
          switch (trim($SplitLine[0])) {
            case 'LatestVersion':
              $Config['LatestVersion'] = Filter_Str_Value($SplitLine[1], $Version);
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
              $Config['Delay'] = Filter_Int_Value($SplitLine[1], 0, 3600, 30);
              break;
            case 'Suppress':
              $Config['Suppress'] = Filter_Str_Value($SplitLine[1], '');
              break;
            case 'BL_Custom': 
            case 'bl_custom':
              $Config['bl_custom'] = Filter_Str_Value($SplitLine[1], '');
              break;
            case 'BlockList_NoTrack':
            case 'bl_notrack':
              $Config['bl_notrack'] = Filter_Int_Value($SplitLine[1], 0, 1, 1);
              break;
            case 'BlockList_TLD':
            case 'bl_tld':
              $Config['bl_tld'] = Filter_Int_Value($SplitLine[1], 0, 1, 1);
              break;
            case 'BlockList_QMalware':
            case 'bl_qmalware':
              $Config['bl_qmalware'] = Filter_Int_Value($SplitLine[1], 0, 1, 1);
              break;
            case 'BlockList_Hexxium':
            case 'bl_hexxium':
              $Config['bl_hexxium'] = Filter_Int_Value($SplitLine[1], 0, 1, 1);
              break;            
            case 'BlockList_DisconnectMalvertising':
            case 'bl_disconnectmalvertising':
              $Config['bl_disconnectmalvertising'] = Filter_Int_Value($SplitLine[1], 0, 1, 0);
              break;
            case 'BlockList_EasyList':
            case 'bl_easylist':
              $Config['bl_easylist'] = Filter_Int_Value($SplitLine[1], 0, 1, 0);
              break;
            case 'BlockList_EasyPrivacy':
            case 'bl_easyprivacy':
              $Config['bl_easyprivacy'] = Filter_Int_Value($SplitLine[1], 0, 1, 0);
              break;
            case 'BlockList_FBAnnoyance':
            case 'bl_fbannoyance':
              $Config['bl_fbannoyance'] = Filter_Int_Value($SplitLine[1], 0, 1, 0);
              break;
            case 'BlockList_FBEnhanced':
            case 'bl_fbenhanced':
              $Config['bl_fbenhanced'] = Filter_Int_Value($SplitLine[1], 0, 1, 0);
              break;
            case 'BlockList_FBSocial':
            case 'bl_fbsocial':
              $Config['bl_fbsocial'] = Filter_Int_Value($SplitLine[1], 0, 1, 0);
              break;
            case 'BlockList_hpHosts':
            case 'bl_hphosts':
              $Config['bl_hphosts'] = Filter_Int_Value($SplitLine[1], 0, 1, 0);
              break;
            case 'BlockList_MalwareDomainList':
            case 'bl_malwaredomainlist':
              $Config['bl_malwaredomainlist'] = Filter_Int_Value($SplitLine[1], 0, 1, 0);
              break;
            case 'BlockList_MalwareDomains':
            case 'bl_malwaredomains':
              $Config['bl_malwaredomains'] = Filter_Int_Value($SplitLine[1], 0, 1, 0);
              break;
            case 'BlockList_PglYoyo':
            case 'bl_pglyoyo':
              $Config['bl_pglyoyo'] = Filter_Int_Value($SplitLine[1], 0, 1, 0);
              break;            
            case 'BlockList_SomeoneWhoCares':
            case 'bl_someonewhocares':
              $Config['bl_someonewhocares'] = Filter_Int_Value($SplitLine[1], 0, 1, 0);
              break;
            case 'BlockList_Spam404':
            case 'bl_spam404':
              $Config['bl_spam404'] = Filter_Int_Value($SplitLine[1], 0, 1, 0);
              break;
            case 'BlockList_SwissRansom':
            case 'bl_swissransom':
              $Config['bl_swissransom'] = Filter_Int_Value($SplitLine[1], 0, 1, 0);
              break;
            case 'BlockList_SwissZeus':
            case 'bl_swisszeus':
              $Config['bl_swisszeus'] = Filter_Int_Value($SplitLine[1], 0, 1, 0);
              break;
            case 'BlockList_Winhelp2002':
            case 'bl_winhelp2002':
              $Config['bl_winhelp2002'] = Filter_Int_Value($SplitLine[1], 0, 1, 0);
              break;
            /*case 'bl_':
              $Config['bl_'] = Filter_Int_Value($SplitLine[1], 0, 1, 0);
              break;*/
            //Region Specific
            case 'bl_areasy':
              $Config['bl_areasy'] = Filter_Int_Value($SplitLine[1], 0, 1, 0);
              break;
            case 'BlockList_CHNEasy':
            case 'bl_chneasy':
              $Config['bl_chneasy'] = Filter_Int_Value($SplitLine[1], 0, 1, 0);
              break;
            case 'bl_deueasy':
              $Config['bl_deueasy'] = Filter_Int_Value($SplitLine[1], 0, 1, 0);
              break;
            case 'bl_dnkeasy':
              $Config['bl_dnkeasy'] = Filter_Int_Value($SplitLine[1], 0, 1, 0);
              break;
            case 'BlockList_RUSEasy':
            case 'bl_ruseasy':
              $Config['bl_ruseasy'] = Filter_Int_Value($SplitLine[1], 0, 1, 0);
              break;            
            case 'bl_fblatin':
              $Config['bl_fblatin'] = Filter_Int_Value($SplitLine[1], 0, 1, 0);
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
    
    fclose($FileHandle);
    $Mem->set('Config', $Config, 0, 1200);
  }
  
  return null;
}
?>

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
//Filter String from GET---------------------------------------------
function Filter_Str($Str) {
  //1. Check Variable Exists
  //2. Check String doesn't contain !"£$%^&*()[]+=<>,|/\
  //Return True on success, and False on fail

  if (isset($_GET[$Str])) {
    if (preg_match('/[!\"£\$%\^&\*\(\)\[\]+=<>:\,\|\/\\\\]/', $_GET[$Str]) == 0) return true;    
  }
  return false;
}
//Filter URL GET-----------------------------------------------------
function Filter_URL($Str) {
  //1. Check Variable Exists
  //2. Check String Length is > 0 AND String doesn't contain !"£$%^&()+=<>,|/\
  //3. Check String matches the form of a URL "any.co"
  //Return True on success, and False on fail
  
  if (isset($_GET[$Str])) {
    if (((strlen($_GET[$Str]) > 0) && (preg_match('/[!\"£\$%\^&\(\)+=<>:\,\|\/\\\\]/', $_GET[$Str]) == 0))) {
      if (preg_match('/.*\..{2,}/', $_GET[$Str]) == 1) return true;
    }
  }
  return false;
}
//Filter URL Str-----------------------------------------------------
function Filter_URL_Str($Str) {
  //1. Check String Length is > 0 AND String doesn't contain !"£$%^&()+=<>,|/\
  //2. Check String matches the form of a URL "any.co"
  //Return True on success, and False on fail
  
  if (((strlen($Str) > 0) && (preg_match('/[!\"£\$%\^&\(\)+=<>:\,\|\/\\\\]/', $Str) == 0))) {
    if (preg_match('/.*\..{2,}/', $Str) == 1) return true;    
  }  
  return false;
}
//Load Config File---------------------------------------------------
function LoadConfigFile() {
  global $FileConfig, $Config, $Mem, $Version;
  
  $Config=$Mem->get('Config');                   //Load array from Memcache
  
  if (!$Config) {                                //Did it load from memory?
    if (file_exists($FileConfig)) {              //Check file exists
      $Config = parse_ini_file($FileConfig);     //Load array
    }
    else {
      $Config = array();                         //No config file, zero the array
    }
    //Set defult values if keys don't exist
    if (!array_key_exists('NetDev', $Config)) $Config += array('NetDev' => 'eth0');
    if (!array_key_exists('IPVersion', $Config)) $Config += array('IPVersion' => 'IPv4');
    if (!array_key_exists('Status', $Config)) $Config += array('Status' => 'Enabled');
    if (!array_key_exists('BlockMessage', $Config)) $Config += array('BlockMessage' => 'pixel');    
    if (!array_key_exists('Password', $Config)) $Config += array('Password' => '');
    if (!array_key_exists('Username', $Config)) $Config += array('Username' => '');
    if (!array_key_exists('Delay', $Config)) $Config += array('Delay' => 30);
    if (!array_key_exists('BlockList_NoTrack', $Config)) $Config += array('BlockList_NoTrack' => 1);
    if (!array_key_exists('BlockList_TLD', $Config)) $Config += array('BlockList_TLD' => 1);
    if (!array_key_exists('BlockList_QMalware', $Config)) $Config += array('BlockList_QMalware' => 1);
    if (!array_key_exists('BlockList_AdBlockManager', $Config)) $Config += array('BlockList_AdBlockManager' => 0);
    if (!array_key_exists('BlockList_DisconnectMalvertising', $Config)) $Config += array('BlockList_DisconnectMalvertising' => 0);
    if (!array_key_exists('BlockList_EasyList', $Config)) $Config += array('BlockList_EasyList' => 0);
    if (!array_key_exists('BlockList_EasyPrivacy', $Config)) $Config += array('BlockList_EasyPrivacy' => 0);
    if (!array_key_exists('BlockList_FBAnnoyance', $Config)) $Config += array('BlockList_FBAnnoyance' => 0);
    if (!array_key_exists('BlockList_FBEnhanced', $Config)) $Config += array('BlockList_FBEnhanced' => 0);
    if (!array_key_exists('BlockList_FBSocial', $Config)) $Config += array('BlockList_FBEnhanced' => 0);    
    if (!array_key_exists('BlockList_hpHosts', $Config)) $Config += array('BlockList_hpHosts' => 0);
    if (!array_key_exists('BlockList_MalwareDomainList', $Config)) $Config += array('BlockList_MalwareDomainList' => 0);
    if (!array_key_exists('BlockList_MalwareDomains', $Config)) $Config += array('BlockList_MalwareDomains' => 0);
    if (!array_key_exists('BlockList_PglYoyo', $Config)) $Config += array('BlockList_PglYoyo' => 0);    
    if (!array_key_exists('BlockList_SomeoneWhoCares', $Config)) $Config += array('BlockList_SomeoneWhoCares' => 0);
    if (!array_key_exists('BlockList_Spam404', $Config)) $Config += array('BlockList_Spam404' => 0);
    if (!array_key_exists('BlockList_Winhelp2002', $Config)) $Config += array('BlockList_Winhelp2002' => 0);
    //Region Specific BlockLists
    if (!array_key_exists('BlockList_CHNEasy', $Config)) $Config += array('BlockList_CHNEasy' => 0);
    if (!array_key_exists('BlockList_RUSEasy', $Config)) $Config += array('BlockList_RUSEasy' => 0);
    //if (!array_key_exists('', $Config)) $Config += array('' => 0);
    
    if (!array_key_exists('LatestVersion', $Config)) $Config += array('LatestVersion' => $Version); //Default to current version
    
    $Mem->set('Config', $Config, 0, 1200); 
  }
  
  return null;
}
?>

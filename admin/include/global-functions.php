<?php
//Global Functions used in NoTrack Admin
//Draw Sys Table-----------------------------------------------------
function DrawSysTable($Title) {
  echo '<div class="sys-group"><div class="sys-title">'."\n";
  echo '<h5>'.$Title.'</h5></div>'."\n";
  echo '<div class="sys-items"><table class="sys-table">'."\n";
  return null;
}
//Draw Sys Row-------------------------------------------------------
function DrawSysRow($Description, $Value) {
  echo '<tr><td>'.$Description.': </td><td>'.$Value.'</td></tr>'."\n";
  return null;
}
//Execute Action-----------------------------------------------------
function ExecAction($Action, $ExecNow, $Fork=false) {
  global $FileTmpAction;
  if (file_put_contents($FileTmpAction, $Action."\n", FILE_APPEND) === false) {
    die('Unable to write to file '.$FileTmpAction);
  }
  
  if (($ExecNow) && (! $Fork)) {
    echo "<pre>\n";
    $Msg = shell_exec('sudo ntrk-exec 2>&1');
    echo $Msg;
    echo "</pre>\n";
  }
  elseif (($ExecNow) && ($Fork)) {
    exec("sudo ntrk-exec > /dev/null &");
  }
  return null;    
}
//Filter Int from GET------------------------------------------------
function Filter_Int($Str, $Min, $Max, $Def=false) {
  //1. Check Variable Exists
  //2. Check Value is between $Min and $Max
  //3. Return Value on success, and $Def on fail
  if (is_numeric($_GET[$Str])) {
    if (($_GET[$Str] >= $Min) && ($_GET[$Str] < $Max)) {
      return intval($_GET[$Str]);
    }
  }
  return $Def;
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
      if (preg_match('/.*\..{2,}/', $_GET[$Str]) == 1)
      return true;
    }
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
    if (!array_key_exists('BlockList_NoTrack', $Config)) $Config += array('BlockList_NoTrack' => 1);
    if (!array_key_exists('BlockList_TLD', $Config)) $Config += array('BlockList_TLD' => 1);
    if (!array_key_exists('BlockList_AdBlockManager', $Config)) $Config += array('BlockList_AdBlockManager' => 0);
    if (!array_key_exists('BlockList_EasyList', $Config)) $Config += array('BlockList_EasyList' => 0);
    if (!array_key_exists('BlockList_EasyPrivacy', $Config)) $Config += array('BlockList_EasyPrivacy' => 0);
    if (!array_key_exists('BlockList_hpHosts', $Config)) $Config += array('BlockList_hpHosts' => 0);
    if (!array_key_exists('BlockList_MalwareDomains', $Config)) $Config += array('BlockList_MalwareDomains' => 0);
    if (!array_key_exists('BlockList_PglYoyo', $Config)) $Config += array('BlockList_PglYoyo' => 0);    
    if (!array_key_exists('BlockList_SomeoneWhoCares', $Config)) $Config += array('BlockList_SomeoneWhoCares' => 0);
    if (!array_key_exists('BlockList_Winhelp2002', $Config)) $Config += array('BlockList_Winhelp2002' => 0);    
    //if (!array_key_exists('', $Config)) $Config += array('' => 0);
    
    if (!array_key_exists('LatestVersion', $Config)) $Config += array('LatestVersion' => $Version); //Default to current version
    
    $Mem->set('Config', $Config, 0, 600); //1200
  }  
    
  return null;
}
?>

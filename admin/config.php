<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <link href="./css/master.css" rel="stylesheet" type="text/css" />
  <link rel="icon" type="image/png" href="./favicon.png" />
  <title>NoTrack - Settings</title>  
</head>

<body>
<div id="main">
<?php
require('./include/global-vars.php');
require('./include/global-functions.php');
$CurTopMenu = 'config';
include('./include/topmenu.html');
echo "<h1>NoTrack Config</h1>\n";

//-------------------------------------------------------------------
function DisplayConfigChoices() {
  global $Config;
  
  echo '<form action="?" method="get">';         //Block Lists
  echo '<input type="hidden" name="action" value="blocklists">';
  DrawSysTable('Block Lists');  
  $Checked='';
  if ($Config['BlockList_TLD'] == 1) $Checked='checked="checked"';
  else $Checked='';
  DrawSysRow('Top Level Domain', '<input type="checkbox" name="blocklist_tld"'.$Checked.'>');
  
  if ($Config['BlockList_PglYoyo'] == 1) $Checked='checked="checked"';
  else $Checked='';
  DrawSysRow('PglYoyo', '<input type="checkbox" name="blocklist_pglyoyo"'.$Checked.'>');
  
  echo "</table><br />\n";
  echo '<div class="centered"><input type="submit" value="Save Changes"></div>'."\n";
  echo "</div></div></form>\n";
  
  
  echo '<form action="?" method="get">';         //Web Server
  echo '<input type="hidden" name="action" value="webserver">';
  DrawSysTable('Web Server');  
  if ($Config['BlockMessage'] == 'pixel') DrawSysRow('Block Message', '<input type="radio" name="block" value="pixel" checked>1x1 Blank Pixel (default)<br /><input type="radio" name="block" value="message">Message - Blocked by NoTrack<br />');
  else DrawSysRow('Block Message', '<input type="radio" name="block" value="pixel">1x1 Blank Pixel (default)<br /><input type="radio" name="block" value="messge" checked>Message - Blocked by NoTrack<br />');
  echo "</table><br />\n";
  echo '<div class="centered"><input type="submit" value="Save Changes"></div>'."\n";
  echo "</div></div></form>\n";
  
  
  DrawSysTable('History');
  DrawSysRow('Delete All History', '<button class="button-danger" type="reset" onclick="ConfirmLogDelete();">Purge</button>');
  echo "</table></div></div>\n";

  return null;
}
//Execute Action-----------------------------------------------------
function ExecAction($Action, $ExecNow) {
  global $FileTmpAction;
  if (file_put_contents($FileTmpAction, $Action."\n", FILE_APPEND) === false) {
    die('Unable to write to file '.$FileTmpAction);
  }
  
  if ($ExecNow) {
    echo "<pre>\n";
    $Msg = shell_exec('sudo ntrk-exec 2>&1');
    #$Msg = shell_exec('sudo /home/quids/NoTrack/ntrk-exec.sh 2>&1');
    echo $Msg;
    echo "</pre>\n";
  }
  return null;
    
}
//Update Block List Config-------------------------------------------
function UpdateBlockListConfig() {
  global $Config;
  
  if (isset($_GET['blocklist_tld'])) {
    if ($_GET['blocklist_tld'] == 'on') $Config['BlockList_TLD'] = 1;    
  }
  else $Config['BlockList_TLD'] = 0;
  
  if (isset($_GET['blocklist_pglyoyo'])) {
    if ($_GET['blocklist_pglyoyo'] == 'on') $Config['BlockList_PglYoyo'] = 1;    
  }
  else $Config['BlockList_PglYoyo'] = 0;
  
  //print_r($Config);
  return null;
}
//Update Webserver Config--------------------------------------------
function UpdateWebserverConfig() {
  global $Config;
  
  if (isset($_GET['block'])) {
    switch ($_GET['block']) {
      case 'pixel':
        $Config['BlockMessage'] = 'pixel';
        ExecAction('blockmsg-pixel', false);
      break;
      case 'message':
        $Config['BlockMessage'] = 'message';
        ExecAction('blockmsg-message', false);
      break;
    }
  }
}
//Write Tmp Config File----------------------------------------------
function WriteTmpConfig() {
  global $Config, $FileTmpConfig, $Mem;
  
  $FileHandle = fopen($FileTmpConfig, 'w');      //Open temp config for writing
  foreach ($Config as $Key => $Value) {          //Loop through Config array
    fwrite($FileHandle, $Key.' = '.$Value."\n"); //Write Key & Value
  }
  fclose($FileHandle);                           //Close file
  
  $Mem->delete('Config');                        //Delete config from Memcache
}
//Main---------------------------------------------------------------

echo '<div class="pag-nav"><ul>'."\n";           //Config Menu
echo '<li class="active"><a href="./config.php" title="General">General</a></li>'."\n";
echo '<li><a href="./blocklist.php" title="Block List">Block List</a></li>'."\n";
echo '<li><a href="./tldblocklist.php" title="Top Level Domain Blocklist">TLD Block List</a></li>'."\n";
echo "</ul></div>\n";
echo '<div class="row"><br /></div>';            //Spacer

LoadConfigFile();

if (isset($_GET['action'])) {
  switch($_GET['action']) {
    case 'blocklists':
      UpdateBlockListConfig();
      WriteTmpConfig();
      ExecAction('update-config', false);
      ExecAction('run-notrack', true);
    break;
    case 'webserver':
      UpdateWebserverConfig();
      WriteTmpConfig();
      ExecAction('update-config', true);
    break;
    case 'delete-history':
      ExecAction('delete-history', true);
    break;
  }
  echo '<div class="row"><br /></div>';          //Spacer
  echo '<div class="centered">'."\n";            //Center div for button
  echo '<button class="button-blue" type="reset" onclick="window.location=\'?\'">Back</button>'."\n";
  echo "</div>\n";
}
else {
  DisplayConfigChoices();
}
?> 
</div>

<script>
function ConfirmLogDelete() {
  if (confirm("Are you sure you want to delete all History?")) window.open("?action=delete-history", "_self");
}
</script>
</body>
</html>
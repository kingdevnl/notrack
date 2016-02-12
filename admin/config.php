<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <link href="./css/master.css" rel="stylesheet" type="text/css" />
  <link rel="icon" type="image/png" href="./favicon.png" />
  <title>NoTrack - Config</title>  
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
function Checked($Var) {
  if ($Var == 1) return ' checked="checked"';
  else return '';
}
//-------------------------------------------------------------------
function DisplayConfigChoices() {
  global $Config;
  
  echo '<form action="?" method="get">';         //Block Lists
  echo '<input type="hidden" name="action" value="blocklists">';
  DrawSysTable('Block Lists');  
  DrawSysRow('NoTrack', '<input type="checkbox" name="blocklist_notrack"'.Checked($Config['BlockList_NoTrack']).'> Default List, containing mixture of Trackers and Ad sites.');
  DrawSysRow('Top Level Domain', '<input type="checkbox" name="blocklist_tld"'.Checked($Config['BlockList_TLD']).'> Whole country and generic domains.');
  DrawSysRow('AdBlock Plus EasyList', '<input type="checkbox" name="blocklist_easylist"'.Checked($Config['BlockList_EasyList']).'> Utilises a small portion of the list to block entire Ad domains.');
  DrawSysRow('EasyPrivacy', '<input type="checkbox" name="blocklist_easyprivacy"'.Checked($Config['BlockList_EasyPrivacy']).'> Supplementary list from AdBlock Plus to protect personal data.');
  DrawSysRow('AdBlock Manager', '<input type="checkbox" name="blocklist_adblockmanager"'.Checked($Config['BlockList_AdBlockManager']).'> Mostly Mobile Ad sites. Over 90% of this list is in NoTrack');
  DrawSysRow('hpHosts', '<input type="checkbox" name="blocklist_hphosts"'.Checked($Config['BlockList_hpHosts']).'> Very inefficient list containing multiple subdomains for known Ad sites.');
  DrawSysRow('Malware Domains', '<input type="checkbox" name="blocklist_malwaredomains"'.Checked($Config['BlockList_MalwareDomains']).'> A good list to add.');
  DrawSysRow('PglYoyo', '<input type="checkbox" name="blocklist_pglyoyo"'.Checked($Config['BlockList_PglYoyo']).'> Ad sites, a few are already in NoTrack.');
  DrawSysRow('Someone Who Cares', '<input type="checkbox" name="blocklist_someonewhocares"'.Checked($Config['BlockList_SomeoneWhoCares']).'> Mixture of Shock and Ad sites.');
  DrawSysRow('WinHelp 2002', '<input type="checkbox" name="blocklist_winhelp2002"'.Checked($Config['BlockList_Winhelp2002']).'> Very inefficient list containing multiple subdomains for known Ad sites.');
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
    echo $Msg;
    echo "</pre>\n";
  }
  return null;
    
}
//Update Block List Config-------------------------------------------
function UpdateBlockListConfig() {
  global $Config;
  
  if (isset($_GET['blocklist_notrack'])) {
    if ($_GET['blocklist_notrack'] == 'on') $Config['BlockList_NoTrack'] = 1;
  }
  else $Config['BlockList_NoTrack'] = 0;
  
  if (isset($_GET['blocklist_tld'])) {
    if ($_GET['blocklist_tld'] == 'on') $Config['BlockList_TLD'] = 1;
  }
  else $Config['BlockList_TLD'] = 0;
  
  if (isset($_GET['blocklist_easylist'])) {
    if ($_GET['blocklist_easylist'] == 'on') $Config['BlockList_EasyList'] = 1;
  }
  else $Config['BlockList_EasyList'] = 0;
  
  if (isset($_GET['blocklist_easyprivacy'])) {
    if ($_GET['blocklist_easyprivacy'] == 'on') $Config['BlockList_EasyPrivacy'] = 1;
  }
  else $Config['BlockList_EasyPrivacy'] = 0;
  
  if (isset($_GET['blocklist_adblockmanager'])) {
    if ($_GET['blocklist_adblockmanager'] == 'on') $Config['BlockList_AdBlockManager'] = 1;
  }
  else $Config['BlockList_AdBlockManager'] = 0;
  
  if (isset($_GET['blocklist_hphosts'])) {
    if ($_GET['blocklist_hphosts'] == 'on') $Config['BlockList_hpHosts'] = 1;
  }
  else $Config['BlockList_hpHosts'] = 0;
  
  if (isset($_GET['blocklist_malwaredomains'])) {
    if ($_GET['blocklist_malwaredomains'] == 'on') $Config['BlockList_MalwareDomains'] = 1;
  }
  else $Config['BlockList_MalwareDomains'] = 0;
  
  if (isset($_GET['blocklist_pglyoyo'])) {
    if ($_GET['blocklist_pglyoyo'] == 'on') $Config['BlockList_PglYoyo'] = 1;
  }
  else $Config['BlockList_PglYoyo'] = 0;
  
  if (isset($_GET['blocklist_someonewhocares'])) {
    if ($_GET['blocklist_someonewhocares'] == 'on') $Config['BlockList_SomeoneWhoCares'] = 1;
  }
  else $Config['BlockList_SomeoneWhoCares'] = 0;
  
  if (isset($_GET['blocklist_winhelp2002'])) {
    if ($_GET['blocklist_winhelp2002'] == 'on') $Config['BlockList_Winhelp2002'] = 1;
  }
  else $Config['BlockList_Winhelp2002'] = 0;
  
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
      ExecAction('run-notrack', false);
      echo "<pre>\n";
      echo 'Copying /tmp/notrack.conf to /etc/notrack.conf'."\n";
      echo 'Updating Blocklists...</pre>';      
      exec("sudo ntrk-exec > /dev/null &");      //Fork NoTrack process
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

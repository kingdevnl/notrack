<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <link href="./css/master.css" rel="stylesheet" type="text/css" />
  <link rel="icon" type="image/png" href="./favicon.png" />
  <script src="./include/menu.js"></script>
  <title>NoTrack - Config</title>  
</head>

<body>
<div id="main">
<?php
require('./include/global-vars.php');
require('./include/global-functions.php');
$CurTopMenu = 'config';
include('./include/topmenu.php');
echo "<h1>NoTrack Config</h1>\n";

$List=array();

//-------------------------------------------------------------------
function Checked($Var) {
  if ($Var == 1) return ' checked="checked"';
  else return '';
}
//Filter Config GET--------------------------------------------------
function Filter_Config($Str) {
  //Range: on or nothing
  //On = Return 1
  //Else = Return 0
  if (isset($_GET[$Str])) {
    if ($_GET[$Str] == 'on') return 1;    
  }
  return 0;
}
//-------------------------------------------------------------------
function LoadBlockList() {
//Blocklist is held in Memcache for 10 minutes
  global $FileBlockingCSV, $List, $Mem;
  
  ///////////////////////////////////////////////////////////////////
  //Temporary warning to cover NoTrack pre 0.7 where blocklist was in a list file
  //Remove at Beta
  if (file_exists('/etc/notrack/tracker-quick.list')) {
    echo '<h4>Warning: Legacy version of NoTrack created the blocklist</h4><br />'."\n";
    echo '<h4>Please wait a few minutes while list is regenerated</h4><br />';
    echo '<p>If this warning persists re-run: notrack --upgrade</p>';
    ExecAction('run-notrack', false);
    echo "<pre>\n";
    echo 'Updating Custom blocklists in background</pre>';      
    exec("sudo ntrk-exec > /dev/null &");      //Fork NoTrack process
    die();
    return null;
  }
  ///////////////////////////////////////////////////////////////////
  
  $List = $Mem->get('TrackerBlockList');
  if (! $TrackerBlockList) {
    $FileHandle = fopen($FileBlockingCSV, 'r') or die('Error unable to open '.$FileBlockingCSV);
    while (!feof($FileHandle)) {
      $List[] = fgetcsv($FileHandle);
    }
    
    fclose($FileHandle);
    $Mem->set('TrackerBlockList', $TrackerBlockList, 0, 600);
  }
  return null;
}
//-------------------------------------------------------------------
function LoadBlackList() {
  global $FileBlackList, $List, $Mem;
  
  $List = $Mem->get('BlackList');
  if (! $List) {
    $FileHandle = fopen($FileBlackList, 'r') or die('Error unable to open '.$FileBlackList);
    while (!feof($FileHandle)) {
      $Line = trim(fgets($FileHandle));
      if (preg_match('/.*\.[a-z]{2,}/', $Line) == 1) {
        $Seg = explode('#', $Line);
        if ($Seg[0] == '') {
          $List[] = Array(trim($Seg[1]), $Seg[2], false);
        }
        else {
          $List[] = Array(trim($Seg[0]), $Seg[1], true);
        }
        #echo $Line.'<br />';
      }
    }  
    fclose($FileHandle);  
    $Mem->set('BlackList', $List, 0, 600);
  }
  return null;
}
//-------------------------------------------------------------------
function LoadWhiteList() {
  global $FileWhiteList, $List, $Mem;
  
  $List = $Mem->get('WhiteList');
  if (! $List) {
    $FileHandle = fopen($FileWhiteList, 'r') or die('Error unable to open '.$FileWhiteList);
    while (!feof($FileHandle)) {
      $Line = trim(fgets($FileHandle));
      if (preg_match('/.*\.[a-z]{2,}/', $Line) == 1) {
        $Seg = explode('#', $Line);
        if ($Seg[0] == '') {
          $List[] = Array(trim($Seg[1]), $Seg[2], false);
        }
        else {
          $List[] = Array(trim($Seg[0]), $Seg[1], true);
        }
        #echo $Line.'<br />';
      }
    }  
    fclose($FileHandle);  
    $Mem->set('WhiteList', $List, 0, 600);
  }
  return null;
}
//-------------------------------------------------------------------
function DisplayCustomList() {
  global $List, $SearchStr, $View;
  
  echo '<div class="sys-group">';
  echo '<div class="centered"><br />'."\n";
  echo '<form action="?" method="get">';
  echo '<input type="hidden" name="v" value="'.$View.'">';
  if ($SearchStr == '') echo '<input type="text" name="s" id="search" placeholder="Search">';
  else echo '<input type="text" name="s" id="search" value="'.$SearchStr.'">';
  echo "</form></div>\n";
  
  echo '<div class="row"><br />'."\n";
  echo '<table id="block-table">'."\n";
  $i = 1;

  if ($SearchStr == '') {
    foreach ($List as $Site) {
      if ($Site[2] == true) {
        echo '<tr><td>'.$i.'</td><td>'.$Site[0].'</td><td>'.$Site[1].'<td><input type="checkbox" name="r'.$i.'" onclick="ChangeSite(this)" checked="checked"><button class="button-small"  onclick="DeleteSite('.$i.')"><span><img src="./images/icon_trash.png" class="btn" alt="-"></span></button></td></tr>'."\n";
      }
      else {
        echo '<tr class="dark"><td>'.$i.'</td><td>'.$Site[0].'</td><td>'.$Site[1].'<td><input type="checkbox" name="r'.$i.'" onclick="ChangeSite(this)"><button class="button-small"  onclick="DeleteSite('.$i.')"><span><img src="./images/icon_trash.png" class="btn" alt="-"></span></button></td></tr>'."\n";
      }
      $i++;
    }
  }
  else {
    foreach ($List as $Site) {
      if (strpos($Site[0], $SearchStr) !== false) {
        if ($Site[2] == true) {
          echo '<tr><td>'.$i.'</td><td>'.$Site[0].'</td><td>'.$Site[1].'<td><input type="checkbox" name="r'.$i.'" onclick="ChangeSite(this)" checked="checked"><button class="button-small"  onclick="DeleteSite('.$i.')"><span><img src="./images/icon_trash.png" class="btn" alt="-"></span></button></td></tr>'."\n";
        }
        else {
          echo '<tr class="dark"><td>'.$i.'</td><td>'.$Site[0].'</td><td>'.$Site[1].'<td><input type="checkbox" name="r'.$i.'" onclick="ChangeSite(this)"><button class="button-small"  onclick="DeleteSite('.$i.')"><span><img src="./images/icon_trash.png" class="btn" alt="-"></span></button></td></tr>'."\n";
        }
      }
      $i++;
    }
  }
  echo '<tr><td>'.$i.'</td><td><input type="text" name="site'.$i.'" placeholder="site.com"></td><td><input type="text" name="comment'.$i.'" placeholder="comment"></td><td><button class="button-small" onclick="AddSite('.$i.')"><span><img src="./images/green_tick.png" class="btn" alt=""></span>Save</button></td></tr>';
  
  echo "</table>\n";
  
  echo '<div class="centered"><br />'."\n";  
  if ($SearchStr == "") echo '<a href="?v='.$View.'&action='.$View.'&do=update" class="button-blue">Update Blocklists</a>';
  else echo '<a href="?v='.$View.'&s='.$SearchStr.'&action='.$View.'&do=update" class="button-blue">Update Blocklists</a>';
  echo "</div></div>\n";  
}
//-------------------------------------------------------------------
function DisplayBlockList() {
  global $List, $SearchStr;
  
  echo '<div class="sys-group">';
  echo '<div class="centered"><br />'."\n";
  echo '<form action="?" method="get">';
  echo '<input type="hidden" name="v" value="blocklist">';
  if ($SearchStr == '') echo '<input type="text" name="s" id="search" placeholder="Search">';
  else echo '<input type="text" name="s" id="search" value="'.$SearchStr.'">';
  echo "</form></div>\n";
  
  echo '<div class="row"><br />'."\n";
  echo '<form action="?" method="get">';         //Block Lists
  echo '<input type="hidden" name="action" value="sites">';
  echo '<table id="block-table">'."\n";
  $i = 1;

  if ($SearchStr == '') {
    foreach ($List as $Site) {
      if ($Site[1] == 'Active') {
        echo '<tr><td>'.$i.'</td><td>'.$Site[0].'</td><td>'.$Site[2].'<td><input type="checkbox" name="'.$Site[0].'" checked="checked"></td></tr>'."\n";
      }
      else {
        echo '<tr class="dark"><td>'.$i.'</td><td>'.$Site[0].'</td><td>'.$Site[2].'<td><input type="checkbox" name="'.$Site[0].'"></td></tr>'."\n";
      }
      $i++;
    }
  }
  else {
    foreach ($List as $Site) {
      if (strpos($Site[0], $SearchStr) !== false) {
        if ($Site[1] == 'Active') {
          echo '<tr><td>'.$i.'</td><td>'.$Site[0].'</td><td>'.$Site[2].'<td><input type="checkbox" name="'.$Site[0].'" checked="checked"></td></tr>'."\n";
        }
        else {
          echo '<tr class="dark"><td>'.$i.'</td><td>'.$Site[0].'</td><td>'.$Site[2].'<td><input type="checkbox" name="'.$Site[0].'"></td></tr>'."\n";
        }
      }
      $i++;
    }
  }
  echo "</table></div></div>\n";

}
//-------------------------------------------------------------------
function DisplayConfigChoices() {
  global $Config;
  
  echo '<form action="?" method="get">';         //Block Lists
  echo '<input type="hidden" name="action" value="blocklists">';
  DrawSysTable('Block Lists');  
  
  DrawSysRow('NoTrack', '<input type="checkbox" name="bl_notrack"'.Checked($Config['BlockList_NoTrack']).'> Default List, containing mixture of Trackers and Ad sites.');
   
  DrawSysRow('Top Level Domain', '<input type="checkbox" name="bl_tld"'.Checked($Config['BlockList_TLD']).'> Whole country and generic domains.');
  
  DrawSysRow('AdBlock Plus EasyList', '<input type="checkbox" name="bl_easylist"'.Checked($Config['BlockList_EasyList']).'> Utilises a small portion of the list to block entire Ad domains.');
  
  DrawSysRow('EasyPrivacy', '<input type="checkbox" name="bl_easyprivacy"'.Checked($Config['BlockList_EasyPrivacy']).'> Supplementary list from AdBlock Plus to protect personal data.');
  
  DrawSysRow('AdBlock Manager', '<input type="checkbox" name="bl_adblockmanager"'.Checked($Config['BlockList_AdBlockManager']).'> Mostly Mobile Ad sites. Over 90% of this list is in NoTrack');
  
  DrawSysRow('hpHosts', '<input type="checkbox" name="bl_hphosts"'.Checked($Config['BlockList_hpHosts']).'> Very inefficient list containing multiple subdomains for known Ad sites.');
  
  DrawSysRow('Malware Domains', '<input type="checkbox" name="bl_malwaredomains"'.Checked($Config['BlockList_MalwareDomains']).'> A good list to add.');
                                                                   
  DrawSysRow('PglYoyo', '<input type="checkbox" name="bl_pglyoyo"'.Checked($Config['BlockList_PglYoyo']).'> Ad sites, a few are already in NoTrack.');
  
  DrawSysRow('Someone Who Cares', '<input type="checkbox" name="bl_someonewhocares"'.Checked($Config['BlockList_SomeoneWhoCares']).'> Mixture of Shock and Ad sites.');

  DrawSysRow('WinHelp 2002', '<input type="checkbox" name="bl_winhelp2002"'.Checked($Config['BlockList_Winhelp2002']).'> Very inefficient list containing multiple subdomains for known Ad sites.');
  
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
  DrawSysRow('Delete All History', '<button class="button-danger" onclick="ConfirmLogDelete();">Purge</button>');
  echo "</table></div></div>\n";

  return null;
}
//Update Block List Config-------------------------------------------
function UpdateBlockListConfig() {
  //Read and Filter values parsed from HTTP GET into the Config array  
  //After this function WriteTmpConfig is run
  
  global $Config, $FileTmpConfig, $Mem;
    
  $Config['BlockList_NoTrack'] = Filter_Config('bl_notrack');
  $Config['BlockList_TLD'] = Filter_Config('bl_tld');
  $Config['BlockList_EasyList'] = Filter_Config('bl_easylist');
  $Config['BlockList_EasyPrivacy'] = Filter_Config('bl_easyprivacy');
  $Config['BlockList_AdBlockManager'] = Filter_Config('bl_adblockmanager');
  $Config['BlockList_hpHosts'] = Filter_Config('bl_hphosts');
  $Config['BlockList_MalwareDomains'] = Filter_Config('bl_malwaredomains');
  $Config['BlockList_PglYoyo'] = Filter_Config('bl_pglyoyo');
  $Config['BlockList_SomeoneWhoCares'] = Filter_Config('bl_someonewhocares');
  $Config['BlockList_Winhelp2002'] = Filter_Config('bl_winhelp2002');
  
  $Mem->delete('BlockList');
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
//Update Custom List-------------------------------------------------
function UpdateCustomList($ListName) {
  //Works for either BlackList or WhiteList
  global $FileTmpBlackList,$FileTmpWhiteList, $List, $Mem;
  
  if ($ListName == 'BlackList') $FileName = $FileTmpBlackList;
  elseif ($ListName == 'WhiteList') $FileName = $FileTmpWhiteList;
  else die('Error unknown option '.$ListName.' in UpdateCustomList');  
  
  if (Filter_Str('do')) {
    switch ($_GET['do']) {
    case 'add':
      if ((Filter_URL('site')) && (Filter_Str('comment'))) {      
        $List[] = Array($_GET['site'], $_GET['comment'], true);
      }
      break;
    case 'cng':
      if ((Filter_Str('site')) && (Filter_Str('status'))) {
        if (is_numeric(substr($_GET['site'],1))) {
          $RowNum = intval(substr($_GET['site'],1));
          $RowNum--;
          if (($RowNum >= 0) && ($RowNum < count($List))) {
            if ($_GET['status'] == 'true') $List[$RowNum][2] = true;
            else $List[$RowNum][2] = false;
          }
        }
      }
      break;
    case 'del':
      //Shift by one to compensate Human readable to actual Array value
      $RowNum = Filter_Int('row', 1, count($List)+1);
      if ($RowNum !== false) {
        array_splice($List, $RowNum-1, 1);       //Remove one line from List array
      }             
     break;
    case 'update':
      ExecAction('run-notrack', false);
      echo "<pre>\n";
      echo 'Updating Custom blocklists in background</pre>';      
      exec("sudo ntrk-exec > /dev/null &");      //Fork NoTrack process
      return null;
      break;
    }
  }  
  
  $FileHandle = fopen($FileName, 'w');           //Open File for writing
  fwrite($FileHandle, "#Use this file to create your own custom".$ListName."\n");
  fwrite($FileHandle, "#Run notrack script (sudo notrack) after you make any changes to this file\n");
  foreach ($List as $Line) {                     //Loop through Config array
    if ($Line[2] == true) {
      fwrite($FileHandle, $Line[0].' #'.$Line[1]."\n");
    }
    else {
      fwrite($FileHandle, '# '.$Line[0].' #'.$Line[1]."\n");
    }    
  }
  fclose($FileHandle);                           //Close file
  
  $Mem->delete($ListName);
  $Mem->set($ListName, $List, 0, 600);
  ExecAction('copy-'.strtolower($ListName), false);  
  return null;
}
//Write Tmp Config File----------------------------------------------
function WriteTmpConfig() {
  //1. Load Config File
  //2. Loop through Config Array
  //3. Write all values, except for "Status = Enabled"
  //4. Close Config File
  //5. Delete Config Array out of Memcache, in order to force reload 
  
  global $Config, $FileTmpConfig, $Mem;
  
  $FileHandle = fopen($FileTmpConfig, 'w');      //Open temp config for writing
  foreach ($Config as $Key => $Value) {          //Loop through Config array
    if (($Key != 'Status') && ($Value != 'Enabled')) { 
      fwrite($FileHandle, $Key.' = '.$Value."\n"); //Write Key & Value
    }
  }
  fclose($FileHandle);                           //Close file
  
  $Mem->delete('Config');                        //Delete config from Memcache
}
//Main---------------------------------------------------------------

$View = 'config';
if (isset($_GET['v'])) {
  switch($_GET['v']) {
    case 'config': $View = 'config'; break;
    case 'blocklist': $View = 'blocklist'; break;
    case 'blacklist': $View = 'blacklist'; break;
    case 'whitelist': $View = 'whitelist'; break;
  }
}
?>
<div class="row"><div class="pag-nav"><ul>
<li<?php if ($View=='config') echo ' class="active"';?>><a href="./config.php" title="General">General</a></li>
<li<?php if ($View=='blacklist') echo ' class="active"';?>><a href="?v=blacklist" title="Black List">Black List</a></li>
<li<?php if ($View=='whitelist') echo ' class="active"';?>><a href="?v=whitelist" title="White List">White List</a></li>
<li<?php if ($View=='blocklist') echo ' class="active"';?>><a href="?v=blocklist" title="Blocking List">Blocking List</a></li>
</ul></div></div>
<div class="row"><br /></div>

<?php
/*<li<?php if ($View=='tldlist') echo ' class="active"';?>><a href="?v=tldlist" title="Top Level Domain Blocklist">TLD List</a></li> */

LoadConfigFile();

$SearchStr = '';
if ($_GET['s']) {
  //Allow only characters a-z A-Z 0-9 ( ) . _ - and \whitespace
  $SearchStr = preg_replace('/[^a-zA-Z0-9\(\)\.\s_-]/', '', $_GET['s']);
  $SearchStr = strtolower($SearchStr);  
}

if (isset($_GET['action'])) {
  switch($_GET['action']) {
    case 'blocklists':
      UpdateBlockListConfig();
      WriteTmpConfig();
      ExecAction('update-config', false);
      ExecAction('run-notrack', false);
      echo "<pre>\n";
      echo 'Copying /tmp/notrack.conf to /etc/notrack.conf'."\n";
      echo 'Updating Blocklists in background</pre>';      
      exec("sudo ntrk-exec > /dev/null &");      //Fork NoTrack process
      DisplayConfigChoices();
      break;
    case 'webserver':
      UpdateWebserverConfig();
      WriteTmpConfig();
      ExecAction('update-config', true);
      DisplayConfigChoices();
      break;
    case 'delete-history':
      ExecAction('delete-history', true);
      DisplayConfigChoices();
      break;
    case 'blacklist':
      LoadBlackList();
      UpdateCustomList('BlackList');
      DisplayCustomList();
      break;      
    case 'whitelist':
      LoadWhiteList();
      UpdateCustomList('WhiteList');
      DisplayCustomList();
      break;
      
  }
}
else {
  switch($View) {
    case 'config':
      DisplayConfigChoices();
      break;
    case 'blocklist':
      LoadBlockList();
      DisplayBlockList();
      break;
    case 'blacklist':
      LoadBlackList();
      DisplayCustomList();
      break;
    case 'whitelist':
      LoadWhiteList();
      DisplayCustomList();
      break;
    default:
      DisplayConfigChoices();
      break;
  }
}

?> 
</div>

<script>
function getUrlVars() {
  var vars = {};
  var parts = window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi,    
  function(m,key,value) {
    vars[key] = value;
  });
  return vars;
}

function ConfirmLogDelete() {
  if (confirm("Are you sure you want to delete all History?")) window.open("?action=delete-history", "_self");
}
function AddSite(RowNum) {  
  var SiteName = document.getElementsByName('site'+RowNum)[0].value;
  var Comment = document.getElementsByName('comment'+RowNum)[0].value;
  window.open('?v='+getUrlVars()["v"]+'&action='+getUrlVars()["v"]+'&do=add&site='+SiteName+'&comment='+Comment, "_self");
}
function DeleteSite(RowNum) {
  window.open('?v='+getUrlVars()["v"]+'&action='+getUrlVars()["v"]+'&do=del&row='+RowNum, "_self");
}
function ChangeSite(Item) {
  window.open('?v='+getUrlVars()["v"]+'&action='+getUrlVars()["v"]+'&do=cng&site='+Item.name+'&status='+Item.checked, "_self");
  //alert(Item.checked);
  //alert(Item.name);
}
</script>
</body>
</html>
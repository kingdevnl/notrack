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
<nav><div id="main-menu">
  <a href="../admin"><span class="pictext"><img src="./svg/menu_home.svg" alt=""></span></a>
  <a href="../admin/config.php"><span class="pictext"><img src="./svg/menu_config.svg" alt=""><span class="dtext">General</span></span></a>
  <a href="../admin/config.php?v=blocks"><span class="pictext"><img src="./svg/menu_blocklists.svg" alt=""><span class="dtext">Block Lists</span></span></a>
  <a href="../admin/config.php?v=black"><span class="pictext"><img src="./svg/menu_black.svg" alt=""><span class="dtext">Black List</span></span></a>
  <a href="../admin/config.php?v=white"><span class="pictext"><img src="./svg/menu_white.svg" alt=""><span class="dtext">White List</span></span></a>
  <!--<a href="../admin/config.php?v=tldblack"><span class="pictext"><img src="./svg/menu_config.svg" alt=""><span class="dtext">TLD Black </span></span></a>
  <a href="../admin/config.php?v=tldwhite"><span class="pictext"><img src="./svg/menu_config.svg" alt=""><span class="dtext">TLD White </span></span></a> -->
  <a href="../admin/config.php?v=sites"><span class="pictext"><img src="./svg/menu_sites.svg" alt=""><span class="dtext">Sites Blocked</span></span></a>
</div></nav>
<?php
require('./include/global-vars.php');
require('./include/global-functions.php');
LoadConfigFile();
//include('./include/topmenu.php');

$List = array();               //Global array for all the Block Lists

//Add GET Var to Link if Variable is used----------------------------
function AddGetVar($Var) {
  global $RowsPerPage, $SearchStr, $StartPoint;
  switch ($Var) {
    case 'C':
      if ($RowsPerPage != 500) return '&amp;c='.$RowsPerPage;
      break;
    case 'S':
      if ($SearchStr != '') return '&amp;s='.$SearchStr;
      break;
    case 'Start':
      if ($StartPoint != 1) return '&amp;start='.$StartPoint;
      break;
    default:
      echo 'Invalid option in AddGetVar';
      die();
  }
  return '';
}
//Add Hidden Var to Form if Variable is used-------------------------
function AddHiddenVar($Var) {
  global $RowsPerPage, $SearchStr, $StartPoint;
  switch ($Var) {
    case 'C':
      if ($RowsPerPage != 500) return '<input type="hidden" name="c" value="'.$RowsPerPage.'" />';
      break;
    case 'S':
      if ($SearchStr != '') '<input type="hidden" name="s" value="'.$SearchStr.'" />';
      break;
    case 'Start':
      if ($StartPoint != 1) return '<input type="hidden" name="start" value="'.$StartPoint.'" />';
      break;
    default:
      echo 'Invalid option in AddHiddenVar';
      die();
  }
  return '';
}
//WriteLI Function for Pagination Boxes-------------------------------
function WriteLI($Character, $Start, $Active, $View) {
  if ($Active) {
    echo '<li class="active"><a href="?v='.$View.'&amp;start='.$Start.AddGetVar('C').AddGetVar('S').'">';
  }
  else {
    echo '<li><a href="?v='.$View.'&amp;start='.$Start.AddGetVar('C').AddGetVar('S').'">';
  }  
  echo "$Character</a></li>\n";  
  return null;
}
//Checked returns Checked if Variable is true------------------------
function Checked($Var) {
//Remove this function
  if ($Var == 1) return ' checked="checked"';
  return '';
}
//Draw BlockList Row-------------------------------------------------
function DrawBlockListRow($BL, $ConfBL, $Item, $Msg) {
  global $Config;
  
  if ($Config[$ConfBL] == 0) {
    echo '<tr><td>'.$Item.':</td><td><input type="checkbox" name="'.$BL.'"> '.$Msg.'</td></tr>'."\n";
  }
  else {
    echo '<tr><td>'.$Item.':</td><td><input type="checkbox" name="'.$BL.'" checked="checked"> '.$Msg.'</td></tr>'."\n";    
  }
  
  return null;
}
//Filter Config POST-------------------------------------------------
function Filter_Config($Str) {
  //Range: on or nothing
  //On = Return 1
  //Else = Return 0
  if (isset($_POST[$Str])) {    
    if ($_POST[$Str] == 'on') return 1;    
  }
  return 0;
}
//-------------------------------------------------------------------
function LoadSiteList() {
//This function is susceptible to race condition:
//If the User changes the BlockLists and switches to this view while NoTrack is processing $FileBlockingCSV is incomplete.
//To combat this race condition Lists below 100 lines aren't stored in Memcache.
//Large lists are held in Memcache for 5 minutes

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
  
  //$List = $Mem->get('SiteList');
  
  if (! $List) {
    //$List[] = array('Null', 'Active', 'Null');  //Bump start point to 1
    $FileHandle = fopen($FileBlockingCSV, 'r') or die('Error unable to open '.$FileBlockingCSV);
    while (!feof($FileHandle)) {
      $List[] = fgetcsv($FileHandle);
    }
    
    fclose($FileHandle);
    if (count($List) > 100) {  //Only store decent size list in Memcache
      $Mem->set('SiteList', $List, 0, 240);      //4 Minutes
    }
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
      }
    }  
    fclose($FileHandle);  
    $Mem->set('BlackList', $List, 0, 60);
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
      }
    }  
    fclose($FileHandle);
    
    $Mem->set('WhiteList', $List, 0, 60);
  }
  return null;
}
//-------------------------------------------------------------------
function DisplayBlockLists() {
  global $Config;

  echo '<form action="?v=blocks" method="post">';         //Block Lists
  echo '<input type="hidden" name="action" value="blocklists">';
  DrawSysTable('Block Lists');  
  
  DrawBlockListRow('bl_notrack', 'BlockList_NoTrack', 'NoTrack', 'Default List, containing mixture of Trackers and Ad sites.'); 
  
  DrawBlockListRow('bl_tld', 'BlockList_TLD', 'Top Level Domain', 'Whole country and generic domains.');
  
  echo '<tr><th colspan="2">Ad Block</th></tr>';
  DrawBlockListRow('bl_easylist', 'BlockList_EasyList', 'EasyList', 'EasyList without element hiding rules‎ <a href="https://forums.lanik.us/">(forums.lanik.us)</a>');
  
  DrawBlockListRow('bl_pglyoyo', 'BlockList_PglYoyo', 'Peter Lowe&rsquo;s Ad server list‎', 'Some of this list is already in NoTrack <a href="https://pgl.yoyo.org/adservers/">(pgl.yoyo.org)</a>'); 
  
  DrawBlockListRow('bl_adblockmanager', 'BlockList_AdBlockManager', 'AdBlock Manager', 'Mostly Mobile Ad sites. Over 90% of this list is in NoTrack');
  
  echo '<tr><th colspan="2">Privacy</th></tr>';
  DrawBlockListRow('bl_easyprivacy', 'BlockList_EasyPrivacy', 'EasyPrivacy', 'Supplementary list from AdBlock Plus <a href="https://forums.lanik.us/">(forums.lanik.us)</a>');
  
  DrawBlockListRow('bl_fbenhanced', 'BlockList_FBEnhanced', 'Fanboy&rsquo;s Enhanced Tracking List', 'Blocks common tracking scripts <a href="https://www.fanboy.co.nz/">(fanboy.co.nz)</a>');
    
  echo '<tr><th colspan="2">Malware domains</th></tr>';
  DrawBlockListRow('bl_malwaredomains', 'BlockList_MalwareDomains', 'Malware Domains', 'A good list to add <a href="http://www.malwaredomains.com/">(malwaredomains.com)</a>');
  
  echo '<tr><th colspan="2">Social</th></tr>';
  DrawBlockListRow('bl_fbannoyance', 'BlockList_FBAnnoyance', 'Fanboy&rsquo;s Annoyance List', 'Block Pop-Ups and other annoyances. <a href="https://www.fanboy.co.nz/">(fanboy.co.nz)</a>');
  
  DrawBlockListRow('bl_fbsocial', 'BlockList_FBSocial', 'Fanboy&rsquo;s Social Blocking List', 'Block social content, widgets, scripts and icons. <a href="https://www.fanboy.co.nz">(fanboy.co.nz)</a>');
  
  echo '<tr><th colspan="2">Multipurpose</th></tr>';
  DrawBlockListRow('bl_someonewhocares', 'BlockList_SomeoneWhoCares', 'Dan Pollock&rsquo;s hosts file', 'Mixture of Shock and Ad sites. <a href="http://someonewhocares.org/hosts">(someonewhocares.org)</a>');
  
  DrawBlockListRow('hpHosts', 'BlockList_hpHosts', 'hpHosts', 'Very inefficient list <a href="http://hosts-file.net">(hosts-file.net)</a>');
                                             
  DrawBlockListRow('bl_winhelp2002', 'BlockList_Winhelp2002', 'MVPS Hosts‎', 'Very inefficient list <a href="http://winhelp2002.mvps.org/">(winhelp2002.mvps.org)</a>');
  
  echo "</table><br />\n";
  
  echo '<div class="centered"><input type="submit" value="Save Changes"></div>'."\n";
  echo "</div></div></form>\n";
  
  return null;
}
//-------------------------------------------------------------------
function DisplayConfigChoices() {
  global $Config;
  
  echo '<form action="?" method="post">';        //Web Server
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
//-------------------------------------------------------------------
function DisplayCustomList($View) {
  global $List, $SearchStr;
  
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
function DisplaySiteList() {
  global $List, $SearchStr, $StartPoint, $RowsPerPage;
  
  //1. Check if a Search has been made
  //2a. Loop through List doing a strpos check to see if search string exists in List[x][0] (site name)
  //2b. Copy items to a Temp Array
  //2c. Copy Temp array back to List
  //2d. Delete Temp array
  //3. Draw page
  
  if ($SearchStr != '') {                        //Is user searching?
    $TempArray = array();
    foreach ($List as $Site) {                   //Go through array
      if (strpos($Site[0], $SearchStr) !== false) {
        $TempArray[] = $Site;                    //Copy matching string to temp array
      }
    }
    $List = $TempArray;                          //Copy temp array to List
    unset($TempArray);                           //Delete temp array
  }
  
  $ListSize = count($List);
  
  if ($List[$ListSize-1][0] == '') {             //Last line is sometimes blank
    array_splice($List, $ListSize-1);            //Cut last line out
  }
  
  if ($StartPoint >= $ListSize) $StartPoint = 1; //Start point can't be greater than the list size
  
  if ($RowsPerPage < $ListSize) {                //Slice array if it's larger than RowsPerPage
    $List = array_slice($List, $StartPoint, $RowsPerPage);
  }
  
  echo '<div class="sys-group">';
  echo '<div class="centered">'."\n";
  echo '<form action="?" method="get">';
  echo '<input type="hidden" name="v" value="sites">';
  echo AddHiddenVar('C');
  
  if ($SearchStr == '') echo '<input type="text" name="s" id="search" placeholder="Search">';
  else echo '<input type="text" name="s" id="search" value="'.$SearchStr.'">';
  echo "</form></div>\n";
  
  if ($ListSize > $RowsPerPage) {               //Is Pagination needed
    echo '<br /><div class="row">';
    DisplayPagination($ListSize, 'sites');
    echo "</div>\n";
  }
  echo "</div>\n";
  
  echo '<div class="sys-group">';
  
  if ($ListSize == 0) {
    echo 'No sites found in Block List'."\n";
    echo '</div>';
    return;
  }
  
  echo '<table id="block-table">'."\n";
  
  $i = $StartPoint;

  foreach ($List as $Site) {    
    if ($Site[1] == 'Active') {
      echo '<tr><td>'.$i.'</td><td>'.$Site[0].'</td><td>'.$Site[2].'<td><input type="checkbox" name="'.$Site[0].'" checked="checked"></td></tr>'."\n";
    }
    else {
      echo '<tr class="dark"><td>'.$i.'</td><td>'.$Site[0].'</td><td>'.$Site[2].'<td><input type="checkbox" name="'.$Site[0].'"></td></tr>'."\n";
    }
    $i++;
  }
  
  echo "</table></div></div>\n";
  
  if ($ListSize > $RowsPerPage) {               //Is Pagination needed
    echo '<div class="sys-group">';
    DisplayPagination($ListSize, 'sites');
    echo "</div>\n";
  }
  
  return null;
}
//-------------------------------------------------------------------
function DisplayPagination($LS, $View) {
  global $RowsPerPage, $StartPoint;

  $ListSize = ceil($LS / $RowsPerPage);         //Calculate List Size
  $CurPos = 0;
  
  while ($CurPos < $ListSize) {                  //Find Current Page
    $CurPos++;
    if ($StartPoint < $CurPos * $RowsPerPage) {
      break;					 //Leave loop when found
    }
  }
  
  echo '<div class="pag-nav"><ul>'."\n";
  
  
  if ($CurPos == 1) {                            //At the beginning display blank box
    echo '<li><span>&nbsp;&nbsp;</span></li>';
    echo "\n";
    WriteLI('1', 0, true, $View);
  }    
  else {                                         // << Symbol & Print Box 1
    WriteLI('&#x00AB;', $RowsPerPage * ($CurPos - 2), false, $View);
    WriteLI('1', 0, false, $View);
  }

  if ($ListSize <= 4) {                          //Small Lists don't need fancy effects
    for ($i = 2; $i <= $ListSize; $i++) {	 //List of Numbers
      if ($i == $CurPos) {
        WriteLI($i, $RowsPerPage * ($i - 1), true, $View);
      }
      else {
        WriteLI($i, $RowsPerPage * ($i - 1), false, $View);
      }
    }
  }
  elseif ($ListSize > 4 && $CurPos == 1) {       // < [1] 2 3 4 T >
    WriteLI('2', $RowsPerPage, false, $View);
    WriteLI('3', $RowsPerPage * 2, false, $View);
    WriteLI('4', $RowsPerPage * 3, false, $View);
    WriteLI($ListSize, ($ListSize - 1) * $RowsPerPage, false, $View);
  }
  elseif ($ListSize > 4 && $CurPos == 2) {       // < 1 [2] 3 4 T >
    WriteLI('2', $RowsPerPage, true, $View);
    WriteLI('3', $RowsPerPage * 2, false, $View);
    WriteLI('4', $RowsPerPage * 3, false, $View);
    WriteLI($ListSize, ($ListSize - 1) * $RowsPerPage, false, $View);
  }
  elseif ($ListSize > 4 && $CurPos > $ListSize - 2) {// < 1 T-3 T-2 T-1 T > 
    for ($i = $ListSize - 3; $i <= $ListSize; $i++) {//List of Numbers
      if ($i == $CurPos) {
        WriteLI($i, $RowsPerPage * ($i - 1), true, $View);
      }
      else {
        WriteLI($i, $RowsPerPage * ($i - 1), false, $View);
    	}
      }
    }
  else {                                         // < 1 c-1 [c] c+1 T >
    for ($i = $CurPos - 1; $i <= $CurPos + 1; $i++) {//List of Numbers
      if ($i == $CurPos) {
        WriteLI($i, $RowsPerPage * ($i - 1), true, $View);
      }
      else {
        WriteLI($i, $RowsPerPage * ($i - 1), false, $View);
      }
    }
    WriteLI($ListSize, ($ListSize - 1) * $RowsPerPage, false, $View);
  }
  
  if ($CurPos < $ListSize) {                     // >> Symbol for Next
    WriteLI('&#x00BB;', $RowsPerPage * $CurPos, false, $View);
  }	
  echo "</ul></div>\n";
}
//Update Block List Config-------------------------------------------
function UpdateBlockListConfig() {
  //Read and Filter values parsed from HTTP POST into the Config array  
  //After this function WriteTmpConfig is run
  
  global $Config, $FileTmpConfig, $Mem;
    
  $Config['BlockList_NoTrack'] = Filter_Config('bl_notrack');
  $Config['BlockList_TLD'] = Filter_Config('bl_tld');
  $Config['BlockList_AdBlockManager'] = Filter_Config('bl_adblockmanager');
  $Config['BlockList_EasyList'] = Filter_Config('bl_easylist');
  $Config['BlockList_EasyPrivacy'] = Filter_Config('bl_easyprivacy');
  $Config['BlockList_FBAnnoyance'] = Filter_Config('bl_fbannoyance');
  $Config['BlockList_FBEnhanced'] = Filter_Config('bl_fbenhanced');
  $Config['BlockList_FBSocial'] = Filter_Config('bl_fbsocial');
  $Config['BlockList_hpHosts'] = Filter_Config('bl_hphosts');
  $Config['BlockList_MalwareDomains'] = Filter_Config('bl_malwaredomains');
  $Config['BlockList_PglYoyo'] = Filter_Config('bl_pglyoyo');
  $Config['BlockList_SomeoneWhoCares'] = Filter_Config('bl_someonewhocares');
  $Config['BlockList_Winhelp2002'] = Filter_Config('bl_winhelp2002');
  
  //print_r($Config); 
  return null;
}
//Update Webserver Config--------------------------------------------
function UpdateWebserverConfig() {
  global $Config;
  
  if (isset($_POST['block'])) {
    switch ($_POST['block']) {
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
    if ($Key == 'Status') {
      if ($Value != 'Enabled') {
        fwrite($FileHandle, $Key.' = '.$Value."\n");//Write Key & Value
      }
    }
    else {
      fwrite($FileHandle, $Key.' = '.$Value."\n");  //Write Key & Value
    }
  }
  fclose($FileHandle);                           //Close file
  
  $Mem->delete('Config');                        //Delete config from Memcache  
}
//Main---------------------------------------------------------------

$SearchStr = '';
if ($_GET['s']) {
  //Allow only characters a-z A-Z 0-9 ( ) . _ - and \whitespace
  $SearchStr = preg_replace('/[^a-zA-Z0-9\(\)\.\s_-]/', '', $_GET['s']);
  $SearchStr = strtolower($SearchStr);  
}

$StartPoint = Filter_Int('start', 1, PHP_INT_MAX-2, 1);

$RowsPerPage = Filter_Int('c', 2, PHP_INT_MAX, 500); //Rows per page

if (isset($_POST['action'])) {
  switch($_POST['action']) {
    case 'blocklists':
      UpdateBlockListConfig();
      WriteTmpConfig();
      ExecAction('update-config', false);
      ExecAction('run-notrack', true, true);
      $Mem->delete('SiteList');
      echo "<pre>\n";
      echo 'Copying /tmp/notrack.conf to /etc/notrack.conf'."\n";
      echo 'Updating Blocklists in background</pre>';
      break;
    case 'webserver':      
      UpdateWebserverConfig();
      WriteTmpConfig();
      ExecAction('update-config', true, true);
      break;    
    default:
      echo 'Unknown POST action';
      die();
  }
}
  

if (isset($_GET['action'])) {
  switch($_GET['action']) {
    case 'delete-history':
      ExecAction('delete-history', true);
      DisplayConfigChoices();
      break;
    case 'black':
      LoadBlackList();
      UpdateCustomList('BlackList');      
      break;      
    case 'white':
      LoadWhiteList();
      UpdateCustomList('WhiteList');      
      break;
      
  }
}

if (isset($_GET['v'])) {
  switch($_GET['v']) {
    case 'config':
      DisplayConfigChoices();
      break;
    case 'blocks':
      DisplayBlockLists();
      break;
    case 'black':
      LoadBlackList();
      DisplayCustomList('black');
      break;
    case 'white':
      LoadWhiteList();
      DisplayCustomList('white');
      break;
    case 'tldblack':
      //LoadTLDBlackList();
      DisplayCustomList('tldblack');
      break;
    case 'tldwhite':
      //LoadTLDWhiteList();
      DisplayCustomList('tldwhite');
      break;
    case 'sites':
      LoadSiteList();
      DisplaySiteList();
      break;
    default:
      DisplayConfigChoices();
      break;
  }
}
else {
  DisplayConfigChoices();
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
}
</script>
</body>
</html>
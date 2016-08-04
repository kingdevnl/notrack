<?php
/********************************************************************
Config.php is split out into 7 sub pages:
 1. General
 2. Block Lists
 3. Black List
 4. White List
 5. Domain List
 6. Sites Blocked
 7. Advanced

Domain List
  Reading:
    1. Load tld.csv
      tld.csv is updated with NoTrack upgrade. tld.csv contains risk level of each domain.
    2. Load users Domain White List
    3. Load users Domain Black List
    4. Display Domain List
      High risk domains are ticked, unless they're added to the white list
      Medium & Low risk domains are unticked, unless they're added to the black list
    5. Domain List contains a form with POST request. When submitted all ticked boxes (named after each domain) are sent with hidden value action=tld
  
  Updating:
    1. Load tld.csv
    2. Write White list, based on any unticked (not present in POST) high risk domains.
    3. Write Black list, based on all ticked (present in POST) domains.
    4. ExecAction to copy Black and White lists into /etc/notrack
    5. sleep for 1 second to prevent race condition
    6. Return to Reading
    
Advanced
  Reading:
    1. Display options
    (Future option - Use JS to validate list as its being typed)
  
  Updating:
    1. Validate list of suppressed sites
    2. Write to file

********************************************************************/

require('./include/global-vars.php');
require('./include/global-functions.php');
require('./include/topmenu.php');

LoadConfigFile();
if ($Config['Password'] != '') {
  session_start();
  if (! Check_SessionID()) {
    header("Location: ./login.php");
    exit;
  }
}

//-------------------------------------------------------------------
$List = array();               //Global array for all the Block Lists

//List of Selectable Search engines, corresponds with Global-Functions.php -> LoadConfigFile()
$SearchEngineList = array(
 'Baidu',
 'Bing',
 'DuckDuckGo',
 'Exalead',
 'Gigablast',
 'Google',
 'Ixquick',
 'Qwant',
 'StartPage',
 'Yahoo',
 'Yandex'
);

//List of Selectable Who Is sites, corresponds with Global-Functions.php -> LoadConfigFile()
$WhoIsList = array(
 'DomainTools',
 'Icann',
 'Who.is'
);

//-------------------------------------------------------------------
//Deal with POST actions first, that way we can reload the page and remove POST requests from browser history.
if (isset($_POST['action'])) {
  switch($_POST['action']) {
    case 'advanced':
      if (UpdateAdvancedConfig()) {              //Are users settings valid?
        WriteTmpConfig();                        //If ok, then write the Config file
        ExecAction('update-config', true, true);
        sleep(1);                                //Short pause to prevent race condition
      }      
      header('Location: ?v=advanced');           //Reload page
      break;
    case 'blocklists':
      UpdateBlockListConfig();
      WriteTmpConfig();
      ExecAction('update-config', false);
      ExecAction('run-notrack', true, true);
      //ExecAction('update-config', true, true);
      $Mem->delete('SiteList');                  //Delete Site Blocked from Memcache
      sleep(1);                                  //Short pause to prevent race condition
      header('Location: ?v=blocks');             //Reload page
      break;
    case 'webserver':      
      UpdateWebserverConfig();
      WriteTmpConfig();
      ExecAction('update-config', true, true);
      header('Location: ?');
      break;
    case 'security':
      if (UpdateSecurityConfig()) {        
        WriteTmpConfig();
        ExecAction('update-config', true, true);
        if (session_status() == PHP_SESSION_ACTIVE) session_destroy();
        header('Location: ?');
      }
      break;
    case 'stats':
      if (UpdateStatsConfig()) {
        WriteTmpConfig();
        ExecAction('update-config', true, true);
        sleep(1);                                //Short pause to prevent race condition
        header('Location: ?');
      }
      break;
    case 'tld':
      Load_CSV($CSVTld, 'CSVTld');               //Load tld.csv
      UpdateDomainList();
      ExecAction('copy-tldblacklist', false);
      ExecAction('copy-tldwhitelist', true, true);
      $Mem->delete('TLDBlackList');              //Delete Black List from Memcache
      $Mem->delete('TLDWhiteList');              //Delete White List from Memcache
      sleep(1);                                  //Prevent race condition
      header('Location: ?v=tld');                //Reload page
      break;
    default:      
      die('Unknown POST action');
  }
}
//-------------------------------------------------------------------
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <link href="./css/master.css" rel="stylesheet" type="text/css" />
  <link rel="icon" type="image/png" href="./favicon.png" />
  <script src="./include/config.js"></script>
  <script src="./include/menu.js"></script>
  <title>NoTrack - Config</title>  
</head>

<body>
<?php
ActionTopMenu();
DrawTopMenu();
DrawConfigMenu();

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
    echo '<li class="active"><a href="?v='.$View.'&amp;start='.$Start.AddGetVar('C').AddGetVar('S').'">'.$Character.'</a></li>'.PHP_EOL;
  }
  else {
    echo '<li><a href="?v='.$View.'&amp;start='.$Start.AddGetVar('C').AddGetVar('S').'">'.$Character.'</a></li>'.PHP_EOL;
  }  
  
  return null;
}
//Draw BlockList Row-------------------------------------------------
function DrawBlockListRow($BL, $ConfBL, $Item, $Msg) {
  global $Config, $DirEtc, $DirTmp, $CSVTld;
  //Txt File = Origniating download file
  //Csv File = Processed file
  //TLD Is a special case, and the Txt file used is $CSVTld
  
  
  if ($Config[$ConfBL] == 0) {
    echo '<tr><td>'.$Item.':</td><td><input type="checkbox" name="'.$BL.'"> '.$Msg.'</td></tr>'.PHP_EOL;
  }
  else {
    $CsvFile = false;
    $CsvLines = 0;
    $TxtFile = false;
    $TxtLines = 0;
    $FileName = '';
    $TotalStr = '';  
    
    $FileName = strtolower(substr($ConfBL, 10));
    if ($ConfBL == 'BlockList_TLD') $TxtFileName = $CSVTld;
    else $TxtFileName = $DirTmp.$FileName.'.txt';
    
    $CSVFileName = $DirEtc.$FileName.'.csv';    
    
    $CsvFile = file_exists($CSVFileName);
    $TxtFile = file_exists($TxtFileName);
    
    if ($CsvFile && $TxtFile) {
      $CsvLines = intval(exec('wc -l '.$CSVFileName));
      $TxtLines = intval(exec('wc -l '.$TxtFileName));
      if ($CsvLines > $TxtLines) $CsvLines = $TxtLines; //Prevent stupid result
      $TotalStr = '<p class="light">'.$CsvLines.' used of '.$TxtLines.'</p>';
    }
    elseif ($CsvFile) {
      $CsvLines = intval(exec('wc -l '.$CSVFileName));
      $TotalStr = '<p class="light">'.$CsvLines.' used of ?</p>';
    }
    elseif ($TxtFile) {
      $TxtLines = intval(exec('wc -l '.$TxtFileName));
      $TotalStr = '<p class="light">? used of '.$TxtLines.'</p>';
    }
   
    echo '<tr><td>'.$Item.':</td><td><input type="checkbox" name="'.$BL.'" checked="checked"> '.$Msg.' '.$TotalStr.'</td></tr>'.PHP_EOL;    
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
function LoadCustomList($ListName, $FileName) {
  //Loads a Black or White List from File into $List Array and respective Memcache array
  //Returns true on completion
  
  global $List, $Mem;
  
  $List = $Mem->get($ListName);
  
  if (empty($List)) {
    $FileHandle = fopen($FileName, 'r') or die('Error unable to open '.$FileName);
    while (!feof($FileHandle)) {
      $Line = trim(fgets($FileHandle));
      
      if (Filter_URL_Str($Line)) {
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
    $Mem->set($ListName, $List, 0, 60);
  }
  
  return true;  
}  
//-------------------------------------------------------------------
function Load_CSV($FileName, $ListName) {
  global $List, $Mem;
    
  //$List = $Mem->get($ListName);
  if (empty($List)) {
    $FileHandle = fopen($FileName, 'r') or die('Error unable to open '.$FileName);
    while (!feof($FileHandle)) {
      $List[] = fgetcsv($FileHandle);
    }
    
    fclose($FileHandle);
    if (count($List) > 50) {                     //Only store decent size list in Memcache
      $Mem->set($ListName, $List, 0, 600);       //10 Minutes
    }
  }
  
  return true;
}
//-------------------------------------------------------------------
function Load_List($FileName, $ListName) {
  global $Mem;
  
  $FileArray = array();
  
  $FileArray = $Mem->get($ListName);
  if (empty($FileArray)) {
    if (file_exists($FileName)) {                //Check if File Exists
      $FileHandle = fopen($FileName, 'r') or die('Error unable to open '.$FileName);
      while (!feof($FileHandle)) {
        $FileArray[] = trim(fgets($FileHandle));
      }
      fclose($FileHandle);
      $Mem->set($ListName, $FileArray, 0, 600);  //Change to 1800
    }
  }
  //else echo 'cache';
  return $FileArray;
}
//-------------------------------------------------------------------
function DisplayAdvanced() {
  global $Config;
  echo '<form action="?v=advanced" method="post">'.PHP_EOL;
  echo '<input type="hidden" name="action" value="advanced">';
  DrawSysTable('Advanced Settings');
  DrawSysRow('Suppress Domains <img class="btn" src="./svg/button_help.svg" alt="help" title="Group together certain domains on the Stats page">', '<textarea rows="5" name="suppress">'.str_replace(',', PHP_EOL, $Config['Suppress']).'</textarea>');
  echo '<tr><td colspan="2"><div class="centered"><input type="submit" value="Save Changes"></div></td></tr>'.PHP_EOL;
  echo '</table>'.PHP_EOL;
  echo '</div></div>'.PHP_EOL;
  echo '</form>'.PHP_EOL;
}
//-------------------------------------------------------------------
function DisplayBlockLists() {
  global $Config;

  echo '<form action="?v=blocks" method="post">';         //Block Lists
  echo '<input type="hidden" name="action" value="blocklists">';
  DrawSysTable('Block Lists');  
  
  DrawBlockListRow('bl_notrack', 'BlockList_NoTrack', 'NoTrack', 'Default List, containing mixture of Trackers and Ad sites.'); 
  
  DrawBlockListRow('bl_tld', 'BlockList_TLD', 'Top Level Domain', 'Whole country and generic domains.');
  
  DrawBlockListRow('bl_qmalware', 'BlockList_QMalware', 'Malware Sites', 'New list which seperates Malware sites from Tracker sites.');
  
  echo '<tr><th colspan="2">Ad Block</th></tr>';
  DrawBlockListRow('bl_easylist', 'BlockList_EasyList', 'EasyList', 'EasyList without element hiding rules‎ <a href="https://forums.lanik.us/">(forums.lanik.us)</a>');
  
  DrawBlockListRow('bl_pglyoyo', 'BlockList_PglYoyo', 'Peter Lowe&rsquo;s Ad server list‎', 'Some of this list is already in NoTrack <a href="https://pgl.yoyo.org/adservers/">(pgl.yoyo.org)</a>'); 
  
  DrawBlockListRow('bl_adblockmanager', 'BlockList_AdBlockManager', 'AdBlock Manager', 'Mostly Mobile Ad sites. Over 90% of this list is in NoTrack');
  
  echo '<tr><th colspan="2">Privacy</th></tr>';
  DrawBlockListRow('bl_easyprivacy', 'BlockList_EasyPrivacy', 'EasyPrivacy', 'Supplementary list from AdBlock Plus <a href="https://forums.lanik.us/">(forums.lanik.us)</a>');
  
  DrawBlockListRow('bl_fbenhanced', 'BlockList_FBEnhanced', 'Fanboy&rsquo;s Enhanced Tracking List', 'Blocks common tracking scripts <a href="https://www.fanboy.co.nz/">(fanboy.co.nz)</a>');
    
  echo '<tr><th colspan="2">Malware</th></tr>';
  DrawBlockListRow('bl_hexxium', 'BlockList_Hexxium', 'Hexxium Creations Malware List', 'Hexxium Creations are a small independent team running a community based malware database <a href="https://hexxiumcreations.com/domain-ip-threat-database/">(hexxiumcreations.com)</a>');
  
  DrawBlockListRow('bl_dismalvertising', 'BlockList_DisconnectMalvertising', 'Malvertising list by Disconnect', '<a href="https://disconnect.me/">(disconnect.me)</a>');
  DrawBlockListRow('bl_maldomainlist', 'BlockList_MalwareDomainList', 'Malware Domain List', '<a href="http://www.malwaredomainlist.com/">(malwaredomainlist.com)</a>');
  DrawBlockListRow('bl_malwaredomains', 'BlockList_MalwareDomains', 'Malware Domains', 'A good list to add <a href="http://www.malwaredomains.com/">(malwaredomains.com)</a>');
  
  DrawBlockListRow('bl_spam404', 'BlockList_Spam404', 'Spam404', '<a href="http://www.spam404.com/">(www.spam404.com)</a>');
  
  DrawBlockListRow('bl_swissransom', 'BlockList_SwissRansom', 'Swiss Security - Ransomware Tracker', 'Protects against downloads of several variants of Ransomware, including Cryptowall and TeslaCrypt <a href="https://ransomwaretracker.abuse.ch/">(abuse.ch)</a>');
  
  DrawBlockListRow('bl_swisszeus', 'BlockList_SwissZeus', 'Swiss Security - ZeuS Tracker', 'Protects systems infected with ZeuS malware from accessing Command & Control servers <a href="https://zeustracker.abuse.ch/">(abuse.ch)</a>');
  
  echo '<tr><th colspan="2">Social</th></tr>';
  DrawBlockListRow('bl_fbannoyance', 'BlockList_FBAnnoyance', 'Fanboy&rsquo;s Annoyance List', 'Block Pop-Ups and other annoyances. <a href="https://www.fanboy.co.nz/">(fanboy.co.nz)</a>');
  
  DrawBlockListRow('bl_fbsocial', 'BlockList_FBSocial', 'Fanboy&rsquo;s Social Blocking List', 'Block social content, widgets, scripts and icons. <a href="https://www.fanboy.co.nz">(fanboy.co.nz)</a>');
  
  echo '<tr><th colspan="2">Multipurpose</th></tr>';
  DrawBlockListRow('bl_someonewhocares', 'BlockList_SomeoneWhoCares', 'Dan Pollock&rsquo;s hosts file', 'Mixture of Shock and Ad sites. <a href="http://someonewhocares.org/hosts">(someonewhocares.org)</a>');
  
  DrawBlockListRow('bl_hphosts', 'BlockList_hpHosts', 'hpHosts', 'Inefficient list <a href="http://hosts-file.net">(hosts-file.net)</a>');
                                             
  DrawBlockListRow('bl_winhelp2002', 'BlockList_Winhelp2002', 'MVPS Hosts‎', 'Very inefficient list <a href="http://winhelp2002.mvps.org/">(winhelp2002.mvps.org)</a>');
  
  echo '<tr><th colspan="2">Region Specific</th></tr>';
  DrawBlockListRow('bl_chneasy', 'BlockList_CHNEasy', 'CHN EasyList', 'EasyList China (中文)‎ <a href="http://abpchina.org/forum/forum.php">(abpchina.org)</a>');
  
  DrawBlockListRow('bl_ruseasy', 'BlockList_RUSEasy', 'RUS EasyList', 'Russia RuAdList+EasyList (Россия Фильтр) <a href="https://forums.lanik.us/viewforum.php?f=102">(forums.lanik.us)</a>');
  
  echo '<tr><th colspan="2">Custom</th></tr>';
  DrawSysRow('Custom Block Lists', 'Use either Downloadable or Localy stored Block Lists<br /><textarea rows="5" name="bl_custom">'.str_replace(',', PHP_EOL,$Config['BL_Custom']).'</textarea>');
  
  echo '</table><br />'.PHP_EOL;
  
  echo '<div class="centered"><input type="submit" value="Save Changes"></div>'.PHP_EOL;
  echo '</div></div></form>'.PHP_EOL;
  
  return null;
}
//-------------------------------------------------------------------
function DisplayConfigChoices() {
  global $Config, $DirOldLogs, $Version, $SearchEngineList, $WhoIsList;
  
  $Load = sys_getloadavg();
  $FreeMem = preg_split('/\s+/', exec('free -m | grep Mem'));

  $PS_Dnsmasq = preg_split('/\s+/', exec('ps -eo fname,pid,stime,pmem | grep dnsmasq'));

  $PS_Lighttpd = preg_split('/\s+/', exec('ps -eo fname,pid,stime,pmem | grep lighttpd'));

  $fi = new FilesystemIterator($DirOldLogs, FilesystemIterator::SKIP_DOTS);
  
  
  DrawSysTable('Server');
  DrawSysRow('Name', gethostname());
  DrawSysRow('Network Device', $Config['NetDev']);
  if (($Config['IPVersion'] == 'IPv4') || ($Config['IPVersion'] == 'IPv6')) {
    DrawSysRow('Internet Protocol', $Config['IPVersion']);
    DrawSysRow('IP Address', $_SERVER['SERVER_ADDR']);
  }
  else {
    DrawSysRow('IP Address', $Config['IPVersion']);
  }
  
  DrawSysRow('Sysload', $Load[0].' | '.$Load[1].' | '.$Load[2]);
  DrawSysRow('Memory Used', $FreeMem[2].' MB');
  DrawSysRow('Free Memory', $FreeMem[3].' MB');
  DrawSysRow('Uptime', exec('uptime -p | cut -d \  -f 2-'));
  DrawSysRow('NoTrack Version', $Version); 
  echo '</table></div></div>'.PHP_EOL;
  
  DrawSysTable('Dnsmasq');
  if ($PS_Dnsmasq[0] != null) DrawSysRow('Status','Dnsmasq is running');
  else DrawSysRow('Status','Inactive');
  DrawSysRow('Pid', $PS_Dnsmasq[1]);
  DrawSysRow('Started On', $PS_Dnsmasq[2]);
  //DrawSysRow('Cpu', $PS_Dnsmasq[3]);
  DrawSysRow('Memory Used', $PS_Dnsmasq[3].' MB');
  DrawSysRow('Historical Logs', iterator_count($fi).' Days');
  DrawSysRow('Delete All History', '<button class="button-danger" onclick="ConfirmLogDelete();">Purge</button>');
  echo '</table></div></div>'.PHP_EOL;

  
  //Web Server
  echo '<form name="blockmsg" action="?" method="post">';
  echo '<input type="hidden" name="action" value="webserver">';
  DrawSysTable('Lighttpd');
  if ($PS_Lighttpd[0] != null) DrawSysRow('Status','Lighttpd is running');
  else DrawSysRow('Status','Inactive');
  DrawSysRow('Pid', $PS_Lighttpd[1]);
  DrawSysRow('Started On', $PS_Lighttpd[2]);
  //DrawSysRow('Cpu', $PS_Lighttpd[3]);
  DrawSysRow('Memory Used', $PS_Lighttpd[3].' MB');
  if ($Config['BlockMessage'] == 'pixel') DrawSysRow('Block Message', '<input type="radio" name="block" value="pixel" checked onclick="document.blockmsg.submit()">1x1 Blank Pixel (default)<br /><input type="radio" name="block" value="message" onclick="document.blockmsg.submit()">Message - Blocked by NoTrack<br />');
  else DrawSysRow('Block Message', '<input type="radio" name="block" value="pixel" onclick="document.blockmsg.submit()">1x1 Blank Pixel (default)<br /><input type="radio" name="block" value="messge" checked onclick="document.blockmsg.submit()">Message - Blocked by NoTrack<br />');  
  echo '</table></div></div></form>'.PHP_EOL;

  
  //Stats
  echo '<form name="stats" action="?" method="post">';
  echo '<input type="hidden" name="action" value="stats">';
  
  DrawSysTable('Domain Stats');
  echo '<tr><td>Search Engine: </td>'.PHP_EOL;
  echo '<td><select name="search" onchange="submit()">'.PHP_EOL;
  echo '<option value="'.$Config['Search'].'">'.$Config['Search'].'</option>'.PHP_EOL;
  foreach ($SearchEngineList as $Site) {
    if ($Site != $Config['Search']) {
      echo '<option value="'.$Site.'">'.$Site.'</option>'.PHP_EOL;
    }
  }
  echo '</select></td></tr>'.PHP_EOL;
  
  echo '<tr><td>Who Is Lookup: </td>'.PHP_EOL;
  echo '<td><select name="whois" onchange="submit()">'.PHP_EOL;
  echo '<option value="'.$Config['WhoIs'].'">'.$Config['WhoIs'].'</option>'.PHP_EOL;
  foreach ($WhoIsList as $Site) {
    if ($Site != $Config['WhoIs']) {
      echo '<option value="'.$Site.'">'.$Site.'</option>'.PHP_EOL;
    }
  }
  echo '</select></td></tr>'.PHP_EOL;
  
  echo '</table></div></div></form>'.PHP_EOL;    //End Stats
  
  
  //Security
  echo '<form name="security" action="?" method="post">';
  echo '<input type="hidden" name="action" value="security">';
  DrawSysTableHelp('Security', 'security');
  DrawSysRow('NoTrack Username', '<input type="text" name="username" value="'.$Config['Username'].'"><p><i>Optional authentication username.</i></p>');
  DrawSysRow('NoTrack Password', '<input type="password" name="password"><p><i>Optional authentication password.</i></p>');
  DrawSysRow('Delay', '<input type="number" name="delay" min="10" max="2400" value="'.$Config['Delay'].'"><p><i>Delay in seconds between attempts.</i></p>');
  echo '<tr><td colspan="2"><div class="centered"><input type="submit" value="Save Changes"></div></td></tr>';
  
  echo '</table></div></div></form>'.PHP_EOL;
  
  return null;
}
//-------------------------------------------------------------------
function DisplayCustomList($View) {
  global $List, $SearchStr;
  
  echo '<div class="sys-group"><div class="sys-title">'.PHP_EOL;
  echo '<h5>'.ucfirst($View).' List</h5>'.PHP_EOL;
  echo '</div>'.PHP_EOL;
  echo '<div class="sys-items">'.PHP_EOL;
  echo '<div class="centered">'.PHP_EOL;
  echo '<form action="?" method="get">';
  echo '<input type="hidden" name="v" value="'.$View.'">';
  if ($SearchStr == '') echo '<input type="text" name="s" id="search" placeholder="Search">'.PHP_EOL;
  else echo '<input type="text" name="s" id="search" value="'.$SearchStr.'">'.PHP_EOL;
  echo '</form>'.PHP_EOL;
  echo '</div></div></div>'.PHP_EOL;
  
  echo '<div class="sys-group">';
  echo '<div class="row"><br />'.PHP_EOL;
  echo '<table id="block-table">'.PHP_EOL;
  $i = 1;

  if ($SearchStr == '') {
    foreach ($List as $Site) {
      if ($Site[2] == true) {
        echo '<tr><td>'.$i.'</td><td>'.$Site[0].'</td><td>'.$Site[1].'<td><input type="checkbox" name="r'.$i.'" onclick="ChangeSite(this)" checked="checked"><button class="button-small"  onclick="DeleteSite('.$i.')"><span><img src="./images/icon_trash.png" class="btn" alt="-"></span></button></td></tr>'.PHP_EOL;
      }
      else {
        echo '<tr class="dark"><td>'.$i.'</td><td>'.$Site[0].'</td><td>'.$Site[1].'<td><input type="checkbox" name="r'.$i.'" onclick="ChangeSite(this)"><button class="button-small"  onclick="DeleteSite('.$i.')"><span><img src="./images/icon_trash.png" class="btn" alt="-"></span></button></td></tr>'.PHP_EOL;
      }
      $i++;
    }
  }
  else {
    foreach ($List as $Site) {
      if (strpos($Site[0], $SearchStr) !== false) {
        if ($Site[2] == true) {
          echo '<tr><td>'.$i.'</td><td>'.$Site[0].'</td><td>'.$Site[1].'<td><input type="checkbox" name="r'.$i.'" onclick="ChangeSite(this)" checked="checked"><button class="button-small"  onclick="DeleteSite('.$i.')"><span><img src="./images/icon_trash.png" class="btn" alt="-"></span></button></td></tr>'.PHP_EOL;
        }
        else {
          echo '<tr class="dark"><td>'.$i.'</td><td>'.$Site[0].'</td><td>'.$Site[1].'<td><input type="checkbox" name="r'.$i.'" onclick="ChangeSite(this)"><button class="button-small"  onclick="DeleteSite('.$i.')"><span><img src="./images/icon_trash.png" class="btn" alt="-"></span></button></td></tr>'.PHP_EOL;
        }
      }
      $i++;
    }
  }
  
  echo '<tr><td>'.$i.'</td><td><input type="text" name="site'.$i.'" placeholder="site.com"></td><td><input type="text" name="comment'.$i.'" placeholder="comment"></td><td><button class="button-small" onclick="AddSite('.$i.')"><span><img src="./images/green_tick.png" class="btn" alt=""></span>Save</button></td></tr>';
        
  echo '</table></div></div>'.PHP_EOL;
  
  echo '<div class="sys-group"><div class="centered">'.PHP_EOL;  
  echo '<a href="./include/downloadlist.php?v='.$View.'" class="button-grey">Download List</a>&nbsp;&nbsp;';
  echo '<a href="?v='.$View.AddGetVar('S').'&amp;action='.$View.'&amp;do=update" class="button-blue">Update Blocklists</a>';
  echo '</div></div>'.PHP_EOL;  
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
  echo '<h5>Sites Blocked</h5>'.PHP_EOL;
  echo '<div class="centered">'.PHP_EOL;
  echo '<form action="?" method="get">';
  echo '<input type="hidden" name="v" value="sites">';
  echo AddHiddenVar('C');
  
  if ($SearchStr == '') echo '<input type="text" name="s" id="search" placeholder="Search">';
  else echo '<input type="text" name="s" id="search" value="'.$SearchStr.'">';
  echo '</form></div>'.PHP_EOL;
  
  if ($ListSize > $RowsPerPage) {               //Is Pagination needed
    echo '<br /><div class="row">';
    DisplayPagination($ListSize, 'sites');
    echo '</div>'.PHP_EOL;
  }
  echo '</div>'.PHP_EOL;
  
  echo '<div class="sys-group">';
  
  if ($ListSize == 0) {
    echo 'No sites found in Block List'.PHP_EOL;
    echo '</div>';
    return;
  }
  
  echo '<table id="block-table">'.PHP_EOL;
  
  $i = $StartPoint;

  foreach ($List as $Site) {    
    if ($Site[1] == 'Active') {
      echo '<tr><td>'.$i.'</td><td>'.$Site[0].'</td><td>'.$Site[2].'<td></td></tr>'.PHP_EOL;
    }
    else {
      echo '<tr class="dark"><td>'.$i.'</td><td><s>'.$Site[0].'</s></td><td><s>'.$Site[2].'</s><td></td></tr>'.PHP_EOL;
    }
    $i++;
  }
  
  echo '</table></div>'.PHP_EOL;
  
  if ($ListSize > $RowsPerPage) {               //Is Pagination needed
    echo '<div class="sys-group">';
    DisplayPagination($ListSize, 'sites');
    echo '</div>'.PHP_EOL;
  }
  
  return null;
}
//-------------------------------------------------------------------
function DisplayPagination($LS, $View) {
  global $RowsPerPage, $StartPoint;

  $ListSize = ceil($LS / $RowsPerPage);          //Calculate List Size
  $CurPos = floor($StartPoint / $RowsPerPage)+ 1;//Calculate Current Position
  
  echo '<div class="pag-nav"><ul>'.PHP_EOL;
  
  
  if ($CurPos == 1) {                            //At the beginning display blank box
    echo '<li><span>&nbsp;&nbsp;</span></li>'.PHP_EOL;    
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
  echo '</ul></div>'.PHP_EOL;
}
//-------------------------------------------------------------------
function DisplayDomainList() {
  global $List, $FileTLDBlackList, $FileTLDWhiteList;
  
  //1. Load Users Domain Black list and convert into associative array
  //2. Load Users Domain White list and convert into associative array
  //3. Display list
  
  $KeyBlack = array_flip(Load_List($FileTLDBlackList, 'TLDBlackList'));
  $KeyWhite = array_flip(Load_List($FileTLDWhiteList, 'TLDWhiteList'));
  $ListSize = count($List);
  
  if ($List[$ListSize-1][0] == '') {             //Last line is sometimes blank
    array_splice($List, $ListSize-1);            //Cut last line out
  }
  
  $FlagImage = '';  
  $UnderscoreName = '';

  echo '<div class="sys-group"><div class="sys-title">'.PHP_EOL;
  echo '<h5>Domain Blocking</h5>'.PHP_EOL;
  echo '</div>'.PHP_EOL;
  echo '<div class="sys-items">'.PHP_EOL;
  echo '<span class="key key-red">High</span>'.PHP_EOL;
  echo '<p>High risk domains are home to a high percentage of Malicious sites compared to legitimate sites. Often they are cheap / free to buy and not well policed.<br />'.PHP_EOL;
  echo 'High risk domains are automatically blocked, unless you specifically untick them.</p>'.PHP_EOL;
  echo '<br />'.PHP_EOL;
  
  echo '<span class="key key-orange">Medium</span>'.PHP_EOL;
  echo '<p>Medium risk domains are home to a significant number of malicious sites, but are outnumbered by legitimate sites. You may want to consider blocking these, unless you live in, or utilise the websites of the affected country.</p>'.PHP_EOL;
  echo '<p>e.g. .pl (Poland) domain used to house a large number of Exploit kits which sat short lived randomly named sites. Traditional blocking is impossible, therefore it can be safer to block the entire .pl domain.</p>'.PHP_EOL;
  echo '<br />'.PHP_EOL;
  
  echo '<span class="key">Low</span>'.PHP_EOL;
  echo '<p>Low risk may still house some malicious sites, but they are vastly outnumbered by legitimate sites.</p>'.PHP_EOL;
  echo '<br />'.PHP_EOL;
  
  echo '<span class="key key-green">Negligible</span>'.PHP_EOL;
  echo '<p>These domains are not open to the public, therefore extremely unlikely to contain malicious sites.</p>'.PHP_EOL;
  echo '<br />'.PHP_EOL;
  
  echo '<p><b>Shady Domains</b><br />'.PHP_EOL;
  echo 'Stats of &quot;Shady&quot; Domains have been taken from <a href="https://www.bluecoat.com/security/security-blog">BlueCoat Security Blog</a>. The definition of Shady includes Malicious, Spam, Suspect, and Adult sites.</p>';  
  
  echo '</div></div>'.PHP_EOL;
  
  
  //Tables
  echo '<div class="sys-group">'.PHP_EOL;
  if ($ListSize == 0) {                          //Is List blank?
    echo 'No sites found in Block List'.PHP_EOL; //Yes, display error, then leave
    echo '</div>';
    return;
  }
  
  echo '<form name="tld" action="?" method="post">'.PHP_EOL;
  echo '<input type="hidden" name="action" value="tld">'.PHP_EOL;
    
  echo '<p><b>Old Generic Domains</b></p>'.PHP_EOL;
  echo '<table class="tld-table">'.PHP_EOL;
  
  foreach ($List as $Site) {
    if ($Site[2] == 0) {                         //Zero means draw new table
      echo '</table>'.PHP_EOL;                   //End old table
      echo '<br />'.PHP_EOL;
      echo '<p><b>'.$Site[1].'</b></p>'.PHP_EOL; //Title of Table
      echo '<table class="tld-table">'.PHP_EOL;  //New Table
      continue;                                  //Jump to end of loop
    }
    
    switch ($Site[2]) {                          //Row colour based on risk
      case 1: echo '<tr class="invalid">'; break;
      case 2: echo '<tr class="orange">'; break;      
      case 3: echo '<tr>'; break;                //Use default colour for low risk
      case 5: echo '<tr class="green">'; break;
    }
    
    $UnderscoreName = str_replace(' ', '_', $Site[1]); //Flag names are seperated by underscore
    
    //Does a Flag image exist?
    if (file_exists('./images/flags/Flag_of_'.$UnderscoreName.'.png')) $FlagImage = '<img src="./images/flags/Flag_of_'.$UnderscoreName.'.png" alt=""> ';
    else $FlagImage = '';
    
    //(Risk 1 & NOT in White List) OR (in Black List)
    if ((($Site[2] == 1) && (! array_key_exists($Site[0], $KeyWhite))) || (array_key_exists($Site[0], $KeyBlack))) {
      echo '<td><b>'.$Site[0].'</b></td><td><b>'.$FlagImage.$Site[1].'</b></td><td>'.$Site[3].'</td><td><input type="checkbox" name="'.substr($Site[0], 1).'" checked="checked"></td></tr>'.PHP_EOL;
    }
    else {
      echo '<td>'.$Site[0].'</td><td>'.$FlagImage.$Site[1].'</td><td>'.$Site[3].'</td><td><input type="checkbox" name="'.substr($Site[0], 1).'"></td></tr>'.PHP_EOL;
    }    
  }
  
  echo '</table>'.PHP_EOL;
  echo '<div class="centered"><br />'.PHP_EOL;
  echo '<input type="submit" value="Save Changes">'.PHP_EOL;
  echo '</div>'.PHP_EOL;
  echo '</form></div>'.PHP_EOL;          //End table
  
  return null;
}
//-------------------------------------------------------------------
function UpdateAdvancedConfig() {
  //1. Make sure Suppress list is valid
  // 1a. Replace new line and space with commas
  // 1b. If string too short, set to '' then leave
  // 1c. Copy Valid URL's to a ValidList array
  // 1d. Write valid URL's to Config Suppress string seperated by commas 
  global $Config;
  
  $SuppressStr = '';
  $SuppressList = array();
  $ValidList = array();
  if (isset($_POST['suppress'])) {
    $SuppressStr = preg_replace('#\s+#',',',trim($_POST['suppress'])); //Split array
    if (strlen($SuppressStr) <= 2) {             //Is string too short?
      $Config['Suppress'] = '';
      return true;
    }
    
    $SuppressList = explode(',', $SuppressStr);  //Split string into array
    foreach ($SuppressList as $Site) {           //Check if each item is a valid URL
      if (Filter_URL_Str($Site)) {
        $ValidList[] = $Site;
      }
    }
    if (sizeof($ValidList) == 0) $Config['Suppress'] = '';
    else $Config['Suppress'] = implode(',', $ValidList);
  }
  
  return true;
}
//Update Block List Config-------------------------------------------
function UpdateBlockListConfig() {
  //Read and Filter values parsed from HTTP POST into the Config array  
  //After this function WriteTmpConfig is run
  
  global $Config;
  $CustromStr = '';
  $CustromList = array();
  $ValidList = array();
    
  $Config['BlockList_NoTrack'] = Filter_Config('bl_notrack');
  $Config['BlockList_TLD'] = Filter_Config('bl_tld');
  $Config['BlockList_QMalware'] = Filter_Config('bl_qmalware');
  $Config['BlockList_Hexxium'] = Filter_Config('bl_hexxium');
  $Config['BlockList_AdBlockManager'] = Filter_Config('bl_adblockmanager');
  $Config['BlockList_DisconnectMalvertising'] = Filter_Config('bl_dismalvertising');
  $Config['BlockList_EasyList'] = Filter_Config('bl_easylist');
  $Config['BlockList_EasyPrivacy'] = Filter_Config('bl_easyprivacy');
  $Config['BlockList_FBAnnoyance'] = Filter_Config('bl_fbannoyance');
  $Config['BlockList_FBEnhanced'] = Filter_Config('bl_fbenhanced');
  $Config['BlockList_FBSocial'] = Filter_Config('bl_fbsocial');
  $Config['BlockList_hpHosts'] = Filter_Config('bl_hphosts');
  $Config['BlockList_MalwareDomainList'] = Filter_Config('bl_maldomainlist');
  $Config['BlockList_MalwareDomains'] = Filter_Config('bl_malwaredomains');  
  $Config['BlockList_PglYoyo'] = Filter_Config('bl_pglyoyo');
  $Config['BlockList_SomeoneWhoCares'] = Filter_Config('bl_someonewhocares');
  $Config['BlockList_Spam404'] = Filter_Config('bl_spam404');
  $Config['BlockList_SwissRansom'] = Filter_Config('bl_swissransom');
  $Config['BlockList_SwissZeus'] = Filter_Config('bl_swisszeus');
  $Config['BlockList_Winhelp2002'] = Filter_Config('bl_winhelp2002');
  $Config['BlockList_CHNEasy'] = Filter_Config('bl_chneasy');
  $Config['BlockList_RUSEasy'] = Filter_Config('bl_ruseasy');
  
  if (isset($_POST['bl_custom'])) {    
    $CustomStr = preg_replace('#\s+#',',',trim($_POST['bl_custom'])); //Split array
    $CustomList = explode(',', $CustomStr);      //Split string into array
    foreach ($CustomList as $Site) {             //Check if each item is a valid URL
      if (Filter_URL_Str($Site)) {
        $ValidList[] = $Site;
      }
    }
    if (sizeof($ValidList) == 0) $Config['BL_Custom'] = '';
    else $Config['BL_Custom'] = implode(',', $ValidList);
  }
  else {
    $Config['BL_Custom'] = "";
  }
    
  return null;
}
//Update Custom List-------------------------------------------------
function UpdateCustomList($LongName, $ListName) {
  //Works for either BlackList or WhiteList
  
  //1. Appropriate list should have already have been loaded into $List Array
  //2. Find out what value has been requested on GET &do=
  //2a. Add Site using site name, and comment to end of $List array
  //2b. Change whether Site is enabled or disabled using Row number of $List array
  //2c. Delete Site from $List array by using Row number
  //2d. Change whether Site is enabled or disabled using name
  //2e. Error and leave function if any other value is given
  //3. Open File for writing in /tmp/"listname".txt
  //4. Write $List array to File
  //5. Delete $List array from Memcache
  //6. Write $List array with changes from #2 to Memcache
  //7. Run ntrk-exec as Forked process
  //8. Onward process is to DisplayCustomList function
  
  global $DirTmp, $List, $Mem;
  
  $LowercaseLongName = strtolower($LongName);
  
  if (Filter_Str('do')) {
    switch ($_GET['do']) {
      case 'add':
        if ((Filter_URL('site')) && (Filter_Str('comment'))) {      
          $List[] = Array($_GET['site'], $_GET['comment'], true);
        }
        break;
      case 'cng':
        //Shift by one to compensate Human readable to actual Array value
        $RowNum = Filter_Int('row', 1, count($List)+1);
        if (($RowNum !== false) && (isset($_GET['status']))) {
          $RowNum--;
          $List[$RowNum][2] = Filter_Bool('status');
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
        echo '<pre>Updating Custom blocklists in background</pre>'.PHP_EOL;
        ExecAction('run-notrack', true, true);
        return null;
        break;    
      default:
        echo 'Invalid request in UpdateCustomList'.PHP_EOL;
        return false;
    }
  }
  else {
    echo 'No request specified in UpdateCustomList'.PHP_EOL;
    return false;
  }
  
  //Open file /tmp/listname.txt for writing
  $FileHandle = fopen($DirTmp.$LowercaseLongName.'.txt', 'w');
  
  //Write Usage Instructions to top of File
  fwrite($FileHandle, "#Use this file to create your own custom ".$LongName.PHP_EOL);
  fwrite($FileHandle, '#Run notrack script (sudo notrack) after you make any changes to this file'.PHP_EOL);
  
  foreach ($List as $Line) {                     //Write List Array to File
    if ($Line[2] == true) {                      //Is site enabled?
      fwrite($FileHandle, $Line[0].' #'.$Line[1].PHP_EOL);
    }
    else {                                       //Site disabled, comment it out by preceding Line with #
      fwrite($FileHandle, '# '.$Line[0].' #'.$Line[1].PHP_EOL);
    }    
  }
  fclose($FileHandle);                           //Close file
  
  $Mem->delete($ListName);
  $Mem->set($ListName, $List, 0, 60);
  
  ExecAction('copy-'.$LowercaseLongName, true, true);
  
  return null;
}
//Update Stats Config------------------------------------------------
function UpdateStatsConfig() {
  global $Config, $SearchEngineList, $WhoIsList;
  
  $Updated = false;
  
  if (isset($_POST['search'])) {
    if (in_array($_POST['search'], $SearchEngineList)) {
      $Config['Search'] = $_POST['search'];
      $Config['SearchUrl'] = '';
      $Updated = true;
    }
  }
  
  if (isset($_POST['whois'])) {    
    if (in_array($_POST['whois'], $WhoIsList)) {
      $Config['WhoIs'] = $_POST['whois'];
      $Config['WhoIsUrl'] = '';
      $Updated = true;
    }
  }
  return $Updated;
}
//-------------------------------------------------------------------
function UpdateDomainList() {
  global $DirTmp, $List;
  
  //Start with White List
  $FileHandle = fopen($DirTmp.'domain-whitelist.txt', 'w') or die('Unable to open '.$DirTmp.'domain-whitelist.txt');
  
  fwrite($FileHandle, '#Domain White list generated by config.php'.PHP_EOL);
  
  foreach ($List as $Site) {                     //Generate White list based on unticked Risk 1 items
    if ($Site[2] == 1) {
      if (! isset($_POST[substr($Site[0], 1)])) { //Check POST for domain minus preceding .
        fwrite($FileHandle, $Site[0].PHP_EOL);   //Add domain to White list
      }
    }
  }
  fclose($FileHandle);                           //Close White List
  
  //Write Black List
  $FileHandle = fopen($DirTmp.'domain-blacklist.txt', 'w') or die('Unable to open '.$DirTmp.'domain-blacklist.txt');
    
  fwrite($FileHandle, '#Domain Block list generated by config.php'.PHP_EOL);
  fwrite($FileHandle, '#Do not make any changes to this file'.PHP_EOL);
  
  foreach ($_POST as $Key => $Value) {           //Generate Black list based on ticked items in $_POST
    if ($Value == 'on') fwrite($FileHandle, '.'.$Key.PHP_EOL); //Add each item of POST of value is "on"
  }
  fclose($FileHandle);                           //Close Black List
}
//Update Security Config---------------------------------------------
function UpdateSecurityConfig() {
  global $Config;
  
  if ((! isset($_POST['username']) && (! isset($_POST['password'])))) return false;
  
  $UserName = $_POST['username'];
  $Password = $_POST['password'];
  
  if (preg_match('/[!\"£\$%\^&\*\(\)\[\]+=<>:\,\|\/\\\\]/', $UserName) != 0) return false;
  
  if (($UserName == '') && ($Password == '')) {    
    $Config['Username'] = '';
    $Config['Password'] = '';
  }
  else {  
    $Config['Username'] = $UserName;
    $Config['Password'] = password_hash($Password, PASSWORD_DEFAULT);
    $Config['Delay'] = Filter_Int_Post('delay', 10, 2401, 30);
  }
  
  return true;
}
//Update Webserver Config--------------------------------------------
function UpdateWebserverConfig() {
  global $Config;
  
  //1. Config should already be in Memcache
  //2. Has POST request block got a value?
  //3. Run ntrk-exec with appropriate change to Webserver setting
  //4. Onward process is WriteTmpConfig function
  
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
//Write Tmp Config File----------------------------------------------
function WriteTmpConfig() {
  //1. Check if Latest Version is less than Current Version
  //2. Open Temp Config file for writing
  //3. Loop through Config Array
  //4. Write all values, except for "Status = Enabled"
  //5. Close Config File
  //6. Delete Config Array out of Memcache, in order to force reload
  //7. Onward process is to Display appropriate config view
  
  global $Config, $FileTmpConfig, $Mem, $Version;  
  
  //Prevent wrong version being written to config file if user has just upgraded and old LatestVersion is still stored in Memcache
  if (Check_Version($Config['LatestVersion'])) $Config['LatestVersion'] = $Version;
  
  $FileHandle = fopen($FileTmpConfig, 'w');      //Open temp config for writing
  
  foreach ($Config as $Key => $Value) {          //Loop through Config array
    if ($Key == 'Status') {
      if ($Value != 'Enabled') {
        fwrite($FileHandle, $Key.' = '.$Value.PHP_EOL);//Write Key & Value
      }
    }
    else {
      fwrite($FileHandle, $Key.' = '.$Value.PHP_EOL);  //Write Key & Value
    }
  }
  fclose($FileHandle);                           //Close file
  
  $Mem->delete('Config');                        //Delete config from Memcache
}
//Main---------------------------------------------------------------

$SearchStr = '';
if (isset($_GET['s'])) {
  //Allow only characters a-z A-Z 0-9 ( ) . _ - and \whitespace
  $SearchStr = preg_replace('/[^a-zA-Z0-9\(\)\.\s_-]/', '', $_GET['s']);
  $SearchStr = strtolower($SearchStr);  
}

$StartPoint = Filter_Int('start', 1, PHP_INT_MAX-2, 1);

$RowsPerPage = Filter_Int('c', 2, PHP_INT_MAX, 500); //Rows per page


if (isset($_GET['action'])) {
  switch($_GET['action']) {
    case 'delete-history':
      ExecAction('delete-history', true);
      DisplayConfigChoices();
      break;
    case 'black':
      LoadCustomList('black', $FileBlackList);
      UpdateCustomList('BlackList', 'black');
      //DisplayCustomList('black');
      break;
    case 'white':
      LoadCustomList('white', $FileWhiteList);
      UpdateCustomList('WhiteList', 'white');
      break;
    case 'tldblack':
      LoadCustomList('tldblack', $FileTLDBlackList);
      UpdateCustomList('TLDBlackList', 'tldblack');
      break;
    case 'tldwhite':
      LoadCustomList('tldwhite', $FileTLDWhiteList);
      UpdateCustomList('TLDWhiteList', 'tldwhite');
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
      LoadCustomList('black', $FileBlackList);
      DisplayCustomList('black');
      break;
    case 'white':
      LoadCustomList('white', $FileWhiteList);
      DisplayCustomList('white');
      break;
    case 'tld':
      Load_CSV($CSVTld, 'CSVTld');
      DisplayDomainList();     
      break;
    case 'sites':
      Load_CSV($CSVBlocking, 'SitesBlocked');
      DisplaySiteList();
      break;
    case 'advanced':
      DisplayAdvanced();
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
</body>
</html>

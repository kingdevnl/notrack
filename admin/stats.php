<?php
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
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <link href="./css/master.css" rel="stylesheet" type="text/css" />
  <link rel="icon" type="image/png" href="./favicon.png" />
  <script src="./include/menu.js"></script>
  <script src="./include/stats.js"></script>
  <title>NoTrack - DNS Stats</title>
</head>

<body>
<div id="main">
<?php
ActionTopMenu();
DrawTopMenu();

$DomainList = array();
$SortedDomainList = array();
$TLDBlockList = array();
$CommonSites = array();                          //Merge Common sites list with Users Suppress list
$CommonSitesList = array('cloudfront.net','googleusercontent.com','googlevideo.com','cedexis-radar.net','gvt1.com','deviantart.net','deviantart.com','stackexchange.com', 'tumblr.com');
//CommonSites referres to websites that have a lot of subdomains which aren't necessarily relivent. In order to improve user experience we'll replace the subdomain of these sites with "*"
//cloudfront.net - Very popular CDN, hard to back trace originating site
//googleusercontent.com - Google+ and YouTube user content
//googlevideo.com - True links to YouTube videos
//cedexis-radar.net - Blocked tracker that uses different subdomain per site they provide tracking services for
//gvt1.com - Google Play Store
//deviantart.net - Image download from deviatart
//deviantart.com - Each user has a different subdomain on deviantart.com
//stackexchange.com - Community Q&A, opens a lot of subdomains per visit
//tumblr.com - Each blog is on a different subdomain

//ReturnURL - Gives a simplier formatted URL for displaying----------
function ReturnURL($Str) {
  //Conditions:
  //1: Drop www (its unnecessary and not all websites use it now)
  //2. Extract domain.tld, including double-barrelled domains
  //3. Check if site is to be suppressed (present in Common sites)
  global $CommonSites;
    
  if (substr($Str,0,4) == 'www.') $Site = substr($Str,4); 
  else $Site = $Str;
  
  if (preg_match('/[A-Za-z1-9\-]{2,63}\.(org\.|co\.|com\.)?[A-Za-z1-9\-]{2,63}$/', $Site, $Match) == 1) {
    if (in_array($Match[0],$CommonSites)) return '*.'.$Match[0];
    else return $Site;
  }
 
  return $Site;
}
//Add GET Var to Link if Variable is used----------------------------
function AddGetVar($Var) {
  global $DateRange, $StartStr, $RowsPerPage, $SortCol, $SortDir, $View;
  switch ($Var) {
    case 'C':
      if ($RowsPerPage != 500) return '&amp;c='.$RowsPerPage;
    break;
    case 'Dir':
      if ($SortDir == 1) return '&amp;dir=1';
    break;
    case 'DR':
      if ($DateRange != 1) return '&amp;dr='.$DateRange;
    break;
    case 'E':
      if ($StartStr != "") return '&amp;e='.$StartStr;
    break;
    case 'Sort':
      if ($SortCol == 1) return '&amp;sort=1';
    break;
    case 'V':
      if ($View != 1) return '&amp;v='.$View;
    break;
  }
  return '';
}

//Add Hidden Var to Form if Variable is used-------------------------
function AddHiddenVar($Var) {
global $DateRange, $RowsPerPage, $SortCol, $SortDir, $StartStr, $View;
  switch ($Var) {
    case 'C':
      if ($RowsPerPage != 500) return '<input type="hidden" name="c" value="'.$RowsPerPage.'" />';
    break;
    case 'Dir':
      if ($SortDir == 1) return '<input type="hidden" name="dir" value="1" />';
    break;
    case 'DR':
      if ($DateRange != 1) return '<input type="hidden" name="dr" value="'.$DateRange.'" />';
    break;
    case 'E':
      if ($StartStr != "") return '<input type="hidden" name="e" value="'.$StartStr.'" />';
    break;
    case 'Sort':
      if ($SortCol == 1) return '<input type="hidden" name="sort" value="1" />';
    break;
    case 'V':
      if ($View != 1) return '<input type="hidden" name="v" value="'.$View.'" />';
    break;
  }
  return '';
}

//WriteLI Function for Pagination Boxes-------------------------------
function WriteLI($Character, $Start, $Active) {
  if ($Active) {
    echo '<li class="active"><a href="?start='.$Start.AddGetVar('C').AddGetVar('Sort').AddGetVar('Dir').AddGetVar('V').AddGetVar('E').AddGetVar('DR').'">'.$Character.'</a></li>'.PHP_EOL;
  }
  else {
    echo '<li><a href="?start='.$Start.AddGetVar('C').AddGetVar('Sort').AddGetVar('Dir').AddGetVar('V').AddGetVar('E').AddGetVar('DR').'">'.$Character.'</a></li>'.PHP_EOL;
  }  
  
  return null;
}

//WriteTH Function for Table Header----------------------------------- 
function WriteTH($Sort, $Dir, $Str) {
  global $StartPoint;
  echo '<th><a href="?start='.$StartPoint.AddGetVar('C').'&amp;sort='.$Sort.'&amp;dir='.$Dir.AddGetVar('V').AddGetVar('E').AddGetVar('DR').'">'.$Str.'</a></th>';
  return null;
}

//Load TLD Block List------------------------------------------------
function Load_TLDBlockList() {
//1. Attempt to load TLDBlockList from Memcache
//2. If that fails then check if DomainQuickList file exists
//3. Read each line into TLDBlockList array and trim off \n
//4. Once loaded store TLDBlockList array in Memcache for 30 mins
  global $TLDBlockList, $Mem, $DomainQuickList;
  
  $TLDBlockList=$Mem->get('TLDBlockList');
  if (! $TLDBlockList) {
    if (file_exists($DomainQuickList)) {          //Check if File Exists
      $FileHandle = fopen($DomainQuickList, 'r') or die('Error unable to open '.$DomainQuickList);
      while (!feof($FileHandle)) {
        $TLDBlockList[] = trim(fgets($FileHandle));
      }
      fclose($FileHandle);
      $Mem->set('TLDBlockList', $TLDBlockList, 0, 1800);
    }
  }
  return null;
}
//Load Historic Log All----------------------------------------------
function Load_HistoricLog_All($LogDate) {
  global $DomainList;
  $LogFile = '/var/log/notrack/dns-'.$LogDate.'.log';
  if (file_exists($LogFile)) {
    $FileHandle= fopen($LogFile, 'r');
    while (!feof($FileHandle)) {
      $Line = trim(fgets($FileHandle));                  //Read Line of LogFile
      $DomainList[] = ReturnURL(substr($Line, 0, -1)).substr($Line, -1, 1);
    }
  }
  
  return null;
}
//Load Historic Log Allowed------------------------------------------
function Load_HistoricLog_Allowed($LogDate) {
  global $DomainList;
  $LogFile = '/var/log/notrack/dns-'.$LogDate.'.log';
  if (file_exists($LogFile)) {
    $FileHandle= fopen($LogFile, 'r');
    while (!feof($FileHandle)) {
      $Line = trim(fgets($FileHandle));                  //Read Line of LogFile
      if (substr($Line, -1, 1) == '+') $DomainList[] = ReturnURL(substr($Line, 0, -1)).'+';
    }
  }
  
  return null;
}
//Load Historic Log Blocked------------------------------------------
function Load_HistoricLog_Blocked($LogDate) {
  global $DomainList;
  $LogFile = '/var/log/notrack/dns-'.$LogDate.'.log';
  if (file_exists($LogFile)) {
    $FileHandle= fopen($LogFile, 'r');
    while (!feof($FileHandle)) {
      $Line = trim(fgets($FileHandle));                  //Read Line of LogFile      
      if (substr($Line, -1, 1) == '-') $DomainList[] = ReturnURL(substr($Line, 0, -1)).'-';
    }
  }
}

//Load Todays LogFile------------------------------------------------
function Load_TodayLog() {
//Dnsmasq log line consists of:
//0 - Month (3 characters)
//1 - Day (d or dd)
//2 - Time (dd:dd:dd)
//3 - dnsmasq[d{1-6}]
//4 - Function (query, forwarded, reply, cached, config)
//5 - Website Requested
//6 - is
//7 - IP Returned

  global $DomainList, $StartTime, $StartStr, $View;
  $Dedup = '';
  $Pattern = '';
  
  $FileHandle= fopen('/var/log/notrack.log', 'r') or die('Error unable to open /var/log/notrack.log');
  
  if (($StartStr == '') || ($StartStr == 'today')) {
    if ($View == 1) $Pattern = '/\w{3}\040\040?\d{1,2}\040\d{2}\:\d{2}\:\d{2}\040dnsmasq\[\d{1,6}\]\:\040(reply|config|\/etc\/localhosts\.list)\040([A-Za-z0-9\-\.]+)/';
    elseif ($View == 2) $Pattern = '/\w{3}\040\040?\d{1,2}\040\d{2}\:\d{2}\:\d{2}\040dnsmasq\[\d{1,6}\]\:\040(reply|\/etc\/localhosts\.list)\040([A-Za-z0-9\-\.]+)/';
    elseif ($View == 3) $Pattern = '/\w{3}\040\040?\d{1,2}\040\d{2}\:\d{2}\:\d{2}\040dnsmasq\[\d{1,6}\]\:\040(config)\040([A-Za-z0-9\-\.]+)/'; 
    
    while (!feof($FileHandle)) {
      $Line = fgets($FileHandle);                  //Read Line of LogFile
      if (preg_match($Pattern, $Line, $Matches) > 0) {      
        if (($Matches[1] == 'reply') && ($Matches[2] != $Dedup)) {
          $DomainList[] = ReturnURL($Matches[2]) . '+';
          $Dedup = $Matches[2];
        }
        elseif (($Matches[1] == 'config') && ($Matches[2] != $Dedup)) {
          $DomainList[] = ReturnURL($Matches[2]) . '-';
          $Dedup = $Matches[2];
        }
        elseif (($Matches[1] == '/etc/localhosts.list') && (substr($Matches[2], 0, 1) != '1')) {
          //!= "1" negates Reverse DNS calls. If RFC 1918 is obeyed 10.0.0.0, 172.31, 192.168 all start with "1"
          $DomainList[] = ReturnURL($Matches[2]) . '1';      
        }    
      }
    }
  }
  else {                                         //Load last x minutes
    if ($View == 1) $Pattern = '/\w{3}\040\040?\d{1,2}\040(\d{2}\:\d{2}\:\d{2})\040dnsmasq\[\d{1,6}\]\:\040(reply|config|\/etc\/localhosts\.list)\040([A-Za-z0-9\-\.]+)/';
    elseif ($View == 2) $Pattern = '/\w{3}\040\040?\d{1,2}\040(\d{2}\:\d{2}\:\d{2})\040dnsmasq\[\d{1,6}\]\:\040(reply|\/etc\/localhosts\.list)\040([A-Za-z0-9\-\.]+)/';
    elseif ($View == 3) $Pattern = '/\w{3}\040\040?\d{1,2}\040(\d{2}\:\d{2}\:\d{2})\040dnsmasq\[\d{1,6}\]\:\040(config)\040([A-Za-z0-9\-\.]+)/';
  
    while (!feof($FileHandle)) {
      $Line = fgets($FileHandle);                  //Read Line of LogFile
      if (preg_match($Pattern, $Line, $Matches) > 0) {    
        if (strtotime($Matches[1]) >= $StartTime) {      //Check if time in log > Earliest
          if (($Matches[2] == 'reply') && ($Matches[3] != $Dedup)) {
            $DomainList[] = ReturnURL($Matches[3]) . '+';
            $Dedup = $Matches[3];
          }
          elseif (($Matches[2] == 'config') && ($Matches[3] != $Dedup)) {
            $DomainList[] = ReturnURL($Matches[3]) . '-';
            $Dedup = $Matches[3];
          }
          elseif (($Matches[2] == '/etc/localhosts.list') && (substr($Matches[3], 0, 1) != '1')) {
            //!= "1" negates Reverse DNS calls. If RFC 1918 is obeyed 10.0.0.0, 172.31, 192.168 all start with "1"
            $DomainList[] = ReturnURL($Matches[3]) . '1';
          }
        }      
      }
    }
  }
  fclose($FileHandle);
  
  return null;
}
//Load Historic Logs-------------------------------------------------
function Load_HistoricLogs() {
  global $DateRange, $StartTime, $View, $Mem, $DomainList;
  
  $LD = $StartTime + 86400;                      //Log files get cached the following day, so we move the start date on by 86,400 seconds (24 hours)
  for ($i = 0; $i < $DateRange; $i++) {
    if ($View == 1) Load_HistoricLog_All(date('Y-m-d', $LD));
    elseif ($View == 2) Load_HistoricLog_Allowed(date('Y-m-d', $LD));
    elseif ($View == 3) Load_HistoricLog_Blocked(date('Y-m-d', $LD));
    $LD = $LD + 86400;                           //Add per run of loop 24 Hours
    if ($LD > time() + 86400) {                  //Don't exceed today
      break;
    }
  }  
}
//Main---------------------------------------------------------------

//HTTP GET Variables-------------------------------------------------
//SortCol 0: Requests
//SortCol 1: Name
$SortCol = Filter_Int('sort', 0, 2, 0);

//Direction 0: Ascending
//Direction 1: Descending
$SortDir = Filter_Int('dir', 0, 2, 0);

$StartPoint = Filter_Int('start', 1, PHP_INT_MAX-2, 1);
$RowsPerPage = Filter_Int('c', 2, PHP_INT_MAX, 500);

//View 1: Show All
//View 2: Allowed only
//View 3: Blocked only
$View = Filter_Int('v', 1, 4, 1);

$ExecTime = time();                              //Time of execution
$StartTime = $ExecTime;
$StartStr = 'today';
if (isset($_GET['e'])) {
  $StartStr = $_GET['e'];
  if ($StartStr != 'today') {
    if (($StartTime = strtotime($StartStr)) === false) {
      $StartTime = $ExecTime;
      $StartStr = 'today';      
    }    
  }
}

$DateRange = Filter_Int('dr', 1, 366, 1);

//Load TLD Blocklist if being used
if ($Config['bl_tld'] == 1) Load_TLDBlockList();                           

//Merge Users Config of Suppress List with CommonSitesList
if ($Config['Suppress'] == '') $CommonSites = array_merge($CommonSitesList);
else $CommonSites = array_merge($CommonSitesList, explode(',', $Config['Suppress']));
unset($CommonSitesList);

//Load Logs----------------------------------------------------------
$LoadList = true;                 //Assume Logs will need loading
$SortList = true;                 //Assume Array will need sorting
$MemSaveTime = 60;                //How long to hold data in memory

//How long to hold data in memcache based on how far back user is searching
//Shorter time search = lower retention of Memcache
if (($StartStr == '') || ($StartStr == 'today')) $MemSaveTime = 240;
elseif ($StartTime >= $ExecTime - 300) $MemSaveTime = 30;    //-5 Min
elseif ($StartTime >= $ExecTime - 1500) $MemSaveTime = 50;   //-15 Min
elseif ($StartTime >= $ExecTime - 3600) $MemSaveTime = 90;   //-1 hour
elseif ($StartTime >= $ExecTime - 28800) $MemSaveTime = 180; //-8 hours
else $MemSaveTime = 600;                                     //-Days

//Attempt to load SortedDomainList from Memcache
$SortedDomainList = $Mem->get('SortedDomainList');   
if ($SortedDomainList) {                         //Has array loaded?
  if (($StartStr == $Mem->get('StartStr')) && 
      ($DateRange == $Mem->get('DateRange')) && 
      ($View == $Mem->get('View'))) {
    if (($SortCol == $Mem->get('SortCol')) && 
        ($SortDir == $Mem->get('SortDir'))) {    //Check if search is same
      $SortList = false;
      $LoadList = false;      
    }
    else {
      $LoadList = false;                         //No need to load list
      $SortedDomainList = array();               //Delete data in array     
    }
  }
  else {
    $Mem->delete('StartStr');                    //Delete old variables from Memcache
    $Mem->delete('SortCol');
    $Mem->delete('SortDir');
    $Mem->delete('DateRange');
    $Mem->delete('DomainList');
    $Mem->delete('SortedDomainList');
    $Mem->delete('View');
    $SortedDomainList = array();                 //Delete data in array
  }    
}
    
if ($LoadList) {                                 //Load domain list from file  
  //Are we loading Todays logs or Historic logs?
  if ($StartTime > (time() - 86400)) Load_TodayLog();
  else Load_HistoricLogs();
  $Mem->set('DomainList', $DomainList, 0, $MemSaveTime);
}
else {                                           //Load domain list from memcache
  $DomainList = $Mem->get('DomainList');
  if (!$DomainList) {                            //Something wrong, get it reloaded
    if ($StartTime > (time() - 86400)) Load_TodayLog();
    else Load_HistoricLogs();
    $Mem->set('DomainList', $DomainList, 0, $MemSaveTime);
  }
}

if ($SortList) {
  //Sort Array of Domains from log file
  $SortedDomainList = array_count_values($DomainList);//Take a count of number of hits
  if ($SortCol == 1) {
    if ($SortDir == 0) ksort($SortedDomainList);
    else krsort($SortedDomainList);
  }
  else {
    if ($SortDir == 0) arsort($SortedDomainList);//Sort array by highest number of hits
    else asort($SortedDomainList);
  }
  
  $Mem->set('StartStr', $StartStr, 0, $MemSaveTime);       //Store variables in Memcache
  $Mem->set('SortCol', $SortCol, 0, $MemSaveTime);
  $Mem->set('SortDir', $SortDir, 0, $MemSaveTime);
  $Mem->set('DateRange', $DateRange, 0, $MemSaveTime);
  $Mem->set('SortedDomainList', $SortedDomainList, 0, $MemSaveTime);
  $Mem->set('View', $View, 0, $MemSaveTime);
}

$ListSize = count($SortedDomainList);
if ($StartPoint >= $ListSize) $StartPoint = 1;   //Start point can't be greater than the list size

//Draw Filter Dropdown list------------------------------------------
echo '<div class="sys-group"><div class="col-half">'.PHP_EOL;
echo '<form action="?" method="get">';
echo '<input type="hidden" name="start" value="'.$StartPoint.'" />'.AddHiddenVar('C').AddHiddenVar('Sort').AddHiddenVar('Dir').AddHiddenVar('E').AddHiddenVar('DR');
echo '<span class="filter">Filter:</span><select name="v" onchange="submit()">';
switch ($View) {                                 //First item is unselectable, therefore we need to
  case 1:                                        //give a different selection for each value of $View
    echo '<option value="1">All Requests</option>';
    echo '<option value="2">Only requests that were allowed</option>';
    echo '<option value="3">Only requests that were blocked</option>';
  break;
  case 2:
    echo '<option value="2">Only requests that were allowed</option>';
    echo '<option value="1">All Requests</option>';
    echo '<option value="3">Only requests that were blocked</option>';
  break;
  case 3:
    echo '<option value="3">Only requests that were blocked</option>';
    echo '<option value="1">All Requests</option>';
    echo '<option value="2">Only requests that were allowed</option>';
  break;
}
echo '</select></form>'.PHP_EOL;

//Draw Time Dropdown list------------------------------------------
echo '<form action="?" method="get">';
echo '<input type="hidden" name="start" value="'.$StartPoint.'" />'.AddHiddenVar('C').AddHiddenVar('Sort').AddHiddenVar('Dir').AddHiddenVar('V').AddHiddenVar('DR');
echo '<span class="filter">Time:</span><select name="e" onchange="submit()">';
switch ($StartStr) {                          //First item is unselectable
  case 'today': case '':
    echo '<option value="today">Today</option>';
    echo '<option value="-5minutes">5 Minutes</option>';
    echo '<option value="-15minutes">15 Minutes</option>';
    echo '<option value="-30minutes">30 Minutes</option>';
    echo '<option value="-1hours">1 Hour</option>';
    echo '<option value="-8hours">8 Hours</option>';
  break;
  case '-5minutes':
    echo '<option value="-5minutes">5 Minutes</option>';
    echo '<option value="today">Today</option>';
    echo '<option value="-15minutes">15 Minutes</option>';
    echo '<option value="-30minutes">30 Minutes</option>';
    echo '<option value="-1hours">1 Hour</option>';
    echo '<option value="-8hours">8 Hours</option>';
  break;
  case '-15minutes':
    echo '<option value="-15minutes">15 Minutes</option>';
    echo '<option value="today">Today</option>';
    echo '<option value="-5minutes">5 Minutes</option>';
    echo '<option value="-30minutes">30 Minutes</option>';
    echo '<option value="-1hours">1 Hour</option>';
    echo '<option value="-8hours">8 Hours</option>';
  break;
  case '-30minutes':
    echo '<option value="-30minutes">30 Minutes</option>';
    echo '<option value="today">Today</option>';
    echo '<option value="-5minutes">5 Minutes</option>';
    echo '<option value="-15minutes">15 Minutes</option>';
    echo '<option value="-1hours">1 Hour</option>';
    echo '<option value="-8hours">8 Hours</option>';
  break;
  case '-1hours':
    echo '<option value="-1hours">1 Hour</option>';
    echo '<option value="today">Today</option>';
    echo '<option value="-5minutes">5 Minutes</option>';
    echo '<option value="-15minutes">15 Minutes</option>';
    echo '<option value="-30minutes">30 Minutes</option>';
    echo '<option value="-8hours">8 Hours</option>';
  break;
  case '-8hours':
    echo '<option value="-8hours">8 Hours</option>';
    echo '<option value="today">Today</option>';
    echo '<option value="-5minutes">5 Minutes</option>';
    echo '<option value="-15minutes">15 Minutes</option>';
    echo '<option value="-30minutes">30 Minutes</option>';
    echo '<option value="-1hours">1 Hour</option>';
  break;
  default:
    echo '<option value="'.$StartStr.'">Other</option>';
    echo '<option value="today">Today</option>';
    echo '<option value="-5minutes">5 Minutes</option>';
    echo '<option value="-15minutes">15 Minutes</option>';
    echo '<option value="-30minutes">30 Minutes</option>';
    echo '<option value="-1hours">1 Hour</option>';
    echo '<option value="-8hours">8 Hours</option>';
  break;
}
echo '</select></form></div>'.PHP_EOL;

//Draw Calendar------------------------------------------------------
echo '<div class="col-half"><form action="?" method="get">';
echo '<span class="filter">Date: </span><input name="e" type="date" value="'.date('Y-m-d', $StartTime).'" /><br />';
echo '<span class="filter">Range: </span><input name="dr" type="number" min="1" max="30" value="'.$DateRange.'"/><br /><br />'.PHP_EOL;
echo '<div class="centered"><input type="submit" value="Submit"></div>'.PHP_EOL;
echo '</form></div></div>';

//Draw Table Headers-------------------------------------------------
echo '<div class="sys-group">'.PHP_EOL;
echo '<table id="domain-table">';             //Table Start
echo '<tr><th>#</th>';
if ($SortCol == 1) {
  if ($SortDir == 0) WriteTH(1, 1, 'Domain&#x25B4;');
  else WriteTH(1, 0, 'Domain&#x25BE;');
}
else {
  WriteTH(1, $SortDir, 'Domain');
}
echo '<th>Action</th>';
if ($SortCol == 0) {
  if ($SortDir == 0) WriteTH(0, 1, 'Requests&#x25BE;');
  else WriteTH(0, 0, 'Requests&#x25B4;');
}
else {
  WriteTH(0, $SortDir, 'Requests');
}
echo '</tr>'.PHP_EOL;

//Draw Table Cells---------------------------------------------------
$i = 1;
foreach ($SortedDomainList as $Str => $Value) {
  if ($i >= $StartPoint) {                       //Start drawing the table when we reach the StartPoint of Pagination
    if ($i >= $StartPoint + $RowsPerPage) break; //Exit the loop at end of Pagination + Rows per page
    
    $Action = substr($Str,-1,1);                 //Last character tells us whether URL was blocked or not
    $Site = substr($Str, 0, -1);
    $ReportSiteStr = '';                         //Assume no Report Button
    
    if ($Action == '+') {                        //+ = Allowed      
      echo '<tr><td>'. $i.'</td><td>'.$Site.'</td>';      
      $ReportSiteStr = '&nbsp;<img src="./images/report_icon.png" alt="Rep" title="Report Site" onclick="ReportSite(\''.$Site.'\', false)">';
    }
    elseif ($Action == '-') {                    //- = Blocked
      $SplitURL = explode('.', $Site);           //Find out wheter site was blocked by TLD or Tracker list
      $CountSubDomains = count($SplitURL);
      
      if ($CountSubDomains <= 1) {               //No TLD Given, this could be a search via address bar  
        echo '<tr class="invalid"><td>'.$i.'</td><td>'.$Site.'</td>';
      }                                          //Is it an IP Address?
      elseif (($CountSubDomains == 4) && (!filter_var($Site, FILTER_VALIDATE_IP) === false)) {
        echo '<tr class="invalid"><td>'.$i.'</td><td>'.$Site.'</td>';
      }
      elseif (in_array('.'.$SplitURL[$CountSubDomains-1], $TLDBlockList)) {
        echo '<tr class="blocked"><td>'.$i.'</td><td>'.$Site.'<p class="small">.'.$SplitURL[$CountSubDomains -1].' Blocked by Top Level Domain List</p></td>';
        
      }
      else {
        echo '<tr class="blocked"><td>'.$i.'</td><td>'.$Site.'</td>';
        $ReportSiteStr = '&nbsp;<img src="./images/report_icon.png" alt="Rep" title="Report Site" onclick="ReportSite(\''.$Site.'\', true)">';
      }      
    }
    elseif ($Action == '1') {                    //1 = Local lookup
      echo '<tr class="local"><td>'.$i.'</td><td>'.$Site.'</td>';
    }
    echo '<td><a target="_blank" href="'.$Config['SearchUrl'].$Site.'"><img class="icon" src="./images/search_icon.png" alt="G" title="Search"></a>&nbsp;
    <a target="_blank" href="'.$Config['WhoIsUrl'].$Site.'"><img class="icon" src="./images/whois_icon.png" alt="W" title="Whois"></a>'
    .$ReportSiteStr;
    echo '</td><td>'.$Value.'</td></tr>'.PHP_EOL;
  }  
  $i++;
}
echo '</table></div>'.PHP_EOL;

//Pagination---------------------------------------------------------
if ($ListSize > $RowsPerPage) {                  //Is Pagination needed
  $ListSize = ceil($ListSize / $RowsPerPage);    //Calculate List Size
  $CurPos = floor($StartPoint / $RowsPerPage)+ 1;//Calculate Current Position
  
  echo '<div class="sys-group"><div class="pag-nav"><ul>'.PHP_EOL;
  
  if ($CurPos == 1) {                            //At the beginning display blank box
    echo '<li><span>&nbsp;&nbsp;</span></li>'.PHP_EOL;    
    WriteLI('1', 0, true);
  }    
  else {                                         // << Symbol & Print Box 1
    WriteLI('&#x00AB;', $RowsPerPage * ($CurPos - 2), false);
    WriteLI('1', 0, false);
  }

  if ($ListSize <= 4) {                          //Small Lists don't need fancy effects
    for ($i = 2; $i <= $ListSize; $i++) {	 //List of Numbers
      if ($i == $CurPos) {
        WriteLI($i, $RowsPerPage * ($i - 1), true);
      }
      else {
        WriteLI($i, $RowsPerPage * ($i - 1), false);
      }
    }
  }
  elseif ($ListSize > 4 && $CurPos == 1) {       // < [1] 2 3 4 T >
    WriteLI('2', $RowsPerPage, false);
    WriteLI('3', $RowsPerPage * 2, false);
    WriteLI('4', $RowsPerPage * 3, false);
    WriteLI($ListSize, ($ListSize - 1) * $RowsPerPage, false);
  }
  elseif ($ListSize > 4 && $CurPos == 2) {       // < 1 [2] 3 4 T >
    WriteLI('2', $RowsPerPage, true);
    WriteLI('3', $RowsPerPage * 2, false);
    WriteLI('4', $RowsPerPage * 3, false);
    WriteLI($ListSize, ($ListSize - 1) * $RowsPerPage, false);
  }
  elseif ($ListSize > 4 && $CurPos > $ListSize - 2) {// < 1 T-3 T-2 T-1 T > 
    for ($i = $ListSize - 3; $i <= $ListSize; $i++) {//List of Numbers
      if ($i == $CurPos) {
        WriteLI($i, $RowsPerPage * ($i - 1), true);
      }
      else {
        WriteLI($i, $RowsPerPage * ($i - 1), false);
    	}
      }
    }
  else {                                         // < 1 c-1 [c] c+1 T >
    for ($i = $CurPos - 1; $i <= $CurPos + 1; $i++) {//List of Numbers
      if ($i == $CurPos) {
        WriteLI($i, $RowsPerPage * ($i - 1), true);
      }
      else {
        WriteLI($i, $RowsPerPage * ($i - 1), false);
      }
    }
    WriteLI($ListSize, ($ListSize - 1) * $RowsPerPage, false);
  }
    
  if ($CurPos < $ListSize) {                     // >> Symbol for Next
    WriteLI('&#x00BB;', $RowsPerPage * $CurPos, false);
  }	
  echo '</ul></div></div>'.PHP_EOL;
}

?>
</div>

<div id="scrollup" class="button-scroll" onclick="ScrollToTop()"><img src="./svg/arrow-up.svg" alt="up"></a></div>
<div id="scrolldown" class="button-scroll" onclick="ScrollToBottom()"><img src="./svg/arrow-down.svg" alt="down"></a></div>

<div id="stats-box">
<div class="dialog-bar">Report</div>
<span id="sitename">site</span>
<span id="statsmsg">something</span>
<span id="statsblock1"><a class="button-blue" href="#">Block Whole</a> Block whole domain</span>
<span id="statsblock2"><a class="button-blue" href="#">Block Sub</a> Block just the subdomain</span>
<span id="statsreport"><a class="button-blue" href="#">Report</a></span>
<br />
<div class="centered"><h6 class="button-grey" onclick="HideStatsBox()">Cancel</h6></div>
<div class="close-button" onclick="HideStatsBox()"><img src="./svg/button_close.svg" onmouseover="this.src='./svg/button_close_over.svg'" onmouseout="this.src='./svg/button_close.svg'" alt="close"></a></div>
</div>

</body>
</html>

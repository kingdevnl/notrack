<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <link href="./css/master.css" rel="stylesheet" type="text/css" />
    <link rel="icon" type="image/png" href="./favicon.png" />
    <title>NoTrack Stats</title>
</head>

<body>
<div id="main">
<?php
$CurTopMenu = 'stats';
include('topmenu.html');
echo "<h1>Domain Stats</h1>\n";
echo "<br />\n";
$DomainList = array();
$SortedDomainList = array();
$TLDBlockList = array();
$CommonSites = array('cloudfront.net','googleusercontent.com','googlevideo.com','akamaiedge.com','cedexis-radar.net','stackexchange.com');
//CommonSites referres to websites that have a lot of subdomains which aren't necessarily relivent. In order to improve user experience we'll replace the subdomain of these sites with "*"

$Mem = new Memcache;                             //Initiate Memcache
$Mem->connect('localhost');

//HTTP GET Variables-------------------------------------------------
$SortCol = 0;
if (isset($_GET['sort'])) {
  switch ($_GET['sort']) {
    case 0: $SortCol = 0; break;                 //Requests
    case 1: $SortCol = 1; break;                 //Name
  }
}

//Direction: 0 = Ascending, 1 = Descending
$SortDir = 0;
if (isset($_GET['dir'])) {
  if ($_GET['dir'] == "1") {
    $SortDir = 1;
  }  
}

$StartPoint = 1;				 //Start point
if (isset($_GET['start'])) {
  if (is_numeric($_GET['start'])) {
    if (($_GET['start'] >= 1) && ($_GET['count'] < PHP_INT_MAX - 2)) {
      $StartPoint = intval($_GET['start']);
    }
  }
}

$ItemsPerPage = 500;				 //Rows per page
if (isset($_GET['c'])) {
  if (is_numeric($_GET['c'])) {
    if (($_GET['c'] >= 2) && ($_GET['c'] < PHP_INT_MAX)) {
      $ItemsPerPage = intval($_GET['c']);
    }
  }
}

$View = 1;
if (isset($_GET['v'])) {
  switch ($_GET['v']) {
    case '1': $View = 1; break;                 //Show All
    case '2': $View = 2; break;                 //Allowed only
    case '3': $View = 3; break;                 //Blocked only
  }  
}

$StartTime = time();
$StartStr = '';
if (isset($_GET['e'])) {
  $StartStr = $_GET['e'];
  if ($StartStr != 'today') {
    if (($StartTime = strtotime($StartStr)) === false) {
      $StartTime = 0;
      $StartStr = 'today';
      echo "Invalid Time <br />\n";
    }    
  }
}

$DateRange = 1;
if (isset($_GET['dr'])) {                        //Date Range
  if (is_numeric($_GET['dr'])) {
    if (($_GET['dr'] >= 1) && ($_GET['dr'] < PHP_INT_MAX)) {
      $DateRange = intval($_GET['dr']);
    }
  }  
}
//ReturnURL - Gives a simplier formatted URL for displaying----------
function ReturnURL($Str) {
  //Conditions:
  //1: Drop www (its unnecessary and not all websites use it now)
  //2: Domain length of 0 or 1 is sent straight onto DNS server
  //   Return it without comparing it against common sites
  //3: Check domain against CommonSites, if there is a match then
  //   return '*.' for the subdomains
  //4: .co.xx, .com.xx, .net.xx need to be evaluated as a single TLD
  global $CommonSites;
  
  if (substr($Str,0,4) == 'www.') $Site = substr($Str,4); 
  else $Site = $Str;
  
  $Split = explode('.', $Site);
  $c = count($Split) - 1;
  
  if ($c < 2) {
    return $Site;
  }
  elseif ($c == 2) {
    if (in_array($Split[1].'.'.$Split[2], $CommonSites)) return '*.'.$Split[1].'.'.$Split[2];
    else return $Site;
  }
  else {
    switch ($Split[$c-1]) {
      case 'co':
      case 'com':
      case 'net':
        if (in_array($Split[$c-2].'.'.$Split[$c-1].'.'.$Split[$c], $CommonSites)) return '*.'.$Split[$c-2].'.'.$Split[$c-1].'.'.$Split[$c];
        break;
      default:        
        if (in_array($Split[$c-1].'.'.$Split[$c], $CommonSites)) return '*.'.$Split[$c-1].'.'.$Split[$c];
        break;
    }    
    return $Site;
  }
  
  return 'Error in URL String';
}
//Add GET Var to Link if Variable is used----------------------------
function AddGetVar($Var) {
  global $DateRange, $StartStr, $ItemsPerPage, $SortCol, $SortDir, $View;
  switch ($Var) {
    case 'C':
      if ($ItemsPerPage != 500) return '&amp;c='.$ItemsPerPage;
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
global $DateRange, $ItemsPerPage, $SortCol, $SortDir, $StartStr, $View;
  switch ($Var) {
    case 'C':
      if ($ItemsPerPage != 500) return '<input type="hidden" name="c" value="'.$ItemsPerPage.'" />';
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
  //global $ItemsPerPage, $SortCol, $SortDir, $View;
  if ($Active) {
    echo '<li class="active"><a href="?start='.$Start.AddGetVar('C').AddGetVar('Sort').AddGetVar('Dir').AddGetVar('V').AddGetVar('E').AddGetVar('DR').'">';
  }
  else {
    echo '<li><a href="?start='.$Start.AddGetVar('C').AddGetVar('Sort').AddGetVar('Dir').AddGetVar('V').AddGetVar('E').AddGetVar('DR').'">';
  }  
  echo "$Character</a></li>\n";  
  return null;
}

//WriteTH Function for Table Header----------------------------------- 
function WriteTH($Sort, $Dir, $Str) {  
  echo '<th><a href="?start='.$StartPoint.AddGetVar('C').'&amp;sort='.$Sort.'&amp;dir='.$Dir.AddGetVar('V').AddGetVar('E').AddGetVar('DR').'">'.$Str.'</a></th>';
  return null;
}

//Load TLD Block List------------------------------------------------
function Load_TLDBlockList() {
//Blocklist is held in Memcache for 10 minutes
  global $TLDBlockList, $Mem;
  $TLDBlockList=$Mem->get('TLDBlockList');
  if (! $TLDBlockList) {
    $FileHandle = fopen('/etc/notrack/domain-quick.list', 'r') or die('Error unable to open /etc/notrack/domain-quick.list');
    while (!feof($FileHandle)) {
      $TLDBlockList[] = trim(fgets($FileHandle));
    }
    fclose($FileHandle);
    $Mem->set('TLDBlockList', $TLDBlockList, 0, 600);
  }
  return null;
}

//Read Day All--------------------------------------------------------
function Read_Day_All($FileHandle) {
  global $DomainList;
  while (!feof($FileHandle)) {
    $Line = fgets($FileHandle);                  //Read Line of LogFile
    if (substr($Line, 4, 1) == ' ') {            //dnsmasq puts a double space for single digit dates
      $Seg = explode(' ', str_replace('  ', ' ', $Line));
    }
    else $Seg = explode(' ', $Line);             //Split Line into segments
    
    if (($Seg[4] == 'reply') && ($Seg[5] != $Dedup)) {
      $DomainList[] = ReturnURL($Seg[5]) . '+';
      $Dedup = $Seg[5];
    }
    elseif (($Seg[4] == 'config') && ($Seg[5] != $Dedup)) {
      $DomainList[] = ReturnURL($Seg[5]) . '-';
      $Dedup = $Seg[5];
    }
    elseif (($Seg[4] == '/etc/localhosts.list') && (substr($Seg[5], 0, 1) != '1')) {
      //!= "1" negates Reverse DNS calls. If RFC 1918 is obeyed 10.0.0.0, 172.31, 192.168 all start with "1"
      $DomainList[] = ReturnURL($Seg[5]) . '1';
      //$Dedup = $Seg[5];
    }    
  }
  return null;
}
//Read Day Allowed---------------------------------------------------
function Read_Day_Allowed($FileHandle) {
  global $DomainList;
  while (!feof($FileHandle)) {
    $Line = fgets($FileHandle);                  //Read Line of LogFile
    if (substr($Line, 4, 1) == ' ') {            //dnsmasq puts a double space for single digit dates
      $Seg = explode(' ', str_replace('  ', ' ', $Line));
    }
    else $Seg = explode(' ', $Line);             //Split Line into segments
    
    if ($Seg[4] == 'reply' && $Seg[5] != $Dedup) {
      $DomainList[] = ReturnURL($Seg[5]) . '+';
      $Dedup = $Seg[5];
    }    
  }
  return null;
}
//Read Day Allowed---------------------------------------------------
function Read_Day_Blocked($FileHandle) {
  global $DomainList;
  while (!feof($FileHandle)) {
    $Line = fgets($FileHandle);                  //Read Line of LogFile
    if (substr($Line, 4, 1) == ' ') {            //dnsmasq puts a double space for single digit dates
      $Seg = explode(' ', str_replace('  ', ' ', $Line));
    }
    else $Seg = explode(' ', $Line);             //Split Line into segments
    
    if ($Seg[4] == 'config' && $Seg[5] != $Dedup) {
      $DomainList[] = ReturnURL($Seg[5]) . '-';
      $Dedup = $Seg[5];
    }
  }
  return null;
}
//Read Time All--------------------------------------------------------
function Read_Time_All($FileHandle) {
  global $DomainList, $StartTime;
  while (!feof($FileHandle)) {
    $Line = fgets($FileHandle);                  //Read Line of LogFile
    if (substr($Line, 4, 1) == ' ') {            //dnsmasq puts a double space for single digit dates
      $Seg = explode(' ', str_replace('  ', ' ', $Line));
    }
    else $Seg = explode(' ', $Line);             //Split Line into segments
    
    if (strtotime($Seg[2]) >= $StartTime) {       //Check if time in log > Earliest required
      if (($Seg[4] == 'reply') && ($Seg[5] != $Dedup)) {
        $DomainList[] = ReturnURL($Seg[5]) . '+';
        $Dedup = $Seg[5];
      }
      elseif (($Seg[4] == 'config') && ($Seg[5] != $Dedup)) {
        $DomainList[] = ReturnURL($Seg[5]) . '-';
        $Dedup = $Seg[5];
      }
      elseif (($Seg[4] == '/etc/localhosts.list') && (substr($Seg[5], 0, 1) != '1')) {
        //!= "1" negates Reverse DNS calls. If RFC 1918 is obeyed 10.0.0.0, 172.31, 192.168 all start with "1"
        $DomainList[] = ReturnURL($Seg[5]) . '1';
      //$Dedup = $Seg[5];
      }    
    }
  }
  return null;
}
//Read Day Allowed---------------------------------------------------
function Read_Time_Allowed($FileHandle) {
  global $DomainList, $StartTime;
  while (!feof($FileHandle)) {
    $Line = fgets($FileHandle);                  //Read Line of LogFile
    
    if (substr($Line, 4, 1) == ' ') {            //dnsmasq puts a double space for single digit dates
      $Seg = explode(' ', str_replace('  ', ' ', $Line));
    }
    else $Seg = explode(' ', $Line);             //Split Line into segments
    
    if (strtotime($Seg[2]) >= $StartTime) {       //Check if time in log > Earliest required
      if ($Seg[4] == 'reply' && $Seg[5] != $Dedup) {
        $DomainList[] = ReturnURL($Seg[5]) . '+';
        $Dedup = $Seg[5];
      }
    }    
  }
  return null;
}
//Read Day Allowed---------------------------------------------------
function Read_Time_Blocked($FileHandle) {
  global $DomainList, $StartTime;
  while (!feof($FileHandle)) {
    $Line = fgets($FileHandle);                  //Read Line of LogFile
    if (substr($Line, 4, 1) == ' ') {            //dnsmasq puts a double space for single digit dates
      $Seg = explode(' ', str_replace('  ', ' ', $Line));
    }
    else $Seg = explode(' ', $Line);             //Split Line into segments
    
    if (strtotime($Seg[2]) >= $StartTime) {      //Check if time in log > Earliest required
      if ($Seg[4] == 'config' && $Seg[5] != $Dedup) {
        $DomainList[] = ReturnURL($Seg[5]) . '-';
        $Dedup = $Seg[5];
      }
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
//0 - Month
//1 - Day
//2 - Time
//3 - dnsmasq[pid]
//4 - Function (query, forwarded, reply, cached, config)
//5 - Website Requested
//6 - "is"
//7 - IP Returned
//The functions are replicated to reduce the number of if statements inside the loop, as this section is very CPU intensive and RPi struggles
  global $StartTime, $StartStr, $View;
  $FileHandle= fopen('/var/log/notrack.log', 'r') or die('Error unable to open /var/log/notrack.log');
  
  if (($StartStr == '') || ($StartStr == 'today')) {
    if ($View == 1) Read_Day_All($FileHandle);     //Read both Allow & Block
    elseif ($View == 2) Read_Day_Allowed($FileHandle);  //Read Allowed only
    elseif ($View == 3) Read_Day_Blocked($FileHandle);  //Read Blocked only    
  }
  else {
    if ($View == 1) Read_Time_All($FileHandle);    //Read both Allow & Block
    elseif ($View == 2) Read_Time_Allowed($FileHandle);  //Read Allowed only
    elseif ($View == 3) Read_Time_Blocked($FileHandle);  //Read Blocked only    
  }
  fclose($FileHandle);
  return null;
}
//Load Historic Logs-------------------------------------------------
function Load_HistoricLogs() {
  global $DateRange, $StartTime, $View, $Mem, $DomainList;
  
  //It can take a while to process days worth of logs, therefore we'll 
  //utilise Memcache to hold the data for 10 minutes
  //Compare $StartTime, $DateRange, and $View to the current settings.
  //If they match then leave function without opening any files
  //Store data in Memcache once loaded
  
  $DomainList = $Mem->get('DomainList');         //Load Domain list from Memcache
  if ($DomainList) {                             //Has array loaded?
    if (($StartTime == $Mem->get('StartTime')) && ($DateRange == $Mem->get('DateRange')) && ($View == $Mem->get('View'))) return;
    else {
      echo "Change";
      $Mem->delete('StartTime');                 //Delete old variables from Memcache
      $Mem->delete('DateRange');
      $Mem->delete('DomainList');
      $Mem->delete('View');
      $DomainList = array();                     //Delete data in array
    }    
  }
    
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
  
  $Mem->set('StartTime', $StartTime, 0, 600);    //Store variables in Memcache
  $Mem->set('DateRange', $DateRange, 0, 600);
  $Mem->set('DomainList', $DomainList, 0, 600);
  $Mem->set('View', $View, 0, 600);
}
//Main---------------------------------------------------------------
Load_TLDBlockList();                             //Load TLD Blocklist from memory or file

if ($StartTime > (time() - 86400)) {             //Load Todays LogFile
  //echo "Today";
  Load_TodayLog();
}
else {
  //echo "Historic";
  Load_HistoricLogs();                           //Or load Historic logs
}

//Sort Array of Domains from log file--------------------------------
$SortedDomainList = array_count_values($DomainList);//Take a count of number of hits
if ($SortCol == 1) {
  if ($SortDir == 0) ksort($SortedDomainList);
  else krsort($SortedDomainList);
}
else {
  if ($SortDir == 0) arsort($SortedDomainList);  //Sort array by highest number of hits
  else asort($SortedDomainList);
}

$ListSize = count($SortedDomainList);
if ($StartPoint >= $ListSize) $StartPoint = 1;   //Start point can't be greater than the list size

//Draw Filter Dropdown list------------------------------------------
echo '<div class="row"><div class="col-half">'."\n";
echo '<form action="?" method="get">';
echo '<input type="hidden" name="start" value="'.$StartPoint.'" />'.AddHiddenVar('C').AddHiddenVar('Sort').AddHiddenVar('Dir').AddHiddenVar('E').AddHiddenVar('DR');
echo '<Label>Filter: <select name="v" onchange="submit()">';
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
echo '</select></label></form>'."\n";

//Draw Time Dropdown list------------------------------------------
echo '<form action="?" method="get">';
echo '<input type="hidden" name="start" value="'.$StartPoint.'" />'.AddHiddenVar('C').AddHiddenVar('Sort').AddHiddenVar('Dir').AddHiddenVar('V').AddHiddenVar('DR');
echo '<Label>Time: <select name="e" onchange="submit()">';
switch ($StartStr) {                          //First item is unselectable
  case "today": case "":
    echo '<option value="today">Today</option>';
    echo '<option value="-5minutes">5 Minutes</option>';
    echo '<option value="-1hours">1 Hour</option>';
    echo '<option value="-8hours">8 Hours</option>';
  break;
  case "-5minutes":
    echo '<option value="-5minutes">5 Minutes</option>';
    echo '<option value="today">Today</option>';
    echo '<option value="-1hours">1 Hour</option>';
    echo '<option value="-8hours">8 Hours</option>';
  break;
  case "-1hours":
    echo '<option value="-1hours">1 Hour</option>';
    echo '<option value="today">Today</option>';
    echo '<option value="-5minutes">5 Minutes</option>';
    echo '<option value="-8hours">8 Hours</option>';
  break;
  case "-8hours":
    echo '<option value="-8hours">8 Hours</option>';
    echo '<option value="today">Today</option>';
    echo '<option value="-5minutes">5 Minutes</option>';
    echo '<option value="-1hours">1 Hour</option>';
  break;
  default:
    echo '<option value="'.$StartStr.'">Other</option>';
    echo '<option value="today">Today</option>';
    echo '<option value="-5minutes">5 Minutes</option>';
    echo '<option value="-1hours">1 Hour</option>';
    echo '<option value="-8hours">8 Hours</option>';
  break;
}
echo '</select></label></form></div>'."\n";

//Draw Calendar------------------------------------------------------
echo '<div class="col-half"><form action="?" method="get">';
echo '<label>Date: <input name="e" type="date" value="'.date('Y-m-d', $StartTime).'" /></label><br />';
echo '<label>Range: <input name="dr" type="number" min="1" max="30" value="'.$DateRange.'"/></label><br />';
echo '<input type="submit" value="Submit">';
echo '</form></div></div>';

//Draw Table Headers-------------------------------------------------
echo '<div class="row"><br />'."\n";
echo '<table class="domain-table">';             //Table Start
echo "<tr>\n";
echo "<th>#</th>\n";
if ($SortCol == 1) {
  if ($SortDir == 0) WriteTH(1, 1, 'Domain&#x25B4;');
  else WriteTH(1, 0, 'Domain&#x25BE;');
}
else {
  WriteTH(1, $SortDir, 'Domain');
}
echo "<th>G</th>\n";
echo "<th>W</th>\n";
if ($SortCol == 0) {
  if ($SortDir == 0) WriteTH(0, 1, 'Requests&#x25BE;');
  else WriteTH(0, 0, 'Requests&#x25B4;');
}
else {
  WriteTH(0, $SortDir, 'Requests');
}
echo "</tr>\n";

//Draw Table Cells---------------------------------------------------
$i = 1;
foreach ($SortedDomainList as $Str => $Value) {
  if ($i >= $StartPoint) {                       //Start drawing the table when we reach the StartPoint of Pagination
    if ($i >= $StartPoint + $ItemsPerPage) break;//Exit the loop at end of Pagination + Number of Items per page
    $Action = substr($Str,-1,1);                 //Last character tells us whether URL was blocked or not
    $Site = substr($Str, 0, -1);
    if ($Action == '+') {                        //+ = Allowed
      if ($i & 1) echo '<tr class="odd">';       //Light grey row on odd numbers
      else echo '<tr class="even">';             //White row on even numbers
      echo '<td>'. $i.'</td><td>'.$Site.'</td>';
    }
    elseif ($Action == '-') {                    //- = Blocked
      $SplitURL = explode('.', $Site);           //Find out wheter site was blocked by TLD or Tracker list
      $CountSubDomains = count($SplitURL);
      echo '<tr class="blocked">';               //Red row for blocked
      if ($CountSubDomains <= 1) {               //No TLD Given, this could be a search via address bar  
        echo '<td>'.$i.'</td><td>'.$Site.'<p class="small">Invalid domain</p></td>';
      }
      elseif (in_array('.'.$SplitURL[$CountSubDomains-1], $TLDBlockList)) {
        echo '<td>'.$i.'</td><td>'.$Site.'<p class="small">.'.$SplitURL[$CountSubDomains -1].' Blocked by Top Level Domain List</p></td>';
      }
      else {
        echo '<td>'.$i.'</td><td>'.$Site.'<p class="small">Blocked by Tracker List</p></td>';
      }
    }
    elseif ($Action == '1') {                    //1 = Local lookup
      echo '<tr class="local">';
      echo '<td>'.$i.'</td><td>'.$Site.'</td>';
    }
    echo '<td><a target="_blank" href="https://www.google.com/search?q='.$Site.'"><img class="icon" src="./images/search_icon.png" alt=""></a></td><td><a target="_blank" href="https://who.is/whois/'.$Site.'"><img class="icon" src="./images/whois_icon.png" alt=""></a></td><td>'.$Value.'</td></tr>'."\n";    
  }  
  $i++;
}

echo "</table></div>\n";
echo '<div class="row"><br /></div>';


//Pagination---------------------------------------------------------
if ($ListSize > $ItemsPerPage) {                 //Is Pagination needed
  $ListSize = ceil($ListSize / $ItemsPerPage);   //Calculate List Size
  $CurPos = 0;
  while ($CurPos < $ListSize) {                  //Find Current Page
    $CurPos++;
    if ($StartPoint < $CurPos * $ItemsPerPage) {
      break;					 //Leave loop when found
    }
  }

  echo '<div class="pag-nav">';
  echo "<br /><ul>\n";
  
  if ($CurPos == 1) {                            //At the beginning display blank box
    echo '<li><span>&nbsp;&nbsp;</span></li>';
    echo "\n";
    WriteLI('1', 0, true);
  }    
  else {                                         // << Symbol & Print Box 1
    WriteLI('&#x00AB;', $ItemsPerPage * ($CurPos - 2), false);
    WriteLI('1', 0, false);
  }

  if ($ListSize <= 4) {                          //Small Lists don't need fancy effects
    for ($i = 2; $i <= $ListSize; $i++) {	 //List of Numbers
      if ($i == $CurPos) {
        WriteLI($i, $ItemsPerPage * ($i - 1), true);
      }
      else {
        WriteLI($i, $ItemsPerPage * ($i - 1), false);
      }
    }
  }
  elseif ($ListSize > 4 && $CurPos == 1) {       // < [1] 2 3 4 T >
    WriteLI('2', $ItemsPerPage, false);
    WriteLI('3', $ItemsPerPage * 2, false);
    WriteLI('4', $ItemsPerPage * 3, false);
    WriteLI($ListSize, ($ListSize - 1) * $ItemsPerPage, false);
  }
  elseif ($ListSize > 4 && $CurPos == 2) {       // < 1 [2] 3 4 T >
    WriteLI('2', $ItemsPerPage, true);
    WriteLI('3', $ItemsPerPage * 2, false);
    WriteLI('4', $ItemsPerPage * 3, false);
    WriteLI($ListSize, ($ListSize - 1) * $ItemsPerPage, false);
  }
  elseif ($ListSize > 4 && $CurPos > $ListSize - 2) {// < 1 T-3 T-2 T-1 T > 
    for ($i = $ListSize - 3; $i <= $ListSize; $i++) {//List of Numbers
      if ($i == $CurPos) {
        WriteLI($i, $ItemsPerPage * ($i - 1), true);
      }
      else {
        WriteLI($i, $ItemsPerPage * ($i - 1), false);
    	}
      }
    }
  else {                                         // < 1 c-1 [c] c+1 T >
    for ($i = $CurPos - 1; $i <= $CurPos + 1; $i++) {//List of Numbers
      if ($i == $CurPos) {
        WriteLI($i, $ItemsPerPage * ($i - 1), true);
      }
      else {
        WriteLI($i, $ItemsPerPage * ($i - 1), false);
      }
    }
    WriteLI($ListSize, ($ListSize - 1) * $ItemsPerPage, false);
  }
    
  if ($CurPos < $ListSize) {                     // >> Symbol for Next
    WriteLI('&#x00BB;', $ItemsPerPage * $CurPos, false);
  }	
  echo "</ul></div>\n";  
}

?>
</div>
</body>
</html>

<?php
require('./include/global-vars.php');
require('./include/global-functions.php');
require('./include/menu.php');

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
  <script src="./include/queries.js"></script>
  <title>NoTrack - DNS Stats</title>
</head>

<body>
<?php
ActionTopMenu();
draw_topmenu();
draw_sidemenu();
echo '<div id="main">'.PHP_EOL;

$unsortedlog = array();
$sortedlog = array();

$TLDBlockList = array();
$CommonSites = array();                          //Merge Common sites list with Users Suppress list
$COMMONSITESLIST = array('cloudfront.net',
                         'googleusercontent.com',
                         'googlevideo.com',
                         'cedexis-radar.net',
                         'gvt1.com',
                         'deviantart.net',
                         'deviantart.com',
                         'stackexchange.com',
                         'tumblr.com');
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

$TIMELIST = array('today' => 'Today',
                  '-1minute' => '1 Minute',
                  '-5minutes' => '5 Minutes',
                  '-15minutes' => '15 Minutes',
                  '-30minutes' => '30 Minutes',
                  '-1hour' => '1 Hour',
                  '-4hours' => '4 Hours',
                  '-8hours' => '8 Hours',
                  '-12hours' => '12 Hours');


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
      if ($RowsPerPage != 500) echo '<input type="hidden" name="c" value="'.$RowsPerPage.'" />'.PHP_EOL;
    break;
    case 'Dir':
      if ($SortDir == 1) echo '<input type="hidden" name="dir" value="1" />'.PHP_EOL;
    break;
    case 'DR':
      if ($DateRange != 1) echo '<input type="hidden" name="dr" value="'.$DateRange.'" />'.PHP_EOL;
    break;
    case 'E':
      if ($StartStr != "") echo '<input type="hidden" name="e" value="'.$StartStr.'" />'.PHP_EOL;
    break;
    case 'Sort':
      if ($SortCol == 1) echo '<input type="hidden" name="sort" value="1" />'.PHP_EOL;
    break;
    case 'V':
      if ($View != 1) echo '<input type="hidden" name="v" value="'.$View.'" />'.PHP_EOL;
    break;
  }
  return null;
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

/********************************************************************
 *  Load Log Files
 *  
 *  1. Attempt to load TLDBlockList from Memcache
 *  2. If that fails then check if DomainQuickList file exists
 *  3. Read each line into TLDBlockList array and trim off \n
 *  4. Once loaded store TLDBlockList array in Memcache for 30 mins
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function load_logfiles() {
  global $sortedlog, $unsortedlog, $Mem;
  global $SortCol, $SortDir, $StartStr, $StartTime, $DateRange, $ExecTime, $View;
  
  $LoadList = true;                              //Assume Logs will need loading
  $SortList = true;                              //Assume Array will need sorting
  $MemSaveTime = 600;                            //How long to hold data in memory

  //How long to hold data in memcache based on how far back user is searching
  //Shorter time search = lower retention of Memcache
  if (($StartStr == '') || ($StartStr == 'today')) $MemSaveTime = 240;
  elseif ($StartTime >= $ExecTime - 300) $MemSaveTime = 30;    //-5 Min
  elseif ($StartTime >= $ExecTime - 1500) $MemSaveTime = 50;   //-15 Min
  elseif ($StartTime >= $ExecTime - 3600) $MemSaveTime = 90;   //-1 hour
  elseif ($StartTime >= $ExecTime - 28800) $MemSaveTime = 180; //-8 hours

  //Attempt to load SortedDomainList from Memcache
  $sortedlog = $Mem->get('sortedlog');   
  if ($sortedlog) {                              //Has array loaded?
    if (($StartStr == $Mem->get('StartStr')) && 
        ($DateRange == $Mem->get('DateRange')) && 
        ($View == $Mem->get('View'))) {
      if (($SortCol == $Mem->get('SortCol')) && 
          ($SortDir == $Mem->get('SortDir'))) {  //Check if search is same
        $SortList = false;
        $LoadList = false;      
      }
      else {
        $LoadList = false;                       //No need to load list
        $sortedlog = array();                    //Delete data in array     
      }
    }
    else {
      $Mem->delete('StartStr');                  //Delete old variables from Memcache
      $Mem->delete('SortCol');
      $Mem->delete('SortDir');
      $Mem->delete('DateRange');
      $Mem->delete('unsortedlog');
      $Mem->delete('sortedlog');
      $Mem->delete('View');
      $sortedlog = array();                      //Delete data in array
    }    
  }
    
  if ($LoadList) {                               //Load domain list from file  
    //Are we loading Todays logs or Historic logs?
    if ($StartTime > (time() - 86400)) load_todaylog();
    else load_historiclogs();
    $Mem->set('unsortedlog', $unsortedlog, 0, $MemSaveTime);
  }
  else {                                         //Load domain list from memcache
  $unsortedlog = $Mem->get('unsortedlog');
    if (!$unsortedlog) {                         //Something wrong, get it reloaded
      if ($StartTime > (time() - 86400)) load_todaylog();
      else load_historiclogs();
      $Mem->set('unsortedlog', $unsortedlog, 0, $MemSaveTime);
    }
  }

  if ($SortList) {                               //Sort Array of Domains from log file    
    if ($SortCol == 1) {
      if ($SortDir == 0) ksort($unsortedlog);
      else krsort($unsortedlog);
    }
    else {
      if ($SortDir == 0) arsort($unsortedlog);   //Sort array by highest number of hits
      else asort($unsortedlog);
    }
    
    $sortedlog = array_keys($unsortedlog);
    $Mem->set('StartStr', $StartStr, 0, $MemSaveTime);       //Store variables in Memcache
    $Mem->set('SortCol', $SortCol, 0, $MemSaveTime);
    $Mem->set('SortDir', $SortDir, 0, $MemSaveTime);
    $Mem->set('DateRange', $DateRange, 0, $MemSaveTime);
    $Mem->set('sortedlog', $sortedlog, 0, $MemSaveTime);
    $Mem->set('View', $View, 0, $MemSaveTime);
  }
  
  return null;
}

/********************************************************************
 *  Load TLD Block List
 *  
 *  1. Attempt to load TLDBlockList from Memcache
 *  2. If that fails then check if DomainQuickList file exists
 *  3. Read each line into TLDBlockList array and trim off \n
 *  4. Once loaded store TLDBlockList array in Memcache for 30 mins
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function load_tldblocklist() {
  global $TLDBlockList, $Mem, $DomainQuickList;
  
  $TLDBlockList = $Mem->get('TLDBlockList');
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

/********************************************************************
 *  Load Today Log
 *  
 *  1. Loads the /var/log/notrack.log
 *  2. Add queries into array $querylist
 *  3. Match response (blocked/allowed/local) with query
 *  4. If match found then add to $unsortedlog and delete from $querylist
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function load_todaylog() {
//Dnsmasq log line consists of:
//0 - Month (3 characters)
//1 - Day (d or dd)
//2 - Time (dd:dd:dd) - Group 1
//3 - dnsmasq[d{1-6}]
//4 - Function (query, forwarded, reply, cached, config) - Group 2
//5 - Query Type [A/AAA] - Group 3
//5 - Website Requested - Group 4
//6 - Action (is|to|from) - Group 5
//7 - Return value - Group 6

  global $unsortedlog, $StartTime, $StartStr, $View;
  
  $dedup_answer = '';
  $pattern = '';
  $url='';
  $querylist = array();
  
  $fh = fopen('/var/log/notrack.log', 'r') or die('Error unable to open /var/log/notrack.log');
  
  if ($View == 1) $pattern = '/^\w{3}\s\s?\d{1,2}\s(\d{2}\:\d{2}\:\d{2})\sdnsmasq\[\d{1,6}\]\:\s(query|reply|config|\/etc\/localhosts\.list)(\[[A]{1,4}\])?\s(\S+)\s(is|to|from)(.*)$/';
  elseif ($View == 2) $pattern = '/^\w{3}\s\s?\d{1,2}\s(\d{2}\:\d{2}\:\d{2})\sdnsmasq\[\d{1,6}\]\:\s(query|reply|\/etc\/localhosts\.list)(\[[A]{1,4}\])?\s(\S+)\s(is|to|from)(.*)$/';
  elseif ($View == 3) $pattern = '/^\w{3}\s\s?\d{1,2}\s(\d{2}\:\d{2}\:\d{2})\sdnsmasq\[\d{1,6}\]\:\s(query|config)(\[[A]{1,4}\])?\s(\S+)\s(is|to|from)(.*)$/';
    
  if (($StartStr == '') || ($StartStr == 'today')) {       //Read whole log
    while (!feof($fh)) {
      $line = fgets($fh);
      if (preg_match($pattern, $line, $matches) > 0) {
        if ($matches[2] == 'query') {                      //Query line
          if ($matches[3] == '[A]') {                      //Only IPv4 (prevent double)
            $querylist[$matches[4]] = true;                //Add to query to $querylist
          }
        }
        elseif ($matches[4] != $dedup_answer) {            //Simplify processing of multiple IP addresses returned
          if (array_key_exists($matches[4], $querylist)) { //Does answer match a query?
            if ($matches[2] == 'reply') $url = simplify_url($matches[4]).'+';
            elseif ($matches[2] == 'config') $url = simplify_url($matches[4]).'-';
            elseif ($matches[2] == '/etc/localhosts.list') $url = simplify_url($matches[4]).'1';
            
            if (array_key_exists($url, $unsortedlog)) $unsortedlog[$url]++; //Tally
            else $unsortedlog[$url] = 1;
                    
            unset($querylist[$matches[4]]);                //Delete query from $querylist
          }
          $dedup_answer = $matches[4];                     //Deduplicate answer
        }
      }
    }
  }  
 
  else {                                                   //Read last x minutes
    while (!feof($fh)) {
      $line = fgets($fh);
      if (preg_match($pattern, $line, $matches) > 0) {
        if (strtotime($matches[1]) >= $StartTime) {        //Check if time in log > Earliest
          if ($matches[2] == 'query') {                    //Query line
            if ($matches[3] == '[A]') {                    //Only IPv4 (prevent double)
              $querylist[$matches[4]] = true;              //Add to query to $querylist
            }
          }
          elseif ($matches[4] != $dedup_answer) {          //Simplify processing of multiple IP addresses returned
            if (array_key_exists($matches[4], $querylist)) { //Does answer match a query?
              if ($matches[2] == 'reply') $url = simplify_url($matches[4]).'+';
              elseif ($matches[2] == 'config') $url = simplify_url($matches[4]).'-';
              elseif ($matches[2] == '/etc/localhosts.list') $url = simplify_url($matches[4]).'1';
            
              if (array_key_exists($url, $unsortedlog)) $unsortedlog[$url]++; //Tally
              else $unsortedlog[$url] = 1;
            
              unset($querylist[$matches[4]]);              //Delete query from $querylist
            }
            $dedup_answer = $matches[4];                   //Deduplicate answer
          }
        }
      }
    }
  }
 
  fclose($fh);                                   //Close log file
  return null;
}

/********************************************************************
 *  Load Historic Logs
 *  
 *  1. Load relevant files from /var/log/notrack
 *  2. Add each line to $unsortedlog
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function load_historiclogs() {
  global $DateRange, $StartTime, $View, $unsortedlog, $ExecTime;
  
  $LogFile = '';
  $url = '';
  $LD = $StartTime + 86400;                      //Log files get cached the following day, so we move the start date on by 86,400 seconds (24 hours)
  
  if ($View == 1) $pattern = '/^(.*)(\-|\+|1)$/';
  elseif ($View == 2) $pattern = '/^(.*)(\+|1)$/';
  elseif ($View == 3) $pattern = '/^(.*)(\-)$/';
  
  for ($i = 0; $i < $DateRange; $i++) {
    $LogFile = '/var/log/notrack/dns-'.date('Y-m-d', $LD).'.log';
    if (file_exists($LogFile)) {
      $FileHandle= fopen($LogFile, 'r');
      while (!feof($FileHandle)) {
        $line = trim(fgets($FileHandle));                  //Read Line of LogFile
        if (preg_match($pattern, $line, $matches) > 0) {          
          $url = simplify_url($matches[1]).$matches[2];          
          if (array_key_exists($url, $unsortedlog)) $unsortedlog[$url]++;
          else $unsortedlog[$url] = 1;
        }        
      }
    }  
  
    $LD = $LD + 86400;                           //Add per run of loop 24 Hours
    if ($LD > $ExecTime + 86400) {               //Don't exceed today      
      break;
    }
  }  
}

/********************************************************************
 *  Simplify URL
 *  
 *  1: Drop www (its unnecessary and not all websites use it now)
 *  2. Extract domain.tld, including double-barrelled domains
 *  3. Check if site is to be suppressed (present in $CommonSites)
 *
 *  Params:
 *    $url - URL To Simplify
 *  Return:
 *    Simplified URL
 */
function simplify_url($url) {  
  global $CommonSites;
  $simpleurl = '';
    
  if (substr($url,0,4) == 'www.') $simpleurl  = substr($url,4); 
  else $simpleurl  = $url;
  
  if (preg_match('/[A-Za-z0-9\-\_]{2,63}\.(gov\.|org\.|co\.|com\.)?[A-Za-z0-9\-]{2,63}$/', $simpleurl , $Match) == 1) {
    if (in_array($Match[0],$CommonSites)) return '*.'.$Match[0];
    else return $simpleurl ;
  }
 
  return $simpleurl ;
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

if ($Config['bl_tld'] == 1) load_tldblocklist(); //Load TLD Blocklist if being used

//Merge Users Config of Suppress List with CommonSitesList
if ($Config['Suppress'] == '') $CommonSites = array_merge($COMMONSITESLIST);
else $CommonSites = array_merge($COMMONSITESLIST, explode(',', $Config['Suppress']));
unset($COMMONSITESLIST);

load_logfiles();                                 //Load either Today or Historic logs

$ListSize = count($sortedlog);
if ($StartPoint >= $ListSize) $StartPoint = 1;   //Start point can't be greater than the list size
//Trim viewable section out of array
$sortedlog = array_slice($sortedlog, $StartPoint - 1, $RowsPerPage);


//Draw Filter Dropdown list------------------------------------------
echo '<div class="sys-group"><div class="col-half">'.PHP_EOL;
echo '<form action="?" method="get">';
echo '<input type="hidden" name="start" value="'.$StartPoint.'" />'.PHP_EOL;
AddHiddenVar('C');
AddHiddenVar('Sort');
AddHiddenVar('Dir');
AddHiddenVar('E');
AddHiddenVar('DR');
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
echo '</select>'.PHP_EOL;
echo '</form>'.PHP_EOL;

//Draw Time Dropdown list------------------------------------------
echo '<form action="?" method="get">';
echo '<input type="hidden" name="start" value="'.$StartPoint.'" />'.PHP_EOL;
AddHiddenVar('C');
AddHiddenVar('Sort');
AddHiddenVar('Dir');
AddHiddenVar('V');
AddHiddenVar('DR');
echo '<span class="filter">Time:</span><select name="e" onchange="submit()">';
if (array_key_exists($StartStr, $TIMELIST)) {    //Is the current time value in TimeList array?
  echo '<option value="'.$StartStr.'">'.$TIMELIST[$StartStr].'</option>'.PHP_EOL;
}
else {                                           //No - work out what the user is searching for
  //Regex matches
  //-
  //Group 1 - 1 to 4 numbers
  //Group 2 - a word
  //End string
  if (preg_match('/^\-(\d{1,4})(\[a-z]+)$/', $StartStr, $matches) > 0) {
    echo '<option value="'.$StartStr.'">'.$matches[1].' '.ucfirst($matches[2]).'</option>'.PHP_EOL;
  }
  else {                                         //No match on regex means a date value
    echo '<option value="today">Historic</option>'.PHP_EOL;
  }
}
foreach ($TIMELIST as $key => $value) {          //Output TimeList array as a select box
  if ($StartStr != $key) {                       //Ignore current setting
    echo '<option value="'.$key.'">'.$value.'</option>'.PHP_EOL;
  }
}
echo '</select></form></div>'.PHP_EOL;

//Draw Calendar------------------------------------------------------
echo '<div class="col-half"><form action="?" method="get">'.PHP_EOL;
AddHiddenVar('C');
AddHiddenVar('Sort');
AddHiddenVar('Dir');
AddHiddenVar('V');
echo '<span class="filter">Date: </span><input name="e" type="date" value="'.date('Y-m-d', $StartTime).'" onchange="submit()"/><br />';
echo '<span class="filter">Range: </span><input name="dr" type="number" min="1" max="30" value="'.$DateRange.'" onchange="submit()"/><br /><br />'.PHP_EOL;
echo '</form>'.PHP_EOL;
echo '</div></div>'.PHP_EOL;

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
$i = $StartPoint;
foreach ($sortedlog as $Str) {
  $Value = $unsortedlog[$Str];    
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
  <a target="_blank" href="'.$Config['WhoIsUrl'].$Site.'"><img class="icon" src="./images/whois_icon.png" alt="W" title="Whois"></a>'.$ReportSiteStr;
  echo '</td><td>'.$Value.'</td></tr>'.PHP_EOL;
  
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

<div id="scrollup" class="button-scroll" onclick="ScrollToTop()"><img src="./svg/arrow-up.svg" alt="up"></div>
<div id="scrolldown" class="button-scroll" onclick="ScrollToBottom()"><img src="./svg/arrow-down.svg" alt="down"></div>

<div id="stats-box">
<div class="dialog-bar">Report</div>
<span id="sitename">site</span>
<span id="statsmsg">something</span>
<span id="statsblock1"><a class="button-blue" href="#">Block Whole</a> Block whole domain</span>
<span id="statsblock2"><a class="button-blue" href="#">Block Sub</a> Block just the subdomain</span>
<form name="reportform" action="https://quidsup.net/notrack/report.php" method="post" target="_blank">
<input type="hidden" name="site" id="siterep" value="none">
<span id="statsreport"><input type="submit" value="Report"></span>
<!--<span id="statsreport"><a class="button-blue" href="#">Report</a></span>-->
</form>
<br />
<div class="centered"><h6 class="button-grey" onclick="HideStatsBox()">Cancel</h6></div>
<div class="close-button" onclick="HideStatsBox()"><img src="./svg/button_close.svg" onmouseover="this.src='./svg/button_close_over.svg'" onmouseout="this.src='./svg/button_close.svg'" alt="close"></div>
</div>

</body>
</html>

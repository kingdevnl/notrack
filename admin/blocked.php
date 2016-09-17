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
  <link href="./css/help.css" rel="stylesheet" type="text/css" />
  <link rel="icon" type="image/png" href="./favicon.png" />
  <script src="./include/menu.js"></script>
  <script src="./include/stats.js"></script>
  <title>NoTrack - Sites Blocked</title>  
</head>

<body>
<?php
ActionTopMenu();
draw_topmenu();
draw_sidemenu();
echo '<div id="main">';

$SiteList = array();

//Add GET Var to Link if Variable is used----------------------------
function AddGetVar($Var) {
  global $RowsPerPage, $SearchStr, $StartPoint;
  switch ($Var) {
    case 'C':
      if ($RowsPerPage != 500) return '&amp;c='.$RowsPerPage;
      break;
    /*case 'S':
      if ($SearchStr != '') return '&amp;s='.$SearchStr;
      break;*/
    case 'Start':
      if ($StartPoint != 1) return '&amp;start='.$StartPoint;
      break;
    default:
      echo 'Invalid option in AddGetVar';
      die();
  }
  return '';
}

//WriteLI Function for Pagination Boxes-------------------------------
function WriteLI($Character, $Start, $Active) {
  if ($Active) {
    echo '<li class="active"><a href="?start='.$Start.AddGetVar('C').'">'.$Character.'</a></li>'.PHP_EOL;
  }
  else {
    echo '<li><a href="?start='.$Start.AddGetVar('C').'">'.$Character.'</a></li>'.PHP_EOL;
  }  
  return null;
}
//-------------------------------------------------------------------
function DisplayPagination($LS) {
  //$LS = List Site
  global $RowsPerPage, $StartPoint;

  $ListSize = ceil($LS / $RowsPerPage);          //Calculate List Size
  $CurPos = floor($StartPoint / $RowsPerPage)+ 1;//Calculate Current Position
  
  echo '<div class="pag-nav"><ul>'.PHP_EOL;
    
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
  echo '</ul></div>'.PHP_EOL;
}
//-------------------------------------------------------------------
function Load_Access_Log() {
  global $SiteList, $LogLightyAccess;
  $Dedup = '';
  $Action='';
  $URL='';
  $i=0;
  $TempList = array();
  
  //Example Log Data
  //1471892585|polling.bbc.co.uk|GET /appconfig/iplayer/android/4.19.3/policy.json HTTP/1.1|200|26
  //1471892807|cmdts.ksmobile.com|POST /c/ HTTP/1.1|200|26
  //1471771627|notrack.local|GET /admin/svg/menu_dhcp.svg HTTP/1.1|304|0
  //1471773009|notrack.local|GET /admin/stats.php HTTP/1.1|200|117684
  
  //Regex Matches:
  //1: \d{1,23} - 64bit Time value
  //2: [A-Za-z0-9\-\.] One or more times Left-hand side of URL (before /)
  //3: (GET|POST) GET or POST
  //Negate /admin and /favicon.ico
  //4: [Non whitespace] Any number of times Right-hand side of URL (after /)
  //HTTP 1.1 or 2.0
  //200 - HTTP Ok (Not interested in 304,404)
  
  if (file_exists($LogLightyAccess)) {
    $FileHandle= fopen($LogLightyAccess, 'r');
    while (!feof($FileHandle)) {
      $Line = trim(fgets($FileHandle));          //Read Line of LogFile
      //echo $Line.'<br />';
      if (preg_match('/^(\d{1,23})\|([A-Za-z0-9\-\_\.]+)\|(GET|POST)\s(?!\/admin|\/favicon\.ico)(\S*)\sHTTP\/\d\.\d\|200/', $Line, $Matches) > 0) {
        if ($Matches[3] == 'GET') $Action='<span class="green">GET</span> ';
        else $Action='<span class="violet">POST</span> ';
        $URL = $Matches[2].$Matches[4];
        
        //If string length too long, then attempt to cut out segment of known file name of URI and join to shortened URL
        //For unknown file just show the first 45 characters and display+ button
        if (strlen($URL) > 48) {
          if (preg_match('/[A-Za-z0-9\-_\%\&\?\.#]{1,18}\.(php|html|js|json|jpg|gif|png)$/', $Matches[4], $URIMatches) > 0) {
            $URL = substr($URL, 0, (45 - strlen($URIMatches[0]))).'.../'.$URIMatches[0].' <span id="b'.$i.'" class="button-small pointer" onclick="ShowFull(\''.$i.'\')">+</span>'.'<p id="r'.$i.'" class="smallhidden">'.$Matches[2].$Matches[4].'</p>';
          }
          else {
            $URL = substr($URL, 0, 45).'... <span id="b'.$i.'" class="button-small pointer" onclick="ShowFull(\''.$i.'\')">+</span>'.'<p id="r'.$i.'" class="smallhidden">'.$Matches[2].$Matches[4].'</p>';
          }
        }      
        if ($Matches[2] == $Dedup) {
          $TempList[count($TempList)-1] = array($Matches[1], $Action.$URL);
        }
        else {
          $TempList[] = array($Matches[1], $Action.$URL);
          $Dedup = $Matches[2];
        }      
      }
      $i++;
    }
    fclose($FileHandle);
    
    $SiteList = array_reverse($TempList);
  }
  else {                                         //Log not found
    return false;
  }
  return true;
}

//Main---------------------------------------------------------------

//Start with GET variables
$StartPoint = Filter_Int('start', 1, PHP_INT_MAX-2, 1);
$RowsPerPage = Filter_Int('c', 2, PHP_INT_MAX, 500);

if (!Load_Access_Log()) {
  die ('Unable to open Lighttpd Access Log '.$LogLightyAccess);
}

$ListSize = count($SiteList);

if ($StartPoint >= $ListSize) $StartPoint = 1;   //Start point can't be greater than the list size
  
if ($RowsPerPage < $ListSize) {                  //Slice array if it's larger than RowsPerPage
  $SiteList = array_slice($SiteList, $StartPoint, $RowsPerPage);
}

//Draw Table Headers-------------------------------------------------
echo '<div class="sys-group">'.PHP_EOL;
echo '<table id="blocked-table">';               //Table Start
echo '<tr><th>#</th><th>Time</th><th>Site</th></tr>'.PHP_EOL;

//Draw Table Cells---------------------------------------------------
$i = $StartPoint;
foreach ($SiteList as $Site) {
  echo '<tr><td>'.$i.'</td><td>'.date('d M - H:i:s',$Site[0]).'</td><td>'.$Site[1].'</td></tr>'.PHP_EOL;
  $i++;  
}

echo '</table></div>'.PHP_EOL;

if ($ListSize > $RowsPerPage) {
  echo '<div class="sys-group">'.PHP_EOL;
  DisplayPagination($ListSize);
  echo '</div>'.PHP_EOL;
}

?>
</div>
<div id="scrollup" class="button-scroll" onclick="ScrollToTop()"><img src="./svg/arrow-up.svg" alt="up"></a></div>
<div id="scrolldown" class="button-scroll" onclick="ScrollToBottom()"><img src="./svg/arrow-down.svg" alt="down"></a></div>
</body>
</html>

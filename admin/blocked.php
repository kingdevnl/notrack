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
  <title>NoTrack - Sites Blocked</title>
</head>

<body>
<div id="main">
<?php
ActionTopMenu();
DrawTopMenu();

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
  $SplitLine = array();
  
  if (file_exists($LogLightyAccess)) {
    $FileHandle= fopen($LogLightyAccess, 'r');
    while (!feof($FileHandle)) {
      $Line = trim(fgets($FileHandle));          //Read Line of LogFile
      if ($Line != '') {
        $SplitLine = explode('|', $Line);
        if ((substr($SplitLine[2], 0, 10) != 'GET /admin') &&
          (substr($SplitLine[2], 0, 12) != 'GET /favicon')) {
          if (substr($SplitLine[1], 0, 4) == 'www.') $SplitLine[1] = substr($SplitLine[1], 4);
          $SiteList[] = array($SplitLine[0], $SplitLine[1]);
        }
      }
    }
    fclose($FileHandle);
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
  echo '<tr><td>'.$i.'</td><td>'.date('d M - H:i:s',$Site[0]).'</td><td>'.$Site[1].'</td></tr>';
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
</body>
</html>

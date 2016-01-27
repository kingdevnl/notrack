<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <link href="./css/master.css" rel="stylesheet" type="text/css" />
  <link rel="icon" type="image/png" href="./favicon.png" />
  <title>NoTrack Tracker List</title>  
</head>

<body>
<div id="main">
<?php
$CurTopMenu = 'blocklist';
include('topmenu.html');
echo "<h1>Tracker Blocklist</h1>\n";

$Show = 'all';
$SingleLetter=false;
$SingleNumber=false;
$Letters = array('a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z');
$Numbers = array('0','1','2','3','4','5','6','7','8','9');

$Mem = new Memcache;                             //Initiate Memcache
$Mem->connect('localhost');

if (isset($_GET['show'])) {
  if ($_GET['show'] == 'num') {
    $SingleNumber = true;
    $Show = 'num';
  }
  if (in_array($_GET['show'], $Letters)) { 
    $Show = $_GET['show'];
    $SingleLetter = true;
  } 
}

$SearchStr = '';
if ($_GET['s']) {
  //Allow only characters a-z A-Z 0-9 ( ) . _ - and \whitespace
  $SearchStr = preg_replace('/[^a-zA-Z0-9\(\)\.\s_-]/', '', $_GET['s']);
  $SearchStr = strtolower($SearchStr);  
}

//Add GET Var to Link if Variable is used----------------------------
function AddGetVar($Var) {
//Function isn't used much yet, but may expand in future
  global $SearchStr;
  if ($SearchStr != '') return '&amp;s='.$SearchStr;
  return '';
}
//WriteLI Function for Pagination Boxes-------------------------------
function WriteLI($Character, $Active) {
  if ($Active) {
    echo '<li class="active"><a href="?show=all'.AddGetVar('s').'">';
  }
  else {
    echo '<li><a href="?show='.strtolower($Character).AddGetVar('s').'">';
  }  
  echo "$Character</a></li>\n";  
  return null;
}
//Load Blocklist------------------------------------------------------
function Load_BlockList() {
//Blocklist is held in Memcache for 10 minutes
  global $TrackerBlockList, $Mem;
  
  $TrackerBlockList = $Mem->get('TrackerBlockList');
  if (! $TrackerBlockList) {
    $FileHandle = fopen('/etc/notrack/tracker-quick.list', 'r') or die('Error unable to open /etc/notrack/tracker-quick.list');
    while (!feof($FileHandle)) {
      $TrackerBlockList[] = trim(fgets($FileHandle));
    }
    fclose($FileHandle);    
    $Mem->set('TrackerBlockList', $TrackerBlockList, 0, 600);
  }
  return null;
}

//Main---------------------------------------------------------------
Load_BlockList();

//Character Select---------------------------------------------------
echo '<div class="pag-nav">';
echo "<br /><ul>\n";
if ($Show == 'all') WriteLI('All', true);
else WriteLI('All', false);
WriteLI('Num', $SingleNumber);
foreach($Letters as $Val) {
  if ($Val == $Show) WriteLI(strtoupper($Val), true);
  else WriteLI(strtoupper($Val), false);
}
echo "</ul></div>\n";
echo '<div class="row"><br /></div>';

//Searchbox----------------------------------------------------------
echo '<div class="centered"><br />'."\n";
echo '<form action="?" method="get">';
echo '<input type="hidden" name="show" value="'.$Show.'" />'."\n";
if ($SearchStr == '') echo '<input type="text" name="s" id="search" placeholder="Search">';
else echo '<input type="text" name="s" id="search" value="'.$SearchStr.'">';
echo "</form></div>\n";


//Draw Table---------------------------------------------------------
echo '<div class="row"><br />'."\n";
echo '<table id="block-table">'."\n";
echo '<tr><th>#</th><th>Site</th></tr>'."\n";
$i = 1;

if ($SearchStr == '') {
  foreach ($TrackerBlockList as $Site) {
    if (($SingleLetter) || ($SingleNumber)) {
      $Char1 = substr($Site,0,1);
      if ($SingleLetter) {
        if ($Char1 == $Show) {
          echo '<tr><td>'.$i.'</td><td>'.$Site.'</td></tr>'."\n";
        }
      }
      if ($SingleNumber) {
        if (in_array($Char1, $Numbers)) {
          echo '<tr><td>'.$i.'</td><td>'.$Site.'</td></tr>'."\n";
        }
      }
    }
    else {
      echo '<tr><td>'.$i.'</td><td>'.$Site.'</td></tr>'."\n";
    }
    $i++;
  }
}
else {
  foreach ($TrackerBlockList as $Site) {
    if (($SingleLetter) || ($SingleNumber)) {
      $Char1 = substr($Site,0,1);
      if ($SingleLetter) {
        if ($Char1 == $Show) {
          if (strpos($Site, $SearchStr) !== false) {
            echo '<tr><td>'.$i.'</td><td>'.$Site.'</td></tr>'."\n";        
          }
        }
      }
      if ($SingleNumber) {
        if (in_array($Char1, $Numbers)) {
          if (strpos($Site, $SearchStr) !== false) {
            echo '<tr><td>'.$i.'</td><td>'.$Site.'</td></tr>'."\n";        
          }
        }
      }
    }
    else {
      if (strpos($Site, $SearchStr) !== false) {
        echo '<tr><td>'.$i.'</td><td>'.$Site.'</td></tr>'."\n";        
      }
    }
    $i++;
  }
}
echo "</table></div>\n";
?> 
</div>
</body>
</html>

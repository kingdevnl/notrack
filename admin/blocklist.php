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
$CurTopMenu = 'config';
include('./include/topmenu.html');
echo "<h1>Tracker Blocklist</h1>\n";

$Mem = new Memcache;                             //Initiate Memcache
$Mem->connect('localhost');

$SearchStr = '';
if ($_GET['s']) {
  //Allow only characters a-z A-Z 0-9 ( ) . _ - and \whitespace
  $SearchStr = preg_replace('/[^a-zA-Z0-9\(\)\.\s_-]/', '', $_GET['s']);
  $SearchStr = strtolower($SearchStr);  
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

echo '<div class="pag-nav"><ul>'."\n";           //Config Menu
echo '<li><a href="./config.php" title="General">General</a></li>'."\n";
echo '<li class="active"><a href="./blocklist.php" title="Block List">Block List</a></li>'."\n";
echo '<li><a href="./tldblocklist.php" title="Top Level Domain Blocklist">TLD Block List</a></li>'."\n";
echo "</ul></div>\n";
echo '<div class="row"><br /></div>';            //Spacer

//Searchbox----------------------------------------------------------
echo '<div class="centered"><br />'."\n";
echo '<form action="?" method="get">';
if ($SearchStr == '') echo '<input type="text" name="s" id="search" placeholder="Search">';
else echo '<input type="text" name="s" id="search" value="'.$SearchStr.'">';
echo "</form></div>\n";


//Draw Table---------------------------------------------------------
echo '<div class="row"><br />'."\n";
echo '<table id="block-table">'."\n";
$i = 1;

if ($SearchStr == '') {
  foreach ($TrackerBlockList as $Site) {
    echo '<tr><td>'.$i.'</td><td>'.$Site.'</td></tr>'."\n";
    $i++;
  }
}
else {
  foreach ($TrackerBlockList as $Site) {
    if (strpos($Site, $SearchStr) !== false) {
      echo '<tr><td>'.$i.'</td><td>'.$Site.'</td></tr>'."\n";
      $i++;
    }
  }
}
echo "</table></div>\n";
?> 
</div>
</body>
</html>

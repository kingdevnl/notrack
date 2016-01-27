<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <link href="./css/master.css" rel="stylesheet" type="text/css" />
    <link rel="icon" type="image/png" href="./favicon.png" />
    <title>NoTrack Upgrade</title>
</head>

<body>
<div id="main">
<?php
$CurTopMenu = 'upgrade';
include('topmenu.html');
echo "<h1>NoTrack Upgrade</h1>\n";
echo  "<br />\n";

$Version="0.5";
$Config = array();

function Load_Config_File() {
  global $Config;
  if (!file_exists('/etc/notrack/notrack.conf')) {
    die("Error file /etc/notrack/notrack.conf doesn't exist");
  }
  $Config = parse_ini_file('/etc/notrack/notrack.conf');
  
  return null;
}

//Main---------------------------------------------------------------
if (isset($_GET['u'])) {                        //Check if we are running upgrade or displaying status
  if ($_GET['u'] == '1') {                      //Doing the upgrade
    echo '<div class="splitrow"><h3>Upgrading NoTrack</h3>';
    echo '<pre>';
    //passthru('/usr/local/sbin/notrack -b');
    echo "Web upgrade is not implemented in this version\n";
    echo "Execute via Bash with: notrack --upgrade";
    echo "</pre>\n";
    echo "<br /></div>\n";
    echo '<h3><a class="linkbutton" href="./">Back</a></h3>';
  }
}
else {                                           //Just displaying status
  Load_Config_File();  
  
  if (array_key_exists('LatestVersion', $Config)) {
    if ($Version == $Config[LatestVersion]) {    //See if upgrade Needed
      echo '<p>You&#39;re running the latest version v'.$Version.'</p>';
      echo '<h3><a class="linkbutton" href="./">Back</a></h3>';      
    }
    else { 
      echo '<div class="row">';
      echo '<p>Currently running version: v'.$Version.'</p>';
      echo '<p>Latest version available: v'.$Config[LatestVersion].'</p><br /></div>'."\n";
      echo '<div class="splitrow"><h3>Changelog</h3>';
      $ch = curl_init();                           //Initiate curl
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
      curl_setopt($ch, CURLOPT_URL,'https://raw.githubusercontent.com/quidsup/notrack/master/changelog.txt');
      $Data = curl_exec($ch);                      //Download Changelog
      curl_close($ch);                             //Close curl
      echo "<pre>\n";
      echo $Data;
      echo "</pre></div>\n";
      echo '<h3><a class="linkbutton" href="?u=1">Upgrade</a>&nbsp;<a class="linkbutton" href="./">Back</a></h3>';
    }
  }
  else {                                       //Config line missing
    echo '<div class="row">';
    echo '<p>Unable to identify latest version available. <br />Fix with:</p>';
    echo '<pre>';
    echo "sudo notrack\n";
    echo "</pre>\n";
    echo "<br /></div>\n";    
  }
  
}
?> 
</div>
</body>
</html>

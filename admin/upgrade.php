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
  <title>NoTrack Upgrade</title>
</head>

<body>
<?php
ActionTopMenu();
draw_topmenu();
draw_configmenu();
echo '<div id="main">'.PHP_EOL;

//Main---------------------------------------------------------------
if (isset($_GET['u'])) {                        //Check if we are running upgrade or displaying status
  if ($_GET['u'] == '1') {                      //Doing the upgrade
    echo '<div class="sys-group">'.PHP_EOL;
    echo '<h5>NoTrack Upgrade</h5></div>'.PHP_EOL;
    
    ExecAction('upgrade-notrack', false);
    echo '<pre>';
    passthru('sudo ntrk-exec 2>&1');
    //echo $Msg;
    echo '</pre>'.PHP_EOL;
    
    echo '<div class="sys-group">'.PHP_EOL;
    echo '<div class="centered">'.PHP_EOL;       //Center div for button
    echo '<button class="button-blue" onclick="window.location=\'./\'">Back</button>'.PHP_EOL;    
    echo '</div></div>'.PHP_EOL;
    $Mem->delete('Config');                      //Delete config from Memcache
  }
  else {
    echo 'Invalid upgrade request';
  }
}

else {                                           //Just displaying status
  if ($Version == $Config['LatestVersion']) {    //See if upgrade Needed
    DrawSysTable('NoTrack Upgrade');
    DrawSysRow('Status', 'Running the latest version v'.$Version);
    DrawSysRow('Force Upgrade', 'Force upgrade to Development version of NoTrack<br /><button class="button-danger" onclick="window.location=\'?u=1\'">Upgrade</button>');
    echo '</table>'.PHP_EOL;
    echo '</div></div>'.PHP_EOL;
  }
  else {
    DrawSysTable('NoTrack Upgrade');
    DrawSysRow('Status', 'Running version v'.$Version.'<br />Latest version available: v'.$Config['LatestVersion']);    
    DrawSysRow('Commence Upgrade', '<button class="button-blue" onclick="window.location=\'?u=1\'">Upgrade</button>');
    echo '</table>'.PHP_EOL;
    echo '</div></div>'.PHP_EOL;
   }
   
   //Display changelog
   if (extension_loaded('curl')) {               //Check if user has Curl installed
    $ch = curl_init();                           //Initiate curl
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_URL,'https://raw.githubusercontent.com/quidsup/notrack/master/changelog.txt');
    $Data = curl_exec($ch);                      //Download Changelog
    curl_close($ch);                             //Close curl
    echo '<pre>'.PHP_EOL;
    echo $Data;                                  //Display Data
    echo '</pre>'.PHP_EOL;;
  }  
}
?> 
</div>
</body>
</html>

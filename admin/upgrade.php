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
  <title>NoTrack Upgrade</title>
</head>

<body>
<div id="main">
<?php
ActionTopMenu();
DrawTopMenu();
echo '<h1>NoTrack Upgrade</h1>'.PHP_EOL;


//Main---------------------------------------------------------------
if (isset($_GET['u'])) {                        //Check if we are running upgrade or displaying status
  if ($_GET['u'] == '1') {                      //Doing the upgrade
    echo '<div class="row"><h5>Upgrading NoTrack</h5>';
    echo '<pre>';
    //passthru('/usr/local/sbin/notrack -b');
    echo "Web upgrade is not implemented in this version\n";
    echo "Execute via Bash with: notrack --upgrade";
    echo "</pre>\n";
    echo "<br /></div>\n";
    echo '<div class="centered">'."\n";          //Center div for button
    echo '<button class="button-blue" type="reset"   onclick="window.location=\'./\'">Back</button>'.PHP_EOL;
    echo "</div>\n";
  }
}
else {                                           //Just displaying status  
  if ($Version == $Config['LatestVersion']) {    //See if upgrade Needed
    echo '<p>You&#39;re running the latest version v'.$Version.'</p><br />';
    echo '<div class="centered">'.PHP_EOL;       //Center div for button
    echo '<button class="button-blue" type="reset"   onclick="window.location=\'./\'">Back</button>'.PHP_EOL;
    echo '</div>'.PHP_EOL;
  }
  else { 
    echo '<div class="row">';
    echo '<p>Currently running version: v'.$Version.'</p>';
    echo '<p>Latest version available: v'.$Config['LatestVersion'].'</p>'.PHP_EOL;
    echo '<br /><br /></div>'.PHP_EOL;
    echo '<div class="row"><h5>Changelog</h5>'.PHP_EOL;;
    if (extension_loaded('curl')) {              //Check if user has Curl installed
      $ch = curl_init();                         //Initiate curl
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
      curl_setopt($ch, CURLOPT_URL,'https://raw.githubusercontent.com/quidsup/notrack/master/changelog.txt');
      $Data = curl_exec($ch);                    //Download Changelog
      curl_close($ch);                           //Close curl
      echo '<pre>'.PHP_EOL;
      echo $Data;
      echo '</pre></div>'.PHP_EOL;;
    }
    echo '<div class="centered">'.PHP_EOL;
    echo '<button class="button-grey" type="reset"   onclick="window.location=\'?u=1\'">Upgrade</button>'.PHP_EOL;
    echo '</div>'.PHP_EOL;
  }  
}
?> 
</div>
</body>
</html>

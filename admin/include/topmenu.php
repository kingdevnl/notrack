<?php
function ActionTopMenu() {
  global $Config, $Mem;
  //Function to Action GET requests from Top Menu
  //Return value false when no action carried out
  //1. Is _GET['a'] (action) set?
  //2a. Delete config out of Memcached, since its about to be changed by ntrk-pause
  //2b. Execute appropriate action
  //2c. In the case of Restart or Shutdown we want to delay execution of the command for a couple of seconds to finish off any disk writes
  //2d. For any other value of 'a' leave this function and carry on with previous page
  //3. Sleep for 5 seconds to prevent a Race Condition occuring where new config could be loaded before ntrk-pause has been able to modify /etc/notrack/notrack.conf
  //   5 seconds is too much for an x86 based server, but for a Raspberry Pi 1 its just enough.
  
    
  if (isset($_GET['a'])) {
    $Mem->delete('Config');                      //Force reload of config    
    switch ($_GET['a']) {      
      case 'pause5':      
        ExecAction('pause5', true, true);
        break;
      case 'pause15':
        ExecAction('pause15', true, true);
        break;
      case 'pause30':
        ExecAction('pause30', true, true);
        break;
      case 'pause60':
        ExecAction('pause60', true, true);
        break;    
      case 'start':
        if ($Config['Status'] != 'Enabled') ExecAction('start', true, true);
        else return false;
        break;
      case 'stop':
        ExecAction('stop', true, true);
        break;
      case 'force-notrack':
        ExecAction('force-notrack', true, true);
        break;
      case 'restart':
        sleep(2);
        ExecAction('restart', true, true);
        exit(0);
        break;
      case 'shutdown':
        sleep(2);
        ExecAction('shutdown', true, true);
        exit(0);
        break;
      default:
        return false;
    }    
    sleep(5);    
  }
  return false;
}
//-------------------------------------------------------------------
function DrawTopMenu() {
  global $Config, $Mem;
      
  if ($_SERVER['PHP_SELF'] == '/admin/index.php') { //Display logo on index.php only
    echo '<nav><div id="menu-top"><div id="menu-logo">'.PHP_EOL;
    echo '<a href="../admin/"><img src="./svg/ntrklogo.svg" alt=""></a></div>'.PHP_EOL;
  
    echo '<div id="menu-top-right">'.PHP_EOL;
    echo '<a href="https://github.com/quidsup/notrack"><img src="../admin/images/icon_github.png" alt="Github"></a>'.PHP_EOL;
    echo '<a href="https://www.google.com/+quidsup" title="Google+"><img src="../admin/images/icon_google.png" alt="G+"></a>'.PHP_EOL;
    echo '<a href="https://www.youtube.com/user/quidsup" title="YouTube"><img src="../admin/images/icon_youtube.png" alt="Y"></a>'.PHP_EOL;
    echo '<a href="https://www.twitter.com/quidsup" title="Twitter"><img src="../admin/images/icon_twitter.png" alt="T"></a>'.PHP_EOL;
    echo '</div></div>'.PHP_EOL;
    echo '<div id="main-menu-padded">'.PHP_EOL;;
  }
  else {
    echo '<nav><div id="main-menu">'.PHP_EOL;
  }
  
  echo '<a href="../admin"><span class="pictext"><img src="./svg/menu_home.svg" alt=""></span></a>'.PHP_EOL;
  echo '<a href="../admin/stats.php"><span class="pictext"><img src="./svg/menu_stats.svg" alt=""><span class="dtext">Stats</span></span></a>'.PHP_EOL;
  echo '<a href="../admin/dhcpleases.php"><span class="pictext"><img src="./svg/menu_dhcp.svg" alt=""><span class="dtext">DHCP</span></span></a>'.PHP_EOL;
  echo '<a href="../admin/config.php"><span class="pictext"><img src="./svg/menu_config.svg" alt=""><span class="dtext">Config</span></span></a>'.PHP_EOL;
  echo '<a href="../admin/help.php"><span class="pictext"><img src="./svg/menu_help.svg" alt=""><span class="dtext">Help</span></span></a>'.PHP_EOL;
  
  if ($Config['Password'] != '') {               //Only do Logout if there is a password
    echo '<a href="../admin/logout.php"><span class="pictext"><img src="./svg/menu_logout.svg" alt=""><span class="dtext">Logout</span></span></a>'.PHP_EOL;
  }
  echo '<a href="#" onclick="ShowOptions()"><span class="pictext rightpictext"><img src="./svg/menu_option.svg" alt=""><span class="dtext">Options</span></span></a>'.PHP_EOL;

  //If Status = Paused & Enable Time < Now then switch Status to Enabled
  if ((substr($Config['Status'], 0, 6) == 'Paused') && (floatval(substr($Config['Status'], 6))) < (time()+60)) {
    $Mem->delete('Config');
    LoadConfigFile();
  }

  echo '<div id="pause">'.PHP_EOL;
  if (substr($Config['Status'], 0, 6) == 'Paused') {
    echo '<span class="timer" title="Paused until">'.date('H:i', substr($Config['Status'], 6)).'</span>'.PHP_EOL;
    echo '<a href="#" onclick="PauseNoTrack(\'start\')"><span class="pbutton" title="Enable Blocking">&#9654;</span></a>'.PHP_EOL;
  }
  elseif ($Config['Status'] == 'Stop') {
    echo '<span class="timer" title="NoTrack Disabled">----</span>'.PHP_EOL;
    echo '<a href="#" onclick="PauseNoTrack(\'start\')"><span class="pbutton" title="Enable Blocking">&#9654;</span></a>'.PHP_EOL;
  }
  else {
    echo '<a href="#" onclick="PauseNoTrack(\'stop\')"><span class="pbutton" title="Disable Blocking">&#8545;</span></a>'.PHP_EOL;
  }
  echo '<div tabindex="1" id="dropbutton">&#x25BC;'.PHP_EOL;
  echo '<div id="pause-menu">'.PHP_EOL;
  echo '<a href="#" onclick="PauseNoTrack(\'pause\', 5)"><span>Pause for 5 minutes</span></a>'.PHP_EOL;
  echo '<a href="#" onclick="PauseNoTrack(\'pause\', 15)"><span>Pause for 15 minutes</span></a>'.PHP_EOL;
  echo '<a href="#" onclick="PauseNoTrack(\'pause\', 30)"><span>Pause for 30 minutes</span></a>'.PHP_EOL;
  echo '<a href="#" onclick="PauseNoTrack(\'pause\', 60)"><span>Pause for 1 Hour</span></a>'.PHP_EOL;
  echo '</div></div>'.PHP_EOL;
  echo '</div></div>'.PHP_EOL;
  echo '</nav>'.PHP_EOL;

  //Dialogs----------------------------------------------------------
  echo '<div id="centerpoint1"><div id="dialog">'.PHP_EOL;
  echo '<div class="dialog-bar">NoTrack</div>'.PHP_EOL;
  echo '<span id="dialogmsg">Doing something</span>'.PHP_EOL;
  echo '<div class="centered"><img src="./images/progress.gif" alt=""></div>'.PHP_EOL;
  echo '</div></div>'.PHP_EOL;

  echo '<div id="centerpoint2"><div id="options">'.PHP_EOL;
  echo '<div class="dialog-bar">Options</div>'.PHP_EOL;
  echo '<div class="centered">'.PHP_EOL;
  echo '<span><a href="#" onclick="PauseNoTrack(\'force-notrack\')" title="Force Download and Update Blocklist" class="button-grey button-options">Update Blocklist</a></span>'.PHP_EOL;
  echo '<span><a href="#" onclick="PauseNoTrack(\'restart\')" class="button-grey button-options">Restart System</a></span>'.PHP_EOL;
  echo '<span><a href="#" onclick="PauseNoTrack(\'shutdown\')" class="button-danger button-options">Shutdown System</a></span>'.PHP_EOL;
  echo '<div class="close-button"><a href="#" onclick="HideOptions()"><img src="./svg/button_close.svg" onmouseover="this.src=\'./svg/button_close_over.svg\'" onmouseout="this.src=\'./svg/button_close.svg\'" alt="Close"></a></div>'.PHP_EOL;
  echo '</div></div></div>'.PHP_EOL;

  echo '<div id="fade"></div>'.PHP_EOL;
}
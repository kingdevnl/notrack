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
  
  if (isset($_POST['operation'])) {
    switch ($_POST['operation']) {
      case 'force-notrack':
        ExecAction('force-notrack', true, true);
        sleep(5);
        header("Location: ?");
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
    }
  }
  
  //if (isset($_GET['a'])) {
  if (isset($_POST['pause-time'])) {  
    $Mem->delete('Config');                      //Force reload of config    
    switch ($_POST['pause-time']) {
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
      default:
        return false;
    }
    sleep(5);
    header("Location: ?");
  }
  return true;
}
//-------------------------------------------------------------------
function draw_sidemenu() {

  echo '<nav><div id="menu-side">'.PHP_EOL;  
  echo '<a href="../admin/"><span><img src="./svg/menu_dashboard.svg" alt="">Dashboard</span></a>'.PHP_EOL;
  echo '<a href="../admin/queries.php"><span><img src="./svg/menu_queries.svg" alt="">DNS Queries</span></a>'.PHP_EOL;
  echo '<a href="../admin/dhcpleases.php"><span><img src="./svg/menu_dhcp.svg" alt="">Network</span></a>'.PHP_EOL;
  echo '<a href="../admin/config.php"><span><img src="./svg/menu_config.svg" alt="">Config</span></a>'.PHP_EOL;
  echo '<a href="../admin/help.php"><span><img src="./svg/menu_help.svg" alt="">Help</span></a>'.PHP_EOL;  
  echo '</div></nav>'.PHP_EOL;
  echo PHP_EOL;
}
//-------------------------------------------------------------------
function draw_configmenu() {
  echo '<nav><div id="menu-side">'.PHP_EOL;
  echo '<a href="../admin/"><span><img src="./svg/menu_dashboard.svg" alt="">Dashboard</span></a>'.PHP_EOL;
  echo '<a href="../admin/config.php"><span>General</span></a>'.PHP_EOL;
  echo '<a href="../admin/config.php?v=blocks"><span>Block Lists</span></a>'.PHP_EOL;
  echo '<a href="../admin/config.php?v=black"><span>BlackList</span></a>'.PHP_EOL;
  echo '<a href="../admin/config.php?v=white"><span>WhiteList</span></a>'.PHP_EOL;
  echo '<a href="../admin/config.php?v=tld"><span>Domains</span></a>'.PHP_EOL;
  echo '<a href="../admin/config.php?v=sites"><span>Sites Blocked</span></a>'.PHP_EOL;
  echo '<a href="../admin/config.php?v=advanced"><span>Advanced</span></a>'.PHP_EOL;
  echo '<a href="../admin/config.php?v=status"><span>Back-end Status</span></a>'.PHP_EOL;
  echo '<a href="../admin/upgrade.php"><span>Upgrade</span></a>'.PHP_EOL;
  
  echo '</div></nav>'.PHP_EOL;
  echo PHP_EOL;
  
}
//-------------------------------------------------------------------
function draw_helpmenu() {
  echo '<nav><div id="menu-side">'.PHP_EOL;
  echo '<a href="../admin/"><span><img src="./svg/menu_dashboard.svg" alt="">Dashboard</span></a>'.PHP_EOL;
  echo '<a href="../admin/help.php"><span>Help</span></a>'.PHP_EOL;
  echo '<a href="../admin/help.php?p=security"><span>Security</span></a>'.PHP_EOL;
  echo '<a href="../admin/help.php?p=position" title="Where To Position NoTrack Device"><span>Positioning Device</span></a>'.PHP_EOL;
  echo '<a href="../admin/help.php?p=newblocklist" title="Programming in new block lists"><span>New block lists</span></a>'.PHP_EOL;
  
  echo '</div></nav>'.PHP_EOL;
  echo PHP_EOL;  
}
//-------------------------------------------------------------------
function draw_topmenu() {
  global $Config, $Mem;
  
  echo '<nav><div id="menu-top">'.PHP_EOL;
  echo '<span class="top-menu-item float-left pointer" onclick="openNav()">&#9776;</span>'.PHP_EOL;
  echo '<a href="./"><span class="logo"><b>No</b>Track</span></a>'.PHP_EOL;
  
  /*if ($_SERVER['PHP_SELF'] == '/admin/index.php') { //Display logo on index.php only
    echo '<nav><div id="menu-top"><div id="menu-logo">'.PHP_EOL;
    echo '<a href="../admin/"><img src="./svg/ntrklogo.svg" alt=""></a></div>'.PHP_EOL;
  */
    echo '<span class="float-left mobile-hide">'.PHP_EOL;
    echo '<a href="https://github.com/quidsup/notrack" target="_blank"><img src="../admin/images/icon_github.png" alt="Github" title="Github"></a>'.PHP_EOL;
    echo '<a href="https://quidsup.net/donate/?ref=ntrk" target="_blank"><img src="./svg/menu_don.svg" alt="Donate" title="Donate"></a>'.PHP_EOL;
    echo '<a href="https://www.google.com/+quidsup" target="_blank"><img src="../admin/images/icon_google.png" alt="Google+" title="Google+"></a>'.PHP_EOL;
    //echo '<a href="https://www.youtube.com/user/quidsup" target="_blank"><img src="../admin/images/icon_youtube.png" alt="YouTube" title="YouTube"></a>'.PHP_EOL;
    echo '<a href="https://www.twitter.com/quidsup" target="_blank"><img src="../admin/images/icon_twitter.png" alt="Twitter" title="Twitter"></a>'.PHP_EOL;
//     echo '</div></div>'.PHP_EOL;*
  echo '</span>';
  
  
  
  /*echo '<a href="../admin"><span class="top-menu-item"><img src="./svg/menu_home.svg" alt=""></span></a>'.PHP_EOL;
  echo '<a href="../admin/stats.php"><span class="top-menu-item"><img src="./svg/menu_stats.svg" alt=""><span class="dtext">Stats</span></span></a>'.PHP_EOL;
  echo '<a href="../admin/dhcpleases.php"><span class="top-menu-item"><img src="./svg/menu_dhcp.svg" alt=""><span class="dtext">DHCP</span></span></a>'.PHP_EOL;
  echo '<a href="../admin/config.php"><span class="top-menu-item"><img src="./svg/menu_config.svg" alt=""><span class="dtext">Config</span></span></a>'.PHP_EOL;
  echo '<a href="../admin/help.php"><span class="top-menu-item"><img src="./svg/menu_help.svg" alt=""><span class="dtext">Help</span></span></a>'.PHP_EOL;*/
  
  if ($Config['Password'] != '') {               //Only do Logout if there is a password
    echo '<a href="../admin/logout.php"><span class="top-menu-item"><img src="./svg/menu_logout.svg" alt="">Logout</span></a>'.PHP_EOL;
  }
  echo '<span class="top-menu-item float-right pointer" onclick="ShowOptions()"><img src="./svg/menu_option.svg" alt="">Options</span>'.PHP_EOL;

  //If Status = Paused & Enable Time < Now then switch Status to Enabled
  if ((substr($Config['Status'], 0, 6) == 'Paused') && (floatval(substr($Config['Status'], 6))) < (time()+60)) {
    $Mem->delete('Config');
    LoadConfigFile();
  }

  echo '<div id="pause">'.PHP_EOL;
  echo '<form id="pause-form" action="?" method="post">'.PHP_EOL;
  echo '<input type="hidden" name="pause-time" id="pause-time" value="">'.PHP_EOL;
  if (substr($Config['Status'], 0, 6) == 'Paused') {
    echo '<span class="timer" title="Paused until">'.date('H:i', substr($Config['Status'], 6)).'</span>'.PHP_EOL;
    echo '<span class="pbutton pointer" title="Enable Blocking" onclick="PauseNoTrack(\'start\')">&#9654;</span>'.PHP_EOL;
  }
  elseif ($Config['Status'] == 'Stop') {
    echo '<span class="timer" title="NoTrack Disabled">----</span>'.PHP_EOL;
    echo '<span class="pbutton pointer" title="Enable Blocking" onclick="PauseNoTrack(\'start\')">&#9654;</span>'.PHP_EOL;
  }
  else {
    echo '<span class="pbutton pointer" title="Disable Blocking" onclick="PauseNoTrack(\'stop\')">&#8545;</span>'.PHP_EOL;
  }
  echo '<div tabindex="1" id="dropbutton" title="Pause for..."><span class="pointer">&#x25BC;</span>'.PHP_EOL;
  echo '<div id="pause-menu">'.PHP_EOL;  
  echo '<span class="pointer" onclick="PauseNoTrack(\'pause\', 5)">Pause for 5 minutes</span>'.PHP_EOL;
  echo '<span class="pointer" onclick="PauseNoTrack(\'pause\', 15)">Pause for 15 minutes</span>'.PHP_EOL;
  echo '<span class="pointer" onclick="PauseNoTrack(\'pause\', 30)">Pause for 30 minutes</span>'.PHP_EOL;
  echo '<span class="pointer" onclick="PauseNoTrack(\'pause\', 60)">Pause for 1 Hour</span>'.PHP_EOL;
  echo '</div></div>'.PHP_EOL;
  echo '</form></div></div>'.PHP_EOL;
  echo '</nav>'.PHP_EOL;

  //Dialogs----------------------------------------------------------
  echo '<div id="dialog-box">'.PHP_EOL;
  echo '<div class="dialog-bar">NoTrack</div>'.PHP_EOL;
  echo '<span id="dialogmsg">Doing something</span>'.PHP_EOL;
  echo '<div class="centered"><img src="./images/progress.gif" alt=""></div>'.PHP_EOL;
  echo '</div>'.PHP_EOL;

  //Operations
  echo '<div id="options-box">'.PHP_EOL;
  echo '<div class="dialog-bar">Options</div>'.PHP_EOL;
  echo '<div class="centered">'.PHP_EOL;
  
  echo '<form id="operation-form" action="?" method="post">'.PHP_EOL;
  echo '<input type="hidden" name="operation" id="operation" value="">'.PHP_EOL;
  echo '<span><a href="#" onclick="PauseNoTrack(\'force-notrack\')" title="Force Download and Update Blocklist" class="button-grey button-options">Update Blocklist</a></span>'.PHP_EOL;
  echo '<span><a href="#" onclick="PauseNoTrack(\'restart\')" class="button-grey button-options">Restart System</a></span>'.PHP_EOL;
  echo '<span><a href="#" onclick="PauseNoTrack(\'shutdown\')" class="button-danger button-options">Shutdown System</a></span>'.PHP_EOL;
  echo '</form>'.PHP_EOL;
  
  echo '<div class="close-button"><img src="./svg/button_close.svg" onmouseover="this.src=\'./svg/button_close_over.svg\'" onmouseout="this.src=\'./svg/button_close.svg\'" alt="Close" onclick="HideOptions()"></div>'.PHP_EOL;
  echo '</div></div>'.PHP_EOL;

  echo '<div id="fade"></div>'.PHP_EOL;
}

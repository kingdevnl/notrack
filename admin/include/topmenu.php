<?php
if (isset($_GET['a'])) {
  $Mem->delete('Config');
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
      ExecAction('start', true, true);
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
      break;
    case 'shutdown':
      sleep(2);
      ExecAction('shutdown', true, true);
      break;
  }
  sleep(5);
}

LoadConfigFile();

if ($_SERVER['PHP_SELF'] == '/admin/index.php') {
  echo '<div id="menu-top"><div id="menu-logo">'."\n";
  echo '<a href="../admin/"><img src="./svg/ntrklogo2.svg" alt=""></a></div>'."\n";
  echo '<div id="menu-top-right">'."\n";
  echo '<a href="https://github.com/quidsup/notrack"><img src="../admin/images/icon_github.png" alt="Github"></a>'."\n";
  echo '<a href="https://www.google.com/+quidsup" title="Google+"><img src="../admin/images/icon_google.png" alt="G+"></a>'."\n";
  echo '<a href="https://www.youtube.com/user/quidsup" title="YouTube"><img src="../admin/images/icon_youtube.png" alt="Y"></a>'."\n";
  echo '<a href="https://www.twitter.com/quidsup" title="Twitter"><img src="../admin/images/icon_twitter.png" alt="T"></a>'."\n";
  echo "</div></div>\n";
}
?>
<nav><div id="main-menu">
  <a href="../admin"><span class="pictext"><img src="./svg/menu_home.svg" alt=""></span></a>
  <a href="../admin/stats.php"><span class="pictext"><img src="./svg/menu_stats.svg" alt=""><span class="dtext">Domain Stats</span></span></a>
  <a href="../admin/dhcpleases.php"><span class="pictext"><img src="./svg/menu_dhcp.svg" alt=""><span class="dtext">DHCP</span></span></a>
  <a href="../admin/config.php"><span class="pictext"><img src="./svg/menu_config.svg" alt=""><span class="dtext">Config</span></span></a>
  <a href="../admin/system.php"><span class="pictext"><img src="./svg/menu_info.svg" alt=""><span class="dtext">System Info</span></span></a>
  <a href="#" onclick="ShowOptions()"><span class="pictext rightpictext"><img src="./svg/menu_option.svg" alt=""><span class="dtext">Options</span></span></a>

<?php
//1. If Status = Paused & Enable Time < Now then switch Status to Enabled

if ((substr($Config['Status'], 0, 6) == 'Paused') && (floatval(substr($Config['Status'], 6))) < (time()+60)) {
  $Mem->delete('Config');
  LoadConfigFile();
}

echo '<div id="pause">'."\n";
if (substr($Config['Status'], 0, 6) == 'Paused') {
  echo '<span class="timer" title="Paused until">'.date('H:i', substr($Config['Status'], 6)).'</span>'."\n";
  echo '<a href="#" onclick="PauseNoTrack(\'start\')"><span class="pbutton" title="Enable Blocking">&#9654;</span></a>'."\n";
}
elseif ($Config['Status'] == 'Stop') {
  echo '<span class="timer" title="NoTrack Disabled">----</span>'."\n";
  echo '<a href="#" onclick="PauseNoTrack(\'start\')"><span class="pbutton" title="Enable Blocking">&#9654;</span></a>'."\n";
}
else {
  echo '<a href="#" onclick="PauseNoTrack(\'stop\')"><span class="pbutton" title="Disable Blocking">&#8545;</span></a>'."\n";
}
echo '<div tabindex="1" id="dropbutton">&#x25BC;'."\n";
echo '<div id="pause-menu">'."\n";
echo '<a href="#" onclick="PauseNoTrack(\'pause\', 5)"><span>Pause for 5 minutes</span></a>'."\n";
echo '<a href="#" onclick="PauseNoTrack(\'pause\', 15)"><span>Pause for 15 minutes</span></a>'."\n";
echo '<a href="#" onclick="PauseNoTrack(\'pause\', 30)"><span>Pause for 30 minutes</span></a>'."\n";
echo '<a href="#" onclick="PauseNoTrack(\'pause\', 60)"><span>Pause for 1 Hour</span></a>'."\n";
echo "</div></div>\n";
echo "</div></div></nav>\n";
?>
<div id="centerpoint1"><div id="dialog">
<div class="dialog-bar">NoTrack</div>
<span id="dialogmsg">Doing something</span>
<div class="centered"><img src="./images/progress.gif"></div>
</div></div>

<div id="centerpoint2"><div id="options">
<div class="dialog-bar">Options</div>
<div id="close-button"><a href="#" onclick="HideOptions()"><img src="./svg/button_close.svg" onmouseover="this.src='./svg/button_close_over.svg'" onmouseout="this.src='./svg/button_close.svg'"></a></div>
<br />
<span><a href="#" onclick="PauseNoTrack('force-notrack')" class="button-grey">Update Blocklist</a></span><br />
<span><a href="#" onclick="PauseNoTrack('restart')" class="button-grey">Restart</a></span>
<span><a href="#" onclick="PauseNoTrack('shutdown')" class="button-danger">Shutdown</a></span><br />
</div></div>

<div id="fade"></div>
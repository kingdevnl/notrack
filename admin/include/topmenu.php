<?php
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
  <a href="../admin"><span><img src="./svg/menu_home.svg" alt=""></span></a>
  <a href="../admin/stats.php"><span><img src="./svg/menu_stats.svg" alt=""><span class="dtext">Domain Stats</span></span></a>
  <a href="../admin/dhcpleases.php"><span><img src="./svg/menu_dhcp.svg" alt=""><span class="dtext">DHCP Leases</span></span></a>
  <a href="../admin/config.php"><span><img src="./svg/menu_config.svg" alt=""><span class="dtext">Config</span></span></a>
  <a href="../admin/system.php"><span><img src="./svg/menu_info.svg" alt=""><span class="dtext">System Info</span></span></a>
  <a href="javascript:void(0)" onclick="document.getElementById('options-box').style.display='block';document.getElementById('fade').style.display='block'"><span class="float-right"><img src="./svg/menu_option.svg" alt=""><span class="dtext">Options</span></span></a>    
</div></nav>

<div id="options-box">
<h2>Options</h2>
<div id="close-button"><a href="javascript:void(0)" onclick="document.getElementById('options-box').style.display='none';document.getElementById('fade').style.display='none'">Close</a></div>

</div>
<div id="fade"></div>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <link href="./css/master.css" rel="stylesheet" type="text/css" />
  <link rel="icon" type="image/png" href="./favicon.png" />
  <script src="./include/menu.js"></script>
  <title>NoTrack - System Information</title>  
</head>

<body>
<div id="main">
<?php
require('./include/global-vars.php');
require('./include/global-functions.php');
include('./include/topmenu.php');

echo "<h1>System Information</h1>\n";

//DrawSysTable is in Global Functions. It echos out sys-group div, sys-title div, and sys-items div
//DrawSysRow is in Global Functions. It echos table row with contents

$Load = sys_getloadavg();
$FreeMem = preg_split('/\s+/', exec('free -m | grep Mem'));

$PS_Dnsmasq = preg_split('/\s+/', exec('ps -eo fname,pid,stime,cputime,pmem | grep dnsmasq'));

$PS_Lighttpd = preg_split('/\s+/', exec('ps -eo fname,pid,stime,cputime,pmem | grep lighttpd'));

DrawSysTable('Server');
DrawSysRow('Name', gethostname());
DrawSysRow('IP Address', $_SERVER['SERVER_ADDR']);
DrawSysRow('Sysload', $Load[0].' | '.$Load[1].' | '.$Load[2]);
DrawSysRow('Memory Used', $FreeMem[2].' MB');
DrawSysRow('Free Memory', $FreeMem[3].' MB');
DrawSysRow('Uptime', exec('uptime -p | cut -d \  -f 2-'));
DrawSysRow('NoTrack Version', $Version);
echo "</table></div></div>\n";

DrawSysTable('Dnsmasq');
if ($PS_Dnsmasq[0] != null) DrawSysRow('Status','Dnsmasq is running');
else DrawSysRow('Status','Inactive');
DrawSysRow('Pid', $PS_Dnsmasq[1]);
DrawSysRow('Started On', $PS_Dnsmasq[2]);
DrawSysRow('Cpu', $PS_Dnsmasq[3]);
DrawSysRow('Memory Used', $PS_Dnsmasq[4].' MB');
echo "</table></div></div>\n";

DrawSysTable('Lighttpd');
if ($PS_Lighttpd[0] != null) DrawSysRow('Status','Lighttpd is running');
else DrawSysRow('Status','Inactive');
DrawSysRow('Pid', $PS_Lighttpd[1]);
DrawSysRow('Started On', $PS_Lighttpd[2]);
DrawSysRow('Cpu', $PS_Lighttpd[3]);
DrawSysRow('Memory Used', $PS_Lighttpd[4].' MB');
echo "</table></div></div>\n";

?> 
</div>
</body>
</html>
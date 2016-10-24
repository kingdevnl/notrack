<?php

DEFINE('VERSION', '0.8');
DEFINE('SERVERNAME', 'localhost');
DEFINE('USERNAME', 'ntrk');
DEFINE('PASSWORD', 'ntrkpass');
DEFINE('DBNAME', 'ntrkdb');

DEFINE('ROWSPERPAGE', 200);

$DomainQuickList = '/etc/notrack/domain-quick.list';
$FileTmpAction = '/tmp/ntrk-exec.txt';
$FileTmpConfig = '/tmp/notrack.conf';
$FileConfig = '/etc/notrack/notrack.conf';

$FileBlackList = '/etc/notrack/blacklist.txt';
$FileWhiteList = '/etc/notrack/whitelist.txt';
$FileTLDBlackList = '/etc/notrack/domain-blacklist.txt';
$FileTLDWhiteList = '/etc/notrack/domain-whitelist.txt';
$FileAccessLog = '/var/log/ntrk-admin.log';
$CSVBlocking = '/etc/notrack/blocking.csv';
$CSVTld = './include/tld.csv';
$LogLightyAccess = '/var/log/lighttpd/access.log';

DEFINE('NTRK_EXEC', 'sudo /usr/local/sbin/ntrk-exec ');
DEFINE('DIR_TMP', '/tmp/');
DEFINE('BL_NOTRACK', '/etc/dnsmasq.d/notrack.list');

$DirEtc = '/etc/notrack/';
$DirOldLogs = '/var/log/notrack';

$Config=array();

$DEFAULTCONFIG = array(
  'NetDev' => 'eth0',
  'IPVersion' => 'IPv4',
  'Status' => 'Enabled',
  'BlockMessage' => 'pixel',
  'Search' => 'DuckDuckGo',
  'SearchUrl' => '',
  'WhoIs' => 'Who.is',
  'WhoIsUrl' => '',
  'Username' => '',
  'Password' => '',
  'Delay' => 30,
  'Suppress' => '',
  'bl_custom' => '',
  'bl_notrack' => 1,
  'bl_tld' => 1,
  'bl_qmalware' => 1,
  'bl_hexxium' => 1,
  'bl_cedia' => 0,
  'bl_disconnectmalvertising' => 0,
  'bl_easylist' => 0,
  'bl_easyprivacy' => 0,
  'bl_fbannoyance' => 0,
  'bl_fbenhanced' => 0,
  'bl_fbsocial' => 0,
  'bl_hphosts' => 0,
  'bl_malwaredomainlist' => 0,
  'bl_malwaredomains' => 0,
  'bl_pglyoyo' => 0,  
  'bl_someonewhocares' => 0,
  'bl_spam404' => 0,
  'bl_swissransom' => 0,
  'bl_swisszeus' => 0,
  'bl_winhelp2002' => 0,
  //Region Specific BlockLists
  'bl_areasy' => 0,
  'bl_chneasy' => 0,
  'bl_deueasy' => 0,
  'bl_dnkeasy' => 0,
  'bl_ruseasy' => 0,
  'bl_fblatin' => 0,
  'LatestVersion' => VERSION  
);

if (!extension_loaded('memcache')) {
  die('NoTrack requires memcached and php-memcache to be installed');
}

$mem = new Memcache;                             //Initiate Memcache
$mem->connect('localhost');

if (!extension_loaded('mysqli')) {
  echo '<p>NoTrack requires mysql to be installed<br />Run: <code>bash install.sh -sql</code></p>';
  die;
}
?>

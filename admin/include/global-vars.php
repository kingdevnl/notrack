<?php
$Version='0.7.17';

$DomainQuickList = '/etc/notrack/domain-quick.list';
$FileTmpAction = '/tmp/ntrk-exec.txt';
$FileTmpConfig = '/tmp/notrack.conf';
$FileConfig = '/etc/notrack/notrack.conf';
$FileBlockList = '/etc/dnsmasq.d/notrack.list';
$FileBlackList = '/etc/notrack/blacklist.txt';
$FileWhiteList = '/etc/notrack/whitelist.txt';
$FileTLDBlackList = '/etc/notrack/domain-blacklist.txt';
$FileTLDWhiteList = '/etc/notrack/domain-whitelist.txt';
$FileAccessLog = '/var/log/ntrk-admin.log';
$CSVBlocking = '/etc/notrack/blocking.csv';
$CSVTld = './include/tld.csv';
$LogLightyAccess = '/var/log/lighttpd/access.log';

$DirTmp = '/tmp/';
$DirEtc = '/etc/notrack/';
$DirOldLogs = '/var/log/notrack';

$Config=array();

$DefaultConfig = array(
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
  'LatestVersion' => $Version  
);

if (!extension_loaded('memcache')) die('NoTrack requires memcached and php-memcache to be installed');

$Mem = new Memcache;                             //Initiate Memcache
$Mem->connect('localhost');
?>

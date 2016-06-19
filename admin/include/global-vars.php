<?php
$Version='0.7.13';

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
  'BlockList_NoTrack' => 1,
  'BlockList_TLD' => 1,
  'BlockList_QMalware' => 1,
  'BlockList_AdBlockManager' => 0,
  'BlockList_DisconnectMalvertising' => 0,
  'BlockList_EasyList' => 0,
  'BlockList_EasyPrivacy' => 0,
  'BlockList_FBAnnoyance' => 0,
  'BlockList_FBEnhanced' => 0,
  'BlockList_FBEnhanced' => 0,
  'BlockList_hpHosts' => 0,
  'BlockList_MalwareDomainList' => 0,
  'BlockList_MalwareDomains' => 0,
  'BlockList_PglYoyo' => 0,
  'BlockList_SomeoneWhoCares' => 0,
  'BlockList_Spam404' => 0,
  'BlockList_Winhelp2002' => 0,
  //Region Specific BlockLists
  'BlockList_CHNEasy' => 0,
  'BlockList_RUSEasy' => 0,
  'LatestVersion' => $Version
);

if (!extension_loaded('memcache')) die('NoTrack requires memcached and php-memcache to be installed');

$Mem = new Memcache;                             //Initiate Memcache
$Mem->connect('localhost');
?>

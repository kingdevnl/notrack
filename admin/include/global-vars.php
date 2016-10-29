<?php

DEFINE('VERSION', '0.8');
DEFINE('SERVERNAME', 'localhost');
DEFINE('USERNAME', 'ntrk');
DEFINE('PASSWORD', 'ntrkpass');
DEFINE('DBNAME', 'ntrkdb');

DEFINE('ROWSPERPAGE', 200);

$DomainQuickList = '/etc/notrack/domain-quick.list';
$FileTmpAction = '/tmp/ntrk-exec.txt';


$FileBlackList = '/etc/notrack/blacklist.txt';
$FileWhiteList = '/etc/notrack/whitelist.txt';
$FileTLDBlackList = '/etc/notrack/domain-blacklist.txt';
$FileTLDWhiteList = '/etc/notrack/domain-whitelist.txt';
$CSVBlocking = '/etc/notrack/blocking.csv';
$CSVTld = './include/tld.csv';
$LogLightyAccess = '/var/log/lighttpd/access.log';

DEFINE('ACCESSLOG', '/var/log/ntrk-admin.log');
DEFINE('CONFIGFILE', '/etc/notrack/notrack.conf');
DEFINE('CONFIGTEMP', '/tmp/notrack.conf');
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
  'bl_cedia_immortal' => 1,
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

$SEARCHENGINELIST = array(
  'Baidu' => 'https://www.baidu.com/s?wd=',
  'Bing' => 'https://www.bing.com/search?q=',
  'DuckDuckGo' => 'https://duckduckgo.com/?q=',
  'Exalead' => 'https://www.exalead.com/search/web/results/?q=',
  'Gigablast' => 'https://www.gigablast.com/search?q=',
  'Google' => 'https://www.google.com/search?q=',
  'Ixquick' => 'https://ixquick.eu/do/search?q=',
  'Qwant' => 'https://www.qwant.com/?q=',
  'StartPage' => 'https://startpage.com/do/search?q=',
  'Yahoo' => 'https://search.yahoo.com/search?p=',
  'Yandex' => 'https://www.yandex.com/search/?text='
);

$WHOISLIST = array(
  'DomainTools' => 'http://whois.domaintools.com/',
  'Icann' => 'https://whois.icann.org/lookup?name=',
  'Who.is' => 'https://who.is/whois/'
);


if (!extension_loaded('memcache')) {
  die('NoTrack requires memcached and php-memcache to be installed');
}

$mem = new Memcache;                             //Initiate Memcache
$mem->connect('localhost');

if (!extension_loaded('mysqli')) {
  echo '<p>NoTrack requires mysql to be installed<br />Run: <code>bash /opt/notrack/install.sh -sql</code> or <code>bash ~/notrack/install.sh -sql</code> (depending where NoTrack folder is located)</p>';
  die;
}
?>

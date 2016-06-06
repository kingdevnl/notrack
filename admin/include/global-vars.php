<?php
$Version='0.7.11';

$DomainQuickList = '/etc/notrack/domain-quick.list';
$FileBlockingCSV = '/etc/notrack/blocking.csv';
$FileTmpAction = '/tmp/ntrk-exec.txt';
$FileTmpConfig = '/tmp/notrack.conf';
$FileConfig = '/etc/notrack/notrack.conf';
$FileBlackList = '/etc/notrack/blacklist.txt';
$FileWhiteList = '/etc/notrack/whitelist.txt';
$FileTLDBlackList = '/etc/notrack/domain-blacklist.txt';
$FileTLDWhiteList = '/etc/notrack/domain-whitelist.txt';
$FileAccessLog = '/var/log/ntrk-admin.log';
$LogLightyAccess = '/var/log/lighttpd/access.log';

$DirTmp = '/tmp/';
$DirEtc = '/etc/notrack/';
$DirOldLogs = '/var/log/notrack';

$Config=array();

if (!extension_loaded('memcache')) die('NoTrack requires memcached and php-memcache to be installed');

$Mem = new Memcache;                             //Initiate Memcache
$Mem->connect('localhost');
?>

<?php
$Version='0.7.6';

$DomainQuickList = '/etc/notrack/domain-quick.list';
$FileBlockingCSV = '/etc/notrack/blocking.csv';
$FileTmpAction = '/tmp/ntrk-exec.txt';
$FileTmpConfig = '/tmp/notrack.conf';
$FileTmpBlackList = '/tmp/blacklist.txt';
$FileTmpWhiteList = '/tmp/whitelist.txt';
$FileConfig = '/etc/notrack/notrack.conf';
$FileBlackList = '/etc/notrack/blacklist.txt';
$FileWhiteList = '/etc/notrack/whitelist.txt';

$Config=array();

if (!extension_loaded('memcache')) die('NoTrack requires memcached and php-memcache to be installed');

$Mem = new Memcache;                             //Initiate Memcache
$Mem->connect('localhost');
?>

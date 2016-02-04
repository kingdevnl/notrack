<?php
$Version='0.6';

$FileTmpAction='/tmp/ntrk-exec.txt';
$FileTmpConfig='/tmp/notrack.conf';
$FileConfig='/etc/notrack/notrack.conf';

$Config=array();

if (!extension_loaded('memcache')) die('NoTrack requires memcached and php-memcache to be installed');

$Mem = new Memcache;                             //Initiate Memcache
$Mem->connect('localhost');
?>

<?php
//Title : Download List
//Description : Takes a file from parameter ?v= and forces the browser to download the echoed out contents.
//Author : QuidsUp
//Date : 2015-03-25
//Usage : This page is called from a link generated in config.php > DisplayCustomList
//Further notes: Strict controls are needed on echo file_get_contents to prevent user from downloading system or config files.

require('./global-vars.php');

header('Content-type: text/plain');

if (isset($_GET['v'])) {
  switch($_GET['v']) {
    case 'black':
      $FileName = $FileBlackList;
      $DefFile = 'blacklist.txt';
      break;
    case 'white':
      $FileName = $FileWhiteList;
      $DefFile = 'whitelist.txt';
      break;
    case 'tldblack':
      $FileName = $FileTLDBlackList;
      $DefFile = 'domain-blacklist.txt';
      break;
    case 'tldwhite':
      $FileName = $FileTLDWhiteList;
      $DefFile = 'domain-whitelist.txt';
      break;
    default:
      echo 'Error: No valid file selected';
      die();
  }
}
else {
  echo 'Error: No file selected';
  die();
}

header('Content-Disposition: attachment; filename="'.$DefFile.'"');
echo file_get_contents($FileName);

?>

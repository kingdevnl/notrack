<?php
require('./include/global-vars.php');
require('./include/global-functions.php');
require('./include/topmenu.php');

LoadConfigFile();
if ($Config['Password'] != '') {  
  session_start();  
  if (! Check_SessionID()) {
    header("Location: ./login.php");
    exit;
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <link href="./css/master.css" rel="stylesheet" type="text/css" />
  <link rel="icon" type="image/png" href="./favicon.png" />
  <script src="./include/menu.js"></script>
  <title>NoTrack Help</title>
</head>

<body>
<div id="main">
<?php

function LoadHelpPage($Page) {  
  if (file_exists('./help/'.$Page.'.html')) {
    echo file_get_contents('./help/'.$Page.'.html');
  }
  else {
    echo 'Error: File not found'.PHP_EOL;
  }
}

ActionTopMenu();
DrawTopMenu();
echo '<h1>NoTrack Help</h1>'.PHP_EOL;

if (isset($_GET['p'])) {
  switch($_GET['p']) {
    case 'security':
      LoadHelpPage('security');
      break;
    default:
      LoadHelpPage('list');
  }
}
else {
  LoadHelpPage('list');
}
?>
</div>
</body>
</html>

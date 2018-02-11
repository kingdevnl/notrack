<?php
require('./global-vars.php');
require('./global-functions.php');

header('Content-Type: application/json; charset=UTF-8');

$response = array();

load_config();
//TODO add security check

if (isset($_POST['operation'])) {
  switch ($_POST['operation']) {
      case 'force-notrack':
        exec(NTRK_EXEC.'--force');
        sleep(3);                                //Prevent race condition
        header("Location: ?");
        break;
      case 'incognito':
        if ($Config['status'] & STATUS_INCOGNITO) $Config['status'] -= STATUS_INCOGNITO;
        else $Config['status'] += STATUS_INCOGNITO;
        $response['status'] = $Config['status'];
        save_config();
        break;
  }
}

echo json_encode($response);
?>

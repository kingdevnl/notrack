<?php
require('./include/global-vars.php');
require('./include/global-functions.php');
require('./include/menu.php');

load_config();
ensure_active_session();

//-------------------------------------------------------------------
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <link href="./css/master.css" rel="stylesheet" type="text/css" />
  <link rel="icon" type="image/png" href="./favicon.png" />
  <script src="./include/config.js"></script>
  <script src="./include/menu.js"></script>
  <title>NoTrack - Security</title>  
</head>

<body>
<?php
action_topmenu();
draw_topmenu();
draw_configmenu();
echo '<div id="main">';

/************************************************
*Constants                                      *
************************************************/
define ('DEF_DELAY', 30);

/********************************************************************
 *  Disable Password Protection
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function disable_password_protection() {
  global $Config;
  
  $Config['Username'] = '';
  $Config['Password'] = '';
  save_config();  
}
/********************************************************************
 *  Draw Password Input Form
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_password_input_form() {
  global $Config;
  
  echo '<form name="security" method="post">';
  echo '<table class="sys-table">'.PHP_EOL;
    
  draw_sysrow('NoTrack Username', '<input type="text" name="username" value="'.$Config['Username'].'"><p><i>Optional authentication username.</i></p>');
  draw_sysrow('NoTrack Password', '<input type="password" name="password"><p><i>Optional authentication password.</i></p>');
  draw_sysrow('Delay', '<input type="number" name="delay" min="10" max="2400" value="'.$Config['Delay'].'"><p><i>Delay in seconds between attempts.</i></p>');
  echo '<tr><td colspan="2"><div class="centered"><input type="submit" class="button-grey" value="Save Changes"></div></td></tr>';
  echo '</table></form>'.PHP_EOL;
}

/********************************************************************
 *  Update Password Config
 *
 *  Params:
 *    None
 *  Return:
 *    true on success, false on fail
 */
function update_password_config() {
  global $Config;
  
  $username = $_POST['username'];
  $password = $_POST['password'];
  
  if (preg_match('/[!\"£\$%\^&\*\(\)\[\]+=<>:\,\|\/\\\\]/', $username) != 0) return false;
  
  if (($username == '') && ($password == '')) {    
    $Config['Username'] = '';
    $Config['Password'] = '';
  }
  else {  
    $Config['Username'] = $username;
    if (function_exists('password_hash')) {
      $Config['Password'] = password_hash($password, PASSWORD_DEFAULT);
    }
    else {                                       //Fallback for older versions of PHP 
      $Config['Password'] = hash('sha256', $password);
    }
    
    if (isset($_POST['delay'])) {                //Set Delay
      $Config['Delay'] = filter_integer($_POST['delay'], 5, 2401, DEF_DELAY);
    }
    else {                                       //Fallback if Delay not posted
      $Config['Delay'] = DEF_DELAY;
    }
  }
  
  return true;
}


//-------------------------------------------------------------------
$show_password_input_form = false;
$show_button_on = true;
$message = '';

if (isset($_POST['enable_password'])) {
  $show_password_input_form = true;
  $show_button_on = false;
}
elseif (isset($_POST['disable_password'])) {
  disable_password_protection();
  $show_password_input_form = false;
  $message = 'Password Protection Removed';
  if (session_status() == PHP_SESSION_ACTIVE) session_destroy();
}
elseif ((isset($_POST['username']) && (isset($_POST['password'])))) {
  if (update_password_config()) {
    save_config();
    if (session_status() == PHP_SESSION_ACTIVE) session_destroy();
    $message = 'Password Protection Enabled';
    $show_button_on = false;
  }
  else {
    $message = 'Invalid Username';
  }
}

echo '<div class="sys-group"><div class="sys-title">'.PHP_EOL;
echo '<h5>Security&nbsp;<a href="./help.php?p=security"><img class="btn" src="./svg/button_help.svg" alt="help"></a></h5></div>'.PHP_EOL;
echo '<div class="sys-items">'.PHP_EOL;

if (is_password_protection_enabled()) {
  echo '<form method="post"><input type="hidden" name="disable_password"><input type="submit" class="button-blue" value="Turn off password protection"></form></br>'.PHP_EOL;
  $show_password_input_form = false;
  $show_button_on = false;
}

if ($show_button_on) {
  echo '<form method="post"><input type="hidden" name="enable_password"><input type="submit" class="button-blue" value="Turn on password protection"></form>'.PHP_EOL;
}

if ($show_password_input_form) { 
  draw_password_input_form();
}

      
if ($message != '') {
  echo '<br />'.PHP_EOL;
  echo '<h3>'.$message.'</h3>'.PHP_EOL;
}
  
echo '</div></div>'.PHP_EOL;
echo '</div>'.PHP_EOL;
?>
</body>
</html>

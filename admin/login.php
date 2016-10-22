<?php
//Title : Login
//Description : Controls loin for NoTrack, validating username and password, and throttling password attemtps
//Author : QuidsUp
//Date : 2015-03-25
//Password attempts are throttled by use of Memcache, a variable is placed in there with the duration set by $Config['Delay']. If this variable is present then no checking will take place until the variable is cleared.

//1. Start Session
//2. Check if session is already active then return to index.php
//3. Check if password is required
//3a. If not return to index.php (Otherwise you get trapped on this page)
//4. Has a password been sent with HTTP POST?
//4a. Check if delay is imposed on Memcache variable 'Delay'
//4ai. If yes then set $message to wait and don't evaluate logon attempt, jump to 5.
//4b. Username is optional, check if it has been set in HTTP POST, otherwise set it to blank
//4c. Create access log file if it doesn't exist
//4d. Use PHP password_verify function to check hashed version of user input with hash in $Config['Password']
//4ei. If username and password match set SESSION['sid'] to 1 (Future version may use a random number, to make it even harder to hijack a session)
//4eii. On failure write Delay into Memcache and show message of Incorrect Username or Password
//      Add entry into ntrk-access.log to allow functionality with Fail2ban
//      (Deny attacker knowledge of whether Username OR Password is wrong)

//5. Draw basic top menu
//6. Draw form login
//7. Draw box with $message (If its set)
//8. Draw hidden box informing user that Cookies must be enabled
//9. Use Javascript to check if Cookies have been enabled
//9a. If Cookies are disabled then set 8. to Visible

require('./include/global-vars.php');
require('./include/global-functions.php');
load_config();

$message = '';

if (! is_password_protection_enabled()) {
  header('Location ./index.php');
  exit;
}

session_start();
if (is_active_session()) {
  header('Location: ./index.php');
  exit;  
}

if (isset($_POST['password'])) {
                     
  if ($mem->get('delay')) {                      //Load Delay from Memcache
    $message = 'Wait';                           //If it is set then Wait
  }
  else {                                         //No Delay, check Password
    $password = $_POST['password'];
    if (isset($_POST['username'])) $username = $_POST['username'];
    else $username = '';
    
    if (!file_exists($FileAccessLog)) {          //Create ntrk-access.log file
      ExecAction('create-accesslog', true, false);
    }
    
    if (function_exists('password_hash')) {
    //Use built in password_verify function to compare with $Config['Password'] hash
      if (($username == $Config['Username']) && (password_verify($password, $Config['Password']))) {
        activate_session();                      //Set session to enabled
        header('Location: ./index.php');         //Redirect to index.php
      }
    }
    else { 
      if (($username == $Config['Username']) && (hash('sha256', $password) == $Config['Password'])) {
        activate_session();                      //Set session to enabled
        header('Location: ./index.php');         //Redirect to index.php
      }
    }
    
    //At this point the Password is Wrong
    $mem->set('delay', $Config['Delay'], 0, $Config['Delay']);
    $message = "Incorrect username or password";   //Deny attacker knowledge of whether username OR password is wrong
      
    error_log(date('d/m/Y H:i:s').': Authentication failure for '.$username.' from '.$_SERVER['REMOTE_ADDR'].' port '.$_SERVER['REMOTE_PORT'].PHP_EOL, 3, $FileAccessLog);    
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <link href="./css/login.css" rel="stylesheet" type="text/css" />
  <link rel="icon" type="image/png" href="./favicon.png" />
  <title>NoTrack Login</title>
</head>

<body>

<div class="login-box">
<form method="post" name="Login_Form">
<span>Username:</span>
<div class="centered"><input name="username" type="text"></div>
<span>Password:</span>
<div class="centered"><input name="password" type="password"></div>
<div class="centered"><input type="submit" value="Login"></div>
</form>
</div>

<?php
if ($message != '') {                            //Any Message to show?
  echo '<div class="login-box">'.PHP_EOL;
  echo '<h4>'.$message.'</h4>'.PHP_EOL;
  echo '</div>'.PHP_EOL;
}

echo '<div id="fade"></div>'.PHP_EOL;            //I was too lazy to convert this to pure HTML
echo '<div id="centerpoint1"><div id="dialog">'.PHP_EOL;
echo '<div class="dialog-bar">NoTrack</div>'.PHP_EOL;
echo '<div class="close-button"><a href="#" onclick="HideOptions()"><img src="./svg/button_close.svg" onmouseover="this.src=\'./svg/button_close_over.svg\'" onmouseout="this.src=\'./svg/button_close.svg\'" alt="Close"></a></div>'.PHP_EOL;
echo '<h4 id="dialogmsg">Cookies need to be enabled</h4>'.PHP_EOL;
echo '</div></div>'.PHP_EOL;
?>
<script>
function HideOptions() {
  document.getElementById('centerpoint1').style.display = "none";
  document.getElementById('fade').style.display = "none";
}
if (! navigator.cookieEnabled) {                 //Use has disabled cookies for this site
  document.getElementById("centerpoint1").style.display = "block";
  document.getElementById("fade").style.display = "block";
}
</script>
</div>
</body>
</html>

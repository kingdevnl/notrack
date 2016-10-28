<?php

session_start();

unset($_SESSION["session_start"]);
unset($_SESSION["session_expired"]);

header("Location:lockscreen.php");
?>
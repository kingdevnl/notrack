<?php
require( dirname(__FILE__) . '/includes' . '/functions.php' );

session_start();

if (!is_password_protection_enabled()){
    header('Location:index.php');
}

if (count($_POST) > 0) {
    if (isset($_POST['password'])) {
        $hash = hash('sha512', $_POST['password']);
        $password_hash = load_password_hash();
        if ($hash == $password_hash) {
            $_SESSION['session_start'] = time();
        } else {
            $invalid_password = true;
        }
    }
}

if (isset($_SESSION['session_start'])) {
    if (is_active_session()) {
        header('Location:index.php');
    }
}
?>


<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>NoTrack Lockscreen</title>
        <!-- Tell the browser to be responsive to screen width -->
        <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
        <!-- Bootstrap 3.3.6 -->
        <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css">
        <!-- Font Awesome -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.5.0/css/font-awesome.min.css">
        <!-- Ionicons -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/ionicons/2.0.1/css/ionicons.min.css">
        <!-- Theme style -->
        <link rel="stylesheet" href="dist/css/AdminLTE.min.css">

        <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
        <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
        <!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
        <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
        <![endif]-->
    </head>
    <body class="hold-transition lockscreen">
        <!-- Automatic element centering -->
        <div class="lockscreen-wrapper">
            <div class="lockscreen-logo">
                <b>No</b>Track
            </div>
            <!-- User name -->
            <!--<div class="lockscreen-name">The password is incorrect. Try again</div>-->

            <!-- START LOCK SCREEN ITEM -->
            <div class="lockscreen-item">
                <!-- lockscreen image -->
                <div class="lockscreen-image">
                    <img src="dist/img/lock.png" alt="Login">
                </div>
                <!-- /.lockscreen-image -->

                <!-- lockscreen credentials (contains the form) -->
                <form class="lockscreen-credentials" action="" method="post">
                    <div class="input-group">
                        <input type="password" class="form-control" placeholder="password" name="password" autofocus>

                        <div class="input-group-btn">
                            <button type="button" class="btn"><i class="fa fa-arrow-right text-muted"></i></button>
                        </div>
                    </div>
                </form>
                <!-- /.lockscreen credentials -->

            </div>
            <!-- /.lockscreen-item -->
            <div class="help-block text-center">
                <?php
                if (isset($invalid_password)) {
                    echo "The password is incorrect. Try again";
                } else {
                    if (isset($_SESSION["session_expired"])) {
                        echo "Enter your password to retrieve your session";
                        ;
                    } else {
                        echo "Enter your password to login";
                    }
                }
                ?>
            </div>
        </div>
        <!-- /.center -->

        <!-- jQuery 2.2.3 -->
        <script src="plugins/jQuery/jquery-2.2.3.min.js"></script>
        <!-- Bootstrap 3.3.6 -->
        <script src="bootstrap/js/bootstrap.min.js"></script>
    </body>
</html>

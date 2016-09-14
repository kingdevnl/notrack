<?php
$current_page_name = "security";
$current_page_title = "Security";

require_once( dirname(__FILE__) . '/includes' . '/functions.php' );

session_start();
ensure_active_session();

$show_password_input_form = FALSE;

if (count($_POST) > 0) {
    if (isset($_POST['enable_password'])) {
        $show_password_input_form = TRUE;
    }
    if (isset($_POST['disable_password'])) {
        disable_password_protection();
        $show_password_input_form = FALSE;
    }
    if (isset($_POST['password'])) {
        $hashed_password = hash('sha512', $_POST['password']);
        enable_password_protection($hashed_password);
        $_SESSION['session_start'] = time();
    }
}

if (is_password_protection_enabled()) {
    $show_password_input_form = TRUE;
}
?>

<!DOCTYPE html>
<html>
    <!-- Document Head -->
    <?php
    require( dirname(__FILE__) . '/includes' . '/head.php' );
    ?>
    <body class="hold-transition skin-blue sidebar-mini">
        <div class="wrapper">

            <!-- Main Header -->
            <?php
            require( dirname(__FILE__) . '/includes' . '/main-header.php' );
            ?>
            <!-- Left side column. contains the logo and sidebar -->
            <?php
            require( dirname(__FILE__) . '/includes' . '/main-sidebar.php' );
            ?>


            <!-- Content Wrapper. Contains page content -->
            <div class="content-wrapper">
                <!-- Content Header (Page header) -->
                <section class="content-header">
                    <h1>
                        <?php echo $current_page_title ?>
                        <small>Alpha</small>
                    </h1>
                </section>


                <!-- Main content -->
                <section class="content">

                    <?php if (isset($_POST['password'])) { ?>
                        <div class="callout callout-success">
                            <p>Your password was successfully updated</p>
                        </div>
                    <?php } ?>       

                    <!-- Your Page Content Here -->

                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title">Password Protection</h3>
                        </div>
                        <!-- /.box-header -->
                        <!-- form start -->

                        <form action="" method="post">
                            <div class="box-body">
                                <div class="form-group">
                                    <?php
                                    if (is_password_protection_enabled()) {
                                        ?>
                                        <button type="submit" class="btn btn-default" name="disable_password"><i class="fa fa-unlock"></i> Turn off password protection</button>
                                        <?php
                                    } else {
                                        ?>
                                        <button type="submit" class="btn btn-primary" name="enable_password"><i class="fa fa-lock"></i> Turn on password protection</button>
                                        <?php
                                    }
                                    ?>
                                </div>
                            </div>
                            <!-- .box-body -->
                        </form>



                        <?php if ($show_password_input_form) { ?>
                            <form action="" method="post" onsubmit="return onSubmitPassword();">
                                <div class="box-body">
                                    <div class="form-group" id="passwordInputFormGroup">
                                        <label for="passwordInput">New Password</label>
                                        <input type="password" class="form-control" id="passwordInput" placeholder="Enter password" name="password">
                                    </div>
                                    <div class="form-group" id="passwordConfirmInputFormGroup">
                                        <label for="passwordConfirmInput">Confirm Password</label>
                                        <input type="password" class="form-control" id="passwordConfirmInput" placeholder="Re-enter password" name="password_confirm">
                                        <span class="help-block" style="display: none">Passwords do not match</span>
                                    </div>
                                </div>

                                <div class="box-footer">
                                    <button type="submit" class="btn btn-primary">Save Password</button>
                                </div>
                            </form>                
                        <?php } ?>

                        <script>
                            function onSubmitPassword() {
                                if (!$("#passwordInput").val()) {
                                    $("#passwordInputFormGroup").addClass("has-error");
                                    return false;
                                }
                                if (!$("#passwordConfirmInput").val()) {
                                    $("#passwordConfirmInputFormGroup").addClass("has-error");
                                    return false;
                                }
                                if ($("#passwordInput").val() !== $("#passwordConfirmInput").val()) {
                                    $("#passwordConfirmInputFormGroup").addClass("has-error");
                                    $(".form-group .help-block").show();
                                    return false;
                                }
                                return true;
                            }
                        </script>

                    </div>

                </section>
                <!-- /.content -->
            </div>
            <!-- /.content-wrapper -->

            <!-- Main Footer -->
            <?php
            require( dirname(__FILE__) . '/includes' . '/main-footer.php' );
            ?>

            <!-- Control Sidebar -->
            <?php
            require( dirname(__FILE__) . '/includes' . '/control-sidebar.php' );
            ?>
        </div>
        <!-- ./wrapper -->

        <!-- REQUIRED JS SCRIPTS -->
        <?php
        require( dirname(__FILE__) . '/includes' . '/required-js-scripts.php' );
        ?>
    </body>
</html>

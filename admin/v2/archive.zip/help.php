<?php
$current_page_name = 'help';
$current_page_title = 'Help';

require_once( dirname(__FILE__) . '/includes' . '/functions.php' );

session_start();
ensure_active_session();
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

                    <!-- Your Page Content Here -->




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

<?php
$current_page_name = 'dashboard';
$current_page_title = 'Dashboard';

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

                    <div class="row">

                        <div class="col-md-3 col-sm-6 col-xs-12">
                            <a href="blocked.php">
                                <div class="info-box bg-aqua">
                                    <!-- Apply any bg-* class to to the icon to color it -->
                                    <span class="info-box-icon"><i class="fa fa-times"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Blocked This Week</span>
                                        <span class="info-box-number"><?php echo get_blocked_queries_count() ?></span>
                                    </div><!-- /.info-box-content -->
                                </div><!-- /.info-box -->
                                <!-- /.info-box -->
                            </a>
                        </div>
                        <!-- /.col -->

                        <div class="col-md-3 col-sm-6 col-xs-12">
                            <a href="#">
                                <div class="info-box bg-light-blue">
                                    <!-- Apply any bg-* class to to the icon to color it -->
                                    <span class="info-box-icon"><i class="fa fa-tasks"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">DNS Queries Today</span>
                                        <span class="info-box-number"><?php echo get_dns_queries_count() ?></span>
                                    </div><!-- /.info-box-content -->
                                </div><!-- /.info-box -->
                            </a>
                        </div><!-- /.col -->

                        <!-- fix for small devices only -->
                        <div class="clearfix visible-sm-block"></div>

                        <div class="col-md-3 col-sm-6 col-xs-12">
                            <a href="network.php">
                                <div class="info-box bg-purple">
                                    <span class="info-box-icon"><i class="fa fa-sitemap"></i></span>
                                    <div class="info-box-content">
                                        <?php
                                        if (is_dhcp_enabled()) {
                                            ?>
                                            <span class="info-box-text">Connected Devices</span>
                                            <span class="info-box-number"><?php echo get_dhcp_lease_count() ?></span>                                        
                                            <?php
                                        } else {
                                            ?>
                                            <span class="info-box-text">DHCP is not enabled</span>
                                            <?php
                                        }
                                        ?>
                                        <!-- The progress section is optional -->
                                    </div><!-- /.info-box-content -->
                                </div><!-- /.info-box -->
                            </a>
                        </div>
                        <!-- /.col -->

                    </div>
                    <!-- /.row -->




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

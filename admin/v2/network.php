<?php
$current_page_name = 'network';
$current_page_title = 'Network';

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

                    <?php
                    if (is_dhcp_enabled()) {
                        ?>
                        <div class="box box-primary">
                            <div class="box-header">
                                <h3 class="box-title">Connected Devices</h3>
                            </div>
                            <!-- /.box-header -->
                            <div class="box-body no-padding">
                                <table class="table table-striped">
                                    <tbody><tr>
                                            <th>IP Address</th>
                                            <th>Device Name</th>
                                            <th>MAC Address</th>
                                            <th>Valid Until</th>
                                        </tr>
                                        <?php
                                        $leases = get_dhcp_leases();
                                        foreach ($leases as $lease) {
                                            ?>
                                            <tr>
                                                <td><?php echo $lease[2]; ?></td>
                                                <td><?php echo $lease[3]; ?></td>
                                                <td><?php echo $lease[1]; ?></td>
                                                <td><?php echo date("d M Y \- H:i:s", $lease[0]); ?></td>
                                            </tr><?php
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                            <!-- /.box-body -->
                        </div>
                        <?php
                    } else {
                        ?>
                        <div class="box box-primary">
                            <div class="box-header with-border">
                                <h3 class="box-title">DHCP</h3>
                            </div><!-- /.box-header -->
                            <div class="box-body">
                                DHCP is currently not handled by NoTrack
                            </div><!-- /.box-body -->
                        </div>
                        <?php
                    }
                    ?>

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

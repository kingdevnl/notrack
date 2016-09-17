<?php
$current_page_name = 'blocked';
$current_page_title = 'Blocked This Week';

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
                        <div class="col-xs-12">
                            <div class="box box-primary">
<!--                                <div class="box-header">
                                    <h3 class="box-title">Hover Data Table</h3>
                                </div>-->
                                <!-- /.box-header -->
                                <div class="box-body">
                                    <style>
                                        .query-time {
                                            width: 15%;
                                        }
                                        .query-method {
                                            width: 7%;
                                        }
                                        .query-ellipsis {
                                            position: relative;
                                        }
                                        .query-ellipsis:before {
                                            content: '&nbsp;';
                                            visibility: hidden;
                                        }
                                        .query-ellipsis span {
                                            position: absolute;
                                            left: 0;
                                            right: 0;
                                            white-space: nowrap;
                                            overflow: hidden;
                                            text-overflow: ellipsis;
                                            padding-left: 8px;
                                            padding-right: 8px;
                                        }
                                    </style>
                                    <table id="blocked_queries" class="table table-bordered table-hover">
                                        <thead>
                                            <tr>
                                                <th>Time</th>
                                                <th>Method</th>
                                                <th>Request</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $blocked_queries = get_blocked_queries();
                                            foreach ($blocked_queries as $blocked_query) {
                                                ?>
                                                <tr>
                                                    <td class="query-ellipsis query-time"><span><?php echo date("d M Y \- H:i:s", $blocked_query[0]); ?></span></td>
                                                    <td class="query-ellipsis query-method"><span><?php echo $blocked_query[1]; ?></span></td>
                                                    <td class="query-ellipsis"><span title="<?php echo $blocked_query[2]; ?>"><?php echo $blocked_query[2]; ?></span></td>
                                                </tr>
                                                <?php
                                            }
                                            ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th>Time</th>
                                                <th>Method</th>
                                                <th>Request</th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <!-- /.box-body -->
                            </div>
                            <!-- /.box -->

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
        
        <script src="plugins/datatables/jquery.dataTables.min.js"></script>
        <script src="plugins/datatables/dataTables.bootstrap.min.js"></script>
        
        <script>
            $(function () {
                $('#blocked_queries').DataTable({
                    "order": [[0, "desc"]]
                });
            });
        </script>
    </body>
</html>

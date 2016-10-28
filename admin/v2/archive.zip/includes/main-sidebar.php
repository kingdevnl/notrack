<?php

function is_active($current_page_name, $page_name) {
    if ($current_page_name == $page_name) {
        echo 'active';
    }
}

function is_treeview_active($current_page_name, $children) {
    foreach ($children as $child) {
        if ($current_page_name == $child) {
            echo 'active';
            break;
        }
    }
}
?>

<aside class="main-sidebar">

    <!-- sidebar: style can be found in sidebar.less -->
    <section class="sidebar">

        <!-- Sidebar Menu -->
        <ul class="sidebar-menu">
            <!--<li class="header">HEADER</li>-->
            <!-- Optionally, you can add icons to the links -->
            <li class="<?php is_active($current_page_name, "dashboard") ?>"><a href="index.php"><i class="fa fa-th-large"></i> <span>Dashboard</span></a></li>


            <li class="treeview <?php is_treeview_active($current_page_name ,array('blocked')) ?>">
                <a href="#">
                    <i class="fa fa-bar-chart"></i> <span>Statistics</span>
                    <span class="pull-right-container">
                        <i class="fa fa-angle-left pull-right"></i>
                    </span>
                </a>
                <ul class="treeview-menu">
                    <li class="<?php is_active($current_page_name, "blocked") ?>"><a href="blocked.php"><i class="fa fa-circle-o"></i> Blocked This Week</a></li>
                </ul>
            </li>

            <li class="<?php is_active($current_page_name, "network") ?>"><a href="network.php"><i class="fa fa-sitemap"></i> <span>Network</span></a></li>
            <li class="<?php is_active($current_page_name, "security") ?>"><a href="security.php"><i class="fa fa-shield"></i> <span>Security</span></a></li>
            <li class="<?php is_active($current_page_name, "help") ?>"><a href="help.php"><i class="fa fa-question"></i> <span>Help</span></a></li>

        </ul>
        <!-- /.sidebar-menu -->
    </section>
    <!-- /.sidebar -->
</aside>
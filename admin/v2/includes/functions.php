<?php

require_once( 'variables.php' );

function ensure_active_session() {
    if (is_password_protection_enabled()) {
        if (isset($_SESSION['session_start'])) {
            if (!is_active_session()) {
                $_SESSION['session_expired'] = TRUE;
                header('Location:lockscreen.php');
            }
        } else {
            header('Location:lockscreen.php');
        }
    }
}

function is_active_session() {
    $session_duration = 1800;
    $current_time = time();
    if (isset($_SESSION['session_start'])) {
        if ((time() - $_SESSION['session_start']) < $session_duration) {
            return true;
        }
    }
    return false;
}

function is_password_protection_enabled() {
    global $PASSWORD_FILE;
    return file_exists($PASSWORD_FILE);
}

function enable_password_protection($hashed_password){
    exec("sudo ntrk-exec --enable-password $hashed_password");
}

function disable_password_protection(){
    exec("sudo ntrk-exec --disable-password");
}

function load_password_hash() {
    global $PASSWORD_FILE;
    if (file_exists($PASSWORD_FILE)) {
        $file = fopen($PASSWORD_FILE, 'r');
        $hash = trim(fgets($file));
        fclose($file);
        return $hash;
    } else {
        throw new Exception('File not found: $PASSWORD_FILE');
    }
}

function is_dhcp_enabled() {
    global $DHCP_LEASE_FILE;
    return file_exists($DHCP_LEASE_FILE);
}

function get_dhcp_leases() {
    global $DHCP_LEASE_FILE;
    $leases = array();
    $file = fopen($DHCP_LEASE_FILE, 'r');
    while (!feof($file)) {
        $line = trim(fgets($file));
        if ($line != '') {
            $lease = explode(' ', $line);
            array_push($leases, $lease);
        }
    }
    return $leases;
}

function get_dhcp_lease_count() {
    global $DHCP_LEASE_FILE;
    return floatval(exec("wc -l $DHCP_LEASE_FILE | cut -d\  -f 1"));
}

function get_dns_queries_count(){
    global $NOTRACK_LOG_FILE;
    return floatval(exec("grep -F query[A] $NOTRACK_LOG_FILE | wc -l"));
}

function get_blocked_sites_count(){
    global $LIGHTTPD_ACCESS_LOG_FILE;
    return floatval(exec("grep -v admin $LIGHTTPD_ACCESS_LOG_FILE | wc -l"));
}

?>
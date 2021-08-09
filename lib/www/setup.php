<?php
require_once dirname(__FILE__)."/core.php";
require_once "Modules/muc/muc_model.php";
require_once "Modules/user/user_model.php";
$ctrl = new Controller($mysqli,$redis);
$user = new User($mysqli,$redis);

// TODO: Do not hardcode type, address and port
$type = 'http';
$address = 'localhost';
$port = 8080;

if (isset($options['a']) || isset($options['apikey'])) {
    $apikey = isset($options['a']) ? $options['a'] : $options['apikey'];
    if (strlen($apikey) != 32) {
        echo "Invalid apikey: $apikey\n"; die;
    }
    $session = $user->apikey_session($apikey);
    $userid = $session['userid'];
}
else if (isset($options['i']) || isset($options['init'])) {
    if ($user->get_number_of_users() == 0) {
        $email = 'admin@'.gethostname().'.local';
        $result = $user->register('admin', 'admin', $email, date_default_timezone_get());
        if (isset($result['success']) && $result['success'] == false) {
            echo "Unable to register default user \"admin\": ".$result['message']."\n"; die;
        }
        $apikey = $result['apikey_write'];
        $userid = $result['userid'];
    }
    else {
        $result = $mysqli->query("SELECT id FROM users ORDER BY id ASC LIMIT 1");
        $userid = $result->fetch_object()->id;
        $apikey = $user->get_apikey_write($userid);
    }
}
else {
    // No user available
    return;
}
try {
    $ctrl->create($userid, 'http', 'Local', '', '{"address":"'.$address.'","port":'.$port.'}');

    if (!is_writable('/opt/openmuc/conf') || (is_file('/opt/openmuc/conf/emoncms.conf') && !is_writable('/opt/openmuc/conf/emoncms.conf'))) {
        echo "Unable to edit emoncms configution file in /opt/openmuc/conf\n"; die;
    }
    if (isset($options['c']) || isset($options['config'])) {
        $config = isset($options['c']) ? $options['c'] : $options['config'];
    }
    else {
        $config = '/opt/openmuc/conf/emoncms.conf';
    }
    if (!is_file($config)) {
        $config = $root.'/conf/emoncms.default.conf';
    }
    if (!is_file($config)) {
        echo "Unable to find default emoncms configuration $config\n"; die;
    }

    $url = $type.'://'.$address.'/';
    $contents = file_get_contents($config);
    $contents = str_replace(';address = http://localhost/emoncms/', 'address = '.$url, $contents);
    $contents = str_replace(';authorization = WRITE', 'authorization = WRITE', $contents);
    $contents = str_replace(';authentication = <apikey>', 'authentication = '.$apikey, $contents);
    file_put_contents('/opt/openmuc/conf/emoncms.conf', $contents);
}
catch(Exception $e) {
    echo "Unable to register controller for user $userid: ".$e->getMessage()."\n";
}

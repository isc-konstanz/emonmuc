<?php
require_once dirname(__FILE__)."/lib/www/core.php";
require_once "Modules/muc/muc_model.php";
require_once "Modules/user/user_model.php";
require_once "Lib/dbschemasetup.php";
db_schema_setup($mysqli,load_db_schema(),true);
$ctrl = new Controller($mysqli,$redis);
$user = new User($mysqli,$redis);

$type = 'HTTP';
$address = 'localhost';
$path = '/emoncms/';

if (isset($options['a']) || isset($options['apikey'])) {
    $apikey = isset($options['a']) ? $options['a'] : $options['apikey'];
    if (strlen($apikey) != 32) {
        echo "Invalid apikey: $apikey\n"; die;
    }
    $session = $user->apikey_session($apikey);
    $userid = $session['userid'];
}
else if ($user->get_number_of_users() == 0) {
    $email = 'admin@'.gethostname().'.local';
    $result = $user->register('admin', 'admin', $email);
    if (isset($result['success']) && $result['success'] == false) {
        echo "Unable to register default user \"admin\": ".$result['message']."\n"; die;
    }
    
    $apikey = $result['apikey_write'];
    $userid = $result['userid'];
}
else {
    $userid = 1;
    $apikey = $user->get_apikey_write($userid);
}

if (count($ctrl->get_list($userid)) == 0) {
    $result = $ctrl->create($userid, $type, $address, 'Local');
    if (isset($result['success']) && $result['success'] == false) {
        echo "Unable to create MUC for user $userid: ".$result['message']."\n"; die;
    }
    
    if (!is_file($root.'/conf/emoncms.default.conf')) {
        echo "Unable to find default emoncms configuration ".$root."/conf/emoncms.default.conf\n"; die;
    }
    if (!is_writable($root.'/conf') || (is_file($root.'/conf/emoncms.conf') && !is_writable($root.'/conf/emoncms.conf'))) {
        echo "Unable to edit emoncms configution file in ".$root."/conf\n"; die;
    }
    
    $url = strtolower($type).'://'.$address.$path;
    $contents = file_get_contents($root.'/conf/emoncms.default.conf');
    $contents = str_replace(';address = http://localhost/emoncms/', 'address = '.$url, $contents);
    $contents = str_replace(';authorization = WRITE', 'authorization = WRITE', $contents);
    $contents = str_replace(';authentication = <apikey>', 'authentication = '.$apikey, $contents);
    file_put_contents($root.'/conf/emoncms.conf', $contents);
}

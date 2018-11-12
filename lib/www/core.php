<?php
define('EMONCMS_EXEC', 1);

$root = dirname(dirname(__FILE__));

$options_short = "d:a:";
$options_long  = array(
    "dir:",
    "apikey:"
);
$options = getopt($options_short, $options_long);

if (isset($options['d'])) {
    $dir = $options['d'];
}
else if (isset($options['dir'])) {
    $dir = $options['dir'];
}
else {
    $dir = "/var/www/html/emoncms";
}
if(substr_compare($dir, '/', strlen($dir)-1, 1) !== 0) {
    $dir = $dir."/";
}
chdir($dir);

require_once "core.php";
require_once "process_settings.php";
require_once "Lib/EmonLogger.php";

if ($redis_enabled) {
    $redis = new Redis();
    $connected = $redis->connect($redis_server['host'], $redis_server['port']);
    if (!$connected) { echo "Can't connect to redis at ".$redis_server['host'].":".$redis_server['port']." , it may be that redis-server is not installed or started see readme for redis installation"; die; }
    if (!empty($redis_server['prefix'])) $redis->setOption(Redis::OPT_PREFIX, $redis_server['prefix']);
    if (!empty($redis_server['auth'])) {
        if (!$redis->auth($redis_server['auth'])) {
            echo "Can't connect to redis at ".$redis_server['host'].", autentication failed"; die;
        }
    }
} else {
    $redis = false;
}

# Check MySQL PHP modules are loaded
if (!extension_loaded('mysql') && !extension_loaded('mysqli')){
    echo "Your PHP installation appears to be missing the MySQL extension(s) which are required by Emoncms."; die;
}

# Check Gettext PHP  module is loaded
if (!extension_loaded('gettext')){
    echo "Your PHP installation appears to be missing the gettext extension which is required by Emoncms."; die;
}

$mysqli = @new mysqli($server,$username,$password,$database,$port);
if ( $mysqli->connect_error ) {
    echo "Can't connect to database, please verify credentials/configuration in settings.php"; die;
}
// Set charset to utf8
$mysqli->set_charset("utf8");

<?php
require_once dirname(__FILE__)."/core.php";

if ($redis && file_exists("Modules/device/device_model.php")) {
    require_once "Modules/device/device_model.php";
    $device = new Device($mysqli,$redis);
    $device->reload_template_list();
}

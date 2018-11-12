<?php
/*
 Released under the GNU Affero General Public License.
 See COPYRIGHT.txt and LICENSE.txt.
 
 MUC module contributed by Adrian Minde Adrian_Minde(at)live.de 2017
 ---------------------------------------------------------------------
 Sponsored by http://isc-konstanz.de/
 */

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function device_controller($format, $action, $subaction, $method) {
    global $mysqli, $redis, $session;
    
    $result = false;
    
    require_once "Modules/muc/muc_model.php";
    $ctrl = new Controller($mysqli, $redis);
    
    require_once "Modules/muc/Models/device_model.php";
    $device = new DeviceConnection($ctrl);
    
    if ($format == 'html') {
        
        if ($action == "view" && $session['write']) $result = view("Modules/muc/Views/device/device_view.php",array());
        elseif ($action == 'api') $result = view("Modules/muc/Views/device/device_api.php", array());
    }
    elseif ($format== 'json') {
        
        if ($action == 'list') {
            if ($session['userid']>0 && $session['write']) $result = $device->get_list($session['userid'], null);
        }
        elseif ($action == 'states') {
            if ($session['userid']>0 && $session['write']) $result = $device->get_states($session['userid'], null);
        }
        else {
            $ctrlid = intval(get('ctrlid'));
            if ($ctrl->exists($ctrlid)) {
                $ctrlget = $ctrl->get($ctrlid);
                if (isset($session['write']) && $session['write'] && $session['userid'] > 0 
                        && $session['userid'] == $ctrlget['userid']) {
                    
                    if ($action == "create") $result = $device->create($ctrlid, get('driverid'), get('configs'));
                    elseif ($action == 'info') $result = $device->info($ctrlid, get('driverid'));
                    elseif ($action == "get") $result = $device->get($ctrlid, get('id'));
                    elseif ($action == 'update') $result = $device->update($ctrlid, get('id'), get('configs'));
                    elseif ($action == "delete") $result = $device->delete($ctrlid, get('id'));
                    elseif ($action == 'scan') {
                        if ($subaction == 'start') $result = $device->scan_start($ctrlid, get('driverid'), get('settings'));
                        elseif ($subaction == "progress") $result = $device->scan_progress($ctrlid, get('driverid'));
                        elseif ($subaction == "cancel") $result = $device->scan_cancel($ctrlid, get('driverid'));
                    }
                }
            }
            else {
                $result = array('success'=>false, 'message'=>'Controller does not exist');
            }
        }
    }
    return $result;
}
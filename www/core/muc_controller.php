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

const DIR_MODULE = "Modules/muc";
const DIR_VIEWS = DIR_MODULE."/Views";

require_once DIR_MODULE."/muc_model.php";

function muc_controller() {
    global $mysqli, $redis, $session, $route;
    
    $result = false;
    if ($route->format == 'html') {
        if ($route->action == "view") {
            $result = view(DIR_VIEWS."/muc_view.php",array());
        }
        elseif ($route->action == 'api' && $session['write']) {
            $result = view(DIR_VIEWS."/muc_api.php", array());
        }
        elseif ($route->action == 'channel') {
            if ($route->subaction == "view") {
                $result = view(DIR_VIEWS."/channel/channel_view.php",array());
            }
            elseif ($route->subaction == 'api' && $session['write']) {
                $result = view(DIR_VIEWS."/channel/channel_api.php", array());
            }
        }
        elseif ($route->action == 'device') {
            if ($route->subaction == "view") {
                $result = view(DIR_VIEWS."/device/device_view.php",array());
            }
            elseif ($route->subaction == 'api' && $session['write']) {
                $result = view(DIR_VIEWS."/device/device_api.php", array());
            }
        }
        elseif ($route->action == 'driver') {
            if ($route->subaction == "view") {
                $result = view(DIR_VIEWS."/driver/driver_view.php",array());
            }
            elseif ($route->subaction == 'api' && $session['write']) {
                $result = view(DIR_VIEWS."/driver/driver_api.php", array());
            }
        }
    }
    elseif ($route->format == 'json') {
        $ctrl = new Controller($mysqli, $redis);
        
        if ($route->action == "channel") $result = channel_controller($ctrl);
        elseif ($route->action == "device") $result = device_controller($ctrl);
        elseif ($route->action == "driver") $result = driver_controller($ctrl);
        elseif ($route->action == "create" && $session['write']) {
            $result = $ctrl->create($session['userid'], get('type'), get('name'), get('description'), get('options'));
        }
        elseif ($route->action == 'list') {
            if ($route->subaction == "drivers") {
                $result = $ctrl->get_child_list($session['userid'], 0);
            }
            elseif ($route->subaction == "devices") {
                $result = $ctrl->get_child_list($session['userid'], 1);
            }
            elseif ($route->subaction == "channels") {
                $result = $ctrl->get_child_list($session['userid'], 2);
            }
            else {
                $result = $ctrl->get_list($session['userid']);
            }
        }
        elseif ($route->action == 'driver') {
            $result = $ctrl->get_list($session['userid']);
        }
        elseif ($route->action == "config") {
            $result = $ctrl->get_config($session['userid'], get('id'));
        }
        else {
            $details = access_ctrl($ctrl, 'id');
            if ($route->action == "get") {
                $result = $details;
            }
            elseif ($route->action == 'load') {
                $result = $ctrl->load($details);
            }
            elseif ($route->action == 'update' && $session['write']) {
                $result = $ctrl->update($session['userid'], $details['id'], get('fields'));
            }
            elseif ($route->action == "delete" && $session['write']) {
                $result = $ctrl->delete($session['userid'], $details['id']);
            }
        }
    }
    return array('content'=>$result);
}

function driver_controller(Controller $ctrl) {
    global $session, $route;
    
    if ($route->subaction == 'list' || $route->subaction == "configured") {
        return call_ctrl_funcs($session['userid'], $ctrl, 'driver', 'get_list');
    }
    elseif ($route->subaction == "unconfigured" && $session['write']) {
        return call_ctrl_funcs($session['userid'], $ctrl, 'driver', 'get_unconfigured');
    }
    elseif ($route->subaction == "registered" && $session['write']) {
        return call_ctrl_funcs($session['userid'], $ctrl, 'driver', 'get_registered');
    }
    else {
        $details = access_ctrl($ctrl, 'ctrlid');
        if ($route->subaction == "create" && $session['write']) {
            return $ctrl->driver($details)->create(get('id'), get('configs'));
        }
        elseif ($route->subaction == "info") {
            return $ctrl->driver($details)->info(get('id'));
        }
        elseif ($route->subaction == "get") {
            return $ctrl->driver($details)->get(get('id'));
        }
        elseif ($route->subaction == 'update' && $session['write']) {
            return $ctrl->driver($details)->update(get('id'), get('configs'));
        }
        elseif ($route->subaction == "delete" && $session['write']) {
            return $ctrl->driver($details)->delete(get('id'));
        }
    }
    return false;
}

function device_controller(Controller $ctrl) {
    global $session, $route;
    
    if ($route->subaction == 'list') {
        return call_ctrl_funcs($session['userid'], $ctrl, 'device', 'get_list');
    }
    elseif ($route->subaction == "states") {
        return call_ctrl_funcs($session['userid'], $ctrl, 'device', 'get_states');
    }
    else {
        $details = access_ctrl($ctrl, 'ctrlid');
        if ($route->subaction == "create" && $session['write']) {
            return $ctrl->device($details)->create(get('driverid'), get('configs'));
        }
        elseif ($route->subaction == "info") {
            return $ctrl->device($details)->info(get('driverid'));
        }
        elseif ($route->subaction == "scan" && $session['write']) {
            if ($route->subaction2 == 'start') {
                return $ctrl->device($details)->scan_start(get('driverid'), get('settings'));
            }
            elseif ($route->subaction2 == 'progress') {
                return $ctrl->device($details)->scan_progress(get('driverid'));
            }
            elseif ($route->subaction2 == 'cancel') {
                return $ctrl->device($details)->scan_cancel(get('driverid'));
            }
        }
        else {
            if (!$ctrl->device($details)->exists(get('id'))) {
                return array('success'=>false, 'message'=>'Device does not exist');
            }
            elseif ($route->subaction == "get") {
                return $ctrl->device($details)->get(get('id'));
            }
            elseif ($route->subaction == "update" && $session['write']) {
                return $ctrl->device($details)->update(get('id'), get('configs'));
            }
            elseif ($route->subaction == "delete" && $session['write']) {
                return $ctrl->device($details)->delete(get('id'));
            }
        }
    }
    return false;
}

function channel_controller(Controller $ctrl) {
    global $session, $route;
    
    if ($route->subaction == 'list') {
        return call_ctrl_funcs($session['userid'], $ctrl, 'channel', 'get_list');
    }
    elseif ($route->subaction == "states") {
        return call_ctrl_funcs($session['userid'], $ctrl, 'channel', 'get_states');
    }
    elseif ($route->subaction == "records") {
        return call_ctrl_funcs($session['userid'], $ctrl, 'channel', 'get_records');
    }
    else {
        $details = access_ctrl($ctrl, 'ctrlid');
        if ($route->subaction == "create" && $session['write']) {
            return $ctrl->channel($details)->create(get('driverid'), get('deviceid'), get('configs'));
        }
        elseif ($route->subaction == "info") {
            return $ctrl->channel($details)->info(get('driverid'));
        }
        elseif ($route->subaction == "scan" && $session['write']) {
            return $ctrl->channel($details)->scan(get('driverid'), get('deviceid'), get('settings'));
        }
        else {
            if (!$ctrl->channel($details)->exists(get('id'))) {
                return array('success'=>false, 'message'=>'Channel does not exist');
            }
            elseif ($route->subaction == "get") {
                return $ctrl->channel($details)->get(get('id'));
            }
            elseif ($route->subaction == "set") {
                return $ctrl->channel($details)->set(get('id'), get('value'), get('valueType'));
            }
            elseif ($route->subaction == "write") {
                return $ctrl->channel($details)->write(get('id'), get('value'), get('valueType'));
            }
            elseif ($route->subaction == "update" && $session['write']) {
                return $ctrl->channel($details)->update(get('id'), get('nodeid'), get('configs'));
            }
            elseif ($route->subaction == "delete" && $session['write']) {
                return $ctrl->channel($details)->delete(get('id'));
            }
        }
    }
    return false;
}

function call_ctrl_funcs($userid, $ctrl, $class, $function) {
    $result = array();
    $userid = intval($userid);
    foreach ($ctrl->get_list($userid) as $c) {
        $result = array_merge($result, $ctrl->$class($c)->$function());
    }
    return $result;
}

function access_ctrl($ctrl, $key) {
    $ctrlid = intval(get($key));
    if (!$ctrl->exists($ctrlid)) {
        throw new ControllerException("Controller does not exist");
    }
    global $session;
    
    $result = $ctrl->get($ctrlid);
    if ($result['userid'] != $session['userid']) {
        throw new ControllerException("Controller access not permitted");
    }
    return $result;
}

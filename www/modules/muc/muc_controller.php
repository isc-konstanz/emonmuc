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

function muc_controller() {
    global $mysqli, $redis, $session, $route;
    
    $result = false;
    
    if ($route->action == "channel") {
        require_once "Modules/muc/Controllers/channel_controller.php";
        $result = channel_controller($route->format, $route->subaction, $route->subaction2, $route->method);
    }
    elseif ($route->action == "device") {
        require_once "Modules/muc/Controllers/device_controller.php";
        $result = device_controller($route->format, $route->subaction, $route->subaction2, $route->method);
    }
    elseif ($route->action == "driver") {
        require_once "Modules/muc/Controllers/driver_controller.php";
        $result = driver_controller($route->format, $route->subaction, $route->method);
    }
    else {
        require_once "Modules/muc/muc_model.php";
        $ctrl = new Controller($mysqli, $redis);
        
        if ($route->format == 'html') {
            
            if ($route->action == "view" && $session['write']) $result = view("Modules/muc/Views/muc_view.php",array());
            elseif ($route->action == 'api') $result = view("Modules/muc/Views/muc_api.php", array());
        }
        elseif ($route->format == 'json') {
            
            if ($route->action == 'list') {
                if ($session['userid']>0 && $session['write']) $result = $ctrl->get_list($session['userid']);
            }
            elseif ($route->action == "create") {
                if ($session['userid']>0 && $session['write']) $result = $ctrl->create($session['userid'], get('type'), get('address'), get('description'));
            }
            elseif ($route->action == "config") {
                // Configuration may be retrieved with read key
                if ($session['userid']>0 && $session['write']) $result = $ctrl->get_config($session['userid'], get('id'));
            }
            else {
                $ctrlid = intval(get('id'));
                if ($ctrl->exists($ctrlid)) {
                    $ctrlget = $ctrl->get($ctrlid);
                    if ($session['write'] && $session['userid']>0 && $ctrlget['userid']==$session['userid']) {
                        if ($route->action == "get") $result = $ctrlget;
                        elseif ($route->action == 'set') $result = $ctrl->set_fields($session['userid'], $ctrlid, get('fields'));
                        elseif ($route->action == "delete") $result = $ctrl->delete($session['userid'], $ctrlid);
                    }
                }
                else {
                    $result = array('success'=>false, 'message'=>'Controller does not exist');
                }
            }
        }
    }
    return array('content'=>$result);
}
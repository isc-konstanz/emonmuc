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

function driver_controller($format, $action, $method) {
    global $mysqli, $redis, $session;

    $result = false;

    require_once "Modules/muc/muc_model.php";
    $ctrl = new Controller($mysqli, $redis);

    require_once "Modules/muc/Models/driver_model.php";
    $driver = new Driver($ctrl);

    if ($format == 'html') {
        
        if ($action == "view" && $session['write']) $result = view("Modules/muc/Views/driver/driver_view.php",array());
        elseif ($action == 'api') $result = view("Modules/muc/Views/driver/driver_api.php", array());
    }
    elseif ($format == 'json') {
        
        if ($action == 'list') {
            if ($session['userid']>0 && $session['write']) $result = $driver->get_list($session['userid'], null);
        }
        elseif ($action == "registered") {
            if ($session['userid']>0 && $session['write']) $result = $driver->get_registered($session['userid'], get('ctrlid'));
        }
        elseif ($action == "configured") {
            if ($session['userid']>0 && $session['write']) $result = $driver->get_configured($session['userid'], get('ctrlid'));
        }
        elseif ($action == "unconfigured") {
            if ($session['userid']>0 && $session['write']) $result = $driver->get_unconfigured($session['userid'], get('ctrlid'));
        }
        else {
            $ctrlid = intval(get('ctrlid'));
            if ($ctrl->exists($ctrlid)) {
                $ctrlget = $ctrl->get($ctrlid);
                if (isset($session['write']) && $session['write'] && $session['userid'] > 0 
                        && $session['userid'] == $ctrlget['userid']) {
                    
                    if ($action == "create") $result = $driver->create($ctrlid, get('id'), get('configs'));
                    elseif ($action == "info") $result = $driver->info($ctrlid, get('id'));
                    elseif ($action == "get") $result = $driver->get($ctrlid, get('id'));
                    elseif ($action == 'update') $result = $driver->update($ctrlid, get('id'), get('configs'));
                    elseif ($action == "delete") $result = $driver->delete($ctrlid, get('id'));
                }
            }
            else {
                $result = array('success'=>false, 'message'=>'Controller does not exist');
            }
        }
    }
    return $result;
}
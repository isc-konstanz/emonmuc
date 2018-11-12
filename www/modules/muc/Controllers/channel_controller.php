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

function channel_controller($format, $action, $subaction, $method) {
    global $mysqli, $redis, $session;

    $result = false;

    require_once "Modules/muc/muc_model.php";
    $ctrl = new Controller($mysqli, $redis);

    require_once "Modules/muc/Models/channel_model.php";
    $channel = new Channel($ctrl, $mysqli, $redis);

    if ($format == 'html') {
        
        if ($action == "view" && $session['write']) $result = view("Modules/muc/Views/channel/channel_view.php",array());
        elseif ($action == 'api') $result = view("Modules/muc/Views/channel/channel_api.php", array());
    }
    elseif ($format == 'json') {
        
        if ($action == 'list') {
            if ($session['userid']>0 && $session['write']) $result = $channel->get_list($session['userid'], null);
        }
        elseif ($action == 'states') {
            if ($session['userid']>0 && $session['write']) $result = $channel->get_states($session['userid'], null);
        }
        elseif ($action == 'records') {
            if ($session['userid']>0 && $session['write']) $result = $channel->get_records($session['userid'], null);
        }
        else {
            $ctrlid = (int) get('ctrlid');
            if ($ctrl->exists($ctrlid)) {
                $ctrlget = $ctrl->get($ctrlid);
                if (isset($session['write']) && $session['write'] && $session['userid'] > 0 
                        && $session['userid'] == $ctrlget['userid']) {
                    
                    if ($action == "create") $result = $channel->create($session['userid'], $ctrlid, get('driverid'), get('deviceid'), get('configs'));
                    elseif ($action == 'scan') $result = $channel->scan($session['userid'], $ctrlid, get('driverid'), get('deviceid'), get('settings'));
                    elseif ($action == 'info') $result = $channel->info($session['userid'], $ctrlid, get('driverid'));
                    elseif ($action == "get") $result = $channel->get($ctrlid, get('id'));
                    elseif ($action == "set") $result = $channel->set($ctrlid, get('id'), get('value'), get('valueType'));
                    elseif ($action == "write") $result = $channel->write($ctrlid, get('id'), get('value'), get('valueType'));
                    elseif ($action == 'update') $result = $channel->update($session['userid'], $ctrlid, get('nodeid'), get('id'), get('configs'));
                    elseif ($action == "delete") $result = $channel->delete($ctrlid, get('id'));
                }
            }
            else {
                $result = array('success'=>false, 'message'=>'Controller does not exist');
            }
        }
    }
    return $result;
}
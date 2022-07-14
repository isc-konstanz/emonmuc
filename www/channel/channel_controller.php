<?php
/*
 Released under the GNU Affero General Public License.
 See COPYRIGHT.txt and LICENSE.txt.
 
 Channel module contributed by Adrian Minde Adrian_Minde(at)live.de 2018
 ---------------------------------------------------------------------
 Sponsored by https://isc-konstanz.de/
 */

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function channel_controller() {
    global $session, $route;

    $result = false;
    if ($route->format == 'html') {
        if ($route->action == "") $route->action = "view";
        
        if ($route->action == "view" && $session['write']) $result = view("Modules/channel/Views/channel_view.php", array());
        else if ($route->action == 'api') $result = view("Modules/channel/Views/channel_api.php", array());
    }
    return array('content'=>$result);
}

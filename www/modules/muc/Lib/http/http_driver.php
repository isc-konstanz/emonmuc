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

require_once "Modules/muc/Lib/muc_driver.php";

class HttpDriver extends ControllerDriver {
    private $http;

    public function __construct($ctrl) {
        parent::__construct($ctrl);
        require_once "Modules/muc/Lib/http/http.php";
        $options = $ctrl['options'];
        $this->http = new Http($ctrl['type'] == 'https', $options['address'], $options['port'], $options['password']);
    }

    public function create($id, $driver) {
        $driver = (array) json_decode($driver);
        $configs = $this->encode($id, $driver);
        
        $this->http->post('drivers/'.$id, array('configs' => $configs));
        return array('success'=>true, 'message'=>'Driver successfully added');
    }

    public function get_list($depth=1) {
        $drivers = array();
        $devices = array();
        if ($depth > 0) {
            foreach ($this->device()->get_list() as $device) {
                if ($depth < 2) {
                    unset($device['channels']);
                }
                $devices[$device['id']] = $device;
            }
        }
        
        $result = $this->http->get('drivers', array('details' => 'true'));
        foreach($result['drivers'] as $driver) {
            $driver = $this->decode($driver);
            
            if ($depth == 0) {
                unset($driver['devices']);
            }
            else if (is_array($driver['devices'])) {
                $result = array();
                
                foreach($driver['devices'] as $device) {
                    $deviceid = is_string($device) ? $device : $device['id'];
                    if (isset($devices[$deviceid])) {
                        $result[] = $devices[$deviceid];
                    }
                }
                usort($result, function($c1, $c2) {
                    return strcmp($c1['id'], $c2['id']);
                });
                $driver['devices'] = $result;
            }
            $drivers[] = $driver;
        }
        usort($drivers, function($d1, $d2) {
            if($d1['id'] == $d2['id'])
                return $d1['ctrlid'] - $d2['ctrlid'];
            return strcmp($d1['id'], $d2['id']);
        });
        return $drivers;
    }

    public function get_registered() {
        $drivers = array();
        $result = $this->http->get('drivers/running');
        foreach($result['drivers'] as $driver) {
            $drivers[] = $this->decode($driver);
        }
        return $drivers;
    }

    public function get_unconfigured() {
        $drivers = array();
        $result = $this->http->get('drivers');
        $configs = $result['drivers'];
        
        $result = $this->get_registered();
        foreach($result as $driver) {
            if (!in_array($driver['id'], $configs)) {
                $drivers[] = $this->decode($driver);
            }
        }
        return $drivers;
    }

    public function is_configured($id) {
        $result = false;
        $drivers = $this->get_list();
        foreach ($drivers as $driver) {
            if ($driver['id'] == $id) $result = true;
        }
        return $result;
    }

    public function info($id) {
        $result = $this->http->get('drivers/'.$id.'/infos/options', array('filter' => 'driver'));
        return $result['infos'];
    }

    public function get($id) {
        $result = $this->http->get('drivers/'.$id, array('details' => 'true'));
        return $this->decode($result);
    }

    public function update($id, $details) {
        $details = (array) json_decode($details);
        $configs = $this->encode($id, $details);
        
        $this->http->put('drivers/'.$id.'/configs', array('configs' => $configs));
        return array('success'=>true, 'message'=>'Driver successfully updated');
    }

    public function delete($id) {
        $this->http->delete('drivers/'.$id);
        return array('success'=>true, 'message'=>'Driver successfully removed');
    }
}
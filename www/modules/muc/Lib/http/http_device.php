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

require_once "Modules/muc/Lib/muc_device.php";

class HttpDevice extends ControllerDevice {
    private static $cache = array();
    private $http;

    public function __construct($ctrl, $redis) {
        parent::__construct($ctrl, $redis);
        require_once "Modules/muc/Lib/http/http.php";
        $options = $ctrl['options'];
        $this->http = new Http($ctrl['type'] == 'https', $options['address'], $options['port'], $options['password']);
    }

    public function create($driverid, $device) {
        $device = (array) json_decode($device, true);
        
        $id = $device['id'];
        if (preg_replace('/[^\p{N}\p{L}\-\_\.\:\/]/u', '', $id) != $id) {
            return array('success'=>false, 'message'=>"Device key must only contain a-z A-Z 0-9 - _ . : and / characters");
        }
        
        // Check if the specified driver is registered already and add it, if necessary
        if (!$this->driver()->is_configured($driverid)) {
            $this->driver()->create($driverid, "{}");
        }
        
        $configs = $this->encode($id, $device);
        $data = array(
            'driver' => $driverid,
            'configs' => $configs
        );
        $this->http->post('devices/'.urlencode($id), $data);
        $device = $this->decode(array(
            'id' => $id,
            'driver' => array('id' => $driverid),
            'configs' => $configs,
            'state' => '',
            'channels' => array()
        ));
        if ($this->redis) {
            $this->add_redis($device);
        }
        return array('success'=>true, 'message'=>'Device successfully added', 'device'=>$device);
    }

    public function exists($id) {
        if (isset(self::$cache[$id])) {
            $exists = self::$cache[$id]; // Retrieve from static cache
        }
        else {
            $exists = false;
            if ($this->redis) {
                $exists = $this->redis->exists("muc#".$this->ctrl['id'].":device:".$id);
            }
            else {
                // Always return true if redis is not enabled
                return true;
            }
            self::$cache[$id] = $exists; // Cache it
        }
        return $exists;
    }

    public function load() {
        if (!$this->redis) {
            throw new ControllerException("Unable to load devices without redis installed");
        }
        $ctrlid = $this->ctrl['id'];
        
        // First, flush redis keys for the controller to reload
        foreach ($this->redis->sMembers("muc#$ctrlid:devices") as $id) {
            $this->redis->del("muc#$ctrlid:device:$id");
            $this->redis->srem("muc#$ctrlid:devices", $id);
        }
        foreach ($this->get_http_list() as $device) {
            $channels = array();
            if (is_array($device['channels'])) {
                foreach($device['channels'] as $channel) {
                    $channels[] = $channel['id'];
                }
            }
            $device['channels'] = $channels;
            $this->add_redis($device);
        }
    }

    public function get_list($depth=1) {
        if ($this->redis) {
            $devices = $this->get_redis_list();
        } else {
            $devices = $this->get_http_list();
        }
        $channels = array();
        if ($depth > 0) {
            foreach ($this->channel()->get_list() as $channel) {
                $channels[$channel['id']] = $channel;
            }
        }
        foreach($devices as &$device) {
            $result = array();
            if (is_array($device['channels'])) {
                foreach($device['channels'] as $channel) {
                    $channelid = is_string($channel) ? $channel : $channel['id'];
                    if (isset($channels[$channelid])) {
                        $result[] = $channels[$channelid];
                    }
                }
                usort($result, function($c1, $c2) {
                    return strcmp($c1['id'], $c2['id']);
                });
            }
            $device['channels'] = $result;
        }
        usort($devices, function($d1, $d2) {
            if($d1['id'] == $d2['id'])
                return $d1['ctrlid'] - $d2['ctrlid'];
            return strcmp($d1['id'], $d2['id']);
        });
        return $devices;
    }

    private function get_http_list() {
        $devices = array();
        $result = $this->http->get('devices', array('details' => 'true'));
        if (isset($result['devices'])) {
            foreach($result['devices'] as $device) {
                $devices[] = $this->decode($device);
            }
        }
        return $devices;
    }

    public function get_states() {
        $states = array();
        $result = $this->http->get('devices/states');
        if (isset($result['states'])) {
            foreach($result['states'] as $state) {
                $states[] = array(
                    'userid'=>$this->ctrl['userid'],
                    'ctrlid'=>$this->ctrl['id'],
                    'id'=>$state['id'],
                    'state'=>$state['state']
                );
            }
        }
        return $states;
    }

    public function info($driverid) {
        $result = $this->http->get('drivers/'.$driverid.'/infos/options', array('filter' => 'device'));
        return $result['infos'];
    }

    public function get($id) {
        if ($this->redis) {
            return $this->get_redis($id);
        } else {
            return $this->get_http($id);
        }
    }

    private function get_http($id) {
        $result = $this->http->get('devices/'.urlencode($id), array('details' => 'true'));
        return $this->decode($result);
    }

    public function scan_start($driverid, $settings) {
        // Check if the specified driver is registered already and add it, if necessary
        if (!$this->driver()->is_configured($driverid)) {
            $this->driver()->create($driverid, "{}");
        }
        $result = $this->http->get('drivers/'.urlencode($driverid).'/scanStart', array('settings' => $settings));
        return $this->parse_scan_progress($driverid, $result);
    }

    public function scan_progress($driverid) {
        $result = $this->http->get('drivers/'.urlencode($driverid).'/scanProgress', null);
        return $this->parse_scan_progress($driverid, $result);
    }

    private function parse_scan_progress($driverid, $result) {
        $meta = $result['scanProgressInfo'];
        if (isset($meta['scanError'])) {
            return array('success'=>false, 'message'=>$meta['scanError']);
        }
        $info = array(
            'finished'=>$meta['isScanFinished'],
            'interrupted'=>$meta['isScanInterrupted'],
            'progress'=>$meta['scanProgress']
        );
        
        $devices = array();
        foreach($result['devices'] as $scan) {
            $device = array(
                'ctrlid'=>$this->ctrl['id'],
                'driverid'=>$driverid,
                'id'=>$scan['id'],
                'description'=>'',
                'address'=>array(),
                'settings'=>array()
            );
            if (isset($scan['description'])) $device['description'] = $scan['description'];
            if (isset($scan['deviceAddress'])) $device['address'] = $scan['deviceAddress'];
            if (isset($scan['settings'])) $device['settings'] = $scan['settings'];
            
            $devices[] = $device;
        }
        return array('success'=>true, 'info'=>$info, 'devices'=>$devices);
    }

    public function scan_cancel($driverid) {
        return $this->http->put('drivers/'.$driverid.'/scanInterrupt');
    }

    public function update($id, $device) {
        $device = (array) json_decode($device, true);
        
        $name = $device['id'];
        if (preg_replace('/[^\p{N}\p{L}\-\_\.\:\/\s]/u', '', $name) != $name) {
            return array('success'=>false, 'message'=>"Device key must only contain a-z A-Z 0-9 - _ . : and / characters");
        }
        $configs = $this->encode($name, $device);
        $this->http->put('devices/'.urlencode($id).'/configs', array('configs' => $configs));
        
        if ($this->redis) {
            $this->update_redis($id, $device);
        }
        return array('success'=>true, 'message'=>'Device successfully updated');
    }

    public function delete($id) {
        $this->http->delete('devices/'.urlencode($id));
        
        if ($this->redis) {
            $this->remove_redis($id);
        }
        return array('success'=>true, 'message'=>'Device successfully removed');
    }
}
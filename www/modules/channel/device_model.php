<?php
/*
 Released under the GNU Affero General Public License.
 See COPYRIGHT.txt and LICENSE.txt.
 
 Device module contributed by Adrian Minde Adrian_Minde(at)live.de 2018
 ---------------------------------------------------------------------
 Sponsored by https://isc-konstanz.de/
 */

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

class DeviceCache {
    private $ctrl;
    private $device;
    private $channel;
    private $redis;
    private $log;

    public function __construct($ctrl, $device, $channel, $redis) {
        $this->ctrl = $ctrl;
        $this->device = $device;
        $this->channel = $channel;
        $this->redis = $redis;
        $this->log = new EmonLogger(__FILE__);
    }

    public function create($userid, $ctrlid, $driverid, $configs) {
        $userid = intval($userid);
        $ctrlid = intval($ctrlid);
        
        $result = $this->device->create($ctrlid, $driverid, $configs);
        if ($this->redis && isset($result['device'])) {
            $device = $result['device'];
            $id = $device['id'];
            
            $this->redis->sAdd("muc#$ctrlid:devices", $id);
            $this->redis->hMSet("muc#$ctrlid:device:$id", array(
                'id'=>$device['id'],
                'userid'=>$userid,
                'ctrlid'=>$ctrlid,
                'driverid'=>$device['driverid'],
                'driver'=>$device['driver'],
                'description'=>$device['description'],
                'address'=>$device['address'],
                'settings'=>$device['settings'],
                'configs'=>json_encode($device['configs']),
                'channels'=>json_encode($device['channels'])
            ));
        }
        return $result;
    }

    public function exist($ctrlid, $id) {
        static $device_exists_cache = array(); // Array to hold the cache
        if (isset($device_exists_cache[$id])) {
            $device_exist = $device_exists_cache[$id]; // Retrieve from static cache
        }
        else {
            $device_exist = false;
            if ($this->redis) {
                $device_exist = $this->redis->exists("muc#$ctrlid:device:$id");
            }
            else {
                // Always return true if redis is not enabled
                return true;
            }
            $device_exists_cache[$id] = $device_exist; // Cache it
        }
        return $device_exist;
    }

    public function load($userid) {
        $userid = intval($userid);
        
        if (!$this->redis) {
            return array('success'=>false, 'message'=>_("Unable to load devices without redis installed"));
        }
        foreach($this->ctrl->get_list($userid) as $ctrl) {
            $ctrlid = intval($ctrl['id']);
            
            // First, flush redis keys for the controller to reload
            foreach ($this->redis->sMembers("muc#$ctrlid:devices") as $id) {
                $this->redis->del("muc#$ctrlid:device:$id");
                $this->redis->srem("muc#$ctrlid:devices", $id);
            }
            
            // Get drivers of all registered MUCs and add identifying location description and parse their configuration
            $result = $this->ctrl->request($ctrlid, 'devices/details', 'GET', null);
            if (isset($result['success']) && $result['success'] == false) {
                return $result;
            }
            foreach($result['details'] as $details) {
                $device = $this->device->get_device($ctrl, $details);
                $id = $device['id'];
                
                $this->redis->sAdd("muc#$ctrlid:devices", $id);
                $this->redis->hMSet("muc#$ctrlid:device:$id", array(
                    'id'=>$device['id'],
                    'userid'=>$userid,
                    'ctrlid'=>$ctrlid,
                    'driverid'=>$device['driverid'],
                    'driver'=>$device['driver'],
                    'description'=>$device['description'],
                    'address'=>$device['address'],
                    'settings'=>$device['settings'],
                    'configs'=>json_encode($device['configs']),
                    'channels'=>json_encode($device['channels'])
                ));
            }
        }
        return array('success'=>true, 'message'=>_("Devices successfully loaded"));
    }

    public function get_list($userid) {
        $devices = array();
        $channels = array();
        $result = $this->channel->get_list($userid);
        if (isset($result['success']) && $result['success'] == false) {
            return $result;
        }
        foreach ($result as $channel) {
            $channels[$channel['id']] = $channel;
        }
        
        foreach($this->ctrl->get_list($userid) as $ctrl) {
            $ctrlid = intval($ctrl['id']);
            
            if ($this->redis) {
                foreach ($this->redis->sMembers("muc#$ctrlid:devices") as $id) {
                    $device = (array) $this->redis->hGetAll("muc#$ctrlid:device:$id");
                    $device['configs'] = json_decode($device['configs'], true);
                    $device['channels'] = json_decode($device['channels']);
                    
                    $devices[] = $device;
                }
            }
            else {
                $devices = $this->device->get_list($userid, $ctrlid);
            }
            foreach($devices as &$device) {
                $channelids = $device['channels'];
                sort($channelids);
                
                $device['channels'] = array();
                foreach($channelids as $id) {
                    $device['channels'][] = $channels[$id];
                }
            }
        }
        usort($devices, function($d1, $d2) {
            if($d1['id'] == $d2['id'])
                return $d1['ctrlid'] - $d2['ctrlid'];
            return strcmp($d1['id'], $d2['id']);
        });
        return $devices;
    }

    public function get($userid, $ctrlid, $id) {
        if (!$this->redis) {
            return $this->device->get($ctrlid, $id);
        }
        $device = (array) $this->redis->hGetAll("muc#$ctrlid:device:$id");
        $device['configs'] = json_decode($device['configs'], true);
        $device['channels'] = json_decode($device['channels']);
        
        return $device;
    }

    public function update($userid, $ctrlid, $id, $configs) {
        $userid = intval($userid);
        $ctrlid = intval($ctrlid);
        
        $result = $this->device->update($ctrlid, $id, $configs);
        if ($this->redis && isset($result['success']) && $result['success']) {
            $configs = (array) json_decode($configs);
            
            if (isset($configs['id'])) {
                $newid = $configs['id'];
            }
            else {
                $newid = $id;
            }
            
            if (empty($configs['driverid'])) {
                $configs = $this->device->get($ctrlid, $newid);
            }
            $driver = $configs['driverid'];
            
            if ($id != $newid) {
                $this->redis->del("muc#$ctrlid:device:$id");
                $this->redis->srem("muc#$ctrlid:devices", $id);
                
                $this->redis->sAdd("muc#$ctrlid:devices", $newid);
            }
            $this->redis->hMSet("muc#$ctrlid:device:$newid", array(
                'id'=>$newid,
                'userid'=>$userid,
                'ctrlid'=>$ctrlid,
                'driverid'=>$driver,
                'driver'=>isset($configs['driverName']) ? $configs['driverName'] : $configs['driver'],
                'description'=>isset($configs['description']) ? $configs['description'] : '',
                'address'=>isset($configs['address']) ? $configs['address'] : '',
                'settings'=>isset($configs['settings']) ? $configs['settings'] : '',
                'configs'=>json_encode(isset($configs['configs']) ? $configs['configs'] : new stdClass()),
                'channels'=>json_encode(isset($configs['channels']) ? $configs['channels'] : array()),
            ));
        }
        return $result;
    }

    public function delete($ctrlid, $id) {
        if ($this->redis) {
            $channelids = json_decode($this->redis->hget("muc#$ctrlid:device:$id",'channels'));
            foreach ($channelids as $channelid) {
                $this->redis->srem("muc#$ctrlid:channels", $channelid);
                $this->redis->del("muc#$ctrlid:channel:$channelid");
            }
            
            $this->redis->srem("muc#$ctrlid:devices", $id);
            $this->redis->del("muc#$ctrlid:device:$id");
        }
        return $this->device->delete($ctrlid, $id);;
    }
}

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

class ChannelCache {
    private $ctrl;
    private $channel;
    private $redis;
    private $log;

    public function __construct($ctrl, $channel, $redis) {
        $this->ctrl = $ctrl;
        $this->channel = $channel;
        $this->redis = $redis;
        $this->log = new EmonLogger(__FILE__);
    }

    public function create($userid, $ctrlid, $driverid, $deviceid, $configs) {
        $userid = intval($userid);
        $ctrlid = intval($ctrlid);
        
        $result = $this->channel->create($userid, $ctrlid, $driverid, $deviceid, $configs);
        if ($this->redis && isset($result["channel"])) {
            $channel = $result['channel'];
            $id = $channel['id'];
            
            $this->redis->sAdd("muc#$ctrlid:channels", $id);
            $this->redis->hMSet("muc#$ctrlid:channel:$id", array(
                'id'=>$id,
                'userid'=>$userid,
                'ctrlid'=>$ctrlid,
                'driverid'=>$driverid,
                'deviceid'=>$deviceid,
                'nodeid'=>$channel['nodeid'],
                'description'=>$channel['description'],
                'address'=>$channel['address'],
                'settings'=>$channel['settings'],
                'logging'=>json_encode($channel['logging']),
                'configs'=>json_encode($channel['configs'])
            ));
            
            $channels = json_decode($this->redis->hget("muc#$ctrlid:device:$deviceid",'channels'));
            $channels[] = $id;
            
            $this->redis->hset("muc#$ctrlid:device:$deviceid",'channels', json_encode($channels));
        }
        return $result;
    }

    public function exist($ctrlid, $id) {
        static $channel_exists_cache = array(); // Array to hold the cache
        if (isset($channel_exists_cache[$id])) {
            $channel_exist = $channel_exists_cache[$id]; // Retrieve from static cache
        }
        else {
            $channel_exist = false;
            if ($this->redis) {
                $channel_exist = $this->redis->exists("muc#$ctrlid:channel:$id");
            }
            else {
                // Always return true if redis is not enabled
                return true;
            }
            $channel_exists_cache[$id] = $channel_exist; // Cache it
        }
        return $channel_exist;
    }

    public function load($userid) {
        $userid = intval($userid);
        
        if (!$this->redis) {
            return array('success'=>false, 'message'=>_("Unable to load channels without redis installed"));
        }
        foreach($this->ctrl->get_list($userid) as $ctrl) {
            $ctrlid = intval($ctrl['id']);
            
            // First, flush redis keys for the controller to reload
            foreach ($this->redis->sMembers("muc#$ctrlid:channels") as $id) {
                $this->redis->del("muc#$ctrlid:channel:$id");
                $this->redis->srem("muc#$ctrlid:channels", $id);
            }
            
            // Get drivers of all registered MUCs and add identifying location description and parse their configuration
            $result = $this->ctrl->request($ctrlid, 'channels/details', 'GET', null);
            if (isset($result['success']) && $result['success'] == false) {
                return $result;
            }
            foreach($result['details'] as $details) {
                $channel = $this->channel->get_channel($ctrl, $details);
                $id = $channel['id'];
                
                $this->redis->sAdd("muc#$ctrlid:channels", $id);
                $this->redis->hMSet("muc#$ctrlid:channel:$id", array(
                    'id'=>$id,
                    'userid'=>$userid,
                    'ctrlid'=>$ctrlid,
                    'driverid'=>$channel['driverid'],
                    'deviceid'=>$channel['deviceid'],
                    'nodeid'=>$channel['nodeid'],
                    'description'=>$channel['description'],
                    'address'=>$channel['address'],
                    'settings'=>$channel['settings'],
                    'logging'=>json_encode($channel['logging']),
                    'configs'=>json_encode($channel['configs'])
                ));
            }
        }
        return array('success'=>true, 'message'=>_("Channels successfully loaded"));
    }

    public function get_list($userid) {
        if (!$this->redis) {
            return $this->channel->get_list($userid, null);
        }
        $channels = array();
        
        foreach($this->ctrl->get_list($userid) as $ctrl) {
            $ctrlid = intval($ctrl['id']);
            
            foreach ($this->redis->sMembers("muc#$ctrlid:channels") as $id) {
                $channel = (array) $this->redis->hGetAll("muc#$ctrlid:channel:$id");
                $channel['logging'] = json_decode($channel['logging'], true);
                $channel['configs'] = json_decode($channel['configs'], true);
                
                $channels[] = $channel;
            }
        }
        usort($channels, function($c1, $c2) {
            if($c1['deviceid'] == $c2['deviceid'])
                return strcmp($c1['id'], $c2['id']);
                return strcmp($c1['deviceid'], $c2['deviceid']);
        });
        return $channels;
    }

    public function get($ctrlid, $id) {
        if (!$this->redis) {
            return $this->channel->get($ctrlid, $id);
        }
        $channel = (array) $this->redis->hGetAll("muc#$ctrlid:channel:$id");
        $channel['logging'] = json_decode($channel['logging'], true);
        $channel['configs'] = json_decode($channel['configs'], true);
        
        return $channel;
    }

    public function update($userid, $ctrlid, $nodeid, $id, $configs) {
        $userid = intval($userid);
        $ctrlid = intval($ctrlid);
        
        $result = $this->channel->update($userid, $ctrlid, $nodeid, $id, $configs);
        if ($this->redis && isset($result['success']) && $result['success']) {
            $configs = (array) json_decode($configs);
            
            if (isset($configs['logging'])) {
                $logging = (array) $configs['logging'];
                $newnode = $logging['nodeid'];
            }
            else {
                $newnode = $nodeid;
            }
            
            if (isset($configs['id'])) {
                $newid = $configs['id'];
            }
            else {
                $newid = $id;
            }
            
            if (empty($configs['driverid']) || empty($configs['deviceid'])) {
                $configs = $this->channel->get($ctrlid, $newid);
            }
            $driver = $configs['driverid'];
            $device = $configs['deviceid'];
            
            if ($id != $newid) {
                $this->redis->del("muc#$ctrlid:channel:$id");
                $this->redis->srem("muc#$ctrlid:channels", $id);
                
                $this->redis->sAdd("muc#$ctrlid:channels", $newid);
            }
            $this->redis->hMSet("muc#$ctrlid:channel:$newid", array(
                'id'=>$newid,
                'userid'=>$userid,
                'ctrlid'=>$ctrlid,
                'driverid'=>$driver,
                'deviceid'=>$device,
                'nodeid'=>$newnode,
                'description'=>isset($configs['description']) ? $configs['description'] : '',
                'address'=>isset($configs['address']) ? $configs['address'] : '',
                'settings'=>isset($configs['settings']) ? $configs['settings'] : '',
                'logging'=>json_encode(isset($configs['logging']) ? $configs['logging'] : new stdClass()),
                'configs'=>json_encode(isset($configs['configs']) ? $configs['configs'] : new stdClass())
            ));
        }
        return $result;
    }

    public function delete($ctrlid, $id) {
        if ($this->redis) {
            $channel = $this->get($ctrlid, $id);
            $channels = json_decode($this->redis->hget("muc#$ctrlid:device:".$channel['deviceid'],'channels'));
            $index = array_search($id, $channels);
            if($index !== false) {
                unset($channels[$index]);
            }
            $this->redis->hset("muc#$ctrlid:device:".$channel['deviceid'],'channels', json_encode($channels));
            
            $this->redis->srem("muc#$ctrlid:channels", $id);
            $this->redis->del("muc#$ctrlid:channel:$id");
        }
        return $this->channel->delete($ctrlid, $id);
    }

}

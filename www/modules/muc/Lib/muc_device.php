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

abstract class ControllerDevice {
    protected $channel = false;
    protected $driver = false;
    protected $ctrl;
    protected $log;

    public function __construct($ctrl, $redis) {
        $this->ctrl = $ctrl;
        $this->redis = $redis;
        $this->log = new EmonLogger(__FILE__);
    }

    public static function build($ctrl, $redis=false): ControllerDevice {
        $type = strtolower($ctrl['type']);
        if ($type === 'redis') {
            throw new ControllerException("Redis controller communication not implemented yet");
        }
        elseif ($type === 'http' || $type === 'https') {
            require_once "Modules/muc/Lib/http/http_device.php";
            return new HttpDevice($ctrl, $redis);
        }
        throw new ControllerException("Unknown controller type: $type");
    }

    protected function driver(): ControllerDriver {
        if (!$this->driver) {
            require_once "Modules/muc/Lib/muc_driver.php";
            $this->driver = ControllerDriver::build($this->ctrl);
        }
        return $this->driver;
    }

    protected function channel(): ControllerChannel {
        if (!$this->channel) {
            global $mysqli;
            require_once "Modules/muc/Lib/muc_channel.php";
            $this->channel = ControllerChannel::build($this->ctrl, $mysqli, $this->redis);
        }
        return $this->channel;
    }

    public abstract function create($driverid, $device);

    public abstract function exists($id);

    public abstract function load();

    public abstract function get_list();

    public abstract function get_states();

    public abstract function info($driverid);

    public abstract function get($id);

    public abstract function update($id, $device);

    public abstract function delete($id);

    public abstract function scan_start($driverid, $settings);

    public abstract function scan_progress($driverid);

    public abstract function scan_cancel($driverid);

    protected function add_redis($device) {
        $this->redis->sAdd("muc#".$device['ctrlid'].":devices", $device['id']);
        $this->redis->hMSet("muc#".$device['ctrlid'].":device:".$device['id'], array(
            'id'=>$device['id'],
            'userid'=>$device['userid'],
            'ctrlid'=>$device['ctrlid'],
            'driverid'=>$device['driverid'],
            'driver'=>$device['driver'],
            'description'=>$device['description'],
            'address'=>$device['address'],
            'settings'=>$device['settings'],
            'configs'=>json_encode($device['configs']),
            'channels'=>json_encode($device['channels']),
            'disabled'=>$device['disabled']
        ));
    }

    protected function get_redis($id) {
        $device = (array) $this->redis->hGetAll("muc#".$this->ctrl['id'].":device:$id");
        $device['configs'] = json_decode($device['configs'], true);
        $device['channels'] = json_decode($device['channels'], true);
        $device['state'] = 'LOADING';
        
        return $device;
    }

    protected function get_redis_list() {
        $devices = array();
        foreach ($this->redis->sMembers("muc#".$this->ctrl['id'].":devices") as $id) {
            $devices[] = $this->get_redis($id);
        }
        return $devices;
    }

    protected function update_redis($id, $device) {
        $userid = $this->ctrl['userid'];
        $ctrlid = $this->ctrl['id'];
        
        if (isset($device['id'])) {
            $newid = $device['id'];
        }
        else {
            $newid = $id;
        }
        if (empty($device['driverid'])) {
            $result = $this->get_redis($newid);
            $device['driverid'] = $result['driverid'];
            $device['driver'] = $result['driver'];
        }
        $driver = $device['driverid'];
        
        $channels = array();
        if (isset($device['channels'])) {
            foreach ($device['channels'] as $channel) {
                $channels[] = is_string($channel) ? $channel : $channel['id'];
            }
        }
        else {
            $channels = json_decode($this->redis->hget("muc#$ctrlid:device:$id",'channels'));
        }
        
        if ($id != $newid) {
            $this->redis->del("muc#$ctrlid:device:$id");
            $this->redis->srem("muc#$ctrlid:devices", $id);
            $this->redis->sAdd("muc#$ctrlid:devices", $newid);
            
            foreach ($channels as $channelid) {
                $this->redis->hset("muc#$ctrlid:channel:$channelid",'deviceid', $newid);
            }
        }
        $this->redis->hMSet("muc#$ctrlid:device:$newid", array(
            'id'=>$newid,
            'userid'=>$userid,
            'ctrlid'=>$ctrlid,
            'driverid'=>$driver,
            'driver'=>isset($device['driverName']) ? $device['driverName'] : $device['driver'],
            'description'=>isset($device['description']) ? $device['description'] : '',
            'address'=>isset($device['address']) ? $device['address'] : '',
            'settings'=>isset($device['settings']) ? $device['settings'] : '',
            'configs'=>json_encode(isset($device['configs']) ? $device['configs'] : new stdClass()),
            'channels'=>json_encode($channels),
            'disabled'=>$device['disabled']
        ));
    }

    protected function remove_redis($id) {
        $ctrlid = $this->ctrl['id'];
        $channels = json_decode($this->redis->hget("muc#$ctrlid:device:$id",'channels'), true);
        foreach ($channels as $channelid) {
            $this->redis->srem("muc#$ctrlid:channels", $channelid);
            $this->redis->del("muc#$ctrlid:channel:$channelid");
        }
        $this->redis->srem("muc#$ctrlid:devices", $id);
        $this->redis->del("muc#$ctrlid:device:$id");
    }

    protected function decode($details) {
        $configs = $details['configs'];
        
        $device = array(
            'id'=>$details['id'],
            'userid'=>$this->ctrl['userid'],
            'ctrlid'=>$this->ctrl['id']
        );
        $driver = $details['driver'];
        $device['driverid'] = $driver['id'];
        $device['driver'] = isset($driver['name']) ? $driver['name'] : $driver['id'];
        
        if (isset($configs['description'])) {
            $device['description'] = $configs['description'];
            unset($configs['description']);
        }
        else {
            $device['description'] = '';
        }
        
        if (isset($configs['deviceAddress'])) {
            $device['address'] = $configs['deviceAddress'];
            unset($configs['deviceAddress']);
        }
        else {
            $device['address'] = '';
        }
        if (isset($configs['settings'])) {
            $device['settings'] = $configs['settings'];
            unset($configs['settings']);
        }
        else {
            $device['settings'] = '';
        }
        
        $disabled = false;
        if (isset($configs['disabled'])) {
            $disabled = $configs['disabled'];
            unset($configs['disabled']);
        }
        if (empty($configs)) {
            $configs = new stdClass();
        }
        $device['configs'] = $configs;
        
        $device['channels'] = isset($details['records']) ? $details['records'] : array();
        $device['state'] = $details['state'];
        $device['disabled'] = $disabled;
        
        return $device;
    }

    protected function encode($id, $device) {
        $configs = array(
            'id' => $id
        );
        if (isset($device['description'])) $configs['description'] = $device['description'];
        if (isset($device['address'])) $configs['deviceAddress'] = $device['address'];
        if (isset($device['settings'])) $configs['settings'] = $device['settings'];
        if (isset($device['configs'])) $configs = array_merge($configs, $device['configs']);
        if (isset($device['disabled'])) $configs['disabled'] = $device['disabled'];
        
        return $configs;
    }

}
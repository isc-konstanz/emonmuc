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

abstract class ControllerChannel {
    protected $feed = false;
    protected $input = false;
    protected $ctrl;
    protected $mysqli;
    protected $redis;
    protected $log;

    public function __construct($ctrl, $mysqli, $redis) {
        $this->ctrl = $ctrl;
        $this->mysqli = $mysqli;
        $this->redis = $redis;
        $this->log = new EmonLogger(__FILE__);
    }

    public static function build($ctrl, $mysqli, $redis=false): ControllerChannel {
        $type = strtolower($ctrl['type']);
        if ($type === 'redis') {
            throw new ControllerException("Redis controller communication not implemented yet");
        }
        elseif ($type === 'http' || $type === 'https') {
            require_once "Modules/muc/Lib/http/http_channel.php";
            return new HttpChannel($ctrl, $mysqli, $redis);
        }
        throw new ControllerException("Unknown controller type: $type");
    }

    protected function feed(): Feed {
        if (!$this->feed) {
            global $settings;
            require_once "Modules/feed/feed_model.php";
            $this->feed = new Feed($this->mysqli, $this->redis, $settings["feed"]);
        }
        return $this->feed;
    }

    protected function input(): Input {
        if (!$this->input) {
            require_once "Modules/input/input_model.php";
            $this->input = new Input($this->mysqli, $this->redis, null);
        }
        return $this->input;
    }

    public abstract function create($driverid, $deviceid, $channel);

    public abstract function exists($id);

    public abstract function load();

    public abstract function get_list();

    public abstract function get_states();

    public abstract function get_records();

    public abstract function info($driverid);

    public abstract function get($id);

    public abstract function update($id, $nodeid, $configs);

    public abstract function write($id, $value, $valueType);

    public abstract function set($id, $value, $valueType);

    public abstract function delete($id);

    public abstract function scan($driverid, $deviceid, $settings);

    protected function get_input($userid, $nodeid, $name) {
        $result = $this->mysqli->query("SELECT id, nodeid, name, description, processList FROM input WHERE nodeid = '$nodeid' AND name = '$name'");
        if ($result->num_rows == 0) {
            return null;
        }
        return (array) $result->fetch_object();
    }

    protected function load_redis_input($id) {
        $result = $this->mysqli->query("SELECT id, nodeid, name, description, processList FROM input WHERE id = '$id'");
        if ($result->num_rows > 0) {
            $row = (array) $result->fetch_object();
            $this->redis->hMSet("input:$id",array(
                'id'=>$id,
                'nodeid'=>$row['nodeid'],
                'name'=>$row['name'],
                'description'=>$row['description'],
                'processList'=>$row['processList']
            ));
            
            return true;
        }
        return false;
    }

    protected function add_redis($channel) {
        $this->redis->sAdd("muc#".$channel['ctrlid'].":channels", $channel['id']);
        $this->redis->hMSet("muc#".$channel['ctrlid'].":channel:".$channel['id'], array(
            'id'=>$channel['id'],
            'userid'=>$channel['userid'],
            'ctrlid'=>$channel['ctrlid'],
            'driverid'=>$channel['driverid'],
            'deviceid'=>$channel['deviceid'],
            'nodeid'=>$channel['nodeid'],
            'description'=>$channel['description'],
            'address'=>$channel['address'],
            'settings'=>$channel['settings'],
            'logging'=>json_encode($channel['logging']),
            'configs'=>json_encode($channel['configs']),
            'disabled'=>$channel['disabled']
        ));
        $channels = json_decode($this->redis->hget("muc#".$channel['ctrlid'].":device:".$channel['deviceid'],'channels'));
        if (!in_array($channel['id'], $channels)) {
            $channels[] = $channel['id'];
            $this->redis->hset("muc#".$channel['ctrlid'].":device:".$channel['deviceid'],'channels', json_encode($channels));
        }
    }

    protected function get_redis($id) {
        $channel = (array) $this->redis->hGetAll("muc#".$this->ctrl['id'].":channel:$id");
        $channel['logging'] = json_decode($channel['logging'], true);
        $channel['configs'] = json_decode($channel['configs'], true);
        $channel['state'] = 'LOADING';
        $channel['flag'] = 'LOADING';
        
        return $channel;
    }

    protected function get_redis_list() {
        $channels = array();
        foreach ($this->redis->sMembers("muc#".$this->ctrl['id'].":channels") as $id) {
            $channels[] = $this->get_redis($id);
        }
        return $channels;
    }

    protected function update_redis($id, $nodeid, $channel) {
        $userid = $this->ctrl['userid'];
        $ctrlid = $this->ctrl['id'];
        
        if (isset($channel['logging'])) {
            $logging = (array) $channel['logging'];
            $newnode = $logging['nodeid'];
        }
        else {
            $newnode = $nodeid;
        }
        
        if (isset($channel['id'])) {
            $newid = $channel['id'];
        }
        else {
            $newid = $id;
        }
        
        if (!isset($channel['disabled']) || empty($channel['deviceid']) || empty($channel['driverid'])) {
            $result = $this->get_redis($id);
            
            if (!isset($channel['disabled'])) $channel['disabled'] = $result['disabled'];
            if (empty($channel['driverid'])) $channel['driverid'] = $result['driverid'];
            if (empty($channel['deviceid'])) $channel['deviceid'] = $result['deviceid'];
        }
        $driver = $channel['driverid'];
        $device = $channel['deviceid'];
        
        if ($id != $newid) {
            $this->redis->del("muc#$ctrlid:channel:$id");
            $this->redis->srem("muc#$ctrlid:channels", $id);
            $this->redis->sAdd("muc#$ctrlid:channels", $newid);
            
            $channels = json_decode($this->redis->hget("muc#$ctrlid:device:".$device,'channels'), true);
            $index = array_search($id, $channels);
            if($index !== false) {
                unset($channels[$index]);
            }
            $channels[] = $newid;
            $this->redis->hset("muc#$ctrlid:device:".$device,'channels', json_encode($channels));
        }
        $this->redis->hMSet("muc#$ctrlid:channel:$newid", array(
            'id'=>$newid,
            'userid'=>$userid,
            'ctrlid'=>$ctrlid,
            'driverid'=>$driver,
            'deviceid'=>$device,
            'nodeid'=>$newnode,
            'description'=>isset($channel['description']) ? $channel['description'] : '',
            'address'=>isset($channel['address']) ? $channel['address'] : '',
            'settings'=>isset($channel['settings']) ? $channel['settings'] : '',
            'logging'=>json_encode(isset($channel['logging']) ? $channel['logging'] : new stdClass()),
            'configs'=>json_encode(isset($channel['configs']) ? $channel['configs'] : new stdClass()),
            'channels'=>json_encode(isset($channel['channels']) ? $channel['channels'] : new stdClass()),
            'disabled'=>$channel['disabled']
        ));
    }

    protected function remove_redis($id) {
        $ctrlid = $this->ctrl['id'];
        $deviceid = $this->redis->hget("muc#$ctrlid:channel:$id", 'deviceid');
        
        $channels = json_decode($this->redis->hget("muc#$ctrlid:device:$deviceid", 'channels'));
        $index = array_search($id, $channels);
        if($index !== false) {
            unset($channels[$index]);
        }
        $this->redis->hset("muc#$ctrlid:device:$deviceid",'channels', json_encode($channels));
        
        $this->redis->srem("muc#$ctrlid:channels", $id);
        $this->redis->del("muc#$ctrlid:channel:$id");
    }

    protected function decode($details) {
        $configs = (array) $details['configs'];
        $logging = array();
        if (isset($configs)) {
            if (isset($configs['loggingInterval'])) {
                $logging['loggingInterval'] = $configs['loggingInterval']; unset($configs['loggingInterval']);
            }
            if (isset($configs['loggingDelayMaximum'])) {
                $logging['loggingDelayMaximum'] = $configs['loggingDelayMaximum']; unset($configs['loggingDelayMaximum']);
            }
            if (isset($configs['loggingTimeOffset'])) {
                $logging['loggingTimeOffset'] = $configs['loggingTimeOffset']; unset($configs['loggingTimeOffset']);
            }
            if (isset($configs['loggingTolerance'])) {
                $logging['loggingTolerance'] = $configs['loggingTolerance']; unset($configs['loggingTolerance']);
            }
            if (isset($configs['loggingAverage'])) {
                $logging['loggingAverage'] = $configs['loggingAverage']; unset($configs['loggingAverage']);
            }
            if (isset($configs['loggingEvent'])) {
                $logging['loggingEvent'] = $configs['loggingEvent']; unset($configs['loggingEvent']);
            }
            if(isset($configs['loggingSettings'])) {
                $str = str_replace('emonlogger:', '', $configs['loggingSettings']);
                if (strpos($str, '=') !== false) {
                    $parameters = explode(',', $str);
                    foreach ($parameters as $parameter) {
                        $keyvalue = explode('=', $parameter);
                        $logging[$keyvalue[0]] = $keyvalue[1];
                    }
                }
                unset($configs['loggingSettings']);
            }
        }
        
        $channel = array(
            'id'=>$details['id'],
            'userid'=>$this->ctrl['userid'],
            'ctrlid'=>$this->ctrl['id'],
            'driverid'=>$details['driver'],
            'deviceid'=>$details['device'],
            'nodeid'=>isset($logging['nodeid']) ? $logging['nodeid'] : ''
        );
        if (isset($configs['description'])) {
            $channel['description'] = $configs['description'];
            
            unset($configs['description']);
        }
        else {
            $channel['description'] = '';
        }
        
        if (isset($configs['address'])) {
            $channel['address'] = $configs['address']; unset($configs['address']);
        }
        else {
            $channel['address'] = '';
        }
        if (isset($configs['settings'])) {
            $channel['settings'] = $configs['settings']; unset($configs['settings']);
        }
        else {
            $channel['settings'] = '';
        }
        if (empty($logging)) {
            $logging = new stdClass();
        }
        $channel['logging'] = $logging;
        
        $disabled = false;
        if (isset($configs['disabled'])) {
            $disabled = $configs['disabled'];
            
            unset($configs['disabled']);
        }
        if (empty($configs)) {
            $configs = new stdClass();
        }
        $channel['configs'] = $configs;
        
        $record = $details['record'];
        $channel['time'] = isset($record['timestamp']) ? $record['timestamp'] : null; // round($record['timestamp']/1000) : null;
        $channel['value'] = isset($record['value']) ? $record['value'] : null;
        $channel['flag'] = $record['flag'];
        $channel['state'] = $details['state'];
        
        $channel['disabled'] = $disabled;
        
        return $channel;
    }

    protected function decode_state($details) {
        return array(
            'userid'=>$this->ctrl['userid'],
            'ctrlid'=>$this->ctrl['id'],
            'id'=>$details['id'],
            'state'=>$details['state']
        );
    }

    protected function decode_record($details) {
        $type = isset($details['valueType']) ? $details['valueType'] : 'DOUBLE';
        $record = $details['record'];
        
        return array(
            'userid'=>$this->ctrl['userid'],
            'ctrlid'=>$this->ctrl['id'],
            'id'=>$details['id'],
            'time'=>isset($record['timestamp']) ? $record['timestamp'] : null, // round($record['timestamp']/1000) : null,
            'value'=>isset($record['value']) ? $record['value'] : null,
            'flag'=>$record['flag'],
            'configs'=>array('valueType'=>$type),
        );
    }

    protected function decode_logging($userid, $nodeid, $logging) {
        $engine = isset($logging['engine']) ? $logging['engine'] : 'HTTP';
        $settings = array(
            'engine' => $engine
        );
        if ($engine == 'HTTP') {
            $settings['api'] = isset($logging['authorization']) ? $logging['authorization'] : 'DEFAULT';
            // TODO: check if device for authid exists and fetch devicekey
            switch ($settings['api']) {
                case 'WRITE':
                    global $user;
                    
                    $settings['apikey'] = $user->get_apikey_write($userid);
                    break;
                case 'READ':
                    global $user;
                    
                    $settings['apikey'] = $user->get_apikey_read($userid);
                    break;
                default:
                    break;
            }
        }
        if (isset($nodeid)) $settings['nodeid'] = $nodeid;
        
        if (isset($logging['inputid'])) $settings['inputid'] = $logging['inputid'];
        if (isset($logging['feedid'])) $settings['feedid'] = $logging['feedid'];
        
        return $settings;
    }

    protected function decode_infos($info) {
        $times = array(
            '0'=>'None',
            '1000'=>'1 second',
            '2000'=>'2 seconds',
            '3000'=>'3 seconds',
            '4000'=>'4 seconds',
            '5000'=>'5 seconds',
            '10000'=>'10 seconds',
            '15000'=>'15 seconds',
            '20000'=>'20 seconds',
            '25000'=>'25 seconds',
            '30000'=>'30 seconds',
            '35000'=>'35 seconds',
            '40000'=>'40 seconds',
            '45000'=>'45 seconds',
            '50000'=>'50 seconds',
            '55000'=>'55 seconds',
            '60000'=>'1 minute',
            '120000'=>'2 minutes',
            '180000'=>'3 minutes',
            '240000'=>'4 minutes',
            '300000'=>'5 minutes',
            '600000'=>'10 minutes',
            '900000'=>'15 minutes',
            '1800000'=>'30 minutes',
            '2700000'=>'45 minutes',
            '3600000'=>'1 hour',
            '86400000'=>'1 day'
        );
        $configs = array('options'=>array());
        $logging = array('options'=>array());
        foreach($info['configs']['options'] as $option) {
            if ($option['id'] == 'loggingInterval' ||
                $option['id'] == 'loggingDelayMaximum' ||
                $option['id'] == 'loggingTimeOffset' ||
                $option['id'] == 'loggingTolerance' ||
                $option['id'] == 'loggingAverage' ||
                $option['id'] == 'loggingEvent') {
                
                if ($option['id'] == 'loggingInterval' ||
                    $option['id'] == 'loggingDelayMaximum') {
                    
                    $option['valueSelection'] = $times;
                }
                $option['name'] = str_replace('Logging', 'Post', $option['name']);
                if (isset($option['description'])) {
                    $option['description'] = str_replace('logging', 'posting', $option['description']);
                    $option['description'] = str_replace('logged', 'posted', $option['description']);
                }
                $logging['options'][] = $option;
            }
            else {
                $configs['options'][] = $option;
            }
        }
        $logging['options'][] = array(
            'id'=>'nodeid',
            'name'=>'Node',
            'description'=>'The node to post channel records to.',
            'type'=>'STRING',
            'mandatory'=>false
        );
        $inputs = array();
        foreach ($this->input()->get_list($this->ctrl['userid']) as $input) {
            $inputs[$input['id']] = $input['nodeid'].': '.$input['name'];
        }
        $logging['options'][] = array(
            'id'=>'inputid',
            'name'=>'Input',
            'description'=>'The input, corresponding to the channel.',
            'type'=>'INTEGER',
            'mandatory'=>false,
            'valueSelection'=>$inputs
        );
        $feeds = array();
        foreach ($this->feed()->get_user_feeds($this->ctrl['userid']) as $feed) {
            $feeds[$feed['id']] = $feed['tag'].": ".$feed['name'];
        }
        $logging['options'][] = array(
            'id'=>'feedid',
            'name'=>'Feed',
            'description'=>'The feed in which the channels values were persistently logged.',
            'type'=>'INTEGER',
            'mandatory'=>false,
            'valueSelection'=>$feeds
        );
        $logging['options'][] = array(
            'id'=>'authorization',
            'name'=>'Authorization',
            'description'=>'The authorization of the channel to post or read values.',
            'type'=>'STRING',
            'mandatory'=>false,
            'valueDefault'=>'DEFAULT',
            'valueSelection'=>array(
                'DEFAULT'=>'Default',
                'DEVICE'=>'Device',
                'WRITE'=>'Write',
                'READ'=>'Read',
                'NONE'=>'None'
            )
        );
        $logging['options'][] = array(
            'id'=>'engine',
            'name'=>'Engine',
            'description'=>'The engine used to post sampled values.',
            'type'=>'STRING',
            'mandatory'=>false,
            'valueDefault'=>'HTTP',
            'valueSelection'=>array(
                'HTTP'=>'HTTP',
                'MQTT'=>'MQTT',
                'SQL'=>'SQL'
            )
        );
        $info['logging'] = $logging;
        $info['configs'] = $configs;
        
        return $info;
    }

    protected function encode_value($value, $valueType) {
        // Make sure to encode the value parameter in the correct format,
        // depending on its valueType
        if (strtolower($valueType) === 'boolean') {
            if (is_bool($value) === false) {
                switch (strtolower($value)) {
                    case 'true':
                        $value = True;
                        break;
                    case 'false':
                        $value = False;
                        break;
                    default:
                        throw new ControllerException("Unknown boolean value: $value");
                }
            }
        }
        else if (is_numeric($value)) {
            $value = floatval($value);
        }
        else {
            throw new ControllerException("Value inconsistend with its type $valueType: $value");
        }
        return $value;
    }

    protected function encode($id, $description, $channel) {
        $configs = array(
            'id' => $id
        );
        if ($description != '') $configs['description'] = $description;
        
        if (isset($channel['address'])) $configs['address'] = $channel['address'];
        if (isset($channel['settings'])) $configs['settings'] = $channel['settings'];
        if (!empty($channel['logging'])) {
            $logging = (array) $channel['logging'];
            $logging_enabled = (isset($logging['loggingInterval']) && $logging['loggingInterval'] > 0) ||
                               (isset($logging['loggingEvent']) && $logging['loggingEvent']);
            
            if (isset($logging['loggingInterval'])) {
                $configs['loggingInterval'] = $logging['loggingInterval']; unset($logging['loggingInterval']);
            }
            if (isset($logging['loggingMaxInterval'])) {
                $configs['loggingDelayMaximum'] = $logging['loggingMaxInterval']; unset($logging['loggingMaxInterval']);
            }
            if (isset($logging['loggingDelayMaximum'])) {
                $configs['loggingDelayMaximum'] = $logging['loggingDelayMaximum']; unset($logging['loggingDelayMaximum']);
            }
            if (isset($logging['loggingTimeOffset'])) {
                $configs['loggingTimeOffset'] = $logging['loggingTimeOffset']; unset($logging['loggingTimeOffset']);
            }
            if (isset($logging['loggingTolerance'])) {
                $configs['loggingTolerance'] = $logging['loggingTolerance']; unset($logging['loggingTolerance']);
            }
            if (isset($logging['tolerance'])) {
                $configs['loggingTolerance'] = $logging['tolerance']; unset($logging['tolerance']);
            }
            if (isset($logging['loggingAverage'])) {
                $configs['loggingAverage'] = $logging['loggingAverage']; unset($logging['loggingAverage']);
            }
            if (isset($logging['average'])) {
                $configs['loggingAverage'] = $logging['average']; unset($logging['average']);
            }
            if (isset($logging['loggingEvent'])) {
                $configs['loggingEvent'] = $logging['loggingEvent']; unset($logging['loggingEvent']);
            }
            if (!empty($logging) && $logging_enabled) {
                $configs['loggingSettings'] = $this->encode_logging($logging);
            }
        }
        if (isset($channel['configs'])) $configs = array_merge($configs, $channel['configs']);
        if (isset($channel['disabled'])) $configs['disabled'] = $channel['disabled'];
        
        return $configs;
    }

    protected function encode_logging($settings) {
        $arr = array();
        foreach ($settings as $key=>$value) {
            if (is_bool($value)) {
                $value = ($value) ? 'true' : 'false';
            }
            else if (is_numeric($value)) {
                $value = str_replace(',', '.', strval($value));
            }
            $arr[] = $key.'='.$value;
        }
        return 'emonlogger:'.implode(",", $arr);
    }
}

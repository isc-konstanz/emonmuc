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

class Channel
{
    private $input;
    private $ctrl;
    private $mysqli;
    private $redis;
    private $log;

    public function __construct($ctrl, $mysqli, $redis) {
        require_once "Modules/input/input_model.php";
        $this->input = new Input($mysqli,$redis,null);

        $this->ctrl = $ctrl;
        $this->mysqli = $mysqli;
        $this->redis = $redis;
        $this->log = new EmonLogger(__FILE__);
    }

    public function create($userid, $ctrlid, $driverid, $deviceid, $channel) {
        $userid = intval($userid);
        $ctrlid = intval($ctrlid);
        
        $channel = (array) json_decode($channel, true);
        
        $id = $channel['id'];
        if (preg_replace('/[^\p{N}\p{L}\-\_\.\:\/]/u', '', $id) != $id) {
            return array('success'=>false, 'message'=>"Channel key must only contain a-z A-Z 0-9 - _ . : and / characters");
        }
        
        if (isset($channel['logging'])) {
            $logging = (array) $channel['logging'];
        }
        else if (isset($channel['nodeid'])) {
            $logging = array('nodeid' => $channel['nodeid']);
        }
        $nodeid = $logging['nodeid'];
        if (preg_replace('/[^\p{N}\p{L}\-\_\.\:\/]/u', '', $nodeid) != $nodeid) {
            return array('success'=>false, 'message'=>"Channel node must only contain a-z A-Z 0-9 - _ . : and / characters");
        }
        
        if (!empty($channel['description'])) {
            $description = $channel['description'];
        }
        else {
            $description = '';
        }
        
        $logging = $this->parse_log_settings($userid, $nodeid, $logging);
        
        $inputid = 0;
        $input = $this->get_input_by_node_name($userid, $logging['nodeid'], $id);
        if (isset($input)) {
            $inputid = $input['id'];
        }
        else if (isset($logging['loggingInterval'])) {
            $inputid = $this->input->create_input($userid, $nodeid, $id);
            if ($inputid < 0) {
                return array('success'=>false, 'message'=>_("Unable to create input for channel: $id"));
            }
        }
        if ($inputid > 0 && $description !== '') {
            $this->input->set_fields($inputid, '{"description":"'.$description.'"}');
            if ($this->redis) $this->load_redis_input($inputid);
        }
        
        $configs = $this->parse_configs($id, $description, $logging, $channel);
        $data = array(
            'device' => $deviceid,
            'configs' => $configs
        );
        
        $response = $this->ctrl->request($ctrlid, 'channels/'.urlencode($id), 'POST', $data);
        if (isset($response['success']) && $response['success'] == false) {
            return $response;
        }
        $channel = array(
            'id' => $id,
            'driver' => $driverid,
            'device' => $deviceid,
            'configs' => $configs,
            'state' => 'LOADING',
            'record' => array('flag' => 'LOADING')
        );
        return array('success'=>true, 'message'=>'Channel successfully added', 
            'channel'=>$this->get_channel($this->ctrl->get($ctrlid), $channel)
        );
    }

    public function get_list($userid, $ctrlid) {
        if (isset($ctrlid)) {
            $ctrlid = intval($ctrlid);
            $ctrls = array();
            $ctrls[] = $this->ctrl->get($ctrlid);
        }
        else {
            $userid = intval($userid);
            $ctrls = $this->ctrl->get_list($userid);
        }
        
        $channels = array();
        foreach($ctrls as $ctrl) {
            // Get drivers of all registered MUCs and add identifying location description and parse their configuration
            $response = $this->ctrl->request($ctrl['id'], 'channels', 'GET', array('details' => 'true'));
            if (isset($response['channels'])) {
                foreach($response['channels'] as $channel) {
                    $channels[] = $this->get_channel($ctrl, $channel);
                }
            }
        }
        return $channels;
    }

    public function get_states($userid, $ctrlid) {
        if (isset($ctrlid)) {
            $ctrlid = intval($ctrlid);
            $ctrls = array();
            $ctrls[] = $this->ctrl->get($ctrlid);
        }
        else {
            $userid = intval($userid);
            $ctrls = $this->ctrl->get_list($userid);
        }
        
        $states = array();
        foreach($ctrls as $ctrl) {
            // Get drivers of all registered MUCs and add identifying location description
            $response = $this->ctrl->request($ctrl['id'], 'channels/states', 'GET', null);
            if (isset($response['states'])) {
                foreach($response['states'] as $state) {
                    $states[] = array(
                            'userid'=>$ctrl['userid'],
                            'ctrlid'=>$ctrl['id'],
                            'id'=>$state['id'],
                            'state'=>$state['state']
                    );
                }
            }
        }
        return $states;
    }

    public function get_records($userid, $ctrlid) {
        if (isset($ctrlid)) {
            $ctrlid = intval($ctrlid);
            $ctrls = array();
            $ctrls[] = $this->ctrl->get($ctrlid);
        }
        else {
            $userid = intval($userid);
            $ctrls = $this->ctrl->get_list($userid);
        }
        
        $records = array();
        foreach($ctrls as $ctrl) {
            // Get drivers of all registered MUCs and add identifying location description
            $response = $this->ctrl->request($ctrl['id'], 'channels', 'GET', null);
            if (isset($response['records'])) {
                foreach($response['records'] as $channel) {
                    $type = isset($channel['valueType']) ? $channel['valueType'] : 'DOUBLE';
                    $record = $channel['record'];
                    $records[] = array(
                        'userid'=>$ctrl['userid'],
                        'ctrlid'=>$ctrl['id'],
                        'id'=>$channel['id'],
                        'time'=>isset($record['timestamp']) ? $record['timestamp'] : null,
                        'value'=>isset($record['value']) ? $record['value'] : null,
                        'flag'=>$record['flag'],
                        'configs'=>array('valueType'=>$type),
                    );
                }
            }
        }
        return $records;
    }

    public function info($userid, $ctrlid, $driverid) {
        $ctrlid = intval($ctrlid);
        
        $response = $this->ctrl->request($ctrlid, 'drivers/'.$driverid.'/infos/options', 'GET', array('filter' => 'channel'));
        if (isset($response['success']) && $response['success'] == false) {
            return $response;
        }
        return $this->create_log_info($userid, $response['infos']);
    }

    public function get($ctrlid, $id) {
        $ctrlid = intval($ctrlid);
        
        $ctrl = $this->ctrl->get($ctrlid);
        $response = $this->ctrl->request($ctrlid, 'channels/'.urlencode($id), 'GET', array('details' => 'true'));
        if (isset($response['success']) && $response['success'] == false) {
            return $response;
        }
        return $this->get_channel($ctrl, $response);
    }

    public function get_channel($ctrl, $details) {
        $configs = $details['configs'];
        $logging = $this->decode_log_settings($configs);
        
        $channel = array(
            'id'=>$details['id'],
            'userid'=>$ctrl['userid'],
            'ctrlid'=>$ctrl['id'],
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
        
        $record = $details['record'];
        $channel['timestamp'] = isset($record['timestamp']) ? $record['timestamp'] : null;
        $channel['value'] = isset($record['value']) ? $record['value'] : null;
        $channel['flag'] = $record['flag'];
        $channel['state'] = $details['state'];
        
        if (isset($configs['channelAddress'])) {
            $channel['address'] = $configs['channelAddress'];
            
            unset($configs['channelAddress']);
        }
        else {
            $channel['address'] = '';
        }
        if (isset($configs['channelSettings'])) {
            $channel['settings'] = $configs['channelSettings'];
            
            unset($configs['channelSettings']);
        }
        else {
            $channel['settings'] = '';
        }
        
        $disabled = false;
        if (isset($configs['disabled'])) {
            $disabled = $configs['disabled'];
            
            unset($configs['disabled']);
        }
        $channel['configs'] = $configs;
        $channel['logging'] = $logging;
        
        $channel['disabled'] = $disabled;
        
        return $channel;
    }

    private function parse_configs($id, $description, $logging, $channel) {
        $configs = array(
            'id' => $id
        );
        if ($description !== '') $channel['description'] = $description;
        
        if (isset($channel['address'])) $configs['channelAddress'] = $channel['address'];
        if (isset($channel['settings'])) $configs['channelSettings'] = $channel['settings'];
        if (!empty($logging)) {
            $configs['loggingSettings'] = $this->encode_log_settings($logging);
        }
        if (isset($channel['logging'])) {
            $logging = (array) $channel['logging'];
            if (isset($logging['loggingInterval'])) $configs['loggingInterval'] = $logging['loggingInterval'];
            if (isset($logging['loggingTimeOffset'])) $configs['loggingTimeOffset'] = $logging['loggingTimeOffset'];
            
        }
        if (isset($channel['configs'])) $configs = array_merge($configs, $channel['configs']);
        if (isset($channel['disabled'])) $configs['disabled'] = $channel['disabled'];
        
        return $configs;
    }

    private function create_log_info($userid, &$info) {
        $userid = intval($userid);
        
        global $feed_settings;
        require_once "Modules/feed/feed_model.php";
        $feed = new Feed($this->mysqli, $this->redis, $feed_settings);
        
        $configs = array('options'=>array());
        $logging = array('options'=>array());
        foreach($info['configs']['options'] as $option) {
            if ($option['key'] == 'loggingInterval' ||
                $option['key'] == 'loggingTimeOffset') {
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
            'key'=>'loggingMaxInterval',
            'name'=>'Post interval maximum',
            'description'=>'Dynamically post records only on changed values, up until to a maximum amount of time.',
            'type'=>'INTEGER',
            'mandatory'=>false,
            'valueSelection'=>array(
                '0'=>'None',
                '100'=>'100 milliseconds',
                '200'=>'200 milliseconds',
                '300'=>'300 milliseconds',
                '400'=>'400 milliseconds',
                '500'=>'500 milliseconds',
                '1000'=>'1 second',
                '2000'=>'2 second',
                '3000'=>'3 second',
                '4000'=>'4 second',
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
                '86400000'=>'1 day')
        );
        $logging['options'][] = array(
            'key'=>'loggingTolerance',
            'name'=>'Posting tolerance',
            'description'=>'Value change tolerance for dynamically logged records.',
            'type'=>'DOUBLE',
            'mandatory'=>false,
        );
        $logging['options'][] = array(
            'key'=>'average',
            'name'=>'Average',
            'description'=>'Average sampled values, if the logging interval is larger than its sampling interval.',
            'type'=>'BOOLEAN',
            'mandatory'=>false,
            'valueDefault'=>false
        );
        $logging['options'][] = array(
            'key'=>'nodeid',
            'name'=>'Node',
            'description'=>'The node to post channel records to.',
            'type'=>'STRING',
            'mandatory'=>true
        );
        $feeds = array();
        foreach ($feed->get_user_feeds($userid) as $f) {
            $feeds[$f['id']] = $f['name'];
        }
        $logging['options'][] = array(
            'key'=>'feedid',
            'name'=>'Feed',
            'description'=>'The feed in which the channels values were persistently logged.',
            'type'=>'INTEGER',
            'mandatory'=>false,
            'valueSelection'=>$feeds
        );
        $logging['options'][] = array(
            'key'=>'authorization',
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
        $info['logging'] = $logging;
        $info['configs'] = $configs;
        
        return $info;
    }

    private function parse_log_settings($userid, $nodeid, $logging) {
        $auth = isset($logging['authorization']) ? $logging['authorization'] : 'DEFAULT';
        
        $key = null;
        if ($auth !== 'NONE') {
            // TODO: check if device for authid exists and fetch devicekey
            switch ($auth) {
                case 'WRITE':
                    global $user;
                    
                    $key = $user->get_apikey_write($userid);
                    break;
                case 'READ':
                    global $user;
                    
                    $key = $user->get_apikey_read($userid);
                    break;
                default:
                    $auth = 'DEFAULT';
                    break;
            }
        }
        $settings = array(
            'nodeid' => $nodeid
        );
        if (isset($logging['loggingMaxInterval'])) $settings['loggingMaxInterval'] = $logging['loggingMaxInterval'];
        if (isset($logging['loggingTolerance'])) $settings['loggingTolerance'] = $logging['loggingTolerance'];
        if (isset($logging['average'])) $settings['average'] = $logging['average'];
        if (isset($logging['feedid'])) $settings['feedid'] = $logging['feedid'];
        
        $settings['authorization'] = $auth;
        if (isset($key)) {
            $settings['key'] = $key;
        }
        return $settings;
    }

    private function decode_log_settings(&$configs) {
        $logging = array();
        if (isset($configs)) {
            if (isset($configs['loggingInterval'])) $logging['loggingInterval'] = $configs['loggingInterval'];
            if (isset($configs['loggingTimeOffset'])) $logging['loggingTimeOffset'] = $configs['loggingTimeOffset'];
            
            if(isset($configs['loggingSettings'])) {
                $str = $configs['loggingSettings'];
                if (strpos($str, ':') !== false) {
                    $parameters = explode(',', $str);
                    foreach ($parameters as $parameter) {
                        $keyvalue = explode(':', $parameter);
                        $logging[$keyvalue[0]] = $keyvalue[1];
                    }
                }
                unset($configs['loggingSettings']);
            }
        }
        return $logging;
    }

    private function encode_log_settings($settings) {
        $arr = array();
        foreach ($settings as $key=>$value) {
            if (is_bool($value)) {
                $value = ($value) ? 'true' : 'false';
            }
            $arr[] = $key.':'.$value;
        }
        return implode(",", $arr);
    }

    private function get_input_by_node_name($userid, $nodeid, $name) {
        $result = $this->mysqli->query("SELECT id, nodeid, name, description, processList FROM input WHERE nodeid = '$nodeid' AND name = '$name'");
        if ($result->num_rows == 0) {
            return null;
        }
        return (array) $result->fetch_object();
    }

    private function load_redis_input($id) {
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

    public function update($userid, $ctrlid, $nodeid, $id, $configs) {
        $userid = intval($userid);
        $ctrlid = intval($ctrlid);
        
        $configs = (array) json_decode($configs, true);
        
        if (isset($configs['logging'])) {
            $logging = (array) $configs['logging'];
        }
        else {
            $logging = array('nodeid' => $nodeid);
        }
        $newnode = $logging['nodeid'];
        if (preg_replace('/[^\p{N}\p{L}\-\_\.\:\/]/u', '', $newnode) != $newnode) {
            return array('success'=>false, 'message'=>"Channel node must only contain a-z A-Z 0-9 - _ . : and / characters");
        }
        
        if (isset($configs['id'])) {
            $newid = $configs['id'];
            if (preg_replace('/[^\p{N}\p{L}\-\_\.\:\/]/u', '', $newid) != $newid) {
                return array('success'=>false, 'message'=>"Channel key must only contain a-z A-Z 0-9 - _ . : and / characters");
            }
        }
        else {
            $newid = $id;
        }
        
        if (!empty($configs['description'])) {
            $description = $configs['description'];
        }
        else {
            $description = '';
        }
        
        $logging = $this->parse_log_settings($userid, $newnode, $logging);
        $channel = $this->parse_configs($newid, $description, $logging, $configs);
        
        $response = $this->ctrl->request($ctrlid, 'channels/'.urlencode($id).'/configs', 'PUT', array('configs' => $channel));
        if (isset($response['success']) && $response['success'] == false) {
            return $response;
        }
        
        $input = $this->get_input_by_node_name($userid, $nodeid, $id);
        if (isset($input)) {
            $inputid = $input['id'];
            if ($id !== $newid || $nodeid !== $newnode) {
                $this->mysqli->query("UPDATE input SET `name`='$newid',`description`='$description',`nodeid`='$newnode' WHERE `id` = '$inputid'");
            }
            else {
                $this->input->set_fields($inputid, '{"description":"'.$description.'"}');
            }
            if ($this->redis) $this->load_redis_input($inputid);
        }
        return array('success'=>true, 'message'=>'Channel successfully updated');
    }

    public function write($ctrlid, $id, $value, $valueType) {
        $value = $this->parse_value($value, $valueType);
        if (isset($value['success']) && !$value['success']) {
            return $value;
        }
        $record = array( 'value' => $value );
        
        $response = $this->ctrl->request($ctrlid, 'channels/'.urlencode($id), 'PUT', array('record' => $record));
        if (isset($response['success']) && $response['success'] == false) {
            if (strpos($response["message"], 'ACCESS_METHOD_NOT_SUPPORTED') !== false) {
                return array('success'=>false, 'message'=>'Channel writing not supported');
            }
            return $response;
        }
        return array('success'=>true, 'message'=>'Channel successfully written to');
    }

    public function set($ctrlid, $id, $value, $valueType) {
        $value = $this->parse_value($value, $valueType);
        if (isset($value["success"]) && !$value["success"]) {
            return $value;
        }
        $record = array(
            'flag' => 'VALID',
            'value' => $value
        );
        
        $response = $this->ctrl->request($ctrlid, 'channels/'.urlencode($id).'/latestRecord', 'PUT', array('record' => $record));
        if (isset($response['success']) && $response['success'] == false) {
            return $response;
        }
        return array('success'=>true, 'message'=>'Channel successfully written to');
    }

    private function parse_value($value, $valueType) {
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
                        return array('success'=>false, 'message'=>'Unknown boolean value: '.$value);
                }
            }
        }
        else if (is_numeric($value)) {
            $value = floatval($value);
        }
        else {
            return array('success'=>false, 'message'=>'Value inconsistend with its type');
        }
        return $value;
    }

    public function delete($ctrlid, $id) {
        $ctrlid = intval($ctrlid);
        
        $response = $this->ctrl->request($ctrlid, 'channels/'.urlencode($id), 'DELETE', null);
        if (isset($response['success']) && $response['success'] == false) {
            return $response;
        }
        return array('success'=>true, 'message'=>'Channel successfully removed');
    }

    public function scan($userid, $ctrlid, $driverid, $deviceid, $settings) {
        $userid = intval($userid);
        $ctrlid = intval($ctrlid);
        
        if (empty($settings)) $settings = "";
        
        $response = $this->ctrl->request($ctrlid, 'devices/'.urlencode($deviceid).'/scan', 'GET', array('settings' => $settings));
        if (isset($response['success']) && $response['success'] == false) {
            return $response;
        };
        
        $channels = array();
        foreach($response['channels'] as $scan) {
            
            $channel = array(
                'userid'=>$userid,
                'ctrlid'=>$ctrlid,
                'driverid'=>$driverid,
                'deviceid'=>$deviceid,
                'description'=>'',
                'address'=>array(),
                'settings'=>array(),
                'logging'=>array(),
                'configs'=>array()
            );
            if (isset($scan['description'])) $channel['description'] = $scan['description'];
            if (isset($scan['channelAddress'])) $channel['address'] = $scan['channelAddress'];
            if (isset($scan['channelSettings'])) $channel['settings'] = $scan['channelSettings'];
            if (isset($scan['valueType'])) $channel['configs'] = array('valueType' => $scan['valueType']);
            if (isset($scan['metadata'])) $channel['metadata'] = $scan['metadata'];
            
            $channels[] = $channel;
        }
        return $channels;
    }
}

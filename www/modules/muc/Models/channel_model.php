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

    public function create($userid, $ctrlid, $driverid, $deviceid, $configs) {
        $userid = intval($userid);
        $ctrlid = intval($ctrlid);
        
        $configs = (array) json_decode($configs);
        
        if (isset($configs['logging'])) {
            $logging = (array) $configs['logging'];
        }
        else if (isset($configs['nodeid'])) {
            $logging = array('nodeid' => $configs['nodeid']);
        }
        
        if (!ctype_alnum(str_replace(array('.', '_', '-'), '', $configs['id']))) {
            return array('success'=>false, 'message'=>_("Invalid characters in channel key"));
        }
        $id = $configs['id'];
        
        if (!ctype_alnum(str_replace(array('.', '_', '-'), '', $logging['nodeid']))) {
            return array('success'=>false, 'message'=>_("Invalid characters in channel node"));
        }
        $nodeid = $logging['nodeid'];
        
        if (!empty($configs['description'])) {
            if (!ctype_alnum(str_replace(array(' ', '.', '_', '-'), '', $configs['description']))) {
                return array('success'=>false, 'message'=>_("Invalid characters in channel description"));
            }
            $description = $configs['description'];
        }
        else {
            $description = '';
        }
        
        $logging = $this->parse_log_settings($userid, $nodeid, $logging);
        
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
        if ($description !== '') {
            $this->input->set_fields($inputid, '{"description":"'.$description.'"}');
            if ($this->redis) $this->load_redis_input($inputid);
        }
        
        $channel = $this->parse_channel($id, $description, $logging, $configs);
        $data = array(
                'device' => $deviceid,
                'configs' => $channel
        );
        $response = $this->ctrl->request($ctrlid, 'channels/'.$id, 'POST', $data);
        if (isset($response['success']) && $response['success'] == false) {
            return $response;
        }
        $channel['driver'] = $driverid;
        $channel['device'] = $deviceid;
        
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
            $response = $this->ctrl->request($ctrl['id'], 'channels/details', 'GET', null);
            if (isset($response["details"])) {
                foreach($response['details'] as $details) {
                    $channels[] = $this->get_channel($ctrl, $details);
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
            if (isset($response["states"])) {
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
            if (isset($response["records"])) {
                foreach($response['records'] as $channel) {
                    $record = $channel['record'];
                    $records[] = array(
                        'userid'=>$ctrl['userid'],
                        'ctrlid'=>$ctrl['id'],
                        'id'=>$channel['id'],
                        'time'=>isset($record['timestamp']) ? $record['timestamp'] : null,
                        'value'=>isset($record['value']) ? $record['value'] : null,
                        'flag'=>$record['flag'],
                        'configs'=>array('valueType'=>$channel['valueType']),
                    );
                }
            }
        }
        return $records;
    }

    public function info($userid, $ctrlid, $driverid) {
        $ctrlid = intval($ctrlid);
        
        $response = $this->ctrl->request($ctrlid, 'drivers/'.$driverid.'/infos/details/channel', 'GET', null);
        if (isset($response['success']) && $response['success'] == false) {
            return $response;
        }
        return $this->create_log_info($userid, $response['infos']);
    }

    private function parse_channel($id, $description, $logging, $configs) {
        $channel = array(
                'id' => $id
        );
        if ($description !== '') $channel['description'] = $description;
        
        if (isset($configs['address'])) $channel['channelAddress'] = $configs['address'];
        if (isset($configs['settings'])) $channel['channelSettings'] = $configs['settings'];
        if (isset($logging)) $channel['loggingSettings'] = $this->encode_log_settings($logging);
        
        if (isset($configs['configs'])) {
            $details = (array) $configs['configs'];
            $logdetails = (array) $configs['logging'];
            
            if (isset($details['samplingInterval'])) $channel['samplingInterval'] = $details['samplingInterval'];
            if (isset($details['samplingTimeOffset'])) $channel['samplingTimeOffset'] = $details['samplingTimeOffset'];
            if (isset($details['samplingGroup'])) $channel['samplingGroup'] = $details['samplingGroup'];
            if (isset($details['listening'])) $channel['listening'] = $details['listening'];
            if (isset($logdetails['loggingInterval'])) $channel['loggingInterval'] = $logdetails['loggingInterval'];
            if (isset($logdetails['loggingTimeOffset'])) $channel['loggingTimeOffset'] = $logdetails['loggingTimeOffset'];
            
            if (isset($details['valueType'])) $channel['valueType'] = $details['valueType'];
            if (isset($details['valueLength'])) $channel['valueLength'] = $details['valueLength'];
            if (isset($details['unit'])) $channel['unit'] = $details['unit'];
            if (isset($details['scalingFactor'])) $channel['scalingFactor'] = $details['scalingFactor'];
            if (isset($details['valueOffset'])) $channel['valueOffset'] = $details['valueOffset'];
        }
        if (isset($configs['disabled'])) {
            $channel['disabled'] = $configs['disabled'];
        }
        
        return $channel;
    }

    public function get($ctrlid, $id) {
        $ctrlid = intval($ctrlid);
        
        $ctrl = $this->ctrl->get($ctrlid);
        $response = $this->ctrl->request($ctrlid, 'channels/'.$id.'/details', 'GET', null);
        if (isset($response['success']) && $response['success'] == false) {
            return $response;
        }
        $details = (array) $response['details'];
        return $this->get_channel($ctrl, $details);
    }

    public function get_channel($ctrl, $details) {
        $logging = $this->decode_log_settings($details);
        
        return array(
            'id'=>$details['id'],
            'userid'=>$ctrl['userid'],
            'ctrlid'=>$ctrl['id'],
            'driverid'=>$details['driver'],
            'deviceid'=>$details['device'],
            'nodeid'=>$logging['nodeid'],
            'description'=>!empty($details['description']) ? $details['description'] : '',
            'time'=>isset($details['timestamp']) ? $details['timestamp'] : null,
            'value'=>isset($details['value']) ? $details['value'] : null,
            'flag'=>isset($details['flag']) ? $details['flag'] : null,
            'state'=>isset($details['state']) ? $details['state'] : null,
            'address'=>isset($details['channelAddress']) ? $details['channelAddress'] : '',
            'settings'=>isset($details['channelSettings']) ? $details['channelSettings'] : '',
            'logging'=>$logging,
            'configs'=>$this->get_configs($details),
            'disabled'=>isset($details['disabled']) ? $details['disabled'] : false
        );
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
                    
                    $logging['options'][] = $option;
                }
                else {
                    $configs['options'][] = $option;
                }
        }
        $logging['options'][] = array(
            'key'=>'loggingMaxInterval',
            'name'=>'Logging interval maximum',
            'description'=>'Dynamically log records only on changed values, up until to a maximum amount of time.',
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
            'name'=>'Logging tolerance',
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

    private function decode_log_settings($channel) {
        $logging = array();
        if (isset($channel)) {
            if (isset($channel['loggingInterval'])) $logging['loggingInterval'] = $channel['loggingInterval'];
            if (isset($channel['loggingTimeOffset'])) $logging['loggingTimeOffset'] = $channel['loggingTimeOffset'];
            
            if(isset($channel['loggingSettings'])) {
                $str = $channel['loggingSettings'];
                if (strpos($str, ':') !== false) {
                    $parameters = explode(',', $str);
                    foreach ($parameters as $parameter) {
                        $keyvalue = explode(':', $parameter);
                        $logging[$keyvalue[0]] = $keyvalue[1];
                    }
                }
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

    private function get_configs($channel) {
        $configs = array();
        if (isset($channel)) {
            foreach($channel as $key => $value) {
                if (strcmp($key, 'id') !== 0 &&
                        strcmp($key, 'driver') !== 0 &&
                        strcmp($key, 'device') !== 0 &&
                        strcmp($key, 'channelAddress') !== 0 &&
                        strcmp($key, 'channelSettings') !== 0  &&
                        strcmp($key, 'loggingSettings') !== 0 &&
                        strcmp($key, 'loggingInterval') !== 0 &&
                        strcmp($key, 'loggingTimeOffset') !== 0 &&
                        strcmp($key, 'timestamp') !== 0 &&
                        strcmp($key, 'value') !== 0 &&
                        strcmp($key, 'flag') !== 0 &&
                        strcmp($key, 'state') !== 0 &&
                        strcmp($key, 'disabled') !== 0) {
                    
                    $configs[$key] = $value;
                }
            }
        }
        if (empty($configs['valueType'])) {
            $configs['valueType'] = 'DOUBLE';
        }
        return $configs;
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
        
        $configs = (array) json_decode($configs);
        
        if (isset($configs['logging'])) {
            $logging = (array) $configs['logging'];
            
            if (!ctype_alnum(str_replace(array('.', '_', '-'), '', $logging['nodeid']))) {
                return array('success'=>false, 'message'=>_("Invalid characters in channel node"));
            }
        }
        else {
            $logging = array('nodeid' => $nodeid);
        }
        
        if (isset($configs['id'])) {
            if (!ctype_alnum(str_replace(array('.', '_', '-'), '', $configs['id']))) {
                return array('success'=>false, 'message'=>_("Invalid characters in channel key"));
            }
            $newid = $configs['id'];
        }
        else {
            $newid = $id;
        }
        $newnode = $logging['nodeid'];
        
        if (!empty($configs['description'])) {
            if (!ctype_alnum(str_replace(array(' ', '.', '_', '-'), '', $configs['description']))) {
                return array('success'=>false, 'message'=>_("Invalid characters in channel description"));
            }
            $description = $configs['description'];
        }
        else {
            $description = '';
        }
        
        $logging = $this->parse_log_settings($userid, $newnode, $logging);
        $channel = $this->parse_channel($newid, $description, $logging, $configs);
        
        $response = $this->ctrl->request($ctrlid, 'channels/'.$id.'/configs', 'PUT', array('configs' => $channel));
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
        if (isset($value["success"]) && !$value["success"]) {
            return $value;
        }
        $record = array( 'value' => $value );
        
        $response = $this->ctrl->request($ctrlid, 'channels/'.$id, 'PUT', array('record' => $record));
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
        
        $response = $this->ctrl->request($ctrlid, 'channels/'.$id.'/latestRecord', 'PUT', array('record' => $record));
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
        
        $response = $this->ctrl->request($ctrlid, 'channels/'.$id, 'DELETE', null);
        if (isset($response['success']) && $response['success'] == false) {
            return $response;
        }
        return array('success'=>true, 'message'=>'Channel successfully removed');
    }

    public function scan($userid, $ctrlid, $driverid, $deviceid, $settings) {
        $userid = intval($userid);
        $ctrlid = intval($ctrlid);
        
        if (empty($settings)) $settings = "";
        
        $response = $this->ctrl->request($ctrlid, 'devices/'.$deviceid.'/scan', 'GET', array('settings' => $settings));
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

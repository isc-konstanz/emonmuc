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

require_once "Modules/muc/Lib/muc_channel.php";

class HttpChannel extends ControllerChannel {
    private static $cache = array();
    private $http;

    public function __construct($ctrl, $mysqli, $redis) {
        parent::__construct($ctrl, $mysqli, $redis);
        require_once "Modules/muc/Lib/http/http.php";
        $options = $ctrl['options'];
        $this->http = new Http($ctrl['type'] == 'https', $options['address'], $options['port'], $options['password']);
    }

    public function create($driverid, $deviceid, $channel) {
        $channel = (array) json_decode($channel, true);
        
        $id = $channel['id'];
        if (preg_replace('/[^\p{N}\p{L}\-\_\.\:\/]/u', '', $id) != $id) {
            return array('success'=>false, 'message'=>"Channel key must only contain a-z A-Z 0-9 - _ . : and / characters");
        }
        
        if (!empty($channel['description'])) {
            $description = $channel['description'];
        }
        else {
            $description = '';
        }
        
        if (!empty($channel['logging'])) {
            $logging = (array) $channel['logging'];
            $logging_enabled = (isset($logging['loggingInterval']) && $logging['loggingInterval'] > 0) ||
                               (isset($logging['loggingEvent']) && $logging['loggingEvent']);
            
            if (empty($logging['nodeid'])) {
                return array('success'=>false, 'message'=>"Channel node needs to be configured to post values");
            }
        }
        if (!empty($logging['nodeid'])) {
            $nodeid = $logging['nodeid'];
        }
        else {
            $nodeid = $deviceid;
        }
        if (preg_replace('/[^\p{N}\p{L}\-\_\.\:\/]/u', '', $nodeid) != $nodeid) {
            return array('success'=>false, 'message'=>"Channel node must only contain a-z A-Z 0-9 - _ . : and / characters");
        }
        
        $inputid = -1;
        $input = $this->get_input($this->ctrl['userid'], $nodeid, $id);
        if (isset($input)) {
            $inputid = $input['id'];
        }
        else if ($inputid <= 0 && $logging_enabled) {
            $inputid = $this->input()->create_input($this->ctrl['userid'], $nodeid, $id);
            if ($inputid < 0) {
                return array('success'=>false, 'message'=>_("Unable to create input for channel: $id"));
            }
        }
        if ($inputid > 0) {
            $channel['logging']['inputid'] = $inputid;
            if ($description !== '') {
                $this->input()->set_fields($inputid, '{"description":"'.$description.'"}');
                if ($this->redis) $this->load_redis_input($inputid);
            }
        }
        
        $configs = $this->encode($id, $description, $channel);
        $data = array(
            'device' => $deviceid,
            'configs' => $configs
        );
        
        $this->http->post('channels/'.urlencode($id), $data);
        $channel = $this->decode(array(
            'id' => $id,
            'driver' => $driverid,
            'device' => $deviceid,
            'configs' => $configs,
            'state' => '',
            'record' => array('flag' => '')
        ));
        if ($this->redis) {
            $this->add_redis($channel);
        }
        return array('success'=>true, 'message'=>'Channel successfully added', 'channel'=>$channel);
    }

    public function exists($id) {
        if (isset(self::$cache[$id])) {
            $exists = self::$cache[$id]; // Retrieve from static cache
        }
        else {
            $exists = false;
            if ($this->redis) {
                $exists = $this->redis->exists("muc#".$this->ctrl['id'].":channel:".$id);
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
            throw new ControllerException("Unable to load channels without redis installed");
        }
        $ctrlid = $this->ctrl['id'];
        
        // First, flush redis keys for the controller to reload
        foreach ($this->redis->sMembers("muc#$ctrlid:channels") as $id) {
            $this->redis->del("muc#$ctrlid:channel:$id");
            $this->redis->srem("muc#$ctrlid:channels", $id);
        }
        $channels = $this->get_http_list();
        foreach ($channels as $channel) {
            $this->add_redis($channel);
        }
        return $channels;
    }

    public function get_list() {
        if ($this->redis) {
            $channels = $this->get_redis_list();
        } else {
            $channels = $this->get_http_list();
        }
        usort($channels, function($c1, $c2) {
            $idsort = function($id1, $id2) {
                return strcmp(preg_replace('/[^\p{N}\p{L}]/u', '', $id1), 
                              preg_replace('/[^\p{N}\p{L}]/u', '', $id2));
            };
            if($c1['deviceid'] != $c2['deviceid']) {
                return $idsort($c1['deviceid'], 
                               $c2['deviceid']);
            }
            return $idsort($c1['id'], $c2['id']);
        });
        return $channels;
    }

    protected function get_redis_list() {
        $channels = parent::get_redis_list();
        
        if (empty($channels)) {
            $channels = $this->load();
        }
        return $channels;
    }

    private function get_http_list() {
        $channels = array();
        $result = $this->http->get('channels', array('details' => 'true'));
        if (isset($result['channels'])) {
            foreach($result['channels'] as $channel) {
                $channels[] = $this->decode($channel);
            }
        }
        return $channels;
    }

    public function get_states() {
        $states = array();
        $result = $this->http->get('channels/states');
        if (isset($result['states'])) {
            foreach($result['states'] as $state) {
                $states[] = $this->decode_state($state);
            }
        }
        return $states;
    }

    public function get_records() {
        $records = array();
        $result = $this->http->get('channels');
        if (isset($result['records'])) {
            foreach($result['records'] as $channel) {
                $records[] = $this->decode_record($channel);
            }
        }
        return $records;
    }

    public function info($driverid) {
        $result = $this->http->get('drivers/'.$driverid.'/infos/options', array('filter' => 'channel'));
        return $this->decode_infos($result['infos']);
    }

    public function get($id) {
        if ($this->redis) {
            return $this->get_redis($id);
        } else {
            return $this->get_http($id);
        }
    }

    private function get_http($id) {
        $result = $this->http->get('channels/'.urlencode($id), array('details' => 'true'));
        return $this->decode($result);
    }

    public function scan($driverid, $deviceid, $settings) {
        if (empty($settings)) $settings = "";
        
        $channels = array();
        $result = $this->http->get('devices/'.urlencode($deviceid).'/scan', array('settings' => $settings));
        foreach($result['channels'] as $scan) {
            $channel = array(
                'userid'=>$this->ctrl['userid'],
                'ctrlid'=>$this->ctrl['id'],
                'driverid'=>$driverid,
                'deviceid'=>$deviceid,
                'description'=>'',
                'address'=>array(),
                'settings'=>array(),
                'logging'=>array(),
                'configs'=>array()
            );
            if (isset($scan['description'])) $channel['description'] = $scan['description'];
            if (isset($scan['address'])) $channel['address'] = $scan['address'];
            if (isset($scan['settings'])) $channel['settings'] = $scan['settings'];
            if (isset($scan['valueType'])) $channel['configs'] = array('valueType' => $scan['valueType']);
            if (isset($scan['valueTypeLength'])) $channel['configs']['valueTypeLength'] = $scan['valueTypeLength'];
            if (isset($scan['metadata'])) $channel['metadata'] = $scan['metadata'];
            
            $channels[] = $channel;
        }
        return $channels;
    }

    public function set($id, $value, $valueType) {
        $record = array(
            'flag' => 'VALID',
            'value' => $this->encode_value($value, $valueType)
        );
        $this->http->put('channels/'.urlencode($id).'/latestRecord', array('record' => $record));
        return array('success'=>true, 'message'=>'Channel value successfully set');
    }

    public function write($id, $value, $valueType) {
        $record = array(
            'value' => $this->encode_value($value, $valueType)
        );
        try {
            $this->http->put('channels/'.urlencode($id), array('record' => $record));
        }
        catch(ControllerException $e) {
            if (stristr($e->getMessage(), 'ACCESS_METHOD_NOT_SUPPORTED') !== false) {
                throw new ControllerException("Channel writing not supported");
            }
            throw $e;
        }
        return array('success'=>true, 'message'=>'Channel successfully written to');
    }

    public function update($id, $nodeid, $channel) {
        $channel = (array) json_decode($channel, true);
        
        if (isset($channel['id'])) {
            $newid = $channel['id'];
            if (preg_replace('/[^\p{N}\p{L}\-\_\.\:\/]/u', '', $newid) != $newid) {
                return array('success'=>false, 'message'=>"Channel key must only contain a-z A-Z 0-9 - _ . : and / characters");
            }
        }
        else {
            $newid = $id;
        }
        
        if (!empty($channel['description'])) {
            $description = $channel['description'];
        }
        else {
            $description = '';
        }
        
        if (!empty($channel['logging']) && !empty($channel['logging']['nodeid'])) {
            $newnode = $channel['logging']['nodeid'];
                if (preg_replace('/[^\p{N}\p{L}\-\_\.\:\/]/u', '', $newnode) != $newnode) {
                    return array('success'=>false, 'message'=>"Channel node must only contain a-z A-Z 0-9 - _ . : and / characters");
                }
            }
        else {
            $newnode = $nodeid;
        }
        
        $configs = $this->encode($newid, $description, $channel);
        $this->http->put('channels/'.urlencode($id).'/configs', array('configs' => $configs));
        
        if (isset($nodeid)) {
            $input = $this->get_input($this->ctrl['userid'], $nodeid, $id);
            if (isset($input)) {
                $inputid = $input['id'];
                if ($id !== $newid || $nodeid !== $newnode) {
                    $this->mysqli->query("UPDATE input SET `name`='$newid',`description`='$description',`nodeid`='$newnode' WHERE `id` = '$inputid'");
                }
                else {
                    $this->input()->set_fields($inputid, '{"description":"'.$description.'"}');
                }
                if ($this->redis) $this->load_redis_input($inputid);
            }
        }
        if ($this->redis) {
            $this->update_redis($id, $nodeid, $channel);
        }
        return array('success'=>true, 'message'=>'Channel successfully updated');
    }

    public function delete($id) {
        $this->http->delete('channels/'.urlencode($id));
        
        if ($this->redis) {
            $this->remove_redis($id);
        }
        return array('success'=>true, 'message'=>'Channel successfully removed');
    }
}

<?php
/*
     Released under the GNU Affero General Public License.
     See COPYRIGHT.txt and LICENSE.txt.

     Device module contributed by Nuno Chaveiro nchaveiro(at)gmail.com 2015
     ---------------------------------------------------------------------
     Sponsored by http://archimetrics.co.uk/
*/

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

require_once "Modules/device/device_template.php";
require_once "Modules/muc/muc_model.php";

class MucTemplate extends DeviceTemplate {
    const DIR_DEFAULT = "/var/opt/emonmuc/";

    private $ctrl;

    function __construct(&$parent) {
        parent::__construct($parent);
        $this->ctrl = new Controller($this->mysqli, $this->redis);
    }

    protected function load_list() {
        $list = array();
        
        $dir = $this->get_dir();
        if (is_dir($dir)) {
            $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::FOLLOW_SYMLINKS);
            foreach (new RecursiveIteratorIterator($it) as $file) {
                if ($file->getExtension() == "json") {
                    $type = substr(pathinfo($file, PATHINFO_DIRNAME), strlen($dir)).'/'.pathinfo($file, PATHINFO_FILENAME);
                    
                    $result = $this->get($type);
                    if (is_array($result) && isset($result['success']) && $result['success'] == false) {
                        return $result;
                    }
                    $list[$type] = $result;
                }
            }
        }
        return $list;
    }

    public function get($type) {
        if (preg_replace('/[^\p{N}\p{L}\-\_\/]/u', '', $type) != $type) {
            return array('success'=>false, 'message'=>"Device type must only contain A-Z a-z 0-9 - _ / characters");
        }
        $file = $this->get_dir().$type.".json";
        if (!file_exists($file)) {
            return array('success'=>false, 'message'=>"Error reading template $type: $file does not exist");
        }
        $content = file_get_contents($file);
        $template = json_decode($content);
        if (json_last_error() != 0) {
            return array('success'=>false, 'message'=>"Error reading template $type: ".json_last_error_msg());
        }
        $options = array();
        
        if (strpos($content, '*') !== false) {
            $options[] = array('id'=>'sep',
                'name'=>'Separator',
                'description'=>'The separator to use in the names of automatically created elements.',
                'type'=>'selection',
                'select'=>array(
                    array('name'=>'Dot', 'value'=>'.'),
                    array('name'=>'Hyphen', 'value'=>'-'),
                    array('name'=>'Underscore', 'value'=>'_'),
                    array('name'=>'Space', 'value'=>' ')
                ),
                'default'=>self::SEPARATOR,
                'mandatory'=>false,
            );
        }
        
        global $session;
        $ctrls = $this->ctrl->get_list($session['userid']);
        if (count($ctrls) > 0) {
            $select = array();
            foreach ($ctrls as $ctrl) {
                $select[] = array('name'=>$ctrl['name'], 'value'=>$ctrl['id']);
            }
            $options[] = array('id'=>'ctrlid',
                'name'=>'Controller',
                'description'=>'The communication controller this device should be registered for.',
                'type'=>'selection',
                'select'=>$select,
                'default'=>$ctrls[0]['id'],
                'mandatory'=>true,
            );
        }
        $template->options = array_merge($options, isset($template->options) ? $template->options : array());
        
        // Recursively cast associative arrays to objects
        return json_decode(json_encode($template));
    }

    private function get_dir() {
        global $settings;
        if (isset($settings['muc']) && !empty($settings['muc']['lib_dir'])) {
            $muc_template_dir = $settings['muc']['lib_dir'];
        }
        else {
            $muc_template_dir = self::DIR_DEFAULT;
        }
        if (substr($muc_template_dir, -1) !== "/") {
            $muc_template_dir .= "/";
        }
        return $muc_template_dir."device/";
    }

    public function prepare($device) {
        $userid = intval($device['userid']);
        $nodeid = $device['nodeid'];
        
        $configs = $this->device->get_configs($device);
        if (empty($configs['ctrlid'])) {
            throw new DeviceException('Unspecified controller ID in device configs.');
        }
        $device['configs'] = $configs;
        try {
            $template = $this->prepare_template($device);
            
            if (isset($template->feeds)) {
                $feeds = $template->feeds;
                $this->prepare_feeds($userid, $nodeid, $feeds);
            }
            else {
                $feeds = [];
            }
            
            if (isset($template->channels)) {
                $channels = $template->channels;
                $this->prepare_inputs($userid, $nodeid, $channels);
            }
            else {
                $channels = [];
            }
            
            if (!empty($feeds)) {
                $this->prepare_feed_processes($userid, $feeds, $channels);
            }
            if (!empty($channels)) {
                $this->prepare_input_processes($userid, $feeds, $channels);
            }
        } catch(Exception $e) {
            return array('success'=>false, 'message'=>$e->getMessage());
        }
        return array('success'=>true, 'feeds'=>$feeds, 'inputs'=>$channels);
    }

    public function prepare_template($device) {
        $file = $this->get_dir().$device['type'].".json";
        if (!file_exists($file)) {
            throw new ParseError("Error reading template ".$device['type'].": $file does not exist");
        }
        $configs = $this->device->get_configs($device);
        $content = file_get_contents($file);
        $template = json_decode($content);
        if (json_last_error() != 0) {
            throw new ParseError("Error reading template ".$device['type'].": ".json_last_error_msg());
        }
        return $this->prepare_json($device, $template, $content, $configs);
    }

    protected function prepare_json($device, $template, $content, $configs) {
        $result = json_decode($this->prepare_str($device, $template, $content, $configs));
        if (json_last_error() != 0) {
            throw new ParseError("Error preparing type ".$device['type'].": ".json_last_error_msg());
        }
        return $result;
    }

    protected function prepare_str($device, $template, $content, $configs) {
        if (strpos($content, '*') !== false) {
            $separator = isset($configs['sep']) ? $configs['sep'] : self::SEPARATOR;
            $content = str_replace("*", $separator, $content);
        }
        if (strpos($content, '<node>') !== false) {
            $content = str_replace("<node>", $device['nodeid'], $content);
        }
        if (strpos($content, '<name>') !== false) {
            $name = !empty($device['name']) ? preg_replace('/[^\p{N}\p{L}\-\_\.\:\s]/u', '', $device['name']) : $device['nodeid'];
            $content = str_replace("<name>", $name, $content);
        }
        if (isset($template->options)) {
            foreach ($template->options as $option) {
                if (strpos($content, "<$option->id>") !== false) {
                    if (isset($configs[$option->id])) {
                        $content = str_replace("<$option->id>", $configs[$option->id], $content);
                    }
                    else if (isset($option->default)) {
                        $content = str_replace("<$option->id>", $option->default, $content);
                    }
                }
            }
        }
        return $content;
    }

    public function init($device, $template) {
        $userid = intval($device['userid']);
        
        if (empty($template)) {
            $result = $this->prepare($device);
            if (isset($result['success']) && $result['success'] == false) {
                return $result;
            }
            $template = $result;
        }
        if (is_string($template)) $template = json_decode($template);
        if (!is_object($template)) $template = (object) $template;
        
        $configs = $this->device->get_configs($device);
        if (empty($configs['ctrlid'])) {
            throw new DeviceException('Unspecified controller ID in device configs.');
        }
        $device['configs'] = $configs;
        try {
            $result = $this->prepare_template($device);
            if (empty($result->devices)) {
                return array('success'=>false, 'message'=>'Bad device template. Devices undefined.');
            }
            $ctrlid = intval($configs['ctrlid']);
            $deviceid = $device['nodeid'];
            $devices = $this->decode_devices($deviceid, $result, $configs);
            $response = $this->create_devices($ctrlid, $devices);
            if (isset($response['success']) && $response['success'] == false) {
                return $response;
            }
            
            if (isset($template->feeds)) {
                $feeds = $template->feeds;
                $this->create_feeds($userid, $feeds);
            }
            else {
                $feeds = array();
            }
            
            if (isset($template->inputs)) {
                $channels = $this->decode_channels($deviceid, $template->inputs, $result, $configs, $feeds);
                $response = $this->create_channels($ctrlid, $devices, $channels);
                if (isset($response['success']) && $response['success'] == false) {
                    return $response;
                }
                $this->create_inputs($userid, $channels);
            }
            else {
                $channels = array();
            }
            
            if (!empty($feeds)) {
                $this->create_feed_processes($userid, $feeds, $channels);
            }
            if (!empty($channels)) {
                $this->create_input_processes($userid, $feeds, $channels);
            }
        } catch(Exception $e) {
            return array('success'=>false, 'message'=>$e->getMessage());
        }
        return array('success'=>true, 'message'=>'Device initialized');
    }

    // Create the devices
    private function create_devices($ctrlid, $devices) {
        $ctrl = $this->ctrl->get($ctrlid);
        foreach ($devices as $device) {
            try {
                $result = $this->ctrl->device($ctrl)->create($device->driver, json_encode($device));
                if (isset($result['success']) && $result['success'] == false) {
                    return $result;
                }
            }
            catch(Exception $e) {
                if (stristr(strtolower($e->getMessage()), '409 conflict') === false) {
                    return $e->getResult();
                }
            }
        }
        return array('success'=>true, 'message'=>'Devices successfully created');
    }

    // Create the channels
    private function create_channels($ctrlid, $devices, &$channels) {
        $ctrl = $this->ctrl->get($ctrlid);
        foreach($channels as $id=>$c) {
            $configs = (array) $c;
            $configs['id'] = $configs['name'];
            
            if (isset($configs['device'])) {
                $deviceid = $configs['device'];
                
                foreach ($devices as $d) {
                    if ($d->id == $deviceid) {
                        $driverid = $d->driver;
                        break;
                    }
                }
            }
            else {
                $deviceid = $devices[0]->id;
                $driverid = $devices[0]->driver;
            }
            try {
                $result = $this->ctrl->channel($ctrl)->create($driverid, $deviceid, json_encode($configs));
                if (isset($result['success']) && $result['success'] == false) {
                    return $result;
                }
            }
            catch(ControllerException $e) {
                if (stristr(strtolower($e->getMessage()), '409 conflict') === false) {
                    return $e->getResult();
                }
            }
            //if (empty($c->node) ||
            if (empty($c->logging) && empty($c->logging->loggingInterval)) {
                // Remove the channel from list to avoid the unnecessary input creation
                $c->action = 'none';
            }
            else {
                $c->id = $this->input->exists_nodeid_name($ctrl['userid'], $c->node, $c->name);
                $c->action = 'set';
            }
        }
        return array('success'=>true, 'message'=>'Channels successfully created');
    }

    protected function create_feeds($userid, &$feeds) {
        foreach($feeds as $f) {
            $datatype = constant($f->type); // DataType::
            $engine = constant($f->engine); // Engine::
            if (isset($f->unit)) $unit = $f->unit; else $unit = "";
            
            $options = new stdClass();
            if ($engine == Engine::PHPFIWA || $engine == Engine::PHPFINA || $engine == Engine::PHPTIMESTORE || $engine == Engine::TIMESTORE) {
                if (property_exists($f, "interval")) {
                    $options->interval = $f->interval;
                }
            }
            else if ($engine == Engine::MYSQL || $engine == Engine::MYSQLMEMORY) {
                if (property_exists($f, "table")) {
                    $options->name = $f->table;
                }
                if (property_exists($f, "valueType")) {
                    $options->type = $f->valueType;
                }
                $options->empty = true;
            }
            
            if ($f->action === 'create') {
                $result = $this->feed->create($userid, $f->tag, $f->name, $datatype, $engine, $options, $unit);
                if($result['success'] === true) {
                    $f->id = $result["feedid"];
                }
            }
        }
    }

    public function set_fields($device, $fields) {
        if (count((array) $fields) < 1) {
            return array('success'=>true, 'message'=>"No fields to update passed");
        }
        $template = $this->get($device['type']);
        if (!is_object($template)) {
            return $template;
        }
        
        // TODO: check if the controller changed.
        $configs = $this->device->get_configs($device);
        if (empty($configs['ctrlid'])) {
            throw new DeviceException('Unspecified controller ID in device configs.');
        }
        $ctrlid = intval($configs['ctrlid']);
        
        $update = $device;
        if (isset($fields->options)) {
            $update['configs'] = (array) $fields->options;
        }
        else {
            $update['configs'] = $configs;
        }
        if (isset($fields->nodeid)) $update['nodeid'] = $fields->nodeid;
        if (isset($fields->name)) $update['name'] = $fields->name;
        
        if (isset($template->devices)) {
            $this->update_devices($ctrlid, $device, $update, $template);
        }
        if (isset($template->channels)) {
            $this->update_channels($ctrlid, $device, $update, $template);
        }
        if (isset($template->feeds)) {
            $this->update_feeds($device, $update, $template);
        }
        return array('success'=>true, 'message'=>"Device updated");
    }

    protected function update_devices($ctrlid, $device, $update, $template) {
        $ctrl = $this->ctrl->get($ctrlid);
        foreach($template->devices as $d) {
            if (isset($d->name)) {
                $id = $this->prepare_str($device, $template, $d->name, $update['configs']);
            }
            else {
                $id = $device['nodeid'];
            }
            if (!$this->ctrl->device($ctrl)->exists($id)) {
                continue;
            }
            $device = $this->prepare_json($update, $template, json_encode($d), $update['configs']);
            $configs = $this->decode_device($update['nodeid'], $device, $template, $update['configs']);
            try {
                $this->ctrl->device($ctrl)->update($id, json_encode($configs));
            }
            catch(ControllerException $e) {
                // Do nothing
            }
        }
    }

    protected function update_channels($ctrlid, $device, $update, $template) {
        $ctrl = $this->ctrl->get($ctrlid);
        foreach($template->channels as $c) {
            $configs = isset($device['configs']) ? $device['configs'] : $update['configs'];
            $configs = $this->prepare_json($update, $template, json_encode($c), $configs);
            $channel = $this->decode_channel($update['nodeid'], $configs, $template, $update['configs']);
            $channel->id = $this->prepare_str($device, $template, $c->name, $update['configs']);
            
            $id = $channel->id;
            if (!$this->ctrl->channel($ctrl)->exists($id)) {
                if (!$this->ctrl->channel($ctrl)->exists($configs->name)) {
                    continue;
                }
                $id = $configs->name;
            }
            unset($channel->name);
            try {
                $this->ctrl->channel($ctrl)->update($id, $device['nodeid'], json_encode($channel));
            }
            catch(ControllerException $e) {
                // Do nothing
            }
        }
    }

    protected function update_feeds($device, $update, $template) {
        foreach($template->feeds as $f) {
            $feed = $this->prepare_json($device, $template, json_encode($f), $update['configs']);
            $id = $this->feed->exists_tag_name(intval($device['userid']), isset($feed->tag) ? $feed->tag : $device['nodeid'], 
                $this->prepare_str($device, $template, $feed->name, $update['configs']));
            
            if ($id > 0) {
                $feed = $this->prepare_json($update, $template, json_encode($f), $update['configs']);
                if (!isset($feed->tag)) {
                    $feed->tag = $update['nodeid'];
                }
                $this->feed->set_feed_fields($id, json_encode(array('name' => $feed->name, 'tag' => $feed->tag)));
            }
        }
    }

    private function decode_devices($id, $template, $parameters) {
        $devices = array();
        foreach ($template->devices as $device) {
            $devices[] = $this->decode_device($id, $device, $template, $parameters);
        }
        return $devices;
    }

    private function decode_device($id, $device, $template, $parameters) {
        if (isset($device->name)) {
            $device->id = $device->name;
        }
        else {
            $device->id = $id;
        }
        if (empty($device->driver)) {
            $device->driver = isset($template->driver) ? $template->driver : null;
        }
        if (isset($template->options)) {
            $device = $this->decode_configs('device', $device, $template, $parameters);
        }
        return $device;
    }

    private function decode_channels($deviceid, $channels, $template, $parameters, $feeds) {
        $result = array();
        foreach ($channels as $channel) {
            $result[] = $this->decode_channel($deviceid, $channel, $template, $parameters, $feeds);
        }
        return $result;
    }

    private function decode_channel($deviceid, $channel, $template, $parameters, $feeds=null) {
        if(!isset($channel->node)) {
            $channel->node = $deviceid;
        }
        if (empty($channel->logging)) {
            $logging = array();
        }
        else if (isset($channel->logging)) {
            $logging = (array) $channel->logging;
            if (isset($logging['feed']) && isset($feeds)) {
                $feed = $logging['feed'];
                $result = $this->search_feed($feeds, $feed->tag, $feed->name);
                if (isset($result->id) && $result->id > 0) {
                    $logging['feedid'] = $result->id;
                }
                unset($logging['feed']);
            }
        }
        $logging['nodeid'] = $channel->node;
        $channel->logging = $logging;
        
        if (isset($template->options)) {
            $channel = $this->decode_configs('channel', $channel, $template, $parameters);
        }
        return $channel;
    }

    private function decode_configs($type, $configs, $template, $parameters) {
        foreach (array('address', 'settings') as $key) {
            if (empty($configs->$key)) {
                $configs->$key = $this->decode_options($type.ucfirst($key), $template, $parameters);
            }
        }
        return $configs;
    }

    private function decode_options($type, $template, $parameters) {
        $result = "";
        
        if (isset($template->syntax) && isset($template->syntax->$type)) {
            $syntax = $template->syntax->$type;
        }
        else if (substr($type, -strlen('Address')) === 'Address') {
            $syntax = (object) array(
                'keyValue' => false,
                'separator' => ':'
            );
        }
        else if (substr($type, -strlen('Settings')) === 'Settings') {
            $syntax = (object) array(
                'keyValue' => true,
                'separator' => ',',
                'assignment' => ':'
            );
        }
        
        // Iterate all options as configured in the template and parse them accordingly, 
        // if they exist in the passed key value options array
        foreach ($template->options as $option) {
            if (empty($option->syntax)) {
                continue;
            }
            if ($option->syntax !== $type) {
                continue;
            }
            if (isset($parameters[$option->id])) {
                $value = $parameters[$option->id];
            }
            else if (isset($option->default)) {
                $value = $option->default;
            }
            else {
                continue;
            }
            
            if (!empty($result)) {
                $result .= $syntax->separator;
            }
            if (!$syntax->keyValue) {
                $result .= $value;
            }
            else {
                $result .= $option->id.$syntax->assignment.$value;
            }
        }
        return $result;
    }

    public function delete($device) {
        $configs = $this->device->get_configs($device);
        if (isset($configs['ctrlid'])) {
            $ctrlid = intval($configs['ctrlid']);
            $ctrl = $this->ctrl->get($ctrlid);
            
            try {
                $this->ctrl->device($ctrl)->delete($device['nodeid']);
            }
            catch (ControllerException $e) {
                if (stristr($e->getMessage(), 'does not exist') === false) {
                    return $e->getResult();
                }
            }
        }
        return array('success'=>true, 'message'=>"No device to delete");
    }

    public function scan_start($type, $options) {
        global $session;
        $userid = $session['userid'];
        
        $template = $this->get($type);
        if (is_array($template) && isset($template['success']) && $template['success'] == false) {
            return $template;
        }
        if (empty($template->driver)) {
            return array('success'=>false, 'message'=>'Unspecified driver in device template.');
        }
        $driverid = $template->driver;
        
        if (empty($options['ctrlid'])) {
            return array('success'=>false, 'message'=>'Unspecified controller ID in device options.');
        }
        $ctrlid = intval($options['ctrlid']);
        $ctrl = $this->ctrl->get($ctrlid);
        
        $settings = "";
        if (isset($template->options)) {
            if (isset($template->scan->settings)) {
                $settings = $template->scan->settings;
                
                foreach ($template->options as $option) {
                    if (strpos($settings, "<$option->id>") !== false) {
                        if (isset($options[$option->id])) {
                            $settings = str_replace("<$option->id>", $options[$option->id], $settings);
                        }
                        else if (isset($option->default)) {
                            $settings = str_replace("<$option->id>", $option->default, $settings);
                        }
                    }
                }
            }
            else {
                $settings = $this->encode_options($template, $options);
            }
        }
        
        if ($this->redis) {
            $this->redis->hMSet("user#$userid:device:$type", $options); // Temporary availability of auth for device ip address
            $this->redis->expire("user#$userid:device:$type", 600);     // Expire after 10 minutes
        }
        try {
            return $this->decode_progress($userid, $ctrlid, $type, $template,
                $this->ctrl->device($ctrl)->scan_start($driverid, $settings));
            
        } catch(ControllerException $e) {
            return $e->getResult();
        }
    }

    public function scan_progress($type) {
        global $session;
        $userid = $session['userid'];
        
        $result = $this->get($type);
        if (is_array($result) && isset($result['success']) && $result['success'] == false) {
            return $result;
        }
        if (empty($result->driver)) {
            return array('success'=>false, 'message'=>'Unspecified driver in device template.');
        }
        $driverid = $result->driver;
        
        $options = array();
        if (!$this->redis) {
            return array('success'=>false, 'message'=>'Unable to retrieve scan progress without redis enabled.');
        }
        if ($this->redis->exists("user#$userid:device:$type")) {
            $options = (array) $this->redis->hGetAll("user#$userid:device:$type");
        }
        if (empty($options['ctrlid'])) {
            return array('success'=>false, 'message'=>'Unspecified controller ID in device options.');
        }
        $ctrlid = intval($options['ctrlid']);
        $ctrl = $this->ctrl->get($ctrlid);
        try {
            return $this->decode_progress($userid, $ctrlid, $type, $result,
                $this->ctrl->device($ctrl)->scan_progress($driverid));
            
        } catch(ControllerException $e) {
            return $e->getResult();
        }
    }

    public function scan_cancel($type) {
        global $session;
        $userid = $session['userid'];
        
        $result = $this->get($type);
        if (is_array($result) && isset($result['success']) && $result['success'] == false) {
            return $result;
        }
        if (empty($result->driver)) {
            return array('success'=>false, 'message'=>'Unspecified driver in device template.');
        }
        $driverid = $result->driver;
        
        $options = array();
        if ($this->redis && $this->redis->exists("user#$userid:device:$type")) {
            $options = (array) $this->redis->hGetAll("user#$userid:device:$type");
        }
        if (empty($options['ctrlid'])) {
            return array('success'=>false, 'message'=>'Unspecified controller ID in device options.');
        }
        $ctrlid = intval($options['ctrlid']);
        $ctrl = $this->ctrl->get($ctrlid);
        try {
            return $this->ctrl->device($ctrl)->scan_cancel($driverid);
            
        } catch(ControllerException $e) {
            return $e->getResult();
        }
    }

}

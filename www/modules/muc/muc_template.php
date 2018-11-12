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

class MucTemplate extends DeviceTemplate
{
    const DEFAULT_DIR = "/opt/emonmuc/";
    
    private $ctrl;
    private $device;
    private $channel;

    function __construct(&$parent) {
        parent::__construct($parent);
        
        require_once "Modules/muc/muc_model.php";
        $this->ctrl = new Controller($this->mysqli, $this->redis);
        
        require_once "Modules/muc/Models/channel_model.php";
        require_once "Modules/channel/channel_model.php";
        $this->channel = new ChannelCache($this->ctrl, 
            new Channel($this->ctrl, $this->mysqli, $this->redis), $this->redis);
        
        require_once "Modules/muc/Models/device_model.php";
        require_once "Modules/channel/device_model.php";
        $this->device = new DeviceCache($this->ctrl, 
            new DeviceConnection($this->ctrl), $this->channel, $this->redis);
    }

    protected function load_template_list($userid) {
        $list = array();
        
        require_once "Modules/muc/Models/driver_model.php";
        $driver = new Driver($this->ctrl);
        $drivers = array();
        $registered = $driver->get_registered($userid, null);
        if (is_array($registered) && count($registered)>0 && !isset($registered['success'])) {
            foreach ($registered as $drv) {
                $drivers[] = $drv['id'];
            }
            
            $dir = $this->get_template_dir();
            $it = new RecursiveDirectoryIterator($dir);
            foreach (new RecursiveIteratorIterator($it) as $file) {
                if ($file->getExtension() == "json") {
                    $type = substr(pathinfo($file, PATHINFO_DIRNAME), strlen($dir)).'/'.pathinfo($file, PATHINFO_FILENAME);
                    
                    $result = $this->get_template($userid, $type);
                    if (is_array($result) && isset($result['success']) && $result['success'] == false) {
                        return $result;
                    }
                    if (empty($result->driver) || in_array($result->driver, $drivers)) {
                        $list[$type] = $result;
                    }
                }
            }
        }
        return $list;
    }

    public function get_template($userid, $type) {
        $file = $this->get_template_dir().$type.".json";
        if (file_exists($file)) {
            $template = json_decode(file_get_contents($file));
            if (json_last_error() == 0) {
                if (empty($template->options)) {
                    $template->options = array();
                }
                return $template;
            }
            return array('success'=>false, 'message'=>"Error reading template $type: ".json_last_error_msg());
        }
        return array('success'=>false, 'message'=>"Error reading template $type: file does not exist");
    }

    protected function get_template_dir() {
        global $muc_settings;
        if (isset($muc_settings) && isset($muc_settings['rootdir']) && $muc_settings['rootdir'] !== "") {
            $muc_template_dir = $muc_settings['rootdir'];
        }
        else {
            $muc_template_dir = self::DEFAULT_DIR;
        }
        if (substr($muc_template_dir, -1) !== "/") {
            $muc_template_dir .= "/";
        }
        return $muc_template_dir."lib/device/";
    }

    public function get_template_options($userid, $type) {
        $result = $this->get_template($userid, $type);
        if (!is_object($result)) {
            return $result;
        }
        $options = array();
        
        $ctrls = $this->ctrl->get_list($userid);
        if (count($ctrls) > 0) {
            $select = array();
            foreach ($ctrls as $ctrl) {
                $select[] = array('name'=>$ctrl['description'], 'value'=>$ctrl['id']);
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
        
        if (isset($result->options)) {
            $options = array_merge($options, (array) $result->options);
        }
        return $options;
    }

    public function prepare_template($device) {
        $userid = intval($device['userid']);
        
        $result = $this->get_template($userid, $device['type']);
        if (!is_object($result)) {
            return $result;
        }
        $prefix = $this->parse_prefix($device['nodeid'], $device['name'], $result);
        
        if (isset($result->feeds)) {
            $feeds = $result->feeds;
            $this->prepare_feeds($userid, $device['nodeid'], $prefix, $feeds);
        }
        else {
            $feeds = [];
        }
        
        if (isset($result->channels)) {
            $channels = $result->channels;
            $this->prepare_inputs($userid, $device['nodeid'], $prefix, $channels);
        }
        else {
            $channels = [];
        }
        
        if (!empty($feeds)) {
            $this->prepare_feed_processes($userid, $prefix, $feeds, $channels);
        }
        if (!empty($channels)) {
            $this->prepare_input_processes($userid, $prefix, $feeds, $channels);
        }
        
        return array('success'=>true, 'feeds'=>$feeds, 'inputs'=>$channels);
    }

    public function init_template($device, $template) {
        $userid = intval($device['userid']);
        
        if (empty($template)) {
            $result = $this->prepare_template($device);
            if (isset($result['success']) && $result['success'] == false) {
                return $result;
            }
            $template = $result;
        }
        if (!is_object($template)) $template = (object) $template;
        
        $result = $this->get_template($userid, $device['type']);
        if (!is_object($result)) {
            return $result;
        }
        
        $options = $device['options'];
        if (empty($options['ctrlid'])) {
            return array('success'=>false, 'message'=>'Unspecified controller ID in device options.');
        }
        $ctrlid = intval($options['ctrlid']);
        $driverid = isset($result->driver) ? $result->driver : null;
        
        $devices = $this->parse_devices($result, $options);
        $response = $this->create_devices($userid, $ctrlid, $driverid, $device['name'], $devices);
        if (isset($response['success']) && $response['success'] == false) {
            return $response;
        }
        
        if (isset($template->inputs)) {
            $channels = $this->parse_channels($result, $options, $template->inputs);
            $response = $this->create_channels($userid, $ctrlid, $devices, $channels);
            if (isset($response['success']) && $response['success'] == false) {
                return $response;
            }
            $this->create_inputs($userid, $channels);
        }
        else {
            $channels = array();
        }
        
        if (isset($template->feeds)) {
            $feeds = $template->feeds;
            $this->create_feeds($userid, $feeds);
        }
        else {
            $feeds = array();
        }
        
        if (!empty($feeds)) {
            $this->create_feed_processes($userid, $feeds, $channels);
        }
        if (!empty($channels)) {
            $this->create_input_processes($userid, $feeds, $channels);
        }
        
        return array('success'=>true, 'message'=>'Device initialized');
    }

    private function parse_devices($result, $parameters) {
        return $this->parse_configs($result, $parameters, $result->devices, 'device');
    }

    // Create the channels
    private function create_devices($userid, $ctrlid, $driverid, $id, &$devices) {
        if (empty($devices)) {
            return array('success'=>false, 'message'=>'Bad device template. Undefined devices');
        }
        
        foreach ($devices as $d) {
            if (isset($d->name)) {
                $d->id = $d->name;
            }
            else {
                $d->id = $id;
            }
            if (empty($d->driver)) {
                $d->driver = $driverid;
            }
            
            $result = $this->device->create($userid, $ctrlid, $d->driver, json_encode($d));
            if (isset($result['success']) && $result['success'] == false) {
                if (strpos($result['message'], 'already exists') !== false) {
                    return array('success'=>true, 'message'=>'Devices already created');
                }
                return $result;
            }
        }
        return array('success'=>true, 'message'=>'Devices successfully created');
    }

    private function parse_channels($result, $parameters, &$channels) {
        return $this->parse_configs($result, $parameters, $channels, 'channel');
    }

    // Create the channels
    private function create_channels($userid, $ctrlid, $devices, &$channels) {
        foreach($channels as $id=>$c) {
            $configs = (array) $c;
            $configs['id'] = $c->name;
            
            if (empty($c->logging)) {
                if (empty($c->logging->loggingInterval)) {
                    // Remove the channel from list to avoid the unnecessary input creation
                    unset($channels[$id]);
                }
                $logging = array();
            }
            else if (isset($c->logging)) {
                $logging = (array) $c->logging;
            }
            $logging['nodeid'] = $c->node;
            $configs['logging'] = $logging;
            
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
                $deviceid = $devices{0}->id;
                $driverid = $devices{0}->driver;
            }
            
            $result = $this->channel->create($userid, $ctrlid, $driverid, $deviceid, json_encode($configs));
            if (isset($result['success']) && $result['success'] == false && 
                    strpos($result['message'], 'already exists') === false) {
                
                return $result;
            }
        }
        return array('success'=>true, 'message'=>'Channels successfully created');
    }

    private function parse_configs($template, $parameters, &$configs, $type) {
        foreach ($configs as &$config) {
            foreach (array('address', 'settings') as $key) {
                if (isset($config->$key)) {
                    foreach ($template->options as $option) {
                        if (isset($option->syntax) && $option->syntax == $type.ucfirst($key)) {
                            $id = $option->id;
                            $config->$key = str_replace("<$id>", $parameters[$id], $config->$key);
                        }
                    }
                }
                else {
                    $config->$key = $this->parse_options($template, $parameters, $type.ucfirst($key));
                }
            }
        }
        return $configs;
    }

    private function parse_options($template, $parameters, $key) {
        $result = "";
        
        // Iterate all options as configured in the template and parse them accordingly, 
        // if they exist in the passed key value options array
        foreach ($template->options as $option) {
            $value = $parameters[$option->id];
            
            $types = explode(',', $option->syntax);
            foreach($types as $type) {
                if ($option->syntax !== $key) {
                    continue;
                }
                if (isset($template->syntax) && isset($template->syntax->$type)) {
                    $syntax = $template->syntax->$type;
                }
                else {
                    $syntax = true;
                }
                
                // Default syntax is <key1>:<value1>,<key2>:<value2>,...
                if (!empty($result)) {
                    $result .= isset($syntax->separator) ? $syntax->separator : ',';
                }
                
                if (isset($syntax->keyValue) && !$syntax->keyValue) {
                    $result .= $value;
                }
                else {
                    $assignment = isset($syntax->assignment) ? $syntax->assignment : ':';
                    $result .= $option->id.$assignment.$value;
                }
            }
        }
        return $result;
    }
}

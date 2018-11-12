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

require_once "Modules/device/device_scan.php";

class MucScan extends DeviceScan
{
    const DEFAULT_DIR = "/opt/emonmuc/";

    const DEVICE_ADDRESS = "deviceAddress";
    const DEVICE_SETTINGS = "deviceSettings";
    const DEVICE_SCAN_SETTINGS = "deviceScanSettings";

    protected $ctrl;
    protected $device;

    // Module required constructor, receives parent as reference
    public function __construct(&$parent) {
        parent::__construct($parent);
        
        require_once "Modules/muc/muc_model.php";
        $this->ctrl = new Controller($this->mysqli, $this->redis);
        
        require_once "Modules/muc/Models/device_model.php";
        $this->device = new DeviceConnection($this->ctrl);
    }

    public function start($userid, $type, $options) {
        $result = $this->get_template($type);
        if (is_array($result) && isset($result['success']) && $result['success'] == false) {
            return $result;
        }
        if (empty($result->driver)) {
            return array('success'=>false, 'message'=>'Unspecified driver in device template.');
        }
        $driverid = $result->driver;
        
        if (empty($options['ctrlid'])) {
            return array('success'=>false, 'message'=>'Unspecified controller ID in device options.');
        }
        $ctrlid = intval($options['ctrlid']);
        
        $settings = "";
        if (isset($result->scan->settings)) {
            $settings = $result->scan->settings;
            
            foreach ($result->options as $option) {
                if (isset($option->syntax) && $option->syntax == self::DEVICE_SCAN_SETTINGS) {
                    $id = $option->id;
                    $settings = str_replace("<$id>", $options[$id], $settings);
                }
            }
        }
        else {
            $settings = $this->encode_options($result, $options);
        }
        
        if ($this->redis) {
            $this->redis->hMSet("user#$userid:device:$type", $options); // Temporary availability of auth for device ip address
            $this->redis->expire("user#$userid:device:$type", 600);     // Expire after 10 minutes
        }
        return $this->parse_progress($userid, $ctrlid, $type, $result, 
            $this->device->scan_start($ctrlid, $driverid, $settings));
    }

    public function progress($userid, $type) {
        $result = $this->get_template($type);
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
        
        return $this->parse_progress($userid, $ctrlid, $type, $result, 
            $this->device->scan_progress($ctrlid, $driverid));
    }

    public function cancel($userid, $type) {
        $result = $this->get_template($type);
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
        
        return $this->device->scan_cancel($ctrlid, $driverid);
    }

    protected function get_template($type) {
        $file = $this->get_template_dir().$type.".json";
        if (!file_exists($file)) {
            return array('success'=>false, 'message'=>"Error reading template ".$type.": file does not exist");
        }
        $template = json_decode(file_get_contents($file));
        if (json_last_error() != 0) {
            return array('success'=>false, 'message'=>"Error reading template ".$type.":".json_last_error_msg());
        }
        return $template;
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

    private function parse_progress($userid, $ctrlid, $type, $template, $result) {
        if (isset($result['success']) && $result['success'] == false) {
            return $result;
        }
        
        $devices = array();
        foreach($result['devices'] as $device) {
            $devices[] = array(
                'userid'=>$userid,
                'name'=>$device['id'],
                'description'=>$device['description'],
                'type'=>$type,
                'options'=>$this->decode_options($ctrlid, $template, $device['address'], $device['settings']),
            );
        }
        $result['devices'] = $devices;
        
        return $result;
    }

    private function encode_options($template, $parameters) {
        $result = "";
        
        // Iterate all options as configured in the template and encode them accordingly,
        // if they exist in the passed key value options array
        foreach ($template->options as $option) {
            if (empty($option->syntax) || empty($parameters[$option->id])) {
                continue;
            }
            $value = $parameters[$option->id];
            
            $types = explode(',', $option->syntax);
            foreach($types as $type) {
                if ($type !== self::DEVICE_SCAN_SETTINGS || empty($template->syntax) || empty($template->syntax->$type)) {
                    continue;
                }
                $syntax = $template->syntax->$type;
                
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

    private function decode_options($ctrlid, $template, $address, $settings) {
        $result = array('ctrlid'=>$ctrlid);
        
        // Iterate all options as configured in the template and decode them accordingly,
        // if they exist in the passed settings string
        foreach (array(self::DEVICE_ADDRESS, self::DEVICE_SETTINGS) as $type) {
            $options = array();
            foreach ($template->options as $option) {
                if (empty($option->syntax)) {
                    continue;
                }
                $types = explode(',', $option->syntax);
                foreach($types as $t) {
                    if ($t === $type) $options[] = $option;
                }
            }
            if (isset($template->syntax) && isset($template->syntax->$type)) {
                $syntax = $template->syntax->$type;
            }
            else {
                $syntax = true;
            }
            
            $separator = isset($syntax->separator) ? $syntax->separator : ',';
            if ($type == self::DEVICE_ADDRESS) {
                $arr = explode($separator, $address);
            }
            else if ($type == self::DEVICE_SETTINGS) {
                $arr = explode($separator, $settings);
            }
            
            for($i=0; $i<count($options); $i++) {
                if (isset($syntax->keyValue) && !$syntax->keyValue) {
                    $result[$options[$i]->id] = $arr[$i];
                }
                else {
                    $assignment = isset($syntax->assignment) ? $syntax->assignment : ':';
                    $pair = explode($assignment, $arr[$i]);
                    
                    $result[$pair[0]] = $pair[1];
                }
            }
        }
        return $result;
    }
}

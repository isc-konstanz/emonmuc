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
require_once "Modules/muc/muc_model.php";

class MucScan extends DeviceScan {
    const DIR_DEFAULT = "/var/lib/emonmuc/";

    const DEVICE_ADDRESS = "deviceAddress";
    const DEVICE_SETTINGS = "deviceSettings";
    const DEVICE_SCAN_SETTINGS = "deviceScanSettings";

    private $ctrl;

    // Module required constructor, receives parent as reference
    public function __construct(&$parent) {
        parent::__construct($parent);
        $this->ctrl = new Controller($this->mysqli, $this->redis);
    }

    public function start($userid, $type, $options) {
        $template = $this->get_template($type);
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
            return $this->parse_progress($userid, $ctrlid, $type, $template,
                $this->ctrl->device($ctrl)->scan_start($driverid, $settings));
            
        } catch(ControllerException $e) {
            return $e->getResult();
        }
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
        $ctrl = $this->ctrl->get($ctrlid);
        try {
            return $this->parse_progress($userid, $ctrlid, $type, $result,
                $this->ctrl->device($ctrl)->scan_progress($driverid));
            
        } catch(ControllerException $e) {
            return $e->getResult();
        }
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
        $ctrl = $this->ctrl->get($ctrlid);
        try {
            return $this->ctrl->device($ctrl)->scan_cancel($driverid);
            
        } catch(ControllerException $e) {
            return $e->getResult();
        }
    }

    protected function get_template($type) {
        $file = $this->get_template_dir().$type.".json";
        if (!file_exists($file)) {
            return array('success'=>false, 'message'=>"Error reading template ".$type.": $file does not exist");
        }
        $template = json_decode(file_get_contents($file));
        if (json_last_error() != 0) {
            return array('success'=>false, 'message'=>"Error reading template ".$type.":".json_last_error_msg());
        }
        return $template;
    }

    protected function get_template_dir() {
        global $muc_settings;
        if (isset($muc_settings) && isset($muc_settings['libdir']) && $muc_settings['libdir'] !== "") {
            $muc_template_dir = $muc_settings['libdir'];
        }
        else {
            $muc_template_dir = self::DIR_DEFAULT;
        }
        if (substr($muc_template_dir, -1) !== "/") {
            $muc_template_dir .= "/";
        }
        return $muc_template_dir."device/";
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

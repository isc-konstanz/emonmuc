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

require_once "Modules/device/device_thing.php";

class MucThing extends DeviceThing
{
    const DEFAULT_DIR = "/opt/emonmuc/";

    protected $ctrl;
    protected $channel;

    // Module required constructor, receives parent as reference
    public function __construct(&$parent) {
        parent::__construct($parent);
        
        require_once "Modules/muc/muc_model.php";
        $this->ctrl = new Controller($this->mysqli, $this->redis);
        
        require_once "Modules/muc/Models/channel_model.php";
        $this->channel = new Channel($this->ctrl, $this->mysqli, $this->redis);
    }

    public function get_item_list($device) {
        $file = $this->get_template_dir().$device['type'].".json";
        if (!file_exists($file)) {
            return array('success'=>false, 'message'=>"Error reading template ".$device['type'].": file does not exist");
        }
        $template = json_decode(file_get_contents($file));
        if (json_last_error() != 0) {
            return array('success'=>false, 'message'=>"Error reading template ".$device['type'].":".json_last_error_msg());
        }
        
        if (empty($device['options']['ctrlid'])) {
            return array('success'=>false, 'message'=>'Unspecified controller ID in device options.');
        }
        $ctrlid = intval($device['options']['ctrlid']);
        
        $prefix = $this->parse_prefix($device['nodeid'], $device['name'], $template);
        
        $items = array();
        for ($i=0; $i<count($template->items); $i++) {
            $item = (array) $template->items[$i];
            
            if (isset($item['mapping'])) {
                foreach($item['mapping'] as &$mapping) {
                    if (isset($mapping->channel)) {
                        $channelid = $prefix.$mapping->channel;
                        
                        $configs = [];
                        foreach($template->channels as $c) {
                            if ($c->name == $mapping->channel) {
                                if (isset($c->configs->valueType)) {
                                    $configs['valueType'] = $c->configs->valueType;
                                }
                            }
                        }
                        unset($mapping->channel);
                        
                        $mapping = array_merge(array('ctrlid'=>$ctrlid, 'channelid'=>$channelid), $configs, (array) $mapping);
                    }
                }
            }
            if (isset($item['input'])) {
                $inputid = $this->get_input_id($device['userid'], $device['nodeid'], $prefix, $item['input'], $template->channels);
                if ($inputid == false) {
                    $this->log->error("get_item_list() failed to find input of item '".$item['id']."' in template: ".$device['type']);
                    continue;
                }
                unset($item['input']);
                $item = array_merge($item, array('inputid'=>$inputid));
            }
            if (isset($item['feed'])) {
                $feedid = $this->get_feed_id($device['userid'], $prefix, $item['feed']);
                if ($feedid == false) {
                    $this->log->error("get_item_list() failed to find feed of item '".$item['id']."' in template: ".$device['type']);
                    continue;
                }
                unset($item['feed']);
                $item = array_merge($item, array('feedid'=>$feedid));
            }
            
            $items[] = $item;
        }
        return $items;
    }

    public function set_item($itemid, $mapping) {
        if (isset($mapping['ctrlid']) && isset($mapping['channelid']) && isset($mapping['value'])) {
            $ctrlid = intval($mapping['ctrlid']);
            
            if (isset($mapping['valueType'])) {
                $valueType = $mapping['valueType'];
            }
            else $valueType = null;
            
            if (isset($mapping['write']) && !$mapping['write']) {
                $result = $this->channel->set($ctrlid, $mapping['channelid'], $mapping['value'], $valueType);
            }
            else {
                $result = $this->channel->write($ctrlid, $mapping['channelid'], $mapping['value'], $valueType);
            }
            if (isset($result['success']) && $result['success'] == false) {
                return $result;
            }
            return array('success'=>true, 'message'=>"Item value set");
        }
        return array('success'=>false, 'message'=>"Error while seting item value");
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

    protected function get_ctrl_id($userid, $name, $driver) {
        require_once "Modules/muc/Models/device_model.php";
        $device = new DeviceConnection($this->ctrl);
        
        $devices = $device->get_list($userid);
        foreach($devices as $d) {
            if ($d['id'] == $name && $d['driverid'] == $driver) {
                return intval($d['ctrlid']);
            }
        }
        return null;
    }
}

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
require_once "Modules/muc/muc_model.php";

class MucThing extends DeviceThing {
    const DIR_DEFAULT = "/var/lib/emonmuc/";

    private $ctrl;

    // Module required constructor, receives parent as reference
    public function __construct(&$parent) {
        parent::__construct($parent);
        $this->ctrl = new Controller($this->mysqli, $this->redis);
    }

    public function get_item_list($device) {
        $template = $this->get_template($device);
        if (!is_object($template)) {
            return $template;
        }
        if (empty($device['options']['ctrlid'])) {
            return array('success'=>false, 'message'=>'Unspecified controller ID in device options.');
        }
        $ctrlid = intval($device['options']['ctrlid']);
        
        $items = array();
        for ($i=0; $i<count($template->items); $i++) {
            $item = (array) $template->items[$i];
            
            if (isset($item['mapping'])) {
                foreach($item['mapping'] as &$mapping) {
                    if (isset($mapping->channel)) {
                        $channelid = $mapping->channel;
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
                $inputid = $this->get_input_id($device['userid'], $device['nodeid'], $item['input'], $template->channels);
                if ($inputid == false) {
                    $this->log->error("get_item_list() failed to find input of item '".$item['id']."' in template: ".$device['type']);
                    continue;
                }
                unset($item['input']);
                $item = array_merge($item, array('inputid'=>$inputid));
            }
            if (isset($item['feed'])) {
                $feedid = $this->get_feed_id($device['userid'], $item['feed']);
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
        if (empty($mapping['ctrlid']) || empty($mapping['channelid']) || empty($mapping['value'])) {
            return array('success'=>false, 'message'=>"Error while seting item value");
        }
        $ctrlid = intval($mapping['ctrlid']);
        $ctrl = $this->ctrl->get($ctrlid);
        
        if (isset($mapping['valueType'])) {
            $valueType = $mapping['valueType'];
        }
        else $valueType = null;
        
        try {
            if (isset($mapping['write']) && !$mapping['write']) {
                $this->ctrl->channel($ctrl)->set($mapping['channelid'], $mapping['value'], $valueType);
            }
            else {
                $this->ctrl->channel($ctrl)->write($mapping['channelid'], $mapping['value'], $valueType);
            }
        } catch(ControllerException $e) {
            return array('success'=>false, 'message'=>"Error while seting item value: ".$e->getMessage());
        }
        return array('success'=>true, 'message'=>"Item value set");
    }

    protected function get_template($device) {
        $file = $this->get_template_dir().$device['type'].".json";
        if (!file_exists($file)) {
            return array('success'=>false, 'message'=>"Error reading template ".$device['type'].": $file does not exist");
        }
        $content = file_get_contents($file);
        $template = json_decode($content);
        if (json_last_error() != 0) {
            return array('success'=>false, 'message'=>"Error reading template ".$device['type'].": ".json_last_error_msg());
        }
        $options = isset($device['options']) ? (array) $device['options'] : array();
        
        if (strpos($content, '*') !== false) {
            $separator = isset($options['sep']) ? $options['sep'] : self::SEPARATOR;
            $content = str_replace("*", $separator, $content);
        }
        if (strpos($content, '<node>') !== false) {
            $content = str_replace("<node>", $device['nodeid'], $content);
        }
        if (isset($template->options)) {
            foreach ($template->options as $option) {
                if (strpos($content, "<$option->id>") !== false) {
                    if (isset($options[$option->id])) {
                        $content = str_replace("<$option->id>", $options[$option->id], $content);
                    }
                    else if (isset($option->default)) {
                        $content = str_replace("<$option->id>", $option->default, $content);
                    }
                }
            }
        }
        
        $template = json_decode($content);
        if (json_last_error() != 0) {
            return array('success'=>false, 'message'=>"Error preparing type ".$device['type'].": ".json_last_error_msg());
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
}

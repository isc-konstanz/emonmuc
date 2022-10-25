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
    const DIR_DEFAULT = "/var/opt/emonmuc/";

    private $ctrl;

    // Module required constructor, receives parent as reference
    public function __construct(&$parent) {
        parent::__construct($parent);
        $this->ctrl = new Controller($this->mysqli, $this->redis);
    }

    protected function parse_item($thing, &$item, $template) {
        if (isset($item['mapping'])) {
            foreach($item['mapping'] as &$mapping) {
                $this->parse_item_mapping($thing, $item, $mapping, $template);
            }
        }
        if (isset($item['channel'])) {
            $this->parse_item_channel($thing, $item, $template);
        }
        if (isset($item['input'])) {
            if (empty($template->inputs) && !empty($template->channels)) {
                $template->inputs = $template->channels;
            }
            $this->parse_item_input($thing, $item, $template);
        }
        if (isset($item['feed'])) {
            $this->parse_item_feed($thing, $item, $template);
        }
        return $item;
    }

    protected function parse_item_mapping($thing, &$item, &$mapping, $template) {
        $configs = $this->device->get_configs($thing);
        if (empty($configs['ctrlid'])) {
            throw new DeviceException('Unspecified controller ID in thing configs.');
        }
        $ctrlid = intval($configs['ctrlid']);
        
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

    protected function parse_item_channel($thing, &$item, $template) {
        $configs = $this->device->get_configs($thing);
        if (empty($configs['ctrlid'])) {
            throw new DeviceException('Unspecified controller ID in thing configs.');
        }
        $ctrlid = intval($configs['ctrlid']);
        $channelid = $item['channel'];
        unset($item['channel']);
        
        $item = array_merge(array('ctrlid'=>$ctrlid, 'channelid'=>$channelid), (array) $item);
    }

    protected function get_item_value($item) {
        $value = null;
        if (isset($item['channelid'])) {
            if (empty($item['ctrlid']) || empty($item['channelid'])) {
                return array('success'=>false, 'message'=>"Error while getting item value");
            }
            $ctrlid = intval($item['ctrlid']);
            $ctrl = $this->ctrl->get($ctrlid);
            $channel = $this->ctrl->channel($ctrl);
            
            $value = $channel->get($item['channelid'])['value'];
        }
        if (isset($item['inputid'])) {
            require_once "Modules/input/input_model.php";
            $input = new Input($this->mysqli, $this->redis, null);
            
            $value = $input->get_last_value($item['inputid']);
        }
        if (isset($item['feedid'])) {
            global $settings;
            require_once "Modules/feed/feed_model.php";
            $feed = new Feed($this->mysqli, $this->redis, $settings['feed']);
            
            $value = $feed->get_value($item['feedid']);
        }
        return $value;
    }

    protected function set_item($itemid, $mapping) {
        if (empty($mapping['ctrlid']) || empty($mapping['channelid']) || !isset($mapping['value'])) {
            return array('success'=>false, 'message'=>"Error while setting item value");
        }
        $ctrlid = intval($mapping['ctrlid']);
        $ctrl = $this->ctrl->get($ctrlid);
        
        if (isset($mapping['valueType'])) {
            $valueType = $mapping['valueType'];
        }
        else $valueType = 'DOUBLE';
        
        try {
            if (isset($mapping['write']) && !$mapping['write']) {
                $this->ctrl->channel($ctrl)->set($mapping['channelid'], $mapping['value'], $valueType);
            }
            else {
                $this->ctrl->channel($ctrl)->write($mapping['channelid'], $mapping['value'], $valueType);
            }
        } catch(ControllerException $e) {
            return array('success'=>false, 'message'=>"Error while setting item value: ".$e->getMessage());
        }
        return array('success'=>true, 'message'=>"Item value set");
    }

}


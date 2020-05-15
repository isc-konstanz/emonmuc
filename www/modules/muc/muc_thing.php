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
            // TODO: implement channel parsing
        }
        if (isset($item['input'])) {
            if (isset($template->channels)) {
                $tmp = $template->channels;
                unset($template->channels);
                $template->inputs = $tmp;
            }
            $this->parse_item_input($thing, $item, $template);
        }
        if (isset($item['feed'])) {
            $this->parse_item_feed($thing, $item, $template);
        }
        return $item;
    }

    protected function parse_item_mapping($thing, &$item, &$mapping, $template) {
        if (empty($thing['options']['ctrlid'])) {
            throw new ThingException('Unspecified controller ID in thing configs.');
        }
        $ctrlid = intval($thing['options']['ctrlid']);
        
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

    public function get_item_list($thing) {
        $template = $this->get_template($thing);
        if (!is_object($template)) {
            return $template;
        }
        if (empty($thing['options']['ctrlid'])) {
            return array('success'=>false, 'message'=>'Unspecified controller ID in device options.');
        }
        $ctrlid = intval($thing['options']['ctrlid']);
        
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
                $nodeid = isset($item['node']) ? $item['node'] : $thing['nodeid'];
                $inputid = $this->get_input_id($thing['userid'], $nodeid, $item['input'], $template->channels);
                if ($inputid == false) {
                    $this->log->error("get_item_list() failed to find input \"$nodeid:".$item['input']."\" of item '".$item['id']."' in template: ".$thing['type']);
                    continue;
                }
                unset($item['input']);
                $item = array_merge($item, array('inputid'=>$inputid));
            }
            if (isset($item['feed'])) {
                $feedid = $this->get_feed_id($thing['userid'], $item['feed']);
                if ($feedid == false) {
                    $this->log->error("get_item_list() failed to find feed of item '".$item['id']."' in template: ".$thing['type']);
                    continue;
                }
                unset($item['feed']);
                $item = array_merge($item, array('feedid'=>$feedid));
            }
            
            $items[] = $item;
        }
        return $items;
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


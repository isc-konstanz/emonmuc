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

class Driver
{
    private $ctrl;
    private $log;

    public function __construct($ctrl) {
        $this->ctrl = $ctrl;
        $this->log = new EmonLogger(__FILE__);
    }

    public function create($ctrlid, $id, $configs) {
        $ctrlid = intval($ctrlid);

        $configs = (array) json_decode($configs);
        $driver = $this->parse_driver($id, $configs);
        
        $response = $this->ctrl->request($ctrlid, 'drivers/'.$id, 'POST', array('configs' => $driver));
        if (isset($response['success']) && $response['success'] == false) {
            return $response;
        }
        return array('success'=>true, 'message'=>'Driver successfully added');
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
        
        $drivers = array();
        foreach($ctrls as $ctrl) {
            // Get drivers of all registered MUCs and add identifying location description and parse their configuration
            $response = $this->ctrl->request($ctrl['id'], 'drivers', 'GET', array('details' => 'true'));
            if (isset($response['drivers'])) {
                foreach($response['drivers'] as $driver) {
                    $drivers[] = $this->get_driver($ctrl, $driver);
                }
            }
        }
        return $drivers;
    }

    public function get_registered($userid, $ctrlid) {
        if (isset($ctrlid)) {
            $ctrlid = intval($ctrlid);
            $ctrls = array();
            $ctrls[] = $this->ctrl->get($ctrlid);
        }
        else {
            $userid = intval($userid);
            $ctrls = $this->ctrl->get_list($userid);
        }
        
        $drivers = array();
        foreach($ctrls as $ctrl) {
            $result = $this->ctrl->request($ctrl['id'], 'drivers/running', 'GET', null);
            if (isset($result['success']) && $result['success'] == false) {
                return $result;
            }
            foreach($result['drivers'] as $driver) {
                $drivers[] = $this->get_description($ctrl, $driver);
            }
        }
        return $drivers;
    }

    public function get_configured($userid, $ctrlid) {
        if (isset($ctrlid)) {
            $ctrlid = intval($ctrlid);
            $ctrls = array();
            $ctrls[] = $this->ctrl->get($ctrlid);
        }
        else {
            $userid = intval($userid);
            $ctrls = $this->ctrl->get_list($userid);
        }
        
        $drivers = array();
        foreach($ctrls as $ctrl) {
            $result = $this->ctrl->request($ctrl['id'], 'drivers', 'GET', array('details' => 'true'));
            if (isset($result['success']) && $result['success'] == false) {
                return $result;
            }
            foreach($result['drivers'] as $driver) {
                $drivers[] = $this->get_description($ctrl, $driver);
            }
        }
        return $drivers;
    }

    public function get_unconfigured($userid, $ctrlid) {
        if (isset($ctrlid)) {
            $ctrlid = intval($ctrlid);
            $ctrls = array();
            $ctrls[] = $this->ctrl->get($ctrlid);
        }
        else {
            $userid = intval($userid);
            $ctrls = $this->ctrl->get_list($userid);
        }
        
        $drivers = array();
        foreach($ctrls as $ctrl) {
            $result = $this->ctrl->request($ctrl['id'], 'drivers', 'GET', null);
            if (isset($result['success']) && $result['success'] == false) {
                return $result;
            }
            $configured = $result['drivers'];
            
            $result = $this->get_registered($userid, $ctrl['id']);
            if (isset($result['success']) && $result['success'] == false) {
                return $result;
            }
            foreach($result as $driver) {
                if (!in_array($driver['id'], $configured)) {
                    $drivers[] = $this->get_description($ctrl, $driver);
                }
            }
        }
        return $drivers;
    }

    private function get_description($ctrl, $desc) {
        $driver = array(
            'userid'=>$ctrl['userid'],
            'ctrlid'=>$ctrl['id'],
            'ctrl'=>$ctrl['description'],
            'id'=>$desc['id']
        );
        
        if (isset($desc['name'])) {
            $driver['name'] = $desc['name'];
        }
        else $driver['name'] = $desc['id'];
        
        if (isset($desc['description'])) {
            $driver['description'] = $desc['description'];
        }
        else $driver['description'] = "";
        
        return $driver;
    }

    public function info($ctrlid, $id) {
        $ctrlid = intval($ctrlid);

        $response = $this->ctrl->request($ctrlid, 'drivers/'.$id.'/infos/options', 'GET', array('filter' => 'driver'));
        if (isset($response['success']) && $response['success'] == false) {
            return $response;
        }
        return $response['infos'];
    }

    public function get($ctrlid, $id) {
        $ctrlid = intval($ctrlid);
        
        $ctrl = $this->ctrl->get($ctrlid);
        $response = $this->ctrl->request($ctrlid, 'drivers/'.$id, 'GET', array('details' => 'true'));
        if (isset($response['success']) && $response['success'] == false) {
            return $response;
        }
        return $this->get_driver($ctrl, $response);
    }

    private function get_driver($ctrl, $details) {
        $driver = array(
            'userid'=>$ctrl['userid'],
            'ctrlid'=>$ctrl['id'],
            'ctrl'=>$ctrl['description'],
            'id'=>$details['id']
        );
        $driver['name'] = isset($details['name']) ? $details['name'] : '';
        
        $disabled = false;
        $configs = $details['configs'];
        if (count($configs) > 0) {
            if (isset($configs['disabled'])) {
                $disabled = $configs['disabled'];
                unset($configs['disabled']);
            }
            $driver['configs'] = $configs;
        }
        $driver['devices'] = isset($details['devices']) ? $details['devices'] : array();
        
        $driver['disabled'] = $disabled;
        $driver['running'] = $details['running'];
        
        return $driver;
    }

    private function parse_driver($id, $configs) {
        $driver = array( 'id' => $id);
        
        if (isset($configs['configs'])) {
            $driverconfigs = (array) $configs['configs'];
            
            if (isset($driverconfigs['samplingTimeout'])) $driver['samplingTimeout'] = $driverconfigs['samplingTimeout'];
            if (isset($driverconfigs['connectRetryInterval'])) $driver['connectRetryInterval'] = $driverconfigs['connectRetryInterval'];
        }
        
        if (isset($configs['disabled'])) $driver['disabled'] = $configs['disabled'];
        
        return $driver;
    }

    public function update($ctrlid, $id, $details) {
        $ctrlid = intval($ctrlid);
        
        $details = (array) json_decode($details);
        $configs = $this->parse_driver($id, $details);
        
        $response = $this->ctrl->request($ctrlid, 'drivers/'.$id.'/configs', 'PUT', array('configs' => $configs));
        if (isset($response['success']) && $response['success'] == false) {
            return $response;
        }
        return array('success'=>true, 'message'=>'Driver successfully updated');
    }

    public function delete($ctrlid, $id) {
        $ctrlid = intval($ctrlid);
        
        $response = $this->ctrl->request($ctrlid, 'drivers/'.$id, 'DELETE', null);
        if (isset($response['success']) && $response['success'] == false) {
            return $response;
        }
        return array('success'=>true, 'message'=>'Driver successfully removed');
    }
}
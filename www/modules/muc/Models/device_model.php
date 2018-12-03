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

class DeviceConnection
{
    private $ctrl;
    private $log;

    public function __construct($ctrl) {
        $this->ctrl = $ctrl;
        $this->log = new EmonLogger(__FILE__);
    }

    public function create($ctrlid, $driverid, $device) {
        $ctrlid = intval($ctrlid);
        
        $device = (array) json_decode($device, true);
        
        $id = $device['id'];
        if (preg_replace('/[^\p{N}\p{L}\-\_\.\:\/]/u', '', $id) != $id) {
            return array('success'=>false, 'message'=>"Device key must only contain a-z A-Z 0-9 - _ . : and / characters");
        }
        
        // Check if the specified driver is registered already and add it, if necessary
        require_once "Modules/muc/Models/driver_model.php";
        $driver = new Driver($this->ctrl);
        
        $response = $driver->get_configured(null, $ctrlid);
        if (isset($response['success']) && $response['success'] == false) {
            return $response;
        }
        else if (!in_array($driverid, $response)) {
            $driver->create($ctrlid, $driverid, "{}");
        }
        
        $configs = $this->parse_configs($id, $device);
        $data = array(
            'driver' => $driverid,
            'configs' => $configs
        );
        
        $response = $this->ctrl->request($ctrlid, 'devices/'.urlencode($id), 'POST', $data);
        if (isset($response['success']) && $response['success'] == false) {
            return $response;
        }
        $device = array(
            'id' => $id,
            'driver' => array('id' => $driverid),
            'configs' => $configs,
            'state' => 'LOADING'
        );
        return array('success'=>true, 'message'=>'Device successfully added',
            'device'=>$this->get_device($this->ctrl->get($ctrlid), $device)
        );
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
        
        $devices = array();
        foreach($ctrls as $ctrl) {
            // Get drivers of all registered MUCs and add identifying location description
            $response = $this->ctrl->request($ctrl['id'], 'devices', 'GET', array('details' => 'true'));
            if (isset($response['devices'])) {
                foreach($response['devices'] as $device) {
                    $devices[] = $this->get_device($ctrl, $device);
                }
            }
        }
        return $devices;
    }

    public function get_states($userid, $ctrlid) {
        if (isset($ctrlid)) {
            $ctrlid = intval($ctrlid);
            $ctrls = array();
            $ctrls[] = $this->ctrl->get($ctrlid);
        }
        else {
            $userid = intval($userid);
            $ctrls = $this->ctrl->get_list($userid);
        }
        
        $states = array();
        foreach($ctrls as $ctrl) {
            // Get drivers of all registered MUCs and add identifying location description
            $response = $this->ctrl->request($ctrl['id'], 'devices/states', 'GET', null);
            if (isset($response['states'])) {
                foreach($response['states'] as $state) {
                    $states[] = array(
                            'userid'=>$ctrl['userid'],
                            'ctrlid'=>$ctrl['id'],
                            'id'=>$state['id'],
                            'state'=>$state['state']
                    );
                }
            }
        }
        return $states;
    }

    public function info($ctrlid, $driverid) {
        $ctrlid = intval($ctrlid);

        $response = $this->ctrl->request($ctrlid, 'drivers/'.$driverid.'/infos/options', 'GET', array('filter' => 'device'));
        if (isset($response['success']) && $response['success'] == false) {
            return $response;
        }
        return $response['infos'];
    }

    public function get($ctrlid, $id) {
        $ctrlid = intval($ctrlid);
        
        $ctrl = $this->ctrl->get($ctrlid);
        $response = $this->ctrl->request($ctrlid, 'devices/'.urlencode($id), 'GET', array('details' => 'true'));
        if (isset($response['success']) && $response['success'] == false) {
            return $response;
        }
        return $this->get_device($ctrl, $response);
    }

    public function get_device($ctrl, $details) {
        $configs = $details['configs'];
        
        $device = array(
            'id'=>$details['id'],
            'userid'=>$ctrl['userid'],
            'ctrlid'=>$ctrl['id']
        );
        $driver = $details['driver'];
        $device['driverid'] = $driver['id'];
        $device['driver'] = isset($driver['name']) ? $driver['name'] : $driver['id'];
        
        if (isset($configs['description'])) {
            $device['description'] = $configs['description'];
            
            unset($configs['description']);
        }
        else {
            $device['description'] = '';
        }
        $device['state'] = $details['state'];
        
        if (isset($configs['deviceAddress'])) {
            $device['address'] = $configs['deviceAddress'];
            
            unset($configs['deviceAddress']);
        }
        else {
            $device['address'] = '';
        }
        if (isset($configs['settings'])) {
            $device['settings'] = $configs['settings'];
            
            unset($configs['settings']);
        }
        else {
            $device['settings'] = '';
        }
        
        $disabled = false;
        if (isset($configs['disabled'])) {
            $disabled = $configs['disabled'];
            
            unset($configs['disabled']);
        }
        $device['configs'] = $configs;
        
        $device['channels'] = isset($details['records']) ? $details['records'] : array();
        $device['disabled'] = $disabled;
        
        return $device;
    }

    private function parse_configs($id, $device) {
        $configs = array(
            'id' => $id
        );
        if (isset($device['description'])) $configs['description'] = $device['description'];
        if (isset($device['address'])) $configs['deviceAddress'] = $device['address'];
        if (isset($device['settings'])) $configs['settings'] = $device['settings'];
        if (isset($device['configs'])) $configs = array_merge($configs, $device['configs']);
        if (isset($device['disabled'])) $configs['disabled'] = $device['disabled'];
        
        return $configs;
    }

    public function update($ctrlid, $id, $device) {
        $ctrlid = intval($ctrlid);
        
        $device = (array) json_decode($device, true);
        
        $name = $device['id'];
        if (preg_replace('/[^\p{N}\p{L}\-\_\.\:\/\s]/u', '', $name) != $name) {
            return array('success'=>false, 'message'=>"Device key must only contain a-z A-Z 0-9 - _ . : and / characters");
        }
        
        $configs = $this->parse_configs($name, $device);
        
        $response = $this->ctrl->request($ctrlid, 'devices/'.urlencode($id).'/configs', 'PUT', array('configs' => $configs));
        if (isset($response['success']) && $response['success'] == false) {
            return $response;
        }
        return array('success'=>true, 'message'=>'Device successfully updated');
    }

    public function delete($ctrlid, $id) {
        $ctrlid = intval($ctrlid);
        
        $response = $this->ctrl->request($ctrlid, 'devices/'.urlencode($id), 'DELETE', null);
        if (isset($response['success']) && $response['success'] == false) {
            return $response;
        };
        return array('success'=>true, 'message'=>'Device successfully removed');
    }

    public function scan_start($ctrlid, $driverid, $settings) {
        $ctrlid = intval($ctrlid);
        
        // Check if the specified driver is registered already and add it, if necessary
        require_once "Modules/muc/Models/driver_model.php";
        $driver = new Driver($this->ctrl);
        
        $response = $driver->get_configured(null, $ctrlid);
        if (isset($response['success']) && $response['success'] == false) {
            return $response;
        }
        else if (!in_array($driverid, $response)) {
            $driver->create($ctrlid, $driverid, "{}");
        }
        
        $response = $this->ctrl->request($ctrlid, 'drivers/'.urlencode($driverid).'/scanStart', 'GET', array('settings' => $settings));
        if (isset($response['success']) && $response['success'] == false) {
            return $response;
        };
        return $this->parse_scan_progress($ctrlid, $driverid, $response);
    }

    public function scan_progress($ctrlid, $driverid) {
        $ctrlid = intval($ctrlid);

        $response = $this->ctrl->request($ctrlid, 'drivers/'.urlencode($driverid).'/scanProgress', 'GET', null);
        if (isset($response['success']) && $response['success'] == false) {
            return $response;
        }
        return $this->parse_scan_progress($ctrlid, $driverid, $response);
    }
    
    private function parse_scan_progress($ctrlid, $driverid, $response) {
        $meta = $response['scanProgressInfo'];
        if (isset($meta['scanError'])) {
            return array('success'=>false, 'message'=>$meta['scanError']);
        }
        $info = array(
            'finished'=>$meta['isScanFinished'],
            'interrupted'=>$meta['isScanInterrupted'],
            'progress'=>$meta['scanProgress']
        );
        
        $devices = array();
        foreach($response['devices'] as $scan) {
            
            $device = array(
                'ctrlid'=>$ctrlid,
                'driverid'=>$driverid,
                'id'=>$scan['id'],
                'description'=>'',
                'address'=>array(),
                'settings'=>array()
            );
            if (isset($scan['description'])) $device['description'] = $scan['description'];
            if (isset($scan['deviceAddress'])) $device['address'] = $scan['deviceAddress'];
            if (isset($scan['settings'])) $device['settings'] = $scan['settings'];
            
            $devices[] = $device;
        }
        return array('success'=>true, 'info'=>$info, 'devices'=>$devices);
    }

    public function scan_cancel($ctrlid, $driverid) {
        $ctrlid = intval($ctrlid);

        return $this->ctrl->request($ctrlid, 'drivers/'.$driverid.'/scanInterrupt', 'PUT', null);
    }

}
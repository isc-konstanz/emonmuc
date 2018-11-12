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

    public function create($ctrlid, $driverid, $configs) {
        $ctrlid = intval($ctrlid);
        
        $configs = (array) json_decode($configs);
        
        if (!ctype_alnum(str_replace(array('.', '_', '-'), '', $configs['id']))) {
            return array('success'=>false, 'message'=>_("Invalid characters in device key"));
        }
        $id = $configs['id'];
        
        if (!empty($configs['description'])) {
            if (!ctype_alnum(str_replace(array(' ', '.', '_', '-'), '', $configs['description']))) {
                return array('success'=>false, 'message'=>_("Invalid characters in device description"));
            }
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
        
        $device = $this->parse_device($id, $configs);
        $data = array(
            'driver' => $driverid,
            'configs' => $device
        );
        $response = $this->ctrl->request($ctrlid, 'devices/'.$configs['id'], 'POST', $data);
        if (isset($response['success']) && $response['success'] == false) {
            return $response;
        }
        $device['driver'] = $driverid;
        
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
            $response = $this->ctrl->request($ctrl['id'], 'devices/details', 'GET', null);
            if (isset($response["details"])) {
                foreach($response['details'] as $details) {
                    $devices[] = $this->get_device($ctrl, $details);
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
            if (isset($response["states"])) {
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

        $response = $this->ctrl->request($ctrlid, 'drivers/'.$driverid.'/infos/details/device', 'GET', null);
        if (isset($response['success']) && $response['success'] == false) {
            return $response;
        }
        return $response['infos'];
    }

    public function get($ctrlid, $id) {
        $ctrlid = intval($ctrlid);
        
        $ctrl = $this->ctrl->get($ctrlid);
        $response = $this->ctrl->request($ctrlid, 'devices/'.$id.'/details', 'GET', null);
        if (isset($response['success']) && $response['success'] == false) {
            return $response;
        }
        $details = (array) $response['details'];
        return $this->get_device($ctrl, $details);
    }

    public function get_device($ctrl, $details) {
        return array(
            'id'=>$details['id'],
            'userid'=>$ctrl['userid'],
            'ctrlid'=>$ctrl['id'],
            'driverid'=>$details['driver'],
            'driver'=>isset($details['driverName']) ? $details['driverName'] : $details['driver'],
            'description'=>!empty($details['description']) ? $details['description'] : '',
            'state'=>isset($details['state']) ? $details['state'] : null,
            'address'=>isset($details['deviceAddress']) ? $details['deviceAddress'] : '',
            'settings'=>isset($details['settings']) ? $details['settings'] : '',
            'configs'=>$this->get_configs($details),
            'channels'=>isset($details['channels']) ? $details['channels'] : array(),
            'disabled'=>isset($details['disabled']) ? $details['disabled'] : false
        );
    }

    private function parse_device($id, $configs) {
        $device = array('id' => $id);
        
        if (isset($configs['description'])) $device['description'] = $configs['description'];
        if (isset($configs['address'])) $device['deviceAddress'] = $configs['address'];
        if (isset($configs['settings'])) $device['settings'] = $configs['settings'];
        if (isset($configs['configs'])) {
            $details = (array) $configs['configs'];
            
            if (isset($details['samplingTimeout'])) $device['samplingTimeout'] = $details['samplingTimeout'];
            if (isset($details['connectRetryInterval'])) $device['connectRetryInterval'] = $details['connectRetryInterval'];
        }
        if (isset($configs['disabled'])) $device['disabled'] = $configs['disabled'];
        
        return $device;
    }

    private function get_configs($device) {
        $configs = array();
        foreach($device as $key => $value) {
            if (strcmp($key, 'ctrlid') !== 0 &&
                    strcmp($key, 'id') !== 0 &&
                    strcmp($key, 'description') !== 0 &&
                    strcmp($key, 'driver') !== 0 &&
                    strcmp($key, 'driverName') !== 0 &&
                    strcmp($key, 'deviceAddress') !== 0 &&
                    strcmp($key, 'settings') !== 0 &&
                    strcmp($key, 'state') !== 0 &&
                    strcmp($key, 'channels') !== 0 &&
                    strcmp($key, 'disabled') !== 0) {
                        
                        $configs[$key] = $value;
                    }
        }
        return $configs;
    }

    public function update($ctrlid, $id, $configs) {
        $ctrlid = intval($ctrlid);

        $configs = (array) json_decode($configs);
        
        if (!ctype_alnum(str_replace(array('.', '_', '-'), '', $configs['id']))) {
            return array('success'=>false, 'message'=>_("Invalid characters in device key"));
        }
        $name = $configs['id'];
        
        if (!empty($configs['description'])) {
            if (!ctype_alnum(str_replace(array(' ', '.', '_', '-'), '', $configs['description']))) {
                return array('success'=>false, 'message'=>_("Invalid characters in device description"));
            }
        }
        $device = $this->parse_device($name, $configs);
        
        $response = $this->ctrl->request($ctrlid, 'devices/'.$id.'/configs', 'PUT', array('configs' => $device));
        if (isset($response['success']) && $response['success'] == false) {
            return $response;
        }
        return array('success'=>true, 'message'=>'Device successfully updated');
    }

    public function delete($ctrlid, $id) {
        $ctrlid = intval($ctrlid);

        $response = $this->ctrl->request($ctrlid, 'devices/'.$id, 'DELETE', null);
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
        
        $response = $this->ctrl->request($ctrlid, 'drivers/'.$driverid.'/scanStart', 'GET', array('settings' => $settings));
        if (isset($response['success']) && $response['success'] == false) {
            return $response;
        };
        return $this->parse_scan_progress($ctrlid, $driverid, $response);
    }

    public function scan_progress($ctrlid, $driverid) {
        $ctrlid = intval($ctrlid);

        $response = $this->ctrl->request($ctrlid, 'drivers/'.$driverid.'/scanProgress', 'GET', null);
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
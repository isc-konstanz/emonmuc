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

abstract class ControllerDriver {
    protected $device = false;
    protected $ctrl;
    protected $log;

    public function __construct($ctrl) {
        $this->ctrl = $ctrl;
        $this->log = new EmonLogger(__FILE__);
    }

    public static function build($ctrl) {
        $type = strtolower($ctrl['type']);
        if ($type === 'redis') {
            throw new ControllerException("Redis controller communication not implemented yet");
        }
        elseif ($type === 'http' || $type === 'https') {
            require_once "Modules/muc/Lib/http/http_driver.php";
            return new HttpDriver($ctrl);
        }
        throw new ControllerException("Unknown controller type: $type");
    }

    protected function device(): ControllerDevice {
        if (!$this->device) {
            global $redis;
            require_once "Modules/muc/Lib/muc_device.php";
            $this->device = ControllerDevice::build($this->ctrl, $redis);
        }
        return $this->device;
    }

    public abstract function create($id, $driver);

    public abstract function get_list($depth=1);

    public abstract function get_registered();

    public abstract function get_unconfigured();

    public abstract function is_configured($id);

    public abstract function info($id);

    public abstract function get($id);

    public abstract function update($id, $details);

    public abstract function delete($id);

    protected function decode($details) {
        $driver = array(
            'userid'=>$this->ctrl['userid'],
            'ctrlid'=>$this->ctrl['id'],
            'ctrl'=>$this->ctrl['name'],
            'id'=>$details['id']
        );
        $driver['name'] = isset($details['name']) ? $details['name'] : ucfirst($details['id']);
        $driver['description'] = isset($details['description']) ? $details['description'] : '';
        
        $running = true;
        $disabled = false;
        if (isset($details['configs'])) {
            $configs = $details['configs'];
            if (count($configs) > 0) {
                if (isset($configs['disabled'])) {
                    $disabled = $configs['disabled'];
                    unset($configs['disabled']);
                }
                $driver['configs'] = $configs;
            }
            $running = $details['running'];
        }
        if (isset($details['devices'])) $driver['devices'] = $details['devices'];
        $driver['disabled'] = $disabled;
        $driver['running'] = $running;
        
        return $driver;
    }

    protected function encode($id, $configs) {
        $driver = array( 'id' => $id);
        
        if (isset($configs['configs'])) {
            $driverconfigs = (array) $configs['configs'];
            
            if (isset($driverconfigs['samplingTimeout'])) $driver['samplingTimeout'] = $driverconfigs['samplingTimeout'];
            if (isset($driverconfigs['connectRetryInterval'])) $driver['connectRetryInterval'] = $driverconfigs['connectRetryInterval'];
        }
        
        if (isset($configs['disabled'])) $driver['disabled'] = $configs['disabled'];
        
        return $driver;
    }
}
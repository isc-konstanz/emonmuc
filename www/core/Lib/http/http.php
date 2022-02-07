<?php
/*
     Released under the GNU Affero General Public License.
     See COPYRIGHT.txt and LICENSE.txt.

     MUC module contributed by Adrian Minde Adrian_Minde(at)live.de 2016
     ---------------------------------------------------------------------
     Sponsored by http://isc-konstanz.de/
*/

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

class Http {

    const DIR_DEFAULT = "/opt/emonmuc";

    private $ssl;
    private $url;
    public $user;
    public $password;

    private $log;

    public function __construct($ssl, $address, $port, $password) {
        $this->ssl = $ssl;
        $this->url = ($ssl ? 'https://' : 'http://' ).$address.':'.$port.'/rest/';
        $this->port = $port;
        $this->user = 'emoncms';
        $this->password = $password;
        
        $this->log = new EmonLogger(__FILE__);
    }

    public function get($action, $data=null) {
        return $this->request('GET', $action, $data);
    }

    public function put($action, $data=null) {
        return $this->request('PUT', $action, $data);
    }

    public function post($action, $data=null) {
        return $this->request('POST', $action, $data);
    }

    public function delete($action, $data=null) {
        return $this->request('DELETE', $action, $data);
    }

    private function request($method, $action, $data) {
        // $this->log->info('Sending request to "'.$this->url.'"');
        
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $this->url.$action,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_FAILONERROR => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            //TODO: This prevents curl from detecting man in the middle attacks. Implement SSL cert verification instead of unsafely disabling it
            //CURLOPT_CAINFO => "PATH_TO/cacert.pem");
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0
        ));
        
        if (isset($data)) {
            if ($method == 'GET') {
                $parameters = http_build_query($data);
                curl_setopt_array($ch, array(
                    CURLOPT_URL => $this->url.$action.'?'.$parameters,
                    CURLOPT_HTTPHEADER => array(
                        "Accept: application/json",
                        'Content-Type: application/json',
                        'Content-Length: 0')
                ));
            }
            else {
                $data_json = json_encode($data);
                curl_setopt_array($ch, array(
                    CURLOPT_POSTFIELDS => $data_json,
                    CURLOPT_HTTPHEADER => array(
                        "Accept: application/json",
                        'Content-Type: application/json',
                        'Content-Length: '.strlen($data_json))
                ));
            }
        }
        else {
            curl_setopt_array($ch, array(
                CURLOPT_HTTPHEADER => array(
                    "Accept: application/json",
                    'Content-Type: application/json',
                    'Content-Length: 0')
            ));
        }
        
        if (isset($this->user) && isset($this->password)) {
            curl_setopt($ch, CURLOPT_USERPWD, $this->user.":".$this->password);
        }
        $response = curl_exec($ch);
        
        $errno = curl_errno($ch);
        if ($errno) {
            if ($errno == 7) {
                $error = 'No Controller available for: '.$this->url;
            }
            else {
                $error = curl_error($ch);
            }
            curl_close($ch);
            
            throw new ControllerException($error);
        }
        // $info = curl_getinfo($ch);
        // $this->log->info('Received response after '.$info['total_time'].' seconds: '.$response);
        
        curl_close($ch);
        
        if (trim($response) === '') {
            return $response;
        }
        
        $result = json_decode($response, true);
        if (json_last_error() != 0) {
            $this->log->warn('Received invalid response: '.$response);
            throw new ControllerException("Invalid JSON response: ".json_last_error_msg());
        }
        return $result;
    }

    private function get_dir() {
        global $settings;
        if (isset($settings['muc']) && !empty($settings['muc']['root_dir'])) {
            $muc_dir = $settings['muc']['root_dir'];
        }
        else {
            $muc_dir = self::DIR_DEFAULT;
        }
        if (substr($muc_dir, -1) !== "/") {
            $muc_dir .= "/";
        }
        return $muc_dir;
    }
}

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

class Controller
{
    const DEFAULT_DIR = "/opt/emonmuc/";

    private $mysqli;
    private $redis;
    private $log;

    public function __construct($mysqli, $redis) {
        $this->mysqli = $mysqli;
        $this->redis = $redis;
        $this->log = new EmonLogger(__FILE__);
    }

    public function create($userid, $type, $address, $description) {
        $userid = intval($userid);
        $type = strtoupper($type);
        if ($type === 'MQTT') {
            return array('success'=>false, 'message'=>'MQTT controller communication not yet implemented');
        }
        elseif ($type !== 'HTTP' && $type !== 'HTTPS') {
            return array('success'=>false, 'message'=>'Unknown Controller communication method: '.$type);
        }
        
        if (!ctype_alnum(str_replace(array(' ', '.', '_', '-'), '', $description))) {
            return array('success'=>false, 'message'=>_("Invalid characters in device description"));
        }
        $password = md5(uniqid(mt_rand(), true));
        
        // Make sure, the defined address is valid
        if(substr_compare($address, '/', strlen($address)-1, 1) === 0) {
            $address = substr($address, 0, strlen($address)-1);
        }

        if (substr($address, 0, 7) === 'http://') {
            $address = substr($address, 7, strlen($address));
        }
        else if (substr($address, 0, 8) === 'https://') {
            $address = substr($address, 8, strlen($address));
        }
        
        $result = $this->mysqli->query("SELECT id, password FROM muc WHERE `address` = '$address'");
        if ($row = (array) $result->fetch_object()) {
            $id = $row['id'];
            $password = $row['password'];
        }
        else {
            $result = $this->mysqli->query("INSERT INTO muc (userid, type, address, description, password) VALUES ('$userid','$type','$address','$description','$password')");
            $id = $this->mysqli->insert_id;
            if ($id > 0) {
                if ($this->redis) {
                    $this->redis->sAdd("user:muc:$userid", $id);
                    $this->redis->hMSet("muc:$id",array(
                            'id'=>$id,
                            'userid'=>$userid,
                            'type'=>$type,
                            'address'=>$address,
                            'description'=>$description,
                            'password'=>$password));
                }
                else {
                    return array('success'=>false, 'message'=>'Foo');
                }
            }
            else {
                return array('success'=>false, 'message'=>'Unknown error while adding MUC');
            }
        }
        
        // Request the muc to register the user
        // TODO: Add ports to be configurable in settings
        $url = 'http://'.$address.':8080/rest/users';
        $data = array('id' => 'emoncms', 
                'password' => $password,
                'groups' => array(),
                'description' => 'Emoncms admin user'
        );
        
        $response = $this->sendHttpRequest('admin', 'admin', $url, 'POST', array('configs' => $data));
        if (isset($response['success']) && $response['success'] == false) {
            return $response;
        }
        
        // Try to delete default admin account if still existing
        $this->sendHttpRequest('admin', 'admin', $url, 'DELETE', array('configs' => array('id' => 'admin', 'password' => 'admin')));
        
        return array('success'=>true, 'id'=>$id, 'message'=>'MUC successfully registered');
    }

    public function exists($id) {
        $id = intval($id);
        
        static $ctrl_exists_cache = array(); // Array to hold the cache
        if (isset($ctrl_exists_cache[$id])) {
            $ctrl_exists = $ctrl_exists_cache[$id]; // Retrieve from static cache
        } else {
            $ctrl_exists = false;
            if ($this->redis) {
                if (!$this->redis->exists("muc:$id")) {
                    if ($this->load_redis_ctrl($id)) {
                        $ctrl_exists = true;
                    }
                } else {
                    $ctrl_exists = true;
                }
            } else {
                $result = $this->mysqli->query("SELECT id FROM muc WHERE id = '$id'");
                if ($result->num_rows>0) {
                    $ctrl_exists = true;
                }
            }
            // Cache it
            $ctrl_exists_cache[$id] = $ctrl_exists;
        }
        return $ctrl_exists;
    }

    public function get_list($userid) {
        if ($this->redis) {
            return $this->get_redis_list($userid);
        } else {
            return $this->get_mysql_list($userid);
        }
    }

    private function get_redis_list($userid) {
        $userid = intval($userid);
        
        if (!$this->redis->exists("user:muc:$userid")) $this->load_redis($userid);
        
        $ctrls = array();
        $ctrlids = $this->redis->sMembers("user:muc:$userid");
        foreach ($ctrlids as $id)
        {
            $row = $this->redis->hGetAll("muc:$id");
            $row['drivers'] = $this->get_driver_list($id);
            
            $ctrls[] = $row;
        }
        return $ctrls;
    }

    private function get_mysql_list($userid) {
        $userid = intval($userid);
        $ctrls = array();

        $result = $this->mysqli->query("SELECT id, userid, type, address, description, password FROM muc WHERE userid = '$userid'");
        while ($row = (array) $result->fetch_object())
        {
            $drivers = $this->get_driver_list($row['id']);
            $ctrl = array(
                'id'=>$row['id'],
                'userid'=>$row['userid'],
                'type'=>$row['type'],
                'address'=>$row['address'],
                'description'=>$row['description'],
                'password'=>$row['password'],
                'drivers'=>$drivers
            );
            
            $ctrls[] = $ctrl;
        }
        return $ctrls;
    }
    
    private function get_driver_list($id) {
        $id = intval($id);
        
        $response = $this->request($id, 'drivers', 'GET', null);
        if (isset($response['success']) && $response['success'] == false) {
            return $response;
        }
        return $response['drivers'];
    }

    public function get($id) {
        $id = intval($id);

        if ($this->redis) {
            if (!$this->redis->exists("muc:$id")) $this->load_redis_ctrl($id);
            return $this->redis->hGetAll("muc:$id");
        } else {
            $result = $this->mysqli->query("SELECT id, userid, type, address, description, password FROM muc WHERE id = '$id'");
            return (array) $result->fetch_object();
        }
    }

    public function set_fields($userid, $id, $fields) {
        $id = intval($id);

        $fields = json_decode(stripslashes($fields));
        $array = array();
        $data = array('id' => $id);

        // Repeat this line changing the field address to add fields that can be updated:
        if (isset($fields->type)) {
            $type = $fields->type;
            if ($type === 'MQTT') {
                return array('success'=>false, 'message'=>'MQTT controller communication not yet implemented');
            }
            elseif ($type !== 'HTTP' && $type !== 'HTTPS') {
                return array('success'=>false, 'message'=>'Unknown Controller communication method: '.$type);
            }
            
            $array[] = "`type` = '".$type."'";
        }
        if (isset($fields->address)) {
            $address = $fields->address;
            
            // Make sure, the defined address is valid
            if(substr_compare($address, '/', strlen($address)-1, 1) !== 0) {
                $address = $address."/";
            }
            $array[] = "`address` = '".$address."'";
        }
        if (isset($fields->description)) {
            $description = preg_replace('/[^\p{L}_\p{N}\s-:]/u','',$fields->description);
            
            $array[] = "`description` = '".$description."'";
        }
        if (isset($fields->password)) {
            $password = preg_replace('/[^\p{L}_\p{N}\s-:]/u','',$fields->password);
            $result = $this->mysqli->query("SELECT password FROM muc WHERE password='$password'");
            if ($result->num_rows > 0)
            {
                return array('success'=>false, 'message'=>'Field password is invalid'); // is duplicate
            }
            

            $ctrl = $this->get($id);
            $data['id'] = 'emoncms';
            $data['oldPassword'] = $ctrl['password'];
            $data['password'] = $fields->password;
            
            $array[] = "`password` = '".$password."'";
        }

        if (count($data) > 1) {
            $response = $this->request($id, 'users', 'PUT', array('configs' => $data));
            if (isset($response['success']) && $response['success'] == false) {
                return $response;
            }
        }

        // Convert to a comma seperated string for the mysql query
        $fieldstr = implode(",",$array);
        $this->mysqli->query("UPDATE muc SET ".$fieldstr." WHERE `id` = '$id'");

        if ($this->mysqli->affected_rows>0){
            // Update redis
            if ($this->redis) {
                if (isset($fields->type)) $this->redis->hset("muc:$id",'type',$type);
                if (isset($fields->address)) $this->redis->hset("muc:$id",'address',$address);
                if (isset($fields->description)) $this->redis->hset("muc:$id",'description',$description);
                if (isset($fields->password)) $this->redis->hset("muc:$id",'password',$password);
            }
            
            return array('success'=>true, 'message'=>'Fields updated');
        } else {
            return array('success'=>false, 'message'=>'Fields could not be updated');
        }
    }

    public function delete($userid, $id) {
        $id = intval($id);
        $userid = intval($userid);
        
        $data = array('id' => $id);
        $response = $this->request($id, 'users', 'DELETE', array('configs' => $data));
        if (isset($response['success']) && $response['success'] == false) {
            $this->log->warn("Controller model: User on Controller with id=$id was not deregistered, as the controller is not available.");
        }
        
        $this->mysqli->query("DELETE FROM muc WHERE `userid` = '$userid' AND `id` = '$id'");

        // Remove from redis
        if ($this->redis) {
            $this->redis->del("muc:$id");
            $this->redis->srem("user:muc:$userid",$id);
        }
        
        // Clear static cache
        if (isset($muc_exists_cache[$id])) { unset($muc_exists_cache[$id]); }
        
        return array('success'=>true, 'message'=>'Controller successfully removed');
    }

    public function request($id, $action, $type, $data) {
        $id = intval($id);

        $ctrl = $this->get($id);

        // TODO: Add ports to be configurable in settings
        if ($ctrl['type'] === 'HTTP'){
            $url = 'http://'.$ctrl['address'].':8080/rest/'.$action;
            return $this->sendHttpRequest('emoncms', $ctrl['password'], $url, $type, $data);
        }
        elseif ($ctrl['type'] === 'HTTPS') {
            $url = 'https://'.$ctrl['address'].':8443/rest/'.$action;
            return $this->sendHttpRequest('emoncms', $ctrl['password'], $url, $type, $data);
        }
        elseif ($ctrl['type'] === 'MQTT') {
            return array('success'=>false, 'message'=>'MQTT controller communication not yet implemented');
        }
        else {
            return array('success'=>false, 'message'=>'Unknown Controller communication method: '.$ctrl['type']);
        }
    }

    private function sendHttpRequest($username, $password, $url, $type, $data) {
//         $this->log->info('Sending request to "'.$url.'"');

        $ch = curl_init();
        curl_setopt_array($ch, array(
                CURLOPT_URL => $url,
                CURLOPT_CUSTOMREQUEST => $type,
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
            if ($type == 'GET') {
                $parameters = http_build_query($data);
                curl_setopt_array($ch, array(
                        CURLOPT_URL => $url.'?'.$parameters,
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
        
        if (isset($username) && isset($password)) {
            curl_setopt($ch, CURLOPT_USERPWD, $username.":".$password);
        }
        
        $response = curl_exec($ch);
        
        $errno = curl_errno($ch);
        if ($errno) {
            if ($errno == 7) {
                $error = 'No Controller available for: '.$url;
            }
            else {
                $error = curl_error($ch);
            }
            curl_close($ch);
            
            return array('success'=>false, 'message'=>$error);
        }
//         $info = curl_getinfo($ch);
//         $this->log->info('Received response after '.$info['total_time'].' seconds: '.$response);
        
        curl_close($ch);
        
        if (trim($response) === '') {
            return array('success'=>true);
        }
        
        $result = json_decode($response, true);
        if (!isset($result)) {
            return array('success'=>false, 'message'=>$response);
        }
        return $result;
    }

    private function load_redis($userid) {
        $this->redis->delete("user:muc:$userid");
        $result = $this->mysqli->query("SELECT id, userid, type, address, description, password FROM muc WHERE userid = '$userid'");
        while ($row = (array) $result->fetch_object())
        {
            $this->redis->sAdd("user:muc:$userid", $row['id']);
            $this->redis->hMSet("muc:".$row['id'],array(
                'id'=>$row['id'],
                'userid'=>$row['userid'],
                'type'=>$row['type'],
                'address'=>$row['address'],
                'description'=>$row['description'],
                'password'=>$row['password']
            ));
        }
    }

    private function load_redis_ctrl($id) {
        $result = $this->mysqli->query("SELECT id, userid, type, address, description, password FROM muc WHERE id = '$id'");
        $row = (array) $result->fetch_object();
        if (!$row) {
            $this->log->warn("MUC model: Requested MUC with id=$id does not exist");
            return false;
        }
            
        $this->redis->hMSet("muc:".$id,array(
                'id'=>$id,
                'userid'=>$row['userid'],
                'type'=>$row['type'],
                'address'=>$row['address'],
                'description'=>$row['description'],
                'password'=>$row['password']
        ));
    }
}
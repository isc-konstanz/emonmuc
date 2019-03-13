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

class Controller {
    private static $cache = array();

    private $mysqli;
    private $redis;
    private $log;

    public function __construct($mysqli, $redis) {
        $this->mysqli = $mysqli;
        $this->redis = $redis;
        $this->log = new EmonLogger(__FILE__);
    }

    public function driver($ctrl): ControllerDriver {
        require_once "Modules/muc/Lib/muc_driver.php";
        return ControllerDriver::build($ctrl);
    }

    public function device($ctrl): ControllerDevice {
        require_once "Modules/muc/Lib/muc_device.php";
        return ControllerDevice::build($ctrl, $this->redis);
    }

    public function channel($ctrl): ControllerChannel {
        require_once "Modules/muc/Lib/muc_channel.php";
        return ControllerChannel::build($ctrl, $this->mysqli, $this->redis);
    }

    public function load($ctrl) {
        $this->device($ctrl)->load();
        $this->channel($ctrl)->load();
        
        return array('success'=>true, 'message'=>'Controller cache reload successful');
    }

    public function create($userid, $type, $name, $description, $options) {
        $userid = intval($userid);
        
        if (preg_replace('/[^\p{N}\p{L}\-\_\.\:\/]/u', '', $name) != $name) {
            return array('success'=>false, 'message'=>"Controller name only contain a-z A-Z 0-9 - _ . : / and space characters");
        }
        if (empty($description)) {
            $description = '';
        }
        $options = json_decode($options, true);
        
        $type = strtolower($type);
        if ($type === 'redis') {
            return array('success'=>false, 'message'=>'Redis controller communication not implemented yet');
        }
        elseif ($type === 'http' || $type === 'https') {
            $this->create_http($type, $options);
        }
        else {
            return array('success'=>false, 'message'=>'Unknown controller type: '.$type);
        }
        $options = json_encode($options);
        
        $this->mysqli->query("INSERT INTO muc (userid, type, name, description, options) VALUES ('$userid','$type','$name','$description','$options')");
        $id = $this->mysqli->insert_id;
        if ($id > 0) {
            if ($this->redis) {
                $this->redis->sAdd("user:muc:$userid", $id);
                $this->redis->hMSet("muc:$id",array(
                    'id'=>$id,
                    'userid'=>$userid,
                    'type'=>$type,
                    'name'=>$name,
                    'description'=>$description,
                    'options'=>$options));
            }
        }
        else {
            return array('success'=>false, 'message'=>'Unknown error while adding MUC');
        }
        return array('success'=>true, 'id'=>$id, 'message'=>'MUC successfully registered');
    }

    private function create_http($type, &$options) {
        $password = md5(uniqid(mt_rand(), true));
        $options['password'] = $password;
        
        $http = $this->get_http($type, $options, 'admin');
        $http->user = 'admin';
        $http->password = 'admin';
        $data = array('id' => 'emoncms',
            'password' => $password,
            'groups' => array(),
            'description' => 'Emoncms admin user'
        );
        $http->post('users', array('configs' => $data));
        
        // Try to delete default admin account if still existing
        $http->delete('users', array('configs' => array('id'=>'admin')));
    }

    public function exists($id) {
        $id = intval($id);
        
        if (isset(self::$cache[$id])) {
            $exists = self::$cache[$id]; // Retrieve from static cache
        } else {
            $exists = false;
            if ($this->redis) {
                if (!$this->redis->exists("muc:$id")) {
                    if ($this->load_redis_ctrl($id)) {
                        $exists = true;
                    }
                } else {
                    $exists = true;
                }
            } else {
                $result = $this->mysqli->query("SELECT id FROM muc WHERE id = '$id'");
                if ($result->num_rows>0) {
                    $exists = true;
                }
            }
            // Cache it
            self::$cache[$id] = $exists;
        }
        return $exists;
    }

    public function get_all() {
        $ctrls = array();
        
        $result = $this->mysqli->query("SELECT id, userid, type, name, description, options FROM muc");
        while ($ctrl = (array) $result->fetch_object()) {
            $ctrls[] = $ctrl;
        }
        return $ctrls;
    }

    public function get_list($userid) {
        if ($this->redis) {
            $ctrls = $this->get_redis_list($userid);
        } else {
            $ctrls = $this->get_mysql_list($userid);
        }
        usort($ctrls, function($c1, $c2) {
            return strcmp($c1['name'], $c2['name']);
        });
        return $ctrls;
    }

    private function get_redis_list($userid) {
        $userid = intval($userid);
        
        if (!$this->redis->exists("user:muc:$userid")) $this->load_redis($userid);
        
        $ctrls = array();
        $ctrlids = $this->redis->sMembers("user:muc:$userid");
        foreach ($ctrlids as $id) {
            $ctrl = $this->redis->hGetAll("muc:$id");
            $ctrl['options'] = json_decode($ctrl['options'], true);
            try {
                $ctrl['drivers'] = $this->driver($ctrl)->get_list(0);
            }
            catch(ControllerException $e) {
                $ctrl['drivers'] = array();
                $this->log->warn($e->getResult());
            }
            $ctrls[] = $ctrl;
        }
        return $ctrls;
    }

    private function get_mysql_list($userid) {
        $userid = intval($userid);
        $ctrls = array();
        
        $result = $this->mysqli->query("SELECT id, userid, type, name, description, options FROM muc WHERE userid = '$userid'");
        while ($row = (array) $result->fetch_object()) {
            $ctrl = array(
                'id'=>$row['id'],
                'userid'=>$row['userid'],
                'type'=>$row['type'],
                'name'=>$row['name'],
                'description'=>$row['description'],
                'options'=>json_decode($row['options'], true)
            );
            try {
                $ctrl['drivers'] = $this->driver($ctrl)->get_list(0);
            }
            catch(ControllerException $e) {
                $ctrl['drivers'] = array();
                $this->log->warn($e->getResult());
            }
            $ctrls[] = $ctrl;
        }
        return $ctrls;
    }

    public function get($id) {
        $id = intval($id);

        if ($this->redis) {
            if (!$this->redis->exists("muc:$id")) $this->load_redis_ctrl($id);
            $ctrl = $this->redis->hGetAll("muc:$id");
        }
        else {
            $result = $this->mysqli->query("SELECT id, userid, type, name, description, options FROM muc WHERE id = '$id'");
            $ctrl = (array) $result->fetch_object();
        }
        $ctrl['options'] = json_decode($ctrl['options'], true);
        
        return $ctrl;
    }

    public function update($userid, $id, $fields) {
        $id = intval($id);
        $fields = json_decode(stripslashes($fields), true);
        $array = array();
        
        if (isset($fields['name'])) {
            $name = $fields['name'];
            
            if (preg_replace('/[^\p{N}\p{L}\-\_\.\:\/]/u', '', $name) != $name) {
                return array('success'=>false, 'message'=>"Controller name only contain a-z A-Z 0-9 - _ . : / and space characters");
            }
            $array[] = "`name` = '".$name."'";
        }
        if (isset($fields['description'])) {
            $array[] = "`description` = '".$fields['description']."'";
        }
        if (isset($fields['type'])) {
            if (isset($fields['options'])) {
                $options = $fields['options'];
                $array[] = "`options` = '".json_encode($options)."'";
            }
            else {
                $ctrl = $this->get($id);
                $options = $ctrl['options'];
            }
            
            $type = strtolower($fields['type']);
            if ($type === 'redis') {
                return array('success'=>false, 'message'=>'Redis controller communication not implemented yet');
            }
            elseif ($type === 'http' || $type === 'https') {
                $this->create_http($type, $options);
            }
            else {
                return array('success'=>false, 'message'=>'Unknown controller type: '.$type);
            }
            $array[] = "`type` = '".$type."'";
        }
        else if (isset($fields['options'])) {
            $ctrl = $this->get($id);
            $options = $fields['options'];
            $type = $ctrl['type'];
            if ($type === 'redis') {
                return array('success'=>false, 'message'=>'Redis controller communication not implemented yet');
            }
            elseif ($type === 'http' || $type === 'https') {
                $this->update_http($type, $ctrl['options'], $options);
            }
            else {
                return array('success'=>false, 'message'=>'Unknown controller type: '.$type);
            }
            $array[] = "`options` = '".json_encode($options)."'";
        }
        
        // Convert to a comma seperated string for the mysql query
        $fieldstr = implode(",",$array);
        $this->mysqli->query("UPDATE muc SET ".$fieldstr." WHERE `id` = '$id'");
        
        if ($this->mysqli->affected_rows<1) {
            return array('success'=>false, 'message'=>'Fields could not be updated');
        }
        if ($this->redis) {
            if (isset($fields['type'])) $this->redis->hset("muc:$id",'type',$type);
            if (isset($fields['name'])) $this->redis->hset("muc:$id",'name',$name);
            if (isset($fields['description'])) $this->redis->hset("muc:$id",'description',$fields['description']);
            if (isset($fields['options'])) $this->redis->hset("muc:$id",'options',json_encode($fields['options']));
        }
        return array('success'=>true, 'message'=>'Fields updated');
    }

    private function update_http($type, $options, &$update) {
        $http = $this->get_http($type, $options);
        
        if ($update['password'] != $options['password']) {
            $configs = array(
                'id' => 'emoncms',
                'oldPassword' => $options['password'],
                'password' => $update['password']
            );
            $http->put('users', array('configs' => $configs));
        }
    }

    public function delete($userid, $id) {
        $userid = intval($userid);
        $id = intval($id);
        $ctrl = $this->get($id);
        
        $type = $ctrl['type'];
        if ($type === 'redis') {
            return array('success'=>false, 'message'=>'Redis controller communication not implemented yet');
        }
        elseif ($type === 'http' || $type === 'https') {
            $this->delete_http($userid, $ctrl);
        }
        else {
            return array('success'=>false, 'message'=>'Unknown controller type: '.$type);
        }
        $this->mysqli->query("DELETE FROM muc WHERE `userid` = '$userid' AND `id` = '$id'");
        
        // Remove from redis
        if ($this->redis) {
            $this->redis->del("muc:$id");
            $this->redis->srem("user:muc:$userid",$id);
        }
        
        // Clear static cache
        if (isset(self::$cache[$id])) {
            unset(self::$cache[$id]);
        }
        return array('success'=>true, 'message'=>'Controller successfully removed');
    }

    private function delete_http($userid, $ctrl) {
        $http = $this->get_http($ctrl['type'], $ctrl['options']);
        
        $driver = $this->driver($ctrl);
        foreach ($driver->get_list($userid) as $d) {
            $driver->delete($d['id']);
        }
        $http->delete('users', array('configs'=>array('id'=>'emoncms')));
    }

    private function get_http($type, &$options) {
        if (empty($options['address'])) {
            throw new ControllerException("Server address needs to be configured");
        }
        $address = $options['address'];
        
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
        if (empty($options['port']) || !is_numeric($options['port'])) {
            throw new ControllerException("Server port invalid");
        }
        if (empty($options['password']) || strlen($options['password'])>32) {
            throw new ControllerException("Server password invalid");
        }
        
        require_once "Modules/muc/Lib/http/http.php";
        return new Http($type=='https', $address, $options['port'], $options['password']);
    }

    private function load_redis($userid) {
        $this->redis->delete("user:muc:$userid");
        $result = $this->mysqli->query("SELECT id, userid, type, name, description, options FROM muc WHERE userid = '$userid'");
        while ($row = (array) $result->fetch_object()) {
            $this->redis->sAdd("user:muc:$userid", $row['id']);
            $this->redis->hMSet("muc:".$row['id'],array(
                'id'=>$row['id'],
                'userid'=>$row['userid'],
                'type'=>$row['type'],
                'name'=>$row['name'],
                'description'=>$row['description'],
                'options'=>$row['options']
            ));
        }
    }

    private function load_redis_ctrl($id) {
        $result = $this->mysqli->query("SELECT id, userid, type, name, description, options FROM muc WHERE id = '$id'");
        $row = (array) $result->fetch_object();
        if (!$row) {
            $this->log->warn("MUC model: Requested MUC with id=$id does not exist");
            return false;
        }
        $this->redis->hMSet("muc:".$id,array(
            'id'=>$id,
            'userid'=>$row['userid'],
            'type'=>$row['type'],
            'name'=>$row['name'],
            'description'=>$row['description'],
            'options'=>$row['options']
        ));
    }
}

class ControllerException extends Exception {
    public function getResult() {
        return array('success'=>false, 'message'=>$this->getMessage());
    }
}

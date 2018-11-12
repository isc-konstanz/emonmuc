<?php

$schema['muc'] = array(
    'id' => array('type' => 'int(11)', 'Null'=>'NO', 'Key'=>'PRI', 'Extra'=>'auto_increment'),
    'userid' => array('type' => 'int(11)'),
    'type' => array('type' => 'text'),
    'address' => array('type' => 'text'),
    'description' => array('type' => 'text'),
    'password' => array('type' => 'varchar(64)')
);

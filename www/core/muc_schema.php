<?php

$schema['muc'] = array(
    'id' => array('type' => 'int(11)', 'Null'=>'NO', 'Key'=>'PRI', 'Extra'=>'auto_increment'),
    'userid' => array('type' => 'int(11)'),
    'type' => array('type' => 'varchar(32)'),
    'name' => array('type' => 'text'),
    'description' => array('type' => 'text'),
    'options' => array('type' => 'text')
);
